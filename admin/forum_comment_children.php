<?php
// admin/forum_comment_children.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Not authenticated']); exit;
}

$post_id   = (int)($_POST['post_id'] ?? 0);
$parent_id = (int)($_POST['parent_id'] ?? 0);
$page      = max(1, (int)($_POST['page'] ?? 1));
$limit     = min(100, max(5, (int)($_POST['limit'] ?? 50)));
$offset    = ($page - 1) * $limit;

if ($post_id <= 0 || $parent_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid post/parent']); exit;
}

/* optional: validate that parent exists and belongs to post */
if ($st0 = $conn->prepare("SELECT 1 FROM forum_post_comment WHERE comment_id=? AND post_id=? LIMIT 1")) {
  $st0->bind_param('ii', $parent_id, $post_id);
  $st0->execute();
  if (!$st0->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Parent comment not found']); exit;
  }
  $st0->close();
}

$sql = "
  SELECT
    c.comment_id, c.body,
    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
    u.user_id, u.full_name,
    COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic
  FROM forum_post_comment c
  JOIN user u ON u.user_id = c.user_id
  WHERE c.post_id = ? AND c.parent_id = ?
  ORDER BY c.created_at ASC, c.comment_id ASC
  LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$st->bind_param('iiii', $post_id, $parent_id, $limit, $offset);
$st->execute();
$res = $st->get_result();

$replies = [];
while ($r = $res->fetch_assoc()) { $replies[] = $r; }
$st->close();

echo json_encode(['ok' => true, 'replies' => $replies]);
