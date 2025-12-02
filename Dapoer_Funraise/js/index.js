// STABLE NAVIGATION - Tidak berubah-ubah saat scroll
document.addEventListener('DOMContentLoaded', function() {
    if ('scrollRestoration' in window.history) window.history.scrollRestoration = 'manual';
    window.scrollTo(0, 0);

    const header = document.querySelector('.app-header');
    
    function getHeaderHeight() {
        return header ? header.offsetHeight : 80;
    }

    // Enhanced fade-in with staggered animation
    const fadeElements = document.querySelectorAll('.fade-in');
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('appear');
                }, index * 200);
                fadeObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    fadeElements.forEach(el => fadeObserver.observe(el));

    // STABLE NAVIGATION SYSTEM
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-links a');
    let currentActive = '';
    
    // Gunakan Intersection Observer untuk navigasi yang lebih stabil
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
                const id = entry.target.getAttribute('id');
                if (id && id !== currentActive) {
                    currentActive = id;
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${id}`) {
                            link.classList.add('active');
                        }
                    });
                    
                    // Update URL hash without scrolling
                    if (history.replaceState) {
                        history.replaceState(null, null, `#${id}`);
                    }
                }
            }
        });
    }, {
        threshold: [0.1, 0.3, 0.5],
        rootMargin: '-20% 0px -20% 0px'
    });

    sections.forEach(section => sectionObserver.observe(section));

    // Smooth scroll dengan offset
    navLinks.forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (!targetId || targetId === '#') return;
            const target = document.querySelector(targetId);
            if (!target) return;

            // Update active nav immediately
            navLinks.forEach(link => link.classList.remove('active'));
            this.classList.add('active');
            currentActive = targetId.substring(1);

            const offset = getHeaderHeight() + 20;
            const elementPosition = target.getBoundingClientRect().top;
            const offsetPosition = window.scrollY + elementPosition - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
            
            // Update URL
            history.pushState(null, null, targetId);
            
            // Close mobile menu if open
            closeMobileMenu();
        });
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const hash = window.location.hash;
        if (hash) {
            const target = document.querySelector(hash);
            if (target) {
                const offset = getHeaderHeight() + 20;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = window.scrollY + elementPosition - offset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        }
    });

    // Focus form on load if URL hash
    const namaInput = document.getElementById('nama');
    if (namaInput && window.location.hash === '#testimoni-section') {
        setTimeout(() => {
            namaInput.focus();
            namaInput.style.borderColor = '#B64B62';
            setTimeout(() => { namaInput.style.borderColor = ''; }, 2000);
        }, 400);
    }

    // Back to top
    const btnBackToTop = document.getElementById('btnBackToTop');
    if (btnBackToTop) {
        function updateScrollButton() {
            btnBackToTop.classList.toggle('show', window.scrollY > 400);
        }
        window.addEventListener('scroll', updateScrollButton);
        updateScrollButton();
        btnBackToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // Add click effects to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Initialize active nav based on current hash
    function setActiveNavFromHash() {
        const hash = window.location.hash;
        if (hash) {
            currentActive = hash.substring(1);
            navLinks.forEach(link => link.classList.remove('active'));
            const activeLink = document.querySelector(`.nav-links a[href="${hash}"]`);
            if (activeLink) activeLink.classList.add('active');
        }
    }
    setActiveNavFromHash();
});

// Hero Slider Functions
let currentHeroSlide = 0;
const heroSlides = document.querySelectorAll('.hero-slide');
const heroDots = document.querySelectorAll('.hero-dot');

function showHeroSlide(index) {
    heroSlides.forEach(slide => slide.classList.remove('active'));
    heroDots.forEach(dot => dot.classList.remove('active'));
    
    currentHeroSlide = index;
    heroSlides[currentHeroSlide].classList.add('active');
    heroDots[currentHeroSlide].classList.add('active');
}

function changeHeroSlide(direction) {
    let newIndex = currentHeroSlide + direction;
    if (newIndex < 0) newIndex = heroSlides.length - 1;
    if (newIndex >= heroSlides.length) newIndex = 0;
    showHeroSlide(newIndex);
}

window.changeHeroSlide = changeHeroSlide; // Expose to HTML
window.goToHeroSlide = showHeroSlide; // Expose to HTML

// Auto-advance hero slider
if (heroSlides.length > 1) {
    setInterval(() => {
        changeHeroSlide(1);
    }, 5000);
}

// About Carousel Functions
let currentAboutSlide = 0;
const aboutSlides = document.querySelectorAll('.about-carousel-slide');
const aboutDots = document.querySelectorAll('.about-carousel-dot');
const aboutWrapper = document.getElementById('aboutCarouselWrapper');

function showAboutSlide(index) {
    aboutWrapper.style.transform = `translateX(-${index * 100}%)`;
    aboutDots.forEach(dot => dot.classList.remove('active'));
    aboutDots[index].classList.add('active');
    currentAboutSlide = index;
}

function changeAboutSlide(direction) {
    let newIndex = currentAboutSlide + direction;
    if (newIndex < 0) newIndex = aboutSlides.length - 1;
    if (newIndex >= aboutSlides.length) newIndex = 0;
    showAboutSlide(newIndex);
}

window.changeAboutSlide = changeAboutSlide; // Expose to HTML
window.goToAboutSlide = showAboutSlide; // Expose to HTML

// Auto-advance about carousel
if (aboutSlides.length > 1) {
    setInterval(() => {
        changeAboutSlide(1);
    }, 4000);
}

// Interactive Functions
function animateCard(card) {
    card.style.transform = 'scale(0.95)';
    setTimeout(() => {
        card.style.transform = '';
    }, 150);
}

function toggleAccordion(accordion) {
    const isActive = accordion.classList.contains('active');
    
    document.querySelectorAll('.testimoni-accordion').forEach(el => {
        el.classList.remove('active');
        el.querySelector('.accordion-body').style.maxHeight = '0';
    });
    
    if (!isActive) {
        accordion.classList.add('active');
        accordion.querySelector('.accordion-body').style.maxHeight = 
            accordion.querySelector('.accordion-body').scrollHeight + 'px';
    }
}

function refreshCaptcha() {
    const captchaImage = document.getElementById('captcha_image');
    if (captchaImage) {
        captchaImage.src = 'captcha.php?' + new Date().getTime();
    }
}

function validateForm() {
    const nama = document.getElementById('nama');
    const komentar = document.getElementById('komentar');
    const captcha = document.getElementById('captcha');

    if (!nama.value || !komentar.value || !captcha.value) {
        alert('Harap lengkapi semua field yang wajib diisi!');
        return false;
    }

    if (komentar.value.length < 10) {
        alert('Testimoni harus minimal 10 karakter!');
        return false;
    }

    return true;
}

window.animateCard = animateCard; // Expose to HTML
window.toggleAccordion = toggleAccordion; // Expose to HTML
window.refreshCaptcha = refreshCaptcha; // Expose to HTML
window.validateForm = validateForm; // Expose to HTML

// ==== MOBILE NAV MENU - IMPROVED ====
let isMenuOpen = false;

function toggleMenu() {
    const nav = document.querySelector('.nav-links');
    const body = document.body;
    const menuToggle = document.querySelector('.menu-toggle');
    
    isMenuOpen = !isMenuOpen;
    nav.classList.toggle('show');
    
    // Toggle hamburger icon
    if (isMenuOpen) {
        menuToggle.innerHTML = '✕';
        menuToggle.setAttribute('aria-expanded', 'true');
        body.style.overflow = 'hidden';
    } else {
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-expanded', 'false');
        body.style.overflow = 'auto';
    }
}

function closeMobileMenu() {
    const nav = document.querySelector('.nav-links');
    const body = document.body;
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (nav.classList.contains('show')) {
        nav.classList.remove('show');
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-expanded', 'false');
        body.style.overflow = 'auto';
        isMenuOpen = false;
    }
}

// Close menu when clicking on a link
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        const nav = document.querySelector('.nav-links');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (isMenuOpen && 
            !nav.contains(e.target) && 
            !menuToggle.contains(e.target)) {
            closeMobileMenu();
        }
    });

    // Close menu on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isMenuOpen) {
            closeMobileMenu();
        }
    });
});

window.toggleMenu = toggleMenu; // Expose to HTML

// Handle window resize - close menu on desktop
window.addEventListener('resize', function() {
    if (window.innerWidth >= 769) {
        closeMobileMenu();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple effect styles
    const style = document.createElement('style');
    style.textContent = `
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .btn {
            position: relative;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);
    
    // Initialize mobile menu state
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', 'false');
    }
});

// Add touch-friendly improvements for mobile
document.addEventListener('DOMContentLoaded', function() {
    // Add touch feedback to cards
    const touchElements = document.querySelectorAll('.order-card, .contact-card, .testimoni-accordion');
    
    touchElements.forEach(element => {
        element.addEventListener('touchstart', function() {
            this.style.transition = 'transform 0.1s ease';
            this.style.transform = 'scale(0.98)';
        });
        
        element.addEventListener('touchend', function() {
            this.style.transform = '';
            setTimeout(() => {
                this.style.transition = '';
            }, 100);
        });
        
        element.addEventListener('touchcancel', function() {
            this.style.transform = '';
            this.style.transition = '';
        });
    });
    
    // Prevent zoom on double tap for buttons
    const buttons = document.querySelectorAll('button, .btn, a.btn');
    buttons.forEach(button => {
        button.addEventListener('touchend', function(e) {
            e.preventDefault();
        });
    });
});

// Optimize for mobile performance
let lastScrollTop = 0;
const header = document.querySelector('.app-header');

if (header) {
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            header.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    }, { passive: true });
}