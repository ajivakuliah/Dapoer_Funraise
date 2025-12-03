<?php
include "../config.php";
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin/pengaturan.css">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-sliders-h"></i>
            <span>Panel Admin</span>
        </h1>
        <p>Kelola semua bagian website dengan mudah</p>
    </div>

    <div class="menu-grid">
        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-heading"></i>
            </div>
            <h3 class="menu-title">Header</h3>
            <p class="menu-desc">Kelola logo dan nama usaha di website</p>
            <a href="header.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-images"></i>
            </div>
            <h3 class="menu-title">Beranda</h3>
            <p class="menu-desc">Kelola latar belakang dan teks utama halaman depan</p>
            <a href="hero.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-list-ol"></i>
            </div>
            <h3 class="menu-title">Langkah</h3>
            <p class="menu-desc">Atur langkah-langkah cara melakukan pemesanan</p>
            <a href="cara-pesan.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 class="menu-title">Konten</h3>
            <p class="menu-desc">Kelola informasi, sejarah dan foto</p>
            <a href="tentang_kami.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-address-book"></i>
            </div>
            <h3 class="menu-title">Kontak</h3>
            <p class="menu-desc">Kelola informasi telepon, email, dan media sosial</p>
            <a href="kontak.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-window-restore"></i>
            </div>
            <h3 class="menu-title">Footer</h3>
            <p class="menu-desc">Kelola teks hak cipta di bagian bawah website</p>
            <a href="footer.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <div class="menu-item">
            <div class="menu-icon">
                <i class="fab fa-whatsapp"></i>
            </div>
            <h3 class="menu-title">Tombol WhatsApp</h3>
            <p class="menu-desc">Kelola tombol WhatsApp di website</p>
            <a href="whatsapp.php" class="btn-edit">
                <i class="fab fa-whatsapp"></i> Kelola
            </a>
        </div>
    </div>
</div>

<script src="../js/admin/pengaturan.js"></script>
</body>
</html>