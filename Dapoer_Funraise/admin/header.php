<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get header data
$header = null;

try {
    // Get header data - ambil data pertama saja
    $stmt = $pdo->query("SELECT * FROM header ORDER BY id DESC LIMIT 1");
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$header) {
        // Create default
        $stmt = $pdo->prepare("INSERT INTO header (logo_path, business_name, tagline) VALUES (?, ?, ?)");
        $stmt->execute([
            'assets/logo.png',
            'Dapoer Funraise',
            'Cemilan rumahan yang bikin nagih!'
        ]);
        $header = [
            'id' => $pdo->lastInsertId(), 
            'logo_path' => 'assets/logo.png',
            'business_name' => 'Dapoer Funraise',
            'tagline' => 'Cemilan rumahan yang bikin nagih!',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_header'])) {
        // Update header data
        $business_name = trim($_POST['business_name']);
        $tagline = trim($_POST['tagline']);
        
        // Handle logo upload
        $logo_path = $header['logo_path'];
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_name = $_FILES['logo']['name'];
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_size = $_FILES['logo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file extension
            if (in_array($file_ext, $allowed_extensions)) {
                // Check file size (max 2MB)
                if ($file_size <= 2097152) {
                    // Generate unique file name
                    $new_file_name = 'logo_' . time() . '.' . $file_ext;
                    $upload_path = '../assets/' . $new_file_name;
                    
                    // Upload file
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Delete old logo if exists and not default
                        if ($logo_path != 'assets/logo.png' && file_exists('../' . $logo_path)) {
                            unlink('../' . $logo_path);
                        }
                        $logo_path = 'assets/' . $new_file_name;
                        $_SESSION['success'] = 'Logo berhasil diupload';
                    } else {
                        $_SESSION['error'] = 'Gagal mengupload logo';
                    }
                } else {
                    $_SESSION['error'] = 'Ukuran file terlalu besar (max 2MB)';
                }
            } else {
                $_SESSION['error'] = 'Format file tidak didukung (hanya JPG, PNG, GIF, WebP)';
            }
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE header SET logo_path = ?, business_name = ?, tagline = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$logo_path, $business_name, $tagline, $header['id']])) {
            $_SESSION['success'] = 'Header berhasil diperbarui';
            header('Location: header.php');
            exit;
        }
    }
    
    // Jika ada error, redirect dengan error message
    header('Location: header.php');
    exit;
}

// Tampilkan pesan success/error jika ada
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus session messages setelah ditampilkan
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 0px;
            width: 100%;
        }
        
        .container {
            width: 100%;
            min-width: 100%;
            background: #f8f6fd;
            border-radius: 15px;
            padding: 30px;
            overflow: visible;
            margin:0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 3px solid #5a46a2;
        }
        
        .page-title {
            color: #2c3e50;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            border: none;
            padding: 0;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(127, 140, 141, 0.3);
        }
        
        h2 {
            color: #5a46a2;
            margin-bottom: 18px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h3 {
            color: #5a46a2;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .input-text {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .input-text:focus {
            outline: none;
            border-color: #b64b62;
            background: white;
            box-shadow: 0 0 0 3px rgba(182, 75, 98, 0.1);
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f9cc22, #b64b62);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #f9cc22, #b64b62);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(252, 199, 224, 0.3);
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Baris dengan 3 kolom */
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            background: white;
            padding: 12px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 15px;
            min-height: 48px;
            display: flex;
            align-items: center;
        }
        
        .help-text {
            color: #6c757d;
            display: block;
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .form-actions {
            margin-top: 30px;
            display: flex;
            justify-content: center;
        }
        
        .info-box {
            background: #e7f1ff;
            border: 1px solid #b8d4ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            color: #0d6efd;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box ul {
            padding-left: 20px;
            color: #495057;
        }
        
        .info-box li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .upload-group {
            background: #f8f9fa;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
            margin-top: 10px;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-btn {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            justify-content: center;
        }
        
        .file-input-btn:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        
        .current-file-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            color: #495057;
            text-align: center;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .timestamp {
            color: #6c757d;
            font-size: 13px;
        }
        
        /* Form Input Row */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }

@media (max-width: 480px) {
    .header-container {
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 5px !important;
        text-align: left !important;
    }

    .btn-back {
        padding: 8px 12px;
        font-size: 12px;
        width: auto !important;
    }

    .page-title {
        font-size: 18px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .info-row {
        grid-template-columns: 1fr;
    }

    .btn-primary {
        width: 100%;
        justify-content: center;
    }

    .upload-group {
        padding: 15px;
    }

    .input-text {
        font-size: 14px;
        padding: 10px;
    }

    .info-value {
        font-size: 14px;
        padding: 10px;
    }
}

/* 📱 Tablet Potrait (≤ 768px) */
@media (max-width: 768px) {

    .container {
        padding: 20px;
    }

    .header-container {
        flex-direction: column;
        gap: 15px;
    }

    .form-row {
        grid-template-columns: 1fr 1fr;
    }

    .info-row {
        grid-template-columns: 1fr 1fr;
    }

    .page-title {
        font-size: 20px;
    }
}

/* 💻 Tablet Landscape / Laptop kecil (≤ 1024px) */
@media (max-width: 1024px) {

    .container {
        padding: 25px;
    }

    .form-row {
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
    }
}        
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-heading"></i> Edit Header</h1>
            <a href="pengaturan.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
            
        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>
        
        <!-- Edit Header Content -->
        <div class="section">
            <h2><i class="fas fa-edit"></i> Konten Header</h2>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Informasi Header</h4>
                <ul>
                    <li>Header adalah bagian atas website yang berisi logo dan informasi bisnis</li>
                    <li>Logo akan ditampilkan di bagian atas semua halaman website</li>
                    <li>Nama bisnis dan tagline akan muncul di dekat logo</li>
                    <li>Format logo yang didukung: JPG, PNG, GIF, WebP (max 2MB)</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Upload Logo -->
                <div class="upload-group">
                    <label>
                        <i class="fas fa-image"></i> Upload Logo:
                    </label>
                    <div class="file-input-wrapper">
                        <div class="file-input-btn">
                            <i class="fas fa-upload"></i> Pilih File Logo Baru
                        </div>
                        <input type="file" name="logo" id="logoInput" accept=".jpg,.jpeg,.png,.gif,.webp">
                    </div>
                    <div class="current-file-info">
                        <i class="fas fa-info-circle"></i>
                        Logo saat ini: <?= htmlspecialchars(basename($header['logo_path'])) ?>
                    </div>
                    <span class="help-text">
                        Ukuran maksimal: 2MB | Format: JPG, PNG, GIF, WebP
                    </span>
                </div>
                
                <!-- Form Input Row - 3 kolom dalam 1 baris -->
                <div class="form-row">
                    <!-- Nama Bisnis -->
                    <div class="form-group">
                        <label for="business_name">
                            <i class="fas fa-store"></i> Nama Bisnis Baru:
                        </label>
                        <input type="text" id="business_name" name="business_name" 
                            value="<?= htmlspecialchars($header['business_name']) ?>" 
                            maxlength="100" required class="input-text"
                            placeholder="Contoh: Dapoer Funraise">
                        <span class="help-text">
                            Nama bisnis Anda
                        </span>
                    </div>
                    
                    <!-- Tagline -->
                    <div class="form-group">
                        <label for="tagline">
                            <i class="fas fa-quote-left"></i> Tagline/Slogan Baru:
                        </label>
                        <input type="text" id="tagline" name="tagline" 
                            value="<?= htmlspecialchars($header['tagline']) ?>" 
                            maxlength="150" required class="input-text"
                            placeholder="Contoh: Cemilan rumahan yang bikin nagih!">
                        <span class="help-text">
                            Slogan bisnis Anda
                        </span>
                    </div>
                    
                    <!-- Timestamp (Readonly) -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-clock"></i> Diperbarui Pada:
                        </label>
                        <div class="info-value">
                            <span class="timestamp">
                                <?= date('d M Y H:i:s') ?>
                            </span>
                        </div>
                        <span class="help-text">
                            Timestamp akan diupdate otomatis
                        </span>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="form-actions">
                    <button type="submit" name="update_header" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // File size validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('logoInput');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size; // in bytes
                const maxSize = 2097152; // 2MB in bytes
                
                if (fileSize > maxSize) {
                    e.preventDefault();
                    alert('Ukuran file terlalu besar! Maksimal 2MB.');
                    return false;
                }
            }
        });
        
        // Update current file info when file is selected
        document.getElementById('logoInput').addEventListener('change', function(e) {
            const fileInfo = document.querySelector('.current-file-info');
            if (this.files.length > 0) {
                fileInfo.innerHTML = `
                    <i class="fas fa-info-circle"></i>
                    File baru yang dipilih: ${this.files[0].name}
                `;
            }
        });
        
        // Update timestamp to current time
        function updateTimestamp() {
            const now = new Date();
            const options = { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const formattedDate = now.toLocaleDateString('id-ID', options);
            document.querySelector('.info-value .timestamp').textContent = formattedDate;
        }
        
        // Update timestamp every minute
        setInterval(updateTimestamp, 60000);
    </script>
</body>
</html>