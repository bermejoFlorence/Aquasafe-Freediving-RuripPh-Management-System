<?php
// admin/reschedule_booking.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  echo json_encode(['ok' => false, 'error' => 'Access denied']); exit;
}

date_default_timezone_set('Asia/Manila');

require_once '../db_connect.php';
@$conn->query("SET time_zone = '+08:00'");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// PHPMailer (same pattern as approvals)
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* -------------------- Helpers -------------------- */

// MariaDB-safe table check (SELECT sa information_schema)
function table_exists(mysqli $conn, string $table): bool {
  $sql = "SELECT 1
          FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('s', $table);
  $st->execute();
  $res = $st->get_result();
  $exists = ($res && $res->num_rows > 0);
  $st->close();
  return $exists;
}

// MariaDB-safe column check
function column_exists(mysqli $conn, string $table, string $column): bool {
  $sql = "SELECT 1
          FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('ss', $table, $column);
  $st->execute();
  $res = $st->get_result();
  $exists = ($res && $res->num_rows > 0);
  $st->close();
  return $exists;
}

// ensure calendar_block table (may UNIQUE sa block_date para sa UPSERT)
function ensure_calendar_block(mysqli $conn): void {
  $sql = "CREATE TABLE IF NOT EXISTS calendar_block (
            block_id   INT AUTO_INCREMENT PRIMARY KEY,
            block_date DATE NOT NULL,
            reason     TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_block_date (block_date)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $conn->query($sql);
}

/* -------------------- Read body -------------------- */

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || empty($body)) { $body = $_POST; }

$bulk        = !empty($body['bulk']);                         // true kapag resched ng buong araw
$groupDate   = $body['group_date']  ?? null;                  // YYYY-MM-DD (OLD date na ni-click sa calendar)
$targetDate  = $body['target_date'] ?? ($body['new_date'] ?? null); // YYYY-MM-DD (NEW date)
$reason      = trim($body['reason'] ?? '');
$bookingIds  = $body['booking_ids'] ?? (isset($body['booking_id']) ? [ (int)$body['booking_id'] ] : []);
$blockOld    = array_key_exists('block_old', $body) ? !!$body['block_old'] : true;

/* -------------------- Validation -------------------- */

if (!$targetDate || !$reason) {
  echo json_encode(['ok'=>false,'error'=>'Missing target date or reason']); exit;
}
$dtTarget = DateTime::createFromFormat('Y-m-d', $targetDate);
if (!$dtTarget) { echo json_encode(['ok'=>false,'error'=>'Invalid target_date']); exit; }

if ($bulk) {
  if (!$groupDate) { echo json_encode(['ok'=>false,'error'=>'Missing group_date']); exit; }
  if ($groupDate === $targetDate) {
    echo json_encode(['ok'=>false,'error'=>'Target date must be different from the original date']); exit;
  }
  if (!is_array($bookingIds)) $bookingIds = [];  // puwedeng wala; DB fallback tayo
} else {
  if (!is_array($bookingIds) || count($bookingIds)!==1) {
    echo json_encode(['ok'=>false,'error'=>'Missing booking_id']); exit;
  }
}

/* -------------------- Optional log table detection -------------------- */

$reschedTable = null;
foreach (['booking_reschedule','reschedule_log','reschedule_history'] as $t) {
  if (table_exists($conn, $t)) { $reschedTable = $t; break; }
}
// piliin kung alin ang column ng admin/actor
$actorCol = null;
if ($reschedTable) {
  if (column_exists($conn, $reschedTable, 'created_by')) $actorCol = 'created_by';
  elseif (column_exists($conn, $reschedTable, 'changed_by')) $actorCol = 'changed_by';
  elseif (column_exists($conn, $reschedTable, 'admin_id'))   $actorCol = 'admin_id';
}

/* -------------------- Main work -------------------- */

try {
  $conn->begin_transaction();

  // 1) Pull affected APPROVED bookings
  $rows = [];
  if ($bulk && count($bookingIds) === 0) {
    // walang IDs galing frontend: kunin lahat ng approved sa groupDate
    $sql = "
      SELECT b.booking_id, b.user_id, b.booking_date, b.status,
             u.full_name, u.email_address,
             pk.name AS package_name, pk.price AS package_price
      FROM booking b
      JOIN user u ON u.user_id=b.user_id
      LEFT JOIN package pk ON pk.package_id=b.package_id
      WHERE b.status='approved' AND DATE(b.booking_date)=?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param('s', $groupDate);
  } else {
    $ids = array_values(array_unique(array_map('intval', $bookingIds)));
    if (count($ids) === 0) { echo json_encode(['ok'=>false,'error'=>'No valid booking_ids']); exit; }
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $tp  = str_repeat('i', count($ids));
    $sql = "
      SELECT b.booking_id, b.user_id, b.booking_date, b.status,
             u.full_name, u.email_address,
             pk.name AS package_name, pk.price AS package_price
      FROM booking b
      JOIN user u ON u.user_id=b.user_id
      LEFT JOIN package pk ON pk.package_id=b.package_id
      WHERE b.status='approved' AND b.booking_id IN ($in)
    ";
    $st = $conn->prepare($sql);
    $st->bind_param($tp, ...$ids);
  }
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();

  if (count($rows) === 0) {
    echo json_encode(['ok'=>false,'error'=>'No approved bookings found for the given criteria']); exit;
  }

  // 2) Prep statements
  $stUpdate = $conn->prepare("UPDATE booking SET booking_date=?, updated_at=NOW() WHERE booking_id=?");
  $stNotif  = $conn->prepare("INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
                              VALUES (?, 'booking_reschedule', ?, ?, 0, NOW())");

  // Optional log (depends on table + actor column)
  $stLog = null;
  if ($reschedTable && $actorCol) {
    $sqlLog = "
      INSERT INTO {$reschedTable} (booking_id, old_datetime, new_datetime, reason, {$actorCol}, created_at)
      VALUES (?,?,?,?,?,NOW())
    ";
    $stLog = $conn->prepare($sqlLog);
  }

  // 3) Update bookings (keep original time-of-day)
  $updated = [];
  foreach ($rows as $row) {
    $bid   = (int)$row['booking_id'];
    $oldDT = $row['booking_date'];      // Y-m-d H:i:s
    $time  = (new DateTime($oldDT))->format('H:i:s');
    $newDT = $targetDate . ' ' . $time;

    $stUpdate->bind_param('si', $newDT, $bid);
    $stUpdate->execute();

    if ($stLog) {
      $adminId = (int)$_SESSION['user_id'];
      $stLog->bind_param('isssi', $bid, $oldDT, $newDT, $reason, $adminId);
      $stLog->execute();
    }

    $msg = sprintf(
      "Your booking for %s was rescheduled from %s to %s. Reason: %s",
      ($row['package_name'] ?? 'your selected package'),
      (new DateTime($oldDT))->format('M j, Y g:ia'),
      (new DateTime($newDT))->format('M j, Y g:ia'),
      $reason
    );
    $stNotif->bind_param('isi', $row['user_id'], $msg, $bid);
    $stNotif->execute();

    $updated[] = [
      'booking_id' => $bid,
      'user_id'    => (int)$row['user_id'],
      'email'      => $row['email_address'],
      'name'       => $row['full_name'],
      'package'    => $row['package_name'],
      'old_dt'     => $oldDT,
      'new_dt'     => $newDT
    ];
  }

  // 4) Block the OLD date (para maging “Full/Unavailable” sa calendar)
  if ($bulk && $blockOld && $groupDate) {
    ensure_calendar_block($conn);
    $stBlock = $conn->prepare("
      INSERT INTO calendar_block (block_date, reason, created_by, created_at)
      VALUES (?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE reason=VALUES(reason), created_by=VALUES(created_by), created_at=NOW()
    ");
    $adminId = (int)$_SESSION['user_id'];
    $stBlock->bind_param('ssi', $groupDate, $reason, $adminId);
    $stBlock->execute();
    $stBlock->close();
  }

  $conn->commit();

  // 5) Email everyone AFTER commit
  $emailResults = [];
  foreach ($updated as $u) {
    if (empty($u['email'])) continue;
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'markson.carino@cbsua.edu.ph';  // <-- palitan sa prod
      $mail->Password   = 'wzzc jkhk bejh xqoe';          // <-- app password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;

      $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
      $mail->addAddress($u['email'], $u['name'] ?: '');

      $oldStr = (new DateTime($u['old_dt']))->format('F j, Y g:ia');
      $newStr = (new DateTime($u['new_dt']))->format('F j, Y g:ia');
      $pkg    = $u['package'] ?: 'your selected package';

      $mail->isHTML(true);
      $mail->Subject = "Booking Rescheduled - AquaSafe RuripPH";
      $mail->Body = "
        Hi <b>".htmlspecialchars($u['name'] ?: 'Diver')."</b>,<br><br>
        Your approved booking for <b>".htmlspecialchars($pkg)."</b> has been <b>RESCHEDULED</b>.<br><br>
        <b>From:</b> {$oldStr}<br>
        <b>To:</b> {$newStr}<br>
        <b>Reason:</b> ".nl2br(htmlspecialchars($reason))."<br><br>
        If the new date doesn't work for you, please reply to this email so we can assist.<br><br>
        <b>AquaSafe RuripPH</b>
      ";
      $mail->AltBody = "Your booking was rescheduled from {$oldStr} to {$newStr}. Reason: {$reason}";
      $mail->send();

      $emailResults[] = ['booking_id'=>$u['booking_id'],'emailed'=>true];
    } catch (Exception $e) {
      $emailResults[] = ['booking_id'=>$u['booking_id'],'emailed'=>false,'error'=>$mail->ErrorInfo];
    }
  }

  echo json_encode([
    'ok'          => true,
    'mode'        => $bulk ? 'bulk' : 'single',
    'group_date'  => $groupDate,
    'target_date' => $targetDate,
    'count'       => count($updated),
    'updated'     => $updated,
    'emails'      => $emailResults
  ]);
  exit;

} catch (Throwable $e) {
  try { $conn->rollback(); } catch (Throwable $e2) {}
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}
