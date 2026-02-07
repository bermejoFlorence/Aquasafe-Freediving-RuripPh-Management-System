<?php
// login_action.php
session_start();
date_default_timezone_set('Asia/Manila');

include 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php'); exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Pull everything we need (incl. ban fields + session_version)
$stmt = $conn->prepare("
  SELECT user_id, full_name, email_address, address, profile_pic, password, role, contact_number,
         is_verified,
         account_status, banned_until, banned_reason, session_version, is_banned, banned_at, banned_by
  FROM user
  WHERE email_address = ?
  LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

echo "<!DOCTYPE html>
<html><head>
  <meta charset='UTF-8'>
  <title>Login</title>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head><body>";

/* ---------- Auth check ---------- */
if (!$user || !password_verify($password, $user['password'])) {
  echo "<script>
    Swal.fire({icon:'error',title:'Login Failed!',text:'Incorrect email or password.',confirmButtonColor:'#1e8fa2'})
      .then(()=>{ window.location='login.php'; });
  </script></body></html>";
  $conn->close(); exit;
}
/* ---------- Email verification gate (ALL ROLES) ---------- */
if ((int)($user['is_verified'] ?? 0) !== 1) {
  echo "<script>
    Swal.fire({
      icon: 'error',
      title: 'Email Not Verified',
      html: 'Please verify your email address first.<br>Check your inbox for the verification link.',
      confirmButtonColor: '#1e8fa2'
    }).then(()=>{ window.location='login.php'; });
  </script></body></html>";
  $conn->close(); exit;
}
/* ---------- Ban/Suspend gates (CLIENT only) ---------- */
if (($user['role'] ?? '') === 'client') {
  $status = $user['account_status'] ?? 'active';
  $until  = $user['banned_until'] ?? null;
  $iBanned = (int)($user['is_banned'] ?? 0);

  $kick = function(string $htmlMsg) {
    echo "<script>
      Swal.fire({icon:'error',title:'Login blocked',html:".json_encode($htmlMsg).",confirmButtonColor:'#1e8fa2'})
        .then(()=>{ window.location='login.php'; });
    </script></body></html>";
  };

  // Suspended → always block
  if ($status === 'suspended') {
    $kick("Your account is currently <b>suspended</b>. Please contact the administrator.");
    $conn->close(); exit;
  }

  // Treat as banned if status is 'banned' OR legacy is_banned=1
  $isStatusBanned = ($status === 'banned');
  $stillBanned = $isStatusBanned || $iBanned
                 ? (empty($until) || strtotime($until) > time())
                 : false;

  if ( ($isStatusBanned || $iBanned) && !$stillBanned && !empty($until) && strtotime($until) <= time()) {
    // Ban expired → auto-unban now
    $uid = (int)$user['user_id'];
    $up = $conn->prepare("
      UPDATE user
         SET account_status='active',
             banned_until=NULL,
             banned_reason=NULL,
             banned_by=NULL,
             banned_at=NULL,
             is_banned=0
       WHERE user_id=?
       LIMIT 1
    ");
    $up->bind_param("i", $uid);
    $up->execute();
    $up->close();
    // continue to login…
  } elseif ($stillBanned) {
    $reason = trim($user['banned_reason'] ?? '');
    $msg  = "Your account is currently <b>banned</b>.";
    if ($reason !== '') { $msg .= "<br><b>Reason:</b> ".htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); }
    $msg .= "<br><b>Until:</b> ".(!empty($until) ? date('M j, Y g:ia', strtotime($until)) : 'indefinite');
    $kick($msg);
    $conn->close(); exit;
  }
}

/* ---------- Set session ---------- */
$_SESSION['user_id']         = $user['user_id'];
$_SESSION['full_name']       = $user['full_name'];
$_SESSION['email_address']   = $user['email_address'];
$_SESSION['address']         = $user['address'] ?? '';
$_SESSION['contact_number']  = $user['contact_number'] ?? '';
$_SESSION['profile_pic']     = $user['profile_pic'] ?? 'default.png';
$_SESSION['role']            = $user['role'];
$_SESSION['session_version'] = (int)($user['session_version'] ?? 0);

/* ---------- Success redirects ---------- */
if ($user['role'] === 'admin') {
  echo "<script>
    Swal.fire({icon:'success',title:'Welcome, Admin!',html:'Login successful.<br>Redirecting to admin dashboard...',confirmButtonColor:'#1e8fa2',timer:1500,showConfirmButton:false})
      .then(()=>{ window.location='admin/index.php'; });
    setTimeout(()=>{ window.location='admin/index.php'; },1700);
  </script>";
} else {
  echo "<script>
    Swal.fire({icon:'success',title:'Welcome!',html:'Login successful.<br>Redirecting to your dashboard...',confirmButtonColor:'#1e8fa2',timer:1500,showConfirmButton:false})
      .then(()=>{ window.location='client/index.php'; });
    setTimeout(()=>{ window.location='client/index.php'; },1700);
  </script>";
}

echo "</body></html>";
$conn->close();
