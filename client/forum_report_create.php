<?php
// client/forum_report_create.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower($_SESSION['role'] ?? '');

if ($uid <= 0 || $role !== 'client') {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Not authorized']); exit;
}

$type   = ($_POST['type'] ?? '');                 // 'post' | 'comment'
$id     = (int)($_POST['id'] ?? 0);               // post_id or comment_id
$reason = trim((string)($_POST['reason'] ?? ''));
$notes  = trim((string)($_POST['notes'] ?? ''));
$other  = trim((string)($_POST['other_text'] ?? ''));

$allowedReasons = ['spam','offensive','harassment','hate','nsfw','other'];
if (!in_array($reason, $allowedReasons, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Invalid reason']); exit;
}
if (!in_array($type, ['post','comment'], true) || $id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Invalid payload']); exit;
}
if ($reason === 'other' && mb_strlen($other) < 4) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Please specify your reason']); exit;
}

/* --- Kunin ang owner/role (+ post_id kapag comment) --- */
$ownerId = 0; $ownerRole = ''; $postIdForLink = null;

if ($type === 'post') {
  $sql = "SELECT p.user_id AS owner_id, COALESCE(u.role,'') AS owner_role
          FROM forum_post p
          JOIN user u ON u.user_id = p.user_id
          WHERE p.post_id = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Post not found']); exit; }
  $ownerId = (int)$row['owner_id'];
  $ownerRole = strtolower($row['owner_role'] ?? '');
  $postIdForLink = $id;
} else {
  $sql = "SELECT c.user_id AS owner_id, c.post_id, COALESCE(u.role,'') AS owner_role
          FROM forum_post_comment c
          JOIN user u ON u.user_id = c.user_id
          WHERE c.comment_id = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Comment not found']); exit; }
  $ownerId = (int)$row['owner_id'];
  $ownerRole = strtolower($row['owner_role'] ?? '');
  $postIdForLink = (int)$row['post_id'];
}

/* --- Bawal ireport ang sarili at admin --- */
if ($ownerId === $uid) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'You cannot report your own content']); exit;
}
if ($ownerRole === 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'Reporting admin content is not allowed']); exit;
}

/* --- Bawal ang duplicate ng parehong user sa parehong target --- */
$st = $conn->prepare("SELECT 1 FROM forum_report WHERE reporter_id=? AND target_type=? AND target_id=? LIMIT 1");
$st->bind_param('isi', $uid, $type, $id);
$st->execute();
$dup = (bool)$st->get_result()->fetch_assoc();
$st->close();
if ($dup) { http_response_code(409); echo json_encode(['ok'=>false,'message'=>'You already reported this item']); exit; }

/* --- Simpleng rate limit: max 5 reports / minute / user --- */
$st = $conn->prepare("SELECT COUNT(*) AS c FROM forum_report WHERE reporter_id=? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
$st->bind_param('i', $uid);
$st->execute();
$c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();
if ($c >= 5) { http_response_code(429); echo json_encode(['ok'=>false,'message'=>'Too many reports, try again later']); exit; }

/* --- Insert report --- */
$notes = mb_substr($notes, 0, 1000);
$other = mb_substr($other, 0, 255);

$ins = $conn->prepare("
  INSERT INTO forum_report
  (reporter_id, target_type, target_id, post_id, target_owner_id, target_owner_role, reason, other_text, notes)
  VALUES (?,?,?,?,?,?,?,?,?)
");
$ins->bind_param('isiisiiss',
  $uid, $type, $id, $postIdForLink, $ownerId, $ownerRole, $reason, $other, $notes
);
$ok = $ins->execute();
$reportId = $ok ? $ins->insert_id : 0;
$err = $ok ? '' : $ins->error;
$ins->close();

if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Insert failed: '.$err]); exit; }

/* --- Notify all admins --- */
$admins = [];
$rs = $conn->query("SELECT user_id FROM user WHERE LOWER(role)='admin'");
while ($r = $rs->fetch_assoc()) $admins[] = (int)$r['user_id'];
$rs->close();

if ($admins) {
  $msg = ($type === 'post')
    ? "New forum report: Post #{$postIdForLink} (Report #{$reportId})"
    : "New forum report: Comment #{$id} on Post #{$postIdForLink} (Report #{$reportId})";

  $stmt = $conn->prepare("
    INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
    VALUES (?, 'report', ?, ?, 0, NOW())
  ");
  foreach ($admins as $aid) {
    $stmt->bind_param('isi', $aid, $msg, $reportId);
    $stmt->execute();
  }
  $stmt->close();
}

echo json_encode(['ok'=>true,'report_id'=>$reportId,'message'=>'Report submitted.']);
