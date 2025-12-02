<?php
session_start();
require 'config.php';

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

// Ambil data tentang_kami_section
$stmtTentang = $pdo->prepare("SELECT title, subtitle, content FROM tentang_kami_section WHERE id = 1");
$stmtTentang->execute();
$tentang = $stmtTentang->fetch(PDO::FETCH_ASSOC);
if (!$tentang) {
    $tentang = [
        'title'    => 'Tentang Kami',
        'subtitle' => 'Dapur kecil, dampak besar untuk pendidikan',
        'content'  => 'Dapoer Funraise adalah wujud kepedulian alumni MAN 2 Samarinda dalam mendukung <strong>Expo Campus MAN 2 Samarinda</strong> — acara tahunan untuk memperkenalkan perguruan tinggi kepada siswa. Seluruh keuntungan penjualan cemilan digunakan untuk kebutuhan acara: konsumsi, dekorasi, dan logistik. Kami percaya: bisnis kecil bisa berdampak besar!'
    ];
}

// Ambil foto carousel aktif
$stmtPhotos = $pdo->prepare("
    SELECT image_path, alt_text, caption 
    FROM carousel_photos 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, id ASC
");
$stmtPhotos->execute();
$photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);
if (empty($photos)) {
    $photos = [
        ['image_path' => 'assets/kegiatan1.jpg', 'alt_text' => 'Tim Dapoer Funraise', 'caption' => 'Tim solid Dapoer Funraise'],
        ['image_path' => 'assets/kegiatan2.jpg', 'alt_text' => 'Kegiatan Expo Campus 2024', 'caption' => 'Momen seru Expo Campus'],
        ['image_path' => 'assets/kegiatan3.jpg', 'alt_text' => 'Proses pembuatan cemilan', 'caption' => 'Proses produksi yang higienis'],
        ['image_path' => 'assets/kegiatan4.jpg', 'alt_text' => 'Distribusi cemilan ke acara', 'caption' => 'Pengiriman tepat waktu']
    ];
}

// Testimoni
$stmtTesti = $pdo->query("
    SELECT id, nama, nama_produk, komentar, rating, dikirim_pada 
    FROM testimoni 
    WHERE is_verified = 1
    ORDER BY dikirim_pada DESC 
    LIMIT 3
");
$testimoni_terbaru = $stmtTesti->fetchAll();

// ====== PERBAIKAN: HERO SECTION - SESUAI STRUKTUR DATABASE ANDA ======
// AMBIL DATA HERO SECTION - HANYA BARIS PERTAMA
$stmtHero = $pdo->query("SELECT id, welcome_text FROM hero_section ORDER BY id DESC LIMIT 1");
$heroData = $stmtHero->fetch(PDO::FETCH_ASSOC);

// TENTUKAN KONTEN HERO
if ($heroData) {
    // Jika ada data di database
    $welcome_text = $heroData['welcome_text'];
    $hero_section_id = $heroData['id'];
    
    // Ambil background yang aktif untuk hero_section_id ini
    $stmtBg = $pdo->prepare("
        SELECT background_path 
        FROM hero_backgrounds 
        WHERE hero_section_id = ? AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmtBg->execute([$hero_section_id]);
    $hero_backgrounds = $stmtBg->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (empty($hero_backgrounds)) {
        $hero_backgrounds = ['assets/bg1.jpg'];
    }
} else {
    // Jika tidak ada data di database, gunakan default
    $welcome_text = 'Selamat Datang di Dapoer Funraise';
    $hero_backgrounds = ['assets/bg1.jpg'];
}
// ====== END PERBAIKAN ======

// Ambil WhatsApp button untuk hero section
$stmtWhatsApp = $pdo->prepare("
    SELECT button_text, whatsapp_number 
    FROM whatsapp_buttons 
    WHERE is_active = 1 
    ORDER BY sort_order ASC 
    LIMIT 1
");
$stmtWhatsApp->execute();
$whatsapp_button = $stmtWhatsApp->fetch();
if (!$whatsapp_button) {
    $whatsapp_button = [
        'button_text' => 'Pesan Sekarang',
        'whatsapp_number' => '6283129704643'
    ];
}

// Ambil pesan default WhatsApp dari site_settings
$stmtWhatsAppMsg = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'whatsapp_default_message'");
$stmtWhatsAppMsg->execute();
$whatsapp_message = $stmtWhatsAppMsg->fetch(PDO::FETCH_COLUMN);
if (!$whatsapp_message) {
    $whatsapp_message = 'Halo, saya ingin memesan produk Dapoer Funraise';
}

// Cara pesan
$stmtCaraPesan = $pdo->query("SELECT title, subtitle FROM cara_pesan_section WHERE id = 1");
$caraPesanSec = $stmtCaraPesan->fetch(PDO::FETCH_ASSOC);
$cara_title = $caraPesanSec['title'] ?? 'Cara Pesan';
$cara_subtitle = $caraPesanSec['subtitle'] ?? 'Mudah dan cepat, hanya dalam 4 langkah';

$stmtSteps = $pdo->query("
    SELECT * FROM cara_pesan_steps 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, step_number ASC
");
$cara_steps = $stmtSteps->fetchAll();

// Footer
$stmtFooter = $pdo->prepare("SELECT main_text, copyright_text FROM footer_section WHERE id = 1 AND is_active = 1");
$stmtFooter->execute();
$footerData = $stmtFooter->fetch(PDO::FETCH_ASSOC);
if (!$footerData) {
    $footerData = [
        'main_text' => 'Mendukung Expo Campus MAN 2 Samarinda',
        'copyright_text' => '© 2025 <strong>Dapoer Funraise</strong>'
    ];
}

// Kontak section title & subtitle
$stmtKontakSec = $pdo->prepare("SELECT title, subtitle FROM kontak_section WHERE id = 1");
$stmtKontakSec->execute();
$kontak_section = $stmtKontakSec->fetch(PDO::FETCH_ASSOC);
if (!$kontak_section) {
    $kontak_section = [
        'title'    => 'Hubungi Kami',
        'subtitle' => 'Siap melayani pesanan Anda dengan senang hati'
    ];
}

// Contact cards
$stmtCards = $pdo->prepare("
    SELECT icon_class, title, label, href 
    FROM contact_cards 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, id ASC
");
$stmtCards->execute();
$contact_cards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($header['business_name']) ?> - <?= htmlspecialchars($header['tagline']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #B64B62;
            --secondary: #5A46A2;
            --accent: #F9CC22;
            --purple-light: #DFBEE0;
            --purple-mid: #9180BB;
            --cream: #FFF5EE;
            --dark: #2a1f3d;
            --font-main: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --shadow-md: 0 12px 30px rgba(90, 70, 162, 0.15);
            --shadow-lg: 0 24px 50px rgba(90, 70, 162, 0.2);
            --section-padding: 0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-main);
            color: #333;
            overflow-x: hidden;
            scroll-behavior: smooth;
            background: linear-gradient(135deg, var(--cream) 0%, #fef8f4 100%);
            line-height: 1.6;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; height: auto; display: block; }
        .container { 
            width: 90%; 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 2rem;
        }

        /* === BUTTONS === */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 32px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            border: none;
            outline: none;
            box-shadow: var(--shadow-md);
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #F9CC22);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 40px rgba(182, 75, 98, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #58c477ff);
            color: white;
        }
        .btn-secondary:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 40px rgba(90, 70, 162, 0.4);
        }

        /* === HEADER === */
        .app-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            color: white;
            padding: 1rem 9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .app-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            z-index: 1;
        }
        .app-header > * {
            position: relative;
            z-index: 2;
        }
        .logo {
            margin-right: auto;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        .logo:hover {
            transform: scale(1.02);
        }
        .logo-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            backdrop-filter: blur(4px);
            overflow: hidden;
        }
        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        .logo-main {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logo-sub {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.9;
            color: rgba(255,255,255,0.95);
            margin-top: -2px;
        }
        .nav-links {
            display: flex;
            justify-content: center;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1.2rem;
            flex-wrap: wrap;
        }
        .nav-links a {
            font-weight: 600;
            font-size: 1.05rem;
            position: relative;
            color: rgba(255,255,255,0.92);
            transition: var(--transition);
            padding: 8px 0;
        }
        .nav-links a:hover {
            color: white;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: var(--transition);
        }
        .nav-links a:hover::after {
            width: 100%;
        }
        .nav-links a.active {
            color: white;
            font-weight: 700;
        }
        .nav-links a.active::after {
            width: 100%;
            background: var(--accent);
            height: 3px;
        }
        /* Hamburger Button */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .hamburger span {
            width: 28px;
            height: 3px;
            background: white;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

@media (max-width: 768px) {
    .hamburger {
        display: flex;
    }

    .nav-links {
        position: fixed;
        top: 0;
        right: -100%;
        height: 100vh;
        width: 280px;
        background: linear-gradient(180deg, var(--secondary), var(--primary));
        flex-direction: column;
        align-items: flex-start;
        padding: 100px 2rem 2rem;
        gap: 0;
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.3);
        transition: right 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        overflow-y: auto;
    }

    .nav-links.active {
        right: 0;
    }

    .nav-links li {
        width: 100%;
        opacity: 0;
        transform: translateX(50px);
        animation: slideIn 0.4s forwards;
    }

    .nav-links.active li:nth-child(1) { animation-delay: 0.1s; }
    .nav-links.active li:nth-child(2) { animation-delay: 0.2s; }
    .nav-links.active li:nth-child(3) { animation-delay: 0.3s; }
    .nav-links.active li:nth-child(4) { animation-delay: 0.4s; }
    .nav-links.active li:nth-child(5) { animation-delay: 0.5s; }

    @keyframes slideIn {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .nav-links a {
        width: 100%;
        padding: 1rem 0;
        font-size: 1.1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
}
        /* Overlay */
        .nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-overlay.active {
            display: block;
            opacity: 1;
        }

        /* === SECTIONS - FULL SCREEN === */
        section {
            min-height: 100vh;
            padding: 70px 9rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            scroll-snap-align: start;
            scroll-margin-top: 80px;
            width: 100%;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-align: center;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            width: 100%;
        }
        .section-subtitle {
            font-size: 1.1rem;
            margin-bottom: 3rem;
            text-align: center;
            max-width: 700px;
            color: var(--dark);
            line-height: 1.6;
            width: 100%;
        }

        /* BERANDA - CONTENT RAISED */
        #beranda {
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            padding: 0;
            height: 100vh;
        }
        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        .hero-slide.active {
            opacity: 1;
        }
        #beranda::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        #beranda .hero-content {
            max-width: 950px;
            position: relative;
            z-index: 2;
            padding: 0 20px;
            transform: translateY(-50px);
        }
        #beranda h2 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1rem;
            text-shadow: 0 4px 12px rgba(0,0,0,0.25);
            background: linear-gradient(135deg, #FFD700, #FF6B6B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: float 3s ease-in-out infinite;
        }
        .welcome-text {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            animation: bounce 2s infinite alternate, glow 2s ease-in-out infinite alternate;
        }
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-10px); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes glow {
            0% { text-shadow: 0 2px 8px rgba(0,0,0,0.5); }
            100% { text-shadow: 0 2px 20px rgba(255,255,255,0.3); }
        }
        #beranda p {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            font-weight: 400;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.95;
            animation: fadeInUp 1s ease-out;
        }

        /* Hero Slider Navigation */
        .hero-nav {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            z-index: 10;
        }
        .hero-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: var(--transition);
        }
        .hero-dot.active {
            background: white;
            transform: scale(1.2);
        }
        .hero-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10;
        }
        .hero-arrow:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-50%) scale(1.1);
        }
        .hero-arrow.prev {
            left: 20px;
        }
        .hero-arrow.next {
            right: 20px;
        }

        /* CARA PESAN - INTERACTIVE */
        #cara-pesan { 
            background: var(--cream); 
            min-height: 100vh;
        }
        .order-container {
            display: flex;
            justify-content: center;
            align-items: stretch;
            gap: 20px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .order-card {
            background: white;
            padding: 2.5rem 1.5rem;
            border-radius: 20px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            background-clip: padding-box;
            cursor: pointer;
            flex: 1;
            min-width: 200px;
            max-width: 250px;
        }
        .order-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            z-index: -1;
            border-radius: 20px;
            opacity: 0;
            transition: var(--transition);
        }
        .order-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: var(--shadow-lg);
        }
        .order-card:hover::before { opacity: 1; }
        .order-card i {
            font-size: 2.2rem;
            margin-bottom: 1.rem;
            transition: var(--transition);
            color: var(--secondary);
        }
        .order-card:hover i { 
            color: white; 
            transform: scale(1.1) rotate(8deg);
        }
        .order-card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            color: var(--dark);
            transition: var(--transition);
        }
        .order-card:hover h3 { color: white; }
        .order-card p {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.5;
            transition: var(--transition);
        }
        .order-card:hover p { color: rgba(255,255,255,0.9); }

        /* TENTANG KAMI - FIXED ALIGNMENT */
        #tentang-kami {
            background: white;
            min-height: 100vh;
            padding: 80px 2rem;
        }
        .about-combined-card {
            max-width: 1000px;
            width: 100%;
            background: #fff5ee;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin: 0 auto;
            transition: var(--transition);
        }
        .about-combined-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .about-content {
            padding: 3rem 2.5rem; 
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .about-content p {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #444;
            margin-bottom: 1rem;
        }
        .about-carousel {
            position: relative;
            overflow: hidden;
            height: 350px;
        }
        .about-carousel-wrapper {
            display: flex;
            scroll-behavior: smooth;
            scroll-snap-type: x mandatory;
            height: 100%;
            transition: transform 0.5s ease-in-out;
        }
        .about-carousel-slide {
            min-width: 100%;
            scroll-snap-align: start;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .about-carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            transition: transform 0.3s ease;
        }
        .about-carousel-slide:hover img {
            transform: scale(1.05);
        }
        .about-carousel-nav {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            z-index: 10;
        }
        .about-carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: var(--transition);
        }
        .about-carousel-dot.active {
            background: white;
            transform: scale(1.2);
        }
        .about-carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.8);
            border: none;
            border-radius: 50%;
            color: var(--secondary);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10;
        }
        .about-carousel-arrow:hover {
            background: white;
            transform: translateY(-50%) scale(1.1);
        }
        .about-carousel-arrow.prev {
            left: 10px;
        }
        .about-carousel-arrow.next {
            right: 10px;
        }

        /* TESTIMONI & FORM - INTERACTIVE */
        #testimoni-section {
            background: linear-gradient(135deg, var(--purple-light), var(--cream));
            min-height: 100vh;
            width: 100%;
        }

        .testimoni-combined {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .testimoni-combined > div {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .testimoni-combined > div:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .testimoni-combined h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-weight: 700;
        }

        .testimoni-combined p.subtitle {
            font-size: 1rem;
            margin-bottom: 2rem;
            color: #666;
            font-weight: 500;
        }

        .testimoni-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .testimoni-accordion {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #f0e6f6;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .testimoni-accordion:hover {
            box-shadow: 0 4px 12px rgba(90,70,162,0.1);
            transform: translateY(-2px);
        }
        .accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.2rem;
            cursor: pointer;
            background: #fbf8ff;
            transition: background 0.3s ease;
        }
        .accordion-header:hover {
            background: #f8f4ff;
        }
        .header-content cite {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            display: block;
        }
        .testimoni-date {
            font-size: 0.8rem;
            color: #666;
            margin-top: 2px;
        }
        .chevron {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: transform 0.3s ease;
        }
        .testimoni-accordion.active .chevron {
            transform: rotate(180deg);
        }
        .accordion-body {
            max-height: 0;
            overflow: hidden;
            background: white;
            transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .testimoni-accordion.active .accordion-body {
            max-height: 400px;
        }
        .accordion-body blockquote {
            margin: 0;
            padding: 0 1.2rem 1.2rem;
            position: relative;
        }
        .accordion-body blockquote::before {
            content: '"';
            position: absolute;
            top: 8px;
            left: 8px;
            font-size: 2rem;
            color: var(--accent);
            opacity: 0.2;
            font-family: serif;
        }
        .accordion-body blockquote p {
            font-size: 0.9rem;
            line-height: 1.5;
            color: #333;
            margin: 0.8rem 0 0.6rem;
            font-style: italic;
        }
        .testimoni-product {
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 600;
            margin-top: 0.6rem;
        }

        /* FORM TESTIMONI */
        .form-testimoni {
            width: 100%;
        }
        .form-row {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            transition: var(--transition);
        }
        .form-row input,
        .form-row textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 2px solid var(--purple-light);
            background: var(--cream);
            color: #333;
            font-size: 1rem;
            transition: var(--transition);
        }
        .form-row input:focus,
        .form-row textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.2);
            transform: translateY(-2px);
        }
        .form-row input:hover,
        .form-row textarea:hover {
            border-color: var(--secondary);
        }
        .form-row textarea { 
            resize: vertical; 
            min-height: 120px;
        }

        /* CAPTCHA STYLES */
        .captcha-container {
            margin-top: 1.5rem;
        }
        .captcha-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .captcha-image-wrapper {
            flex-shrink: 0;
            cursor: pointer;
            border: 2px solid var(--purple-light);
            border-radius: 8px;
            padding: 5px;
            background: white;
            transition: var(--transition);
        }
        .captcha-image-wrapper:hover {
            border-color: var(--secondary);
            transform: scale(1.02);
        }
        .captcha-image-wrapper img {
            height: 40px;
            border-radius: 4px;
        }
        .captcha-input-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .captcha-input-wrapper input {
            flex: 1;
            min-width: 120px;
        }
        .refresh-btn {
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .refresh-btn:hover {
            background: var(--primary);
            transform: rotate(90deg);
        }
        .form-actions {
            margin-top: 2rem;
            text-align: center;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }

        .alert-sukses {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
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

        /* KONTAK - FIXED SIZE CARDS */
        #kontak {
            background: url('assets/lotus.jpg') center/cover no-repeat;
            color: #333;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            padding: 80px 2rem;
        }
        #kontak::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }
        #kontak .section-title,
        #kontak .section-subtitle,
        #kontak .contact-cards {
            position: relative;
            z-index: 2;
        }
        #kontak .section-title {
            color: white;
            -webkit-text-fill-color: white;
        }
        #kontak .section-subtitle {
            color: rgba(255, 255, 255, 0.95);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .contact-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            max-width: 1070px;
            width: 100%;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .contact-card {
            background: var(--primary);
            padding: 2.5rem 1.5rem;
            border-radius: 20px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            width: 100%;
        }
        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }
        .contact-card:hover::before {
            opacity: 1;
        }
        .contact-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(182, 75, 98, 0.4);
        }
        .card-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent), #FFD700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--dark);
            box-shadow: 0 6px 20px rgba(249, 204, 34, 0.4);
            position: relative;
            z-index: 2;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .contact-card:hover .card-icon {
            transform: scale(1.1) rotate(10deg);
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
            position: relative;
            z-index: 2;
            text-align: center;
            width: 100%;
        }
        .contact-link {
            display: inline-block;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            padding: 12px 24px;
            border-radius: 10px;
            background: var(--accent);
            transition: var(--transition);
            position: relative;
            z-index: 2;
            text-align: center;
            min-width: 160px;
        }
        .contact-link:hover {
            background: #e6b800;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(249, 204, 34, 0.4);
            color: var(--dark);
        }

        /* FOOTER */
        footer {
            background: linear-gradient(135deg, var(--secondary), var(--dark));
            color: rgba(255,255,255,0.85);
            text-align: center;
            padding: 30px 20px;
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* ANIMATIONS */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }
        .fade-in.appear {
            opacity: 1;
            transform: translateY(0);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* INTERACTIVE ELEMENTS */
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .shake:hover {
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* BACK TO TOP */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), #d05876);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            box-shadow: 0 6px 20px rgba(182, 75, 98, 0.4);
            z-index: 1000;
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .back-to-top:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 8px 25px rgba(182, 75, 98, 0.6);
            background: linear-gradient(135deg, #d05876, var(--primary));
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .app-header, section {
                padding-left: 2rem;
                padding-right: 2rem;
            }
            
            .contact-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .contact-card {
                min-height: 260px;
            }
            .about-combined-card {
                max-width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            .app-header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .logo {
                margin-right: 0;
                width: 100%;
                justify-content: center;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
            }
            
            section {
                padding: 80px 1rem;
            }
            
            .testimoni-combined {
                grid-template-columns: 1fr;
            }
            .about-combined-card {
                grid-template-columns: 1fr;
                max-width: 95%;
            }
            .contact-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .contact-card {
                min-height: 240px;
                min-width: auto;
            }
            .order-container {
                flex-direction: column;
                align-items: center;
            }
            .order-card {
                max-width: 100%;
                width: 100%;
            }
            .nav-links {
                gap: 0.8rem;
            }
            .nav-links a {
                font-size: 0.9rem;
            }
            .logo-main {
                font-size: 1.2rem;
            }
            .logo-icon {
                width: 50px;
                height: 50px;
            }
            #tentang-kami {
                padding: 80px 1rem;
            }
            .about-content {
                padding: 2rem 1.5rem;
            }
            .card-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }
            #beranda h2 {
                font-size: 2.5rem;
            }
            .welcome-text {
                font-size: 2rem;
            }
            .hero-arrow {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 480px) {
            .section-title {
                font-size: 2rem;
            }
            #beranda h2 {
                font-size: 2rem;
            }
            .welcome-text {
                font-size: 1.5rem;
            }
            #beranda p {
                font-size: 1rem;
            }
            .btn {
                padding: 12px 24px;
                font-size: 1rem;
            }
            .hero-arrow {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            .hero-dot {
                width: 10px;
                height: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div class="logo">
                <div class="logo-icon">
                    <img src="<?= htmlspecialchars($header['logo_path']) ?>" alt="Logo <?= htmlspecialchars($header['business_name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="logo-text">
                    <span class="logo-main"><?= htmlspecialchars($header['business_name']) ?></span>
                    <span class="logo-sub"><?= htmlspecialchars($header['tagline']) ?></span>
                </div>
            </div>
            <button class="hamburger" id="hamburger" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-overlay" id="navOverlay"></div>
            <ul class="nav-links">
                <li><a href="#beranda">Beranda</a></li>
                <li><a href="#cara-pesan"><?= htmlspecialchars($cara_title) ?></a></li>
                <li><a href="#tentang-kami"><?= htmlspecialchars($tentang['title']) ?></a></li>
                <li><a href="#testimoni-section">Testimoni</a></li>
                <li><a href="#kontak"><?= htmlspecialchars($kontak_section['title']) ?></a></li>
            </ul>
        </div>
    </header>
    

    <main>
        <!-- ========== HERO SECTION - SELALU DITAMPILKAN ========== -->
        <section id="beranda" class="fade-in">
            <!-- Hero Slider with Navigation -->
            <div class="hero-slider" id="heroSlider">
                <?php foreach ($hero_backgrounds as $index => $bg): ?>
                    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" 
                         style="background-image: url('<?= htmlspecialchars($bg) ?>')"></div>
                <?php endforeach; ?>
            </div>
            
            <!-- Hero Navigation Arrows -->
            <?php if (count($hero_backgrounds) > 1): ?>
                <button class="hero-arrow prev" onclick="changeHeroSlide(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="hero-arrow next" onclick="changeHeroSlide(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <!-- Hero Dots -->
                <div class="hero-nav">
                    <?php foreach ($hero_backgrounds as $index => $bg): ?>
                        <div class="hero-dot <?= $index === 0 ? 'active' : '' ?>" 
                             onclick="goToHeroSlide(<?= $index ?>)"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="hero-content">
                <div class="welcome-text pulse"><?= htmlspecialchars($welcome_text) ?></div>
                <h2><?= htmlspecialchars($header['business_name']) ?></h2>
                <p><?= htmlspecialchars($header['tagline']) ?></p>
                <div style="display: flex; gap: 20px; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <a href="produk.php" class="btn btn-primary shake">
                        <i class="fa-solid fa-cookie-bite"></i> Lihat Produk
                    </a>
                    <a href="https://wa.me/<?= $whatsapp_button['whatsapp_number'] ?>?text=<?= urlencode($whatsapp_message) ?>" 
                       class="btn btn-secondary shake" 
                       target="_blank" 
                       rel="noopener noreferrer">
                        <i class="fa-brands fa-whatsapp"></i> 
                        <?= htmlspecialchars($whatsapp_button['button_text']) ?>
                    </a>
                </div>
            </div>
        </section>
        <!-- ========== END HERO SECTION ========== -->

        <section id="cara-pesan" class="fade-in">
            <h2 class="section-title"><?= htmlspecialchars($cara_title) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars($cara_subtitle) ?></p>
            <div class="order-container">
                <?php foreach ($cara_steps as $step): ?>
                    <div class="order-card" onclick="animateCard(this)">
                        <i class="fa-solid <?= htmlspecialchars($step['icon_class']) ?>"></i>
                        <h3><?= htmlspecialchars($step['step_number']) ?>. <?= htmlspecialchars($step['title']) ?></h3>
                        <p><?= htmlspecialchars($step['description']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="tentang-kami" class="fade-in">
            <h2 class="section-title"><?= htmlspecialchars($tentang['title']) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars($tentang['subtitle']) ?></p>
            <div class="about-combined-card">
                <div class="about-content">
                    <p><?= $tentang['content'] ?></p>
                </div>
                <div class="about-carousel">
                    <div class="about-carousel-wrapper" id="aboutCarouselWrapper">
                        <?php foreach ($photos as $p): ?>
                            <div class="about-carousel-slide">
                                <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['alt_text']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- About Carousel Arrows -->
                    <?php if (count($photos) > 1): ?>
                        <button class="about-carousel-arrow prev" onclick="changeAboutSlide(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="about-carousel-arrow next" onclick="changeAboutSlide(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        
                        <!-- About Carousel Dots -->
                        <div class="about-carousel-nav">
                            <?php foreach ($photos as $index => $p): ?>
                                <div class="about-carousel-dot <?= $index === 0 ? 'active' : '' ?>" 
                                     onclick="goToAboutSlide(<?= $index ?>)"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="testimoni-section" class="fade-in">
            <h2 class="section-title">Testimoni & Kirim Pesan</h2>
            <p class="section-subtitle">Dengar dari pelanggan kami dan bagikan pengalaman Anda!</p>
            <div class="testimoni-combined">
                <div>
                    <h3>Testimoni Pelanggan</h3>
                    <p class="subtitle">3 terbaru — jujur & hangat</p>
                    <div class="testimoni-list">
                        <?php if ($testimoni_terbaru): ?>
                            <?php foreach ($testimoni_terbaru as $t): ?>
                                <div class="testimoni-accordion" onclick="toggleAccordion(this)">
                                    <div class="accordion-header">
                                        <div class="header-content">
                                            <cite><?= htmlspecialchars($t['nama']) ?></cite>
                                            <div class="testimoni-date"><?= date('d M Y', strtotime($t['dikirim_pada'])) ?></div>
                                        </div>
                                        <div class="chevron">
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                    </div>
                                    <div class="accordion-body">
                                        <blockquote>
                                            <p>"<?= nl2br(htmlspecialchars($t['komentar'])) ?>"</p>
                                            <?php if (!empty($t['nama_produk'])): ?>
                                                <div class="testimoni-product">Produk: <strong><?= htmlspecialchars($t['nama_produk']) ?></strong></div>
                                            <?php endif; ?>
                                        </blockquote>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-testimoni">
                                <i class="fas fa-comment"></i>
                                <p>Belum ada testimoni. Jadilah yang pertama!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h3>Kirim Testimoni Anda</h3>
                    <p class="subtitle">Bagikan pengalaman Anda!</p>
                    <?php if (isset($_SESSION['pesan_sukses'])): ?>
                        <div class="alert alert-sukses"><?= htmlspecialchars($_SESSION['pesan_sukses']); ?></div>
                        <?php unset($_SESSION['pesan_sukses']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['pesan_error'])): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['pesan_error']); ?></div>
                        <?php unset($_SESSION['pesan_error']); ?>
                    <?php endif; ?>
                    <form action="kirim_testimoni.php" method="POST" class="form-testimoni" onsubmit="return validateForm()">
                        <div class="form-row">
                            <label for="nama">Nama Anda</label>
                            <input type="text" id="nama" name="nama" placeholder="Contoh: Budi Santoso" required>
                        </div>
                        <div class="form-row">
                            <label for="nama_produk">Nama Produk (Opsional)</label>
                            <input type="text" id="nama_produk" name="nama_produk" placeholder="Contoh: Tahu Crispy">
                        </div>
                        <div class="form-row">
                            <label for="komentar">Testimoni Anda</label>
                            <textarea id="komentar" name="komentar" rows="5" placeholder="Ceritakan pengalaman Anda..." required></textarea>
                        </div>
                        <div class="form-row captcha-container">
                            <label for="captcha">Masukkan Kode CAPTCHA:</label>
                            <div class="captcha-group">
                                <div class="captcha-image-wrapper" onclick="refreshCaptcha()">
                                    <img src="captcha.php" alt="CAPTCHA Image" id="captcha_image">
                                </div>
                                <div class="captcha-input-wrapper">
                                    <input type="text" id="captcha" name="captcha" placeholder="Kode CAPTCHA" required>
                                    <button type="button" class="refresh-btn" 
                                            onclick="refreshCaptcha()" 
                                            title="Refresh CAPTCHA">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary pulse">
                                <i class="fa-solid fa-paper-plane"></i> Kirim Testimoni
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section id="kontak" class="fade-in">
            <h2 class="section-title"><?= htmlspecialchars($kontak_section['title']) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars($kontak_section['subtitle']) ?></p>
            <div class="contact-cards">
                <?php if ($contact_cards): ?>
                    <?php foreach ($contact_cards as $card): ?>
                        <div class="contact-card">
                            <div class="card-icon">
                                <i class="fa <?= htmlspecialchars($card['icon_class']) ?>"></i>
                            </div>
                            <div class="card-title"><?= htmlspecialchars($card['title']) ?></div>
                            <a href="<?= htmlspecialchars($card['href']) ?>"
                               class="contact-link"
                               target="_blank"
                               rel="noopener noreferrer">
                                <?= htmlspecialchars($card['label']) ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-testimoni" style="grid-column: 1 / -1;">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Belum ada kontak yang aktif. Silakan tambahkan via halaman admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <button id="btnBackToTop" class="back-to-top" aria-label="Kembali ke atas" title="Kembali ke atas">
            <i class="fa-solid fa-arrow-up"></i>
        </button>
    </main>

    <footer>
        <p><?= $footerData['copyright_text'] ?> — <?= htmlspecialchars($footerData['main_text']) ?></p>
    </footer>

    <script>
        // STABLE NAVIGATION - Tidak berubah-ubah saat scroll
        document.addEventListener('DOMContentLoaded', function() {
            if ('scrollRestoration' in window.history) window.history.scrollRestoration = 'manual';
            window.scrollTo(0, 0);

            const header = document.querySelector('.app-header');
            
            function getHeaderHeight() {
                return header ? header.offsetHeight : 80;
            }

            // Enhanced fade-in with staggered animation
            const fadeElements = document.querySelectorAll('.fade-in');
            const fadeObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('appear');
                        }, index * 200);
                        fadeObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
            fadeElements.forEach(el => fadeObserver.observe(el));

            // STABLE NAVIGATION SYSTEM
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-links a');
            let currentActive = '';
            
            // Gunakan Intersection Observer untuk navigasi yang lebih stabil
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
                        const id = entry.target.getAttribute('id');
                        if (id && id !== currentActive) {
                            currentActive = id;
                            navLinks.forEach(link => {
                                link.classList.remove('active');
                                if (link.getAttribute('href') === `#${id}`) {
                                    link.classList.add('active');
                                }
                            });
                            
                            // Update URL hash without scrolling
                            if (history.replaceState) {
                                history.replaceState(null, null, `#${id}`);
                            }
                        }
                    }
                });
            }, {
                threshold: [0.1, 0.3, 0.5],
                rootMargin: '-20% 0px -20% 0px'
            });

            sections.forEach(section => sectionObserver.observe(section));

            // Smooth scroll dengan offset
            navLinks.forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (!targetId || targetId === '#') return;
                    const target = document.querySelector(targetId);
                    if (!target) return;

                    // Update active nav immediately
                    navLinks.forEach(link => link.classList.remove('active'));
                    this.classList.add('active');
                    currentActive = targetId.substring(1);

                    const offset = getHeaderHeight() + 20;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = window.scrollY + elementPosition - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Update URL
                    history.pushState(null, null, targetId);
                });
            });

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function() {
                const hash = window.location.hash;
                if (hash) {
                    const target = document.querySelector(hash);
                    if (target) {
                        const offset = getHeaderHeight() + 20;
                        const elementPosition = target.getBoundingClientRect().top;
                        const offsetPosition = window.scrollY + elementPosition - offset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            });

            // Focus form on load if URL hash
            const namaInput = document.getElementById('nama');
            if (namaInput && window.location.hash === '#testimoni-section') {
                setTimeout(() => {
                    namaInput.focus();
                    namaInput.style.borderColor = '#B64B62';
                    setTimeout(() => { namaInput.style.borderColor = ''; }, 2000);
                }, 400);
            }

            // Back to top
            const btnBackToTop = document.getElementById('btnBackToTop');
            if (btnBackToTop) {
                function updateScrollButton() {
                    btnBackToTop.classList.toggle('show', window.scrollY > 400);
                }
                window.addEventListener('scroll', updateScrollButton);
                updateScrollButton();
                btnBackToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
            }

            // Add click effects to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Initialize active nav based on current hash
            function setActiveNavFromHash() {
                const hash = window.location.hash;
                if (hash) {
                    currentActive = hash.substring(1);
                    navLinks.forEach(link => link.classList.remove('active'));
                    const activeLink = document.querySelector(`.nav-links a[href="${hash}"]`);
                    if (activeLink) activeLink.classList.add('active');
                }
            }
            setActiveNavFromHash();
        });

        // Hero Slider Functions
        let currentHeroSlide = 0;
        const heroSlides = document.querySelectorAll('.hero-slide');
        const heroDots = document.querySelectorAll('.hero-dot');

        function showHeroSlide(index) {
            heroSlides.forEach(slide => slide.classList.remove('active'));
            heroDots.forEach(dot => dot.classList.remove('active'));
            
            currentHeroSlide = index;
            heroSlides[currentHeroSlide].classList.add('active');
            heroDots[currentHeroSlide].classList.add('active');
        }

        function changeHeroSlide(direction) {
            let newIndex = currentHeroSlide + direction;
            if (newIndex < 0) newIndex = heroSlides.length - 1;
            if (newIndex >= heroSlides.length) newIndex = 0;
            showHeroSlide(newIndex);
        }

        function goToHeroSlide(index) {
            showHeroSlide(index);
        }

        // Auto-advance hero slider
        if (heroSlides.length > 1) {
            setInterval(() => {
                changeHeroSlide(1);
            }, 5000);
        }

        // About Carousel Functions
        let currentAboutSlide = 0;
        const aboutSlides = document.querySelectorAll('.about-carousel-slide');
        const aboutDots = document.querySelectorAll('.about-carousel-dot');
        const aboutWrapper = document.getElementById('aboutCarouselWrapper');

        function showAboutSlide(index) {
            aboutWrapper.style.transform = `translateX(-${index * 100}%)`;
            aboutDots.forEach(dot => dot.classList.remove('active'));
            aboutDots[index].classList.add('active');
            currentAboutSlide = index;
        }

        function changeAboutSlide(direction) {
            let newIndex = currentAboutSlide + direction;
            if (newIndex < 0) newIndex = aboutSlides.length - 1;
            if (newIndex >= aboutSlides.length) newIndex = 0;
            showAboutSlide(newIndex);
        }

        function goToAboutSlide(index) {
            showAboutSlide(index);
        }

        // Auto-advance about carousel
        if (aboutSlides.length > 1) {
            setInterval(() => {
                changeAboutSlide(1);
            }, 4000);
        }

        // Interactive Functions
        function animateCard(card) {
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.style.transform = '';
            }, 150);
        }

        function toggleAccordion(accordion) {
            const isActive = accordion.classList.contains('active');
            
            document.querySelectorAll('.testimoni-accordion').forEach(el => {
                el.classList.remove('active');
                el.querySelector('.accordion-body').style.maxHeight = '0';
            });
            
            if (!isActive) {
                accordion.classList.add('active');
                accordion.querySelector('.accordion-body').style.maxHeight = 
                    accordion.querySelector('.accordion-body').scrollHeight + 'px';
            }
        }

        function refreshCaptcha() {
            const captchaImage = document.getElementById('captcha_image');
            captchaImage.src = 'captcha.php?' + new Date().getTime();
        }

        function validateForm() {
            const nama = document.getElementById('nama').value;
            const komentar = document.getElementById('komentar').value;
            const captcha = document.getElementById('captcha').value;

            if (!nama || !komentar || !captcha) {
                alert('Harap lengkapi semua field yang wajib diisi!');
                return false;
            }

            if (komentar.length < 10) {
                alert('Testimoni harus minimal 10 karakter!');
                return false;
            }

            return true;
        }

        // Add ripple effect style
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
            }
            
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .btn {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

    // Hamburger Menu - PERBAIKAN
    const hamburger = document.getElementById('hamburger');
    const navLinksMenu = document.querySelector('.nav-links'); // Gunakan class bukan ID
    const navOverlay = document.getElementById('navOverlay');

    function toggleMobileMenu() {
        if (hamburger && navLinksMenu && navOverlay) {
            hamburger.classList.toggle('active');
            navLinksMenu.classList.toggle('active');
            navOverlay.classList.toggle('active');
            document.body.style.overflow = navLinksMenu.classList.contains('active') ? 'hidden' : '';
        }
    }

    function closeMobileMenu() {
        if (hamburger && navLinksMenu && navOverlay) {
            hamburger.classList.remove('active');
            navLinksMenu.classList.remove('active');
            navOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (hamburger) {
        hamburger.addEventListener('click', toggleMobileMenu);
    }

    if (navOverlay) {
        navOverlay.addEventListener('click', closeMobileMenu);
    }

    // Close menu when clicking navigation link
    document.querySelectorAll('.nav-links a').forEach(anchor => {
        anchor.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && navLinksMenu && navLinksMenu.classList.contains('active')) {
            closeMobileMenu();
        }
    });

    // Handle resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && navLinksMenu && navLinksMenu.classList.contains('active')) {
            closeMobileMenu();
        }
    });
    </script>
</body>
</html>