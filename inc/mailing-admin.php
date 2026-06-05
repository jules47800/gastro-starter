<?php
/**
 * Page d'administration pour l'envoi de mailing aux réservations d'une date donnée
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la sous-page Mailing sous le menu Réservations
 */
function gastro_starter_add_mailing_submenu() {
    add_submenu_page(
        'gastro-starter-reservations',
        __('Mailing soirée', 'gastro-starter'),
        __('Mailing', 'gastro-starter'),
        'manage_options',
        'gastro-starter-mailing',
        'gastro_starter_mailing_page'
    );
}
add_action('admin_menu', 'gastro_starter_add_mailing_submenu');

/**
 * Formate une date ISO en libellé français court pour les chips
 */
function gastro_starter_format_chip_date($date_str) {
    $timestamp = strtotime($date_str);
    $days_fr = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $months_fr = ['', 'jan.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $dow = (int) date('w', $timestamp);
    $day = (int) date('j', $timestamp);
    $month = (int) date('n', $timestamp);
    return $days_fr[$dow] . ' ' . $day . ' ' . $months_fr[$month];
}

/**
 * Récupère les dates suggérées pour le mailing (réservations futures + événements)
 */
function gastro_starter_mailing_get_suggested_dates() {
    global $wpdb;
    $table = $wpdb->prefix . 'reservations';
    $today = current_time('Y-m-d');

    // Dates avec réservations (qui ont des emails)
    $reservation_dates = $wpdb->get_results($wpdb->prepare(
        "SELECT reservation_date, COUNT(*) as count, SUM(people) as total_people
         FROM $table
         WHERE reservation_date >= %s
           AND status IN ('confirmed','pending')
           AND customer_email IS NOT NULL AND customer_email != ''
         GROUP BY reservation_date
         ORDER BY reservation_date ASC
         LIMIT 30",
        $today
    ));

    // Pas de filtrage : s'il y a des réservations, la date est pertinente
    $filtered_reservations = $reservation_dates;

    // Événements futurs
    $events_query = new WP_Query([
        'post_type' => 'event',
        'posts_per_page' => 20,
        'post_status' => 'publish',
        'meta_key' => 'event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [
            ['key' => 'event_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
        ],
    ]);

    // Pas de filtrage holidays pour les événements non plus
    $event_dates = [];
    $event_by_date = [];
    if ($events_query->have_posts()) {
        while ($events_query->have_posts()) {
            $events_query->the_post();
            $event_date = get_post_meta(get_the_ID(), 'event_date', true);
            if ($event_date) {
                $event_dates[] = [
                    'date' => $event_date,
                    'title' => get_the_title(),
                ];
                $event_by_date[$event_date] = get_the_title();
            }
        }
        wp_reset_postdata();
    }

    // Enrichir les dates de réservation avec le nom de l'événement si applicable
    foreach ($filtered_reservations as $row) {
        $row->event_name = $event_by_date[$row->reservation_date] ?? '';
    }

    return [
        'reservations' => $filtered_reservations,
        'events' => $event_dates,
    ];
}

/**
 * Affichage de la page Mailing
 */
function gastro_starter_mailing_page() {
    $suggestions = gastro_starter_mailing_get_suggested_dates();
    ?>
    <div class="wrap gastro-starter-mailing-wrap">

        <!-- Header -->
        <div class="mailing-header">
            <h1><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Mailing soirée', 'gastro-starter'); ?></h1>
            <p class="mailing-description"><?php esc_html_e('Envoyez un message à toutes les réservations d\'une date précise — rappel, précisions, changement d\'horaire...', 'gastro-starter'); ?></p>
        </div>

        <!-- KPI Grid (affiché dynamiquement après sélection de date) -->
        <div class="mailing-kpi-grid" id="mailing-kpi-grid" style="display: none;">
            <div class="kpi-box">
                <span class="kpi-icon"><span class="dashicons dashicons-calendar-alt"></span></span>
                <span class="kpi-value" id="kpi-reservations">0</span>
                <span class="kpi-label"><?php esc_html_e('Réservations', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box">
                <span class="kpi-icon"><span class="dashicons dashicons-email"></span></span>
                <span class="kpi-value" id="kpi-emails">0</span>
                <span class="kpi-label"><?php esc_html_e('Emails à envoyer', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box">
                <span class="kpi-icon"><span class="dashicons dashicons-groups"></span></span>
                <span class="kpi-value" id="kpi-couverts">0</span>
                <span class="kpi-label"><?php esc_html_e('Couverts', 'gastro-starter'); ?></span>
            </div>
        </div>

        <!-- Card : Sélection de la date -->
        <div class="mailing-card" id="mailing-date-card">
            <div class="mailing-card-header">
                <h2><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Sélectionnez la date', 'gastro-starter'); ?></h2>
            </div>

            <div class="mailing-date-suggestions">
                <?php if (!empty($suggestions['reservations'])) : ?>
                    <div class="date-group" id="date-group-reservations">
                        <h3 class="date-group-title">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e('Soirées avec réservations', 'gastro-starter'); ?>
                        </h3>
                        <div class="date-chips">
                            <?php foreach ($suggestions['reservations'] as $row) :
                                $has_event = !empty($row->event_name);
                                $chip_class = $has_event ? 'date-chip date-chip-event' : 'date-chip';
                            ?>
                                <button type="button" class="<?php echo esc_attr($chip_class); ?>" data-date="<?php echo esc_attr($row->reservation_date); ?>">
                                    <?php if ($has_event) : ?>
                                        <span class="chip-event-name"><?php echo esc_html($row->event_name); ?></span>
                                    <?php endif; ?>
                                    <span class="chip-date"><?php echo esc_html(gastro_starter_format_chip_date($row->reservation_date)); ?></span>
                                    <span class="chip-meta">
                                        <?php echo esc_html(sprintf(
                                            _n('%d résa · %d couvert', '%d résa · %d couverts', (int) $row->total_people, 'gastro-starter'),
                                            (int) $row->count,
                                            (int) $row->total_people
                                        )); ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="date-group">
                        <h3 class="date-group-title">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e('Soirées avec réservations', 'gastro-starter'); ?>
                        </h3>
                        <p class="date-empty-msg"><?php esc_html_e('Aucune soirée avec réservations à venir.', 'gastro-starter'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($suggestions['events'])) : ?>
                    <div class="date-group" id="date-group-events">
                        <h3 class="date-group-title">
                            <span class="dashicons dashicons-calendar"></span>
                            <?php esc_html_e('Événements à venir', 'gastro-starter'); ?>
                        </h3>
                        <div class="date-chips">
                            <?php foreach ($suggestions['events'] as $event) : ?>
                                <button type="button" class="date-chip date-chip-event" data-date="<?php echo esc_attr($event['date']); ?>">
                                    <span class="chip-date"><?php echo esc_html(gastro_starter_format_chip_date($event['date'])); ?></span>
                                    <span class="chip-meta"><?php echo esc_html($event['title']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Date manuelle (fallback) -->
            <div class="mailing-date-manual">
                <button type="button" class="mailing-toggle-manual" id="toggle-manual-date">
                    <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Saisir une date manuellement', 'gastro-starter'); ?>
                </button>
                <div class="manual-date-field" id="manual-date-field" style="display: none;">
                    <input type="date" id="mailing_date" name="mailing_date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                </div>
            </div>

            <!-- Warning aucun résultat -->
            <div id="mailing-no-results" class="mailing-notice mailing-notice-warning" style="display: none;">
                <?php esc_html_e('Aucune réservation avec email trouvée pour cette date et ces statuts.', 'gastro-starter'); ?>
            </div>
        </div>

        <!-- Card : Formulaire (destinataires + message) -->
        <div class="mailing-card" id="mailing-form-card">
            <form id="gastro-starter-mailing-form" method="post">
                <?php wp_nonce_field('gastro_starter_mailing_nonce', '_mailing_nonce'); ?>
                <input type="hidden" id="mailing_date_hidden" name="mailing_date_value" value="">

                <!-- Destinataires -->
                <div class="mailing-section">
                    <h2><span class="step-number">2</span> <?php esc_html_e('Destinataires', 'gastro-starter'); ?></h2>
                    <div class="mailing-status-checkboxes">
                        <label class="checkbox-label checked">
                            <input type="checkbox" name="mailing_statuses[]" value="confirmed" checked>
                            <span class="status-badge status-confirmed"><?php esc_html_e('Confirmées', 'gastro-starter'); ?></span>
                        </label>
                        <label class="checkbox-label checked">
                            <input type="checkbox" name="mailing_statuses[]" value="pending" checked>
                            <span class="status-badge status-pending"><?php esc_html_e('En attente', 'gastro-starter'); ?></span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="mailing_statuses[]" value="cancelled">
                            <span class="status-badge status-cancelled"><?php esc_html_e('Annulées', 'gastro-starter'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Message -->
                <div class="mailing-section">
                    <h2><span class="step-number">3</span> <?php esc_html_e('Rédigez votre message', 'gastro-starter'); ?></h2>
                    <div class="form-field">
                        <label for="mailing_subject"><?php esc_html_e('Objet de l\'email', 'gastro-starter'); ?></label>
                        <input type="text" id="mailing_subject" name="mailing_subject" required
                               placeholder="<?php esc_attr_e('Ex: Rappel — Votre soirée au restaurant ce vendredi', 'gastro-starter'); ?>">
                    </div>
                    <div class="form-field">
                        <label for="mailing_message"><?php esc_html_e('Corps du message', 'gastro-starter'); ?></label>
                        <div class="mailing-variables-help">
                            <span class="variables-label"><?php esc_html_e('Variables disponibles :', 'gastro-starter'); ?></span>
                            <code class="var-tag" data-var="{nom}">{nom}</code>
                            <code class="var-tag" data-var="{date}">{date}</code>
                            <code class="var-tag" data-var="{heure}">{heure}</code>
                            <code class="var-tag" data-var="{personnes}">{personnes}</code>
                            <code class="var-tag" data-var="{reference}">{reference}</code>
                        </div>
                        <textarea id="mailing_message" name="mailing_message" required
                                  placeholder="<?php esc_attr_e("Bonjour {nom},\n\nNous avons hâte de vous accueillir ce vendredi à {heure} pour {personnes} personnes.\n\nÀ très bientôt,\nL'équipe du restaurant", 'gastro-starter'); ?>"></textarea>
                        <p class="field-help"><?php esc_html_e('Cliquez sur une variable pour l\'insérer. Le message sera personnalisé pour chaque client.', 'gastro-starter'); ?></p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mailing-actions">
                    <button type="button" id="mailing-preview-btn" class="button mailing-btn-secondary">
                        <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Prévisualiser', 'gastro-starter'); ?>
                    </button>
                    <button type="submit" id="mailing-send-btn" class="button mailing-btn-primary" disabled>
                        <span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Envoyer le mailing', 'gastro-starter'); ?>
                    </button>
                    <span id="mailing-send-spinner" class="spinner" style="float: none;"></span>
                </div>
            </form>
        </div>

        <!-- Prévisualisation -->
        <div id="mailing-preview-container" class="mailing-card" style="display: none;">
            <div class="mailing-card-header">
                <h2><span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Prévisualisation', 'gastro-starter'); ?></h2>
            </div>
            <div id="mailing-preview-frame-wrapper">
                <iframe id="mailing-preview-frame"></iframe>
            </div>
        </div>

        <!-- Résultat -->
        <div id="mailing-result" style="display: none;"></div>
    </div>
    <?php
}

/**
 * AJAX : Récupérer les infos des réservations pour une date
 */
function gastro_starter_mailing_get_date_info() {
    check_ajax_referer('gastro_starter_mailing_nonce', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Accès refusé', 'gastro-starter')]);
    }

    $date = sanitize_text_field($_POST['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => __('Date invalide', 'gastro-starter')]);
    }

    $statuses = isset($_POST['statuses']) && is_array($_POST['statuses'])
        ? array_map('sanitize_text_field', $_POST['statuses'])
        : ['confirmed', 'pending'];

    $allowed_statuses = ['confirmed', 'pending', 'cancelled', 'no-show', 'completed'];
    $statuses = array_intersect($statuses, $allowed_statuses);

    if (empty($statuses)) {
        wp_send_json_error(['message' => __('Aucun statut sélectionné', 'gastro-starter')]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'reservations';

    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $params = array_merge([$date], $statuses);

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT customer_email, customer_name, people, status
         FROM $table
         WHERE reservation_date = %s AND status IN ($placeholders)
         ORDER BY reservation_time ASC",
        ...$params
    ));

    $total_reservations = count($results);
    $emails = [];
    $total_people = 0;

    foreach ($results as $row) {
        $total_people += (int) $row->people;
        if (!empty($row->customer_email) && is_email($row->customer_email)) {
            $emails[$row->customer_email] = $row->customer_name;
        }
    }

    wp_send_json_success([
        'total_reservations' => $total_reservations,
        'total_emails' => count($emails),
        'total_people' => $total_people,
        'recipients' => array_keys($emails),
    ]);
}
add_action('wp_ajax_gastro_starter_mailing_get_date_info', 'gastro_starter_mailing_get_date_info');

/**
 * AJAX : Prévisualiser le mailing
 */
function gastro_starter_mailing_preview() {
    check_ajax_referer('gastro_starter_mailing_nonce', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Accès refusé', 'gastro-starter')]);
    }

    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = wp_kses_post($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        wp_send_json_error(['message' => __('Objet et message requis', 'gastro-starter')]);
    }

    // Remplacer les variables par des exemples pour la preview
    $preview_vars = [
        '{nom}' => 'Jean Dupont',
        '{date}' => date_i18n('l j F Y', strtotime('+2 days')),
        '{heure}' => '19:30',
        '{personnes}' => '4',
        '{reference}' => 'RES-000042',
    ];
    $preview_subject = str_replace(array_keys($preview_vars), array_values($preview_vars), $subject);
    $preview_message = str_replace(array_keys($preview_vars), array_values($preview_vars), $message);

    $content_html = nl2br($preview_message);
    $email_manager = Gastro_Starter_Email_Manager::get_instance();
    $html = $email_manager->render_template('<h2>' . esc_html($preview_subject) . '</h2>' . $content_html);

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_gastro_starter_mailing_preview', 'gastro_starter_mailing_preview');

/**
 * AJAX : Envoyer le mailing via Brevo
 */
function gastro_starter_mailing_send() {
    check_ajax_referer('gastro_starter_mailing_nonce', '_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Accès refusé', 'gastro-starter')]);
    }

    $date = sanitize_text_field($_POST['date'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = wp_kses_post($_POST['message'] ?? '');
    $statuses = isset($_POST['statuses']) && is_array($_POST['statuses'])
        ? array_map('sanitize_text_field', $_POST['statuses'])
        : ['confirmed', 'pending'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => __('Date invalide', 'gastro-starter')]);
    }
    if (empty($subject) || empty($message)) {
        wp_send_json_error(['message' => __('Objet et message requis', 'gastro-starter')]);
    }

    $allowed_statuses = ['confirmed', 'pending', 'cancelled', 'no-show', 'completed'];
    $statuses = array_intersect($statuses, $allowed_statuses);
    if (empty($statuses)) {
        wp_send_json_error(['message' => __('Aucun statut sélectionné', 'gastro-starter')]);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'reservations';
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $params = array_merge([$date], $statuses);

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, customer_email, customer_name, reservation_date, reservation_time, people
         FROM $table
         WHERE reservation_date = %s AND status IN ($placeholders)
         AND customer_email IS NOT NULL AND customer_email != ''
         ORDER BY reservation_time ASC",
        ...$params
    ));

    // Grouper par email (un client peut avoir plusieurs réservations le même jour)
    $recipients = [];
    foreach ($results as $row) {
        if (!is_email($row->customer_email)) {
            continue;
        }
        if (!isset($recipients[$row->customer_email])) {
            $recipients[$row->customer_email] = $row;
        }
    }

    if (empty($recipients)) {
        wp_send_json_error(['message' => __('Aucun destinataire avec email valide trouvé', 'gastro-starter')]);
    }

    $api_key = get_option('gastro_starter_brevo_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('Clé API Brevo non configurée. Allez dans Réglages > Brevo.', 'gastro-starter')]);
    }

    $sender_name = get_option('gastro_starter_brevo_sender_name', 'Mon Restaurant');
    $sender_email = get_option('gastro_starter_brevo_sender_email', 'contact@mon-restaurant.fr');
    $email_manager = Gastro_Starter_Email_Manager::get_instance();

    $sent = 0;
    $errors = 0;
    $last_error = '';

    foreach ($recipients as $email => $reservation) {
        // Remplacer les variables dans le message et l'objet
        $vars = [
            '{nom}' => $reservation->customer_name,
            '{date}' => date_i18n('l j F Y', strtotime($reservation->reservation_date)),
            '{heure}' => date_i18n('H:i', strtotime($reservation->reservation_time)),
            '{personnes}' => $reservation->people,
            '{reference}' => sprintf('RES-%06d', $reservation->id),
        ];

        $personal_subject = str_replace(array_keys($vars), array_values($vars), $subject);
        $personal_message = str_replace(array_keys($vars), array_values($vars), $message);

        $content_html = '<h2>' . esc_html($personal_subject) . '</h2>' . nl2br($personal_message);
        $full_html = $email_manager->render_template($content_html);

        // Tracking pixel
        $tracking_id = Gastro_Starter_Email_Tracking::register_send($email, $personal_subject, 'mailing_soiree');
        $full_html = apply_filters('gastro_starter_email_html', $full_html, $tracking_id);

        $payload = [
            'sender' => ['name' => $sender_name, 'email' => $sender_email],
            'to' => [['email' => $email, 'name' => $reservation->customer_name]],
            'subject' => $personal_subject,
            'htmlContent' => $full_html,
            'headers' => ['X-Mailin-Tag' => 'mailing-soiree'],
        ];

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key' => $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $errors++;
            $last_error = $response->get_error_message();
            error_log("[MAILING SOIRÉE] Erreur envoi à $email: " . $last_error);
            continue;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $errors++;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $last_error = $body['message'] ?? "HTTP $code";
            error_log("[MAILING SOIRÉE] Erreur API pour $email ($code): " . $last_error);
            continue;
        }

        $sent++;
    }

    if (class_exists('Gastro_Starter_Email_Logger')) {
        Gastro_Starter_Email_Logger::log_email_attempt(
            sprintf('Mailing soirée %s (%d destinataires)', $date, count($recipients)),
            $subject,
            $errors === 0,
            'mailing_soiree',
            1,
            $errors > 0 ? sprintf('%d erreurs. Dernière: %s', $errors, $last_error) : '',
            null
        );
    }

    $result_message = sprintf(__('%d email(s) envoyé(s) avec succès', 'gastro-starter'), $sent);
    if ($errors > 0) {
        $result_message .= sprintf(__(', %d erreur(s)', 'gastro-starter'), $errors);
    }

    wp_send_json_success([
        'sent' => $sent,
        'errors' => $errors,
        'message' => $result_message,
    ]);
}
add_action('wp_ajax_gastro_starter_mailing_send', 'gastro_starter_mailing_send');
