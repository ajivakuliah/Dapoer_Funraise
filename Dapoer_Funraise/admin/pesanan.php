<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$error = $success = '';

// --- Pencarian: satu field (ID atau Nama) ---
$q = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 3;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status_filter !== '' && in_array($status_filter, ['baru', 'diproses', 'selesai', 'batal'])) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

if ($q !== '') {
    if (ctype_digit($q)) {
        $where .= " AND id = ?";
        $params[] = (int)$q;
    } else {
        $where .= " AND LOWER(nama_pelanggan) LIKE ?";
        $params[] = '%' . strtolower($q) . '%';
    }
}

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
                header('Location: pesanan.php' . 
                    ($q ? '?q=' . urlencode($q) : '') . 
                    ($status_filter ? ($q ? '&' : '?') . 'status=' . urlencode($status_filter) : ''));
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
    $sql_count = "SELECT COUNT(*) FROM pesanan WHERE $where";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_orders = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_orders / $per_page));
    
    $sql = "SELECT id, nama_pelanggan, alamat, produk, total, pengambilan, metode_bayar, status, created_at
            FROM pesanan WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_all = $pdo->prepare("SELECT status FROM pesanan");
    $stmt_all->execute();
    $all_orders = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    $count_baru = count(array_filter($all_orders, fn($o) => $o['status'] === 'baru'));
    $count_diproses = count(array_filter($all_orders, fn($o) => $o['status'] === 'diproses'));
    $count_selesai = count(array_filter($all_orders, fn($o) => $o['status'] === 'selesai'));
    $count_batal = count(array_filter($all_orders, fn($o) => $o['status'] === 'batal'));
    
} catch (Exception $e) {
    $error = "Gagal memuat data.";
    $orders = [];
    $all_orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $count_baru = $count_diproses = $count_selesai = $count_batal = 0;
}

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
        }

        /* .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        } */

        .stat-card.active {
            border-color: var(--primary);
            background: rgba(90, 70, 162, 0.03);
        }

        .stat-card.active::after {
            content: '✓';
            position: absolute;
            top: 6px;
            right: 6px;
            background: var(--primary);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

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
            flex: 1;
            min-width: 200px;
        }

        .search-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--primary);
        }

        .search-group input {
            width: 100%;
            padding: 0.5rem 0.8rem;
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.25s ease;
        }

        .search-group input:focus {
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

        /* 🎯 3 CARD PER BARIS — GRID, TANPA SCROLL HORIZONTAL */
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

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }

        .order-head {
            background: #fbf9ff;
            padding: 0.6rem 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-medium);
            font-size: 0.82rem;
        }

        .order-id { font-weight: 700; color: var(--primary); }
        .order-time { color: #888; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 14px;
            font-size: 0.68rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-badge i { font-size: 0.7rem; }
        .status-baru { background: #e3f2fd; color: #1565c0; }
        .status-diproses { background: #fff8e1; color: #ef6c00; }
        .status-selesai { background: #e8f5e9; color: #2e7d32; }
        .status-batal { background: #ffebee; color: #c62828; }

        .order-body {
            padding: 0.7rem;
            font-size: 0.8rem;
            flex: 1;
        }

        .customer-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.2rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .customer-addr {
            color: #555;
            line-height: 1.35;
            margin-bottom: 0.5rem;
            font-size: 0.76rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .produk-list {
            background: #fcfbff;
            padding: 0.5rem;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            border: 1px solid var(--border-light);
            max-height: 60px;
            overflow-y: auto;
        }

        .produk-item { margin-bottom: 0.2rem; line-height: 1.3; color: #333; }
        .produk-item:last-child { margin-bottom: 0; }
        .varian { color: #777; font-weight: normal; opacity: 0.9; }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin: 0.2rem 0;
            font-size: 0.76rem;
        }

        .total {
            font-size: 1rem;
            font-weight: 700;
            color: var(--secondary);
            text-align: right;
            margin-top: 0.4rem;
            padding-top: 0.3rem;
            border-top: 1px solid var(--border-medium);
        }

        /* 🔹 Tombol ikon kecil */
        .order-footer {
            padding: 0.4rem 0.6rem;
            background: #fbf9ff;
            display: flex;
            justify-content: flex-end;
            gap: 0.3rem;
            border-top: 1px solid var(--border-medium);
        }

        .status-update-group {
            display: flex;
            gap: 0.3rem;
        }

        .status-select {
            padding: 4px 8px 4px 24px !important;
            font-size: 0.75rem !important;
            border-radius: 5px !important;
            border: 1px solid var(--border-medium) !important;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235A46A2' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat 6px center;
            background-size: 12px;
            appearance: none;
            min-width: 90px;
            height: 28px;
        }

        .btn-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0;
            font-size: 0.75rem;
        }
        .btn-icon i { font-size: 0.7rem; }
        .btn-icon:hover { transform: translateY(-1px); }

        .btn-icon {
            background: #e8eaff;
            color: var(--primary);
            border-color: #d0c9f0;
        }
        .btn-icon:hover { background: #dde5ff; }

        .btn-danger {
            background: #ffe8e8;
            color: var(--secondary);
            border-color: #ffcdd2;
        }
        .btn-danger:hover { background: #ffd8d8; }

        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 1rem;
            color: #777;
            background: var(--card);
            border-radius: 10px;
            border: 1px dashed var(--border-medium);
            min-width: 280px;
            flex: 0 0 auto;
        }
        .empty i { font-size: 2.4rem; color: #ddd; margin-bottom: 0.6rem; }
        .empty h3 { font-size: 1.15rem; font-weight: 600; margin-bottom: 0.3rem; color: #555; }

        @media (max-width: 768px) {
            .orders-grid { flex-direction: column; overflow-x: hidden; gap: 0.8rem; }
            .order-card, .empty { width: 100%; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            body { padding: 0.6rem; }
        }

        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr; }
            .order-footer { flex-direction: column; align-items: stretch; }
            .order-footer > * { width: 100%; justify-content: center; }
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.8rem;
            border: 1px solid var(--border-medium);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
            background: var(--card);
            min-width: 34px;
            text-align: center;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .pagination .active {
            background: var(--primary);
            color: white;
        }

        .pagination-info {
            text-align: center;
            margin-top: 0.8rem;
            color: #777;
            font-size: 0.85rem;
        }

/* Styling untuk STAT CARD (di file produk.css) */

.stats {
    display: flex;
    justify-content: space-around;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    flex-grow: 1;
    /* PENTING: Untuk penempatan ikon dan konten */
    display: flex; 
    align-items: center; /* Vertikal: Ikon dan konten sejajar di tengah */
    justify-content: center; /* Horizontal: Biarkan ikon di kiri */
    
    padding: 15px 60px;
    border-radius: 10px;
    background-color: #f7f7f7;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.stat-icon {
    font-size: 2.5rem;
    margin-right: -1px;
    color: var(--primary, #5A46A2);
}

.stat-content {
    /* PENTING: Untuk menengahkan Nilai dan Label */
    display: flex;
    flex-direction: column; /* Nilai di atas Label */
    align-items: center; /* Horizontal: Nilai dan Label berada di tengah */
    flex-grow: 1;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    line-height: 1.1;
}

.stat-label {
    font-size: 0.9rem;
    margin-top: 5px;
    color: #666;
}

/* Tambahkan styling aktif agar warna terlihat */
.stat-card.active {
    background-color: var(--primary, #5A46A2);
    color: white;
}
.stat-card.active .stat-icon {
    color: #ffffff;
}
.stat-card.active .stat-value {
    color: #ffffff;
}
.stat-card.active .stat-label {
    color: #eee;
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

    <form method="GET" class="search-bar">
        <div class="search-group">
            <label for="search">Cari Pesanan (ID atau Nama)</label>
            <input type="text" id="search" name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Contoh: 123 atau Budi"
                autocomplete="off">
        </div>
        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;min-width:180px;">
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Cari
            </button>
            <a href="pesanan.php" class="btn-reset">
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
        <?php if (empty($orders)): ?>
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <h3>Belum Ada Pesanan</h3>
                <p>Pesanan akan muncul setelah checkout.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
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
                        <a href="?hapus=<?= (int)$o['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $status_filter ? ($q ? '&' : '?') . 'status=' . urlencode($status_filter) : '' ?>"
                           class="btn-icon btn-danger"
                           title="Hapus"
                           onclick="return confirm('Yakin hapus pesanan #<?= (int)$o['id'] ?>?')">
                            <i class="fas fa-trash-alt fa-xs"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 1);
            $end = min($total_pages, $page + 1);
            if ($start > 1) echo '<a href="?page=1' . ($q ? '&q=' . urlencode($q) : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '') . '">1</a>';
            if ($start > 2) echo '<span>...</span>';
            for ($i = $start; $i <= $end; $i++) {
                echo $i == $page 
                    ? '<span class="active">' . $i . '</span>' 
                    : '<a href="?page=' . $i . ($q ? '&q=' . urlencode($q) : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '') . '">' . $i . '</a>';
            }
            if ($end < $total_pages - 1) echo '<span>...</span>';
            if ($end < $total_pages) echo '<a href="?page=' . $total_pages . ($q ? '&q=' . urlencode($q) : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '') . '">' . $total_pages . '</a>';
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">
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
function filterByStatus(status) {
    const url = new URL(window.location);
    url.searchParams.get('status') === status 
        ? url.searchParams.delete('status') 
        : url.searchParams.set('status', status);
    window.location.href = url.toString();
}

function submitSearch() {
    const q = document.getElementById('search').value.trim();
    const url = new URL(window.location);
    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('status');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }
});

// Filter status — pertahankan q
function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}
</script>

</body>
</html>