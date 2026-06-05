<?php
/**
 * Gestionnaire de réservations pour Mon Restaurant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GASTRO_STARTER_MAX_PEOPLE_PER_SLOT')) {
    define('GASTRO_STARTER_MAX_PEOPLE_PER_SLOT', get_option('gastro_starter_restaurant_capacity', 4));
}

if (!class_exists('Gastro_Starter_Reservation_Manager')) {
    class Gastro_Starter_Reservation_Manager {
        private static $instance = null;
        private $wpdb;
        private $table_name;
        public $last_error = '';

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
            $this->table_name = $wpdb->prefix . 'reservations';
        }

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function check_availability($date, $time, $people, $source = 'public') {
            error_log("=== VÉRIFICATION DISPONIBILITÉ (Serveur) ===");
            error_log("Date: $date, Heure: $time, Personnes: $people, Source: $source");
            
            // --- 1. VÉRIFICATION DES RÈGLES DE BASE ---
            
            // Vérifier si le jour est un jour de fermeture hebdomadaire (fiabilisé)
            $day_of_week_index = date('w', strtotime($date)); // 0 (dimanche) à 6 (samedi)
            $days_map = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $day_of_week = $days_map[$day_of_week_index];
            
            $schedule = get_option('gastro_starter_daily_schedule', []);
            if (!isset($schedule[$day_of_week]) || !$schedule[$day_of_week]['open']) {
                error_log("ERREUR DISPO: Le restaurant est fermé le $day_of_week.");
                return false;
            }

            // Vérifier si la date est dans les vacances
            $holidays = get_option('gastro_starter_holiday_dates', '');
            $holiday_dates = !empty($holidays) ? explode(',', $holidays) : [];
            if (in_array($date, $holiday_dates)) {
                error_log("ERREUR DISPO: La date $date est une date de fermeture (vacances).");
                return false;
            }

            // Vérifier le délai de 1 heure SEULEMENT pour le public
            if ($source !== 'admin') {
                try {
                    $reservation_datetime = new DateTime("$date $time", new DateTimeZone('Europe/Paris'));
                    $min_booking_time = new DateTime('now', new DateTimeZone('Europe/Paris'));
                    $min_booking_time->modify('+1 hour');

                    if ($reservation_datetime < $min_booking_time) {
                        error_log("ERREUR DISPO: La réservation est dans moins de 1 heure (source: $source).");
                        return false;
                    }
                } catch (Exception $e) { 
                    error_log("ERREUR PARSING DATE: " . $e->getMessage());
                    return false;
                }
            }


            // --- 2. VÉRIFICATION DE LA CAPACITÉ ---

            // Vérifier que les paramètres sont valides
            if (empty($date) || empty($time) || empty($people)) {
                error_log("ERREUR: Paramètres invalides pour la vérification de capacité");
                return false;
            }
            
            $total_people = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(people) FROM {$this->table_name} 
                WHERE reservation_date = %s 
                AND reservation_time = %s 
                AND status != 'cancelled'",
                $date,
                $time
            ));
            
            // Si aucune réservation, total_people est null, on le met à 0
            $total_people = intval($total_people);
            
            // Utiliser la capacité par créneau configurée dans l'admin
            $capacity_per_slot = get_option('gastro_starter_restaurant_capacity', 4);
            $requested_people = intval($people);
            $available_spots = $capacity_per_slot - $total_people;
            $will_fit = $available_spots >= $requested_people;
            
            error_log("Détails de disponibilité:");
            error_log("- Capacité par créneau: $capacity_per_slot");
            error_log("- Personnes déjà réservées: $total_people");
            error_log("- Places disponibles: $available_spots");
            error_log("- Personnes demandées: $requested_people");
            error_log("- Peut accueillir: " . ($will_fit ? 'OUI' : 'NON'));
            
            // Log des réservations existantes pour ce créneau
            $existing_reservations = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, customer_name, people, status FROM {$this->table_name} 
                WHERE reservation_date = %s 
                AND reservation_time = %s 
                ORDER BY created_at",
                $date,
                $time
            ));
            
            if ($existing_reservations) {
                error_log("Réservations existantes pour ce créneau:");
                foreach ($existing_reservations as $res) {
                    error_log("  - ID: {$res->id}, Client: {$res->customer_name}, Personnes: {$res->people}, Statut: {$res->status}");
                }
            } else {
                error_log("Aucune réservation existante pour ce créneau");
            }
            
            error_log("=== FIN VÉRIFICATION DISPONIBILITÉ ===");
            
            return $will_fit;
        }

        public function create_reservation($data) {
            $this->last_error = ''; // Réinitialiser l'erreur
            $default_data = array(
                'status' => 'pending',
                'confirmation_email_sent' => 0,
                'reminder_sent' => 0,
                'accept_reminder' => 0,
                'newsletter' => 0,
                'consent_data_processing' => 0,
                'consent_data_storage' => 0
            );

            $data = wp_parse_args($data, $default_data);
            
            // Journaliser les données pour le débogage
            error_log('Tentative d\'insertion de réservation avec les données: ' . print_r($data, true));

            $result = $this->wpdb->insert($this->table_name, $data);

            if ($result === false) {
                $this->last_error = $this->wpdb->last_error;
                error_log('Erreur d\'insertion BDD: ' . $this->last_error);
                return false;
            }

            return $this->wpdb->insert_id;
        }

        public function get_reservation($id) {
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ));
        }

        public function update_reservation($id, $data) {
            return $this->wpdb->update(
                $this->table_name,
                $data,
                array('id' => $id)
            );
        }

        public function send_confirmation_email($reservation_id) {
            error_log("=== DÉBUT ENVOI EMAIL DE CONFIRMATION (ID: $reservation_id) ===");
            
            $reservation = $this->get_reservation($reservation_id);
            if (!$reservation) {
                error_log("ERREUR: Réservation introuvable.");
                return false;
            }
            
            // NOUVEAU : Vérifier si un email a déjà été envoyé
            if ($reservation->confirmation_email_sent == 1) {
                error_log("INFO: Email de confirmation déjà envoyé pour la réservation ID $reservation_id. Annulation de l'envoi.");
                return true; // On considère l'opération comme un succès pour ne pas bloquer le flux
            }
            
            error_log("Réservation trouvée - Client: {$reservation->customer_name}, Email: {$reservation->customer_email}");
            
            if (!function_exists('gastro_starter_get_email_manager')) {
                error_log("ERREUR: La fonction gastro_starter_get_email_manager n'existe pas.");
                return false;
            }
            
            $email_manager = gastro_starter_get_email_manager();
            if (!$email_manager) {
                error_log("ERREUR: Impossible d'obtenir l'instance du gestionnaire d'emails.");
                return false;
            }
            
            error_log("Gestionnaire d'emails obtenu, tentative d'envoi...");
            
            try {
                $sent = $email_manager->send_reservation_confirmation($reservation);
                error_log("Résultat de l'envoi: " . ($sent ? 'SUCCÈS' : 'ÉCHEC'));
                
                if ($sent) {
                    $update_result = $this->update_reservation($reservation_id, array('confirmation_email_sent' => 1));
                    if (!$update_result) {
                        error_log("ALERTE: Échec de la mise à jour du statut 'confirmation_email_sent' pour la réservation ID $reservation_id.");
                    }
                } else {
                    error_log("ATTENTION: L'envoi de l'email a échoué. Consulter les logs pour plus de détails.");
                }
                
                error_log("=== FIN ENVOI EMAIL DE CONFIRMATION (ID: $reservation_id) ===");
                return $sent;
                
            } catch (Exception $e) {
                error_log("EXCEPTION lors de l'envoi: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                error_log("=== FIN ENVOI EMAIL DE CONFIRMATION (ID: $reservation_id, AVEC ERREUR) ===");
                return false;
            }
        }

        public function send_reminder_email($reservation_id) {
            $reservation = $this->get_reservation($reservation_id);
            if (!$reservation || !$reservation->accept_reminder) {
                return false;
            }

            $email_manager = gastro_starter_get_email_manager();
            $sent = $email_manager->send_reminder_email($reservation);

            if ($sent) {
                $this->update_reservation($reservation_id, array('reminder_sent' => 1));
            }

            return $sent;
        }
    }
}

// Initialiser le gestionnaire de réservations
if (!function_exists('gastro_starter_get_reservation_manager')) {
    function gastro_starter_get_reservation_manager() {
        return Gastro_Starter_Reservation_Manager::get_instance();
    }
}

/**
 * Fonction wrapper pour vérifier la disponibilité
 * 
 * @param string $date Date de réservation (Y-m-d)
 * @param string $time Heure de réservation (H:i)
 * @param int $people Nombre de personnes
 * @return bool True si disponible, false sinon
 */
if (!function_exists('gastro_starter_check_availability')) {
    function gastro_starter_check_availability($date, $time, $people) {
        $reservation_manager = gastro_starter_get_reservation_manager();
        return $reservation_manager->check_availability($date, $time, $people);
    }
} 