/**
 * Fichier dédié à la gestion de la navigation du thème Gastro Starter
 */

(function() {
    // Éléments de navigation
    const siteNavigation = document.getElementById('site-navigation');
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    
    // Sortir si la navigation n'existe pas
    if (!siteNavigation || !mobileToggle) {
        return;
    }
    
    // S'assurer que le menu est fermé au chargement
    siteNavigation.classList.remove('active');
    mobileToggle.setAttribute('aria-expanded', 'false');
    
    // Gérer le clic sur le bouton du menu mobile
    mobileToggle.addEventListener('click', function() {
        const isExpanded = mobileToggle.getAttribute('aria-expanded') === 'true';
        
        // Inverser l'état
        mobileToggle.setAttribute('aria-expanded', !isExpanded);
        
        // Basculer la classe active sur la navigation
        siteNavigation.classList.toggle('active');
        
        // Changer l'icône du menu (si disponible)
        const menuIcon = mobileToggle.querySelector('.dashicons');
        if (menuIcon) {
            if (isExpanded) {
                menuIcon.classList.remove('dashicons-no-alt');
                menuIcon.classList.add('dashicons-menu');
            } else {
                menuIcon.classList.remove('dashicons-menu');
                menuIcon.classList.add('dashicons-no-alt');
            }
        }
    });
    
    // Fermer le menu mobile quand on clique sur un lien
    const navLinks = siteNavigation.querySelectorAll('a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768 && siteNavigation.classList.contains('active')) {
                mobileToggle.setAttribute('aria-expanded', 'false');
                siteNavigation.classList.remove('active');
                
                // Restaurer l'icône du menu (si disponible)
                const menuIcon = mobileToggle.querySelector('.dashicons');
                if (menuIcon) {
                    menuIcon.classList.remove('dashicons-no-alt');
                    menuIcon.classList.add('dashicons-menu');
                }
            }
        });
    });
    
    // Fermer le menu si clic à l'extérieur
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && 
            !siteNavigation.contains(event.target) && 
            !mobileToggle.contains(event.target) && 
            siteNavigation.classList.contains('active')) {
            
            mobileToggle.setAttribute('aria-expanded', 'false');
            siteNavigation.classList.remove('active');
            
            // Restaurer l'icône du menu (si disponible)
            const menuIcon = mobileToggle.querySelector('.dashicons');
            if (menuIcon) {
                menuIcon.classList.remove('dashicons-no-alt');
                menuIcon.classList.add('dashicons-menu');
            }
        }
    });

    // Ajouter la classe active au lien du menu correspondant à la page courante
    const currentUrl = window.location.href;
    const menuLinks = document.querySelectorAll('.main-navigation a');
    
    menuLinks.forEach(link => {
        if (link.href === currentUrl) {
            link.classList.add('current-menu-item');
            // Ajouter également la classe au parent li
            const parentLi = link.closest('li');
            if (parentLi) {
                parentLi.classList.add('current-menu-item');
            }
        }
    });
})(); 