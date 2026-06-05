<?php
/**
 * Redirections pour les anciennes pages supprimées
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Redirection des anciennes pages supprimées vers l'accueil
 */
function gastro_starter_redirect_old_pages() {
    $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'], '/')) : '';
    
    $redirects = array(
        'a-propos'    => '/',
        'about'       => '/',
        'contact'     => '/',
        'menu'        => '/menus/',
        'carte'       => '/menus/',
        'la-carte'    => '/menus/',
        'events'      => '/agenda/',
        'evenements'  => '/agenda/',
        'evenement'   => '/agenda/',
        'galerie'     => '/',
        'gallery'     => '/',
        'reservation' => '/reserver/',
        'book'        => '/reserver/',
        'booking'     => '/reserver/',
        'gift-card'   => '/bon-achat/',
        'bon-cadeau'  => '/bon-achat/',
        'bons-cadeaux'=> '/bon-achat/',
    );
    
    foreach ($redirects as $old_slug => $new_path) {
        if ($uri === $old_slug || strpos($uri, $old_slug . '/') === 0 || strpos($uri, '/' . $old_slug) !== false) {
            wp_redirect(home_url($new_path), 301);
            exit;
        }
    }
}
add_action('template_redirect', 'gastro_starter_redirect_old_pages', 1);

/**
 * Envoyer un en-tête 410 (Gone) pour les pages définitivement supprimées
 */
function gastro_starter_send_410_for_deleted_pages() {
    if (is_page('a-propos') || 
        strpos($_SERVER['REQUEST_URI'], '/a-propos') !== false) {
        
        status_header(410); // 410 = Gone (page définitivement supprimée)
        nocache_headers();
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Page supprimée - Mon Restaurant</title>
    <meta charset="utf-8">
</head>
<body>
    <h1>Cette page a été supprimée</h1>
    <p>La page que vous recherchez a été définitivement supprimée.</p>
    <p><a href="' . home_url() . '">Retour à l\'accueil du restaurant</a></p>
</body>
</html>';
        exit;
    }
}
// Décommentez cette ligne si vous préférez un 410 au lieu d'une redirection 301
// add_action('template_redirect', 'gastro_starter_send_410_for_deleted_pages', 1);
?> 