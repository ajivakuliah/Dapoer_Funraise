function submitSearch() {
    const q = document.getElementById('searchInput').value.trim();
    const url = new URL(window.location);
    
    // Hapus parameter page saat pencarian baru
    url.searchParams.delete('page'); 

    // Atur parameter q
    if (q) {
        url.searchParams.set('q', q);
    } else {
        url.searchParams.delete('q');
    }
    
    // Langsung arahkan
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('filter');
    url.searchParams.delete('page');
    // Jika hanya ingin reset pencarian, gunakan:
    // window.location.href = '?filter=' + url.searchParams.get('filter');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Expose functions to global scope (diperlukan karena onclick di HTML)
    window.submitSearch = submitSearch;
    window.resetAll = resetAll;
    
    // Tekan Enter di input langsung submit
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearch();
            }
        });
    }
});