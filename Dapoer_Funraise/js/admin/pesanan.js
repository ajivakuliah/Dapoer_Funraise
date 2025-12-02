
// ✅ MODIFIKASI: filterByStatus sekarang delete 'page' dan pertahankan 'bulan'
function filterByStatus(status) {
    const url = new URL(window.location);
    url.searchParams.delete('page');
    
    if (status) {
        if(url.searchParams.get('status') === status) {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', status);
        }
    } else {
        url.searchParams.delete('status');
    }
    
    // Pertahankan filter bulan
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');

    window.location.href = url.toString();
}

// ✅ MODIFIKASI: submitSearch sekarang handle 'bulan' dan reset ke halaman 1
function submitSearch() {
    const q = document.getElementById('search').value.trim();
    const url = new URL(window.location);
    url.searchParams.set('page', 1);
    
    // Pertahankan filter bulan
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');
    
    // Pertahankan filter status jika ada
    const status = url.searchParams.get('status');
    status ? url.searchParams.set('status', status) : url.searchParams.delete('status');

    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    window.location.href = url.toString();
}

// ✅ MODIFIKASI: Fungsi resetAll untuk menghapus semua filter
function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('status');
    url.searchParams.delete('bulan'); // Hapus filter bulan
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearch(); // Panggil submitSearch
            }
        });
    }
});
