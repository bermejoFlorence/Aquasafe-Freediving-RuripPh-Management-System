<?php
include '../db_connect.php';

// New Bookings (pending)
$sql1 = "SELECT COUNT(*) as cnt FROM booking WHERE status = 'pending'";
$result1 = $conn->query($sql1);
$new_bookings = $result1->fetch_assoc()['cnt'];

// Pending Approvals (processing)
$sql2 = "SELECT COUNT(*) as cnt FROM booking WHERE status = 'processing'";
$result2 = $conn->query($sql2);
$pending_approvals = $result2->fetch_assoc()['cnt'];

// Total Users (clients only)
$sql3 = "SELECT COUNT(*) as cnt FROM user WHERE role = 'client'";
$result3 = $conn->query($sql3);
$total_clients = $result3->fetch_assoc()['cnt'];

echo json_encode([
    'new_bookings' => (int)$new_bookings,
    'pending_approvals' => (int)$pending_approvals,
    'total_clients' => (int)$total_clients
]);
?>
