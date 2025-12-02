// Fungsi untuk inisialisasi grafik
function initCharts() {
    // Pastikan variabel data global sudah tersedia
    if (typeof pendapatanData === 'undefined' || typeof produkData === 'undefined') {
        console.error('Data grafik (pendapatanData atau produkData) tidak ditemukan.');
        return;
    }

    // Grafik Pendapatan
    const pendapatanChartElement = document.getElementById('pendapatanChart');
    if (pendapatanChartElement) {
        if (pendapatanData.length > 0 && pendapatanData.some(item => item.pendapatan > 0)) {
            const ctx1 = pendapatanChartElement.getContext('2d');
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
            pendapatanChartElement.parentElement.innerHTML = `
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <h3>Tidak ada data pendapatan</h3>
                    <p>Belum ada pendapatan yang tercatat dalam 12 bulan terakhir.</p>
                </div>
            `;
        }
    }

    // Grafik Produk Terlaris (Pie Chart)
    const produkChartElement = document.getElementById('produkChart');
    if (produkChartElement) {
        if (produkData.length > 0) {
            const ctx2 = produkChartElement.getContext('2d');
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
                        },
                        cutout: '60%',
                        animation: {
                            animateScale: true,
                            animateRotate: true,
                            duration: 2000,
                            easing: 'easeOutQuart'
                        }
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
            
            produkChartElement.parentElement.appendChild(legendContainer);
        } else {
            // Tampilkan pesan jika tidak ada data
            produkChartElement.parentElement.innerHTML = `
                <div class="no-data">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Tidak ada data produk terlaris</h3>
                    <p>Belum ada produk yang terjual pada periode ini.</p>
                </div>
            `;
        }
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
        // Hapus elemen chart lama sebelum inisialisasi ulang
        const chartContainers = document.querySelectorAll('.chart-container');
        chartContainers.forEach(container => {
            const oldCanvas = container.querySelector('canvas');
            if (oldCanvas) {
                oldCanvas.remove();
            }
            const oldLegend = container.querySelector('.chart-legend');
            if (oldLegend) {
                oldLegend.remove();
            }
            // Buat canvas baru agar Chart.js bisa inisialisasi ulang
            const newCanvas = document.createElement('canvas');
            if (container.id === 'pendapatanChartContainer') {
                 newCanvas.id = 'pendapatanChart';
            } else if (container.id === 'produkChartContainer') {
                 newCanvas.id = 'produkChart';
            }
            
            // Tambahkan kembali elemen canvas ke container yang kosong
            if (newCanvas.id) {
                 container.appendChild(newCanvas);
            }
        });
        
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
    
    // Expose functions to global scope (diperlukan karena onclick di HTML)
    window.showSection = showSection;
    window.toggleSidebar = toggleSidebar;
    
    // Handle iframe loading
    const iframes = document.querySelectorAll('iframe');
    iframes.forEach(iframe => {
        iframe.onload = function() {
            console.log('Iframe loaded:', iframe.src);
        };
        
        iframe.onerror = function() {
            console.error('Iframe failed to load:', iframe.src);
            // Coba akses contentDocument
            try {
                if (iframe.contentDocument) {
                    iframe.contentDocument.body.innerHTML = `
                        <div style="padding: 3rem; text-align: center; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h3>Gagal memuat halaman</h3>
                            <p>Pastikan file "${iframe.src}" ada di lokasi yang benar.</p>
                        </div>
                    `;
                }
            } catch (e) {
                console.warn("Could not modify iframe content due to cross-origin restriction or other error.");
            }
        };
    });
});