<?php
/**
 * Handlers AJAX pour l'envoi de newsletters Soirées Spéciales via Brevo
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Récupérer le nombre de contacts pour une audience donnée
 */
function gastro_starter_brevo_get_contact_count() {
    check_ajax_referer('brevo_newsletter_nonce', '_nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes', 'gastro-starter')]);
    }

    $audience = sanitize_text_field($_POST['audience'] ?? 'newsletter');
    $sender = new Gastro_Starter_Brevo_Sender();
    $count = $sender->count_contacts($audience);

    wp_send_json_success(['count' => $count]);
}
add_action('wp_ajax_gastro_starter_brevo_get_contact_count', 'gastro_starter_brevo_get_contact_count');

/**
 * AJAX: Prévisualiser l'email
 */
function gastro_starter_brevo_preview_email() {
    check_ajax_referer('brevo_newsletter_nonce', '_nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes', 'gastro-starter')]);
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || get_post_type($post_id) !== 'event') {
        wp_send_json_error(['message' => __('Événement invalide', 'gastro-starter')]);
    }

    // Sauvegarder d'abord les champs temporaires (envoyés via AJAX)
    gastro_starter_save_email_fields_from_ajax($post_id);

    $lang = sanitize_text_field($_POST['lang'] ?? 'fr');
    if (!in_array($lang, ['fr', 'en'], true)) $lang = 'fr';

    $sender = new Gastro_Starter_Brevo_Sender();
    $html    = $sender->generate_email_html($post_id, $lang);
    $subject = $sender->generate_subject($post_id, $lang);

    wp_send_json_success([
        'html'    => $html,
        'subject' => $subject,
    ]);
}
add_action('wp_ajax_gastro_starter_brevo_preview_email', 'gastro_starter_brevo_preview_email');

/**
 * AJAX: Envoyer la newsletter
 */
function gastro_starter_brevo_send_email() {
    check_ajax_referer('brevo_newsletter_nonce', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Seuls les administrateurs peuvent envoyer des newsletters', 'gastro-starter')]);
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    $audience = sanitize_text_field($_POST['audience'] ?? 'newsletter');

    if (!$post_id || get_post_type($post_id) !== 'event') {
        wp_send_json_error(['message' => __('Événement invalide', 'gastro-starter')]);
    }

    // Sauvegarder les champs depuis le formulaire AJAX
    gastro_starter_save_email_fields_from_ajax($post_id);

    $sender = new Gastro_Starter_Brevo_Sender();

    if (!$sender->is_configured()) {
        wp_send_json_error([
            'message' => __('Brevo non configuré. Allez dans Réglages > Brevo pour ajouter votre clé API.', 'gastro-starter')
        ]);
    }

    $result = $sender->send_newsletter($post_id, $audience);

    if ($result['success'] || $result['sent'] > 0) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_gastro_starter_brevo_send_email', 'gastro_starter_brevo_send_email');

/**
 * Sauvegarde les champs email depuis une requête AJAX
 * (pour prévisualisation et envoi sans sauvegarder le post)
 */
function gastro_starter_save_email_fields_from_ajax($post_id) {
    $text_fields = [
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

    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Image principale
    if (isset($_POST['email_image_id'])) {
        $image_id = intval($_POST['email_image_id']);
        if ($image_id > 0) {
            update_post_meta($post_id, 'email_image_id', $image_id);
        } else {
            delete_post_meta($post_id, 'email_image_id');
        }
    }

    // Image menu (remplace les items)
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

    // Menu items (JSON array, avec variantes EN)
    if (isset($_POST['email_menu_items'])) {
        $raw_items = $_POST['email_menu_items'];
        // Si c'est un JSON string, le décoder
        if (is_string($raw_items)) {
            $raw_items = json_decode(stripslashes($raw_items), true);
        }

        $sanitized = [];
        if (is_array($raw_items)) {
            foreach ($raw_items as $item) {
                if (!empty($item['name'])) {
                    $sanitized[] = [
                        'name'           => sanitize_text_field($item['name']),
                        'description'    => sanitize_text_field($item['description'] ?? ''),
                        'name_en'        => sanitize_text_field($item['name_en'] ?? ''),
                        'description_en' => sanitize_text_field($item['description_en'] ?? ''),
                    ];
                }
            }
        }
        update_post_meta($post_id, 'email_menu_items', $sanitized);
    }
}

/**
 * AJAX: Envoyer un email de test à une adresse spécifique
 */
function gastro_starter_brevo_send_test_email() {
    check_ajax_referer('brevo_newsletter_nonce', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes', 'gastro-starter')]);
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    $test_email = sanitize_email($_POST['test_email'] ?? '');

    if (!$post_id || get_post_type($post_id) !== 'event') {
        wp_send_json_error(['message' => __('Événement invalide', 'gastro-starter')]);
    }

    if (!is_email($test_email)) {
        wp_send_json_error(['message' => __('Adresse email de test invalide', 'gastro-starter')]);
    }

    // Sauvegarder les champs
    gastro_starter_save_email_fields_from_ajax($post_id);

    $sender = new Gastro_Starter_Brevo_Sender();

    if (!$sender->is_configured()) {
        wp_send_json_error(['message' => __('Brevo non configuré', 'gastro-starter')]);
    }

    // Langue forcée par le sélecteur admin, sinon détection auto
    $forced_lang = sanitize_text_field($_POST['lang'] ?? '');
    $test_lang = in_array($forced_lang, ['fr', 'en'], true)
        ? $forced_lang
        : $sender->detect_language($test_email, '');

    $html    = $sender->generate_email_html($post_id, $test_lang);
    $subject = '[TEST - ' . strtoupper($test_lang) . '] ' . $sender->generate_subject($post_id, $test_lang);

    $api_key = get_option('gastro_starter_brevo_api_key', '');
    $sender_name = get_option('gastro_starter_brevo_sender_name', 'Mon Restaurant');
    $sender_email = get_option('gastro_starter_brevo_sender_email', 'contact@mon-restaurant.fr');

    $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
        'headers' => [
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body' => json_encode([
            'sender'      => ['name' => $sender_name, 'email' => $sender_email],
            'to'          => [['email' => $test_email]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
        wp_send_json_success([
            'message' => sprintf(__('Email de test envoyé à %s', 'gastro-starter'), $test_email)
        ]);
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_error([
            'message' => sprintf(__('Erreur Brevo (%d) : %s', 'gastro-starter'), $code, $body['message'] ?? 'Erreur inconnue')
        ]);
    }
}
add_action('wp_ajax_gastro_starter_brevo_send_test_email', 'gastro_starter_brevo_send_test_email');
