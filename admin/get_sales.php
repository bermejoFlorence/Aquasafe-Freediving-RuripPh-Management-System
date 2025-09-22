<?php
// diving/admin/get_sales.php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Huwag mag-print ng PHP warnings bilang HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../db_connect.php';
@$conn->query("SET time_zone = '+08:00'");

function send_json($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

// Guard
$g = strtolower($_GET['g'] ?? 'daily');
$allowed = ['daily','weekly','monthly','yearly'];
if (!in_array($g, $allowed, true)) $g = 'daily';

// NOTE: table name is `payment` (singular) per your DB screenshot
$labels = [];
$values = [];

try {
  if ($g === 'daily') {
    // last 30 days
    $sql = "
      SELECT DATE(payment_date) d, COALESCE(SUM(amount),0) t
      FROM payment
      WHERE status='paid'
        AND DATE(payment_date) >= (CURDATE() - INTERVAL 29 DAY)
      GROUP BY DATE(payment_date)
      ORDER BY d
    ";
    $res = $conn->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) { $map[$row['d']] = (float)$row['t']; }

    $today = new DateTime('today', new DateTimeZone('Asia/Manila'));
    for ($i=29; $i>=0; $i--) {
      $d = clone $today; $d->modify("-$i day");
      $key = $d->format('Y-m-d');
      $labels[] = $key;
      $values[] = $map[$key] ?? 0.0;
    }
  }
  elseif ($g === 'weekly') {
    // last 12 weeks (week starts Monday)
    $sql = "
      SELECT DATE(DATE_SUB(payment_date, INTERVAL WEEKDAY(payment_date) DAY)) wk_start,
             COALESCE(SUM(amount),0) t
      FROM payment
      WHERE status='paid'
        AND DATE(payment_date) >= (CURDATE() - INTERVAL 83 DAY)
      GROUP BY wk_start
      ORDER BY wk_start
    ";
    $res = $conn->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) { $map[$row['wk_start']] = (float)$row['t']; }

    $today = new DateTime('today', new DateTimeZone('Asia/Manila'));
    $dow = (int)$today->format('N'); // 1=Mon..7=Sun
    $monday = (clone $today)->modify("-".($dow-1)." days");
    for ($i=11; $i>=0; $i--) {
      $start = (clone $monday)->modify("-$i week");
      $key = $start->format('Y-m-d');
      $labels[] = $key;         // week start date
      $values[] = $map[$key] ?? 0.0;
    }
  }
  elseif ($g === 'monthly') {
    // last 12 months
    $sql = "
      SELECT DATE_FORMAT(payment_date,'%Y-%m-01') m_start,
             COALESCE(SUM(amount),0) t
      FROM payment
      WHERE status='paid'
        AND DATE(payment_date) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
      GROUP BY m_start
      ORDER BY m_start
    ";
    $res = $conn->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) { $map[$row['m_start']] = (float)$row['t']; }

    $first = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));
    for ($i=11; $i>=0; $i--) {
      $d = (clone $first)->modify("-$i month");
      $k = $d->format('Y-m-01');
      $labels[] = $d->format('Y-m'); // nicer label
      $values[] = $map[$k] ?? 0.0;
    }
  }
  else { // yearly: last 5 years including current
    $sql = "
      SELECT YEAR(payment_date) y, COALESCE(SUM(amount),0) t
      FROM payment
      WHERE status='paid'
        AND YEAR(payment_date) >= YEAR(CURDATE())-4
      GROUP BY y
      ORDER BY y
    ";
    $res = $conn->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) { $map[(int)$row['y']] = (float)$row['t']; }

    $year = (int)date('Y');
    for ($i=4; $i>=0; $i--) {
      $y = $year - $i;
      $labels[] = (string)$y;
      $values[] = $map[$y] ?? 0.0;
    }
  }

  send_json(['labels'=>$labels, 'sales'=>$values]);

} catch (Throwable $e) {
  // always JSON on error
  send_json(['error' => true, 'message' => $e->getMessage()], 500);
}
