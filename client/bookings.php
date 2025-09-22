<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
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

// Fetch bookings for current client (latest ID on top)
$user_id = $_SESSION['user_id'];
$sql = "SELECT 
            b.*, 
            pk.name AS package_name,
            pk.price AS package_price,
            (
                SELECT p.amount
                FROM payment p 
                WHERE p.booking_id = b.booking_id 
                ORDER BY p.payment_id DESC 
                LIMIT 1
            ) AS latest_payment_amount,   -- ⬅️ added
            (
                SELECT p.status 
                FROM payment p 
                WHERE p.booking_id = b.booking_id 
                ORDER BY p.payment_id DESC 
                LIMIT 1
            ) AS payment_status
        FROM booking b
        LEFT JOIN package pk ON pk.package_id = b.package_id
        WHERE b.user_id = ?
        ORDER BY b.booking_id DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('SQL prepare failed: ' . $conn->error . "<br><br>SQL: " . $sql);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Aquasafe RuripPh</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .booking-table-container {
            width: 95%;
            margin: 0 auto 30px auto;
            background: transparent;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: none;
        }
        .booking-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: transparent;
            border-radius: 16px;
            overflow: hidden;
        }
        .booking-table th, .booking-table td {
            text-align: center !important;
        }
        .booking-table th {
            background: #1e8fa2;
            color: #fff;
            padding: 16px 12px;
            font-weight: 700;
            font-size: 1.07em;
            border: none;
        }
        .booking-table td {
            padding: 14px 12px;
            background: #ffffffea;
            color: #186070;
            font-size: 1em;
            border-bottom: 2px solid #e3f6fc; /* Line between rows */
            vertical-align: middle;  /* <-- Add this */
        }
        .booking-table tbody tr:last-child td {
            border-bottom: none;
        }
        .booking-table tr {
            transition: background 0.18s;
        }
        .booking-table tr:hover td {
            background: #e7f7fc !important;
        }
        .booking-table .action-btn {
            color: #1e8fa2;
            text-decoration: none;
            font-weight: 500;
            margin-right: 12px;
            transition: color 0.18s;
        }
        .booking-table .action-btn:last-child {
            margin-right: 0;
        }
        .booking-table .action-btn:hover {
            color: #0c5460;
            text-decoration: underline;
        }
        .table-title {
            margin-top: 30px;
            margin-bottom: 18px;
            margin-left: 30px;
            color: #1e8fa2;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 0.02em;
            text-shadow: none;
            text-align: left;
            transition: margin 0.3s, text-align 0.3s;
        }

        .action-btn,
.pay-btn,
.cancel-btn,
.view-btn {
    display: inline-block;
    padding: 7px 18px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1em;
    cursor: pointer;
    margin-right: 8px;
    vertical-align: middle;
    box-sizing: border-box;
    transition: background 0.18s, color 0.18s;
}
        @media (max-width: 700px) {
            .table-title {
                margin-left: 0;
                text-align: center;
            }
        }
        @media (max-width: 800px) {
            .booking-table th, .booking-table td {
                padding: 10px 6px;
                font-size: 0.93em;
            }
        }

    @media (max-width: 600px) {
    .booking-table-container {
        font-size: 1em;
        padding: 0 10px;
        box-sizing: border-box;
    }
    .booking-table {
        border: none;
        background: transparent;
    }
    .booking-table thead {
        display: none;
    }
    .booking-table,
    .booking-table tbody,
    .booking-table tr,
    .booking-table td {
        display: block;
        width: 100%;
    }
    .booking-table tr {
        background: #fafdff;
        border-radius: 22px;
        border: 1.5px solid #d3f0fa;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px 0 #b9eafc60;
        padding: 18px 0 12px 0;
        overflow: hidden;
        position: relative;
    }
    .booking-table td {
        border: none;
        background: transparent;
        text-align: left !important;
        padding: 8px 16px 8px 16px;
        position: relative;
        font-size: 1.08em;
        min-height: 28px;
        margin-bottom: 0;
    }
    .booking-table td:before {
        content: attr(data-label);
        display: inline-block;
        min-width: 120px;
        color: #1e8fa2;
        font-weight: bold;
        font-size: 1em;
        margin-right: 12px;
        margin-bottom: 3px;
        vertical-align: top;
        width: auto;
        position: static;
        white-space: normal;
    }
    /* Para sa Action buttons na magkatabi at may gap */
    .booking-table td[data-label="Action"] {
        display: flex;
        gap: 8px;
        justify-content: flex-start;
        align-items: center;
        padding-top: 16px;
        padding-bottom: 0;
        flex-wrap: wrap;
    }
        .action-btn,
    .pay-btn,
    .cancel-btn,
    .view-btn,
    .print-btn,
    .cancelled-btn {
        display: inline-flex;
        align-items: center;
        width: auto !important;
        min-width: 0 !important;
        max-width: 100%;
        margin: 0;
        font-size: 0.95em;           /* Paliitin font */
        padding: 6px 12px;           /* Paliitin padding */
        white-space: nowrap;
        flex-shrink: 1;              /* Allow shrinking */
    }
    /* Optional: If sobrang sikip, mag-auto-adjust font size */
    .booking-table td[data-label="Action"] {
        font-size: 0.93em;
    }
}


        .booking-table td {
        padding-left: 8px;
        padding-right: 8px;
    }

        .status-pending {
    background: #ffe7c2;
    color: #e59819;
    font-weight: bold;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-block;
    min-width: 80px;
}
.status-approved {
    background: #c8f7e5;
    color: #11a97c;
    font-weight: bold;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-block;
    min-width: 80px;
}
.action-btn {
    padding: 7px 18px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1em;
    cursor: pointer;
    transition: background 0.18s, color 0.18s;
    margin-right: 8px;
    outline: none;
}

.pay-btn {
    background: #1e8fa2;
    color: #fff !important;
    box-shadow: 0 1px 4px #d3f0fa40;
}
.pay-btn:hover, .pay-btn:focus {
    background: #156c79;
    color: #fff !important;
}

.cancel-btn {
    background: #ffe3e6;
    color: #e34b4b !important;
    border: 1.5px solid #f9c6cc;
}
.cancel-btn:hover, .cancel-btn:focus {
    background: #ffd1d6;
    color: #c92a2a !important;
}

.view-btn {
    background: #e3fbf3;
    color: #20b57a !important;
    border: 1.5px solid #b8f2d3;
    font-weight: 600;
    border-radius: 8px;
    padding: 7px 18px;
    margin-right: 8px;
    transition: background 0.18s, color 0.18s;
}
.view-btn:hover, .view-btn:focus {
    background: #c6f5e2;
    color: #108657 !important;
}

.status-rejected {
    background: #ffdadb;
    color: #de3247;
    font-weight: bold;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-block;
    min-width: 80px;
}
.status-cancelled {
    background: #ececec;
    color: #888;
    font-weight: bold;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-block;
    min-width: 80px;
}
.status-processing {
    background: #e4e9ff;
    color: #3b50ce;
    font-weight: bold;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-block;
    min-width: 80px;
}

.print-btn {
    background: #338afc;
    color: #fff !important;
    border-radius: 8px;
    padding: 7px 18px;
    font-weight: 600;
    margin-right: 8px;
    display: inline-block;
    border: none;
    transition: background .18s;
}
.print-btn:hover,
.print-btn:focus {
    background: #2168c2;
    color: #fff !important;
}
.cancelled-btn {
    background: #ececec;
    color: #888 !important;
    font-weight: bold;
    border-radius: 8px;
    padding: 7px 18px;
    pointer-events: none;
    display: inline-block;
}
/* === Admin-style overlay + animation for DEPOSIT modal only === */
#deposit-modal.custom-modal{
  position: fixed; inset: 0; z-index: 10000;
  display: none;                 /* toggled by .active */
  align-items: center; justify-content: center;
  background: rgba(0, 151, 183, 0.11); /* same tint as admin */
}
#deposit-modal.custom-modal.active{
  display: flex;
  animation: depModalBg .25s ease;
}
@keyframes depModalBg{
  from { background: rgba(0,151,183,0); }
  to   { background: rgba(0,151,183,0.11); }
}

/* Card look + entry animation (admin feel) */
#deposit-modal .modal-content{
  background: #fff;
  border-radius: 22px;                 /* admin’s roundness */
  box-shadow: 0 2px 24px #1e8fa233;
  padding: 42px 32px 28px 32px;        /* spacious like admin */
  max-width: 480px; width: 96vw;
  position: relative;

  transform: translateY(18px) scale(.98);
  animation: depModalCard .34s cubic-bezier(.36,1.33,.4,.9) forwards;
}
@keyframes depModalCard{
  to { transform: translateY(0) scale(1); opacity: 1; }
}

/* Title style to match admin */
#deposit-modal .modal-title{
  text-align: center;
  font-size: 2rem;
  color: #1698b4;
  font-weight: 700;
  margin: 6px 0 22px;
}

/* Top-right close “×” */
#deposit-modal .close-btn{
  position: absolute; right: 16px; top: 12px;
  font-size: 1.8rem; color: #1e8fa2;
  cursor: pointer; line-height: 1;
}
#deposit-modal .close-btn:hover{ color: #de3247; }

/* Reuse your existing field styles but ensure spacing is tight with admin look */
#deposit-modal .modal-group{ margin-bottom: 14px; }
#deposit-modal .modal-group label{
  font-weight: 700; color: #1e8fa2; margin-bottom: 6px; display: block;
}
#deposit-modal input[type="number"],
#deposit-modal input[type="text"],
#deposit-modal input[type="file"]{
  width: 100%;
  border: 1.5px solid #c8eafd; border-radius: 10px;
  padding: 10px; font-size: 1em;
}
#deposit-modal input[type="number"]:focus,
#deposit-modal input[type="text"]:focus{
  outline: none; border-color: #1e8fa2;
}

/* Buttons same palette as admin */
#deposit-modal .modal-submit-btn{
  width: 100%;
  padding: 11px 0;
  border: none; border-radius: 10px;
  font-size: 1.07em; font-weight: 700; cursor: pointer;
  box-shadow: 0 1px 6px #b9eafc55;
}
#deposit-modal .modal-submit-btn:first-of-type{ background: #1e8fa2; color: #fff; }
#deposit-modal #cancel-deposit-btn{ background: #c8eafd; color: #146b8b; }

@media (max-width: 600px){
  #deposit-modal .modal-content{ padding: 24px 12px 18px 12px; }
}

/* === Admin-style overlay + animation for VIEW DETAILS modal === */
#view-details-modal.custom-modal{
  position: fixed; inset: 0; z-index: 10000;
  display: none;                 /* toggled by .active */
  align-items: center; justify-content: center;
  background: rgba(0, 151, 183, 0.11);
}
#view-details-modal.custom-modal.active{
  display: flex;
  animation: vdModalBg .25s ease;
}
@keyframes vdModalBg{
  from { background: rgba(0,151,183,0); }
  to   { background: rgba(0,151,183,0.11); }
}

#view-details-modal .modal-content{
  background:#fff;
  border-radius:22px;
  box-shadow:0 2px 24px #1e8fa233;
  padding:42px 32px 28px 32px;
  max-width:480px; width:96vw;
  position:relative;

  transform: translateY(18px) scale(.98);
  animation: vdModalCard .34s cubic-bezier(.36,1.33,.4,.9) forwards;
}
@keyframes vdModalCard{
  to { transform: translateY(0) scale(1); opacity:1; }
}

#view-details-modal .modal-title{
  text-align:center;
  font-size:2rem;
  color:#1698b4;
  font-weight:700;
  margin:6px 0 22px;
}

#view-details-modal .close-btn{
  position:absolute; right:16px; top:12px;
  font-size:1.8rem; color:#1e8fa2; line-height:1;
  cursor:pointer;
}
#view-details-modal .close-btn:hover{ color:#de3247; }

/* Style ng Close button para pareho sa admin */
#view-details-modal .modal-submit-btn{
  width:100%;
  padding:11px 0;
  border:none; border-radius:10px;
  font-size:1.07em; font-weight:700; cursor:pointer;
  background:#c8eafd; color:#146b8b;
  box-shadow:0 1px 6px #b9eafc55;
}

@media (max-width:600px){
  #view-details-modal .modal-content{ padding:24px 12px 18px 12px; }
}
/* === Admin-style overlay + animation for VIEW BOOKING modal === */
#view-booking-modal.custom-modal{
  position: fixed; inset: 0; z-index: 10000;
  display: none;
  align-items: center; justify-content: center;
  background: rgba(0, 151, 183, 0.11);
}
#view-booking-modal.custom-modal.active{
  display: flex;
  animation: vbModalBg .25s ease;
}
@keyframes vbModalBg{
  from { background: rgba(0,151,183,0); }
  to   { background: rgba(0,151,183,0.11); }
}

#view-booking-modal .modal-content{
  background:#fff;
  border-radius:22px;
  box-shadow:0 2px 24px #1e8fa233;
  padding:42px 32px 28px 32px;
  max-width:520px; width:96vw;
  position:relative;
  transform: translateY(18px) scale(.98);
  animation: vbModalCard .34s cubic-bezier(.36,1.33,.4,.9) forwards;
}
@keyframes vbModalCard{
  to { transform: translateY(0) scale(1); opacity:1; }
}

#view-booking-modal .modal-title{
  text-align:center; font-size:2rem; color:#1698b4;
  font-weight:700; margin:6px 0 22px;
}
#view-booking-modal .close-btn{
  position:absolute; right:16px; top:12px;
  font-size:1.8rem; color:#1e8fa2; line-height:1; cursor:pointer;
}
#view-booking-modal .close-btn:hover{ color:#de3247; }

#view-booking-modal .row{
  display:flex; justify-content:space-between;
  margin-bottom:12px;
}
#view-booking-modal .label{
  font-weight:700; color:#16687b;
}
#view-booking-modal .val{
  color:#12747b;
}

#view-booking-modal .modal-submit-btn{
  width:100%; padding:11px 0; border:none; border-radius:10px;
  font-size:1.07em; font-weight:700; cursor:pointer;
  background:#c8eafd; color:#146b8b; box-shadow:0 1px 6px #b9eafc55;
}
@media (max-width:600px){
  #view-booking-modal .modal-content{ padding:24px 12px 18px 12px; }
}
/* ==== Client Bookings: edge-to-edge like admin ==== */
.content-area.bookings-fullbleed{
  padding-left: 0 !important;
  padding-right: 0 !important;
  gap: 0 !important;
}

/* full width ng title at table container + maliit na safe gutter */
.content-area.bookings-fullbleed .table-title,
.content-area.bookings-fullbleed .booking-table-container{
  width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 8px !important;   /* pwede 6px kung gusto mo mas sagad */
  padding-right: 8px !important;
}

/* bawasan ang bottom space ng list */
.content-area.bookings-fullbleed .booking-table-container{
  margin-bottom: 12px !important;
  overflow-x: auto;
}

/* table takes full width; remove min-width locks */
.content-area.bookings-fullbleed .booking-table{
  min-width: 0 !important;
  width: 100% !important;
  border-spacing: 0;
}

/* comfortable cell padding kahit flush to edge */
.content-area.bookings-fullbleed .booking-table th,
.content-area.bookings-fullbleed .booking-table td{
  padding-left: 12px;
  padding-right: 12px;
}

/* alisin ang dating left margin ng title */
.content-area.bookings-fullbleed .table-title{
  margin-left: 0 !important;
  text-align: left;   /* keep same look */
}
/* === Unified status badge (same look as admin) === */
.status-badge{
  display:inline-flex; align-items:center; justify-content:center;
  padding:8px 16px;
  border-radius:12px;
  font-weight:800; font-size:.98rem; letter-spacing:.2px; line-height:1;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.04), 0 1px 0 rgba(0,0,0,.03);
}

/* Booking statuses */
.status-badge.pending    { background:#ffe3d7; color:#b65e1b; }
.status-badge.processing { background:#e8f6fb; color:#1e8fa2; }
.status-badge.approved   { background:#dff5e8; color:#1d8b58; }
.status-badge.rejected   { background:#ffdede; color:#b33a2b; }
.status-badge.cancelled  { background:#e9eef3; color:#5a6b78; }

/* Payment statuses */
.status-badge.paid        { background:#dff5e8; color:#1d8b58; }  /* Paid */
.status-badge.unpaid      { background:#ffe3e3; color:#b33a2b; }  /* Unpaid */
.status-badge.pay-pending { background:#fff1d6; color:#a66a00; }  /* Pending verification */

@media (max-width:700px){ .status-badge{ padding:7px 14px; } }


    </style>
</head>
<body>
    <?php $page = 'bookings'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="content-area bookings-fullbleed">
        <?php include 'includes/header.php'; ?>

        <div class="table-title">My Bookings</div>
        <div class="booking-table-container">
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Date Booked</th>
                        <th>Package</th>        <!-- ✅ NEW -->
                        <th>Amount</th>         <!-- ✅ NEW -->
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $row_number = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr id="booking-row-<?= $row['booking_id'] ?>"
                                data-package="<?= htmlspecialchars($row['package_name']) ?>"
                                data-date="<?= date('F j, Y', strtotime($row['booking_date'])) ?>"
                                data-amount="<?= number_format($row['package_price'], 2, '.', '') ?>">

                                <td data-label="No"><?= $row_number ?></td>
                                <td data-label="Date Booked"><?= date("F j, Y", strtotime($row['booking_date'])) ?></td>
                                <td data-label="Package"><?= htmlspecialchars($row['package_name']) ?></td>
                                <td data-label="Amount">₱ <?= number_format($row['package_price'], 2) ?></td>
                                <td data-label="Booking Status">
<?php
  $status = strtolower($row['status']); // booking.status
  $valid  = ['pending','processing','approved','rejected','cancelled'];
  if (!in_array($status, $valid)) { $status = 'pending'; }

  // label
  $label = ucfirst($status);
  echo '<span class="status-badge '.$status.'">'.$label.'</span>';
?>
</td>

                                <td data-label="Payment Status">
                        <?php
                            if ($status == 'cancelled' || $status == 'rejected') {
                                echo 'Cancelled';
                            } else {
                                echo $row['payment_status'] ? ucfirst($row['payment_status']) : 'Unpaid';
                            }
                        ?>
                    </td>

                    <td data-label="Action">
                        <?php if ($status == 'pending'): ?>
                            <button class="action-btn pay-btn open-deposit-modal"
                                data-booking-id="<?= $row['booking_id'] ?>"
                                data-booking-number="<?= $row_number ?>">Deposit</button>
                            <button class="action-btn cancel-btn open-cancel-modal"
                                data-booking-id="<?= $row['booking_id'] ?>">Cancel</button>
                        <?php elseif ($status == 'processing'): ?>
                              <button class="action-btn view-btn open-view-modal"
                                    data-booking-id="<?= $row['booking_id'] ?>"
                                    data-package="<?= htmlspecialchars($row['package_name']) ?>"
                                    data-amount="<?= number_format($row['package_price'], 2, '.', '') ?>"
                                    data-date="<?= date('F j, Y', strtotime($row['booking_date'])) ?>"
                                    data-downpayment="<?= isset($row['latest_payment_amount']) && $row['latest_payment_amount'] !== null
                                        ? number_format($row['latest_payment_amount'], 2, '.', '')
                                        : '' ?>"
                                    data-status="<?= ucfirst($row['status']) ?>"
                                >
                                    <i class="fa fa-eye"></i> View Details
                                </button>
                        <?php elseif ($status == 'approved'): ?>
                                <button class="action-btn view-btn open-view-booking-modal"
                                    data-booking-id="<?= $row['booking_id'] ?>"
                                    data-package="<?= htmlspecialchars($row['package_name']) ?>"
                                    data-amount="<?= number_format($row['package_price'], 2, '.', '') ?>"
                                    data-date="<?= date('F j, Y', strtotime($row['booking_date'])) ?>"
                                    data-paid="<?= isset($row['latest_payment_amount']) && $row['latest_payment_amount'] !== null
                                        ? number_format($row['latest_payment_amount'], 2, '.', '')
                                        : '' ?>"
                                    data-paystatus="<?= $row['payment_status'] ? ucfirst($row['payment_status']) : 'Unpaid' ?>"
                                    data-status="<?= ucfirst($row['status']) ?>"
                                >
                                    <i class="fa fa-eye"></i> View Booking
                                </button>
                            <a href="receipt.php?booking_id=<?= $row['booking_id'] ?>" class="action-btn print-btn" target="_blank">
                                <i class="fa fa-print"></i> Print Receipt
                            </a>
                        <?php elseif ($status == 'cancelled' || $status == 'rejected'): ?>
                            <span class="action-btn cancelled-btn" style="cursor:not-allowed;opacity:.75;background:#ececec;color:#888;">
                                <i class="fa fa-ban"></i> Cancelled
                            </span>
                        <?php else: ?>
                            <span style="color:#bbb;">N/A</span>
                        <?php endif; ?>
                    </td>


                            </tr>
                        <?php $row_number++; endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:#aaa;">No bookings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </main>

    <div id="deposit-modal" class="custom-modal">
  <div class="modal-content">
    <span class="close-btn" id="close-deposit-modal">&times;</span>
    <h2 class="modal-title">Deposit Payment</h2>

    <!-- Booking Details Display -->
    <div class="booking-summary" style="margin-bottom: 1em;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
        <span style="font-weight: bold; color: #177687;">Package</span>
        <span id="display-package" style="color: #173d54;">-</span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
        <span style="font-weight: bold; color: #177687;">Date Booked</span>
        <span id="display-date" style="color: #173d54;">-</span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
        <span style="font-weight: bold; color: #177687;">Amount</span>
        <span id="display-amount" style="color: #173d54;">-</span>
      </div>
    </div>

    <form id="deposit-form" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="booking_id" id="deposit-booking-id" />

      <!-- GCash Note -->
      <p style="color:#126f8d;margin-bottom:5px;font-size:0.95em;">
        <em>Note: Only GCash payment is accepted.</em>
      </p>

      <div class="modal-group">
        <label>Downpayment Amount (₱)</label>
        <input type="number" min="1" name="amount" id="deposit-amount" required placeholder="Enter amount"
               style="width: 100%;">
      </div>

      <div class="modal-group">
        <label>Reference Number <span style="color:#888;font-size:0.9em;">(13-digit number only)</span></label>
        <input type="text"
       name="reference_number"
       id="deposit-reference"
       maxlength="13"
       pattern="\d{13}"
       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
       placeholder="Enter reference number"
       style="width: 100%; border-radius: 12px; padding: 10px; border: 1px solid #ccc; font-size: 1em;">

      </div>

      <div class="modal-group">
        <label>Proof of Payment <span style="color:#888;font-size:0.9em;">(jpg/png, max 3MB)</span></label>
        <input type="file" name="proof" id="deposit-proof" accept="image/png, image/jpeg" required>
        <div id="proof-preview" style="margin-top:10px;display:none;">
          <img id="preview-img" style="max-width:140px;max-height:120px;border-radius:6px;box-shadow:0 0 8px #b9eafc50;">
        </div>
      </div>

      <div style="display: flex; gap: 10px; justify-content: space-between; margin-top: 20px;">
  <button type="submit" class="modal-submit-btn" style="flex:1;">Send Payment</button>
  <button type="button" class="modal-submit-btn" id="cancel-deposit-btn" style="flex:1; background:#ddd; color:#444;">Cancel</button>
</div>

    </form>
  </div>
</div>

<div id="view-details-modal" class="custom-modal">
  <div class="modal-content" style="max-width:420px">
    <span class="close-btn" id="close-view-details-modal">&times;</span>
    <h2 class="modal-title">Booking Details</h2>
    <div style="padding:4px 0 12px 0">
      <div style="margin-bottom:13px;display:flex;justify-content:space-between">
        <span style="color:#16687b;font-weight:600">Package</span>
        <span id="view-package" style="color:#12747b"></span>
      </div>
      <div style="margin-bottom:13px;display:flex;justify-content:space-between">
        <span style="color:#16687b;font-weight:600">Booking Amount</span>
        <span id="view-amount" style="color:#12747b"></span>
      </div>
      <div style="margin-bottom:13px;display:flex;justify-content:space-between">
        <span style="color:#16687b;font-weight:600">Booked Date</span>
        <span id="view-date" style="color:#12747b"></span>
      </div>
      <div style="margin-bottom:13px;display:flex;justify-content:space-between">
        <span style="color:#16687b;font-weight:600">Downpayment Amount</span>
        <span id="view-downpayment" style="color:#12747b"></span>
      </div>
      <div style="margin-bottom:13px;display:flex;justify-content:space-between">
        <span style="color:#16687b;font-weight:600">Status</span>
        <span id="view-status" style="color:#12747b"></span>
      </div>
    </div>
    <button class="modal-submit-btn" id="close-view-btn" style="background:#eee;color:#177687;">Close</button>
  </div>
</div>

<div id="view-booking-modal" class="custom-modal">
  <div class="modal-content">
    <span class="close-btn" id="close-view-booking-modal">&times;</span>
    <h2 class="modal-title">Booking Summary</h2>

    <div class="rows">
      <div class="row"><span class="label">Package</span><span class="val" id="vb-package"></span></div>
      <div class="row"><span class="label">Booking Amount</span><span class="val" id="vb-amount"></span></div>
      <div class="row"><span class="label">Booked Date</span><span class="val" id="vb-date"></span></div>
      <div class="row"><span class="label">Amount Paid</span><span class="val" id="vb-paid"></span></div>
      <div class="row"><span class="label">Payment Status</span><span class="val" id="vb-paystatus"></span></div>
      <div class="row"><span class="label">Booking Status</span><span class="val" id="vb-status"></span></div>
    </div>

    <button class="modal-submit-btn" id="vb-close-btn">Close</button>
  </div>
</div>

<!-- Cancel Modal handled by SweetAlert2 only -->
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

const depositModal = document.getElementById('deposit-modal');
const closeDepositModalBtn = document.getElementById('close-deposit-modal');
const depositForm = document.getElementById('deposit-form');

document.querySelectorAll('.open-deposit-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        depositModal.classList.add('active');
        document.getElementById('deposit-booking-id').value = btn.dataset.bookingId;
        depositForm.reset();
        document.getElementById('proof-preview').style.display = 'none';
    });
});
closeDepositModalBtn.onclick = () => depositModal.classList.remove('active');
window.onclick = (e) => { if (e.target === depositModal) depositModal.classList.remove('active'); };

// Image preview
document.getElementById('deposit-proof').addEventListener('change', function(){
    const file = this.files[0];
    if (file && (file.type === "image/jpeg" || file.type === "image/png")) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('proof-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('proof-preview').style.display = 'none';
    }
});

// -------- Deposit Form AJAX --------
depositForm.onsubmit = function(e){
    e.preventDefault();
    const formData = new FormData(depositForm);
    Swal.fire({title:'Sending Payment...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
    fetch('pay.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            Swal.fire({
  icon:'success',
  title:'Payment Submitted',
  text:data.msg || 'We will verify your payment.',
  confirmButtonColor:'#1e8fa2'
}).then(() => {
  window.location.reload(); // 🔁 Refreshes page after user clicks OK
});

depositModal.classList.remove('active');

            // Optional: Update row payment status
            const row = document.getElementById('booking-row-'+formData.get('booking_id'));
            if(row) row.querySelector('td[data-label="Payment Status"]').textContent = 'Pending';
        } else {
            Swal.fire({icon:'error',title:'Failed',text:data.msg||'Payment failed.',confirmButtonColor:'#1e8fa2'});
        }
    }).catch(()=>{
        Swal.fire({icon:'error',title:'Server Error',text:'Try again later.',confirmButtonColor:'#1e8fa2'});
    });
};
// Cancel button inside the modal
document.getElementById('cancel-deposit-btn').onclick = function() {
    depositModal.classList.remove('active');
};

// -------- Cancel Button Handler with SweetAlert2 --------
document.querySelectorAll('.open-cancel-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        Swal.fire({
            title: 'Cancel this booking?',
            text: "Are you sure? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e34b4b',
            cancelButtonColor: '#1e8fa2',
            confirmButtonText: 'Yes, cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX to cancel_booking.php
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'booking_id=' + encodeURIComponent(btn.dataset.bookingId)
                })
                .then(r => r.json())
                .then(data => {
                    if(data.success){
                        Swal.fire({icon:'success',title:'Cancelled',text:data.msg||'Booking cancelled.',confirmButtonColor:'#1e8fa2'});
                        // Optional: Update status
                        const row = document.getElementById('booking-row-'+btn.dataset.bookingId);
                        if(row) {
                            row.querySelector('td[data-label="Booking Status"]').innerHTML = '<span class="status-cancelled">Cancelled</span>';
                            row.querySelector('td[data-label="Action"]').innerHTML = '<span style="color:#bbb;">N/A</span>';
                        }
                    } else {
                        Swal.fire({icon:'error',title:'Failed',text:data.msg||'Failed to cancel.',confirmButtonColor:'#1e8fa2'});
                    }
                }).catch(()=>{
                    Swal.fire({icon:'error',title:'Server Error',text:'Try again later.',confirmButtonColor:'#1e8fa2'});
                });
            }
        });
    });
});

document.querySelectorAll('.open-deposit-modal').forEach(button => {
  button.addEventListener('click', function() {
    const bookingId = this.getAttribute('data-booking-id');
    const bookingRow = document.querySelector(`#booking-row-${bookingId}`);

    const packageName = bookingRow.getAttribute('data-package'); // Set this attribute via PHP
    const bookingDate = bookingRow.getAttribute('data-date');
    const amount = bookingRow.getAttribute('data-amount');

    // Set display fields
    document.getElementById('deposit-booking-id').value = bookingId;
    document.getElementById('display-package').textContent = packageName || '-';
    document.getElementById('display-date').textContent = bookingDate || '-';
    document.getElementById('display-amount').textContent = amount ? '₱ ' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '-';

    // Show modal
    depositModal.classList.add('active');
  });
});

const viewDetailsModal = document.getElementById('view-details-modal');
const closeViewDetailsBtn = document.getElementById('close-view-details-modal');
const closeViewBtn = document.getElementById('close-view-btn');

// Open modal when button is clicked
document.querySelectorAll('.open-view-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('view-package').textContent = this.dataset.package || '-';
        document.getElementById('view-amount').textContent = this.dataset.amount ? '₱ ' + parseFloat(this.dataset.amount).toLocaleString('en-PH', {minimumFractionDigits:2}) : '-';
        document.getElementById('view-date').textContent = this.dataset.date || '-';
        document.getElementById('view-downpayment').textContent = this.dataset.downpayment ? '₱ ' + this.dataset.downpayment : '-';
        document.getElementById('view-status').textContent = this.dataset.status || '-';

        viewDetailsModal.classList.add('active');
    });
});

// Close modal handlers
closeViewDetailsBtn.onclick = () => viewDetailsModal.classList.remove('active');
closeViewBtn.onclick = () => viewDetailsModal.classList.remove('active');
window.addEventListener('click', function(e) {
    if (e.target === viewDetailsModal) viewDetailsModal.classList.remove('active');
});

// === VIEW BOOKING MODAL ===
const viewBookingModal = document.getElementById('view-booking-modal');
const closeViewBookingX = document.getElementById('close-view-booking-modal');
const closeViewBookingBtn = document.getElementById('vb-close-btn');

document.querySelectorAll('.open-view-booking-modal').forEach(btn => {
  btn.addEventListener('click', function(){
    document.getElementById('vb-package').textContent = this.dataset.package || '-';
    document.getElementById('vb-amount').textContent = this.dataset.amount ? '₱ ' + parseFloat(this.dataset.amount).toLocaleString('en-PH', {minimumFractionDigits:2}) : '-';
    document.getElementById('vb-date').textContent   = this.dataset.date || '-';
    document.getElementById('vb-paid').textContent   = this.dataset.paid ? '₱ ' + parseFloat(this.dataset.paid).toLocaleString('en-PH', {minimumFractionDigits:2}) : '-';
    document.getElementById('vb-paystatus').textContent = this.dataset.paystatus || '-';
    document.getElementById('vb-status').textContent = this.dataset.status || '-';
    viewBookingModal.classList.add('active');
  });
});
closeViewBookingX.onclick = () => viewBookingModal.classList.remove('active');
closeViewBookingBtn.onclick = () => viewBookingModal.classList.remove('active');
window.addEventListener('click', e => { if (e.target === viewBookingModal) viewBookingModal.classList.remove('active'); });

</script>
</body>
</html>