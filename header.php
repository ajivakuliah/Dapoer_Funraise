<?php
// admin/header.php
require '../config.php';

$error = $success = '';

// Path upload yang benar (dari root project)
$uploadDir = '../uploads/logo/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function deleteOldLogo($oldPath) {
    $defaultLogo = 'uploads/logo.png';
    // Hapus file lama jika bukan default dan file ada
    if (!empty($oldPath) && $oldPath !== $defaultLogo && file_exists('../' . $oldPath)) {
        unlink('../' . $oldPath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['business_name'] ?? 'Dapoer Funraise');
    $tag = trim($_POST['tagline'] ?? 'Cemilan rumahan yang bikin nagih!');
    $logoPath = $_POST['current_logo'] ?? 'uploads/logo.png';

    // Proses upload file
    if (!empty($_FILES['logo_file']['name'])) {
        $file = $_FILES['logo_file'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Terjadi kesalahan saat upload.";
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $error = "Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.";
        } elseif ($file['size'] > $maxSize) {
            $error = "Ukuran gambar terlalu besar (maks. 2 MB).";
        } else {
            $ext = match ($file['type']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };

            $newName = 'logo_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            $targetPath = $uploadDir . $newName; // ../uploads/logo/xxx.jpg

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Hapus logo lama
                deleteOldLogo($_POST['current_logo'] ?? '');
                
                // Simpan path relatif dari root (tanpa ../)
                $logoPath = 'uploads/logo/' . $newName;
            } else {
                $error = "Gagal menyimpan file ke server.";
            }
        }
    }

    // Simpan ke database jika tidak ada error
    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO header (id, logo_path, business_name, tagline) 
                VALUES (1, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    logo_path = VALUES(logo_path),
                    business_name = VALUES(business_name),
                    tagline = VALUES(tagline),
                    updated_at = NOW()
            ");
            $stmt->execute([$logoPath, $name, $tag]);
            $success = "Header berhasil diperbarui!";
            
            // Update data untuk preview
            $data = [
                'logo_path' => $logoPath,
                'business_name' => $name,
                'tagline' => $tag
            ];
        } catch (PDOException $e) {
            $error = "Gagal menyimpan ke database: " . $e->getMessage();
        }
    }
}

// Ambil data dari database
$stmt = $pdo->query("SELECT * FROM header WHERE id = 1");
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'logo_path' => 'uploads/logo.png',
    'business_name' => 'Dapoer Funraise',
    'tagline' => 'Cemilan rumahan yang bikin nagih!'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header & Logo — Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --accent: #F9CC22;
            --bg-light: #FFF5EE;
            --soft: #DFBEE0;
            --text-muted: #9180BB;
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
            padding: 0;
            margin: 0;
        }

        .main-wrapper {
            display: flex;
            gap: 0;
            width: 100vw;
            margin: 0;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
        }

        .form-box {
            flex: 1;
            background: white;
            box-shadow: 0 5px 20px rgba(90, 70, 162, 0.1);
            overflow: hidden;
            border: 1px solid #f0eaff;
            margin: 0;
            border-radius: 0;
        }

        .preview-box {
            width: 380px;
            flex-shrink: 0;
            background: white;
            box-shadow: 0 5px 20px rgba(90, 70, 162, 0.1);
            overflow: hidden;
            border: 1px solid #f0eaff;
            margin: 0;
            border-radius: 0;
        }

        @media (max-width: 768px) {
            .preview-box {
                width: 100%;
                max-width: 100%;
            }
        }

        .form-header, .preview-header {
            background: linear-gradient(120deg, #f5f3ff, #faf5ff);
            padding: 0.9rem 1.4rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 1px solid #f0eaff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-header { color: var(--primary); }
        .preview-header { color: var(--secondary); justify-content: center; }

        .form-body {
            padding: 1.5rem 1.4rem 1rem;
        }

        .row {
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
        }

        @media (min-width: 768px) {
            .row {
                flex-direction: row;
                gap: 1.1rem;
            }
            .form-group {
                flex: 1;
            }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
            color: var(--primary);
        }

        input[type="text"] {
            width: 100%;
            padding: 11px 15px;
            border: 2px solid #e8e6f2;
            border-radius: 10px;
            font-size: 0.93rem;
            background: #faf9ff;
            font-family: inherit;
            transition: all 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.1);
        }

        .upload-area {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 18px 16px;
            border: 2px dashed var(--soft);
            border-radius: 10px;
            background: #faf9ff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: #f5f3ff;
        }

        .upload-area i {
            font-size: 1.6rem;
            color: var(--text-muted);
        }

        .upload-text {
            font-size: 0.92rem;
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

        .help {
            display: block;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 3px;
            font-style: italic;
        }

        .alert {
            background: #fff8f8;
            color: #c0392b;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 3px solid var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #66bb6a;
        }

        .action-bar {
            padding: 0.8rem 1.4rem 0.9rem;
            background: #fbf9ff;
            border-top: 1px solid #f3f0ff;
            display: flex;
            gap: 10px;
            margin-top: 0;
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
            gap: 6px;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s;
            font-family: inherit;
            min-height: 40px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #9e3e52);
            color: white;
            flex: 1;
            box-shadow: 0 2px 8px rgba(182, 75, 98, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(182, 75, 98, 0.25);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--soft), #c8a5d0);
            color: var(--primary);
            flex: 1;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #d0a8d5, #c095cb);
        }

        .preview-body {
            padding: 1.5rem 1.2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.1rem;
        }

        .preview-logo-container {
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .preview-logo {
            width: 250px;
            height: 250px;
            object-fit: contain;
        }

        .preview-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .preview-tag {
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
            max-width: 90%;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="form-box">
            <div class="form-header">
                <i class="fas fa-sliders-h" style="color: var(--secondary);"></i>
                Header & Logo
            </div>

            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form id="headerForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="current_logo" value="<?= htmlspecialchars($data['logo_path']) ?>">

                    <div class="row">
                        <div class="form-group">
                            <label for="business_name">Nama Usaha</label>
                            <input 
                                type="text" 
                                id="business_name" 
                                name="business_name"
                                value="<?= htmlspecialchars($data['business_name']) ?>"
                                maxlength="100" 
                                required
                                placeholder="Contoh: Dapoer Funraise"
                            >
                        </div>

                        <div class="form-group">
                            <label for="tagline">Tagline</label>
                            <input 
                                type="text" 
                                id="tagline" 
                                name="tagline"
                                value="<?= htmlspecialchars($data['tagline']) ?>"
                                maxlength="150" 
                                required
                                placeholder="Contoh: Cemilan rumahan yang bikin nagih!"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Ganti Logo</label>
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="upload-text" id="uploadFileName">Klik atau seret file</div>
                            <div class="upload-hint">JPG, PNG, WebP • ≤2 MB</div>
                            <input 
                                type="file" 
                                id="logo_file" 
                                name="logo_file" 
                                class="upload-input"
                                accept=".jpg,.jpeg,.png,.webp"
                            >
                        </div>
                        <span class="help">Logo saat ini: <?= basename($data['logo_path']) ?></span>
                    </div>
                </form>
            </div>

            <div class="action-bar">
                <a href="pengaturan.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" form="headerForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>

        <div class="preview-box">
            <div class="preview-header">
                <i class="fas fa-eye"></i> Peninjauan 
            </div>
            <div class="preview-body">
                <div class="preview-logo-container">
                    <img 
                        src="../<?= htmlspecialchars($data['logo_path']) ?>"
                        alt="Preview Logo"
                        class="preview-logo"
                        id="previewLogo"
                        onerror="this.src='../uploads/logo.png'; this.onerror=null;"
                    >
                </div>
                <div class="preview-name" id="previewName"><?= htmlspecialchars($data['business_name']) ?></div>
                <div class="preview-tag" id="previewTag"><?= htmlspecialchars($data['tagline']) ?></div>
            </div>
        </div>
    </div>

    <script>
        // Live preview untuk nama usaha
        document.getElementById('business_name').addEventListener('input', e => {
            document.getElementById('previewName').textContent = e.target.value || 'Nama Usaha';
        });

        // Live preview untuk tagline
        document.getElementById('tagline').addEventListener('input', e => {
            document.getElementById('previewTag').textContent = e.target.value || 'Tagline';
        });

        // Live preview untuk logo
        document.getElementById('logo_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadFileName = document.getElementById('uploadFileName');
            const previewLogo = document.getElementById('previewLogo');
            
            if (file) {
                // Validasi ukuran file
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar! Maksimal 2 MB.');
                    this.value = '';
                    uploadFileName.textContent = 'Klik atau seret file';
                    return;
                }

                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung! Gunakan JPG, PNG, atau WebP.');
                    this.value = '';
                    uploadFileName.textContent = 'Klik atau seret file';
                    return;
                }

                // Update nama file
                let name = file.name;
                if (name.length > 20) name = name.substring(0, 17) + '...';
                uploadFileName.textContent = name;

                // Preview gambar
                const reader = new FileReader();
                reader.onload = ev => {
                    previewLogo.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                uploadFileName.textContent = 'Klik atau seret file';
            }
        });

        // Drag and drop support
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--primary)';
            uploadArea.style.background = '#f5f3ff';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = 'var(--soft)';
            uploadArea.style.background = '#faf9ff';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--soft)';
            uploadArea.style.background = '#faf9ff';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('logo_file').files = files;
                // Trigger change event
                document.getElementById('logo_file').dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>