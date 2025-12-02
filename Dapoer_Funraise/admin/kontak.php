<?php
session_start();
require_once '../config.php';

// === DATA SECTION ===
$stmtSec = $pdo->prepare("SELECT title, subtitle FROM kontak_section WHERE id = 1");
$stmtSec->execute();
$kontak_section = $stmtSec->fetch(PDO::FETCH_ASSOC);
if (!$kontak_section) {
    $pdo->exec("INSERT INTO kontak_section (title, subtitle) VALUES ('Hubungi Kami', 'Siap melayani pesanan Anda dengan senang hati')");
    $kontak_section = ['title' => 'Hubungi Kami', 'subtitle' => 'Siap melayani pesanan Anda dengan senang hati'];
}

// === DAFTAR CARD ===
$stmtCards = $pdo->prepare("
    SELECT * FROM contact_cards 
    ORDER BY sort_order ASC, id ASC
");
$stmtCards->execute();
$contact_cards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

// === ALERT ===
$alert = '';
$alertType = 'success';
if (isset($_SESSION['kontak_alert'])) {
    $alert = $_SESSION['kontak_alert'];
    $alertType = strpos($_SESSION['kontak_alert'], 'Gagal') !== false ? 'error' : 'success';
    unset($_SESSION['kontak_alert']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak — Dapoer Funraise</title>
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
            padding: 0;
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

        .cards-box {
            width: 380px;
            flex-shrink: 0;
            background: white;
            box-shadow: 0 5px 20px rgba(90, 70, 162, 0.1);
            overflow: hidden;
            border: 1px solid #f0eaff;
            border-radius: 0;
        }

        @media (max-width: 768px) {
            .cards-box {
                width: 100%;
                max-width: 100%;
            }
        }

        .form-header, .cards-header {
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
        .cards-header { color: var(--secondary); }

        .form-body, .cards-body {
            padding: 1.5rem 1.4rem;
        }

        .row {
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
        }

        @media (min-width: 768px) {
            .row {
                flex-direction: row;
                gap: 1.1rem;
            }
            .form-group {
                flex: 1;
            }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
            color: var(--primary);
        }

        input[type="text"] {
            width: 100%;
            padding: 11px 15px;
            border: 2px solid #e8e6f2;
            border-radius: 10px;
            font-size: 0.93rem;
            background: #faf9ff;
            font-family: inherit;
            transition: all 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.1);
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

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid #e8e6f2;
        }

        .btn-outline:hover {
            background: #faf9ff;
            border-color: var(--primary);
        }

        .btn-danger {
            background: #f9d9d9;
            color: #d32f2f;
            border: 1px solid #f1b7b7;
        }

        .btn-danger:hover {
            background: #f5baba;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            min-height: auto;
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

        .card-item {
            background: #faf9ff;
            border: 1px solid #f0eaff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--soft);
            color: var(--primary);
            border-radius: 50%;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .card-info {
            flex: 1;
            min-width: 0;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.83rem;
        }

        .card-number { font-weight: 600; color: var(--primary); }
        .card-status { font-weight: 500; }
        .status-active { color: #2e7d32; }
        .status-inactive { color: var(--text-muted); }

        .card-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 3px;
            font-size: 0.95rem;
        }

        .card-label,
        .card-href {
            font-size: 0.85rem;
            color: #555;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .card-label { color: var(--text-muted); }
        .card-href {
            font-family: monospace;
            background: #f9f7ff;
            padding: 3px 6px;
            border-radius: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .no-cards {
            text-align: center;
            padding: 30px 10px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .no-cards i {
            font-size: 2rem;
            margin-bottom: 12px;
            color: #dcd6f7;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Form Section -->
        <div class="form-box">
            <div class="form-header">
                <i class="fas fa-heading" style="color: var(--secondary);"></i>
                Judul & Subjudul
            </div>

            <div class="form-body">
                <?php if ($alert): ?>
                    <div class="alert <?= $alertType === 'success' ? 'alert-success' : '' ?>">
                        <i class="fas fa-<?= $alertType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <form action="update_kontak_section.php" method="POST">
                    <div class="row">
                        <div class="form-group">
                            <label for="title">Judul</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title"
                                value="<?= htmlspecialchars($kontak_section['title']) ?>"
                                required
                                placeholder="Contoh: Hubungi Kami"
                            >
                        </div>

                        <div class="form-group">
                            <label for="subtitle">Subjudul</label>
                            <input 
                                type="text" 
                                id="subtitle" 
                                name="subtitle"
                                value="<?= htmlspecialchars($kontak_section['subtitle']) ?>"
                                required
                                placeholder="Contoh: Siap melayani..."
                            >
                        </div>
                    </div>

                    <button type="submit" name="update_section" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                        <i class="fas fa-save"></i> Simpan Judul & Subjudul
                    </button>
                </form>
            </div>

            <div class="action-bar">
                <a href="../pengaturan.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="add-contact-card.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Kontak Baru
                </a>
            </div>
        </div>

        <!-- Cards List Section -->
        <div class="cards-box">
            <div class="cards-header">
                <i class="fas fa-id-card"></i>
                Daftar Kontak (<?= count($contact_cards) ?> item)
            </div>

            <div class="cards-body">
                <?php if ($contact_cards): ?>
                    <?php foreach ($contact_cards as $i => $card): ?>
                        <div class="card-item">
                            <div class="card-icon">
                                <i class="<?= htmlspecialchars($card['icon_class']) ?>"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-meta">
                                    <span class="card-number">Kontak #<?= $i + 1 ?></span>
                                    <span class="card-status <?= $card['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $card['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </div>
                                <div class="card-title"><?= htmlspecialchars($card['title']) ?></div>
                                <div class="card-label"><?= htmlspecialchars($card['label']) ?></div>
                                <div class="card-actions">
                                    <a href="edit-contact-card.php?id=<?= $card['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($card['is_active']): ?>
                                        <a href="toggle-contact-card.php?id=<?= $card['id'] ?>&action=deactivate"
                                           class="btn btn-gray btn-sm"
                                           onclick="return confirm('Nonaktifkan kontak ini? Tidak tampil di halaman utama.')">
                                            <i class="fas fa-eye-slash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle-contact-card.php?id=<?= $card['id'] ?>&action=activate"
                                           class="btn btn-gray btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete-contact-card.php?id=<?= $card['id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Hapus kontak ini? Tindakan tidak bisa dibatalkan.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-cards">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Belum ada kontak.</p>
                        <a href="add-contact-card.php" class="btn btn-primary" style="margin-top:12px; width:100%;">
                            <i class="fas fa-plus"></i> Tambah Kontak
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>