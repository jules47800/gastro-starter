/**
 * Single Event Page — Gastro Starter
 * Animations : IntersectionObserver pour les sections + parallax hero.
 */
(function () {
    'use strict';

    // ── Reveal au scroll ──────────────────────────────────────────
    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        document.querySelectorAll(
            '.event-infos, .event-accroche, .event-gallery, ' +
            '.event-menu, .event-citation, .event-vins, ' +
            '.event-content, .event-cta, .event-gift, .event-nav'
        ).forEach(function (el) {
            revealObserver.observe(el);
        });
    } else {
        // Fallback : tout rendre visible immédiatement
        document.querySelectorAll(
            '.event-infos, .event-accroche, .event-gallery, ' +
            '.event-menu, .event-citation, .event-vins, ' +
            '.event-content, .event-cta, .event-gift, .event-nav'
        ).forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    // ── Parallax hero ─────────────────────────────────────────────
    var heroBg = document.querySelector('.event-hero__bg');
    if (heroBg && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var ticking = false;
        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(function () {
                    var scrollY = window.pageYOffset;
                    var heroH   = document.querySelector('.event-hero').offsetHeight;
                    if (scrollY < heroH * 1.5) {
                        var shift = scrollY * 0.25;
                        heroBg.style.transform = 'scale(1.04) translateY(' + shift + 'px)';
                    }
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    // ── Ouverture image menu en lightbox simple ───────────────────
    var menuImg = document.querySelector('.event-menu__image img');
    if (menuImg) {
        menuImg.style.cursor = 'zoom-in';
        menuImg.addEventListener('click', function () {
            var overlay = document.createElement('div');
            overlay.style.cssText = [
                'position:fixed', 'inset:0', 'z-index:9999',
                'background:rgba(0,0,0,0.85)',
                'display:flex', 'align-items:center', 'justify-content:center',
                'cursor:zoom-out', 'padding:24px'
            ].join(';');

            var img = document.createElement('img');
            img.src = menuImg.src;
            img.style.cssText = 'max-width:100%;max-height:90vh;object-fit:contain;border-radius:4px;box-shadow:0 8px 40px rgba(0,0,0,0.4);';

            overlay.appendChild(img);
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';

            overlay.addEventListener('click', function () {
                document.body.removeChild(overlay);
                document.body.style.overflow = '';
            });

            document.addEventListener('keydown', function escClose(e) {
                if (e.key === 'Escape') {
                    if (document.body.contains(overlay)) {
                        document.body.removeChild(overlay);
                        document.body.style.overflow = '';
                    }
                    document.removeEventListener('keydown', escClose);
                }
            });
        });
    }

})();
