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
$sql = "
SELECT 
    p.package_id, 
    p.name AS package_name, 
    p.price,
    GROUP_CONCAT(f.feature ORDER BY f.feature_id SEPARATOR '||') AS features
FROM package p
LEFT JOIN package_feature f ON p.package_id = f.package_id
GROUP BY p.package_id
ORDER BY p.package_id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aquasafe RuripPh Admin Packages</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles/style.css">
<style>
/* ========== Main Wrapper for Spacing ========== */
.packages-wrapper {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding-left: 18px;
    padding-right: 18px;
    box-sizing: border-box;
}

/* ========== Packages Header ========== */
.packages-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 28px 0 16px 0;
    width: 100%;
    box-sizing: border-box;
}
.packages-title {
    color: #1e8fa2;
    font-size: 1.7rem;
    font-weight: bold;
    letter-spacing: 0.01em;
}
.add-package-btn {
    background: #1e8fa2;
    color: #fff;
    padding: 10px 28px;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    font-size: 1.15rem;
    cursor: pointer;
    box-shadow: 0 2px 8px #b9eafc33;
    transition: background .18s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.add-package-btn i { font-size: 1.1em; }
.add-package-btn:hover { background: #156c79; }

/* ========== Table ========== */
.packages-table-container {
    width: 100%;
    background: transparent;
    border-radius: 16px;
    overflow-x: auto;
    box-shadow: none;
    box-sizing: border-box;
}
.packages-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: transparent;
    border-radius: 16px;
    overflow: hidden;
}
.packages-table th, .packages-table td {
    text-align: center !important;
}
.packages-table th {
    background: #1e8fa2;
    color: #fff;
    padding: 14px 10px;
    font-weight: 700;
    font-size: 1em;
    border: none;
}
.packages-table td {
    padding: 12px 10px;
    background: #ffffffea;
    color: #186070;
    font-size: 0.98em;
    border-bottom: 2px solid #e3f6fc;
    vertical-align: middle;
}
.packages-table tbody tr:last-child td {
    border-bottom: none;
}
.packages-table tr {
    transition: background 0.18s;
}
.packages-table tr:hover td {
    background: #e7f7fc !important;
}
.features-list {
    text-align: left;
    display: inline-block;
    padding-left: 12px;
}
.features-list li {
    margin-bottom: 3px;
    color: #186070;
    font-size: 0.96em;
}

/* ========== Buttons ========== */
.action-btn {
    padding: 7px 14px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.97em;
    cursor: pointer;
    transition: background 0.18s, color 0.18s;
    margin-right: 8px;
    outline: none;
}
.edit-btn {
    background: #e3fbf3;
    color: #20b57a !important;
    border: 1.5px solid #b8f2d3;
}
.edit-btn:hover, .edit-btn:focus {
    background: #c6f5e2;
    color: #108657 !important;
}
.delete-btn {
    background: #ffe3e6;
    color: #e34b4b !important;
    border: 1.5px solid #f9c6cc;
}
.delete-btn:hover, .delete-btn:focus {
    background: #ffd1d6;
    color: #c92a2a !important;
}

/* ========== Modal Styles ========== */
.custom-modal {
    position: fixed;
    z-index: 1001;
    left: 0; top: 0;
    width: 100vw; height: 100vh;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(24, 96, 112, 0.10);
    transition: background 0.3s;
}
.custom-modal.active {
    display: flex;
    animation: fadeInBg 0.3s;
}
@keyframes fadeInBg { from {background: rgba(24,96,112,0);} to {background: rgba(24,96,112,0.10);} }

.package-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 32px 22px 24px 22px;
    min-width: 410px;      /* from 310px */
    max-width: 480px;      /* or 95vw, whichever is smaller */
    width: 100%;
    box-shadow: 0 8px 36px 0 #0d34424c;
    position: relative;
    animation: modalShow 0.37s cubic-bezier(0.36,1.33,0.4,0.9);
}
@keyframes modalShow {
    from { transform: scale(0.89) translateY(50px); opacity:0; }
    to   { transform: scale(1) translateY(0); opacity:1; }
}
.package-modal-content .close-btn {
    position: absolute;
    right: 17px; top: 15px;
    font-size: 1.4em;
    color: #1e8fa2;
    cursor: pointer;
    font-weight: bold;
    transition: color .18s;
}
.package-modal-content .close-btn:hover { color: #de3247; }
.package-modal-content .modal-title {
    font-size: 1.45em;    /* adjust as you like */
    color: #1e8fa2;
    margin-bottom: 16px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.01em;
}
.package-modal-content .modal-group { margin-bottom: 16px; }
.package-modal-content .modal-group label { font-weight: 600; color: #16687b; display: block; margin-bottom: 6px;}
.package-modal-content .modal-group input[type="number"], 
.package-modal-content .modal-group input[type="text"] {
    width: 92%; padding: 8px; font-size: 1em; border: 1.5px solid #c8eafd; border-radius: 8px;
    transition: border .15s;
    padding-right: 0px;
}
.package-modal-content .modal-group input:focus { border-color: #1e8fa2; outline: none;}
.package-modal-content .modal-submit-btn {
    width: 100%; padding: 10px 0; background: #1e8fa2; color: #fff;
    border: none; border-radius: 9px; font-size: 1.03em; font-weight: 600;
    margin-top: 6px; cursor: pointer;
    transition: background .19s, box-shadow .16s;
    box-shadow: 0 2px 8px #b9eafc33;
}
.package-modal-content .modal-submit-btn:hover { background: #156c79; }

/* ========== RESPONSIVE ========== */
@media (max-width: 800px) {
    .packages-table th, .packages-table td {
        padding: 8px 5px;
        font-size: 0.92em;
    }
}
@media (max-width: 700px) {
    .packages-wrapper {
    padding-left: 10px !important;
    padding-right: 50px !important;
    /* Optional: para makita mo talaga yung area */
    /* background: rgba(255,0,0,0.03); */
  }
  
  .packages-header {
        flex-direction: row !important;
        gap: 7px;
        align-items: center;
        justify-content: space-between;
        margin-top: 20px;
    }
    .add-package-btn, .packages-title {
        font-size: 1.03rem;
        width: auto !important;
        justify-content: flex-start;
    }
    .packages-table-container {
        padding: 0;
    }
}
@media (max-width: 600px) {
    .packages-wrapper {
        padding-left: 6px !important;
    padding-right: 6px !important;
  }
    .packages-table-container {
        font-size: 0.97em;
        padding: 0;
        box-sizing: border-box;
    }
    .packages-table { border: none; background: transparent; }
    .packages-table thead { display: none; }
    .packages-table, .packages-table tbody, .packages-table tr, .packages-table td {
        display: block; width: 100%;
    }
    .packages-table tr {
        background: #fafdff;
        border-radius: 22px;
        border: 1.5px solid #d3f0fa;
        margin-bottom: 22px;
        box-shadow: 0 2px 8px 0 #b9eafc60;
        padding: 12px 0 10px 0;
        overflow: hidden;
        position: relative;
    }
    .packages-table td {
        border: none;
        background: transparent;
        text-align: left !important;
        padding: 6px 12px 6px 12px;
        position: relative;
        font-size: 1em;
        min-height: 26px;
        margin-bottom: 0;
    }
    .packages-table td:before {
        content: attr(data-label);
        display: inline-block;
        min-width: 108px;
        color: #1e8fa2;
        font-weight: bold;
        font-size: 1em;
        margin-right: 10px;
        margin-bottom: 3px;
        vertical-align: top;
        width: auto;
        position: static;
        white-space: normal;
    }
    .packages-table td[data-label="Action"] {
        display: flex;
        gap: 7px;
        justify-content: flex-start;
        align-items: center;
        padding-top: 13px;
        padding-bottom: 0;
        flex-wrap: wrap;
    }
    .action-btn, .edit-btn, .delete-btn {
        display: inline-flex;
        align-items: center;
        width: auto !important;
        min-width: 0 !important;
        max-width: 100%;
        margin: 0;
        font-size: 0.93em;
        padding: 5px 10px;
        white-space: nowrap;
        flex-shrink: 1;
    }
}
#add-features-list input[type="text"] {
    margin-right: 14px;
    margin-bottom: 9px;
    width: 95%;
    box-sizing: border-box;
}
/* ==== Packages: edge-to-edge, like Payments/Users ==== */
.content-area.packages-fullbleed{
  padding-left: 0 !important;
  padding-right: 0 !important;
  gap: 0 !important;
}

/* wrapper stretches full width */
.content-area.packages-fullbleed .packages-wrapper{
  width: 100% !important;
  max-width: none !important;
  margin: 0 !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* inner blocks also full width */
.content-area.packages-fullbleed .packages-header,
.content-area.packages-fullbleed .packages-table-container{
  width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 5px !important;
  padding-right: 5px !important;
}

/* table spans the viewport */
.content-area.packages-fullbleed .packages-table{
  width: 100% !important;
  min-width: 0 !important;
  border-spacing: 0;
}

/* comfortable cell padding even when flush to the edge */
.content-area.packages-fullbleed .packages-table th,
.content-area.packages-fullbleed .packages-table td{
  padding-left: 12px;
  padding-right: 12px;
}

</style>
</head>
<body>
    <?php $page = 'packages'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="content-area packages-fullbleed">
        <?php include 'includes/header.php'; ?>
        <div class="packages-wrapper">
            <div class="packages-header">
                <span class="packages-title">Packages</span>
                <button class="add-package-btn" id="open-add-modal"><i class="fa fa-plus"></i> Add Package</button>
            </div>
            <div class="packages-table-container">
                <table class="packages-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Package Name</th>
                            <th>Price (₱)</th>
                            <th>Features</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $row_number = 1; while($row = $result->fetch_assoc()): ?>
                            <tr data-package-id="<?= $row['package_id'] ?>">
                                <td data-label="#"> <?= $row_number ?> </td>
                                <td data-label="Package Name"><?= htmlspecialchars($row['package_name']) ?></td>
                                <td data-label="Price (₱)"><?= number_format($row['price'], 2) ?></td>
                                <td data-label="Features">
                                    <?php
                                    if ($row['features']) {
                                        $features = explode('||', $row['features']);
                                        echo "<ul class='features-list'>";
                                        foreach ($features as $feature) {
                                            echo "<li>" . htmlspecialchars($feature) . "</li>";
                                        }
                                        echo "</ul>";
                                    } else {
                                        echo "<span style='color:#aaa;'>No features listed.</span>";
                                    }
                                    ?>
                                </td>
                                <td data-label="Action">
                                    <button class="action-btn edit-btn open-edit-modal"
                                        data-package-id="<?= $row['package_id'] ?>"
                                        data-package-name="<?= htmlspecialchars($row['package_name'], ENT_QUOTES) ?>"
                                        data-package-price="<?= $row['price'] ?>"
                                        data-package-features="<?= htmlspecialchars($row['features'] ?? '', ENT_QUOTES) ?>"
                                    ><i class="fa fa-pen"></i> Edit</button>
                                    <button class="action-btn delete-btn open-delete-modal"
                                        data-package-id="<?= $row['package_id'] ?>"
                                        data-package-name="<?= htmlspecialchars($row['package_name'], ENT_QUOTES) ?>"
                                    ><i class="fa fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        <?php $row_number++; endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:#aaa;">No packages found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Package Modal -->
    <div id="add-modal" class="custom-modal">
        <div class="package-modal-content">
            <span class="close-btn" id="close-add-modal">&times;</span>
            <h2 class="modal-title">Add Package</h2>
            <form id="add-package-form" autocomplete="off">
                <div class="modal-group">
                    <label>Package Name</label>
                    <input type="text" name="package_name" required>
                </div>
                <div class="modal-group">
                    <label>Price (₱)</label>
                    <input type="number" name="price" step="0.01" min="0" required>
                </div>
                <div class="modal-group">
                    <div id="add-features-list">
                        <input type="text" name="features[]" placeholder="Feature 1">
                        <input type="text" name="features[]" placeholder="Feature 2">
                        <input type="text" name="features[]" placeholder="Feature 3">
                    </div>
                    <button type="button" class="action-btn edit-btn" id="add-feature-btn">
                        <i class="fa fa-plus"></i> Add Feature
                    </button>
                    <button type="submit" class="modal-submit-btn">Add Package</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Package Modal -->
    <div id="edit-modal" class="custom-modal">
        <div class="package-modal-content">
            <span class="close-btn" id="close-edit-modal">&times;</span>
            <h2 class="modal-title">Edit Package</h2>
            <form id="edit-package-form" autocomplete="off">
                <input type="hidden" name="package_id" id="edit-package-id">
                <div class="modal-group">
                    <label>Package Name</label>
                    <input type="text" name="package_name" id="edit-package-name" required>
                </div>
                <div class="modal-group">
                    <label>Price (₱)</label>
                    <input type="number" name="price" id="edit-package-price" step="0.01" min="0" required>
                </div>
                <div class="modal-group">
                    <label>Features</label>
                    <div id="edit-features-list"></div>
                    <button type="button" class="action-btn edit-btn" id="edit-feature-btn"><i class="fa fa-plus"></i> Add Feature</button>
                </div>
                <button type="submit" class="modal-submit-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // === Sidebar Menu Logic ===
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

    if (hamburger) {
        hamburger.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 700) closeSidebar();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === "Escape" && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 700) closeSidebar();
    });

    // === Modals ===
    const addModal = document.getElementById('add-modal');
    const editModal = document.getElementById('edit-modal');

    document.getElementById('open-add-modal').onclick = () => {
        editModal.classList.remove('active'); // just in case
        addModal.classList.add('active');
    };

    document.getElementById('close-add-modal').onclick = () => addModal.classList.remove('active');
    document.getElementById('close-edit-modal').onclick = () => editModal.classList.remove('active');

    window.onclick = (e) => {
        if (e.target === addModal) addModal.classList.remove('active');
        if (e.target === editModal) editModal.classList.remove('active');
    };

    // === Add Package Features ===
    document.getElementById('add-feature-btn').onclick = () => {
        const featuresDiv = document.getElementById('add-features-list');
        const newInput = document.createElement('input');
        newInput.type = 'text';
        newInput.name = 'features[]';
        newInput.placeholder = `Feature ${featuresDiv.children.length + 1}`;
        newInput.required = true;
        featuresDiv.appendChild(newInput);
    };

    // === Edit Package Features ===
    document.getElementById('edit-feature-btn').onclick = () => {
        const featuresDiv = document.getElementById('edit-features-list');
        const newInput = document.createElement('input');
        newInput.type = 'text';
        newInput.name = 'features[]';
        newInput.placeholder = 'Another feature';
        newInput.required = true;
        featuresDiv.appendChild(newInput);
    };

    // === Open Edit Modal & Prefill Data ===
    document.querySelectorAll('.open-edit-modal').forEach(btn => {
        btn.onclick = () => {
            const id = btn.dataset.packageId;
            const name = btn.dataset.packageName;
            const price = btn.dataset.packagePrice;
            const featuresStr = btn.dataset.packageFeatures || '';

            document.getElementById('edit-package-id').value = id;
            document.getElementById('edit-package-name').value = name;
            document.getElementById('edit-package-price').value = price;

            const featuresDiv = document.getElementById('edit-features-list');
            featuresDiv.innerHTML = '';

            if (featuresStr) {
                featuresStr.split('||').forEach(f => {
                    const inp = document.createElement('input');
                    inp.type = 'text';
                    inp.name = 'features[]';
                    inp.value = f;
                    inp.required = true;
                    featuresDiv.appendChild(inp);
                });
            }

            addModal.classList.remove('active'); // in case it's open
            editModal.classList.add('active');
        };
    });

   // === Delete Package Logic ===
document.querySelectorAll('.open-delete-modal').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.packageId;
    const name = btn.dataset.packageName;

    Swal.fire({
      title: 'Delete package?',
      text: `Are you sure you want to delete "${name}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e34b4b',
      cancelButtonColor: '#1e8fa2',
      confirmButtonText: 'Yes, delete'
    }).then(result => {
      if (!result.isConfirmed) return;

      fetch('delete_package.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'package_id=' + encodeURIComponent(id)
      })
      .then(r => r.json())
      .then(data => {
        const ok = data && (data.success === true || data.ok === true);
        if (!ok) throw new Error(data?.msg || 'Failed to delete.');

        // remove row + renumber first column
        const tr = btn.closest('tr');
        tr?.parentNode?.removeChild(tr);
        renumberRows();

        Swal.fire({ icon:'success', title:'Deleted', text: data.msg || 'Package deleted.', confirmButtonColor:'#1e8fa2' });
      })
      .catch(err => {
        Swal.fire({ icon:'error', title:'Failed', text: String(err.message || err), confirmButtonColor:'#1e8fa2' });
      });
    });
  });
});

// helper to renumber "#" column
function renumberRows(){
  document.querySelectorAll('.packages-table tbody tr').forEach((tr, i) => {
    const cell = tr.querySelector('td[data-label="#"], td:first-child');
    if (cell) cell.textContent = (i + 1);
  });
}


    // === Add Package Submit ===
    document.getElementById('add-package-form').onsubmit = function (e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        fetch('add_package.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.msg || 'Package added successfully!',
                    confirmButtonColor: '#1e8fa2'
                }).then(() => location.reload());
                addModal.classList.remove('active');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.msg || 'Failed to add package!',
                    confirmButtonColor: '#e34b4b'
                });
            }
        }).catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Server error. Please try again later.',
                confirmButtonColor: '#e34b4b'
            });
        });
    };

    // === Edit Package Submit ===
    document.getElementById('edit-package-form').onsubmit = function (e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        fetch('edit_package.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated',
                    text: data.msg || 'Package updated!',
                    confirmButtonColor: '#1e8fa2'
                }).then(() => location.reload());
                editModal.classList.remove('active');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.msg || 'Failed to update package.',
                    confirmButtonColor: '#e34b4b'
                });
            }
        }).catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Try again later.',
                confirmButtonColor: '#e34b4b'
            });
        });
    };
});
</script>

</body>
</html>
