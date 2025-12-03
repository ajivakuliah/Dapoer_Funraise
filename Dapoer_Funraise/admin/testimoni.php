<?php
session_start();
require '../config.php';
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;
$q = trim($_GET['q'] ?? '');

$filter = $_GET['filter'] ?? 'all';
$where_clause = 'WHERE 1=1';
$params = [];

if ($filter === 'verified') {
    $where_clause .= ' AND is_verified = 1';
} elseif ($filter === 'pending') {
    $where_clause .= ' AND is_verified = 0';
}

if ($q !== '') {
    $search_term = '%' . strtolower($q) . '%';
    $where_clause .= ' AND (LOWER(nama) LIKE ? OR LOWER(nama_produk) LIKE ? OR LOWER(komentar) LIKE ?)';
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}


try {
    $stats_counts = $pdo->query("
        SELECT 
            COUNT(*) AS total_all,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS stats_verified,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) AS stats_pending
        FROM testimoni
    ")->fetch(PDO::FETCH_ASSOC);

    $total_all = (int)($stats_counts['total_all'] ?? 0);
    $stats_verified = (int)($stats_counts['stats_verified'] ?? 0);
    $stats_pending = (int)($stats_counts['stats_pending'] ?? 0);
    
    $count_sql = "SELECT COUNT(*) FROM testimoni $where_clause";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn(); 
    
    $total_pages = max(1, ceil($total / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;    
    $data_sql = "
        SELECT * FROM testimoni 
        $where_clause
        ORDER BY dikirim_pada ASC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($data_sql);
    
    $param_index = 1;
    foreach ($params as $p) {
        $stmt->bindValue($param_index++, $p);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $testimoni = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Testimoni Error: " . $e->getMessage());
    $testimoni = [];
    $total = 0;
    $total_pages = 1;
    $total_all = 0;
    $stats_verified = 0;
    $stats_pending = 0;
}

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimoni • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin/testimoni.css">
</head>
<body>

<div class="full-bleed">
    
    <div class="stats-testimoni-wrapper">
        <div class="stat-card-testi <?= $filter === 'all' ? 'active' : '' ?>" onclick="window.location.href='?filter=all&q=<?= urlencode($q) ?>&page=1'">
            <div class="stat-icon-testi"><i class="fas fa-list-alt"></i></div>
            <div class="stat-content-testi">
                <div class="stat-value-testi"><?= $total_all ?></div>
                <div class="stat-label-testi">Total</div>
            </div>
        </div>

        <div class="stat-card-testi <?= $filter === 'verified' ? 'active' : '' ?>" onclick="window.location.href='?filter=verified&q=<?= urlencode($q) ?>&page=1'">
            <div class="stat-icon-testi"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-testi">
                <div class="stat-value-testi"><?= $stats_verified ?></div>
                <div class="stat-label-testi">Terverifikasi</div>
            </div>
        </div>

        <div class="stat-card-testi <?= $filter === 'pending' ? 'active' : '' ?>" onclick="window.location.href='?filter=pending&q=<?= urlencode($q) ?>&page=1'">
            <div class="stat-icon-testi"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content-testi">
                <div class="stat-value-testi"><?= $stats_pending ?></div>
                <div class="stat-label-testi">Menunggu</div>
            </div>
        </div>
    </div>
    
    <form class="controls controls-top" onsubmit="event.preventDefault(); submitSearch();">
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="Cari nama, produk, atau komentar..." value="<?= htmlspecialchars($q) ?>">
        </div>
        
        <button type="submit" class="btn-action btn-search">
            <i class="fas fa-search"></i> Cari
        </button>

        <button type="button" class="btn-action btn-reset" onclick="resetAll()">
            <i class="fas fa-redo"></i> Reset
        </button>
    </form>
    
    <?php if ($msg): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table id="testimoniTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Nama</th>
                    <th>Produk</th>
                    <th>Komentar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($testimoni)): ?>
                    <?php foreach ($testimoni as $t): ?>
                    <tr>
                        <td><strong>#<?= (int)$t['id'] ?></strong></td>
                        <td>
                            <?php if ($t['is_verified']): ?>
                                <span class="status-verified">Aktif</span>
                            <?php else: ?>
                                <span class="status-pending">Menunggu</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y H:i', strtotime($t['dikirim_pada'])) ?></td>
                        <td><?= htmlspecialchars($t['nama']) ?></td>
                        <td><?= htmlspecialchars($t['nama_produk'] ?? '-') ?></td>
                        <td><?= nl2br(htmlspecialchars($t['komentar'])) ?></td>
                        <td class="action-cell">
                            <?php 
                            $base_url = "toggle_verifikasi.php?id={$t['id']}&filter={$filter}&q=" . urlencode($q) . "&page={$page}";
                            ?>
                            <?php if ($t['is_verified']): ?>
                                <a href="<?= $base_url ?>&action=unverify"
                                   class="action-btn btn-unverify"
                                   title="Batalkan verifikasi"
                                   onclick="return confirm('Batalkan verifikasi untuk <?= htmlspecialchars(addslashes($t['nama'])) ?>?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?= $base_url ?>&action=verify"
                                   class="action-btn btn-verify"
                                   title="Verifikasi"
                                   onclick="return confirm('Verifikasi testimoni dari <?= htmlspecialchars(addslashes($t['nama'])) ?>?')">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>

                            <a href="hapus_testimoni.php?id=<?= $t['id'] ?>&filter=<?= $filter ?>&q=<?= urlencode($q) ?>&page=<?= $page ?>"
                               class="action-btn btn-delete"
                               title="Hapus"
                               onclick="return confirm('Hapus testimoni dari <?= htmlspecialchars(addslashes($t['nama'])) ?>?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">
                            <?php if($q !== ''): ?>
                                Tidak ditemukan testimoni untuk pencarian: "<strong><?= htmlspecialchars($q) ?></strong>"
                            <?php else: ?>
                                Tidak ada testimoni.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php 
        $pagination_base_url = "?filter={$filter}&q=" . urlencode($q) . "&page="; 
        ?>
        
        <?php if ($page > 1): ?>
            <a href="<?= $pagination_base_url . ($page - 1) ?>">&laquo;</a>
        <?php else: ?>
            <span class="disabled">&laquo;</span>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        if ($start > 1) {
            echo '<a href="' . $pagination_base_url . '1">1</a>';
            if ($start > 2) echo '<span>⋯</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                echo '<span class="active">' . $i . '</span>';
            } else {
                echo '<a href="' . $pagination_base_url . $i . '">' . $i . '</a>';
            }
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span>⋯</span>';
            echo '<a href="' . $pagination_base_url . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?= $pagination_base_url . ($page + 1) ?>">&raquo;</a>
        <?php else: ?>
            <span class="disabled">&raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="../js/admin/testimoni.js"></script>

</body>
</html>