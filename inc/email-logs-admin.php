<?php
/**
 * Page admin discrète pour consulter les logs d'emails
 */

if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_email_logs_menu() {
    add_submenu_page(
        'gastro-starter-reservations',
        'Logs Emails',
        'Logs Emails',
        'manage_options',
        'gastro-starter-email-logs',
        'gastro_starter_email_logs_page'
    );
}
add_action('admin_menu', 'gastro_starter_email_logs_menu', 99);

function gastro_starter_email_logs_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // S'assurer que la table existe
    Gastro_Starter_Email_Logger::create_logs_table();

    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
    $per_page = 30;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    global $wpdb;
    $table = $wpdb->prefix . 'email_logs';
    $tracking_table = $wpdb->prefix . 'email_tracking';
    $has_tracking = $wpdb->get_var("SHOW TABLES LIKE '$tracking_table'") === $tracking_table;

    $col_prefix = $has_tracking ? 'l.' : '';
    $where = ['1=1'];
    if ($filter_status !== 'all') {
        $where[] = $wpdb->prepare("{$col_prefix}status = %s", $filter_status);
    }
    if ($filter_type !== 'all') {
        $where[] = $wpdb->prepare("{$col_prefix}email_type = %s", $filter_type);
    }
    $where_sql = implode(' AND ', $where);

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table " . ($has_tracking ? "l" : "") . " WHERE $where_sql");
    $offset = ($paged - 1) * $per_page;

    if ($has_tracking) {
        $logs = $wpdb->get_results("
            SELECT l.*, t.open_count, t.opened_at AS track_opened_at
            FROM $table l
            LEFT JOIN $tracking_table t ON t.recipient = l.recipient AND t.subject = l.subject AND t.sent_at >= l.sent_at - INTERVAL 5 SECOND AND t.sent_at <= l.sent_at + INTERVAL 5 SECOND
            WHERE $where_sql
            ORDER BY l.sent_at DESC
            LIMIT $per_page OFFSET $offset
        ");
    } else {
        $logs = $wpdb->get_results("SELECT *, NULL as open_count, NULL as track_opened_at FROM $table WHERE $where_sql ORDER BY sent_at DESC LIMIT $per_page OFFSET $offset");
    }
    $total_pages = ceil($total / $per_page);

    $stats = Gastro_Starter_Email_Logger::get_stats(7);
    $types = $wpdb->get_col("SELECT DISTINCT email_type FROM $table ORDER BY email_type");

    // Stats d'ouverture
    $open_stats = $has_tracking ? Gastro_Starter_Email_Tracking::get_open_stats(7) : null;

    ?>
    <div class="wrap">
        <h1 style="font-size: 1.3em; font-weight: 400; color: #555;">Logs Emails</h1>

        <div style="display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap;">
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 18px; min-width: 120px;">
                <div style="font-size: 22px; font-weight: 600; color: #333;"><?php echo intval($stats['total'] ?? 0); ?></div>
                <div style="font-size: 11px; color: #888; text-transform: uppercase;">7 derniers jours</div>
            </div>
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 18px; min-width: 120px;">
                <div style="font-size: 22px; font-weight: 600; color: #27ae60;"><?php echo intval($stats['sent'] ?? 0); ?></div>
                <div style="font-size: 11px; color: #888; text-transform: uppercase;">Envoyés</div>
            </div>
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 18px; min-width: 120px;">
                <div style="font-size: 22px; font-weight: 600; color: #e74c3c;"><?php echo intval($stats['failed'] ?? 0); ?></div>
                <div style="font-size: 11px; color: #888; text-transform: uppercase;">Échoués</div>
            </div>
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 18px; min-width: 120px;">
                <div style="font-size: 22px; font-weight: 600; color: #333;"><?php echo round($stats['success_rate'] ?? 0); ?>%</div>
                <div style="font-size: 11px; color: #888; text-transform: uppercase;">Taux de succès</div>
            </div>
            <?php if ($open_stats && $open_stats['total_sent'] > 0) : ?>
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 18px; min-width: 120px;">
                <div style="font-size: 22px; font-weight: 600; color: #9b59b6;"><?php echo intval($open_stats['open_rate']); ?>%</div>
                <div style="font-size: 11px; color: #888; text-transform: uppercase;">Taux d'ouverture</div>
                <div style="font-size: 10px; color: #aaa;"><?php echo intval($open_stats['total_opened']); ?>/<?php echo intval($open_stats['total_sent']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <form method="get" style="margin: 15px 0; display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="page" value="gastro-starter-email-logs">
            <select name="status" style="font-size: 13px;">
                <option value="all" <?php selected($filter_status, 'all'); ?>>Tous les statuts</option>
                <option value="sent" <?php selected($filter_status, 'sent'); ?>>Envoyés</option>
                <option value="failed" <?php selected($filter_status, 'failed'); ?>>Échoués</option>
            </select>
            <select name="type" style="font-size: 13px;">
                <option value="all" <?php selected($filter_type, 'all'); ?>>Tous les types</option>
                <?php foreach ($types as $type) : ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($filter_type, $type); ?>><?php echo esc_html($type); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-small">Filtrer</button>
        </form>

        <table class="widefat striped" style="font-size: 13px;">
            <thead>
                <tr>
                    <th style="width: 140px;">Date</th>
                    <th style="width: 80px;">Statut</th>
                    <th style="width: 90px;">Type</th>
                    <th>Destinataire</th>
                    <th>Sujet</th>
                    <th style="width: 50px;">Essais</th>
                    <th style="width: 90px;">Ouverture</th>
                    <th>Erreur</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)) : ?>
                    <tr><td colspan="8" style="text-align: center; color: #888; padding: 20px;">Aucun log trouvé.</td></tr>
                <?php else : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td style="color: #666;"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log->sent_at))); ?></td>
                            <td>
                                <?php if ($log->status === 'sent') : ?>
                                    <span style="color: #27ae60; font-weight: 500;">OK</span>
                                <?php else : ?>
                                    <span style="color: #e74c3c; font-weight: 500;">Échec</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size: 11px; background: #f0f0f0; padding: 2px 5px; border-radius: 3px;"><?php echo esc_html($log->email_type); ?></code></td>
                            <td><?php echo esc_html($log->recipient); ?></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo esc_html($log->subject); ?></td>
                            <td style="text-align: center;"><?php echo intval($log->attempts); ?></td>
                            <td style="text-align: center;">
                                <?php if ($log->open_count > 0) : ?>
                                    <span style="color: #27ae60; font-weight: 600;" title="Ouvert le <?php echo esc_attr($log->track_opened_at ? date_i18n('d/m/Y H:i', strtotime($log->track_opened_at)) : ''); ?> (<?php echo intval($log->open_count); ?>x)">&#128065; <?php echo intval($log->open_count); ?></span>
                                <?php elseif ($log->open_count === '0' || $log->open_count === 0) : ?>
                                    <span style="color: #ccc;" title="Pas encore ouvert">—</span>
                                <?php else : ?>
                                    <span style="color: #eee;">·</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #e74c3c; font-size: 11px;"><?php echo esc_html($log->error_message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div style="margin-top: 12px; display: flex; gap: 5px; align-items: center;">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <?php
                    $url = add_query_arg(['page' => 'gastro-starter-email-logs', 'paged' => $i, 'status' => $filter_status, 'type' => $filter_type], admin_url('admin.php'));
                    ?>
                    <?php if ($i === $paged) : ?>
                        <span style="background: #333; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 12px;"><?php echo $i; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url($url); ?>" style="padding: 4px 10px; border-radius: 3px; font-size: 12px; text-decoration: none; border: 1px solid #ddd;"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <span style="font-size: 11px; color: #888; margin-left: 8px;"><?php echo $total; ?> entrées</span>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
