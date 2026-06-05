<?php
/**
 * Mon Restaurant - Fonctions pour la mise en file des scripts et styles
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Configuration des scripts et styles
 */
function gastro_starter_enqueue_assets($hook = '') {
    // Styles communs
    wp_enqueue_style('gastro-starter-style', get_stylesheet_uri(), array(), GASTRO_STARTER_VERSION);
    wp_enqueue_style('gastro-starter-google-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', array(), null);
    wp_enqueue_style('gastro-starter-main', get_template_directory_uri() . '/assets/css/main.css', array('gastro-starter-google-fonts'), GASTRO_STARTER_VERSION);
    wp_enqueue_style('gastro-starter-animations', get_template_directory_uri() . '/assets/css/animations.css', array(), GASTRO_STARTER_VERSION);
    wp_enqueue_style('gastro-starter-checkboxes', get_template_directory_uri() . '/assets/css/custom-checkboxes.css', array(), GASTRO_STARTER_VERSION);

    // Scripts communs
    wp_enqueue_script('gastro-starter-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), GASTRO_STARTER_VERSION, true);
    wp_enqueue_script('gastro-starter-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), GASTRO_STARTER_VERSION, true);

    // Scripts spécifiques à l'administration
    if (is_admin()) {
        wp_enqueue_media();

        // ─── Admin Design System (Glassmorphism 2026) ───
        $css_admin = get_template_directory_uri() . '/assets/css/admin/';
        wp_enqueue_style('gastro-starter-admin-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', [], null);
        wp_enqueue_style('gastro-starter-admin-layers', $css_admin . '_layers.css', ['gastro-starter-admin-font'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-tokens', $css_admin . '_tokens.css', ['gastro-starter-admin-layers'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-reset', $css_admin . '_reset.css', ['gastro-starter-admin-tokens'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-base', $css_admin . '_base.css', ['gastro-starter-admin-reset'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-layout', $css_admin . '_layout.css', ['gastro-starter-admin-base'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-components', $css_admin . '_components.css', ['gastro-starter-admin-layout'], GASTRO_STARTER_VERSION);
        wp_enqueue_style('gastro-starter-admin-print', $css_admin . '_print.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);

        // Legacy admin.css loaded first as fallback — new styles override it
        wp_enqueue_style('gastro-starter-admin-css', get_template_directory_uri() . '/assets/css/admin.css', ['gastro-starter-admin-font'], GASTRO_STARTER_VERSION);

        wp_enqueue_script('gastro-starter-admin', get_template_directory_uri() . '/assets/js/admin.js', array('jquery'), GASTRO_STARTER_VERSION, true);
        wp_localize_script('gastro-starter-admin', 'gastro_starter_admin', array(
            'nonce' => wp_create_nonce('gastro_starter_upload_pdf'),
            'pdf_only_message' => __('Seuls les fichiers PDF sont autorisés.', 'gastro-starter')
        ));

        // ─── Page-specific styles ───

        // Dashboard
        if ($hook === 'index.php') {
            wp_enqueue_style('gastro-starter-admin-dashboard-css', $css_admin . 'dashboard.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
        }

        // Reservations
        $reservation_pages = [
            'toplevel_page_gastro-starter-reservations',
            'reservations_page_gastro-starter-reservation-settings'
        ];
        if (in_array($hook, $reservation_pages)) {
            wp_enqueue_style('gastro-starter-admin-reservations-css', $css_admin . 'reservations.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
            wp_enqueue_style('gastro-starter-admin-settings-css', $css_admin . 'settings.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
            wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
            wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.9', true);
            wp_enqueue_script('flatpickr-fr', 'https://npmcdn.com/flatpickr/dist/l10n/fr.js', ['flatpickr'], '4.6.9', true);

            wp_enqueue_script('gastro-starter-admin-reservations', get_template_directory_uri() . '/assets/js/admin-reservations.js', ['jquery', 'flatpickr'], GASTRO_STARTER_VERSION, true);
            wp_localize_script('gastro-starter-admin-reservations', 'gastro_starter_res_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gastro_starter_reservation_edit'),
                'i18n' => array(
                    'editTitle' => __('Modifier la réservation', 'gastro-starter'),
                    'save' => __('Enregistrer', 'gastro-starter'),
                    'cancel' => __('Annuler', 'gastro-starter'),
                    'updated' => __('Réservation mise à jour.', 'gastro-starter'),
                    'error' => __('Erreur lors de la mise à jour.', 'gastro-starter')
                )
            ));
        }

        // Customers CRM
        if ($hook === 'toplevel_page_gastro-starter-customers') {
            wp_enqueue_style('gastro-starter-admin-customers-css', $css_admin . 'customers.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
            wp_enqueue_script('gastro-starter-customer-admin', get_template_directory_uri() . '/assets/js/customer-admin.js', array(), GASTRO_STARTER_VERSION, true);
            wp_localize_script('gastro-starter-customer-admin', 'gastro_starter_customers', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gastro_starter_customers_nonce'),
            ));
        }

        // Mailing soirée
        if ($hook === 'reservations_page_gastro-starter-mailing') {
            wp_enqueue_style('gastro-starter-admin-mailing-css', $css_admin . 'mailing.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
            wp_enqueue_script('gastro-starter-mailing-admin', get_template_directory_uri() . '/assets/js/mailing-admin.js', ['jquery'], GASTRO_STARTER_VERSION, true);
            wp_localize_script('gastro-starter-mailing-admin', 'gastro_starter_mailing', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gastro_starter_mailing_nonce'),
                'i18n' => [
                    'fill_fields' => __('Veuillez remplir tous les champs requis.', 'gastro-starter'),
                    'confirm_send' => __('Envoyer ce message à %d destinataire(s) ? Cette action est irréversible.', 'gastro-starter'),
                    'error' => __('Une erreur est survenue lors de l\'envoi.', 'gastro-starter'),
                ],
            ]);
        }

        // Menu manager
        if ($hook === 'toplevel_page_gastro-starter-menus' || $hook === 'appearance_page_gastro-starter-menus') {
            wp_enqueue_style('gastro-starter-admin-menu-css', $css_admin . 'menu-manager.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
        }

        // Brevo newsletter
        if (strpos($hook, 'brevo') !== false || strpos($hook, 'newsletter') !== false) {
            wp_enqueue_style('gastro-starter-admin-brevo-css', $css_admin . 'brevo.css', ['gastro-starter-admin-components'], GASTRO_STARTER_VERSION);
        }
    }

    // Scripts spécifiques à la page de réservation
    if (is_page('reserver')) {
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.9');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.9', true);
        wp_enqueue_script('flatpickr-fr', 'https://npmcdn.com/flatpickr/dist/l10n/fr.js', array('flatpickr'), '4.6.9', true);
        wp_enqueue_style('gastro-starter-reservation', get_template_directory_uri() . '/assets/css/reservation.css', array(), '1.0.0');
        wp_enqueue_script('gastro-starter-reservation', get_template_directory_uri() . '/assets/js/reservation.js', array('jquery'), '1.0.1', true);

        // CORRECTION : Centralisation de la configuration des scripts de réservation
        // Récupérer les événements futurs pour les mettre en avant dans le calendrier
        $event_dates = [];
        $events = get_posts([
            'post_type'      => 'event',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'meta_key'       => 'event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [[
                'key'     => 'event_date',
                'value'   => date('Y-m-d'),
                'compare' => '>=',
                'type'    => 'DATE',
            ]],
        ]);
        foreach ($events as $ev) {
            $ev_date   = get_post_meta($ev->ID, 'event_date',   true);
            $ev_time   = get_post_meta($ev->ID, 'event_time',   true);
            $ev_status = get_post_meta($ev->ID, 'event_status', true) ?: 'open';
            $ev_subtitle = get_post_meta($ev->ID, 'email_subtitle', true) ?: '';
            if ($ev_date) {
                $event_dates[$ev_date] = [
                    'title'    => get_the_title($ev),
                    'subtitle' => $ev_subtitle,
                    'time'     => $ev_time,
                    'status'   => $ev_status,
                    'url'      => get_permalink($ev->ID),
                ];
            }
        }

        wp_localize_script('gastro-starter-reservation', 'gastro_starter_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'restaurant_capacity' => intval(get_option('gastro_starter_restaurant_capacity', 4)),
            'daily_schedule' => get_option('gastro_starter_daily_schedule', array()),
            'holiday_dates' => get_option('gastro_starter_holiday_dates', ''),
            'booking_period' => intval(get_option('gastro_starter_booking_period', 1)),
            'version' => '2.1.0',
            'restaurant_phone' => get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00'),
            'event_dates' => $event_dates,
        ));
    }

    if (is_page('bon-achat')) {
        wp_enqueue_script('jquery');
        wp_enqueue_style('gastro-starter-voucher', get_template_directory_uri() . '/assets/css/voucher.css', array(), GASTRO_STARTER_VERSION);
        wp_enqueue_script('gastro-starter-voucher', get_template_directory_uri() . '/assets/js/voucher.js', array('jquery'), GASTRO_STARTER_VERSION, true);
        wp_localize_script('gastro-starter-voucher', 'gastro_starter_voucher', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    // Swiper JS uniquement sur les pages qui en ont besoin
    if (is_front_page() || is_post_type_archive('event') || is_post_type_archive('testimonial') || is_singular('event')) {
        wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.5');
        wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.5', true);
        wp_enqueue_script('gastro-starter-swiper-init', get_template_directory_uri() . '/assets/js/swiper-init.js', array('swiper-js'), GASTRO_STARTER_VERSION, true);
    }

    wp_enqueue_script('gastro-starter-image-modal', get_template_directory_uri() . '/assets/js/image-modal.js', array('jquery'), GASTRO_STARTER_VERSION, true);

    if (is_front_page()) {
        wp_enqueue_script('gastro-starter-gallery-lightbox', get_template_directory_uri() . '/assets/js/gallery-lightbox.js', array('jquery'), GASTRO_STARTER_VERSION, true);
    }
}
add_action('wp_enqueue_scripts', 'gastro_starter_enqueue_assets');
add_action('admin_enqueue_scripts', 'gastro_starter_enqueue_assets');

/**
 * Charger dynamiquement la police Google Font choisie dans le Customizer.
 */
function gastro_starter_enqueue_custom_fonts() {
    $primary_font = get_theme_mod('gastro_starter_primary_font', 'Inter');
    
    if ($primary_font) {
        $font_families = array(
            'Inter' => 'Inter:wght@300;400;500',
            'Poppins' => 'Poppins:wght@300;400;500',
            'Lato' => 'Lato:wght@300;400;700',
            'Lora' => 'Lora:wght@400;500;600',
            'Cormorant Garamond' => 'Cormorant+Garamond:wght@400;500;600',
            'Playfair Display' => 'Playfair+Display:wght@400;500;600',
        );

        if (isset($font_families[$primary_font])) {
            $font_query = $font_families[$primary_font];
            $fonts_url = 'https://fonts.googleapis.com/css2?family=' . $font_query . '&display=swap';
            wp_enqueue_style('gastro-starter-custom-font', $fonts_url, array(), null);
        }
    }
}
add_action('wp_enqueue_scripts', 'gastro_starter_enqueue_custom_fonts');

/**
 * Ajouter la classe wrapper pour le design system admin glassmorphism.
 */
function gastro_starter_admin_body_class($classes) {
    $classes .= ' gastro-starter-admin-shell';
    return $classes;
}
add_filter('admin_body_class', 'gastro_starter_admin_body_class'); 