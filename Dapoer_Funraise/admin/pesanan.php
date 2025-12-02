<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Pastikan path ke config.php benar sesuai struktur folder Anda
require_once '../config.php'; 

$error = $success = '';

// --- Parameter Pencarian dan Filter ---
$q = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$bulan_filter = trim($_GET['bulan'] ?? ''); // ✅ Tambah: Ambil filter bulan (format YYYY-MM)

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 3;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

// --- Logika Filter Status ---
if ($status_filter !== '' && in_array($status_filter, ['baru', 'diproses', 'selesai', 'batal'])) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

// ✅ Tambah: Logika Filter Bulan
if ($bulan_filter !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan_filter)) {
    // Memfilter data berdasarkan tahun dan bulan (YYYY-MM)
    $where .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $bulan_filter;
}

// --- Logika Pencarian Q (ID atau Nama) ---
if ($q !== '') {
    if (ctype_digit($q)) {
        $where .= " AND id = ?";
        $params[] = (int)$q;
    } else {
        $where .= " AND LOWER(nama_pelanggan) LIKE ?";
        $params[] = '%' . strtolower($q) . '%';
    }
}


// --- 🔄 MODIFIKASI: Menghasilkan Daftar Semua Bulan (meskipun kosong) ---
$available_months = [];
$indonesian_months = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];

$current_year = (int)date('Y');
$start_year = $current_year;
$end_year = $current_year;

try {
    // Tentukan range tahun yang memiliki pesanan
    $stmt_years = $pdo->query("SELECT MIN(YEAR(created_at)) AS min_year, MAX(YEAR(created_at)) AS max_year FROM pesanan");
    $years_result = $stmt_years->fetch(PDO::FETCH_ASSOC);
    if ($years_result && $years_result['min_year']) {
        $start_year = (int)$years_result['min_year'];
        $end_year = (int)$years_result['max_year'];
    }
    // Pastikan tahun berjalan juga termasuk
    $end_year = max($end_year, $current_year);
    if ($start_year > $current_year) $start_year = $current_year;
    
} catch (Exception $e) {
    error_log("Error fetching years: " . $e->getMessage());
}

// Loop melalui semua tahun (dari terbaru ke terlama) dan semua bulan
for ($year = $end_year; $year >= $start_year; $year--) {
    for ($month = 12; $month >= 1; $month--) {
        // Jika tahun berjalan, jangan tampilkan bulan di masa depan
        if ($year == $current_year && $month > (int)date('m')) {
            continue; 
        }
        
        $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
        $month_year_key = $year . '-' . $month_padded; // e.g., '2025-12'
        
        $timestamp = mktime(0, 0, 0, $month, 1, $year);
        $english_month = date('F', $timestamp);
        
        // Terjemahkan bulan ke Bahasa Indonesia
        $indonesian_name = ($indonesian_months[$english_month] ?? $english_month) . ' ' . $year;
        
        $available_months[$month_year_key] = $indonesian_name;
    }
}
// -----------------------------------------------------

// --- Update status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowed = ['baru', 'diproses', 'selesai', 'batal'];
        if ($id <= 0 || !in_array($status, $allowed)) {
            throw new Exception("Status tidak valid.");
        }
        $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
        $updated = $stmt->execute([$status, $id]);
        if ($updated && $stmt->rowCount() > 0) {
            $success = "Status pesanan #$id berhasil diperbarui.";
        } else {
            $error = "Tidak ada perubahan.";
        }
    } catch (Exception $e) {
        $error = htmlspecialchars($e->getMessage());
    }
}

// --- Hapus ---
if (isset($_GET['hapus'])) {
    try {
        $id = (int)$_GET['hapus'];
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM pesanan WHERE id = ?");
            $deleted = $stmt->execute([$id]);
            if ($deleted && $stmt->rowCount() > 0) {
                $_SESSION['success'] = 'Pesanan berhasil dihapus!';
                // Redirect mempertahankan filter lain
                $redirect_query = http_build_query(array_filter([
                    'q' => $q, 
                    'status' => $status_filter, 
                    'bulan' => $bulan_filter // ✅ Tambah: Pertahankan filter bulan saat hapus
                ]));
                header('Location: pesanan.php' . ($redirect_query ? '?' . $redirect_query : ''));
                exit;
            }
        }
        $error = 'Gagal menghapus pesanan.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// --- Ambil data ---
try {
    // Hitung total data
    $sql_count = "SELECT COUNT(*) FROM pesanan WHERE $where";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_orders = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_orders / $per_page));
    
    // Ambil data dengan limit dan offset
    $sql = "SELECT id, nama_pelanggan, alamat, produk, total, pengambilan, metode_bayar, status, created_at
            FROM pesanan WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Logika Pengelompokan Pesanan per Bulan
    $orders_by_month = [];
    
    foreach ($orders as $order) {
        $english_month = date('F', strtotime($order['created_at']));
        $year = date('Y', strtotime($order['created_at']));
        
        // Terjemahkan bulan ke Bahasa Indonesia
        $indonesian_month = $indonesian_months[$english_month] ?? $english_month;
        $indonesian_month_year = $indonesian_month . ' ' . $year;
        
        if (!isset($orders_by_month[$indonesian_month_year])) {
            $orders_by_month[$indonesian_month_year] = [];
        }
        $orders_by_month[$indonesian_month_year][] = $order;
    }

    // Hitung total status untuk stat cards
    $stmt_all = $pdo->prepare("SELECT status FROM pesanan");
    $stmt_all->execute();
    $all_orders = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    $count_baru = count(array_filter($all_orders, fn($o) => $o['status'] === 'baru'));
    $count_diproses = count(array_filter($all_orders, fn($o) => $o['status'] === 'diproses'));
    $count_selesai = count(array_filter($all_orders, fn($o) => $o['status'] === 'selesai'));
    $count_batal = count(array_filter($all_orders, fn($o) => $o['status'] === 'batal'));
    
} catch (Exception $e) {
    $error = "Gagal memuat data: " . htmlspecialchars($e->getMessage());
    $orders = [];
    $orders_by_month = []; 
    $all_orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $count_baru = $count_diproses = $count_selesai = $count_batal = 0;
}

// Fungsi helper untuk menampilkan produk dalam keranjang
function renderProduk($produkJson) {
    $data = json_decode($produkJson, true);
    if (!is_array($data)) return '<div class="produk-item">[Data produk tidak valid]</div>';
    
    $items = [];
    foreach ($data as $item) {
        $qty = (int)($item['qty'] ?? 0);
        if ($qty <= 0) continue;
        $nama = htmlspecialchars($item['nama'] ?? '—');
        $varian = htmlspecialchars($item['varian'] ?? '');
        $text = $nama;
        if ($varian) $text .= " <span class=\"varian\">($varian)</span>";
        $items[] = "<div class=\"produk-item\">• $text × <strong>$qty</strong></div>";
    }
    return $items ? implode("\n", $items) : '<div class="produk-item">—</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --bg: #f5f3fb;
            --card: #ffffff;
            --text: #333333;
            --border-light: #eae6ff;
            --border-medium: #d8d2f0;
            --border-card: #c9c1e8;
            --shadow: 0 4px 10px rgba(90, 70, 162, 0.08);
            --shadow-hover: 0 6px 14px rgba(90, 70, 162, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background:#f1e8fdff;
            color: var(--text);
            padding: 0rem;
            line-height: 1.4;
            font-size: 14px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 0rem;
        }

        /* --- STATS CARD STYLES --- */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: var(--card);
            padding: 0.9rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-card);
            transition: all 0.25s ease;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center; 
            justify-content: center;
            gap: 10px;
            padding: 15px 10px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary, #5A46A2);
        }
        
        .stat-content {
            display: flex;
            flex-direction: column; 
            align-items: center; 
        }

        .stat-card.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .stat-card.active .stat-icon { color: white; }
        .stat-card.active .stat-value { color: white; }
        .stat-card.active .stat-label { color: #eee; }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.82rem;
            font-weight: 500;
            margin-top: 0.2rem;
            color: #666;
        }
        
        /* --- SEARCH BAR STYLES --- */
        .search-bar {
            background: var(--card);
            padding: 0.8rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap; 
            gap: 0.6rem;
            align-items: flex-end;
            border: 1px solid var(--border-card);
        }

        .search-group {
            /* Hapus flex: 1; agar flex dikendalikan oleh inline style di HTML */
            min-width: 200px; 
        }

        .search-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--primary);
        }

        .search-group input,
        .search-group select { 
            width: 100%;
            padding: 0.5rem 0.8rem;
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.25s ease;
            background: white;
            cursor: pointer;
            appearance: menulist-button;
        }
        
        .search-group input:focus,
        .search-group select:focus { 
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.12);
        }

        .btn-search,
        .btn-reset {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-search {
            background: var(--primary);
            color: white;
        }

        .btn-search:hover {
            background: #4a3a8a;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: #f0f0f0;
            color: #555;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* --- ORDERS GRID STYLES --- */
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 0.9rem;
            padding: 0.8rem 0;
            margin: 0;
        }

        .order-card {
            background: var(--card);
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(90, 70, 162, 0.07);
            border: 1px solid var(--border-card);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }

        .month-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 1.5rem 0 0.8rem 0;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--border-medium);
            grid-column: 1 / -1; 
        }
        
        /* Tambahkan style untuk card order lainnya */
        .order-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1rem;
            background: var(--bg);
            border-bottom: 1px solid var(--border-light);
        }
        
        .order-id {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
            line-height: 1.2;
        }

        .order-time {
            font-size: 0.75rem;
            color: #888;
        }
        
        .status-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .status-baru { background: #fff3e0; color: #ff9800; }
        .status-diproses { background: #e3f2fd; color: #2196f3; }
        .status-selesai { background: #e8f5e9; color: #4caf50; }
        .status-batal { background: #fbecec; color: #f44336; }

        .order-body {
            padding: 1rem;
            flex-grow: 1;
        }

        .customer-name {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        
        .customer-addr {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.8rem;
        }

        .produk-list {
            padding: 0.5rem 0;
            border-top: 1px dashed var(--border-light);
            border-bottom: 1px dashed var(--border-light);
            margin-bottom: 0.8rem;
        }
        .produk-item {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }
        .produk-item:last-child {
            margin-bottom: 0;
        }
        .produk-item .varian {
            font-style: italic;
            color: #777;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
        }
        .meta-row span {
            color: #777;
        }
        
        .total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--secondary);
            text-align: right;
            margin-top: 0.6rem;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1rem;
            background: var(--bg);
            border-top: 1px solid var(--border-light);
        }

        .status-update-group {
            display: flex;
            gap: 5px;
        }
        
        .status-select {
            padding: 0.3rem 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-medium);
            font-size: 0.85rem;
        }
        
        .btn-icon {
            padding: 0.4rem 0.6rem;
            border: none;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: #e57373;
        }
        .btn-danger:hover {
            background: #c62828;
        }

        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty h3 {
            margin: 10px 0;
            font-weight: 600;
            color: #555;
        }
        .empty .fas {
            font-size: 2rem;
            color: var(--border-medium);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .pagination a {
            background: var(--card);
            color: var(--primary);
            border: 1px solid var(--border-medium);
        }
        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .pagination span.active {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }
        .pagination span.disabled {
            background: #f0f0f0;
            color: #ccc;
            cursor: default;
            border: 1px solid #ddd;
        }
        .pagination-info {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #777;
        }

        /* --- MEDIA QUERIES --- */
        @media (max-width: 768px) {
            .orders-grid { flex-direction: column; overflow-x: hidden; gap: 0.8rem; }
            .order-card, .empty { width: 100%; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            body { padding: 0.6rem; }
            
            .search-bar { 
                align-items: stretch;
                flex-direction: column; 
            }
            .search-bar > div { 
                min-width: 100% !important; 
                flex: 1 1 100% !important;
            }
            .search-bar > div:last-child {
                display: flex;
                gap: 0.6rem;
                justify-content: stretch;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="stats">
        <div class="stat-card <?= $status_filter === 'baru' ? 'active' : '' ?>" onclick="filterByStatus('baru')">
            <div class="stat-icon"><i class="fas fa-magic"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= $count_baru ?></div>
                <div class="stat-label">Baru</div>
            </div>
        </div>

        <div class="stat-card <?= $status_filter === 'diproses' ? 'active' : '' ?>" onclick="filterByStatus('diproses')">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= $count_diproses ?></div>
                <div class="stat-label">Diproses</div>
            </div>
        </div>

        <div class="stat-card <?= $status_filter === 'selesai' ? 'active' : '' ?>" onclick="filterByStatus('selesai')">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= $count_selesai ?></div>
                <div class="stat-label">Selesai</div>
            </div>
        </div>

        <div class="stat-card <?= $status_filter === 'batal' ? 'active' : '' ?>" onclick="filterByStatus('batal')">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= $count_batal ?></div>
                <div class="stat-label">Dibatalkan</div>
            </div>
        </div>
    </div>
</div>

    <form method="GET" class="search-bar" id="filterForm">
        <div class="search-group" style="flex: 2 1 250px;">
            <label for="search">Cari Pesanan (ID atau Nama)</label>
            <input type="text" id="search" name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Contoh: 123 atau Budi"
                autocomplete="off">
        </div>

        <div class="search-group" style="flex: 1 1 180px;">
            <label for="bulan">Filter Per Bulan</label>
            <select id="bulan" name="bulan" onchange="this.form.submit()">
                <option value="">Semua Waktu</option>
                <?php foreach ($available_months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $bulan_filter === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;min-width:180px;">
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Cari
            </button>
            <a href="pesanan.php" onclick="resetAll(); return false;" class="btn-reset">
                <i class="fas fa-undo"></i> Reset
            </a>
        </div>
    </form>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="orders-grid">
        <?php if (empty($orders_by_month)): ?> 
            <div class="empty" style="grid-column: 1 / -1;">
                <i class="fas fa-inbox"></i>
                <h3><?= ($q || $status_filter || $bulan_filter) ? 'Tidak ada pesanan yang cocok dengan filter.' : 'Belum Ada Pesanan' ?></h3>
                <?php if (!($q || $status_filter || $bulan_filter)): ?>
                    <p>Pesanan akan muncul setelah checkout.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php 
            // Loop per bulan
            foreach ($orders_by_month as $month_name => $monthly_orders): 
            ?>
            
                <h2 class="month-header"><?= htmlspecialchars($month_name) ?> (<?= count($monthly_orders) ?> Pesanan)</h2>
                
                <?php 
                // Loop kartu pesanan di dalam bulan tersebut
                foreach ($monthly_orders as $o): 
                ?>
                    <div class="order-card">
                        <div class="order-head">
                            <div>
                                <div class="order-id">#<?= htmlspecialchars($o['id']) ?></div>
                                <div class="order-time"><?= date('d M Y • H:i', strtotime($o['created_at'])) ?></div>
                            </div>
                            <span class="status-badge status-<?= htmlspecialchars($o['status']) ?>">
                                <?php
                                $statusMap = [
                                    'baru' => ['icon' => 'clock', 'text' => 'Baru'],
                                    'diproses' => ['icon' => 'sync-alt', 'text' => 'Diproses'],
                                    'selesai' => ['icon' => 'check-circle', 'text' => 'Selesai'],
                                    'batal' => ['icon' => 'times-circle', 'text' => 'Dibatalkan']
                                ];
                                $s = $o['status'];
                                $icon = $statusMap[$s]['icon'] ?? 'question-circle';
                                $text = $statusMap[$s]['text'] ?? ucfirst($s);
                                ?>
                                <i class="fas fa-<?= $icon ?> fa-xs"></i>
                                <span><?= $text ?></span>
                            </span>
                        </div>
                        <div class="order-body">
                            <div class="customer-name"><?= htmlspecialchars($o['nama_pelanggan']) ?></div>
                            <div class="customer-addr"><?= htmlspecialchars($o['alamat']) ?></div>

                            <div class="produk-list">
                                <?= renderProduk($o['produk']) ?>
                            </div>

                            <div class="meta-row">
                                <span>Pengambilan</span>
                                <strong><?= $o['pengambilan'] === 'ambil' ? 'Ambil' : 'Antar' ?></strong>
                            </div>
                            <div class="meta-row">
                                <span>Pembayaran</span>
                                <strong><?= $o['metode_bayar'] === 'cash' ? 'Cash' : 'Transfer' ?></strong>
                            </div>

                            <div class="total">Rp <?= number_format($o['total'], 0, ',', '.') ?></div>
                        </div>
                        <div class="order-footer">
                            <form method="POST" onsubmit="return confirm('Ubah status pesanan #<?= (int)$o['id'] ?>?')">
                                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                <div class="status-update-group">
                                    <select name="status" class="status-select">
                                        <option value="baru" <?= $o['status'] === 'baru' ? 'selected' : '' ?>>Baru</option>
                                        <option value="diproses" <?= $o['status'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                                        <option value="selesai" <?= $o['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="batal" <?= $o['status'] === 'batal' ? 'selected' : '' ?>>Dibatalkan</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-icon" title="Simpan">
                                        <i class="fas fa-sync-alt fa-xs"></i>
                                    </button>
                                </div>
                            </form>
                            <?php
                                // Buat query string yang mempertahankan filter yang ada
                                $current_query = http_build_query(array_filter([
                                    'q' => $q, 
                                    'status' => $status_filter, 
                                    'bulan' => $bulan_filter
                                ]));
                                $hapus_link = '?hapus=' . (int)$o['id'] . ($current_query ? '&' . $current_query : '');
                            ?>
                            <a href="<?= htmlspecialchars($hapus_link) ?>"
                               class="btn-icon btn-danger"
                               title="Hapus"
                               onclick="return confirm('Yakin hapus pesanan #<?= (int)$o['id'] ?>?')">
                                <i class="fas fa-trash-alt fa-xs"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $base_query = http_build_query(array_filter([
                'q' => $q, 
                'status' => $status_filter, 
                'bulan' => $bulan_filter // ✅ Tambah: Sertakan bulan filter di pagination
            ]));
            $base_url = '?' . $base_query . ($base_query ? '&' : '');
            ?>

            <?php if ($page > 1): ?>
                <a href="<?= $base_url ?>page=<?= $page - 1 ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 1);
            $end = min($total_pages, $page + 1);
            if ($start > 1) echo '<a href="' . $base_url . 'page=1">1</a>';
            if ($start > 2) echo '<span>...</span>';
            for ($i = $start; $i <= $end; $i++) {
                echo $i == $page 
                    ? '<span class="active">' . $i . '</span>' 
                    : '<a href="' . $base_url . 'page=' . $i . '">' . $i . '</a>';
            }
            if ($end < $total_pages - 1) echo '<span>...</span>';
            if ($end < $total_pages) echo '<a href="' . $base_url . 'page=' . $total_pages . '">' . $total_pages . '</a>';
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $base_url ?>page=<?= $page + 1 ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div class="pagination-info">
            Halaman <?= $page ?> dari <?= $total_pages ?> • Total: <?= $total_orders ?> pesanan
        </div>
    <?php endif; ?>
</div>

<script>
// ✅ MODIFIKASI: filterByStatus sekarang delete 'page' dan pertahankan 'bulan'
function filterByStatus(status) {
    const url = new URL(window.location);
    url.searchParams.delete('page');
    
    if (status) {
        if(url.searchParams.get('status') === status) {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', status);
        }
    } else {
        url.searchParams.delete('status');
    }
    
    // Pertahankan filter bulan
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');

    window.location.href = url.toString();
}

// ✅ MODIFIKASI: submitSearch sekarang handle 'bulan' dan reset ke halaman 1
function submitSearch() {
    const q = document.getElementById('search').value.trim();
    const url = new URL(window.location);
    url.searchParams.set('page', 1);
    
    // Pertahankan filter bulan
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');
    
    // Pertahankan filter status jika ada
    const status = url.searchParams.get('status');
    status ? url.searchParams.set('status', status) : url.searchParams.delete('status');

    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    window.location.href = url.toString();
}

// ✅ MODIFIKASI: Fungsi resetAll untuk menghapus semua filter
function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('status');
    url.searchParams.delete('bulan'); // Hapus filter bulan
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearch(); // Panggil submitSearch
            }
        });
    }
});
</script>

</body>
</html>