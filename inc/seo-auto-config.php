<?php
/**
 * Script d'application automatique des meta données SEO
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration des meta données par page
 */
function gastro_starter_get_seo_config() {
    $name = get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant');

    return array(
        'front-page' => array(
            'title' => $name . ' | Réservation en ligne',
            'description' => 'Cuisine maison avec les produits du marché. Réservez en ligne en 30 secondes — on vous attend.'
        ),

        'page-menus' => array(
            'title' => 'Notre Carte · ' . $name,
            'description' => 'Découvrez notre carte : produits frais du marché, poissons du jour, viandes maturées. Carte renouvelée chaque semaine.'
        ),
        'page-galerie' => array(
            'title' => 'Galerie Photos · ' . $name,
            'description' => 'Plongez dans l\'ambiance du restaurant : plats de saison, cadre chaleureux, soirées conviviales. Découvrez le restaurant en images.'
        ),
        'page-reserver' => array(
            'title' => 'Réserver une Table · ' . $name,
            'description' => 'Réservez en ligne en 30 secondes. Cuisine bistronomique · Produits locaux · Vins naturels.'
        ),
        'page-decouverte-locale' => array(
            'title' => 'Découvrir Notre Ville · ' . $name,
            'description' => 'Votre restaurant bistronomique au cœur de la ville. Produits locaux, vins naturels, ambiance chaleureuse → Venez nous découvrir.'
        ),
        'archive-daily_menu' => array(
            'title' => 'Menu du Jour · ' . $name,
            'description' => 'L\'ardoise du jour : plats fait maison qui changent chaque jour selon les arrivages du marché. Consultez le menu → Réservez votre table.'
        ),
        'archive-testimonial' => array(
            'title' => 'Avis Clients · ' . $name,
            'description' => 'Ce que nos clients disent du restaurant. Cuisine sincère, accueil chaleureux, produits du terroir : lisez les témoignages.'
        ),
        'page-politique-confidentialite' => array(
            'title' => 'Politique de Confidentialité · ' . $name,
            'description' => 'Politique de confidentialité du restaurant. Protection de vos données personnelles conformément au RGPD.'
        ),
        'page-suppression-donnees' => array(
            'title' => 'Suppression des Données · ' . $name,
            'description' => 'Demandez la suppression de vos données personnelles conformément au RGPD.'
        ),
        'archive-event' => array(
            'title' => 'Agenda · Soirées & Événements | ' . $name,
            'description' => 'Soirées à thème, concerts, dégustations de vins naturels. Consultez les prochains événements → Réservez votre place.'
        ),
        'page-bon-achat' => array(
            'title' => 'Bon Cadeau · ' . $name,
            'description' => 'Offrez une expérience bistronomique unique. Bon cadeau disponible en ligne, livraison instantanée par email.'
        ),
        'page-merci' => array(
            'title' => 'Merci · ' . $name,
            'description' => 'Votre demande a bien été prise en compte. Merci de votre confiance.'
        ),
        '404' => array(
            'title' => 'Page introuvable · ' . $name,
            'description' => 'Cette page n\'existe plus. Découvrez notre carte ou réservez directement votre table.'
        )
    );
}

/**
 * Applique les meta données à une page
 */
function gastro_starter_apply_seo_meta($post_id, $template_name) {
    $config = gastro_starter_get_seo_config();
    
    // Retire le .php de la fin du nom du template
    $template_name = str_replace('.php', '', $template_name);
    
    if (isset($config[$template_name])) {
        update_post_meta($post_id, '_gastro_starter_meta_title', $config[$template_name]['title']);
        update_post_meta($post_id, '_gastro_starter_meta_description', $config[$template_name]['description']);
    }
}

/**
 * Applique les meta données lors de la sauvegarde d'une page
 */
function gastro_starter_auto_apply_seo_meta($post_id) {
    // Vérifie si c'est une sauvegarde automatique
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Vérifie si c'est le bon type de contenu
    if (!in_array(get_post_type($post_id), array('page', 'post'))) {
        return;
    }

    // Récupère le template de la page
    $template = get_page_template_slug($post_id);
    
    // Si pas de template spécifique mais c'est la page d'accueil
    if (empty($template) && get_option('page_on_front') == $post_id) {
        $template = 'front-page';
    }
    
    if ($template) {
        gastro_starter_apply_seo_meta($post_id, $template);
    }
}
add_action('save_post', 'gastro_starter_auto_apply_seo_meta');

/**
 * Applique les meta données à toutes les pages existantes
 */
function gastro_starter_apply_seo_to_all_pages() {
    $pages = get_pages();
    
    foreach ($pages as $page) {
        $template = get_page_template_slug($page->ID);
        
        // Gestion spéciale pour la page d'accueil
        if (empty($template) && get_option('page_on_front') == $page->ID) {
            $template = 'front-page';
        }
        
        if ($template) {
            gastro_starter_apply_seo_meta($page->ID, $template);
        }
    }
}

// Fonction pour appliquer manuellement les meta données à toutes les pages
function gastro_starter_manual_apply_seo() {
    if (current_user_can('manage_options')) {
        gastro_starter_apply_seo_to_all_pages();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Les meta données SEO ont été appliquées avec succès à toutes les pages.</p></div>';
        });
    }
}

// Ajoute un bouton dans l'admin pour appliquer les meta données
function gastro_starter_add_seo_button() {
    if (current_user_can('manage_options')) {
        add_management_page(
            'Appliquer les Meta SEO',
            'Appliquer les Meta SEO',
            'manage_options',
            'apply-seo-meta',
            function() {
                if (isset($_POST['apply_seo'])) {
                    gastro_starter_manual_apply_seo();
                }
                ?>
                <div class="wrap">
                    <h1>Appliquer les Meta Données SEO</h1>
                    <form method="post">
                        <p>Cliquez sur le bouton ci-dessous pour appliquer automatiquement les meta données SEO à toutes vos pages.</p>
                        <input type="submit" name="apply_seo" class="button button-primary" value="Appliquer les Meta Données">
                    </form>
                </div>
                <?php
            }
        );
    }
}
add_action('admin_menu', 'gastro_starter_add_seo_button'); 