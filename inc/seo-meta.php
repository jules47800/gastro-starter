<?php
/**
 * Gestion des méta-données SEO et Open Graph
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute une meta box pour les paramètres SEO
 */
function gastro_starter_add_seo_meta_box() {
    $post_types = array('page', 'post', 'daily_menu', 'testimonial', 'event');
    
    foreach ($post_types as $post_type) {
        add_meta_box(
            'gastro_starter_seo_meta_box',
            __('Paramètres SEO & Partage Social', 'gastro-starter'),
            'gastro_starter_render_seo_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'gastro_starter_add_seo_meta_box');

/**
 * Affiche le contenu de la meta box
 */
function gastro_starter_render_seo_meta_box($post) {
    // Récupération des valeurs existantes
    $meta_title = get_post_meta($post->ID, '_gastro_starter_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_gastro_starter_meta_description', true);
    $og_image = get_post_meta($post->ID, '_gastro_starter_og_image', true);
    
    // Nonce pour la sécurité
    wp_nonce_field('gastro_starter_seo_meta_box', 'gastro_starter_seo_meta_box_nonce');
    ?>
    <div class="gastro-starter-seo-meta-box">
        <style>
            .gastro-starter-seo-meta-box .form-field { margin: 1em 0; }
            .gastro-starter-seo-meta-box .form-field label { display: block; margin-bottom: 5px; font-weight: 600; }
            .gastro-starter-seo-meta-box .form-field input[type="text"],
            .gastro-starter-seo-meta-box .form-field textarea { width: 100%; }
            .gastro-starter-seo-meta-box .form-field textarea { height: 80px; }
            .gastro-starter-seo-meta-box .description { color: #666; font-style: italic; margin-top: 5px; }
            .gastro-starter-seo-meta-box .og-image-preview { max-width: 300px; margin-top: 10px; }
            .gastro-starter-seo-meta-box .og-image-preview img { max-width: 100%; height: auto; }
        </style>

        <div class="form-field">
            <label for="gastro_starter_meta_title"><?php _e('Meta Title', 'gastro-starter'); ?></label>
            <input type="text" id="gastro_starter_meta_title" name="gastro_starter_meta_title" 
                   value="<?php echo esc_attr($meta_title); ?>" />
            <p class="description">
                <?php _e('Le titre qui apparaîtra dans les résultats de recherche. Idéalement entre 50-60 caractères.', 'gastro-starter'); ?>
            </p>
        </div>

        <div class="form-field">
            <label for="gastro_starter_meta_description"><?php _e('Meta Description', 'gastro-starter'); ?></label>
            <textarea id="gastro_starter_meta_description" name="gastro_starter_meta_description"><?php echo esc_textarea($meta_description); ?></textarea>
            <p class="description">
                <?php _e('La description qui apparaîtra dans les résultats de recherche. Idéalement entre 150-160 caractères.', 'gastro-starter'); ?>
            </p>
        </div>

        <div class="form-field">
            <label for="gastro_starter_og_image"><?php _e('Image de Partage Social', 'gastro-starter'); ?></label>
            <input type="hidden" id="gastro_starter_og_image" name="gastro_starter_og_image" 
                   value="<?php echo esc_attr($og_image); ?>" />
            
            <button type="button" class="button" id="gastro_starter_og_image_button">
                <?php _e('Choisir une image', 'gastro-starter'); ?>
            </button>

            <div class="og-image-preview">
                <?php if ($og_image): ?>
                    <img src="<?php echo esc_url(wp_get_attachment_image_url($og_image, 'medium')); ?>" />
                <?php endif; ?>
            </div>
            
            <p class="description">
                <?php _e('Cette image sera utilisée lors du partage sur les réseaux sociaux. Taille recommandée : 1200x630 pixels.', 'gastro-starter'); ?>
            </p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var mediaUploader;
        
        $('#gastro_starter_og_image_button').click(function(e) {
            e.preventDefault();
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media({
                title: '<?php _e('Choisir une image de partage', 'gastro-starter'); ?>',
                button: {
                    text: '<?php _e('Utiliser cette image', 'gastro-starter'); ?>'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#gastro_starter_og_image').val(attachment.id);
                $('.og-image-preview').html('<img src="' + attachment.url + '" />');
            });
            
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

/**
 * Sauvegarde les méta-données
 */
function gastro_starter_save_seo_meta_box($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['gastro_starter_seo_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['gastro_starter_seo_meta_box_nonce'], 'gastro_starter_seo_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Sauvegarde des méta-données
    if (isset($_POST['gastro_starter_meta_title'])) {
        update_post_meta($post_id, '_gastro_starter_meta_title', sanitize_text_field($_POST['gastro_starter_meta_title']));
    }
    
    if (isset($_POST['gastro_starter_meta_description'])) {
        update_post_meta($post_id, '_gastro_starter_meta_description', sanitize_textarea_field($_POST['gastro_starter_meta_description']));
    }
    
    if (isset($_POST['gastro_starter_og_image'])) {
        update_post_meta($post_id, '_gastro_starter_og_image', absint($_POST['gastro_starter_og_image']));
    }
}
add_action('save_post', 'gastro_starter_save_seo_meta_box');

/**
 * Ajoute les méta-données dans le head
 */
function gastro_starter_output_seo_meta() {
    global $post;
    
    $meta_title = '';
    $meta_description = '';
    $og_image_id = '';
    
    // Gestion de la page d'accueil
    if (is_front_page()) {
        $config = gastro_starter_get_seo_config();
        if (isset($post->ID)) {
            $meta_title = get_post_meta($post->ID, '_gastro_starter_meta_title', true);
            $meta_description = get_post_meta($post->ID, '_gastro_starter_meta_description', true);
            $og_image_id = get_post_meta($post->ID, '_gastro_starter_og_image', true);
        }
        if (empty($meta_title) && isset($config['front-page'])) {
            $meta_title = $config['front-page']['title'];
        }
        if (empty($meta_description) && isset($config['front-page'])) {
            $meta_description = $config['front-page']['description'];
        }
    }
    // Gestion des pages singulières
    elseif (is_singular() && isset($post->ID)) {
        $meta_title = get_post_meta($post->ID, '_gastro_starter_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_gastro_starter_meta_description', true);
        $og_image_id = get_post_meta($post->ID, '_gastro_starter_og_image', true);
        
        // Fallback: générer une description depuis l'extrait si vide
        if (empty($meta_description)) {
            $excerpt = wp_strip_all_tags(get_the_excerpt($post->ID));
            if (!empty($excerpt)) {
                $meta_description = wp_trim_words($excerpt, 25, '...');
            }
        }
    }
    // Gestion des archives avec configuration automatique
    elseif (is_archive()) {
        $config = gastro_starter_get_seo_config();
        
        if (is_post_type_archive('daily_menu') && isset($config['archive-daily_menu'])) {
            $meta_title = $config['archive-daily_menu']['title'];
            $meta_description = $config['archive-daily_menu']['description'];
        }
        elseif (is_post_type_archive('testimonial') && isset($config['archive-testimonial'])) {
            $meta_title = $config['archive-testimonial']['title'];
            $meta_description = $config['archive-testimonial']['description'];
        }
        elseif (is_post_type_archive('event') && isset($config['archive-event'])) {
            $meta_title = $config['archive-event']['title'];
            $meta_description = $config['archive-event']['description'];
        }
    }
    // Gestion de la 404
    elseif (is_404()) {
        $config = gastro_starter_get_seo_config();
        if (isset($config['404'])) {
            $meta_title = $config['404']['title'];
            $meta_description = $config['404']['description'];
        }
    }
    
    // Pour les événements sans titre custom : générer un titre attractif
    if (empty($meta_title) && is_singular('event') && isset($post->ID)) {
        $event_date = get_post_meta($post->ID, 'event_date', true);
        $date_str = '';
        if ($event_date) {
            $date_obj = new DateTime($event_date);
            $date_str = date_i18n('j F Y', $date_obj->getTimestamp());
        }
        $meta_title = get_the_title() . ($date_str ? ' · ' . $date_str : '') . ' | Mon Restaurant';
    }
    
    // Titre personnalisé
    if (!empty($meta_title)) {
        add_filter('document_title_parts', function($title) use ($meta_title) {
            return array('title' => $meta_title);
        }, 99);
        add_filter('document_title_separator', function() {
            return '';
        }, 99);
        
        echo '<meta property="og:title" content="' . esc_attr($meta_title) . '" />' . "\n";
    }
    
    // Description personnalisée
    if (!empty($meta_description)) {
        echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '" />' . "\n";
    }
    
    // Image Open Graph personnalisée
    if (!empty($og_image_id)) {
        $og_image_url = wp_get_attachment_image_url($og_image_id, 'full');
        if ($og_image_url) {
            echo '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
            
            $og_image_meta = wp_get_attachment_metadata($og_image_id);
            if (!empty($og_image_meta['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr($og_image_meta['width']) . '" />' . "\n";
            }
            if (!empty($og_image_meta['height'])) {
                echo '<meta property="og:image:height" content="' . esc_attr($og_image_meta['height']) . '" />' . "\n";
            }
        }
    }
    
    // Méta-données générales
    echo '<meta property="og:type" content="' . (is_front_page() ? 'website' : 'article') . '" />' . "\n";
    
    // URL canonique pour OG
    if (is_front_page()) {
        $og_url = home_url('/');
    } elseif (is_singular()) {
        $og_url = get_permalink();
    } else {
        $og_url = home_url(add_query_arg(array(), wp_unslash($_SERVER['REQUEST_URI'] ?? '')));
    }
    echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
    echo '<meta property="og:site_name" content="Mon Restaurant" />' . "\n";
    
    // Fallback OG image si aucune image spécifiée
    if (empty($og_image_id)) {
        if (is_singular() && has_post_thumbnail()) {
            echo '<meta property="og:image" content="' . esc_url(get_the_post_thumbnail_url(null, 'full')) . '" />' . "\n";
        } else {
            $default_og = get_template_directory_uri() . '/assets/images/restaurant-facade.jpg';
            echo '<meta property="og:image" content="' . esc_url($default_og) . '" />' . "\n";
        }
    }
}
add_action('wp_head', 'gastro_starter_output_seo_meta', 0);

/**
 * Séparateur de titre par défaut propre (pour pages sans titre custom)
 */
add_filter('document_title_separator', function($sep) {
    return '·';
}, 10);