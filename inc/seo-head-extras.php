<?php
/**
 * Balises <head> additionnelles SEO
 * - Canonical (si pas de plugin SEO)
 * - Twitter Cards (basées sur les metas existantes)
 * - hreflang (FR/EN via système de langue du thème)
 * - noindex pour recherche/404/pièces jointes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Détecter la présence d'un plugin SEO majeur pour éviter les doublons
 */
function gastro_starter_has_seo_plugin() {
    return (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION'));
}

/**
 * Canonical: filtrer l'URL Core pour inclure ?lang= si langue non-default
 * (ne PAS appeler rel_canonical() manuellement — WP Core le fait déjà à priority 10)
 */
add_filter('get_canonical_url', function ($canonical_url) {
    if (gastro_starter_has_seo_plugin()) {
        return $canonical_url;
    }
    if (!function_exists('gastro_starter_get_current_language')) {
        return $canonical_url;
    }
    $current = gastro_starter_get_current_language();
    if ($current && $current !== 'fr_FR') {
        $canonical_url = add_query_arg('lang', $current, $canonical_url);
    }
    return $canonical_url;
}, 10);

/**
 * Twitter Cards basées sur les metas déjà posées par inc/seo-meta.php
 * Si un plugin SEO est présent, on n'ajoute rien pour éviter les doublons
 */
add_action('wp_head', function () {
    if (gastro_starter_has_seo_plugin()) {
        return;
    }

    // Récupérer title/description via la logique existante
    $title = wp_get_document_title();

    $description = '';
    if (is_singular()) {
        $description = get_post_meta(get_the_ID(), '_gastro_starter_meta_description', true);
        if (!$description) {
            $description = wp_strip_all_tags(get_the_excerpt());
        }
    } elseif (is_archive() || is_home()) {
        // Laisser WordPress/archives — on garde minimal
        $description = get_bloginfo('description');
    }

    $image = '';
    if (is_singular()) {
        $og_image_id = get_post_meta(get_the_ID(), '_gastro_starter_og_image', true);
        if ($og_image_id) {
            $image = wp_get_attachment_image_url($og_image_id, 'full');
        } elseif (has_post_thumbnail()) {
            $image = get_the_post_thumbnail_url(null, 'full');
        }
    }

    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
    if ($description) {
        echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words($description, 40)) . '" />' . "\n";
    }
    if ($image) {
        echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
    }
}, 11);

/**
 * hreflang: génère <link rel="alternate" hreflang="fr|en|x-default" ...>
 */
add_action('wp_head', function () {
    if (gastro_starter_has_seo_plugin()) {
        return;
    }
    if (!function_exists('gastro_starter_get_available_languages')) {
        return;
    }

    $available = gastro_starter_get_available_languages();

    // Mapper WP locale -> hreflang (codes simples, pas de région)
    $map = [
        'fr_FR' => 'fr',
        'en_US' => 'en',
    ];

    // Construire l'URL de base propre (avec trailing slash sur homepage)
    $base_url = gastro_starter_get_clean_page_url();

    foreach ($available as $locale => $label) {
        $hreflang = isset($map[$locale]) ? $map[$locale] : strtolower(substr($locale, 0, 2));
        $url = add_query_arg('lang', $locale, $base_url);
        echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($url) . '" />' . "\n";
    }

    // x-default pointe vers l'URL de base SANS ?lang= (= français par défaut)
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($base_url) . '" />' . "\n";
}, 12);

/**
 * Retourne l'URL propre de la page courante (sans query string, avec trailing slash)
 */
function gastro_starter_get_clean_page_url() {
    if (is_front_page()) {
        return home_url('/');
    }
    if (is_singular()) {
        return get_permalink();
    }
    if (is_post_type_archive()) {
        return get_post_type_archive_link(get_queried_object()->name);
    }
    if (is_tax() || is_category() || is_tag()) {
        return get_term_link(get_queried_object());
    }
    // Fallback
    global $wp;
    return home_url(trailingslashit($wp->request));
}

/**
 * noindex pour pages non désirées dans l'index (recherche, 404, pièces jointes)
 */
add_action('wp_head', function () {
    $noindex = false;
    if (is_search() || is_404() || is_attachment()) {
        $noindex = true;
    }
    // Pages transactionnelles: merci / confirmation / échec paiement
    if (function_exists('is_page') && (is_page('merci') || is_page('merci-voucher') || is_page('telecharger-bon-achat') || is_page('confirmation-de-paiement') || is_page('echec-du-paiement'))) {
        $noindex = true;
    }
    if ($noindex) {
        echo '<meta name="robots" content="noindex,follow" />' . "\n";
    }
}, 1);
