<?php
// admin/create_admin.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Invalid request method.']);
    exit;
}

// ---------- helpers ----------
function clean($s) {
    return trim(filter_var($s, FILTER_SANITIZE_STRING));
}

function random_password($length = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

// ---------- read inputs ----------
$full_name  = clean($_POST['full_name'] ?? '');
$address    = clean($_POST['address'] ?? '');
$contact    = preg_replace('/[^0-9+]/', '', $_POST['contact_number'] ?? '');
$email_raw  = trim($_POST['email_address'] ?? '');
$email      = filter_var($email_raw, FILTER_SANITIZE_EMAIL);

// basic validation
if ($full_name === '' || $email === '') {
    echo json_encode(['success' => false, 'msg' => 'Name and email are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid email address.']);
    exit;
}

// check kung existing na ang email
$stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE email_address = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

if ($cnt > 0) {
    echo json_encode(['success' => false, 'msg' => 'This email is already in use.']);
    exit;
}

// ---------- handle profile picture ----------
$profileFilename = 'default.png';

if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profile_pic'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'msg' => 'Error uploading profile picture.']);
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid image type. Use JPG, PNG, or WEBP.']);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) { // 3MB
        echo json_encode(['success' => false, 'msg' => 'Image is too large. Max 3MB.']);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $profileFilename = 'admin_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $profileFilename)) {
        echo json_encode(['success' => false, 'msg' => 'Failed to save uploaded image.']);
        exit;
    }
}

// ---------- generate temp password ----------
$tempPassword = random_password(10);
$passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

// ---------- insert new admin ----------
$stmt = $conn->prepare("
    INSERT INTO user (full_name, address, email_address, contact_number, password, profile_pic, role)
    VALUES (?, ?, ?, ?, ?, ?, 'admin')
");
$stmt->bind_param('ssssss', $full_name, $address, $email, $contact, $passwordHash, $profileFilename);

if ($stmt->execute()) {
    echo json_encode([
        'success'       => true,
        'msg'           => 'Admin created successfully.',
        'temp_password' => $tempPassword
    ]);
} else {
    echo json_encode([
        'success' => false,
        'msg'     => 'Database error: ' . $stmt->error
    ]);
}
$stmt->close();
