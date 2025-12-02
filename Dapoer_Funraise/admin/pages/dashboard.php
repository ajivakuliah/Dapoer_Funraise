<?php
// Ambil data untuk dashboard
try {
    // Data statistik untuk dashboard
    $current_month = date('Y-m');
    $stmt_revenue = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total_pendapatan 
        FROM pesanan 
        WHERE status = 'selesai' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt_revenue->execute([$current_month]);
    $pendapatan_bulan_ini = (float)$stmt_revenue->fetchColumn();

    $stmt_produk = $pdo->query("SELECT COUNT(*) FROM produk");
    $total_produk = (int)$stmt_produk->fetchColumn();

    $stmt_testimoni = $pdo->query("SELECT COUNT(*) FROM testimoni");
    $total_testimoni = (int)$stmt_testimoni->fetchColumn();

    $stmt_pesanan_baru = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'baru'");
    $stmt_pesanan_baru->execute();
    $pesanan_masuk = (int)$stmt_pesanan_baru->fetchColumn();

    $stmt_pesanan_diproses = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'diproses'");
    $stmt_pesanan_diproses->execute();
    $pesanan_diproses = (int)$stmt_pesanan_diproses->fetchColumn();

    $stmt_pesanan_selesai = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'selesai'");
    $stmt_pesanan_selesai->execute();
    $pesanan_selesai = (int)$stmt_pesanan_selesai->fetchColumn();

    $stmt_pesanan_batal = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'batal'");
    $stmt_pesanan_batal->execute();
    $pesanan_batal = (int)$stmt_pesanan_batal->fetchColumn();

    // Data untuk grafik pendapatan 6 bulan terakhir
    $six_months_ago = date('Y-m-01', strtotime('-5 months'));
    $current_month_start = date('Y-m-01');
    
    $stmt_revenue_chart = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as bulan,
            COALESCE(SUM(total), 0) as total_pendapatan
        FROM pesanan 
        WHERE status = 'selesai' 
        AND DATE_FORMAT(created_at, '%Y-%m') >= ?
        AND DATE_FORMAT(created_at, '%Y-%m') <= ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY bulan ASC
    ");
    $stmt_revenue_chart->execute([$six_months_ago, $current_month_start]);
    $revenue_data = $stmt_revenue_chart->fetchAll(PDO::FETCH_ASSOC);

    // Siapkan data untuk 6 bulan terakhir
    $monthly_revenue = [];
    $month_labels = [];
    
    // Buat array untuk 6 bulan terakhir
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        $month_labels[] = $month_label;
        $monthly_revenue[$month] = 0;
    }
    
    // Isi dengan data dari database
    foreach ($revenue_data as $row) {
        $monthly_revenue[$row['bulan']] = (float)$row['total_pendapatan'];
    }
    
    // Konversi ke array untuk Chart.js
    $revenue_values = array_values($monthly_revenue);
    $revenue_labels = $month_labels;

    // Data untuk grafik produk terlaris
    $stmt_top_products = $pdo->prepare("
        SELECT 
            p.Nama as nama_produk,
            COUNT(*) as jumlah_terjual
        FROM pesanan pes,
        JSON_TABLE(
            pes.produk,
            '$[*]' COLUMNS (
                nama VARCHAR(100) PATH '$.nama',
                qty INT PATH '$.qty'
            )
        ) AS p
        WHERE pes.status = 'selesai'
        GROUP BY p.Nama
        ORDER BY jumlah_terjual DESC
        LIMIT 5
    ");
    $stmt_top_products->execute();
    $top_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);

    // Data untuk status pesanan
    $order_status_data = [
        'baru' => $pesanan_masuk,
        'diproses' => $pesanan_diproses,
        'selesai' => $pesanan_selesai,
        'batal' => $pesanan_batal
    ];

    // Data untuk trend pendapatan
    $current_month_revenue = $pendapatan_bulan_ini;
    $last_month = date('Y-m', strtotime('-1 month'));
    
    $stmt_last_month_revenue = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total_pendapatan 
        FROM pesanan 
        WHERE status = 'selesai' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt_last_month_revenue->execute([$last_month]);
    $last_month_revenue = (float)$stmt_last_month_revenue->fetchColumn();
    
    $revenue_change = $last_month_revenue > 0 ? 
        (($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100 : 0;
    
    // Data pesanan hari ini
    $today = date('Y-m-d');
    $stmt_today_orders = $pdo->prepare("
        SELECT COUNT(*) as total_pesanan_hari_ini 
        FROM pesanan 
        WHERE DATE(created_at) = ?
    ");
    $stmt_today_orders->execute([$today]);
    $pesanan_hari_ini = (int)$stmt_today_orders->fetchColumn();
    
    // Pendapatan hari ini
    $stmt_today_revenue = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total_pendapatan_hari_ini 
        FROM pesanan 
        WHERE status = 'selesai' AND DATE(created_at) = ?
    ");
    $stmt_today_revenue->execute([$today]);
    $pendapatan_hari_ini = (float)$stmt_today_revenue->fetchColumn();

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $pendapatan_bulan_ini = $total_produk = $total_testimoni = $pesanan_masuk = 
    $pesanan_diproses = $pesanan_selesai = $pesanan_batal = $pesanan_hari_ini = 
    $pendapatan_hari_ini = 0;
    $revenue_values = array_fill(0, 6, 0);
    $revenue_labels = [];
    $top_products = [];
    $order_status_data = ['baru' => 0, 'diproses' => 0, 'selesai' => 0, 'batal' => 0];
    $revenue_change = 0;
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function getRevenueTrendIcon($change) {
    if ($change > 0) {
        return '<i class="fas fa-arrow-up text-success"></i> ' . number_format($change, 1) . '%';
    } elseif ($change < 0) {
        return '<i class="fas fa-arrow-down text-danger"></i> ' . number_format(abs($change), 1) . '%';
    } else {
        return '<i class="fas fa-minus text-muted"></i> 0%';
    }
}
?>
<style>
    /* Dashboard Styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        position: relative;
        border: 2px solid #dfbee0;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-info {
        flex: 1;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
        color: #5a46a2;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 0.3rem;
    }

    .stat-trend {
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .text-success { color: #10b981; }
    .text-danger { color: #ef4444; }
    .text-muted { color: #9ca3af; }

    .charts-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: 2px solid #dfbee0;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .chart-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #5a46a2;
    }

    .chart-container {
        height: 300px;
        position: relative;
    }

    .year-filter {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .recent-orders {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: 2px solid #dfbee0;
        margin-bottom: 2rem;
    }

    .recent-orders h3 {
        font-size: 1.2rem;
        font-weight: 600;
        color: #5a46a2;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.3s ease;
    }

    .order-item:hover {
        background: #f9fafb;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .order-customer {
        font-weight: 500;
        color: #374151;
    }

    .order-amount {
        font-weight: 600;
        color: #5a46a2;
    }

    .order-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-new { background: #dbeafe; color: #1e40af; }
    .status-processing { background: #fef3c7; color: #92400e; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }

    @media (max-width: 1024px) {
        .charts-section {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-container {
            height: 250px;
        }
    }
</style>

<div class="content-wrapper">
    <?php if (isset($error_message)): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <p><?= htmlspecialchars($error_message) ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i>
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= formatRupiah($pendapatan_bulan_ini) ?></div>
                <div class="stat-label">Pendapatan Bulan Ini</div>
                <div class="stat-trend">
                    <?= getRevenueTrendIcon($revenue_change) ?>
                    <span style="font-size: 0.8rem; color: #9ca3af;">vs bulan lalu</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #5a46a2, #4a3a8a);">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $total_produk ?></div>
                <div class="stat-label">Total Produk</div>
                <div class="stat-trend">
                    <i class="fas fa-box-open"></i>
                    <span style="font-size: 0.8rem; color: #9ca3af;">produk aktif</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $total_testimoni ?></div>
                <div class="stat-label">Total Testimoni</div>
                <div class="stat-trend">
                    <i class="fas fa-star"></i>
                    <span style="font-size: 0.8rem; color: #9ca3af;">ulasan pelanggan</span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $pesanan_hari_ini ?></div>
                <div class="stat-label">Pesanan Hari Ini</div>
                <div class="stat-trend">
                    <i class="fas fa-clock"></i>
                    <span style="font-size: 0.8rem; color: #9ca3af;"><?= date('d M Y') ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= formatRupiah($pendapatan_hari_ini) ?></div>
                <div class="stat-label">Pendapatan Hari Ini</div>
                <div class="stat-trend">
                    <i class="fas fa-calendar-day"></i>
                    <span style="font-size: 0.8rem; color: #9ca3af;"><?= date('d M Y') ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $pesanan_batal ?></div>
                <div class="stat-label">Pesanan Dibatalkan</div>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span style="font-size: 0.8rem; color: #9ca3af;">bulan ini</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">Pendapatan 6 Bulan Terakhir</div>
                <div class="year-filter">
                    <select id="yearSelect" class="form-control" style="width: 120px; padding: 5px 10px; border: 2px solid #dfbee0; border-radius: 6px;">
                        <option value="6months">6 Bulan</option>
                        <option value="12months">12 Bulan</option>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">Status Pesanan</div>
            </div>
            <div class="chart-container">
                <canvas id="orderChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Produk Terlaris -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">5 Produk Terlaris</div>
        </div>
        <div class="chart-container">
            <canvas id="topProductsChart"></canvas>
        </div>
    </div>

    <!-- Pesanan Terbaru -->
    <?php
    try {
        $stmt_recent_orders = $pdo->prepare("
            SELECT id, nama_pelanggan, total, status, created_at 
            FROM pesanan 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt_recent_orders->execute();
        $recent_orders = $stmt_recent_orders->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_orders = [];
    }
    ?>
    
    <?php if (!empty($recent_orders)): ?>
    <div class="recent-orders">
        <h3>Pesanan Terbaru</h3>
        <?php foreach ($recent_orders as $order): ?>
        <div class="order-item">
            <div>
                <div class="order-customer">#<?= $order['id'] ?> - <?= htmlspecialchars($order['nama_pelanggan']) ?></div>
                <div style="font-size: 0.85rem; color: #6b7280;">
                    <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                </div>
            </div>
            <div class="order-amount"><?= formatRupiah($order['total']) ?></div>
            <div class="order-status status-<?= $order['status'] ?>">
                <?= $order['status'] ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data dari PHP
        const revenueData = <?= json_encode($revenue_values) ?>;
        const revenueLabels = <?= json_encode($revenue_labels) ?>;
        const orderStatusData = <?= json_encode(array_values($order_status_data)) ?>;
        const topProducts = <?= json_encode($top_products) ?>;
        
        // Revenue Chart (Line Chart untuk trend)
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: revenueData,
                    backgroundColor: 'rgba(90, 70, 162, 0.1)',
                    borderColor: '#5a46a2',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#5a46a2',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'Rp ' + (value / 1000000).toFixed(1) + 'Jt';
                                } else if (value >= 1000) {
                                    return 'Rp ' + (value / 1000).toFixed(0) + 'K';
                                }
                                return 'Rp ' + value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Order Status Chart (Doughnut)
        const orderCtx = document.getElementById('orderChart').getContext('2d');
        const orderChart = new Chart(orderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Baru', 'Diproses', 'Selesai', 'Dibatalkan'],
                datasets: [{
                    data: orderStatusData,
                    backgroundColor: [
                        '#3b82f6', // Biru untuk baru
                        '#f59e0b', // Kuning untuk diproses
                        '#10b981', // Hijau untuk selesai
                        '#ef4444'  // Merah untuk batal
                    ],
                    borderWidth: 1,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} pesanan (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Top Products Chart (Horizontal Bar)
        const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
        
        // Siapkan data untuk chart
        const productLabels = topProducts.map(p => p.nama_produk);
        const productData = topProducts.map(p => p.jumlah_terjual);
        const backgroundColors = [
            '#5a46a2',
            '#b64b62',
            '#f9cc22',
            '#10b981',
            '#3b82f6'
        ];
        
        const topProductsChart = new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Jumlah Terjual',
                    data: productData,
                    backgroundColor: backgroundColors,
                    borderColor: backgroundColors.map(color => color + 'CC'),
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Terjual: ${context.parsed.x} unit`;
                            }
                        }
                    }
                }
            }
        });

        // Filter tahun untuk revenue chart
        document.getElementById('yearSelect').addEventListener('change', function() {
            const period = this.value;
            // Di sini bisa ditambahkan logika untuk mengubah periode data
            // Misalnya: fetch data 12 bulan jika dipilih
            console.log('Periode dipilih:', period);
            
            // Untuk sekarang, kita hanya log ke console
            // Implementasi fetching data bisa ditambahkan jika diperlukan
        });

        // Auto-refresh dashboard setiap 5 menit
        setInterval(() => {
            fetch('?page=dashboard&refresh=1')
                .then(response => response.text())
                .then(html => {
                    // Parsing HTML dan update bagian-bagian tertentu
                    console.log('Dashboard refreshed at', new Date().toLocaleTimeString());
                })
                .catch(error => console.error('Refresh error:', error));
        }, 300000); // 5 menit
    });
</script>