<?php
/**
 * Page de statistiques avancées pour Mon Restaurant — Version 2.0
 * Corrige le biais des jours fermés : toutes les stats ne prennent en compte
 * que les jours où le restaurant est effectivement ouvert.
 */

function gastro_starter_add_advanced_stats_menu() {
    add_submenu_page(
        'gastro-starter-customers',
        __('Statistiques Avancées', 'gastro-starter'),
        __('Statistiques Avancées', 'gastro-starter'),
        'manage_options',
        'gastro-starter-advanced-stats',
        'gastro_starter_advanced_stats_page'
    );
}
add_action('admin_menu', 'gastro_starter_add_advanced_stats_menu');

function gastro_starter_advanced_stats_page() {
    $selected_period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'last_30_days';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date_input = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    $advanced_stats = gastro_starter_get_advanced_restaurant_stats($selected_period, $start_date, $end_date_input);

    // occupancy_data ne contient désormais QUE les jours ouverts (ou ayant eu des résas)
    $daily_schedule = get_option('gastro_starter_daily_schedule', []);
    $open_occupancy = $advanced_stats['occupancy_data'] ?? [];

    // Moyenne d'occupation corrigée (jours ouverts uniquement)
    $avg_occupancy = 0;
    $open_days_count = count($open_occupancy);
    if ($open_days_count > 0) {
        $sum = array_sum(array_column($open_occupancy, 'overall'));
        $avg_occupancy = round($sum / $open_days_count, 1);
    }

    // Jours d'ouverture de la semaine (pour filtrer le graphique weekday)
    $open_day_keys = [];
    foreach ($daily_schedule as $day => $config) {
        if (!empty($config['open'])) {
            $open_day_keys[] = $day;
        }
    }

    // Segmentation des clients
    $customer_stats = gastro_starter_get_global_customer_stats();
    ?>
    <div class="wrap gastro-starter-stats-wrap">
        <div class="stats-page-header">
            <h1><?php esc_html_e('Statistiques Avancées', 'gastro-starter'); ?></h1>
            <form method="GET" class="period-form">
                <input type="hidden" name="page" value="gastro-starter-advanced-stats">
                <select name="period" id="period-select">
                    <option value="last_7_days" <?php selected($selected_period, 'last_7_days'); ?>><?php esc_html_e('7 derniers jours', 'gastro-starter'); ?></option>
                    <option value="last_30_days" <?php selected($selected_period, 'last_30_days'); ?>><?php esc_html_e('30 derniers jours', 'gastro-starter'); ?></option>
                    <option value="last_90_days" <?php selected($selected_period, 'last_90_days'); ?>><?php esc_html_e('90 derniers jours', 'gastro-starter'); ?></option>
                    <option value="this_year" <?php selected($selected_period, 'this_year'); ?>><?php esc_html_e('Cette année', 'gastro-starter'); ?></option>
                    <option value="custom" <?php selected($selected_period, 'custom'); ?>><?php esc_html_e('Personnalisé', 'gastro-starter'); ?></option>
                </select>
                <span id="custom-dates" style="<?php echo $selected_period === 'custom' ? '' : 'display:none;'; ?>">
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date_input); ?>">
                </span>
                <button type="submit" class="button"><?php esc_html_e('Appliquer', 'gastro-starter'); ?></button>
            </form>
        </div>

        <!-- KPIs principaux -->
        <div class="stats-kpi-row">
            <div class="stats-kpi">
                <span class="stats-kpi-value"><?php echo esc_html($avg_occupancy); ?>%</span>
                <span class="stats-kpi-label"><?php esc_html_e('Occupation moy.', 'gastro-starter'); ?></span>
                <span class="stats-kpi-sub"><?php echo esc_html($open_days_count); ?> <?php esc_html_e('jours ouverts', 'gastro-starter'); ?></span>
            </div>
            <div class="stats-kpi">
                <span class="stats-kpi-value"><?php echo esc_html($advanced_stats['retention']['30_days']); ?>%</span>
                <span class="stats-kpi-label"><?php esc_html_e('Rétention 30j', 'gastro-starter'); ?></span>
            </div>
            <div class="stats-kpi">
                <span class="stats-kpi-value"><?php echo esc_html($advanced_stats['avg_days_between_visits'] ?: '-'); ?></span>
                <span class="stats-kpi-label"><?php esc_html_e('Jours entre visites', 'gastro-starter'); ?></span>
            </div>
            <div class="stats-kpi">
                <span class="stats-kpi-value stats-kpi-danger"><?php echo esc_html($advanced_stats['no_show_rate']); ?>%</span>
                <span class="stats-kpi-label"><?php esc_html_e('No-shows', 'gastro-starter'); ?></span>
                <span class="stats-kpi-sub"><?php echo esc_html($advanced_stats['no_show_count']); ?> <?php esc_html_e('résas', 'gastro-starter'); ?></span>
            </div>
            <div class="stats-kpi">
                <?php
                $total_period_resas = array_sum($advanced_stats['source_stats']);
                ?>
                <span class="stats-kpi-value"><?php echo esc_html($total_period_resas); ?></span>
                <span class="stats-kpi-label"><?php esc_html_e('Réservations', 'gastro-starter'); ?></span>
            </div>
            <div class="stats-kpi">
                <span class="stats-kpi-value"><?php echo esc_html($customer_stats['habitues'] ?? 0); ?></span>
                <span class="stats-kpi-label"><?php esc_html_e('Habitués', 'gastro-starter'); ?></span>
            </div>
        </div>

        <div class="stats-grid-2col">
            <!-- Occupation (jours ouverts uniquement) -->
            <div class="stats-card stats-card-wide">
                <h2><?php esc_html_e('Taux d\'Occupation', 'gastro-starter'); ?> <small><?php esc_html_e('(jours ouverts uniquement)', 'gastro-starter'); ?></small></h2>
                <div class="chart-container">
                    <canvas id="occupancy-chart"></canvas>
                </div>
            </div>

            <!-- Répartition par jour (jours ouverts uniquement) -->
            <div class="stats-card">
                <h2><?php esc_html_e('Réservations par jour', 'gastro-starter'); ?></h2>
                <div class="chart-container chart-sm">
                    <canvas id="weekday-chart"></canvas>
                </div>
                <table class="stats-table">
                    <thead><tr><th><?php esc_html_e('Jour', 'gastro-starter'); ?></th><th><?php esc_html_e('Résas', 'gastro-starter'); ?></th><th><?php esc_html_e('Moy. pers.', 'gastro-starter'); ?></th></tr></thead>
                    <tbody>
                    <?php
                    $day_names = [
                        __('Lundi', 'gastro-starter'), __('Mardi', 'gastro-starter'), __('Mercredi', 'gastro-starter'),
                        __('Jeudi', 'gastro-starter'), __('Vendredi', 'gastro-starter'), __('Samedi', 'gastro-starter'), __('Dimanche', 'gastro-starter')
                    ];
                    $day_keys_map = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                    if (!empty($advanced_stats['weekday_stats'])) {
                        foreach ($advanced_stats['weekday_stats'] as $day) {
                            $dk = $day_keys_map[$day->weekday] ?? '';
                            if (!in_array($dk, $open_day_keys)) continue;
                            echo '<tr><td>' . esc_html($day_names[$day->weekday]) . '</td><td>' . esc_html($day->reservation_count) . '</td><td>' . esc_html(round($day->avg_party_size, 1)) . '</td></tr>';
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <!-- Source des réservations -->
            <div class="stats-card">
                <h2><?php esc_html_e('Sources', 'gastro-starter'); ?></h2>
                <div class="chart-container chart-sm">
                    <canvas id="source-chart"></canvas>
                </div>
                <div class="source-breakdown">
                    <?php
                    $pub = $advanced_stats['source_stats']['public'] ?? 0;
                    $adm = $advanced_stats['source_stats']['admin'] ?? 0;
                    $tot = $pub + $adm;
                    $pub_pct = $tot > 0 ? round(($pub / $tot) * 100) : 0;
                    $adm_pct = $tot > 0 ? round(($adm / $tot) * 100) : 0;
                    ?>
                    <div class="source-item">
                        <span class="source-dot source-dot-web"></span>
                        <span><?php esc_html_e('Site web', 'gastro-starter'); ?></span>
                        <strong><?php echo esc_html($pub); ?> (<?php echo esc_html($pub_pct); ?>%)</strong>
                    </div>
                    <div class="source-item">
                        <span class="source-dot source-dot-admin"></span>
                        <span><?php esc_html_e('Admin/téléphone', 'gastro-starter'); ?></span>
                        <strong><?php echo esc_html($adm); ?> (<?php echo esc_html($adm_pct); ?>%)</strong>
                    </div>
                </div>
            </div>

            <!-- Taille des groupes -->
            <div class="stats-card">
                <h2><?php esc_html_e('Taille des groupes', 'gastro-starter'); ?></h2>
                <div class="chart-container chart-sm">
                    <canvas id="group-size-chart"></canvas>
                </div>
            </div>

            <!-- Segmentation clients -->
            <div class="stats-card">
                <h2><?php esc_html_e('Segmentation clients', 'gastro-starter'); ?></h2>
                <div class="chart-container chart-sm">
                    <canvas id="segment-chart"></canvas>
                </div>
                <div class="segment-list">
                    <div class="segment-row"><span class="seg-dot seg-habitue"></span> <?php esc_html_e('Habitués', 'gastro-starter'); ?> <strong><?php echo esc_html($customer_stats['habitues'] ?? 0); ?></strong></div>
                    <div class="segment-row"><span class="seg-dot seg-occasionnel"></span> <?php esc_html_e('Occasionnels', 'gastro-starter'); ?> <strong><?php echo esc_html($customer_stats['occasionnels'] ?? 0); ?></strong></div>
                    <div class="segment-row"><span class="seg-dot seg-perdu"></span> <?php esc_html_e('Perdus', 'gastro-starter'); ?> <strong><?php echo esc_html($customer_stats['perdus'] ?? 0); ?></strong></div>
                    <div class="segment-row"><span class="seg-dot seg-nouveau"></span> <?php esc_html_e('Nouveaux', 'gastro-starter'); ?> <strong><?php echo esc_html(($customer_stats['nouveaux'] ?? 0) + ($customer_stats['inactifs'] ?? 0)); ?></strong></div>
                </div>
            </div>

            <!-- Évolution mensuelle -->
            <div class="stats-card stats-card-wide">
                <h2><?php esc_html_e('Évolution mensuelle', 'gastro-starter'); ?></h2>
                <div class="chart-container">
                    <canvas id="monthly-chart"></canvas>
                </div>
                <table class="stats-table">
                    <thead><tr><th><?php esc_html_e('Mois', 'gastro-starter'); ?></th><th><?php esc_html_e('Résas', 'gastro-starter'); ?></th><th><?php esc_html_e('Convives', 'gastro-starter'); ?></th><th><?php esc_html_e('Moy.', 'gastro-starter'); ?></th><th><?php esc_html_e('Annulations', 'gastro-starter'); ?></th></tr></thead>
                    <tbody>
                    <?php
                    if (!empty($advanced_stats['monthly_stats'])) {
                        $displayed = 0;
                        foreach ($advanced_stats['monthly_stats'] as $m) {
                            if ($displayed >= 6) break;
                            $formatted = date_i18n('F Y', strtotime($m->month . '-01'));
                            $cancel_data = $advanced_stats['cancellation_rates'][$m->month] ?? null;
                            $cancel_txt = $cancel_data ? $cancel_data['rate'] . '%' : '-';
                            echo '<tr><td>' . esc_html($formatted) . '</td><td>' . esc_html($m->reservation_count) . '</td><td>' . esc_html($m->total_people) . '</td><td>' . esc_html(round($m->avg_party_size, 1)) . '</td><td>' . esc_html($cancel_txt) . '</td></tr>';
                            $displayed++;
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <!-- Nouveaux vs Fidèles -->
            <div class="stats-card stats-card-wide">
                <h2><?php esc_html_e('Nouveaux vs Fidèles', 'gastro-starter'); ?></h2>
                <div class="chart-container">
                    <canvas id="new-returning-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <style>
    .gastro-starter-stats-wrap { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
    .stats-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .stats-page-header h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
    .period-form { display: flex; align-items: center; gap: 8px; }
    .period-form select, .period-form input[type="date"] { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }

    .stats-kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .stats-kpi { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 20px 16px; text-align: center; }
    .stats-kpi-value { display: block; font-size: 1.8rem; font-weight: 700; color: #3a3c36; line-height: 1.2; }
    .stats-kpi-value.stats-kpi-danger { color: #e74c3c; }
    .stats-kpi-label { display: block; font-size: 0.8rem; color: #777; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
    .stats-kpi-sub { display: block; font-size: 0.75rem; color: #aaa; margin-top: 2px; }

    .stats-grid-2col { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .stats-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 24px; }
    .stats-card-wide { grid-column: 1 / -1; }
    .stats-card h2 { font-size: 1rem; font-weight: 600; color: #333; margin: 0 0 16px 0; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
    .stats-card h2 small { font-weight: 400; color: #999; font-size: 0.8rem; }

    .chart-container { position: relative; height: 280px; margin-bottom: 16px; }
    .chart-container.chart-sm { height: 200px; }
    .chart-container canvas { max-width: 100%; }

    .stats-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .stats-table th, .stats-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    .stats-table th { font-weight: 600; color: #555; font-size: 0.8rem; text-transform: uppercase; }

    .source-breakdown { margin-top: 12px; }
    .source-item { display: flex; align-items: center; gap: 8px; padding: 8px 0; font-size: 0.9rem; }
    .source-item strong { margin-left: auto; color: #333; }
    .source-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .source-dot-web { background: #7f9c96; }
    .source-dot-admin { background: #e0a872; }

    .segment-list { margin-top: 12px; }
    .segment-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 0.9rem; }
    .segment-row strong { margin-left: auto; }
    .seg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .seg-habitue { background: #27ae60; }
    .seg-occasionnel { background: #f39c12; }
    .seg-perdu { background: #e74c3c; }
    .seg-nouveau { background: #3498db; }

    @media (max-width: 1024px) {
        .stats-grid-2col { grid-template-columns: 1fr; }
        .stats-card-wide { grid-column: auto; }
    }
    @media (max-width: 768px) {
        .stats-kpi-row { grid-template-columns: repeat(2, 1fr); }
        .stats-page-header { flex-direction: column; align-items: flex-start; }
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var P = '#e0a872', S = '#9e8e7e', T = '#3a3c36';

        document.getElementById('period-select').addEventListener('change', function() {
            document.getElementById('custom-dates').style.display = this.value === 'custom' ? 'inline-flex' : 'none';
        });

        // Données filtrées (jours ouverts uniquement)
        var openOccupancy = <?php echo json_encode($open_occupancy); ?>;
        var statsData = <?php echo json_encode($advanced_stats); ?>;
        var openDayKeys = <?php echo json_encode($open_day_keys); ?>;
        var dayKeysMap = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

        // 1. Occupation
        var occDates = [], occValues = [];
        Object.keys(openOccupancy).sort().forEach(function(date) {
            occDates.push(new Date(date).toLocaleDateString('fr-FR', {day:'2-digit', month:'short'}));
            occValues.push(openOccupancy[date].overall);
        });

        if (document.getElementById('occupancy-chart') && occDates.length > 0) {
            new Chart(document.getElementById('occupancy-chart'), {
                type: 'line',
                data: {
                    labels: occDates,
                    datasets: [{
                        label: 'Occupation %',
                        data: occValues,
                        borderColor: P,
                        backgroundColor: 'rgba(224,168,114,0.15)',
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: function(ctx) {
                            var v = ctx.parsed.y;
                            if (v >= 80) return '#27ae60';
                            if (v >= 50) return '#f39c12';
                            return '#e74c3c';
                        },
                        pointRadius: 5,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { min: 0, max: 100, ticks: { callback: function(v) { return v+'%'; } } } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // 2. Weekday (jours ouverts uniquement)
        var dayLabels = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
        var wdLabels = [], wdData = [];
        if (statsData.weekday_stats) {
            statsData.weekday_stats.forEach(function(d) {
                var dk = dayKeysMap[d.weekday];
                if (openDayKeys.indexOf(dk) !== -1) {
                    wdLabels.push(dayLabels[d.weekday]);
                    wdData.push(d.reservation_count);
                }
            });
        }
        if (document.getElementById('weekday-chart') && wdLabels.length > 0) {
            new Chart(document.getElementById('weekday-chart'), {
                type: 'bar',
                data: { labels: wdLabels, datasets: [{ data: wdData, backgroundColor: P, borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }

        // 3. Sources (doughnut)
        if (document.getElementById('source-chart')) {
            new Chart(document.getElementById('source-chart'), {
                type: 'doughnut',
                data: {
                    labels: ['Site web', 'Admin'],
                    datasets: [{ data: [statsData.source_stats.public, statsData.source_stats.admin], backgroundColor: ['#7f9c96', P] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // 4. Taille groupes
        if (document.getElementById('group-size-chart') && statsData.group_size_stats) {
            var gsLabels = [], gsData = [];
            statsData.group_size_stats.forEach(function(g) { gsLabels.push(g.group_size+' pers.'); gsData.push(g.count); });
            new Chart(document.getElementById('group-size-chart'), {
                type: 'bar',
                data: { labels: gsLabels, datasets: [{ data: gsData, backgroundColor: S, borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }

        // 5. Segmentation (doughnut)
        if (document.getElementById('segment-chart')) {
            var segData = <?php echo json_encode([
                $customer_stats['habitues'] ?? 0,
                $customer_stats['occasionnels'] ?? 0,
                $customer_stats['perdus'] ?? 0,
                ($customer_stats['nouveaux'] ?? 0) + ($customer_stats['inactifs'] ?? 0)
            ]); ?>;
            new Chart(document.getElementById('segment-chart'), {
                type: 'doughnut',
                data: {
                    labels: ['Habitués', 'Occasionnels', 'Perdus', 'Nouveaux'],
                    datasets: [{ data: segData, backgroundColor: ['#27ae60','#f39c12','#e74c3c','#3498db'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // 6. Évolution mensuelle
        if (document.getElementById('monthly-chart') && statsData.monthly_stats) {
            var mLabels = [], mResas = [], mPeople = [];
            statsData.monthly_stats.slice().reverse().forEach(function(m) {
                var d = new Date(m.month+'-01');
                mLabels.push(d.toLocaleDateString('fr-FR', {month:'short', year:'2-digit'}));
                mResas.push(m.reservation_count);
                mPeople.push(m.total_people);
            });
            new Chart(document.getElementById('monthly-chart'), {
                type: 'line',
                data: {
                    labels: mLabels,
                    datasets: [
                        { label: 'Réservations', data: mResas, borderColor: P, backgroundColor: 'rgba(224,168,114,0.1)', tension: 0.3, yAxisID: 'y' },
                        { label: 'Convives', data: mPeople, borderColor: S, backgroundColor: 'rgba(158,142,126,0.1)', tension: 0.3, yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Résas' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Convives' } }
                    }
                }
            });
        }

        // 7. Nouveaux vs Fidèles
        if (document.getElementById('new-returning-chart') && statsData.new_vs_returning) {
            var nrDates = [], nrNew = [], nrRet = [];
            statsData.new_vs_returning.slice().reverse().forEach(function(d) {
                nrDates.push(new Date(d.reservation_date).toLocaleDateString('fr-FR', {day:'2-digit', month:'short'}));
                nrNew.push(parseInt(d.new_customers));
                nrRet.push(parseInt(d.returning_customers));
            });
            new Chart(document.getElementById('new-returning-chart'), {
                type: 'bar',
                data: {
                    labels: nrDates,
                    datasets: [
                        { label: 'Nouveaux', data: nrNew, backgroundColor: S },
                        { label: 'Fidèles', data: nrRet, backgroundColor: P }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
            });
        }
    });
    </script>
    <?php
}
