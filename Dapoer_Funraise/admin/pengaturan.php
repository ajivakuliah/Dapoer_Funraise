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
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --accent: #F9CC22;
            --whatsapp: #25D366;
            --info: #17a2b8;
            --dark: #2c3e50;
            --light: #f8f9fa;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f6fd;
            color: #333;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .container {
            margin: 0;
            width: 100%;
            background: #f8f6fd;
            border-radius: 15px;
            overflow: visible;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Grid 3 kolom */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 3rem;
        }
        
        /* Menu Item Styles dengan warna berbeda */
        .menu-item {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-top: 5px solid;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        /* Warna border berbeda untuk setiap menu */
        .menu-item:nth-child(1) { border-color: var(--primary); }
        .menu-item:nth-child(2) { border-color: var(--secondary); }
        .menu-item:nth-child(3) { border-color: var(--accent); }
        .menu-item:nth-child(4) { border-color: var(--info); }
        .menu-item:nth-child(5) { border-color: var(--dark); }
        .menu-item:nth-child(6) { border-color: var(--whatsapp); }
        .menu-item:nth-child(7) { border-color: #6f42c1; }
        
        .menu-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        /* Warna background icon berbeda */
        .menu-item:nth-child(1) .menu-icon { background: linear-gradient(135deg, var(--primary), #7B68EE); }
        .menu-item:nth-child(2) .menu-icon { background: linear-gradient(135deg, var(--secondary), #FF6B9D); }
        .menu-item:nth-child(3) .menu-icon { background: linear-gradient(135deg, var(--accent), #FFD700); }
        .menu-item:nth-child(4) .menu-icon { background: linear-gradient(135deg, var(--info), #20c997); }
        .menu-item:nth-child(5) .menu-icon { background: linear-gradient(135deg, var(--dark), #34495e); }
        .menu-item:nth-child(6) .menu-icon { background: linear-gradient(135deg, var(--whatsapp), #128C7E); }
        .menu-item:nth-child(7) .menu-icon { background: linear-gradient(135deg, #6f42c1, #9d4edd); }
        
        .menu-item:hover .menu-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .menu-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: #2c3e50;
        }
        
        .menu-desc {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.8rem 1.8rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid;
            background: white;
            width: 100%;
        }
        
        /* PERBAIKAN: Warna tombol default */
        .menu-item:nth-child(1) .btn-edit { 
            border-color: var(--primary); 
            color: var(--primary);
        }
        .menu-item:nth-child(2) .btn-edit { 
            border-color: var(--secondary); 
            color: var(--secondary);
        }
        .menu-item:nth-child(3) .btn-edit { 
            border-color: var(--accent); 
            color: var(--accent);
        }
        .menu-item:nth-child(4) .btn-edit { 
            border-color: var(--info); 
            color: var(--info);
        }
        .menu-item:nth-child(5) .btn-edit { 
            border-color: var(--dark); 
            color: var(--dark);
        }
        .menu-item:nth-child(6) .btn-edit { 
            border-color: var(--whatsapp); 
            color: var(--whatsapp);
        }
        .menu-item:nth-child(7) .btn-edit { 
            border-color: #6f42c1; 
            color: #6f42c1;
        }
        
        /* PERBAIKAN: Warna teks saat hover untuk setiap tombol */
        .btn-edit:hover {
            transform: translateY(-2px);
        }
        
        /* PERBAIKAN: Warna background hover dengan teks putih */
        .menu-item:nth-child(1) .btn-edit:hover { 
            background: var(--primary); 
            border-color: var(--primary);
            color: white !important;
        }
        .menu-item:nth-child(2) .btn-edit:hover { 
            background: var(--secondary); 
            border-color: var(--secondary);
            color: white !important;
        }
        .menu-item:nth-child(3) .btn-edit:hover { 
            background: var(--accent); 
            border-color: var(--accent);
            color: white !important;
        }
        .menu-item:nth-child(4) .btn-edit:hover { 
            background: var(--info); 
            border-color: var(--info);
            color: white !important;
        }
        .menu-item:nth-child(5) .btn-edit:hover { 
            background: var(--dark); 
            border-color: var(--dark);
            color: white !important;
        }
        .menu-item:nth-child(6) .btn-edit:hover { 
            background: var(--whatsapp); 
            border-color: var(--whatsapp);
            color: white !important;
        }
        .menu-item:nth-child(7) .btn-edit:hover { 
            background: #6f42c1; 
            border-color: #6f42c1;
            color: white !important;
        }
        
        /* PERBAIKAN: Warna saat tombol di-klik (active) */
        .btn-edit:active {
            transform: translateY(0);
        }
        
        /* Logout Section */
        .logout-section {
            text-align: center;
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e6ed;
        }
        
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.9rem 2.5rem;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-logout:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(192, 57, 43, 0.3);
            color: white !important;
        }
        
        .btn-logout:active {
            transform: translateY(-1px);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1.5rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .menu-item {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.7rem;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-sliders-h"></i>
            <span>Panel Admin</span>
        </h1>
        <p>Kelola semua bagian website dengan mudah</p>
    </div>

    <!-- Grid Menu 3 kolom -->
    <div class="menu-grid">
        <!-- Header -->
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

        <!-- Hero -->
        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-images"></i>
            </div>
            <h3 class="menu-title">Hero Section</h3>
            <p class="menu-desc">Kelola background dan teks utama halaman depan</p>
            <a href="hero.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <!-- Cara Pesan -->
        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-list-ol"></i>
            </div>
            <h3 class="menu-title">Cara Pesan</h3>
            <p class="menu-desc">Atur langkah-langkah cara melakukan pemesanan</p>
            <a href="cara-pesan.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <!-- Tentang Kami -->
        <div class="menu-item">
            <div class="menu-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 class="menu-title">Tentang Kami</h3>
            <p class="menu-desc">Kelola informasi, sejarah dan foto</p>
            <a href="tentang_kami.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>

        <!-- Kontak -->
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

        <!-- Footer -->
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

        <!-- Tombol WhatsApp -->
        <div class="menu-item">
            <div class="menu-icon">
                <i class="fab fa-whatsapp"></i>
            </div>
            <h3 class="menu-title">Tombol WhatsApp</h3>
            <p class="menu-desc">Kelola tombol WhatsApp floating di website</p>
            <a href="whatsapp.php" class="btn-edit">
                <i class="fab fa-whatsapp"></i> Kelola
            </a>
        </div>
    </div>
</div>

</body>
</html>