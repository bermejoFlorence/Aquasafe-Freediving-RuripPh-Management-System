<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'msg' => 'Access denied.']);
    exit;
}

include '../db_connect.php';

$name = trim($_POST['package_name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$features = $_POST['features'] ?? [];

if (!$name || $price < 0) {
    echo json_encode(['success' => false, 'msg' => 'Please fill all required fields.']);
    exit;
}

// Insert package
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO package (name, price, date_created) VALUES (?, ?, ?)");
$stmt->bind_param('sds', $name, $price, $now);

if ($stmt->execute()) {
    $package_id = $stmt->insert_id;

    // Insert features
    $featureStmt = $conn->prepare("INSERT INTO package_feature (package_id, feature) VALUES (?, ?)");
    foreach ($features as $feat) {
        $f = trim($feat);
        if ($f) {
            $featureStmt->bind_param('is', $package_id, $f);
            $featureStmt->execute();
        }
    }
    echo json_encode(['success' => true, 'msg' => 'Package added successfully!']);
} else {
    echo json_encode(['success' => false, 'msg' => 'Failed to add package!']);
}
?>
