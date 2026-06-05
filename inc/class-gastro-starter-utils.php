<?php
/**
 * Classe utilitaire pour Mon Restaurant
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Utils {
    private static $instance = null;

    private function __construct() {}

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charge un template partiel
     */
    public static function get_template_part($template, $data = array()) {
        $template_path = get_template_directory() . '/template-parts/' . $template . '.php';
        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        }
    }

    /**
     * Formate une date selon le format spécifié
     */
    public static function format_date($date, $format = 'd/m/Y') {
        return date_i18n($format, strtotime($date));
    }

    /**
     * Fonction utilitaire pour formater les créneaux horaires
     */
    public static function format_time_slots($times_str) {
        return array_map(function($time) {
            return array('value' => trim($time), 'label' => trim($time));
        }, explode(',', $times_str));
    }

    /**
     * Nettoie et valide les données de réservation
     */
    public static function sanitize_reservation_data($data) {
        // Conversion sécurisée de la date
        $date_input = sanitize_text_field($data['date']);
        $date_obj = DateTime::createFromFormat('d/m/Y', $date_input);
        
        if ($date_obj === false) {
            // Si le format d/m/Y échoue, essayer d'autres formats
            $date_obj = DateTime::createFromFormat('Y-m-d', $date_input);
            if ($date_obj === false) {
                // Si aucun format ne fonctionne, lever une exception
                throw new Exception('Format de date invalide. Format attendu : JJ/MM/AAAA');
            }
        }
        
        return array(
            'meal_type' => 'general', // Plus de distinction déjeuner/dîner
            'reservation_time' => sanitize_text_field($data['time']),
            'people' => intval($data['people']),
            'reservation_date' => $date_obj->format('Y-m-d'),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'notes' => sanitize_textarea_field($data['notes']),
            'accept_reminder' => isset($data['accept_reminder']) ? 1 : 0,
            'newsletter' => isset($data['newsletter']) ? 1 : 0,
            'consent_data_processing' => isset($data['consent_data_processing']) ? 1 : 0,
            'consent_data_storage' => isset($data['consent_data_storage']) ? 1 : 0
        );
    }
}

/**
 * Gestionnaire d'erreurs
 */
class Gastro_Starter_Error_Handler {
    public static function handle_error($message, $redirect_url = null) {
        error_log($message);
        if ($redirect_url) {
            wp_redirect(add_query_arg('reservation_error', urlencode($message), $redirect_url));
            exit;
        }
        return false;
    }

    public static function handle_ajax_error($message) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => $message));
        exit;
    }
}

/**
 * Gestionnaire de redirection
 */
class Gastro_Starter_Redirect_Handler {
    public static function redirect($url, $is_ajax = false) {
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => true, 'redirect' => $url));
            exit;
        } else {
            wp_safe_redirect($url);
            exit;
        }
    }
}

/**
 * Gestionnaire de rate limiting
 */
class Gastro_Starter_Rate_Limiter {
    private static $instance = null;
    private $wpdb;
    private $table_name;
    private $max_attempts = 5; // Maximum de tentatives par IP
    private $time_window = 3600; // Fenêtre de temps en secondes (1 heure)

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'gastro_starter_rate_limits';
        $this->create_table();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            attempt_count int(11) NOT NULL DEFAULT 1,
            last_attempt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address),
            KEY last_attempt (last_attempt)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function check_rate_limit($ip) {
        // Nettoyer les anciennes entrées
        $this->cleanup_old_entries();

        // Vérifier les tentatives existantes
        $attempts = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE ip_address = %s 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $ip,
            $this->time_window
        ));

        if ($attempts) {
            if ($attempts->attempt_count >= $this->max_attempts) {
                return false; // Rate limit atteint
            }

            // Incrémenter le compteur
            $this->wpdb->update(
                $this->table_name,
                array('attempt_count' => $attempts->attempt_count + 1),
                array('id' => $attempts->id)
            );
        } else {
            // Nouvelle entrée
            $this->wpdb->insert(
                $this->table_name,
                array(
                    'ip_address' => $ip,
                    'attempt_count' => 1
                )
            );
        }

        return true;
    }

    private function cleanup_old_entries() {
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE last_attempt < DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $this->time_window
        ));
    }
}

/**
 * Gestionnaire de sécurité
 */
class Gastro_Starter_Security {
    public static function get_client_ip() {
        $ip = '';
        
        // Vérifier les en-têtes de proxy
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',       // Nginx proxy
            'HTTP_CLIENT_IP',       // Client IP
            'HTTP_X_FORWARDED_FOR', // Forwarded for
            'REMOTE_ADDR'           // Fallback
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    // Si plusieurs IPs, prendre la première
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                break;
            }
        }

        // Valider l'IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0'; // IP par défaut si aucune IP valide n'est trouvée
    }

    public static function validate_phone_number($phone) {
        // Nettoyer le numéro en gardant seulement les chiffres et le +
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Vérifier qu'il y a au moins 8 chiffres (minimum pour un numéro valide)
        $digits_only = preg_replace('/[^0-9]/', '', $clean_phone);
        
        if (strlen($digits_only) < 8) {
            return false;
        }
        
        // Validation très simple : au moins 8 chiffres et peut commencer par + ou 0
        return (strpos($clean_phone, '+') === 0) || (strpos($clean_phone, '0') === 0) || (strlen($digits_only) >= 10);
    }

    public static function validate_email($email) {
        // Vérification plus stricte que la fonction PHP standard
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Vérifier la présence d'un MX record
        $domain = substr(strrchr($email, "@"), 1);
        return checkdnsrr($domain, 'MX');
    }

    public static function sanitize_phone($phone) {
        // Nettoyer et formater le numéro de téléphone
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Format français
        if (preg_match('/^(?:(?:\+|00)33|0)([1-9])(\d{2})(\d{2})(\d{2})(\d{2})$/', $phone, $matches)) {
            return "+33 " . $matches[1] . " " . $matches[2] . " " . $matches[3] . " " . $matches[4] . " " . $matches[5];
        }
        
        return $phone;
    }
}

class Gastro_Starter_File_Security {
    private static $allowed_mime_types = array(
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );

    private static $max_file_size = 5242880; // 5MB

    public static function validate_file($file) {
        // Vérifier si le fichier existe
        if (empty($file) || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('Fichier invalide ou manquant.');
        }

        // Vérifier la taille du fichier
        if ($file['size'] > self::$max_file_size) {
            throw new Exception('Le fichier est trop volumineux. Taille maximale : 5MB.');
        }

        // Vérifier l'extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($file_ext, self::$allowed_mime_types)) {
            throw new Exception('Type de fichier non autorisé.');
        }

        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, self::$allowed_mime_types)) {
            throw new Exception('Type de fichier non autorisé.');
        }

        // Vérifier que le type MIME correspond à l'extension
        if ($mime_type !== self::$allowed_mime_types[$file_ext]) {
            throw new Exception('Le type de fichier ne correspond pas à l\'extension.');
        }

        // Vérifier si le fichier est une image valide
        if (in_array($file_ext, array('jpg', 'jpeg', 'png'))) {
            if (!self::validate_image($file['tmp_name'])) {
                throw new Exception('Image invalide ou corrompue.');
            }
        }

        // Vérifier si le fichier est un PDF valide
        if ($file_ext === 'pdf') {
            if (!self::validate_pdf($file['tmp_name'])) {
                throw new Exception('PDF invalide ou corrompu.');
            }
        }

        return true;
    }

    private static function validate_image($file_path) {
        // Vérifier si c'est une image valide
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            return false;
        }

        // Vérifier les dimensions maximales
        if ($image_info[0] > 4096 || $image_info[1] > 4096) {
            return false;
        }

        return true;
    }

    private static function validate_pdf($file_path) {
        // Vérifier la signature PDF
        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === '%PDF';
    }

    public static function sanitize_filename($filename) {
        // Retirer les caractères spéciaux
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Éviter les noms de fichiers dangereux
        $filename = str_replace(array('..', '.php', '.asp', '.js'), '', $filename);
        
        // Limiter la longueur
        $filename = substr($filename, 0, 255);
        
        return $filename;
    }
}

class Gastro_Starter_Error_Messages {
    private static $allowed_errors = array(
        'security_error' => 'La vérification de sécurité a échoué.',
        'spam_error' => 'Erreur anti-spam.',
        'rate_limit' => 'Trop de tentatives. Veuillez réessayer dans une heure.',
        'invalid_phone' => 'Le numéro de téléphone est invalide.',
        'invalid_email' => 'L\'adresse email est invalide.',
        'invalid_name' => 'Le nom est trop court.',
        'date_format_error' => 'Le format de la date est incorrect. Veuillez utiliser JJ/MM/AAAA.',
        'past_date' => 'La date de réservation ne peut pas être dans le passé.',
        'availability_error' => 'Désolé, ce créneau n\'est plus disponible ou ne respecte pas nos conditions de réservation.',
        'db_error' => 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.',
        'invalid_file' => 'Le fichier téléchargé est invalide.',
        'file_size_error' => 'Le fichier est trop volumineux.',
        'file_type_error' => 'Type de fichier non autorisé.',
        'upload_error' => 'Erreur lors du téléchargement du fichier.'
    );

    public static function get_error_message($error_code) {
        if (isset(self::$allowed_errors[$error_code])) {
            return self::$allowed_errors[$error_code];
        }
        return 'Une erreur est survenue. Veuillez réessayer.';
    }

    public static function display_error($error_code) {
        $message = self::get_error_message($error_code);
        return sprintf(
            '<div class="error-message" role="alert">%s</div>',
            esc_html($message)
        );
    }

    public static function log_error($error_code, $additional_info = '') {
        $message = self::get_error_message($error_code);
        $log_message = sprintf(
            '[%s] Error %s: %s %s',
            current_time('mysql'),
            $error_code,
            $message,
            $additional_info ? '- ' . $additional_info : ''
        );
        error_log($log_message);
    }
}

// Fonction utilitaire pour accéder aux utilitaires
if (!function_exists('gastro_starter_get_utils')) {
function gastro_starter_get_utils() {
    return Gastro_Starter_Utils::get_instance();
    }
} 