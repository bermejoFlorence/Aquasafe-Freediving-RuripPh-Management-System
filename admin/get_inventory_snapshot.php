<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}
require '../db_connect.php';
$conn->query("SET time_zone = '+08:00'");

// Compute in-use (outstanding) per item for active kits
$sql = "
  SELECT
    ii.item_id,
    ii.name,
    ii.total_qty,
    ii.cleaning_qty,
    ii.damaged_qty,
    COALESCE(SUM(
      CASE
        WHEN rk.status IN ('issued','partial','overdue')
        THEN GREATEST(rki.qty - COALESCE(rki.returned_qty,0), 0)
        ELSE 0
      END
    ),0) AS in_use
  FROM inventory_item ii
  LEFT JOIN rental_kit_item rki ON rki.item_id = ii.item_id
  LEFT JOIN rental_kit rk       ON rk.kit_id   = rki.kit_id
  GROUP BY ii.item_id, ii.name, ii.total_qty, ii.cleaning_qty, ii.damaged_qty
  ORDER BY in_use DESC, ii.name ASC
  LIMIT 8
";
$labels=[]; $available=[]; $in_use=[]; $cleaning=[]; $damaged=[];
if ($res = $conn->query($sql)) {
  while ($r = $res->fetch_assoc()){
    $name = $r['name'];
    $in   = (int)$r['in_use'];
    $cl   = (int)$r['cleaning_qty'];
    $dm   = (int)$r['damaged_qty'];
    $tot  = (int)$r['total_qty'];
    $av   = max(0, $tot - $in - $cl - $dm);

    $labels[]    = $name;
    $available[] = $av;
    $in_use[]    = $in;
    $cleaning[]  = $cl;
    $damaged[]   = $dm;
  }
  $res->free();
}

echo json_encode([
  'labels'    => $labels,
  'available' => $available,
  'in_use'    => $in_use,
  'cleaning'  => $cleaning,
  'damaged'   => $damaged
]);
