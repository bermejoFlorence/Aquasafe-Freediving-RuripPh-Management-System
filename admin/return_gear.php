<?php
// admin/return_gear.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

function fail($msg,$code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'message'=>$msg]);
  exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  fail('Not authorized', 403);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('Invalid JSON');

$kit_id  = (int)($in['kit_id'] ?? 0);
$overall = strtolower(trim((string)($in['overall_condition'] ?? 'good')));
if (!in_array($overall, ['good','bad'], true)) $overall = 'good';
$notes = trim((string)($in['notes'] ?? ''));
$items = is_array($in['items'] ?? null) ? $in['items'] : [];

if ($kit_id <= 0) fail('Missing kit_id');

try{
  $conn->begin_transaction();

  // 1) Lock kit row and validate status
  $stmt = $conn->prepare("SELECT status FROM rental_kit WHERE kit_id=? FOR UPDATE");
  if (!$stmt) throw new Exception($conn->error);
  $stmt->bind_param('i', $kit_id);
  $stmt->execute();
  $stmt->bind_result($status);
  if (!$stmt->fetch()) { $stmt->close(); throw new Exception('Kit not found'); }
  $stmt->close();

  if (!in_array($status, ['issued','partial','overdue'])) {
    throw new Exception('Kit is not in a returnable status');
  }

  // 2) Read kit items with previous state for delta calculations
  $rows = []; // by kit_item_id
  $stmt = $conn->prepare("
    SELECT kit_item_id, item_id, qty,
           COALESCE(returned_qty,0)  AS prev_returned,
           COALESCE(`condition`,'good') AS prev_condition
    FROM rental_kit_item
    WHERE kit_id=?
  ");
  if (!$stmt) throw new Exception($conn->error);
  $stmt->bind_param('i', $kit_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()){
    $kid = (int)$r['kit_item_id'];
    $rows[$kid] = [
      'item_id'        => (int)$r['item_id'],
      'qty'            => (int)$r['qty'],
      'prev_returned'  => (int)$r['prev_returned'],
      'prev_condition' => strtolower($r['prev_condition'] ?? 'good'),
    ];
  }
  $stmt->close();

  if (!$rows) throw new Exception('Kit has no items');

  // 3) Update each kit item (delta-based) and accumulate inventory deltas
  $stmtU = $conn->prepare("
    UPDATE rental_kit_item
       SET returned_qty=?, `condition`=?, damage_notes=?
     WHERE kit_item_id=? AND kit_id=?
  ");
  if (!$stmtU) throw new Exception($conn->error);

  $addCleaning = []; // item_id => sum(delta)
  $addDamaged  = []; // item_id => sum(delta)

  foreach ($items as $it){
    $kid = (int)($it['kit_item_id'] ?? 0);
    if (!$kid || !isset($rows[$kid])) continue;

    $row    = $rows[$kid];
    $iid    = $row['item_id'];
    $issued = $row['qty'];
    $prev   = $row['prev_returned'];

    // Input values
    $ret = (int)($it['returned_qty'] ?? 0);
    if ($ret < 0) $ret = 0;
    if ($ret > $issued) $ret = $issued;

    $cond = strtolower(trim((string)($it['condition'] ?? 'good')));
    if (!in_array($cond, ['good','damaged','missing'], true)) $cond = 'good';

    // Missing means no units are physically returned (force 0 for safety)
    if ($cond === 'missing') $ret = 0;

    $dn = (string)($it['damage_notes'] ?? '');

    // Do not allow decreasing returned_qty; clamp up to previous value
    if ($ret < $prev) $ret = $prev;

    // Delta to credit this submission only
    $delta = $ret - $prev;

    // Persist latest per-line state (snapshot)
    $stmtU->bind_param('issii', $ret, $cond, $dn, $kid, $kit_id);
    if (!$stmtU->execute()) throw new Exception('Update item failed: '.$stmtU->error);

    // Accumulate inventory deltas (no changes to total_qty)
    if ($delta > 0 && $iid > 0){
      if ($cond === 'good'){
        $addCleaning[$iid] = ($addCleaning[$iid] ?? 0) + $delta;
      } elseif ($cond === 'damaged'){
        $addDamaged[$iid] = ($addDamaged[$iid] ?? 0) + $delta;
      } else {
        // missing -> no inventory credit
      }
    }
  }
  $stmtU->close();

  // 4) Apply inventory deltas
  if ($addCleaning){
    $stClean = $conn->prepare("UPDATE inventory_item SET cleaning_qty = cleaning_qty + ? WHERE item_id=?");
    if (!$stClean) throw new Exception($conn->error);
    foreach ($addCleaning as $iid => $q){
      $q = max(0,(int)$q); if ($q === 0) continue;
      $stClean->bind_param('ii', $q, $iid);
      if (!$stClean->execute()) throw new Exception('Update cleaning failed: '.$stClean->error);
    }
    $stClean->close();
  }
  if ($addDamaged){
    $stDam = $conn->prepare("UPDATE inventory_item SET damaged_qty = damaged_qty + ? WHERE item_id=?");
    if (!$stDam) throw new Exception($conn->error);
    foreach ($addDamaged as $iid => $q){
      $q = max(0,(int)$q); if ($q === 0) continue;
      $stDam->bind_param('ii', $q, $iid);
      if (!$stDam->execute()) throw new Exception('Update damaged failed: '.$stDam->error);
    }
    $stDam->close();
  }

  // 5) Recompute outstanding units to decide kit status
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(GREATEST(qty - COALESCE(returned_qty,0),0)),0)
    FROM rental_kit_item
    WHERE kit_id=?
  ");
  if (!$stmt) throw new Exception($conn->error);
  $stmt->bind_param('i',$kit_id);
  $stmt->execute();
  $stmt->bind_result($outstanding);
  $stmt->fetch();
  $stmt->close();
  $outstanding = (int)$outstanding;

  $newStatus = $outstanding > 0 ? 'partial' : 'returned';

  // 6) Update rental_kit header
  if ($newStatus === 'returned'){
    $stmt = $conn->prepare("
      UPDATE rental_kit
         SET overall_condition=?, notes=?, status='returned',
             returned_at=NOW(), received_by=?
       WHERE kit_id=?
    ");
    if (!$stmt) throw new Exception($conn->error);
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('ssii', $overall, $notes, $uid, $kit_id);
  } else {
    $stmt = $conn->prepare("
      UPDATE rental_kit
         SET overall_condition=?, notes=?, status='partial'
       WHERE kit_id=?
    ");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('ssi', $overall, $notes, $kit_id);
  }
  if (!$stmt->execute()) throw new Exception('Update kit failed: '.$stmt->error);
  $stmt->close();

  $conn->commit();
  echo json_encode([
    'success'=>true,
    'status'=>$newStatus,
    'outstanding'=>$outstanding,
    'credited'=>[
      'cleaning'=>$addCleaning,
      'damaged'=>$addDamaged
    ]
  ]);
}catch(Throwable $e){
  $conn->rollback();
  fail($e->getMessage(), 500);
}
