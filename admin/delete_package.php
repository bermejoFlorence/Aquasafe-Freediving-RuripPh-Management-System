<?php
// delete_package.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['success'=>false, 'msg'=>'Unauthorized']);
  exit;
}

require_once '../db_connect.php';

$pid = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
if ($pid <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'msg'=>'Invalid package id']);
  exit;
}

$conn->begin_transaction();
try {
  // delete features first (FK safety)
  $stmt = $conn->prepare("DELETE FROM package_feature WHERE package_id=?");
  $stmt->bind_param('i', $pid);
  $stmt->execute();
  $stmt->close();

  // delete package
  $stmt = $conn->prepare("DELETE FROM package WHERE package_id=?");
  $stmt->bind_param('i', $pid);
  $stmt->execute();
  $aff = $stmt->affected_rows;
  $stmt->close();

  if ($aff < 1) throw new Exception('Package not found or already deleted');

  $conn->commit();
  echo json_encode(['success'=>true, 'msg'=>'Package deleted']);
} catch (Exception $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
}
