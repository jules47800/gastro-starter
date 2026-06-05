<?php
/**
 * Fonctions qui améliorent le thème en accrochant aux hooks et en appliquant des filtres
 *
 * @package Gastro_Starter
 */

/**
 * Ajoute des classes personnalisées au tableau de classes body_class
 *
 * @param array $classes Classes pour la balise body.
 * @return array
 */
function gastro_starter_body_classes($classes) {
	// Ajoute une classe 'hfeed' aux pages non singulières
	if (!is_singular()) {
		$classes[] = 'hfeed';
	}

	// Ajoute une classe basée sur le type de page
	if (is_front_page() && !is_home()) {
		$classes[] = 'page-accueil';
	} elseif (is_front_page() && is_home()) {
		$classes[] = 'page-accueil-blog';
	} elseif (is_home()) {
		$classes[] = 'page-blog';
	} elseif (is_archive()) {
		$classes[] = 'page-archive';
	} elseif (is_search()) {
		$classes[] = 'page-search';
	} elseif (is_singular('post')) {
		$classes[] = 'page-article';
	} elseif (is_singular('page')) {
		$classes[] = 'page-simple';
	} elseif (is_singular('menu_item')) {
		$classes[] = 'page-menu-item';
	} elseif (is_singular('testimonial')) {
		$classes[] = 'page-testimonial';
	}

	return $classes;
}
add_filter('body_class', 'gastro_starter_body_classes');

/**
 * Ajoute un titre de page dans la balise title de l'en-tête pour les archives
 *
 * @param string $title La chaîne de titre originale.
 * @return string Le titre mis à jour.
 */
function gastro_starter_archive_title($title) {
	if (is_category()) {
		$title = single_cat_title('', false);
	} elseif (is_tag()) {
		$title = single_tag_title('', false);
	} elseif (is_author()) {
		$title = '<span class="vcard">' . get_the_author() . '</span>';
	} elseif (is_post_type_archive()) {
		$title = post_type_archive_title('', false);
	} elseif (is_tax()) {
		$title = single_term_title('', false);
	}

	return $title;
}
add_filter('get_the_archive_title', 'gastro_starter_archive_title');

/**
 * Fonction pour afficher le logo dans le pied de page
 */
function gastro_starter_footer_logo() {
	if (function_exists('the_custom_logo')) {
		if (has_custom_logo()) {
			the_custom_logo();
		} else {
			echo '<a href="' . esc_url(home_url('/')) . '" rel="home">' . esc_html(get_bloginfo('name')) . '</a>';
		}
	}
}

/**
 * Limite la longueur des extraits
 */
function gastro_starter_custom_excerpt_length($length) {
	return 20;
}
add_filter('excerpt_length', 'gastro_starter_custom_excerpt_length', 999);

/**
 * Modifie le texte "Lire la suite"
 */
function gastro_starter_excerpt_more($more) {
	return '...';
}
add_filter('excerpt_more', 'gastro_starter_excerpt_more');

/**
 * Personnalise le chargement des scripts dans le pied de page
 */
function gastro_starter_scripts_to_footer() {
	remove_action('wp_head', 'wp_print_scripts');
	remove_action('wp_head', 'wp_print_head_scripts', 9);
	remove_action('wp_head', 'wp_enqueue_scripts', 1);
	add_action('wp_footer', 'wp_print_scripts', 5);
	add_action('wp_footer', 'wp_enqueue_scripts', 5);
	add_action('wp_footer', 'wp_print_head_scripts', 5);
}
add_action('wp_enqueue_scripts', 'gastro_starter_scripts_to_footer');

/**
 * Récupère et formate les horaires d'ouverture pour un affichage public.
 * Regroupe les jours consécutifs avec les mêmes horaires.
 *
 * @return string Le HTML formaté des horaires d'ouverture.
 */
function gastro_starter_get_formatted_opening_hours() {
    $schedule = get_option('gastro_starter_daily_schedule');

    // Si les horaires ne sont pas configurés, on ne renvoie rien.
    if (empty($schedule)) {
        return '';
    }

    $days_fr = array(
        'monday'    => __('Lundi', 'gastro-starter'),
        'tuesday'   => __('Mardi', 'gastro-starter'),
        'wednesday' => __('Mercredi', 'gastro-starter'),
        'thursday'  => __('Jeudi', 'gastro-starter'),
        'friday'    => __('Vendredi', 'gastro-starter'),
        'saturday'  => __('Samedi', 'gastro-starter'),
        'sunday'    => __('Dimanche', 'gastro-starter'),
    );
    $days_order = array_keys($days_fr);

    // 1. Créer une chaîne de caractères représentant les horaires pour chaque jour.
    $day_hours_strings = [];
    foreach ($days_order as $day_key) {
        if (isset($schedule[$day_key]) && $schedule[$day_key]['open'] && !empty($schedule[$day_key]['time_ranges'])) {
            $ranges = [];
            foreach ($schedule[$day_key]['time_ranges'] as $range) {
                if (!empty($range['start']) && !empty($range['end'])) {
                    $start = date('G\hi', strtotime($range['start']));
                    $end = date('G\hi', strtotime($range['end']));
                    $ranges[] = str_replace('i', '', str_replace('00', '', $start)) . ' - ' . str_replace('i', '', str_replace('00', '', $end));
                }
            }
            $day_hours_strings[$day_key] = !empty($ranges) ? implode(' / ', $ranges) : __('Fermé', 'gastro-starter');
        } else {
            $day_hours_strings[$day_key] = __('Fermé', 'gastro-starter');
        }
    }

    // 2. Regrouper les jours consécutifs avec des horaires identiques.
    $grouped_schedule = [];
    $current_group = null;
    foreach ($day_hours_strings as $day_key => $hours_string) {
        if ($current_group === null || $hours_string !== $current_group['hours']) {
            if ($current_group !== null) {
                $grouped_schedule[] = $current_group;
            }
            $current_group = ['days' => [], 'hours' => $hours_string];
        }
        $current_group['days'][] = $day_key;
    }
    if ($current_group !== null) {
        $grouped_schedule[] = $current_group;
    }

    // 3. Formater le HTML final.
    $output_html = '<div class="opening-hours-list">';
    foreach ($grouped_schedule as $group) {
        if (empty($group['days'])) continue;

        $day_label = count($group['days']) === 1
            ? $days_fr[reset($group['days'])]
            : $days_fr[reset($group['days'])] . ' - ' . $days_fr[end($group['days'])];

        $hours_display = ($group['hours'] === __('Fermé', 'gastro-starter'))
            ? '<span class="hours-closed">' . $group['hours'] . '</span>'
            : '<span class="hours-open">' . $group['hours'] . '</span>';

        $output_html .= '<p><span class="hours-day">' . $day_label . ' :</span> ' . $hours_display . '</p>';
    }
    $output_html .= '</div>';

    return $output_html;
}

/**
 * ===================================================================
 * Fonctions pour l'affichage conditionnel du menu
 * ===================================================================
 */

/**
 * Masquer le bouton RÉSERVER dans le menu principal sur la page de réservation
 */
function gastro_starter_filter_nav_menu_items($items, $args) {
    if ($args->theme_location === 'menu-principal' && is_page('reserver')) {
        // Recherche et supprime les liens vers la page réserver dans le menu
        // Utilisation d'une expression plus précise pour cibler le lien "RÉSERVER"
        $items = preg_replace('/<li[^>]*>\s*<a[^>]*href="[^"]*\/reserver\/?[^"]*"[^>]*>.*?RÉSERVER.*?<\/a>\s*<\/li>/i', '', $items);
    }
    return $items;
}
add_filter('wp_nav_menu_items', 'gastro_starter_filter_nav_menu_items', 10, 2);

// Ajouter une classe pour identifier les éléments de menu sur la page réservation
function gastro_starter_nav_menu_css_class($classes, $item) {
    // Si on est sur la page réserver et que le lien mène à la page réserver
    if (is_page('reserver') && strpos($item->url, '/reserver') !== false) {
        // Ajouter une classe pour cibler par CSS
        $classes[] = 'hidden-on-reservation-page';
    }
    return $classes;
}
add_filter('nav_menu_css_class', 'gastro_starter_nav_menu_css_class', 10, 2);

// Ajouter le CSS personnalisé pour masquer les éléments du menu sur la page réservation
function gastro_starter_add_reservation_css() {
    if (is_page('reserver')) {
        echo '<style>
            .hidden-on-reservation-page {
                display: none !important;
            }
        </style>';
    }
}
add_action('wp_head', 'gastro_starter_add_reservation_css'); 