<?php
/**
 * Réglages des XML Sitemaps WordPress Core
 * - Activation (si désactivé)
 * - Exclusion des pièces jointes
 * - Ajustements légers pour CPT publics
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nettoyer le buffer pour les requêtes sitemap afin d'éviter tout octet avant la déclaration XML
add_action('init', function () {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ($uri && (strpos($uri, 'wp-sitemap') !== false || preg_match('#/sitemap\\.xml$#', $uri))) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}, 0);

// Toujours autoriser les sitemaps (sauf si un plugin SEO gère déjà)
add_filter('wp_sitemaps_enabled', function ($enabled) {
    // Si Yoast ou Rank Math est actif, ne pas forcer
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
        return $enabled; // laisser le plugin gérer
    }
    return true;
}, 10, 1);

// Retirer les pièces jointes du sitemap (souvent inutile et verbeux)
add_filter('wp_sitemaps_post_types', function ($post_types) {
    if (isset($post_types['attachment'])) {
        unset($post_types['attachment']);
    }
    // S'assurer que nos CPT publics restent inclus (au cas où)
    $post_types['daily_menu'] = 'daily_menu';
    $post_types['testimonial'] = 'testimonial';
    return $post_types;
});

// Optionnel: limiter le nombre d'URL par sitemap (défaut 2000). Gardons le défaut.
// add_filter('wp_sitemaps_max_urls', fn($max) => 2000);

// Optionnel: Exclure des posts précis du sitemap (ex: pages techniques)
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    if ($post_type === 'page') {
        // Exclure les pages transactionnelles du sitemap
        $slugs_to_exclude = array('confirmation-de-paiement', 'echec-du-paiement', 'merci', 'merci-voucher', 'telecharger-bon-achat');
        $ids = array();
        foreach ($slugs_to_exclude as $slug) {
            $p = get_page_by_path($slug);
            if ($p && isset($p->ID)) {
                $ids[] = (int) $p->ID;
            }
        }
        if (!empty($ids)) {
            $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
            $args['post__not_in'] = array_values(array_unique(array_merge($existing, $ids)));
        }
    }
    return $args;
}, 10, 2);
