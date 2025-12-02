function openEditModal(photoId, altText, caption) {
    document.getElementById('modalPhotoId').value = photoId;
    document.getElementById('modalAltText').value = altText || '';
    document.getElementById('modalCaption').value = caption || '';
    document.getElementById('editModal').style.display = 'flex';
    updateAllCounters();
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('editForm').reset();
}

function updateCounter(input, max) {
    const current = input.value.length;
    const counterElement = input.parentNode.querySelector('.char-counter');

    if (counterElement) {
        counterElement.textContent = `${current}/${max}`;
        
        if (current > max * 0.9) {
            counterElement.style.color = '#dc3545';
        } else if (current > max * 0.7) {
            counterElement.style.color = '#ffc107';
        } else {
            counterElement.style.color = '#6c757d';
        }
    }
}

function initializeCounter(input) {
    const max = parseInt(input.getAttribute('maxlength'));
    const helpText = input.parentNode.querySelector('.help-text');
    
    if (helpText) {
        const counter = document.createElement('span');
        counter.className = 'char-counter';
        counter.style.float = 'right';
        counter.style.fontSize = '12px';
        counter.style.color = '#6c757d';
        
        function updateHandler() {
            updateCounter(input, max);
        }
        
        input.addEventListener('input', updateHandler);
        
        if (helpText.nextSibling) {
            helpText.parentNode.insertBefore(counter, helpText.nextSibling);
        } else {
             helpText.parentNode.appendChild(counter);
        }
        
        updateHandler(); // Initial call
    }
}

function updateAllCounters() {
    document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(input => {
        const max = parseInt(input.getAttribute('maxlength'));
        updateCounter(input, max);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    window.openEditModal = openEditModal;
    window.closeModal = closeModal;

    document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(initializeCounter);
    
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
            if (this.querySelector('button[name="delete_photo"]')) {
                if (!confirm('Yakin ingin menghapus foto ini?')) {
                    e.preventDefault();
                }
            }
        });
    });
    
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