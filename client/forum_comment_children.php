<?php
// client/forum_comment_children.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Not authenticated']); exit;
}

$post_id   = (int)($_POST['post_id'] ?? 0);
$parent_id = (int)($_POST['parent_id'] ?? 0);
$page      = max(1, (int)($_POST['page'] ?? 1));
$limit     = min(50, max(5, (int)($_POST['limit'] ?? 20)));
$offset    = ($page - 1) * $limit;

if ($post_id <= 0 || $parent_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid parameters']); exit;
}

// validate parent belongs to the post
$st = $conn->prepare("SELECT 1 FROM forum_post_comment WHERE comment_id=? AND post_id=? LIMIT 1");
$st->bind_param('ii', $parent_id, $post_id);
$st->execute();
$ok = (bool)$st->get_result()->fetch_assoc();
$st->close();
if (!$ok) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'message' => 'Parent comment not found']); exit;
}

// fetch replies (ADD: u.role AS user_role)
$sql = "
  SELECT
    c.comment_id,
    c.body,
    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
    u.user_id,
    u.full_name,
    COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
    u.role AS user_role
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
while ($r = $res->fetch_assoc()) {
  $isAdminAuthor = (strtolower($r['user_role'] ?? '') === 'admin');
  $isSelf        = ((int)$r['user_id'] === $user_id);

  $replies[] = [
    'comment_id'  => (int)$r['comment_id'],
    'user_id'     => (int)$r['user_id'],
    'user_role'   => (string)($r['user_role'] ?? ''),
    'full_name'   => (string)($r['full_name'] ?? 'User'),
    'profile_pic' => (string)($r['profile_pic'] ?? 'uploads/default.png'),
    'body'        => (string)($r['body'] ?? ''),
    'created_at'  => (string)($r['created_at'] ?? ''),
    // NEW: convenience flag for the UI
    'can_report'  => (!$isSelf && !$isAdminAuthor),
  ];
}
$st->close();

// has_more peek
$peekOffset = $offset + $limit;
$st2 = $conn->prepare("SELECT 1 FROM forum_post_comment WHERE post_id=? AND parent_id=? LIMIT 1 OFFSET ?");
$st2->bind_param('iii', $post_id, $parent_id, $peekOffset);
$st2->execute();
$has_more = (bool)$st2->get_result()->fetch_assoc();
$st2->close();

echo json_encode([
  'ok'        => true,
  'replies'   => $replies,
  'has_more'  => $has_more,
  'next_page' => $has_more ? ($page + 1) : null
]);
