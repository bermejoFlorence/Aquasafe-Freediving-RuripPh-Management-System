<?php
// admin/admin_get_notifications.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(401);
  echo json_encode([]);
  exit;
}

$admin_id = (int)$_SESSION['user_id'];
$limit    = 20;
$only     = trim($_GET['only'] ?? '');   // e.g. 'report' for the Reports tab

// ---- base SQL (we'll tack on the optional filter below) ----
$sql = "
SELECT
  n.notification_id,
  n.type,
  n.message,
  n.related_id,
  n.is_read,
  n.created_at,
  n.user_id AS notif_user_id,           -- personal vs broadcast

  -- FOR NEW/BROADCAST POST (type='forum')
  fp.title              AS forum_title,
  fcp.slug              AS forum_cat_slug,
  fp.user_id            AS forum_author_id,

  -- OPTIONAL SUPPORT: FOR REPLY (type='forum_reply')
  rpp.post_id           AS reply_post_id,
  rpp.title             AS reply_post_title,
  rfc.slug              AS reply_cat_slug,

  -- NEW: JOIN report details when type='report'
  r.report_id           AS report_id,
  r.status              AS report_status,
  r.target_type         AS report_target_type,
  r.post_id             AS report_post_id,
  r.target_id           AS report_target_id
FROM notification n

LEFT JOIN forum_post fp
  ON (n.type = 'forum' AND n.related_id = fp.post_id)
LEFT JOIN forum_category fcp
  ON (fp.category_id = fcp.category_id)

LEFT JOIN forum_post_comment rpc
  ON (n.type = 'forum_reply' AND n.related_id = rpc.comment_id)
LEFT JOIN forum_post rpp
  ON (rpc.post_id = rpp.post_id)
LEFT JOIN forum_category rfc
  ON (rpp.category_id = rfc.category_id)

-- NEW: only matches when n.type='report'
LEFT JOIN forum_report r
  ON (n.type = 'report' AND n.related_id = r.report_id)

WHERE
  (
    n.user_id = ?                                 -- personal to this admin
    OR (n.user_id IS NULL AND n.type = 'forum')   -- broadcast new forum post
  )
  AND n.type IN (
    'booking_status','payment_status','forum',
    'booking_reschedule','booking','payment',
    'forum_reply','report'                         -- ← NEW
  )
  -- keep the author-hide only for forum broadcasts
  AND NOT (n.type = 'forum' AND fp.user_id = ?)
";

if ($only === 'report') {
  $sql .= " AND n.type = 'report' ";
}

$sql .= " ORDER BY n.is_read ASC, n.created_at DESC LIMIT ? ";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $admin_id, $admin_id, $limit);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $type = (string)$row['type'];
  $link = '';
  $msg  = (string)($row['message'] ?? '');

  if ($type === 'booking_status' && !empty($row['related_id'])) {
    $link = 'view_booking.php?booking_id=' . (int)$row['related_id'];

  } elseif ($type === 'payment_status' || $type === 'payment') {
    $link = 'payments.php';

  } elseif ($type === 'forum' && !empty($row['related_id'])) {
    if (!is_null($row['notif_user_id'])) {
      // personal forum notif (comment/reply) → full thread
      $link = 'view_post.php?post_id=' . (int)$row['related_id'];
    } else {
      // broadcast (new post) → feed + highlight
      $slug = $row['forum_cat_slug'] ?: 'all';
      $link = 'forum.php?cat=' . urlencode($slug) . '&highlight=' . (int)$row['related_id'];
    }
    if ($msg === '') {
      $title = trim((string)($row['forum_title'] ?? ''));
      $msg = 'New forum post: ' . ($title !== '' ? $title : '(untitled)');
    }

  } elseif ($type === 'forum_reply' && !empty($row['related_id'])) {
    $postId  = (int)($row['reply_post_id'] ?? 0);
    $comment = (int)$row['related_id']; // reply comment_id
    if ($postId > 0) $link = 'view_post.php?post_id=' . $postId . '&focus=' . $comment;
    if ($msg === '') {
      $title = trim((string)($row['reply_post_title'] ?? ''));
      $msg = 'New reply on: ' . ($title !== '' ? $title : 'your post');
    }

  } elseif ($type === 'report') {
    // NEW: link straight to the moderation page
    $rid  = (int)($row['report_id'] ?? $row['related_id']);
    $link = 'reports.php?rid=' . $rid;
    if ($msg === '') {
      $what = ($row['report_target_type'] ?? 'post');
      $msg  = 'New report on ' . $what . ' (ID ' . $rid . ')';
    }
  }

  $out[] = [
    'notification_id' => (int)$row['notification_id'],
    'type'            => $type,
    'message'         => $msg,
    'related_id'      => (int)$row['related_id'],
    'is_read'         => (int)$row['is_read'],
    'created_at'      => $row['created_at'],
    'forum_cat_slug'  => $row['forum_cat_slug'] ?: ($row['reply_cat_slug'] ?? 'all'),
    'forum_title'     => $row['forum_title'] ?: ($row['reply_post_title'] ?? ''),
    'report_status'   => $row['report_status'] ?? null,       // ← for badges on Reports tab
    'link'            => $link,
  ];
}

echo json_encode($out);
