<?php
// admin/mark_notification_read.php
session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$notification_id = (int)($_POST['notification_id'] ?? 0);

if ($notification_id > 0) {
    $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = ?");
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
}
