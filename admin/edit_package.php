<?php
include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = $_POST['package_id'] ?? null;
    $package_name = $_POST['package_name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $features = $_POST['features'] ?? [];

    if (!$package_id || !$package_name || !is_numeric($price)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid input.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Update package table
        $stmt = $conn->prepare("UPDATE package SET name=?, price=? WHERE package_id=?");
        $stmt->bind_param("sdi", $package_name, $price, $package_id);
        $stmt->execute();
        $stmt->close();

        // Delete old features
        $stmt = $conn->prepare("DELETE FROM package_feature WHERE package_id=?");
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $stmt->close();

        // Insert new features
        if (!empty($features)) {
            $stmt = $conn->prepare("INSERT INTO package_feature (package_id, feature) VALUES (?, ?)");
            foreach ($features as $feature) {
                $feature = trim($feature);
                if ($feature !== '') {
                    $stmt->bind_param("is", $package_id, $feature);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => 'Package updated successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'msg' => 'Failed to update package.']);
    }
} else {
    echo json_encode(['success' => false, 'msg' => 'Invalid request method.']);
}
?>
