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

require_once '../db_connect.php';

// kunin lahat ng admin accounts
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
    /* ===== full width (similar sa All Bookings) ===== */
    .content-area.admins-fullbleed{
        padding-left: 0 !important;
        padding-right: 0 !important;
        gap: 0 !important;
    }
    .content-area.admins-fullbleed .admins-header,
    .content-area.admins-fullbleed .admin-table-container{
        width: 100%;
        margin: 0;
        padding: 0 12px;
        box-sizing: border-box;
    }

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
    .btn-add-admin i{ font-size: 0.95rem; }
    .btn-add-admin:hover{ background:#156c79; }

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
        background:#e7f7fc;
    }
    .admin-table .col-no{
        width: 70px;
        text-align: center;
    }
    @media (max-width: 700px){
        .admins-header .page-title{ font-size: 1.7rem; }
        .admin-table-container{ padding-left: 6px; padding-right: 6px; }
    }

    /* ===== Add Admin Modal ===== */
    body.modal-open{ overflow:hidden; }

    #add-admin-modal.custom-modal{
        position: fixed;
        inset: 0;
        z-index: 12000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,.45);
        padding: 16px;
    }
    #add-admin-modal.custom-modal.active{
        display:flex;
    }
    #add-admin-modal .modal-card{
        background:#fff;
        border-radius:22px;
        box-shadow:0 10px 30px rgba(0,0,0,.25);
        width:min(520px, 94vw);
        padding:26px 24px 20px;
        position:relative;
    }
    #add-admin-modal .modal-title{
        margin:0 0 14px;
        font-size:1.5rem;
        font-weight:700;
        color:#1698b4;
        text-align:left;
    }
    #add-admin-close{
        position:absolute;
        right:14px;
        top:10px;
        font-size:1.6rem;
        color:#1e8fa2;
        cursor:pointer;
        line-height:1;
    }
    #add-admin-close:hover{ color:#de3247; }

    .admin-form-grid{
        display:grid;
        grid-template-columns: 1fr;
        gap:10px;
    }
    .admin-field label{
        display:block;
        font-size:0.9rem;
        font-weight:600;
        color:#16687b;
        margin-bottom:4px;
    }
    .admin-field input[type="text"],
    .admin-field input[type="email"]{
        width:100%;
        border-radius:10px;
        border:1px solid #c8eafd;
        padding:9px 10px;
        font-size:0.96rem;
    }
    .admin-field input:focus{
        outline:none;
        border-color:#1e8fa2;
    }

    .photo-wrap{
        display:flex;
        align-items:center;
        gap:12px;
        margin-bottom:6px;
    }
    .photo-wrap img{
        width:64px;
        height:64px;
        border-radius:50%;
        object-fit:cover;
        box-shadow:0 1px 6px rgba(0,0,0,.15);
    }
    .photo-wrap small{
        display:block;
        font-size:0.8rem;
        color:#6a7f89;
    }

    .admin-modal-actions{
        margin-top:16px;
        display:flex;
        justify-content:flex-end;
        gap:10px;
    }
    .admin-modal-actions button{
        padding:9px 16px;
        border-radius:10px;
        border:none;
        font-weight:700;
        font-size:0.96rem;
        cursor:pointer;
    }
    #add-admin-cancel{
        background:#e5eef4;
        color:#445b66;
    }
    #add-admin-save{
        background:#1e8fa2;
        color:#fff;
        box-shadow:0 1px 6px #b9eafc55;
    }
    #add-admin-save:hover{
        background:#156c79;
    }
    </style>
</head>
<body>
    <?php $page = 'admins'; ?>
    <?php include 'includes/sidebar.php'; ?>

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
                        <td>-</td> <!-- wala pang created_at column -->
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

        <!-- ===== Add Admin Modal ===== -->
        <div id="add-admin-modal" class="custom-modal" aria-hidden="true">
            <div class="modal-card">
                <span id="add-admin-close">&times;</span>
                <h2 class="modal-title">Add Administrator</h2>

                <form id="add-admin-form" enctype="multipart/form-data" autocomplete="off">
                    <div class="admin-form-grid">
                        <div class="admin-field">
                            <label for="admin-full-name">Name</label>
                            <input type="text" id="admin-full-name" name="full_name" required>
                        </div>

                        <div class="admin-field">
                            <label for="admin-address">Address</label>
                            <input type="text" id="admin-address" name="address">
                        </div>

                        <div class="admin-field">
                            <label for="admin-contact">Contact Number</label>
                            <input type="text" id="admin-contact" name="contact_number"
                                   placeholder="e.g. 09123456789">
                        </div>

                        <div class="admin-field">
                            <label for="admin-email">Email Address</label>
                            <input type="email" id="admin-email" name="email_address" required>
                        </div>

                        <div class="admin-field">
                            <label>Profile Picture</label>
                            <div class="photo-wrap">
                                <img id="admin-photo-preview"
                                     src="https://ui-avatars.com/api/?name=AD&background=1e8fa2&color=fff&rounded=true"
                                     alt="Preview">
                                <div>
                                    <input type="file" id="admin-photo" name="profile_pic"
                                           accept="image/png, image/jpeg, image/webp">
                                    <small>Optional. Max 3MB.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-modal-actions">
                        <button type="button" id="add-admin-cancel">Cancel</button>
                        <button type="submit" id="add-admin-save">Save Admin</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addBtn   = document.getElementById('add-admin-btn');
    const modal    = document.getElementById('add-admin-modal');
    const closeX   = document.getElementById('add-admin-close');
    const cancelBtn= document.getElementById('add-admin-cancel');
    const form     = document.getElementById('add-admin-form');
    const fileInput= document.getElementById('admin-photo');
    const preview  = document.getElementById('admin-photo-preview');

    function openModal() {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
    function resetForm() {
        form.reset();
        preview.src = "https://ui-avatars.com/api/?name=AD&background=1e8fa2&color=fff&rounded=true";
    }
    function closeModal() {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        resetForm();
    }

    addBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    });
    closeX?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // preview image
    fileInput?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (!file.type.match(/^image\//)) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    // submit form
    form?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(form);

        Swal.fire({
            title: 'Saving admin...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('create_admin.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Admin Created',
                    html: 'New administrator has been created.<br><br>' +
                          '<b>Temporary password:</b> ' +
                          (data.temp_password || '(check email)'),
                    confirmButtonColor: '#1e8fa2'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: data.msg || 'Unable to create admin.',
                    confirmButtonColor: '#1e8fa2'
                });
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Please try again later.',
                confirmButtonColor: '#1e8fa2'
            });
        });
    });
});
</script>
</body>
</html>
