<?php
session_start();
include 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Fetch ALL user fields you need for the session (including profile_pic, address)
    $stmt = $conn->prepare("SELECT user_id, full_name, email_address, address, profile_pic, password, role, contact_number FROM user WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Login</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>";

    if ($user && password_verify($password, $user['password'])) {
        // Save ALL needed fields to session for use in header.php and profile
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email_address'] = $user['email_address'];
        $_SESSION['address'] = $user['address'] ?? '';
        $_SESSION['contact_number'] = $user['contact_number'] ?? '';
        $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'default.png';
        $_SESSION['role'] = $user['role'];

        // SweetAlert + redirect based on role
        if ($user['role'] === 'admin') {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome, Admin!',
                    html: 'Login successful.<br>Redirecting to admin dashboard...',
                    confirmButtonColor: '#1e8fa2',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => { window.location = 'admin/index.php'; });
                setTimeout(() => { window.location = 'admin/index.php'; }, 1700);
            </script>";
        } else if ($user['role'] === 'client') {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome!',
                    html: 'Login successful.<br>Redirecting to your dashboard...',
                    confirmButtonColor: '#1e8fa2',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => { window.location = 'client/index.php'; });
                setTimeout(() => { window.location = 'client/index.php'; }, 1700);
            </script>";
        } else {
            // fallback for other roles
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome!',
                    html: 'Login successful.<br>Redirecting...',
                    confirmButtonColor: '#1e8fa2',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => { window.location = 'index.php'; });
                setTimeout(() => { window.location = 'index.php'; }, 1700);
            </script>";
        }
    } else {
        // Login Failed
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed!',
                text: 'Incorrect email or password.',
                confirmButtonColor: '#1e8fa2'
            }).then(() => { window.location = 'login.php'; });
        </script>";
    }

    echo "</body></html>";
    $conn->close();
}
?>
