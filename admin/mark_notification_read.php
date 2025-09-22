<?php
include '../db_connect.php'; // adjust path if needed

$notification_id = intval($_POST['notification_id'] ?? 0);
if ($notification_id > 0) {
    $conn->query("UPDATE notification SET is_read = 1 WHERE notification_id = $notification_id");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
