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
$limit     = 20;

$sql = "
SELECT
  n.notification_id,
  n.type,
  n.message,
  n.related_id,
  n.is_read,
  n.created_at,
  n.user_id AS notif_user_id,              -- ✅ identify personal vs broadcast

  -- forum info (for deep-linking / hide-own broadcast)
  fp.title        AS forum_title,
  fc.slug         AS forum_cat_slug,
  fp.user_id      AS forum_author_id
FROM notification n
LEFT JOIN forum_post fp
  ON n.type = 'forum' AND n.related_id = fp.post_id
LEFT JOIN forum_category fc
  ON fp.category_id = fc.category_id
WHERE
  (
    n.user_id = ?                                     -- personal to this client
    OR (n.user_id IS NULL AND n.type = 'forum')       -- broadcast new forum post
  )
  AND n.type IN ('booking_status','payment_status','forum','booking_reschedule','booking','payment')
  AND NOT (n.type = 'forum' AND fp.user_id = ?)       -- hide my own broadcast
ORDER BY n.is_read ASC, n.created_at DESC
LIMIT ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $client_id, $client_id, $limit);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $link = '';

  if ($row['type'] === 'booking_status' && !empty($row['related_id'])) {
    $link = 'view_booking.php?booking_id=' . (int)$row['related_id'];
  } elseif ($row['type'] === 'payment_status' || $row['type'] === 'payment') {
    $link = 'payments.php';
  } elseif ($row['type'] === 'booking_reschedule' && !empty($row['related_id'])) {
    $link = 'view_booking.php?booking_id=' . (int)$row['related_id'];
  } elseif ($row['type'] === 'forum' && !empty($row['related_id'])) {
    if (!is_null($row['notif_user_id'])) {
      // ✅ personal forum notif (comment/reply) → go to the full thread
      $link = 'view_post.php?post_id=' . (int)$row['related_id'];
    } else {
      // ✅ broadcast new post → keep the category feed highlight
      $slug = $row['forum_cat_slug'] ?: 'all';
      $link = 'forum.php?cat=' . urlencode($slug) . '&highlight=' . (int)$row['related_id'];
    }
  }

  // Fallback message for broadcast entries without a message
  $msg = (string)($row['message'] ?? '');
  if ($row['type'] === 'forum' && trim($msg) === '') {
    $title = trim((string)($row['forum_title'] ?? ''));
    $msg   = 'New forum post: ' . ($title !== '' ? $title : '(untitled)');
  }

  $out[] = [
    'notification_id' => (int)$row['notification_id'],
    'type'            => $row['type'],
    'message'         => $msg,
    'related_id'      => (int)$row['related_id'],
    'is_read'         => (int)$row['is_read'],
    'created_at'      => $row['created_at'],
    'forum_title'     => $row['forum_title'] ?? '',
    'forum_cat_slug'  => $row['forum_cat_slug'] ?? 'all',
    'link'            => $link,
  ];
}

echo json_encode($out);
