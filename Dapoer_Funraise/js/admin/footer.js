document.addEventListener('DOMContentLoaded', function() {
    // Auto close alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Update status indicator when checkbox changes
    const checkbox = document.getElementById('is_active');
    const statusIndicator = document.querySelector('.status-indicator');
    
    if (checkbox && statusIndicator) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                statusIndicator.className = 'status-indicator status-active';
                statusIndicator.innerHTML = '<i class="fas fa-toggle-on"></i> AKTIF';
            } else {
                statusIndicator.className = 'status-indicator status-inactive';
                statusIndicator.innerHTML = '<i class="fas fa-toggle-off"></i> NONAKTIF';
            }
        });
    }
    
    // Force full width on all elements (optional, based on iframe context)
    window.addEventListener('resize', function() {
        document.querySelectorAll('.container, .section, .compact-form').forEach(el => {
            el.style.width = '100%';
            el.style.maxWidth = '100%';
            el.style.minWidth = '100%';
        });
    });
});