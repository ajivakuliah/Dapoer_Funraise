<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$about = null;
$carousel_photos = [];

try {
    $stmt = $pdo->query("SELECT * FROM tentang_kami_section ORDER BY id DESC LIMIT 1");
    $about = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$about) {
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
    
    $stmt = $pdo->query("SELECT * FROM carousel_photos ORDER BY sort_order ASC, uploaded_at DESC");
    $carousel_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_about'])) {
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $content = trim($_POST['content']);
        
        $stmt = $pdo->prepare("UPDATE tentang_kami_section SET title = ?, subtitle = ?, content = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $content, $about['id']])) {
            $_SESSION['success'] = 'Konten berhasil diperbarui';
            header('Location: tentang_kami.php');
            exit;
        }
        
    } elseif (isset($_POST['update_photo'])) {
        $photo_id = (int)$_POST['photo_id'];
        $update_data = [];
        
        if (!empty($_FILES['new_image']['name'])) {
            $file = $_FILES['new_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG/GIF yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                $upload_dir = '../uploads/carousel/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'photo_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = 'uploads/carousel/' . $filename;
                    
                    $stmt = $pdo->prepare("SELECT image_path FROM carousel_photos WHERE id = ?");
                    $stmt->execute([$photo_id]);
                    $old_photo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_photo && !empty($old_photo['image_path']) && strpos($old_photo['image_path'], 'uploads/carousel/') !== false) {
                        $old_file = '../' . $old_photo['image_path'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    $update_data['image_path'] = $image_path;
                }
            }
        }
        
        $update_data['alt_text'] = trim($_POST['alt_text'] ?? '');
        $update_data['caption'] = trim($_POST['caption'] ?? '');
        
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
        $photo_id = (int)$_POST['photo_id'];
        
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
        if (!empty($_FILES['new_photo_image']['name'])) {
            $file = $_FILES['new_photo_image'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Hanya JPG/PNG/GIF yang diperbolehkan';
            } elseif ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Maksimal 2MB';
            } else {
                $upload_dir = '../uploads/carousel/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'photo_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = 'uploads/carousel/' . $filename;
                    
                    $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM carousel_photos");
                    $max = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_sort_order = ($max['max_order'] ?? 0) + 1;
                    
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
        $photo_id = (int)$_POST['photo_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE carousel_photos SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $photo_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: tentang_kami.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_photo'])) {
        $photo_id = (int)$_POST['photo_id'];
        
        $stmt = $pdo->prepare("SELECT image_path FROM carousel_photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($photo && !empty($photo['image_path'])) {
            $file_path = '../' . $photo['image_path'];
            if (file_exists($file_path) && strpos($photo['image_path'], 'uploads/carousel/') !== false) {
                unlink($file_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM carousel_photos WHERE id = ?");
        if ($stmt->execute([$photo_id])) {
            $_SESSION['success'] = 'Foto berhasil dihapus';
            header('Location: tentang_kami.php');
            exit;
        }
    }
    
    header('Location: tentang_kami.php');
    exit;
}

$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

$active_count = count(array_filter($carousel_photos, fn($photo) => $photo['is_active']));
$inactive_count = count($carousel_photos) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Konten - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/tentang_kami.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-info-circle"></i> Edit Konten</h1>
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
            <h2><i class="fas fa-file-alt"></i> Konten</h2>
            <form method="POST" id="tentang-kami-form">
                <div class="input-group">
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
                </div>
                
                <div class="form-group">
                    <label for="content">Konten:</label>
                    <textarea id="content" name="content" 
                        rows="8" required class="textarea"
                        placeholder="Masukkan konten..."><?= htmlspecialchars($about['content']) ?></textarea>
                </div>
                
                <button type="submit" name="update_about" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <div class="section carousel-section">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> Galeri Foto <span class="badge-count"><?= count($carousel_photos) ?></span></h2>
            </div>
            
            <div class="upload-form">
                <h3><i class="fas fa-plus"></i> Tambah Foto Baru</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="input-group">
                        <div class="form-group">
                            <label for="new_alt_text">Teks:</label>
                            <input type="text" id="new_alt_text" name="new_alt_text" 
                                maxlength="150" class="input-text"
                                placeholder="Deskripsi singkat foto...">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_caption">Keterangan:</label>
                            <input type="text" id="new_caption" name="new_caption" 
                                maxlength="300" class="input-text"
                                placeholder="Keterangan foto...">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_photo_image">Foto:</label>
                            <input type="file" name="new_photo_image" accept=".jpg,.jpeg,.png,.gif" required class="file-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <span class="help-text">
                            <i class="fas fa-info-circle"></i> Format: JPG, PNG, GIF | Maksimal: 2MB | Ukuran disarankan: 800x600px
                        </span>
                    </div>
                    
                    <button type="submit" name="add_photo" class="btn btn-success">
                        <i class="fas fa-upload"></i> Unggah Foto
                    </button>
                </form>
            </div>
            
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
                                
                                <div class="photo-actions">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $photo['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $photo['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $photo['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $photo['id'] ?>, '<?= htmlspecialchars(addslashes($photo['alt_text'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($photo['caption'] ?? '')) ?>')" 
                                            title="Edit Foto">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
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
                    <label for="modalAltText">Teks :</label>
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
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/tentang_kami.js"></script>
</body>
</html>