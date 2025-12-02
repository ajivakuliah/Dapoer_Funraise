<?php
session_start();
require 'config.php';

// Cek login admin
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Ambil parameter
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);

// Validasi
if ($id <= 0 || !in_array($action, ['verify', 'unverify'])) {
    $_SESSION['pesan_error'] = 'Parameter tidak valid.';
    header("Location: testimoni.php?filter=$filter&page=$page");
    exit;
}

try {
    // Tentukan status baru
    $new_status = ($action === 'verify') ? 1 : 0;
    
    // Update status verifikasi
    $stmt = $pdo->prepare("UPDATE testimoni SET is_verified = :status WHERE id = :id");
    $stmt->execute([
        'status' => $new_status,
        'id' => $id
    ]);

    // Pesan sukses
    if ($action === 'verify') {
        $msg = 'Testimoni berhasil diverifikasi dan akan ditampilkan di halaman utama.';
    } else {
        $msg = 'Verifikasi testimoni berhasil dibatalkan.';
    }

    header("Location: testimoni.php?filter=$filter&page=$page&msg=" . urlencode($msg));
    exit;

} catch (PDOException $e) {
    error_log("Toggle Verifikasi Error: " . $e->getMessage());
    $_SESSION['pesan_error'] = 'Terjadi kesalahan saat memproses verifikasi.';
    header("Location: testimoni.php?filter=$filter&page=$page");
    exit;
}
?>