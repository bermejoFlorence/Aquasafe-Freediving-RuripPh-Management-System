<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
include 'db_connect.php'; // adjust path if needed

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get & sanitize POST data
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $full_name = $firstname . ' ' . $lastname;
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $role = "client";

    // PHP-side validation
    $errors = [];
    if (empty($firstname) || !preg_match('/^[a-zA-Z\s\-\'"]+$/', $firstname)) $errors[] = "Invalid first name.";
    if (empty($lastname) || !preg_match('/^[a-zA-Z\s\-\'"]+$/', $lastname)) $errors[] = "Invalid last name.";
    if (empty($address)) $errors[] = "Address is required.";
    if (!preg_match('/^\d{11,12}$/', $contact)) $errors[] = "Contact number must be 11 to 12 digits.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if ($password !== $cpassword) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // Check for duplicate email
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = "Email address already registered.";
    $stmt->close();

    if ($errors) {
        $error_list = "<ul style='text-align:left;'>";
        foreach($errors as $err) $error_list .= "<li>$err</li>";
        $error_list .= "</ul>";
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Registration</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Registration Error',
                html: `$error_list`,
                confirmButtonColor: '#1e8fa2'
            }).then(() => { window.location = 'register.php'; });
        </script>
        </body>
        </html>
        ";
        $conn->close();
        exit;
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $is_verified = 0;
    $token = bin2hex(random_bytes(32)); // Secure random token

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO user (full_name, address, email_address, contact_number, password, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssis", $full_name, $address, $email, $contact, $password_hash, $role, $is_verified, $token);

    if ($stmt->execute()) {
        // Send verification email using PHPMailer
        $verify_link = "https://divingrurip.com/verify_email.php?token=$token&email=" . urlencode($email);
        $subject = "Verify your AquaSafe RuripPH account";
        $message = "Hi $full_name,<br><br>
            Please confirm your email address by clicking the link below:<br>
            <a href='$verify_link'>Verify Email</a><br><br>
            If you did not register, please ignore this email.<br><br>
            <b>Thank you!</b>";

        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP
            $mail->SMTPAuth   = true;
            $mail->Username   = 'markson.carino@cbsua.edu.ph'; // Your Gmail
            $mail->Password   = 'wzzc jkhk bejh xqoe';   // Your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            //Recipients
            $mail->setFrom('marksoncarino@cbsua.edu.ph', 'AquaSafe RuripPH');
            $mail->addAddress($email, $full_name);

            //Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
            $sentMsg = "Please check your email to verify your account before logging in.";
        } catch (Exception $e) {
            $sentMsg = "Registration successful, but failed to send verification email. Please contact admin.<br><small>Error: {$mail->ErrorInfo}</small>";
        }

        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Registration</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Registration Successful!',
                html: '" . addslashes($sentMsg) . "',
                confirmButtonColor: '#1e8fa2'
            }).then(() => { window.location = 'login.php'; });
        </script>
        </body>
        </html>
        ";
    } else {
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Registration</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: 'Please try again.',
                confirmButtonColor: '#1e8fa2'
            }).then(() => { window.location = 'register.php'; });
        </script>
        </body>
        </html>
        ";
    }
    $stmt->close();
    $conn->close();
}
?>
