<?php
require 'config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Internal Server Error: Database connection not established.');
}

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

$msg = '';
$namaVal = '';
$kategoriVal = '';
$hargaVal = '';
$varianVal = '';
$deskripsiVal = '';

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
            $foto_name = null;
            if (!empty($_FILES['foto']['name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
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
                    $upload_dir = __DIR__ . "/uploads/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_name)) {
                        $msg = 'Gagal menyimpan file. Periksa izin folder uploads.';
                    }
                }
            }

            if (!$msg) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO produk (Nama, Kategori, Harga, Varian, Deskripsi_Produk, Foto_Produk) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        substr($nama, 0, 100),
                        $kategori ?: null,
                        (float)$harga,
                        !empty($varianArray) ? implode(', ', $varianArray) : null,
                        substr($deskripsi, 0, 2000),
                        $foto_name
                    ]);
                    header('Location: ./admin/daftar_produk.php?msg=' . urlencode('Produk berhasil ditambahkan!'));
                    exit;
                } catch (PDOException $e) {
                    error_log("Insert Produk Error: " . $e->getMessage());
                    $msg = 'Gagal menyimpan produk. Silakan coba lagi.';
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
    <title>Tambah Produk — Dapoer Funraise</title>
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
            margin:0;
            padding: 0;
        }

        .main-wrapper {
            display: flex;
            gap: 2px;
            width: 100%;
            margin: 0 auto;
            background: white;
            box-shadow: 0 5px 30px rgba(90, 70, 162, 0.12);
            border-radius: 16px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
            
            body {
                padding: 20px 10px;
            }
        }

        .form-box {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            border-right: 2px solid #B64B62;
        }

        .preview-box {
            width: 360px;
            flex-shrink: 0;
            background: white;
            display: flex;
            flex-direction: column;
        }

        @media (max-width: 768px) {
            .preview-box {
                width: 100%;
            }
        }

        .form-header, .preview-header {
            background: #faf5ff;
            padding: 12px 20px;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 1px solid #f0eaff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-header { color: var(--primary); }
        .preview-header { color: var(--secondary); justify-content: center; }

        .form-body {
            padding: 20px;
            padding-bottom: 1px;
            flex: 1;
        }

        .alert {
            background: #fff8f8;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border-left: 3px solid var(--secondary);
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.95rem;
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
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
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
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.1);
        }

        /* DROPDOWN STYLING */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%235A46A2' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 10px;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-row-meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
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
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
            display: block;
        }

        .variant-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .variant-tag {
            background: var(--soft);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .category-display {
            display: inline-block;
            background: #f0f0ff;
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 6px;
        }

        .form-row-desc-foto {
            display: flex;
            gap: 16px;
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
            min-height: 150px;
            resize: none;
            padding: 14px 16px;
        }

        .upload-area {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px 16px;
            border: 2px dashed var(--soft);
            border-radius: 10px;
            background: var(--bg-light);
            cursor: pointer;
            min-height: 150px;
            text-align: center;
            transition: all 0.2s;
        }

        .upload-area:hover {
            border-color: var(--primary);
        }

        .upload-area i {
            font-size: 1.8rem;
            color: var(--text-muted);
        }

        .upload-text {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary);
        }

        .upload-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
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
            padding: 12px 20px;
            background: #fbf9ff;
            border-top: 1px solid #f3f0ff;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
            font-family: inherit;
            min-width: 120px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #9e3e52);
            color: white;
            box-shadow: 0 3px 8px rgba(182, 75, 98, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(182, 75, 98, 0.25);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--soft), #c8a5d0);
            color: var(--primary);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #d0a8d5, #c095cb);
        }

        .preview-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 16px;
        }

        .preview-img-container {
            width: 100%;
            max-width: 260px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-light);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #f0eaff;
        }

        .preview-img-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            padding: 10px;
            text-align: center;
        }

        .preview-img-placeholder i {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }

        .preview-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: none;
        }

        .preview-text {
            text-align: center;
            max-width: 260px;
        }

        .preview-text h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary);
            min-height: 1.4em;
        }

        .preview-meta {
            font-size: 0.95rem;
            margin-bottom: 12px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .preview-meta span {
            color: var(--secondary);
            font-weight: 600;
        }

        .preview-category-badge {
            display: inline-block;
            background: var(--soft);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .preview-text p {
            font-size: 0.92rem;
            color: #666;
            line-height: 1.5;
            min-height: 60px;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="form-box">
            <div class="form-header">
                <i class="fas fa-plus-circle"></i>
                Tambah Produk
            </div>
            <div class="form-body">
                <?php if ($msg): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <form id="addForm" method="POST" enctype="multipart/form-data">
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
                            <div class="variant-tags" id="variantPreview"></div>
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
                                <div class="upload-text" id="uploadFileName">Klik atau seret file</div>
                                <div class="upload-hint">JPG, PNG, WebP • ≤3MB</div>
                                <input 
                                    id="foto" 
                                    type="file" 
                                    name="foto" 
                                    accept="image/jpeg,image/png,image/webp"
                                    class="upload-input"
                                >
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="action-bar">
                <a href="./admin/daftar_produk.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" form="addForm" class="btn btn-primary">
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
                    <div class="preview-img-placeholder" id="previewPlaceholder">
                        <i class="fas fa-image"></i>
                        <div>Belum ada foto</div>
                    </div>
                    <img id="livePreviewImg" class="preview-img" src="" alt="Preview Produk">
                </div>
                <div class="preview-text">
                    <h3 id="liveNama">Nama Produk</h3>
                    <div class="preview-meta">
                        <span id="liveHarga">Rp 0</span>
                        <span id="liveVarianDisplay"></span>
                        <span id="liveKategoriDisplay" class="preview-category-badge" style="display: none;"></span>
                    </div>
                    <p id="liveDeskripsi">Deskripsi produk akan muncul...</p>
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
        const variantPreview = document.getElementById('variantPreview');
        const categoryDisplay = document.getElementById('categoryDisplay');

        // Initialize with current values
        liveNama.textContent = namaInput.value || 'Nama Produk';
        liveHarga.textContent = formatRupiah(hargaInput.value);
        liveDeskripsi.textContent = deskripsiInput.value || 'Deskripsi produk akan muncul...';
        
        const initVars = (varianInput.value || '').split(',').map(v => v.trim()).filter(v => v);
        liveVarianDisplay.innerHTML = createVariantTags(initVars);
        variantPreview.innerHTML = createVariantTags(initVars);
        
        // Initialize kategori
        if (kategoriSelect.value) {
            liveKategoriDisplay.textContent = kategoriSelect.value;
            liveKategoriDisplay.style.display = 'inline-block';
            categoryDisplay.textContent = kategoriSelect.value;
            categoryDisplay.style.display = 'block';
        }

        // Live updates
        namaInput.addEventListener('input', e => liveNama.textContent = e.target.value.trim() || 'Nama Produk');
        
        hargaInput.addEventListener('input', e => liveHarga.textContent = formatRupiah(e.target.value));
        
        deskripsiInput.addEventListener('input', e => liveDeskripsi.textContent = e.target.value.trim() || 'Deskripsi produk akan muncul...');
        
        varianInput.addEventListener('input', e => {
            const vars = e.target.value.split(',').map(v => v.trim()).filter(v => v);
            liveVarianDisplay.innerHTML = createVariantTags(vars);
            variantPreview.innerHTML = createVariantTags(vars);
        });
        
        kategoriSelect.addEventListener('change', function(e) {
            if (e.target.value) {
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
                uploadFileName.textContent = name;
                const reader = new FileReader();
                reader.onload = ev => {
                    livePreviewImg.src = ev.target.result;
                    livePreviewImg.style.display = 'block';
                    previewPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                uploadFileName.textContent = 'Klik atau seret file';
                livePreviewImg.style.display = 'none';
                previewPlaceholder.style.display = 'flex';
            }
        });

        // Form validation
        document.getElementById('addForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>