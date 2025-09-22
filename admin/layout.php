<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<!DOCTYPE html>
    <html><head>
    <meta charset='UTF-8'>
    <title>Access Denied</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head><body>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You do not have permission to access this page.',
        confirmButtonColor: '#1e8fa2'
    }).then(() => { window.location = '../login.php'; });
    </script>
    </body></html>";
    exit;
}

// --- Fetch Total Clients (users with role 'client') ---
include '../db_connect.php'; // adjust path as needed
$total_clients = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS total_clients FROM user WHERE role = 'client'");
$stmt->execute();
$stmt->bind_result($total_clients);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aquasafe RuripPh Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles/style.css">

</head>
<body>
    <?php $page = 'packages'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="content-area">
        <?php include 'includes/header.php'; ?>
        <div class="dashboard-main"> 
    
        </div>


    </main>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger-btn');
    const overlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = "hidden";
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = "";
    }

    // Hamburger toggles open/close
    if (hamburger) {
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }
    // Overlay click closes sidebar
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    // Close sidebar on sidebar-nav link click (mobile only)
    document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 700) closeSidebar();
        });
    });
    // ESC key closes sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape" && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    // Auto-close sidebar if window resized to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 700) closeSidebar();
    });

});

</script>
</body>
</html>
