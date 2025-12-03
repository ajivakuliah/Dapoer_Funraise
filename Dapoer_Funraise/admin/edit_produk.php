<?php
require '../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Internal Server Error: Database connection not established.');
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$kategoriList = [
    'Makanan',
    'Minuman', 
    'Kue & Roti',
    'Snack',
    'Catering',
    'Lainnya'
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: daftar_produk.php?msg=ID produk tidak valid');
    exit;
}

$produk = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE ID = ?");
    $stmt->execute([$id]);
    $produk = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Produk Error: " . $e->getMessage());
    header('Location: daftar_produk.php?msg=Gagal mengambil data produk');
    exit;
}

if (!$produk) {
    header('Location: daftar_produk.php?msg=Produk tidak ditemukan');
    exit;
}

$msg = '';
$namaVal = $produk['Nama'] ?? '';
$kategoriVal = $produk['Kategori'] ?? '';
$hargaVal = $produk['Harga'] ?? '';
$varianVal = $produk['Varian'] ?? '';
$deskripsiVal = $produk['Deskripsi_Produk'] ?? '';
$foto_current = $produk['Foto_Produk'] ?? '';


$foto_current_url = '';
if ($foto_current && file_exists(UPLOAD_DIR . $foto_current)) {
    $foto_current_url = UPLOAD_URL . $foto_current;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $harga = trim($_POST['harga'] ?? '');
    $varian = trim($_POST['varian'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    $namaVal = $nama;
    $kategoriVal = $kategori;
    $hargaVal = $harga;
    $varianVal = $varian;
    $deskripsiVal = $deskripsi;

    if (!$nama || $harga === '' || !is_numeric($harga) || $harga <= 0) {
        $msg = "Nama dan harga wajib diisi. Harga harus angka positif!";
    } else {
        $varianArray = array_filter(array_map('trim', explode(',', $varian)));
        if ($varian && empty($varianArray)) {
            $msg = "Varian tidak valid. Pisahkan dengan koma, contoh: S,M,L";
        } else {
            $foto_name = $foto_current;
            
          
            if (!empty($_FILES['foto']['name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
               
                if ($foto_current) {
                    $file_path = UPLOAD_DIR . $foto_current;
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
                
                $original = basename($_FILES['foto']['name']);
                $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original, PATHINFO_FILENAME));
                $foto_name = $safe . '_' . time();
                if ($ext) $foto_name .= '.' . $ext;

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['foto']['tmp_name']);
                finfo_close($finfo);
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($mime, $allowed)) {
                    $msg = 'File tidak valid. Gunakan: JPG, PNG, atau WEBP.';
                } elseif ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                    $msg = 'Ukuran file terlalu besar. Maksimal 3MB.';
                } else {
                    if (!is_dir(UPLOAD_DIR)) {
                        mkdir(UPLOAD_DIR, 0755, true);
                    }
                    if (!move_uploaded_file($_FILES['foto']['tmp_name'], UPLOAD_DIR . $foto_name)) {
                        $msg = 'Gagal menyimpan file. Periksa izin folder uploads.';
                    }
                }
            }

            if (!$msg) {
                try {
                    $stmt = $pdo->prepare("UPDATE produk SET Nama = ?, Kategori = ?, Harga = ?, Varian = ?, Deskripsi_Produk = ?, Foto_Produk = ?, updated_at = CURRENT_TIMESTAMP WHERE ID = ?");
                    $stmt->execute([
                        substr($nama, 0, 100),
                        $kategori ?: null,
                        (float)$harga,
                        !empty($varianArray) ? implode(', ', $varianArray) : null,
                        substr($deskripsi, 0, 2000),
                        $foto_name,
                        $id
                    ]);
                    header('Location: daftar_produk.php?msg=' . urlencode('Produk berhasil diperbarui!'));
                    exit;
                } catch (PDOException $e) {
                    error_log("Update Produk Error: " . $e->getMessage());
                    $msg = 'Gagal memperbarui produk. Silakan coba lagi.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Edit Produk — Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin/edit_produk.css">
</head>
<body>
    <div class="main-wrapper">
        <div class="form-box">
            <div class="form-header">
                <i class="fas fa-edit"></i>
                Edit Produk
            </div>
            <div class="form-body">
                <?php if ($msg): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <form id="editForm" method="POST" enctype="multipart/form-data">
                    <div class="form-row-meta">
                        <div class="form-group">
                            <label for="nama">Nama Produk <span class="required">*</span></label>
                            <input 
                                id="nama" 
                                type="text" 
                                name="nama" 
                                value="<?= htmlspecialchars($namaVal) ?>" 
                                placeholder="Contoh: Jus Mangga Segar"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="harga">Harga (Rp) <span class="required">*</span></label>
                            <input 
                                id="harga" 
                                type="number" 
                                name="harga" 
                                value="<?= htmlspecialchars($hargaVal) ?>" 
                                placeholder="45000"
                                required
                            >
                            <small class="help">Tanpa titik/koma</small>
                        </div>

                        <div class="form-group">
                            <label for="varian">Varian</label>
                            <input 
                                id="varian" 
                                type="text" 
                                name="varian" 
                                value="<?= htmlspecialchars($varianVal) ?>"
                                placeholder="S,M,L"
                                maxlength="255"
                            >
                            <small class="help">Pisahkan dengan koma</small>
                        </div>

                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <select 
                                id="kategori" 
                                name="kategori" 
                                class="dropdown-select"
                            >
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategoriList as $kat): ?>
                                    <option value="<?= htmlspecialchars($kat) ?>"
                                        <?= $kategoriVal === $kat ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="help">Kategori produk</small>
                        </div>
                    </div>

                    <div class="form-row-desc-foto">
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea 
                                id="deskripsi" 
                                name="deskripsi"
                                placeholder="Ceritakan keunggulan produk..."
                                maxlength="2000"
                            ><?= htmlspecialchars($deskripsiVal) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="foto">Foto Produk</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="upload-text" id="uploadFileName">
                                    <?= $foto_current ? 'Ganti foto produk' : 'Upload foto produk' ?>
                                </div>
                                <div class="upload-hint" id="uploadHint">
                                    <?php if ($foto_current): ?>
                                        Foto saat ini: <span class="current-foto"><?= htmlspecialchars($foto_current) ?></span>
                                    <?php else: ?>
                                        JPG, PNG, WebP • ≤3MB
                                    <?php endif; ?>
                                </div>
                                <input 
                                    id="foto" 
                                    type="file" 
                                    name="foto" 
                                    accept="image/jpeg,image/png,image/webp"
                                    class="upload-input"
                                >
                            </div>
                            <small class="help">Biarkan kosong untuk tetap menggunakan foto saat ini</small>
                        </div>
                    </div>
                </form>
            </div>

            <div class="action-bar">
                <div class="action-info">
                    <i class="fas fa-info-circle"></i>
                    <?php if ($foto_current): ?>
                        Foto saat ini akan diganti jika upload file baru
                    <?php else: ?>
                        Produk ini belum memiliki foto
                    <?php endif; ?>
                </div>
                <a href="daftar_produk.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" form="editForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>

        <div class="preview-box">
            <div class="preview-header">
                <i class="fas fa-eye"></i> Peninjauan
            </div>
            <div class="preview-body">
                <div class="preview-img-container">
                    <?php if ($foto_current_url): ?>
                        <img id="livePreviewImg" class="preview-img" 
                            src="<?= htmlspecialchars($foto_current_url) ?>" 
                            alt="Foto Produk Saat Ini"
                            onload="handleImageLoad(this)"
                            onerror="handleImageError(this)">
                        <div class="preview-img-placeholder" id="previewPlaceholder" style="display: none;">
                            <i class="fas fa-image"></i>
                            <div>Belum ada foto</div>
                        </div>
                    <?php else: ?>
                        <div class="preview-img-placeholder" id="previewPlaceholder">
                            <i class="fas fa-image"></i>
                            <div>Belum ada foto</div>
                        </div>
                        <img id="livePreviewImg" class="preview-img" src="" alt="Preview Produk" style="display: none;">
                    <?php endif; ?>
                </div>
                <div class="preview-text">
                    <h3 id="liveNama"><?= htmlspecialchars($produk['Nama']) ?></h3>
                    <div class="preview-meta">
                        <span id="liveHarga">Rp <?= number_format($produk['Harga'], 0, ',', '.') ?></span>
                        <?php if ($varianVal): ?>
                            <span id="liveVarianDisplay">
                                <?php 
                                $variants = array_map('trim', explode(',', $varianVal));
                                foreach ($variants as $v):
                                    if (!empty($v)):
                                ?>
                                    <span><?= htmlspecialchars($v) ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($kategoriVal): ?>
                            <span id="liveKategoriDisplay" class="preview-category-badge"><?= htmlspecialchars($kategoriVal) ?></span>
                        <?php endif; ?>
                    </div>
                    <p id="liveDeskripsi"><?= nl2br(htmlspecialchars($deskripsiVal ?: 'Deskripsi produk akan muncul...')) ?></p>
                    
                    <div class="preview-updated">
                        <i class="fas fa-info-circle"></i> Preview akan diperbarui saat Anda mengedit
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.EDIT_PRODUK_DATA = {
            fotoCurrent: <?= json_encode($foto_current) ?>,
            fotoCurrentUrl: <?= json_encode($foto_current_url) ?>
        };
    </script>
    <script src="../js/admin/edit_produk.js"></script>
</body>
</html>