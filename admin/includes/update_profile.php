<?php
// update_profile.php
session_start();
include '../../db_connect.php'; // adjust path as needed

$user_id = $_POST['user_id'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email_address'] ?? '';
$address = $_POST['address'] ?? '';

// Handle profile pic upload
$profile_pic = $_SESSION['profile_pic'] ?? 'default.png';
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
    $destination = __DIR__ . '/../../uploads/' . $filename;
    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
        $profile_pic = $filename;
    }
}

// Update the user in the database
$stmt = $conn->prepare("UPDATE user SET full_name=?, email_address=?, address=?, profile_pic=? WHERE user_id=?");
$stmt->bind_param('ssssi', $full_name, $email, $address, $profile_pic, $user_id);

if ($stmt->execute()) {
    // Update session vars
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email_address'] = $email;
    $_SESSION['address'] = $address;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['update_profile_msg'] = "Profile updated successfully!";
} else {
    $_SESSION['update_profile_msg'] = "Error updating profile!";
}

header('Location: ../index.php'); // redirect back to dashboard
exit;
