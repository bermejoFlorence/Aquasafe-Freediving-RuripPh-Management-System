<?php
// Hostinger MySQL connection
$host     = 'localhost';                  // sa Hostinger, usually 'localhost'
$dbname   = 'u578970591_diving_db';       // DB name (kitang-kita sa screenshot)
$username = 'u578970591_diving_db';       // DB user (same as name mo)
$password = 'diving_ruripPh@2025'; // ilagay ang totoong password

$port     = 3306; // default

$conn = @new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_errno) {
    http_response_code(500);
    die('Database connection failed: '.$conn->connect_errno.' - '.$conn->connect_error);
}

// Optional but recommended
$conn->set_charset('utf8mb4');
@$conn->query("SET time_zone = '+08:00'"); // Asia/Manila
?>
