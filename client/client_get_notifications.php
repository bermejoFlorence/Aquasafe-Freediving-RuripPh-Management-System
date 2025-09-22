<?php
// client/client_get_notifications.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
  http_response_code(401);
  echo json_encode([]);
  exit;
}

$client_id = (int)$_SESSION['user_id'];

$sql = "
SELECT
  n.notification_id,
  n.type,
  n.message,
  n.related_id,
  n.is_read,
  n.created_at,

  -- forum info (for deep-linking)
  fp.title            AS forum_title,
  fc.slug             AS forum_cat_slug
FROM notification n
LEFT JOIN forum_post fp
  ON (n.type = 'forum' AND n.related_id = fp.post_id)
LEFT JOIN forum_category fc
  ON (fp.category_id = fc.category_id)
WHERE n.user_id = ?
  AND n.type IN ('booking_status','payment_status','forum')
ORDER BY n.is_read ASC, n.created_at DESC
LIMIT 20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $link = '';

  if ($row['type'] === 'booking_status' && !empty($row['related_id'])) {
    $link = 'view_booking.php?booking_id=' . (int)$row['related_id'];
  } elseif ($row['type'] === 'payment_status') {
    $link = 'payments.php';
  } elseif ($row['type'] === 'forum' && !empty($row['related_id'])) {
    $slug = $row['forum_cat_slug'] ?: 'all';
    $link = 'forum.php?cat=' . urlencode($slug) . '&highlight=' . (int)$row['related_id'];
  }

  $out[] = [
    'notification_id' => (int)$row['notification_id'],
    'type'            => $row['type'],
    'message'         => $row['message'],
    'related_id'      => (int)$row['related_id'],
    'is_read'         => (int)$row['is_read'],
    'created_at'      => $row['created_at'],
    'forum_title'     => $row['forum_title'] ?? '',
    'link'            => $link,
  ];
}

echo json_encode($out);
