/**
 * Captive Portal - Client-Side Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initLoadingOverlay();
    initAdCarousel();
    initSessionTimer();
});

/**
 * Theme Toggle (Light/Dark Mode)
 */
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    // Always check localStorage and set theme
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }

    if (!themeToggle) return;

    themeToggle.addEventListener('click', (e) => {
        e.preventDefault();
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    });
}

/**
 * Show loading overlay when login buttons are clicked
 * Hanya untuk tombol yang navigasi di window yang sama (bukan new tab)
 */
function initLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    const loginBtns = document.querySelectorAll('.login-btn');

    if (!overlay) return;

    loginBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Jangan tampilkan loading untuk free login (halaman lokal)
            if (btn.classList.contains('login-btn--free')) return;
            // Jangan tampilkan loading untuk tombol yang dibuka di tab baru
            // (Google/Facebook OAuth — target="_blank")
            if (btn.getAttribute('target') === '_blank') return;
            overlay.classList.add('active');
        });
    });
}

/**
 * Ad Carousel for success page
 */
function initAdCarousel() {
    const carousel = document.querySelector('.ad-carousel');
    if (!carousel) return;

    const inner = carousel.querySelector('.ad-carousel-inner');
    const slides = carousel.querySelectorAll('.ad-slide');
    const dotsContainer = carousel.querySelector('.ad-dots');

    if (slides.length <= 1) return;

    let currentSlide = 0;
    const totalSlides = slides.length;

    // Create dots
    slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.classList.add('ad-dot');
        dot.setAttribute('aria-label', `Slide ${i + 1}`);
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer.appendChild(dot);
    });

    function goToSlide(index) {
        currentSlide = index;
        inner.style.transform = `translateX(-${index * 100}%)`;

        // Update dots
        dotsContainer.querySelectorAll('.ad-dot').forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
    }

    // Auto-rotate every 5 seconds
    setInterval(() => {
        const next = (currentSlide + 1) % totalSlides;
        goToSlide(next);
    }, 5000);

    // Touch/swipe support
    let startX = 0;
    let isDragging = false;

    inner.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        isDragging = true;
    });

    inner.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            if (diff > 0 && currentSlide < totalSlides - 1) {
                goToSlide(currentSlide + 1);
            } else if (diff < 0 && currentSlide > 0) {
                goToSlide(currentSlide - 1);
            }
        }
    });
}

/**
 * Session countdown timer for free users
 */
function initSessionTimer() {
    const timerEl = document.getElementById('sessionTimer');
    if (!timerEl) return;

    const remainingSeconds = parseInt(timerEl.dataset.remaining, 10);
    if (isNaN(remainingSeconds) || remainingSeconds <= 0) return;

    let remaining = remainingSeconds;
    const valueEl = timerEl.querySelector('.session-timer-value');

    function updateTimer() {
        if (remaining <= 0) {
            valueEl.textContent = '00:00:00';
            valueEl.style.color = '#f43f5e';
            return;
        }

        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;

        valueEl.textContent =
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        // Warn when < 5 minutes
        if (remaining < 300) {
            valueEl.style.color = '#f43f5e';
        }

        remaining--;
    }

    updateTimer();
    setInterval(updateTimer, 1000);
}
