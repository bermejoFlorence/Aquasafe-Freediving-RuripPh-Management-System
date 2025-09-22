<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']); exit;
}

require_once '../db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

// Inputs
$user_id    = isset($data['user_id']) ? (int)$data['user_id'] : 0;          // client id (creator)
$package_id = isset($data['package_id']) ? (int)$data['package_id'] : 0;
$date_raw   = $data['booking_date'] ?? '';
$note       = $data['note'] ?? '';

if (!$user_id || !$package_id || !$date_raw) {
    echo json_encode(['success' => false, 'message' => 'Incomplete data.']); exit;
}

/* Normalize date (YYYY-MM-DD) */
$booking_date = date('Y-m-d', strtotime($date_raw));
if (!$booking_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking date.']); exit;
}

/* Check slot limit (max 5 approved+pending for that DATE) */
$slotStmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM booking
    WHERE DATE(booking_date) = ? AND status IN ('pending','approved')
");
$slotStmt->bind_param('s', $booking_date);
$slotStmt->execute();
$slotRes = $slotStmt->get_result()->fetch_assoc();
$slotStmt->close();

if (($slotRes['cnt'] ?? 0) >= 5) {
    echo json_encode(['success' => false, 'message' => 'Sorry, slots are full for this date.']); exit;
}

/* Start transaction: create booking + notify admins */
$conn->begin_transaction();

try {
    $status = 'pending';
    $stmt = $conn->prepare("
        INSERT INTO booking (user_id, package_id, booking_date, status, note)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
    $stmt->bind_param('iisss', $user_id, $package_id, $booking_date, $status, $note);
    if (!$stmt->execute()) throw new Exception('Booking failed: '.$stmt->error);

    $booking_id = $stmt->insert_id;
    $stmt->close();

    /* Get package name for message */
    $pkg_name = '';
    $pkgQ = $conn->prepare("SELECT name FROM package WHERE package_id = ?");
    $pkgQ->bind_param('i', $package_id);
    $pkgQ->execute();
    $pkgQ->bind_result($pkg_name);
    $pkgQ->fetch();
    $pkgQ->close();

    /* Build notification (admin recipients) */
    $full_name = $_SESSION['full_name'] ?? 'Client';
    $booking_date_readable = date('F j, Y', strtotime($booking_date));
    $notif_msg  = "Booking received from $full_name for $pkg_name on $booking_date_readable.";
    $notif_type = 'booking';
    $is_read    = 0;

    /* Fetch all admins */
    $admins = [];
    $admRes = $conn->query("SELECT user_id FROM user WHERE role='admin'");
    while ($row = $admRes->fetch_assoc()) $admins[] = (int)$row['user_id'];

    /* Insert one notification per admin (recipient = admin user_id) */
    if (!empty($admins)) {
        $notifStmt = $conn->prepare("
            INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        foreach ($admins as $admin_id) {
            $notifStmt->bind_param('issii', $admin_id, $notif_type, $notif_msg, $booking_id, $is_read);
            if (!$notifStmt->execute()) throw new Exception('Notify admin failed: '.$notifStmt->error);
        }
        $notifStmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'booking_id' => $booking_id
    ]);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
}
