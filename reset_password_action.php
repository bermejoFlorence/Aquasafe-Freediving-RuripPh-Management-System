<?php
require __DIR__.'/db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (method_exists($conn,'set_charset')) $conn->set_charset('utf8mb4');

function swal_and_redirect($icon,$title,$html,$to){
  $icon=json_encode($icon);$title=json_encode($title);$html=json_encode($html);$to=json_encode($to);
  echo "<!doctype html><meta charset='utf-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11' defer></script>
  <script>window.addEventListener('DOMContentLoaded',()=>{Swal.fire({icon:$icon,title:$title,html:$html,confirmButtonColor:'#1e8fa2'}).then(()=>{location=$to;});});</script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD']!=='POST') swal_and_redirect('error','Invalid','POST only.','login.php');

$token = $_POST['token'] ?? '';
$email = $_POST['email'] ?? '';
$pw    = $_POST['password'] ?? '';
$cpw   = $_POST['cpassword'] ?? '';

if (!$token || !$email) swal_and_redirect('error','Invalid link','Missing token/email.','forgot_password.php');
if (strlen($pw) < 8)    swal_and_redirect('error','Weak password','Minimum 8 characters.','javascript:history.back()');
if ($pw !== $cpw)       swal_and_redirect('error','Mismatch','Passwords do not match.','javascript:history.back()');

// check token
$stmt=$conn->prepare("SELECT id FROM password_reset WHERE email=? AND token=? AND used=0 AND expires_at > NOW()");
$stmt->bind_param('ss',$email,$token); $stmt->execute(); $stmt->bind_result($rid);
if(!$stmt->fetch()){ $stmt->close(); swal_and_redirect('error','Invalid or expired','Please request a new link.','forgot_password.php'); }
$stmt->close();

// update user password
$hash = password_hash($pw, PASSWORD_DEFAULT);
$stmt=$conn->prepare("UPDATE `user` SET `password`=? WHERE email_address=?");
$stmt->bind_param('ss',$hash,$email); $stmt->execute(); $stmt->close();

// mark token used
$stmt=$conn->prepare("UPDATE password_reset SET used=1 WHERE id=?");
$stmt->bind_param('i',$rid); $stmt->execute(); $stmt->close();

swal_and_redirect('success','Password updated','You can now log in with your new password.','login.php');
