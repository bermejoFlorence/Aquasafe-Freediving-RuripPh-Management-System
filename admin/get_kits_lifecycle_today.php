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

$out = ['issued'=>0, 'due'=>0, 'returned'=>0, 'overdue'=>0];

$sql = "
  SELECT
    SUM(CASE
          WHEN status IN ('issued','partial','overdue')
           AND DATE(issue_time)=CURDATE() THEN 1 ELSE 0 END) AS issued,
    SUM(CASE
          WHEN status IN ('issued','partial','overdue')
           AND DATE(due_back)=CURDATE() THEN 1 ELSE 0 END) AS due_today,
    SUM(CASE
          WHEN DATE(returned_at)=CURDATE() THEN 1 ELSE 0 END) AS returned_today,
    SUM(CASE
          WHEN status IN ('issued','partial','overdue')
           AND NOW()>due_back THEN 1 ELSE 0 END) AS overdue_now
  FROM rental_kit
";

if ($stmt = $conn->prepare($sql)) {
  $stmt->execute();
  $stmt->bind_result($issued, $due, $returned, $overdue);
  if ($stmt->fetch()) {
    $out['issued']   = (int)$issued;
    $out['due']      = (int)$due;
    $out['returned'] = (int)$returned;
    $out['overdue']  = (int)$overdue;
  }
  $stmt->close();
}

echo json_encode($out);
