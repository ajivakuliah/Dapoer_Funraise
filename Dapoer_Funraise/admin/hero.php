<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get hero data
$hero = null;
$backgrounds = [];

try {
    // Get hero section - ambil data pertama saja
    $stmt = $pdo->query("SELECT * FROM hero_section ORDER BY id DESC LIMIT 1");
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hero) {
        // Create default
        $stmt = $pdo->prepare("INSERT INTO hero_section (welcome_text) VALUES ('Selamat Datang di Dapoer Funraise')");
        $stmt->execute();
        $hero = ['id' => $pdo->lastInsertId(), 'welcome_text' => 'Selamat Datang di Dapoer Funraise'];
    }
    
    // Get backgrounds ordered by sort_order
    $stmt = $pdo->query("SELECT * FROM hero_backgrounds ORDER BY sort_order ASC, id DESC");
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_text'])) {
        // Update welcome text
        $welcome_text = trim($_POST['welcome_text']);
        
        $stmt = $pdo->prepare("UPDATE hero_section SET welcome_text = ? WHERE id = ?");
        if ($stmt->execute([$welcome_text, $hero['id']])) {
            $_SESSION['success'] = 'Teks berhasil diperbarui';
            header('Location: hero.php');
            exit;
        }
        
    } elseif (isset($_POST['update_bg'])) {
        // Update background
        $bg_id = (int)$_POST['bg_id'];
        
        if (!empty($_FILES['new_image']['name'])) {
            $file = $_FILES['new_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                // Upload folder
                $upload_dir = 'uploads/hero/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Save file
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $bg_path = 'uploads/hero/' . $filename;
                    
                    // Update database
                    $stmt = $pdo->prepare("UPDATE hero_backgrounds SET background_path = ? WHERE id = ?");
                    if ($stmt->execute([$bg_path, $bg_id])) {
                        $_SESSION['success'] = 'Background berhasil diupdate';
                        header('Location: hero.php');
                        exit;
                    }
                }
            }
        } else {
            $_SESSION['error'] = 'Pilih gambar terlebih dahulu';
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        // Toggle background active status
        $bg_id = (int)$_POST['bg_id'];
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM hero_backgrounds WHERE id = ?");
        $stmt->execute([$bg_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE hero_backgrounds SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $bg_id])) {
                $status_text = $new_status ? 'diaktifkan' : 'dinonaktifkan';
                $_SESSION['success'] = "Background $status_text";
                header('Location: hero.php');
                exit;
            }
        }
        
    } elseif (isset($_POST['add_bg'])) {
        // Add new background
        if (!empty($_FILES['new_bg_image']['name'])) {
            $file = $_FILES['new_bg_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                // Upload folder
                $upload_dir = 'uploads/hero/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Save file
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $bg_path = 'admin/uploads/hero/' . $filename;
                    
                    // Get max sort_order and add 1
                    $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM hero_backgrounds");
                    $max = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_sort_order = ($max['max_order'] ?? 0) + 1;
                    
                    // Insert to database - aktif secara default
                    $hero_id = $hero['id'] ?? 1;
                    $stmt = $pdo->prepare("INSERT INTO hero_backgrounds (hero_section_id, background_path, sort_order, is_active) VALUES (?, ?, ?, 1)");
                    if ($stmt->execute([$hero_id, $bg_path, $new_sort_order])) {
                        $_SESSION['success'] = 'Background ditambahkan dan diaktifkan';
                        header('Location: hero.php');
                        exit;
                    }
                }
            }
        } else {
            $_SESSION['error'] = 'Pilih gambar terlebih dahulu';
        }
        
    } elseif (isset($_POST['update_order'])) {
        // Update sort order
        $bg_id = (int)$_POST['bg_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE hero_backgrounds SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $bg_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: hero.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_bg'])) {
        // Delete background
        $bg_id = (int)$_POST['bg_id'];
        
        // Get file path first
        $stmt = $pdo->prepare("SELECT background_path FROM hero_backgrounds WHERE id = ?");
        $stmt->execute([$bg_id]);
        $bg = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bg && !empty($bg['background_path'])) {
            $file_path = '../' . $bg['background_path'];
            if (file_exists($file_path) && strpos($bg['background_path'], 'uploads/hero/') !== false) {
                unlink($file_path);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM hero_backgrounds WHERE id = ?");
        if ($stmt->execute([$bg_id])) {
            $_SESSION['success'] = 'Background berhasil dihapus';
            header('Location: hero.php');
            exit;
        }
    }
    
    // Jika ada error, redirect dengan error message
    header('Location: hero.php');
    exit;
}

// Tampilkan pesan success/error jika ada
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus session messages setelah ditampilkan
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Hitung statistik
$active_count = count(array_filter($backgrounds, fn($bg) => $bg['is_active']));
$inactive_count = count($backgrounds) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Beranda - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 0px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #f8f6fd;
            border-radius: 15px;
            padding: 30px;
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
            margin-bottom: 20px;
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
            background: #f8f9fa;
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
        
        .btn-success {
            background: linear-gradient(135deg, #f9cc22, #b64b62);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #f9cc22, #b64b62);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(252, 199, 224, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(230, 126, 34, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(192, 57, 43, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(127, 140, 141, 0.3);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .btn-xs {
            padding: 6px 10px;
            font-size: 12px;
            min-width: 32px;
            height: 32px;
            justify-content: center;
        }
        
        .backgrounds-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .bg-card {
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
            background: white;
        }
        
        .bg-card.active {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
        }
        
        .bg-image {
            width: 100%;
            height: 130px;
            object-fit: cover;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .bg-info {
            padding: 12px;
        }
        
        .bg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .background-section {
            margin-top: 10px; /* Atur jarak dari atas */
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-badge {
            background: #e2e3e5;
            color: #383d41;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .bg-filename {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 12px;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .bg-actions {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-top: 10px;
        }
        
        .bg-actions form {
            margin: 0;
        }
        
        .upload-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px dashed #b64b62;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .file-input {
            width: 100%;
            padding: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            border: 1px solid #ced4da;
            border-radius: 6px;
        }
        
        .file-input:hover {
            background: #f8f9fa;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 450px;
            width: 100%;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        
        .close-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .close-btn:hover {
            background: #c0392b;
            transform: rotate(90deg);
        }
        
        .order-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .order-input {
            width: 50px;
            padding: 4px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
        }
        
        .badge-count {
            background: #f9cc22;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .info-text {
            color: #6c757d;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .help-text {
            color: #6c757d;
            display: block;
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        @media (max-width: 1200px) {
            .backgrounds-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .backgrounds-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .container {
                padding: 20px;
            }
        }
        
        @media (max-width: 600px) {
            .backgrounds-grid {
                grid-template-columns: 1fr;
            }
            
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-back {
                align-self: flex-start;
            }
            
            body {
                padding: 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-sliders-h"></i> Edit Beranda</h1>
            <a href="../pengaturan.php" class="btn-back">
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
        
        <!-- Edit Welcome Text -->
        <div class="section">
            <h2><i class="fas fa-font"></i> Teks Utama</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="welcome_text">Teks Selamat Datang:</label>
                    <input type="text" id="welcome_text" name="welcome_text" 
                        value="<?= htmlspecialchars($hero['welcome_text']) ?>" 
                        maxlength="200" required class="input-text"
                        placeholder="Masukkan teks welcome...">
                    <span class="help-text">
                        Maksimal 200 karakter. Teks ini akan ditampilkan di halaman depan.
                    </span>
                </div>
                
                <button type="submit" name="update_text" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <!-- Background Management -->
        <div class="section background-section">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> Background Images <span class="badge-count"><?= count($backgrounds) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Multiple background bisa aktif
                </span>
            </div>
            
            <!-- Add New Background -->
            <div class="upload-form">
                <h3><i class="fas fa-plus"></i> Tambah Background Baru</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <input type="file" name="new_bg_image" accept=".jpg,.jpeg,.png" required class="file-input">
                        <span class="help-text">
                            Format: JPG, PNG | Maksimal: 2MB | Ukuran disarankan: 1920x1080px
                        </span>
                    </div>
                    <button type="submit" name="add_bg" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload Background
                    </button>
                </form>
            </div>
            
            <!-- Existing Backgrounds -->
            <?php if (empty($backgrounds)): ?>
                <div class="empty-state">
                    <i class="fas fa-image-slash"></i>
                    <h3>Belum ada background</h3>
                    <p>Tambahkan background pertama Anda menggunakan form di atas</p>
                </div>
            <?php else: ?>
                <div class="backgrounds-grid">
                    <?php foreach ($backgrounds as $index => $bg): ?>
                        <div class="bg-card <?= $bg['is_active'] ? 'active' : '' ?>">
                            <?php
                            $bg_url = '../' . htmlspecialchars($bg['background_path']);
                            if (!file_exists($bg_url)) {
                                $bg_url = '../assets/bg.jpg';
                            }
                            ?>
                            <img src="<?= $bg_url ?>" alt="Background <?= $index + 1 ?>" class="bg-image">    
                            <div class="bg-info">
                                <div class="bg-header">
                                    <span class="status-badge <?= $bg['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $bg['is_active'] ? 'Aktif' : 'Non Aktif' ?>
                                    </span>
                                    <span class="order-badge">
                                        <i class="fas fa-sort-numeric-down"></i> <?= $bg['sort_order'] ?>
                                    </span>
                                </div>
                                
                                <div class="bg-filename">
                                    <i class="fas fa-file-image"></i> 
                                    <?= strlen(basename($bg['background_path'])) > 20 ? 
                                       substr(basename($bg['background_path']), 0, 20) . '...' : 
                                       basename($bg['background_path']) ?>
                                </div>
                                
                                <!-- Hanya 4 Tombol Aksi dalam 1 Baris -->
                                <div class="bg-actions">
                                    <!-- Tombol 1: Toggle Aktif/Nonaktif -->
                                    <form method="POST">
                                        <input type="hidden" name="bg_id" value="<?= $bg['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $bg['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $bg['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $bg['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Tombol 2: Edit -->
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $bg['id'] ?>)" title="Edit Gambar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Tombol 3: Update Urutan -->
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="bg_id" value="<?= $bg['id'] ?>">
                                        <div class="order-controls">
                                            <input type="number" name="sort_order" value="<?= $bg['sort_order'] ?>" 
                                                    min="0" max="100" required class="order-input" title="Nomor urut">
                                            <button type="submit" name="update_order" class="btn btn-xs btn-secondary" title="Update urutan">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Tombol 4: Hapus -->
                                    <form method="POST" onsubmit="return confirm('Hapus background ini?')" style="display: contents;">
                                        <input type="hidden" name="bg_id" value="<?= $bg['id'] ?>">
                                        <button type="submit" name="delete_bg" class="btn btn-xs btn-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <h3><i class="fas fa-edit"></i> Edit Background</h3>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="modalBgId" name="bg_id">
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Ganti Gambar:</label>
                    <input type="file" name="new_image" accept=".jpg,.jpeg,.png" class="file-input" required>
                    <span class="help-text">
                        Pilih gambar baru untuk mengganti gambar saat ini. Format: JPG, PNG | Maksimal: 2MB
                    </span>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="update_bg" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(bgId) {
            document.getElementById('modalBgId').value = bgId;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editForm').reset();
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Confirm before delete
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('button[name="delete_bg"]')) {
                    if (!confirm('Yakin ingin menghapus background ini?')) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // Validate file size on upload
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB');
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html>