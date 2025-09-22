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

// --- STATUS FILTER (server-side) ---
$allowed_statuses = ['all','pending','approved','rejected','cancelled','processing'];
$status_filter = strtolower($_GET['status'] ?? 'all');
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'all';

// Build WHERE clause for queries
$where_sql = '';
$where_params = [];
$where_types  = '';
if ($status_filter !== 'all') {
    $where_sql   = " WHERE b.status = ? ";
    $where_params[] = $status_filter;
    $where_types  .= 's';
}


// ---------- AJAX HANDLER FOR PAYMENT VERIFICATION MODAL ---------- //
if (isset($_GET['action']) && $_GET['action'] === 'fetch_payment' && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);

    $sql = "SELECT 
        b.booking_id, b.booking_date, b.status as booking_status,
        u.full_name, u.email_address, u.contact_number,
        pk.name as package_name, pk.price as package_price,
        (
            SELECT p.amount FROM payment p 
            WHERE p.booking_id = b.booking_id 
            ORDER BY p.payment_id DESC LIMIT 1
        ) as payment_amount,
        (
            SELECT p.status FROM payment p 
            WHERE p.booking_id = b.booking_id 
            ORDER BY p.payment_id DESC LIMIT 1
        ) as payment_status,
        (
            SELECT p.proof FROM payment p 
            WHERE p.booking_id = b.booking_id 
            ORDER BY p.payment_id DESC LIMIT 1
        ) as payment_proof
    FROM booking b
    JOIN user u ON b.user_id = u.user_id
    LEFT JOIN package pk ON pk.package_id = b.package_id
    WHERE b.booking_id = ?
    LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['success'=>true, 'data'=>$row]);
    } else {
        echo json_encode(['error'=>'Not found']);
    }
    exit;
}
// ---------- END AJAX HANDLER ---------- //

// ---------- AJAX HANDLER FOR RECEIPT MODAL ---------- //
if (isset($_GET['action']) && $_GET['action'] === 'fetch_receipt' && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);

    $sql = "SELECT 
        b.booking_id, b.booking_date,
        u.full_name,
        pk.name as package_name, pk.price as package_price,
        (
            SELECT p.amount FROM payment p 
            WHERE p.booking_id = b.booking_id 
            ORDER BY p.payment_id DESC LIMIT 1
        ) as amount_paid,
        (
            SELECT p.status FROM payment p 
            WHERE p.booking_id = b.booking_id 
            ORDER BY p.payment_id DESC LIMIT 1
        ) as payment_status
    FROM booking b
    JOIN user u ON b.user_id = u.user_id
    LEFT JOIN package pk ON pk.package_id = b.package_id
    WHERE b.booking_id = ?
    LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['success'=>true, 'data'=>$row]);
    } else {
        echo json_encode(['error'=>'Not found']);
    }
    exit;
}
// ---------- END AJAX HANDLER FOR RECEIPT ---------- //

// PAGINATION SETTINGS
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;


// Get total rows (respect filter)
$count_sql = "SELECT COUNT(*) AS total FROM booking b" . $where_sql;
$count_stmt = $conn->prepare($count_sql);
if ($status_filter !== 'all') {
    $count_stmt->bind_param($where_types, ...$where_params);
}
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total = (int)($count_res->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = ($limit > 0) ? ceil($total / $limit) : 1;


// MAIN QUERY: Only Pending, Latest first, 10 per page
$sql = "
SELECT 
    b.booking_id,
    b.booking_date,
    u.full_name,
    pk.name AS package_name,
    pk.price AS package_price,
    b.status AS booking_status,
    (
        SELECT p.amount 
        FROM payment p 
        WHERE p.booking_id = b.booking_id 
        ORDER BY p.payment_id DESC 
        LIMIT 1
    ) AS latest_payment_amount,
    (
        SELECT p.status 
        FROM payment p 
        WHERE p.booking_id = b.booking_id 
        ORDER BY p.payment_id DESC 
        LIMIT 1
    ) AS latest_payment_status
FROM booking b
JOIN user u ON u.user_id = b.user_id
LEFT JOIN package pk ON pk.package_id = b.package_id
" . $where_sql . "
ORDER BY 
    FIELD(b.status, 'pending','processing','approved','rejected','cancelled'),
    b.booking_id DESC
LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($status_filter !== 'all') {
    $bind_types = $where_types . 'ii';             // e.g. "sii"
    $stmt->bind_param($bind_types, $where_params[0], $limit, $offset);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Bookings - Aquasafe RuripPh Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
    /* (CSS remains unchanged, as per previous versions) */
    .booking-table-container { width: 95%; margin: 0 auto 30px auto; background: transparent; border-radius: 16px; overflow-x: auto; box-shadow: none; }
    .booking-table { width: 100%; border-collapse: separate; border-spacing: 0; background: transparent; border-radius: 16px; overflow: hidden; }
    .booking-table th, .booking-table td { text-align: center !important; }
    .booking-table th { background: #1e8fa2; color: #fff; padding: 16px 12px; font-weight: 700; font-size: 1.07em; border: none; }
    .booking-table td { padding: 14px 12px; background: #ffffffea; color: #186070; font-size: 1em; border-bottom: 2px solid #e3f6fc; vertical-align: middle; }
    .booking-table tbody tr:last-child td { border-bottom: none; }
    .booking-table tr { transition: background 0.18s; }
    .booking-table tr:hover td { background: #e7f7fc !important; }
    .table-title { margin-top: 30px; margin-bottom: 18px; margin-left: 30px; color: #1e8fa2; font-size: 2rem; font-weight: bold; letter-spacing: 0.02em; text-shadow: none; text-align: left; transition: margin 0.3s, text-align 0.3s; }
    @media (max-width: 700px) { .table-title { margin-left: 0; text-align: center; } }
    @media (max-width: 800px) { .booking-table th, .booking-table td { padding: 10px 6px; font-size: 0.93em; } }
    @media (max-width: 600px) {
        .booking-table-container { font-size: 1em; padding: 0 10px; box-sizing: border-box; }
        .booking-table { border: none; background: transparent; }
        .booking-table thead { display: none; }
        .booking-table, .booking-table tbody, .booking-table tr, .booking-table td { display: block; width: 100%; }
        .booking-table tr { background: #fafdff; border-radius: 22px; border: 1.5px solid #d3f0fa; margin-bottom: 24px; box-shadow: 0 2px 8px 0 #b9eafc60; padding: 18px 0 12px 0; overflow: hidden; position: relative; }
        .booking-table td { border: none; background: transparent; text-align: left !important; padding: 8px 16px 8px 16px; position: relative; font-size: 1.08em; min-height: 28px; margin-bottom: 0; }
        .booking-table td:before { content: attr(data-label); display: inline-block; min-width: 120px; color: #1e8fa2; font-weight: bold; font-size: 1em; margin-right: 12px; margin-bottom: 3px; vertical-align: top; width: auto; position: static; white-space: normal; }
    }
    .booking-table td { padding-left: 8px; padding-right: 8px; }
    .status-pending { background: #ffe7c2; color: #e59819; font-weight: bold; border-radius: 8px; padding: 6px 12px; display: inline-block; min-width: 80px; }
    .table-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    margin-bottom: 1px;
    width: 95%;
    margin-left: auto;
    margin-right: auto;
    flex-wrap: wrap;
}
.table-title {
    color: #1e8fa2;
    font-size: 2rem;
    font-weight: bold;
    letter-spacing: 0.02em;
}
.table-search input[type="text"] {
    padding: 8px 16px;
    border: 1.5px solid #c8eafd;
    border-radius: 10px;
    font-size: 1em;
    min-width: 180px;
    max-width: 100vw;
    background: #fff;
    color: #186070;
    box-shadow: 0 2px 8px #b9eafc22;
    transition: border .18s;
}
.table-search input[type="text"]:focus {
    outline: none;
    border-color: #1e8fa2;
}
@media (max-width: 700px) {
    .table-title-bar {
        flex-direction: column;
        align-items: center;
        margin-top: 20px;
        margin-bottom: 10px;

        margin-left: 0;
        margin-right: 0;
    }
    .table-title {
        font-size: 1.3rem;
        text-align: center;
        width: 100%;
        margin-top:10px;
    }
    .table-search {
        width: 50%;
    }
    .table-search input[type="text"] {
        width: 100%;
        min-width: 0;
    }
}
.modal {
    position: fixed; z-index: 10000;
    left: 0; top: 0; width: 100vw; height: 100vh;
    background: rgba(0, 151, 183, 0.11);
    display: flex; justify-content: center; align-items: center;
}
.modal-content {
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 2px 24px #1e8fa233;
    padding: 42px 32px 28px 32px;
    max-width: 400px; width: 98vw;
}
.modal-title {
    text-align: center;
    font-size: 2rem;
    color: #1698b4;
    font-weight: bold;
    margin-bottom: 25px;
    margin-top: 10px;
}
.modal-details div {
    display: flex; justify-content: space-between;
    margin-bottom: 11px;
}
.modal-label {
    font-weight: bold;
    color: #1e8fa2;
    min-width: 128px;
    display: inline-block;
}
.modal-buttons {
    margin-top: 28px; text-align: center;
}
.approve-btn {
    background: #b8f2d3; color: #1aa877;
    border: none; padding: 10px 30px;
    border-radius: 9px; font-size: 1.1em; font-weight: bold;
    margin-right: 16px; cursor: pointer;
    box-shadow: 0 1px 4px #b8f2d366;
}
.reject-btn {
    background: #ffdbdb; color: #c41e1e;
    border: none; padding: 10px 30px;
    border-radius: 9px; font-size: 1.1em; font-weight: bold;
    margin-right: 16px; cursor: pointer;
    box-shadow: 0 1px 4px #ffe0e0b2;
}
.close-btn {
    background: #c8eafd; color: #146b8b;
    border: none; padding: 10px 22px;
    border-radius: 9px; font-size: 1.05em; font-weight: 500;
    cursor: pointer;
}
@media (max-width: 600px) {
    .modal-content { padding: 25px 8px 18px 8px; }
    .modal-details div { flex-direction: column; align-items: flex-start; }
    .modal-label { min-width: 90px; }
}
/* ===== Full-bleed table (no side margins) ===== */
.content-area { padding-left: 0; padding-right: 0; }      /* tanggalin padding ng page */
.table-title-bar,
.booking-table-container { width: 100%; margin-left: 0; margin-right: 0; }

/* alisin ang rounding/box look para sumagad sa edges */
.booking-table-container { 
  border-radius: 0; 
  background: transparent; 
  /* keep overflow-x para may horizontal-scroll sa mas maliit na screen */
  overflow-x: auto; 
}

/* ===== Borderless table ===== */
.booking-table { 
  width: 100%;
  border-radius: 0;           /* no rounded corners */
  border-collapse: separate;
  border-spacing: 0;
  background: transparent;
}
.booking-table th,
.booking-table td { 
  border: none !important;    /* WALANG border sa cells */
}

/* optional: mas malinis na zebra stripes (instead of borders) */
.booking-table tbody tr:nth-child(odd)  td { background: #ffffff; }
.booking-table tbody tr:nth-child(even) td { background: #f9feff; }

/* keep hover highlight */
.booking-table tr:hover td { background: #e7f7fc !important; }

/* bawasan ang side padding ng cells para mas lapad ang content */
.booking-table th, .booking-table td { padding-left: 10px; padding-right: 10px; }

/* title bar spacing para pantay pa rin tingnan */
.table-title { margin-left: 16px; }
.table-search { margin-right: 16px; }

/* mobile cards layout – retain as-is, pero tanggalin borders para consistent */
@media (max-width: 600px) {
  .content-area { padding-left: 0; padding-right: 0; }
  .booking-table tr { 
    border: none; 
    box-shadow: 0 2px 8px 0 #b9eafc60; 
    background: #fafdff;
  }
}
.table-controls{display:flex; align-items:center; gap:10px;}
.table-filter{
  padding:8px 12px;
  border:1.5px solid #c8eafd;
  border-radius:10px;
  background:#fff;
  color:#186070;
  font-weight:600;
  box-shadow:0 2px 8px #b9eafc22;
}
.table-filter:focus{outline:none; border-color:#1e8fa2;}

@media (max-width:700px){
  .table-controls{flex-direction:column; width:100%;}
  .table-filter{width:100%;}
  .table-search{width:100%;}
  .table-search input{width:100%;}
}

/* Unified status badge (soft, darker colors, bold text) */
.status-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:8px 16px;
  border-radius:12px;
  font-weight:800;
  font-size:.98rem;
  letter-spacing:.2px;
  line-height:1;
  box-shadow:
    inset 0 0 0 1px rgba(0,0,0,.04),
    0 1px 0 rgba(0,0,0,.03);
}

/* palette tuned to your teal theme */
.status-badge.pending{
  background:#ffe3d7;   /* warm peach */
  color:#b65e1b;        /* darker amber */
}
.status-badge.processing{
  background:#e8f6fb;   /* teal-tinted */
  color:#1e8fa2;        /* your theme teal */
}
.status-badge.approved{
  background:#dff5e8;   /* mint */
  color:#1d8b58;        /* dark green */
}
.status-badge.fully-paid{
  background:#dff5e8;   /* same as approved */
  color:#1d8b58;
}
.status-badge.rejected{
  background:#ffdede;   /* soft red */
  color:#b33a2b;        /* darker red */
}
.status-badge.cancelled{
  background:#e9eef3;   /* cool gray */
  color:#5a6b78;        /* slate */
}

/* optional: make them breathe a bit more on mobile */
@media (max-width:700px){
  .status-badge{ padding:7px 14px; }
}

    </style>
</head>
<body>
    <?php $page = 'bookings'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="content-area">
        <?php include 'includes/header.php'; ?>
        <div class="table-title-bar">
  <div class="table-title">All Bookings</div>
  <div class="table-controls">
    <select id="status-filter" class="table-filter" aria-label="Filter by status">
      <option value="all"       <?= $status_filter==='all' ? 'selected' : '' ?>>All</option>
      <option value="pending"   <?= $status_filter==='pending' ? 'selected' : '' ?>>Pending</option>
      <option value="approved"  <?= $status_filter==='approved' ? 'selected' : '' ?>>Approved</option>
      <option value="rejected"  <?= $status_filter==='rejected' ? 'selected' : '' ?>>Rejected</option>
      <option value="cancelled" <?= $status_filter==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>

    <div class="table-search">
      <input type="text" id="table-search-input" placeholder="Search...">
    </div>
  </div>
</div>


        <div class="booking-table-container">
            <table class="booking-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date Booked</th>
                    <th>Client Name</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Booking Status</th>
                    <th>Amount Paid</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rownum = $offset + 1; // offset is ($page-1)*$limit
            if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                    // Calculate days passed
                    $booking_date = new DateTime($row['booking_date']);
                    $now = new DateTime();
                    $days_passed = $now->diff($booking_date)->days;
                    // Status display
                    $status_display = '<span class="status-pending">Pending';
                    if ($days_passed > 0) {
                        $status_display .= ' (' . $days_passed . ' day' . ($days_passed > 1 ? 's' : '') . ' passed)';
                    }
                    $status_display .= '</span>';
            ?>
                <tr>
                    <td data-label="#"><?= $rownum++; ?></td>
                    <td data-label="Date Booked"><?= date("F j, Y", strtotime($row['booking_date'])) ?></td>
                    <td data-label="Client Name"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td data-label="Package"><?= htmlspecialchars($row['package_name']) ?></td>
                    <td data-label="Amount">₱ <?= number_format($row['package_price'], 2) ?></td>
                    <td data-label="Booking Status">
                        <?php
                        $status = strtolower($row['booking_status']);

                        // (Optional) If you want to show “Fully Paid” when appropriate:
                        if ($status === 'approved' &&
                            isset($row['latest_payment_amount'], $row['package_price']) &&
                            (float)$row['latest_payment_amount'] >= (float)$row['package_price']) {
                            $status = 'fully-paid';
                        }

                        $valid = ['pending','processing','approved','rejected','cancelled','fully-paid'];
                        if (!in_array($status, $valid)) { $status = 'pending'; }

                        // Label (nice casing)
                        $label = $status === 'fully-paid' ? 'Fully Paid' : ucfirst($status);

                        echo '<span class="status-badge ' . $status . '">' . $label . '</span>';
                        ?>
                        </td>
                    <td data-label="Amount Paid">
                        <?php 
                            if (is_null($row['latest_payment_amount']) || $row['latest_payment_amount'] <= 0) {
                                echo 'No payment';
                            } else {
                                echo '₱ ' . number_format($row['latest_payment_amount'], 2);
                            }
                        ?>
                    </td>
                    <td data-label="Action">
                        <?php
                        // CONDITION: If processing AND may payment, show the verify button
                        if ($row['booking_status'] === 'processing' && !is_null($row['latest_payment_amount']) && $row['latest_payment_amount'] > 0) {
                            ?>
                            <button class="verify-btn"
                                    data-booking="<?= $row['booking_id']; ?>"
                                    style="background:#ffc77d; color:#db7800; border-radius:10px; padding:8px 26px; font-weight:600; border:none; margin:0 3px; cursor:pointer;">
                                Verify Payment
                            </button>
                            <?php
                        } else if ($row['booking_status'] === 'pending') {
                            // "Awaiting Downpayment" kapag pending pa at walang payment
                            echo '<span style="background:#eee; color:#b7b7b7; border-radius:8px; padding:7px 16px; font-weight:600;">Awaiting Downpayment</span>';
                        } else if ($row['booking_status'] === 'approved') {
                            echo '<a href="receipt.php?booking_id=' . $row['booking_id'] . '" target="_blank" class="print-receipt-btn" style="background:#21ba6e; color:#fff; border-radius:8px; padding:7px 18px; font-weight:600; border:none; cursor:pointer; text-decoration:none; display:inline-block;">View/Print</a>';
                        } else if ($row['booking_status'] === 'rejected' || $row['booking_status'] === 'cancelled') {
                            echo '<button style="background:#f5f6fa; color:#b7b7b7; border-radius:8px; padding:7px 18px; font-weight:600; border:none; cursor:not-allowed;" disabled>Rejected</button>';
                        }
                        ?>
                    </td>
                </tr>
            <?php
                endwhile;
            else:
            ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#aaa;">No pending bookings found.</td>
                </tr>
            <?php endif; ?>
            <tr class="no-data-row" style="display:none;">
                <td colspan="8" style="text-align:center; color:#aaa;">No data found.</td>
            </tr>

            </tbody>

            </table>
            <!-- Pagination controls -->
            <div style="text-align:center;margin-top:18px;">
                <?php
                    $total = (int)$total;
                    $limit = (int)$limit;
                    $page = (int)$page;

                    $total_pages = $limit > 0 ? ceil($total / $limit) : 1;
                    $total_pages = (int)$total_pages; // <- make absolutely sure this is integer
                    if ($total_pages > 1):
                        if ($page > 1) {
                            echo '<a href="?page='.($page-1).'" style="margin-right:12px;">&laquo; Previous</a>';
                        }
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == $page) {
                                echo '<strong style="color:#1e8fa2;margin:0 8px;">'.$i.'</strong>';
                            } else {
                                echo '<a href="?page='.$i.'" style="margin:0 8px;">'.$i.'</a>';
                            }
                        }
                        if ($page < $total_pages) {
                            echo '<a href="?page='.($page+1).'" style="margin-left:12px;">Next &raquo;</a>';
                        }
                    endif;
                ?>
            </div>

        </div>
        <!-- PAYMENT VERIFICATION MODAL -->
<div id="verifyPaymentModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h2 class="modal-title">Payment Verification</h2>
        <div class="modal-details">
            <div>
                <span class="modal-label">Client Name:</span>
                <span id="modalClientName"></span>
            </div>
            <div>
                <span class="modal-label">Booking Date:</span>
                <span id="modalBookingDate"></span>
            </div>
            <div>
                <span class="modal-label">Package:</span>
                <span id="modalPackage"></span>
            </div>
            <div>
                <span class="modal-label">Amount Due:</span>
                <span id="modalAmount"></span>
            </div>
            <div>
                <span class="modal-label">Amount Paid:</span>
                <span id="modalPaid"></span>
            </div>
            <div>
                <span class="modal-label">Payment Status:</span>
                <select id="modalPaymentType" style="padding:7px 14px;border-radius:8px;font-size:1em;border:1.5px solid #c8eafd;">
                    <option value="Downpayment">Downpayment</option>
                    <option value="Full Payment">Full Payment</option>
                </select>
            </div>
            <div>
                <span class="modal-label">Proof:</span>
                <span id="modalProof"></span>
            </div>
        </div>
        <div class="modal-buttons">
            <button id="approvePaymentBtn" class="approve-btn">Approve</button>
            <button id="rejectPaymentBtn" class="reject-btn">Reject</button>
            <button id="closeModalBtn" class="close-btn">Close</button>
        </div>
    </div>
</div>

<div id="viewReceiptModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:420px">
        <h2 class="modal-title">Booking Receipt</h2>
        <div class="modal-details" id="receiptDetails">
            <!-- Dynamically filled -->
        </div>
        <div class="modal-buttons">
            <button id="printReceiptBtn" class="approve-btn">Print Receipt</button>
            <button onclick="document.getElementById('viewReceiptModal').style.display='none'" class="close-btn">Close</button>
        </div>
    </div>
</div>


    </main>
    <!-- Sidebar/Hamburger Script remains unchanged -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- SIDEBAR HANDLING ---- //
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

    // ---- TABLE SEARCH ---- //
    const searchInput = document.getElementById('table-search-input');
    const table = document.querySelector('.booking-table');
    const rows = table.querySelectorAll('tbody tr:not(.no-data-row)');
    const noDataRow = table.querySelector('.no-data-row');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            let visibleCount = 0;
            rows.forEach(row => {
                let rowText = Array.from(row.cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                if (rowText.includes(term)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            if (visibleCount === 0) {
                noDataRow.style.display = '';
            } else {
                noDataRow.style.display = 'none';
            }
        });
    }

    // ---- PAYMENT VERIFICATION MODAL ---- //
    document.querySelectorAll('.verify-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-booking');
            // Fetch booking/payment details via AJAX
            fetch(`bookings.php?action=fetch_payment&booking_id=${bookingId}`)
                .then(response => response.json())
                .then(res => {
                    if(res.success && res.data) {
                        document.getElementById('modalClientName').textContent = res.data.full_name || '';
                        document.getElementById('modalBookingDate').textContent = res.data.booking_date || '';
                        document.getElementById('modalPackage').textContent = res.data.package_name || '';
                        document.getElementById('modalAmount').textContent = res.data.package_price ? '₱ ' + parseFloat(res.data.package_price).toLocaleString() : '';
                        document.getElementById('modalPaid').textContent = res.data.payment_amount ? '₱ ' + parseFloat(res.data.payment_amount).toLocaleString() : 'No payment';

                        // Payment proof image/file
                        if (res.data.payment_proof) {
                            document.getElementById('modalProof').innerHTML = `<a href="../uploads/${res.data.payment_proof}" target="_blank">View Proof</a>`;
                        } else {
                            document.getElementById('modalProof').innerHTML = '—';
                        }
                    } else {
                        document.getElementById('modalClientName').textContent = '[Error loading data]';
                        document.getElementById('modalBookingDate').textContent = '';
                        document.getElementById('modalPackage').textContent = '';
                        document.getElementById('modalAmount').textContent = '';
                        document.getElementById('modalPaid').textContent = '';
                        document.getElementById('modalProof').innerHTML = '';
                    }
                    document.getElementById('verifyPaymentModal').style.display = 'flex';
                })
                .catch(err => {
                    document.getElementById('modalClientName').textContent = '[Error]';
                    document.getElementById('verifyPaymentModal').style.display = 'flex';
                });
        });
    });
    // Close modal
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('verifyPaymentModal').style.display = 'none';
    };

       // ---- APPROVE/REJECT BUTTONS LOGIC ---- //
  document.getElementById('approvePaymentBtn').onclick = function() {
        document.getElementById('verifyPaymentModal').style.display = 'none'; // close modal agad
    Swal.fire({
        title: 'Are you sure?',
        text: 'Do you want to approve this payment?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#21ba6e',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('verifyPaymentModal').style.display = 'none';
            const bookingId = document.querySelector('.verify-btn.active')?.getAttribute('data-booking') || 
                document.querySelector('.verify-btn[data-booking]')?.getAttribute('data-booking');
            const paymentType = document.getElementById('modalPaymentType').value;

            fetch('booking_payment_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=approve_payment&booking_id=' + encodeURIComponent(bookingId) + '&payment_type=' + encodeURIComponent(paymentType)
            })
            .then(res => res.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Approved' : 'Error',
                    text: data.message,
                    confirmButtonColor: '#21ba6e'
                }).then(() => {
                    if (data.success) window.location.reload();
                });
            });
        }
    });
};

  document.getElementById('rejectPaymentBtn').onclick = function() {
        document.getElementById('verifyPaymentModal').style.display = 'none'; // close modal agad
    Swal.fire({
        title: 'Are you sure?',
        text: 'Do you want to reject this payment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Yes, reject it'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('verifyPaymentModal').style.display = 'none';
            const bookingId = document.querySelector('.verify-btn.active')?.getAttribute('data-booking') || 
                document.querySelector('.verify-btn[data-booking]')?.getAttribute('data-booking');
            fetch('booking_payment_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reject_payment&booking_id=' + encodeURIComponent(bookingId)
            })
            .then(res => res.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Rejected' : 'Error',
                    text: data.message,
                    confirmButtonColor: '#e74c3c'
                }).then(() => {
                    if (data.success) window.location.reload();
                });
            });
        }
    });
};

document.querySelectorAll('.view-receipt-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const bookingId = this.getAttribute('data-booking');
        fetch(`bookings.php?action=fetch_receipt&booking_id=${bookingId}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    document.getElementById('receiptDetails').innerHTML = `
                        <div><span class="modal-label">Client Name:</span> ${res.data.full_name}</div>
                        <div><span class="modal-label">Date Booked:</span> ${res.data.booking_date}</div>
                        <div><span class="modal-label">Package:</span> ${res.data.package_name}</div>
                        <div><span class="modal-label">Package Price:</span> ₱${parseFloat(res.data.package_price).toLocaleString()}</div>
                        <div><span class="modal-label">Amount Paid:</span> ₱${parseFloat(res.data.amount_paid).toLocaleString()}</div>
                        <div><span class="modal-label">Payment Status:</span> ${res.data.payment_status}</div>
                    `;
                    document.getElementById('viewReceiptModal').style.display = 'flex';
                }
            });
    });
});

// Print button logic
document.getElementById('printReceiptBtn').onclick = function() {
    const content = document.getElementById('receiptDetails').innerHTML;
    const printWindow = window.open('', '', 'width=720,height=600');
    printWindow.document.write('<html><head><title>Receipt</title>');
    printWindow.document.write('<link rel="stylesheet" href="styles/style.css">'); // If you want styling
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>Booking Receipt</h2>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
};


// ---- STATUS FILTER (server-side reload) ----
const statusSelect = document.getElementById('status-filter');
if (statusSelect) {
  statusSelect.addEventListener('change', () => {
    const url = new URL(window.location.href);
    const val = statusSelect.value;
    if (val === 'all') { url.searchParams.delete('status'); }
    else { url.searchParams.set('status', val); }
    url.searchParams.set('page', '1');      // reset pagination
    window.location.href = url.toString();
  });
}

});
</script>



</body>
</html>
