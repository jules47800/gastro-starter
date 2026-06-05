<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Calendar_Integration {
    
    public static function generate_calendar_links($reservation_id) {
        $reservation_manager = gastro_starter_get_reservation_manager();
        $reservation = $reservation_manager->get_reservation($reservation_id);
        
        if (!$reservation) {
            return false;
        }
        
        $restaurant_name = get_bloginfo('name');
        $restaurant_address = get_theme_mod('gastro_starter_restaurant_address', '');
        $restaurant_phone = get_theme_mod('gastro_starter_restaurant_phone', '');
        
        $event_title = sprintf(__('Réservation au %s', 'gastro-starter'), $restaurant_name);
        $event_description = sprintf(
            __('Réservation pour %d personne(s)\nClient: %s\nTéléphone: %s\nEmail: %s', 'gastro-starter'),
            $reservation->people,
            $reservation->customer_name,
            $reservation->customer_phone,
            $reservation->customer_email
        );
        
        if (!empty($reservation->notes)) {
            $event_description .= "\n\n" . __('Notes:', 'gastro-starter') . "\n" . $reservation->notes;
        }
        
        $event_description .= "\n\n" . __('Restaurant:', 'gastro-starter') . "\n" . $restaurant_name;
        if ($restaurant_address) {
            $event_description .= "\n" . $restaurant_address;
        }
        if ($restaurant_phone) {
            $event_description .= "\n" . $restaurant_phone;
        }
        
        $start_datetime = $reservation->reservation_date . ' ' . $reservation->reservation_time;
        $start_timestamp = strtotime($start_datetime);
        $end_timestamp = $start_timestamp + (2 * 3600);
        
        $start_date_formatted = date('Ymd\THis\Z', $start_timestamp);
        $end_date_formatted = date('Ymd\THis\Z', $end_timestamp);
        
        $event_details = array(
            'title' => $event_title,
            'description' => $event_description,
            'location' => $restaurant_address,
            'start_date' => $start_date_formatted,
            'end_date' => $end_date_formatted,
            'start_timestamp' => $start_timestamp,
            'end_timestamp' => $end_timestamp
        );
        
        return array(
            'google' => self::generate_google_calendar_link($event_details),
            'outlook' => self::generate_outlook_calendar_link($event_details),
            'yahoo' => self::generate_yahoo_calendar_link($event_details),
            'apple' => self::generate_apple_calendar_link($event_details),
            'ics' => self::generate_ics_file($event_details)
        );
    }
    
    private static function generate_google_calendar_link($event_details) {
        $params = array(
            'action' => 'TEMPLATE',
            'text' => $event_details['title'],
            'dates' => $event_details['start_date'] . '/' . $event_details['end_date'],
            'details' => $event_details['description'],
            'location' => $event_details['location']
        );
        
        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
    
    private static function generate_outlook_calendar_link($event_details) {
        $params = array(
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $event_details['title'],
            'startdt' => date('c', $event_details['start_timestamp']),
            'enddt' => date('c', $event_details['end_timestamp']),
            'body' => $event_details['description'],
            'location' => $event_details['location']
        );
        
        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }
    
    private static function generate_yahoo_calendar_link($event_details) {
        $params = array(
            'v' => '60',
            'title' => $event_details['title'],
            'st' => date('Ymd\THis\Z', $event_details['start_timestamp']),
            'et' => date('Ymd\THis\Z', $event_details['end_timestamp']),
            'desc' => $event_details['description'],
            'in_loc' => $event_details['location']
        );
        
        return 'https://calendar.yahoo.com/?' . http_build_query($params);
    }
    
    private static function generate_apple_calendar_link($event_details) {
        return self::generate_ics_file($event_details, true);
    }
    
    private static function generate_ics_file($event_details, $data_uri = false) {
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//Mon Restaurant//Réservation//FR\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:" . uniqid() . "@gastro-starter.local\r\n";
        $ics_content .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
        $ics_content .= "DTSTART:" . $event_details['start_date'] . "\r\n";
        $ics_content .= "DTEND:" . $event_details['end_date'] . "\r\n";
        $ics_content .= "SUMMARY:" . $event_details['title'] . "\r\n";
        $ics_content .= "DESCRIPTION:" . str_replace(array("\r\n", "\n"), "\\n", $event_details['description']) . "\r\n";
        $ics_content .= "LOCATION:" . $event_details['location'] . "\r\n";
        $ics_content .= "STATUS:CONFIRMED\r\n";
        $ics_content .= "SEQUENCE:0\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";
        
        if ($data_uri) {
            return 'data:text/calendar;charset=utf8,' . urlencode($ics_content);
        }
        
        return $ics_content;
    }
    
    public static function render_calendar_button($reservation_id) {
        $calendar_links = self::generate_calendar_links($reservation_id);
        
        if (!$calendar_links) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="calendar-integration">
            <h3><?php _e('Ajouter à votre calendrier', 'gastro-starter'); ?></h3>
            <div class="calendar-buttons">
                <a href="<?php echo esc_url($calendar_links['google']); ?>" target="_blank" class="calendar-btn google-calendar" title="<?php _e('Ajouter à Google Calendar', 'gastro-starter'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                    <span><?php _e('Google', 'gastro-starter'); ?></span>
                </a>
                
                <a href="<?php echo esc_url($calendar_links['outlook']); ?>" target="_blank" class="calendar-btn outlook-calendar" title="<?php _e('Ajouter à Outlook', 'gastro-starter'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm0 2v16h10V4H7zm1 2h8v2H8V6zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/>
                    </svg>
                    <span><?php _e('Outlook', 'gastro-starter'); ?></span>
                </a>
                
                <a href="<?php echo esc_url($calendar_links['yahoo']); ?>" target="_blank" class="calendar-btn yahoo-calendar" title="<?php _e('Ajouter à Yahoo Calendar', 'gastro-starter'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span><?php _e('Yahoo', 'gastro-starter'); ?></span>
                </a>
                
                <a href="<?php echo esc_url($calendar_links['apple']); ?>" class="calendar-btn apple-calendar" title="<?php _e('Ajouter à Apple Calendar', 'gastro-starter'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                    </svg>
                    <span><?php _e('Apple', 'gastro-starter'); ?></span>
                </a>
            </div>
            <p class="calendar-note">
                <?php _e('Cliquez sur le calendrier de votre choix pour ajouter cette réservation à votre agenda.', 'gastro-starter'); ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
