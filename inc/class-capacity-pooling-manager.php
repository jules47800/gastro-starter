<?php
/**
 * Gestionnaire de Pooling de Capacité Multi-Créneaux
 * 
 * Permet de mutualiser les places disponibles de plusieurs créneaux
 * pour accueillir des groupes plus grands
 * 
 * @package Gastro_Starter
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Capacity_Pooling_Manager {
    
    /**
     * Trouver le pool de capacité disponible pour un groupe
     * 
     * @param string $date Date au format Y-m-d
     * @param int $people Nombre de personnes
     * @return array|false Informations sur le pool ou false si impossible
     */
    public function find_available_capacity_pool($date, $people) {
        $capacity_per_slot = get_option('gastro_starter_restaurant_capacity', 6);
        $all_slots = gastro_starter_get_available_slots_for_date($date);
        $pooling_enabled = get_option('gastro_starter_pooling_enabled', true);
        
        if (!$pooling_enabled) {
            return false;
        }
        
        $slots_with_availability = [];
        $total_available = 0;
        
        // 1. Calculer places disponibles par créneau
        foreach ($all_slots as $slot) {
            $occupied = $this->get_people_count_for_slot($date, $slot['time']);
            $available = $capacity_per_slot - $occupied;
            
            if ($available > 0) {
                $slots_with_availability[] = [
                    'time' => $slot['time'],
                    'occupied' => $occupied,
                    'available' => $available,
                    'fill_percent' => ($occupied / $capacity_per_slot) * 100
                ];
                $total_available += $available;
            }
        }
        
        // 2. Vérifier si capacité poolée suffisante
        if ($total_available < $people) {
            return false;
        }
        
        // 3. Trier selon la stratégie
        $strategy = get_option('gastro_starter_pooling_strategy', 'least_filled');
        
        if ($strategy === 'least_filled') {
            // Moins rempli en premier
            usort($slots_with_availability, function($a, $b) {
                return $a['fill_percent'] <=> $b['fill_percent'];
            });
        } elseif ($strategy === 'earliest') {
            // Plus tôt en premier
            usort($slots_with_availability, function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });
        } elseif ($strategy === 'latest') {
            // Plus tard en premier
            usort($slots_with_availability, function($a, $b) {
                return strcmp($b['time'], $a['time']);
            });
        }
        
        // 4. Déterminer quels créneaux utiliser
        $max_slots_to_pool = get_option('gastro_starter_max_pooling_slots', 5);
        $slots_to_use = [];
        $remaining_people = $people;
        
        foreach ($slots_with_availability as $slot) {
            if ($remaining_people <= 0) break;
            if (count($slots_to_use) >= $max_slots_to_pool) break;
            
            $to_take = min($slot['available'], $remaining_people);
            $slots_to_use[] = [
                'time' => $slot['time'],
                'people_from_this_slot' => $to_take,
                'occupied_before' => $slot['occupied'],
                'occupied_after' => $slot['occupied'] + $to_take,
                'fill_percent_before' => $slot['fill_percent'],
                'fill_percent_after' => (($slot['occupied'] + $to_take) / $capacity_per_slot) * 100
            ];
            $remaining_people -= $to_take;
        }
        
        if ($remaining_people > 0) {
            return false; // Pas réussi à placer tout le monde
        }
        
        // 5. Créneau principal = le premier de la liste triée
        $primary_slot = $slots_to_use[0]['time'];
        
        return [
            'can_accommodate' => true,
            'primary_slot' => $primary_slot,
            'slots_used' => $slots_to_use,
            'total_people' => $people,
            'pooling_required' => count($slots_to_use) > 1,
            'strategy_used' => $strategy
        ];
    }
    
    /**
     * Obtenir le nombre de personnes réservées pour un créneau
     * 
     * @param string $date Date Y-m-d
     * @param string $time Heure H:i
     * @return int Nombre de personnes
     */
    private function get_people_count_for_slot($date, $time) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(people), 0)
            FROM {$table_name}
            WHERE reservation_date = %s 
            AND reservation_time = %s
            AND status NOT IN ('cancelled', 'no_show')
        ", $date, $time));
        
        return (int) $result;
    }
    
    /**
     * Créer une réservation avec pooling de capacité
     * 
     * @param string $date Date Y-m-d
     * @param int $people Nombre de personnes
     * @param array $customer_data Données client
     * @param string $placement_choice 'auto' ou un horaire spécifique
     * @param string $status Status de la réservation ('pending' ou 'confirmed')
     * @return array Résultat de la création
     */
    public function create_pooled_reservation($date, $people, $customer_data, $placement_choice = 'auto', $status = 'confirmed') {
        global $wpdb;
        
        // 1. Trouver le pool de capacité
        $pool = $this->find_available_capacity_pool($date, $people);
        
        if (!$pool || !$pool['can_accommodate']) {
            return [
                'success' => false,
                'message' => 'Capacité insuffisante même avec pooling'
            ];
        }
        
        // 2. Déterminer le créneau principal
        if ($placement_choice === 'auto') {
            $primary_slot = $pool['primary_slot'];
        } else {
            // Vérifier que le choix manuel est dans les slots disponibles
            $valid_choice = false;
            foreach ($pool['slots_used'] as $slot) {
                if ($slot['time'] === $placement_choice) {
                    $valid_choice = true;
                    $primary_slot = $placement_choice;
                    break;
                }
            }
            if (!$valid_choice) {
                $primary_slot = $pool['primary_slot'];
            }
        }
        
        $table_name = $wpdb->prefix . 'reservations';
        
        // 3. Créer la réservation principale
        $wpdb->insert($table_name, [
            'reservation_date' => $date,
            'reservation_time' => $primary_slot,
            'people' => $people,
            'customer_name' => sanitize_text_field($customer_data['name']),
            'customer_email' => sanitize_email($customer_data['email']),
            'customer_phone' => sanitize_text_field($customer_data['phone']),
            'is_pooled' => 1,
            'pooling_data' => json_encode($pool['slots_used']),
            'status' => $status, // Utiliser le status fourni
            'notes' => isset($customer_data['notes']) ? sanitize_textarea_field($customer_data['notes']) : '',
            'created_at' => current_time('mysql')
        ]);
        
        $reservation_id = $wpdb->insert_id;
        
        if (!$reservation_id) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la réservation'
            ];
        }
        
        // 4. Créer les "réservations fantômes" pour les autres créneaux
        foreach ($pool['slots_used'] as $slot_info) {
            if ($slot_info['time'] === $primary_slot) {
                continue; // Ne pas créer de fantôme pour le créneau principal
            }
            
            $wpdb->insert($table_name, [
                'reservation_date' => $date,
                'reservation_time' => $slot_info['time'],
                'people' => $slot_info['people_from_this_slot'],
                'customer_name' => sanitize_text_field($customer_data['name']),
                'customer_email' => sanitize_email($customer_data['email']),
                'customer_phone' => sanitize_text_field($customer_data['phone']),
                'status' => 'phantom',
                'parent_reservation_id' => $reservation_id,
                'created_at' => current_time('mysql')
            ]);
        }
        
        // 5. Envoyer email de confirmation
        $this->send_pooling_confirmation_email($reservation_id, $customer_data, $pool);
        
        return [
            'success' => true,
            'reservation_id' => $reservation_id,
            'primary_slot' => $primary_slot,
            'slots_used' => $pool['slots_used'],
            'pooling_required' => $pool['pooling_required']
        ];
    }
    
    /**
     * Vérifier si un créneau simple peut accueillir le groupe
     * (sans pooling)
     * 
     * @param string $date Date Y-m-d
     * @param string $time Heure H:i
     * @param int $people Nombre de personnes
     * @return bool
     */
    public function can_accommodate_single_slot($date, $time, $people) {
        $capacity = get_option('gastro_starter_restaurant_capacity', 6);
        $occupied = $this->get_people_count_for_slot($date, $time);
        $available = $capacity - $occupied;
        
        return $available >= $people;
    }
    
    /**
     * Obtenir les détails d'une réservation poolée
     * 
     * @param int $reservation_id ID de la réservation
     * @return array|null Détails ou null
     */
    public function get_pooled_reservation_details($reservation_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
        
        $reservation = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table_name} WHERE id = %d
        ", $reservation_id), ARRAY_A);
        
        if (!$reservation || !$reservation['is_pooled']) {
            return null;
        }
        
        $pooling_data = json_decode($reservation['pooling_data'], true);
        
        // Récupérer les réservations fantômes
        $phantoms = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name} 
            WHERE parent_reservation_id = %d
            AND status = 'phantom'
        ", $reservation_id), ARRAY_A);
        
        return [
            'main_reservation' => $reservation,
            'pooling_data' => $pooling_data,
            'phantom_reservations' => $phantoms,
            'total_slots_used' => count($pooling_data)
        ];
    }
    
    /**
     * Annuler une réservation poolée (annule aussi les fantômes)
     * 
     * @param int $reservation_id ID de la réservation principale
     * @return bool
     */
    public function cancel_pooled_reservation($reservation_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
        
        // Annuler la réservation principale
        $wpdb->update(
            $table_name,
            ['status' => 'cancelled'],
            ['id' => $reservation_id]
        );
        
        // Annuler les fantômes
        $wpdb->update(
            $table_name,
            ['status' => 'cancelled'],
            ['parent_reservation_id' => $reservation_id, 'status' => 'phantom']
        );
        
        return true;
    }
    
    /**
     * Envoyer email de confirmation avec détails pooling
     * 
     * @param int $reservation_id ID réservation
     * @param array $customer_data Données client
     * @param array $pool Informations pooling
     */
    private function send_pooling_confirmation_email($reservation_id, $customer_data, $pool) {
        $to = $customer_data['email'];
        $subject = 'Confirmation de votre réservation - Mon Restaurant';
        
        $message = "Bonjour " . $customer_data['name'] . ",\n\n";
        $message .= "Votre réservation a bien été enregistrée !\n\n";
        $message .= "Détails de votre réservation :\n";
        $message .= "- Nombre de personnes : " . $pool['total_people'] . "\n";
        $message .= "- Créneau principal : " . $pool['primary_slot'] . "\n\n";
        
        if ($pool['pooling_required']) {
            $message .= "ℹ️ Votre groupe nécessite plusieurs créneaux :\n";
            foreach ($pool['slots_used'] as $slot) {
                $message .= "  • " . $slot['time'] . " : " . $slot['people_from_this_slot'] . " personnes\n";
            }
            $message .= "\nVous serez tous ensemble au restaurant !\n\n";
        }
        
        $message .= "À bientôt au restaurant Mon Restaurant !\n";
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Vérifier si le pooling est activé
     * 
     * @return bool
     */
    public function is_pooling_enabled() {
        return (bool) get_option('gastro_starter_pooling_enabled', true);
    }
    
    /**
     * Obtenir les statistiques de pooling pour une date
     * 
     * @param string $date Date Y-m-d
     * @return array Statistiques
     */
    public function get_pooling_statistics($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
        
        $pooled_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table_name}
            WHERE reservation_date = %s
            AND is_pooled = 1
            AND status NOT IN ('cancelled', 'no_show')
        ", $date));
        
        $total_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table_name}
            WHERE reservation_date = %s
            AND status NOT IN ('cancelled', 'no_show', 'phantom')
        ", $date));
        
        return [
            'total_reservations' => (int) $total_count,
            'pooled_reservations' => (int) $pooled_count,
            'pooling_percentage' => $total_count > 0 ? ($pooled_count / $total_count) * 100 : 0
        ];
    }
}
