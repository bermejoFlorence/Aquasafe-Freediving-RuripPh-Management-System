<?php
// admin/forum_post_create.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false, 'message'=>'Not logged in']);
  exit;
}

require_once '../db_connect.php';

$user_id = (int)$_SESSION['user_id'];
$title   = trim($_POST['title'] ?? '');
$body    = trim($_POST['body'] ?? '');
$slug    = strtolower(trim($_POST['category'] ?? ''));

/* ---- Basic validations ---- */
if ($title === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>'Title is required']);
  exit;
}

/* ---- Option A rule: do not allow posting to "All" (or empty) ---- */
if ($slug === '' || $slug === 'all') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Please pick a category before posting.']);
  exit;
}

/* ---- Resolve category_id from slug (must exist & active) ---- */
$category_id = null;
if ($st = $conn->prepare("SELECT category_id FROM forum_category WHERE slug=? AND is_active=1 LIMIT 1")) {
  $st->bind_param('s', $slug);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if ($row) $category_id = (int)$row['category_id'];
}

if (!$category_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Unknown or inactive category.']);
  exit;
}

/* ---- Uploads setup ---- */
$uploadsDir = realpath(__DIR__ . '/../uploads');
if ($uploadsDir === false) {
  $uploadsDir = __DIR__ . '/../uploads';
  @mkdir($uploadsDir, 0777, true);
}
$subdir = $uploadsDir . '/forum';
@mkdir($subdir, 0777, true);

$allowed = [
  'image/jpeg','image/png','image/gif','image/webp',
  'application/pdf','text/plain',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/zip'
];
$maxSize = 10 * 1024 * 1024; // 10MB

$filesMeta = [];
if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
  $count = count($_FILES['files']['name']);
  for ($i=0; $i<$count; $i++) {
    if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

    $tmp  = $_FILES['files']['tmp_name'][$i];
    $name = basename($_FILES['files']['name'][$i]);
    $type = $_FILES['files']['type'][$i] ?? (@mime_content_type($tmp) ?: '');
    $size = (int)($_FILES['files']['size'][$i] ?? 0);

    if ($size > $maxSize) continue;
    if ($type && !in_array($type, $allowed, true)) continue;

    $ext      = pathinfo($name, PATHINFO_EXTENSION);
    $base     = pathinfo($name, PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base);
    $final    = sprintf('post_%d_%s_%d.%s', $user_id, $safeBase, time()+$i, $ext ?: 'dat');

    $destFs  = $subdir . '/' . $final;
    $destWeb = 'uploads/forum/' . $final;

    if (@move_uploaded_file($tmp, $destFs)) {
      $filesMeta[] = ['name'=>$name,'path'=>$destWeb,'type'=>$type,'size'=>$size];
    }
  }
}

$attJson = !empty($filesMeta) ? json_encode($filesMeta, JSON_UNESCAPED_SLASHES) : null;

/* ---- Insert post ---- */
$sql = "INSERT INTO forum_post (user_id, category_id, title, body, attachments_json)
        VALUES (?,?,?,?,?)";
if ($st = $conn->prepare($sql)) {
  $st->bind_param('iisss', $user_id, $category_id, $title, $body, $attJson);
  if (!$st->execute()) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'DB insert failed: '.$st->error]);
    $st->close();
    exit;
  }
  $post_id = $st->insert_id;
  $st->close();

  /* === NEW: notify ALL users (admins + clients), excluding the author === */
  $author_name = $_SESSION['full_name'] ?? 'User';
  $notif_type  = 'forum';
  $notif_msg   = "New forum post from $author_name: $title";
  $is_read     = 0;

  // fetch recipients: everyone except the author
  $recipients = [];
  if ($rs = $conn->prepare("SELECT user_id FROM user WHERE user_id <> ?")) {
    $rs->bind_param('i', $user_id);
    $rs->execute();
    $res = $rs->get_result();
    while ($r = $res->fetch_assoc()) {
      $recipients[] = (int)$r['user_id'];
    }
    $rs->close();
  }

  // insert one notification per recipient
  if (!empty($recipients)) {
    $nstmt = $conn->prepare("
      INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
      VALUES (?, ?, ?, ?, ?, NOW())
    ");
    foreach ($recipients as $uid) {
      $nstmt->bind_param('issii', $uid, $notif_type, $notif_msg, $post_id, $is_read);
      $nstmt->execute();
    }
    $nstmt->close();
  }
  /* === /NEW === */

  echo json_encode([
    'ok'=>true,
    'post_id'=>$post_id,
    'category'=>$slug,
    'attachments'=>$filesMeta
  ]);
  exit;
}

http_response_code(500);
echo json_encode(['ok'=>false,'message'=>'Prepare failed']);
