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
<style>
    :root {
        --primary: #5A46A2;
        --secondary: #B64B62;
        --accent: #F9CC22;
        --bg-light: #FFF5EE;
        --soft: #DFBEE0;
        --text-muted: #9180BB;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    html, body {
        height: 100%;
        font-family: 'Poppins', 'Segoe UI', system-ui, sans-serif;
    }

    body {
        background: linear-gradient(135deg, var(--bg-light) 0%, #f9f5ff 100%);
        color: #333;
    }

    /* 🔹 Wrapper utama: full viewport */
    .app-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
    }

    /* 🔹 Header */
    .app-header {
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        color: white;
        padding: 1rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 4px 20px rgba(90, 70, 162, 0.25);
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        flex-shrink: 0;
        overflow: hidden;
        transition: transform 0.3s ease;
    }
    .app-header.hide {
        transform: translateY(-100%);
    }
    .app-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        z-index: 1;
    }
    .app-header > * { position: relative; z-index: 2; }
    .logo {
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
    }
    .logo:hover { transform: scale(1.02); }

    .logo-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        backdrop-filter: blur(4px);
    }
    .logo-text { display: flex; flex-direction: column; }
    .logo-main {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.5px;
        color: white;
        text-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .logo-sub {
        font-size: 0.85rem;
        font-weight: 500;
        color: rgba(255,255,255,0.92);
        margin-top: -2px;
    }

    /* 🔹 Konten utama */
    .main-content {
        flex: 1;
        padding: 1rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .content-wrapper {
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 28px rgba(90, 70, 162, 0.15);
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.02);
    }

    .page-header {
        background: linear-gradient(120deg, #faf8ff, #f9f5ff);
        color: var(--primary);
        padding: 0.8rem 1.3rem;
        font-size: 1.25rem;
        font-weight: 700;
        border-bottom: 1px solid #f0eaff;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .page-body {
        flex: 1;
        overflow-y: auto;
        padding: 0.7rem;
    }

    /* 🔹 Alert */
    .alert {
        background: #fff8f8;
        color: #c0392b;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 1rem;
        border-left: 3px solid var(--secondary);
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        box-shadow: 0 1px 4px rgba(182, 75, 98, 0.06);
    }
    .alert ul { margin: 4px 0 0 18px; font-size: 0.9rem; }

    /* 🔹 Empty state */
    .empty-state {
        text-align: center;
        padding: 1.8rem 1rem;
        color: var(--text-muted);
    }
    .empty-icon { font-size: 3rem; margin-bottom: 0.8rem; color: var(--soft); }
    .empty-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.4rem;
    }
    .empty-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 0.8rem;
        padding: 8px 20px;
        background: var(--primary);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.05rem;
        text-decoration: none;
    }

    /* 🔹 Layout 2 kolom */
    .two-columns {
        display: flex;
        gap: 1rem;
        height: 100%;
    }

    .column {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }

    /* 🔹 Card section */
    .section {
        background: white;
        border-radius: 14px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        border: 1px solid #f5f0ff;
        flex: 1;
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(120deg, #faf8ff, #f8f5ff);
        color: var(--primary);
        padding: 0.7rem 1.1rem;
        font-weight: 700;
        font-size: 1.1rem;
        border-bottom: 1px solid #f0eaff;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    /* 🔹 Keranjang */
    .cart-items {
        padding: 0.8rem 0.8rem 0.6rem;
        overflow-y: auto;
        flex: 1;
    }

    .cart-item {
        display: flex;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f8f5ff;
    }
    .cart-item:last-child { border-bottom: none; }

    .item-img {
        width: 90px;
        height: 90px;
        background: #fcfbff;
        border-radius: 10px;
        flex-shrink: 0;
        border: 2px solid #f8f6ff;
        overflow: hidden;
    }
    .item-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .item-img-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-size: 1.8rem;
    }

    .item-info { flex: 1; }
    .item-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 4px;
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .item-variant {
        font-size: 0.95rem;
        color: var(--secondary);
        font-weight: 600;
        font-style: italic;
    }
    .item-price { 
        font-weight: 700; 
        color: var(--secondary); 
        font-size: 1rem; 
        margin-top: 4px;
    }

    .item-controls {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .quantity-btn {
        width: 30px;
        height: 30px;
        border: none;
        background: #f0eaff;
        color: var(--primary);
        font-weight: 700;
        font-size: 0.85rem;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .quantity-btn:hover { background: #e6d9ff; }
    .quantity-input {
        width: 50px;
        padding: 4px 0;
        border: none;
        background: #faf9ff;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--primary);
        text-align: center;
        border-radius: 5px;
    }
    .remove-btn {
        width: 30px;
        height: 30px;
        border: none;
        background: #ffe8e8;
        color: var(--secondary);
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .remove-btn:hover {
        background: #ffd5d5;
        transform: scale(1.05);
    }

    /* 🔹 Form */
    .checkout-form {
        padding: 0.8rem;
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
    }

    .form-label {
        display: block;
        font-weight: 700;
        margin-bottom: 4px;
        color: var(--primary);
        font-size: 1.15rem;
    }
    .required { color: var(--secondary); }

    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e8e6f2;
        border-radius: 8px;
        font-size: 1.05rem;
        line-height: 1.4;
        transition: all 0.2s;
        background: #faf9ff;
        font-weight: 500;
        font-family: inherit;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(90, 70, 162, 0.2);
        background: white;
    }

    /* 🔹 Input nama dan alamat sama tinggi */
    .nama-input, .alamat-input {
        min-height: 120px;
        height: 120px;
        resize: none;
    }

    .nama-input {
        display: flex;
        align-items: flex-start;
        padding-top: 10px;
        line-height: 1.4;
    }

    /* 🔹 CAPTCHA Styles */
    .captcha-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .captcha-input {
        flex: 1;
        min-height: 50px;
        height: 50px;
        font-size: 1.1rem;
        font-weight: 600;
        letter-spacing: 2px;
        text-align: center;
    }

    .captcha-image {
        border: 2px solid #e8e6f2;
        border-radius: 8px;
        height: 50px;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.2s;
    }

    .captcha-image:hover {
        border-color: var(--primary);
        transform: scale(1.02);
    }

    /* 🔹 TOMBOL REFRESH BARU */
    .captcha-refresh-btn {
        width: 50px;
        height: 50px;
        border: 2px solid #e8e6f2;
        border-radius: 8px;
        background: #faf9ff;
        color: var(--primary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .captcha-refresh-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: scale(1.05);
    }

    .captcha-help {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-top: 4px;
    }

    .captcha-error {
        color: var(--secondary);
        font-size: 0.9rem;
        font-weight: 600;
        margin-top: 4px;
    }

    .radio-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border: 2px solid #f0eaff;
        border-radius: 8px;
        background: #faf8ff;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1.05rem;
    }
    .radio-option:hover {
        border-color: var(--primary);
        background: #f5f2ff;
    }
    .radio-option input {
        width: 16px;
        height: 16px;
        accent-color: var(--secondary);
    }
    .radio-label-text { font-weight: 600; color: #444; }
    .radio-option input:checked + i + .radio-label-text {
        color: var(--primary);
    }
    .radio-option i {
        font-size: 1.1rem;
        color: var(--primary);
    }
    .radio-option input:checked ~ i {
        color: var(--secondary);
    }

    /* 🔹 HANYA SUBTOTAL — tidak ada total */
    .cart-summary {
        background: linear-gradient(135deg, #fbf9ff, #f7f3ff);
        border-radius: 12px;
        padding: 0.7rem 1rem;
        margin: 0.8rem 0 1.5rem 0;
        border: 1px solid #f0eaff;
        flex-shrink: 0;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .summary-label { color: var(--text-muted); }
    .summary-value { color: var(--secondary); font-weight: 700; }

    /* 🔹 Tombol */
    .form-footer {
        margin-top: 1rem;
        padding-top: 0.8rem;
        border-top: 1px solid #f0eaff;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1rem;
        cursor: pointer;
        text-decoration: none;
        border: none;
        transition: all 0.25s;
        font-family: inherit;
        flex: 1;
    }
    .btn-secondary {
        background: linear-gradient(135deg, var(--soft), #c8a5d0);
        color: var(--primary);
    }
    .btn-secondary:hover {
        background: linear-gradient(135deg, #d0a8d5, #c095cb);
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--secondary), #9e3e52);
        color: white;
        box-shadow: 0 3px 10px rgba(182, 75, 98, 0.25);
    }
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(182, 75, 98, 0.35);
    }

    /* 🔹 Form row */
    .form-row {
        display: flex;
        gap: 0.8rem;
        flex-wrap: wrap;
        margin-bottom: 0.9rem;
    }
    .form-row > div {
        flex: 1;
        min-width: 220px;
    }

    /* 🔹 Update buttons container */
    .update-buttons {
        display: flex;
        gap: 8px;
        margin-top: 1rem;
    }
    .update-buttons .btn {
        flex: 1;
    }

    /* 🔹 Responsif */
    @media (max-width: 899px) {
        .two-columns {
            flex-direction: column;
            gap: 0.6rem;
        }
        .column { gap: 0.6rem; }
        .app-header { padding: 0.6rem 1rem; }
        .logo-main { font-size: 1.3rem; }
        .logo-icon { width: 38px; height: 38px; }
        .item-img { width: 80px; height: 80px; }
        .captcha-container {
            flex-direction: column;
            align-items: stretch;
        }
        .captcha-image {
            align-self: center;
        }
        .captcha-refresh-btn {
            align-self: center;
        }
        .cart-summary {
            margin: 0.8rem 0 1.2rem 0;
        }
    }

    @media (max-width: 599px) {
        .app-header { padding: 0.55rem 0.8rem; }
        .logo-main { font-size: 1.2rem; }
        .logo-sub { display: none; }
        .page-header { font-size: 1.15rem; padding: 0.7rem 1rem; }
        .section-header { font-size: 1.05rem; padding: 0.6rem 1rem; }
        .cart-item { gap: 8px; padding: 6px 0; }
        .item-img { width: 70px; height: 70px; }
        .item-name { font-size: 1.05rem; }
        .form-label { font-size: 1.1rem; }
        .form-control { font-size: 1rem; padding: 9px 12px; }
        .btn { font-size: 1.05rem; padding: 7px 14px; }
        .form-footer { gap: 6px; margin-top: 0.8rem; }
        .update-buttons { flex-direction: column; }
        .item-name {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        .captcha-input {
            font-size: 1rem;
            min-height: 45px;
            height: 45px;
        }
        .captcha-image {
            height: 45px;
        }
        .captcha-refresh-btn {
            width: 45px;
            height: 45px;
            font-size: 1.1rem;
        }
        .cart-summary {
            margin: 0.8rem 0 1rem 0;
        }
    }
</style>
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

    <script>
        function adjustQty(button, delta) {
            const controls = button.closest('.item-controls');
            const input = controls.querySelector('.quantity-input');
            if (!input) return;
            
            let val = parseInt(input.value) || 1;
            val += delta;
            val = Math.max(1, Math.min(100, val));
            input.value = val;
            
            const form = document.getElementById('cartForm');
            if (form) form.submit();
        }
        
        function refreshCaptcha() {
            const captchaImage = document.getElementById('captchaImage');
            if (captchaImage) {
                // Tambahkan timestamp untuk menghindari cache
                captchaImage.src = 'captcha.php?' + new Date().getTime();
            }
        }

        // 🔹 FUNGSI KONFIRMASI PESANAN
        function confirmCheckout() {
            // Validasi form terlebih dahulu
            const form = document.getElementById('checkoutForm');
            const nama = document.getElementById('nama').value.trim();
            const alamat = document.getElementById('alamat').value.trim();
            const captcha = document.getElementById('captcha').value.trim();
            const pengambilan = document.querySelector('input[name="pengambilan"]:checked');
            const metodeBayar = document.querySelector('input[name="metode_bayar"]:checked');
            
            let errors = [];
            
            if (!nama) errors.push('Nama lengkap');
            if (!alamat) errors.push('Alamat lengkap');
            if (!captcha) errors.push('Kode verifikasi');
            if (!pengambilan) errors.push('Metode pengiriman');
            if (!metodeBayar) errors.push('Metode pembayaran');
            
            if (errors.length > 0) {
                alert('Harap lengkapi data berikut:\n• ' + errors.join('\n• '));
                return false;
            }
            
            // Tampilkan konfirmasi
            const confirmation = confirm('Apakah Anda yakin untuk membuat pesanan?\n\nPesanan akan dikirim ke WhatsApp dan tidak dapat dibatalkan.');
            return confirmation;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const header = document.querySelector('.app-header');
            let lastScrollY = window.scrollY;
            let ticking = false;

            // Auto-focus ke input CAPTCHA ketika gambar diklik
            const captchaImage = document.getElementById('captchaImage');
            const captchaInput = document.getElementById('captcha');
            
            if (captchaImage && captchaInput) {
                captchaImage.addEventListener('click', function() {
                    refreshCaptcha();
                    captchaInput.focus();
                });
            }

            const updateHeader = () => {
                if (window.scrollY > lastScrollY && window.scrollY > 80) {
                    // Scroll turun → sembunyikan
                    header.classList.add('hide');
                } else {
                    // Scroll naik / di atas → tampilkan
                    header.classList.remove('hide');
                }
                lastScrollY = window.scrollY;
                ticking = false;
            };

            const requestTick = () => {
                if (!ticking) {
                    requestAnimationFrame(updateHeader);
                    ticking = true;
                }
            };

            window.addEventListener('scroll', requestTick, { passive: true });
        });  
    </script>
</body>
</html>