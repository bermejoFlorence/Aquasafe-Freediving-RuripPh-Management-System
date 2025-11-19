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

include '../db_connect.php';

// DEBUG habang nag-aayos (optional, pwede mong alisin kapag ok na)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kunin lahat ng admin users
$sqlAdmins = "
  SELECT user_id, full_name, email_address, created_at
FROM user
WHERE role = 'admin'
ORDER BY created_at DESC;

";
$resultAdmins = $conn->query($sqlAdmins);
if (!$resultAdmins) {
    die('Query error: ' . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Administrators - Aquasafe RuripPh</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles/style.css">

    <style>
        /* ===== Administrators page styling (similar sa All Bookings) ===== */

        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 18px 0 16px;
        }
        .page-header-left h1 {
            margin: 0;
            font-size: 2.1rem;
            font-weight: 800;
            color: #0f7c90;
        }
        .page-header-left p {
            margin: 4px 0 0;
            color: #5a7c87;
            font-size: 0.95rem;
        }

        .page-header-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-add-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 999px;
            border: none;
            background: #1e8fa2;
            color: #fff;
            font-weight: 700;
            font-size: 0.98rem;
            text-decoration: none;
            box-shadow: 0 2px 8px #1e8fa233;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-add-admin:hover {
            background: #187284;
            color: #fff;
        }

        .admins-table-container {
            width: 100%;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 2px 12px #1e8fa214;
            overflow-x: auto;
        }

        .admins-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }
        .admins-table thead th {
            background: #0f8ca0;
            color: #fff;
            padding: 14px 16px;
            font-weight: 700;
            font-size: 0.98rem;
            text-align: left;
        }
        .admins-table thead th:first-child {
            border-top-left-radius: 18px;
        }
        .admins-table thead th:last-child {
            border-top-right-radius: 18px;
        }

        .admins-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #e3f3f8;
            font-size: 0.96rem;
            color: #144f61;
        }
        .admins-table tbody tr:nth-child(even) td {
            background-color: #f9fdff;
        }
        .admins-table tbody tr:last-child td {
            border-bottom: none;
        }
        .admins-table tbody tr:hover td {
            background-color: #e9f7fc;
        }

        .admins-table .col-no {
            width: 60px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .page-header-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .page-header-right {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php $page = 'admins'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="content-area">
        <?php include 'includes/header.php'; ?>

        <div class="dashboard-main">

            <!-- ===== Top header row (title + Add button) ===== -->
            <div class="page-header-row">
                <div class="page-header-left">
                    <h1>Administrators</h1>
                    <p>Manage accounts that have full access to the Aquasafe RuripPh admin panel.</p>
                </div>
                <div class="page-header-right">
                    <!-- TODO: palitan ang href kung may sarili kang create-admin page -->
                    <a href="admin_add.php" class="btn-add-admin">
                        <i class="fa-solid fa-user-plus"></i>
                        Add Admin
                    </a>
                </div>
            </div>

            <!-- ===== Admins table ===== -->
            <div class="admins-table-container">
                <table class="admins-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                   <tbody>
                        <?php
                        $no = 1;
                        if ($resultAdmins->num_rows > 0):
                            while ($row = $resultAdmins->fetch_assoc()):
                        ?>
                            <tr>
                                <td class="col-no"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email_address']); ?></td>
                              <td>
  <?php echo $row['created_at']
         ? date('F j, Y', strtotime($row['created_at']))
         : '-'; ?>
</td>

                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:16px;color:#8aa0aa;">
                                    No admin accounts found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar   = document.getElementById('sidebar');
        const hamburger = document.getElementById('hamburger-btn');
        const overlay   = document.getElementById('sidebar-overlay');

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
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
        document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 700) closeSidebar();
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === "Escape" && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 700) closeSidebar();
        });
    });
    </script>
</body>
</html>
