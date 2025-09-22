<?php
// admin/forum_comment_list.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Not authenticated']); exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
$page    = max(1, (int)($_POST['page'] ?? 1));
$limit   = min(50, max(5, (int)($_POST['limit'] ?? 10))); // 5..50
$offset  = ($page - 1) * $limit;

if ($post_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid post_id']); exit;
}

// optional: check post exists
if ($st = $conn->prepare("SELECT 1 FROM forum_post WHERE post_id=? LIMIT 1")) {
  $st->bind_param('i', $post_id);
  $st->execute();
  $ex = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$ex) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Post not found']); exit;
  }
}

$sql = "
  SELECT
    c.comment_id, c.body,
    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
    u.user_id, u.full_name,
    COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic
  FROM forum_post_comment c
  JOIN user u ON u.user_id = c.user_id
  WHERE c.post_id = ?
  ORDER BY c.created_at ASC, c.comment_id ASC
  LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$st->bind_param('iii', $post_id, $limit, $offset);
$st->execute();
$res = $st->get_result();

$comments = [];
while ($r = $res->fetch_assoc()) { $comments[] = $r; }
$st->close();

// detect has_more (cheap peek)
$has_more = false;
if ($st2 = $conn->prepare("SELECT 1 FROM forum_post_comment WHERE post_id=? LIMIT 1 OFFSET ?")) {
  $peekOffset = $offset + $limit;
  $st2->bind_param('ii', $post_id, $peekOffset);
  $st2->execute();
  $has_more = (bool)$st2->get_result()->fetch_assoc();
  $st2->close();
}

echo json_encode([
  'ok'       => true,
  'comments' => $comments,
  'has_more' => $has_more,
  'next_page'=> $has_more ? ($page + 1) : null
]);
