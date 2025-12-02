<?php
session_start();
require_once '../config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $main_text = trim($_POST['main_text'] ?? '');
    $copyright_text = trim($_POST['copyright_text'] ?? '');

    if (empty($main_text) || empty($copyright_text)) {
        $message = ['type' => 'error', 'text' => 'Semua kolom wajib diisi.'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO footer_section (id, main_text, copyright_text, is_active)
            VALUES (1, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                main_text = VALUES(main_text),
                copyright_text = VALUES(copyright_text)
        ");
        $success = $stmt->execute([$main_text, $copyright_text]);

        if ($success) {
            $message = ['type' => 'success', 'text' => 'Footer berhasil diperbarui.'];
        } else {
            $message = ['type' => 'error', 'text' => 'Gagal menyimpan data.'];
        }
    }
}

// Ambil data saat ini
$stmt = $pdo->query("SELECT main_text, copyright_text FROM footer_section WHERE id = 1");
$current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'main_text' => 'Mendukung Expo Campus MAN 2 Samarinda',
    'copyright_text' => '© 2025 <strong>Dapoer Funraise</strong>'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Footer — Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5A46A2;
            --secondary: #B64B62;
            --accent: #F9CC22;
            --soft: #DFBEE0;
            --text-muted: #9180BB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f1e8fdff;
            color: #333;
            font-size: 15px;
        }

        .main-wrapper {
            display: flex;
            gap: 0;
            width: 100vw;
            margin: 0;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
        }

        .form-box {
            flex: 1;
            background: white;
            box-shadow: 0 5px 20px rgba(90, 70, 162, 0.1);
            overflow: hidden;
            border: 1px solid #f0eaff;
            border-radius: 0;
        }

        .preview-box {
            width: 380px;
            flex-shrink: 0;
            background: white;
            box-shadow: 0 5px 20px rgba(90, 70, 162, 0.1);
            overflow: hidden;
            border: 1px solid #f0eaff;
            border-radius: 0;
        }

        @media (max-width: 768px) {
            .preview-box {
                width: 100%;
                max-width: 100%;
            }
        }

        .form-header, .preview-header {
            background: #faf5ff;
            padding: 0.9rem 1.4rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 1px solid #f0eaff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-header { color: var(--primary); }
        .preview-header { color: var(--secondary); justify-content: center; }

        .form-body {
            padding: 1.5rem 1.4rem 1rem;
        }

        .input-pair {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 1.2rem;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .input-group label {
            font-weight: 600;
            font-size: 0.92rem;
            color: var(--primary);
            line-height: 1.3;
            margin: 0;
        }

        .input-group input {
            width: 100%;
            padding: 11px 15px;
            border: 2px solid #e8e6f2;
            border-radius: 10px;
            font-size: 0.93rem;
            background: #faf9ff;
            font-family: inherit;
            height: 46px;
            line-height: 1.4;
            margin: 0;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.1);
        }

        .help-text {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.4;
        }

        .help-text code {
            background: #f0eaff;
            padding: 1px 4px;
            border-radius: 4px;
        }

        .alert {
            background: #fff8f8;
            color: #c0392b;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 3px solid var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #66bb6a;
        }

        .action-bar {
            padding: 0.8rem 1.4rem 0.9rem;
            background: #fbf9ff;
            border-top: 1px solid #f3f0ff;
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s;
            font-family: inherit;
            min-height: 40px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #9e3e52);
            color: white;
            flex: 1;
            box-shadow: 0 2px 8px rgba(182, 75, 98, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(182, 75, 98, 0.25);
        }

        .btn-secondary, .btn-gray {
            background: linear-gradient(135deg, var(--soft), #c8a5d0);
            color: var(--primary);
            flex: 1;
        }

        .btn-secondary:hover, .btn-gray:hover {
            background: linear-gradient(135deg, #d0a8d5, #c095cb);
        }

        /* Preview Footer — tanpa card dalam card */
        .preview-body {
            padding: 1.5rem 1.4rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            background: #f8f6ff;
            height: 100%;
        }

        .footer-preview {
            text-align: center;
            max-width: 100%;
        }

        .preview-copyright {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .preview-main {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.1rem;
            margin-top: 8px;
            line-height: 1.4;
        }

        /* Helper untuk inline HTML seperti <strong> */
        .preview-copyright strong {
            color: var(--primary);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="form-box">
            <div class="form-header">
                <i class="fas fa-edit" style="color: var(--secondary);"></i>
                Edit Footer
            </div>

            <div class="form-body">
                <?php if ($message): ?>
                    <div class="alert <?= $message['type'] === 'success' ? 'alert-success' : '' ?>">
                        <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message['text']) ?>
                    </div>
                <?php endif; ?>

                <form id="footerForm" method="POST">
                    <div class="input-pair">
                        <div class="input-group">
                            <label for="copyright_text">Teks Hak Cipta</label>
                            <input 
                                type="text" 
                                id="copyright_text" 
                                name="copyright_text"
                                value="<?= htmlspecialchars($current['copyright_text'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="© 2025 &lt;strong&gt;Dapoer Funraise&lt;/strong&gt;"
                                required
                            >
                            <div class="help-text">
                                Gunakan <code>&lt;strong&gt;</code> untuk teks tebal.<br>
                                Contoh: <code>&lt;strong&gt;Dapoer Funraise&lt;/strong&gt;</code>
                            </div>
                        </div>
                    </div>

                    <div class="input-pair">
                        <div class="input-group">
                            <label for="main_text">Teks Utama</label>
                            <input 
                                type="text" 
                                id="main_text" 
                                name="main_text"
                                value="<?= htmlspecialchars($current['main_text']) ?>"
                                placeholder="Mendukung Expo Campus MAN 2 Samarinda"
                                required
                            >
                        </div>
                    </div>
                </form>
            </div>

            <div class="action-bar">
                <a href="pengaturan.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" form="footerForm" class="btn btn-primary"
                        onclick="return confirm('Simpan perubahan? Perubahan akan langsung tampil di website.')">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </div>

        <!-- Preview Section — tanpa nested card -->
        <div class="preview-box">
            <div class="preview-header">
                <i class="fas fa-eye"></i> Preview Footer
            </div>
            <div class="preview-body">
                <div class="footer-preview">
                    <div class="preview-copyright" id="previewCopyright">
                        <?= html_entity_decode($current['copyright_text']) ?>
                    </div>
                    <div class="preview-main" id="previewMain">
                        <?= htmlspecialchars($current['main_text']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live preview (HTML-safe parsing)
        function decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        }

        document.getElementById('copyright_text').addEventListener('input', function() {
            const raw = this.value;
            const decoded = decodeHtmlEntities(raw);
            // Biarkan HTML rendering di dalam preview (aman karena hanya <strong>, <em>, dll. sederhana)
            document.getElementById('previewCopyright').innerHTML = decoded || '—';
        });

        document.getElementById('main_text').addEventListener('input', function() {
            document.getElementById('previewMain').textContent = this.value || '—';
        });
    </script>
</body>
</html>