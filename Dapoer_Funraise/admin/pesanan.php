<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$error = $success = '';

// --- Ambil session messages ---
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// --- Pencarian dan Filter ---
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
                // Re-direct ke halaman yang sama untuk membersihkan parameter hapus
                header('Location: pesanan.php' . 
                    ($q ? '?q=' . urlencode($q) : '') . 
                    ($status_filter ? ($q ? '&' : '?') . 'status=' . urlencode($status_filter) : ''));
                exit;
            }
        }
        $_SESSION['error'] = 'Gagal menghapus pesanan.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: pesanan.php');
    exit;
}

// --- Ambil data ---
try {
    // 1. Ambil data untuk STATS card (tidak difilter)
    $stmt_all = $pdo->prepare("SELECT status FROM pesanan");
    $stmt_all->execute();
    $all_orders = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    $count_baru = count(array_filter($all_orders, fn($o) => $o['status'] === 'baru'));
    $count_diproses = count(array_filter($all_orders, fn($o) => $o['status'] === 'diproses'));
    $count_selesai = count(array_filter($all_orders, fn($o) => $o['status'] === 'selesai'));
    $count_batal = count(array_filter($all_orders, fn($o) => $o['status'] === 'batal'));
    
    // 2. Hitung total pesanan terfilter
    $sql_count = "SELECT COUNT(*) FROM pesanan WHERE $where";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_orders = (int)$stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_orders / $per_page));
    
    // Sesuaikan offset dan page jika total_pages berubah
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    
    // 3. Ambil data pesanan terfilter
    $sql = "SELECT id, nama_pelanggan, alamat, produk, total, pengambilan, metode_bayar, status, created_at
            FROM pesanan WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    
    // Gunakan array_slice untuk parameter query utama
    $query_params = array_slice($params, 0, count($params)); 
    $stmt->execute($query_params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Gagal memuat data: " . $e->getMessage();
    $orders = [];
    $all_orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $count_baru = $count_diproses = $count_selesai = $count_batal = 0;
}

// --- Helper function ---
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
    <title>Kelola Pesanan • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin/pesanan.css">
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

    <form method="GET" class="search-bar" onsubmit="event.preventDefault(); submitSearch();">
        <div class="search-group">
            <label for="search">Cari Pesanan (ID atau Nama)</label>
            <input type="text" id="search" name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Contoh: 123 atau Budi"
                autocomplete="off">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        </div>
        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;min-width:180px;">
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Cari
            </button>
            <button type="button" class="btn-reset" onclick="resetAll()">
                <i class="fas fa-undo"></i> Reset
            </button>
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
                        <a href="?hapus=<?= (int)$o['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>"
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
            <?php 
            $pagination_base = '?page=';
            $pagination_query = ($q ? '&q=' . urlencode($q) : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '');
            ?>
            
            <?php if ($page > 1): ?>
                <a href="<?= $pagination_base . ($page - 1) . $pagination_query ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 1);
            $end = min($total_pages, $page + 1);
            if ($start > 1) echo '<a href="' . $pagination_base . '1' . $pagination_query . '">1</a>';
            if ($start > 2) echo '<span>...</span>';
            for ($i = $start; $i <= $end; $i++) {
                echo $i == $page 
                    ? '<span class="active">' . $i . '</span>' 
                    : '<a href="' . $pagination_base . $i . $pagination_query . '">' . $i . '</a>';
            }
            if ($end < $total_pages - 1) echo '<span>...</span>';
            if ($end < $total_pages) echo '<a href="' . $pagination_base . $total_pages . $pagination_query . '">' . $total_pages . '</a>';
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $pagination_base . ($page + 1) . $pagination_query ?>">
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

<script src="../js/admin/pesanan.js"></script>

</body>
</html>