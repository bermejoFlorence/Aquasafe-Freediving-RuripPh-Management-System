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

function swal_and_redirect($icon,$title,$html,$to){
  $icon=json_encode($icon);$title=json_encode($title);$html=json_encode($html);$to=json_encode($to);
  echo "<!doctype html><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11' defer></script>
  <script>window.addEventListener('DOMContentLoaded',()=>{Swal.fire({icon:$icon,title:$title,html:$html,confirmButtonColor:'#1e8fa2'}).then(()=>{location=$to;});});</script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') swal_and_redirect('error','Invalid','POST only.','forgot_password.php');
if (!isset($_POST['csrf'], $_SESSION['fp_csrf']) || !hash_equals($_SESSION['fp_csrf'], $_POST['csrf'])) {
  swal_and_redirect('error','Security check failed','Invalid CSRF token.','forgot_password.php');
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  swal_and_redirect('error','Invalid email','Please enter a valid email address.','forgot_password.php');
}

// check if email exists
$stmt=$conn->prepare("SELECT user_id, full_name FROM `user` WHERE email_address=?");
$stmt->bind_param('s',$email); $stmt->execute(); $stmt->bind_result($uid,$full_name);
$found = $stmt->fetch(); $stmt->close();

if (!$found) {
  // For privacy, still show success message
  swal_and_redirect('success','Check your email','If that email is registered, a reset link has been sent.','login.php');
}

// create token (invalidate previous)
$conn->query("DELETE FROM password_reset WHERE email='".$conn->real_escape_string($email)."'");

$token = bin2hex(random_bytes(32)); // 64 chars
$expires = date('Y-m-d H:i:s', time()+15*60); // 15 minutes
$stmt=$conn->prepare("INSERT INTO password_reset (email, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('sss',$email,$token,$expires); $stmt->execute(); $stmt->close();

$reset_link = "https://divingrurip.com/reset_password.php?token=$token&email=".urlencode($email);
$subject = "Reset your AquaSafe RuripPH password";
$message = "Hi ".htmlspecialchars($full_name ?? 'there',ENT_QUOTES).",<br><br>
Click the link below to reset your password (valid for 15 minutes):<br>
<a href='$reset_link'>Reset Password</a><br><br>
If you didn’t request this, you can ignore this email.";

// send mail
$mail = new PHPMailer(true);
try{
  $mail->isSMTP();
  $mail->Host='smtp.gmail.com';
  $mail->SMTPAuth=true;
  $mail->Username='markson.carino@cbsua.edu.ph';       // <- use your account
  $mail->Password='wzzc jkhk bejh xqoe';            // <- app password (rotate!)
  $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port=587;

  $mail->setFrom('markson.carino@cbsua.edu.ph','AquaSafe RuripPH'); // match Username
  $mail->addAddress($email, $full_name ?: $email);
  $mail->isHTML(true);
  $mail->Subject=$subject;
  $mail->Body=$message;
  $mail->send();

  swal_and_redirect('success','Check your email','We sent a password reset link.','login.php');
}catch(Exception $e){
  swal_and_redirect('error','Cannot send email','Please contact admin.','forgot_password.php');
}
