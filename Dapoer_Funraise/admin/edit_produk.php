<?php
require '../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Internal Server Error: Database connection not established.');
}

// Set constants untuk upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Daftar kategori untuk dropdown
$kategoriList = [
    'Makanan',
    'Minuman', 
    'Kue & Roti',
    'Snack',
    'Catering',
    'Lainnya'
];

// Get product ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: daftar_produk.php?msg=ID produk tidak valid');
    exit;
}

// Fetch product data
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

// Set URL foto untuk preview
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
            
            // Handle new photo upload
            if (!empty($_FILES['foto']['name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
                // Delete old photo if exists
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
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --accent: #F9CC22;
            --soft: #DFBEE0;
            --text-muted: #9180BB;
            --border: #e8e6f2;
            --border-dark: #d8d2f0;
            --bg-light: #faf9ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f1e8fdff;
            color: #333;
            font-size: 15px;
            min-height: 100vh;
            margin: 0;
            padding: 0px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
        }

        .main-wrapper {
            display: flex;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 5px 30px rgba(90, 70, 162, 0.15);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border-dark);
            min-height: 85vh;
        }

        @media (max-width: 992px) {
            .main-wrapper {
                max-width: 95%;
            }
        }

        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
                min-height: auto;
                border-radius: 16px;
            }
        }

        .form-box {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
            border-right: 2px solid #B64B62;
        }

        .preview-box {
            width: 380px;
            flex-shrink: 0;
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        @media (max-width: 992px) {
            .preview-box {
                width: 340px;
            }
        }

        @media (max-width: 768px) {
            .preview-box {
                width: 100%;
                border-top: 2px solid var(--border-dark);
                border-right: none;
            }
        }

        .form-header, .preview-header {
            background: #faf5ff;
            padding: 16px 24px;
            font-size: 1.25rem;
            font-weight: 600;
            border-bottom: 3px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-header { 
            color: var(--primary); 
            border-bottom: 1px solid var(--border);
        }
        
        .preview-header { 
            background: #fff5f8;
            color: var(--secondary); 
            justify-content: center; 
            border-bottom: 1px solid var(--border);
        }

        .form-body {
            padding: 24px;
            padding-bottom: 0;
            flex: 1;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .form-body {
                padding: 20px;
            }
        }

        .alert {
            background: #fff8f8;
            color: #c0392b;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary);
            font-size: 0.94rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left-color: #4caf50;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.96rem;
            color: var(--primary);
        }

        .required {
            color: var(--secondary);
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.96rem;
            background: var(--bg-light);
            font-family: inherit;
            transition: all 0.2s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(90, 70, 162, 0.12);
        }

        /* DROPDOWN STYLING */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%235A46A2' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 12px;
            padding-right: 45px;
            cursor: pointer;
        }

        .form-row-meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .form-row-meta {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .form-row-meta {
                grid-template-columns: 1fr;
            }
        }

        .form-row-meta .form-group {
            margin-bottom: 0;
        }

        .help {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 5px;
            display: block;
        }

        .variant-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .variant-tag {
            background: var(--soft);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .category-display {
            display: inline-block;
            background: #f0f0ff;
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.86rem;
            font-weight: 500;
            margin-top: 8px;
            border: 1px solid #e5e0ff;
        }

        .form-row-desc-foto {
            display: flex;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row-desc-foto {
                flex-direction: column;
            }
        }

        .form-row-desc-foto .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        textarea {
            min-height: 160px;
            resize: none;
            padding: 16px 18px;
            line-height: 1.5;
        }

        .upload-area {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 24px 20px;
            border: 2px dashed var(--soft);
            border-radius: 12px;
            background: var(--bg-light);
            cursor: pointer;
            min-height: 160px;
            text-align: center;
            transition: all 0.2s;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: #f9f7ff;
        }

        .upload-area i {
            font-size: 2rem;
            color: var(--text-muted);
        }

        .upload-text {
            font-size: 0.98rem;
            font-weight: 600;
            color: var(--primary);
        }

        .upload-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .upload-hint .warning {
            color: var(--secondary);
        }

        .current-foto {
            font-weight: 500;
            background: #f0f0ff;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
        }

        .upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .action-bar {
            padding: 16px 24px;
            background: #fbf9ff;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            align-items: center;
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
                padding: 16px 20px;
            }
        }

        .action-info {
            flex: 1;
            font-size: 0.88rem;
            color: var(--text-muted);
            padding-right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.96rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
            font-family: inherit;
            min-width: 130px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #4a3a8a);
            color: white;
            box-shadow: 0 4px 12px rgba(90, 70, 162, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(90, 70, 162, 0.25);
            background: linear-gradient(135deg, #6a56c2, #5a46a2);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--soft), #c8a5d0);
            color: var(--primary);
            border: 1px solid #e0c8e5;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #e0c8e5, #d0a8d5);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(223, 190, 224, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--secondary), #9e3e52);
            color: white;
            box-shadow: 0 4px 12px rgba(182, 75, 98, 0.2);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #d05876, #c0392b);
            transform: translateY(-2px);
        }

        .preview-body {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .preview-body {
                padding: 20px;
            }
        }

        .preview-img-container {
            width: 100%;
            max-width: 280px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-light);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .preview-img-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            padding: 15px;
            text-align: center;
            width: 100%;
            height: 100%;
        }

        .preview-img-placeholder.error {
            background: #fff5f5;
            color: var(--secondary);
            border: 1px dashed var(--secondary);
        }

        .preview-img-placeholder i {
            font-size: 2.8rem;
            margin-bottom: 10px;
        }

        .preview-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: none;
            border-radius: 10px;
        }

        .preview-img.loaded {
            display: block;
        }

        .preview-text {
            text-align: center;
            max-width: 280px;
            width: 100%;
        }

        .preview-text h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--primary);
            min-height: 1.4em;
            line-height: 1.3;
        }

        .preview-meta {
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .preview-meta span {
            color: var(--secondary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .preview-category-badge {
            display: inline-block;
            background: var(--soft);
            color: var(--primary);
            padding: 5px 14px;
            border-radius: 16px;
            font-size: 0.88rem;
            font-weight: 500;
            border: 1px solid #e5d5e9;
        }

        .preview-text p {
            font-size: 0.94rem;
            color: #666;
            line-height: 1.6;
            min-height: 70px;
            text-align: left;
            background: #f9f9ff;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-top: 10px;
        }

        .preview-updated {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 15px;
            font-style: italic;
            text-align: center;
            padding: 8px 12px;
            background: #f5f3ff;
            border-radius: 8px;
            border: 1px dashed var(--soft);
        }

        /* Variant tags in preview */
        #liveVarianDisplay {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
        }

        #liveVarianDisplay span {
            display: inline-block;
            background: #f0f0f0;
            color: #666;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 500;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- FORM SECTION (KIRI) -->
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
                    <!-- BARIS 1: NAMA, HARGA, VARIAN, KATEGORI -->
                    <div class="form-row-meta">
                        <!-- Nama Produk -->
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

                        <!-- Harga -->
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

                        <!-- Varian -->
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
                            <div class="variant-tags" id="variantPreview">
                                <?php if ($varianVal): ?>
                                    <?php 
                                    $variants = array_map('trim', explode(',', $varianVal));
                                    foreach ($variants as $v):
                                        if (!empty($v)):
                                    ?>
                                        <span class="variant-tag"><i class="fas fa-tag"></i> <?= htmlspecialchars($v) ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Kategori -->
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
                            <div id="categoryDisplay" class="category-display" style="display: <?= $kategoriVal ? 'block' : 'none' ?>;">
                                <?= $kategoriVal ? htmlspecialchars($kategoriVal) : '' ?>
                            </div>
                        </div>
                    </div>

                    <!-- BARIS 2: DESKRIPSI DAN FOTO -->
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
                    <i class="fas fa-save"></i> Update Produk
                </button>
            </div>
        </div>

        <!-- PREVIEW SECTION (KANAN) -->
        <div class="preview-box">
            <div class="preview-header">
                <i class="fas fa-eye"></i> Preview Update
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
        function formatRupiah(num) {
            const n = parseFloat(num) || 0;
            return 'Rp ' + n.toLocaleString('id-ID');
        }

        const createVariantTags = (variants) => {
            if (!variants?.length) return '';
            return variants.map(v => 
                `<span class="variant-tag"><i class="fas fa-tag"></i> ${v.trim()}</span>`
            ).join('');
        };

        const createPreviewVariantTags = (variants) => {
            if (!variants?.length) return '';
            return variants.map(v => 
                `<span>${v.trim()}</span>`
            ).join('');
        };

        // DOM Elements
        const namaInput = document.getElementById('nama');
        const hargaInput = document.getElementById('harga');
        const varianInput = document.getElementById('varian');
        const kategoriSelect = document.getElementById('kategori');
        const deskripsiInput = document.getElementById('deskripsi');
        const fotoInput = document.getElementById('foto');
        
        // Live Preview Elements
        const liveNama = document.getElementById('liveNama');
        const liveHarga = document.getElementById('liveHarga');
        const liveVarianDisplay = document.getElementById('liveVarianDisplay');
        const liveKategoriDisplay = document.getElementById('liveKategoriDisplay');
        const liveDeskripsi = document.getElementById('liveDeskripsi');
        const livePreviewImg = document.getElementById('livePreviewImg');
        const previewPlaceholder = document.getElementById('previewPlaceholder');
        const uploadFileName = document.getElementById('uploadFileName');
        const uploadHint = document.getElementById('uploadHint');
        const variantPreview = document.getElementById('variantPreview');
        const categoryDisplay = document.getElementById('categoryDisplay');

        // Image handlers
        function handleImageLoad(img) {
            img.classList.add('loaded');
            const placeholder = document.getElementById('previewPlaceholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        }

        function handleImageError(img) {
            img.style.display = 'none';
            const placeholder = document.getElementById('previewPlaceholder');
            if (placeholder) {
                placeholder.style.display = 'flex';
                placeholder.className = 'preview-img-placeholder error';
                placeholder.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div>Foto tidak dapat dimuat</div>
                    <small style="font-size: 0.7rem; margin-top: 5px; color: #999;"><?= htmlspecialchars($foto_current) ?></small>
                `;
            }
        }

        // Initialize with current values
        function initializePreview() {
            liveNama.textContent = namaInput.value || 'Nama Produk';
            liveHarga.textContent = formatRupiah(hargaInput.value);
            liveDeskripsi.textContent = deskripsiInput.value || 'Deskripsi produk akan muncul...';
            
            const initVars = (varianInput.value || '').split(',').map(v => v.trim()).filter(v => v);
            liveVarianDisplay.innerHTML = createPreviewVariantTags(initVars);
            variantPreview.innerHTML = createVariantTags(initVars);
            
            // Initialize kategori
            if (kategoriSelect.value) {
                if (!liveKategoriDisplay.classList.contains('preview-category-badge')) {
                    liveKategoriDisplay.classList.add('preview-category-badge');
                }
                liveKategoriDisplay.textContent = kategoriSelect.value;
                liveKategoriDisplay.style.display = 'inline-block';
                categoryDisplay.textContent = kategoriSelect.value;
                categoryDisplay.style.display = 'block';
            } else {
                liveKategoriDisplay.style.display = 'none';
                categoryDisplay.style.display = 'none';
            }
        }

        // Live updates
        namaInput.addEventListener('input', e => liveNama.textContent = e.target.value.trim() || 'Nama Produk');
        
        hargaInput.addEventListener('input', e => liveHarga.textContent = formatRupiah(e.target.value));
        
        deskripsiInput.addEventListener('input', e => liveDeskripsi.textContent = e.target.value.trim() || 'Deskripsi produk akan muncul...');
        
        varianInput.addEventListener('input', e => {
            const vars = e.target.value.split(',').map(v => v.trim()).filter(v => v);
            liveVarianDisplay.innerHTML = createPreviewVariantTags(vars);
            variantPreview.innerHTML = createVariantTags(vars);
        });
        
        kategoriSelect.addEventListener('change', function(e) {
            if (e.target.value) {
                if (!liveKategoriDisplay.classList.contains('preview-category-badge')) {
                    liveKategoriDisplay.classList.add('preview-category-badge');
                }
                liveKategoriDisplay.textContent = e.target.value;
                liveKategoriDisplay.style.display = 'inline-block';
                categoryDisplay.textContent = e.target.value;
                categoryDisplay.style.display = 'block';
            } else {
                liveKategoriDisplay.style.display = 'none';
                categoryDisplay.style.display = 'none';
            }
        });
        
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const name = file.name.length > 22 ? file.name.substring(0, 19) + '...' : file.name;
                uploadFileName.textContent = 'File baru: ' + name;
                uploadFileName.style.color = 'var(--secondary)';
                
                // Update hint text
                uploadHint.innerHTML = `
                    <span class="warning">
                        <i class="fas fa-sync-alt"></i> Akan mengganti: 
                        <span style="text-decoration: line-through; color: #999;"><?= htmlspecialchars($foto_current) ?></span>
                    </span>
                `;
                
                const reader = new FileReader();
                reader.onload = ev => {
                    livePreviewImg.src = ev.target.result;
                    livePreviewImg.classList.add('loaded');
                    previewPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                // Reset jika user cancel
                resetPhotoDisplay();
            }
        });

        // Fungsi untuk reset display foto
        function resetPhotoDisplay() {
            uploadFileName.textContent = '<?= $foto_current ? "Ganti foto produk" : "Upload foto produk" ?>';
            uploadFileName.style.color = 'var(--primary)';
            
            // Reset hint text
            uploadHint.innerHTML = '<?= $foto_current ? "Foto saat ini: <span class=\'current-foto\'>" . htmlspecialchars($foto_current) . "</span>" : "JPG, PNG, WebP • ≤3MB" ?>';
            
            // Reset preview image
            <?php if ($foto_current_url): ?>
                livePreviewImg.src = '<?= htmlspecialchars($foto_current_url) ?>';
                livePreviewImg.classList.add('loaded');
                previewPlaceholder.style.display = 'none';
            <?php else: ?>
                livePreviewImg.style.display = 'none';
                livePreviewImg.classList.remove('loaded');
                previewPlaceholder.style.display = 'flex';
                previewPlaceholder.className = 'preview-img-placeholder';
                previewPlaceholder.innerHTML = `
                    <i class="fas fa-image"></i>
                    <div>Belum ada foto</div>
                `;
            <?php endif; ?>
        }

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            if (!namaInput.value.trim()) {
                alert('Nama produk wajib diisi!');
                e.preventDefault();
                namaInput.focus();
                return;
            }
            
            if (!hargaInput.value || parseFloat(hargaInput.value) <= 0) {
                alert('Harga wajib diisi dengan angka positif!');
                e.preventDefault();
                hargaInput.focus();
                return;
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initializePreview);
        
        // Drag and drop untuk upload area
        const uploadArea = document.getElementById('uploadArea');
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = 'var(--primary)';
                uploadArea.style.background = '#f5f3ff';
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = 'var(--soft)';
                uploadArea.style.background = 'var(--bg-light)';
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = 'var(--soft)';
                uploadArea.style.background = 'var(--bg-light)';
                
                if (e.dataTransfer.files.length) {
                    fotoInput.files = e.dataTransfer.files;
                    const event = new Event('change', { bubbles: true });
                    fotoInput.dispatchEvent(event);
                }
            });
        }
    </script>
</body>
</html>