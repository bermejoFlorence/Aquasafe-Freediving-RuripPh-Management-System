<?php
// admin/forum_like_toggle.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

// 1) Auth: kailangan naka-login (any role)
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
    exit;
}

// 2) Input
$post_id = (int)($_POST['post_id'] ?? 0);
if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid post_id']);
    exit;
}

try {
    // 3) Start transaction
    if (!$conn->begin_transaction()) throw new Exception('Failed to start transaction');

    // 4) Lock the post row to avoid race conditions on counter
    $stmt = $conn->prepare("SELECT likes FROM forum_post WHERE post_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception('Prepare failed (lock)');
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Post not found']);
        exit;
    }

    // 5) Check if already liked
    $stmt = $conn->prepare("SELECT like_id FROM forum_post_like WHERE post_id = ? AND user_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Prepare failed (check like)');
    $stmt->bind_param('ii', $post_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $like = $res->fetch_assoc();
    $stmt->close();

    $liked = false;
    $newLikes = (int)$row['likes'];

    if ($like) {
        // 6A) UNLIKE: delete row + decrement counter (floor at 0)
        $stmt = $conn->prepare("DELETE FROM forum_post_like WHERE like_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (delete like)');
        $stmt->bind_param('i', $like['like_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE forum_post SET likes = GREATEST(likes - 1, 0) WHERE post_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (decrement counter)');
        $stmt->bind_param('i', $post_id);
        $stmt->execute();
        $stmt->close();

        $liked = false;
        $newLikes = max($newLikes - 1, 0);
    } else {
        // 6B) LIKE: insert row + increment counter
        $stmt = $conn->prepare("INSERT INTO forum_post_like (post_id, user_id) VALUES (?, ?)");
        if (!$stmt) throw new Exception('Prepare failed (insert like)');
        $stmt->bind_param('ii', $post_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE forum_post SET likes = likes + 1 WHERE post_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (increment counter)');
        $stmt->bind_param('i', $post_id);
        $stmt->execute();
        $stmt->close();

        $liked = true;
        $newLikes = $newLikes + 1;
    }

    // 7) Commit
    $conn->commit();

    echo json_encode([
        'ok'          => true,
        'liked'       => $liked,
        'likes_count' => $newLikes,
        'post_id'     => $post_id,
    ]);
} catch (Throwable $e) {
    // Rollback on error
    if ($conn->errno) { /* noop */ }
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
}
