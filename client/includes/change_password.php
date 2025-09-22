<?php
session_start();
require_once '../../db_connect.php'; // ayusin path kung iba

function flash_and_redirect($type, $message, $title = 'Change Password') {
    $_SESSION['flash'] = [
        'type' => $type,   // 'success' | 'error' | 'warning' | 'info'
        'title' => $title,
        'message' => $message,
    ];
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_and_redirect('error', 'Invalid request method.');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    flash_and_redirect('error', 'Not authenticated.');
}

$current = trim($_POST['current_password'] ?? '');
$new     = trim($_POST['new_password'] ?? '');
$confirm = trim($_POST['confirm_password'] ?? '');

// Min length = 6 (as requested)
$MIN_LEN = 6;

if ($new !== $confirm) {
    flash_and_redirect('error', 'New passwords do not match.');
}
if (strlen($new) < $MIN_LEN) {
    flash_and_redirect('error', "Password must be at least {$MIN_LEN} characters.");
}

// Get current hashed password
$stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($db_password);
$hasRow = $stmt->fetch();
$stmt->close();

if (!$hasRow || !$db_password) {
    flash_and_redirect('error', 'Account not found.');
}

// Verify current password
if (!password_verify($current, $db_password)) {
    flash_and_redirect('error', 'Incorrect current password.');
}

// Disallow reusing the same password
if (password_verify($new, $db_password)) {
    flash_and_redirect('error', 'New password must be different from the current password.');
}

// Hash + update
$new_hashed = password_hash($new, PASSWORD_DEFAULT);
$up = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
$up->bind_param('si', $new_hashed, $user_id);

if ($up->execute()) {
    $up->close();
    flash_and_redirect('success', 'Password changed successfully.', 'Success');
} else {
    $up->close();
    flash_and_redirect('error', 'Error changing password. Please try again.');
}
