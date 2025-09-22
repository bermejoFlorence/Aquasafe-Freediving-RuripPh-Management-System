<?php
// client/mark_notification_read.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$notification_id = (int)($_POST['notification_id'] ?? 0);

if (!$user_id || !$notification_id) { echo json_encode(['success'=>false]); exit; }

$stmt = $conn->prepare("UPDATE notification SET is_read=1 WHERE notification_id=? AND user_id=?");
$stmt->bind_param('ii', $notification_id, $user_id);
$ok = $stmt->execute();
echo json_encode(['success' => (bool)$ok]);
