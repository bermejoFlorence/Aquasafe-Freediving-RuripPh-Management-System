<?php
// admin/forum_category_add.php  (now handles add/update/delete)
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'message'=>'Forbidden']); exit;
}
require_once '../db_connect.php';

/* ---------- Helpers ---------- */
function slugify($text) {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  return trim($text, '-') ?: 'category';
}
function next_sort_order(mysqli $conn) {
  $q = $conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM forum_category");
  $row = $q ? $q->fetch_assoc() : ['n'=>100];
  return (int)$row['n'];
}
function json_error($code, $msg) {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'message'=>$msg]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_error(405, 'Method Not Allowed');
}

$action = strtolower(trim($_POST['action'] ?? ''));
if ($action === '') {
  // Backward compatibility: no "action" means ADD
  $action = 'add';
}

/* ===================== ADD ===================== */
if ($action === 'add') {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') json_error(422, 'Category name is required.');
  if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

  $slug = slugify($name);

  // duplicate name (case-insensitive)
  $stmt = $conn->prepare("SELECT category_id FROM forum_category WHERE LOWER(name)=LOWER(?) LIMIT 1");
  $stmt->bind_param('s', $name);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); json_error(409, 'Category already exists.'); }
  $stmt->close();

  // ensure unique slug
  $stmt = $conn->prepare("SELECT category_id FROM forum_category WHERE slug=? LIMIT 1");
  $stmt->bind_param('s', $slug);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $slug .= '-' . substr(uniqid('', true), -4); }
  $stmt->close();

  $sort_order = next_sort_order($conn);
  $is_active  = 1;
  $created_by = (int)$_SESSION['user_id'];

  $stmt = $conn->prepare("INSERT INTO forum_category (name, slug, sort_order, is_active, created_by) VALUES (?,?,?,?,?)");
  $stmt->bind_param('ssiii', $name, $slug, $sort_order, $is_active, $created_by);
  if (!$stmt->execute()) { $err=$stmt->error; $stmt->close(); json_error(500, "DB error: $err"); }
  $new_id = $stmt->insert_id; $stmt->close();

  echo json_encode([
    'ok'=>true,
    'message'=>'Category saved.',
    'category'=>[
      'category_id'=>$new_id,
      'name'=>$name,
      'slug'=>$slug,
      'sort_order'=>$sort_order
    ]
  ]);
  exit;
}

/* ===================== UPDATE ===================== */
if ($action === 'update') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  if (!$category_id) json_error(422, 'Missing category_id.');
  if ($name === '')   json_error(422, 'Name required.');
  if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

  // load current
  $stmt = $conn->prepare("SELECT category_id, name, slug FROM forum_category WHERE category_id=? LIMIT 1");
  $stmt->bind_param('i', $category_id);
  $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$cur) json_error(404, 'Category not found.');

  // duplicate name (exclude self)
  $stmt = $conn->prepare("SELECT category_id FROM forum_category WHERE LOWER(name)=LOWER(?) AND category_id<>? LIMIT 1");
  $stmt->bind_param('si', $name, $category_id);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); json_error(409, 'Category name already exists.'); }
  $stmt->close();

  // recompute slug if name changed
  $new_slug = slugify($name);
  if ($new_slug !== $cur['slug']) {
    $stmt = $conn->prepare("SELECT category_id FROM forum_category WHERE slug=? AND category_id<>? LIMIT 1");
    $stmt->bind_param('si', $new_slug, $category_id);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $new_slug .= '-' . substr(uniqid('', true), -4); }
    $stmt->close();
  } else {
    $new_slug = $cur['slug'];
  }

  $stmt = $conn->prepare("UPDATE forum_category SET name=?, slug=? WHERE category_id=?");
  $stmt->bind_param('ssi', $name, $new_slug, $category_id);
  if (!$stmt->execute()) { $err=$stmt->error; $stmt->close(); json_error(500, "DB error: $err"); }
  $stmt->close();

  echo json_encode(['ok'=>true, 'message'=>'Category updated.', 'category'=>[
    'category_id'=>$category_id,
    'name'=>$name,
    'slug'=>$new_slug
  ]]);
  exit;
}

/* ===================== DELETE (soft) ===================== */
if ($action === 'delete') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  if (!$category_id) json_error(422, 'Missing category_id.');

  // OPTIONAL: block delete if has posts
  // $stmt = $conn->prepare("SELECT COUNT(*) c FROM forum_post WHERE category_id=?");
  // $stmt->bind_param('i', $category_id); $stmt->execute(); $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
  // if ($cnt > 0) json_error(409, 'Category has posts. Cannot delete.');

  // soft delete
  $stmt = $conn->prepare("UPDATE forum_category SET is_active=0 WHERE category_id=?");
  $stmt->bind_param('i', $category_id);
  if (!$stmt->execute()) { $err=$stmt->error; $stmt->close(); json_error(500, "DB error: $err"); }
  $stmt->close();

  echo json_encode(['ok'=>true, 'message'=>'Category deleted.']);
  exit;
}

/* ===================== Unknown action ===================== */
json_error(400, 'Unknown action. Use add, update, or delete.');
