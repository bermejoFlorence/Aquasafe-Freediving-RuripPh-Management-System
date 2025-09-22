<?php
// admin/clean_item.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

function fail($msg, $code = 400){
  http_response_code($code);
  echo json_encode(['success' => false, 'message' => $msg]);
  exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  fail('Not authorized', 403);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('Invalid JSON');

$item_id = (int)($in['item_id'] ?? 0);
$qty_req = max(1, (int)($in['qty'] ?? 1));
if ($item_id <= 0) fail('Missing item_id');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn->begin_transaction();

  // 1) Lock the row (avoid race conditions)
  $stmt = $conn->prepare("SELECT cleaning_qty FROM inventory_item WHERE item_id = ? FOR UPDATE");
  $stmt->bind_param('i', $item_id);
  $stmt->execute();
  $stmt->bind_result($cleaning_qty);
  if (!$stmt->fetch()) { $stmt->close(); $conn->rollback(); fail('Item not found', 404); }
  $stmt->close();

  $cleaning_qty = (int)$cleaning_qty;
  if ($cleaning_qty <= 0) {
    $conn->rollback();
    fail('Nothing to clean for this item', 409);
  }

  // 2) Clamp qty at what is actually in cleaning
  $qty = min($qty_req, $cleaning_qty);

  // 3) Apply update (defensive GREATEST + touch updated_at)
  $stmtU = $conn->prepare("
    UPDATE inventory_item
       SET cleaning_qty = GREATEST(cleaning_qty - ?, 0),
           updated_at   = NOW()
     WHERE item_id = ?
  ");
  $stmtU->bind_param('ii', $qty, $item_id);
  $stmtU->execute();
  $stmtU->close();

  // 4) Read back fresh counts using your live “in use” formula
  $stmt2 = $conn->prepare("
    SELECT
      ii.total_qty,
      ii.cleaning_qty,
      ii.damaged_qty,
      COALESCE(SUM(
        CASE WHEN rk.status IN ('issued','partial','overdue')
             THEN GREATEST(rki.qty - COALESCE(rki.returned_qty,0), 0)
             ELSE 0 END
      ), 0) AS in_use_out
    FROM inventory_item ii
    LEFT JOIN rental_kit_item rki ON rki.item_id = ii.item_id
    LEFT JOIN rental_kit rk      ON rk.kit_id  = rki.kit_id
    WHERE ii.item_id = ?
    GROUP BY ii.total_qty, ii.cleaning_qty, ii.damaged_qty
  ");
  $stmt2->bind_param('i', $item_id);
  $stmt2->execute();
  $row = $stmt2->get_result()->fetch_assoc();
  $stmt2->close();

  $total    = (int)$row['total_qty'];
  $cleaning = (int)$row['cleaning_qty'];
  $damaged  = (int)$row['damaged_qty'];
  $in_use   = (int)$row['in_use_out'];
  $available = max(0, $total - $in_use - $cleaning - $damaged);

  $conn->commit();

  echo json_encode([
    'success'        => true,
    'item_id'        => $item_id,
    'cleaned_qty'    => $qty,
    'counts' => [
      'total'     => $total,
      'cleaning'  => $cleaning,
      'damaged'   => $damaged,
      'in_use'    => $in_use,
      'available' => $available
    ]
  ]);
} catch (Throwable $e) {
  $conn->rollback();
  fail($e->getMessage(), 500);
}
