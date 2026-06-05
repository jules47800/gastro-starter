<?php
/**
 * Gastro Starter Theme Customizer
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit; // Ne pas autoriser l'accès direct
}

/**
 * Ajouter les sections, réglages et contrôles au Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Manager de personnalisation de WordPress.
 */
function gastro_starter_customize_register($wp_customize) {
    
    // 1. Section des couleurs
    $wp_customize->add_section('gastro_starter_colors_section', array(
        'title'      => __('Couleurs du Thème', 'gastro-starter'),
        'priority'   => 30,
        'description' => __('Gérez ici les couleurs principales du site.', 'gastro-starter'),
    ));

    // Section Informations du Restaurant
    $wp_customize->add_section('gastro_starter_restaurant_info_section', array(
        'title'      => __('Informations du Restaurant', 'gastro-starter'),
        'priority'   => 25,
        'description' => __('Gérez ici les informations principales du restaurant.', 'gastro-starter'),
    ));

    // Nom du restaurant
    $wp_customize->add_setting('gastro_starter_restaurant_name', array(
        'default'   => 'Mon Restaurant',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('gastro_starter_restaurant_name_control', array(
        'label'    => __('Nom du Restaurant', 'gastro-starter'),
        'section'  => 'gastro_starter_restaurant_info_section',
        'settings' => 'gastro_starter_restaurant_name',
        'type'     => 'text',
    ));

    // Téléphone
    $wp_customize->add_setting('gastro_starter_restaurant_phone', array(
        'default'   => '01 23 45 67 89',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('gastro_starter_restaurant_phone_control', array(
        'label'    => __('Numéro de Téléphone', 'gastro-starter'),
        'section'  => 'gastro_starter_restaurant_info_section',
        'settings' => 'gastro_starter_restaurant_phone',
        'type'     => 'text',
    ));

    // Adresse
    $wp_customize->add_setting('gastro_starter_restaurant_address', array(
        'default'   => '1 rue de la Gastronomie, 75001 Paris',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('gastro_starter_restaurant_address_control', array(
        'label'    => __('Adresse', 'gastro-starter'),
        'section'  => 'gastro_starter_restaurant_info_section',
        'settings' => 'gastro_starter_restaurant_address',
        'type'     => 'textarea',
    ));

    // SIRET
    $wp_customize->add_setting('gastro_starter_restaurant_siret', array(
        'default'   => '000 000 000 00000',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('gastro_starter_restaurant_siret_control', array(
        'label'    => __('Numéro SIRET', 'gastro-starter'),
        'section'  => 'gastro_starter_restaurant_info_section',
        'settings' => 'gastro_starter_restaurant_siret',
        'type'     => 'text',
    ));

    // Liste des couleurs à ajouter
    $colors = array(
        '--color-black'       => array('label' => __('Couleur Texte Principal', 'gastro-starter'), 'default' => '#1a1a1a'),
        '--color-white'       => array('label' => __('Fond Principal (Blanc)', 'gastro-starter'), 'default' => '#fefefe'),
        '--color-beige'       => array('label' => __('Arrière-plan (Beige)', 'gastro-starter'), 'default' => '#f4f1eb'),
        '--color-beige-dark'  => array('label' => __('Beige Foncé (Bordures)', 'gastro-starter'), 'default' => '#e8e3d9'),
        '--color-warm-gray'   => array('label' => __('Texte Secondaire (Gris)', 'gastro-starter'), 'default' => '#8b8680'),
        '--color-dark-brown'  => array('label' => __('Couleur d\'Accent (Brun)', 'gastro-starter'), 'default' => '#2d2824'),
    );

    foreach ($colors as $variable => $details) {
        // Nettoyer le nom de la variable pour l'utiliser comme ID
        $setting_id = str_replace(['--', '-'], ['', '_'], $variable);

        // Réglage (Setting)
        $wp_customize->add_setting($setting_id, array(
            'default'   => $details['default'],
            'transport' => 'refresh', // ou 'postMessage' pour un aperçu en direct plus avancé
            'sanitize_callback' => 'sanitize_hex_color',
        ));

        // Contrôle (Control)
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $setting_id . '_control', array(
            'label'      => $details['label'],
            'section'    => 'gastro_starter_colors_section',
            'settings'   => $setting_id,
        )));
    }

    // 2. Section Typographie
    $wp_customize->add_section('gastro_starter_typography_section', array(
        'title'      => __('Typographie', 'gastro-starter'),
        'priority'   => 35,
        'description' => __('Gérez les polices et tailles de texte.', 'gastro-starter'),
    ));

    // Réglage pour la police principale
    $wp_customize->add_setting('gastro_starter_primary_font', array(
        'default'   => 'Inter',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // Contrôle pour la police principale
    $wp_customize->add_control('gastro_starter_primary_font_control', array(
        'label'    => __('Police Principale', 'gastro-starter'),
        'section'  => 'gastro_starter_typography_section',
        'settings' => 'gastro_starter_primary_font',
        'type'     => 'select',
        'choices'  => array(
            'Inter' => 'Inter (moderne, sans-serif)',
            'Poppins' => 'Poppins (géométrique, sans-serif)',
            'Lato' => 'Lato (chaleureux, sans-serif)',
            'Lora' => 'Lora (élégant, serif)',
            'Cormorant Garamond' => 'Cormorant Garamond (raffiné, serif)',
            'Playfair Display' => 'Playfair Display (classique, serif)',
        ),
    ));

    // --- Tailles de police ---

    // Taille de la police de base
    $wp_customize->add_setting('gastro_starter_body_font_size', array( 'default'   => '16', 'sanitize_callback' => 'absint' ));
    $wp_customize->add_control('gastro_starter_body_font_size_control', array(
        'label' => __('Taille texte de corps (px)', 'gastro-starter'),
        'section'  => 'gastro_starter_typography_section', 'settings' => 'gastro_starter_body_font_size', 'type' => 'number',
        'input_attrs' => array('min' => 12, 'max' => 22, 'step' => 1),
    ));

    // Taille des titres principaux (h1)
    $wp_customize->add_setting('gastro_starter_h1_font_size', array( 'default' => '56', 'sanitize_callback' => 'absint' ));
    $wp_customize->add_control('gastro_starter_h1_font_size_control', array(
        'label' => __('Taille Titre Principal (H1 en px)', 'gastro-starter'),
        'section'  => 'gastro_starter_typography_section', 'settings' => 'gastro_starter_h1_font_size', 'type' => 'number',
        'input_attrs' => array('min' => 24, 'max' => 90, 'step' => 1),
    ));

    // Taille de la description du Hero
    $wp_customize->add_setting('gastro_starter_hero_desc_font_size', array( 'default' => '24', 'sanitize_callback' => 'absint' ));
    $wp_customize->add_control('gastro_starter_hero_desc_font_size_control', array(
        'label' => __('Taille description page d\'accueil (px)', 'gastro-starter'),
        'section'  => 'gastro_starter_typography_section', 'settings' => 'gastro_starter_hero_desc_font_size', 'type' => 'number',
        'input_attrs' => array('min' => 16, 'max' => 40, 'step' => 1),
    ));

    // Section Bannière Menu Spécial
    $wp_customize->add_section('gastro_starter_special_menu_banner_section', array(
        'title'      => __('Bannière Menu Spécial', 'gastro-starter'),
        'priority'   => 40,
        'description' => __('Configurez une bannière promotionnelle pour vos menus spéciaux (Nouvel An, Saint-Valentin, etc.)', 'gastro-starter'),
        'panel'      => '',
    ));

    // Activer/désactiver la bannière
    $wp_customize->add_setting('gastro_starter_special_menu_enabled', array(
        'default'   => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport' => 'refresh',
    ));
    $wp_customize->add_control('gastro_starter_special_menu_enabled_control', array(
        'label'    => __('Afficher la bannière', 'gastro-starter'),
        'section'  => 'gastro_starter_special_menu_banner_section',
        'settings' => 'gastro_starter_special_menu_enabled',
        'type'     => 'checkbox',
    ));

    // Texte de la bannière 
    $wp_customize->add_setting('gastro_starter_special_menu_text', array(
        'default'   => __('Menu Spécial Nouvel An - Découvrez notre carte exceptionnelle', 'gastro-starter'),
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control('gastro_starter_special_menu_text_control', array(
        'label'    => __('Texte de la bannière', 'gastro-starter'),
        'section'  => 'gastro_starter_special_menu_banner_section',
        'settings' => 'gastro_starter_special_menu_text',
        'type'     => 'text',
    ));

    // Upload du fichier PDF
    $wp_customize->add_setting('gastro_starter_special_menu_pdf', array(
        'default'   => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control(new WP_Customize_Upload_Control($wp_customize, 'gastro_starter_special_menu_pdf_control', array(
        'label'      => __('Menu PDF', 'gastro-starter'),
        'section'    => 'gastro_starter_special_menu_banner_section',
        'settings'   => 'gastro_starter_special_menu_pdf',
        'description' => __('Uploadez le fichier PDF du menu spécial', 'gastro-starter'),
    )));
}
add_action('customize_register', 'gastro_starter_customize_register');

/**
 * Générer le CSS à partir des réglages du Customizer et l'injecter dans l'en-tête.
 */
function gastro_starter_customizer_css() {
    ?>
    <style type="text/css">
        :root {
            <?php
            $colors = array(
                '--color-black' => 'color_black',
                '--color-white' => 'color_white',
                '--color-beige' => 'color_beige',
                '--color-beige-dark' => 'color_beige_dark',
                '--color-warm-gray' => 'color_warm_gray',
                '--color-dark-brown' => 'color_dark_brown',
            );

            foreach ($colors as $variable => $setting_id) {
                $default_value = get_theme_mod($setting_id . '_default', ''); // Récupérer la valeur par défaut si elle existe
                $value = get_theme_mod($setting_id, $default_value);
                if (!empty($value)) {
                    echo esc_html($variable) . ': ' . esc_html($value) . ';';
                }
            }

            // Variables de typographie
            $primary_font = get_theme_mod('gastro_starter_primary_font', 'Inter');
            $body_font_size = get_theme_mod('gastro_starter_body_font_size', '16');
            $h1_font_size = get_theme_mod('gastro_starter_h1_font_size', '56');
            $hero_desc_font_size = get_theme_mod('gastro_starter_hero_desc_font_size', '24');
            
            echo '--font-primary: "' . esc_html($primary_font) . '", sans-serif;';
            
            // Appliquer les tailles de police
            echo '--font-size-base: ' . esc_html($body_font_size) . 'px;';
            echo '--font-size-h1: ' . esc_html($h1_font_size) . 'px;';
            echo '--font-size-hero-desc: ' . esc_html($hero_desc_font_size) . 'px;';
             ?>
        }

        html {
            font-size: var(--font-size-base);
        }

        h1, .hero-card__title {
            font-size: var(--font-size-h1);
        }

        .hero-card__description {
            font-size: var(--font-size-hero-desc);
        }
    </style>
    <?php
}
add_action('wp_head', 'gastro_starter_customizer_css');

/**
 * JS pour le rafraîchissement en direct du Customizer
 */
function gastro_starter_customize_preview_js() {
    if (!is_customize_preview()) return;
    ?>
    <script type="text/javascript">
    (function($) {
        // Rafraîchissement en direct du texte de la bannière
        wp.customize('gastro_starter_special_menu_text', function(value) {
            value.bind(function(newval) {
                $('.special-menu-banner a').text(newval);
            });
        });
    }(jQuery));
    </script>
    <?php
}
add_action('wp_footer', 'gastro_starter_customize_preview_js');

/**
 * Inclure le nouveau fichier customizer.php
 */
// Cette fonction sera ajoutée à functions.php 