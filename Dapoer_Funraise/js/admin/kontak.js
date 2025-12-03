function openEditModal(id, iconClass, title, label, href, sortOrder) {
    document.getElementById('modalCardId').value = id;
    document.getElementById('modalIconClass').value = iconClass;
    document.getElementById('modalCardTitle').value = title;
    document.getElementById('modalLabel').value = label;
    document.getElementById('modalHref').value = href;
    document.getElementById('modalSortOrder').value = sortOrder;
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('editForm').reset();
}

document.addEventListener('DOMContentLoaded', function() {
    window.openEditModal = openEditModal;
    window.closeModal = closeModal;
    
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            closeModal();
        }
    });
    
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.querySelector('button[name="delete_card"]')) {
                if (!confirm('Yakin ingin menghapus kontak ini?')) {
                    e.preventDefault();
                }
            }
        });
    });
});