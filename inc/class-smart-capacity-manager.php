<?php
/**
 * Smart Capacity Manager
 * 
 * Handles intelligent overbooking and capacity optimization for the reservation system.
 * 
 * @package Gastro_Starter
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Smart_Capacity_Manager {
    
    /**
     * Database instance
     */
    private $wpdb;
    
    /**
     * Reservation manager instance
     */
    private $reservation_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->reservation_manager = new Gastro_Starter_Reservation_Manager();
    }
    
    /**
     * Check if overbooking is enabled globally
     * 
     * @return bool
     */
    public function is_overbooking_enabled() {
        return (bool) get_option('gastro_starter_overbooking_enabled', false);
    }
    
    /**
     * Get maximum overbooking allowed for a specific group size
     * 
     * @param int $people Number of people in the group
     * @return int Maximum overbooking allowed
     */
    public function get_max_overbooking_for_group($people) {
        // Get configuration for different group sizes
        $config = get_option('gastro_starter_overbooking_by_group_size', [
            '1-2' => 1,
            '3-4' => 2,
            '5+' => 0
        ]);
        
        if ($people <= 2) {
            return isset($config['1-2']) ? (int) $config['1-2'] : 1;
        } elseif ($people <= 4) {
            return isset($config['3-4']) ? (int) $config['3-4'] : 2;
        } else {
            return isset($config['5+']) ? (int) $config['5+'] : 0;
        }
    }
    
    /**
     * Get maximum overbooking allowed per slot
     * 
     * @return int
     */
    public function get_max_overbooking_per_slot() {
        return (int) get_option('gastro_starter_max_overbooking_per_slot', 2);
    }
    
    /**
     * Check if overbooking is allowed for a specific group size
     * 
     * @param int $people Number of people in the group
     * @return bool
     */
    public function is_overbooking_allowed_for_group($people) {
        $enabled_config = get_option('gastro_starter_overbooking_enabled_by_group', [
            '1-2' => true,
            '3-4' => true,
            '5+' => false
        ]);
        
        if ($people <= 2) {
            return isset($enabled_config['1-2']) ? (bool) $enabled_config['1-2'] : true;
        } elseif ($people <= 4) {
            return isset($enabled_config['3-4']) ? (bool) $enabled_config['3-4'] : true;
        } else {
            return isset($enabled_config['5+']) ? (bool) $enabled_config['5+'] : false;
        }
    }
    
    /**
     * Get list of time slots where overbooking is blocked
     * 
     * @return array Array of time slots (e.g., ['12:00', '12:15', '13:00'])
     */
    public function get_blocked_overbooking_slots() {
        $blocked = get_option('gastro_starter_overbooking_blocked_slots', '');
        if (empty($blocked)) {
            return [];
        }
        
        // Support both array and comma-separated string
        if (is_array($blocked)) {
            return $blocked;
        }
        
        return array_map('trim', explode(',', $blocked));
    }
    
    /**
     * Check if a time slot is blocked for overbooking
     * 
     * @param string $time Time slot (e.g., '12:00')
     * @return bool
     */
    public function is_slot_blocked_for_overbooking($time) {
        $blocked_slots = $this->get_blocked_overbooking_slots();
        return in_array($time, $blocked_slots);
    }
    
    /**
     * Get total people reserved for a specific date and time
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $time Time (H:i format)
     * @return int
     */
    private function get_total_people($date, $time) {
        $table_name = $this->wpdb->prefix . 'reservations';
        
        $total = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(people) FROM {$table_name} 
            WHERE reservation_date = %s 
            AND reservation_time = %s 
            AND status != 'cancelled'",
            $date,
            $time
        ));
        
        return $total ? (int) $total : 0;
    }
    
    /**
     * Smart availability check with overbooking support
     * 
     * @param string $date Date (Y-m-d format)
     * @param string $time Time (H:i format)
     * @param int $people Number of people
     * @param string $source Source of request ('public' or 'admin')
     * @return array Availability information
     */
    public function check_availability_smart($date, $time, $people, $source = 'public') {
        $total_people = $this->get_total_people($date, $time);
        $capacity = (int) get_option('gastro_starter_restaurant_capacity', 6);
        $available_spots = $capacity - $total_people;
        
        // 1. If normal capacity is sufficient
        if ($available_spots >= $people) {
            return [
                'available' => true,
                'type' => 'normal',
                'slot' => $time,
                'capacity_left' => $available_spots - $people,
                'total_after' => $total_people + $people,
                'capacity' => $capacity
            ];
        }
        
        // 2. If not enough spots, check if overbooking is possible
        if (!$this->is_overbooking_enabled() || $source !== 'public') {
            return [
                'available' => false,
                'reason' => 'capacity_full',
                'capacity_left' => $available_spots
            ];
        }
        
        // 3. Check if overbooking is allowed for this group size
        if (!$this->is_overbooking_allowed_for_group($people)) {
            return [
                'available' => false,
                'reason' => 'group_too_large_for_overbooking',
                'capacity_left' => $available_spots
            ];
        }
        
        // 4. Check overbooking limits
        $max_overbooking_for_group = $this->get_max_overbooking_for_group($people);
        $max_overbooking_per_slot = $this->get_max_overbooking_per_slot();
        $total_with_group = $total_people + $people;
        $overbooking_amount = $total_with_group - $capacity;
        
        // Use the more restrictive limit
        $max_allowed = min($max_overbooking_for_group, $max_overbooking_per_slot);
        
        if ($overbooking_amount > $max_allowed) {
            return [
                'available' => false,
                'reason' => 'overbooking_limit_exceeded',
                'overbooking_amount' => $overbooking_amount,
                'max_allowed' => $max_allowed,
                'capacity_left' => $available_spots
            ];
        }
        
        // 5. Check if this slot is blocked for overbooking
        if ($this->is_slot_blocked_for_overbooking($time)) {
            return [
                'available' => false,
                'reason' => 'slot_blocked_for_overbooking',
                'capacity_left' => $available_spots
            ];
        }
        
        // 6. Allow with overbooking
        return [
            'available' => true,
            'type' => 'overbooking',
            'slot' => $time,
            'overbooking_amount' => $overbooking_amount,
            'capacity_exceeded' => true,
            'total_after' => $total_with_group,
            'capacity' => $capacity,
            'requires_confirmation' => true
        ];
    }
    
    /**
     * Find the best slot for a group based on placement strategy
     * 
     * @param string $date Date (Y-m-d format)
     * @param int $people Number of people
     * @return array|false Slot information or false if no slot available
     */
    public function find_best_slot_for_group($date, $people) {
        $all_slots = gastro_starter_get_available_slots_for_date($date);
        $capacity = (int) get_option('gastro_starter_restaurant_capacity', 6);
        $strategy = get_option('gastro_starter_placement_strategy', 'balance');
        
        $slot_analysis = [];
        
        // Analyze all slots
        foreach ($all_slots as $slot) {
            $total_people = $this->get_total_people($date, $slot['time']);
            $available = $capacity - $total_people;
            
            $slot_analysis[] = [
                'time' => $slot['time'],
                'occupied' => $total_people,
                'available' => $available,
                'can_fit_normal' => ($available >= $people),
                'fill_percentage' => $total_people > 0 ? ($total_people / $capacity) * 100 : 0,
                'after_booking' => $total_people + $people
            ];
        }
        
        // Sort according to strategy
        if ($strategy === 'fill_empty') {
            // Fill empty slots first
            usort($slot_analysis, function($a, $b) {
                return $a['occupied'] <=> $b['occupied'];
            });
        } elseif ($strategy === 'balance') {
            // Balance: prefer medium-filled slots
            usort($slot_analysis, function($a, $b) use ($capacity) {
                $target = $capacity / 2;
                $diff_a = abs($a['occupied'] - $target);
                $diff_b = abs($b['occupied'] - $target);
                return $diff_a <=> $diff_b;
            });
        } elseif ($strategy === 'concentrate') {
            // Concentrate: fill the fullest slots first
            usort($slot_analysis, function($a, $b) {
                return $b['occupied'] <=> $a['occupied'];
            });
        }
        
        // Find first available slot (with or without overbooking)
        foreach ($slot_analysis as $slot) {
            $check = $this->check_availability_smart($date, $slot['time'], $people, 'public');
            
            if ($check['available']) {
                return [
                    'success' => true,
                    'slot' => $slot,
                    'booking_type' => $check['type'],
                    'availability_info' => $check
                ];
            }
        }
        
        return [
            'success' => false,
            'reason' => 'no_slots_available'
        ];
    }
    
    /**
     * Get capacity statistics for a specific date
     * 
     * @param string $date Date (Y-m-d format)
     * @return array Statistics
     */
    public function get_capacity_stats($date) {
        $all_slots = gastro_starter_get_available_slots_for_date($date);
        $capacity = (int) get_option('gastro_starter_restaurant_capacity', 6);
        
        $stats = [
            'total_capacity' => 0,
            'total_reserved' => 0,
            'total_available' => 0,
            'overbooking_count' => 0,
            'overbooking_total' => 0,
            'slots' => []
        ];
        
        foreach ($all_slots as $slot) {
            $total_people = $this->get_total_people($date, $slot['time']);
            $is_overbooked = $total_people > $capacity;
            $overbooking_amount = $is_overbooked ? $total_people - $capacity : 0;
            
            $stats['total_capacity'] += $capacity;
            $stats['total_reserved'] += $total_people;
            $stats['total_available'] += max(0, $capacity - $total_people);
            
            if ($is_overbooked) {
                $stats['overbooking_count']++;
                $stats['overbooking_total'] += $overbooking_amount;
            }
            
            $stats['slots'][] = [
                'time' => $slot['time'],
                'capacity' => $capacity,
                'reserved' => $total_people,
                'available' => $capacity - $total_people,
                'fill_percentage' => ($total_people / $capacity) * 100,
                'is_overbooked' => $is_overbooked,
                'overbooking_amount' => $overbooking_amount,
                'status' => $this->get_slot_status($total_people, $capacity)
            ];
        }
        
        $stats['fill_percentage'] = $stats['total_capacity'] > 0 
            ? ($stats['total_reserved'] / $stats['total_capacity']) * 100 
            : 0;
        
        return $stats;
    }
    
    /**
     * Get status for a slot based on occupancy
     * 
     * @param int $reserved Number of people reserved
     * @param int $capacity Capacity of the slot
     * @return string Status ('normal', 'attention', 'overbooking')
     */
    private function get_slot_status($reserved, $capacity) {
        if ($reserved > $capacity) {
            return 'overbooking';
        } elseif ($reserved >= $capacity * 0.9) {
            return 'attention';
        } else {
            return 'normal';
        }
    }
    
    /**
     * Send overbooking alert email to admin
     * 
     * @param string $date Date
     * @param string $time Time
     * @param int $total_people Total people after booking
     * @param int $capacity Capacity
     */
    public function send_overbooking_alert($date, $time, $total_people, $capacity) {
        if (!get_option('gastro_starter_overbooking_alerts_enabled', true)) {
            return;
        }
        
        $admin_email = get_option('gastro_starter_overbooking_alert_email', get_option('admin_email'));
        $overbooking_amount = $total_people - $capacity;
        
        $subject = sprintf(
            '[%s] Alerte Overbooking - %s à %s',
            get_bloginfo('name'),
            date('d/m/Y', strtotime($date)),
            $time
        );
        
        $message = sprintf(
            "Une réservation en overbooking vient d'être effectuée.\n\n" .
            "Date: %s\n" .
            "Heure: %s\n" .
            "Capacité standard: %d personnes\n" .
            "Total réservé: %d personnes\n" .
            "Overbooking: +%d personnes\n\n" .
            "Merci de vérifier cette réservation dans le panneau d'administration.\n\n" .
            "Lien: %s",
            date('d/m/Y', strtotime($date)),
            $time,
            $capacity,
            $total_people,
            $overbooking_amount,
            admin_url('admin.php?page=gastro-starter-reservations')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}
