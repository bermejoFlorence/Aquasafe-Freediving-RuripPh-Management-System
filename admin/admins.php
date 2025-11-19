<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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

// DEBUG habang inaayos (optional, pwede mo rin tanggalin)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kunin lahat ng admin accounts
$sqlAdmins = "
    SELECT user_id, full_name, email_address
    FROM user
    WHERE role = 'admin'
    ORDER BY user_id DESC
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
    /* === FULL WIDTH LAYOUT (same idea as All Bookings) === */
    .content-area.admins-fullbleed{
        padding-left: 0 !important;
        padding-right: 0 !important;
        gap: 0 !important;
    }
    .content-area.admins-fullbleed .admins-header,
    .content-area.admins-fullbleed .admin-table-container{
        width: 100%;
        margin: 0;
        padding: 0 12px;          /* maliit lang na gutter sa left/right */
        box-sizing: border-box;
    }

    /* Header row: title + button */
    .admins-header{
        margin-top: 24px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        row-gap: 10px;
    }
    .admins-header .page-title{
        font-size: 2rem;
        font-weight: 700;
        color: #1e8fa2;
        margin: 0;
    }
    .btn-add-admin{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 999px;
        border: none;
        background: #1e8fa2;
        color: #fff;
        font-size: 0.98rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 2px 8px #b9eafc80;
        white-space: nowrap;
    }
    .btn-add-admin i{
        font-size: 0.95rem;
    }
    .btn-add-admin:hover{
        background: #156c79;
    }

    /* Table wrapper */
    .admin-table-container{
        margin-bottom: 18px;
        overflow-x: auto;
    }

    .admin-table{
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .admin-table thead th{
        background: #1e8fa2;
        color: #fff;
        padding: 14px 12px;
        font-weight: 700;
        font-size: 0.98rem;
        text-align: left;
        border: none;
    }
    .admin-table tbody td{
        background: #ffffffea;
        padding: 12px 12px;
        font-size: 0.97rem;
        color: #186070;
        border-bottom: 2px solid #e3f6fc;
    }
    .admin-table tbody tr:last-child td{
        border-bottom: none;
    }
    .admin-table tbody tr:hover td{
        background: #e7f7fc;
    }
    .admin-table .col-no{
        width: 70px;
        text-align: center;
    }

    @media (max-width: 700px){
        .admins-header{
            align-items: flex-start;
        }
        .admins-header .page-title{
            font-size: 1.7rem;
        }
        .admin-table-container{
            padding-left: 6px;
            padding-right: 6px;
        }
    }
    </style>
</head>
<body>
    <?php $page = 'admins'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- NOTE: dagdagan natin ng admins-fullbleed class para mag full width -->
    <main class="content-area admins-fullbleed">
        <?php include 'includes/header.php'; ?>

        <div class="admins-header">
            <h1 class="page-title">Administrators</h1>
            <button type="button" class="btn-add-admin" id="add-admin-btn">
                <i class="fa-solid fa-user-plus"></i>
                Add Admin
            </button>
        </div>

        <div class="admin-table-container">
            <table class="admin-table">
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
                        <td>-</td> <!-- wala pa tayong created_at column sa table -->
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
    </main>

    <script>
    // simple handler: palitan mo na lang ng tamang page (e.g. create_admin.php)
    document.getElementById('add-admin-btn')?.addEventListener('click', () => {
        // TODO: gawa ka ng page/form para mag-add ng admin
        // For now, redirect or show alert:
        // window.location = 'create_admin.php';
        Swal.fire({
            icon: 'info',
            title: 'Add Admin',
            text: 'Hook this button to your admin-creation form.',
            confirmButtonColor: '#1e8fa2'
        });
    });
    </script>
</body>
</html>
