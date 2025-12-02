<?php
session_start();
include "../config.php";

// Default to current month/year
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Validate month/year
$bulan = ($bulan < 1 || $bulan > 12) ? date('n') : $bulan;
$tahun = ($tahun < 2020 || $tahun > 2030) ? date('Y') : $tahun;

// Get month name helper
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
        return htmlspecialchars($produkJson ?: '–');
    }

    $list = [];
    foreach ($items as $item) {
        $nama = htmlspecialchars($item['nama'] ?? '–');
        $qty  = (int)($item['qty'] ?? 1);
        $list[] = "{$nama} × {$qty}";
    }
    return !empty($list) ? implode('<br>', $list) : '–';
}

$bulan_nama = namaBulan($bulan);

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=laporan_keuangan_'.$bulan.'_'.$tahun.'.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Tanggal', 'Nama Pelanggan', 'Produk', 'Total (Rp)', 'Status']);
    
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
        // Format produk untuk CSV (satu baris, dipisah koma)
        $items = json_decode($row['produk'], true);
        $produk_str = '';
        if (is_array($items)) {
            $parts = [];
            foreach ($items as $item) {
                $nama = $item['nama'] ?? '–';
                $qty = (int)($item['qty'] ?? 1);
                $parts[] = "{$nama} ×{$qty}";
            }
            $produk_str = implode(', ', $parts);
        } else {
            $produk_str = $row['produk'];
        }

        fputcsv($output, [
            $counter,
            date('d M Y H:i', strtotime($row['created_at'])),
            $row['nama_pelanggan'],
            $produk_str, // <-- produk
            number_format($row['total'], 0, ',', '.'),
            $row['status']
        ]);
        $counter++;
    }
    
    // Add total row
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

    fputcsv($output, ['', '', '', 'TOTAL', number_format($total, 0, ',', '.'), '']);
    fclose($output);
    exit;
}

// Get data for display
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

// Get total revenue
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
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        .container-fluid { margin: 0; padding: 0;}
        body {background: #f1e8fdff; }
        .filter-container { background: white; padding: 15px;  margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table th { background-color: #DFBEE0; font-weight: 600;  }
        .total-row { font-weight: bold; background-color: #DFBEE0 !important; }
        .download-btn {
            background: #5A46A2;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px #4a3a8a;
        }
        .table-container {
          padding: 15px;
        }
        
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Filter Form -->
        <div class="filter-container mb-4">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>>
                                <?= namaBulan($i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($y = 2020; $y <= 2030; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="download-btn w-100">
                        <i class="mdi mdi-filter me-2"></i>Tampilkan
                    </button>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" onclick="downloadCSV()" class="download-btn w-100">
                        <i class="mdi mdi-download me-2"></i>Download CSV
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
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
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">Tidak ada data penjualan untuk <?= $bulan_nama ?> <?= $tahun ?></div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="text-center"><?= $counter++ ?></td>
                                    <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($order['nama_pelanggan']) ?></td>
                                    <td><?= formatProduk($order['produk']) ?></td>
                                    <td class="text-end"><?= number_format($order['total'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Total Row -->
                            <tr class="total-row">
                                <td colspan="4" class="text-end fw-bold">TOTAL PENDAPATAN:</td>
                                <td class="text-end fw-bold">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
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

