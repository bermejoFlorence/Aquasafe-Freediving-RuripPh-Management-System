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
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
    exit;
}

$post_id  = (int)($_POST['post_id'] ?? 0);
$parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
$body_raw = trim($_POST['body'] ?? '');

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

// Simple sanitization (allow plain text only for now)
$body = $body_raw;
// Optionally limit size to avoid abuse
if (mb_strlen($body) > 5000) {
    $body = mb_substr($body, 0, 5000);
}

try {
    if (!$conn->begin_transaction()) {
        throw new Exception('Failed to start transaction');
    }

    // 1) Ensure post exists + get post author for notification
    $stmt = $conn->prepare("SELECT user_id, title, category_id FROM forum_post WHERE post_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception('Prepare failed: post check');
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $post = $res->fetch_assoc();
    $stmt->close();

    if (!$post) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Post not found']);
        exit;
    }

    // Optional: validate parent_id belongs to same post if provided
    if ($parent_id !== null) {
        $stmt = $conn->prepare("SELECT comment_id FROM forum_post_comment WHERE comment_id = ? AND post_id = ? LIMIT 1");
        if (!$stmt) throw new Exception('Prepare failed: parent check');
        $stmt->bind_param('ii', $parent_id, $post_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $parent_ok = (bool)$res->fetch_assoc();
        $stmt->close();
        if (!$parent_ok) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid parent comment']);
            exit;
        }
    }

    // 2) Insert comment
    $stmt = $conn->prepare("INSERT INTO forum_post_comment (post_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare failed: insert comment');
    if ($parent_id === null) {
        $null = null;
        $stmt->bind_param('iiis', $post_id, $user_id, $null, $body);
    } else {
        $stmt->bind_param('iiis', $post_id, $user_id, $parent_id, $body);
    }
    $stmt->execute();
    $comment_id = $stmt->insert_id;
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
        // Build a short message preview
        $preview = mb_substr($body, 0, 120);
        // We’ll use type='forum' and related_id = post_id so your existing admin_get_notifications.php continues to work
        // Message example: "X commented on your post 'Title': preview..."
        // Resolve current commenter name
        $who = 'Someone';
        if ($s = $conn->prepare("SELECT COALESCE(full_name, user_email) AS name FROM user WHERE user_id = ? LIMIT 1")) {
            $s->bind_param('i', $user_id);
            $s->execute();
            $rr = $s->get_result()->fetch_assoc();
            if ($rr && !empty($rr['name'])) $who = $rr['name'];
            $s->close();
        }

        $msg = $who . " commented on your post \"" . mb_substr($post['title'] ?? 'Post', 0, 80) . "\": " . $preview;

        $stmt = $conn->prepare("
            INSERT INTO notification (user_id, type, related_id, message, is_read, created_at)
            VALUES (?, 'forum', ?, ?, 0, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('iis', $post_author_id, $post_id, $msg);
            $stmt->execute();
            $stmt->close();
        }
        // If you want to also notify parent comment owner (for replies), we can add it later.
    }

    // 5) Return fresh comment payload (with commenter display info)
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
               DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at_fmt,
               c.comment_id, c.body
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
