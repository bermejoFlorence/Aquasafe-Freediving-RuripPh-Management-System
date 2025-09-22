<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
    echo json_encode(['success'=>false,'msg'=>'Access denied.']); exit;
}

require_once '../db_connect.php';

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$amount     = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
$user_id    = (int)$_SESSION['user_id'];

if (!$booking_id || !$amount || !isset($_FILES['proof'])) {
    echo json_encode(['success'=>false,'msg'=>'Missing fields.']); exit;
}

/* ---------- File upload validation ---------- */
$allowed = ['image/jpeg','image/png'];
$max_size = 3 * 1024 * 1024;

if (!in_array($_FILES['proof']['type'], $allowed) || $_FILES['proof']['size'] > $max_size) {
    echo json_encode(['success'=>false,'msg'=>'Invalid file.']); exit;
}

$ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png'])) {
    echo json_encode(['success'=>false,'msg'=>'Invalid file extension.']); exit;
}

$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

$filename = 'proof_' . uniqid('', true) . '.' . $ext;
$dest_path = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dest_path)) {
    echo json_encode(['success'=>false,'msg'=>'Failed to upload file.']); exit;
}

/* ---------- Start transaction: insert payment, update booking, notify admins ---------- */
$conn->begin_transaction();

try {
    // 1) Insert payment (pending)
    $stmt = $conn->prepare("
        INSERT INTO payment (booking_id, amount, proof, payment_date, status)
        VALUES (?, ?, ?, NOW(), 'pending')
    ");
    if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
    $stmt->bind_param("ids", $booking_id, $amount, $filename);
    if (!$stmt->execute()) throw new Exception('DB error: '.$stmt->error);
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // 2) Move booking to 'processing' (owned by this client)
    $updateStmt = $conn->prepare("UPDATE booking SET status='processing' WHERE booking_id=? AND user_id=?");
    $updateStmt->bind_param("ii", $booking_id, $user_id);
    if (!$updateStmt->execute()) throw new Exception('Failed to update booking.');
    $updateStmt->close();

    // 3) Fetch booking + package to build message
    $booking_stmt = $conn->prepare("SELECT booking_date, package_id FROM booking WHERE booking_id=?");
    $booking_stmt->bind_param("i", $booking_id);
    $booking_stmt->execute();
    $booking_stmt->bind_result($booking_date, $package_id);
    $booking_stmt->fetch();
    $booking_stmt->close();

    $package_stmt = $conn->prepare("SELECT name FROM package WHERE package_id=?");
    $package_stmt->bind_param("i", $package_id);
    $package_stmt->execute();
    $package_stmt->bind_result($package_name);
    $package_stmt->fetch();
    $package_stmt->close();

    $full_name = $_SESSION['full_name'] ?? 'Client';
    $booking_date_fmt = $booking_date ? date('F j, Y', strtotime($booking_date)) : '';
    $notif_msg  = "Downpayment received from $full_name for $package_name on $booking_date_fmt.";
    $notif_type = 'payment';
    $is_read    = 0;

    // 4) Fetch ALL admins (recipients)
    $admins = [];
    $admRes = $conn->query("SELECT user_id FROM user WHERE role='admin'");
    while ($row = $admRes->fetch_assoc()) { $admins[] = (int)$row['user_id']; }

    // 5) Insert one notification per admin (recipient = admin user_id)  ✅ CHANGED
    if (!empty($admins)) {
        $notif_stmt = $conn->prepare("
            INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        foreach ($admins as $admin_id) {
            $notif_stmt->bind_param("issii", $admin_id, $notif_type, $notif_msg, $payment_id, $is_read);
            if (!$notif_stmt->execute()) throw new Exception('Notify admin failed: '.$notif_stmt->error);
        }
        $notif_stmt->close();
    }

    $conn->commit();
    echo json_encode(['success'=>true, 'msg'=>'We will verify your payment.']);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    // Try to remove uploaded file if we failed post-insert
    if (is_file($dest_path)) { @unlink($dest_path); }
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
}
