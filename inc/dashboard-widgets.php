<?php
/**
 * Widgets du tableau de bord — Réservations
 * Design glassmorphism Mon Restaurant
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_add_dashboard_widgets() {
    wp_add_dashboard_widget(
        'gastro_starter_todays_reservations',
        __('Réservations du jour', 'gastro-starter'),
        'gastro_starter_todays_reservations_widget'
    );

    wp_add_dashboard_widget(
        'gastro_starter_upcoming_reservations',
        __('Prochaines réservations', 'gastro-starter'),
        'gastro_starter_upcoming_reservations_widget'
    );
}
add_action('wp_dashboard_setup', 'gastro_starter_add_dashboard_widgets');

/**
 * Widget — Réservations du jour (KPI + cartes)
 */
function gastro_starter_todays_reservations_widget() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $today = date('Y-m-d');

    $reservations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE reservation_date = %s
            AND status != 'cancelled'
            ORDER BY reservation_time ASC",
            $today
        )
    );

    $total = count($reservations);
    $couverts = 0;
    foreach ($reservations as $r) {
        $couverts += (int) $r->people;
    }

    // KPI
    echo '<div class="lm-dash-kpi-grid">';
    echo '<div class="lm-dash-kpi">';
    echo '<div class="lm-dash-kpi-value">' . esc_html($total) . '</div>';
    echo '<div class="lm-dash-kpi-label">' . esc_html__('Réservations', 'gastro-starter') . '</div>';
    echo '</div>';
    echo '<div class="lm-dash-kpi">';
    echo '<div class="lm-dash-kpi-value">' . esc_html($couverts) . '</div>';
    echo '<div class="lm-dash-kpi-label">' . esc_html__('Couverts', 'gastro-starter') . '</div>';
    echo '</div>';
    echo '</div>';

    if (empty($reservations)) {
        echo '<div class="lm-dash-empty">';
        echo '<div class="lm-dash-empty-icon">&#128197;</div>';
        echo '<p>' . esc_html__('Aucune réservation pour aujourd\'hui.', 'gastro-starter') . '</p>';
        echo '</div>';
        return;
    }

    // Cards
    echo '<div class="lm-dash-cards">';
    foreach ($reservations as $reservation) {
        $time_obj = new DateTime($reservation->reservation_time);
        $formatted_time = $time_obj->format('H:i');

        $name = !empty($reservation->customer_name) ? $reservation->customer_name : __('Client', 'gastro-starter');

        $is_confirmed = ($reservation->status === 'confirmed');
        $status_label = $is_confirmed ? __('Confirmé', 'gastro-starter') : __('En attente', 'gastro-starter');
        $status_class = $is_confirmed ? 'lm-dash-card-status--confirmed' : 'lm-dash-card-status--pending';

        echo '<div class="lm-dash-card">';
        echo '<span class="lm-dash-card-time">' . esc_html($formatted_time) . '</span>';
        echo '<div class="lm-dash-card-info">';
        echo '<div class="lm-dash-card-name">' . esc_html($name) . '</div>';
        echo '<div class="lm-dash-card-details">' . esc_html($reservation->people) . ' ' . esc_html__('pers.', 'gastro-starter');
        if (!empty($reservation->phone)) {
            echo ' &middot; ' . esc_html($reservation->phone);
        }
        echo '</div>';
        echo '</div>';
        echo '<span class="lm-dash-card-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="lm-dash-footer">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=gastro-starter-reservations&date_filter=' . $today)) . '">';
    echo esc_html__('Voir toutes', 'gastro-starter') . ' &rarr;</a>';
    echo '</div>';
}

/**
 * Widget — Prochaines réservations (cartes avec date)
 */
function gastro_starter_upcoming_reservations_widget() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $today = date('Y-m-d');

    $reservations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE (reservation_date > %s OR (reservation_date = %s AND reservation_time > %s))
            AND status != 'cancelled'
            ORDER BY reservation_date ASC, reservation_time ASC
            LIMIT 5",
            $today, $today, date('H:i:s')
        )
    );

    if (empty($reservations)) {
        echo '<div class="lm-dash-empty">';
        echo '<div class="lm-dash-empty-icon">&#9203;</div>';
        echo '<p>' . esc_html__('Aucune réservation à venir.', 'gastro-starter') . '</p>';
        echo '</div>';
        return;
    }

    echo '<div class="lm-dash-cards">';
    foreach ($reservations as $reservation) {
        $date_obj = new DateTime($reservation->reservation_date);
        $formatted_date = $date_obj->format('d/m');

        $time_obj = new DateTime($reservation->reservation_time);
        $formatted_time = $time_obj->format('H:i');

        $name = !empty($reservation->customer_name) ? $reservation->customer_name : __('Client', 'gastro-starter');

        echo '<div class="lm-dash-card">';
        echo '<span class="lm-dash-card-date">' . esc_html($formatted_date) . '</span>';
        echo '<span class="lm-dash-card-time">' . esc_html($formatted_time) . '</span>';
        echo '<div class="lm-dash-card-info">';
        echo '<div class="lm-dash-card-name">' . esc_html($name) . '</div>';
        echo '<div class="lm-dash-card-details">' . esc_html($reservation->people) . ' ' . esc_html__('pers.', 'gastro-starter') . '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="lm-dash-footer">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=gastro-starter-reservations')) . '">';
    echo esc_html__('Toutes les réservations', 'gastro-starter') . ' &rarr;</a>';
    echo '</div>';
}
