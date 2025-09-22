<?php
// admin/issue_gear.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
  echo json_encode(['success'=>false,'message'=>'Invalid JSON body']); exit;
}

$booking_id   = (int)($in['booking_id'] ?? 0);
$days_charged = max(1, (int)($in['days_charged'] ?? 1));
$notes        = trim((string)($in['notes'] ?? ''));
$items        = is_array($in['items'] ?? null) ? $in['items'] : [];   // unified source

// fallback (optional) if older frontend only sends "addons"
if (!$items && isset($in['addons']) && is_array($in['addons'])) {
  foreach ($in['addons'] as $a) {
    $qty = (int)($a['qty'] ?? 0);
    if ($qty <= 0) continue;
    $items[] = [
      'item_id'       => (int)($a['item_id'] ?? 0),
      'qty'           => $qty,
      'is_addon'      => 1,
      'price_per_day' => (float)($a['price_per_day'] ?? 0),
    ];
  }
}

$normDT = function($s){
  if (!$s) return null;
  $s = str_replace('T',' ',trim($s));
  if (strlen($s) === 16) $s .= ':00';
  return $s;
};
$issue_time = $normDT($in['issue_time'] ?? null);
$due_back   = $normDT($in['due_back']   ?? null);

if ($booking_id<=0 || !$issue_time || !$due_back) {
  echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit;
}
if (!$items) {
  echo json_encode(['success'=>false,'message'=>'No items to issue']); exit;
}

/* ---- sanitize items and compute add-ons total ---- */
$clean = [];
$addons_total = 0.0;
foreach ($items as $it) {
  $iid   = (int)($it['item_id'] ?? 0);
  $qty   = (int)($it['qty'] ?? 0);
  $addon = (int)($it['is_addon'] ?? 0) ? 1 : 0;
  $rate  = (float)($it['price_per_day'] ?? 0);

  if ($iid <= 0 || $qty <= 0) continue;

  $clean[] = ['item_id'=>$iid, 'qty'=>$qty, 'is_addon'=>$addon, 'price_per_day'=>$rate];
  if ($addon) $addons_total += $qty * $rate * $days_charged;
}
if (!$clean) {
  echo json_encode(['success'=>false,'message'=>'All item quantities are zero']); exit;
}

try {
  $conn->begin_transaction();

  /* ---- Insert into rental_kit ---- */
  $sqlKit = "INSERT INTO rental_kit
    (booking_id, created_by, days_charged, issue_time, due_back, notes, status, addons_total)
    VALUES (?,?,?,?,?,?,?,?)";
  $stmtKit = $conn->prepare($sqlKit);
  if (!$stmtKit) throw new Exception('Prepare failed (rental_kit): '.$conn->error);

  $status     = 'issued';
  $created_by = (int)$_SESSION['user_id'];

  $stmtKit->bind_param(
    'iiissssd',
    $booking_id, $created_by, $days_charged, $issue_time, $due_back, $notes, $status, $addons_total
  );
  if (!$stmtKit->execute()) throw new Exception('Execute failed (rental_kit): '.$stmtKit->error);
  $kit_id = $stmtKit->insert_id;
  $stmtKit->close();

  /* ---- Fetch item names for all item_ids in one go ---- */
  $ids = array_values(array_unique(array_column($clean, 'item_id')));
  $place = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sqlL  = "SELECT item_id, name FROM inventory_item WHERE item_id IN ($place)";
  $stmtL = $conn->prepare($sqlL);
  if (!$stmtL) throw new Exception('Prepare failed (lookup): '.$conn->error);
  $stmtL->bind_param($types, ...$ids);
  $stmtL->execute();
  $res = $stmtL->get_result();

  $nameById = [];
  while ($r = $res->fetch_assoc()) {
    $nameById[(int)$r['item_id']] = $r['name'];
  }
  $stmtL->close();

  // ensure all ids exist
  foreach ($ids as $iid) {
    if (!isset($nameById[$iid])) {
      throw new Exception("Unknown inventory item_id: $iid. Add it in Inventory first.");
    }
  }

  /* ---- Insert rental_kit_item rows ---- */
  $sqlItem = "INSERT INTO rental_kit_item
    (kit_id, item_id, item_name, qty, price_per_day, is_addon)
    VALUES (?,?,?,?,?,?)";
  $stmtItem = $conn->prepare($sqlItem);
  if (!$stmtItem) throw new Exception('Prepare failed (rental_kit_item): '.$conn->error);

  $itemsInserted = 0;
  foreach ($clean as $it) {
    $kid   = $kit_id;
    $iid   = $it['item_id'];
    $name  = $nameById[$iid];                    // from DB to keep a snapshot name
    $qty   = $it['qty'];
    $price = $it['price_per_day'];
    $isadd = $it['is_addon'];

    $stmtItem->bind_param('iisidi', $kid, $iid, $name, $qty, $price, $isadd);
    if (!$stmtItem->execute()) throw new Exception('Insert item failed: '.$stmtItem->error);
    $itemsInserted++;
  }
  $stmtItem->close();

  if ($itemsInserted === 0) throw new Exception('No rental_kit_item rows inserted.');

  $conn->commit();
  echo json_encode(['success'=>true, 'kit_id'=>$kit_id, 'items_inserted'=>$itemsInserted, 'addons_total'=>$addons_total]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
