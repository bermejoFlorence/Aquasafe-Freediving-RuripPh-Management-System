<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require __DIR__.'/db_connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (method_exists($conn,'set_charset')) $conn->set_charset('utf8mb4');

session_start();

/* ---------- Helper to show SweetAlert then redirect ---------- */
function swal_and_redirect(string $icon, string $title, string $html, string $to){
  $icon=json_encode($icon); $title=json_encode($title); $html=json_encode($html); $to=json_encode($to);
  echo "<!doctype html><meta charset='utf-8'>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11' defer></script>
  <script>window.addEventListener('DOMContentLoaded',function(){
    Swal.fire({icon:$icon,title:$title,html:$html,confirmButtonColor:'#1e8fa2'})
      .then(function(){ location=$to; });
  });</script>";
  exit;
}

/* ---------- Guard ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  swal_and_redirect('error','Invalid request','POST only.','forgot_password.php');
}
if (!isset($_POST['csrf'], $_SESSION['fp_csrf']) || !hash_equals($_SESSION['fp_csrf'], $_POST['csrf'])) {
  swal_and_redirect('error','Security check failed','Invalid CSRF token.','forgot_password.php');
}

/* ---------- Input ---------- */
$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  swal_and_redirect('error','Invalid email','Please enter a valid email address.','forgot_password.php');
}

/* ---------- Check if email exists ---------- */
$stmt = $conn->prepare("SELECT user_id, full_name FROM `user` WHERE email_address=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($uid, $full_name);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
  // You asked to show an error when not registered
  swal_and_redirect('error','Email not registered','Please use an email that is registered in the system.','forgot_password.php');
}

/* ---------- Create token (invalidate older ones first) ---------- */
$conn->query("DELETE FROM password_reset WHERE email='".$conn->real_escape_string($email)."'");

$token = bin2hex(random_bytes(32)); // 64 chars

// Insert with MySQL time to avoid timezone drift
$stmt = $conn->prepare("
  INSERT INTO password_reset (email, token, expires_at)
  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
");
$stmt->bind_param('ss', $email, $token);
$stmt->execute();
$stmt->close();

/* ---------- Send email ---------- */
$reset_link = "https://divingrurip.com/reset_password.php?token=$token&email=".urlencode($email);
$subject    = "Reset your AquaSafe RuripPH password";
$safe_name  = htmlspecialchars($full_name ?: 'there', ENT_QUOTES);
$message    = "Hi {$safe_name},<br><br>
Click the link below to reset your password (valid for 15 minutes):<br>
<a href='$reset_link'>Reset Password</a><br><br>
If you didn’t request this, you can ignore this email.";

$mail = new PHPMailer(true);
try{
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'markson.carino@cbsua.edu.ph';     // <-- your Gmail
  $mail->Password   = 'wzzc jkhk bejh xqoe';          // <-- replace with App Password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // For Gmail deliverability, From should match Username
  $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
  $mail->addAddress($email, $full_name ?: $email);

  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $message;

  $mail->send();
  swal_and_redirect('success','Check your email','We sent a password reset link.','login.php');
}catch(Exception $e){
  swal_and_redirect('error','Could not send email','Please contact admin or try again later.','forgot_password.php');
}
