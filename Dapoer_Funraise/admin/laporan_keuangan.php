<?php
session_start();
include "../config.php";

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

$bulan = ($bulan < 1 || $bulan > 12) ? date('n') : $bulan;
$tahun = ($tahun < 2020 || $tahun > 2030) ? date('Y') : $tahun;

function namaBulan($bulan) {
    $nama = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return $nama[(int)$bulan] ?? 'Bulan';
}

function formatProduk($produkJson) {
    $items = json_decode($produkJson, true);
    if (!is_array($items)) {
        return htmlspecialchars($produkJson ?: '—');
    }

    $list = [];
    foreach ($items as $item) {
        $nama = htmlspecialchars($item['nama'] ?? '—');
        $qty  = (int)($item['qty'] ?? 1);
        $varian = !empty($item['varian']) ? ' (' . htmlspecialchars($item['varian']) . ')' : '';
        $list[] = "{$nama}{$varian} × {$qty}";
    }
    return !empty($list) ? implode('<br>', $list) : '—';
}

$bulan_nama = namaBulan($bulan);

if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename=laporan_keuangan_'.$bulan.'_'.$tahun.'.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['No', 'Tanggal', 'Waktu', 'Nama Pelanggan', 'Produk', 'Qty', 'Total (Rp)', 'Status']);
    
    $start_date = "$tahun-$bulan-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT id, created_at, nama_pelanggan, produk, total, status 
        FROM pesanan 
        WHERE status = 'selesai' 
        AND DATE(created_at) BETWEEN :start AND :end
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        'start' => $start_date . ' 00:00:00',
        'end' => $end_date . ' 23:59:59'
    ]);
    
    $counter = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        $items = json_decode($row['produk'], true);
        
        if (is_array($items) && !empty($items)) {
            foreach ($items as $idx => $item) {
                $nama = $item['nama'] ?? '—';
                $varian = !empty($item['varian']) ? ' (' . $item['varian'] . ')' : '';
                $qty = (int)($item['qty'] ?? 1);
                
                if ($idx === 0) {
                    fputcsv($output, [
                        $counter,
                        date('d/m/Y', strtotime($row['created_at'])),
                        date('H:i', strtotime($row['created_at'])),
                        $row['nama_pelanggan'],
                        $nama . $varian,
                        $qty,
                        $row['total'],
                        ucfirst($row['status'])
                    ]);
                } else {
                    fputcsv($output, [
                        '',
                        '',
                        '',
                        '',
                        $nama . $varian,
                        $qty,
                        '',
                        ''
                    ]);
                }
            }
        } else {
            fputcsv($output, [
                $counter,
                date('d/m/Y', strtotime($row['created_at'])),
                date('H:i', strtotime($row['created_at'])),
                $row['nama_pelanggan'],
                $row['produk'],
                '-',
                $row['total'],
                ucfirst($row['status'])
            ]);
        }
        
        $counter++;
    }
    
    fputcsv($output, ['', '', '', '', '', '', '', '']);
    
    $stmt = $pdo->prepare("
        SELECT SUM(total) as total_pendapatan 
        FROM pesanan 
        WHERE status = 'selesai' 
        AND DATE(created_at) BETWEEN :start AND :end
    ");
    $stmt->execute([
        'start' => $start_date . ' 00:00:00',
        'end' => $end_date . ' 23:59:59'
    ]);
    $total = $stmt->fetchColumn();

    fputcsv($output, ['', '', '', '', '', 'TOTAL PENDAPATAN', $total, '']);
    fclose($output);
    exit;
}

$start_date = "$tahun-$bulan-01";
$end_date = date('Y-m-t', strtotime($start_date));

$stmt = $pdo->prepare("
    SELECT id, created_at, nama_pelanggan, produk, total, status 
    FROM pesanan 
    WHERE status = 'selesai' 
    AND DATE(created_at) BETWEEN :start AND :end
    ORDER BY created_at DESC
");
$stmt->execute([
    'start' => $start_date . ' 00:00:00',
    'end' => $end_date . ' 23:59:59'
]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT SUM(total) as total_pendapatan 
    FROM pesanan 
    WHERE status = 'selesai' 
    AND DATE(created_at) BETWEEN :start AND :end
");
$stmt->execute([
    'start' => $start_date . ' 00:00:00',
    'end' => $end_date . ' 23:59:59'
]);
$total_pendapatan = (float)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan • Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f6fd;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #5A46A2;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(90, 70, 162, 0.08);
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #5A46A2;
            margin-bottom: 0.5rem;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e8d9ff;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: white;
        }

        .form-select:focus {
            outline: none;
            border-color: #5A46A2;
            box-shadow: 0 0 0 4px rgba(90, 70, 162, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5A46A2 0%, #7B68B8 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90, 70, 162, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .stats-card {
            background: linear-gradient(135deg, #5A46A2 0%, #7B68B8 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            color: white;
            box-shadow: 0 4px 20px rgba(90, 70, 162, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(90, 70, 162, 0.08);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #DFBEE0 0%, #d4a9d5 100%);
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #5A46A2;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th.text-center {
            text-align: center;
        }

        th.text-end {
            text-align: right;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }

        tbody tr:hover {
            background: #f9f6ff;
        }

        td {
            padding: 1rem;
            font-size: 0.9rem;
            color: #333;
        }

        td.text-center {
            text-align: center;
        }

        td.text-end {
            text-align: right;
        }

        .produk-item {
            padding: 0.25rem 0;
            color: #555;
        }

        .total-row {
            background: linear-gradient(135deg, #DFBEE0 0%, #d4a9d5 100%) !important;
            font-weight: 700;
            color: #5A46A2 !important;
        }

        .total-row td {
            padding: 1.25rem 1rem;
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }

            .filter-form {
                flex-direction: column;
            }

            .form-group {
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Laporan Keuangan
            </h1>
            <p class="page-subtitle">Laporan penjualan dan pendapatan bulanan</p>
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i> Bulan
                    </label>
                    <select name="bulan" class="form-select">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>>
                                <?= namaBulan($i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Tahun
                    </label>
                    <select name="tahun" class="form-select">
                        <?php for ($y = 2020; $y <= 2030; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                </div>
                <div class="form-group">
                    <button type="button" onclick="downloadCSV()" class="btn btn-success">
                        <i class="fas fa-download"></i> Download CSV
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($orders)): ?>
        <div class="stats-card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-calendar-check"></i> Periode
                    </div>
                    <div class="stat-value"><?= $bulan_nama ?> <?= $tahun ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-receipt"></i> Total Transaksi
                    </div>
                    <div class="stat-value"><?= count($orders) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">
                        <i class="fas fa-money-bill-wave"></i> Total Pendapatan
                    </div>
                    <div class="stat-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th>Tanggal Pesanan</th>
                            <th>Nama Pelanggan</th>
                            <th>Produk</th>
                            <th class="text-end">Total (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Tidak ada data penjualan</h3>
                                    <p>Belum ada transaksi selesai untuk <?= $bulan_nama ?> <?= $tahun ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="text-center"><?= $counter++ ?></td>
                                    <td><?= date('d M Y • H:i', strtotime($order['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($order['nama_pelanggan']) ?></td>
                                    <td><?= formatProduk($order['produk']) ?></td>
                                    <td class="text-end"><?= number_format($order['total'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="4" class="text-end">
                                    <i class="fas fa-coins"></i> TOTAL PENDAPATAN
                                </td>
                                <td class="text-end">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function downloadCSV() {
            const bulan = document.querySelector('select[name="bulan"]').value;
            const tahun = document.querySelector('select[name="tahun"]').value;
            window.location.href = `?bulan=${bulan}&tahun=${tahun}&download=csv`;
        }
    </script>
</body>
</html>