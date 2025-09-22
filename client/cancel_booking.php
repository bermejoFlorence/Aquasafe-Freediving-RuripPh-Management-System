<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success'=>false,'msg'=>'Access denied.']); exit;
}
include '../db_connect.php';

$booking_id = $_POST['booking_id'] ?? '';
$user_id = $_SESSION['user_id'];

if(!$booking_id){
    echo json_encode(['success'=>false,'msg'=>'No booking ID.']); exit;
}
// Only allow cancelling user's own booking and if not cancelled yet
$stmt = $conn->prepare("UPDATE booking SET status='cancelled' WHERE booking_id=? AND user_id=? AND status!='cancelled'");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();

if($stmt->affected_rows>0){
    echo json_encode(['success'=>true,'msg'=>'Booking cancelled.']);
} else {
    echo json_encode(['success'=>false,'msg'=>'Cancel failed.']);
}
?>
