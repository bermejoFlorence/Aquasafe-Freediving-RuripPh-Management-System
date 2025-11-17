<?php
// client/forum_comment_create.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// Prevent accidental whitespace/HTML before JSON
if (function_exists('ob_get_level')) {
  while (ob_get_level()) { ob_end_clean(); }
}

// DEV aid: throw mysqli errors as exceptions so we can return JSON details
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../db_connect.php';
$conn->set_charset('utf8mb4');

try {
    // --- Auth ---
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // --- Inputs ---
    $post_id  = (int)($_POST['post_id'] ?? 0);

    // accept either "parent_comment_id" or "parent_id"
    $parent_raw = $_POST['parent_comment_id'] ?? $_POST['parent_id'] ?? '';
    $parent_id  = ($parent_raw === '' ? null : (int)$parent_raw);

    $body_raw = trim((string)($_POST['body'] ?? ''));
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
    $body = mb_substr($body_raw, 0, 5000);

    // --- TX ---
    $conn->begin_transaction();

    // 1) Lock post & fetch author/title
    $stmt = $conn->prepare("SELECT user_id, title FROM forum_post WHERE post_id = ? FOR UPDATE");
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
    $post_author_id = (int)$post['user_id'];

    // 2) If reply, validate parent belongs to same post & get parent owner
    $parent_owner_id = 0;
    if ($parent_id !== null) {
        $stmt = $conn->prepare("SELECT user_id FROM forum_post_comment WHERE comment_id = ? AND post_id = ? LIMIT 1");
        $stmt->bind_param('ii', $parent_id, $post_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid parent comment']);
            exit;
        }
        $parent_owner_id = (int)$row['user_id'];
    }

    // 3) Insert comment
    // NOTE: this assumes parent_id allows NULL (recommended).
    // If your column is NOT NULL, see SQL fix below.
    $stmt = $conn->prepare("
        INSERT INTO forum_post_comment (post_id, user_id, parent_id, body)
        VALUES (?, ?, ?, ?)
    ");
    // Use null for top-level; mysqli will pass NULL properly when we use 'i' with null via bind_param by using NULL variable
    if ($parent_id === null) {
        $null = null;
        $stmt->bind_param('iiis', $post_id, $user_id, $null, $body);
    } else {
        $stmt->bind_param('iiis', $post_id, $user_id, $parent_id, $body);
    }
    $stmt->execute();
    $comment_id = (int)$stmt->insert_id;
    $stmt->close();

    // 4) Increment post.comments
    $stmt = $conn->prepare("UPDATE forum_post SET comments = comments + 1 WHERE post_id = ?");
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $stmt->close();

    // Helper: display name of actor
    $who = 'Someone';
    $stmt = $conn->prepare("SELECT COALESCE(full_name, email_address, 'Someone') AS name FROM user WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    if ($r = $stmt->get_result()->fetch_assoc()) {
        $who = $r['name'] ?: 'Someone';
    }
    $stmt->close();

    // 5) Notifications
    $preview = mb_substr($body, 0, 120);

    // 5a) Notify post author (if not me)
    if ($post_author_id && $post_author_id !== $user_id) {
        $msg = $who . ' commented on your post "' . mb_substr((string)($post['title'] ?? 'Post'), 0, 80) . '": ' . $preview;
        $stmt = $conn->prepare("
            INSERT INTO notification (user_id, type, related_id, message, is_read, created_at)
            VALUES (?, 'forum', ?, ?, 0, NOW())
        ");
        $stmt->bind_param('iis', $post_author_id, $post_id, $msg);
        $stmt->execute();
        $stmt->close();
    }

// 5b) If reply: notify parent comment owner (not me, and not same as post author to avoid dup)
if ($parent_id !== null && $parent_owner_id && $parent_owner_id !== $user_id && $parent_owner_id !== $post_author_id) {
    $msg = $who . ' replied to your comment: ' . $preview;

    // IMPORTANT: use a distinct type and set related_id = NEW REPLY ID
    $stmt = $conn->prepare("
        INSERT INTO notification (user_id, type, related_id, message, is_read, created_at)
        VALUES (?, 'forum_reply', ?, ?, 0, NOW())
    ");
    $stmt->bind_param('iis', $parent_owner_id, $comment_id, $msg);
    $stmt->execute();
    $stmt->close();
}


    // 6) Return the created comment payload
    $stmt = $conn->prepare("
        SELECT u.user_id,
               u.full_name,
               COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
               DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at_fmt,
               c.comment_id, c.post_id, c.parent_id, c.body
        FROM forum_post_comment c
        JOIN user u ON u.user_id = c.user_id
        WHERE c.comment_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $comment_id);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'comment' => [
            'comment_id' => (int)$c['comment_id'],
            'post_id'    => (int)$c['post_id'],
            'parent_id'  => isset($c['parent_id']) ? (int)$c['parent_id'] : null,
            'user_id'    => (int)$c['user_id'],
            'full_name'  => $c['full_name'] ?? 'User',
            'profile_pic'=> $c['profile_pic'] ?? 'uploads/default.png',
            'created_at' => $c['created_at_fmt'] ?? '',
            'body'       => $c['body'] ?? '',
        ],
    ]);
} catch (Throwable $e) {
    // Always roll back and return JSON (no HTML)
    if ($conn && $conn->errno === 0) { /* no-op */ }
    try { $conn->rollback(); } catch (Throwable $ignore) {}

    // In production you might want to log $e->getMessage() instead
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error',
        'detail'  => $e->getMessage(), // keep for now while debugging
    ]);
}
