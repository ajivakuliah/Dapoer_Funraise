<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../config.php";

$error = '';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><div style="padding:2rem;max-width:600px;margin:2rem auto;background:#fee;border-radius:8px;color:#c00;font-family:sans-serif;"><h2>❌ Koneksi Database Gagal</h2><p>File <code>config.php</code> tidak menyediakan variabel <code>$pdo</code> yang valid.</p></div></body></html>');
}
$stmtHeader = $pdo->query("SELECT logo_path, business_name, tagline FROM header WHERE id = 1");
$header = $stmtHeader->fetch(PDO::FETCH_ASSOC);
if (!$header) {
    $header = [
        'logo_path' => 'assets/logo.png',
        'business_name' => 'Dapoer Funraise',
        'tagline' => 'Cemilan rumahan yang bikin nagih!'
    ];
}

$bulan_pilihan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun_pilihan = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($bulan_pilihan < 1 || $bulan_pilihan > 12) {
    $bulan_pilihan = (int)date('n');
}
if ($tahun_pilihan < 2020 || $tahun_pilihan > 2030) {
    $tahun_pilihan = (int)date('Y');
}

try {
    $month_start = sprintf('%04d-%02d-01 00:00:00', $tahun_pilihan, $bulan_pilihan);
    $month_end   = date('Y-m-t 23:59:59', strtotime($month_start));

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS total_pendapatan FROM pesanan WHERE status = 'selesai' AND created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $pendapatan = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $pesanan_masuk = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'selesai' AND created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $pesanan_selesai = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produk");
    $stmt->execute();
    $jumlah_produk = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM testimoni");
    $stmt->execute();
    $jumlah_testimoni = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'batal' AND created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $pesanan_dibatalkan = (int) $stmt->fetchColumn();

    $grafik_pendapatan = [];
    for ($i = 11; $i >= 0; $i--) {
        $bulan_grafik = date('Y-m', strtotime("-$i months", strtotime($month_start)));
        $tahun_bulan = explode('-', $bulan_grafik);
        $tahun_g = $tahun_bulan[0];
        $bulan_g = $tahun_bulan[1];
        
        $start_g = sprintf('%04d-%02d-01 00:00:00', $tahun_g, $bulan_g);
        $end_g = date('Y-m-t 23:59:59', strtotime($start_g));
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pesanan WHERE status = 'selesai' AND created_at BETWEEN :start AND :end");
        $stmt->execute(['start' => $start_g, 'end' => $end_g]);
        $pendapatan_bulan = (float) $stmt->fetchColumn();
        
        $grafik_pendapatan[] = [
            'bulan' => date('M Y', strtotime($start_g)),
            'pendapatan' => $pendapatan_bulan
        ];
    }

    $produk_terlaris_grafik = [];
    
    $stmtFooter = $pdo->prepare("SELECT main_text, copyright_text FROM footer_section WHERE id = 1 AND is_active = 1");
    $stmtFooter->execute();
    $footerData = $stmtFooter->fetch(PDO::FETCH_ASSOC);
    if (!$footerData) {
        $footerData = [
            'main_text' => 'Mendukung Expo Campus MAN 2 Samarinda',
            'copyright_text' => '© 2025 <strong>Dapoer Funraise</strong>'
        ];
    }
    $stmt = $pdo->prepare("SELECT produk, total FROM pesanan WHERE status = 'selesai' AND created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $all_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $produk_data = [];
    $total_terjual_all = 0;
    
    foreach ($all_pesanan as $pesanan) {
        $produk_list = @json_decode($pesanan['produk'], true);
        
        if (is_array($produk_list)) {
            foreach ($produk_list as $produk_item) {
                if (isset($produk_item['nama'])) {
                    $nama_produk = $produk_item['nama'];
                    $jumlah = $produk_item['jumlah'] ?? 1;
                    
                    if (!isset($produk_data[$nama_produk])) {
                        $produk_data[$nama_produk] = [
                            'terjual' => 0,
                            'pendapatan' => 0
                        ];
                    }
                    $produk_data[$nama_produk]['terjual'] += $jumlah;
                    $produk_data[$nama_produk]['pendapatan'] += $produk_item['subtotal'] ?? 0;
                    $total_terjual_all += $jumlah;
                }
            }
        } else {
            $produk_string = $pesanan['produk'];
            
            if (preg_match('/(.*?)\s*\((\d+)\)/', $produk_string, $matches)) {
                $nama_produk = trim($matches[1]);
                $jumlah = (int)$matches[2];
                $subtotal = $pesanan['total'];
                
                if (!isset($produk_data[$nama_produk])) {
                    $produk_data[$nama_produk] = [
                        'terjual' => 0,
                        'pendapatan' => 0
                    ];
                }
                $produk_data[$nama_produk]['terjual'] += $jumlah;
                $produk_data[$nama_produk]['pendapatan'] += $subtotal;
                $total_terjual_all += $jumlah;
            } else {
                $produk_array = array_map('trim', explode(',', $produk_string));
                $jumlah_produk = count($produk_array);
                $subtotal_per_produk = $jumlah_produk > 0 ? $pesanan['total'] / $jumlah_produk : 0;
                
                foreach ($produk_array as $produk_nama) {
                    if (!empty($produk_nama)) {
                        $nama_produk = $produk_nama;
                        if (!isset($produk_data[$nama_produk])) {
                            $produk_data[$nama_produk] = [
                                'terjual' => 0,
                                'pendapatan' => 0
                            ];
                        }
                        $produk_data[$nama_produk]['terjual'] += 1;
                        $produk_data[$nama_produk]['pendapatan'] += $subtotal_per_produk;
                        $total_terjual_all += 1;
                    }
                }
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT id, Nama, Foto_Produk FROM produk");
    $stmt->execute();
    $all_produk = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $produk_terlaris_grafik = [];
    $counter = 0;
    foreach ($all_produk as $produk) {
        $nama_produk = $produk['Nama'];
        if (isset($produk_data[$nama_produk])) {
            $produk_terlaris_grafik[] = [
                'nama_produk' => $nama_produk,
                'foto_produk' => $produk['Foto_Produk'],
                'total_terjual' => $produk_data[$nama_produk]['terjual'],
                'total_pendapatan' => $produk_data[$nama_produk]['pendapatan'],
                'persentase' => $total_terjual_all > 0 ? round(($produk_data[$nama_produk]['terjual'] / $total_terjual_all) * 100, 1) : 0
            ];
            $counter++;
            if ($counter >= 6) break;
        }
    }
    
    if ($counter < 6) {
        foreach ($produk_data as $nama_produk => $data) {
            if ($counter >= 6) break;
            
            $produk_ada = false;
            foreach ($produk_terlaris_grafik as $produk_grafik) {
                if ($produk_grafik['nama_produk'] == $nama_produk) {
                    $produk_ada = true;
                    break;
                }
            }
            
            if (!$produk_ada) {
                $produk_terlaris_grafik[] = [
                    'nama_produk' => $nama_produk,
                    'foto_produk' => null,
                    'total_terjual' => $data['terjual'],
                    'total_pendapatan' => $data['pendapatan'],
                    'persentase' => $total_terjual_all > 0 ? round(($data['terjual'] / $total_terjual_all) * 100, 1) : 0
                ];
                $counter++;
            }
        }
    }
    
    usort($produk_terlaris_grafik, function($a, $b) {
        return $b['total_terjual'] - $a['total_terjual'];
    });

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
    $pendapatan = $pesanan_masuk = $pesanan_selesai = $pesanan_dibatalkan = $jumlah_produk = $jumlah_testimoni = 0;
    $grafik_pendapatan = [];
    $produk_terlaris_grafik = [];
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatRupiahShort($angka) {
    if ($angka >= 1000000000) {
        return 'Rp ' . number_format($angka / 1000000000, 1, ',', '.') . 'M';
    } elseif ($angka >= 1000000) {
        return 'Rp ' . number_format($angka / 1000000, 1, ',', '.') . 'Jt';
    } elseif ($angka >= 1000) {
        return 'Rp ' . number_format($angka / 1000, 1, ',', '.') . 'K';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function namaBulan($bulan) {
    $nama = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $nama[(int)$bulan] ?? 'Bulan';
}
$bulan_ini = namaBulan($bulan_pilihan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard Admin • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin/IndexAdmin.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2>
                <span class="logo-main"><?= htmlspecialchars($header['business_name']) ?></span>
            </h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="active" onclick="showSection('dashboard'); return false;">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('produk'); return false;">
                    <i class="fas fa-box"></i>
                    <span>Daftar Produk</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('pesanan'); return false;">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Kelola Pesanan</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('testimoni'); return false;">
                    <i class="fas fa-comments"></i>
                    <span>Testimoni</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('laporan'); return false;">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Laporan Keuangan</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('pengaturan'); return false;">
                    <i class="fas fa-cogs"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
            <li style="margin-top: 30px;">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
            </li>
        </ul>
    </aside>

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="topbar-right">
            <div class="user-profile">
                <div class="user-avatar">
                    A
                </div>
                <div class="user-info">
                    <span class="user-name">Administrator</span>
                    <span class="user-role">Admin Dapoer Funraise</span>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <div id="dashboard-section" class="content-section active">
            <form method="GET" action="" class="filter-section">
                <div class="filter-group">
                    <span class="filter-label">
                        <i class="fas fa-calendar-alt"></i>
                        Periode Laporan:
                    </span>
                    <select name="bulan" class="filter-select">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $bulan_pilihan ? 'selected' : '' ?>>
                            <?= namaBulan($i) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                    <select name="tahun" class="filter-select">
                        <?php for ($y = 2020; $y <= 2030; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun_pilihan ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-apply">
                    <i class="fas fa-filter"></i>
                    Terapkan Filter
                </button>
            </form>

            <div class="period-display">
                <i class="fas fa-info-circle"></i> 
                Menampilkan data untuk periode: 
                <span><?= $bulan_ini ?> <?= $tahun_pilihan ?></span>
            </div>

            <div class="stats-grid">
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Pendapatan</div>
                            <div class="stat-value"><?= formatRupiah($pendapatan) ?></div>
                            <div class="stat-description">Pesanan selesai di <?= $bulan_ini ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Produk</div>
                            <div class="stat-value"><?= $jumlah_produk ?></div>
                            <div class="stat-description">Produk tersedia di katalog</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Testimoni</div>
                            <div class="stat-value"><?= $jumlah_testimoni ?></div>
                            <div class="stat-description">Ulasan dari pelanggan</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pesanan Masuk</div>
                            <div class="stat-value"><?= $pesanan_masuk ?></div>
                            <div class="stat-description">Total pesanan di <?= $bulan_ini ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card primary">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pesanan Selesai</div>
                            <div class="stat-value"><?= $pesanan_selesai ?></div>
                            <div class="stat-description">Transaksi berhasil di <?= $bulan_ini ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card dark">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pesanan Dibatalkan</div>
                            <div class="stat-value"><?= $pesanan_dibatalkan ?></div>
                            <div class="stat-description">Transaksi batal di <?= $bulan_ini ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Grafik Pendapatan 12 Bulan Terakhir
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="pendapatanChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Distribusi Produk Terlaris di <?= $bulan_ini ?> <?= $tahun_pilihan ?>
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="produkChart"></canvas>
                    </div>
                </div>
            </div>

            </div>

        <div id="produk-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="daftar_produk.php" title="Daftar Produk"></iframe>
                </div>
            </div>
        </div>

        <div id="pesanan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="pesanan.php" title="Kelola Pesanan"></iframe>
                </div>
            </div>
        </div>

        <div id="testimoni-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="testimoni.php" title="Testimoni"></iframe>
                </div>
            </div>
        </div>

        <div id="laporan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="laporan_keuangan.php" title="Laporan Keuangan"></iframe>
                </div>
            </div>
        </div>

        <div id="pengaturan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="pengaturan.php" title="Pengaturan"></iframe>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p><?= $footerData['copyright_text'] ?> — <?= $footerData['main_text'] ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const pendapatanData = <?= json_encode($grafik_pendapatan) ?>;
        const produkData = <?= json_encode($produk_terlaris_grafik) ?>;
    </script>
    <script src="../js/admin/IndexAdmin.js"></script>
</body>
</html>