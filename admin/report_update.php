<?php
// admin/report_update.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Access denied']);
  exit;
}

require_once '../db_connect.php';

/** PHPMailer (same as your payment flow) */
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function jfail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg]); exit; }
function jok($data=[]){ echo json_encode(array_merge(['ok'=>true], $data)); exit; }
function now(){ return date('Y-m-d H:i:s'); }
function safe_note($s){ $s = trim($s ?? ''); return mb_substr($s, 0, 1000); } // cap note length

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jfail('Invalid request method');

$action   = $_POST['action']      ?? '';
$reportId = (int)($_POST['report_id'] ?? 0);
$noteIn   = safe_note($_POST['note'] ?? '');
$hide     = (int)($_POST['hide'] ?? 0);
$hours    = (int)($_POST['deadline_hours'] ?? 48);
// Ban duration options coming from the modal
$banDays   = isset($_POST['ban_days'])  ? max(0, (int)$_POST['ban_days'])  : 0;
$banHours  = isset($_POST['ban_hours']) ? max(0, (int)$_POST['ban_hours']) : 0;
$permanent = !empty($_POST['permanent']) ? 1 : 0;
$adminId   = (int)($_SESSION['user_id'] ?? 0); // for banned_by, logs


if (!$reportId || !in_array($action, ['notify_start','resolve','ban_now'], true)) {
  jfail('Invalid parameters');
}

/** Load the report + targets */
$sql = "
  SELECT
    r.*,
    u.user_id         AS target_user_id,
    u.full_name       AS target_full_name,
    u.email_address   AS target_email,
    p.title           AS post_title,
    p.body            AS post_body,
    c.body            AS comment_body
  FROM forum_report r
  JOIN user u ON u.user_id = r.target_owner_id
  LEFT JOIN forum_post p ON p.post_id = r.post_id
  LEFT JOIN forum_post_comment c
    ON (c.comment_id = r.target_id AND r.target_type='comment')
  WHERE r.report_id = ?
  LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param("i", $reportId);
$st->execute();
$rep = $st->get_result()->fetch_assoc();
$st->close();
if (!$rep) jfail('Report not found');

/** Small helpers */
function append_note(mysqli $conn, int $reportId, string $append){
  if ($append === '') return;
  $time = date('Y-m-d H:i');
  $line = "\n[$time] ADMIN: ".$append;
  $sql  = "UPDATE forum_report SET notes = CONCAT(COALESCE(notes,''), ?) WHERE report_id=?";
  $st   = $conn->prepare($sql);
  $st->bind_param("si", $line, $reportId);
  $st->execute();
  $st->close();
}
function add_notif(mysqli $conn, int $userId, string $type, string $message, int $relatedId){
  $sql = "INSERT INTO notification (user_id, type, message, related_id, is_read, created_at)
          VALUES (?,?,?,?,0,NOW())";
  $st  = $conn->prepare($sql);
  $st->bind_param("issi", $userId, $type, $message, $relatedId);
  $st->execute();
  $st->close();
}
function mailer(): PHPMailer {
  $mail = new PHPMailer(true);
  // === copy your payment SMTP settings here ===
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'markson.carino@cbsua.edu.ph';      // same as payment flow
  $mail->Password   = 'wzzc jkhk bejh xqoe';               // same app password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->setFrom('markson.carino@cbsua.edu.ph', 'AquaSafe RuripPH');
  $mail->isHTML(true);
  return $mail;
}
function reason_label($r){
  if (!$r) return '—';
  $map = [
    'spam'=>'Spam','offensive'=>'Offensive','harassment'=>'Harassment',
    'hate'=>'Hate','nsfw'=>'NSFW','other'=>'Other'
  ];
  return $map[$r] ?? $r;
}
function short_excerpt($s, $len=140){
  $s = trim((string)$s);
  $s = preg_replace('/\s+/', ' ', $s);
  return (mb_strlen($s) > $len) ? (mb_substr($s,0,$len-1).'…') : $s;
}

/** Switch actions */
if ($action === 'notify_start') {
  $deadline = date('Y-m-d H:i:s', time() + max(1,$hours)*3600);

  // Update report
  $sql = "UPDATE forum_report
          SET status='under_review',
              notified_at = NOW(),
              respond_deadline = ?,
              hide_target = ?
          WHERE report_id=?";
  $st = $conn->prepare($sql);
  $st->bind_param("sii", $deadline, $hide, $reportId);
  $st->execute();
  $st->close();

  append_note($conn, $reportId, $noteIn);

  // Build email to reported user
  $isComment = ($rep['target_type'] === 'comment');
  $snippet   = $isComment ? $rep['comment_body'] : ($rep['post_body'] ?? '');
  $title     = $isComment ? ('Comment #'.$rep['target_id']) : ($rep['post_title'] ?: 'Your Post');
  $reason    = reason_label($rep['reason']);

  // Notification row
  $notifMsg = "Your ".($isComment?'comment':'post')." was reported (reason: $reason). "
            . "Please comply within 48 hours or your account may be banned.";
  add_notif($conn, (int)$rep['target_user_id'], 'report_notice', $notifMsg, $reportId);

  // Send email
  try {
    $mail = mailer();
    $mail->addAddress($rep['target_email'], $rep['target_full_name']);
    $mail->Subject = "Action required: Your ".($isComment?'comment':'post')." was reported (Report #{$rep['report_id']})";
    $deadlineFmt = date('M j, Y g:ia', strtotime($deadline));
    $mail->Body = "
      Hi <b>".htmlspecialchars($rep['target_full_name'])."</b>,<br><br>
      Your ".($isComment?'comment':'post')." has been <b>reported</b> and is now under review.<br>
      <b>Reason:</b> ".htmlspecialchars($reason)."<br>
      <b>Item:</b> ".htmlspecialchars($title)."<br>
      <b>Excerpt:</b> ".htmlspecialchars(short_excerpt($snippet))."<br><br>
      Please review our guidelines and comply or reply to this email within <b>48 hours</b> (until <b>{$deadlineFmt}</b>).<br>
      If no action is taken by the deadline, your account may be banned.<br><br>
      You can reply to this email if you want to appeal or provide context.<br><br>
      — AquaSafe RuripPH Moderation
    ";
    $mail->send();
  } catch (Exception $e) {
    // Optional: log $mail->ErrorInfo
  }

  jok(['status'=>'under_review','deadline'=>$deadline]);
}

if ($action === 'resolve') {
  // Mark resolved + unhide target
  $sql = "UPDATE forum_report
          SET status='resolved', hide_target=0
          WHERE report_id=?";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $reportId);
  $st->execute();
  $st->close();

  append_note($conn, $reportId, $noteIn);

  // Notify reported user (and optionally the reporter)
  add_notif($conn, (int)$rep['target_user_id'], 'report_resolved',
            'Your reported item has been resolved. Thank you for complying.', $reportId);

  try {
    $mail = mailer();
    $mail->addAddress($rep['target_email'], $rep['target_full_name']);
    $mail->Subject = "Resolved: Report #{$rep['report_id']}";
    $mail->Body = "
      Hi <b>".htmlspecialchars($rep['target_full_name'])."</b>,<br><br>
      Your case (Report #{$rep['report_id']}) has been <b>resolved</b>.<br>
      Thank you for your cooperation.<br><br>
      — AquaSafe RuripPH Moderation
    ";
    $mail->send();
  } catch (Exception $e) {}

  jok(['status'=>'resolved']);
}

if ($action === 'ban_now') {
  // Inputs coming from the ban modal
  $mode        = $_POST['ban_mode']   ?? 'preset'; // preset|custom|perm
  $hoursInput  = (int)($_POST['ban_hours'] ?? 168); // for preset (24, 72, 168, 720)
  $customDays  = (int)($_POST['ban_custom_days'] ?? 0);
  $permFlag    = (int)($_POST['ban_perm'] ?? 0);
  $reasonText  = safe_note($_POST['ban_reason'] ?? $noteIn); // use textarea or admin note

  $adminId     = (int)($_SESSION['user_id']);
  $targetId    = (int)$rep['target_user_id'];

  // Compute end time
  $isPermanent = ($mode === 'perm') || ($permFlag === 1);
  $startAt = date('Y-m-d H:i:s');
  if ($isPermanent) {
    $endAt = null;
  } else {
    $totalHours = ($mode === 'custom' && $customDays > 0)
      ? ($customDays * 24)
      : max(1, $hoursInput);
    $endAt = date('Y-m-d H:i:s', time() + $totalHours * 3600);
  }

  // Begin
  $conn->begin_transaction();
  try {
    // 1) Log into user_ban
    $q1 = "INSERT INTO user_ban (user_id, report_id, type, reason_text, start_at, end_at, created_by)
           VALUES (?,?,?,?,?,?,?)";
    $st = $conn->prepare($q1);
    $type = $isPermanent ? 'perm' : 'temp';
    $st->bind_param("iissssi", $targetId, $reportId, $type, $reasonText, $startAt, $endAt, $adminId);
    $st->execute();
    $st->close();

    // 2) Update user status (NO banned_by column here)
    if ($isPermanent) {
      $q2 = "UPDATE user
             SET account_status='banned',
                 is_banned=1,
                 banned_until=NULL,
                 banned_at=NOW(),
                 banned_reason=?,
                 ban_reason=?,
                 session_version = COALESCE(session_version,0) + 1
             WHERE user_id=? AND role='client'";
      $st = $conn->prepare($q2);
      $st->bind_param("ssi", $reasonText, $reasonText, $targetId);
    } else {
      $q2 = "UPDATE user
             SET account_status='banned',
                 is_banned=1,
                 banned_until=?,
                 banned_at=NOW(),
                 banned_reason=?,
                 ban_reason=?,
                 session_version = COALESCE(session_version,0) + 1
             WHERE user_id=? AND role='client'";
      $st = $conn->prepare($q2);
      $st->bind_param("sssi", $endAt, $reasonText, $reasonText, $targetId);
    }
    $st->execute();
    if ($st->affected_rows < 1) {
      $st->close();
      throw new Exception('Ban failed: user not updated');
    }
    $st->close();

    // 3) Close the report + hide target
    $q3 = "UPDATE forum_report SET status='closed', hide_target=1 WHERE report_id=?";
    $st = $conn->prepare($q3);
    $st->bind_param("i", $reportId);
    $st->execute();
    $st->close();

    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    jfail('Ban failed: ' . $e->getMessage(), 500);
  }

  // 4) Notifications + Email
  add_notif($conn, $targetId, 'ban',
    $isPermanent
      ? 'Your account has been permanently banned.'
      : ('Your account has been banned until ' . date('M j, Y g:ia', strtotime($endAt)) . '.'),
    $reportId
  );

  try {
    $mail = mailer();
    $mail->addAddress($rep['target_email'], $rep['target_full_name']);
    if ($isPermanent) {
      $mail->Subject = "Account permanently banned — AquaSafe RuripPH";
      $mail->Body = "
        Hi <b>".htmlspecialchars($rep['target_full_name'])."</b>,<br><br>
        After review of Report #{$rep['report_id']}, your account has been <b>permanently banned</b> due to policy violations.<br>
        <b>Reason:</b> ".htmlspecialchars($reasonText)."<br><br>
        You may reply to this email to appeal.<br><br>
        — AquaSafe RuripPH Moderation
      ";
    } else {
      $untilFmt = date('M j, Y g:ia', strtotime($endAt));
      $mail->Subject = "Temporary account ban — AquaSafe RuripPH";
      $mail->Body = "
        Hi <b>".htmlspecialchars($rep['target_full_name'])."</b>,<br><br>
        After review of Report #{$rep['report_id']}, your account has been <b>temporarily banned</b> until <b>{$untilFmt}</b>.<br>
        <b>Reason:</b> ".htmlspecialchars($reasonText)."<br><br>
        You may reply to this email to appeal.<br><br>
        — AquaSafe RuripPH Moderation
      ";
    }
    $mail->send();
  } catch (Exception $e) { /* ignore mail errors */ }

  jok([
    'status'  => 'closed',
    'banned'  => true,
    'perm'    => $isPermanent,
    'until'   => $endAt
  ]);
}

jfail('Unknown action');
