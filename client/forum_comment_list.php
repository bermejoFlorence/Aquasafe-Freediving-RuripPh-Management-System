<?php
// client/forum_comment_list.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
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

// (optional) ensure post exists
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

/*
  Top-level comments only (c.parent_id IS NULL)
  IMPORTANT: we now return u.role AS user_role so the frontend can hide the
  Report button for admin authors; we also compute can_report here for convenience.
*/
$sql = "
  SELECT
    c.comment_id,
    c.body,
    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
    u.user_id,
    u.full_name,
    COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
    u.role AS user_role,  -- ← ADD
    (
      SELECT COUNT(*)
      FROM forum_post_comment r
      WHERE r.post_id = c.post_id
        AND r.parent_id = c.comment_id
    ) AS replies_count
  FROM forum_post_comment c
  JOIN user u ON u.user_id = c.user_id
  WHERE c.post_id = ?
    AND c.parent_id IS NULL
  ORDER BY c.created_at ASC, c.comment_id ASC
  LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$st->bind_param('iii', $post_id, $limit, $offset);
$st->execute();
$res = $st->get_result();

$comments = [];
while ($r = $res->fetch_assoc()) {
  $isAdminAuthor = (strtolower($r['user_role'] ?? '') === 'admin');
  $isSelf        = ((int)$r['user_id'] === $user_id);

  $comments[] = [
    'comment_id'    => (int)$r['comment_id'],
    'user_id'       => (int)$r['user_id'],
    'user_role'     => (string)($r['user_role'] ?? ''),
    'full_name'     => (string)($r['full_name'] ?? 'User'),
    'profile_pic'   => (string)($r['profile_pic'] ?? 'uploads/default.png'),
    'body'          => (string)($r['body'] ?? ''),
    'created_at'    => (string)($r['created_at'] ?? ''),
    'replies_count' => (int)($r['replies_count'] ?? 0),
    // convenience flag para di na mag-compute pa sa frontend (optional pero helpful)
    'can_report'    => (!$isSelf && !$isAdminAuthor),
  ];
}
$st->close();

// has_more (top-level only)
$has_more = false;
if ($st2 = $conn->prepare(
  "SELECT 1 FROM forum_post_comment WHERE post_id=? AND parent_id IS NULL LIMIT 1 OFFSET ?"
)) {
  $peekOffset = $offset + $limit;
  $st2->bind_param('ii', $post_id, $peekOffset);
  $st2->execute();
  $has_more = (bool)$st2->get_result()->fetch_assoc();
  $st2->close();
}

echo json_encode([
  'ok'        => true,
  'comments'  => $comments,
  'has_more'  => $has_more,
  'next_page' => $has_more ? ($page + 1) : null
]);
