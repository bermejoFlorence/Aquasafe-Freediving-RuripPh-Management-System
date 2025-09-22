<?php
// admin/returns_list.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}

require_once '../db_connect.php';

/*
 * scope options:
 *   - outstanding : kits na hindi pa fully returned (issued/partial/overdue)
 *   - returned    : history lang ng returned
 *   - all (default): lahat
 */
$scope = isset($_GET['scope']) ? strtolower($_GET['scope']) : 'all';
switch ($scope) {
  case 'outstanding':
    $statuses = ['issued','partial','overdue'];
    break;
  case 'returned':
    $statuses = ['returned'];
    break;
  default:
    $scope    = 'all';
    $statuses = ['issued','partial','overdue','returned'];
    break;
}

try {
  // ---- 1) Kunin ang mga kits (header info)
  $inPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
  $types          = str_repeat('s', count($statuses));

  $sql = "
    SELECT
      rk.kit_id,
      rk.booking_id,
      rk.issue_time,
      rk.due_back,
      rk.status,
      rk.overall_condition,      -- << important for Condition column
      u.full_name
    FROM rental_kit rk
    JOIN booking b ON b.booking_id = rk.booking_id
    JOIN user    u ON u.user_id    = b.user_id
    WHERE rk.status IN ($inPlaceholders)
    ORDER BY rk.issue_time DESC
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception($conn->error);
  $stmt->bind_param($types, ...$statuses);
  $stmt->execute();
  $res = $stmt->get_result();

  $kits   = [];
  $kitIds = [];
  while ($r = $res->fetch_assoc()) {
    $kid = (int)$r['kit_id'];
    $r['booking_code'] = sprintf('BK-%04d', (int)$r['booking_id']);
    $r['items'] = []; // pupunuin sa step 2
    $kits[$kid] = $r;
    $kitIds[]   = $kid;
  }
  $stmt->close();

  if (!$kitIds) {
    echo json_encode(['success'=>true, 'scope'=>$scope, 'kits'=>[]]);
    exit;
  }

  // ---- 2) Kunin lahat ng items ng mga kits (one shot)
  $in2    = implode(',', array_fill(0, count($kitIds), '?'));
  $types2 = str_repeat('i', count($kitIds));

  $sql2 = "
    SELECT
       kit_item_id,           -- << add
      kit_id,
         item_id,               -- << optional, but useful
      item_name,
      qty,
      is_addon,
      COALESCE(returned_qty,0) AS returned_qty,
      `condition`,              -- reserved word, so backticked
      damage_notes
    FROM rental_kit_item
    WHERE kit_id IN ($in2)
    ORDER BY is_addon, item_name
  ";
  $stmt2 = $conn->prepare($sql2);
  if (!$stmt2) throw new Exception($conn->error);
  $stmt2->bind_param($types2, ...$kitIds);
  $stmt2->execute();
  $res2 = $stmt2->get_result();

  while ($it = $res2->fetch_assoc()) {
    $kid = (int)$it['kit_id'];
    if (!isset($kits[$kid])) continue;
    $kits[$kid]['items'][] = [
        'kit_item_id'  => (int)$it['kit_item_id'],  // << add
  'item_id'      => (int)$it['item_id'],      // << add (optional)
      'item_name'    => $it['item_name'],
      'qty'          => (int)$it['qty'],
      'is_addon'     => (int)$it['is_addon'],
      'returned_qty' => (int)$it['returned_qty'],
      'condition'    => $it['condition'],      // 'good' | 'damaged' | 'missing' (or null)
      'damage_notes' => $it['damage_notes'] ?? ''
    ];
  }
  $stmt2->close();

  // ---- 3) Ibalik as list (hindi by id)
  echo json_encode([
    'success' => true,
    'scope'   => $scope,
    'kits'    => array_values($kits),
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
