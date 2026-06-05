<?php
/**
 * Template pour l'affichage d'un événement unique
 * Design inspiré du template email Soirées Spéciales.
 *
 * @package Gastro_Starter
 */

get_header();

while (have_posts()) :
    the_post();
    $post_id = get_the_ID();

    // ── Données de base ──────────────────────────────────────────
    $event_date   = get_post_meta($post_id, 'event_date',  true);
    $event_time   = get_post_meta($post_id, 'event_time',  true);
    $event_price  = get_post_meta($post_id, 'event_price', true);
    $event_status = get_post_meta($post_id, 'event_status', true) ?: 'open';

    // ── Données newsletter ────────────────────────────────────────
    $subtitle        = get_post_meta($post_id, 'email_subtitle', true)        ?: 'Soirée Spéciale';
    $accroche        = get_post_meta($post_id, 'email_accroche', true)        ?: get_the_excerpt();
    $places          = get_post_meta($post_id, 'email_places', true)          ?: '';
    $citation        = get_post_meta($post_id, 'email_citation', true)        ?: '';
    $citation_author = get_post_meta($post_id, 'email_citation_author', true) ?: "L'équipe du restaurant";
    $vins_text       = get_post_meta($post_id, 'email_vins_text', true)       ?: '';
    $vins_price      = get_post_meta($post_id, 'email_vins_price', true)      ?: '';
    $menu_items      = get_post_meta($post_id, 'email_menu_items', true)      ?: [];
    $menu_image_id   = get_post_meta($post_id, 'email_menu_image_id', true)   ?: '';
    $gallery_img1_id = get_post_meta($post_id, 'email_gallery_img1', true)    ?: '';
    $gallery_img2_id = get_post_meta($post_id, 'email_gallery_img2', true)    ?: '';
    $hero_image_id   = get_post_meta($post_id, 'email_image_id', true)        ?: '';

    // ── Images ────────────────────────────────────────────────────
    $hero_url = '';
    if ($hero_image_id) {
        $hero_url = wp_get_attachment_image_url($hero_image_id, 'full');
    }
    if (!$hero_url && has_post_thumbnail()) {
        $hero_url = get_the_post_thumbnail_url($post_id, 'full');
    }

    $menu_image_url = $menu_image_id ? wp_get_attachment_image_url($menu_image_id, 'large') : '';
    $gallery_url1   = $gallery_img1_id ? wp_get_attachment_image_url($gallery_img1_id, 'large') : '';
    $gallery_url2   = $gallery_img2_id ? wp_get_attachment_image_url($gallery_img2_id, 'large') : '';

    // ── Date formatée ──────────────────────────────────────────────
    $date_obj = !empty($event_date) ? new DateTime($event_date) : null;
    $formatted_date = '';
    $day_num = '';
    $month_name = '';
    $year_num = '';
    if ($date_obj) {
        $formatted_date = date_i18n('l j F Y', $date_obj->getTimestamp());
        $day_num    = $date_obj->format('d');
        $month_name = date_i18n('M', $date_obj->getTimestamp());
        $year_num   = $date_obj->format('Y');
    }

    // Heure formatée
    $formatted_time = '';
    if ($event_time) {
        $t = explode(':', $event_time);
        $formatted_time = $t[0] . 'h' . ($t[1] !== '00' ? $t[1] : '');
    }

    // ── URL de réservation ─────────────────────────────────────────
    $reservation_url = home_url('/reserver/');
    if ($event_date) {
        $reservation_url = add_query_arg('date', date('d/m/Y', strtotime($event_date)), $reservation_url);
    }
    if ($event_time) {
        $reservation_url = add_query_arg('time', $event_time, $reservation_url);
    }

    // ── Statut ────────────────────────────────────────────────────
    $is_open = ($event_status === 'open');
    $is_full = ($event_status === 'full');
    $is_past = ($event_status === 'closed') || ($date_obj && $date_obj < new DateTime('today'));
?>

<main id="primary" class="site-main event-single">

    <?php // ── HERO ─────────────────────────────────────────────── ?>
    <section class="event-hero <?php echo $hero_url ? 'has-image' : 'no-image'; ?>">
        <?php if ($hero_url) : ?>
            <div class="event-hero__bg" style="background-image: url('<?php echo esc_url($hero_url); ?>');" role="img" aria-label="<?php the_title_attribute(); ?>"></div>
            <div class="event-hero__overlay"></div>
        <?php else : ?>
            <div class="event-hero__fallback"></div>
        <?php endif; ?>

        <div class="event-hero__content container">
            <?php if ($formatted_date) : ?>
                <p class="event-hero__date fade-in"><?php echo esc_html(ucfirst($formatted_date)); ?></p>
            <?php endif; ?>

            <p class="event-hero__label fade-in delay-1"><?php echo esc_html($subtitle); ?></p>
            <h1 class="event-hero__title fade-in delay-2"><?php the_title(); ?></h1>

            <?php if ($is_full) : ?>
                <span class="event-status-pill pill-full fade-in delay-3">Complet</span>
            <?php elseif ($is_past) : ?>
                <span class="event-status-pill pill-past fade-in delay-3">Terminé</span>
            <?php endif; ?>
        </div>

        <?php if ($date_obj) : ?>
            <div class="event-date-badge-hero">
                <span class="badge-day"><?php echo esc_html($day_num); ?></span>
                <span class="badge-month"><?php echo esc_html($month_name); ?></span>
            </div>
        <?php endif; ?>
    </section>

    <?php // ── CORPS ─────────────────────────────────────────────── ?>
    <div class="event-body container">

        <?php // ── INFOS PRATIQUES ──────────────────────────────── ?>
        <?php if ($formatted_time || $event_price || $places) : ?>
        <section class="event-section event-infos fade-in-up">
            <div class="event-infos__grid">
                <?php if ($formatted_time) : ?>
                <div class="info-cell">
                    <span class="info-label">Horaire</span>
                    <span class="info-value"><?php echo esc_html($formatted_time); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($event_price) : ?>
                <div class="info-cell">
                    <span class="info-label">Tarif</span>
                    <span class="info-value"><?php echo esc_html($event_price); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($places) : ?>
                <div class="info-cell">
                    <span class="info-label">Places</span>
                    <span class="info-value"><?php echo esc_html($places); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── ACCROCHE ─────────────────────────────────────── ?>
        <?php if ($accroche) : ?>
        <section class="event-section event-accroche fade-in-up">
            <p class="event-accroche__text"><?php echo nl2br(esc_html($accroche)); ?></p>
        </section>
        <?php endif; ?>

        <?php // ── GALERIE D'AMBIANCE ───────────────────────────── ?>
        <?php if ($gallery_url1 || $gallery_url2) : ?>
        <section class="event-section event-gallery fade-in-up">
            <div class="event-gallery__grid">
                <?php if ($gallery_url1) : ?>
                    <figure class="event-gallery__item">
                        <img src="<?php echo esc_url($gallery_url1); ?>" alt="<?php the_title_attribute(); ?> — ambiance" loading="lazy" />
                    </figure>
                <?php endif; ?>
                <?php if ($gallery_url2) : ?>
                    <figure class="event-gallery__item">
                        <img src="<?php echo esc_url($gallery_url2); ?>" alt="<?php the_title_attribute(); ?> — ambiance" loading="lazy" />
                    </figure>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── MENU ─────────────────────────────────────────── ?>
        <?php if ($menu_image_url || (!empty($menu_items) && is_array($menu_items))) : ?>
        <section class="event-section event-menu fade-in-up">
            <h2 class="event-section__label">Le menu</h2>

            <?php if ($menu_image_url) : ?>
                <div class="event-menu__image">
                    <img src="<?php echo esc_url($menu_image_url); ?>" alt="Menu — <?php the_title_attribute(); ?>" loading="lazy" />
                </div>
            <?php else : ?>
                <div class="event-menu__items">
                    <?php foreach ($menu_items as $i => $item) :
                        $name = $item['name'] ?? '';
                        $desc = $item['description'] ?? '';
                        if (!$name) continue;
                    ?>
                    <div class="menu-item <?php echo $i > 0 ? 'has-separator' : ''; ?>">
                        <div class="menu-item__num"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="menu-item__content">
                            <p class="menu-item__name"><?php echo esc_html($name); ?></p>
                            <?php if ($desc) : ?>
                                <p class="menu-item__desc"><?php echo esc_html($desc); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php // ── CITATION ─────────────────────────────────────── ?>
        <?php if ($citation) : ?>
        <section class="event-section event-citation fade-in-up">
            <blockquote class="event-citation__block">
                <p class="event-citation__text"><?php echo nl2br(esc_html($citation)); ?></p>
                <cite class="event-citation__author">— <?php echo esc_html($citation_author); ?></cite>
            </blockquote>
        </section>
        <?php endif; ?>

        <?php // ── ACCORD VINS ──────────────────────────────────── ?>
        <?php if ($vins_text) : ?>
        <section class="event-section event-vins fade-in-up">
            <p class="event-section__label">Accord mets &amp; vins</p>
            <p class="event-vins__text">
                <?php echo esc_html($vins_text); ?>
                <?php if ($vins_price) : ?>
                    <span class="event-vins__price"><?php echo esc_html($vins_price); ?></span>
                <?php endif; ?>
            </p>
        </section>
        <?php endif; ?>

        <?php // ── CONTENU PRINCIPAL (s'il y en a) ──────────────── ?>
        <?php
        $content = get_the_content();
        if ($content && strip_tags($content) !== strip_tags(get_the_excerpt())) :
        ?>
        <section class="event-section event-content fade-in-up">
            <div class="event-content__text">
                <?php the_content(); ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── CTA RÉSERVER ─────────────────────────────────── ?>
        <section class="event-section event-cta fade-in-up">
            <?php if ($is_open) : ?>
                <a href="<?php echo esc_url($reservation_url); ?>" class="event-cta__button">
                    Réserver ma place
                </a>
                <?php
                $phone = get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00');
                $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
                if ($phone) :
                ?>
                <p class="event-cta__phone">
                    ou appelez-nous au <a href="tel:+33<?php echo esc_attr(ltrim($phone_clean, '0')); ?>"><?php echo esc_html($phone); ?></a>
                </p>
                <?php endif; ?>
            <?php elseif ($is_full) : ?>
                <p class="event-cta__full">Cet événement est complet.</p>
                <p class="event-cta__phone">
                    Inscrivez-vous à notre newsletter pour être informé(e) en priorité des prochaines soirées.
                </p>
            <?php else : ?>
                <p class="event-cta__full">Cet événement est terminé.</p>
                <a href="<?php echo esc_url(home_url('/agenda/')); ?>" class="event-cta__link">
                    Voir les prochains événements →
                </a>
            <?php endif; ?>
        </section>

        <?php // ── BONS CADEAUX ─────────────────────────────────── ?>
        <section class="event-section event-gift fade-in-up">
            <div class="event-gift__card">
                <p class="event-gift__title">Offrez une soirée Soirées Spéciales</p>
                <p class="event-gift__desc">Nos bons-cadeaux sont disponibles en ligne, à offrir pour une occasion spéciale.</p>
                <a href="<?php echo esc_url(home_url('/bon-achat/')); ?>" class="event-gift__link">
                    Découvrir les bons-cadeaux →
                </a>
            </div>
        </section>

        <?php // ── RETOUR AGENDA ─────────────────────────────────── ?>
        <nav class="event-nav fade-in-up" aria-label="Navigation événements">
            <a href="<?php echo esc_url(home_url('/agenda/')); ?>" class="event-nav__back">
                ← Retour à l'agenda
            </a>
            <?php
            $next = get_next_post();
            $prev = get_previous_post();
            if ($next) : ?>
                <a href="<?php echo esc_url(get_permalink($next)); ?>" class="event-nav__next">
                    <?php echo esc_html(get_the_title($next)); ?> →
                </a>
            <?php elseif ($prev) : ?>
                <a href="<?php echo esc_url(get_permalink($prev)); ?>" class="event-nav__next">
                    <?php echo esc_html(get_the_title($prev)); ?> →
                </a>
            <?php endif; ?>
        </nav>

    </div><!-- .event-body -->

</main>

<?php
endwhile;
get_footer();
?>
