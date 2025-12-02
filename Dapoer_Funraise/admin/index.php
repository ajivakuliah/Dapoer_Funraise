<?php
// === KONEKSI & AMBIL DATA ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../config.php";

$error = '';

// Validasi koneksi PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><div style="padding:2rem;max-width:600px;margin:2rem auto;background:#fee;border-radius:8px;color:#c00;font-family:sans-serif;"><h2>❌ Koneksi Database Gagal</h2><p>File <code>config.php</code> tidak menyediakan variabel <code>$pdo</code> yang valid.</p></div></body></html>');
}

// 🔹 AMBIL PARAMETER BULAN & TAHUN DARI GET
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

    // Query data dasar
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

    // 🔹 DATA UNTUK GRAFIK PENDAPATAN 12 BULAN TERAKHIR
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

    // 🔹 DATA UNTUK GRAFIK PRODUK TERLARIS (TOP 6 untuk grafik lingkaran)
    $produk_terlaris_grafik = [];
    
    // Ambil semua pesanan selesai
    $stmt = $pdo->prepare("SELECT produk, total FROM pesanan WHERE status = 'selesai' AND created_at BETWEEN :start AND :end");
    $stmt->execute(['start' => $month_start, 'end' => $month_end]);
    $all_pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Proses data produk dari pesanan
    $produk_data = [];
    $total_terjual_all = 0;
    
    foreach ($all_pesanan as $pesanan) {
        // Coba decode JSON dari kolom produk
        $produk_list = @json_decode($pesanan['produk'], true);
        
        if (is_array($produk_list)) {
            // Format JSON: [{"nama": "Tahu Crispy", "jumlah": 2, "subtotal": 30000}, ...]
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
            // Format string biasa: "Tahu Crispy, Pisang Coklat" atau "Tahu Crispy (2)"
            $produk_string = $pesanan['produk'];
            
            // Coba parse format seperti "Tahu Crispy (2)"
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
                // Ambil semua produk yang dipisah koma
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
    
    // Ambil info produk dari tabel produk
    $stmt = $pdo->prepare("SELECT id, Nama, Foto_Produk FROM produk");
    $stmt->execute();
    $all_produk = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gabungkan data untuk grafik (TOP 6)
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
            if ($counter >= 6) break; // Ambil hanya 6 untuk grafik lingkaran
        }
    }
    
    // Tambahkan produk yang tidak ada di tabel produk tapi ada di data penjualan
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
    
    // Urutkan berdasarkan yang paling banyak terjual
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
      * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
      }
      
      body {
        font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f1e8fdff;
        overflow-x: hidden;
      }

      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        width: 260px;
        background: linear-gradient(180deg, #2a1f3d 100%, #5A46A2 0%);
        padding: 0px 0;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(90, 70, 162, 0.1);
        transition: all 0.3s ease;
      }

      .sidebar-brand {
        padding: 19px 19px;
        margin-bottom: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }

      .sidebar-brand h2 {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
      }

      .sidebar-brand i {
        color: #F9CC22;
        font-size: 1.8rem;
      }

      .sidebar-menu {
        list-style: none;
        padding: 0 15px;
      }

      .sidebar-menu li {
        margin-bottom: 8px;
      }

      .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 14px 20px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 15px;
        font-weight: 500;
        border-left: 3px solid transparent;
      }

      .sidebar-menu a:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        transform: translateX(5px);
        border-left-color: #F9CC22;
      }

      .sidebar-menu a.active {
        background: linear-gradient(135deg, #B64B62 0%, #8e3a4d 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(182, 75, 98, 0.4);
        border-left-color: #F9CC22;
      }

      .sidebar-menu i {
        margin-right: 12px;
        font-size: 20px;
        width: 24px;
        text-align: center;
      }

      .topbar {
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        height: 70px;
        background: #2a1f3d;
        border-bottom: 1px solid #eae6ff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        z-index: 999;
        box-shadow: 0 2px 8px rgba(90, 70, 162, 0.05);
      }

      .topbar-left h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #5A46A2;
        margin: 0;
      }

      .topbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
      }

      .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px;
        background: #fbf9ff;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid #eae6ff;
      }

      .user-profile:hover {
        background: #f5f3ff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(90, 70, 162, 0.1);
      }

      .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #5A46A2, #B64B62);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1rem;
      }

      .user-info {
        display: flex;
        flex-direction: column;
      }

      .user-name {
        font-size: 14px;
        font-weight: 600;
        color: #2a1f3d;
      }

      .user-role {
        font-size: 12px;
        color: #9180BB;
      }

      .main-content {
        margin-left: 260px;
        margin-top: 70px;
        padding: 30px;
        min-height: calc(100vh - 70px);
        background: #f8f6fd;
      }

      .filter-section {
        background: white;
        padding: 22px 28px;
        border-radius: 16px;
        margin-bottom: 30px;
        box-shadow: 0 4px 16px rgba(90, 70, 162, 0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        border: 1px solid #eae6ff;
      }

      .filter-group {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
      }

      .filter-label {
        font-size: 14px;
        font-weight: 600;
        color: #5A46A2;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .filter-select {
        padding: 12px 18px;
        border: 2px solid #DFBEE0;
        border-radius: 10px;
        font-size: 14px;
        color: #2a1f3d;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 160px;
        font-family: 'Poppins', sans-serif;
      }

      .filter-select:focus {
        outline: none;
        border-color: #5A46A2;
        box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.15);
      }

      .btn-apply {
        padding: 12px 28px;
        background: linear-gradient(135deg, #5A46A2 0%, #B64B62 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: 'Poppins', sans-serif;
      }

      .btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(90, 70, 162, 0.3);
        background: linear-gradient(135deg, #6b54c1 0%, #d05876 100%);
      }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
      }

      .stat-card {
        background: white;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 4px 16px rgba(90, 70, 162, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid #eae6ff;
      }

      .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(90, 70, 162, 0.15);
      }

      .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        opacity: 0.08;
        transform: translate(30%, -30%);
      }

      .stat-card.danger::before { background: #B64B62; }
      .stat-card.success::before { background: #48bb78; }
      .stat-card.info::before { background: #5A46A2; }
      .stat-card.warning::before { background: #F9CC22; }
      .stat-card.primary::before { background: #9180BB; }
      .stat-card.dark::before { background: #2a1f3d; }

      .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
      }

      .stat-title {
        font-size: 14px;
        font-weight: 600;
        color: #9180BB;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }

      .stat-card.danger .stat-icon { 
        background: linear-gradient(135deg, #B64B62 0%, #d05876 100%); 
      }
      .stat-card.success .stat-icon { 
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); 
      }
      .stat-card.info .stat-icon { 
        background: linear-gradient(135deg, #5A46A2 0%, #9180BB 100%); 
      }
      .stat-card.warning .stat-icon { 
        background: linear-gradient(135deg, #F9CC22 0%, #ffd84d 100%); 
      }
      .stat-card.primary .stat-icon { 
        background: linear-gradient(135deg, #9180BB 0%, #DFBEE0 100%); 
      }
      .stat-card.dark .stat-icon { 
        background: linear-gradient(135deg, #2a1f3d 0%, #4a3a8a 100%); 
      }

      .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: #2a1f3d;
        margin-bottom: 8px;
        line-height: 1;
      }

      .stat-description {
        font-size: 14px;
        color: #9180BB;
        font-weight: 500;
      }

      .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
      }

      @media (max-width: 1200px) {
        .charts-grid {
          grid-template-columns: 1fr;
        }
      }

      .chart-card {
        background: white;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 4px 16px rgba(90, 70, 162, 0.08);
        border: 1px solid #eae6ff;
      }

      .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
      }

      .chart-title {
        font-size: 18px;
        font-weight: 600;
        color: #2a1f3d;
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .chart-title i {
        color: #5A46A2;
      }

      .chart-container {
        position: relative;
        height: 320px;
        width: 100%;
      }

      .period-display {
        background: linear-gradient(135deg, #fbf9ff, #f5f3ff);
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 24px;
        border: 1px solid #eae6ff;
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .period-display i {
        color: #5A46A2;
      }

      .period-display span {
        color: #5A46A2;
        font-weight: 600;
      }

      .content-section {
        display: none;
        animation: fadeIn 0.3s ease;
      }

      .content-section.active {
        display: block;
      }

      .iframe-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(90, 70, 162, 0.08);
        overflow: hidden;
        border: 1px solid #eae6ff;
      }

      .iframe-container iframe {
        width: 100%;
        height: 85vh;
        min-height: 600px;
        border: none;
        display: block;
      }

      .alert-error {
        background: #ffebee;
        border-left: 4px solid #B64B62;
        padding: 18px 22px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .alert-error i {
        color: #B64B62;
        font-size: 24px;
      }

      .alert-error p {
        margin: 0;
        color: #c53030;
        font-weight: 500;
        font-size: 15px;
      }

      @media (max-width: 992px) {
        .sidebar {
          transform: translateX(-100%);
          width: 280px;
        }

        .sidebar.active {
          transform: translateX(0);
        }

        .topbar {
          left: 0;
          padding: 0 20px;
        }

        .main-content {
          margin-left: 0;
          padding: 20px;
        }

        .stats-grid {
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .charts-grid {
          grid-template-columns: 1fr;
        }

        .chart-card {
          padding: 20px;
        }

        .chart-container {
          height: 280px;
        }

        .filter-section {
          padding: 18px 22px;
        }
      }

      @media (max-width: 768px) {
        .topbar-left h2 {
          font-size: 1.3rem;
        }

        .main-content {
          padding: 16px;
        }

        .filter-section {
          flex-direction: column;
          align-items: stretch;
        }

        .filter-group {
          flex-direction: column;
          align-items: stretch;
        }

        .filter-select {
          width: 100%;
        }

        .stats-grid {
          grid-template-columns: 1fr;
          gap: 18px;
        }

        .stat-card {
          padding: 22px;
        }

        .stat-value {
          font-size: 32px;
        }

        .charts-grid {
          gap: 18px;
        }

        .chart-card {
          padding: 18px;
        }

        .chart-container {
          height: 250px;
        }

        .iframe-container iframe {
          height: 70vh;
          min-height: 500px;
        }
      }

      .mobile-toggle {
        display: none;
        font-size: 24px;
        color: white;
        cursor: pointer;
        background: none;
        border: none;
        margin-right: 15px;
      }

      @media (max-width: 992px) {
        .mobile-toggle {
          display: block;
        }
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .no-data {
        text-align: center;
        padding: 40px 20px;
        color: #9180BB;
      }

      .no-data i {
        font-size: 48px;
        margin-bottom: 16px;
        color: #DFBEE0;
      }

      .no-data h3 {
        font-size: 18px;
        margin-bottom: 8px;
        color: #5A46A2;
      }

      .no-data p {
        font-size: 14px;
      }

      /* Styling untuk chart lingkaran produk */
      .pie-chart-container {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
      }

      .chart-legend {
        margin-top: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
      }

      .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: #f9f7ff;
        border-radius: 8px;
        font-size: 12px;
        color: #5A46A2;
        border: 1px solid #eae6ff;
      }

      .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 3px;
      }

      .legend-info {
        display: flex;
        flex-direction: column;
      }

      .legend-name {
        font-weight: 600;
      }

      .legend-stats {
        font-size: 10px;
        color: #9180BB;
      }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2>
                <i class="fas fa-utensils"></i>
                Dapoer Funraise
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

    <!-- Topbar -->
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

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard-section" class="content-section active">
            <!-- Filter Section -->
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

            <!-- Period Display -->
            <div class="period-display">
                <i class="fas fa-info-circle"></i> 
                Menampilkan data untuk periode: 
                <span><?= $bulan_ini ?> <?= $tahun_pilihan ?></span>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <!-- Pendapatan -->
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

                <!-- Produk -->
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

                <!-- Testimoni -->
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

                <!-- Pesanan Masuk -->
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

                <!-- Pesanan Selesai -->
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

                <!-- Pesanan Dibatalkan -->
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

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Grafik Pendapatan 12 Bulan -->
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

                <!-- Grafik Produk Terlaris -->
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

            <!-- Tabel Produk Terlaris DIHAPUS sesuai permintaan -->
        </div>

        <!-- Daftar Produk -->
        <div id="produk-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="daftar_produk.php" title="Daftar Produk"></iframe>
                </div>
            </div>
        </div>

        <!-- Kelola Pesanan -->
        <div id="pesanan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="pesanan.php" title="Kelola Pesanan"></iframe>
                </div>
            </div>
        </div>

        <!-- Testimoni -->
        <div id="testimoni-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="testimoni.php" title="Testimoni"></iframe>
                </div>
            </div>
        </div>

        <!-- Laporan Keuangan -->
        <div id="laporan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="laporan_keuangan.php" title="Laporan Keuangan"></iframe>
                </div>
            </div>
        </div>

        <!-- Pengaturan -->
        <div id="pengaturan-section" class="content-section">
            <div class="card">
                <div class="iframe-container">
                    <iframe src="pengaturan.php" title="Pengaturan"></iframe>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Data untuk grafik dari PHP
        const pendapatanData = <?= json_encode($grafik_pendapatan) ?>;
        const produkData = <?= json_encode($produk_terlaris_grafik) ?>;

        // Fungsi untuk inisialisasi grafik
        function initCharts() {
            // Grafik Pendapatan
            if (pendapatanData.length > 0 && pendapatanData.some(item => item.pendapatan > 0)) {
                const ctx1 = document.getElementById('pendapatanChart').getContext('2d');
                const labels = pendapatanData.map(item => item.bulan);
                const data = pendapatanData.map(item => item.pendapatan);
                
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Pendapatan',
                            data: data,
                            borderColor: '#B64B62',
                            backgroundColor: 'rgba(182, 75, 98, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#B64B62',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Rp ' + context.raw.toLocaleString('id-ID');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000000) {
                                            return 'Rp ' + (value / 1000000).toFixed(1) + 'Jt';
                                        }
                                        if (value >= 1000) {
                                            return 'Rp ' + (value / 1000).toFixed(1) + 'K';
                                        }
                                        return 'Rp ' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                // Tampilkan pesan jika tidak ada data
                document.getElementById('pendapatanChart').parentElement.innerHTML = `
                    <div class="no-data">
                        <i class="fas fa-chart-line"></i>
                        <h3>Tidak ada data pendapatan</h3>
                        <p>Belum ada pendapatan yang tercatat dalam 12 bulan terakhir.</p>
                    </div>
                `;
            }

            // Grafik Produk Terlaris (Pie Chart)
            if (produkData.length > 0) {
                const ctx2 = document.getElementById('produkChart').getContext('2d');
                const labels = produkData.map(item => {
                    // Potong nama produk jika terlalu panjang
                    const nama = item.nama_produk;
                    return nama.length > 15 ? nama.substring(0, 12) + '...' : nama;
                });
                const data = produkData.map(item => item.total_terjual);
                
                // Warna untuk pie chart (menggunakan tema Dapoer Funraise)
                const backgroundColors = [
                    'rgba(90, 70, 162, 0.8)',    // Ungu tua
                    'rgba(182, 75, 98, 0.8)',    // Merah muda
                    'rgba(249, 204, 34, 0.8)',   // Kuning
                    'rgba(72, 187, 120, 0.8)',   // Hijau
                    'rgba(42, 31, 61, 0.8)',     // Ungu sangat tua
                    'rgba(144, 128, 187, 0.8)',  // Ungu muda
                    'rgba(223, 190, 224, 0.8)',  // Ungu sangat muda
                    'rgba(248, 246, 253, 0.8)'   // Putih ungu
                ];

                const borderColors = [
                    'rgb(90, 70, 162)',
                    'rgb(182, 75, 98)',
                    'rgb(249, 204, 34)',
                    'rgb(72, 187, 120)',
                    'rgb(42, 31, 61)',
                    'rgb(144, 128, 187)',
                    'rgb(223, 190, 224)',
                    'rgb(248, 246, 253)'
                ];

                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: backgroundColors.slice(0, produkData.length),
                            borderColor: borderColors.slice(0, produkData.length),
                            borderWidth: 2,
                            borderAlign: 'inner'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false, // Sembunyikan legend default
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const index = context.dataIndex;
                                        const produk = produkData[index];
                                        return [
                                            `${produk.nama_produk}`,
                                            `Terjual: ${produk.total_terjual} item (${produk.persentase}%)`,
                                            `Pendapatan: Rp ${produk.total_pendapatan.toLocaleString('id-ID')}`
                                        ];
                                    }
                                }
                            }
                        },
                        cutout: '60%',
                        animation: {
                            animateScale: true,
                            animateRotate: true,
                            duration: 2000,
                            easing: 'easeOutQuart'
                        }
                    }
                });

                // Tambahkan custom legend di bawah chart
                const legendContainer = document.createElement('div');
                legendContainer.className = 'chart-legend';
                
                produkData.forEach((produk, index) => {
                    const legendItem = document.createElement('div');
                    legendItem.className = 'legend-item';
                    
                    const colorBox = document.createElement('div');
                    colorBox.className = 'legend-color';
                    colorBox.style.backgroundColor = borderColors[index];
                    
                    const legendInfo = document.createElement('div');
                    legendInfo.className = 'legend-info';
                    
                    const legendName = document.createElement('div');
                    legendName.className = 'legend-name';
                    legendName.textContent = produk.nama_produk;
                    
                    const legendStats = document.createElement('div');
                    legendStats.className = 'legend-stats';
                    legendStats.textContent = `${produk.total_terjual} item (${produk.persentase}%)`;
                    
                    legendInfo.appendChild(legendName);
                    legendInfo.appendChild(legendStats);
                    
                    legendItem.appendChild(colorBox);
                    legendItem.appendChild(legendInfo);
                    legendContainer.appendChild(legendItem);
                });
                
                document.getElementById('produkChart').parentElement.appendChild(legendContainer);
            } else {
                // Tampilkan pesan jika tidak ada data
                document.getElementById('produkChart').parentElement.innerHTML = `
                    <div class="no-data">
                        <i class="fas fa-chart-pie"></i>
                        <h3>Tidak ada data produk terlaris</h3>
                        <p>Belum ada produk yang terjual pada periode ini.</p>
                    </div>
                `;
            }
        }

        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected section
            const target = document.getElementById(section + '-section');
            if (target) {
                target.classList.add('active');
            }
            
            // Update active menu
            document.querySelectorAll('.sidebar-menu a').forEach(el => {
                el.classList.remove('active');
            });
            
            const menuItem = document.querySelector(`.sidebar-menu a[onclick*="${section}"]`);
            if (menuItem) {
                menuItem.classList.add('active');
            }
            
            // Close sidebar on mobile
            if (window.innerWidth <= 992) {
                document.getElementById('sidebar').classList.remove('active');
            }

            // Jika kembali ke dashboard, inisialisasi ulang grafik
            if (section === 'dashboard') {
                setTimeout(initCharts, 100);
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close sidebar when resizing to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });

        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            showSection('dashboard');
            initCharts();
            
            // Handle iframe loading
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                iframe.onload = function() {
                    console.log('Iframe loaded:', iframe.src);
                };
                
                iframe.onerror = function() {
                    console.error('Iframe failed to load:', iframe.src);
                    iframe.contentDocument.body.innerHTML = `
                        <div style="padding: 3rem; text-align: center; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h3>Gagal memuat halaman</h3>
                            <p>Pastikan file "${iframe.src}" ada di lokasi yang benar.</p>
                        </div>
                    `;
                };
            });
        });
    </script>
</body>
</html>