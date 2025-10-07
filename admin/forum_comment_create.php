<?php
// admin/forum_comment_create.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

/**
 * Any logged-in user (admin role sa admin panel) can comment.
 * Later we will mirror this for client side.
 */

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
  exit;
}

$post_id   = (int)($_POST['post_id'] ?? 0);
$parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? (int)$_POST['parent_id'] : null;
$body_raw  = trim((string)($_POST['body'] ?? ''));

if ($post_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid post_id']);
  exit;
}
if ($body_raw === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Comment cannot be empty']);
  exit;
}

// Allow plain text only; clamp to avoid abuse
$body = mb_substr($body_raw, 0, 5000);

try {
  $conn->begin_transaction();

  // 1) Ensure post exists + lock (needed for safe counter update)
  $stmt = $conn->prepare("SELECT user_id, title, category_id FROM forum_post WHERE post_id = ? FOR UPDATE");
  if (!$stmt) throw new Exception('Prepare failed: post check');
  $stmt->bind_param('i', $post_id);
  $stmt->execute();
  $post = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$post) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Post not found']);
    exit;
  }

  // 1.5) If replying, validate parent belongs to same post
  if ($parent_id !== null) {
    $stmt = $conn->prepare("SELECT comment_id FROM forum_post_comment WHERE comment_id = ? AND post_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Prepare failed: parent check');
    $stmt->bind_param('ii', $parent_id, $post_id);
    $stmt->execute();
    $parent_ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$parent_ok) {
      $conn->rollback();
      http_response_code(400);
      echo json_encode(['ok' => false, 'message' => 'Invalid parent comment']);
      exit;
    }
  }

  // 2) Insert comment (explicit NOW() for created_at)
  if ($parent_id === null) {
    // Embed NULL literal for parent_id (avoids binding NULL as int=0 under strict modes)
    $stmt = $conn->prepare("
      INSERT INTO forum_post_comment (post_id, user_id, parent_id, body, created_at)
      VALUES (?, ?, NULL, ?, NOW())
    ");
    if (!$stmt) throw new Exception('Prepare failed: insert comment (root)');
    $stmt->bind_param('iis', $post_id, $user_id, $body);
  } else {
    $stmt = $conn->prepare("
      INSERT INTO forum_post_comment (post_id, user_id, parent_id, body, created_at)
      VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception('Prepare failed: insert comment (reply)');
    $stmt->bind_param('iiis', $post_id, $user_id, $parent_id, $body);
  }
  $stmt->execute();
  $comment_id = (int)$stmt->insert_id;
  $stmt->close();

  // 3) Increment counter on forum_post
  $stmt = $conn->prepare("UPDATE forum_post SET comments = comments + 1 WHERE post_id = ?");
  if (!$stmt) throw new Exception('Prepare failed: inc counter');
  $stmt->bind_param('i', $post_id);
  $stmt->execute();
  $stmt->close();

  // 4) Notification to post author (skip if commenter == author)
  $post_author_id = (int)$post['user_id'];
  if ($post_author_id && $post_author_id !== $user_id) {
    // Resolve commenter name from your actual schema (table: user; email column: email_address)
    $who = 'Someone';
    if ($s = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(full_name),''), NULLIF(TRIM(email_address),'')) AS name
                             FROM user WHERE user_id = ? LIMIT 1")) {
      $s->bind_param('i', $user_id);
      $s->execute();
      if ($rr = $s->get_result()->fetch_assoc()) {
        if (!empty($rr['name'])) $who = $rr['name'];
      }
      $s->close();
    }

    $title   = (string)($post['title'] ?? 'Post');
    $preview = mb_substr($body, 0, 120);
    $msg     = $who . ' commented on your post "' . mb_substr($title, 0, 80) . '": ' . $preview;
    // TEXT can hold long strings, but clamp anyway for safety
    $msg     = mb_substr($msg, 0, 1000);

    // IMPORTANT: notif insert must NOT rollback the comment if it fails (e.g., charset/emoji issues)
    try {
      if ($n = $conn->prepare("
            INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
            VALUES (?, 'forum', ?, ?, 0, NOW())
          ")) {
        $n->bind_param('isi', $post_author_id, $msg, $post_id);
        $n->execute();
        $n->close();
      }
    } catch (Throwable $nt) {
      // swallow notification errors; optionally log:
      // error_log('[notif] '.$nt->getMessage());
    }
  }

  // 5) Return fresh comment payload (with commenter display info)
  $stmt = $conn->prepare("
    SELECT
      u.user_id,
      COALESCE(NULLIF(TRIM(u.full_name),''), NULLIF(TRIM(u.email_address),''), 'User') AS full_name,
      COALESCE(u.profile_pic, 'uploads/default.png') AS profile_pic,
      DATE_FORMAT(c.created_at, '%b %d, %Y %l:%i%p') AS created_at_fmt,
      c.comment_id,
      c.body
    FROM forum_post_comment c
    JOIN user u ON u.user_id = c.user_id
    WHERE c.comment_id = ?
    LIMIT 1
  ");
  if (!$stmt) throw new Exception('Prepare failed: fetch newly created comment');
  $stmt->bind_param('i', $comment_id);
  $stmt->execute();
  $newc = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $conn->commit();

  echo json_encode([
    'ok' => true,
    'comment' => [
      'comment_id' => (int)$newc['comment_id'],
      'post_id'    => $post_id,
      'user_id'    => (int)$newc['user_id'],
      'full_name'  => $newc['full_name'] ?? 'User',
      'profile_pic'=> $newc['profile_pic'] ?? 'uploads/default.png',
      'created_at' => $newc['created_at_fmt'] ?? '',
      'body'       => $newc['body'] ?? '',
    ],
  ]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
}
