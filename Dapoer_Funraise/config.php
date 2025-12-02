<?php
// config.php — Koneksi Database untuk Dapoer Funraise
$host = 'localhost';
$db   = 'dapoer_funraise';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(503);
    die("<h2>Database Sedang Tidak Tersedia</h2><p>Mohon coba lagi nanti.</p>");
}