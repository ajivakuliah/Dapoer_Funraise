document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    }, 4000);
});

document.querySelectorAll('.desc-toggle').forEach(button => {
    button.addEventListener('click', function() {
        const content = this.nextElementSibling;
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        
        this.setAttribute('aria-expanded', !isExpanded);
        this.classList.toggle('expanded');
        content.classList.toggle('expanded');
    });
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.desc-wrapper')) {
        document.querySelectorAll('.desc-content.expanded').forEach(el => {
            el.classList.remove('expanded');
            el.previousElementSibling.setAttribute('aria-expanded', 'false');
            el.previousElementSibling.classList.remove('expanded');
        });
    }
});

document.querySelectorAll('.add-to-cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const variantSelect = form.querySelector('select[name="varian_pilih"]');
        const qtyInput = form.querySelector('input[name="quantity"]');
        const cartBtn = form.querySelector('.inline-cart-btn');
        
        let hasError = false;

        if (variantSelect && variantSelect.required && !variantSelect.value.trim()) {
            e.preventDefault();
            variantSelect.classList.add('form-error');
            variantSelect.focus();
            hasError = true;
            setTimeout(() => variantSelect.classList.remove('form-error'), 1500);
        }

        const qty = parseInt(qtyInput.value) || 0;
        if (qty < 1 || qty > 20) {
            e.preventDefault();
            qtyInput.classList.add('form-error');
            qtyInput.focus();
            hasError = true;
            setTimeout(() => qtyInput.classList.remove('form-error'), 1500);
        }

        if (!hasError) {
            cartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            cartBtn.classList.add('btn-loading');
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.product-card').forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `all 0.5s ease ${i * 0.1}s`;
        observer.observe(card);
    });

    document.querySelectorAll('.product-img img').forEach(img => {
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            img.onload = () => img.classList.add('loaded');
        }
    });
});

const header = document.querySelector('.app-header');
const scrollTopBtn = document.getElementById('scrollTopBtn');
let lastScroll = 0;

window.addEventListener('scroll', () => {
    const current = window.scrollY;
    header.style.transform = current > lastScroll && current > 100 
        ? 'translateY(-100%)' 
        : 'translateY(0)';
    scrollTopBtn.classList.toggle('visible', current > 300);
    lastScroll = current;
});

scrollTopBtn?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.desc-content.expanded').forEach(el => {
            el.classList.remove('expanded');
            el.previousElementSibling.setAttribute('aria-expanded', 'false');
            el.previousElementSibling.classList.remove('expanded');
        });
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.querySelector('.search-input')?.focus();
    }
});