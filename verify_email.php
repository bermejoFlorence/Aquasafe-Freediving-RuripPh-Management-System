<?php
include 'db_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get token and email from URL
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// SweetAlert JS include
$sweetalert = "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

if ($token && $email) {
    // Look up user with matching email, token, and not yet verified
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE email_address=? AND verification_token=? AND is_verified=0");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Update as verified
        $update = $conn->prepare("UPDATE user SET is_verified=1, verification_token=NULL WHERE email_address=?");
        $update->bind_param("s", $email);
        $update->execute();

        echo "<!DOCTYPE html>
        <html><head>
            <meta charset='UTF-8'>
            <title>Email Verified</title>
            $sweetalert
        </head><body>
        <script>
        Swal.fire({
            icon: 'success',
            title: 'Email Verified!',
            text: 'Your account is now active. You may now login.',
            confirmButtonColor: '#1e8fa2'
        }).then(() => { window.location = 'login.php'; });
        </script>
        </body></html>";
    } else {
        // Invalid/used/expired token
        echo "<!DOCTYPE html>
        <html><head>
            <meta charset='UTF-8'>
            <title>Invalid Link</title>
            $sweetalert
        </head><body>
        <script>
        Swal.fire({
            icon: 'error',
            title: 'Invalid or Expired Link',
            text: 'The verification link is invalid or your account is already verified.',
            confirmButtonColor: '#1e8fa2'
        }).then(() => { window.location = 'login.php'; });
        </script>
        </body></html>";
    }
    $stmt->close();
    $conn->close();
} else {
    // No token/email in URL, just redirect
    header("Location: login.php");
    exit;
}
?>
