<?php
session_start();
require '../config.php';

// Cek login admin
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Ambil parameter
$id = (int)($_GET['id'] ?? 0);
$filter = $_GET['filter'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);

// Validasi ID
if ($id <= 0) {
    header("Location: testimoni.php?filter=$filter&page=$page");
    exit;
}

try {
    // Hapus testimoni
    $stmt = $pdo->prepare("DELETE FROM testimoni WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $msg = 'Testimoni berhasil dihapus.';
    header("Location: testimoni.php?filter=$filter&page=$page&msg=" . urlencode($msg));
    exit;

} catch (PDOException $e) {
    error_log("Hapus Testimoni Error: " . $e->getMessage());
    header("Location: testimoni.php?filter=$filter&page=$page&msg=" . urlencode('Gagal menghapus testimoni.'));
    exit;
}
?>