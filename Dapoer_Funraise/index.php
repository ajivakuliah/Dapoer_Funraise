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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($header['business_name']) ?> - <?= htmlspecialchars($header['tagline']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Dapoer_Funraise/css/index.css">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($header['logo_path']) ?>">
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
            
            <!-- Mobile hamburger button -->
            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle menu" aria-expanded="false">
                ☰
            </button>
            
            <!-- Desktop navigation -->
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
        <section id="beranda" class="fade-in">
            <div class="hero-slider" id="heroSlider">
                <?php foreach ($hero_backgrounds as $index => $bg): ?>
                    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" 
                         style="background-image: url('<?= htmlspecialchars($bg) ?>')"></div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($hero_backgrounds) > 1): ?>
                <button class="hero-arrow prev" onclick="changeHeroSlide(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="hero-arrow next" onclick="changeHeroSlide(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <div class="hero-nav">
                    <?php foreach ($hero_backgrounds as $index => $bg): ?>
                        <div class="hero-dot <?= $index === 0 ? 'active' : '' ?>" 
                             onclick="goToHeroSlide(<?= $index ?>)"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="hero-content">
                <h2 class="welcome-text"><?= htmlspecialchars($welcome_text) ?></h2>
                <p><?= htmlspecialchars($header['tagline']) ?></p>

                <div class="hero-buttons">
                    <a href="produk.php" class="btn btn-primary">Lihat Produk</a>
                    <a href="https://wa.me/<?= $whatsapp_button['whatsapp_number'] ?>?text=<?= urlencode($whatsapp_message) ?>" 
                       class="btn btn-secondary"
                       target="_blank" 
                       rel="noopener noreferrer">
                        <?= htmlspecialchars($whatsapp_button['button_text']) ?>
                    </a>
                </div>
            </div>
        </section>

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
                                <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['alt_text']) ?>" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($photos) > 1): ?>
                        <button class="about-carousel-arrow prev" onclick="changeAboutSlide(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="about-carousel-arrow next" onclick="changeAboutSlide(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        
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
                                    <img src="captcha.php" alt="CAPTCHA Image" id="captcha_image" loading="lazy">
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

    <script src="../Dapoer_Funraise/js/index.js"></script>
</body>
</html>