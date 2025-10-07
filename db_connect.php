<?php
// db_connect.php — works for localhost AND Hostinger, with utf8mb4

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// -------- Detect environment (simple) --------
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost','127.0.0.1']) || php_sapi_name()==='cli';

if ($is_local) {
  // ===== LOCAL (XAMPP) =====
  $DB_HOST = '127.0.0.1';
  $DB_USER = 'root';
  $DB_PASS = '';
  $DB_NAME = 'diving_db';
  $DB_PORT = 3306;
} else {
  // ===== HOSTINGER =====
  // Fill these from Hostinger’s MySQL details
  $DB_HOST = 'localhost';           // usually 'localhost' sa Hostinger
  $DB_USER = 'u578970591_user';     // ← palitan
  $DB_PASS = 'diving_ruripPh@2025';    // ← palitan
  $DB_NAME = 'u578970591_diving_db';// ← base sa screenshot mo
  $DB_PORT = 3306;
}

// -------- Connect --------
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

// -------- Force utf8mb4 (fixes emoji/strict issues) --------
$conn->set_charset('utf8mb4');
@$conn->query("SET NAMES utf8mb4");

// (optional but nice)
@$conn->query("SET time_zone = '+08:00'");

// You can also log errors to file during debug:
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__.'/php-error.log');
