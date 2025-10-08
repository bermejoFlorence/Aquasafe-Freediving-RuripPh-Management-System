<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require __DIR__.'/db_connect.php'; // adjust path if needed

// Strict errors + UTF-8
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (method_exists($conn,'set_charset')) { $conn->set_charset('utf8mb4'); }

// CSRF
session_start();
if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>Swal.fire({icon:"error",title:"Security check failed",text:"Invalid CSRF token."}).then(()=>history.back());</script>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Helpers
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    // Normalize PH contact to 11-digit starting with 09
    // Accepts: 09xxxxxxxxx, +639xxxxxxxxx, 639xxxxxxxxx, 9xxxxxxxxx
    function normalize_ph_contact(string $raw): string {
        $d = preg_replace('/\D+/', '', $raw);          // keep digits only
        if (strpos($d, '639') === 0 && strlen($d) === 12) return '0'.substr($d, 2); // 639xx.. -> 09..
        if (strpos($d, '9') === 0  && strlen($d) === 10)  return '0'.$d;            // 9xx..   -> 09..
        if (strpos($d, '09') === 0 && strlen($d) === 11)  return $d;                // already ok
        return $d; // fallback (still validated below)
    }

    // Get & sanitize
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $full_name = trim($firstname . ' ' . $lastname);
    $address   = trim($_POST['address']   ?? '');
    $contact   = normalize_ph_contact(trim($_POST['contact'] ?? ''));   // <<< contact normalization
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']  ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $role      = "client";
    $is_verified = 0;

    // Validation
    $errors = [];
    if ($firstname === '' || !preg_match('/^[a-zA-Z\s\-\'`]{1,30}$/', $firstname)) $errors[] = "Invalid first name.";
    if ($lastname  === '' || !preg_match('/^[a-zA-Z\s\-\'`]{1,30}$/', $lastname))  $errors[] = "Invalid last name.";
    if ($address   === '')                                                         $errors[] = "Address is required.";
    if (!preg_match('/^09\d{9}$/', $contact))                                      $errors[] = "Contact number must be PH format (11 digits starting with 09).";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                                 $errors[] = "Invalid email address.";
    if ($password !== $cpassword)                                                   $errors[] = "Passwords do not match.";
    if (strlen($password) < 8)                                                      $errors[] = "Password must be at least 8 characters.";

    // Duplicate checks (email + contact)
    $stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN email_address=? THEN 1 ELSE 0 END) AS e,
        SUM(CASE WHEN contact_number=? THEN 1 ELSE 0 END) AS c
      FROM `user`");
    $stmt->bind_param("ss", $email, $contact);
    $stmt->execute();
    $stmt->bind_result($dupEmail, $dupContact);
    $stmt->fetch();
    $stmt->close();
    if ($dupEmail)   $errors[] = "Email address already registered.";
    if ($dupContact) $errors[] = "Contact number already used.";

    if ($errors) {
        $error_list = "<ul style='text-align:left;margin:0;padding-left:1.1em'>";
        foreach($errors as $err) $error_list .= "<li>".h($err)."</li>";
        $error_list .= "</ul>";
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Registration</title>
              <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
              <script>
                Swal.fire({icon:'error',title:'Registration Error',html:`$error_list`,confirmButtonColor:'#1e8fa2'})
                .then(()=>{ window.location = 'register.php'; });
              </script></body></html>";
        exit;
    }

    // Hash + token
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32)); // 64 chars fits varchar(100)

    // INSERT (explicit columns + correct order)
    $stmt = $conn->prepare("INSERT INTO `user`
      (full_name, address, email_address, contact_number, `password`, role, is_verified, verification_token)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssis", $full_name, $address, $email, $contact, $password_hash, $role, $is_verified, $token);
    $stmt->execute();
    $stmt->close();

    // Clear CSRF after success
    unset($_SESSION['csrf']);

    // Email verification
    $verify_link = "https://divingrurip.com/verify_email.php?token=$token&email=" . urlencode($email);
    $subject = "Verify your AquaSafe RuripPH account";
    $message = "Hi ".h($full_name).",<br><br>Please confirm your email address:<br>
                <a href='$verify_link'>Verify Email</a><br><br>If you did not register, please ignore this email.<br><br><b>Thank you!</b>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // TODO: move to env + use a real App Password
        $mail->Username   = 'markson.carino@cbsua.edu.ph';
        $mail->Password   = 'YOUR_APP_PASSWORD_HERE';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // From should match Username for Gmail deliverability
        $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
        $mail->addAddress($email, $full_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        $sentMsg = "Please check your email to verify your account before logging in.";
    } catch (Exception $e) {
        $sentMsg = "Registration successful, but failed to send verification email. Please contact admin.<br><small>Error: ".h($mail->ErrorInfo)."</small>";
    }

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Registration</title>
          <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
          <script>
            Swal.fire({icon:'success',title:'Registration Successful!',html:'".h($sentMsg)."',confirmButtonColor:'#1e8fa2'})
            .then(()=>{ window.location = 'login.php'; });
          </script></body></html>";
}
