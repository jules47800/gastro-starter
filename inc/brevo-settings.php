<?php
/**
 * Page de réglages Brevo pour l'envoi de newsletters
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajouter le menu Brevo dans les réglages
 */
function gastro_starter_brevo_admin_menu() {
    add_options_page(
        __('Réglages Brevo', 'gastro-starter'),
        __('Brevo (Newsletter)', 'gastro-starter'),
        'manage_options',
        'gastro-starter-brevo',
        'gastro_starter_brevo_settings_page'
    );
}
add_action('admin_menu', 'gastro_starter_brevo_admin_menu');

/**
 * Enregistrer les réglages
 */
function gastro_starter_brevo_settings_init() {
    register_setting('gastro_starter_brevo_options', 'gastro_starter_brevo_api_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('gastro_starter_brevo_options', 'gastro_starter_brevo_sender_name', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'Mon Restaurant',
    ]);
    register_setting('gastro_starter_brevo_options', 'gastro_starter_brevo_sender_email', [
        'sanitize_callback' => 'sanitize_email',
        'default' => 'contact@mon-restaurant.fr',
    ]);

    add_settings_section(
        'gastro_starter_brevo_section',
        __('Configuration de l\'API Brevo', 'gastro-starter'),
        'gastro_starter_brevo_section_callback',
        'gastro-starter-brevo'
    );

    add_settings_field(
        'gastro_starter_brevo_api_key',
        __('Clé API Brevo', 'gastro-starter'),
        'gastro_starter_brevo_api_key_field',
        'gastro-starter-brevo',
        'gastro_starter_brevo_section'
    );

    add_settings_field(
        'gastro_starter_brevo_sender_name',
        __('Nom de l\'expéditeur', 'gastro-starter'),
        'gastro_starter_brevo_sender_name_field',
        'gastro-starter-brevo',
        'gastro_starter_brevo_section'
    );

    add_settings_field(
        'gastro_starter_brevo_sender_email',
        __('Email de l\'expéditeur', 'gastro-starter'),
        'gastro_starter_brevo_sender_email_field',
        'gastro-starter-brevo',
        'gastro_starter_brevo_section'
    );

    register_setting('gastro_starter_brevo_options', 'gastro_starter_brevo_list_id', [
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);

    add_settings_field(
        'gastro_starter_brevo_list_id',
        __('ID de liste Brevo', 'gastro-starter'),
        'gastro_starter_brevo_list_id_field',
        'gastro-starter-brevo',
        'gastro_starter_brevo_section'
    );
}
add_action('admin_init', 'gastro_starter_brevo_settings_init');

/**
 * Callbacks des champs
 */
function gastro_starter_brevo_section_callback() {
    echo '<p>' . __('Configurez votre clé API Brevo pour activer l\'envoi de newsletters depuis les événements.', 'gastro-starter') . '</p>';
    echo '<p><a href="https://app.brevo.com/settings/keys/api" target="_blank">' . __('Obtenir une clé API Brevo →', 'gastro-starter') . '</a></p>';
}

function gastro_starter_brevo_api_key_field() {
    $value = get_option('gastro_starter_brevo_api_key', '');
    $masked = !empty($value) ? str_repeat('•', 20) . substr($value, -6) : '';
    echo '<input type="password" id="gastro_starter_brevo_api_key" name="gastro_starter_brevo_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    if (!empty($value)) {
        echo '<p class="description">' . sprintf(__('Clé actuelle : %s', 'gastro-starter'), $masked) . '</p>';
    }
}

function gastro_starter_brevo_sender_name_field() {
    $value = get_option('gastro_starter_brevo_sender_name', 'Mon Restaurant');
    echo '<input type="text" name="gastro_starter_brevo_sender_name" value="' . esc_attr($value) . '" class="regular-text" />';
}

function gastro_starter_brevo_sender_email_field() {
    $value = get_option('gastro_starter_brevo_sender_email', 'contact@mon-restaurant.fr');
    echo '<input type="email" name="gastro_starter_brevo_sender_email" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">' . __('Ce domaine doit être vérifié dans Brevo.', 'gastro-starter') . '</p>';
}

function gastro_starter_brevo_list_id_field() {
    $value = get_option('gastro_starter_brevo_list_id', 0);
    echo '<input type="number" name="gastro_starter_brevo_list_id" value="' . esc_attr($value) . '" class="small-text" min="0" />';
    echo '<p class="description">' . __('ID de la liste dans laquelle ajouter les contacts (visible dans Brevo > Contacts > Listes). Laisser 0 pour ne pas assigner de liste.', 'gastro-starter') . '</p>';
}

/**
 * Page de réglages HTML
 */
function gastro_starter_brevo_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <form action="options.php" method="post">
            <?php
            settings_fields('gastro_starter_brevo_options');
            do_settings_sections('gastro-starter-brevo');
            submit_button(__('Enregistrer', 'gastro-starter'));
            ?>
        </form>

        <hr>

        <h2><?php _e('Test de connexion', 'gastro-starter'); ?></h2>
        <p>
            <button type="button" id="brevo-test-connection" class="button button-secondary">
                <?php _e('🔌 Tester la connexion Brevo', 'gastro-starter'); ?>
            </button>
            <span id="brevo-test-result" style="margin-left: 10px;"></span>
        </p>

        <hr>

        <h2><?php _e('Synchronisation des contacts', 'gastro-starter'); ?></h2>
        <p class="description"><?php _e('Les nouveaux clients sont automatiquement synchronisés vers Brevo. Utilisez le bouton ci-dessous pour synchroniser tous les clients existants.', 'gastro-starter'); ?></p>
        <p>
            <button type="button" id="brevo-sync-all" class="button button-secondary">
                <?php _e('Synchroniser tous les contacts vers Brevo', 'gastro-starter'); ?>
            </button>
            <span id="brevo-sync-result" style="margin-left: 10px;"></span>
        </p>

        <hr>

        <h2><?php _e('Statistiques des contacts', 'gastro-starter'); ?></h2>
        <?php
        global $wpdb;
        $stats_table = $wpdb->prefix . 'customer_stats';
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $stats_table WHERE email != ''");
        $newsletter = (int) $wpdb->get_var("SELECT COUNT(*) FROM $stats_table WHERE email != '' AND newsletter = 1");
        ?>
        <table class="widefat" style="max-width: 400px;">
            <tr>
                <td><strong><?php _e('Total des contacts', 'gastro-starter'); ?></strong></td>
                <td><?php echo $total; ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Inscrits newsletter', 'gastro-starter'); ?></strong></td>
                <td><?php echo $newsletter; ?></td>
            </tr>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#brevo-sync-all').on('click', function() {
            var $btn = $(this);
            var $result = $('#brevo-sync-result');
            if (!confirm('<?php _e("Synchroniser tous les contacts vers Brevo ? Cela peut prendre quelques minutes.", "gastro-starter"); ?>')) return;
            $btn.prop('disabled', true).text('<?php _e("Synchronisation en cours...", "gastro-starter"); ?>');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gastro_starter_brevo_sync_all',
                    _nonce: '<?php echo wp_create_nonce("brevo_sync_all"); ?>'
                },
                timeout: 300000,
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;"><?php _e("Erreur de connexion ou timeout", "gastro-starter"); ?></span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('<?php _e("Synchroniser tous les contacts vers Brevo", "gastro-starter"); ?>');
                }
            });
        });

        $('#brevo-test-connection').on('click', function() {
            var $btn = $(this);
            var $result = $('#brevo-test-result');
            $btn.prop('disabled', true).text('<?php _e("Test en cours...", "gastro-starter"); ?>');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gastro_starter_brevo_test_connection',
                    _nonce: '<?php echo wp_create_nonce("brevo_test_connection"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">❌ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">❌ <?php _e("Erreur de connexion", "gastro-starter"); ?></span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('<?php _e("🔌 Tester la connexion Brevo", "gastro-starter"); ?>');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX: Tester la connexion Brevo
 */
function gastro_starter_brevo_test_connection_ajax() {
    check_ajax_referer('brevo_test_connection', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes', 'gastro-starter')]);
    }

    $api_key = get_option('gastro_starter_brevo_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('Clé API non configurée', 'gastro-starter')]);
    }

    $response = wp_remote_get('https://api.brevo.com/v3/account', [
        'headers' => [
            'api-key' => $api_key,
            'Accept'  => 'application/json',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 200 && isset($body['email'])) {
        $plan = isset($body['plan'][0]['type']) ? $body['plan'][0]['type'] : 'N/A';
        $credits = isset($body['plan'][0]['credits']) ? $body['plan'][0]['credits'] : 'N/A';
        wp_send_json_success([
            'message' => sprintf(
                __('Connecté ! Compte : %s — Plan : %s — Crédits : %s', 'gastro-starter'),
                $body['email'],
                $plan,
                $credits
            )
        ]);
    } else {
        $error_msg = isset($body['message']) ? $body['message'] : __('Erreur inconnue', 'gastro-starter');
        wp_send_json_error(['message' => sprintf(__('Erreur API (%d) : %s', 'gastro-starter'), $code, $error_msg)]);
    }
}
add_action('wp_ajax_gastro_starter_brevo_test_connection', 'gastro_starter_brevo_test_connection_ajax');
