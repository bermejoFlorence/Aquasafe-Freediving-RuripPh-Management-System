<?php
// admin/assign_options.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}

// optional, for future use; ok lang kahit di pa kailangan
$booking_id = (int)($_GET['booking_id'] ?? 0);

/* currently in-use per item (lahat ng issued kits) */
$inuseSQL = "
  SELECT rki.item_id, SUM(rki.qty) AS sum_qty
  FROM rental_kit rk
  JOIN rental_kit_item rki ON rki.kit_id = rk.kit_id
  WHERE rk.status='issued'
  GROUP BY rki.item_id
";

/* === FREE items (is_addon = 0) === */
$included = [];
$sqlFree = "
  SELECT ii.item_id, ii.name, ii.image_path, ii.total_qty,
         COALESCE(u.sum_qty,0) AS in_use
  FROM inventory_item ii
  LEFT JOIN ($inuseSQL) u ON u.item_id = ii.item_id
  WHERE ii.is_addon = 0
  ORDER BY ii.name
";
$res = $conn->query($sqlFree);
while ($r = $res->fetch_assoc()) {
  $avail = max(0, (int)$r['total_qty'] - (int)$r['in_use']);
  $included[] = [
    'item_id'   => (int)$r['item_id'],
    'name'      => $r['name'],
    'img'       => $r['image_path'] ?: null,
    'qty'       => 1,                 // default free qty per booking
    'available' => $avail
  ];
}

/* === ADD-ONS (is_addon = 1) === */
$addons = [];
$sqlAdd = "
  SELECT ii.item_id, ii.name, ii.image_path, ii.total_qty,
         COALESCE(u.sum_qty,0) AS in_use,
         COALESCE(ii.price_per_day,0) AS price_per_day
  FROM inventory_item ii
  LEFT JOIN ($inuseSQL) u ON u.item_id = ii.item_id
  WHERE ii.is_addon = 1
  ORDER BY ii.name
";
$res = $conn->query($sqlAdd);
while ($r = $res->fetch_assoc()) {
  $avail = max(0, (int)$r['total_qty'] - (int)$r['in_use']);
  $addons[] = [
    'item_id'       => (int)$r['item_id'],
    'name'          => $r['name'],
    'img'           => $r['image_path'] ?: null,
    'available'     => $avail,
    'price_per_day' => (float)$r['price_per_day']
  ];
}

echo json_encode(['success'=>true, 'included'=>$included, 'addons'=>$addons]);
