<?php
/**
 * Intégration Stripe Checkout native (sans plugin)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les clés API Stripe
function gastro_starter_get_stripe_api_keys() {
    $test_mode = get_option('gastro_starter_stripe_test_mode', true);
    
    if ($test_mode) {
        return array(
            'publishable_key' => get_option('gastro_starter_stripe_test_public_key', ''),
            'secret_key' => get_option('gastro_starter_stripe_test_secret_key', ''),
            'mode' => 'test'
        );
    } else {
        return array(
            'publishable_key' => get_option('gastro_starter_stripe_live_public_key', ''),
            'secret_key' => get_option('gastro_starter_stripe_live_secret_key', ''),
            'mode' => 'live'
        );
    }
}

// Créer une session de paiement Stripe Checkout
function gastro_starter_create_stripe_checkout_session($voucher_id, $amount_cents) {
    $keys = gastro_starter_get_stripe_api_keys();
    $secret_key = $keys['secret_key'];
    
    if (empty($secret_key)) {
        return new WP_Error('no_key', 'Clé API Stripe non configurée');
    }
    
    $voucher = gastro_starter_get_voucher($voucher_id);
    if (!$voucher) {
        return new WP_Error('no_voucher', 'Bon d\'achat introuvable');
    }
    
    // URLs de retour
    $success_url = add_query_arg(array(
        'voucher' => $voucher->code,
        'payment' => 'success',
        'session_id' => '{CHECKOUT_SESSION_ID}'
    ), home_url('/merci-voucher'));
    
    $cancel_url = add_query_arg(array(
        'voucher' => $voucher->code,
        'payment' => 'cancelled'
    ), home_url('/merci-voucher'));
    
    // Préparer les données pour Stripe
    $recipient_info = '';
    if (!empty($voucher->recipient_name)) {
        $recipient_info = ' - Pour ' . $voucher->recipient_name;
    }
    
    $data = array(
        'payment_method_types' => array('card'),
        'line_items' => array(
            array(
                'price_data' => array(
                    'currency' => 'eur',
                    'product_data' => array(
                        'name' => 'Bon Cadeau Restaurant Mon Restaurant',
                        'description' => 'Code: ' . $voucher->code . $recipient_info . ' - Valable 1 an',
                    ),
                    'unit_amount' => intval($amount_cents),
                ),
                'quantity' => 1,
            ),
        ),
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'metadata' => array(
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'type' => 'voucher',
            'purchaser_name' => !empty($voucher->purchaser_name) ? $voucher->purchaser_name : '',
            'recipient_name' => !empty($voucher->recipient_name) ? $voucher->recipient_name : '',
        ),
        'customer_email' => !empty($voucher->purchaser_email) ? $voucher->purchaser_email : null,
        'locale' => 'fr',
    );
    
    // Appel API Stripe
    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => http_build_query($data),
        'timeout' => 30,
    ));
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body);
    
    if (isset($result->error)) {
        return new WP_Error('stripe_error', $result->error->message);
    }
    
    if (!isset($result->id) || !isset($result->url)) {
        return new WP_Error('stripe_error', 'Réponse Stripe invalide');
    }
    
    // Sauvegarder la session ID dans le voucher
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    $wpdb->update(
        $table,
        array('stripe_checkout_session_id' => $result->id),
        array('id' => $voucher->id),
        array('%s'),
        array('%d')
    );
    
    return $result;
}

// Endpoint AJAX pour créer la session et rediriger
add_action('wp_ajax_gastro_starter_create_checkout_session', 'gastro_starter_ajax_create_checkout_session');
add_action('wp_ajax_nopriv_gastro_starter_create_checkout_session', 'gastro_starter_ajax_create_checkout_session');

// Nouvelle fonction AJAX : crée le voucher ET la session Stripe en un seul appel
add_action('wp_ajax_gastro_starter_create_voucher_and_checkout', 'gastro_starter_ajax_create_voucher_and_checkout');
add_action('wp_ajax_nopriv_gastro_starter_create_voucher_and_checkout', 'gastro_starter_ajax_create_voucher_and_checkout');

function gastro_starter_ajax_create_voucher_and_checkout() {
    check_ajax_referer('gastro_starter_voucher_nonce', 'nonce');
    
    // Honeypot anti-spam
    if (!empty($_POST['hp_field'])) {
        wp_send_json_error(array('message' => 'Spam détecté'));
    }
    
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    
    // Validation : vérifier que le montant est dans la liste des montants autorisés
    $allowed_amounts = get_option('gastro_starter_voucher_amounts', array());
    
    if (empty($allowed_amounts)) {
        wp_send_json_error(array('message' => 'Aucun montant configuré'));
    }
    
    if ($amount < 1 || !in_array($amount, $allowed_amounts, true)) {
        wp_send_json_error(array('message' => 'Montant non autorisé'));
    }
    
    // Créer le voucher en base de données
    $voucher = gastro_starter_create_voucher(array(
        'amount_cents' => $amount * 100,
        'purchaser_name' => isset($_POST['purchaser_name']) ? sanitize_text_field($_POST['purchaser_name']) : '',
        'purchaser_email' => isset($_POST['purchaser_email']) ? sanitize_email($_POST['purchaser_email']) : '',
        'recipient_name' => isset($_POST['recipient_name']) ? sanitize_text_field($_POST['recipient_name']) : '',
        'recipient_email' => isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '',
        'message' => isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : ''
    ));
    
    if (is_wp_error($voucher)) {
        wp_send_json_error(array('message' => 'Erreur lors de la création du bon d\'achat'));
    }
    
    // Créer la session Stripe
    $session = gastro_starter_create_stripe_checkout_session($voucher->id, $amount * 100);
    
    if (is_wp_error($session)) {
        wp_send_json_error(array('message' => $session->get_error_message()));
    }
    
    wp_send_json_success(array(
        'sessionId' => $session->id,
        'url' => $session->url,
        'voucherId' => $voucher->id
    ));
}

function gastro_starter_ajax_create_checkout_session() {
    check_ajax_referer('gastro_starter_stripe_checkout', 'nonce');
    
    $voucher_code = isset($_POST['voucher_code']) ? sanitize_text_field($_POST['voucher_code']) : '';
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    
    if (!$voucher_code || !$amount) {
        wp_send_json_error(array('message' => 'Données manquantes'));
    }
    
    // Récupérer l'ID du voucher depuis la base de données
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    $voucher_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE code = %s LIMIT 1",
        $voucher_code
    ));
    
    if (!$voucher_id) {
        wp_send_json_error(array('message' => 'Voucher introuvable'));
    }
    
    $session = gastro_starter_create_stripe_checkout_session($voucher_id, $amount * 100);
    
    if (is_wp_error($session)) {
        wp_send_json_error(array('message' => $session->get_error_message()));
    }
    
    wp_send_json_success(array(
        'sessionId' => $session->id,
        'url' => $session->url
    ));
}

// Webhook pour gérer les paiements réussis
add_action('rest_api_init', function() {
    register_rest_route('gastro-starter/v1', '/stripe-webhook', array(
        'methods' => 'POST',
        'callback' => 'gastro_starter_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ));
});

function gastro_starter_handle_stripe_webhook(WP_REST_Request $request) {
    $payload = $request->get_body();
    $sig_header = $request->get_header('stripe_signature');
    
    // Récupérer la clé secrète du webhook
    $test_mode = get_option('gastro_starter_stripe_test_mode', true);
    if ($test_mode) {
        $webhook_secret = get_option('gastro_starter_stripe_test_webhook_secret', '');
    } else {
        $webhook_secret = get_option('gastro_starter_stripe_live_webhook_secret', '');
    }
    
    // Si la clé webhook est configurée, vérifier la signature
    if (!empty($webhook_secret) && !empty($sig_header)) {
        try {
            // Vérification de la signature Stripe
            $event = gastro_starter_verify_stripe_signature($payload, $sig_header, $webhook_secret);
        } catch (Exception $e) {
            error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
            return new WP_REST_Response(array('error' => 'Invalid signature'), 400);
        }
    } else {
        // Pas de vérification si la clé webhook n'est pas configurée (mode dev)
        $event = json_decode($payload);
    }
    
    if (!$event || !isset($event->type)) {
        return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
    }
    
    // Gérer l'événement de paiement réussi
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        
        if (isset($session->metadata->voucher_id)) {
            $voucher_id = intval($session->metadata->voucher_id);
            
            // Marquer le voucher comme payé
            gastro_starter_mark_voucher_status($voucher_id, 'paid');
            
            // Envoyer les emails
            gastro_starter_send_voucher_emails($voucher_id);
        }
    }
    
    return new WP_REST_Response(array('received' => true));
}

// Vérification de la signature Stripe (version simplifiée)
function gastro_starter_verify_stripe_signature($payload, $sig_header, $webhook_secret) {
    // Parse le header de signature
    $signatures = array();
    $timestamp = 0;
    
    foreach (explode(',', $sig_header) as $item) {
        $parts = explode('=', $item, 2);
        if (count($parts) === 2) {
            if ($parts[0] === 't') {
                $timestamp = intval($parts[1]);
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }
    }
    
    // Vérifier que le timestamp n'est pas trop ancien (tolérance de 5 minutes)
    if (abs(time() - $timestamp) > 300) {
        throw new Exception('Timestamp too old');
    }
    
    // Calculer la signature attendue
    $signed_payload = $timestamp . '.' . $payload;
    $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);
    
    // Vérifier que la signature correspond
    $signature_valid = false;
    foreach ($signatures as $signature) {
        if (hash_equals($expected_signature, $signature)) {
            $signature_valid = true;
            break;
        }
    }
    
    if (!$signature_valid) {
        throw new Exception('Invalid signature');
    }
    
    return json_decode($payload);
}

// Envoyer les emails de confirmation
function gastro_starter_send_voucher_emails($voucher_id) {
    $voucher = gastro_starter_get_voucher($voucher_id);
    if (!$voucher) {
        return;
    }
    
    $email_manager = gastro_starter_get_email_manager();
    $amount_euros = number_format(((int) $voucher->amount_cents) / 100, 2, ',', ' ');
    
    // URLs importantes
    $download_url = home_url('/telecharger-bon-achat?code=' . urlencode($voucher->code));
    $view_url = home_url('/merci-voucher?voucher=' . urlencode($voucher->code) . '&payment=success');
    
    $details = '<div class="reservation-details">'
        . '<div class="detail-row"><span class="detail-label">Montant :</span> ' . esc_html($amount_euros) . ' €</div>'
        . '<div class="detail-row"><span class="detail-label">Code :</span> <strong>' . esc_html($voucher->code) . '</strong></div>'
        . (!empty($voucher->recipient_name) ? '<div class="detail-row"><span class="detail-label">Bénéficiaire :</span> ' . esc_html($voucher->recipient_name) . '</div>' : '')
        . '</div>';
    
    $note = !empty($voucher->message) ? '<p style="margin-top:16px;">Message :<br>' . nl2br(esc_html($voucher->message)) . '</p>' : '';
    
    // Boutons d'action
    $action_buttons = '<div style="margin: 30px 0; text-align: center;">'
        . '<a href="' . esc_url($download_url) . '" target="_blank" style="display: inline-block; padding: 14px 28px; background: #1a1a1a; color: #fff; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; margin: 0 8px; border-radius: 4px;">📥 Télécharger le PDF</a>'
        . '<a href="' . esc_url($view_url) . '" style="display: inline-block; padding: 14px 28px; background: #fff; color: #1a1a1a; border: 1px solid #e8e3d9; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; margin: 0 8px; border-radius: 4px;">👁️ Voir le bon</a>'
        . '</div>'
        . '<p style="text-align: center; font-size: 12px; color: #8b8680; margin-top: 8px;">💡 Cliquez sur "Télécharger le PDF" puis "Imprimer" et choisissez "Enregistrer au format PDF"</p>';
    
    // Email à l'acheteur
    if (!empty($voucher->purchaser_email)) {
        $subject_buyer = 'Votre bon cadeau Mon Restaurant - ' . $voucher->code;
        $content_buyer = '<h2>🎁 Merci pour votre achat</h2>'
            . '<p>Bonjour' . (!empty($voucher->purchaser_name) ? ' ' . esc_html($voucher->purchaser_name) : '') . ',</p>'
            . '<p>Votre paiement a été confirmé avec succès ! Voici votre bon cadeau pour le restaurant Mon Restaurant.</p>'
            . $details
            . $note
            . $action_buttons
            . '<div style="background: #f9f6f1; padding: 20px; margin: 25px 0; border-left: 4px solid #1a1a1a;">'
            . '<p style="margin: 0; font-size: 14px; line-height: 1.6;"><strong>📄 Comment obtenir votre PDF ?</strong><br>'
            . '1. Cliquez sur le bouton "📥 Télécharger le PDF" ci-dessus<br>'
            . '2. Une nouvelle page s\'ouvrira avec votre bon cadeau<br>'
            . '3. Cliquez sur "Imprimer / Enregistrer en PDF"<br>'
            . '4. Dans la fenêtre d\'impression, choisissez "Enregistrer au format PDF" comme imprimante</p>'
            . '</div>'
            . '<p>Vous pouvez également imprimer directement votre bon cadeau pour l\'offrir.</p>'
            . '<p style="margin-top: 25px;"><strong>Validité :</strong> 1 an à compter de la date d\'achat<br>'
            . '<strong>Utilisation :</strong> Présentez ce code lors de votre venue au restaurant. Le montant sera déduit de votre addition.</p>'
            . '<p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e3d9; font-size: 13px; color: #8b8680;">À très bientôt au restaurant Mon Restaurant !<br>'
            . '<a href="' . home_url('/reserver') . '" style="color: #1a1a1a; text-decoration: underline;">Réserver une table</a></p>';
        
        $headers_buyer = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Mon Restaurant <contact@mon-restaurant.fr>',
            'Reply-To: contact@mon-restaurant.fr',
        );
        $email_manager->send_email($voucher->purchaser_email, $subject_buyer, $content_buyer, $headers_buyer);
    }
    
    // Email au bénéficiaire (si différent de l'acheteur)
    if (!empty($voucher->recipient_email) && $voucher->recipient_email !== $voucher->purchaser_email) {
        $from_name = !empty($voucher->purchaser_name) ? $voucher->purchaser_name : 'Quelqu\'un';
        
        $subject_recipient = '🎁 Vous avez reçu un bon cadeau Mon Restaurant !';
        $content_recipient = '<h2>🎁 Un bon cadeau pour vous !</h2>'
            . '<p>Bonjour' . (!empty($voucher->recipient_name) ? ' ' . esc_html($voucher->recipient_name) : '') . ',</p>'
            . '<p><strong>' . esc_html($from_name) . '</strong> vous a offert un bon cadeau pour le restaurant Mon Restaurant. Quelle belle attention !</p>'
            . $details
            . $note
            . $action_buttons
            . '<div style="background: #f9f6f1; padding: 20px; margin: 25px 0; border-left: 4px solid #1a1a1a;">'
            . '<p style="margin: 0; font-size: 14px; line-height: 1.6;"><strong>📄 Comment obtenir votre PDF ?</strong><br>'
            . '1. Cliquez sur le bouton "📥 Télécharger le PDF" ci-dessus<br>'
            . '2. Une nouvelle page s\'ouvrira avec votre bon cadeau<br>'
            . '3. Cliquez sur "Imprimer / Enregistrer en PDF"<br>'
            . '4. Dans la fenêtre d\'impression, choisissez "Enregistrer au format PDF" comme imprimante</p>'
            . '</div>'
            . '<p><strong>Comment l\'utiliser ?</strong><br>'
            . 'Présentez simplement le code ci-dessus lors de votre venue au restaurant. Le montant sera déduit de votre addition.</p>'
            . '<p style="margin-top: 25px;"><strong>Validité :</strong> 1 an à compter de la date d\'achat</p>'
            . '<div style="background: #f9f6f1; padding: 20px; margin: 25px 0; text-align: center;">'
            . '<p style="margin: 0 0 15px 0; font-size: 16px;"><strong>Profitez de ce moment au restaurant !</strong></p>'
            . '<a href="' . home_url('/reserver') . '" style="display: inline-block; padding: 12px 28px; background: #1a1a1a; color: #fff; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; border-radius: 4px;">Réserver une table</a>'
            . '</div>'
            . '<p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e3d9; font-size: 13px; color: #8b8680; text-align: center;">À très bientôt au restaurant Mon Restaurant !</p>';
        
        $headers_recipient = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Mon Restaurant <contact@mon-restaurant.fr>',
            'Reply-To: contact@mon-restaurant.fr',
        );
        $email_manager->send_email($voucher->recipient_email, $subject_recipient, $content_recipient, $headers_recipient);
    }
    
    // Email aux admins
    $admin_users = get_users(array('role' => 'administrator'));
    $admin_emails = array_map(function($u){ return $u->user_email; }, $admin_users);
    if (!empty($admin_emails)) {
        $subject_admin = 'Nouveau bon d\'achat payé — ' . $voucher->code;
        $content_admin = '<h2>Nouveau bon d\'achat</h2>' 
            . $details
            . '<p><a href="' . admin_url('admin.php?page=gastro-starter-vouchers') . '">Voir tous les bons d\'achat</a></p>';
        $headers_admin = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Mon Restaurant <contact@mon-restaurant.fr>',
            'Reply-To: contact@mon-restaurant.fr',
        );
        $email_manager->send_email($admin_emails, $subject_admin, $content_admin, $headers_admin);
    }
}
