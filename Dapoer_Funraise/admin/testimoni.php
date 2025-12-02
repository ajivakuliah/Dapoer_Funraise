<?php
session_start();
require 'config.php';
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$filter = $_GET['filter'] ?? 'all';
$where_clause = '';
if ($filter === 'verified') {
    $where_clause = 'WHERE is_verified = 1';
} elseif ($filter === 'pending') {
    $where_clause = 'WHERE is_verified = 0';
}

try {
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM testimoni $where_clause");
    $total = (int)$total_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
    $page = min($page, $total_pages);

    $stmt = $pdo->prepare("
        SELECT * FROM testimoni 
        $where_clause
        ORDER BY dikirim_pada DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $testimoni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_verified = $pdo->query("SELECT COUNT(*) FROM testimoni WHERE is_verified = 1")->fetchColumn();
    $stats_pending = $pdo->query("SELECT COUNT(*) FROM testimoni WHERE is_verified = 0")->fetchColumn();
} catch (Exception $e) {
    error_log("Testimoni Error: " . $e->getMessage());
    $testimoni = [];
    $total = 0;
    $total_pages = 1;
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
    <style>
        :root {
            --primary: #5A46A2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8f6fd;
            --card: #ffffff;
            --text: #333;
            --border: #eae6ff;
            --shadow: 0 4px 12px rgba(90, 70, 162, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f1e8fdff;
            color: var(--text);
            line-height: 1.5;
            margin: 0;
            padding: 0;
            width: 100vw;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
            background: var(--card);
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .page-title h1 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .page-title p {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }

        .stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .stat {
            text-align: center;
            min-width: 90px;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.85rem;
            color: #777;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.2rem;
            align-items: flex-end;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            flex: 1;
            min-width: 250px;
        }

        .search-input {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.55rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #444;
            background: #f9f7ff;
            border: 1px solid var(--border);
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .alert {
            padding: 0.8rem 1rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .table-wrapper {
            background: var(--card);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        /* ✅ Tetap pertahankan lebar kolom tetap sesuai preferensi Anda */
        th:nth-child(1), td:nth-child(1) { width: 7%;  }
        th:nth-child(2), td:nth-child(2) { width: 12%; }
        th:nth-child(3), td:nth-child(3) { width: 15%; }
        th:nth-child(4), td:nth-child(4) { width: 15%; }
        th:nth-child(5), td:nth-child(5) { width: 15%; }
        th:nth-child(6), td:nth-child(6) { width: 28%; }
        th:nth-child(7), td:nth-child(7) { width: 8%;  }

        th {
            background: #f5f3ff;
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 1rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-word;
            line-height: 1.5;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #fbf9ff;
        }

        .status-verified { color: var(--success); font-weight: 500; }
        .status-pending { color: var(--warning); font-weight: 500; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-right: 4px;
        }

        .btn-verify   { background: var(--success); }
        .btn-unverify { background: var(--warning); color: #000; }
        .btn-delete   { background: var(--danger); }

        .btn-verify:hover   { background: #059669; }
        .btn-unverify:hover { background: #d97706; }
        .btn-delete:hover   { background: #dc2626; }

        .action-cell {
            white-space: nowrap;
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #888;
            font-style: italic;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.4rem;
            margin: 1.5rem 0;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            color: #444;
            background: #f9f7ff;
            border: 1px solid var(--border);
        }

        .pagination a:hover,
        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .page-header { padding: 1rem; flex-direction: column; }
            .controls { flex-direction: column; }
            th, td { padding: 0.9rem 0.8rem; font-size: 0.85rem; }
            .action-btn { width: 28px; height: 28px; }
        }
    </style>
</head>
<body>

<!-- ✅ FULL BLEED WRAPPER -->
<div class="full-bleed">
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-comments"></i> Manajemen Testimoni</h1>
            <p>Kelola testimoni pelanggan</p>
        </div>
        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats_verified ?></div>
                <div class="stat-label">Terverifikasi</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats_pending ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
        </div>
    </div>

    <div class="controls">
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="Cari nama, produk, atau komentar...">
        </div>

        <div class="filter-tabs">
            <a href="?filter=all&page=1" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Semua</a>
            <a href="?filter=verified&page=1" class="filter-tab <?= $filter === 'verified' ? 'active' : '' ?>">Terverifikasi</a>
            <a href="?filter=pending&page=1" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Menunggu</a>
        </div>
    </div>

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
                    <tr data-nama="<?= htmlspecialchars(strtolower($t['nama'])) ?>"
                        data-produk="<?= htmlspecialchars(strtolower($t['nama_produk'] ?? '')) ?>"
                        data-komentar="<?= htmlspecialchars(strtolower($t['komentar'])) ?>">
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
                            <?php if ($t['is_verified']): ?>
                                <a href="toggle_verifikasi.php?id=<?= $t['id'] ?>&action=unverify&filter=<?= $filter ?>&page=<?= $page ?>"
                                   class="action-btn btn-unverify"
                                   title="Batalkan verifikasi"
                                   onclick="return confirm('Batalkan verifikasi untuk <?= htmlspecialchars(addslashes($t['nama'])) ?>?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php else: ?>
                                <a href="toggle_verifikasi.php?id=<?= $t['id'] ?>&action=verify&filter=<?= $filter ?>&page=<?= $page ?>"
                                   class="action-btn btn-verify"
                                   title="Verifikasi"
                                   onclick="return confirm('Verifikasi testimoni dari <?= htmlspecialchars(addslashes($t['nama'])) ?>?')">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>

                            <a href="hapus_testimoni.php?id=<?= $t['id'] ?>&filter=<?= $filter ?>&page=<?= $page ?>"
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
                        <td colspan="7" class="no-data">Tidak ada testimoni.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">&laquo;</a>
        <?php else: ?>
            <span class="disabled">&laquo;</span>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        if ($start > 1) {
            echo '<a href="?filter=' . $filter . '&page=1">1</a>';
            if ($start > 2) echo '<span>⋯</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                echo '<span class="active">' . $i . '</span>';
            } else {
                echo '<a href="?filter=' . $filter . '&page=' . $i . '">' . $i . '</a>';
            }
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span>⋯</span>';
            echo '<a href="?filter=' . $filter . '&page=' . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>

        <?php if ($page < $total_pages): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">&raquo;</a>
        <?php else: ?>
            <span class="disabled">&raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#testimoniTable tbody tr:not(.no-data)');
    let found = false;

    rows.forEach(row => {
        if (!q) {
            row.style.display = '';
            found = true;
            return;
        }

        const nama = row.dataset.nama || '';
        const produk = row.dataset.produk || '';
        const komentar = row.dataset.komentar || '';

        const match = nama.includes(q) || produk.includes(q) || komentar.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) found = true;
    });

    const tbody = document.querySelector('#testimoniTable tbody');
    const noData = document.querySelector('.no-data');

    if (!found && rows.length > 0) {
        if (!noData) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="7" class="no-data">Tidak ditemukan: "<strong>${this.value}</strong>"</td>`;
            tbody.innerHTML = '';
            tbody.appendChild(tr);
        }
    } else if (noData) {
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }
});
</script>

</body>
</html>