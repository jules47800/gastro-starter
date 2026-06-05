<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère le message d'accroche pour les bons-cadeaux
 * Rotation automatique selon la période de l'année si aucun message personnalisé
 */
function gastro_starter_get_voucher_pickup_line() {
    // Vérifier si un message personnalisé est défini
    $custom_message = get_option('gastro_starter_voucher_pickup_line', '');
    if (!empty($custom_message)) {
        return $custom_message;
    }
    
    // Sinon, rotation automatique selon le mois
    $month = (int) date('n');
    
    $seasonal_messages = array(
        1 => 'La douceur d\'un bon repas pour la Saint-Valentin',
        2 => 'La douceur d\'un bon repas pour la Saint-Valentin',
        3 => 'Célébrez le printemps avec une table au restaurant',
        4 => 'Célébrez le printemps avec une table au restaurant',
        5 => 'Célébrez le printemps avec une table au restaurant',
        6 => 'L\'été se savoure au restaurant',
        7 => 'L\'été se savoure au restaurant',
        8 => 'L\'été se savoure au restaurant',
        9 => 'Un automne gourmand au restaurant',
        10 => 'Un automne gourmand au restaurant',
        11 => 'Offrez un moment gourmand pour les fêtes',
        12 => 'Offrez un moment gourmand pour les fêtes'
    );
    
    return isset($seasonal_messages[$month]) ? $seasonal_messages[$month] : 'Offrez un moment au restaurant';
}

function gastro_starter_create_vouchers_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'gastro_starter_vouchers';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        code varchar(64) NOT NULL,
        amount_cents int(11) NOT NULL,
        currency varchar(10) NOT NULL DEFAULT 'eur',
        purchaser_name varchar(100) NULL,
        purchaser_email varchar(100) NULL,
        recipient_name varchar(100) NULL,
        recipient_email varchar(100) NULL,
        message text NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        stripe_payment_intent_id varchar(100) NULL,
        stripe_checkout_session_id varchar(100) NULL,
        expires_at datetime NULL,
        redeemed_at datetime NULL,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY code (code),
        PRIMARY KEY (id),
        KEY status (status),
        KEY purchaser_email (purchaser_email)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('after_setup_theme', function() {
    gastro_starter_create_vouchers_table();
});

function gastro_starter_generate_voucher_code($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($chars) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

function gastro_starter_create_voucher(array $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    $code = gastro_starter_generate_voucher_code();

    $insert = $wpdb->insert(
        $table,
        array(
            'code' => $code,
            'amount_cents' => intval($data['amount_cents']),
            'currency' => 'eur',
            'purchaser_name' => isset($data['purchaser_name']) ? sanitize_text_field($data['purchaser_name']) : null,
            'purchaser_email' => isset($data['purchaser_email']) ? sanitize_email($data['purchaser_email']) : null,
            'recipient_name' => isset($data['recipient_name']) ? sanitize_text_field($data['recipient_name']) : null,
            'recipient_email' => isset($data['recipient_email']) ? sanitize_email($data['recipient_email']) : null,
            'message' => isset($data['message']) ? wp_kses_post($data['message']) : null,
            'status' => 'pending'
        ),
        array('%s','%d','%s','%s','%s','%s','%s','%s','%s')
    );

    if (!$insert) {
        return new WP_Error('db_error', 'Erreur lors de la création du bon.');
    }

    return (object) array('id' => $wpdb->insert_id, 'code' => $code);
}

function gastro_starter_mark_voucher_status($voucher_id, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    $allowed = array('pending','paid','redeemed','cancelled');
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    return (bool) $wpdb->update($table, array('status' => $status), array('id' => intval($voucher_id)), array('%s'), array('%d'));
}

function gastro_starter_get_voucher($id_or_code) {
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    if (is_numeric($id_or_code)) {
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id_or_code)));
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE code = %s", $id_or_code));
}

function gastro_starter_update_voucher_stripe_refs($voucher_id, $refs) {
    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    $data = array();
    $format = array();
    if (isset($refs['payment_intent'])) {
        $data['stripe_payment_intent_id'] = sanitize_text_field($refs['payment_intent']);
        $format[] = '%s';
    }
    if (isset($refs['checkout_session'])) {
        $data['stripe_checkout_session_id'] = sanitize_text_field($refs['checkout_session']);
        $format[] = '%s';
    }
    if (!$data) return false;
    return (bool) $wpdb->update($table, $data, array('id' => intval($voucher_id)), $format, array('%d'));
}

function gastro_starter_voucher_form_shortcode() {
    ob_start();
    get_template_part('template-parts/voucher', 'form');
    return ob_get_clean();
}
add_shortcode('gastro_starter_voucher_form', 'gastro_starter_voucher_form_shortcode');


