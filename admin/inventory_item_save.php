<?php
// admin/inventory_item_save.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']);
  exit;
}

require_once '../db_connect.php';

function fail($msg, $code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'message'=>$msg]);
  exit;
}

/* ---------- Inputs ---------- */
$name        = trim($_POST['name'] ?? '');
$total_qty   = isset($_POST['total_qty']) ? (int)$_POST['total_qty'] : 0;
$item_id     = ($_POST['item_id'] ?? '') !== '' ? (int)$_POST['item_id'] : null;
$is_addon    = isset($_POST['is_addon']) ? (int)$_POST['is_addon'] : 0;              // 0 = Included, 1 = Add-on
$price_day   = isset($_POST['price_per_day']) ? (float)$_POST['price_per_day'] : 0.0; // only if add-on

if ($name === '') fail('Name is required.');
if ($total_qty < 0) fail('Quantity must be >= 0.');
if ($is_addon !== 0 && $is_addon !== 1) $is_addon = 0;
if ($is_addon === 0) $price_day = 0.0;        // enforce free = 0
if ($price_day < 0)  $price_day = 0.0;

$created_by = (int)$_SESSION['user_id'];

/* ---------- File upload (../uploads) ---------- */
$image_path = null;
if (!empty($_FILES['photo']['name'])) {
  $f = $_FILES['photo'];
  if ($f['error'] !== UPLOAD_ERR_OK) fail('File upload error (code '.$f['error'].')');

  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  $mime = mime_content_type($f['tmp_name']);
  if (!isset($allowed[$mime])) fail('Only JPG/PNG/WEBP allowed.');
  if ($f['size'] > 5*1024*1024) fail('Max file size is 5MB.');

  $rootDir    = realpath(__DIR__ . '/..');
  $uploadsDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $ext  = $allowed[$mime];
  $base = preg_replace('/[^a-zA-Z0-9_\-]+/', '_',
          strtolower(pathinfo($f['name'], PATHINFO_FILENAME)));
  $newName = $base . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

  $dest = $uploadsDir . DIRECTORY_SEPARATOR . $newName;
  if (!move_uploaded_file($f['tmp_name'], $dest)) fail('Failed to move uploaded file.');
  $image_path = 'uploads/' . $newName; // web path
}

try {
  if ($item_id === null) {
    /* ---------- INSERT ---------- */
    $stmt = $conn->prepare("
      INSERT INTO inventory_item
        (name, variant, image_path, total_qty, min_threshold, is_addon, price_per_day,
         created_by, created_at, updated_at)
      VALUES
        (?, NULL, ?, ?, 10, ?, ?, ?, NOW(), NOW())
    ");
    // types: s s i i d i  => total 6 values after the two ss? Wait: list:
    // name(s), image_path(s), total_qty(i), is_addon(i), price_per_day(d), created_by(i)
    $stmt->bind_param('ssiidi', $name, $image_path, $total_qty, $is_addon, $price_day, $created_by);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    $row = [
      'item_id'        => $new_id,
      'name'           => $name,
      'total_qty'      => $total_qty,
      'image_path'     => $image_path,
      'is_addon'       => $is_addon,
      'price_per_day'  => $price_day
    ];
  } else {
    /* ---------- UPDATE ---------- */
    // keep old image if none uploaded
    if ($image_path === null) {
      $stmt = $conn->prepare("SELECT image_path FROM inventory_item WHERE item_id=?");
      $stmt->bind_param('i', $item_id);
      $stmt->execute();
      $stmt->bind_result($old);
      $stmt->fetch();
      $stmt->close();
      $image_path = $old;
    }

    $stmt = $conn->prepare("
      UPDATE inventory_item
         SET name=?,
             image_path=?,
             total_qty=?,
             is_addon=?,
             price_per_day=?,
             updated_at=NOW()
       WHERE item_id=?
    ");
    // name(s), image_path(s), total_qty(i), is_addon(i), price_per_day(d), item_id(i)
    $stmt->bind_param('ssiidi', $name, $image_path, $total_qty, $is_addon, $price_day, $item_id);
    $stmt->execute();
    $stmt->close();

    $row = [
      'item_id'        => $item_id,
      'name'           => $name,
      'total_qty'      => $total_qty,
      'image_path'     => $image_path,
      'is_addon'       => $is_addon,
      'price_per_day'  => $price_day
    ];
  }

  // convenience URL for admin page (since it's /admin/*)
  $row['image_admin_url'] = $row['image_path'] ? ('../'.$row['image_path']) : null;

  echo json_encode(['success'=>true, 'item'=>$row]);
} catch (Throwable $e) {
  fail('DB error: '.$e->getMessage(), 500);
}
