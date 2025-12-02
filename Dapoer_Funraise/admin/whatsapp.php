<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get WhatsApp buttons data
$whatsapp_buttons = [];

try {
    // Get WhatsApp buttons ordered by sort_order
    $stmt = $pdo->query("SELECT * FROM whatsapp_buttons ORDER BY sort_order ASC, id DESC");
    $whatsapp_buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Jika belum ada data, buat default
    if (empty($whatsapp_buttons)) {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_buttons (button_text, whatsapp_number, is_active, sort_order) VALUES (?, ?, 1, 0)");
        $stmt->execute(['Pesan Sekarang', '6283129704643']);
        
        // Ambil data lagi
        $stmt = $pdo->query("SELECT * FROM whatsapp_buttons ORDER BY sort_order ASC, id DESC");
        $whatsapp_buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_button'])) {
        // Add new button - default tidak aktif (karena hanya satu yang aktif)
        $button_text = trim($_POST['button_text']);
        $whatsapp_number = trim($_POST['whatsapp_number']);
        
        // Get max sort_order and add 1
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM whatsapp_buttons");
        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $sort_order = ($max['max_order'] ?? 0) + 1;
        
        // Insert new button (TIDAK AKTIF secara default)
        $stmt = $pdo->prepare("INSERT INTO whatsapp_buttons (button_text, whatsapp_number, sort_order, is_active) VALUES (?, ?, ?, 0)");
        if ($stmt->execute([$button_text, $whatsapp_number, $sort_order])) {
            $_SESSION['success'] = 'Tombol WhatsApp baru berhasil ditambahkan (status nonaktif)';
            header('Location: whatsapp.php');
            exit;
        }
        
    } elseif (isset($_POST['update_button'])) {
        // Update button
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
        // Toggle button active status - HANYA SATU YANG AKTIF
        $button_id = (int)$_POST['button_id'];
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM whatsapp_buttons WHERE id = ?");
        $stmt->execute([$button_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            // Jika ingin mengaktifkan tombol ini
            if (!$current['is_active']) {
                // Nonaktifkan SEMUA tombol lainnya terlebih dahulu
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 0");
                $stmt->execute();
                
                // Aktifkan tombol yang dipilih
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 1 WHERE id = ?");
                if ($stmt->execute([$button_id])) {
                    $_SESSION['success'] = "Tombol WhatsApp diaktifkan (tombol lain otomatis dinonaktifkan)";
                    header('Location: whatsapp.php');
                    exit;
                }
            } else {
                // Jika ingin menonaktifkan tombol ini
                $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET is_active = 0 WHERE id = ?");
                if ($stmt->execute([$button_id])) {
                    $_SESSION['success'] = "Tombol WhatsApp dinonaktifkan";
                    header('Location: whatsapp.php');
                    exit;
                }
            }
        }
        
    } elseif (isset($_POST['update_order'])) {
        // Update sort order
        $button_id = (int)$_POST['button_id'];
        $sort_order = (int)$_POST['sort_order'];
        
        $stmt = $pdo->prepare("UPDATE whatsapp_buttons SET sort_order = ? WHERE id = ?");
        if ($stmt->execute([$sort_order, $button_id])) {
            $_SESSION['success'] = 'Urutan berhasil diupdate';
            header('Location: whatsapp.php');
            exit;
        }
        
    } elseif (isset($_POST['delete_button'])) {
        // Delete button
        $button_id = (int)$_POST['button_id'];
        
        // Cek apakah tombol yang akan dihapus adalah yang aktif
        $stmt = $pdo->prepare("SELECT is_active FROM whatsapp_buttons WHERE id = ?");
        $stmt->execute([$button_id]);
        $button = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($button && $button['is_active']) {
            // Jika yang aktif dihapus, aktifkan tombol pertama yang tersisa
            $stmt = $pdo->prepare("DELETE FROM whatsapp_buttons WHERE id = ?");
            if ($stmt->execute([$button_id])) {
                // Aktifkan tombol pertama setelah penghapusan
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
            // Jika bukan yang aktif, hapus saja
            $stmt = $pdo->prepare("DELETE FROM whatsapp_buttons WHERE id = ?");
            if ($stmt->execute([$button_id])) {
                $_SESSION['success'] = 'Tombol WhatsApp berhasil dihapus';
                header('Location: whatsapp.php');
                exit;
            }
        }
    }
    
    // Jika ada error, redirect dengan error message
    header('Location: whatsapp.php');
    exit;
}

// Display success/error messages if any
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages after displaying
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Count statistics
$active_count = count(array_filter($whatsapp_buttons, fn($button) => $button['is_active']));
$inactive_count = count($whatsapp_buttons) - $active_count;

// Find active button
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
        
        /* WhatsApp Buttons Grid Style - 4 cards per row */
        .buttons-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .button-card {
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
            background: white;
            height: 240px; /* Slightly taller for WhatsApp buttons */
            display: flex;
            flex-direction: column;
            margin: 0;
        }
        
        .button-card.active {
            border-color: #25D366;
            box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.2);
        }
        
        .button-card.active::before {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: #25D366;
            color: white;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            padding: 3px;
            letter-spacing: 0.5px;
            z-index: 5;
        }
        
        .button-icon {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dcf8c6, #ffffff);
            color: #25D366;
            font-size: 45px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .button-info {
            padding: 15px 12px 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .button-header {
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
        
        .button-title {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: 10px;
        }
        
        .button-number {
            font-size: 11px;
            color: #6c757d;
            text-align: center;
            margin-bottom: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .button-actions {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: auto;
        }
        
        .button-actions form {
            margin: 0;
        }
        
        .add-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px dashed #25D366;
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
        
        .modal .input-text {
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
        
        @media (max-width: 1200px) {
            .buttons-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 992px) {
            .buttons-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .buttons-grid {
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
            .buttons-grid {
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
        
        <!-- WhatsApp Buttons Management -->
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
            
            <!-- Add New Button -->
            <div class="add-form">
                <h3><i class="fas fa-plus"></i> Tambah Tombol WhatsApp Baru</h3>
                <p class="help-text" style="margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    Tombol baru akan ditambahkan dalam status <strong>NONAKTIF</strong>. 
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
            
            <!-- Existing Buttons - 4 cards per row -->
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
                                
                                <!-- Menampilkan teks tombol -->
                                <div class="button-title">
                                    <?= htmlspecialchars($button['button_text']) ?>
                                </div>
                                
                                <!-- Menampilkan nomor WhatsApp -->
                                <div class="button-number">
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($button['whatsapp_number']) ?>
                                </div>
                                
                                <!-- 4 Tombol Aksi dalam 1 Baris -->
                                <div class="button-actions">
                                    <!-- Tombol 1: Toggle Aktif/Nonaktif -->
                                    <form method="POST">
                                        <input type="hidden" name="button_id" value="<?= $button['id'] ?>">
                                        <button type="submit" name="toggle_active" 
                                                class="btn btn-xs <?= $button['is_active'] ? 'btn-success' : 'btn-secondary' ?>"
                                                title="<?= $button['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                onclick="return confirm('<?= $button['is_active'] ? 'Nonaktifkan tombol ini?' : 'Aktifkan tombol ini? (Tombol aktif lainnya akan otomatis dinonaktifkan)' ?>')">
                                            <i class="fas <?= $button['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Tombol 2: Edit -->
                                    <button type="button" class="btn btn-xs btn-warning" 
                                            onclick="openEditModal(<?= $button['id'] ?>, '<?= addslashes($button['button_text']) ?>', '<?= addslashes($button['whatsapp_number']) ?>', '<?= addslashes($button['sort_order']) ?>')" 
                                            title="Edit Tombol">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- Tombol 3: Update Urutan -->
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
                                    
                                    <!-- Tombol 4: Hapus -->
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
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <h3><i class="fas fa-edit"></i> Edit Tombol WhatsApp</h3>
            
            <form id="editForm" method="POST">
                <input type="hidden" id="modalButtonId" name="button_id">
                
                <!-- Baris 1: Teks Tombol dan Nomor -->
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
                
                <!-- Baris 2: Urutan -->
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
    
    <script>
        function openEditModal(id, buttonText, whatsappNumber, sortOrder) {
            document.getElementById('modalButtonId').value = id;
            document.getElementById('modalButtonText').value = buttonText;
            document.getElementById('modalWhatsappNumber').value = whatsappNumber;
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
        
        // Confirm delete with custom message
        function confirmDelete(buttonId, isActive) {
            if (isActive) {
                return confirm('PERINGATAN! Tombol ini sedang AKTIF di website.\n\nMenghapusnya akan otomatis mengaktifkan tombol lain.\n\nLanjutkan menghapus?');
            } else {
                return confirm('Yakin ingin menghapus tombol WhatsApp ini?');
            }
        }
        
        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Toggle button confirmation
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('button[name="toggle_active"]')) {
                const form = e.target;
                const button = form.querySelector('button[name="toggle_active"]');
                const isActive = button.classList.contains('btn-success');
                
                if (isActive) {
                    if (!confirm('Nonaktifkan tombol WhatsApp ini?')) {
                        e.preventDefault();
                    }
                } else {
                    if (!confirm('Aktifkan tombol WhatsApp ini?\n\nTombol WhatsApp lain yang aktif akan otomatis dinonaktifkan.')) {
                        e.preventDefault();
                    }
                }
            }
        });
    </script>
</body>
</html>