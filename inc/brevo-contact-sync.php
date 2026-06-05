<?php
/**
 * Synchronisation automatique des clients vers Brevo (Contacts API)
 *
 * Chaque client est créé/mis à jour dans Brevo lors d'une réservation confirmée.
 * Attributs synchronisés : prénom, nom, téléphone, visites, segment, VIP, dernière visite.
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Synchroniser un client vers Brevo après mise à jour des stats
 */
function gastro_starter_sync_customer_to_brevo($customer_email) {
    $api_key = get_option('gastro_starter_brevo_api_key', '');
    if (empty($api_key)) {
        return;
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE email = %s",
        $customer_email
    ));

    if (!$customer) {
        return;
    }

    $name_parts = explode(' ', trim($customer->name), 2);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';

    $segment = gastro_starter_compute_segment($customer);
    $score = gastro_starter_compute_loyalty_score($customer);

    $attributes = [
        'PRENOM'         => $first_name,
        'NOM'            => $last_name,
        'SMS'            => !empty($customer->phone) ? $customer->phone : '',
        'VISITES'        => (int) $customer->visits,
        'SEGMENT'        => $segment,
        'SCORE_FIDELITE' => $score,
        'VIP'            => (bool) $customer->is_vip,
        'DERNIERE_VISITE'=> $customer->last_visit ? date('Y-m-d', strtotime($customer->last_visit)) : '',
        'NEWSLETTER'     => (bool) $customer->newsletter,
    ];

    $list_ids = gastro_starter_get_brevo_list_ids($customer);

    $payload = [
        'email'            => $customer_email,
        'attributes'       => $attributes,
        'updateEnabled'    => true,
    ];

    if (!empty($list_ids)) {
        $payload['listIds'] = $list_ids;
    }

    $response = wp_remote_post('https://api.brevo.com/v3/contacts', [
        'headers' => [
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body'    => json_encode($payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('[BREVO SYNC] Erreur pour ' . $customer_email . ': ' . $response->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
        error_log('[BREVO SYNC] Contact sync OK: ' . $customer_email);
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $msg = $body['message'] ?? "HTTP $code";
        error_log('[BREVO SYNC] Erreur API pour ' . $customer_email . ' (' . $code . '): ' . $msg);
    }
}

/**
 * Déterminer les listes Brevo pour un client
 */
function gastro_starter_get_brevo_list_ids($customer) {
    $list_ids = [];

    $main_list = (int) get_option('gastro_starter_brevo_list_id', 0);
    if ($main_list > 0) {
        $list_ids[] = $main_list;
    }

    return $list_ids;
}

/**
 * Hook sur la mise à jour des visites client → sync Brevo
 */
function gastro_starter_brevo_sync_on_visit_update($customer_email, $reservation_id = null) {
    gastro_starter_sync_customer_to_brevo($customer_email);
}

/**
 * Synchronisation en masse de tous les clients existants vers Brevo
 */
function gastro_starter_brevo_sync_all_contacts() {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';

    $customers = $wpdb->get_results("SELECT email FROM $customers_table WHERE email IS NOT NULL AND email != ''");
    $synced = 0;
    $errors = 0;

    foreach ($customers as $customer) {
        gastro_starter_sync_customer_to_brevo($customer->email);
        $synced++;
        usleep(100000); // 100ms entre chaque appel pour ne pas dépasser le rate limit
    }

    return ['synced' => $synced, 'errors' => $errors];
}

/**
 * AJAX : Synchroniser tous les contacts vers Brevo
 */
function gastro_starter_brevo_sync_all_ajax() {
    check_ajax_referer('brevo_sync_all', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes', 'gastro-starter')]);
    }

    $result = gastro_starter_brevo_sync_all_contacts();
    wp_send_json_success([
        'message' => sprintf(__('%d contacts synchronisés vers Brevo', 'gastro-starter'), $result['synced']),
    ]);
}
add_action('wp_ajax_gastro_starter_brevo_sync_all', 'gastro_starter_brevo_sync_all_ajax');
