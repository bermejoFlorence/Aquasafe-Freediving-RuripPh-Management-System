<?php
// admin/fix_damage.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit; }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') fail('Not authorized',403);

$in = json_decode(file_get_contents('php://input'), true);
$item_id = (int)($in['item_id'] ?? 0);
$qty_req = max(1, (int)($in['qty'] ?? 1));
if ($item_id<=0) fail('Missing item_id');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try{
  $conn->begin_transaction();

  // lock
  $stmt = $conn->prepare("SELECT damaged_qty, cleaning_qty FROM inventory_item WHERE item_id=? FOR UPDATE");
  $stmt->bind_param('i',$item_id);
  $stmt->execute();
  $stmt->bind_result($damaged,$cleaning);
  if(!$stmt->fetch()){ $stmt->close(); $conn->rollback(); fail('Item not found',404); }
  $stmt->close();

  if ($damaged <= 0){ $conn->rollback(); fail('No damaged items to fix',409); }
  $qty = min($qty_req, $damaged);

  // move damaged -> cleaning
  $stmtU = $conn->prepare("
    UPDATE inventory_item
       SET damaged_qty  = GREATEST(damaged_qty - ?, 0),
           cleaning_qty = cleaning_qty + ?,
           updated_at   = NOW()
     WHERE item_id = ?
  ");
  $stmtU->bind_param('iii', $qty, $qty, $item_id);
  $stmtU->execute();
  $stmtU->close();

  // read fresh counts (with live in_use)
  $stmt2 = $conn->prepare("
    SELECT ii.total_qty, ii.cleaning_qty, ii.damaged_qty,
           COALESCE(SUM(
             CASE WHEN rk.status IN ('issued','partial','overdue')
                  THEN GREATEST(rki.qty - COALESCE(rki.returned_qty,0),0)
                  ELSE 0 END
           ),0) AS in_use_out
    FROM inventory_item ii
    LEFT JOIN rental_kit_item rki ON rki.item_id = ii.item_id
    LEFT JOIN rental_kit rk      ON rk.kit_id  = rki.kit_id
    WHERE ii.item_id = ?
    GROUP BY ii.total_qty, ii.cleaning_qty, ii.damaged_qty
  ");
  $stmt2->bind_param('i',$item_id);
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
    'success'=>true,
    'item_id'=>$item_id,
    'moved_qty'=>$qty,
    'counts'=>[
      'total'=>$total, 'cleaning'=>$cleaning, 'damaged'=>$damaged,
      'in_use'=>$in_use, 'available'=>$available
    ]
  ]);
}catch(Throwable $e){
  $conn->rollback();
  fail($e->getMessage(),500);
}
