<?php
session_start();
require 'config.php';

// Ambil data header
$stmtHeader = $pdo->query("SELECT logo_path, business_name, tagline FROM header WHERE id = 1");
$header = $stmtHeader->fetch(PDO::FETCH_ASSOC);
if (!$header) {
    $header = [
        'logo_path' => 'assets/logo.png',
        'business_name' => 'Dapoer Funraise',
        'tagline' => 'Cemilan rumahan yang bikin nagih!'
    ];
}

// 🔹 Hitung jumlah PRODUK UNIK (bukan total quantity)
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = count($_SESSION['cart']); // by produk/entri
}

// 🔹 Ambil semua kategori produk
$kategori_list = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT kategori FROM produk WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC");
    $kategori_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Database Error (kategori): " . $e->getMessage());
}

// 🔹 Ambil 3 produk terlaris berdasarkan pesanan selesai
$produk_terlaris = [];
try {
    $stmt = $pdo->query("
        SELECT p.id, p.Nama AS nama, p.Harga AS harga, p.Varian AS varian,
               p.Deskripsi_Produk AS deskripsi, p.Foto_Produk AS foto, p.kategori, p.Status,
               SUM(oi.quantity) as total_terjual
        FROM produk p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'selesai'
        AND p.Status = 'aktif'  -- TAMBAHKAN FILTER INI
        GROUP BY p.id
        ORDER BY total_terjual DESC
        LIMIT 3
    ");
    $produk_terlaris = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database Error (produk terlaris): " . $e->getMessage());
}

// 🔹 Ambil semua produk dengan filter pencarian dan kategori
// 🔹 Ambil semua produk dengan filter pencarian dan kategori
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';

$produk_list = [];
try {
    $sql = "
        SELECT id, Nama AS nama, Harga AS harga, Varian AS varian,
               Deskripsi_Produk AS deskripsi, Foto_Produk AS foto, kategori, Status
        FROM produk
        WHERE Status = 'aktif'
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (Nama LIKE ? OR Deskripsi_Produk LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($kategori)) {
        $sql .= " AND kategori = ?";
        $params[] = $kategori;
    }
    
    $sql .= " ORDER BY nama ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produk_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database Error (produk): " . $e->getMessage());
}

// Handle tambah ke keranjang
// Handle tambah ke keranjang
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id = (int)($_POST['id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $varian_pilih = trim($_POST['varian_pilih'] ?? '');
    $quantity = max(1, min(20, $quantity));

    // Cari produk
    $found = null;
    foreach ($produk_list as $p) {
        if ((int)$p['id'] === $id) {
            // Tambahkan pengecekan status di sini
            if (($p['Status'] ?? 'aktif') !== 'aktif') {
                $error = 'Produk ini tidak tersedia untuk saat ini.';
                $found = null;
            } else {
                $found = $p;
            }
            break;
        }
    }

    if (!$found) {
        $error = $error ?: 'Produk tidak ditemukan atau tidak aktif.';
    } else {
        $varian_list = !empty($found['varian'])
            ? array_filter(array_map('trim', explode(',', $found['varian'])))
            : [];
        
        if (!empty($varian_list) && empty($varian_pilih)) {
            $error = 'Silakan pilih varian terlebih dahulu.';
        } else {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $varian_key = $varian_pilih ?: 'default';
            $cart_key = 'prod_' . $id . '_' . md5($varian_key);

            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
                $success = '✓ ' . htmlspecialchars($found['nama']) .
                           (!empty($varian_pilih) ? ' (' . htmlspecialchars($varian_pilih) . ')' : '') .
                           ' ×' . $quantity . ' berhasil ditambahkan.';
            } else {
                $_SESSION['cart'][$cart_key] = [
                    'id' => $id,
                    'nama' => $found['nama'],
                    'harga' => (float)$found['harga'],
                    'varian' => $varian_pilih ?: null,
                    'foto' => $found['foto'] ?: '',
                    'quantity' => $quantity
                ];
                $success = ' ' . htmlspecialchars($found['nama']) .
                           (!empty($varian_pilih) ? ' (' . htmlspecialchars($varian_pilih) . ')' : '') .
                           ' ×' . $quantity . ' berhasil masuk keranjang!';
            }

            $cart_count = count($_SESSION['cart']);
        }
    }
}

// Dapatkan ID produk terlaris untuk badge
$terlaris_ids = array_column($produk_terlaris, 'id');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Produk | Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #5A46A2;
        --primary-dark: #3d2f73;
        --secondary: #B64B62;
        --accent: #F9CC22;
        --bg-light: #FFF5EE;
        --soft: #DFBEE0;
        --text-muted: #9180BB;
        --shadow-sm: 0 2px 8px rgba(90, 70, 162, 0.08);
        --shadow-md: 0 4px 16px rgba(90, 70, 162, 0.12);
        --shadow-lg: 0 8px 24px rgba(90, 70, 162, 0.16);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    
    html, body {
        margin: 0; padding: 0;
        height: 100%;
        font-family: 'Poppins', 'Segoe UI', system-ui, sans-serif;
        line-height: 1.6;
        color: #2d3748;
        scroll-behavior: smooth;
        padding: 0;
    }

    body {
        background: linear-gradient(135deg, #faf8ff 0%, #f3f0ff 50%, #fff5f7 100%);
        background-attachment: fixed;
    }

    /* 🔹 HEADER PREMIUM */
    .app-header {
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        color: white;
        padding: 1rem 8.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 4px 20px rgba(90, 70, 162, 0.25);
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        overflow: hidden;
        transition: transform 0.3s ease;
    }
    .app-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        z-index: 1;
    }
    .app-header > * { position: relative; z-index: 2; }
    .logo {
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
        transition: transform 0.3s ease;
    }
    .logo:hover { transform: scale(1.02); }

    .logo-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        backdrop-filter: blur(4px);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }
    .logo-main {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.5px;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .logo-sub {
        font-size: 0.85rem;
        font-weight: 500;
        opacity: 0.9;
        color: rgba(255,255,255,0.95);
        margin-top: -2px;
    }

    .nav-links { display: flex; gap: 1rem; }
    .nav-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
    }
    .nav-link:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: linear-gradient(135deg, var(--accent), #ffd54f);
        color: #333;
        font-weight: 800;
        font-size: 0.68rem;
        min-width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 3px 8px rgba(0,0,0,0.25);
        pointer-events: none;
        border: 2px solid white;
    }

    .container {
        max-width: 1150px;
        margin: 0 auto;
        padding: 2rem 2rem;
    }

    .page-title {
        font-size: 2rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0 0 1.5rem;
        text-align: center;
        font-weight: 900;
        letter-spacing: -1px;
    }
    .page-title::after {
        content: '';
        display: block;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        margin: 0.6rem auto 0;
        border-radius: 4px;
    }

    /* 🔹 SEARCH BAR STYLES */
    .search-section {
        margin-bottom: 2rem;
    }

    .search-container {
        max-width: 600px;
        margin: 0 auto 1.5rem;
        position: relative;
    }

    .search-box {
        position: relative;
        width: 100%;
    }

    .search-input {
        width: 100%;
        padding: 14px 52px 14px 20px;
        border: 2px solid #e2e8f0;
        border-radius: 50px;
        background: white;
        font-size: 1rem;
        font-weight: 500;
        color: #2d3748;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(90, 70, 162, 0.15);
    }

    .search-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .search-btn:hover {
        transform: translateY(-50%) scale(1.05);
        box-shadow: 0 4px 12px rgba(90, 70, 162, 0.3);
    }

    /* 🔹 CATEGORY FILTER STYLES */
    .category-section {
        margin-bottom: 2rem;
        text-align: center;
    }

    .category-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
        justify-content: center;
        align-items: center;
    }

    .category-btn {
        padding: 10px 20px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 50px;
        color: var(--primary);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
        text-decoration: none;
    }

    .category-btn:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        text-decoration: none;
    }

    .category-btn.active {
        background: linear-gradient(135deg, var(--secondary), #F9CC22);
        color: white;
        border-color: transparent;
    }

    /* 🔹 BEST SELLER BADGE */
    .bestseller-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: linear-gradient(135deg, var(--secondary), #d6536f);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(182, 75, 98, 0.4);
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 4px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .bestseller-badge::before {
        content: '🔥';
        font-size: 0.8rem;
    }

    /* Alert Enhanced */
    .alert {
        padding: 16px 24px;
        border-radius: 14px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 14px;
        font-weight: 600;
        font-size: 1.05rem;
        box-shadow: var(--shadow-md);
        animation: slideDown 0.4s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .alert-success {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #2e7d32;
        border-left: 5px solid #4caf50;
    }
    .alert-error {
        background: linear-gradient(135deg, #ffebee, #ffcdd2);
        color: #c62828;
        border-left: 5px solid #ef5350;
    }
    .alert i { font-size: 1.4rem; }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    /* Product Card Premium */
    .product-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(90, 70, 162, 0.08);
        position: relative;
    }
    .product-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        opacity: 0;
        transition: opacity 0.3s;
    }
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-lg);
    }
    .product-card:hover::before {
        opacity: 1;
    }

    .product-img {
        width: 100%;
        height: 260px;
        background: linear-gradient(135deg, #faf8ff, #f3f0ff);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }
    .product-img::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.02) 100%);
    }
    .product-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
        opacity: 0;
    }
    .product-card:hover .product-img img {
        transform: scale(1.08);
    }
    .product-img img.loaded {
        opacity: 1;
    }

    .product-body {
        padding: 0.9rem 0.9rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }

    .product-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0;
        line-height: 1.3;
        letter-spacing: -0.3px;
    }

    .product-price {
        font-size: 1.3rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--secondary), #d6536f);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
    }

    /* === DESKRIPSI DROPDOWN === */
    .desc-toggle {
        font-size: 0.88rem;
        color: #64748b;
        background: none;
        border: none;
        padding: 0;
        margin: 0;
        cursor: pointer;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        transition: color 0.2s;
    }
    .desc-toggle:hover {
        color: var(--primary);
    }
    .desc-toggle i {
        font-size: 0.85rem;
        transition: transform 0.3s ease;
    }
    .desc-toggle.expanded i {
        transform: rotate(180deg);
    }

    .desc-content {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        margin-top: 0;
        transition: 
            max-height 0.4s ease,
            opacity 0.3s ease,
            margin-top 0.3s ease;
    }
    .desc-content.expanded {
        max-height: 500px;
        opacity: 1;
        margin-top: 0.8rem;
    }

    .desc-text {
        font-size: 0.9rem;
        line-height: 1.6;
        color: #4b5563;
        padding: 10px 14px;
        background: #fcfbff;
        border-radius: 10px;
        margin-top: 8px;
    }

    /* 🔹 Compact form group with inline cart icon */
    .form-group.compact {
        display: flex;
        gap: 0.6rem;
        margin-top: 0.2rem;
        align-items: flex-end;
    }
    .form-group.compact > div {
        flex: 1;
    }
    .input-label {
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 5px;
        font-size: 0.88rem;
    }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        font-size: 0.92rem;
        color: #2d3748;
        font-family: inherit;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(90, 70, 162, 0.12);
        background: white;
    }
    .form-control:hover { border-color: var(--soft); }

    .form-control.form-error {
        border-color: #ef5350 !important;
        animation: shake 0.5s ease;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    /* 🔹 Inline Cart Button – Icon Only */
    .inline-cart-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 3px 8px rgba(90, 70, 162, 0.25);
        flex-shrink: 0;
    }
    .inline-cart-btn:hover {
        transform: scale(1.1) rotate(6deg);
        box-shadow: 0 5px 14px rgba(90, 70, 162, 0.4);
    }
    .inline-cart-btn:active {
        transform: scale(1);
    }
    .inline-cart-btn.btn-loading {
        opacity: 0.6;
        pointer-events: none;
    }
    .empty-state {
        text-align: center;
        padding: 4rem 1.5rem;
        color: #94a3b8;
    }
    .empty-state i {
        font-size: 5rem;
        color: var(--soft);
        margin-bottom: 1.5rem;
        opacity: 0.6;
    }
    .empty-state h3 {
        font-size: 1.8rem;
        color: var(--primary);
        margin-bottom: 0.8rem;
        font-weight: 700;
    }
    .empty-state p { font-size: 1.1rem; }

    .scroll-top-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #d05876, var(--secondary));
        color: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        box-shadow: 0 6px 16px rgba(182, 75, 98, 0.4);
        z-index: 99;
    }
    .scroll-top-btn.visible {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .scroll-top-btn:hover {
        box-shadow: 0 8px 24px rgba(182, 75, 98, 0.6);
        background: linear-gradient(135deg, #d05876, var(--primary));
    }

    /* 🔹 Results Info */
    .results-info {
        text-align: center;
        margin-bottom: 1.5rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .results-count {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
    }
</style>
</head>
<body>
    <div class="container-fluid">
        <header class="app-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="<?= htmlspecialchars($header['logo_path']) ?>" alt="Logo <?= htmlspecialchars($header['business_name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="logo-text">
                    <span class="logo-main"><?= htmlspecialchars($header['business_name']) ?></span>
                    <span class="logo-sub"><?= htmlspecialchars($header['tagline']) ?></span>
                </div>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali</span>
                </a>
                <a href="keranjang.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Keranjang</span>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <main class="container">
            <h1 class="page-title">Produk Kami</h1>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- 🔹 SEARCH SECTION -->
            <div class="search-section">
                <form method="GET" class="search-container">
                    <div class="search-box">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Cari produk..." 
                            value="<?= htmlspecialchars($search) ?>"
                        >
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- 🔹 CATEGORY FILTERS -->
                <?php if (!empty($kategori_list)): ?>
                <div class="category-section">
                    <div class="category-filters">
                        <a href="?" class="category-btn <?= empty($kategori) ? 'active' : '' ?>">
                            Semua
                        </a>
                        <?php foreach ($kategori_list as $kat): ?>
                            <a href="?kategori=<?= urlencode($kat) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                               class="category-btn <?= $kategori === $kat ? 'active' : '' ?>">
                                <?= htmlspecialchars($kat) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- 🔹 RESULTS INFO -->
            <?php if (!empty($search) || !empty($kategori)): ?>
                <div class="results-info">
                    Menampilkan <span class="results-count"><?= count($produk_list) ?> produk</span>
                    <?php if (!empty($search)): ?>
                        untuk pencarian "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                    <?php if (!empty($kategori)): ?>
                        dalam kategori "<strong><?= htmlspecialchars($kategori) ?></strong>"
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($produk_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Produk Tidak Ditemukan</h3>
                    <p><?= !empty($search) || !empty($kategori) 
                        ? 'Coba ubah kata kunci pencarian atau pilih kategori lain.' 
                        : 'Admin belum menambahkan produk. Cek kembali nanti ya~' ?></p>
                    <?php if (!empty($search) || !empty($kategori)): ?>
                        <a href="?" class="category-btn" style="margin-top: 1rem;">
                            Tampilkan Semua Produk
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produk_list as $p):
                        $varian_list = !empty($p['varian'])
                            ? array_filter(array_map('trim', explode(',', $p['varian'])))
                            : [];
                        $deskripsi = $p['deskripsi'] ?? 'Deskripsi tidak tersedia';
                        $is_terlaris = in_array($p['id'], $terlaris_ids);
                    ?>
                        <div class="product-card">
                            <?php if ($is_terlaris): ?>
                                <div class="bestseller-badge">Terlaris</div>
                            <?php endif; ?>
                            
                            <div class="product-img">
                                <?php if (!empty($p['foto']) && file_exists('uploads/' . $p['foto'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($p['foto']) ?>" 
                                        alt="<?= htmlspecialchars($p['nama']) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-cookie-bite" style="font-size:4.5rem; color:var(--soft); opacity: 0.4;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <h2 class="product-name"><?= htmlspecialchars($p['nama']) ?></h2>
                                <div class="product-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                                
                                <!-- 🔹 DESKRIPSI DROPDOWN -->
                                <div class="desc-wrapper">
                                    <button class="desc-toggle" type="button" aria-expanded="false">
                                        <i class="fas fa-chevron-down"></i>
                                        <span>Lihat deskripsi lengkap</span>
                                    </button>
                                    <div class="desc-content">
                                        <div class="desc-text"><?= nl2br(htmlspecialchars($deskripsi)) ?></div>
                                    </div>
                                </div>

                                <!-- 🔹 Compact Form with Inline Cart Icon -->
                                <form method="POST" class="add-to-cart-form">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                                    <div class="form-group compact">
                                        <div>
                                            <label class="input-label" for="qty_<?= $p['id'] ?>">
                                                <i class="fas fa-hashtag"></i> Jumlah
                                            </label>
                                            <input 
                                                type="number" 
                                                id="qty_<?= $p['id'] ?>" 
                                                name="quantity" 
                                                value="1" 
                                                min="1" 
                                                max="20"
                                                class="form-control qty-input"
                                                required
                                            >
                                        </div>

                                        <?php if (!empty($varian_list)): ?>
                                            <div>
                                                <label class="input-label" for="var_<?= $p['id'] ?>">
                                                    <i class="fas fa-tags"></i> Varian
                                                </label>
                                                <select 
                                                    id="var_<?= $p['id'] ?>" 
                                                    name="varian_pilih" 
                                                    class="form-control variant-select"
                                                    required
                                                >
                                                    <option value="">Pilih Varian</option>
                                                    <?php foreach ($varian_list as $v): ?>
                                                        <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="varian_pilih" value="">
                                        <?php endif; ?>

                                        <button 
                                            type="submit" 
                                            name="add_to_cart" 
                                            class="inline-cart-btn"
                                            title="Tambah ke keranjang"
                                            aria-label="Tambah <?= htmlspecialchars($p['nama']) ?> ke keranjang"
                                        >
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <button class="scroll-top-btn" id="scrollTopBtn" aria-label="Scroll ke atas">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>
    <script>
        // Auto-hide alert
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            }, 4000);
        });

        // Deskripsi toggle
        document.querySelectorAll('.desc-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                
                this.setAttribute('aria-expanded', !isExpanded);
                this.classList.toggle('expanded');
                content.classList.toggle('expanded');
            });
        });

        // Tutup semua deskripsi saat klik di luar
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.desc-wrapper')) {
                document.querySelectorAll('.desc-content.expanded').forEach(el => {
                    el.classList.remove('expanded');
                    el.previousElementSibling.setAttribute('aria-expanded', 'false');
                    el.previousElementSibling.classList.remove('expanded');
                });
            }
        });

        // Form validation & loading state
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const variantSelect = form.querySelector('select[name="varian_pilih"]');
                const qtyInput = form.querySelector('input[name="quantity"]');
                const cartBtn = form.querySelector('.inline-cart-btn');
                
                let hasError = false;

                if (variantSelect && variantSelect.required && !variantSelect.value.trim()) {
                    e.preventDefault();
                    variantSelect.classList.add('form-error');
                    variantSelect.focus();
                    hasError = true;
                    setTimeout(() => variantSelect.classList.remove('form-error'), 1500);
                }

                const qty = parseInt(qtyInput.value) || 0;
                if (qty < 1 || qty > 20) {
                    e.preventDefault();
                    qtyInput.classList.add('form-error');
                    qtyInput.focus();
                    hasError = true;
                    setTimeout(() => qtyInput.classList.remove('form-error'), 1500);
                }

                if (!hasError) {
                    cartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    cartBtn.classList.add('btn-loading');
                }
            });
        });

        // Lazy load & animate cards
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.product-card').forEach((card, i) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `all 0.5s ease ${i * 0.1}s`;
                observer.observe(card);
            });

            document.querySelectorAll('.product-img img').forEach(img => {
                if (img.complete) {
                    img.classList.add('loaded');
                } else {
                    img.onload = () => img.classList.add('loaded');
                }
            });
        });

        // Scroll behavior
        const header = document.querySelector('.app-header');
        const scrollTopBtn = document.getElementById('scrollTopBtn');
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const current = window.scrollY;
            header.style.transform = current > lastScroll && current > 100 
                ? 'translateY(-100%)' 
                : 'translateY(0)';
            scrollTopBtn.classList.toggle('visible', current > 300);
            lastScroll = current;
        });

        scrollTopBtn?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Escape key closes descriptions
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.desc-content.expanded').forEach(el => {
                    el.classList.remove('expanded');
                    el.previousElementSibling.setAttribute('aria-expanded', 'false');
                    el.previousElementSibling.classList.remove('expanded');
                });
            }
        });

        // Focus search input on '/' key press
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                document.querySelector('.search-input')?.focus();
            }
        });
    </script>

</body>
</html>