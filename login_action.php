<?php
session_start();
include 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Pull all fields we need (including ban fields + session_version)
    $stmt = $conn->prepare("
        SELECT user_id, full_name, email_address, address, profile_pic, password, role, contact_number,
               account_status, banned_until, banned_reason, session_version
        FROM user
        WHERE email_address = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    echo "<!DOCTYPE html>
    <html><head>
        <meta charset='UTF-8'>
        <title>Login</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head><body>";

    // Basic auth check
    if (!$user || !password_verify($password, $user['password'])) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed!',
                text: 'Incorrect email or password.',
                confirmButtonColor: '#1e8fa2'
            }).then(()=>{ window.location='login.php'; });
        </script></body></html>";
        $conn->close();
        exit;
    }

    // ---- BAN / SUSPEND GATES ----
    $status = $user['account_status'] ?? 'active';
    $until  = $user['banned_until'] ?? null;

    // Helper to show a message then kick back to login
    $kick = function(string $htmlMsg) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Login blocked',
                html: ".json_encode($htmlMsg).",
                confirmButtonColor: '#1e8fa2'
            }).then(()=>{ window.location='login.php'; });
        </script></body></html>";
    };

    // If suspended (manual hold) → always block
    if ($status === 'suspended') {
        $msg = "Your account is currently <b>suspended</b>. Please contact the administrator.";
        $kick($msg);
        $conn->close();
        exit;
    }

    // If banned → block if still active; auto-unban if expired
    if ($status === 'banned') {
        $stillBanned = ($until === null || $until === '' || strtotime($until) > time());
        if ($stillBanned) {
            $reason = trim($user['banned_reason'] ?? '');
            $msg  = "Your account is currently <b>banned</b>.";
            if ($reason !== '') $msg .= "<br><b>Reason:</b> ".htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            if ($until) {
                $msg .= "<br><b>Until:</b> ".date('M j, Y g:ia', strtotime($until));
            } else {
                $msg .= "<br><b>Until:</b> indefinite";
            }
            $kick($msg);
            $conn->close();
            exit;
        } else {
            // Auto-unban (ban expired)
            $uid = (int)$user['user_id'];
            $conn->query("
                UPDATE user
                   SET account_status='active',
                       banned_until=NULL,
                       banned_reason=NULL,
                       banned_by=NULL,
                       banned_at=NULL
                 WHERE user_id={$uid}
            ");
            // keep going to login
        }
    }

    // ---- SET SESSION (include session_version) ----
    $_SESSION['user_id']         = $user['user_id'];
    $_SESSION['full_name']       = $user['full_name'];
    $_SESSION['email_address']   = $user['email_address'];
    $_SESSION['address']         = $user['address'] ?? '';
    $_SESSION['contact_number']  = $user['contact_number'] ?? '';
    $_SESSION['profile_pic']     = $user['profile_pic'] ?? 'default.png';
    $_SESSION['role']            = $user['role'];
    $_SESSION['session_version'] = (int)($user['session_version'] ?? 0);

    // ---- SUCCESS REDIRECTS ----
    if ($user['role'] === 'admin') {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Welcome, Admin!',
                html: 'Login successful.<br>Redirecting to admin dashboard...',
                confirmButtonColor: '#1e8fa2',
                timer: 1500, showConfirmButton: false
            }).then(()=>{ window.location='admin/index.php'; });
            setTimeout(()=>{ window.location='admin/index.php'; },1700);
        </script>";
    } else { // client (or other)
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Welcome!',
                html: 'Login successful.<br>Redirecting to your dashboard...',
                confirmButtonColor: '#1e8fa2',
                timer: 1500, showConfirmButton: false
            }).then(()=>{ window.location='client/index.php'; });
            setTimeout(()=>{ window.location='client/index.php'; },1700);
        </script>";
    }

    echo "</body></html>";
    $conn->close();
}
?>
