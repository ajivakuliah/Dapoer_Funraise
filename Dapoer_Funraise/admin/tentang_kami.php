<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get about data
$about = null;
$carousel_photos = [];

try {
    // Get about section - ambil data pertama saja
    $stmt = $pdo->query("SELECT * FROM tentang_kami_section ORDER BY id DESC LIMIT 1");
    $about = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$about) {
        // Create default
        $default_content = "Dapoer Funraise adalah sebuah inisiatif sosial yang berfokus pada peningkatan akses pendidikan melalui program-program kreatif. Berdiri sejak 2020, kami telah membantu ratusan anak mendapatkan pendidikan yang layak.\n\nMisi kami adalah menciptakan peluang pendidikan yang merata untuk semua anak, terlepas dari latar belakang ekonomi mereka. Melalui berbagai kegiatan fundraising dan program komunitas, kami berkomitmen untuk membuat perubahan yang berkelanjutan.";
        
        $stmt = $pdo->prepare("INSERT INTO tentang_kami_section (title, subtitle, content) VALUES (?, ?, ?)");
        $stmt->execute(['Tentang Kami', 'Dapur kecil, dampak besar untuk pendidikan', $default_content]);
        $about = [
            'id' => $pdo->lastInsertId(), 
            'title' => 'Tentang Kami',
            'subtitle' => 'Dapur kecil, dampak besar untuk pendidikan',
            'content' => $default_content
        ];
    }
    
    // Get carousel photos ordered by sort_order
    $stmt = $pdo->query("SELECT * FROM carousel_photos ORDER BY sort_order ASC, uploaded_at DESC");
    $carousel_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_about'])) {
        // Update about content
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $content = trim($_POST['content']);
        
        $stmt = $pdo->prepare("UPDATE tentang_kami_section SET title = ?, subtitle = ?, content = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $content, $about['id']])) {
            $_SESSION['success'] = 'Konten tentang kami berhasil diperbarui';
            header('Location: tentang_kami.php');
            exit;
        }
        
    } elseif (isset($_POST['update_photo'])) {
        // Update carousel photo
        $photo_id = (int)$_POST['photo_id'];
        
        // Prepare update data
        $update_data = [];
        
        if (!empty($_FILES['new_image']['name'])) {
            $file = $_FILES['new_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG/GIF yang diperbolehkan';
                header('Location: tentang_kami.php');
                exit;
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
                header('Location: tentang_kami.php');
                exit;
            } else {
                // Upload folder
                $upload_dir = '../uploads/carousel/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Save file
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'photo_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = 'uploads/carousel/' . $filename;
                    
                    // Get old image to delete
                    $stmt = $pdo->prepare("SELECT image_path FROM carousel_photos WHERE id = ?");
                    $stmt->execute([$photo_id]);
                    $old_photo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_photo && !empty($old_photo['image_path']) && strpos($old_photo['image_path'], 'uploads/') !== false) {
                        $old_file = '../' . $old_photo['image_path'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    $update_data['image_path'] = $image_path;
                }
            }
        }
        
        // Update alt_text and caption
        $update_data['alt_text'] = trim($_POST['alt_text'] ?? '');
        $update_data['caption'] = trim($_POST['caption'] ?? '');
        
        // Build update query
        $update_fields = [];
        $update_values = [];
        
        foreach ($update_data as $field => $value) {
            $update_fields[] = "$field = ?";
            $update_values[] = $value;
        }
        
        $update_values[] = $photo_id;
        
        if (!empty($update_fields)) {
            $sql = "UPDATE carousel_photos SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($update_values)) {
                $_SESSION['success'] = 'Foto berhasil diperbarui';
                header('Location: tentang_kami.php');
                exit;
            }
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        // Toggle photo active status
        $photo_id = (int)$_POST['photo_id'];
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM carousel_photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE carousel_photos SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $photo_id])) {
                $status_text = $new_status ? 'diaktifkan' : 'dinonaktifkan';
                $_SESSION['success'] = "Foto $status_text";
                header('Location: tentang_kami.php');
                exit;
            }
        }
        
    } elseif (isset($_POST['add_photo'])) {
        // Add new carousel photo
        if (!empty($_FILES['new_photo_image']['name'])) {
            $file = $_FILES['new_photo_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG/GIF yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                // Upload folder
                $upload_dir = '../uploads/carousel/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Save file
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'photo_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = 'uploads/carousel/' . $filename;
                    
                    // Get max sort_order and add 1
                    $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM carousel_photos");
                    $max = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_sort_order = ($max['max_order'] ?? 0) + 1;
                    
                    // Insert to database - aktif secara default
                    $alt_text = trim($_POST['new_alt_text'] ?? '');
                    $caption = trim($_POST['new_caption'] ?? '');
                    
                    $stmt = $pdo->prepare("INSERT INTO carousel_photos (image_path, alt_text, caption, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
                    if ($stmt->execute([$image_path, $alt_text, $caption, $new_sort_order])) {
                        $_SESSION['success'] = 'Foto berhasil ditambahkan dan diaktifkan';
                        header('Location: tentang_kami.php');
                        exit;
                    }
                }
            }
        } else {
            $_SESSION['error'] = 'Pilih foto terlebih dahulu';
        }
        
    } elseif (isset($_POST['update_order'])) {
        // Update sort order
        $photo_id = (int)$_POST['photo_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE carousel_photos SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $photo_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: tentang_kami.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_photo'])) {
        // Delete photo
        $photo_id = (int)$_POST['photo_id'];
        
        // Get file path first
        $stmt = $pdo->prepare("SELECT image_path FROM carousel_photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($photo && !empty($photo['image_path'])) {
            $file_path = '../' . $photo['image_path'];
            if (file_exists($file_path) && strpos($photo['image_path'], 'uploads/') !== false) {
                unlink($file_path);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM carousel_photos WHERE id = ?");
        if ($stmt->execute([$photo_id])) {
            $_SESSION['success'] = 'Foto berhasil dihapus';
            header('Location: tentang_kami.php');
            exit;
        }
    }
    
    // Jika ada error, redirect dengan error message
    header('Location: tentang_kami.php');
    exit;
}

// Tampilkan pesan success/error jika ada
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus session messages setelah ditampilkan
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Hitung statistik
$active_count = count(array_filter($carousel_photos, fn($photo) => $photo['is_active']));
$inactive_count = count($carousel_photos) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tentang Kami - Admin</title>
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
        
        .textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fa;
            min-height: 200px;
            resize: vertical;
            font-family: inherit;
        }
        
        .textarea:focus {
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
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .photo-card {
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
            background: white;
        }
        
        .photo-card.active {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
        }
        
        .photo-image {
            width: 100%;
            height: 130px;
            object-fit: cover;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .photo-info {
            padding: 12px;
        }
        
        .photo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .carousel-section {
            margin-top: 10px;
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
        
        .photo-filename {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 8px;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .photo-caption {
            font-size: 12px;
            color: #495057;
            margin-bottom: 12px;
            line-height: 1.4;
            max-height: 36px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .photo-actions {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-top: 10px;
        }
        
        .photo-actions form {
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
            max-width: 500px;
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
        
        .input-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        @media (max-width: 1200px) {
            .photos-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .photos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .container {
                padding: 20px;
            }
            
            .input-group {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 600px) {
            .photos-grid {
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
            <h1 class="page-title"><i class="fas fa-info-circle"></i> Edit Tentang Kami</h1>
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
        
        <!-- Edit About Content -->
        <div class="section">
            <h2><i class="fas fa-file-alt"></i> Konten Tentang Kami</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="title">Judul:</label>
                    <input type="text" id="title" name="title" 
                        value="<?= htmlspecialchars($about['title']) ?>" 
                        maxlength="150" required class="input-text"
                        placeholder="Masukkan judul...">
                </div>
                
                <div class="form-group">
                    <label for="subtitle">Subjudul:</label>
                    <input type="text" id="subtitle" name="subtitle" 
                        value="<?= htmlspecialchars($about['subtitle']) ?>" 
                        maxlength="255" required class="input-text"
                        placeholder="Masukkan subjudul...">
                </div>
                
                <div class="form-group">
                    <label for="content">Konten:</label>
                    <textarea id="content" name="content" 
                        rows="8" required class="textarea"
                        placeholder="Masukkan konten tentang kami..."><?= htmlspecialchars($about['content']) ?></textarea>
                    <span class="help-text">
                        Gunakan baris baru (enter) untuk membuat paragraf baru.
                    </span>
                </div>
                
                <button type="submit" name="update_about" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <!-- Carousel Photos Management -->
        <div class="section carousel-section">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> Galeri Foto <span class="badge-count"><?= count($carousel_photos) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Foto akan ditampilkan di carousel halaman tentang kami
                </span>
            </div>
            
            <!-- Add New Photo -->
            <div class="upload-form">
                <h3><i class="fas fa-plus"></i> Tambah Foto Baru</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="input-group">
                        <div>
                            <label for="new_alt_text">Teks Alternatif:</label>
                            <input type="text" id="new_alt_text" name="new_alt_text" 
                                maxlength="150" class="input-text"
                                placeholder="Deskripsi singkat foto...">
                        </div>
                        <div>
                            <label for="new_caption">Keterangan:</label>
                            <input type="text" id="new_caption" name="new_caption" 
                                maxlength="300" class="input-text"
                                placeholder="Keterangan foto...">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <input type="file" name="new_photo_image" accept=".jpg,.jpeg,.png,.gif" required class="file-input">
                        <span class="help-text">
                            Format: JPG, PNG, GIF | Maksimal: 2MB | Ukuran disarankan: 800x600px
                        </span>
                    </div>
                    <button type="submit" name="add_photo" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload Foto
                    </button>
                </form>
            </div>
            
            <!-- Existing Photos -->
            <?php if (empty($carousel_photos)): ?>
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <h3>Belum ada foto</h3>
                    <p>Tambahkan foto pertama Anda menggunakan form di atas</p>
                </div>
            <?php else: ?>
                <div class="photos-grid">
                    <?php foreach ($carousel_photos as $index => $photo): ?>
                        <div class="photo-card <?= $photo['is_active'] ? 'active' : '' ?>">
                            <?php
                            $photo_url = '../' . htmlspecialchars($photo['image_path']);
                            if (!file_exists($photo_url)) {
                                $photo_url = '../assets/default-photo.jpg';
                            }
                            ?>
                            <img src="<?= $photo_url ?>" alt="<?= htmlspecialchars($photo['alt_text'] ?? '') ?>" class="photo-image">
                            
                            <div class="photo-info">
                                <div class="photo-header">
                                    <span class="status-badge <?= $photo['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $photo['is_active'] ? 'Aktif' : 'Non Aktif' ?>
                                    </span>
                                    <span class="order-badge">
                                        <i class="fas fa-sort-numeric-down"></i> <?= $photo['sort_order'] ?>
                                    </span>
                                </div>
                                
                                <div class="photo-filename">
                                    <i class="fas fa-file-image"></i> 
                                    <?= strlen(basename($photo['image_path'])) > 20 ? 
                                       substr(basename($photo['image_path']), 0, 20) . '...' : 
                                       basename($photo['image_path']) ?>
                                </div>
                                
                                <?php if (!empty($photo['caption'])): ?>
                                    <div class="photo-caption">
                                        <?= htmlspecialchars($photo['caption']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 4 Tombol Aksi dalam 1 Baris -->
                                <div class="photo-actions">
                                    <!-- Tombol 1: Toggle Aktif/Nonaktif -->
                                    <form method="POST">
                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $photo['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $photo['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $photo['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Tombol 2: Edit -->
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $photo['id'] ?>, '<?= htmlspecialchars(addslashes($photo['alt_text'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($photo['caption'] ?? '')) ?>')" 
                                            title="Edit Foto">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Tombol 3: Update Urutan -->
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                        <div class="order-controls">
                                            <input type="number" name="sort_order" value="<?= $photo['sort_order'] ?>" 
                                                    min="0" max="100" required class="order-input" title="Nomor urut">
                                            <button type="submit" name="update_order" class="btn btn-xs btn-secondary" title="Update urutan">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Tombol 4: Hapus -->
                                    <form method="POST" onsubmit="return confirm('Hapus foto ini?')" style="display: contents;">
                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                        <button type="submit" name="delete_photo" class="btn btn-xs btn-danger" title="Hapus">
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
            <h3><i class="fas fa-edit"></i> Edit Foto</h3>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="modalPhotoId" name="photo_id">
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Ganti Foto:</label>
                    <input type="file" name="new_image" accept=".jpg,.jpeg,.png,.gif" class="file-input">
                    <span class="help-text">
                        Kosongkan jika tidak ingin mengganti foto. Format: JPG, PNG, GIF | Maksimal: 2MB
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="modalAltText">Teks Alternatif:</label>
                    <input type="text" id="modalAltText" name="alt_text" 
                        maxlength="150" class="input-text"
                        placeholder="Deskripsi singkat foto...">
                </div>
                
                <div class="form-group">
                    <label for="modalCaption">Keterangan:</label>
                    <input type="text" id="modalCaption" name="caption" 
                        maxlength="300" class="input-text"
                        placeholder="Keterangan foto...">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="update_photo" class="btn btn-primary">
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
        function openEditModal(photoId, altText, caption) {
            document.getElementById('modalPhotoId').value = photoId;
            document.getElementById('modalAltText').value = altText || '';
            document.getElementById('modalCaption').value = caption || '';
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
                if (this.querySelector('button[name="delete_photo"]')) {
                    if (!confirm('Yakin ingin menghapus foto ini?')) {
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
        
        // Character counters
        document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(input => {
            const max = parseInt(input.getAttribute('maxlength'));
            const helpText = input.parentNode.querySelector('.help-text');
            
            if (helpText) {
                const counter = document.createElement('span');
                counter.className = 'char-counter';
                counter.style.float = 'right';
                counter.style.fontSize = '12px';
                counter.style.color = '#6c757d';
                
                function updateCounter() {
                    const current = input.value.length;
                    counter.textContent = `${current}/${max}`;
                    
                    if (current > max * 0.9) {
                        counter.style.color = '#dc3545';
                    } else if (current > max * 0.7) {
                        counter.style.color = '#ffc107';
                    } else {
                        counter.style.color = '#6c757d';
                    }
                }
                
                updateCounter();
                input.addEventListener('input', updateCounter);
                
                // Insert counter after help text
                helpText.parentNode.insertBefore(counter, helpText.nextSibling);
            }
        });
    </script>
</body>
</html>