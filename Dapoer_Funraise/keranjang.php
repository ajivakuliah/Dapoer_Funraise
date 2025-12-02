<?php
session_start();
require 'config.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ambil data header
$stmtHeader = $pdo->query("SELECT logo_path, business_name, tagline FROM header WHERE id = 1");
$header = $stmtHeader->fetch(PDO::FETCH_ASSOC);
if (!$header) {
    $header = [
        'logo_path' => 'assets/logo.png',
        'business_name' => 'Dapoer Funraise',
        'tagline' => 'Cemilan rumahan yang bikin nagih!'
    ];
}

// Ambil nomor WhatsApp dari database - ambil yang aktif dan urutkan
$whatsapp_number = '';
try {
    $stmt = $pdo->query("SELECT whatsapp_number FROM whatsapp_buttons WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['whatsapp_number'])) {
        $whatsapp_number = $result['whatsapp_number'];
    } else {
        $whatsapp_number = '6283129704643'; // fallback default
    }
} catch (Exception $e) {
    error_log("WhatsApp number error: " . $e->getMessage());
    $whatsapp_number = '6283129704643'; // fallback default
}

// Hapus item
if (isset($_GET['remove'])) {
    $key = $_GET['remove'];
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
        header('Location: keranjang.php');
        exit;
    }
}

// Update kuantitas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $key => $qty) {
            $qty = (int)$qty;
            if (isset($_SESSION['cart'][$key])) {
                if ($qty > 0 && $qty <= 100) {
                    $_SESSION['cart'][$key]['quantity'] = $qty;
                } else {
                    unset($_SESSION['cart'][$key]);
                }
            }
        }
        header('Location: keranjang.php');
        exit;
    }

    // Proses checkout — SIMPAN KE DATABASE + Kirim ke WhatsApp
    if (isset($_POST['generate_wa'])) {
        $errors = [];
        $nama = trim($_POST['nama'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $pengambilan = $_POST['pengambilan'] ?? '';
        $metode_bayar = $_POST['metode_bayar'] ?? '';
        $captcha_input = trim($_POST['captcha'] ?? '');

        if (!$nama) $errors[] = 'Nama wajib diisi.';
        if (!$alamat) $errors[] = 'Alamat wajib diisi.';
        if (!in_array($pengambilan, ['ambil', 'antar'])) $errors[] = 'Pilih metode pengambilan.';
        if (!in_array($metode_bayar, ['cash', 'tf'])) $errors[] = 'Pilih metode pembayaran.';
        
        // 🔹 Validasi CAPTCHA - case sensitive
        if (empty($captcha_input)) {
            $errors[] = 'Kode CAPTCHA wajib diisi.';
        } elseif (!isset($_SESSION['captcha_code']) || $captcha_input !== $_SESSION['captcha_code']) {
            $errors[] = 'Kode CAPTCHA tidak valid.';
            $captcha_error = 'Kode CAPTCHA salah, silakan coba lagi.';
        } else {
            $captcha_verified = true;
        }

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) $errors[] = 'Keranjang belanja kosong.';

        // 🔹 Hanya lanjutkan jika CAPTCHA valid dan tidak ada error lain
        if (empty($errors) && $captcha_verified) {
            try {
                $total = 0;
                $produk_list = [];
                foreach ($cart as $item) {
                    $qty = (int)($item['quantity'] ?? 0);
                    if ($qty <= 0) continue; // ✅ Hanya simpan yang dipilih
                    $harga = (int)($item['harga'] ?? 0);
                    $subtotal = $harga * $qty;
                    $total += $subtotal;
                    $produk_list[] = [
                        'nama' => $item['nama'],
                        'varian' => $item['varian'] ?? null,
                        'qty' => $qty,
                        'harga' => $harga,
                        'subtotal' => $subtotal
                    ];
                }

                if (empty($produk_list)) {
                    throw new Exception("Tidak ada produk dengan jumlah > 0.");
                }

                // 🔹 SIMPAN KE DATABASE (tabel `pesanan`)
                $produk_json = json_encode($produk_list, JSON_UNESCAPED_UNICODE);
                
                // Sesuaikan nilai metode_bayar dengan ENUM di database
                $metode_bayar_db = ($metode_bayar === 'cash') ? 'Tunai' : 'transfer';
                
                $stmt = $pdo->prepare("
                    INSERT INTO pesanan (nama_pelanggan, alamat, produk, total, pengambilan, metode_bayar, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'baru')
                ");
                
                // Eksekusi dan cek hasil
                $result = $stmt->execute([
                    $nama,
                    $alamat,
                    $produk_json,
                    $total,
                    $pengambilan,
                    $metode_bayar_db
                ]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Database error: " . $errorInfo[2]);
                }
                
                $order_id = $pdo->lastInsertId();
                error_log("Pesanan berhasil dibuat dengan ID: " . $order_id);

                // 🔹 Format WhatsApp
                $wa_text = "Halo Dapoer Funraise!\n\n";
                $wa_text .= "Saya ingin memesan:\n";
                foreach ($produk_list as $p) {
                    $wa_text .= "• " . htmlspecialchars_decode($p['nama']);
                    if (!empty($p['varian'])) {
                        $wa_text .= " (" . htmlspecialchars_decode($p['varian']) . ")";
                    }
                    $wa_text .= "\n      Jumlah: " . $p['qty'] . " × Rp " . number_format($p['harga'], 0, ',', '.') .
                                " = Rp " . number_format($p['subtotal'], 0, ',', '.') . "\n";
                }
                $wa_text .= "\n" . str_repeat("─", 24) . "\n";
                $wa_text .= "Total: _Rp " . number_format($total, 0, ',', '.') . "_\n\n";
                $wa_text .= "Detail Pemesan:\n";
                $wa_text .= "• Nama      : " . htmlspecialchars_decode($nama) . "\n";
                $wa_text .= "• Alamat    : " . htmlspecialchars_decode($alamat) . "\n";
                $wa_text .= "• Pengambilan: " . ($pengambilan === 'ambil' ? 'Ambil di Toko' : 'Diantar') . "\n";
                $wa_text .= "• Pembayaran : " . ($metode_bayar_db === 'Tunai' ? 'Cash (di Tempat)' : 'Transfer Bank') . "\n\n";
                $wa_text .= "Terima kasih ";

                $wa_encoded = rawurlencode($wa_text);
                $whatsapp_link = "https://wa.me/" . $whatsapp_number . "?text=" . $wa_encoded;

                // Update whatsapp_link di database
                $update_stmt = $pdo->prepare("UPDATE pesanan SET whatsapp_link = ? WHERE id = ?");
                $update_stmt->execute([$whatsapp_link, $order_id]);

                // Reset session
                unset($_SESSION['captcha_code']);
                $_SESSION['cart'] = []; // Reset keranjang
                header("Location: " . $whatsapp_link);
                exit;

            } catch (Exception $e) {
                error_log("Checkout DB Error: " . $e->getMessage());
                $errors[] = 'Gagal menyimpan pesanan. Silakan coba lagi.';
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$total = 0;
foreach ($cart as $item) {
    $total += $item['harga'] * $item['quantity'];
}

// 🔹 CAPTCHA - Inisialisasi (HANYA set variabel jika ada POST request)
$captcha_error = '';
$captcha_verified = false;

// 🔹 HILANGKAN pesan error di awal - hanya set $errors ketika ada POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errors = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Keranjang — Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/keranjang.css">

</head>
<body>

    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">
                <img src="<?= htmlspecialchars($header['logo_path']) ?>" alt="Logo <?= htmlspecialchars($header['business_name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="logo-text">
                <span class="logo-main"><?= htmlspecialchars($header['business_name']) ?></span>
                <span class="logo-sub"><?= htmlspecialchars($header['tagline']) ?></span>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-body">

            <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Perbaiki kesalahan berikut:</strong>
                        <ul style="margin:6px 0 0 20px; font-size:1rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($cart)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h2 class="empty-title">Keranjang Belanja Kosong</h2>
                    <p>Tambahkan produk terlebih dahulu untuk melanjutkan pembelian.</p>
                    <a href="produk.php" class="empty-btn">
                        <i class="fas fa-scarf"></i> Lihat Produk
                    </a>
                </div>
            <?php else: ?>

                <div class="two-columns">
                    <!-- 🔸 KIRI: PRODUK -->
                    <div class="column">
                        <div class="section">
                            <div class="section-header">
                                <i class="fas fa-list"></i> Produk yang Dipesan
                            </div>
                            <div class="cart-items">
                                <form method="POST" id="cartForm">
                                    <?php foreach ($cart as $key => $item): ?>
                                        <div class="cart-item">
                                            <div class="item-img">
                                                <?php
                                                $foto = trim($item['foto'] ?? '');
                                                $foto_path = $foto ? 'uploads/' . $foto : '';
                                                $full_path = __DIR__ . '/' . $foto_path;
                                                $use_image = $foto && is_file($full_path);
                                                ?>
                                                <?php if ($use_image): ?>
                                                    <img src="<?= htmlspecialchars($foto_path) ?>" 
                                                         alt="<?= htmlspecialchars($item['nama']) ?>">
                                                <?php else: ?>
                                                    <div class="item-img-placeholder">
                                                        <i class="fas fa-cookie-bite"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name">
                                                    <?= htmlspecialchars($item['nama']) ?>
                                                    <?php if (!empty($item['varian'])): ?>
                                                        <span class="item-variant">(<?= htmlspecialchars($item['varian']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="item-price">Rp <?= number_format($item['harga'], 0, ',', '.') ?></div>
                                            </div>
                                            <div class="item-controls">
                                                <button type="button" class="quantity-btn" onclick="adjustQty(this, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input 
                                                    type="number" 
                                                    name="quantity[<?= htmlspecialchars($key) ?>]" 
                                                    value="<?= (int)$item['quantity'] ?>" 
                                                    min="1" 
                                                    max="100" 
                                                    class="quantity-input"
                                                >
                                                <button type="button" class="quantity-btn" onclick="adjustQty(this, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <a href="?remove=<?= urlencode($key) ?>" class="remove-btn" title="Hapus">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="update-buttons">
                                        <a href="produk.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Lanjut Belanja
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync-alt"></i> Perbarui
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 🔸 KANAN: DETAIL -->
                    <div class="column">
                        <div class="section">
                            <div class="section-header">
                                <i class="fas fa-file-invoice"></i> Detail Pemesanan
                            </div>
                            <div class="checkout-form">
                                <form method="POST" id="checkoutForm">

                                    <!-- 🔹 Baris 1: Nama & Alamat -->
                                    <div class="form-row">
                                        <div>
                                            <label class="form-label" for="nama">
                                                Nama Lengkap <span class="required">*</span>
                                            </label>
                                            <textarea 
                                                id="nama" 
                                                name="nama" 
                                                class="form-control nama-input"
                                                required 
                                                placeholder="Masukkan nama lengkap Anda"
                                            ><?= htmlspecialchars($_POST['nama'] ?? '') ?></textarea>
                                        </div>
                                        <div>
                                            <label class="form-label" for="alamat">
                                                Alamat Lengkap <span class="required">*</span>
                                            </label>
                                            <textarea 
                                                id="alamat" 
                                                name="alamat" 
                                                class="form-control alamat-input"
                                                required 
                                                placeholder="Masukkan alamat lengkap Anda"
                                            ><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                                        </div>
                                    </div>

                                    <!-- 🔹 Baris 2: Pengiriman & Pembayaran -->
                                    <div class="form-row">
                                        <div>
                                            <label class="form-label">
                                                Metode Pengiriman <span class="required">*</span>
                                            </label>
                                            <div class="radio-group">
                                                <label class="radio-option">
                                                    <input type="radio" name="pengambilan" value="ambil" required 
                                                        <?= (($_POST['pengambilan'] ?? '') === 'ambil') ? 'checked' : '' ?>>
                                                    <i class="fas fa-store"></i>
                                                    <span class="radio-label-text">Ambil di Toko</span>
                                                </label>
                                                <label class="radio-option">
                                                    <input type="radio" name="pengambilan" value="antar" required 
                                                        <?= (($_POST['pengambilan'] ?? '') === 'antar') ? 'checked' : '' ?>>
                                                    <i class="fas fa-shipping-fast"></i>
                                                    <span class="radio-label-text">Diantar</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="form-label">
                                                Metode Pembayaran <span class="required">*</span>
                                            </label>
                                            <div class="radio-group">
                                                <label class="radio-option">
                                                    <input type="radio" name="metode_bayar" value="cash" required 
                                                        <?= (($_POST['metode_bayar'] ?? '') === 'cash') ? 'checked' : '' ?>>
                                                    <i class="fas fa-money-bill-wave"></i>
                                                    <span class="radio-label-text">Cash (di Tempat)</span>
                                                </label>
                                                <label class="radio-option">
                                                    <input type="radio" name="metode_bayar" value="tf" required 
                                                        <?= (($_POST['metode_bayar'] ?? '') === 'tf') ? 'checked' : '' ?>>
                                                    <i class="fas fa-university"></i>
                                                    <span class="radio-label-text">Transfer Bank</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 🔹 Summary -->
                                    <div class="cart-summary">
                                        <div class="summary-row">
                                            <span class="summary-label">Subtotal</span>
                                            <span class="summary-value">Rp <?= number_format($total, 0, ',', '.') ?></span>
                                        </div>
                                    </div>

                                    <!-- 🔹 Baris 3: CAPTCHA - DIPINDAHKAN KE BAWAH SUBTOTAL DENGAN JARAK -->
                                    <div class="form-row">
                                        <div>
                                            <label class="form-label" for="captcha">
                                                Kode Verifikasi <span class="required">*</span>
                                            </label>
                                            <div class="captcha-container">
                                                <input 
                                                    type="text" 
                                                    id="captcha" 
                                                    name="captcha" 
                                                    class="form-control captcha-input" 
                                                    required 
                                                    placeholder="Masukkan kode sesuai gambar"
                                                    maxlength="6"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars($_POST['captcha'] ?? '') ?>"
                                                >
                                                <img src="captcha.php" id="captchaImage" class="captcha-image" alt="CAPTCHA" onclick="refreshCaptcha()">
                                                <!-- 🔹 TOMBOL REFRESH BARU -->
                                                <button type="button" class="captcha-refresh-btn" onclick="refreshCaptcha()" title="Refresh CAPTCHA">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </div>
                                            <?php if (!empty($captcha_error)): ?>
                                                <div class="captcha-error">
                                                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($captcha_error) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="captcha-help">
                                                    <i class="fas fa-info-circle"></i> Masukkan kode persis seperti yang terlihat (huruf besar/kecil diperhatikan)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="form-footer">
                                        <button type="submit" name="generate_wa" class="btn btn-primary" onclick="return confirmCheckout()">
                                            <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <script src="js/keranjang.js"></script>

</body>
</html>