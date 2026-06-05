<?php
/**
 * Métaboxes pour les événements (Agenda)
 *
 * @package Gastro_Starter
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute les métaboxes pour les événements
 */
function gastro_starter_add_event_metaboxes() {
    add_meta_box(
        'event_details',
        __('Détails de l\'événement', 'gastro-starter'),
        'gastro_starter_event_details_callback',
        'event',
        'normal',
        'high'
    );

    add_meta_box(
        'event_newsletter',
        __('Newsletter Soirées Spéciales', 'gastro-starter'),
        'gastro_starter_event_newsletter_callback',
        'event',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'gastro_starter_add_event_metaboxes');

/**
 * Charger les scripts et styles admin pour la métabox newsletter
 */
function gastro_starter_event_admin_scripts($hook) {
    global $post_type;

    if ($post_type !== 'event') return;
    if (!in_array($hook, ['post.php', 'post-new.php'])) return;

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');

    wp_enqueue_style(
        'brevo-admin-css',
        get_template_directory_uri() . '/assets/css/brevo-admin.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'brevo-admin-js',
        get_template_directory_uri() . '/assets/js/brevo-admin.js',
        ['jquery', 'jquery-ui-sortable'],
        '1.0.0',
        true
    );

    global $post;
    wp_localize_script('brevo-admin-js', 'brevoAdmin', [
        'nonce'      => wp_create_nonce('brevo_newsletter_nonce'),
        'postId'     => $post ? $post->ID : 0,
        'postStatus' => $post ? $post->post_status : 'draft',
        'publishUrl' => $post ? add_query_arg(['action' => 'edit', 'post' => $post->ID, '_publish_event' => 1], admin_url('post.php')) : '',
    ]);
}
add_action('admin_enqueue_scripts', 'gastro_starter_event_admin_scripts');

/**
 * Callback pour afficher le contenu de la métabox "Détails"
 */
function gastro_starter_event_details_callback($post) {
    // Nonce pour la sécurité
    wp_nonce_field('gastro_starter_event_save', 'event_nonce');

    // Récupération des valeurs
    $event_date = get_post_meta($post->ID, 'event_date', true);
    $event_time = get_post_meta($post->ID, 'event_time', true);
    $event_price = get_post_meta($post->ID, 'event_price', true);
    $event_menu_url = get_post_meta($post->ID, 'event_menu_url', true);
    $event_status = get_post_meta($post->ID, 'event_status', true);
    
    if (empty($event_status)) $event_status = 'open';
    ?>

    <div class="gastro-starter-event-admin">
        <style>
            .gastro-starter-event-admin {
                padding: 10px;
                background: #fff;
            }
            .gastro-starter-event-admin .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }
            .gastro-starter-event-admin .form-field {
                margin-bottom: 15px;
            }
            .gastro-starter-event-admin .full-width {
                grid-column: span 2;
            }
            .gastro-starter-event-admin .field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #23282d;
            }
            .gastro-starter-event-admin .flex-row {
                display: flex;
                gap: 10px;
                align-items: center;
            }
        </style>

        <div class="form-grid">
            <div class="form-field">
                <label for="event_date" class="field-label"><?php _e('Date de l\'événement', 'gastro-starter'); ?></label>
                <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr($event_date); ?>" class="widefat" />
            </div>
            
            <div class="form-field">
                <label for="event_time" class="field-label"><?php _e('Heure de début', 'gastro-starter'); ?></label>
                <input type="time" id="event_time" name="event_time" value="<?php echo esc_attr($event_time); ?>" class="widefat" />
            </div>

            <div class="form-field">
                <label for="event_price" class="field-label"><?php _e('Prix (ex: 45€)', 'gastro-starter'); ?></label>
                <input type="text" id="event_price" name="event_price" value="<?php echo esc_attr($event_price); ?>" class="widefat" placeholder="ex: 45€" />
            </div>

            <div class="form-field">
                <label for="event_status" class="field-label"><?php _e('Statut des réservations', 'gastro-starter'); ?></label>
                <select id="event_status" name="event_status" class="widefat">
                    <option value="open" <?php selected($event_status, 'open'); ?>><?php _e('Ouvert (Réservations possibles)', 'gastro-starter'); ?></option>
                    <option value="full" <?php selected($event_status, 'full'); ?>><?php _e('Complet', 'gastro-starter'); ?></option>
                    <option value="closed" <?php selected($event_status, 'closed'); ?>><?php _e('Terminé / Fermé', 'gastro-starter'); ?></option>
                </select>
            </div>
            
            <div class="form-field full-width">
                <label for="event_menu_url" class="field-label"><?php _e('Lien vers le menu (PDF ou Image)', 'gastro-starter'); ?></label>
                <div class="flex-row">
                    <input type="url" id="event_menu_url" name="event_menu_url" value="<?php echo esc_url($event_menu_url); ?>" class="widefat" placeholder="https://..." />
                    <button type="button" class="button button-secondary upload_menu_button"><?php _e('Choisir un fichier', 'gastro-starter'); ?></button>
                </div>
                <p class="description"><?php _e('Laissez vide si le menu est décrit dans le contenu principal.', 'gastro-starter'); ?></p>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($){
        var mediaUploader;
        $('.upload_menu_button').click(function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: '<?php _e('Choisir un fichier menu', 'gastro-starter'); ?>',
                button: {
                    text: '<?php _e('Utiliser ce fichier', 'gastro-starter'); ?>'
                },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#event_menu_url').val(attachment.url);
            });
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

/**
 * Callback pour la métabox "Newsletter Soirées Spéciales"
 */
function gastro_starter_event_newsletter_callback($post) {
    // Récupération des valeurs newsletter (FR)
    $subtitle        = get_post_meta($post->ID, 'email_subtitle', true) ?: 'Soirée Spéciale';
    $accroche        = get_post_meta($post->ID, 'email_accroche', true);
    $image_id        = get_post_meta($post->ID, 'email_image_id', true);
    $places          = get_post_meta($post->ID, 'email_places', true) ?: 'Places limitées — 24 couverts maximum';
    $menu_items      = get_post_meta($post->ID, 'email_menu_items', true) ?: [];
    $menu_image_id   = get_post_meta($post->ID, 'email_menu_image_id', true);
    $citation        = get_post_meta($post->ID, 'email_citation', true);
    $citation_author = get_post_meta($post->ID, 'email_citation_author', true) ?: "L'équipe du restaurant";
    $vins_text       = get_post_meta($post->ID, 'email_vins_text', true);
    $vins_price      = get_post_meta($post->ID, 'email_vins_price', true);

    // Variantes EN
    $title_en           = get_post_meta($post->ID, 'email_title_en', true);
    $subtitle_en        = get_post_meta($post->ID, 'email_subtitle_en', true);
    $accroche_en        = get_post_meta($post->ID, 'email_accroche_en', true);
    $places_en          = get_post_meta($post->ID, 'email_places_en', true);
    $citation_en        = get_post_meta($post->ID, 'email_citation_en', true);
    $citation_author_en = get_post_meta($post->ID, 'email_citation_author_en', true);
    $vins_text_en       = get_post_meta($post->ID, 'email_vins_text_en', true);

    // Galerie
    $gallery_img1    = get_post_meta($post->ID, 'email_gallery_img1', true);
    $gallery_img2    = get_post_meta($post->ID, 'email_gallery_img2', true);

    // Infos d'envoi précédent
    $sent_at    = get_post_meta($post->ID, 'email_sent_at', true);
    $sent_count = get_post_meta($post->ID, 'email_sent_count', true);
    $sent_errors = get_post_meta($post->ID, 'email_sent_errors', true);
    $sent_audience = get_post_meta($post->ID, 'email_sent_audience', true);

    // Image preview (hero)
    $image_preview = '';
    if ($image_id) {
        $img_url = wp_get_attachment_image_url($image_id, 'medium');
        if ($img_url) {
            $image_preview = '<img src="' . esc_url($img_url) . '" style="max-width: 100%; height: auto; border-radius: 6px;" />';
        }
    }

    // Aperçu image menu
    $menu_image_preview = '';
    if ($menu_image_id) {
        $mi_url = wp_get_attachment_image_url($menu_image_id, 'medium');
        if ($mi_url) {
            $menu_image_preview = '<img src="' . esc_url($mi_url) . '" style="max-width: 100%; height: auto; border-radius: 6px;" />';
        }
    }

    // Mode menu courant : image si une image est définie, sinon items
    $menu_mode = $menu_image_id ? 'image' : 'items';

    // Vérifier si Brevo est configuré
    $brevo_configured = !empty(get_option('gastro_starter_brevo_api_key', ''));
    ?>

    <div class="brevo-newsletter-metabox">

        <?php if (!$brevo_configured): ?>
        <div class="brevo-notice brevo-notice-warning">
            <strong>Brevo non configuré</strong> —
            <a href="<?php echo admin_url('options-general.php?page=gastro-starter-brevo'); ?>">Configurez votre clé API Brevo</a> pour activer l'envoi de newsletters.
        </div>
        <?php endif; ?>

        <div class="brevo-intro">
            Personnalisez le contenu de la newsletter envoyée à vos clients.
            La langue est détectée automatiquement (numéro de téléphone international ou extension email anglophone).
            Les champs <em>English version</em> sont optionnels : s'ils sont vides, la version française est utilisée par défaut.
        </div>

        <!-- ===== IMAGE HERO ===== -->
        <div class="brevo-section">
            <div class="brevo-section-title"><span class="brevo-icon-dot"></span> Image principale (hero)</div>

            <div class="brevo-image-picker">
                <div id="brevo-image-preview">
                    <?php if ($image_preview): ?>
                        <?php echo $image_preview; ?>
                    <?php else: ?>
                        <p class="brevo-muted">L'image à la une de l'événement sera utilisée par défaut.</p>
                    <?php endif; ?>
                </div>
                <div class="brevo-image-buttons">
                    <button type="button" id="brevo-choose-image" class="button button-secondary">Choisir une image</button>
                    <button type="button" id="brevo-remove-image" class="button button-link brevo-btn-danger" <?php echo $image_id ? '' : 'style="display:none;"'; ?>>Retirer</button>
                </div>
            </div>
            <input type="hidden" id="email_image_id" name="email_image_id" value="<?php echo esc_attr($image_id); ?>" />
        </div>

        <!-- ===== GALERIE D'AMBIANCE ===== -->
        <div class="brevo-section">
            <div class="brevo-section-title"><span class="brevo-icon-dot"></span> Galerie d'ambiance <span class="brevo-optional">(optionnel)</span></div>
            <p class="description">Affichez deux images côte à côte sous les infos pratiques.</p>

            <div class="brevo-grid-2">
                <div>
                    <div class="brevo-image-picker" data-target="email_gallery_img1">
                        <div class="brevo-image-preview gallery-preview-img1">
                            <?php if ($gallery_img1): ?>
                                <img src="<?php echo wp_get_attachment_image_url($gallery_img1, 'medium'); ?>" style="max-width: 100%; height: auto; border-radius: 6px;" />
                            <?php else: ?>
                                <p class="brevo-muted brevo-center">Image (gauche)</p>
                            <?php endif; ?>
                        </div>
                        <div class="brevo-image-buttons">
                            <button type="button" class="button button-secondary brevo-choose-gallery" data-target="email_gallery_img1">Choisir</button>
                            <button type="button" class="button button-link brevo-remove-gallery brevo-btn-danger" data-target="email_gallery_img1" <?php echo $gallery_img1 ? '' : 'style="display:none;"'; ?>>Retirer</button>
                        </div>
                    </div>
                    <input type="hidden" id="email_gallery_img1" name="email_gallery_img1" value="<?php echo esc_attr($gallery_img1); ?>" />
                </div>

                <div>
                    <div class="brevo-image-picker" data-target="email_gallery_img2">
                        <div class="brevo-image-preview gallery-preview-img2">
                            <?php if ($gallery_img2): ?>
                                <img src="<?php echo wp_get_attachment_image_url($gallery_img2, 'medium'); ?>" style="max-width: 100%; height: auto; border-radius: 6px;" />
                            <?php else: ?>
                                <p class="brevo-muted brevo-center">Image (droite)</p>
                            <?php endif; ?>
                        </div>
                        <div class="brevo-image-buttons">
                            <button type="button" class="button button-secondary brevo-choose-gallery" data-target="email_gallery_img2">Choisir</button>
                            <button type="button" class="button button-link brevo-remove-gallery brevo-btn-danger" data-target="email_gallery_img2" <?php echo $gallery_img2 ? '' : 'style="display:none;"'; ?>>Retirer</button>
                        </div>
                    </div>
                    <input type="hidden" id="email_gallery_img2" name="email_gallery_img2" value="<?php echo esc_attr($gallery_img2); ?>" />
                </div>
            </div>
        </div>

        <!-- ===== CONTENU — Onglets FR/EN ===== -->
        <div class="brevo-section">
            <div class="brevo-section-title"><span class="brevo-icon-dot"></span> Contenu de l'email</div>

            <div class="brevo-tabs" role="tablist">
                <button type="button" class="brevo-tab active" data-tab="fr">Version française</button>
                <button type="button" class="brevo-tab" data-tab="en">English version <span class="brevo-tab-hint">(optionnel)</span></button>
            </div>

            <!-- ===== Onglet FR ===== -->
            <div class="brevo-tab-panel active" data-panel="fr">
                <div class="brevo-grid-2">
                    <div class="brevo-field">
                        <label>Sous-titre</label>
                        <input type="text" name="email_subtitle" value="<?php echo esc_attr($subtitle); ?>" placeholder="ex: Soirée Spéciale" />
                    </div>
                    <div class="brevo-field">
                        <label>Places</label>
                        <input type="text" name="email_places" value="<?php echo esc_attr($places); ?>" placeholder="ex: Places limitées — 24 couverts" />
                    </div>
                </div>

                <div class="brevo-field">
                    <label>Accroche</label>
                    <textarea name="email_accroche" rows="3" placeholder="Un voyage culinaire entre les produits de nos côtes et le terroir du Périgord..."><?php echo esc_textarea($accroche); ?></textarea>
                    <p class="description">Si vide, l'extrait de l'événement sera utilisé.</p>
                </div>

                <div class="brevo-field">
                    <label>Citation / Message du chef</label>
                    <textarea name="email_citation" rows="3" placeholder="Chaque soirée Soirées Spéciales est une invitation à redécouvrir le Périgord..."><?php echo esc_textarea($citation); ?></textarea>
                </div>
                <div class="brevo-field">
                    <label>Auteur de la citation</label>
                    <input type="text" name="email_citation_author" value="<?php echo esc_attr($citation_author); ?>" placeholder="L'équipe du restaurant" />
                </div>

                <div class="brevo-grid-2">
                    <div class="brevo-field">
                        <label>Accord mets &amp; vins</label>
                        <textarea name="email_vins_text" rows="2" placeholder="Notre sélection de vins naturels et bio..."><?php echo esc_textarea($vins_text); ?></textarea>
                    </div>
                    <div class="brevo-field">
                        <label>Prix option vins</label>
                        <input type="text" name="email_vins_price" value="<?php echo esc_attr($vins_price); ?>" placeholder="ex: +25€" />
                    </div>
                </div>
            </div>

            <!-- ===== Onglet EN ===== -->
            <div class="brevo-tab-panel" data-panel="en">
                <p class="description" style="margin-bottom:14px;">
                    Ces champs sont envoyés aux contacts anglophones détectés automatiquement.
                    Laissez vide pour réutiliser la version française.
                </p>

                <div class="brevo-field">
                    <label>Event title (English)</label>
                    <input type="text" name="email_title_en" value="<?php echo esc_attr($title_en); ?>" placeholder="Optional override of the event title" />
                </div>

                <div class="brevo-grid-2">
                    <div class="brevo-field">
                        <label>Subtitle</label>
                        <input type="text" name="email_subtitle_en" value="<?php echo esc_attr($subtitle_en); ?>" placeholder="e.g. Special Evening" />
                    </div>
                    <div class="brevo-field">
                        <label>Seats</label>
                        <input type="text" name="email_places_en" value="<?php echo esc_attr($places_en); ?>" placeholder="e.g. Limited seating — 24 max" />
                    </div>
                </div>

                <div class="brevo-field">
                    <label>Intro / Hook</label>
                    <textarea name="email_accroche_en" rows="3" placeholder="A culinary journey between coast and terroir..."><?php echo esc_textarea($accroche_en); ?></textarea>
                </div>

                <div class="brevo-field">
                    <label>Quote / Chef's message</label>
                    <textarea name="email_citation_en" rows="3" placeholder="Each Soirées Spéciales evening is an invitation to rediscover Périgord..."><?php echo esc_textarea($citation_en); ?></textarea>
                </div>
                <div class="brevo-field">
                    <label>Quote author</label>
                    <input type="text" name="email_citation_author_en" value="<?php echo esc_attr($citation_author_en); ?>" placeholder="Mon Restaurant team" />
                </div>

                <div class="brevo-field">
                    <label>Wine pairing text</label>
                    <textarea name="email_vins_text_en" rows="2" placeholder="A curated selection of organic wines..."><?php echo esc_textarea($vins_text_en); ?></textarea>
                </div>
            </div>
        </div>

        <!-- ===== MENU : Items OU Image ===== -->
        <div class="brevo-section">
            <div class="brevo-section-title"><span class="brevo-icon-dot"></span> Le menu</div>

            <div class="brevo-menu-mode-switch" role="radiogroup" aria-label="Mode d'affichage du menu">
                <label class="brevo-switch-option <?php echo $menu_mode === 'items' ? 'active' : ''; ?>">
                    <input type="radio" name="menu_mode" value="items" <?php checked($menu_mode, 'items'); ?> />
                    <span>Liste de plats</span>
                </label>
                <label class="brevo-switch-option <?php echo $menu_mode === 'image' ? 'active' : ''; ?>">
                    <input type="radio" name="menu_mode" value="image" <?php checked($menu_mode, 'image'); ?> />
                    <span>Image du menu</span>
                </label>
            </div>

            <!-- Mode "items" -->
            <div class="brevo-menu-panel" data-menu-panel="items" <?php echo $menu_mode !== 'items' ? 'style="display:none;"' : ''; ?>>
                <div id="brevo-menu-items-list">
                    <?php if (!empty($menu_items) && is_array($menu_items)): ?>
                        <?php foreach ($menu_items as $i => $item): ?>
                            <div class="brevo-menu-item" data-index="<?php echo $i; ?>">
                                <div class="brevo-menu-item-header">
                                    <span class="brevo-menu-item-number"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span>
                                    <button type="button" class="brevo-remove-item button-link" title="Supprimer">Supprimer</button>
                                </div>
                                <input type="text" name="email_menu_items[<?php echo $i; ?>][name]" value="<?php echo esc_attr($item['name'] ?? ''); ?>" placeholder="Nom du plat (FR)" class="widefat brevo-item-name" />
                                <input type="text" name="email_menu_items[<?php echo $i; ?>][description]" value="<?php echo esc_attr($item['description'] ?? ''); ?>" placeholder="Description courte (FR)" class="widefat brevo-item-desc" />
                                <input type="text" name="email_menu_items[<?php echo $i; ?>][name_en]" value="<?php echo esc_attr($item['name_en'] ?? ''); ?>" placeholder="Dish name (EN) — optional" class="widefat brevo-item-name-en" />
                                <input type="text" name="email_menu_items[<?php echo $i; ?>][description_en]" value="<?php echo esc_attr($item['description_en'] ?? ''); ?>" placeholder="Short description (EN) — optional" class="widefat brevo-item-desc-en" />
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="brevo-add-menu-item" class="button button-secondary">+ Ajouter un plat</button>
            </div>

            <!-- Mode "image" -->
            <div class="brevo-menu-panel" data-menu-panel="image" <?php echo $menu_mode !== 'image' ? 'style="display:none;"' : ''; ?>>
                <p class="description">Importez une photo ou un PDF exporté en image (le menu s'affichera à la place des items texte dans l'email).</p>
                <div class="brevo-image-picker">
                    <div id="brevo-menu-image-preview">
                        <?php if ($menu_image_preview): ?>
                            <?php echo $menu_image_preview; ?>
                        <?php else: ?>
                            <p class="brevo-muted brevo-center">Aucune image menu sélectionnée.</p>
                        <?php endif; ?>
                    </div>
                    <div class="brevo-image-buttons">
                        <button type="button" id="brevo-choose-menu-image" class="button button-secondary">Choisir une image</button>
                        <button type="button" id="brevo-remove-menu-image" class="button button-link brevo-btn-danger" <?php echo $menu_image_id ? '' : 'style="display:none;"'; ?>>Retirer</button>
                    </div>
                </div>
                <input type="hidden" id="email_menu_image_id" name="email_menu_image_id" value="<?php echo esc_attr($menu_image_id); ?>" />
            </div>
        </div>

        <!-- ===== ENVOI ===== -->
        <div class="brevo-section brevo-section-last">
            <div class="brevo-section-title"><span class="brevo-icon-dot"></span> Envoi de la newsletter</div>

            <?php if (get_post_status($post->ID) !== 'publish'): ?>
            <div class="brevo-notice brevo-notice-draft">
                <strong>Événement non publié</strong> — Les clients qui cliqueront sur le lien dans l'email arriveront sur une page introuvable.
                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" id="brevo-publish-link" class="brevo-publish-btn">
                    Publier l'événement maintenant →
                </a>
            </div>
            <?php endif; ?>

            <?php if ($sent_at): ?>
                <div class="brevo-previous-send <?php echo ($sent_errors > 0) ? 'has-errors' : ''; ?>">
                    <strong>Dernier envoi :</strong> <?php echo date_i18n('j F Y à H:i', strtotime($sent_at)); ?>
                    — <?php echo intval($sent_count); ?> emails envoyés
                    <?php if ($sent_errors > 0): ?>, <?php echo intval($sent_errors); ?> erreur(s)<?php endif; ?>
                    (audience : <?php echo $sent_audience === 'all' ? 'Tous' : 'Newsletter'; ?>)
                </div>
            <?php endif; ?>

            <div class="brevo-send-panel">
                <div class="brevo-audience-selector">
                    <label class="brevo-audience-option">
                        <input type="radio" name="email_audience" value="newsletter" checked />
                        <span>Inscrits newsletter uniquement</span>
                    </label>
                    <label class="brevo-audience-option">
                        <input type="radio" name="email_audience" value="all" />
                        <span>Tous les contacts</span>
                    </label>
                </div>

                <div class="brevo-contact-count">
                    <span id="brevo-contact-count">...</span>
                </div>

                <div class="brevo-lang-selector">
                    <span class="brevo-lang-label">Langue du test / aperçu</span>
                    <label class="brevo-lang-opt">
                        <input type="radio" name="preview_lang" value="fr" checked />
                        <span>FR</span>
                    </label>
                    <label class="brevo-lang-opt">
                        <input type="radio" name="preview_lang" value="en" />
                        <span>EN</span>
                    </label>
                </div>

                <div class="brevo-test-section">
                    <input type="email" id="brevo-test-email" placeholder="adresse@test.com" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" />
                    <button type="button" id="brevo-send-test-btn" class="button button-secondary" <?php echo !$brevo_configured ? 'disabled' : ''; ?>>Envoyer le test</button>
                    <span id="brevo-test-status"></span>
                </div>

                <div class="brevo-actions">
                    <button type="button" id="brevo-preview-btn" class="button button-secondary">Prévisualiser</button>
                    <button type="button" id="brevo-send-btn" class="button brevo-send-button" <?php echo !$brevo_configured ? 'disabled' : ''; ?>>Envoyer la newsletter</button>
                </div>

                <div id="brevo-send-status"></div>
            </div>
        </div>

    </div>
    <?php
}

/**
 * Sauvegarde des métadonnées (événement + newsletter)
 */
function gastro_starter_save_event_meta($post_id) {
    // Vérifications de sécurité
    if (!isset($_POST['event_nonce']) || !wp_verify_nonce($_POST['event_nonce'], 'gastro_starter_event_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['post_type']) && 'event' === $_POST['post_type'] && !current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Champs événement classiques
    $fields = array(
        'event_date'   => 'sanitize_text_field',
        'event_time'   => 'sanitize_text_field',
        'event_price'  => 'sanitize_text_field',
        'event_menu_url' => 'esc_url_raw',
        'event_status' => 'sanitize_text_field'
    );
    
    foreach ($fields as $field => $sanitizer) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, call_user_func($sanitizer, $_POST[$field]));
        }
    }

    // Champs newsletter (FR + EN)
    $email_text_fields = [
        // FR
        'email_subtitle',
        'email_accroche',
        'email_places',
        'email_citation',
        'email_citation_author',
        'email_vins_text',
        'email_vins_price',
        // EN
        'email_title_en',
        'email_subtitle_en',
        'email_accroche_en',
        'email_places_en',
        'email_citation_en',
        'email_citation_author_en',
        'email_vins_text_en',
    ];

    foreach ($email_text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Image newsletter principale
    if (isset($_POST['email_image_id'])) {
        $image_id = intval($_POST['email_image_id']);
        if ($image_id > 0) {
            update_post_meta($post_id, 'email_image_id', $image_id);
        } else {
            delete_post_meta($post_id, 'email_image_id');
        }
    }

    // Image menu (remplace les items si définie)
    if (isset($_POST['email_menu_image_id'])) {
        $menu_img = intval($_POST['email_menu_image_id']);
        if ($menu_img > 0) {
            update_post_meta($post_id, 'email_menu_image_id', $menu_img);
        } else {
            delete_post_meta($post_id, 'email_menu_image_id');
        }
    }

    // Galerie
    if (isset($_POST['email_gallery_img1'])) {
        $img1 = intval($_POST['email_gallery_img1']);
        if ($img1 > 0) update_post_meta($post_id, 'email_gallery_img1', $img1);
        else delete_post_meta($post_id, 'email_gallery_img1');
    }

    if (isset($_POST['email_gallery_img2'])) {
        $img2 = intval($_POST['email_gallery_img2']);
        if ($img2 > 0) update_post_meta($post_id, 'email_gallery_img2', $img2);
        else delete_post_meta($post_id, 'email_gallery_img2');
    }

    // Menu items (avec variantes EN)
    if (isset($_POST['email_menu_items']) && is_array($_POST['email_menu_items'])) {
        $sanitized = [];
        foreach ($_POST['email_menu_items'] as $item) {
            if (!empty($item['name'])) {
                $sanitized[] = [
                    'name'           => sanitize_text_field($item['name']),
                    'description'    => sanitize_text_field($item['description'] ?? ''),
                    'name_en'        => sanitize_text_field($item['name_en'] ?? ''),
                    'description_en' => sanitize_text_field($item['description_en'] ?? ''),
                ];
            }
        }
        update_post_meta($post_id, 'email_menu_items', $sanitized);
    }
}
add_action('save_post_event', 'gastro_starter_save_event_meta');
