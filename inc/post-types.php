<?php
/**
 * Mon Restaurant - Fonctions pour les types de publication personnalisés et taxonomies
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Types de publication personnalisés
 */
function gastro_starter_custom_post_types() {    
    // Type de publication pour les témoignages
    register_post_type(
        'testimonial',
        array(
            'labels' => array(
                'name'               => __('Témoignages', 'gastro-starter'),
                'singular_name'      => __('Témoignage', 'gastro-starter'),
                'menu_name'          => __('Témoignages', 'gastro-starter'),
                'add_new'            => __('Ajouter un témoignage', 'gastro-starter'),
                'add_new_item'       => __('Ajouter un nouveau témoignage', 'gastro-starter'),
                'edit_item'          => __('Modifier le témoignage', 'gastro-starter'),
                'new_item'           => __('Nouveau témoignage', 'gastro-starter'),
                'view_item'          => __('Voir le témoignage', 'gastro-starter'),
                'search_items'       => __('Rechercher des témoignages', 'gastro-starter'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports'    => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon'   => 'dashicons-format-quote',
            'rewrite'     => array('slug' => 'temoignages'),
        )
    );
    
    // Type de publication pour les menus - simplifié
    register_post_type(
        'daily_menu',
        array(
            'labels' => array(
                'name'               => __('Menus', 'gastro-starter'),
                'singular_name'      => __('Menu', 'gastro-starter'),
                'menu_name'          => __('Menus', 'gastro-starter'),
                'add_new'            => __('Ajouter un menu', 'gastro-starter'),
                'add_new_item'       => __('Ajouter un nouveau menu', 'gastro-starter'),
                'edit_item'          => __('Modifier le menu', 'gastro-starter'),
                'new_item'           => __('Nouveau menu', 'gastro-starter'),
                'view_item'          => __('Voir le menu', 'gastro-starter'),
                'search_items'       => __('Rechercher des menus', 'gastro-starter'),
            ),
            'public'       => true,
            'has_archive'  => true,
            'supports'     => array('title', 'custom-fields'),
            'menu_icon'    => 'dashicons-media-document',
            'rewrite'      => array('slug' => 'menu'),
            'show_in_menu' => true,
        )
    );
    
    // Type de publication pour les événements (Agenda)
    register_post_type(
        'event',
        array(
            'labels' => array(
                'name'               => __('Agenda', 'gastro-starter'),
                'singular_name'      => __('Événement', 'gastro-starter'),
                'menu_name'          => __('Agenda', 'gastro-starter'),
                'add_new'            => __('Ajouter un événement', 'gastro-starter'),
                'add_new_item'       => __('Ajouter un nouvel événement', 'gastro-starter'),
                'edit_item'          => __('Modifier l\'événement', 'gastro-starter'),
                'new_item'           => __('Nouvel événement', 'gastro-starter'),
                'view_item'          => __('Voir l\'événement', 'gastro-starter'),
                'search_items'       => __('Rechercher des événements', 'gastro-starter'),
                'all_items'          => __('Tous les événements', 'gastro-starter'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports'    => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon'   => 'dashicons-calendar-alt',
            'rewrite'     => array('slug' => 'agenda'),
            'show_in_rest' => true, // Pour l'éditeur de blocs si nécessaire
        )
    );
}
add_action('init', 'gastro_starter_custom_post_types');

/**
 * Taxonomies personnalisées
 */
function gastro_starter_custom_taxonomies() {
    // Aucune taxonomie nécessaire
}
add_action('init', 'gastro_starter_custom_taxonomies'); 