function submitSearch() {
    const q = document.getElementById('searchInput').value.trim();
    const url = new URL(window.location);
    
    url.searchParams.delete('page'); 
    if (q) {
        url.searchParams.set('q', q);
    } else {
        url.searchParams.delete('q');
    }
    window.location.href = url.toString();
}

function resetAll() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.delete('filter');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    window.submitSearch = submitSearch;
    window.resetAll = resetAll;

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