<?php
include '../db_connect.php';

$max_slots = 5;
$results = [];

$today = date('Y-m-d');

// Get approved + pending bookings per date
$sql = "SELECT DATE(booking_date) as booking_date, COUNT(*) as total 
        FROM booking 
        WHERE status IN ('pending','approved')
        GROUP BY DATE(booking_date)";
$query = $conn->query($sql);

// Map: date => booked_count
$dates = [];
while ($row = $query->fetch_assoc()) {
    $dates[$row['booking_date']] = (int)$row['total'];
}

// Build slots only for today and future
$period = new DatePeriod(new DateTime($today), new DateInterval('P1D'), (new DateTime($today))->modify('+31 days')); // 1 month ahead

foreach ($period as $dt) {
    $date = $dt->format('Y-m-d');
    $booked = isset($dates[$date]) ? $dates[$date] : 0;
    $available = $max_slots - $booked;

    if ($available > 0) {
        $results[] = [
            "title" => "Slots: $available",
            "start" => $date,
            "display" => "auto",
            "className" => "slot-pill"
        ];
    } else {
        $results[] = [
            "title" => "Fully booked",
            "start" => $date,
            "display" => "auto",
            "className" => "full-pill"
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>
