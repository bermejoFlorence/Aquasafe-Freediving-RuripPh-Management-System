<?php
// contact_submit.php
header('Content-Type: application/json');

session_start();

// quick JSON responder
function respond($ok, $msg, $errors = []) {
  echo json_encode(['ok' => $ok, 'msg' => $msg, 'errors' => $errors], JSON_UNESCAPED_SLASHES);
  exit;
}

// CSRF check
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  respond(false, 'Session expired. Please refresh the page and try again.', ['csrf' => 'invalid']);
}

// Honeypot (bots fill this)
if (!empty($_POST['website'] ?? '')) {
  // pretend OK to avoid tipping bots
  respond(true, 'Thank you! Your message has been received.');
}

// Collect + basic validation
$name  = trim($_POST['name']    ?? '');
$phone = trim($_POST['phone']   ?? '');
$email = trim($_POST['email']   ?? '');
$body  = trim($_POST['message'] ?? '');

$errors = [];

// Required fields
if ($name === '')  $errors['name']    = 'Required';
if ($email === '') $errors['email']   = 'Required';
if ($body === '')  $errors['message'] = 'Required';

// Email format
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors['email'] = 'Invalid email';
}

// Length guards (avoid overly long payloads)
if (mb_strlen($name)  > 150)   $errors['name']    = 'Too long';
if (mb_strlen($email) > 250)   $errors['email']   = 'Too long';
if (mb_strlen($phone) > 30)    $errors['phone']   = 'Too long';
if (mb_strlen($body)  > 5000)  $errors['message'] = 'Too long';

if ($errors) {
  respond(false, 'Please check the highlighted fields.', $errors);
}

// Insert
require __DIR__ . '/db_connect.php'; // adjust path if needed

$stmt = $conn->prepare(
  "INSERT INTO contact_message (full_name, email_address, contact_number, body)
   VALUES (?, ?, ?, ?)"
);

if (!$stmt) {
  respond(false, 'Server error. Please try again later.');
}

$stmt->bind_param("ssss", $name, $email, $phone, $body);

if ($stmt->execute()) {
  $stmt->close();
  respond(true, 'Thanks! Your message was sent successfully.');
} else {
  $stmt->close();
  respond(false, 'Unable to send right now. Please try again later.');
}
