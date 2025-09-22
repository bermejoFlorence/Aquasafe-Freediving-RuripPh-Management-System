<?php
$host = "localhost";
$username = "root"; // default sa XAMPP
$password = "";     // default sa XAMPP
$dbname = "diving_db";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
