<?php
/**
 * Widgets du tableau de bord — Clients
 * Design glassmorphism Mon Restaurant
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_add_customer_dashboard_widgets() {
    wp_add_dashboard_widget(
        'gastro_starter_customer_stats_widget',
        __('Statistiques Clients', 'gastro-starter'),
        'gastro_starter_customer_stats_widget_callback'
    );

    wp_add_dashboard_widget(
        'gastro_starter_vip_customers_widget',
        __('Clients VIP', 'gastro-starter'),
        'gastro_starter_vip_customers_widget_callback'
    );

    wp_add_dashboard_widget(
        'gastro_starter_recent_customers_widget',
        __('Clients Récents', 'gastro-starter'),
        'gastro_starter_recent_customers_widget_callback'
    );
}
add_action('wp_dashboard_setup', 'gastro_starter_add_customer_dashboard_widgets');

/**
 * Widget — Statistiques Clients (KPI glassmorphism)
 */
function gastro_starter_customer_stats_widget_callback() {
    $stats = gastro_starter_get_global_customer_stats();

    echo '<div class="lm-dash-stats-grid">';

    echo '<div class="lm-dash-stat-box">';
    echo '<div class="lm-dash-stat-value">' . esc_html($stats['total_customers']) . '</div>';
    echo '<div class="lm-dash-stat-label">' . esc_html__('Clients', 'gastro-starter') . '</div>';
    echo '</div>';

    echo '<div class="lm-dash-stat-box lm-dash-stat-box--vip">';
    echo '<div class="lm-dash-stat-value">' . esc_html($stats['vip_customers']) . '</div>';
    echo '<div class="lm-dash-stat-label">' . esc_html__('VIP', 'gastro-starter') . '</div>';
    echo '</div>';

    echo '<div class="lm-dash-stat-box">';
    echo '<div class="lm-dash-stat-value">' . esc_html($stats['habitues'] ?? 0) . '</div>';
    echo '<div class="lm-dash-stat-label">' . esc_html__('Habitués', 'gastro-starter') . '</div>';
    echo '</div>';

    echo '<div class="lm-dash-stat-box lm-dash-stat-box--new">';
    echo '<div class="lm-dash-stat-value">' . esc_html($stats['new_customers_this_month']) . '</div>';
    echo '<div class="lm-dash-stat-label">' . esc_html__('Nouveaux', 'gastro-starter') . '</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="lm-dash-footer">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=gastro-starter-customers')) . '">';
    echo esc_html__('Gérer les clients', 'gastro-starter') . ' &rarr;</a>';
    echo '</div>';
}

/**
 * Widget — Clients VIP (tableau stylisé)
 */
function gastro_starter_vip_customers_widget_callback() {
    $vip_customers = gastro_starter_get_vip_customers(5);

    if (empty($vip_customers)) {
        echo '<div class="lm-dash-empty">';
        echo '<div class="lm-dash-empty-icon">&#11088;</div>';
        echo '<p>' . esc_html__('Aucun client VIP pour le moment.', 'gastro-starter') . '</p>';
        echo '</div>';
        return;
    }

    echo '<table class="lm-dash-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Nom', 'gastro-starter') . '</th>';
    echo '<th>' . esc_html__('Visites', 'gastro-starter') . '</th>';
    echo '<th>' . esc_html__('Dernière', 'gastro-starter') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($vip_customers as $customer) {
        echo '<tr>';
        echo '<td>' . esc_html($customer->name) . ' <span class="lm-dash-badge-vip">VIP</span></td>';
        echo '<td>' . esc_html($customer->visits) . '</td>';
        echo '<td>' . esc_html(date_i18n('d/m/Y', strtotime($customer->last_visit))) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if (count($vip_customers) === 5) {
        echo '<div class="lm-dash-footer">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=gastro-starter-customers')) . '">';
        echo esc_html__('Tous les VIP', 'gastro-starter') . ' &rarr;</a>';
        echo '</div>';
    }
}

/**
 * Widget — Clients Récents (tableau stylisé)
 */
function gastro_starter_recent_customers_widget_callback() {
    $recent_customers = gastro_starter_get_recent_customers(5);

    if (empty($recent_customers)) {
        echo '<div class="lm-dash-empty">';
        echo '<div class="lm-dash-empty-icon">&#128100;</div>';
        echo '<p>' . esc_html__('Aucun client récent.', 'gastro-starter') . '</p>';
        echo '</div>';
        return;
    }

    echo '<table class="lm-dash-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Nom', 'gastro-starter') . '</th>';
    echo '<th>' . esc_html__('Visites', 'gastro-starter') . '</th>';
    echo '<th>' . esc_html__('Date', 'gastro-starter') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($recent_customers as $customer) {
        echo '<tr>';
        echo '<td>' . esc_html($customer->name);
        if ($customer->is_vip) {
            echo ' <span class="lm-dash-badge-vip">VIP</span>';
        }
        echo '</td>';
        echo '<td>' . esc_html($customer->visits) . '</td>';
        echo '<td>' . esc_html(date_i18n('d/m/Y', strtotime($customer->last_visit))) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if (count($recent_customers) === 5) {
        echo '<div class="lm-dash-footer">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=gastro-starter-customers')) . '">';
        echo esc_html__('Tous les clients', 'gastro-starter') . ' &rarr;</a>';
        echo '</div>';
    }
}
