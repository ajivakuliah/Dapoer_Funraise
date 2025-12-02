<?php
require '../config.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header('Location: dashboard.php?msg=ID tidak valid');
    exit;
}

// Ambil nama file foto dari database
$stmt = $pdo->prepare("SELECT Foto_Produk FROM produk WHERE ID=?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if ($p) {
    // Hapus file foto jika ada
    if (!empty($p['Foto_Produk']) && file_exists(__DIR__ . '/uploads/' . $p['Foto_Produk'])) {
        if (!unlink(__DIR__ . 'uploads/' . $p['Foto_Produk'])) {
            error_log("Gagal menghapus file: uploads/" . $p['Foto_Produk']);
        }
    }

    // Hapus record di database
    try {
        $delStmt = $pdo->prepare("DELETE FROM produk WHERE ID=?");
        $delStmt->execute([$id]);
        header('Location: daftar_produk.php?msg=Produk berhasil dihapus!');
        exit;
    } catch (PDOException $e) {
        header('Location: daftar_produk.php?msg=Gagal menghapus produk');
        exit;
    }

} else {
    header('Location: dashboard.php?msg=Produk tidak ditemukan');
    exit;
}
?>
