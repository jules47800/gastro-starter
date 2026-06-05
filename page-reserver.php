<?php
/*
 * Template Name: Réserver
 * Page de réservation du restaurant Mon Restaurant
 */
get_header();

// Détection d'une date fermée passée en paramètre URL
$closed_date_message = '';
if (!empty($_GET['date'])) {
    $url_date_raw = sanitize_text_field($_GET['date']);
    // Convertir JJ/MM/AAAA en YYYY-MM-DD
    $date_parts = explode('/', $url_date_raw);
    if (count($date_parts) === 3) {
        $url_date_iso = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];

        // Vérifier si c'est un jour de vacances
        $holidays = get_option('gastro_starter_holiday_dates', '');
        $holiday_dates = !empty($holidays) ? array_map('trim', explode(',', $holidays)) : [];
        $is_holiday = in_array($url_date_iso, $holiday_dates);

        // Vérifier si c'est un jour de fermeture hebdomadaire
        $is_closed_day = false;
        $date_obj = DateTime::createFromFormat('Y-m-d', $url_date_iso);
        if ($date_obj) {
            $day_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $day_key = $day_names[(int)$date_obj->format('w')];
            $schedule = get_option('gastro_starter_daily_schedule', []);
            if (empty($schedule[$day_key]) || empty($schedule[$day_key]['open'])) {
                $is_closed_day = true;
            }
        }

        // Vérifier si un événement complet est prévu ce jour
        $is_event_full = false;
        $event_title = '';
        $events = get_posts([
            'post_type'   => 'event',
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'   => 'event_date',
                'value' => $url_date_iso,
            ]],
            'numberposts' => 1,
        ]);
        if (!empty($events)) {
            $ev_status = get_post_meta($events[0]->ID, 'event_status', true);
            if ($ev_status === 'full') {
                $is_event_full = true;
                $event_title = get_the_title($events[0]);
            }
        }

        $formatted_date = $date_obj ? $date_obj->format('d/m/Y') : $url_date_raw;
        $phone = get_theme_mod('gastro_starter_restaurant_phone', '06 02 55 63 15');
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);

        if ($is_event_full) {
            $closed_date_message = sprintf(
                '<div class="reservation-closed-notice">
                    <h3>%s</h3>
                    <p>%s</p>
                    <p>%s</p>
                </div>',
                esc_html__('Soirée complète', 'gastro-starter'),
                sprintf(
                    esc_html__('La soirée « %1$s » du %2$s affiche complet. La réservation en ligne n\'est plus disponible pour cette date.', 'gastro-starter'),
                    esc_html($event_title),
                    esc_html(ucfirst($formatted_date))
                ),
                sprintf(
                    esc_html__('Vous pouvez choisir une autre date ci-dessous ou nous appeler au %s en cas de désistement.', 'gastro-starter'),
                    '<a href="tel:+33' . esc_attr(ltrim($phone_clean, '0')) . '">' . esc_html($phone) . '</a>'
                )
            );
        } elseif ($is_holiday || $is_closed_day) {
            // Chercher un événement ce jour-là même s'il n'est pas "full"
            $event_name_for_closed = '';
            if (!empty($events)) {
                $event_name_for_closed = get_the_title($events[0]);
            } elseif (empty($events)) {
                $events_for_day = get_posts([
                    'post_type'   => 'event',
                    'post_status' => 'publish',
                    'meta_query'  => [[
                        'key'   => 'event_date',
                        'value' => $url_date_iso,
                    ]],
                    'numberposts' => 1,
                ]);
                if (!empty($events_for_day)) {
                    $event_name_for_closed = get_the_title($events_for_day[0]);
                }
            }

            if ($event_name_for_closed) {
                $reason_text = sprintf(
                    esc_html__('La soirée « %1$s » du %2$s est complète, nous sommes donc fermés à la réservation en ligne pour cette date.', 'gastro-starter'),
                    esc_html($event_name_for_closed),
                    esc_html(ucfirst($formatted_date))
                );
            } else {
                $reason_text = sprintf(
                    esc_html__('Le restaurant est fermé le %s. Nous vous invitons à choisir une autre date pour votre réservation.', 'gastro-starter'),
                    esc_html(ucfirst($formatted_date))
                );
            }

            $closed_date_message = sprintf(
                '<div class="reservation-closed-notice">
                    <h3>%s</h3>
                    <p>%s</p>
                    <p>%s</p>
                </div>',
                $event_name_for_closed
                    ? esc_html(sprintf(__('Soirée « %s » — Complet', 'gastro-starter'), $event_name_for_closed))
                    : esc_html__('Restaurant fermé à cette date', 'gastro-starter'),
                $reason_text,
                sprintf(
                    esc_html__('Vous pouvez choisir une autre date ci-dessous ou nous appeler au %s en cas de désistement.', 'gastro-starter'),
                    '<a href="tel:+33' . esc_attr(ltrim($phone_clean, '0')) . '">' . esc_html($phone) . '</a>'
                )
            );
        }
    }
}
?>
<main id="main" class="site-main">
    <header class="page-header">
        <h1 class="page-title"><?php _e('Réserver', 'gastro-starter'); ?></h1>
        <p class="page-subtitle"><?php _e('Une table, un moment', 'gastro-starter'); ?></p>
    </header>

    <section class="reservation-form-section">
        <div class="container">
            <?php if ($closed_date_message) : ?>
                <?php echo $closed_date_message; ?>
                <script>
                    // Supprimer le paramètre date de l'URL pour ne pas pré-remplir le formulaire
                    if (window.history && window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('date');
                        url.searchParams.delete('time');
                        window.history.replaceState({}, '', url);
                    }
                </script>
            <?php endif; ?>
            <?php get_template_part('template-parts/reservation-form'); ?>
        </div>
    </section>

    <section class="closing-section">
        <h2 class="section-title"><?php _e('Mon Restaurant vous attend', 'gastro-starter'); ?></h2>
        <p><?php _e('Une cuisine qui raconte une histoire', 'gastro-starter'); ?></p>
        <p><?php _e('Des saveurs qui marquent les esprits', 'gastro-starter'); ?></p>
        <p><?php _e('Un moment qui devient souvenir', 'gastro-starter'); ?></p>
    </section>
</main>
<?php get_footer(); ?> 