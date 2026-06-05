/**
 * JavaScript pour la page Notre Ville Interactive
 * Gestion des animations, cartes interactives, galerie filtrable, etc.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===============================================
    // 1. ANIMATIONS DE MOTS AU CHARGEMENT
    // ===============================================
    
    function animateWords() {
        const words = document.querySelectorAll('.eymet-main-title .word');
        words.forEach((word, index) => {
            const delay = word.getAttribute('data-delay') || 0;
            setTimeout(() => {
                word.style.animationDelay = delay + 'ms';
                word.classList.add('animate');
            }, parseInt(delay));
        });
    }
    
    // ===============================================
    // 2. PARALLAX POUR LA SECTION HERO
    // ===============================================
    
    function initParallax() {
        const parallaxElements = document.querySelectorAll('.parallax-layer');
        
        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = element.getAttribute('data-speed') || 0.5;
                const yPos = -(scrollTop * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        });
    }
    
    // ===============================================
    // 3. CARTE INTERACTIVE DES ATTRACTIONS
    // ===============================================
    
    function initInteractiveMap() {
        const mapPoints = document.querySelectorAll('.map-point');
        const infoPanel = document.getElementById('attraction-info');
        
        // Données des attractions
        const attractionsData = {
            chateau: {
                title: 'Château d\'Notre Ville',
                description: 'Forteresse médiévale du XIIIe siècle, témoin de l\'histoire mouvementée d\'Notre Ville.',
                details: 'Construit vers 1270, le château d\'Notre Ville offre une vue imprenable sur la bastide et la vallée de la Dropt.',
                hours: 'Visites guidées sur rendez-vous',
                price: 'Gratuit'
            },
            place: {
                title: 'Place Centrale',
                description: 'Cœur battant de la bastide avec ses arcades typiques du Moyen Âge.',
                details: 'Place rectangulaire bordée de maisons à arcades, caractéristique des bastides du Sud-Ouest.',
                hours: 'Accessible 24h/24',
                price: 'Gratuit'
            },
            dropt: {
                title: 'Rivière Dropt',
                description: 'Paisible rivière qui traverse Notre Ville, idéale pour la promenade.',
                details: 'La Dropt offre de magnifiques sentiers de randonnée et des aires de pique-nique.',
                hours: 'Toute l\'année',
                price: 'Gratuit'
            },
            margo: {
                title: 'Gastro Starter Restaurant',
                description: 'Restaurant gastronomique au cœur de la bastide, cuisine locale et vins naturels.',
                details: 'Antoine et Floriane vous accueillent pour une expérience culinaire unique.',
                hours: 'Jeudi-Samedi 19h-23h',
                price: 'Menus à partir de 21€'
            },
            marche: {
                title: 'Marché Traditionnel',
                description: 'Marché hebdomadaire avec les meilleurs produits locaux du Périgord.',
                details: 'Tous les jeudis matin, découvrez fromages, fruits, légumes et spécialités locales.',
                hours: 'Jeudi 8h-12h',
                price: 'Gratuit'
            }
        };
        
        mapPoints.forEach(point => {
            point.addEventListener('click', function() {
                const attraction = this.getAttribute('data-attraction');
                const data = attractionsData[attraction];
                
                if (data && infoPanel) {
                    // Animation de sortie
                    infoPanel.style.opacity = '0';
                    infoPanel.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        // Mise à jour du contenu
                        infoPanel.innerHTML = `
                            <div class="info-content">
                                <h3 class="info-title">${data.title}</h3>
                                <p class="info-description">${data.description}</p>
                                <div class="info-details">
                                    <p><strong>Détails :</strong> ${data.details}</p>
                                    <p><strong>Horaires :</strong> ${data.hours}</p>
                                    <p><strong>Tarif :</strong> ${data.price}</p>
                                </div>
                                ${attraction === 'margo' ? 
                                    '<a href="/reserver" class="btn" style="margin-top: 15px;">Réserver</a>' : 
                                    ''
                                }
                            </div>
                        `;
                        
                        // Animation d'entrée
                        infoPanel.style.opacity = '1';
                        infoPanel.style.transform = 'translateY(0)';
                    }, 200);
                }
                
                // Effet visuel sur le point cliqué
                mapPoints.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
            });
            
            // Effet hover
            point.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            
            point.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'scale(1)';
                }
            });
        });
    }
    
    // ===============================================
    // 4. TIMELINE INTERACTIVE
    // ===============================================
    
    function initInteractiveTimeline() {
        const timelinePoints = document.querySelectorAll('.timeline-point');
        
        // Observer pour l'animation au scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.3
        });
        
        timelinePoints.forEach(point => {
            observer.observe(point);
            
            // Interaction au clic pour plus de détails
            point.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                const info = this.getAttribute('data-info');
                
                // Vous pouvez ajouter une modal avec plus d'informations
                showTimelineModal(year, info);
            });
        });
    }
    
    function showTimelineModal(year, info) {
        // Données détaillées pour chaque période
        const timelineData = {
            '1270': {
                title: 'Fondation de la Bastide (1270)',
                content: 'Alphonse de Poitiers, frère du roi Saint Louis, fonde la bastide d\'Notre Ville selon un plan géométrique rigoureux. La ville est conçue comme un rectangle avec une place centrale entourée d\'arcades.',
                image: '/assets/images/timeline-1270.jpg'
            },
            '1337': {
                title: 'Guerre de Cent Ans (1337-1453)',
                content: 'Notre Ville devient un enjeu stratégique entre la France et l\'Angleterre. La ville change plusieurs fois de mains, subissant destructions et reconstructions.',
                image: '/assets/images/timeline-1337.jpg'
            },
            '1500': {
                title: 'Renaissance (XVIe siècle)',
                content: 'Période de prospérité et de reconstruction. Notre Ville s\'embellit avec de nouveaux édifices et développe son commerce.',
                image: '/assets/images/timeline-1500.jpg'
            },
            '2024': {
                title: 'Notre Ville Aujourd\'hui',
                content: 'Notre Ville est devenue une destination touristique prisée, alliant patrimoine historique et qualité de vie. Gastro Starter participe à ce rayonnement gastronomique.',
                image: '/assets/images/timeline-2024.jpg'
            }
        };
        
        const data = timelineData[year];
        if (data) {
            // Créer et afficher une modal simple
            const modal = document.createElement('div');
            modal.className = 'timeline-modal';
            modal.innerHTML = `
                <div class="modal-overlay">
                    <div class="modal-content">
                        <button class="modal-close">&times;</button>
                        <h3>${data.title}</h3>
                        <img src="${data.image}" alt="${data.title}" style="width: 100%; margin: 15px 0; border-radius: 8px;">
                        <p>${data.content}</p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Fermeture de la modal
            modal.querySelector('.modal-close').addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            modal.querySelector('.modal-overlay').addEventListener('click', (e) => {
                if (e.target === modal.querySelector('.modal-overlay')) {
                    document.body.removeChild(modal);
                }
            });
        }
    }
    
    // ===============================================
    // 5. GALERIE FILTRABLE
    // ===============================================
    
    function initFilterableGallery() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const galleryItems = document.querySelectorAll('.gallery-item');
        
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Mise à jour des boutons actifs
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Filtrage des éléments
                galleryItems.forEach(item => {
                    const category = item.getAttribute('data-category');
                    
                    if (filter === 'all' || category === filter) {
                        item.classList.remove('hidden');
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    } else {
                        item.classList.add('hidden');
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.8)';
                    }
                });
            });
        });
        
        // Lightbox pour les images
        galleryItems.forEach(item => {
            item.addEventListener('click', function() {
                const img = this.querySelector('img');
                const title = this.querySelector('.gallery-overlay h4').textContent;
                
                openLightbox(img.src, title);
            });
        });
    }
    
    function openLightbox(imageSrc, title) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-overlay">
                <div class="lightbox-content">
                    <button class="lightbox-close">&times;</button>
                    <img src="${imageSrc}" alt="${title}">
                    <h4>${title}</h4>
                </div>
            </div>
        `;
        
        document.body.appendChild(lightbox);
        
        // Fermeture
        lightbox.querySelector('.lightbox-close').addEventListener('click', () => {
            document.body.removeChild(lightbox);
        });
        
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                document.body.removeChild(lightbox);
            }
        });
        
        // Échapper pour fermer
        document.addEventListener('keydown', function escapeClose(e) {
            if (e.key === 'Escape') {
                if (document.body.contains(lightbox)) {
                    document.body.removeChild(lightbox);
                }
                document.removeEventListener('keydown', escapeClose);
            }
        });
    }
    
    // ===============================================
    // 6. VIEWER 360° PANORAMIQUE
    // ===============================================
    
    function initPanoramaViewer() {
        const panoramaContainer = document.querySelector('.panorama-image');
        const controls = document.querySelectorAll('.control-btn');
        
        if (!panoramaContainer) return;
        
        let isDragging = false;
        let startX = 0;
        let currentX = 0;
        let backgroundPosition = 0;
        
        // Configuration du panorama
        const panoramaUrl = panoramaContainer.getAttribute('data-panorama');
        if (panoramaUrl) {
            panoramaContainer.style.backgroundImage = `url(${panoramaUrl})`;
        }
        
        // Gestion du drag pour la rotation
        panoramaContainer.addEventListener('mousedown', startDrag);
        panoramaContainer.addEventListener('touchstart', startDrag);
        
        function startDrag(e) {
            isDragging = true;
            startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
            panoramaContainer.style.cursor = 'grabbing';
        }
        
        document.addEventListener('mousemove', handleDrag);
        document.addEventListener('touchmove', handleDrag);
        
        function handleDrag(e) {
            if (!isDragging) return;
            
            e.preventDefault();
            currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
            const diffX = currentX - startX;
            backgroundPosition += diffX * 0.5;
            
            panoramaContainer.style.backgroundPosition = `${backgroundPosition}px center`;
            startX = currentX;
        }
        
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);
        
        function stopDrag() {
            isDragging = false;
            panoramaContainer.style.cursor = 'grab';
        }
        
        // Contrôles de navigation
        controls.forEach(btn => {
            btn.addEventListener('click', function() {
                const direction = this.getAttribute('data-direction');
                const action = this.getAttribute('data-action');
                
                if (direction === 'left') {
                    backgroundPosition += 100;
                } else if (direction === 'right') {
                    backgroundPosition -= 100;
                } else if (action === 'fullscreen') {
                    toggleFullscreen(panoramaContainer);
                }
                
                panoramaContainer.style.backgroundPosition = `${backgroundPosition}px center`;
            });
        });
    }
    
    function toggleFullscreen(element) {
        if (!document.fullscreenElement) {
            element.requestFullscreen().catch(err => {
                console.log(`Erreur fullscreen: ${err.message}`);
            });
        } else {
            document.exitFullscreen();
        }
    }
    
    // ===============================================
    // 7. SCROLL SMOOTH ET INDICATEUR
    // ===============================================
    
    function initSmoothScroll() {
        const scrollIndicator = document.querySelector('.scroll-indicator');
        
        if (scrollIndicator) {
            scrollIndicator.addEventListener('click', function() {
                const firstSection = document.querySelector('.eymet-timeline-section');
                if (firstSection) {
                    firstSection.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        }
    }
    
    // ===============================================
    // 8. ANIMATIONS AU SCROLL
    // ===============================================
    
    function initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);
        
        // Observer tous les éléments animables
        const animatableElements = document.querySelectorAll('.event-card, .gallery-item, .highlight-item');
        animatableElements.forEach(el => observer.observe(el));
    }
    
    // ===============================================
    // 9. INITIALISATION GÉNÉRALE
    // ===============================================
    
    // Vérifier si on est sur la page Notre Ville
    if (document.body.classList.contains('eymet-page') || document.querySelector('.eymet-hero-section')) {
        animateWords();
        initParallax();
        initInteractiveMap();
        initInteractiveTimeline();
        initFilterableGallery();
        initPanoramaViewer();
        initSmoothScroll();
        initScrollAnimations();
        
        // Effet de typing pour le sous-titre
        const subtitle = document.querySelector('.eymet-subtitle');
        if (subtitle) {
            const text = subtitle.textContent;
            subtitle.textContent = '';
            let i = 0;
            
            setTimeout(() => {
                const typeInterval = setInterval(() => {
                    subtitle.textContent += text.charAt(i);
                    i++;
                    if (i >= text.length) {
                        clearInterval(typeInterval);
                    }
                }, 50);
            }, 2000);
        }
    }
    
    // ===============================================
    // 10. STYLES CSS POUR LES MODALS ET LIGHTBOX
    // ===============================================
    
    // Ajouter les styles CSS dynamiquement
    const modalStyles = `
        <style>
        .timeline-modal, .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay, .lightbox-overlay {
            background: rgba(0,0,0,0.8);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content, .lightbox-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            animation: slideInUp 0.3s ease;
        }
        
        .lightbox-content {
            text-align: center;
            max-width: 90vw;
        }
        
        .lightbox-content img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
        }
        
        .modal-close, .lightbox-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-close:hover, .lightbox-close:hover {
            color: #000;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', modalStyles);
    
});

// ===============================================
// FONCTIONS UTILITAIRES
// ===============================================

// Debounce pour optimiser les performances
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Fonction pour détecter si l'élément est visible
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
} 