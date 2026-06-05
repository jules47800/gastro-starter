<?php
/**
 * Gestion des statistiques clients pour Mon Restaurant
 * Version 2.0 — Scoring fidélité, segmentation auto, resync non-destructif
 */

/**
 * Normaliser un email pour éviter les doublons
 */
function gastro_starter_normalize_email($email) {
    return strtolower(trim($email));
}

/**
 * Calculer le score de fidélité d'un client
 */
function gastro_starter_compute_loyalty_score($customer) {
    $visits = (int)($customer->visits ?? 0);
    $no_shows = (int)($customer->no_show_count ?? 0);
    $cancellations = (int)($customer->cancelled_count ?? 0);

    $frequency_score = $visits * 12;

    $recency_bonus = 0;
    if (!empty($customer->last_visit)) {
        $days_since = (int)((time() - strtotime($customer->last_visit)) / 86400);
        if ($days_since < 14) {
            $recency_bonus = 25;
        } elseif ($days_since < 30) {
            $recency_bonus = 20;
        } elseif ($days_since < 60) {
            $recency_bonus = 10;
        } elseif ($days_since < 90) {
            $recency_bonus = 5;
        }
    }

    $consistency_bonus = 0;
    if ($visits >= 3 && !empty($customer->avg_days_between_visits) && $customer->avg_days_between_visits < 30) {
        $consistency_bonus = 15;
    } elseif ($visits >= 3 && !empty($customer->avg_days_between_visits) && $customer->avg_days_between_visits < 60) {
        $consistency_bonus = 8;
    }

    $penalty = ($no_shows * 10) + ($cancellations * 5);

    return (int)min(100, max(0, $frequency_score + $recency_bonus + $consistency_bonus - $penalty));
}

/**
 * Déterminer le segment d'un client
 */
function gastro_starter_compute_segment($customer) {
    $visits = (int)($customer->visits ?? 0);
    $days_since_last = null;
    $days_since_first = null;

    if (!empty($customer->last_visit)) {
        $days_since_last = (int)((time() - strtotime($customer->last_visit)) / 86400);
    }
    if (!empty($customer->first_visit)) {
        $days_since_first = (int)((time() - strtotime($customer->first_visit)) / 86400);
    }

    if ($visits >= 5 && $days_since_last !== null && $days_since_last <= 60) {
        return 'habitue';
    }
    if ($visits >= 2 && $visits < 5 && $days_since_last !== null && $days_since_last <= 90) {
        return 'occasionnel';
    }
    if ($visits >= 2 && $days_since_last !== null && $days_since_last > 90) {
        return 'perdu';
    }
    if ($visits == 1 && $days_since_first !== null && $days_since_first <= 60) {
        return 'nouveau';
    }
    if ($visits == 1 && $days_since_first !== null && $days_since_first > 60) {
        return 'inactif';
    }

    return 'nouveau';
}

/**
 * Mettre à jour les stats d'un client après une réservation
 */
function gastro_starter_update_customer_visits($customer_email, $reservation_id = null) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';
    $reservations_table = $wpdb->prefix . 'reservations';

    if (empty($customer_email) || !is_email($customer_email)) {
        return;
    }

    $customer_email = gastro_starter_normalize_email($customer_email);

    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT customer_name, customer_phone, consent_data_processing, consent_data_storage, accept_reminder, newsletter FROM $reservations_table WHERE id = %d",
        $reservation_id
    ));
    $customer_name = $reservation ? $reservation->customer_name : '';
    $customer_phone = $reservation ? ($reservation->customer_phone ?? '') : '';

    $wpdb->query($wpdb->prepare(
        "INSERT INTO $customers_table (email, name, phone, visits, first_visit, last_visit, last_reservation_id, is_vip, consent_data_processing, consent_data_storage, accept_reminder, newsletter, consent_date)
         VALUES (%s, %s, %s, 1, NOW(), NOW(), %d, 0, %d, %d, %d, %d, NOW())
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            phone = COALESCE(NULLIF(VALUES(phone), ''), phone),
            visits = visits + 1,
            last_visit = NOW(),
            last_reservation_id = VALUES(last_reservation_id),
            consent_data_processing = VALUES(consent_data_processing),
            consent_data_storage = VALUES(consent_data_storage),
            accept_reminder = VALUES(accept_reminder),
            newsletter = VALUES(newsletter),
            consent_date = NOW(),
            is_vip = IF(visits >= 4, 1, is_vip)",
        $customer_email,
        $customer_name,
        $customer_phone,
        $reservation_id,
        $reservation->consent_data_processing ?? 0,
        $reservation->consent_data_storage ?? 0,
        $reservation->accept_reminder ?? 0,
        $reservation->newsletter ?? 0
    ));

    // Vérifier si VIP atteint pour envoyer l'email
    $customer = $wpdb->get_row($wpdb->prepare("SELECT visits, is_vip FROM $customers_table WHERE email = %s", $customer_email));
    if ($customer && (int)$customer->visits === 5 && $customer->is_vip == 1) {
        gastro_starter_send_vip_email($customer_email, $customer_name);
    }

    // Invalider le cache des stats globales
    delete_transient('gastro_starter_customer_global_stats');

    // Synchroniser le contact vers Brevo
    if (function_exists('gastro_starter_sync_customer_to_brevo')) {
        gastro_starter_sync_customer_to_brevo($customer_email);
    }
}

/**
 * Envoyer un email de félicitations au client qui devient VIP
 */
function gastro_starter_send_vip_email($email, $name) {
    $subject = __('Félicitations, vous êtes maintenant un client VIP du restaurant !', 'gastro-starter');
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $message = '<p>Bonjour ' . esc_html($name) . ',</p>';
    $message .= '<p>Toute l\'équipe du restaurant <strong>Mon Restaurant</strong> vous remercie pour votre fidélité !</p>';
    $message .= '<p>Nous sommes ravis de vous compter parmi nos clients réguliers et nous vous accorderons une attention toute particulière lors de vos prochaines visites.</p>';
    $message .= '<p>À très bientôt,</p>';
    $message .= '<p>L\'équipe du restaurant</p>';

    wp_mail($email, $subject, $message, $headers);
}

/**
 * Récupérer les statistiques globales (requête unique + cache)
 */
function gastro_starter_get_global_customer_stats() {
    $cached = get_transient('gastro_starter_customer_global_stats');
    if (false !== $cached) {
        return $cached;
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';
    $reservations_table = $wpdb->prefix . 'reservations';

    $current_month_start = date('Y-m-01');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) as total_customers,
            COALESCE(SUM(is_vip), 0) as vip_customers,
            COALESCE(AVG(visits), 0) as avg_visits,
            COALESCE(SUM(CASE WHEN visits > 1 THEN 1 ELSE 0 END), 0) as returning_customers,
            COALESCE(SUM(CASE WHEN first_visit >= %s THEN 1 ELSE 0 END), 0) as new_this_month,
            COALESCE(SUM(CASE WHEN segment = 'habitue' THEN 1 ELSE 0 END), 0) as habitues,
            COALESCE(SUM(CASE WHEN segment = 'occasionnel' THEN 1 ELSE 0 END), 0) as occasionnels,
            COALESCE(SUM(CASE WHEN segment = 'perdu' THEN 1 ELSE 0 END), 0) as perdus,
            COALESCE(SUM(CASE WHEN segment = 'nouveau' THEN 1 ELSE 0 END), 0) as nouveaux,
            COALESCE(SUM(CASE WHEN segment = 'inactif' THEN 1 ELSE 0 END), 0) as inactifs
        FROM $customers_table",
        $current_month_start
    ));

    $total_reservations = $wpdb->get_var("SELECT COUNT(*) FROM $reservations_table");
    $most_loyal = $wpdb->get_row("SELECT * FROM $customers_table ORDER BY loyalty_score DESC, visits DESC LIMIT 1");

    $total = (int)($row->total_customers ?? 0);
    $returning = (int)($row->returning_customers ?? 0);
    $return_rate = $total > 0 ? round(($returning / $total) * 100, 1) : 0;

    $stats = array(
        'total_customers'        => $total,
        'vip_customers'          => (int)($row->vip_customers ?? 0),
        'total_reservations'     => (int)$total_reservations,
        'most_loyal_customer'    => $most_loyal,
        'avg_visits'             => round((float)($row->avg_visits ?? 0), 1),
        'return_rate'            => $return_rate,
        'new_customers_this_month' => (int)($row->new_this_month ?? 0),
        'habitues'               => (int)($row->habitues ?? 0),
        'occasionnels'           => (int)($row->occasionnels ?? 0),
        'perdus'                 => (int)($row->perdus ?? 0),
        'nouveaux'               => (int)($row->nouveaux ?? 0),
        'inactifs'               => (int)($row->inactifs ?? 0),
    );

    set_transient('gastro_starter_customer_global_stats', $stats, HOUR_IN_SECONDS);
    return $stats;
}

/**
 * Resynchronisation non-destructive depuis les réservations
 */
function gastro_starter_resync_customer_stats() {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';
    $reservations_table = $wpdb->prefix . 'reservations';

    // Requête agrégée unique
    $aggregated = $wpdb->get_results(
        "SELECT
            LOWER(TRIM(customer_email)) as email,
            MAX(customer_name) as name,
            MAX(customer_phone) as phone,
            COUNT(CASE WHEN status IN ('confirmed','completed') THEN 1 END) as visits,
            COUNT(CASE WHEN status = 'no-show' THEN 1 END) as no_show_count,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
            MIN(reservation_date) as first_visit,
            MAX(reservation_date) as last_visit,
            MAX(id) as last_reservation_id,
            COALESCE(SUM(CASE WHEN status IN ('confirmed','completed') THEN people ELSE 0 END), 0) as total_people,
            AVG(CASE WHEN status IN ('confirmed','completed') THEN people END) as avg_party_size,
            MAX(consent_data_processing) as consent_data_processing,
            MAX(consent_data_storage) as consent_data_storage,
            MAX(accept_reminder) as accept_reminder,
            MAX(newsletter) as newsletter
        FROM $reservations_table
        WHERE customer_email IS NOT NULL AND customer_email != ''
        GROUP BY LOWER(TRIM(customer_email))"
    );

    if (empty($aggregated)) {
        return 0;
    }

    $updated_count = 0;

    foreach ($aggregated as $row) {
        if (empty($row->email) || !is_email($row->email)) {
            continue;
        }

        $visits = (int)$row->visits;
        $is_vip_value = ($visits >= 5) ? 1 : 0;

        // Calculer avg_days_between_visits pour ce client
        $avg_days = null;
        if ($visits >= 2) {
            $dates = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT reservation_date FROM $reservations_table
                 WHERE LOWER(TRIM(customer_email)) = %s AND status IN ('confirmed','completed')
                 ORDER BY reservation_date ASC",
                $row->email
            ));
            if (count($dates) >= 2) {
                $total_days = 0;
                for ($i = 1; $i < count($dates); $i++) {
                    $total_days += (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
                }
                $avg_days = round($total_days / (count($dates) - 1), 1);
            }
        }

        // Préparer l'objet pour calcul score/segment
        $customer_obj = (object)[
            'visits' => $visits,
            'last_visit' => $row->last_visit,
            'first_visit' => $row->first_visit,
            'no_show_count' => (int)$row->no_show_count,
            'cancelled_count' => (int)$row->cancelled_count,
            'avg_days_between_visits' => $avg_days,
        ];

        $loyalty_score = gastro_starter_compute_loyalty_score($customer_obj);
        $segment = gastro_starter_compute_segment($customer_obj);

        // Conserver le statut VIP existant s'il est déjà VIP (ne jamais retirer automatiquement)
        $existing_vip = $wpdb->get_var($wpdb->prepare(
            "SELECT is_vip FROM $customers_table WHERE email = %s",
            $row->email
        ));
        if ($existing_vip == 1) {
            $is_vip_value = 1;
        }

        // Conserver les notes existantes
        $existing_notes = $wpdb->get_var($wpdb->prepare(
            "SELECT notes FROM $customers_table WHERE email = %s",
            $row->email
        ));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $customers_table
                (email, name, phone, visits, first_visit, last_visit, last_reservation_id, is_vip,
                 loyalty_score, segment, avg_party_size, total_people, no_show_count, cancelled_count,
                 avg_days_between_visits, last_computed,
                 consent_data_processing, consent_data_storage, accept_reminder, newsletter, consent_date, notes)
             VALUES (%s, %s, %s, %d, %s, %s, %d, %d, %d, %s, %f, %d, %d, %d, %f, NOW(), %d, %d, %d, %d, NOW(), %s)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = COALESCE(NULLIF(VALUES(phone), ''), phone),
                visits = VALUES(visits),
                first_visit = VALUES(first_visit),
                last_visit = VALUES(last_visit),
                last_reservation_id = VALUES(last_reservation_id),
                is_vip = GREATEST(is_vip, VALUES(is_vip)),
                loyalty_score = VALUES(loyalty_score),
                segment = VALUES(segment),
                avg_party_size = VALUES(avg_party_size),
                total_people = VALUES(total_people),
                no_show_count = VALUES(no_show_count),
                cancelled_count = VALUES(cancelled_count),
                avg_days_between_visits = VALUES(avg_days_between_visits),
                last_computed = NOW(),
                consent_data_processing = VALUES(consent_data_processing),
                consent_data_storage = VALUES(consent_data_storage),
                accept_reminder = VALUES(accept_reminder),
                newsletter = VALUES(newsletter),
                consent_date = NOW(),
                notes = COALESCE(notes, VALUES(notes))",
            $row->email,
            $row->name,
            $row->phone ?? '',
            $visits,
            $row->first_visit,
            $row->last_visit,
            (int)$row->last_reservation_id,
            $is_vip_value,
            $loyalty_score,
            $segment,
            (float)($row->avg_party_size ?? 0),
            (int)$row->total_people,
            (int)$row->no_show_count,
            (int)$row->cancelled_count,
            $avg_days ?? 0,
            (int)$row->consent_data_processing,
            (int)$row->consent_data_storage,
            (int)$row->accept_reminder,
            (int)$row->newsletter,
            $existing_notes ?? ''
        ));

        $updated_count++;
    }

    // Invalider le cache
    delete_transient('gastro_starter_customer_global_stats');

    return $updated_count;
}

/**
 * Recalculer les segments de tous les clients (cron nightly)
 */
function gastro_starter_recompute_all_segments() {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';

    $customers = $wpdb->get_results("SELECT * FROM $customers_table");

    foreach ($customers as $customer) {
        $score = gastro_starter_compute_loyalty_score($customer);
        $segment = gastro_starter_compute_segment($customer);

        $wpdb->update(
            $customers_table,
            ['loyalty_score' => $score, 'segment' => $segment, 'last_computed' => current_time('mysql')],
            ['id' => $customer->id],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }

    delete_transient('gastro_starter_customer_global_stats');
}

/**
 * Planifier le cron nightly pour les segments
 */
function gastro_starter_schedule_segment_cron() {
    if (!wp_next_scheduled('gastro_starter_recompute_segments_event')) {
        wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'gastro_starter_recompute_segments_event');
    }
}
add_action('admin_init', 'gastro_starter_schedule_segment_cron');
add_action('gastro_starter_recompute_segments_event', 'gastro_starter_recompute_all_segments');

/**
 * Mettre à jour la structure de la table customer_stats
 */
function gastro_starter_update_customer_stats_table_check() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'customer_stats';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        name varchar(100) NULL,
        phone varchar(30) NULL,
        visits int(11) NOT NULL DEFAULT 0,
        loyalty_score int(11) NOT NULL DEFAULT 0,
        segment varchar(30) NOT NULL DEFAULT 'nouveau',
        avg_party_size decimal(3,1) NULL,
        total_people int(11) NOT NULL DEFAULT 0,
        no_show_count int(11) NOT NULL DEFAULT 0,
        cancelled_count int(11) NOT NULL DEFAULT 0,
        avg_days_between_visits decimal(5,1) NULL,
        last_computed datetime NULL,
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
        KEY is_vip (is_vip),
        KEY segment (segment),
        KEY loyalty_score (loyalty_score),
        KEY last_visit (last_visit)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('admin_init', 'gastro_starter_update_customer_stats_table_check');

/**
 * Récupérer les clients VIP
 */
function gastro_starter_get_vip_customers($limit = 10) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table
        WHERE is_vip = 1
        ORDER BY loyalty_score DESC, visits DESC
        LIMIT %d",
        $limit
    ));
}

/**
 * Récupérer les clients récemment actifs
 */
function gastro_starter_get_recent_customers($limit = 10) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'customer_stats';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table
        ORDER BY last_visit DESC
        LIMIT %d",
        $limit
    ));
}

/**
 * Récupérer des statistiques avancées pour le restaurant
 */
function gastro_starter_get_advanced_restaurant_stats($period = 'last_30_days', $custom_start = '', $custom_end = '') {
    global $wpdb;

    $transient_key = 'gastro_starter_advanced_stats_' . md5($period . $custom_start . $custom_end);
    $cached_stats = get_transient($transient_key);
    if (false !== $cached_stats) {
        return $cached_stats;
    }

    $customers_table = $wpdb->prefix . 'customer_stats';
    $reservations_table = $wpdb->prefix . 'reservations';

    $end_date = date('Y-m-d');
    switch ($period) {
        case 'last_7_days':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'last_90_days':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'this_year':
            $start_date = date('Y-01-01');
            break;
        case 'custom':
            $start_date = !empty($custom_start) ? $custom_start : date('Y-m-d', strtotime('-30 days'));
            $end_date = !empty($custom_end) ? $custom_end : date('Y-m-d');
            break;
        case 'last_30_days':
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
    }

    $where_clause = $wpdb->prepare("WHERE reservation_date BETWEEN %s AND %s", $start_date, $end_date);

    $weekday_stats = $wpdb->get_results(
        "SELECT WEEKDAY(reservation_date) as weekday, COUNT(*) as reservation_count, AVG(people) as avg_party_size
        FROM $reservations_table $where_clause AND status IN ('confirmed', 'completed')
        GROUP BY WEEKDAY(reservation_date) ORDER BY weekday ASC"
    );

    $service_stats = $wpdb->get_results(
        "SELECT 'general' as meal_type, COUNT(*) as reservation_count, SUM(people) as total_guests
        FROM $reservations_table $where_clause"
    );

    $occupancy_data = gastro_starter_calculate_occupancy_data($start_date, $end_date);

    $customer_type_stats = $wpdb->get_results(
        "SELECT r.reservation_date,
            SUM(CASE WHEN c.visits = 1 THEN 1 ELSE 0 END) as new_customers,
            SUM(CASE WHEN c.visits > 1 THEN 1 ELSE 0 END) as returning_customers
        FROM $reservations_table r
        JOIN $customers_table c ON LOWER(TRIM(r.customer_email)) = c.email
        $where_clause
        GROUP BY r.reservation_date ORDER BY r.reservation_date DESC"
    );

    $monthly_stats = $wpdb->get_results(
        "SELECT DATE_FORMAT(reservation_date, '%Y-%m') as month, COUNT(*) as reservation_count,
            SUM(people) as total_people, AVG(people) as avg_party_size
        FROM $reservations_table $where_clause
        GROUP BY DATE_FORMAT(reservation_date, '%Y-%m') ORDER BY month DESC"
    );

    $cancellation_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(reservation_date, '%%Y-%%m') as month, COUNT(*) as cancelled_count
        FROM $reservations_table
        WHERE status = 'cancelled' AND reservation_date BETWEEN %s AND %s
        GROUP BY DATE_FORMAT(reservation_date, '%%Y-%%m') ORDER BY month DESC",
        $start_date, $end_date
    ));

    $cancellation_rates = array();
    foreach ($monthly_stats as $month_data) {
        $month = $month_data->month;
        $total_reservations = $month_data->reservation_count;
        $cancelled = 0;
        foreach ($cancellation_stats as $cancel_data) {
            if ($cancel_data->month === $month) {
                $cancelled = $cancel_data->cancelled_count;
                break;
            }
        }
        $cancellation_rates[$month] = array(
            'total' => $total_reservations,
            'cancelled' => $cancelled,
            'rate' => round(($cancelled / ($total_reservations + $cancelled)) * 100, 1)
        );
    }

    $group_size_stats = $wpdb->get_results(
        "SELECT people as group_size, COUNT(*) as count
        FROM $reservations_table $where_clause
        GROUP BY people ORDER BY people ASC"
    );

    $visit_distribution = $wpdb->get_results(
        "SELECT visits, COUNT(*) as customer_count
        FROM $customers_table GROUP BY visits ORDER BY visits ASC"
    );

    $retention_30days = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT c.id) / GREATEST((SELECT COUNT(*) FROM $customers_table), 1) * 100
        FROM $customers_table c
        JOIN $reservations_table r ON c.email = LOWER(TRIM(r.customer_email))
        WHERE r.reservation_date >= DATE_SUB(%s, INTERVAL 30 DAY)
            AND c.first_visit < DATE_SUB(%s, INTERVAL 30 DAY)",
        $end_date, $end_date
    ));

    $retention_90days = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT c.id) / GREATEST((SELECT COUNT(*) FROM $customers_table), 1) * 100
        FROM $customers_table c
        JOIN $reservations_table r ON c.email = LOWER(TRIM(r.customer_email))
        WHERE r.reservation_date >= DATE_SUB(%s, INTERVAL 90 DAY)
            AND c.first_visit < DATE_SUB(%s, INTERVAL 90 DAY)",
        $end_date, $end_date
    ));

    // Utiliser la valeur pré-calculée depuis customer_stats plutôt que le correlated subquery
    $time_between_visits = $wpdb->get_var(
        "SELECT AVG(avg_days_between_visits) FROM $customers_table WHERE avg_days_between_visits IS NOT NULL AND avg_days_between_visits > 0"
    );

    $no_show_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(CASE WHEN status = 'no-show' THEN 1 END) as no_show_count,
            COUNT(CASE WHEN status IN ('confirmed', 'completed', 'no-show') THEN 1 END) as total_relevant
        FROM $reservations_table WHERE reservation_date BETWEEN %s AND %s",
        $start_date, $end_date
    ));

    $source_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT source, COUNT(*) as count
        FROM $reservations_table
        WHERE reservation_date BETWEEN %s AND %s AND status != 'cancelled'
        GROUP BY source",
        $start_date, $end_date
    ));

    $source_data = ['public' => 0, 'admin' => 0];
    if ($source_stats) {
        foreach ($source_stats as $source) {
            if (isset($source_data[$source->source])) {
                $source_data[$source->source] = (int)$source->count;
            }
        }
    }

    $stats = array(
        'weekday_stats'       => $weekday_stats,
        'service_stats'       => $service_stats,
        'occupancy_data'      => $occupancy_data,
        'new_vs_returning'    => $customer_type_stats,
        'monthly_stats'       => $monthly_stats,
        'cancellation_rates'  => $cancellation_rates,
        'group_size_stats'    => $group_size_stats,
        'visit_distribution'  => $visit_distribution,
        'retention'           => array(
            '30_days' => round((float)$retention_30days, 1),
            '90_days' => round((float)$retention_90days, 1)
        ),
        'avg_days_between_visits' => round((float)$time_between_visits, 1),
        'no_show_rate'   => 0,
        'no_show_count'  => 0,
        'source_stats'   => $source_data,
    );

    if ($no_show_stats && $no_show_stats->total_relevant > 0) {
        $stats['no_show_count'] = (int)$no_show_stats->no_show_count;
        $stats['no_show_rate'] = round(($no_show_stats->no_show_count / $no_show_stats->total_relevant) * 100, 1);
    }

    set_transient($transient_key, $stats, HOUR_IN_SECONDS);
    return $stats;
}

/**
 * Calcule le taux d'occupation pour une période donnée.
 * Approche hybride : utilise le planning actuel pour les jours futurs/récents,
 * mais considère aussi comme "ouvert" tout jour passé ayant eu des réservations
 * (pour gérer les changements de jours d'ouverture).
 */
function gastro_starter_calculate_occupancy_data($start_date, $end_date) {
    global $wpdb;
    $reservations_table = $wpdb->prefix . 'reservations';

    $capacity_per_slot = get_option('gastro_starter_restaurant_capacity', 4);
    $daily_schedule = get_option('gastro_starter_daily_schedule');

    $reservations = $wpdb->get_results($wpdb->prepare(
        "SELECT reservation_date, reservation_time, people
         FROM $reservations_table
         WHERE status IN ('confirmed', 'completed') AND reservation_date BETWEEN %s AND %s",
        $start_date, $end_date
    ));

    $daily_covers = [];
    foreach ($reservations as $res) {
        $date = $res->reservation_date;
        if (!isset($daily_covers[$date])) {
            $daily_covers[$date] = ['total' => 0];
        }
        $daily_covers[$date]['total'] += $res->people;
    }

    $occupancy_data = [];
    $current_date = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);

    while ($current_date <= $end_date_obj) {
        $date_str = $current_date->format('Y-m-d');
        $day_key = strtolower($current_date->format('l'));

        $is_open_now = isset($daily_schedule[$day_key]) && !empty($daily_schedule[$day_key]['open']);
        $had_reservations = isset($daily_covers[$date_str]) && $daily_covers[$date_str]['total'] > 0;

        // Un jour est considéré "ouvert" s'il est ouvert dans le planning actuel
        // OU s'il a eu des réservations (le resto était peut-être ouvert avant un changement de planning)
        if ($is_open_now || $had_reservations) {
            $total_capacity = 0;

            if ($is_open_now && isset($daily_schedule[$day_key]['time_ranges'])) {
                $schedule = $daily_schedule[$day_key];
                $total_slots = 0;
                foreach ($schedule['time_ranges'] as $range) {
                    $start_dt = new DateTime($range['start']);
                    $end_dt = new DateTime($range['end']);
                    $interval = new DateInterval('PT' . $schedule['slot_interval'] . 'M');
                    $period = new DatePeriod($start_dt, $interval, $end_dt);
                    $total_slots += iterator_count($period);
                }
                $total_capacity = $total_slots * $capacity_per_slot;
            } elseif ($had_reservations) {
                // Jour qui n'est plus ouvert mais avait des résas : estimer la capacité
                // basée sur le service le plus long de la semaine
                $max_slots = 0;
                foreach ($daily_schedule as $dk => $sched) {
                    if (empty($sched['open']) || empty($sched['time_ranges'])) continue;
                    $slots = 0;
                    foreach ($sched['time_ranges'] as $range) {
                        $s = new DateTime($range['start']);
                        $e = new DateTime($range['end']);
                        $iv = new DateInterval('PT' . $sched['slot_interval'] . 'M');
                        $slots += iterator_count(new DatePeriod($s, $iv, $e));
                    }
                    if ($slots > $max_slots) $max_slots = $slots;
                }
                $total_capacity = $max_slots * $capacity_per_slot;
            }

            $covers = $daily_covers[$date_str]['total'] ?? 0;
            $occupancy_data[$date_str] = [
                'overall' => $total_capacity > 0 ? min(100, round(($covers / $total_capacity) * 100)) : 0,
                'was_open' => true,
            ];
        }
        // Les jours fermés sans réservations ne sont PAS inclus du tout

        $current_date->modify('+1 day');
    }

    return $occupancy_data;
}
