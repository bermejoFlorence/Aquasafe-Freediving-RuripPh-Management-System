<?php
include '../db_connect.php';

$max_slots = 5;

// Visible range coming from FullCalendar
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');

header('Content-Type: application/json');

$results = [];

/* -------------------------------
   1) Get approved bookings by day
-------------------------------- */
$sql = "SELECT DATE(b.booking_date) AS d, GROUP_CONCAT(u.full_name) AS names
        FROM booking b
        JOIN user u ON u.user_id = b.user_id
        WHERE b.status = 'approved'
          AND b.booking_date BETWEEN ? AND ?
        GROUP BY DATE(b.booking_date)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$rs = $stmt->get_result();

$byDate = []; // d => [names]
while ($row = $rs->fetch_assoc()) {
  $d = $row['d'];
  $byDate[$d] = $row['names'] ? explode(',', $row['names']) : [];
}
$stmt->close();

/* -------------------------------
   2) Get blocked days (old dates)
      from calendar_block created
      by reschedule_booking.php
-------------------------------- */
$blocked = []; // d => reason
if ($stmt = $conn->prepare("SELECT block_date, reason 
                            FROM calendar_block
                            WHERE block_date BETWEEN ? AND ?")) {
  $stmt->bind_param('ss', $start, $end);
  $stmt->execute();
  $rb = $stmt->get_result();
  while ($r = $rb->fetch_assoc()) {
    $blocked[$r['block_date']] = $r['reason'] ?? '';
  }
  $stmt->close();
}

/* -------------------------------
   3) Build pills day by day
-------------------------------- */
$period = new DatePeriod(
  new DateTime($start),
  new DateInterval('P1D'),
  (new DateTime($end))->modify('+1 day')
);

foreach ($period as $dt) {
  $d = $dt->format('Y-m-d');

  // If the day is blocked, show the reason and skip slots/names
  if (isset($blocked[$d])) {
    $reason = trim($blocked[$d]) ?: 'Unavailable';
    $results[] = [
      "title"     => "<b>Unavailable</b><br>" . htmlentities($reason),
      "start"     => $d,
      "display"   => "auto",
      // use red pill styling; your JS already treats 'full-pill' as non-clickable
      "className" => "full-pill"
    ];
    continue;
  }

  // Otherwise, show Slots / Fully booked + the approved names
  $names = $byDate[$d] ?? [];
  $available = $max_slots - count($names);

  if ($available > 0) {
    $results[] = [
      "title"     => "<b>Slots: {$available}</b>",
      "start"     => $d,
      "display"   => "auto",
      "className" => "slot-pill"
    ];
  } else {
    $results[] = [
      "title"     => "<b>Fully booked</b>",
      "start"     => $d,
      "display"   => "auto",
      "className" => "full-pill"
    ];
  }

  foreach ($names as $n) {
    $results[] = [
      "title"     => htmlentities($n),
      "start"     => $d,
      "display"   => "auto",
      "className" => "name-pill"
    ];
  }
}

echo json_encode($results);
