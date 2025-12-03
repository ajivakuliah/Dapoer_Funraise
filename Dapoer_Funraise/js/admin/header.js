document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    document.getElementById('headerForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('logoInput');
        if (fileInput.files.length > 0) {
            const fileSize = fileInput.files[0].size;
            const maxSize = 2097152;
            
            if (fileSize > maxSize) {
                e.preventDefault();
                alert('Ukuran file terlalu besar! Maksimal 2MB.');
                return false;
            }
        }
    });
    
    document.getElementById('logoInput').addEventListener('change', function(e) {
        const fileInfo = document.querySelector('.current-file-info');
        if (this.files.length > 0) {
            fileInfo.innerHTML = `
                <i class="fas fa-info-circle"></i>
                File baru yang dipilih: ${this.files[0].name}
            `;
        }
    });
    updateTimestamp();

    function updateTimestamp() {
        const now = new Date();
        const options = { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        const formattedDate = now.toLocaleDateString('id-ID', options);
        document.querySelector('.info-value .timestamp').textContent = formattedDate;
    }
    
    setInterval(updateTimestamp, 60000);
});