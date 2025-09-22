<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}
include '../db_connect.php';

// PHPMailer requirement (adjust path if needed)
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- AJAX HANDLER FOR PAYMENT APPROVAL/REJECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $payment_type = $_POST['payment_type'] ?? ''; // Downpayment/Full Payment/Rejected

    // Get the latest payment for this booking
    $payment_sql = "SELECT * FROM payment WHERE booking_id=? ORDER BY payment_id DESC LIMIT 1";
    $stmt = $conn->prepare($payment_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    // Fetch user info + booking info (for email)
    $user_sql = "
        SELECT u.full_name, u.email_address, b.booking_date, pk.name AS package_name, pk.price AS package_price
        FROM booking b
        JOIN user u ON b.user_id = u.user_id
        LEFT JOIN package pk ON pk.package_id = b.package_id
        WHERE b.booking_id = ?
    ";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // (NEW) Get user_id for notifications (kept separate so we don't change your existing SELECT)
    $notify_user_id = null;
    if ($booking_id > 0) {
        if ($stUid = $conn->prepare("SELECT user_id FROM booking WHERE booking_id=? LIMIT 1")) {
            $stUid->bind_param("i", $booking_id);
            $stUid->execute();
            $uidRow = $stUid->get_result()->fetch_assoc();
            if ($uidRow) $notify_user_id = (int)$uidRow['user_id'];
            $stUid->close();
        }
    }

    if ($payment && $user) {
        $booking_date = date("F j, Y", strtotime($user['booking_date']));
        $package_name = $user['package_name'] ?? '[Unknown]';
        $package_price = isset($user['package_price']) ? '₱ ' . number_format($user['package_price'], 2) : '₱ 0.00';
        $amount_paid = isset($payment['amount']) ? '₱ ' . number_format($payment['amount'], 2) : '₱ 0.00';
        $payment_label = ($payment_type == 'Full Payment') ? 'Full Payment' : 'Downpayment';

        if ($action === 'approve_payment') {
            // Update payment status
            $new_status = ($payment_type == 'Full Payment') ? 'paid' : 'downpayment';
            $stmt = $conn->prepare("UPDATE payment SET status=? WHERE payment_id=?");
            $stmt->bind_param("si", $new_status, $payment['payment_id']);
            $stmt->execute();

            // Update booking status
            $stmt = $conn->prepare("UPDATE booking SET status='approved' WHERE booking_id=?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();

            // --- (NEW) Save notification for the client ---
            if ($notify_user_id) {
                $notif_msg = "Your booking for {$package_name} on {$booking_date} has been approved.";
                if ($n = $conn->prepare("INSERT INTO notification (user_id, type, message, related_id, is_read, created_at) VALUES (?,?,?,?,0,NOW())")) {
                    $type = 'booking_status';
                    $n->bind_param("issi", $notify_user_id, $type, $notif_msg, $booking_id);
                    $n->execute();
                    $n->close();
                }
            }

            // --- SEND EMAIL ---
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'markson.carino@cbsua.edu.ph'; // change to your email
                $mail->Password   = 'wzzc jkhk bejh xqoe';    // change to your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
                $mail->addAddress($user['email_address'], $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = "Booking Approved - AquaSafe RuripPH";
                $mail->Body    = "
                    Hi <b>{$user['full_name']}</b>,<br><br>
                    Your booking on <b>{$booking_date}</b> has been <b>APPROVED</b>.<br>
                    <b>Package:</b> {$package_name}<br>
                    <b>Package Price:</b> {$package_price}<br>
                    <b>Amount Paid:</b> {$amount_paid}<br>
                    <b>Payment Type:</b> {$payment_label}<br><br>
                    Thank you for booking with us!<br>
                    <b>AquaSafe RuripPH</b>
                ";
                $mail->send();
            } catch (Exception $e) {
                // Optional: log $mail->ErrorInfo if needed
            }

            echo json_encode(['success' => true, 'message' => 'Payment approved and email sent!']);
            exit;

        } elseif ($action === 'reject_payment') {
            // Update payment status
            $stmt = $conn->prepare("UPDATE payment SET status='rejected' WHERE payment_id=?");
            $stmt->bind_param("i", $payment['payment_id']);
            $stmt->execute();

            // Update booking status
            $stmt = $conn->prepare("UPDATE booking SET status='rejected' WHERE booking_id=?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();

            // --- (NEW) Save notification for the client ---
            if ($notify_user_id) {
                $notif_msg = "Your booking for {$package_name} on {$booking_date} was rejected.";
                if ($n = $conn->prepare("INSERT INTO notification (user_id, type, message, related_id, is_read, created_at) VALUES (?,?,?,?,0,NOW())")) {
                    $type = 'booking_status';
                    $n->bind_param("issi", $notify_user_id, $type, $notif_msg, $booking_id);
                    $n->execute();
                    $n->close();
                }
            }

            // --- SEND EMAIL ---
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'markson.carino@cbsua.edu.ph'; // change to your email
                $mail->Password   = 'wzzc jkhk bejh xqoe';    // change to your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
                $mail->addAddress($user['email_address'], $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = "Booking Payment Rejected - AquaSafe RuripPH";
                $mail->Body    = "
                    Hi <b>{$user['full_name']}</b>,<br><br>
                    Your payment for your booking on <b>{$booking_date}</b> was <b>REJECTED</b>.<br>
                    <b>Package:</b> {$package_name}<br>
                    <b>Amount Attempted:</b> {$amount_paid}<br>
                    If you believe this was a mistake or want to try again, please contact us or upload a new payment proof.<br><br>
                    <b>AquaSafe RuripPH</b>
                ";
                $mail->send();
            } catch (Exception $e) {
                // Optional: log $mail->ErrorInfo if needed
            }

            echo json_encode(['success' => true, 'message' => 'Payment rejected and email sent!']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Payment not found or invalid request.']);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
?>
