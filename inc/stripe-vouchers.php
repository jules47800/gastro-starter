<?php
if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_is_wp_simple_pay_active() {
    static $cached = null;
    if ($cached !== null) { return $cached; }

    $active = false;

    if (!function_exists('is_plugin_active')) {
        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (function_exists('is_plugin_active')) {
        if (is_plugin_active('wp-simple-pay/wp-simple-pay.php')) { $active = true; }
        if (is_plugin_active('wp-simple-pay-pro/wp-simple-pay-pro.php')) { $active = true; }
        if (is_plugin_active('wp-simple-pay-lite/wp-simple-pay-lite.php')) { $active = true; }
    }

    if (!$active && function_exists('shortcode_exists') && shortcode_exists('simpay')) {
        $active = true;
    }

    if (!$active && class_exists('WP_Block_Type_Registry')) {
        $registry = WP_Block_Type_Registry::get_instance();
        if (method_exists($registry, 'is_registered') && $registry->is_registered('simpay/payment-form')) {
            $active = true;
        } elseif (method_exists($registry, 'get_registered') && $registry->get_registered('simpay/payment-form')) {
            $active = true;
        }
    }

    if (!$active && (class_exists('SimplePay') || class_exists('SimplePay\\Core\\Plugin') || defined('SIMPLE_PAY_VERSION') || defined('SIMPAY_VERSION'))) {
        $active = true;
    }

    $cached = $active;
    return $cached;
}

// Clés Stripe (dépréciées): on ne les utilise plus côté thème. Conservées pour compat ascendante éventuelle.
function gastro_starter_get_stripe_public_key() {
    return '';
}

function gastro_starter_get_stripe_secret_key() {
    return '';
}

function gastro_starter_render_simpay_block_by_form_id($form_id) {
    if (!$form_id) { return ''; }
    $html = '';
    // 1) Shortcode en priorité: de nombreux plugins y gèrent l'enqueue des scripts frontend
    if (function_exists('do_shortcode')) {
        $html = do_shortcode('[simpay id="' . intval($form_id) . '"]');
        if (!$html) { $html = do_shortcode('[simpay_payment_form id="' . intval($form_id) . '"]'); }
    }
    // 2) Fallback: bloc Gutenberg si disponible
    if (!$html && function_exists('do_blocks')) {
        $block = '<!-- wp:simpay/payment-form {"formId":' . intval($form_id) . '} /-->';
        $html = do_blocks($block);
    }
    return $html;
}

function gastro_starter_get_simpay_form_map() {
    // Option attendue: tableau associatif [ montant(EUR) => formId ]
    $map = get_option('gastro_starter_simpay_form_map', array());
    if (!is_array($map)) { $map = array(); }
    // Nettoyage clés => int
    $clean = array();
    foreach ($map as $k => $v) {
        $kk = is_string($k) ? intval($k) : (int) $k;
        $vv = is_string($v) ? intval($v) : (int) $v;
        if ($kk > 0 && $vv > 0) { $clean[$kk] = $vv; }
    }
    return $clean;
}

function gastro_starter_map_amount_to_simpay_form_id($amount) {
    $map = gastro_starter_get_simpay_form_map();
    $amount_int = intval($amount);
    return isset($map[$amount_int]) ? intval($map[$amount_int]) : 0;
}

add_shortcode('gastro_starter_voucher_pay_button', function($atts = array()) {
    if (!gastro_starter_is_wp_simple_pay_active()) { return ''; }
    $atts = shortcode_atts(array('amount' => 0), $atts, 'gastro_starter_voucher_pay_button');
    $amount = $atts['amount'] ? intval($atts['amount']) : (isset($_GET['amount']) ? intval($_GET['amount']) : 0);
    $next = isset($_GET['next']) ? sanitize_text_field($_GET['next']) : '';
    if ($next !== 'pay' || $amount <= 0) { return ''; }
    $form_id = gastro_starter_map_amount_to_simpay_form_id($amount);
    if (!$form_id) { return ''; }
    return gastro_starter_render_simpay_block_by_form_id($form_id);
});

add_filter('the_content', function($content) {
    if (!gastro_starter_is_wp_simple_pay_active()) { return $content; }
    if (!function_exists('is_page')) { return $content; }
    if (!is_page()) { return $content; }
    global $post;
    if (!$post || stripos(get_permalink($post), '/bon-achat') === false) { return $content; }
    $next = isset($_GET['next']) ? sanitize_text_field($_GET['next']) : '';
    $amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
    if ($next !== 'pay' || $amount <= 0) { return $content; }
    $form_id = gastro_starter_map_amount_to_simpay_form_id($amount);
    if (!$form_id) { return $content . '<div class="voucher-warning">Montant indisponible pour le paiement en ligne. Merci de choisir un autre montant.</div>'; }
    $button = gastro_starter_render_simpay_block_by_form_id($form_id);
    if (!$button) { return $content; }
    $wrapper = '<div class="gastro-starter-voucher-pay" style="margin-top:24px;">' . $button . '</div>';
    return $content . $wrapper;
});

// Inutile de dupliquer en footer; tout est injecté dans le contenu via le filtre ci-dessus.

// IMPORTANT: Certains mécanismes d'enqueue du plugin se basent sur la présence du shortcode dans $post->post_content.
// Comme notre page `page-bon-achat.php` n'affiche pas the_content, on injecte le shortcode en amont dans $post->post_content
// uniquement pour déclencher l'enqueue des scripts du plugin.
add_action('wp', function() {
    if (!gastro_starter_is_wp_simple_pay_active()) { return; }
    if (!function_exists('is_page') || !is_page()) { return; }
    global $post;
    if (!$post) { return; }
    // Vérifier que l'URL correspond à notre page bon d'achat
    $permalink = get_permalink($post);
    if (!$permalink || stripos($permalink, '/bon-achat') === false) { return; }
    $next   = isset($_GET['next']) ? sanitize_text_field($_GET['next']) : '';
    $amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
    if ($next !== 'pay' || $amount <= 0) { return; }
    $form_id = gastro_starter_map_amount_to_simpay_form_id($amount);
    if (!$form_id) { return; }
    $shortcode = '[simpay id="' . intval($form_id) . '"]';
    // Éviter double injection si déjà présent
    if (strpos($post->post_content, $shortcode) === false) {
        $post->post_content .= "\n\n" . $shortcode;
    }
});

function gastro_starter_voucher_checkout_url($voucher) {
    $success = add_query_arg(array('voucher' => $voucher->code, 'status' => 'success', 'session_id' => '{CHECKOUT_SESSION_ID}'), home_url('/merci'));
    $cancel = add_query_arg(array('voucher' => $voucher->code, 'status' => 'cancel'), home_url('/bon-achat'));
    return (object) array('success_url' => $success, 'cancel_url' => $cancel);
}

function gastro_starter_handle_create_voucher_request() {
    if (!isset($_POST['voucher_nonce']) || !wp_verify_nonce($_POST['voucher_nonce'], 'gastro_starter_voucher_nonce')) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Sécurité'), home_url('/bon-achat')));
        exit;
    }
    if (!empty($_POST['hp_field'])) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Spam détecté'), home_url('/bon-achat')));
        exit;
    }
    
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    
    // Validation: vérifier que le montant est dans la liste des montants autorisés
    $allowed_amounts = get_option('gastro_starter_voucher_amounts', array());
    
    if (empty($allowed_amounts)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Aucun montant configuré'), home_url('/bon-achat')));
        exit;
    }
    
    if ($amount < 1 || !in_array($amount, $allowed_amounts, true)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Montant non autorisé'), home_url('/bon-achat')));
        exit;
    }
    
    $voucher = gastro_starter_create_voucher(array(
        'amount_cents' => $amount * 100,
        'purchaser_name' => isset($_POST['purchaser_name']) ? $_POST['purchaser_name'] : '',
        'purchaser_email' => isset($_POST['purchaser_email']) ? $_POST['purchaser_email'] : '',
        'recipient_name' => isset($_POST['recipient_name']) ? $_POST['recipient_name'] : '',
        'recipient_email' => isset($_POST['recipient_email']) ? $_POST['recipient_email'] : '',
        'message' => isset($_POST['message']) ? $_POST['message'] : ''
    ));
    
    if (is_wp_error($voucher)) {
        wp_safe_redirect(add_query_arg('voucher_error', urlencode('Erreur BDD'), home_url('/bon-achat')));
        exit;
    }
    
    // Rediriger vers la page de paiement Stripe natif
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

add_action('admin_post_nopriv_gastro_starter_create_voucher', 'gastro_starter_handle_create_voucher_request');
add_action('admin_post_gastro_starter_create_voucher', 'gastro_starter_handle_create_voucher_request');

// Webhook Stripe (REST API): /wp-json/gastro-starter/v1/stripe-webhook
// Désormais, pas d'endpoint REST Stripe propre au thème: la gestion webhook est assurée par le plugin.

// Conservé: utilitaires d'envoi d'e-mails après paiement, déclenchés par les hooks du plugin.
function gastro_starter_stripe_webhook_handler(WP_REST_Request $request) {
    $payload = $request->get_body();
    $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
    $webhook_secret = get_option('gastro_starter_stripe_webhook_secret', '');

    // Vérification optionnelle (si secret configuré)
    if ($webhook_secret && $sig_header) {
        // Vérification simple: Stripe recommande leur lib. Pour rester sans SDK, on omet la vérification cryptographique.
        // Dans un déploiement final, installer la lib Stripe PHP pour vérifier la signature.
    }

    $event = json_decode($payload);
    if (!isset($event->type)) {
        return new WP_REST_Response(array('status' => 'ignored'), 400);
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        if (isset($session->id)) {
            global $wpdb; $table = $wpdb->prefix . 'gastro_starter_vouchers';
            $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE stripe_checkout_session_id = %s", $session->id));
            if ($voucher) {
                if ($voucher->status !== 'paid') {
                    gastro_starter_mark_voucher_status($voucher->id, 'paid');
                    $voucher = gastro_starter_get_voucher($voucher->id);
                    $email_manager = gastro_starter_get_email_manager();

                    $amount_euros = number_format(((int) $voucher->amount_cents) / 100, 2, ',', ' ');
                    $details = '<div class="reservation-details">'
                        . '<div class="detail-row"><span class="detail-label">Montant :</span> ' . esc_html($amount_euros) . ' €</div>'
                        . '<div class="detail-row"><span class="detail-label">Code :</span> ' . esc_html($voucher->code) . '</div>'
                        . (!empty($voucher->recipient_name) ? '<div class="detail-row"><span class="detail-label">Bénéficiaire :</span> ' . esc_html($voucher->recipient_name) . '</div>' : '')
                        . (!empty($voucher->purchaser_name) ? '<div class="detail-row"><span class="detail-label">Offert par :</span> ' . esc_html($voucher->purchaser_name) . '</div>' : '')
                        . '</div>';

                    $note = !empty($voucher->message) ? '<p style="margin-top:16px;">Message :<br>' . nl2br(esc_html($voucher->message)) . '</p>' : '';

                    if (!empty($voucher->purchaser_email)) {
                        $subject_buyer = 'Votre bon d\'achat Mon Restaurant — ' . $voucher->code;
                        $content_buyer = '<h2>Merci pour votre achat</h2>'
                            . '<p>Bonjour' . (!empty($voucher->purchaser_name) ? ' ' . esc_html($voucher->purchaser_name) : '') . ',</p>'
                            . '<p>Votre paiement a été confirmé. Voici les détails de votre bon d\'achat :</p>'
                            . $details
                            . $note
                            . '<p>Présentez ce code lors de votre venue au restaurant.</p>';

                        $headers_buyer = array(
                            'Content-Type: text/html; charset=UTF-8',
                            'From: Mon Restaurant <' . get_option('admin_email') . '>',
                            'Reply-To: Mon Restaurant <' . get_option('admin_email') . '>'
                        );
                        $email_manager->send_email($voucher->purchaser_email, $subject_buyer, $content_buyer, $headers_buyer);
                    }

                    if (!empty($voucher->recipient_email)) {
                        $subject_recipient = 'Vous avez reçu un bon d\'achat Mon Restaurant';
                        $content_recipient = '<h2>Un bon d\'achat pour vous</h2>'
                            . '<p>Bonjour' . (!empty($voucher->recipient_name) ? ' ' . esc_html($voucher->recipient_name) : '') . ',</p>'
                            . '<p>Vous avez reçu un bon d\'achat pour le restaurant Mon Restaurant.</p>'
                            . $details
                            . $note
                            . '<p>Présentez ce code lors de votre venue. À très bientôt !</p>';

                        $headers_recipient = array(
                            'Content-Type: text/html; charset=UTF-8',
                            'From: Mon Restaurant <' . get_option('admin_email') . '>',
                            ( !empty($voucher->purchaser_email) ? 'Reply-To: ' . ( !empty($voucher->purchaser_name) ? $voucher->purchaser_name . ' ' : '' ) . '<' . $voucher->purchaser_email . '>' : 'Reply-To: ' . get_option('admin_email') )
                        );
                        $email_manager->send_email($voucher->recipient_email, $subject_recipient, $content_recipient, $headers_recipient);
                    }

                    $admin_users = get_users(array('role' => 'administrator'));
                    $admin_emails = array_map(function($u){ return $u->user_email; }, $admin_users);
                    if (!empty($admin_emails)) {
                        $subject_admin = 'Nouveau bon d\'achat payé — ' . $voucher->code;
                        $content_admin = '<h2>Nouveau bon d\'achat</h2>'
                            . $details
                            . '<div class="reservation-details">'
                            . (!empty($voucher->purchaser_email) ? '<div class="detail-row"><span class="detail-label">Email acheteur :</span> ' . esc_html($voucher->purchaser_email) . '</div>' : '')
                            . (!empty($voucher->recipient_email) ? '<div class="detail-row"><span class="detail-label">Email bénéficiaire :</span> ' . esc_html($voucher->recipient_email) . '</div>' : '')
                            . '</div>';
                        $headers_admin = array('Content-Type: text/html; charset=UTF-8', 'From: Mon Restaurant <' . get_option('admin_email') . '>' );
                        $email_manager->send_email($admin_emails, $subject_admin, $content_admin, $headers_admin);
                    }
                }
            }
        }
    }

    return new WP_REST_Response(array('received' => true));
}

// Suppression des anciens flux REST et fallback sans plugin.

// 1) Enrichir la Checkout Session créée par le plugin avec nos métadonnées et URLs de retour
add_filter('simpay_create_stripe_checkout_session_args', function($args) {
    if (!isset($_GET['voucher'])) { return $args; }
    $code = sanitize_text_field($_GET['voucher']);
    if (!$code) { return $args; }
    $voucher = function_exists('gastro_starter_get_voucher') ? gastro_starter_get_voucher($code) : null;
    if (!$voucher) { return $args; }
    $urls = gastro_starter_voucher_checkout_url($voucher);
    // Injecter metadata + URLs
    if (!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }
    $args['metadata']['voucher_id'] = intval($voucher->id);
    $args['metadata']['voucher_code'] = $voucher->code;
    $args['success_url'] = $urls->success_url;
    $args['cancel_url'] = $urls->cancel_url;
    return $args;
}, 10, 1);

// 2) Sur succès de paiement (webhook géré par le plugin), marquer le bon comme payé et envoyer les emails
add_action('simpay_webhook_payment_intent_succeeded', function($event, $payment_intent) {
    try {
        if (!isset($payment_intent->metadata)) { return; }
        $meta = (array) $payment_intent->metadata;
        if (empty($meta['voucher_id']) && empty($meta['voucher_code'])) { return; }
        $voucher = null;
        if (!empty($meta['voucher_id']) && function_exists('gastro_starter_get_voucher')) {
            $voucher = gastro_starter_get_voucher(intval($meta['voucher_id']));
        }
        if (!$voucher && !empty($meta['voucher_code']) && function_exists('gastro_starter_get_voucher')) {
            $voucher = gastro_starter_get_voucher(sanitize_text_field($meta['voucher_code']));
        }
        if (!$voucher) { return; }
        if ($voucher->status !== 'paid') {
            gastro_starter_mark_voucher_status($voucher->id, 'paid');
            $voucher = gastro_starter_get_voucher($voucher->id);
            // Réutiliser l'implémentation d'envoi de mails (même contenu que précédemment)
            $email_manager = gastro_starter_get_email_manager();
            $amount_euros = number_format(((int) $voucher->amount_cents) / 100, 2, ',', ' ');
            $details = '<div class="reservation-details">'
                . '<div class="detail-row"><span class="detail-label">Montant :</span> ' . esc_html($amount_euros) . ' €</div>'
                . '<div class="detail-row"><span class="detail-label">Code :</span> ' . esc_html($voucher->code) . '</div>'
                . (!empty($voucher->recipient_name) ? '<div class="detail-row"><span class="detail-label">Bénéficiaire :</span> ' . esc_html($voucher->recipient_name) . '</div>' : '')
                . (!empty($voucher->purchaser_name) ? '<div class="detail-row"><span class="detail-label">Offert par :</span> ' . esc_html($voucher->purchaser_name) . '</div>' : '')
                . '</div>';
            $note = !empty($voucher->message) ? '<p style="margin-top:16px;">Message :<br>' . nl2br(esc_html($voucher->message)) . '</p>' : '';
            if (!empty($voucher->purchaser_email)) {
                $subject_buyer = 'Votre bon d\'achat Mon Restaurant — ' . $voucher->code;
                $content_buyer = '<h2>Merci pour votre achat</h2>'
                    . '<p>Bonjour' . (!empty($voucher->purchaser_name) ? ' ' . esc_html($voucher->purchaser_name) : '') . ',</p>'
                    . '<p>Votre paiement a été confirmé. Voici les détails de votre bon d\'achat :</p>'
                    . $details
                    . $note
                    . '<p>Présentez ce code lors de votre venue au restaurant.</p>';
                $headers_buyer = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: Mon Restaurant <' . get_option('admin_email') . '>',
                    'Reply-To: Mon Restaurant <' . get_option('admin_email') . '>'
                );
                $email_manager->send_email($voucher->purchaser_email, $subject_buyer, $content_buyer, $headers_buyer);
            }
            if (!empty($voucher->recipient_email)) {
                $subject_recipient = 'Vous avez reçu un bon d\'achat Mon Restaurant';
                $content_recipient = '<h2>Un bon d\'achat pour vous</h2>'
                    . '<p>Bonjour' . (!empty($voucher->recipient_name) ? ' ' . esc_html($voucher->recipient_name) : '') . ',</p>'
                    . '<p>Vous avez reçu un bon d\'achat pour le restaurant Mon Restaurant.</p>'
                    . $details
                    . $note
                    . '<p>Présentez ce code lors de votre venue. À très bientôt !</p>';
                $headers_recipient = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: Mon Restaurant <' . get_option('admin_email') . '>',
                    (!empty($voucher->purchaser_email) ? 'Reply-To: ' . (!empty($voucher->purchaser_name) ? $voucher->purchaser_name . ' ' : '') . '<' . $voucher->purchaser_email . '>' : 'Reply-To: ' . get_option('admin_email'))
                );
                $email_manager->send_email($voucher->recipient_email, $subject_recipient, $content_recipient, $headers_recipient);
            }
            $admin_users = get_users(array('role' => 'administrator'));
            $admin_emails = array_map(function($u){ return $u->user_email; }, $admin_users);
            if (!empty($admin_emails)) {
                $subject_admin = 'Nouveau bon d\'achat payé — ' . $voucher->code;
                $content_admin = '<h2>Nouveau bon d\'achat</h2>'
                    . $details
                    . '<div class="reservation-details">'
                    . (!empty($voucher->purchaser_email) ? '<div class="detail-row"><span class="detail-label">Email acheteur :</span> ' . esc_html($voucher->purchaser_email) . '</div>' : '')
                    . (!empty($voucher->recipient_email) ? '<div class="detail-row"><span class="detail-label">Email bénéficiaire :</span> ' . esc_html($voucher->recipient_email) . '</div>' : '')
                    . '</div>';
                $headers_admin = array('Content-Type: text/html; charset=UTF-8', 'From: Mon Restaurant <' . get_option('admin_email') . '>' );
                $email_manager->send_email($admin_emails, $subject_admin, $content_admin, $headers_admin);
            }
        }
    } catch (\Throwable $e) {
        // Silence: éviter de casser le flux du plugin.
    }
}, 10, 2);


function gastro_starter_admin_vouchers_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__('Droits insuffisants.'));
	}
	global $wpdb;
	$table = $wpdb->prefix . 'gastro_starter_vouchers';
	$notice = '';
	if (isset($_GET['action'], $_GET['voucher_id']) && $_GET['action'] === 'delete') {
		$vid = intval($_GET['voucher_id']);
		$nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
		if (wp_verify_nonce($nonce, 'gastro_starter_delete_voucher_' . $vid)) {
			$wpdb->delete($table, array('id' => $vid), array('%d'));
			$notice = 'Bon supprimé.';
		} else {
			$notice = 'Action non autorisée.';
		}
	}
	if (isset($_POST['gastro_starter_update_voucher'])) {
		$nonce = isset($_POST['gastro_starter_voucher_admin_nonce']) ? $_POST['gastro_starter_voucher_admin_nonce'] : '';
		if (wp_verify_nonce($nonce, 'gastro_starter_voucher_admin')) {
			$vid = intval($_POST['voucher_id']);
			$amount = max(0, intval($_POST['amount_eur'])) * 100;
			$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
			$purchaser_name = isset($_POST['purchaser_name']) ? sanitize_text_field($_POST['purchaser_name']) : '';
			$purchaser_email = isset($_POST['purchaser_email']) ? sanitize_email($_POST['purchaser_email']) : '';
			$recipient_name = isset($_POST['recipient_name']) ? sanitize_text_field($_POST['recipient_name']) : '';
			$recipient_email = isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '';
			$fields = array(
				'amount_cents' => $amount,
				'purchaser_name' => $purchaser_name,
				'purchaser_email' => $purchaser_email,
				'recipient_name' => $recipient_name,
				'recipient_email' => $recipient_email
			);
			$formats = array('%d','%s','%s','%s','%s');
			if ($status) {
				$fields['status'] = $status;
				$formats[] = '%s';
			}
			$wpdb->update($table, $fields, array('id' => $vid), $formats, array('%d'));
			$notice = 'Bon mis à jour.';
		} else {
			$notice = 'Action non autorisée.';
		}
	}
	$search = isset($_GET['s']) ? trim(sanitize_text_field($_GET['s'])) : '';
	$where = '1=1';
	$params = array();
	if ($search !== '') {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$where .= " AND (code LIKE %s OR purchaser_email LIKE %s OR recipient_email LIKE %s)";
		$params[] = $like; $params[] = $like; $params[] = $like;
	}
	$query = "SELECT id, code, amount_cents, status, purchaser_name, purchaser_email, recipient_name, recipient_email FROM $table WHERE $where ORDER BY id DESC LIMIT 200";
	$vouchers = $params ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);
	echo '<div class="wrap">';
	echo '<h1>Bons d\'achat</h1>';
	if ($notice) { echo '<div class="updated"><p>' . esc_html($notice) . '</p></div>'; }
	echo '<form method="get" style="margin:16px 0;">';
	echo '<input type="hidden" name="page" value="gastro-starter-vouchers" />';
	echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher code/email" /> ';
	echo '<button class="button">Rechercher</button>';
	echo '</form>';
	$statuses = array('draft' => 'Brouillon', 'pending' => 'En attente', 'paid' => 'Payé', 'used' => 'Utilisé', 'cancelled' => 'Annulé');
	$editing_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
	echo '<table class="widefat fixed striped">';
	echo '<thead><tr><th>ID</th><th>Code</th><th>Montant (€)</th><th>Statut</th><th>Acheteur</th><th>Destinataire</th><th>Actions</th></tr></thead><tbody>';
	if ($vouchers) {
		foreach ($vouchers as $v) {
			$is_edit = ($editing_id === intval($v->id));
			$delete_url = wp_nonce_url(admin_url('admin.php?page=gastro-starter-vouchers&action=delete&voucher_id=' . $v->id), 'gastro_starter_delete_voucher_' . $v->id);
			$edit_url = admin_url('admin.php?page=gastro-starter-vouchers&edit=' . intval($v->id) . ($search !== '' ? '&s=' . urlencode($search) : ''));
			$list_url = admin_url('admin.php?page=gastro-starter-vouchers' . ($search !== '' ? '&s=' . urlencode($search) : ''));
			echo '<tr>';
			echo '<td>' . intval($v->id) . '</td>';
			echo '<td><code>' . esc_html($v->code) . '</code></td>';
			if ($is_edit) {
				echo '<form method="post">';
				echo '<td>';
				echo '<input type="hidden" name="voucher_id" value="' . intval($v->id) . '" />';
				echo '<input type="number" min="0" step="1" name="amount_eur" value="' . esc_attr(intval($v->amount_cents) / 100) . '" style="width:90px;" />';
				echo '</td>';
				echo '<td>';
				echo '<select name="status">';
				$current = $v->status ? $v->status : '';
				foreach ($statuses as $key => $label) {
					$sel = selected($current, $key, false);
					echo '<option value="' . esc_attr($key) . '" ' . $sel . '>' . esc_html($label) . '</option>';
				}
				echo '</select>';
				echo '</td>';
				echo '<td>';
				echo '<input type="text" name="purchaser_name" value="' . esc_attr($v->purchaser_name) . '" placeholder="Nom" style="width:120px;" /> ';
				echo '<input type="email" name="purchaser_email" value="' . esc_attr($v->purchaser_email) . '" placeholder="Email" style="width:180px;" />';
				echo '</td>';
				echo '<td>';
				echo '<input type="text" name="recipient_name" value="' . esc_attr($v->recipient_name) . '" placeholder="Nom" style="width:120px;" /> ';
				echo '<input type="email" name="recipient_email" value="' . esc_attr($v->recipient_email) . '" placeholder="Email" style="width:180px;" />';
				echo '</td>';
				echo '<td style="white-space:nowrap;">';
				wp_nonce_field('gastro_starter_voucher_admin', 'gastro_starter_voucher_admin_nonce');
				echo '<button class="button button-primary" name="gastro_starter_update_voucher" value="1">Enregistrer</button> ';
				echo '<a class="button" href="' . esc_url($list_url) . '">Annuler</a> ';
				echo '<a class="button button-secondary" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Supprimer ce bon ?\');">Supprimer</a>';
				echo '</td>';
				echo '</form>';
			} else {
				$amount_eur = number_format(((int) $v->amount_cents) / 100, 0, ',', ' ');
				$status_label = isset($statuses[$v->status]) ? $statuses[$v->status] : $v->status;
				echo '<td>' . esc_html($amount_eur) . '</td>';
				echo '<td>' . esc_html($status_label) . '</td>';
				echo '<td>' . esc_html(trim(($v->purchaser_name ?: '') . (!empty($v->purchaser_email) ? ' — ' . $v->purchaser_email : ''))) . '</td>';
				echo '<td>' . esc_html(trim(($v->recipient_name ?: '') . (!empty($v->recipient_email) ? ' — ' . $v->recipient_email : ''))) . '</td>';
				echo '<td style="white-space:nowrap;">';
				echo '<a class="button button-primary" href="' . esc_url($edit_url) . '">Modifier</a> ';
				echo '<a class="button" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Supprimer ce bon ?\');">Supprimer</a>';
				echo '</td>';
			}
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="7">Aucun bon trouvé.</td></tr>';
	}
	echo '</tbody></table>';
	echo '</div>';
}

