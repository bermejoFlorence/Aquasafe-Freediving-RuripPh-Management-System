<?php
include '../db_connect.php';
$booking_id = intval($_GET['booking_id']);
$sql = "SELECT b.booking_id, b.booking_date, u.full_name, pk.name AS package_name, p.amount, p.status AS payment_status, p.proof
        FROM booking b
        JOIN user u ON u.user_id = b.user_id
        JOIN package pk ON pk.package_id = b.package_id
        LEFT JOIN payment p ON p.booking_id = b.booking_id
        WHERE b.booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
?>
<div>
    <div style="margin-bottom:8px;"><b>Client:</b> <?= htmlspecialchars($row['full_name']) ?></div>
    <div style="margin-bottom:8px;"><b>Booking Date:</b> <?= htmlspecialchars($row['booking_date']) ?></div>
    <div style="margin-bottom:8px;"><b>Package:</b> <?= htmlspecialchars($row['package_name']) ?></div>
    <div style="margin-bottom:8px;"><b>Amount Paid:</b> ₱ <?= number_format($row['amount'],2) ?></div>
    <div style="margin-bottom:8px;"><b>Payment Status:</b> <?= htmlspecialchars(ucfirst($row['payment_status'])) ?></div>
    <div style="margin-bottom:8px;"><b>Proof:</b><br>
      <img src="../uploads/<?= htmlspecialchars($row['proof']) ?>" style="max-width:100%; border-radius:8px; margin-top:7px;">
    </div>
</div>
