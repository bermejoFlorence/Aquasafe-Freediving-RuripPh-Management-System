<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden']);
  exit;
}

require '../db_connect.php';
$conn->query("SET time_zone = '+08:00'");

// helpers
function table_exists(mysqli $c, $table){
  $table = $c->real_escape_string($table);
  $res = $c->query("SHOW TABLES LIKE '$table'");
  return $res && $res->num_rows > 0;
}
function column_exists(mysqli $c, $table, $col){
  $table = $c->real_escape_string($table);
  $col   = $c->real_escape_string($col);
  $res = $c->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
  return $res && $res->num_rows > 0;
}

// last 30 days labels
$labels = [];
$revMap = [];
for ($i=29; $i>=0; $i--){
  $d = date('Y-m-d', strtotime("-$i day"));
  $labels[] = $d;
  $revMap[$d] = 0.0;
}

if (table_exists($conn, 'payment')) {
  $dateCol = column_exists($conn, 'payment', 'paid_at') ? 'paid_at'
         : (column_exists($conn, 'payment', 'payment_date') ? 'payment_date' : null);
  $amtCol  = column_exists($conn, 'payment', 'amount') ? 'amount' : null;

  if ($dateCol && $amtCol) {
    $sql = "
      SELECT DATE($dateCol) d, COALESCE(SUM($amtCol),0) amt
      FROM payment
      WHERE status='paid'
        AND DATE($dateCol) BETWEEN CURDATE()-INTERVAL 29 DAY AND CURDATE()
      GROUP BY DATE($dateCol)
      ORDER BY d
    ";
    if ($res = $conn->query($sql)) {
      while ($r = $res->fetch_assoc()){
        $d = $r['d']; $amt = (float)$r['amt'];
        if (isset($revMap[$d])) $revMap[$d] = $amt;
      }
      $res->free();
    }
  }
}

$revenue = [];
foreach ($labels as $d){ $revenue[] = $revMap[$d]; }

echo json_encode(['labels'=>$labels, 'revenue'=>$revenue]);
