<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get cara pesan section data
$section = null;
$steps = [];

try {
    // Get section data - take first record
    $stmt = $pdo->query("SELECT * FROM cara_pesan_section ORDER BY id DESC LIMIT 1");
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        // Create default
        $stmt = $pdo->prepare("INSERT INTO cara_pesan_section (title, subtitle) VALUES ('Cara Pesan', 'Mudah dan cepat, hanya dalam 4 langkah')");
        $stmt->execute();
        $section = [
            'id' => $pdo->lastInsertId(), 
            'title' => 'Cara Pesan', 
            'subtitle' => 'Mudah dan cepat, hanya dalam 4 langkah'
        ];
    }
    
    // Get steps ordered by sort_order
    $stmt = $pdo->query("SELECT * FROM cara_pesan_steps ORDER BY sort_order ASC, step_number ASC");
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_section'])) {
        // Update section title and subtitle
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        
        $stmt = $pdo->prepare("UPDATE cara_pesan_section SET title = ?, subtitle = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $section['id']])) {
            $_SESSION['success'] = 'Judul dan subjudul berhasil diperbarui';
            header('Location: cara-pesan.php');
            exit;
        }
        
    } elseif (isset($_POST['update_step'])) {
        // Update step
        $step_id = (int)$_POST['step_id'];
        $step_number = (int)$_POST['step_number'];
        $title = trim($_POST['step_title']);
        $description = trim($_POST['description']);
        $icon_class = trim($_POST['icon_class']);
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE cara_pesan_steps SET step_number = ?, title = ?, description = ?, icon_class = ?, sort_order = ? WHERE id = ?");
        if ($stmt->execute([$step_number, $title, $description, $icon_class, $sort_order, $step_id])) {
            $_SESSION['success'] = 'Langkah berhasil diperbarui';
            header('Location: cara-pesan.php');
            exit;
        }
        
    } elseif (isset($_POST['add_step'])) {
        // Add new step
        $step_number = (int)$_POST['step_number'];
        $title = trim($_POST['step_title']);
        $description = trim($_POST['description']);
        $icon_class = trim($_POST['icon_class']);
        
        // Get max sort_order and add 1
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM cara_pesan_steps");
        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $sort_order = ($max['max_order'] ?? 0) + 1;
        
        // Insert new step (active by default)
        $stmt = $pdo->prepare("INSERT INTO cara_pesan_steps (step_number, title, description, icon_class, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$step_number, $title, $description, $icon_class, $sort_order])) {
            $_SESSION['success'] = 'Langkah baru berhasil ditambahkan';
            header('Location: cara-pesan.php');
            exit;
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        // Toggle step active status
        $step_id = (int)$_POST['step_id'];
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM cara_pesan_steps WHERE id = ?");
        $stmt->execute([$step_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE cara_pesan_steps SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $step_id])) {
                $status_text = $new_status ? 'diaktifkan' : 'dinonaktifkan';
                $_SESSION['success'] = "Langkah $status_text";
                header('Location: cara-pesan.php');
                exit;
            }
        }
        
    } elseif (isset($_POST['update_order'])) {
        // Update sort order
        $step_id = (int)$_POST['step_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE cara_pesan_steps SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $step_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: cara-pesan.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_step'])) {
        // Delete step
        $step_id = (int)$_POST['step_id'];
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM cara_pesan_steps WHERE id = ?");
        if ($stmt->execute([$step_id])) {
            $_SESSION['success'] = 'Langkah berhasil dihapus';
            header('Location: cara-pesan.php');
            exit;
        }
    }
    
    // If there's an error, redirect with error message
    header('Location: cara-pesan.php');
    exit;
}

// Display success/error messages if any
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages after displaying
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Count statistics
$active_count = count(array_filter($steps, fn($step) => $step['is_active']));
$inactive_count = count($steps) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Cara Pesan - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/cara-pesan.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-list-ol"></i> Kelola Cara Pesan</h1>
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
            <h2><i class="fas fa-heading"></i> Judul & Subjudul</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Judul:</label>
                        <input type="text" id="title" name="title" 
                            value="<?= htmlspecialchars($section['title']) ?>" 
                            maxlength="150" required class="input-text"
                            placeholder="Masukkan judul...">
                    </div>
                    
                    <div class="form-group">
                        <label for="subtitle">Subjudul:</label>
                        <input type="text" id="subtitle" name="subtitle" 
                            value="<?= htmlspecialchars($section['subtitle']) ?>" 
                            maxlength="255" required class="input-text"
                            placeholder="Masukkan subjudul...">
                    </div>
                </div>
                
                <button type="submit" name="update_section" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-steps"></i> Langkah-langkah <span class="badge-count"><?= count($steps) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Aktif: <?= $active_count ?> | Nonaktif: <?= $inactive_count ?>
                </span>
            </div>
            
            <div class="add-form">
                <h3><i class="fas fa-plus"></i> Tambah Langkah Baru</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="step_number">Nomor Langkah:</label>
                            <input type="number" id="step_number" name="step_number" 
                                min="1" max="20" required class="input-text"
                                placeholder="Contoh: 1">
                        </div>
                        
                        <div class="form-group">
                            <label for="step_title">Judul Langkah:</label>
                            <input type="text" id="step_title" name="step_title" 
                                maxlength="100" required class="input-text"
                                placeholder="Contoh: Pilih Menu">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="icon_class">Kelas Ikon FontAwesome:</label>
                            <input type="text" id="icon_class" name="icon_class" 
                                value="fa-cookie-bite" required class="input-text"
                                placeholder="Contoh: fa-cookie-bite">
                            <span class="help-text">
                                Gunakan kelas ikon FontAwesome (contoh: fa-cookie-bite, fa-shopping-cart)
                            </span>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Deskripsi:</label>
                            <textarea id="description" name="description" 
                                required class="textarea"
                                placeholder="Deskripsi langkah..." 
                                maxlength="500"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_step" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Tambah Langkah
                    </button>
                </form>
            </div>
            
            <?php if (empty($steps)): ?>
                <div class="empty-state">
                    <i class="fas fa-list-alt"></i>
                    <h3>Belum ada langkah</h3>
                    <p>Tambahkan langkah pertama Anda menggunakan form di atas</p>
                </div>
            <?php else: ?>
                <div class="backgrounds-grid">
                    <?php foreach ($steps as $step): ?>
                        <div class="bg-card <?= $step['is_active'] ? 'active' : '' ?>">
                            <div class="step-icon">
                                <i class="fa-solid <?= htmlspecialchars($step['icon_class']) ?>"></i>
                            </div>
                            
                            <div class="bg-info">
                                <div class="bg-header">
                                    <span class="status-badge <?= $step['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $step['is_active'] ? 'Aktif' : 'Non Aktif' ?>
                                    </span>
                                    <span class="order-badge">
                                        <i class="fas fa-sort-numeric-down"></i> <?= $step['sort_order'] ?>
                                    </span>
                                </div>
                                
                                <div class="step-title">
                                    <?= htmlspecialchars($step['title']) ?>
                                </div>
                                
                                <div class="bg-actions">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $step['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $step['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $step['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $step['id'] ?>, '<?= addslashes($step['step_number']) ?>', '<?= addslashes($step['title']) ?>', '<?= addslashes($step['description']) ?>', '<?= addslashes($step['icon_class']) ?>', '<?= addslashes($step['sort_order']) ?>')" 
                                            title="Edit Langkah">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                        <div class="order-controls">
                                            <input type="number" name="sort_order" value="<?= $step['sort_order'] ?>" 
                                                    min="0" max="100" required class="order-input" title="Nomor urut">
                                            <button type="submit" name="update_order" class="btn btn-xs btn-secondary" title="Update urutan">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('Hapus langkah ini?')" style="display: contents;">
                                        <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                        <button type="submit" name="delete_step" class="btn btn-xs btn-danger" title="Hapus">
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
            <h3><i class="fas fa-edit"></i> Edit Langkah</h3>
            
            <form id="editForm" method="POST">
                <input type="hidden" id="modalStepId" name="step_id">
                
                <div class="modal-row">
                    <div class="form-group">
                        <label for="modalStepNumber">Nomor Langkah:</label>
                        <input type="number" id="modalStepNumber" name="step_number" 
                            min="1" max="20" required class="input-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="modalStepTitle">Judul Langkah:</label>
                        <input type="text" id="modalStepTitle" name="step_title" 
                            maxlength="100" required class="input-text">
                    </div>
                </div>
                
                <div class="modal-row">
                    <div class="form-group">
                        <label for="modalIconClass">Kelas Ikon:</label>
                        <input type="text" id="modalIconClass" name="icon_class" 
                            required class="input-text" placeholder="fa-cookie-bite">
                    </div>
                    
                    <div class="form-group">
                        <label for="modalSortOrder">Urutan Tampil:</label>
                        <input type="number" id="modalSortOrder" name="sort_order" 
                            min="0" max="100" required class="input-text">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalDescription">Deskripsi:</label>
                    <textarea id="modalDescription" name="description" 
                        required class="textarea" maxlength="500" rows="4"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="update_step" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/cara-pesan.js"></script>
</body>
</html>