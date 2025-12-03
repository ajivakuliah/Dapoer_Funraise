<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$header = null;

try {
    $stmt = $pdo->query("SELECT * FROM header ORDER BY id DESC LIMIT 1");
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$header) {
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_header'])) {
        $business_name = trim($_POST['business_name']);
        $tagline = trim($_POST['tagline']);
        
        $logo_path = $header['logo_path'];
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_name = $_FILES['logo']['name'];
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_size = $_FILES['logo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_extensions)) {
                if ($file_size <= 2097152) {
                    $new_file_name = 'logo_' . time() . '.' . $file_ext;
                    $upload_path = '../assets/' . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
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
        
        $stmt = $pdo->prepare("UPDATE header SET logo_path = ?, business_name = ?, tagline = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$logo_path, $business_name, $tagline, $header['id']])) {
            $_SESSION['success'] = 'Header berhasil diperbarui';
            header('Location: header.php');
            exit;
        }
    }
    
    header('Location: header.php');
    exit;
}

$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/header.css">
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
            
            <form method="POST" enctype="multipart/form-data" id="headerForm">
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
                
                <div class="form-row">
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
                
                <div class="form-actions">
                    <button type="submit" name="update_header" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/header.js"></script>
</body>
</html>