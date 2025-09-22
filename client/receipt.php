<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
  die('Access denied.');
}
require_once '../db_connect.php';

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
  die('Missing or invalid booking ID.');
}
$booking_id = (int)$_GET['booking_id'];
$user_id    = (int)$_SESSION['user_id'];

/* 1) Booking + client + package (with ownership) */
$sql = "SELECT 
    b.booking_id, b.booking_date, b.status AS booking_status,
    u.user_id, u.full_name, u.email_address,
    pk.name AS package_name, COALESCE(pk.price,0) AS package_price
  FROM booking b
  JOIN user u ON b.user_id = u.user_id
  LEFT JOIN package pk ON b.package_id = pk.package_id
  WHERE b.booking_id = ?
  LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$booking) die('Booking not found.');
if ((int)$booking['user_id'] !== $user_id) die('Access denied.');

/* 2) Add-ons total */
$stmt = $conn->prepare("SELECT COALESCE(SUM(addons_total),0) AS addons_total FROM rental_kit WHERE booking_id=?");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$addons_total = (float)($stmt->get_result()->fetch_assoc()['addons_total'] ?? 0);
$stmt->close();

/* 3) Latest payment + totals (paid/cleared) */
$stmt = $conn->prepare("SELECT payment_id, amount, status, payment_date
                        FROM payment WHERE booking_id=? ORDER BY payment_id DESC LIMIT 1");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$last = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid
                        FROM payment WHERE booking_id=? AND status IN ('paid','cleared')");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$total_paid = (float)($stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
$stmt->close();

/* Derived figures (same logic as admin) */
$package_price = (float)$booking['package_price'];
$grand_total   = $package_price + $addons_total;

$last_amount   = (float)($last['amount'] ?? 0);
$last_date     = $last['payment_date'] ?? null;
$last_id       = isset($last['payment_id']) ? (int)$last['payment_id'] : null;

$prev_paid     = max($total_paid - $last_amount, 0);
$balance_after = max($grand_total - $total_paid, 0);

$payment_type = ($balance_after <= 0.009) ? 'Full Payment'
               : (($prev_paid > 0) ? 'Partial Payment' : 'Downpayment');

function peso($n){ return '₱'.number_format((float)$n, 2); }

$booking_date_f = $booking['booking_date'] ? date("F d, Y", strtotime($booking['booking_date'])) : '—';
$statusLabel    = ucfirst($booking['booking_status'] ?? '—');
$last_date_f    = $last_date ? date("F d, Y", strtotime($last_date)) : '—';
$receipt_id     = $last_id ? 'R-'.str_pad((string)$last_id, 5, '0', STR_PAD_LEFT) : 'B-'.str_pad((string)$booking_id, 5, '0', STR_PAD_LEFT);
$top_date       = $last_date ? date('m/d/Y', strtotime($last_date)) : date('m/d/Y', strtotime($booking['booking_date'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt - RURIP PH</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --ink:#1b2b45; --navy:#224e7a; --line:#2e5f91; --muted:#6b7d90;
  --surface:#fff; --border:#e8eef6; --pill:#2e5f91;
}
*{box-sizing:border-box}
html,body{margin:0;background:#ddd;}
.page{ max-width:820px; margin:0 auto; background:#333; }
.paper{ background:#fff; margin:0 26px; }

/* header */
.header{ padding:26px 26px 10px; position:relative; text-align:center; }
.brand-mark{ width:72px; height:72px; border-radius:50%; object-fit:cover; display:block; margin:0 auto 6px; }
.h-top-info{ position:absolute; right:26px; top:24px; text-align:right; color:#222; font-size:14px; }
.h-top-info .lbl{ color:#666; margin-right:10px; }
.h-title{ font-size:28px; color:#1f3c65; font-weight:800; letter-spacing:.6px; margin:8px 0 12px; }
.h-sub{ font-size:12px; color:#6c7f93; margin-top:-6px; }
.h-rule{ height:10px; background:var(--line); margin:0 0 18px; }

/* meta */
.meta{ padding:0 26px 10px; display:grid; grid-template-columns: 280px 1fr; gap:30px; }
.m-block h5{ margin:0 0 4px; color:#203b60; font-weight:800; font-size:14px; letter-spacing:.3px; }
.m-block .val{ background:#f3f8fd; border-radius:4px; padding:6px 10px; color:#2f4b66; font-size:14px; display:inline-block; min-width:200px; }
.m-block .muted{ color:#7b8ea2; font-size:13px; margin-top:4px; }

/* section title */
.section-title{ padding:16px 26px 8px; color:#2b4766; font-weight:800; font-size:14px; }

/* table */
.table-wrap{ padding:0 26px 14px; }
.table{ width:100%; border-collapse:collapse; font-size:14px; background:#fbfdff; border-radius:6px; overflow:hidden; }
.table th, .table td{ padding:10px 12px; border-bottom:1px solid #edf2f8; }
.table th{ background:#eaf2fb; color:#274a71; text-align:left; font-weight:800; }
.table tr:last-child td{ border-bottom:none; }
.t-right{ text-align:right; }

/* total pill */
.total-pill-row{ padding:14px 26px 4px; display:flex; justify-content:flex-end; }
.total-pill{
  background:var(--pill); color:#fff; border-radius:6px; padding:10px 14px;
  display:inline-flex; align-items:center; gap:14px; min-width:220px;
  justify-content:space-between; font-weight:700;
}

/* footer */
.footer{ padding:18px 26px 26px; font-size:12px; color:#566a80; }

/* print button (screen only) */
.actions{ padding:10px 26px 26px; }
.print-btn{ background:#224e7a; color:#fff; border:none; border-radius:6px; padding:10px 18px; font-weight:700; cursor:pointer; }
.print-btn:active{ transform:translateY(1px); }

@media print{
  html,body{ background:#fff; }
  .page{ background:#fff; }
  .paper{ margin:0; }
  .actions{ display:none; }
}
@media (max-width:640px){
  .meta{ grid-template-columns:1fr; gap:16px; }
  .m-block .val{ min-width:0; display:block; }
  .h-top-info{ position:static; text-align:center; margin-top:6px; }
}
</style>
</head>
<body>
<div class="page">
  <div class="paper">

    <!-- Header -->
    <div class="header">
      <img class="brand-mark" src="../uploads/logo.jpeg" alt="Logo">
      <div class="h-top-info">
        <div><span class="lbl">Receipt ID</span> <strong><?= htmlspecialchars($receipt_id) ?></strong></div>
        <div><span class="lbl">Date</span> <strong><?= htmlspecialchars($top_date) ?></strong></div>
      </div>
      <div class="h-title">Deposit Receipt</div>
      <div class="h-sub">Client Copy</div>
    </div>
    <div class="h-rule"></div>

    <!-- Meta -->
    <div class="meta">
      <div class="m-block">
        <h5>Received From:</h5>
        <div class="val"><?= htmlspecialchars($booking['full_name']) ?></div>
        <div class="muted"><?= htmlspecialchars($booking['email_address']) ?></div>
      </div>
      <div class="m-block">
        <h5>Booking Information</h5>
        <div class="val">Booking # <?= htmlspecialchars($booking['booking_id']) ?></div>
        <div class="muted">Date: <?= htmlspecialchars($booking_date_f) ?> &nbsp; • &nbsp; Status: <?= htmlspecialchars($statusLabel) ?></div>
      </div>
    </div>

    <!-- Payment Information -->
    <div class="section-title">Payment Information</div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:26%;">Type</th>
            <th>Description</th>
            <th class="t-right" style="width:22%;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Package</td>
            <td><?= htmlspecialchars($booking['package_name'] ?? '—') ?></td>
            <td class="t-right"><?= peso($package_price) ?></td>
          </tr>
          <?php if ($addons_total > 0): ?>
          <tr>
            <td>Add-ons</td>
            <td>Equipment / Rental add-ons</td>
            <td class="t-right"><?= peso($addons_total) ?></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td>This Transaction</td>
            <td><?= htmlspecialchars($payment_type) ?><?= $last_date ? ' — '.htmlspecialchars($last_date_f) : '' ?></td>
            <td class="t-right"><?= peso($last_amount) ?></td>
          </tr>
          <tr>
            <td>Total Paid to Date</td>
            <td>Sum of all successful payments</td>
            <td class="t-right"><?= peso($total_paid) ?></td>
          </tr>
          <tr>
            <td>Balance Remaining</td>
            <td>Grand total minus total paid</td>
            <td class="t-right"><?= peso($balance_after) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Total Amount pill (grand total) -->
    <div class="total-pill-row">
      <div class="total-pill">
        <span>Total Amount</span>
        <span><?= peso($grand_total) ?></span>
      </div>
    </div>

    <div class="footer">
      Please bring this receipt on the day of your booking. Thank you for booking with <b>RURIP PH!</b>
    </div>

    <div class="actions">
      <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>

  </div>
</div>
</body>
</html>
