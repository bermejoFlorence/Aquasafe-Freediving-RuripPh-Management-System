<?php
// admin/forum_comment_create.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Not authenticated']); exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);

/* Accept both names for compatibility with existing JS */
$parent_id = null;
if (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') {
  $parent_id = (int)$_POST['parent_id'];
} elseif (isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== '') {
  $parent_id = (int)$_POST['parent_comment_id'];
}

$body_raw = trim((string)($_POST['body'] ?? ''));

if ($post_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid post_id']); exit;
}
if ($body_raw === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Comment cannot be empty']); exit;
}

$body = mb_substr($body_raw, 0, 5000);

try {
  $conn->begin_transaction();

  // Lock the post row for safe counter update
  $stmt = $conn->prepare("
    SELECT user_id, title, category_id
    FROM forum_post WHERE post_id = ? FOR UPDATE
  ");
  if (!$stmt) throw new Exception('Prepare failed: post check');
  $stmt->bind_param('i', $post_id);
  $stmt->execute();
  $post = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$post) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Post not found']); exit;
  }

  // If reply, validate parent belongs to this post and get parent owner
  $parent_owner_id = 0;
  if ($parent_id !== null) {
    $stmt = $conn->prepare("
      SELECT user_id FROM forum_post_comment
      WHERE comment_id = ? AND post_id = ? LIMIT 1
    ");
    if (!$stmt) throw new Exception('Prepare failed: parent check');
    $stmt->bind_param('ii', $parent_id, $post_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
      $conn->rollback();
      http_response_code(400);
      echo json_encode(['ok' => false, 'message' => 'Invalid parent comment']); exit;
    }
    $parent_owner_id = (int)$row['user_id'];
  }

  // Insert comment (NULL parent for top-level)
  if ($parent_id === null) {
    $stmt = $conn->prepare("
      INSERT INTO forum_post_comment (post_id, user_id, parent_id, body, created_at)
      VALUES (?, ?, NULL, ?, NOW())
    ");
    if (!$stmt) throw new Exception('Prepare failed: insert root');
    $stmt->bind_param('iis', $post_id, $user_id, $body);
  } else {
    $stmt = $conn->prepare("
      INSERT INTO forum_post_comment (post_id, user_id, parent_id, body, created_at)
      VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception('Prepare failed: insert reply');
    $stmt->bind_param('iiis', $post_id, $user_id, $parent_id, $body);
  }
  $stmt->execute();
  $comment_id = (int)$stmt->insert_id;
  $stmt->close();

  // Increment post.comments
  $stmt = $conn->prepare("UPDATE forum_post SET comments = comments + 1 WHERE post_id = ?");
  if (!$stmt) throw new Exception('Prepare failed: inc counter');
  $stmt->bind_param('i', $post_id);
  $stmt->execute();
  $stmt->close();

  // Resolve commenter display name once
  $who = 'Someone';
  if ($s = $conn->prepare("
        SELECT COALESCE(NULLIF(TRIM(full_name),''), NULLIF(TRIM(email_address),'')) AS name
        FROM user WHERE user_id = ? LIMIT 1
      ")) {
    $s->bind_param('i', $user_id);
    $s->execute();
    if ($rr = $s->get_result()->fetch_assoc()) {
      if (!empty($rr['name'])) $who = $rr['name'];
    }
    $s->close();
  }

  $title   = (string)($post['title'] ?? 'Post');
  $preview = mb_substr($body, 0, 120);

  // Notify post author (personal 'forum' type)
  $post_author_id = (int)$post['user_id'];
  if ($post_author_id && $post_author_id !== $user_id) {
    $msg = $who . ' commented on your post "' . mb_substr($title, 0, 80) . '": ' . $preview;
    $msg = mb_substr($msg, 0, 1000);
    try {
      if ($n = $conn->prepare("
        INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
        VALUES (?, 'forum', ?, ?, 0, NOW())
      ")) {
        $n->bind_param('isi', $post_author_id, $msg, $post_id);
        $n->execute();
        $n->close();
      }
    } catch (Throwable $nt) { /* optional: error_log */ }
  }

// If reply, also notify the parent commenter (use 'forum' for client compatibility)
if ($parent_id !== null && $parent_owner_id && $parent_owner_id !== $user_id && $parent_owner_id !== $post_author_id) {
  $msg = $who . ' replied to your comment on "' . mb_substr($title, 0, 80) . '": ' . $preview;
  $msg = mb_substr($msg, 0, 1000);
  try {
    if ($n = $conn->prepare("
      INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
      VALUES (?, 'forum', ?, ?, 0, NOW())
    ")) {
      // related_id => post_id para tumama sa existing client routing (view_post.php?post_id=...)
      $n->bind_param('isi', $parent_owner_id, $msg, $post_id);
      $n->execute();
      $n->close();
    }
  } catch (Throwable $nt) { /* optionally log */ }
}

  // Return the newly created comment (uniform timestamp)
  $stmt = $conn->prepare("
    SELECT
      u.user_id,
      COALESCE(NULLIF(TRIM(u.full_name),''), NULLIF(TRIM(u.email_address),''), 'User') AS full_name,
      COALESCE(u.profile_pic, 'uploads/default.png') AS profile_pic,
      DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at_fmt,
      c.comment_id, c.body, c.parent_id
    FROM forum_post_comment c
    JOIN user u ON u.user_id = c.user_id
    WHERE c.comment_id = ?
    LIMIT 1
  ");
  if (!$stmt) throw new Exception('Prepare failed: fetch new');
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
      'parent_id'  => $newc['parent_id'] !== null ? (int)$newc['parent_id'] : null,
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
