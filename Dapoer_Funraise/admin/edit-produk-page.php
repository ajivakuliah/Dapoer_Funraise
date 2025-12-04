<?php
include "../config.php"; 
session_start();
if (!isset($_SESSION['username'])) header('Location: ../login.php');

$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$offset = (int)$offset;
$perPage = (int)$perPage;

if ($offset < 0 || $perPage <= 0) {
    die('Invalid pagination parameters.');
}

$keyword = trim($_GET['search'] ?? '');

if ($keyword !== '') {
    $sql = "SELECT * FROM produk 
            WHERE ID LIKE ? OR Nama LIKE ? 
            ORDER BY ID DESC 
            LIMIT $offset, $perPage";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$keyword%", "%$keyword%"]);
} else {
    $sql = "SELECT * FROM produk ORDER BY ID DESC LIMIT $offset, $perPage";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$produk = $stmt->fetchAll();

$totalStmt = $pdo->prepare($keyword !== '' ? 
    "SELECT COUNT(*) FROM produk WHERE ID LIKE ? OR Nama LIKE ?" : 
    "SELECT COUNT(*) FROM produk"
);
if ($keyword !== '') {
    $totalStmt->execute(["%$keyword%", "%$keyword%"]);
} else {
    $totalStmt->execute();
}
$totalData = $totalStmt->fetchColumn();
$totalPage = ceil($totalData / $perPage);

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Produk - Dashboard Admin</title>
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
    <style>
      body {
        background-color: #f5f7fa;
        padding: 20px;
      }
      .content-wrapper {
        padding: 30px;
        max-width: 1400px;
        margin: 0 auto;
      }
    </style>
  </head>
  <body>
    <div class="content-wrapper">
      <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <div class="row mb-3">
        <div class="col-md-12">
          <div class="card">
            <div class="card-body">
              <form method="GET" class="d-flex align-items-center">
                <input type="text" name="search" class="form-control me-2" 
                       placeholder="Cari produk berdasarkan ID atau nama..." 
                       value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit" class="btn btn-gradient-primary me-2">
                  <i class="mdi mdi-magnify"></i> Cari
                </button>
                <?php if($keyword): ?>
                  <a href="edit-produk-page.php" class="btn btn-gradient-secondary">
                    <i class="mdi mdi-refresh"></i> Reset
                  </a>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Daftar Produk</h4>
                <a href="../tambah_produk.php" class="btn btn-gradient-primary btn-rounded btn-fw">
                  <i class="mdi mdi-plus"></i> Tambah Produk
                </a>
              </div>
              
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Foto</th>
                      <th>Nama</th>
                      <th>Harga</th>
                      <th>Tanggal Upload</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if($produk && count($produk) > 0): ?>
                    <?php foreach($produk as $p): ?>
                    <tr>
                      <td><?= $p['ID'] ?></td>
                      <td>
                        <?php if(!empty($p['Foto_Produk']) && file_exists(__DIR__ . '/../uploads/' . $p['Foto_Produk'])): ?>
                          <img src="../uploads/<?= htmlspecialchars($p['Foto_Produk']) ?>" 
                               alt="<?= htmlspecialchars($p['Nama']) ?>" 
                               style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                          <label class="badge badge-warning">Tidak Ada</label>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($p['Nama']) ?></td>
                      <td><label class="badge badge-gradient-success">Rp <?= number_format($p['Harga'],0,',','.') ?></label></td>
                      <td><?= htmlspecialchars($p['created_at'] ?? '-') ?></td>
                      <td>
                      <div class="btn-group" role="group">
                          <a href="../detail_produk.php?id=<?= $p['ID'] ?>" 
                          class="btn btn-sm btn-info" 
                          title="Detail"
                          data-bs-toggle="tooltip">
                          <i class="mdi mdi-eye"></i>
                          </a>
                          <a href="../edit_produk.php?id=<?= $p['ID'] ?>" 
                          class="btn btn-sm btn-warning" 
                          title="Edit"
                          data-bs-toggle="tooltip">
                          <i class="mdi mdi-pencil"></i>
                          </a>
                          <a href="../hapus_produk.php?id=<?= $p['ID'] ?>" 
                          class="btn btn-sm btn-danger" 
                          onclick="return confirm('Yakin ingin hapus produk ini?')" 
                          title="Hapus"
                          data-bs-toggle="tooltip">
                          <i class="mdi mdi-delete"></i>
                          </a>
                      </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center">
                        <div class="text-muted py-3">
                          <i class="mdi mdi-information-outline mdi-24px"></i>
                          <p>Tidak ada produk ditemukan</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <?php if($totalPage > 1): ?>
              <div class="mt-4">
                <nav aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= $page-1 ?><?= $keyword !== '' ? '&search=' . urlencode($keyword) : '' ?>">
                        <i class="mdi mdi-chevron-left"></i>
                      </a>
                    </li>
                    <?php for($i = 1; $i <= $totalPage; $i++): ?>
                      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $keyword !== '' ? '&search=' . urlencode($keyword) : '' ?>">
                          <?= $i ?>
                        </a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPage ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= $page+1 ?><?= $keyword !== '' ? '&search=' . urlencode($keyword) : '' ?>">
                        <i class="mdi mdi-chevron-right"></i>
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/vendors/chart.js/chart.umd.js"></script>
    <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/misc.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
    <script src="assets/js/jquery.cookie.js"></script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
  </body>
</html>