<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

// Get footer data
$footer = null;

try {
    // Get footer section - ambil data pertama saja
    $stmt = $pdo->query("SELECT * FROM footer_section ORDER BY id DESC LIMIT 1");
    $footer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$footer) {
        // Create default
        $stmt = $pdo->prepare("INSERT INTO footer_section (main_text, copyright_text) VALUES (?, ?)");
        $stmt->execute([
            '© 2024 Dapoer Funraise. Hak Cipta Dilindungi.',
            'Terima kasih telah mengunjungi Dapoer Funraise. Kami berkomitmen untuk memberikan pelayanan terbaik kepada Anda.'
        ]);
        $footer = [
            'id' => $pdo->lastInsertId(), 
            'copyright_text' => '© 2024 Dapoer Funraise. Hak Cipta Dilindungi.',
            'main_text' => 'Terima kasih telah mengunjungi Dapoer Funraise. Kami berkomitmen untuk memberikan pelayanan terbaik kepada Anda.',
            'is_active' => 1
        ];
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_footer'])) {
        // Update footer text
        $copyright_text = trim($_POST['copyright_text']);
        $main_text = trim($_POST['main_text']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE footer_section SET copyright_text = ?, main_text = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([$copyright_text, $main_text, $is_active, $footer['id']])) {
            $_SESSION['success'] = 'Footer berhasil diperbarui';
            header('Location: footer.php');
            exit;
        }
    }
    
    // Jika ada error, redirect dengan error message
    header('Location: footer.php');
    exit;
}

// Tampilkan pesan success/error jika ada
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus session messages setelah ditampilkan
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer - Admin</title>
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
            background: #f8f9fa;
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
        
        .toggle-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e0e6ed;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .toggle-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
            font-size: 15px;
        }
        
        .checkbox-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .help-text {
            color: #6c757d;
            display: block;
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .form-actions {
            margin-top: 10px;
            display: flex;
            justify-content: center;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .toggle-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        
        @media (max-width: 576px) {
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
            <h1 class="page-title"><i class="fas fa-shoe-prints"></i> Pengaturan Footer</h1>
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
        
        <!-- Edit Footer Content -->
        <div class="section">
            <h2><i class="fas fa-edit"></i> Konten Footer</h2>
            
            <form method="POST">
                <!-- Teks Utama dan Hak Cipta -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="main_text">
                            <i class="fas fa-align-left"></i> Teks Utama:
                        </label>
                        <input type="text" id="main_text" name="main_text" 
                            value="<?= htmlspecialchars($footer['main_text']) ?>" 
                            maxlength="255" required class="input-text"
                            placeholder="Contoh: Terima kasih telah mengunjungi Dapoer Funraise.">
                        <span class="help-text">
                            Teks yang muncul di atas hak cipta
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label for="copyright_text">
                            <i class="fas fa-copyright"></i> Teks Hak Cipta:
                        </label>
                        <input type="text" id="copyright_text" name="copyright_text" 
                            value="<?= htmlspecialchars($footer['copyright_text']) ?>" 
                            maxlength="255" required class="input-text"
                            placeholder="Contoh: © 2024 Dapoer Funraise. Hak Cipta Dilindungi.">
                        <span class="help-text">
                            Teks yang muncul paling bawah
                        </span>
                    </div>
                </div>
                
                <!-- Toggle Status -->
                <div class="toggle-section">
                    <div class="toggle-left">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" class="checkbox-input" 
                                <?= $footer['is_active'] ? 'checked' : '' ?>>
                            <span>Tampilkan Footer di Website</span>
                        </label>
                    </div>
                    
                    <span class="status-indicator <?= $footer['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <i class="fas <?= $footer['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                        <?= $footer['is_active'] ? 'AKTIF' : 'NONAKTIF' ?>
                    </span>
                </div>
                
                <!-- Tombol Simpan -->
                <div class="form-actions">
                    <button type="submit" name="update_footer" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan Footer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Update status indicator when checkbox changes
        const checkbox = document.querySelector('input[name="is_active"]');
        const statusIndicator = document.querySelector('.status-indicator');
        
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                statusIndicator.className = 'status-indicator status-active';
                statusIndicator.innerHTML = '<i class="fas fa-toggle-on"></i> AKTIF';
            } else {
                statusIndicator.className = 'status-indicator status-inactive';
                statusIndicator.innerHTML = '<i class="fas fa-toggle-off"></i> NONAKTIF';
            }
        });
    </script>
</body>
</html>