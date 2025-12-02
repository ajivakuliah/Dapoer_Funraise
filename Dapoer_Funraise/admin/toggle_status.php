<?php
require 'config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Internal Server Error: Database connection not established.');
}

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Get product ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ./admin/daftar_produk.php?msg=' . urlencode('ID produk tidak valid'));
    exit;
}

try {
    // Get current status
    $stmt = $pdo->prepare("SELECT Status FROM produk WHERE ID = ?");
    $stmt->execute([$id]);
    $produk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produk) {
        header('Location: ./admin/daftar_produk.php?msg=' . urlencode('Produk tidak ditemukan'));
        exit;
    }
    
    // Toggle status
    $newStatus = ($produk['Status'] === 'aktif') ? 'tidak_aktif' : 'aktif';
    
    $updateStmt = $pdo->prepare("UPDATE produk SET Status = ?, updated_at = CURRENT_TIMESTAMP WHERE ID = ?");
    $updateStmt->execute([$newStatus, $id]);
    
    $statusText = ($newStatus === 'aktif') ? 'diaktifkan' : 'dinonaktifkan';
    header('Location: ./admin/daftar_produk.php?msg=' . urlencode("Produk berhasil $statusText!"));
    exit;
    
} catch (PDOException $e) {
    error_log("Toggle Status Error: " . $e->getMessage());
    header('Location: ./admin/daftar_produk.php?msg=' . urlencode('Gagal mengubah status produk'));
    exit;
}
?>