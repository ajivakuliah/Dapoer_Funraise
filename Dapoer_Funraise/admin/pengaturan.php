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
    <title>Pengaturan • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --bg: #f0ecfa;
            --card: #ffffff;
            --text: #333333;
            --border-light: #eae6ff;
            --border-medium: #d8d2f0;
            --border-card: #c9c1e8;
            --shadow: 0 6px 16px rgba(90, 70, 162, 0.12);
            --shadow-hover: 0 8px 24px rgba(90, 70, 162, 0.2);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1e8fdff;
            color: var(--text);
            padding: 0rem;
            line-height: 1.5;
        }
        .container {
            width: 100vw;  
            max-width: none;
            margin: 0;
            padding: 0 0rem;
        }
        /* Card Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .card-item {
            background: var(--card);
            border-radius: 14px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .card-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .card-header {
            padding: 1.2rem 1.4rem;
            background: #fbf9ff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #7058c4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }
        .card-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }
        .card-body {
            padding: 1.4rem;
        }
        .card-desc {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 1.2rem;
            line-height: 1.6;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.65rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.25s;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        /* Responsive */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="cards-grid">
        <!-- Header -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-heading"></i></div>
                <h3 class="card-title">Header</h3>
            </div>
            <div class="card-body">
                <a href="header.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>

        <!-- Hero -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-images"></i></div>
                <h3 class="card-title">Hero Section</h3>
            </div>
            <div class="card-body">
                <a href="hero.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit 
                </a>
            </div>
        </div>

        <!-- Cara Pesan -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-list-ol"></i></div>
                <h3 class="card-title">Cara Pesan</h3>
            </div>
            <div class="card-body">
                <a href="cara-pesan.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>

        <!-- Tentang Kami -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-info-circle"></i></div>
                <h3 class="card-title">Tentang Kami</h3>
            </div>
            <div class="card-body">
                <a href="tentang_kami.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>

        <!-- Kontak -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-address-book"></i></div>
                <h3 class="card-title">Kontak</h3>
            </div>
            <div class="card-body">
                <a href="kontak.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="card-item">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-window-restore"></i></div>
                <h3 class="card-title">Footer</h3>
            </div>
            <div class="card-body">
                <a href="admin/footer.php" class="btn btn-outline">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>