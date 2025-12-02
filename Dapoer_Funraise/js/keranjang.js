    function adjustQty(button, delta) {
        const controls = button.closest('.item-controls');
        const input = controls.querySelector('.quantity-input');
        if (!input) return;
        
        let val = parseInt(input.value) || 1;
        val += delta;
        val = Math.max(1, Math.min(100, val));
        input.value = val;
        
        const form = document.getElementById('cartForm');
        if (form) form.submit();
    }
    
    function refreshCaptcha() {
        const captchaImage = document.getElementById('captchaImage');
        if (captchaImage) {
            captchaImage.src = 'captcha.php?' + new Date().getTime();
        }
    }

    function confirmCheckout() {
        const form = document.getElementById('checkoutForm');
        const nama = document.getElementById('nama').value.trim();
        const alamat = document.getElementById('alamat').value.trim();
        const captcha = document.getElementById('captcha').value.trim();
        const pengambilan = document.querySelector('input[name="pengambilan"]:checked');
        const metodeBayar = document.querySelector('input[name="metode_bayar"]:checked');
        
        let errors = [];
        
        if (!nama) errors.push('Nama lengkap');
        if (!alamat) errors.push('Alamat lengkap');
        if (!captcha) errors.push('Kode verifikasi');
        if (!pengambilan) errors.push('Metode pengiriman');
        if (!metodeBayar) errors.push('Metode pembayaran');
        
        if (errors.length > 0) {
            alert('Harap lengkapi data berikut:\n• ' + errors.join('\n• '));
            return false;
        }
        
        const confirmation = confirm('Apakah Anda yakin untuk membuat pesanan?\n\nPesanan akan dikirim ke WhatsApp dan tidak dapat dibatalkan.');
        return confirmation;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const header = document.querySelector('.app-header');
        let lastScrollY = window.scrollY;
        let ticking = false;

        const captchaImage = document.getElementById('captchaImage');
        const captchaInput = document.getElementById('captcha');
        
        if (captchaImage && captchaInput) {
            captchaImage.addEventListener('click', function() {
                refreshCaptcha();
                captchaInput.focus();
            });
        }

        const updateHeader = () => {
            if (window.scrollY > lastScrollY && window.scrollY > 80) {
                header.classList.add('hide');
            } else {
                header.classList.remove('hide');
            }
            lastScrollY = window.scrollY;
            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateHeader);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick, { passive: true });
    });  
