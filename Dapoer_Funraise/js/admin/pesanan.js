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
    
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');

    window.location.href = url.toString();
}

function submitSearch() {
    const q = document.getElementById('search').value.trim();
    const url = new URL(window.location);
    url.searchParams.set('page', 1);
    
    const bulan = document.getElementById('bulan').value;
    bulan ? url.searchParams.set('bulan', bulan) : url.searchParams.delete('bulan');
    
    const status = url.searchParams.get('status');
    status ? url.searchParams.set('status', status) : url.searchParams.delete('status');

    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('status');
    url.searchParams.delete('bulan');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
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
