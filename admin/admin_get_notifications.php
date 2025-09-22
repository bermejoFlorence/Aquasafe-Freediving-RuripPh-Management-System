<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode([]); exit;
}
$admin_id = (int)($_SESSION['user_id'] ?? 0);
if (!$admin_id) { echo json_encode([]); exit; }

$sql = "
SELECT 
    n.*,

    -- booking
    b.booking_date,
    p.name AS package_name,

    -- payment (resolve booking/package via payment.related_id = payment_id)
    (SELECT booking_id FROM payment WHERE payment_id = n.related_id LIMIT 1) AS pay_booking_id,
    (SELECT booking_date FROM booking WHERE booking_id = (SELECT booking_id FROM payment WHERE payment_id = n.related_id LIMIT 1)) AS pay_booking_date,
    (SELECT name FROM package WHERE package_id = (
        SELECT package_id FROM booking WHERE booking_id = (SELECT booking_id FROM payment WHERE payment_id = n.related_id LIMIT 1)
    )) AS pay_package_name,

    -- forum
    fp.title AS forum_title,
    fc2.slug AS forum_slug
FROM notification n
LEFT JOIN booking b        ON (n.type='booking' AND n.related_id = b.booking_id)
LEFT JOIN package p        ON b.package_id = p.package_id
LEFT JOIN forum_post fp    ON (n.type='forum' AND n.related_id = fp.post_id)
LEFT JOIN forum_category fc2 ON (n.type='forum' AND fc2.category_id = fp.category_id)
WHERE n.type IN ('booking','payment','forum')
  AND n.user_id = ?
ORDER BY n.is_read ASC, n.created_at DESC
LIMIT 20
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([]); exit;
}
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $package_name = $row['type'] === 'payment'
        ? ($row['pay_package_name'] ?: '')
        : ($row['package_name']   ?: '');
    $booking_date = $row['type'] === 'payment'
        ? ($row['pay_booking_date'] ?: '')
        : ($row['booking_date']     ?: '');

    // default links per type
    $link = 'bookings.php';
    if ($row['type'] === 'forum') {
        $slug = $row['forum_slug'] ?: 'all';
        $link = 'forum.php?cat=' . urlencode($slug) . '&highlight=' . (int)$row['related_id'];
    }

    $notifications[] = [
        'notification_id' => (int)$row['notification_id'],
        'message'         => $row['message'],
        'created_at'      => $row['created_at'],
        'type'            => $row['type'],
        'related_id'      => (int)$row['related_id'],
        'package_name'    => $package_name,
        'booking_date'    => $booking_date,
        'forum_title'     => $row['forum_title'] ?? '',
        'is_read'         => (int)$row['is_read'],
        'link'            => $link,
    ];
}

echo json_encode($notifications);
