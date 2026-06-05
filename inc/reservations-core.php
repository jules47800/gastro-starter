<?php
/**
 * Mon Restaurant - Fonctions de base pour les réservations (règles, cron, etc.)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Créer les tables nécessaires lors de l'activation du thème
 */
function gastro_starter_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table des réservations
    $table_name = $wpdb->prefix . 'reservations';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        reservation_date date NOT NULL,
        reservation_time time NOT NULL,
        people int(11) NOT NULL,
        customer_name varchar(100) NOT NULL,
        customer_email varchar(100) NULL,
        customer_phone varchar(20) NULL,
        notes text NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        source varchar(20) NOT NULL DEFAULT 'public',
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
        followup_email_sent tinyint(1) NOT NULL DEFAULT 0,
        accept_reminder tinyint(1) NOT NULL DEFAULT 0,
        newsletter tinyint(1) NOT NULL DEFAULT 0,
        consent_data_processing tinyint(1) NOT NULL DEFAULT 0,
        consent_data_storage tinyint(1) NOT NULL DEFAULT 0,
        is_pooled tinyint(1) NOT NULL DEFAULT 0,
        pooling_data TEXT NULL,
        parent_reservation_id INT NULL,
        PRIMARY KEY  (id),
        KEY reservation_date (reservation_date),
        KEY status (status),
        KEY customer_email (customer_email),
        KEY is_pooled (is_pooled),
        KEY parent_reservation_id (parent_reservation_id)
    ) $charset_collate;";

    // Table des limites de taux
    $rate_limits_table = $wpdb->prefix . 'gastro_starter_rate_limits';
    $sql .= "CREATE TABLE IF NOT EXISTS $rate_limits_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL,
        attempt_count int(11) NOT NULL DEFAULT 1,
        last_attempt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY ip_address (ip_address),
        KEY last_attempt (last_attempt)
    ) $charset_collate;";

    // Table des statistiques clients (définition unique et correcte)
    $stats_table = $wpdb->prefix . 'customer_stats';
    $sql .= "CREATE TABLE IF NOT EXISTS $stats_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        name varchar(100) NULL,
        visits int(11) NOT NULL DEFAULT 0,
        first_visit datetime NULL,
        last_visit datetime NULL,
        last_reservation_id bigint(20) NULL,
        is_vip tinyint(1) NOT NULL DEFAULT 0,
        consent_data_processing tinyint(1) NOT NULL DEFAULT 0,
        consent_data_storage tinyint(1) NOT NULL DEFAULT 0,
        accept_reminder tinyint(1) NOT NULL DEFAULT 0,
        newsletter tinyint(1) NOT NULL DEFAULT 0,
        consent_date datetime NULL,
        notes text NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email),
        KEY is_vip (is_vip)
    ) $charset_collate;";

    // Table des logs d'emails
    $email_logs_table = $wpdb->prefix . 'email_logs';
    $sql .= "CREATE TABLE IF NOT EXISTS $email_logs_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        recipient varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        email_type varchar(50) NOT NULL DEFAULT 'general',
        status varchar(20) NOT NULL,
        attempts int(11) NOT NULL DEFAULT 1,
        error_message text NULL,
        reservation_id bigint(20) NULL,
        sent_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY recipient (recipient),
        KEY status (status),
        KEY email_type (email_type),
        KEY sent_at (sent_at),
        KEY reservation_id (reservation_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log('Tables créées ou mises à jour : reservations, rate_limits, customer_stats, email_logs');
}
register_activation_hook(get_template_directory() . '/functions.php', 'gastro_starter_create_tables');

/**
 * Ajouter les routes pour les annulations
 */
function gastro_starter_add_rewrite_rules() {
    add_rewrite_rule(
        'annuler-reservation/([0-9]+)/([^/]+)/?$',
        'index.php?pagename=annuler-reservation&id=$matches[1]&nonce=$matches[2]',
        'top'
    );
}
add_action('init', 'gastro_starter_add_rewrite_rules');

/**
 * Ajouter les variables de requête personnalisées
 */
function gastro_starter_query_vars($vars) {
    $vars[] = 'id';
    $vars[] = 'nonce';
    return $vars;
}
add_filter('query_vars', 'gastro_starter_query_vars');

/**
 * Planifier l'envoi des rappels
 */
function gastro_starter_schedule_reminders() {
    if (!wp_next_scheduled('gastro_starter_send_reminders')) {
        wp_schedule_event(strtotime('today 10:00:00'), 'daily', 'gastro_starter_send_reminders');
    }
}
add_action('wp', 'gastro_starter_schedule_reminders');

/**
 * Envoyer les rappels
 */
function gastro_starter_send_reminders() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // Récupérer les réservations du lendemain
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $reservations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE reservation_date = %s 
        AND status = 'confirmed' 
        AND reminder_sent = 0",
        $tomorrow
    ));
    
    foreach ($reservations as $reservation) {
        gastro_starter_send_reminder_email($reservation->id);
    }
}
add_action('gastro_starter_send_reminders', 'gastro_starter_send_reminders');

/**
 * Planifier l'envoi des emails de relance post-visite (J+3)
 */
function gastro_starter_schedule_followup_emails() {
    if (!wp_next_scheduled('gastro_starter_send_followup_emails')) {
        wp_schedule_event(strtotime('today 10:00:00'), 'daily', 'gastro_starter_send_followup_emails');
    }
}
add_action('wp', 'gastro_starter_schedule_followup_emails');

/**
 * Traiter les emails de relance post-visite
 */
function gastro_starter_process_followup_emails() {
    if (!get_option('gastro_starter_followup_enabled', true)) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'reservations';

    $delay = (int) get_option('gastro_starter_followup_delay', 2);
    $target_date = date('Y-m-d', strtotime("-{$delay} days"));

    // Grouper par email pour éviter les doublons (client avec 2 résas le même jour)
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT MIN(id) as id, customer_email, customer_name
         FROM $table
         WHERE reservation_date = %s
         AND status = 'confirmed'
         AND followup_email_sent = 0
         AND customer_email IS NOT NULL
         AND customer_email != ''
         GROUP BY customer_email",
        $target_date
    ));

    foreach ($customers as $customer) {
        gastro_starter_send_followup_email($customer->id);

        // Marquer TOUTES les résas de ce client/date comme traitées
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET followup_email_sent = 1
             WHERE reservation_date = %s AND customer_email = %s AND status = 'confirmed'",
            $target_date,
            $customer->customer_email
        ));
    }
}
add_action('gastro_starter_send_followup_emails', 'gastro_starter_process_followup_emails');

/**
 * Fonction pour réactiver le thème et recréer les tables si nécessaire
 */
function gastro_starter_maybe_recreate_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        gastro_starter_create_tables();
    }
}
add_action('after_switch_theme', 'gastro_starter_maybe_recreate_tables'); 

/**
 * Mettre à jour la structure de la base de données si nécessaire
 */
function gastro_starter_update_db_check() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';

    // Vérifier si la colonne 'source' existe
    $column_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM `$table_name` LIKE %s",
        'source'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE `$table_name` ADD `source` VARCHAR(20) NOT NULL DEFAULT 'public' AFTER `status`");
    }

    // Vérifier si la colonne 'followup_email_sent' existe
    $followup_col = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM `$table_name` LIKE %s",
        'followup_email_sent'
    ));

    if (empty($followup_col)) {
        $wpdb->query("ALTER TABLE `$table_name` ADD `followup_email_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`");
    }
}
add_action('admin_init', 'gastro_starter_update_db_check');

/**
 * Planifier la clôture automatique des réservations passées
 */
function gastro_starter_schedule_auto_complete() {
    if (!wp_next_scheduled('gastro_starter_auto_complete_reservations')) {
        wp_schedule_event(strtotime('today 06:00:00'), 'daily', 'gastro_starter_auto_complete_reservations');
    }
}
add_action('wp', 'gastro_starter_schedule_auto_complete');

/**
 * Passer en "completed" les réservations confirmées dont la date est passée
 */
function gastro_starter_process_auto_complete() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $table_name
         SET status = 'completed'
         WHERE reservation_date <= %s
         AND status = 'confirmed'",
        $yesterday
    ));

    if ($updated > 0) {
        error_log("[Mon Restaurant] Auto-complete: $updated réservation(s) passée(s) en terminé.");
    }
}
add_action('gastro_starter_auto_complete_reservations', 'gastro_starter_process_auto_complete');