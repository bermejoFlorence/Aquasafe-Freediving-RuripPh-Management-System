<?php
// admin/create_admin.php
session_start();
header('Content-Type: application/json');

// Only admins
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

// Mas malinaw na DB error (habang dev)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ========== PHPMailer ==========
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// *** IMPORTANT ***
// Gamitin ang KAPAREHONG path na gamit mo sa registration file:
// root: PHPMailer/...
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

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

function json_error($msg, $extra = []) {
    echo json_encode(array_merge(['success' => false, 'msg' => $msg], $extra));
    exit;
}

// ---------- method check ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.');
}

// ---------- read inputs ----------
$full_name  = clean($_POST['full_name'] ?? '');
$address    = clean($_POST['address'] ?? '');
$contact    = preg_replace('/[^0-9+]/', '', $_POST['contact_number'] ?? '');
$email_raw  = trim($_POST['email_address'] ?? '');
$email      = filter_var($email_raw, FILTER_SANITIZE_EMAIL);

// basic validation
if ($full_name === '' || $email === '') {
    json_error('Name and email are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email address.');
}

// ---------- check duplicate email ----------
$stmt = $conn->prepare("SELECT COUNT(*) FROM user WHERE email_address = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

if ($cnt > 0) {
    json_error('This email is already in use.');
}

// ---------- handle profile picture ----------
$profileFilename = 'default.png';

if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profile_pic'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('Error uploading profile picture.');
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        json_error('Invalid image type. Use JPG, PNG, or WEBP.');
    }

    if ($file['size'] > 3 * 1024 * 1024) { // 3MB
        json_error('Image is too large. Max 3MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $profileFilename = 'admin_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $profileFilename)) {
        json_error('Failed to save uploaded image.');
    }
}

// ---------- generate temp password + verification token ----------
$tempPassword  = random_password(10);
$passwordHash  = password_hash($tempPassword, PASSWORD_DEFAULT);
$isVerified    = 0;
$token         = bin2hex(random_bytes(32)); // 64-char token

// ---------- insert new admin ----------
// NOTE: sinama na natin: role, account_status, is_verified, verification_token, is_banned, created_at
$stmt = $conn->prepare("
    INSERT INTO user (
        full_name,
        address,
        email_address,
        contact_number,
        password,
        profile_pic,
        role,
        account_status,
        is_verified,
        verification_token,
        is_banned,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', ?, ?, 0, NOW())
");

$stmt->bind_param(
    'ssssssis',
    $full_name,
    $address,
    $email,
    $contact,
    $passwordHash,
    $profileFilename,
    $isVerified,
    $token
);

try {
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    json_error('Database error while creating admin.', ['debug' => $e->getMessage()]);
}

// ========== send verification email ==========
$verify_link = "https://divingrurip.com/verify_email.php?token={$token}&email=" . urlencode($email);

$subject = 'Verify your AquaSafe RuripPH Admin account';
$message = "
    Hi {$full_name},<br><br>
    An administrator account has been created for you in <b>AquaSafe RuripPH</b>.<br><br>
    <b>Temporary password:</b> {$tempPassword}<br><br>
    Before you can log in, please verify your email address by clicking the link below:<br>
    <a href='{$verify_link}' target='_blank'>Verify Email</a><br><br>
    Once verified, you may log in using your email and the temporary password above.
    We recommend changing your password after logging in.<br><br>
    Thank you!
";

$mail = new PHPMailer(true);

try {
    // SMTP config (palitan ng actual mo)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'markson.carino@cbsua.edu.ph';      // TODO: palitan
    $mail->Password   = 'wzzc jkhk bejh xqoe';         // TODO: palitan (Gmail App Password)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
    $mail->addAddress($email, $full_name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;

    $mail->send();

    echo json_encode([
        'success'       => true,
        'msg'           => 'Admin created and verification email sent.',
        'temp_password' => $tempPassword
    ]);
} catch (Exception $e) {
    // Account created na, email lang ang pumalya
    echo json_encode([
        'success'       => true,
        'msg'           => 'Admin created, but failed to send verification email. Please check mail configuration.',
        'temp_password' => $tempPassword,
        'mail_error'    => $mail->ErrorInfo
    ]);
}
