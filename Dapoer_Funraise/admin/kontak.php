<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

$section = null;
$cards = [];

try {
    $stmt = $pdo->query("SELECT * FROM kontak_section ORDER BY id DESC LIMIT 1");
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        $stmt = $pdo->prepare("INSERT INTO kontak_section (title, subtitle) VALUES ('Kontak', 'Siap melayani pesanan Anda dengan senang hati')");
        $stmt->execute();
        $section = [
            'id' => $pdo->lastInsertId(), 
            'title' => 'Kontak', 
            'subtitle' => 'Siap melayani pesanan Anda dengan senang hati'
        ];
    }
    
    $stmt = $pdo->query("SELECT * FROM contact_cards ORDER BY sort_order ASC");
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_section'])) {
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        
        $stmt = $pdo->prepare("UPDATE kontak_section SET title = ?, subtitle = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $section['id']])) {
            $_SESSION['success'] = 'Judul dan subjudul berhasil diperbarui';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['update_card'])) {
        $card_id = (int)$_POST['card_id'];
        $icon_class = trim($_POST['icon_class']);
        $title = trim($_POST['card_title']);
        $label = trim($_POST['label']);
        $href = trim($_POST['href']);
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE contact_cards SET icon_class = ?, title = ?, label = ?, href = ?, sort_order = ? WHERE id = ?");
        if ($stmt->execute([$icon_class, $title, $label, $href, $sort_order, $card_id])) {
            $_SESSION['success'] = 'Kontak berhasil diperbarui';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['add_card'])) {
        $icon_class = trim($_POST['icon_class']);
        $title = trim($_POST['card_title']);
        $label = trim($_POST['label']);
        $href = trim($_POST['href']);
        
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM contact_cards");
        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $sort_order = ($max['max_order'] ?? 0) + 1;
        
        $stmt = $pdo->prepare("INSERT INTO contact_cards (icon_class, title, label, href, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$icon_class, $title, $label, $href, $sort_order])) {
            $_SESSION['success'] = 'Kontak baru berhasil ditambahkan';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        $card_id = (int)$_POST['card_id'];
        
        $stmt = $pdo->prepare("SELECT is_active FROM contact_cards WHERE id = ?");
        $stmt->execute([$card_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE contact_cards SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $card_id])) {
                $status_text = $new_status ? 'diaktifkan' : 'dinonaktifkan';
                $_SESSION['success'] = "Kontak $status_text";
                header('Location: kontak.php');
                exit;
            }
        }
        
    } elseif (isset($_POST['update_order'])) {
        $card_id = (int)$_POST['card_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE contact_cards SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $card_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_card'])) {
        $card_id = (int)$_POST['card_id'];
        
        $stmt = $pdo->prepare("DELETE FROM contact_cards WHERE id = ?");
        if ($stmt->execute([$card_id])) {
            $_SESSION['success'] = 'Kontak berhasil dihapus';
            header('Location: kontak.php');
            exit;
        }
    }
    
    header('Location: kontak.php');
    exit;
}

$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

$active_count = count(array_filter($cards, fn($card) => $card['is_active']));
$inactive_count = count($cards) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kontak - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/kontak.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-address-book"></i> Kelola Kontak</h1>
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
                <h2><i class="fas fa-id-card"></i> Kartu Kontak <span class="badge-count"><?= count($cards) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Aktif: <?= $active_count ?> | Nonaktif: <?= $inactive_count ?>
                </span>
            </div>
            
            <div class="add-form">
                <h3><i class="fas fa-plus"></i> Tambah Kontak Baru</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="icon_class">Kelas Ikon FontAwesome:</label>
                            <input type="text" id="icon_class" name="icon_class" 
                                value="fa-phone" required class="input-text"
                                placeholder="Contoh: fa-phone">
                            <span class="help-text">
                                Gunakan kelas ikon FontAwesome (contoh: fa-phone, fa-envelope, fa-map-marker-alt)
                            </span>
                        </div>
                        
                        <div class="form-group">
                            <label for="card_title">Judul Kontak:</label>
                            <input type="text" id="card_title" name="card_title" 
                                maxlength="100" required class="input-text"
                                placeholder="Contoh: Telepon">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="label">Label/Teks:</label>
                            <input type="text" id="label" name="label" 
                                maxlength="100" required class="input-text"
                                placeholder="Contoh: +62 812-3456-7890">
                        </div>
                        
                        <div class="form-group">
                            <label for="href">Link/URL:</label>
                            <input type="text" id="href" name="href" 
                                maxlength="255" required class="input-text"
                                placeholder="Contoh: tel:+6281234567890">
                            <span class="help-text">
                                Untuk telepon: tel:+6281234567890 | Email: mailto:email@contoh.com | Lokasi: https://maps.google.com/0...
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_card" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Tambah Kontak
                    </button>
                </form>
            </div>
            
            <?php if (empty($cards)): ?>
                <div class="empty-state">
                    <i class="fas fa-address-book"></i>
                    <h3>Belum ada kontak</h3>
                    <p>Tambahkan kontak pertama Anda menggunakan form di atas</p>
                </div>
            <?php else: ?>
                <div class="backgrounds-grid">
                    <?php foreach ($cards as $card): ?>
                        <div class="bg-card <?= $card['is_active'] ? 'active' : '' ?>">
                            <div class="step-icon">
                                <i class="fa-solid <?= htmlspecialchars($card['icon_class']) ?>"></i>
                            </div>
                            
                            <div class="bg-info">
                                <div class="bg-header">
                                    <span class="status-badge <?= $card['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $card['is_active'] ? 'Aktif' : 'Non Aktif' ?>
                                    </span>
                                    <span class="order-badge">
                                        <i class="fas fa-sort-numeric-down"></i> <?= $card['sort_order'] ?>
                                    </span>
                                </div>
                                
                                <div class="step-title">
                                    <?= htmlspecialchars($card['title']) ?>
                                </div>
                                
                                <div class="step-label">
                                    <?= htmlspecialchars($card['label']) ?>
                                </div>
                                
                                <div class="bg-actions">
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $card['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $card['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $card['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $card['id'] ?>, '<?= addslashes($card['icon_class']) ?>', '<?= addslashes($card['title']) ?>', '<?= addslashes($card['label']) ?>', '<?= addslashes($card['href']) ?>', '<?= addslashes($card['sort_order']) ?>')" 
                                            title="Edit Kontak">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: contents;">
                                        <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                        <div class="order-controls">
                                            <input type="number" name="sort_order" value="<?= $card['sort_order'] ?>" 
                                                    min="0" max="100" required class="order-input" title="Nomor urut">
                                            <button type="submit" name="update_order" class="btn btn-xs btn-secondary" title="Update urutan">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <form method="POST" onsubmit="return confirm('Hapus kontak ini?')" style="display: contents;">
                                        <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                        <button type="submit" name="delete_card" class="btn btn-xs btn-danger" title="Hapus">
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
            <button class="close-btn" onclick="closeModal()">×</button>
            <h3><i class="fas fa-edit"></i> Edit Kontak</h3>
            
            <form id="editForm" method="POST">
                <input type="hidden" id="modalCardId" name="card_id">
                
                <div class="modal-row">
                    <div class="form-group">
                        <label for="modalIconClass">Kelas Ikon:</label>
                        <input type="text" id="modalIconClass" name="icon_class" 
                            required class="input-text" placeholder="fa-phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="modalCardTitle">Judul Kontak:</label>
                        <input type="text" id="modalCardTitle" name="card_title" 
                            maxlength="100" required class="input-text">
                    </div>
                </div>
                
                <div class="modal-row">
                    <div class="form-group">
                        <label for="modalLabel">Label/Teks:</label>
                        <input type="text" id="modalLabel" name="label" 
                            maxlength="100" required class="input-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="modalHref">Link/URL:</label>
                        <input type="text" id="modalHref" name="href" 
                            maxlength="255" required class="input-text">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalSortOrder">Urutan Tampil:</label>
                    <input type="number" id="modalSortOrder" name="sort_order" 
                        min="0" max="100" required class="input-text">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="update_card" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/kontak.js"></script>
</body>
</html>