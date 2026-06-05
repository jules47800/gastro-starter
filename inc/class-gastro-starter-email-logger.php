<?php
/**
 * Gestionnaire de logs pour les emails
 * Permet de tracer tous les envois d'emails et identifier les problèmes
 * 
 * @package Gastro_Starter
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Email_Logger {
    
    /**
     * Créer la table de logs lors de l'activation
     */
    public static function create_logs_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'email_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
        
        error_log('Table email_logs créée ou mise à jour');
    }
    
    /**
     * Logger une tentative d'envoi d'email
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param bool $success Succès de l'envoi
     * @param string $email_type Type d'email (confirmation, reminder, etc.)
     * @param int $attempts Nombre de tentatives
     * @param string $error Message d'erreur si échec
     * @param int $reservation_id ID de la réservation associée
     */
    public static function log_email_attempt($to, $subject, $success, $email_type = 'general', $attempts = 1, $error = '', $reservation_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_logs';
        
        $data = [
            'recipient' => sanitize_email($to),
            'subject' => sanitize_text_field($subject),
            'email_type' => sanitize_text_field($email_type),
            'status' => $success ? 'sent' : 'failed',
            'attempts' => intval($attempts),
            'error_message' => $error ? sanitize_text_field($error) : '',
            'reservation_id' => $reservation_id ? intval($reservation_id) : null,
            'sent_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if (!$result) {
            error_log('Erreur lors du logging email: ' . $wpdb->last_error);
        }
        
        // Log également dans error_log pour backup
        $log_message = sprintf(
            '[EMAIL %s] Type: %s | To: %s | Subject: %s | Attempts: %d%s',
            $success ? 'SENT' : 'FAILED',
            $email_type,
            $to,
            $subject,
            $attempts,
            $error ? ' | Error: ' . $error : ''
        );
        error_log($log_message);
    }
    
    /**
     * Récupérer les logs d'emails récents
     * 
     * @param int $limit Nombre de logs à récupérer
     * @param string $status Filtrer par statut (sent, failed, all)
     * @return array Liste des logs
     */
    public static function get_recent_logs($limit = 50, $status = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_logs';
        
        $where = $status !== 'all' ? $wpdb->prepare("WHERE status = %s", $status) : '';
        
        $query = "SELECT * FROM $table_name 
                  $where 
                  ORDER BY sent_at DESC 
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $limit));
    }
    
    /**
     * Obtenir les statistiques d'envoi
     * 
     * @param int $days Nombre de jours à analyser
     * @return array Statistiques
     */
    public static function get_stats($days = 7) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_logs';
        
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(attempts) as avg_attempts
            FROM $table_name
            WHERE sent_at >= %s
        ", $date_from), ARRAY_A);
        
        if ($stats) {
            $stats['success_rate'] = $stats['total'] > 0 
                ? round(($stats['sent'] / $stats['total']) * 100, 2) 
                : 0;
        }
        
        return $stats;
    }
    
    /**
     * Nettoyer les vieux logs (garder 30 jours)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_logs';
        
        $date_cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE sent_at < %s",
            $date_cutoff
        ));
        
        if ($deleted) {
            error_log("Email logs: $deleted anciens logs supprimés");
        }
    }
}

// Créer la table lors du chargement du fichier
add_action('after_switch_theme', ['Gastro_Starter_Email_Logger', 'create_logs_table']);

// Nettoyer les logs hebdomadairement
if (!wp_next_scheduled('gastro_starter_cleanup_email_logs')) {
    wp_schedule_event(time(), 'weekly', 'gastro_starter_cleanup_email_logs');
}
add_action('gastro_starter_cleanup_email_logs', ['Gastro_Starter_Email_Logger', 'cleanup_old_logs']);
