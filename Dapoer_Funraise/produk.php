<?php
session_start();
require 'config.php';

$stmtHeader = $pdo->query("SELECT logo_path, business_name, tagline FROM header WHERE id = 1");
$header = $stmtHeader->fetch(PDO::FETCH_ASSOC);
if (!$header) {
    $header = [
        'logo_path' => 'assets/logo.png',
        'business_name' => 'Dapoer Funraise',
        'tagline' => 'Cemilan rumahan yang bikin nagih!'
    ];
}

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = count($_SESSION['cart']); // by produk/entri
}

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
        AND p.Status = 'aktif'
        GROUP BY p.id
        ORDER BY total_terjual DESC
        LIMIT 3
    ");
    $produk_terlaris = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database Error (produk terlaris): " . $e->getMessage());
}

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
    
    <link rel="stylesheet" href="css/produk.css"> 
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
                                
                                <div class="desc-wrapper">
                                    <button class="desc-toggle" type="button" aria-expanded="false">
                                        <i class="fas fa-chevron-down"></i>
                                        <span>Lihat deskripsi lengkap</span>
                                    </button>
                                    <div class="desc-content">
                                        <div class="desc-text"><?= nl2br(htmlspecialchars($deskripsi)) ?></div>
                                    </div>
                                </div>

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
    
    <script src="js/produk.js"></script>

</body>
</html>