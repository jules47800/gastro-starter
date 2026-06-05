<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire de création de voucher (soumission du formulaire)
 * Valide le montant et crée le voucher en BDD, puis redirige vers la page de paiement
 */
function gastro_starter_handle_create_voucher_request() {
    // Vérification du nonce
    if (!isset($_POST['voucher_nonce']) || !wp_verify_nonce($_POST['voucher_nonce'], 'gastro_starter_voucher_nonce')) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Sécurité'), home_url('/bon-achat')));
        exit;
    }
    
    // Honeypot anti-spam
    if (!empty($_POST['hp_field'])) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Spam détecté'), home_url('/bon-achat')));
        exit;
    }
    
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    
    // Validation : vérifier que le montant est dans la liste des montants autorisés
    $allowed_amounts = get_option('gastro_starter_voucher_amounts', array());
    
    if (empty($allowed_amounts)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Aucun montant configuré'), home_url('/bon-achat')));
        exit;
    }
    
    if ($amount < 1 || !in_array($amount, $allowed_amounts, true)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Montant non autorisé'), home_url('/bon-achat')));
        exit;
    }
    
    // Création du voucher en base de données
    $voucher = gastro_starter_create_voucher(array(
        'amount_cents' => $amount * 100,
        'purchaser_name' => isset($_POST['purchaser_name']) ? sanitize_text_field($_POST['purchaser_name']) : '',
        'purchaser_email' => isset($_POST['purchaser_email']) ? sanitize_email($_POST['purchaser_email']) : '',
        'recipient_name' => isset($_POST['recipient_name']) ? sanitize_text_field($_POST['recipient_name']) : '',
        'recipient_email' => isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '',
        'message' => isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : ''
    ));
    
    if (is_wp_error($voucher)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Erreur BDD'), home_url('/bon-achat')));
        exit;
    }
    
    // Redirection vers la page de paiement avec le code voucher
    $voucher_db = gastro_starter_get_voucher($voucher->id);
    $redirect = add_query_arg(array(
        'voucher_created' => 1,
        'voucher' => $voucher_db->code,
        'amount' => intval($voucher_db->amount_cents) / 100,
        'next' => 'pay'
    ), home_url('/bon-achat'));
    
    wp_safe_redirect($redirect);
    exit;
}

// Enregistrer les actions pour les utilisateurs connectés et non-connectés
add_action('admin_post_nopriv_gastro_starter_create_voucher', 'gastro_starter_handle_create_voucher_request');
add_action('admin_post_gastro_starter_create_voucher', 'gastro_starter_handle_create_voucher_request');
