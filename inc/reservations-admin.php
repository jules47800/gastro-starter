<?php
/**
 * Fonctions pour la gestion administrative des réservations
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit; // Sortie si accès direct
}

require_once get_template_directory() . '/inc/smtp-config.php';

/**
 * Gestion de la vue imprimable des réservations
 */
function gastro_starter_print_reservations_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'gastro-starter'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    $today = date('Y-m-d');
    $date_filter = isset($_GET['date_filter']) && !empty($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : $today;
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    // Construction de la requête (similaire à la page admin principale)
    $where_clauses = array();
    // Par défaut, on filtre par date si spécifiée, sinon aujourd'hui
    if (!empty($date_filter)) {
        $where_clauses[] = $wpdb->prepare('reservation_date = %s', $date_filter);
    }
    if (!empty($status_filter)) {
        $where_clauses[] = $wpdb->prepare('status = %s', $status_filter);
    }
    // Exclure les annulés par défaut pour l'impression sauf si demandé explicitement
    if ($status_filter !== 'cancelled' && empty($status_filter)) {
        $where_clauses[] = "status != 'cancelled'";
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);
    }

    $reservations = $wpdb->get_results(
        "SELECT * FROM $table_name" . $where_sql . " 
         ORDER BY reservation_time ASC" // Tri par heure pour la cuisine/service
    );

    // Début du rendu HTML
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo get_locale(); ?>">
    <head>
        <meta charset="UTF-8">
        <title><?php echo sprintf(__('Réservations du %s - Mon Restaurant', 'gastro-starter'), date_i18n(get_option('date_format'), strtotime($date_filter))); ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; margin: 20px; color: #333; }
            h1 { text-align: center; margin-bottom: 5px; }
            .meta { text-align: center; margin-bottom: 20px; color: #666; font-size: 0.9em; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; border-top: 2px solid #333; font-weight: 600; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .status-confirmed { color: #2ecc71; font-weight: bold; }
            .status-pending { color: #f39c12; }
            .notes { font-style: italic; background: #fff3cd; padding: 5px; border-radius: 4px; display: inline-block; }
            .total-count { margin-top: 20px; font-weight: bold; text-align: right; font-size: 1.1em; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
                th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
                .notes { background: #eee !important; -webkit-print-color-adjust: exact; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="margin-bottom: 20px; text-align: center;">
            <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-size: 16px;">Imprimer cette page</button>
            <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; font-size: 16px;">Fermer</button>
        </div>

        <h1><?php bloginfo('name'); ?> - Liste des réservations</h1>
        <div class="meta">
            Date : <strong><?php echo date_i18n(get_option('date_format'), strtotime($date_filter)); ?></strong>
            <?php if (!empty($status_filter)) echo ' | Statut : ' . esc_html($status_filter); ?>
            | Généré le : <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?>
        </div>

        <?php if (empty($reservations)) : ?>
            <p style="text-align: center; font-style: italic; margin-top: 50px;">Aucune réservation trouvée pour cette date.</p>
        <?php else : ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">Heure</th>
                        <th style="width: 60px;">Pers.</th>
                        <th>Nom du client</th>
                        <th>Téléphone</th>
                        <th>Notes / Allergies</th>
                        <th style="width: 100px;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_people = 0;
                    foreach ($reservations as $res) : 
                        $total_people += (int)$res->people;
                        $status_label = $res->status === 'confirmed' ? 'Confirmé' : ($res->status === 'pending' ? 'En attente' : $res->status);
                    ?>
                        <tr>
                            <td style="font-weight: bold; font-size: 1.1em;"><?php echo date('H:i', strtotime($res->reservation_time)); ?></td>
                            <td style="text-align: center; font-weight: bold; font-size: 1.1em;"><?php echo esc_html($res->people); ?></td>
                            <td>
                                <?php echo esc_html($res->customer_name); ?>
                                <?php if (!empty($res->customer_email)) : ?>
                                    <br><small style="color:#666;"><?php echo esc_html($res->customer_email); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($res->customer_phone) ? esc_html($res->customer_phone) : '-'; ?></td>
                            <td>
                                <?php if (!empty($res->notes)) : ?>
                                    <div class="notes"><?php echo nl2br(esc_html($res->notes)); ?></div>
                                <?php else : ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="status-<?php echo esc_attr($res->status); ?>"><?php echo esc_html($status_label); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-count">
                Total couverts : <?php echo $total_people; ?> | Total tables : <?php echo count($reservations); ?>
            </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
}
add_action('admin_post_gastro_starter_print_reservations', 'gastro_starter_print_reservations_handler');

/**
 * Ajoute une page d'administration pour les réservations
 */
function gastro_starter_add_admin_menu() {
    add_menu_page(
        __('Réservations', 'gastro-starter'),
        __('Réservations', 'gastro-starter'),
        'manage_options',
        'gastro-starter-reservations',
        'gastro_starter_reservations_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Ajouter une sous-page pour les paramètres
    add_submenu_page(
        'gastro-starter-reservations',
        __('Paramètres de réservation', 'gastro-starter'),
        __('Paramètres', 'gastro-starter'),
        'manage_options',
        'gastro-starter-reservation-settings',
        'gastro_starter_reservation_settings_page'
    );
}
add_action('admin_menu', 'gastro_starter_add_admin_menu');

/**
 * Enregistrer les paramètres de réservation
 */
function gastro_starter_register_reservation_settings() {
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_restaurant_capacity', [
        'type' => 'integer',
        'default' => 4,
        'sanitize_callback' => 'absint',
    ]);
    
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_reminder_time', [
        'type' => 'integer',
        'default' => 90,
        'sanitize_callback' => 'absint',
    ]);

    register_setting('gastro_starter_reservation_settings', 'gastro_starter_table_hold_time', [
        'type' => 'integer',
        'default' => 15,
        'sanitize_callback' => 'absint',
    ]);
    
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_booking_period', [
        'type' => 'integer',
        'default' => 1,
        'sanitize_callback' => 'absint',
    ]);
    
    // Email de relance post-visite (avis Google)
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_followup_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);

    register_setting('gastro_starter_reservation_settings', 'gastro_starter_followup_delay', [
        'type' => 'integer',
        'default' => 2,
        'sanitize_callback' => 'absint',
    ]);

    register_setting('gastro_starter_reservation_settings', 'gastro_starter_followup_google_url', [
        'type' => 'string',
        'default' => 'https://g.page/r/CaJeDfuzM41pEAE/review',
        'sanitize_callback' => 'esc_url_raw',
    ]);

    // NOUVEAU : Périodes de fermeture (vacances)
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_holiday_dates', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'gastro_starter_sanitize_holiday_dates',
    ]);
    
    // NOUVEAU : Système d'horaires par jour avec plages multiples
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_daily_schedule', [
        'type' => 'array',
        'default' => array(
            'monday' => array(
                'open' => false,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            ),
            'tuesday' => array(
                'open' => true,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            ),
            'wednesday' => array(
                'open' => true,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            ),
            'thursday' => array(
                'open' => true,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            ),
            'friday' => array(
                'open' => true,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:30')
                ),
                'slot_interval' => 30
            ),
            'saturday' => array(
                'open' => true,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:30')
                ),
                'slot_interval' => 30
            ),
            'sunday' => array(
                'open' => false,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            )
        ),
        'sanitize_callback' => 'gastro_starter_sanitize_daily_schedule',
    ]);
    
    // Anciens paramètres (pour compatibilité)
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_lunch_times', [
        'type' => 'string',
        'default' => '10:00,10:30,11:00,11:30,12:00,12:30,13:00,13:30,14:00',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    
    register_setting('gastro_starter_reservation_settings', 'gastro_starter_dinner_times', [
        'type' => 'string',
        'default' => '19:00,19:30,20:00,20:30,21:00,21:30,22:00',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}
add_action('admin_init', 'gastro_starter_register_reservation_settings');

/**
 * Sanitize le planning quotidien
 */
function gastro_starter_sanitize_daily_schedule($input) {
    $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
    $output = array();
    
    foreach ($days as $day) {
        if (!isset($input[$day])) {
            $output[$day] = array(
                'open' => false,
                'time_ranges' => array(
                    array('start' => '12:00', 'end' => '14:00'),
                    array('start' => '19:00', 'end' => '22:00')
                ),
                'slot_interval' => 30
            );
            continue;
        }
        
        $day_data = $input[$day];
        
        $output[$day] = array(
            'open' => isset($day_data['open']) ? (bool)$day_data['open'] : false,
            'time_ranges' => array(),
            'slot_interval' => isset($day_data['slot_interval']) ? absint($day_data['slot_interval']) : 30
        );
        
        // Traitement des plages horaires
        if (isset($day_data['time_ranges']) && is_array($day_data['time_ranges'])) {
            foreach ($day_data['time_ranges'] as $range) {
                if (isset($range['start']) && isset($range['end'])) {
                    $start = sanitize_text_field($range['start']);
                    $end = sanitize_text_field($range['end']);
                    
                    // Validation des heures
                    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start) && 
                        preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end)) {
                        $output[$day]['time_ranges'][] = array(
                            'start' => $start,
                            'end' => $end
                        );
                    }
                }
            }
        }
        
        // Si aucune plage valide, utiliser les valeurs par défaut
        if (empty($output[$day]['time_ranges'])) {
            $output[$day]['time_ranges'] = array(
                array('start' => '12:00', 'end' => '14:00'),
                array('start' => '19:00', 'end' => '22:00')
            );
        }
        
        // Validation de l'intervalle
        if (!in_array($output[$day]['slot_interval'], array(15, 30, 45, 60))) {
            $output[$day]['slot_interval'] = 30;
        }
    }
    
    return $output;
}

/**
 * Sanitize les dates de vacances
 */
function gastro_starter_sanitize_holiday_dates($input) {
    // S'attend à une chaîne de dates "YYYY-MM-DD", séparées par des virgules
    $dates = explode(',', $input);
    $sanitized_dates = [];
    foreach ($dates as $date_str) {
        $trimmed_date = trim($date_str);
        // Valider le format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed_date)) {
            $sanitized_dates[] = $trimmed_date;
        }
    }
    return implode(',', $sanitized_dates);
}

/**
 * Page de paramètres de réservation
 */
function gastro_starter_reservation_settings_page() {
    ?>
    <div class="wrap gastro-starter-admin gastro-starter-reservations">
        <h1><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html__('Paramètres de réservation', 'gastro-starter'); ?></h1>
        
        <div class="gastro-starter-admin-card">
            <form method="post" action="options.php">
                <?php settings_fields('gastro_starter_reservation_settings'); ?>
                
                <div class="form-section">
                    <h2><?php echo esc_html__('Capacité par créneau', 'gastro-starter'); ?></h2>
                    <p class="description"><?php echo esc_html__('Définissez le nombre maximum de couverts par créneau.', 'gastro-starter'); ?></p>
                    
                    <div class="form-field">
                        <label for="gastro_starter_restaurant_capacity"><?php echo esc_html__('Nombre de couverts par créneau', 'gastro-starter'); ?></label>
                        <input type="number" id="gastro_starter_restaurant_capacity" name="gastro_starter_restaurant_capacity" 
                               value="<?php echo esc_attr(get_option('gastro_starter_restaurant_capacity', 4)); ?>" min="1" max="20">
                        <p class="field-help"><?php echo esc_html__('Recommandé : 4-6 couverts par créneau pour un service optimal', 'gastro-starter'); ?></p>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2><?php echo esc_html__('Paramètres des rappels par email', 'gastro-starter'); ?></h2>
                    <p class="description"><?php echo esc_html__('Configurez les rappels envoyés aux clients avant leur réservation.', 'gastro-starter'); ?></p>
                    
                    <div class="form-field">
                        <label for="gastro_starter_reminder_time"><?php echo esc_html__('Envoyer le rappel (minutes avant la réservation)', 'gastro-starter'); ?></label>
                        <input type="number" id="gastro_starter_reminder_time" name="gastro_starter_reminder_time" 
                               value="<?php echo esc_attr(get_option('gastro_starter_reminder_time', 90)); ?>" min="30" max="1440" step="30">
                        <p class="field-help"><?php echo esc_html__('Par défaut: 90 minutes (1h30)', 'gastro-starter'); ?></p>
                    </div>
                </div>

                <div class="form-section">
                    <h2><?php echo esc_html__('Relance post-visite (Avis Google)', 'gastro-starter'); ?></h2>
                    <p class="description"><?php echo esc_html__('Un email est envoyé automatiquement après le repas pour inviter le client à laisser un avis Google.', 'gastro-starter'); ?></p>

                    <div class="form-field">
                        <label for="gastro_starter_followup_enabled">
                            <input type="checkbox" id="gastro_starter_followup_enabled" name="gastro_starter_followup_enabled" value="1" <?php checked(get_option('gastro_starter_followup_enabled', true)); ?>>
                            <?php echo esc_html__('Activer l\'envoi automatique', 'gastro-starter'); ?>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="gastro_starter_followup_delay"><?php echo esc_html__('Délai après le repas (jours)', 'gastro-starter'); ?></label>
                        <input type="number" id="gastro_starter_followup_delay" name="gastro_starter_followup_delay"
                               value="<?php echo esc_attr(get_option('gastro_starter_followup_delay', 2)); ?>" min="1" max="7" step="1">
                        <p class="field-help"><?php echo esc_html__('Nombre de jours après la visite avant l\'envoi de l\'email. Par défaut : 2 jours.', 'gastro-starter'); ?></p>
                    </div>

                    <div class="form-field">
                        <label for="gastro_starter_followup_google_url"><?php echo esc_html__('Lien avis Google', 'gastro-starter'); ?></label>
                        <input type="url" id="gastro_starter_followup_google_url" name="gastro_starter_followup_google_url"
                               value="<?php echo esc_attr(get_option('gastro_starter_followup_google_url', 'https://g.page/r/CaJeDfuzM41pEAE/review')); ?>"
                               class="regular-text" style="width: 100%;">
                        <p class="field-help"><?php echo esc_html__('URL directe vers la page d\'avis Google de votre restaurant.', 'gastro-starter'); ?></p>
                    </div>
                </div>

                <div class="form-section">
                    <h2><?php echo esc_html__('Politique de réservation', 'gastro-starter'); ?></h2>
                    <div class="form-field">
                        <label for="gastro_starter_table_hold_time"><?php echo esc_html__('Temps de maintien de la table (minutes)', 'gastro-starter'); ?></label>
                        <input type="number" id="gastro_starter_table_hold_time" name="gastro_starter_table_hold_time"
                               value="<?php echo esc_attr(get_option('gastro_starter_table_hold_time', 15)); ?>" min="5" max="60" step="5">
                        <p class="field-help"><?php echo esc_html__('Durée après laquelle une table non occupée peut être réattribuée. Par défaut : 15 minutes.', 'gastro-starter'); ?></p>
                    </div>
                    <div class="form-field">
                        <label for="gastro_starter_booking_period"><?php echo esc_html__('Période de réservation maximale (mois)', 'gastro-starter'); ?></label>
                        <input type="number" id="gastro_starter_booking_period" name="gastro_starter_booking_period"
                               value="<?php echo esc_attr(get_option('gastro_starter_booking_period', 1)); ?>" min="1" max="12" step="1">
                        <p class="field-help"><?php echo esc_html__('Jusqu\'à combien de mois à l\'avance les clients peuvent réserver. Par défaut : 1 mois.', 'gastro-starter'); ?></p>
                    </div>
                </div>

                <div class="form-section">
                    <h2><?php echo esc_html__('Périodes de fermeture (Vacances)', 'gastro-starter'); ?></h2>
                    <p class="description"><?php echo esc_html__('Bloquez des dates ou des périodes pour les réservations. Idéal pour les vacances ou les fermetures exceptionnelles.', 'gastro-starter'); ?></p>
                    
                    <div class="form-field">
                        <label for="gastro_starter_holiday_dates_calendar"><?php echo esc_html__('Cliquez sur les dates pour les bloquer/débloquer', 'gastro-starter'); ?></label>
                        <!-- Ce div affichera le calendrier -->
                        <div id="gastro_starter_holiday_dates_calendar"></div>
                        <!-- Ce champ caché stockera les dates pour l'envoi du formulaire -->
                        <input type="hidden" id="gastro_starter_holiday_dates" name="gastro_starter_holiday_dates" value="<?php echo esc_attr(get_option('gastro_starter_holiday_dates', '')); ?>">
                        <p class="field-help"><?php echo esc_html__('Les jours sélectionnés en orange seront bloqués à la réservation.', 'gastro-starter'); ?></p>
                    </div>
                </div>
                
                <?php gastro_starter_daily_schedule_settings_section(); ?>
                
                <?php submit_button(__('Enregistrer les paramètres', 'gastro-starter'), 'primary', 'submit', true); ?>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Section des horaires quotidiens
 */
function gastro_starter_daily_schedule_settings_section() {
    $days = array(
        'monday'    => __('Lundi', 'gastro-starter'),
        'tuesday'   => __('Mardi', 'gastro-starter'),
        'wednesday' => __('Mercredi', 'gastro-starter'),
        'thursday'  => __('Jeudi', 'gastro-starter'),
        'friday'    => __('Vendredi', 'gastro-starter'),
        'saturday'  => __('Samedi', 'gastro-starter'),
        'sunday'    => __('Dimanche', 'gastro-starter'),
    );
    
    $schedule = get_option('gastro_starter_daily_schedule', array());
    
    echo '<div class="form-section daily-schedule-section">';
    echo '<h2>' . esc_html__('Planning hebdomadaire', 'gastro-starter') . '</h2>';
    echo '<p class="description">' . esc_html__('Configurez les horaires de réservation pour chaque jour de la semaine.', 'gastro-starter') . '</p>';
    
    echo '<div class="schedule-grid">';
    
    foreach ($days as $day_key => $day_label) {
        $day_data = isset($schedule[$day_key]) ? $schedule[$day_key] : array(
            'open' => false,
            'time_ranges' => array(
                array('start' => '12:00', 'end' => '14:00'),
                array('start' => '19:00', 'end' => '22:00')
            ),
            'slot_interval' => 30
        );
        
        $is_open = isset($day_data['open']) ? $day_data['open'] : false;
        $time_ranges = isset($day_data['time_ranges']) ? $day_data['time_ranges'] : array(
            array('start' => '12:00', 'end' => '14:00'),
            array('start' => '19:00', 'end' => '22:00')
        );
        $slot_interval = isset($day_data['slot_interval']) ? $day_data['slot_interval'] : 30;
        
        echo '<div class="schedule-day" data-day="' . esc_attr($day_key) . '">';
        echo '<div class="day-header">';
        echo '<h3>' . esc_html($day_label) . '</h3>';
        echo '<label class="day-toggle">';
        echo '<input type="checkbox" name="gastro_starter_daily_schedule[' . esc_attr($day_key) . '][open]" value="1" ' . checked($is_open, true, false) . '>';
        echo '<span class="toggle-slider"></span>';
        echo '<span class="toggle-label">' . esc_html__('Ouvert', 'gastro-starter') . '</span>';
        echo '</label>';
        echo '</div>';
        
        echo '<div class="day-schedule ' . ($is_open ? 'day-open' : 'day-closed') . '">';
        
        // Plages horaires
        echo '<div class="time-ranges-section">';
        echo '<h4>' . esc_html__('Plages horaires', 'gastro-starter') . '</h4>';
        echo '<div class="time-ranges-container" data-day="' . esc_attr($day_key) . '">';
        
        foreach ($time_ranges as $index => $range) {
            echo '<div class="time-range-row">';
            echo '<div class="time-inputs">';
            echo '<div class="time-input">';
            echo '<label>' . esc_html__('Début', 'gastro-starter') . '</label>';
            echo '<input type="time" name="gastro_starter_daily_schedule[' . esc_attr($day_key) . '][time_ranges][' . $index . '][start]" value="' . esc_attr($range['start']) . '">';
            echo '</div>';
            echo '<div class="time-input">';
            echo '<label>' . esc_html__('Fin', 'gastro-starter') . '</label>';
            echo '<input type="time" name="gastro_starter_daily_schedule[' . esc_attr($day_key) . '][time_ranges][' . $index . '][end]" value="' . esc_attr($range['end']) . '">';
            echo '</div>';
            echo '</div>';
            echo '<button type="button" class="remove-range" data-day="' . esc_attr($day_key) . '" data-index="' . $index . '">' . esc_html__('Supprimer', 'gastro-starter') . '</button>';
            echo '</div>';
        }
        
        echo '</div>'; // .time-ranges-container
        echo '<button type="button" class="add-range" data-day="' . esc_attr($day_key) . '">' . esc_html__('+ Ajouter une plage', 'gastro-starter') . '</button>';
        echo '</div>'; // .time-ranges-section
        
        // Intervalle des créneaux
        echo '<div class="slot-interval">';
        echo '<label>' . esc_html__('Intervalle des créneaux', 'gastro-starter') . '</label>';
        echo '<select name="gastro_starter_daily_schedule[' . esc_attr($day_key) . '][slot_interval]">';
        echo '<option value="15" ' . selected($slot_interval, 15, false) . '>15 minutes</option>';
        echo '<option value="30" ' . selected($slot_interval, 30, false) . '>30 minutes</option>';
        echo '<option value="45" ' . selected($slot_interval, 45, false) . '>45 minutes</option>';
        echo '<option value="60" ' . selected($slot_interval, 60, false) . '>1 heure</option>';
        echo '</select>';
        echo '</div>';
        
        // Aperçu des créneaux
        echo '<div class="slots-preview">';
        echo '<h5>' . esc_html__('Créneaux générés', 'gastro-starter') . '</h5>';
        echo '<div class="slots-list" id="slots-' . esc_attr($day_key) . '">';
        if ($is_open) {
            $all_slots = array();
            foreach ($time_ranges as $range) {
                $slots = gastro_starter_generate_time_slots($range['start'], $range['end'], $slot_interval);
                $all_slots = array_merge($all_slots, $slots);
            }
            echo '<span class="slot-count">' . count($all_slots) . ' créneaux</span>';
            echo '<div class="slots-example">';
            $example_slots = array_slice($all_slots, 0, 5);
            echo implode(', ', $example_slots);
            if (count($all_slots) > 5) {
                echo '...';
            }
            echo '</div>';
        } else {
            echo '<span class="day-closed-text">' . esc_html__('Jour fermé', 'gastro-starter') . '</span>';
        }
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .day-schedule
        echo '</div>'; // .schedule-day
    }
    
    echo '</div>'; // .schedule-grid
    echo '</div>'; // .form-section
}

/**
 * Génère les créneaux horaires entre deux heures
 */
function gastro_starter_generate_time_slots($start_time, $end_time, $interval_minutes = 30) {
    $slots = array();
    
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $interval = new DateInterval('PT' . $interval_minutes . 'M');
    
    $current = clone $start;
    
    while ($current < $end) {
        $slots[] = $current->format('H:i');
        $current->add($interval);
    }
    
    return $slots;
}

/**
 * Récupère les créneaux disponibles pour une date donnée
 */
function gastro_starter_get_available_slots_for_date($date) {
    $schedule = get_option('gastro_starter_daily_schedule', array());
    
    // Obtenir le jour de la semaine (0 = dimanche, 1 = lundi, etc.)
    $day_of_week = date('w', strtotime($date));
    $day_names = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
    $day_key = $day_names[$day_of_week];
    
    if (!isset($schedule[$day_key]) || !$schedule[$day_key]['open']) {
        return array();
    }
    
    $day_data = $schedule[$day_key];
    $slots = array();
    
    // Générer les créneaux pour toutes les plages horaires
    if (isset($day_data['time_ranges']) && is_array($day_data['time_ranges'])) {
        foreach ($day_data['time_ranges'] as $range) {
            if (!empty($range['start']) && !empty($range['end'])) {
                $time_slots = gastro_starter_generate_time_slots(
                    $range['start'], 
                    $range['end'], 
                    $day_data['slot_interval']
                );
                foreach ($time_slots as $slot) {
                    $slots[] = array(
                        'time' => $slot,
                        'meal_type' => 'general' // Plus de distinction déjeuner/dîner
                    );
                }
            }
        }
    }
    
    return $slots;
}

/**
 * Génère une URL d'action en préservant les paramètres de filtrage
 */
function gastro_starter_get_action_url($action, $reservation_id, $date_filter, $status_filter) {
    $params = array(
        'page' => 'gastro-starter-reservations',
        'action' => $action,
        'id' => $reservation_id
    );
    
    // Ajouter les paramètres de filtrage s'ils existent
    if (!empty($date_filter)) {
        $params['date_filter'] = $date_filter;
    }
    if (!empty($status_filter)) {
        $params['status_filter'] = $status_filter;
    }
    
    $url = add_query_arg($params, admin_url('admin.php'));
    return wp_nonce_url($url, 'reservation_action_' . $reservation_id);
}

/**
 * Affichage de la page d'administration des réservations
 */
function gastro_starter_reservations_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';

    // Traitement des actions
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $action = sanitize_text_field($_GET['action']);
        $id = intval($_GET['id']);
        
        // Vérifier le nonce pour la sécurité
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reservation_action_' . $id)) {
            if ($action === 'confirm') {
                $wpdb->update(
                    $table_name,
                    array('status' => 'confirmed'),
                    array('id' => $id)
                );
                add_settings_error('gastro_starter_reservations', 'reservation_confirmed', __('Réservation confirmée avec succès.', 'gastro-starter'), 'success');
                
                // Envoyer un email de confirmation au client
                if (function_exists('gastro_starter_get_reservation_manager')) {
                    gastro_starter_get_reservation_manager()->send_confirmation_email($id);
                }
                
            } elseif ($action === 'cancel') {
                $wpdb->update(
                    $table_name,
                    array('status' => 'cancelled'),
                    array('id' => $id)
                );
                add_settings_error('gastro_starter_reservations', 'reservation_cancelled', __('Réservation annulée.', 'gastro-starter'), 'success');
                
                 // Envoyer notification d'annulation au client
                 if (function_exists('gastro_starter_get_email_manager')) {
                    $reservation = gastro_starter_get_reservation_manager()->get_reservation($id);
                    if ($reservation && function_exists('gastro_starter_get_email_manager')) {
                        gastro_starter_get_email_manager()->send_cancellation_email($reservation);
                    }
                }
                
            } elseif ($action === 'noshow') { // Action No-Show
                $wpdb->update(
                    $table_name,
                    array('status' => 'no-show'),
                    array('id' => $id)
                );
                add_settings_error('gastro_starter_reservations', 'reservation_noshow', __('Réservation marquée comme No-Show.', 'gastro-starter'), 'success');

            } elseif ($action === 'delete') {
                $wpdb->delete(
                    $table_name,
                    array('id' => $id)
                );
                add_settings_error('gastro_starter_reservations', 'reservation_deleted', __('Réservation supprimée définitivement.', 'gastro-starter'), 'success');
                
            } elseif ($action === 'send_confirmation') {
                $sent = false;
                if (function_exists('gastro_starter_get_reservation_manager')) {
                   $sent = gastro_starter_get_reservation_manager()->send_confirmation_email($id);
                }
                if ($sent) {
                    add_settings_error('gastro_starter_reservations', 'email_sent', __('Email de confirmation envoyé avec succès.', 'gastro-starter'), 'success');
                } else {
                    add_settings_error('gastro_starter_reservations', 'email_error', __('Erreur lors de l\'envoi de l\'email.', 'gastro-starter'), 'error');
                }
                
            } elseif ($action === 'send_reminder') {
                $sent = false;
                 if (function_exists('gastro_starter_get_reservation_manager')) {
                   $sent = gastro_starter_get_reservation_manager()->send_reminder_email($id);
                }
                if ($sent) {
                    add_settings_error('gastro_starter_reservations', 'reminder_sent', __('Rappel envoyé avec succès.', 'gastro-starter'), 'success');
                } else {
                    add_settings_error('gastro_starter_reservations', 'reminder_error', __('Erreur lors de l\'envoi du rappel.', 'gastro-starter'), 'error');
                }
            }
        }
    }

    // Traitement du formulaire rapide d'ajout de réservation
    if (isset($_POST['quick_add_reservation']) && check_admin_referer('quick_add_reservation')) {
        $reservation_manager = gastro_starter_get_reservation_manager();
        $date = sanitize_text_field($_POST['reservation_date']);
        $time = sanitize_text_field($_POST['reservation_time']);
        $people = intval($_POST['people']);
        
        if ($reservation_manager) {
            $customer_email = isset($_POST['customer_email']) && !empty($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : null;

            $data = array(
                'reservation_date' => $date,
                'reservation_time' => $time,
                'people' => $people,
                'customer_name' => sanitize_text_field($_POST['customer_name']),
                'customer_phone' => sanitize_text_field($_POST['customer_phone']),
                'customer_email' => $customer_email,
                'notes' => sanitize_textarea_field($_POST['notes']),
                'status' => 'confirmed',
                'source' => 'admin',
                'meal_type' => 'general',
                'confirmation_email_sent' => 0,
            );

            // NOUVEAU : Vérifier si le pooling est nécessaire
            $pooling_enabled = get_option('gastro_starter_pooling_enabled', true);
            $reservation_id = false;
            
            if ($pooling_enabled) {
                $pooling_manager = new Gastro_Starter_Capacity_Pooling_Manager();
                $pool_result = $pooling_manager->find_available_capacity_pool($date, $people);
                
                // Si pooling requis et possible
                if ($pool_result['can_accommodate'] && $pool_result['pooling_required']) {
                    // Utiliser le pooling manager avec la bonne signature
                    $customer_data_pooling = [
                        'name' => $data['customer_name'],
                        'phone' => $data['customer_phone'],
                        'email' => $data['customer_email'],
                        'notes' => $data['notes']
                    ];
                    
                    $result = $pooling_manager->create_pooled_reservation(
                        $date,
                        $people,
                        $customer_data_pooling,
                        $time, // Le créneau choisi par l'admin
                        'confirmed' // Status confirmé pour admin
                    );
                    
                    if ($result && $result['success']) {
                        $reservation_id = $result['reservation_id'];
                        add_settings_error('gastro_starter_reservations', 'reservation_added', 
                            sprintf(__('Réservation poolée ajoutée avec succès (utilise %d créneaux).', 'gastro-starter'), count($result['slots_used'])), 
                            'success');
                    } else {
                        $reservation_id = false;
                    }
                } else {
                    // Créer normalement sans pooling
                    $reservation_id = $reservation_manager->create_reservation($data);
                    
                    if ($reservation_id) {
                        add_settings_error('gastro_starter_reservations', 'reservation_added', __('Réservation ajoutée avec succès.', 'gastro-starter'), 'success');
                    }
                }
            } else {
                // Pooling désactivé, créer normalement
                $reservation_id = $reservation_manager->create_reservation($data);
                
                if ($reservation_id) {
                    add_settings_error('gastro_starter_reservations', 'reservation_added', __('Réservation ajoutée avec succès.', 'gastro-starter'), 'success');
                }
            }

            if ($reservation_id) {
                // Envoyer l'email de confirmation si un email est fourni
                if ($customer_email && function_exists('gastro_starter_get_email_manager')) {
                    $email_sent = $reservation_manager->send_confirmation_email($reservation_id);
                    if ($email_sent) {
                        add_settings_error('gastro_starter_reservations', 'email_sent', __('Email de confirmation envoyé.', 'gastro-starter'), 'success');
                    } else {
                        add_settings_error('gastro_starter_reservations', 'email_error', __("La réservation a été créée, mais l'email de confirmation n'a pas pu être envoyé.", 'gastro-starter'), 'warning');
                    }
                }

                if(function_exists('gastro_starter_update_customer_visits') && !empty($data['customer_email'])) {
                    gastro_starter_update_customer_visits($data['customer_email'], $reservation_id);
                }
            } else {
                $error_message = __('Erreur lors de l\'ajout de la réservation.', 'gastro-starter');
                if (!empty($reservation_manager->last_error)) {
                    $error_message .= ' ' . sprintf(__('Détail de l\'erreur : %s', 'gastro-starter'), $reservation_manager->last_error);
                }
                add_settings_error('gastro_starter_reservations', 'reservation_error', $error_message, 'error');
            }
        } else {
            add_settings_error('gastro_starter_reservations', 'manager_unavailable', __('Le gestionnaire de réservation est indisponible.', 'gastro-starter'), 'error');
        }
    }

    // -- NOUVELLE LOGIQUE DE FILTRAGE ET PAGINATION --

    // 1. Paramètres de pagination et de filtrage
    $items_per_page = 20;
    $today = date('Y-m-d');
    
    // Récupérer les filtres depuis l'URL
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $date_filter = isset($_GET['date_filter']) && !empty($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

    // 2. Construire la clause WHERE pour les requêtes
    $where_clauses = array();
    if (!empty($search_query)) {
        $search_like = '%' . $wpdb->esc_like($search_query) . '%';
        $where_clauses[] = $wpdb->prepare('(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR id = %d)', $search_like, $search_like, $search_like, intval($search_query));
    }
    if (!empty($date_filter)) {
        $where_clauses[] = $wpdb->prepare('reservation_date = %s', $date_filter);
    }
    if (!empty($status_filter)) {
        $where_clauses[] = $wpdb->prepare('status = %s', $status_filter);
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);
    }

    // L'ancien système de saut de page est supprimé car le nouveau tri affiche directement les réservations du jour en premier.
    
    // 4. Récupérer les données pour le tableau
    // Obtenir le nombre total d'éléments correspondant aux filtres pour la pagination
    $total_items_query = "SELECT COUNT(id) FROM $table_name" . $where_sql;
    $total_items = $wpdb->get_var($total_items_query);

    // Calculer l'offset et récupérer les réservations pour la page actuelle
    $offset = ($current_page - 1) * $items_per_page;
    $reservations_query = $wpdb->prepare(
        "SELECT * FROM $table_name" . $where_sql . " 
         ORDER BY 
            -- 1. Grouper par futur/passé. 0 pour futur/aujourd'hui, 1 pour passé.
            CASE WHEN reservation_date >= %s THEN 0 ELSE 1 END ASC,
            -- 2. Pour le futur, trier par date/heure ASC.
            CASE WHEN reservation_date >= %s THEN reservation_date END ASC,
            CASE WHEN reservation_date >= %s THEN reservation_time END ASC,
            -- 3. Pour le passé, trier par date/heure DESC (plus récent en premier).
            CASE WHEN reservation_date < %s THEN reservation_date END DESC,
            CASE WHEN reservation_date < %s THEN reservation_time END DESC
         LIMIT %d OFFSET %d",
        $today,
        $today,
        $today,
        $today,
        $today,
        $items_per_page,
        $offset
    );
    $reservations = $wpdb->get_results($reservations_query);
    
    // Associer le statut VIP aux réservations listées via customer_email
    $vip_map = array();
    if (!empty($reservations)) {
        $customer_emails = array();
        foreach ($reservations as $res) {
            if (!empty($res->customer_email)) {
                $customer_emails[] = $res->customer_email;
            }
        }
        $customer_emails = array_values(array_unique($customer_emails));
        if (!empty($customer_emails)) {
            $placeholders = implode(',', array_fill(0, count($customer_emails), '%s'));
            $customers_table = $wpdb->prefix . 'customer_stats';
            $vip_results = $wpdb->get_results($wpdb->prepare("SELECT email, is_vip FROM $customers_table WHERE email IN ($placeholders)", $customer_emails));
            if ($vip_results) {
                foreach ($vip_results as $row) {
                    $vip_map[$row->email] = (int) $row->is_vip;
                }
            }
            foreach ($reservations as $res) {
                $res->customer_is_vip = (!empty($res->customer_email) && isset($vip_map[$res->customer_email]) && $vip_map[$res->customer_email] === 1) ? 1 : 0;
            }
        }
    }
    
    // Calculer le nombre total de pages
    $total_pages = ceil($total_items / $items_per_page);

    // -- Calcul des statistiques simplifiées et pertinentes --
    $today_covers_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(people) FROM $table_name WHERE reservation_date = %s AND status = 'confirmed'", 
        $today
    ));
    $pending_reservations_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table_name WHERE status = 'pending' AND reservation_date >= %s", 
        $today
    ));
    $next_week_covers_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(people) FROM $table_name WHERE status = 'confirmed' AND reservation_date >= %s",
        $today
    ));
    
    settings_errors('gastro_starter_reservations');
    ?>
    <div class="wrap gastro-starter-reservations">
        <h1><?php echo esc_html__('Gestion des réservations', 'gastro-starter'); ?></h1>
        
        <div class="reservation-stats" id="dashboard-widgets">
            <a href="<?php echo esc_url(admin_url('admin.php?page=gastro-starter-reservations&date_filter=' . $today . '&status_filter=confirmed#reservations-list')); ?>" class="stat-box stat-box-clickable" data-has-items="<?php echo $today_covers_count > 0 ? 'true' : 'false'; ?>">
                <div class="stat-icon"><span class="dashicons dashicons-food"></span></div>
                <div class="stat-content">
                    <h3><?php echo esc_html__('Couverts ce jour', 'gastro-starter'); ?></h3>
                    <p class="stat-number"><?php echo esc_html($today_covers_count); ?></p>
                    <p class="stat-label"><?php echo esc_html__('Confirmés', 'gastro-starter'); ?></p>
                </div>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=gastro-starter-reservations&status_filter=pending#reservations-list')); ?>" class="stat-box stat-box-clickable" data-has-items="<?php echo $pending_reservations_count > 0 ? 'true' : 'false'; ?>">
                <div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
                <div class="stat-content">
                    <h3><?php echo esc_html__('En attente', 'gastro-starter'); ?></h3>
                    <p class="stat-number"><?php echo esc_html($pending_reservations_count); ?></p>
                    <p class="stat-label"><?php echo esc_html__('Aujourd\'hui & à venir', 'gastro-starter'); ?></p>
                </div>
            </a>
            <div class="stat-box">
                <div class="stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="stat-content">
                    <h3><?php echo esc_html__('Total Couverts (Futur)', 'gastro-starter'); ?></h3>
                    <p class="stat-number"><?php echo esc_html($next_week_covers_count); ?></p>
                    <p class="stat-label"><?php echo esc_html__('Prévisionnel', 'gastro-starter'); ?></p>
                </div>
            </div>
        </div>
        
        <div id="quick-reservation">
            <?php 
            if (file_exists(dirname(__FILE__) . '/quick-reservation-form.php')) {
                require_once dirname(__FILE__) . '/quick-reservation-form.php';
                gastro_starter_quick_reservation_form();
            }
            ?>
        </div>

        <?php wp_enqueue_style('gastro-starter-admin-css'); ?>
        
        <div class="gastro-starter-admin-card" id="reservations-list">
            <h2 style="margin-top: 0; padding: 0 0 15px 0; border-bottom: 1px solid #e2e4e7;"><span class="dashicons dashicons-list-view" style="margin-right: 8px;"></span><?php echo esc_html__('Liste des réservations', 'gastro-starter'); ?></h2>
            <div class="filter-bar">
                <form method="get" action="" class="filter-form" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="gastro-starter-reservations">
                    
                    <div class="filter-group filter-group-search" style="flex-grow: 1;">
                        <label for="reservation-search-input" class="screen-reader-text"><?php echo esc_html__('Rechercher', 'gastro-starter'); ?></label>
                        <input type="search" id="reservation-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php echo esc_attr__('Rechercher par nom, email, tél, ID...', 'gastro-starter'); ?>" style="width: 100%;">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_filter" class="screen-reader-text"><?php echo esc_html__('Date :', 'gastro-starter'); ?></label>
                        <input type="date" id="date_filter" name="date_filter" value="<?php echo esc_attr($date_filter); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status_filter" class="screen-reader-text"><?php echo esc_html__('Statut :', 'gastro-starter'); ?></label>
                        <select name="status_filter" id="status_filter" class="filter-input">
                            <option value=""><?php echo esc_html__('Tous les statuts', 'gastro-starter'); ?></option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php echo esc_html__('En attente', 'gastro-starter'); ?></option>
                            <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php echo esc_html__('Confirmé', 'gastro-starter'); ?></option>
                            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php echo esc_html__('Annulé', 'gastro-starter'); ?></option>
                            <option value="no-show" <?php selected($status_filter, 'no-show'); ?>><?php echo esc_html__('No-Show', 'gastro-starter'); ?></option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php echo esc_html__('Terminée', 'gastro-starter'); ?></option>
                        </select>
                    </div>

                    <div class="filter-actions" style="display: flex; gap: 8px;">
                        <button type="submit" class="button button-primary filter-button"><span class="dashicons dashicons-filter"></span><?php echo esc_html__('Filtrer', 'gastro-starter'); ?></button>
                        <?php if ($is_filtered) : ?>
                            <a href="?page=gastro-starter-reservations" class="button reset-button"><span class="dashicons dashicons-dismiss"></span><?php echo esc_html__('Réinitialiser', 'gastro-starter'); ?></a>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=gastro_starter_print_reservations&date_filter=' . (!empty($date_filter) ? $date_filter : $today) . '&status_filter=' . $status_filter)); ?>" class="button button-secondary" target="_blank" style="margin-left: 10px;">
                            <span class="dashicons dashicons-printer"></span> <?php echo esc_html__('Imprimer la liste du jour', 'gastro-starter'); ?>
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="reservations-table-container">
                <table class="wp-list-table widefat fixed striped reservations-table">
                    <thead>
                        <tr>
                            <th class="column-id"><?php echo esc_html__('ID', 'gastro-starter'); ?></th>
                            <th class="column-created"><?php echo esc_html__('Prise le', 'gastro-starter'); ?></th>
                            <th class="column-date"><?php echo esc_html__('Date', 'gastro-starter'); ?></th>
                            <th class="column-time"><?php echo esc_html__('Heure', 'gastro-starter'); ?></th>
                            <th class="column-people"><?php echo esc_html__('Pers.', 'gastro-starter'); ?></th>
                            <th class="column-customer"><?php echo esc_html__('Client', 'gastro-starter'); ?></th>
                            <th class="column-status"><?php echo esc_html__('Statut', 'gastro-starter'); ?></th>
                            <th class="column-notes"><?php echo esc_html__('Notes', 'gastro-starter'); ?></th>
                            <th class="column-actions"><?php echo esc_html__('Actions', 'gastro-starter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)) : ?>
                            <tr><td colspan="8" class="no-results"><?php echo esc_html__('Aucune réservation trouvée.', 'gastro-starter'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($reservations as $reservation) : ?>
                                <?php 
                                $date_obj = new DateTime($reservation->reservation_date);
                                $reservation_datetime = new DateTime($reservation->reservation_date . ' ' . $reservation->reservation_time);
                                $now = new DateTime();
                                $is_past = $reservation_datetime < $now;
                                
                                $status_labels = [
                                    'pending' => __('En attente', 'gastro-starter'),
                                    'confirmed' => __('Confirmé', 'gastro-starter'),
                                    'cancelled' => __('Annulé', 'gastro-starter'),
                                    'no-show' => __('No-Show', 'gastro-starter'),
                                    'completed' => __('Terminée', 'gastro-starter')
                                ];
                                $status_class = "status-" . $reservation->status;
                                $status_label = $status_labels[$reservation->status] ?? $reservation->status;
                                
                                $row_class = $reservation->reservation_date === $today ? 'today-reservation' : '';
                                if (isset($_GET['id']) && intval($_GET['id']) === $reservation->id) {
                                    $row_class .= ' reservation-action-executed';
                                }
                                if (isset($reservation->source) && $reservation->source === 'admin') {
                                    $row_class .= ' admin-reservation';
                                }
                                ?>
                                <tr class="<?php echo esc_attr($row_class); ?>">
                                    <td class="column-id" data-label="<?php echo esc_attr__('ID', 'gastro-starter'); ?>"><?php echo esc_html($reservation->id); ?></td>
                                    <td class="column-created" data-label="<?php echo esc_attr__('Prise le', 'gastro-starter'); ?>">
                                        <?php echo !empty($reservation->created_at) ? esc_html(date('d/m/Y H:i', strtotime($reservation->created_at))) : '-'; ?>
                                    </td>
                                    <td class="column-date" data-label="<?php echo esc_attr__('Date', 'gastro-starter'); ?>"><div class="date-info"><span class="date-display"><?php echo esc_html($date_obj->format('d/m/Y')); ?></span><span class="day-label"><?php echo esc_html($date_obj->format('D')); ?></span></div></td>
                                    <td class="column-time" data-label="<?php echo esc_attr__('Heure', 'gastro-starter'); ?>"><?php echo esc_html(date('H:i', strtotime($reservation->reservation_time))); ?></td>
                                    <td class="column-people" data-label="<?php echo esc_attr__('Personnes', 'gastro-starter'); ?>"><span class="people-count"><?php echo esc_html($reservation->people); ?></span></td>
                                    <td class="column-customer" data-label="<?php echo esc_attr__('Client', 'gastro-starter'); ?>">
                                        <div class="customer-info">
                                            <div class="customer-name"><?php if (!empty($reservation->customer_is_vip)) : ?><span class="vip-crown" title="<?php echo esc_attr__('VIP', 'gastro-starter'); ?>"></span><?php endif; ?><?php echo esc_html($reservation->customer_name); ?></div>
                                            <?php if (!empty($reservation->customer_email)) : ?><a href="mailto:<?php echo esc_attr($reservation->customer_email); ?>" class="customer-email"><span class="dashicons dashicons-email"></span><?php echo esc_html($reservation->customer_email); ?></a><?php endif; ?>
                                            <?php if (!empty($reservation->customer_phone)) : ?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $reservation->customer_phone)); ?>" class="customer-phone"><span class="dashicons dashicons-phone"></span><?php echo esc_html($reservation->customer_phone); ?></a><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-status" data-label="<?php echo esc_attr__('Statut', 'gastro-starter'); ?>"><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                                    <td class="column-notes" data-label="<?php echo esc_attr__('Notes', 'gastro-starter'); ?>">
                                        <?php if (!empty($reservation->notes)) : ?>
                                            <div class="notes-content">
                                                <span class="notes-icon dashicons dashicons-admin-comments"></span>
                                                <span class="notes-text"><?php echo esc_html($reservation->notes); ?></span>
                                            </div>
                                        <?php else : ?>
                                            <span class="no-notes">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-actions" data-label="<?php echo esc_attr__('Actions', 'gastro-starter'); ?>">
                                        <div class="action-buttons">
                                            <button type="button" class="button action-button edit-button" data-reservation-id="<?php echo esc_attr($reservation->id); ?>" title="<?php echo esc_attr__('Modifier', 'gastro-starter'); ?>"><span class="dashicons dashicons-edit"></span></button>
                                            <?php if ($reservation->status === 'pending') : ?>
                                                <a href="<?php echo gastro_starter_get_action_url('confirm', $reservation->id, $date_filter, $status_filter); ?>" class="button action-button confirm-button" title="<?php echo esc_attr__('Confirmer', 'gastro-starter'); ?>" data-touch="1"><span class="dashicons dashicons-yes"></span></a>
                                            <?php endif; ?>
                                             <?php if ($is_past && $reservation->status === 'confirmed') : ?>
                                                <a href="<?php echo gastro_starter_get_action_url('noshow', $reservation->id, $date_filter, $status_filter); ?>" class="button action-button noshow-button" title="<?php echo esc_attr__('Marquer comme No-Show', 'gastro-starter'); ?>" data-touch="1" onclick="return confirm('<?php echo esc_js(__('Marquer cette réservation comme non-présentée ?', 'gastro-starter')); ?>');"><span class="dashicons dashicons-marker" style="color:#a00;"></span></a>
                                            <?php endif; ?>
                                            <?php if ($reservation->status !== 'cancelled') : ?>
                                                <a href="<?php echo gastro_starter_get_action_url('cancel', $reservation->id, $date_filter, $status_filter); ?>" class="button action-button cancel-button" title="<?php echo esc_attr__('Annuler', 'gastro-starter'); ?>" data-touch="1" onclick="return confirm('<?php echo esc_js(__('Êtes-vous sûr de vouloir annuler cette réservation?', 'gastro-starter')); ?>');"><span class="dashicons dashicons-no"></span></a>
                                            <?php endif; ?>
                                            <a href="<?php echo gastro_starter_get_action_url('delete', $reservation->id, $date_filter, $status_filter); ?>" class="button action-button delete-button" title="<?php echo esc_attr__('Supprimer', 'gastro-starter'); ?>" data-touch="1" onclick="return confirm('<?php echo esc_js(__('Êtes-vous sûr de vouloir supprimer définitivement cette réservation?', 'gastro-starter')); ?>');"><span class="dashicons dashicons-trash"></span></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="tablenav bottom" style="padding: 15px 0 0 0;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html($total_items); ?> réservations</span>
                    <?php
                    if ($total_pages > 1) {
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'show_all' => false,
                            'end_size' => 1,
                            'mid_size' => 2,
                            'prev_next' => true,
                            'prev_text' => __('&laquo; Précédent'),
                            'next_text' => __('Suivant &raquo;'),
                            'type' => 'plain',
                        );
                        
                        // Conserver les paramètres de filtre dans les liens de pagination
                        $pagination_args['add_args'] = array();
                        if (!empty($search_query)) $pagination_args['add_args']['s'] = urlencode($search_query);
                        if (!empty($date_filter)) $pagination_args['add_args']['date_filter'] = $date_filter;
                        if (!empty($status_filter)) $pagination_args['add_args']['status_filter'] = $status_filter;
                        
                        echo paginate_links($pagination_args);
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="gastro-starter-admin-card" id="email-tests" style="margin-top: 30px;">
             <h2 style="margin-top: 0; padding: 0 0 15px 0; border-bottom: 1px solid #e2e4e7;"><span class="dashicons dashicons-admin-tools" style="margin-right: 8px;"></span><?php echo esc_html__('Diagnostic et tests', 'gastro-starter'); ?></h2>
             <div class="diagnostic-section">
                <h3><?php echo esc_html__('Test d\'envoi d\'emails', 'gastro-starter'); ?></h3>
                <p class="description"><?php echo esc_html__('Utilisez ce bouton pour tester la configuration SMTP et l\'envoi d\'emails.', 'gastro-starter'); ?></p>
                <button type="button" id="test-email-btn" class="button button-secondary"><span class="dashicons dashicons-email-alt"></span><?php echo esc_html__('Tester l\'envoi d\'emails', 'gastro-starter'); ?></button>
                <div id="test-email-result" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Fonction AJAX pour confirmer une réservation
 */
function gastro_starter_confirm_reservation_callback() {
    // Vérifier nonce
    check_ajax_referer('gastro_starter_confirm_reservation', 'security');
    
    // Vérifier les droits
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => __('Vous n\'avez pas les autorisations nécessaires.', 'gastro-starter')
        ));
    }
    
    // Vérifier réservation ID
    if (!isset($_POST['reservation_id']) || !is_numeric($_POST['reservation_id'])) {
        wp_send_json_error(array(
            'message' => __('ID de réservation invalide.', 'gastro-starter')
        ));
    }
    
    $reservation_id = intval($_POST['reservation_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // Récupérer la réservation
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $reservation_id
    ));
    
    if (!$reservation) {
        wp_send_json_error(array(
            'message' => __('Réservation introuvable.', 'gastro-starter')
        ));
    }
    
    // Mettre à jour le statut
    $result = $wpdb->update(
        $table_name,
        array('status' => 'confirmed'),
        array('id' => $reservation_id)
    );
    
    if ($result === false) {
        wp_send_json_error(array(
            'message' => __('Erreur lors de la confirmation de la réservation.', 'gastro-starter')
        ));
    }
    
    // Envoyer l'email de confirmation si pas déjà envoyé
    if (!$reservation->confirmation_email_sent) {
        $email_sent = gastro_starter_send_confirmation_email($reservation_id);
        
        if ($email_sent) {
            $wpdb->update(
                $table_name,
                array('confirmation_email_sent' => 1),
                array('id' => $reservation_id)
            );
        }
    }
    
    // Mettre à jour les statistiques client
    if (!empty($reservation->customer_email)) {
        gastro_starter_update_customer_visits($reservation->customer_email, $reservation_id);
    }
    
    wp_send_json_success(array(
        'message' => __('Réservation confirmée avec succès !', 'gastro-starter')
    ));
}
add_action('wp_ajax_gastro_starter_confirm_reservation', 'gastro_starter_confirm_reservation_callback');


/**
 * Répond à la requête AJAX pour obtenir les disponibilités.
 * C'est le point d'entrée pour le script de réservation côté client.
 * Intègre le système de pooling de capacité.
 */
function gastro_starter_get_availability_callback() {
    // Vérification de sécurité de base
    if (!isset($_GET['date'])) {
        wp_send_json_error(['message' => 'Date manquante.'], 400);
    }

    $date_str = sanitize_text_field($_GET['date']);
    
    // Validation simple du format de date YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        wp_send_json_error(['message' => 'Format de date invalide.'], 400);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // 1. Récupérer les créneaux théoriques pour ce jour
    $available_slots = gastro_starter_get_available_slots_for_date($date_str);
    
    // 2. Récupérer les réservations existantes pour ce jour
    // IMPORTANT: On compte TOUTES les réservations, y compris les fantômes
    // car chaque réservation (fantôme ou non) occupe réellement des places sur son créneau
    $raw_reservations = $wpdb->get_results($wpdb->prepare(
        "SELECT reservation_time, people 
         FROM $table_name 
         WHERE reservation_date = %s 
         AND status IN ('confirmed', 'pending')",
        $date_str
    ));
    
    $reservations_by_time = [];
    foreach ($raw_reservations as $res) {
        // On formate l'heure en PHP pour garantir le format H:i
        $time_key = date('H:i', strtotime($res->reservation_time));
        if (!isset($reservations_by_time[$time_key])) {
            $reservations_by_time[$time_key] = 0;
        }
        $reservations_by_time[$time_key] += (int)$res->people;
    }

    // 3. Récupérer la capacité du restaurant
    $capacity_per_slot = (int)get_option('gastro_starter_restaurant_capacity', 4);
    
    // 4. NOUVEAU : Vérifier si le pooling est activé
    $pooling_enabled = get_option('gastro_starter_pooling_enabled', true);
    $pooling_data = null;
    
    if ($pooling_enabled) {
        // Si nombre de personnes fourni, vérifier pooling
        $people = isset($_GET['people']) ? absint($_GET['people']) : 0;
        
        if ($people > 0) {
            $pooling_manager = new Gastro_Starter_Capacity_Pooling_Manager();
            $pool_result = $pooling_manager->find_available_capacity_pool($date_str, $people);
            
            if ($pool_result['can_accommodate']) {
                $pooling_data = [
                    'available' => true,
                    'pooling_required' => $pool_result['pooling_required'],
                    'primary_slot' => $pool_result['primary_slot'],
                    'slots_used' => $pool_result['slots_used'],
                    'message' => $pool_result['pooling_required'] 
                        ? sprintf('Groupe de %d personnes accepté en combinant %d créneaux', $people, count($pool_result['slots_used']))
                        : sprintf('Groupe de %d personnes accepté sur le créneau %s', $people, $pool_result['primary_slot'])
                ];
            } else {
                $pooling_data = [
                    'available' => false,
                    'message' => sprintf('Impossible d\'accueillir %d personnes (capacité insuffisante)', $people)
                ];
            }
        }
    }

    $response_data = [
        'available_slots' => $available_slots,
        'time_slots' => $reservations_by_time,
        'capacity_per_slot' => $capacity_per_slot,
        'pooling_enabled' => $pooling_enabled,
        'pooling_data' => $pooling_data
    ];

    wp_send_json_success($response_data);
}
add_action('wp_ajax_gastro_starter_get_availability', 'gastro_starter_get_availability_callback');
add_action('wp_ajax_nopriv_gastro_starter_get_availability', 'gastro_starter_get_availability_callback');


/**
 * Récupérer une réservation (AJAX)
 */
function gastro_starter_get_reservation_ajax() {
    check_ajax_referer('gastro_starter_reservation_edit', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Accès refusé', 'gastro-starter')], 403);
    }
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        wp_send_json_error(['message' => __('ID invalide', 'gastro-starter')], 400);
    }
    $id = intval($_GET['id']);
    $reservation = gastro_starter_get_reservation_manager()->get_reservation($id);
    if (!$reservation) {
        wp_send_json_error(['message' => __('Introuvable', 'gastro-starter')], 404);
    }
    wp_send_json_success($reservation);
}
add_action('wp_ajax_gastro_starter_get_reservation', 'gastro_starter_get_reservation_ajax');

/**
 * Mettre à jour une réservation (AJAX)
 */
function gastro_starter_update_reservation_ajax() {
    check_ajax_referer('gastro_starter_reservation_edit', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Accès refusé', 'gastro-starter')], 403);
    }
    $required = ['id','reservation_date','reservation_time','people','customer_name'];
    foreach ($required as $key) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            wp_send_json_error(['message' => __('Champs manquants', 'gastro-starter')], 400);
        }
    }

    $id = intval($_POST['id']);
    $data = array(
        'reservation_date' => sanitize_text_field($_POST['reservation_date']),
        'reservation_time' => sanitize_text_field($_POST['reservation_time']),
        'people' => absint($_POST['people']),
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : null,
        'customer_phone' => isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : null,
        'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
    );

    $updated = gastro_starter_get_reservation_manager()->update_reservation($id, $data);
    if ($updated === false) {
        wp_send_json_error(['message' => __('Échec de la mise à jour', 'gastro-starter')], 500);
    }
    wp_send_json_success(['message' => __('Réservation mise à jour', 'gastro-starter')]);
}
add_action('wp_ajax_gastro_starter_update_reservation', 'gastro_starter_update_reservation_ajax');