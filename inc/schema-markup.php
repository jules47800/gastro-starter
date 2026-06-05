<?php
/**
 * Ajout de Schema.org pour améliorer le SEO
 *
 * @package Gastro_Starter
 */

// Détection plugin SEO (éviter doublons de schémas génériques)
if (!function_exists('gastro_starter_has_seo_plugin')) {
    function gastro_starter_has_seo_plugin() {
        return (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION'));
    }
}

/**
 * Ajoute le balisage Schema.org pour un restaurant dans le pied de page
 */
function gastro_starter_add_restaurant_schema() {
    // Récupération des informations du site
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $site_description = get_bloginfo('description');
    
    // Construction du schéma JSON-LD
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        'name' => $site_name,
        'url' => $site_url,
        'description' => $site_description,
        '@id' => $site_url . '/#restaurant',
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie'),
            'addressLocality' => get_theme_mod('gastro_starter_address_city', 'Paris'),
            'postalCode' => get_theme_mod('gastro_starter_address_postal_code', '75001'),
            'addressRegion' => get_theme_mod('gastro_starter_address_region', ''),
            'addressCountry' => 'FR'
        ),
        'geo' => array(
            '@type' => 'GeoCoordinates',
            'latitude' => get_theme_mod('gastro_starter_geo_latitude', ''),
            'longitude' => get_theme_mod('gastro_starter_geo_longitude', '')
        ),
        'telephone' => get_theme_mod('gastro_starter_restaurant_phone', '+33123456789'),
        'email' => get_theme_mod('gastro_starter_restaurant_email', 'contact@mon-restaurant.fr'),
        'priceRange' => '€€',
        'servesCuisine' => ['Cuisine française', 'Bistronomique', 'Cuisine locale'],
        'openingHoursSpecification' => array(
            array(
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opens' => '19:00',
                'closes' => '22:00'
            )
        ),
        'hasMenu' => $site_url . '/menus/',
        'acceptsReservations' => 'True',
        'image' => get_template_directory_uri() . '/assets/images/restaurant-facade.jpg',
        'sameAs' => array(
            get_theme_mod('gastro_starter_instagram_url', '')
        )
    );
    
    // Insertion du balisage JSON-LD dans le pied de page
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}
add_action('wp_footer', 'gastro_starter_add_restaurant_schema', 100);

/**
 * Ajoute le balisage Schema.org pour les articles de blog
 */
function gastro_starter_add_blog_schema() {
    // Si un plugin SEO majeur est actif, éviter les doublons d'Article/BlogPosting
    if (gastro_starter_has_seo_plugin()) {
        return;
    }
    if (!is_single()) {
        return;
    }
    
    global $post;
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => get_the_title(),
        'description' => get_the_excerpt(),
        'url' => get_permalink(),
        'datePublished' => get_the_date('c'),
        'dateModified' => get_the_modified_date('c'),
        'mainEntityOfPage' => get_permalink(),
        'author' => array(
            '@type' => 'Person',
            'name' => get_the_author()
        ),
        'publisher' => array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => get_template_directory_uri() . '/assets/images/logo.png'
            )
        )
    );
    
    // Ajout de l'image mise en avant si disponible
    if (has_post_thumbnail()) {
        $schema['image'] = array(
            '@type' => 'ImageObject',
            'url' => get_the_post_thumbnail_url(null, 'full')
        );
    }
    
    // Insertion du balisage JSON-LD dans l'en-tête
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_head', 'gastro_starter_add_blog_schema');

/**
 * Ajoute le balisage Schema.org pour les pages de menu
 */
function gastro_starter_add_menu_schema() {
    if (!is_page('menu')) {
        return;
    }
    
    // Construction du schéma JSON-LD pour le menu
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Menu',
        'name' => 'Menu du Restaurant',
        'description' => 'Découvrez notre carte de spécialités',
        'hasMenuSection' => array(
            array(
                '@type' => 'MenuSection',
                'name' => 'Entrées',
                'description' => 'Nos entrées de saison',
                'hasMenuItem' => array(
                    array(
                        '@type' => 'MenuItem',
                        'name' => 'Entrée du jour',
                        'description' => 'Entrée de saison selon arrivages',
                        'price' => '12€',
                        'suitableForDiet' => 'None'
                    )
                )
            ),
            array(
                '@type' => 'MenuSection',
                'name' => 'Plats',
                'description' => 'Nos plats principaux',
                'hasMenuItem' => array(
                    array(
                        '@type' => 'MenuItem',
                        'name' => 'Plat du jour',
                        'description' => 'Plat de saison selon arrivages du marché',
                        'price' => '18€',
                        'suitableForDiet' => 'None'
                    )
                )
            ),
            array(
                '@type' => 'MenuSection',
                'name' => 'Desserts',
                'description' => 'Nos desserts maison',
                'hasMenuItem' => array(
                    array(
                        '@type' => 'MenuItem',
                        'name' => 'Dessert du jour',
                        'description' => 'Dessert maison de saison',
                        'price' => '8€',
                        'suitableForDiet' => 'None'
                    )
                )
            )
        )
    );
    
    // Insertion du balisage JSON-LD dans le pied de page
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_footer', 'gastro_starter_add_menu_schema');

// LocalBusiness supprimé : le schéma Restaurant (qui hérite de LocalBusiness) suffit

/**
 * Ajoute le balisage Schema.org Breadcrumb dans l'en-tête
 */
function gastro_starter_add_breadcrumb_schema() {
    // Si un plugin SEO est actif, il gère souvent BreadcrumbList
    if (gastro_starter_has_seo_plugin()) {
        return;
    }
    if (is_front_page()) {
        return;
    }
    
    global $post;
    
    $breadcrumbs = array();
    $position = 1;
    
    // Page d'accueil
    $breadcrumbs[] = array(
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => __('Accueil', 'gastro-starter'),
        'item' => home_url()
    );
    
    if (is_singular('post')) {
        // Blog
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Blog', 'gastro-starter'),
            'item' => get_permalink(get_option('page_for_posts'))
        );
        
        // Article
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => get_the_title(),
            'item' => get_permalink()
        );
    } elseif (is_page()) {
        // Page
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => get_the_title(),
            'item' => get_permalink()
        );
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbs
    );
    
    // Insertion du balisage JSON-LD dans l'en-tête
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_head', 'gastro_starter_add_breadcrumb_schema');

/**
 * Schema Event pour les pages d'événements individuels
 * Génère un rich snippet avec date, lieu et prix dans les SERP
 */
function gastro_starter_add_event_schema() {
    if (!is_singular('event')) {
        return;
    }
    
    $post_id    = get_the_ID();
    $event_date = get_post_meta($post_id, 'event_date', true);
    $event_time = get_post_meta($post_id, 'event_time', true);
    $event_price = get_post_meta($post_id, 'event_price', true);
    $event_status = get_post_meta($post_id, 'event_status', true) ?: 'open';
    
    if (empty($event_date)) {
        return;
    }
    
    $start_datetime = $event_date;
    if ($event_time) {
        $start_datetime .= 'T' . $event_time . ':00';
    } else {
        $start_datetime .= 'T19:00:00';
    }
    
    $status_map = array(
        'open'   => 'https://schema.org/EventScheduled',
        'full'   => 'https://schema.org/EventScheduled',
        'closed' => 'https://schema.org/EventCancelled',
    );
    
    $image_url = '';
    $hero_image_id = get_post_meta($post_id, 'email_image_id', true);
    if ($hero_image_id) {
        $image_url = wp_get_attachment_image_url($hero_image_id, 'full');
    }
    if (!$image_url && has_post_thumbnail()) {
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
    }
    
    $description = get_post_meta($post_id, '_gastro_starter_meta_description', true);
    if (!$description) {
        $description = wp_strip_all_tags(get_the_excerpt());
    }
    if (!$description) {
        $accroche = get_post_meta($post_id, 'email_accroche', true);
        if ($accroche) {
            $description = wp_strip_all_tags($accroche);
        }
    }
    
    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'FoodEvent',
        'name'        => get_the_title(),
        'description' => $description ?: get_the_title(),
        'url'         => get_permalink(),
        'startDate'   => $start_datetime,
        'eventStatus' => isset($status_map[$event_status]) ? $status_map[$event_status] : $status_map['open'],
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location'    => array(
            '@type'   => 'Restaurant',
            '@id'     => home_url() . '/#restaurant',
            'name'    => get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant'),
            'address' => array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => get_theme_mod('gastro_starter_restaurant_address', '6 avenue du 6 juin 1944'),
                'addressLocality' => 'Paris',
                'postalCode'      => '24500',
                'addressCountry'  => 'FR',
            ),
        ),
        'organizer' => array(
            '@type' => 'Restaurant',
            '@id'   => home_url() . '/#restaurant',
            'name'  => get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant'),
            'url'   => home_url(),
        ),
    );
    
    if ($image_url) {
        $schema['image'] = $image_url;
    }
    
    if ($event_price) {
        $price_num = preg_replace('/[^0-9.,]/', '', $event_price);
        if ($price_num) {
            $schema['offers'] = array(
                '@type'         => 'Offer',
                'price'         => str_replace(',', '.', $price_num),
                'priceCurrency' => 'EUR',
                'url'           => get_permalink(),
                'availability'  => ($event_status === 'full')
                    ? 'https://schema.org/SoldOut'
                    : 'https://schema.org/InStock',
            );
        }
    }
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}
add_action('wp_head', 'gastro_starter_add_event_schema');

/**
 * Schema WebSite pour sitelinks search box dans les SERP
 */
function gastro_starter_add_website_schema() {
    if (!is_front_page()) {
        return;
    }
    if (gastro_starter_has_seo_plugin()) {
        return;
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => get_bloginfo('name'),
        'url'      => home_url('/'),
        'potentialAction' => array(
            '@type'       => 'SearchAction',
            'target'      => home_url('/?s={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ),
    );
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'gastro_starter_add_website_schema');

/**
 * Schema FAQPage sur la page de réservation → accordéon dans les SERP
 */
function gastro_starter_add_faq_schema() {
    if (!is_page('reserver')) {
        return;
    }
    if (gastro_starter_has_seo_plugin()) {
        return;
    }
    
    $faqs = array(
        array(
            'question' => 'Comment réserver une table au restaurant ?',
            'answer'   => 'Réservez en ligne directement sur notre site en quelques clics, ou appelez-nous au 05 53 22 78 90. Nous recommandons de réserver à l\'avance, surtout le week-end.',
        ),
        array(
            'question' => 'Quels sont les horaires du restaurant Mon Restaurant ?',
            'answer'   => 'Mon Restaurant est ouvert du mardi au samedi soir. Consultez notre site pour les horaires exacts et les éventuelles fermetures exceptionnelles.',
        ),
        array(
            'question' => 'Mon Restaurant propose-t-il des options végétariennes ?',
            'answer'   => 'Oui, notre carte évolue chaque semaine selon les arrivages du marché et inclut toujours des options végétariennes. N\'hésitez pas à nous signaler vos allergies ou régimes lors de la réservation.',
        ),
        array(
            'question' => 'Peut-on offrir un bon cadeau pour le restaurant ?',
            'answer'   => 'Absolument ! Nos bons cadeaux sont disponibles en ligne avec livraison instantanée par email. Rendez-vous sur notre page Bons Cadeaux.',
        ),
    );
    
    $faq_items = array();
    foreach ($faqs as $faq) {
        $faq_items[] = array(
            '@type'          => 'Question',
            'name'           => $faq['question'],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $faq['answer'],
            ),
        );
    }
    
    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_items,
    );
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}
add_action('wp_head', 'gastro_starter_add_faq_schema'); 