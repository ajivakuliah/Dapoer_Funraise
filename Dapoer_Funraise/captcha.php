<?php
session_start();

// 1. Definisikan karakter yang akan digunakan
$characters = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
$length = 6;
$captcha_string = '';

// 2. Hasilkan string acak
for ($i = 0; $i < $length; $i++) {
    $captcha_string .= $characters[rand(0, strlen($characters) - 1)];
}

// 3. Simpan string CAPTCHA di session
$_SESSION['captcha_code'] = $captcha_string;

// 4. Buat gambar CAPTCHA
$image_width = 120;
$image_height = 40;
$image = imagecreate($image_width, $image_height);

// Warna-warna
$background_color = imagecolorallocate($image, 255, 255, 255); // Putih
$text_color = imagecolorallocate($image, 0, 0, 0);             // Hitam
$line_color = imagecolorallocate($image, 180, 180, 180);       // Abu-abu

// Isi latar belakang dengan warna putih
imagefill($image, 0, 0, $background_color);

// Tambahkan garis/titik acak untuk mempersulit bot (noise)
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand(0, $image_height), $image_width, rand(0, $image_height), $line_color);
}
for ($i = 0; $i < 50; $i++) {
    imagesetpixel($image, rand(0, $image_width), rand(0, $image_height), $line_color);
}

// Tulis teks CAPTCHA (menggunakan font bawaan PHP GD: font 5)
$font_size = 5;
$x = 10;
$y = 10;
imagestring($image, $font_size, $x, $y, $captcha_string, $text_color);

// Kirim header dan output gambar
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>