function openEditModal(bgId) {
    document.getElementById('modalBgId').value = bgId;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('editForm').reset();
}

document.addEventListener('DOMContentLoaded', function() {
    // Expose functions to global scope (diperlukan karena onclick di HTML)
    window.openEditModal = openEditModal;
    window.closeModal = closeModal;
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            closeModal();
        }
    });
    
    // Auto close alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Confirm before delete
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.querySelector('button[name="delete_bg"]')) {
                if (!confirm('Yakin ingin menghapus background ini?')) {
                    e.preventDefault();
                }
            }
        });
    });
    
    // Validate file size on upload
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB');
                this.value = '';
            }
        });
    });
});