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
    // Jika terjadi error database pada saat ambil data, tampilkan error
    die("Error Database: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_footer'])) {
        try {
            // Update footer text
            $copyright_text = trim($_POST['copyright_text']);
            $main_text = trim($_POST['main_text']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE footer_section SET copyright_text = ?, main_text = ?, is_active = ? WHERE id = ?");
            if ($stmt->execute([$copyright_text, $main_text, $is_active, $footer['id']])) {
                $_SESSION['success'] = 'Footer berhasil diperbarui';
            } else {
                $_SESSION['error'] = 'Tidak ada perubahan pada data.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
    
    // Redirect untuk membersihkan POST request
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Kelola Footer - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/footer.css">
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
        
        <div class="section">
            <h2><i class="fas fa-edit"></i> Konten Footer</h2>
            
            <form method="POST" class="compact-form">
                <div class="form-group copyright-group">
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
                
                <div class="toggle-section">
                    <div class="toggle-left">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" id="is_active" class="checkbox-input" 
                                <?= $footer['is_active'] ? 'checked' : '' ?>>
                            <span>Tampilkan Footer di Website</span>
                        </label>
                    </div>
                    
                    <span class="status-indicator <?= $footer['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <i class="fas <?= $footer['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                        <?= $footer['is_active'] ? 'AKTIF' : 'NONAKTIF' ?>
                    </span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_footer" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan Footer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/admin/footer.js"></script>
</body>
</html>