<?php
/**
 * Page d'administration des clients — Version 2.0
 * Interface moderne style CRM avec cartes, KPIs, segments, timeline
 */

function gastro_starter_add_customers_menu() {
    add_menu_page(
        __('Clients du restaurant', 'gastro-starter'),
        __('Clients', 'gastro-starter'),
        'manage_options',
        'gastro-starter-customers',
        'gastro_starter_customers_page',
        'dashicons-groups',
        25
    );
}
add_action('admin_menu', 'gastro_starter_add_customers_menu');

/**
 * Page principale
 */
function gastro_starter_customers_page() {
    // Gérer les actions non-AJAX (resync, toggle VIP)
    gastro_starter_handle_customer_actions();

    $stats = gastro_starter_get_global_customer_stats();
    ?>
    <div class="wrap gastro-starter-customers-wrap">
        <div class="customers-header">
            <h1><?php esc_html_e('Clients du restaurant', 'gastro-starter'); ?></h1>
            <div class="header-actions">
                <button type="button" class="button button-primary" id="btn-add-customer">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Ajouter un client', 'gastro-starter'); ?>
                </button>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=gastro-starter-customers&action=resync_stats'), 'resync_customer_stats')); ?>" class="button button-secondary" id="btn-resync">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Resynchroniser', 'gastro-starter'); ?>
                </a>
                <button type="button" class="button button-secondary" id="btn-export-csv">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e('Exporter CSV', 'gastro-starter'); ?>
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="customers-kpi-grid">
            <div class="kpi-box kpi-total active" data-segment="all">
                <span class="kpi-value"><?php echo esc_html($stats['total_customers']); ?></span>
                <span class="kpi-label"><?php esc_html_e('Total clients', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box kpi-habitue" data-segment="habitue">
                <span class="kpi-value"><?php echo esc_html($stats['habitues']); ?></span>
                <span class="kpi-label"><?php esc_html_e('Habitués', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box kpi-occasionnel" data-segment="occasionnel">
                <span class="kpi-value"><?php echo esc_html($stats['occasionnels']); ?></span>
                <span class="kpi-label"><?php esc_html_e('Occasionnels', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box kpi-perdu" data-segment="perdu">
                <span class="kpi-value"><?php echo esc_html($stats['perdus']); ?></span>
                <span class="kpi-label"><?php esc_html_e('Perdus', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box kpi-nouveau" data-segment="nouveau">
                <span class="kpi-value"><?php echo esc_html($stats['nouveaux'] + $stats['inactifs']); ?></span>
                <span class="kpi-label"><?php esc_html_e('Nouveaux', 'gastro-starter'); ?></span>
            </div>
            <div class="kpi-box kpi-vip" data-segment="vip">
                <span class="kpi-value"><?php echo esc_html($stats['vip_customers']); ?></span>
                <span class="kpi-label"><?php esc_html_e('VIP', 'gastro-starter'); ?></span>
            </div>
        </div>

        <!-- Barre de filtres -->
        <div class="customers-filter-bar">
            <div class="filter-search">
                <input type="text" id="customer-search" placeholder="<?php esc_attr_e('Rechercher un client...', 'gastro-starter'); ?>" autocomplete="off">
            </div>
            <div class="filter-sort">
                <select id="customer-sort">
                    <option value="last_visit"><?php esc_html_e('Dernière visite', 'gastro-starter'); ?></option>
                    <option value="loyalty_score"><?php esc_html_e('Score fidélité', 'gastro-starter'); ?></option>
                    <option value="visits"><?php esc_html_e('Nombre de visites', 'gastro-starter'); ?></option>
                    <option value="name"><?php esc_html_e('Nom', 'gastro-starter'); ?></option>
                </select>
            </div>
        </div>

        <!-- Grille de cartes clients -->
        <div class="customers-grid" id="customers-grid">
            <div class="customers-loading"><?php esc_html_e('Chargement...', 'gastro-starter'); ?></div>
        </div>

        <!-- Pagination -->
        <div class="customers-pagination" id="customers-pagination"></div>

        <!-- Panneau Timeline (slide-out) -->
        <div class="customer-panel-overlay" id="panel-overlay"></div>
        <div class="customer-panel" id="customer-panel">
            <div class="panel-header">
                <button class="panel-close" id="panel-close">&times;</button>
            </div>
            <div class="panel-content" id="panel-content"></div>
        </div>

        <!-- Modale Ajout Client -->
        <div class="add-customer-modal-overlay" id="add-customer-overlay"></div>
        <div class="add-customer-modal" id="add-customer-modal">
            <div class="modal-header">
                <h2><?php esc_html_e('Ajouter un client', 'gastro-starter'); ?></h2>
                <button class="modal-close" id="modal-close-customer">&times;</button>
            </div>
            <form id="add-customer-form" class="modal-body">
                <div class="modal-field">
                    <label for="new-customer-name"><?php esc_html_e('Nom', 'gastro-starter'); ?> <span class="required">*</span></label>
                    <input type="text" id="new-customer-name" required>
                </div>
                <div class="modal-field">
                    <label for="new-customer-phone"><?php esc_html_e('Téléphone', 'gastro-starter'); ?></label>
                    <input type="tel" id="new-customer-phone">
                </div>
                <div class="modal-field">
                    <label for="new-customer-email"><?php esc_html_e('Email', 'gastro-starter'); ?></label>
                    <input type="email" id="new-customer-email">
                </div>
                <div class="modal-field">
                    <label for="new-customer-notes"><?php esc_html_e('Notes', 'gastro-starter'); ?></label>
                    <textarea id="new-customer-notes" rows="3"></textarea>
                </div>
                <div class="modal-error" id="add-customer-error" style="display:none;"></div>
                <div class="modal-actions">
                    <button type="button" class="button button-secondary" id="modal-cancel-customer"><?php esc_html_e('Annuler', 'gastro-starter'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Ajouter', 'gastro-starter'); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Gérer les actions admin (resync, toggle VIP, etc.)
 */
function gastro_starter_handle_customer_actions() {
    if (!isset($_GET['action'])) return;

    if ($_GET['action'] === 'resync_stats' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'resync_customer_stats')) {
        $count = gastro_starter_resync_customer_stats();
        echo '<div class="notice notice-success is-dismissible"><p>' .
            sprintf(esc_html__('%d clients synchronisés avec succès.', 'gastro-starter'), $count) .
            '</p></div>';
    }

    if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
        $customer_id = intval($_GET['customer_id']);

        if ($_GET['action'] === 'toggle_vip' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'toggle_vip_' . $customer_id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'customer_stats';
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $customer_id));
            if ($customer) {
                $wpdb->update($table, ['is_vip' => $customer->is_vip ? 0 : 1], ['id' => $customer_id]);
                delete_transient('gastro_starter_customer_global_stats');
                $msg = $customer->is_vip
                    ? sprintf(__('%s n\'est plus VIP.', 'gastro-starter'), $customer->name)
                    : sprintf(__('%s est maintenant VIP.', 'gastro-starter'), $customer->name);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            }
        }
    }
}

// ─── AJAX Endpoints ────────────────────────────────────────────────────────────

/**
 * Recherche/filtre des clients (AJAX)
 */
function gastro_starter_ajax_customer_search() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'customer_stats';

    $search = sanitize_text_field($_POST['search'] ?? '');
    $segment = sanitize_text_field($_POST['segment'] ?? 'all');
    $sort = sanitize_text_field($_POST['sort'] ?? 'last_visit');
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 24;
    $offset = ($page - 1) * $per_page;

    $where = ['1=1'];
    $params = [];

    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = "(name LIKE %s OR email LIKE %s OR phone LIKE %s)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($segment === 'vip') {
        $where[] = "is_vip = 1";
    } elseif ($segment !== 'all') {
        if ($segment === 'nouveau') {
            $where[] = "segment IN ('nouveau', 'inactif')";
        } else {
            $where[] = "segment = %s";
            $params[] = $segment;
        }
    }

    $where_sql = implode(' AND ', $where);

    $allowed_sorts = [
        'last_visit' => 'last_visit DESC',
        'loyalty_score' => 'loyalty_score DESC',
        'visits' => 'visits DESC',
        'name' => 'name ASC',
    ];
    $order_sql = $allowed_sorts[$sort] ?? 'last_visit DESC';

    $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = $wpdb->get_var(empty($params) ? $count_query : $wpdb->prepare($count_query, ...$params));

    $query = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_sql LIMIT %d OFFSET %d";
    $query_params = array_merge($params, [$per_page, $offset]);
    $customers = $wpdb->get_results($wpdb->prepare($query, ...$query_params));

    $cards_html = '';
    foreach ($customers as $customer) {
        $cards_html .= gastro_starter_render_customer_card($customer);
    }

    if (empty($customers)) {
        $cards_html = '<div class="no-customers">' . esc_html__('Aucun client trouvé.', 'gastro-starter') . '</div>';
    }

    $total_pages = ceil($total / $per_page);

    wp_send_json_success([
        'html' => $cards_html,
        'total' => (int)$total,
        'pages' => (int)$total_pages,
        'page' => $page,
    ]);
}
add_action('wp_ajax_gastro_starter_customer_search', 'gastro_starter_ajax_customer_search');

/**
 * Timeline d'un client (AJAX)
 */
function gastro_starter_ajax_customer_timeline() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $customers_table = $wpdb->prefix . 'customer_stats';
    $reservations_table = $wpdb->prefix . 'reservations';

    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers_table WHERE id = %d", $customer_id));
    if (!$customer) {
        wp_send_json_error('Client introuvable');
        return;
    }

    $reservations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $reservations_table WHERE LOWER(TRIM(customer_email)) = %s ORDER BY reservation_date DESC, reservation_time DESC LIMIT 50",
        $customer->email
    ));

    $html = gastro_starter_render_customer_panel($customer, $reservations);
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_gastro_starter_customer_timeline', 'gastro_starter_ajax_customer_timeline');

/**
 * Sauvegarder les notes d'un client (AJAX)
 */
function gastro_starter_ajax_customer_save_notes() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    $wpdb->update(
        $wpdb->prefix . 'customer_stats',
        ['notes' => $notes],
        ['id' => $customer_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success();
}
add_action('wp_ajax_gastro_starter_customer_save_notes', 'gastro_starter_ajax_customer_save_notes');

/**
 * Toggle VIP via AJAX
 */
function gastro_starter_ajax_customer_toggle_vip() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $table = $wpdb->prefix . 'customer_stats';

    $customer = $wpdb->get_row($wpdb->prepare("SELECT is_vip, name FROM $table WHERE id = %d", $customer_id));
    if (!$customer) {
        wp_send_json_error('Client introuvable');
        return;
    }

    $new_vip = $customer->is_vip ? 0 : 1;
    $wpdb->update($table, ['is_vip' => $new_vip], ['id' => $customer_id]);
    delete_transient('gastro_starter_customer_global_stats');

    wp_send_json_success(['is_vip' => $new_vip, 'name' => $customer->name]);
}
add_action('wp_ajax_gastro_starter_customer_toggle_vip', 'gastro_starter_ajax_customer_toggle_vip');

/**
 * Export CSV
 */
function gastro_starter_ajax_customer_export_csv() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'customer_stats';
    $segment = sanitize_text_field($_POST['segment'] ?? 'all');

    $where = "consent_data_processing = 1";
    if ($segment === 'vip') {
        $where .= " AND is_vip = 1";
    } elseif ($segment !== 'all') {
        $where .= $wpdb->prepare(" AND segment = %s", $segment);
    }

    $customers = $wpdb->get_results("SELECT name, email, phone, visits, loyalty_score, segment, last_visit FROM $table WHERE $where ORDER BY loyalty_score DESC");

    $csv = "Nom,Email,Téléphone,Visites,Score,Segment,Dernière visite\n";
    foreach ($customers as $c) {
        $csv .= sprintf(
            '"%s","%s","%s",%d,%d,"%s","%s"' . "\n",
            str_replace('"', '""', $c->name ?? ''),
            $c->email,
            $c->phone ?? '',
            $c->visits,
            $c->loyalty_score,
            $c->segment,
            $c->last_visit ? date('d/m/Y', strtotime($c->last_visit)) : ''
        );
    }

    wp_send_json_success(['csv' => $csv, 'filename' => 'clients-gastro-starter-' . date('Y-m-d') . '.csv']);
}
add_action('wp_ajax_gastro_starter_customer_export_csv', 'gastro_starter_ajax_customer_export_csv');

/**
 * Ajouter un client manuellement (AJAX)
 */
function gastro_starter_ajax_customer_add() {
    check_ajax_referer('gastro_starter_customers_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'customer_stats';

    $name = sanitize_text_field($_POST['name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if (empty($name)) {
        wp_send_json_error(__('Le nom est obligatoire.', 'gastro-starter'));
        return;
    }

    if (!empty($email)) {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
        if ($existing) {
            wp_send_json_error(__('Un client avec cet email existe déjà.', 'gastro-starter'));
            return;
        }
    }

    $insert_data = [
        'name' => $name,
        'phone' => $phone,
        'email' => $email ?: null,
        'notes' => $notes,
        'visits' => 0,
        'loyalty_score' => 0,
        'segment' => 'nouveau',
        'is_vip' => 0,
        'first_visit' => current_time('mysql'),
    ];

    $result = $wpdb->insert($table, $insert_data);

    if ($result === false) {
        wp_send_json_error(__('Erreur lors de l\'ajout du client.', 'gastro-starter'));
        return;
    }

    delete_transient('gastro_starter_customer_global_stats');
    wp_send_json_success(['id' => $wpdb->insert_id, 'name' => $name]);
}
add_action('wp_ajax_gastro_starter_customer_add', 'gastro_starter_ajax_customer_add');

/**
 * Autocomplétion clients (AJAX léger pour le formulaire rapide)
 */
function gastro_starter_ajax_customer_autocomplete() {
    check_ajax_referer('gastro_starter_reservation_edit', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'customer_stats';

    $term = sanitize_text_field($_POST['term'] ?? '');
    if (strlen($term) < 2) {
        wp_send_json_success([]);
        return;
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, phone, email FROM $table WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s ORDER BY visits DESC LIMIT 8",
        $like, $like, $like
    ));

    $customers = [];
    foreach ($results as $row) {
        $customers[] = [
            'id' => (int)$row->id,
            'name' => $row->name,
            'phone' => $row->phone ?? '',
            'email' => $row->email ?? '',
        ];
    }

    wp_send_json_success($customers);
}
add_action('wp_ajax_gastro_starter_customer_autocomplete', 'gastro_starter_ajax_customer_autocomplete');

// ─── Rendering Helpers ─────────────────────────────────────────────────────────

function gastro_starter_render_customer_card($customer) {
    $initials = gastro_starter_get_initials($customer->name ?? $customer->email);
    $color = gastro_starter_avatar_color($customer->email);
    $segment = $customer->segment ?? 'nouveau';
    $score = (int)($customer->loyalty_score ?? 0);
    $visits = (int)($customer->visits ?? 0);
    $is_vip = (int)($customer->is_vip ?? 0);

    $days_since = '';
    if (!empty($customer->last_visit)) {
        $days = (int)((time() - strtotime($customer->last_visit)) / 86400);
        if ($days === 0) {
            $days_since = __("Aujourd'hui", 'gastro-starter');
        } elseif ($days === 1) {
            $days_since = __('Hier', 'gastro-starter');
        } elseif ($days < 30) {
            $days_since = sprintf(__('Il y a %d jours', 'gastro-starter'), $days);
        } elseif ($days < 365) {
            $months = (int)($days / 30);
            $days_since = sprintf(_n('Il y a %d mois', 'Il y a %d mois', $months, 'gastro-starter'), $months);
        } else {
            $days_since = sprintf(__('Il y a %d+ mois', 'gastro-starter'), 12);
        }
    }

    $segment_labels = [
        'habitue' => __('Habitué', 'gastro-starter'),
        'occasionnel' => __('Occasionnel', 'gastro-starter'),
        'perdu' => __('Perdu', 'gastro-starter'),
        'nouveau' => __('Nouveau', 'gastro-starter'),
        'inactif' => __('Inactif', 'gastro-starter'),
    ];

    ob_start();
    ?>
    <div class="customer-card" data-id="<?php echo esc_attr($customer->id); ?>" data-segment="<?php echo esc_attr($segment); ?>">
        <div class="card-top">
            <div class="card-avatar" style="background-color: <?php echo esc_attr($color); ?>;">
                <?php echo esc_html($initials); ?>
            </div>
            <div class="card-badges">
                <?php if ($is_vip): ?>
                    <span class="badge badge-vip">VIP</span>
                <?php endif; ?>
                <span class="badge badge-segment badge-<?php echo esc_attr($segment); ?>">
                    <?php echo esc_html($segment_labels[$segment] ?? $segment); ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <h3 class="card-name"><?php echo esc_html($customer->name ?: $customer->email); ?></h3>
            <div class="card-meta">
                <span class="meta-visits"><?php echo esc_html($visits); ?> <?php echo esc_html(_n('visite', 'visites', $visits, 'gastro-starter')); ?></span>
                <span class="meta-score">
                    <span class="score-bar"><span class="score-fill" style="width:<?php echo esc_attr($score); ?>%"></span></span>
                    <?php echo esc_html($score); ?>
                </span>
            </div>
            <?php if ($days_since): ?>
                <div class="card-last-visit"><?php echo esc_html($days_since); ?></div>
            <?php endif; ?>
        </div>
        <div class="card-actions">
            <button class="card-action-btn" data-action="timeline" title="<?php esc_attr_e('Historique', 'gastro-starter'); ?>">
                <span class="dashicons dashicons-backup"></span>
            </button>
            <button class="card-action-btn" data-action="toggle-vip" title="<?php echo $is_vip ? esc_attr__('Retirer VIP', 'gastro-starter') : esc_attr__('Promouvoir VIP', 'gastro-starter'); ?>">
                <span class="dashicons dashicons-star-<?php echo $is_vip ? 'filled' : 'empty'; ?>"></span>
            </button>
            <a href="mailto:<?php echo esc_attr($customer->email); ?>" class="card-action-btn" title="<?php esc_attr_e('Envoyer un email', 'gastro-starter'); ?>">
                <span class="dashicons dashicons-email"></span>
            </a>
            <?php if (!empty($customer->phone)): ?>
                <a href="tel:<?php echo esc_attr($customer->phone); ?>" class="card-action-btn" title="<?php esc_attr_e('Appeler', 'gastro-starter'); ?>">
                    <span class="dashicons dashicons-phone"></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function gastro_starter_render_customer_panel($customer, $reservations) {
    $initials = gastro_starter_get_initials($customer->name ?? $customer->email);
    $color = gastro_starter_avatar_color($customer->email);
    $score = (int)($customer->loyalty_score ?? 0);
    $segment_labels = [
        'habitue' => __('Habitué', 'gastro-starter'),
        'occasionnel' => __('Occasionnel', 'gastro-starter'),
        'perdu' => __('Perdu', 'gastro-starter'),
        'nouveau' => __('Nouveau', 'gastro-starter'),
        'inactif' => __('Inactif', 'gastro-starter'),
    ];

    ob_start();
    ?>
    <div class="panel-customer-header">
        <div class="panel-avatar" style="background-color: <?php echo esc_attr($color); ?>;">
            <?php echo esc_html($initials); ?>
        </div>
        <div class="panel-info">
            <h2><?php echo esc_html($customer->name ?: $customer->email); ?></h2>
            <div class="panel-badges">
                <?php if ($customer->is_vip): ?>
                    <span class="badge badge-vip">VIP</span>
                <?php endif; ?>
                <span class="badge badge-segment badge-<?php echo esc_attr($customer->segment); ?>">
                    <?php echo esc_html($segment_labels[$customer->segment] ?? $customer->segment); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="panel-score-section">
        <div class="panel-score-bar">
            <div class="panel-score-fill" style="width: <?php echo esc_attr($score); ?>%"></div>
        </div>
        <span class="panel-score-label">Score fidélité : <strong><?php echo esc_html($score); ?>/100</strong></span>
    </div>

    <div class="panel-contact">
        <?php if (!empty($customer->email)): ?>
            <a href="mailto:<?php echo esc_attr($customer->email); ?>" class="panel-contact-item">
                <span class="dashicons dashicons-email"></span> <?php echo esc_html($customer->email); ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($customer->phone)): ?>
            <a href="tel:<?php echo esc_attr($customer->phone); ?>" class="panel-contact-item">
                <span class="dashicons dashicons-phone"></span> <?php echo esc_html($customer->phone); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="panel-stats-mini">
        <div class="mini-stat">
            <span class="mini-stat-value"><?php echo esc_html($customer->visits); ?></span>
            <span class="mini-stat-label"><?php esc_html_e('Visites', 'gastro-starter'); ?></span>
        </div>
        <div class="mini-stat">
            <span class="mini-stat-value"><?php echo esc_html($customer->avg_party_size ? number_format((float)$customer->avg_party_size, 1) : '-'); ?></span>
            <span class="mini-stat-label"><?php esc_html_e('Moy. pers.', 'gastro-starter'); ?></span>
        </div>
        <div class="mini-stat">
            <span class="mini-stat-value"><?php echo esc_html($customer->total_people ?? 0); ?></span>
            <span class="mini-stat-label"><?php esc_html_e('Total pers.', 'gastro-starter'); ?></span>
        </div>
        <div class="mini-stat">
            <span class="mini-stat-value"><?php echo esc_html($customer->no_show_count ?? 0); ?></span>
            <span class="mini-stat-label"><?php esc_html_e('No-shows', 'gastro-starter'); ?></span>
        </div>
    </div>

    <div class="panel-section">
        <h3><?php esc_html_e('Historique des réservations', 'gastro-starter'); ?></h3>
        <?php if (empty($reservations)): ?>
            <p class="panel-empty"><?php esc_html_e('Aucune réservation trouvée.', 'gastro-starter'); ?></p>
        <?php else: ?>
            <div class="panel-timeline">
                <?php foreach ($reservations as $resa): ?>
                    <?php
                    $status_class = 'timeline-' . ($resa->status ?? 'pending');
                    $status_labels = [
                        'confirmed' => __('Confirmé', 'gastro-starter'),
                        'completed' => __('Terminé', 'gastro-starter'),
                        'pending' => __('En attente', 'gastro-starter'),
                        'cancelled' => __('Annulé', 'gastro-starter'),
                        'no-show' => __('No-show', 'gastro-starter'),
                    ];
                    $time_formatted = $resa->reservation_time ? substr($resa->reservation_time, 0, 5) : '';
                    ?>
                    <div class="timeline-item <?php echo esc_attr($status_class); ?>">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <?php echo esc_html(date('d/m/Y', strtotime($resa->reservation_date))); ?>
                                <?php if ($time_formatted): ?>
                                    <span class="timeline-time"><?php echo esc_html($time_formatted); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-details">
                                <span class="timeline-people"><?php echo esc_html($resa->people); ?> <?php esc_html_e('pers.', 'gastro-starter'); ?></span>
                                <span class="timeline-status status-<?php echo esc_attr($resa->status); ?>"><?php echo esc_html($status_labels[$resa->status] ?? $resa->status); ?></span>
                            </div>
                            <?php if (!empty($resa->notes)): ?>
                                <div class="timeline-notes"><?php echo esc_html($resa->notes); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel-section">
        <h3><?php esc_html_e('Emails envoyés', 'gastro-starter'); ?></h3>
        <?php
        global $wpdb;
        $email_logs_table = $wpdb->prefix . 'email_logs';
        $email_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT subject, email_type, status, sent_at FROM $email_logs_table WHERE recipient = %s ORDER BY sent_at DESC LIMIT 20",
            $customer->email
        ));

        $type_labels = [
            'reservation_confirmation' => __('Confirmation', 'gastro-starter'),
            'reminder' => __('Rappel', 'gastro-starter'),
            'cancellation' => __('Annulation', 'gastro-starter'),
            'admin_notification' => __('Notif. admin', 'gastro-starter'),
            'followup' => __('Relance', 'gastro-starter'),
            'becfin_newsletter' => __('Newsletter', 'gastro-starter'),
            'mailing' => __('Mailing', 'gastro-starter'),
            'general' => __('Général', 'gastro-starter'),
        ];

        if (empty($email_logs)): ?>
            <p class="panel-empty"><?php esc_html_e('Aucun email envoyé.', 'gastro-starter'); ?></p>
        <?php else: ?>
            <div class="panel-email-logs">
                <?php foreach ($email_logs as $log):
                    $type_label = $type_labels[$log->email_type] ?? $log->email_type;
                    $is_success = ($log->status === 'success' || $log->status === 'sent');
                    $status_class = $is_success ? 'email-status--success' : 'email-status--failed';
                ?>
                    <div class="email-log-item">
                        <div class="email-log-meta">
                            <span class="email-log-type"><?php echo esc_html($type_label); ?></span>
                            <span class="email-log-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log->sent_at))); ?></span>
                        </div>
                        <div class="email-log-subject">
                            <?php echo esc_html($log->subject); ?>
                            <span class="email-log-status <?php echo esc_attr($status_class); ?>"><?php echo $is_success ? '&#10003;' : '&#10007;'; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel-section">
        <h3><?php esc_html_e('Notes internes', 'gastro-starter'); ?></h3>
        <textarea id="panel-notes" class="panel-notes-textarea" data-customer-id="<?php echo esc_attr($customer->id); ?>"><?php echo esc_textarea($customer->notes ?? ''); ?></textarea>
        <button class="button button-primary" id="btn-save-notes" data-customer-id="<?php echo esc_attr($customer->id); ?>">
            <?php esc_html_e('Enregistrer', 'gastro-starter'); ?>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

function gastro_starter_get_initials($name) {
    if (empty($name)) return '?';
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return mb_strtoupper(mb_substr($parts[0], 0, 2));
}

function gastro_starter_avatar_color($email) {
    $colors = ['#b5a692', '#e0a872', '#9e8e7e', '#7ea89e', '#a87e7e', '#8e9ea8', '#a89e7e', '#7e8ea8'];
    $hash = crc32($email ?? '');
    return $colors[abs($hash) % count($colors)];
}
