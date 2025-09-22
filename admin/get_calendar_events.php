<?php
include '../db_connect.php';

$max_slots = 5;

// Accept calendar visible range (from FullCalendar GET params)
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');

$results = [];

// Query bookings in the visible range, group by date
$sql = "SELECT DATE(booking_date) as booking_date, GROUP_CONCAT(u.full_name) as names
        FROM booking b
        JOIN user u ON b.user_id = u.user_id
        WHERE b.status = 'approved'
          AND booking_date BETWEEN ? AND ?
        GROUP BY DATE(booking_date)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$query = $stmt->get_result();

// Map: date => array of names
$dates = [];
while ($row = $query->fetch_assoc()) {
    $date = $row['booking_date'];
    $names = $row['names'] ? explode(',', $row['names']) : [];
    $dates[$date] = $names;
}

// For visible days, show pills
$period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));

foreach ($period as $dt) {
    $date = $dt->format('Y-m-d');
    $names = $dates[$date] ?? [];
    $available = $max_slots - count($names);

    // Show slot/fully booked
    if ($available > 0) {
        $results[] = [
            "title" => "<b>Slots: $available</b>",
            "start" => $date,
            "display" => "auto",
            "className" => "slot-pill"
        ];
    } else {
        $results[] = [
            "title" => "<b>Fully booked</b>",
            "start" => $date,
            "display" => "auto",
            
            "className" => "full-pill"
        ];
    }

    // Booked names
    foreach ($names as $n) {
        $results[] = [
            "title" => htmlentities($n),
            "start" => $date,
            "display" => "auto",
            "className" => "name-pill"
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>
