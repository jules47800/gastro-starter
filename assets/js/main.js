/**
 * Script principal pour le thème Gastro Starter
 */

(function($) {
    'use strict';
    
    // Défilement fluide pour les ancres
    $('a[href*="#"]:not([href="#"])').on('click', function() {
        if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && location.hostname === this.hostname) {
            let target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 800);
                return false;
            }
        }
    });
    
    // Animation du header au défilement
    const header = $('#masthead');
    const headerHeight = header.outerHeight();
    
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > headerHeight) {
            header.addClass('sticky');
        } else {
            header.removeClass('sticky');
        }
    });
    
    // Initialisation des effets au chargement de la page
    $(document).ready(function() {
        // Ajouter une classe 'loaded' au body quand tout est chargé
        $('body').addClass('loaded');
    });
    
})(jQuery); 