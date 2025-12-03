<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get kontak section data
$section = null;
$cards = [];

try {
    // Get section data - take first record
    $stmt = $pdo->query("SELECT * FROM kontak_section ORDER BY id DESC LIMIT 1");
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        // Create default
        $stmt = $pdo->prepare("INSERT INTO kontak_section (title, subtitle) VALUES ('Kontak', 'Siap melayani pesanan Anda dengan senang hati')");
        $stmt->execute();
        $section = [
            'id' => $pdo->lastInsertId(), 
            'title' => 'Kontak', 
            'subtitle' => 'Siap melayani pesanan Anda dengan senang hati'
        ];
    }
    
    // Get cards ordered by sort_order
    $stmt = $pdo->query("SELECT * FROM contact_cards ORDER BY sort_order ASC");
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_section'])) {
        // Update section title and subtitle
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        
        $stmt = $pdo->prepare("UPDATE kontak_section SET title = ?, subtitle = ? WHERE id = ?");
        if ($stmt->execute([$title, $subtitle, $section['id']])) {
            $_SESSION['success'] = 'Judul dan subjudul berhasil diperbarui';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['update_card'])) {
        // Update card
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
        // Add new card
        $icon_class = trim($_POST['icon_class']);
        $title = trim($_POST['card_title']);
        $label = trim($_POST['label']);
        $href = trim($_POST['href']);
        
        // Get max sort_order and add 1
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM contact_cards");
        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $sort_order = ($max['max_order'] ?? 0) + 1;
        
        // Insert new card (active by default)
        $stmt = $pdo->prepare("INSERT INTO contact_cards (icon_class, title, label, href, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$icon_class, $title, $label, $href, $sort_order])) {
            $_SESSION['success'] = 'Kontak baru berhasil ditambahkan';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['toggle_active'])) {
        // Toggle card active status
        $card_id = (int)$_POST['card_id'];
        
        // Get current status
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
        // Update sort order
        $card_id = (int)$_POST['card_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE contact_cards SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $card_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: kontak.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_card'])) {
        // Delete card
        $card_id = (int)$_POST['card_id'];
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM contact_cards WHERE id = ?");
        if ($stmt->execute([$card_id])) {
            $_SESSION['success'] = 'Kontak berhasil dihapus';
            header('Location: kontak.php');
            exit;
        }
    }
    
    // If there's an error, redirect with error message
    header('Location: kontak.php');
    exit;
}

// Display success/error messages if any
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages after displaying
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Count statistics
$active_count = count(array_filter($cards, fn($card) => $card['is_active']));
$inactive_count = count($cards) - $active_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak - Admin</title>
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
        
        .input-text, .textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .input-text:focus, .textarea:focus {
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
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60, #1e8449);
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
        
        /* Backgrounds Grid Style from hero.php - 4 cards per row */
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
            height: 210px; /* Compact height for 4 cards per row */
            display: flex;
            flex-direction: column;
            margin: 0;
        }
        
        .bg-card.active {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
        }
        
        .step-icon {
            width: 100%;
            height: 90px; /* Slightly smaller icon area */
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0e6ff, #e6f7ff);
            color: #5a46a2;
            font-size: 45px; /* Smaller icon */
            border-bottom: 1px solid #e0e6ed;
        }
        
        .bg-info {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .bg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .status-badge {
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 9px;
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
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .step-title {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: 10px;
        }
        
        .step-label {
            font-size: 11px;
            color: #6c757d;
            text-align: center;
            margin-bottom: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .bg-actions {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: auto;
        }
        
        .bg-actions form {
            margin: 0;
        }
        
        .add-form {
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
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
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
            padding: 20px;
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
        
        .modal .form-group {
            margin-bottom: 12px;
        }
        
        .modal label {
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .modal .input-text, .modal .textarea {
            padding: 10px 12px;
            font-size: 14px;
        }
        
        .modal-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .modal-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        .header-container {
            position: relative;
            padding-right: 80px; /* ruang agar judul tidak ketabrak tombol */
        }
        .btn-back {
            position: absolute;
            right: 0;
            top: 0;
            z-index: 10;
        }
        
        @media (max-width: 1200px) {
            .backgrounds-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 992px) {
            .backgrounds-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .backgrounds-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .container {
                padding: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
        }
        
        @media (max-width: 576px) {
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
                padding: 8px 14px;
                font-size: 12px
            }
            .page-title {
                font-size: 18px;
            }
            
            .modal-row {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-address-book"></i> Kontak</h1>
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
        
        <!-- Edit Section Title -->
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
        
        <!-- Contact Cards Management -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-id-card"></i> Kartu Kontak <span class="badge-count"><?= count($cards) ?></span></h2>
                <span class="info-text">
                    <i class="fas fa-info-circle"></i> Aktif: <?= $active_count ?> | Nonaktif: <?= $inactive_count ?>
                </span>
            </div>
            
            <!-- Add New Card -->
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
                                Untuk telepon: tel:+6281234567890 
                                | Email: mailto:email@contoh.com 
                                | Lokasi: https://maps.google.com/...
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_card" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Tambah Kontak
                    </button>
            </div>
            
            <!-- Existing Cards - 4 cards per row -->
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
                                
                                <!-- Menampilkan judul dan label -->
                                <div class="step-title">
                                    <?= htmlspecialchars($card['title']) ?>
                                </div>
                                
                                <div class="step-label">
                                    <?= htmlspecialchars($card['label']) ?>
                                </div>
                                
                                <!-- 4 Tombol Aksi dalam 1 Baris -->
                                <div class="bg-actions">
                                    <!-- Tombol 1: Toggle Aktif/Nonaktif -->
                                    <form method="POST">
                                        <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $card['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $card['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $card['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Tombol 2: Edit -->
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $card['id'] ?>, '<?= addslashes($card['icon_class']) ?>', '<?= addslashes($card['title']) ?>', '<?= addslashes($card['label']) ?>', '<?= addslashes($card['href']) ?>', '<?= addslashes($card['sort_order']) ?>')" 
                                            title="Edit Kontak">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Tombol 3: Update Urutan -->
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
                                    
                                    <!-- Tombol 4: Hapus -->
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
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <h3><i class="fas fa-edit"></i> Edit Kontak</h3>
            
            <form id="editForm" method="POST">
                <input type="hidden" id="modalCardId" name="card_id">
                
                <!-- Baris 1: Ikon dan Judul -->
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
                
                <!-- Baris 2: Label dan Link -->
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
                
                <!-- Baris 3: Urutan -->
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
    
    <script>
        function openEditModal(id, iconClass, title, label, href, sortOrder) {
            document.getElementById('modalCardId').value = id;
            document.getElementById('modalIconClass').value = iconClass;
            document.getElementById('modalCardTitle').value = title;
            document.getElementById('modalLabel').value = label;
            document.getElementById('modalHref').value = href;
            document.getElementById('modalSortOrder').value = sortOrder;
            
            // Show modal
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
                if (this.querySelector('button[name="delete_card"]')) {
                    if (!confirm('Yakin ingin menghapus kontak ini?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>