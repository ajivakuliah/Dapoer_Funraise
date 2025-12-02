<?php
session_start();
require '../config.php';
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// --------------------------------------------------------
// 1. TANGKAP PARAMETER DARI URL (q, filter, page)
// --------------------------------------------------------
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;
$q = trim($_GET['q'] ?? ''); // <--- MENANGKAP QUERY PENCARIAN BARU

$filter = $_GET['filter'] ?? 'all';
$where_clause = 'WHERE 1=1'; // Mulai dengan kondisi dasar
$params = [];

if ($filter === 'verified') {
    $where_clause .= ' AND is_verified = 1';
} elseif ($filter === 'pending') {
    $where_clause .= ' AND is_verified = 0';
}

// 🔹 Logika Pencarian: mencari di kolom nama, produk, atau komentar
if ($q !== '') {
    $search_term = '%' . strtolower($q) . '%';
    $where_clause .= ' AND (LOWER(nama) LIKE ? OR LOWER(nama_produk) LIKE ? OR LOWER(komentar) LIKE ?)';
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}


try {
    // --------------------------------------------------------
    // 2. HITUNG STATS CARDS (Total, Verified, Pending) - TIDAK TERPENGARUH FILTER Q
    // --------------------------------------------------------
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
    
    // --------------------------------------------------------
    // 3. HITUNG TOTAL TERFILTER (untuk Pagination)
    // --------------------------------------------------------
    $count_sql = "SELECT COUNT(*) FROM testimoni $where_clause";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn(); 
    
    $total_pages = max(1, ceil($total / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page; // Pastikan offset dihitung ulang jika page berubah
    
    // --------------------------------------------------------
    // 4. AMBIL DATA TESTIMONI YANG SUDAH DIFILTER
    // --------------------------------------------------------
    $data_sql = "
        SELECT * FROM testimoni 
        $where_clause
        ORDER BY dikirim_pada DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($data_sql);
    
    // Bind parameter q (jika ada) dan limit/offset
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
        
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.2rem;
            align-items: flex-end;
        }
        
        .controls-top {
            margin-bottom: 25px; 
            align-items: center; /* Tambahkan ini agar semua elemen di controls sejajar */
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
        
        /* Gaya baru untuk tombol di controls */
        .btn-action {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
            height: 40px; /* Samakan tinggi dengan input */
        }

        .btn-search {
            background-color: var(--primary);
            color: white;
        }

        .btn-search:hover {
            background-color: var(--primary-dark, #3d2f73);
        }

        .btn-reset {
            background-color: #e2e8f0;
            color: #475569;
        }

        .btn-reset:hover {
            background-color: #cbd5e1;
        }
        /* Akhir gaya tombol baru */

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

        th:nth-child(1), td:nth-child(1) { width: 6%;  }
        th:nth-child(2), td:nth-child(2) { width: 7%; }
        th:nth-child(3), td:nth-child(3) { width: 12%; }
        th:nth-child(4), td:nth-child(4) { width: 13%; }
        th:nth-child(5), td:nth-child(5) { width: 15%; }
        th:nth-child(6), td:nth-child(6) { width: 35%; }
        th:nth-child(7), td:nth-child(7) { width: 12%;  }

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

        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fbf9ff; }

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

        .pagination a, .pagination span {
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

        .pagination a:hover, .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* --- STAT CARD STYLES --- */
        .stats-testimoni-wrapper {
            display: flex;
            justify-content: space-around;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card-testi {
            flex-grow: 1;
            flex-basis: 220px;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 12px 15px; 
            border-radius: 12px;
            background-color: var(--card);
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .stat-icon-testi {
            font-size: 2.5rem;
            margin-right: 15px;
            color: var(--primary);
        }

        .stat-content-testi {
            display: flex;
            flex-direction: column; 
            align-items: center; 
            flex-grow: 1;
        }

        .stat-value-testi {
            font-size: 1.8rem;
            font-weight: 600;
            line-height: 1.1;
            color: var(--text); 
        }

        .stat-label-testi {
            font-size: 0.9rem;
            margin-top: 5px;
            color: #666;
        }

        .stat-card-testi.active {
            background-color: var(--primary);
            color: white; 
        }

        .stat-card-testi.active .stat-icon-testi,
        .stat-card-testi.active .stat-value-testi,
        .stat-card-testi.active .stat-label-testi {
            color: #ffffff;
        }
        
        @media (max-width: 768px) {
            .controls { flex-direction: column; }
            .controls-top { align-items: stretch; } /* Di layar kecil, tombol bisa di bawah input */
            .search-box { flex-direction: column; gap: 10px; }
            .search-input { width: 100%; }
            .btn-action { width: 100%; height: auto; padding: 10px; }
            .stats-testimoni-wrapper { flex-direction: column; }
            .stat-card-testi { flex-basis: auto; }
            th, td { padding: 0.9rem 0.8rem; font-size: 0.85rem; }
        }
    </style>
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

<script>
function submitSearch() {
    const q = document.getElementById('searchInput').value.trim();
    const url = new URL(window.location);
    
    // Hapus parameter page saat pencarian baru
    url.searchParams.delete('page'); 

    // Atur parameter q
    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    // Langsung arahkan
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('filter');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearch();
            }
        });
    }
    
    // Tambahkan kembali fungsi pencarian real-time untuk estetika (optional)
    // Walaupun pencarian utama menggunakan tombol, ini bisa membantu feedback
    // Hapus fungsi ini jika ingin pencarian murni lewat tombol.
    /* searchInput.addEventListener('input', function() {
         // Hapus fungsi filter real-time agar fokus ke tombol
    });
    */
});
</script>

</body>
</html>