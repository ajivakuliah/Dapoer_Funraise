<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$hero = null;
$backgrounds = [];

try {
    $stmt = $pdo->query("SELECT * FROM hero_section ORDER BY id DESC LIMIT 1");
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hero) {
        $stmt = $pdo->prepare("INSERT INTO hero_section (welcome_text) VALUES ('Selamat Datang di Dapoer Funraise')");
        $stmt->execute();
        $hero = ['id' => $pdo->lastInsertId(), 'welcome_text' => 'Selamat Datang di Dapoer Funraise'];
    }
    
    $stmt = $pdo->query("SELECT * FROM hero_backgrounds ORDER BY sort_order ASC, id DESC");
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_text'])) {
        $welcome_text = trim($_POST['welcome_text']);
        
        $stmt = $pdo->prepare("UPDATE hero_section SET welcome_text = ? WHERE id = ?");
        if ($stmt->execute([$welcome_text, $hero['id']])) {
            $_SESSION['success'] = 'Teks berhasil diperbarui';
            header('Location: hero.php');
            exit;
        }
        
    } elseif (isset($_POST['update_bg'])) {
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
                $upload_dir = '../uploads/hero/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $bg_path = 'uploads/hero/' . $filename;
                    
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
        $bg_id = (int)$_POST['bg_id'];
        
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
        if (!empty($_FILES['new_bg_image']['name'])) {
            $file = $_FILES['new_bg_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                $upload_dir = '../uploads/hero/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $bg_path = 'uploads/hero/' . $filename;
                    
                    $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM hero_backgrounds");
                    $max = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_sort_order = ($max['max_order'] ?? 0) + 1;
                    
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
        $bg_id = (int)$_POST['bg_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE hero_backgrounds SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $bg_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: hero.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_bg'])) {
        $bg_id = (int)$_POST['bg_id'];
        
        $stmt = $pdo->prepare("SELECT background_path FROM hero_backgrounds WHERE id = ?");
        $stmt->execute([$bg_id]);
        $bg = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bg && !empty($bg['background_path'])) {
            $file_path = '../' . $bg['background_path'];
            if (file_exists($file_path) && strpos($bg['background_path'], 'uploads/hero/') !== false) {
                unlink($file_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM hero_backgrounds WHERE id = ?");
        if ($stmt->execute([$bg_id])) {
            $_SESSION['success'] = 'Background berhasil dihapus';
            header('Location: hero.php');
            exit;
        }
    }
    
    header('Location: hero.php');
    exit;
}

$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

$active_count = count(array_filter($backgrounds, fn($bg) => $bg['is_active']));
$inactive_count = count($backgrounds) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Beranda - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/hero.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-sliders-h"></i> Edit Beranda</h1>
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
        
        <div class="section background-section">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> Background Images <span class="badge-count"><?= count($backgrounds) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Multiple background bisa aktif
                </span>
            </div>
            
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
                                
                                <div class="bg-actions">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="bg_id" value="<?= $bg['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $bg['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $bg['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $bg['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $bg['id'] ?>)" title="Edit Gambar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
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
    
    <script src="../js/admin/hero.js"></script>
</body>
</html>