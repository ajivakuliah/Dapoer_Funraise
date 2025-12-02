<?php
include "../config.php"; 
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Pagination
$perPage = 3;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$searchTerm = trim($_GET['search'] ?? '');

// Query produk
try {
    // Siapkan parameter untuk pencarian
    $params = [];
    $searchCondition = '';
    
    if ($searchTerm !== '') {
        $searchCondition = " WHERE ID LIKE ? OR Nama LIKE ? OR Kategori LIKE ?";
        $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
    }

    // Query untuk mengambil data produk
    $sql = "SELECT * FROM produk" . $searchCondition . " ORDER BY ID DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung total data
    $countSql = "SELECT COUNT(*) FROM produk" . $searchCondition;
    $countStmt = $pdo->prepare($countSql);
    // Hapus offset dan perPage dari params untuk count query
    $countParams = array_slice($params, 0, count($params) - 2); 
    $countStmt->execute($countParams);
    
    $totalData = (int)$countStmt->fetchColumn();
    $totalPage = ceil($totalData / $perPage);
} catch (Exception $e) {
    // Handle error database
    error_log("Database Error: " . $e->getMessage());
    $produk = [];
    $totalData = 0;
    $totalPage = 0;
    $error_msg = "Gagal memuat data produk.";
}

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Produk • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin/daftar_produk.css">
</head>
<body>
    <div class="page-wrapper">
        <div class="header-section">
            <div class="search-section">
                <form method="GET" class="search-form" id="searchForm">
                    <input type="text" name="search" class="search-input"
                           placeholder="Cari produk berdasarkan ID, nama, atau kategori"
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </form>
                <div class="search-buttons">
                    <button type="submit" form="searchForm" class="btn btn-search">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <button type="button" class="btn btn-reset" onclick="resetSearch()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="tambah_produk.php" class="btn btn-add">
                        <i class="fas fa-plus"></i> Tambah Produk
                    </a>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table-admin">
                <thead>
                    <tr>
                        <th style="width: 55px;">ID</th>
                        <th style="width: 90px;">Foto</th>
                        <th style="width: 200px;">Produk</th>
                        <th style="width: 120px;">Tanggal Unggah</th>
                        <th style="width: 120px;">Harga</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($produk)): ?>
                        <?php foreach ($produk as $p): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 1rem;">#<?= (int)($p['ID'] ?? 0) ?></strong>
                            </td>
                            <td>
                                <div class="foto-thumbnail">
                                    <?php 
                                    $fotoPath = !empty($p['Foto_Produk']) ? "../uploads/" . htmlspecialchars($p['Foto_Produk']) : null;
                                    ?>
                                    <?php if ($fotoPath && file_exists($fotoPath)): ?>
                                        <img src="<?= $fotoPath ?>" 
                                             alt="Foto Produk <?= htmlspecialchars($p['Nama'] ?? 'Produk') ?>"
                                             onerror="this.parentElement.innerHTML='<div class=\'foto-placeholder\'><i class=\'fas fa-image\'></i></div>'">
                                    <?php else: ?>
                                        <div class="foto-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="product-name">
                                    <?= htmlspecialchars($p['Nama'] ?? '-') ?>
                                </div>
                                <div class="product-details">
                                    <?php if (!empty($p['Kategori'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Kategori:</span> 
                                            <?= htmlspecialchars($p['Kategori']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($p['Varian'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Varian:</span> 
                                            <?php 
                                            $variants = array_map('trim', explode(',', $p['Varian']));
                                            echo htmlspecialchars(implode(', ', $variants));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="date-display">
                                    <?php if (!empty($p['created_at'])): ?>
                                        <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="price-display">
                                    Rp <?= number_format($p['Harga'] ?? 0, 0, ',', '.') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (($p['Status'] ?? 'aktif') == 'aktif'): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times-circle"></i> Tidak Aktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_produk.php?id=<?= (int)($p['ID'] ?? 0) ?>" 
                                       class="btn-action btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="toggle_status.php?id=<?= (int)($p['ID'] ?? 0) ?>" 
                                       class="btn-action btn-status" 
                                       title="Ubah Status"
                                       onclick="return confirm('Yakin ubah status produk ini?')">
                                        <?php if (($p['Status'] ?? 'aktif') == 'aktif'): ?>
                                            <i class="fas fa-toggle-off"></i>
                                        <?php else: ?>
                                            <i class="fas fa-toggle-on"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="hapus_produk.php?id=<?= (int)($p['ID'] ?? 0) ?>" 
                                       class="btn-action btn-delete" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin hapus produk ini?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-box-open"></i>
                                <p>Tidak ada produk ditemukan.</p>
                                <?php if ($searchTerm): ?>
                                    <p style="margin-top: 0.6rem; font-size: 0.9rem;">
                                        Coba kata kunci lain atau <a href="?page=1" onclick="resetSearch(); return false;" style="color: var(--primary); font-weight: 600;">reset pencarian</a>.
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPage > 1): ?>
        <div class="pagination">
            <a href="?page=<?= max(1, $page - 1) ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" 
               class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php for ($i = 1; $i <= $totalPage; $i++): ?>
                <?php if ($i == 1 || $i == $totalPage || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?page=<?= $i ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" 
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPage, $page + 1) ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" 
               class="page-link <?= $page >= $totalPage ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="../js/admin/daftar_produk.js"></script>
</body>
</html>