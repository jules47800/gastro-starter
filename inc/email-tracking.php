<?php
/**
 * Tracking d'ouverture des emails par pixel invisible
 *
 * @package Gastro_Starter
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Email_Tracking {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_tracking_pixel']);
        add_filter('gastro_starter_email_html', [$this, 'inject_tracking_pixel'], 99, 2);
    }

    /**
     * Créer la table de tracking
     */
    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tracking_id varchar(64) NOT NULL,
            email_log_id bigint(20) NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            email_type varchar(50) NOT NULL DEFAULT 'general',
            sent_at datetime NOT NULL,
            opened_at datetime NULL,
            open_count int(11) NOT NULL DEFAULT 0,
            user_agent text NULL,
            ip_address varchar(45) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tracking_id (tracking_id),
            KEY recipient (recipient),
            KEY email_type (email_type),
            KEY opened_at (opened_at),
            KEY sent_at (sent_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Générer un ID de tracking unique
     */
    public static function generate_tracking_id() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Enregistrer un envoi pour tracking
     */
    public static function register_send($recipient, $subject, $email_type = 'general', $email_log_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';
        $tracking_id = self::generate_tracking_id();

        $wpdb->insert($table, [
            'tracking_id' => $tracking_id,
            'email_log_id' => $email_log_id,
            'recipient' => sanitize_email($recipient),
            'subject' => sanitize_text_field($subject),
            'email_type' => sanitize_text_field($email_type),
            'sent_at' => current_time('mysql'),
        ]);

        return $tracking_id;
    }

    /**
     * Construire l'URL du pixel de tracking
     */
    public static function get_pixel_url($tracking_id) {
        return home_url('/email-track/' . $tracking_id . '.png');
    }

    /**
     * Générer le tag HTML du pixel invisible
     */
    public static function get_pixel_html($tracking_id) {
        $url = self::get_pixel_url($tracking_id);
        return '<img src="' . esc_url($url) . '" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" alt="" />';
    }

    /**
     * Injecter le pixel dans le HTML d'un email (via filtre)
     */
    public function inject_tracking_pixel($html, $tracking_id) {
        if (empty($tracking_id)) {
            return $html;
        }
        $pixel = self::get_pixel_html($tracking_id);
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $pixel . '</body>', $html);
        } else {
            $html .= $pixel;
        }
        return $html;
    }

    /**
     * Rewrite rules pour l'endpoint /email-track/{id}.png
     */
    public function register_rewrite_rules() {
        add_rewrite_rule(
            '^email-track/([a-f0-9]{32})\.png$',
            'index.php?gastro_starter_track=$matches[1]',
            'top'
        );
        add_filter('query_vars', function ($vars) {
            $vars[] = 'gastro_starter_track';
            return $vars;
        });
    }

    /**
     * Servir le pixel et enregistrer l'ouverture
     */
    public function handle_tracking_pixel() {
        $tracking_id = get_query_var('gastro_starter_track');
        if (empty($tracking_id) || !preg_match('/^[a-f0-9]{32}$/', $tracking_id)) {
            return;
        }

        $this->record_open($tracking_id);

        // Pixel GIF transparent 1x1
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        nocache_headers();
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($pixel));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $pixel;
        exit;
    }

    /**
     * Enregistrer une ouverture
     */
    private function record_open($tracking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, open_count FROM $table WHERE tracking_id = %s",
            $tracking_id
        ));

        if (!$existing) {
            return;
        }

        $data = [
            'open_count' => $existing->open_count + 1,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) : '',
            'ip_address' => $this->get_client_ip(),
        ];

        if ($existing->open_count === 0) {
            $data['opened_at'] = current_time('mysql');
        }

        $wpdb->update($table, $data, ['id' => $existing->id]);
    }

    /**
     * Récupérer l'IP du client
     */
    private function get_client_ip() {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    /**
     * Obtenir les stats d'ouverture
     */
    public static function get_open_stats($days = 7, $email_type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));

        $where = $wpdb->prepare("sent_at >= %s", $date_from);
        if ($email_type) {
            $where .= $wpdb->prepare(" AND email_type = %s", $email_type);
        }

        return $wpdb->get_row("
            SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN open_count > 0 THEN 1 ELSE 0 END) as total_opened,
                ROUND(SUM(CASE WHEN open_count > 0 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as open_rate
            FROM $table
            WHERE $where
        ", ARRAY_A);
    }

    /**
     * Obtenir les ouvertures récentes
     */
    public static function get_recent_opens($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE open_count > 0
            ORDER BY opened_at DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Obtenir tout le tracking récent (ouvert ou non)
     */
    public static function get_recent_tracking($limit = 50, $filter = 'all') {
        global $wpdb;
        $table = $wpdb->prefix . 'email_tracking';

        $where = '1=1';
        if ($filter === 'opened') {
            $where = 'open_count > 0';
        } elseif ($filter === 'unopened') {
            $where = 'open_count = 0';
        }

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE $where
            ORDER BY sent_at DESC
            LIMIT %d
        ", $limit));
    }
}

// Initialiser
Gastro_Starter_Email_Tracking::get_instance();

// Créer la table à l'activation du thème
add_action('after_switch_theme', ['Gastro_Starter_Email_Tracking', 'create_table']);

// Flush rewrite rules une seule fois
add_action('init', function () {
    if (get_option('gastro_starter_tracking_rewrite_flushed') !== '2.5.0') {
        flush_rewrite_rules();
        update_option('gastro_starter_tracking_rewrite_flushed', '2.5.0');
    }
});
