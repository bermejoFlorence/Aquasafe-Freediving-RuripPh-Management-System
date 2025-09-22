<?php
// admin/settle_payment.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']);
  exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
$booking_id = isset($in['booking_id']) ? (int)$in['booking_id'] : 0;
$amount_in  = isset($in['amount']) ? (float)$in['amount'] : 0;
$notes      = isset($in['notes']) ? trim((string)$in['notes']) : '';

if ($booking_id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Invalid booking_id']);
  exit;
}

require_once '../db_connect.php'; // $conn (mysqli)

// 1) Get base price (via package)
$base_price = 0.0;
$stmt = $conn->prepare("
  SELECT COALESCE(pk.price,0)
  FROM booking b
  LEFT JOIN package pk ON pk.package_id = b.package_id
  WHERE b.booking_id = ?
");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->bind_result($base_price);
if (!$stmt->fetch()) {
  $stmt->close();
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>'Booking not found']);
  exit;
}
$stmt->close();

// 2) Add-ons total
$addons_total = 0.0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(addons_total),0) FROM rental_kit WHERE booking_id = ?");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->bind_result($addons_total);
$stmt->fetch();
$stmt->close();

// 3) Amount already paid
$paid_sum = 0.0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM payment WHERE booking_id = ? AND status='paid'");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->bind_result($paid_sum);
$stmt->fetch();
$stmt->close();

// 4) Compute current balance
$total_due = (float)$base_price + (float)$addons_total;
$current_balance = max(0.0, round($total_due - (float)$paid_sum, 2));

// 5) Clamp incoming amount
$amount = round((float)$amount_in, 2);
if ($amount <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Amount must be greater than zero']);
  exit;
}
if ($current_balance <= 0.0) {
  echo json_encode(['success'=>false,'message'=>'Nothing to pay. Already settled.','new_balance'=>0]);
  exit;
}
if ($amount > $current_balance) {
  $amount = $current_balance; // prevent overpay
}

// 6) Insert payment (store notes in 'proof' column)
$stmt = $conn->prepare("
  INSERT INTO payment (booking_id, amount, proof, status, payment_date)
  VALUES (?,?,?,?, NOW())
");
$status = 'paid';
$stmt->bind_param('idss', $booking_id, $amount, $notes, $status);

if (!$stmt->execute()) {
  $err = $conn->error;
  $stmt->close();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Insert failed: '.$err]);
  exit;
}
$payment_id = $stmt->insert_id;
$stmt->close();

// 7) Recompute new balance & totals
$new_paid_sum = 0.0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM payment WHERE booking_id = ? AND status='paid'");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->bind_result($new_paid_sum);
$stmt->fetch();
$stmt->close();

$new_balance = max(0.0, round($total_due - (float)$new_paid_sum, 2));
$is_fully_paid = ($new_balance <= 0.009);

// Return JSON
echo json_encode([
  'success'       => true,
  'payment_id'    => (int)$payment_id,
  'new_balance'   => (float)$new_balance,
  'paid_total'    => (float)$new_paid_sum,
  'is_fully_paid' => $is_fully_paid
]);
