function openEditModal(id, stepNumber, title, description, iconClass, sortOrder) {
    document.getElementById('modalStepId').value = id;
    document.getElementById('modalStepNumber').value = stepNumber;
    document.getElementById('modalStepTitle').value = title;
    document.getElementById('modalDescription').value = description;
    document.getElementById('modalIconClass').value = iconClass;
    document.getElementById('modalSortOrder').value = sortOrder;
    
    // Show modal
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
    
    // Confirm before delete (using event delegation on form submission)
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Cek apakah form berisi tombol delete
            if (this.querySelector('button[name="delete_step"]')) {
                // Konfirmasi sudah dilakukan di PHP, ini hanya sebagai fallback JS
                // Karena kita menggunakan `onsubmit="return confirm(...)"` di PHP,
                // ini mungkin tidak diperlukan, tetapi disimpan sebagai praktik baik.
            }
        });
    });
});