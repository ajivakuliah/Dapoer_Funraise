function openEditModal(id, buttonText, whatsappNumber, sortOrder) {
    document.getElementById('modalButtonId').value = id;
    document.getElementById('modalButtonText').value = buttonText;
    document.getElementById('modalWhatsappNumber').value = whatsappNumber;
    document.getElementById('modalSortOrder').value = sortOrder;
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('editForm').reset();
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
});

function confirmDelete(buttonId, isActive) {
    if (isActive) {
        return confirm('PERINGATAN! Tombol ini sedang AKTIF di website.\n\nMenghapusnya akan otomatis mengaktifkan tombol lain.\n\nLanjutkan menghapus?');
    } else {
        return confirm('Yakin ingin menghapus tombol WhatsApp ini?');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    document.addEventListener('submit', function(e) {
        const toggleButton = e.target.querySelector('button[name="toggle_active"]');
        if (toggleButton) {
            const isActive = toggleButton.classList.contains('btn-success');
            
            if (isActive) {
                if (!confirm('Nonaktifkan tombol WhatsApp ini?')) {
                    e.preventDefault();
                }
            } else {
                if (!confirm('Aktifkan tombol WhatsApp ini?\n\nTombol WhatsApp lain yang aktif akan otomatis dinonaktifkan.')) {
                    e.preventDefault();
                }
            }
        }
    });
});