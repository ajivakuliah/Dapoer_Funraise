CREATE DATABASE IF NOT EXISTS dapoer_funraise;
USE dapoer_funraise;

-- Table structure for header
CREATE TABLE header (
    id INT PRIMARY KEY AUTO_INCREMENT,
    logo_path VARCHAR(255) NOT NULL DEFAULT 'assets/logo.png',
    business_name VARCHAR(100) NOT NULL DEFAULT 'Dapoer Funraise',
    tagline VARCHAR(150) NOT NULL DEFAULT 'Cemilan rumahan yang bikin nagih!',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for hero section
CREATE TABLE hero_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    welcome_text VARCHAR(200) NOT NULL DEFAULT 'Selamat Datang di Dapoer Funraise',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure untuk multiple hero backgrounds
CREATE TABLE hero_backgrounds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hero_section_id INT,
    background_path VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hero_section_id) REFERENCES hero_section(id) ON DELETE CASCADE
);

-- Table structure for tentang_kami_section
CREATE TABLE tentang_kami_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL DEFAULT 'Tentang Kami',
    subtitle VARCHAR(255) NOT NULL DEFAULT 'Dapur kecil, dampak besar untuk pendidikan',
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for carousel_photos
CREATE TABLE carousel_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(150),
    caption VARCHAR(300),
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for cara_pesan_section
CREATE TABLE cara_pesan_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL DEFAULT 'Cara Pesan',
    subtitle VARCHAR(255) NOT NULL DEFAULT 'Mudah dan cepat, hanya dalam 4 langkah',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for cara_pesan_steps
CREATE TABLE cara_pesan_steps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    icon_class VARCHAR(100) NOT NULL DEFAULT 'fa-cookie-bite',
    step_number TINYINT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for produk
CREATE TABLE produk (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Nama VARCHAR(100) NOT NULL,
    Harga DECIMAL(15,2) NOT NULL,
    Varian TEXT,
    Deskripsi_Produk TEXT,
    Kategori VARCHAR(100),
    Status ENUM('aktif', 'tidak_aktif') DEFAULT 'aktif',
    Foto_Produk VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for pesanan
CREATE TABLE pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    produk TEXT NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    pengambilan ENUM('ambil','antar') NOT NULL,
    metode_bayar ENUM('Tunai','transfer') NOT NULL,
    status ENUM('baru','diproses','selesai','batal') DEFAULT 'baru',
    whatsapp_link TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for testimoni
CREATE TABLE testimoni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    nama_produk VARCHAR(100),
    komentar TEXT NOT NULL,
    rating INT DEFAULT 5,
    is_verified TINYINT(1) DEFAULT 0,
    dikirim_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for kontak_section
CREATE TABLE kontak_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL DEFAULT 'Kontak',
    subtitle VARCHAR(255) NOT NULL DEFAULT 'Siap melayani pesanan Anda dengan senang hati',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure for contact_cards
CREATE TABLE contact_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    icon_class VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL,
    label VARCHAR(100) NOT NULL,
    href VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for footer_section
CREATE TABLE footer_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    main_text TEXT NOT NULL,
    copyright_text VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Table structure for site_settings
CREATE TABLE site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table structure untuk WhatsApp buttons (tombol pesan sekarang)
CREATE TABLE whatsapp_buttons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    button_text VARCHAR(100) NOT NULL DEFAULT 'Pesan Sekarang',
    whatsapp_number VARCHAR(20) NOT NULL DEFAULT '6283129704643',
    is_active BOOLEAN DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========== INSERT DEFAULT DATA ==========

-- Insert default header data
INSERT INTO header (logo_path, business_name, tagline) VALUES 
('assets/logo.png', 'Dapoer Funraise', 'Cemilan rumahan yang bikin nagih!');

-- Insert default hero section data
INSERT INTO hero_section (background_path, welcome_text) VALUES 
('assets/bg1.jpg', 'Selamat Datang di Dapoer Funraise');

-- Insert multiple hero backgrounds
INSERT INTO hero_backgrounds (hero_section_id, background_path, sort_order) VALUES 
(1, 'assets/bg1.jpg', 1),
(1, 'assets/bg2.jpg', 2),
(1, 'assets/bg3.jpg', 3);

-- Insert default tentang kami data
INSERT INTO tentang_kami_section (title, subtitle, content) VALUES 
('Tentang Kami', 'Dapur kecil, dampak besar untuk pendidikan', 
'Dapoer Funraise adalah wujud kepedulian alumni MAN 2 Samarinda dalam mendukung <strong>Expo Campus MAN 2 Samarinda</strong> — acara tahunan untuk memperkenalkan perguruan tinggi kepada siswa. Seluruh keuntungan penjualan cemilan digunakan untuk kebutuhan acara: konsumsi, dekorasi, dan logistik. Kami percaya: bisnis kecil bisa berdampak besar!');

-- Insert default carousel photos
INSERT INTO carousel_photos (image_path, alt_text, caption, sort_order) VALUES 
('assets/kegiatan1.jpg', 'Tim Dapoer Funraise', 'Tim solid Dapoer Funraise', 1),
('assets/kegiatan2.jpg', 'Kegiatan Expo Campus 2024', 'Momen seru Expo Campus', 2),
('assets/kegiatan3.jpg', 'Proses pembuatan cemilan', 'Proses produksi yang higienis', 3),
('assets/kegiatan4.jpg', 'Distribusi cemilan ke acara', 'Pengiriman tepat waktu', 4);

-- Insert default cara pesan section
INSERT INTO cara_pesan_section (title, subtitle) VALUES 
('Cara Pesan', 'Mudah dan cepat, hanya dalam 4 langkah');

-- Insert default cara pesan steps
INSERT INTO cara_pesan_steps (step_number, title, description, icon_class, sort_order) VALUES 
(1, 'Pilih Produk', 'Pilih produk favorit Anda dari katalog kami', 'fa-list', 1),
(2, 'Hubungi Kami', 'Kirim pesan via WhatsApp dengan detail pesanan', 'fa-whatsapp', 2),
(3, 'Konfirmasi Pesanan', 'Tim kami akan mengkonfirmasi ketersediaan produk', 'fa-check-circle', 3),
(4, 'Pembayaran & Pengiriman', 'Lakukan pembayaran dan tunggu pesanan sampai', 'fa-shipping-fast', 4);

-- Insert sample produk data
INSERT INTO produk (Nama, Harga, Varian, Deskripsi_Produk, Kategori, Foto_Produk) VALUES 
('Tahu Crispy', 15000, 'Original, Pedas, Keju', 'Tahu goreng crispy dengan berbagai pilihan rasa', 'Makanan Ringan', 'assets/tahu-crispy.jpg'),
('Pisang Coklat', 12000, 'Coklat, Keju, Matcha', 'Pisang bakar dengan topping coklat yang lumer', 'Makanan Penutup', 'assets/pisang-coklat.jpg'),
('Lumpia Basah', 20000, 'Ayam, Sayur, Udang', 'Lumpia segar dengan isian yang melimpah', 'Makanan Utama', 'assets/lumpia-basah.jpg');

-- Insert sample testimoni data
INSERT INTO testimoni (nama, nama_produk, komentar, rating, is_verified) VALUES 
('Budi Santoso', 'Tahu Crispy', 'Rasanya enak banget! Crispy diluar lembut didalam. Pelayanannya juga cepat dan ramah.', 5, 1),
('Sari Indah', 'Pisang Coklat', 'Pisang coklatnya fresh dan coklatnya tidak terlalu manis. Anak-anak suka sekali!', 5, 1),
('Ahmad Rizki', 'Lumpia Basah', 'Lumpianya masih hangat ketika sampai. Isiannya banyak dan bumbunya pas.', 4, 1);

-- Insert default kontak section
INSERT INTO kontak_section (title, subtitle) VALUES 
('Hubungi Kami', 'Siap melayani pesanan Anda dengan senang hati');

-- Insert default contact cards
INSERT INTO contact_cards (icon_class, title, label, href, sort_order) VALUES 
('fa-whatsapp', 'WhatsApp', '+62 831-2970-4643', 'https://wa.me/6283129704643', 1),
('fa-instagram', 'Instagram', '@dapoerfunraise', 'https://instagram.com/dapoerfunraise', 2),
('fa-envelope', 'Email', 'dapoerfunraise@gmail.com', 'mailto:dapoerfunraise@gmail.com', 3),
('fa-map-marker-alt', 'Lokasi', 'MAN 2 Samarinda', 'https://maps.google.com/?q=MAN+2+Samarinda', 4);

-- Insert default footer data
INSERT INTO footer_section (main_text, copyright_text) VALUES 
('Mendukung Expo Campus MAN 2 Samarinda', '© 2025 <strong>Dapoer Funraise</strong>');

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@dapoerfunraise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'superadmin');

-- Insert default site settings
INSERT INTO site_settings (setting_key, setting_value, setting_description) VALUES 
('site_name', 'Dapoer Funraise', 'Nama website'),
('site_description', 'Cemilan rumahan untuk mendukung pendidikan', 'Deskripsi website'),
('contact_phone', '+6283129704643', 'Nomor telepon utama'),
('contact_email', 'dapoerfunraise@gmail.com', 'Email utama'),
('whatsapp_default_message', 'Halo, saya ingin memesan produk Dapoer Funraise', 'Pesan default WhatsApp'),
('hero_auto_rotate', '1', 'Rotasi otomatis hero section');

-- Insert WhatsApp buttons data
INSERT INTO whatsapp_buttons (button_text, whatsapp_number, whatsapp_message, button_style, position, sort_order) VALUES 
('Pesan Sekarang', '6283129704643', 'Halo, saya ingin memesan produk Dapoer Funraise', 'secondary', 'hero', 1),
('Chat via WhatsApp', '6283129704643', 'Halo, saya ada pertanyaan tentang produk Dapoer Funraise', 'primary', 'floating', 2);

-- Create indexes for better performance
CREATE INDEX idx_produk_status ON produk(Status);
CREATE INDEX idx_produk_kategori ON produk(Kategori);
CREATE INDEX idx_testimoni_verified ON testimoni(is_verified);
CREATE INDEX idx_pesanan_status ON pesanan(status);
CREATE INDEX idx_cara_pesan_steps_active ON cara_pesan_steps(is_active, sort_order);
CREATE INDEX idx_contact_cards_active ON contact_cards(is_active, sort_order);
CREATE INDEX idx_whatsapp_buttons_active ON whatsapp_buttons(is_active, position);