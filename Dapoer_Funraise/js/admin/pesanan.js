function filterByStatus(status) {
    const url = new URL(window.location.href);
    const currentStatus = url.searchParams.get('status');
    const q = url.searchParams.get('q') || '';
    
    // Jika status yang diklik sudah aktif, hapus filternya
    if (currentStatus === status) {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }

    // Pertahankan parameter q (pencarian)
    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    // Reset halaman ke 1 saat filter berubah
    url.searchParams.delete('page');

    window.location.href = url.toString();
}

function submitSearch() {
    const q = document.getElementById('search').value.trim();
    const url = new URL(window.location);
    
    // Atur parameter q (pencarian)
    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    // Reset halaman ke 1
    url.searchParams.delete('page');
    
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    // Hapus semua parameter filter dan pencarian
    url.searchParams.delete('q');
    url.searchParams.delete('status');
    url.searchParams.delete('page');
    window.location.href = url.origin + url.pathname;
}

document.addEventListener('DOMContentLoaded', function() {
    // Expose functions to global scope (diperlukan karena onclick di HTML)
    window.filterByStatus = filterByStatus;
    window.submitSearch = submitSearch;
    window.resetAll = resetAll;
    
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearch();
            }
        });
    }
});