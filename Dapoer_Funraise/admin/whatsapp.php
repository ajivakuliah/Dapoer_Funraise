<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$whatsapp_buttons = [];

try {
    $stmt = $pdo->query("SELECT * FROM whatsapp_buttons ORDER BY sort_order ASC, id DESC");
    $whatsapp_buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($whatsapp_buttons)) {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_buttons (button_text, whatsapp_number, is_active, sort_order) VALUES (?, ?, 1, 0)");
        $stmt->execute(['Pesan Sekarang', '6283129704643']);
        
        $stmt = $pdo->query("SELECT * FROM whatsapp_buttons ORDER BY sort_order ASC, id DESC");
        $whatsapp_buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_button'])) {
        $button_text = trim($_POST['button_text']);
        $whatsapp_number = trim($_POST['whatsapp_number']);
        
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM whatsapp_buttons");
        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $sort_order = ($max['max_order'] ?? 0) + 1;
        
        $stmt = $pdo->prepare("INSERT INTO whatsapp_buttons (button_text, whatsapp_number, sort_order, is_active) VALUES (?, ?, ?, 0)");
        if ($stmt->execute([$button_text, $whatsapp_number, $sort_order])) {
            $_SESSION['success'] = 'Tombol WhatsApp baru berhasil ditambahkan (status nonaktif)';
            header('Location: whatsapp.php');
            exit;
        }
        
    } elseif (isset($_POST['update_button'])) {
        $button_id = (int)$_POST['button_id'];
        $button_text = trim($_POST['button_text']);
        $whatsapp_number = trim($_POST['whatsapp_number']);
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET button_text = ?, whatsapp_number = ?, sort_order = ? WHERE id = ?");
        if ($stmt->execute([$button_text, $whatsapp_number, $sort_order, $button_id])) {
            $_SESSION['success'] = 'Tombol WhatsApp berhasil diperbarui';
            header('Location: whatsapp.php');
            exit;
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        $button_id = (int)$_POST['button_id'];
        
        $stmt = $pdo->prepare("SELECT is_active FROM whatsapp_buttons WHERE id = ?");
        $stmt->execute([$button_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            if (!$current['is_active']) {
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 0");
                $stmt->execute();
                
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 1 WHERE id = ?");
                if ($stmt->execute([$button_id])) {
                    $_SESSION['success'] = "Tombol WhatsApp diaktifkan (tombol lain otomatis dinonaktifkan)";
                    header('Location: whatsapp.php');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 0 WHERE id = ?");
                if ($stmt->execute([$button_id])) {
                    $_SESSION['success'] = "Tombol WhatsApp dinonaktifkan";
                    header('Location: whatsapp.php');
                    exit;
                }
            }
        }
        
    } elseif (isset($_POST['update_order'])) {
        $button_id = (int)$_POST['button_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $button_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: whatsapp.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_button'])) {
        $button_id = (int)$_POST['button_id'];
        
        $stmt = $pdo->prepare("SELECT is_active FROM whatsapp_buttons WHERE id = ?");
        $stmt->execute([$button_id]);
        $button = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($button && $button['is_active']) {
            $stmt = $pdo->prepare("DELETE FROM whatsapp_buttons WHERE id = ?");
            if ($stmt->execute([$button_id])) {
                $stmt = $pdo->query("SELECT id FROM whatsapp_buttons ORDER BY sort_order ASC, id ASC LIMIT 1");
                $first_button = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($first_button) {
                    $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$first_button['id']]);
                }
                
                $_SESSION['success'] = 'Tombol WhatsApp berhasil dihapus. Tombol lain otomatis diaktifkan.';
                header('Location: whatsapp.php');
                exit;
            }
        } else {
            $stmt = $pdo->prepare("DELETE FROM whatsapp_buttons WHERE id = ?");
            if ($stmt->execute([$button_id])) {
                $_SESSION['success'] = 'Tombol WhatsApp berhasil dihapus';
                header('Location: whatsapp.php');
                exit;
            }
        }
    }
    
    header('Location: whatsapp.php');
    exit;
}

$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

$active_count = count(array_filter($whatsapp_buttons, fn($button) => $button['is_active']));
$inactive_count = count($whatsapp_buttons) - $active_count;

$active_button = null;
foreach ($whatsapp_buttons as $button) {
    if ($button['is_active']) {
        $active_button = $button;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tombol WhatsApp - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/whatsapp.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fab fa-whatsapp"></i> Tombol WhatsApp</h1>
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
            <div class="section-header">
                <h2><i class="fas fa-comment-alt"></i> Daftar Tombol WhatsApp <span class="badge-count"><?= count($whatsapp_buttons) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> 
                    Aktif: <?= $active_count ?> | Nonaktif: <?= $inactive_count ?>
                    <?php if ($active_button): ?>
                        | <strong>Aktif saat ini:</strong> "<?= htmlspecialchars($active_button['button_text']) ?>"
                    <?php else: ?>
                        | <strong class="text-danger">Tidak ada tombol aktif!</strong>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="add-form">
                <h3><i class="fas fa-plus"></i> Tambah Tombol WhatsApp Baru</h3>
                <p class="help-text" style="margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    Tombol baru akan ditambahkan dalam status **NONAKTIF**. 
                    Untuk mengaktifkannya, klik tombol toggle pada card tombol.
                </p>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="button_text">Teks Tombol:</label>
                            <input type="text" id="button_text" name="button_text" 
                                value="Pesan Sekarang" required class="input-text"
                                placeholder="Contoh: Pesan Sekarang" maxlength="100">
                            <span class="help-text">
                                Teks yang akan ditampilkan pada tombol WhatsApp
                            </span>
                        </div>
                        
                        <div class="form-group">
                            <label for="whatsapp_number">Nomor WhatsApp:</label>
                            <input type="text" id="whatsapp_number" name="whatsapp_number" 
                                value="6283129704643" required class="input-text"
                                placeholder="Contoh: 6283129704643" maxlength="20">
                            <span class="help-text">
                                Nomor WhatsApp dengan kode negara (tanpa +)
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_button" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Tambah Tombol WhatsApp (Nonaktif)
                    </button>
                </form>
            </div>
            
            <?php if (empty($whatsapp_buttons)): ?>
                <div class="empty-state">
                    <i class="fab fa-whatsapp"></i>
                    <h3>Belum ada tombol WhatsApp</h3>
                    <p>Tambahkan tombol WhatsApp pertama Anda menggunakan form di atas</p>
                </div>
            <?php else: ?>
                <div class="buttons-grid">
                    <?php foreach ($whatsapp_buttons as $button): ?>
                        <div class="button-card <?= $button['is_active'] ? 'active' : '' ?>">
                            <div class="button-icon">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            
                            <div class="button-info">
                                <div class="button-header">
                                    <span class="status-badge <?= $button['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $button['is_active'] ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                    <span class="order-badge">
                                        <i class="fas fa-sort-numeric-down"></i> <?= $button['sort_order'] ?>
                                    </span>
                                </div>
                                
                                <div class="button-title">
                                    <?= htmlspecialchars($button['button_text']) ?>
                                </div>
                                
                                <div class="button-number">
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($button['whatsapp_number']) ?>
                                </div>
                                
                                <div class="button-actions">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="button_id" value="<?= $button['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $button['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $button['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                >
                                            <i class="fas <?= $button['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $button['id'] ?>, '<?= addslashes($button['button_text']) ?>', '<?= addslashes($button['whatsapp_number']) ?>', '<?= addslashes($button['sort_order']) ?>')" 
                                            title="Edit Tombol">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="button_id" value="<?= $button['id'] ?>">
                                        <div class="order-controls">
                                            <input type="number" name="sort_order" value="<?= $button['sort_order'] ?>" 
                                                    min="0" max="100" required class="order-input" title="Nomor urut">
                                            <button type="submit" name="update_order" class="btn btn-xs btn-secondary" title="Update urutan">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirmDelete(<?= $button['id'] ?>, <?= $button['is_active'] ? 'true' : 'false' ?>)" style="display: contents;">
                                        <input type="hidden" name="button_id" value="<?= $button['id'] ?>">
                                        <button type="submit" name="delete_button" class="btn btn-xs btn-danger" title="Hapus">
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
            <h3><i class="fas fa-edit"></i> Edit Tombol WhatsApp</h3>
            
            <form id="editForm" method="POST">
                <input type="hidden" id="modalButtonId" name="button_id">
                
                <div class="modal-row">
                    <div class="form-group">
                        <label for="modalButtonText">Teks Tombol:</label>
                        <input type="text" id="modalButtonText" name="button_text" 
                            maxlength="100" required class="input-text"
                            placeholder="Pesan Sekarang">
                    </div>
                    
                    <div class="form-group">
                        <label for="modalWhatsappNumber">Nomor WhatsApp:</label>
                        <input type="text" id="modalWhatsappNumber" name="whatsapp_number" 
                            maxlength="20" required class="input-text"
                            placeholder="6283129704643">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalSortOrder">Urutan Tampil:</label>
                    <input type="number" id="modalSortOrder" name="sort_order" 
                        min="0" max="100" required class="input-text">
                    <span class="help-text">
                        Urutan untuk sorting (lebih kecil = lebih prioritas)
                    </span>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="update_button" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/whatsapp.js"></script>
</body>
</html>