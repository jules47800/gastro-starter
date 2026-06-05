<?php
/**
 * Le modèle pour l'affichage de l'agenda (Archives Événements)
 *
 * @package Gastro_Starter
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="container">
        
        <header class="page-header event-header">
            <h1 class="page-title fade-in"><?php esc_html_e('L\'Agenda', 'gastro-starter'); ?></h1>
            <p class="page-subtitle fade-in delay-1"><?php esc_html_e('Nos soirées spéciales & événements "Soirées Spéciales"', 'gastro-starter'); ?></p>
        </header>

        <?php if (have_posts()) : ?>
            <div class="event-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    $event_date = get_post_meta(get_the_ID(), 'event_date', true);
                    $event_time = get_post_meta(get_the_ID(), 'event_time', true);
                    $event_price = get_post_meta(get_the_ID(), 'event_price', true);
                    $event_status = get_post_meta(get_the_ID(), 'event_status', true);
                    $event_menu_url = get_post_meta(get_the_ID(), 'event_menu_url', true);
                    
                    // Formatage de la date
                    $date_obj = !empty($event_date) ? new DateTime($event_date) : null;
                    $day = $date_obj ? $date_obj->format('d') : '';
                    $month = $date_obj ? date_i18n('M', $date_obj->getTimestamp()) : '';
                    
                    // Classe de statut
                    $status_class = 'status-' . ($event_status ? $event_status : 'open');
                    ?>

                    <article id="post-<?php the_ID(); ?>" <?php post_class('event-card fade-in-up'); ?>>
                        <div class="event-card__image">
                            <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('large'); ?>
                            <?php else : ?>
                                <div class="event-card__placeholder" style="height:200px;background:#e8e3d9;"></div>
                            <?php endif; ?>
                            </a>
                            
                            <?php if ($date_obj) : ?>
                                <div class="event-date-badge">
                                    <span class="event-day"><?php echo esc_html($day); ?></span>
                                    <span class="event-month"><?php echo esc_html($month); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($event_status === 'full') : ?>
                                <div class="event-status-badge status-full"><?php esc_html_e('Complet', 'gastro-starter'); ?></div>
                            <?php elseif ($event_status === 'closed') : ?>
                                <div class="event-status-badge status-closed"><?php esc_html_e('Terminé', 'gastro-starter'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="event-card__content">
                            <h2 class="event-title">
                                <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a>
                            </h2>
                            
                            <div class="event-meta">
                                <?php if ($date_obj) : ?>
                                    <div class="meta-item meta-item--date">
                                        <?php echo esc_html(ucfirst(date_i18n('l j F Y', $date_obj->getTimestamp()))); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($event_time) : ?>
                                    <div class="meta-item">
                                        <?php
                                        $t = explode(':', $event_time);
                                        echo esc_html($t[0] . 'h' . ($t[1] !== '00' ? $t[1] : ''));
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($event_price) : ?>
                                    <div class="meta-item">
                                        <?php echo esc_html($event_price); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="event-description">
                                <?php the_excerpt(); ?>
                            </div>

                            <div class="event-actions">
                                <?php if ($event_menu_url) : ?>
                                    <a href="<?php echo esc_url($event_menu_url); ?>" class="event-link" target="_blank">
                                        <?php esc_html_e('Voir le menu', 'gastro-starter'); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($event_status === 'open' || empty($event_status)) : 
                                    $reservation_url = home_url('/reserver');
                                    if ($event_date) {
                                        $formatted_date = date('d/m/Y', strtotime($event_date));
                                        $reservation_url = add_query_arg('date', $formatted_date, $reservation_url);
                                    }
                                    if ($event_time) {
                                        $reservation_url = add_query_arg('time', $event_time, $reservation_url);
                                    }
                                    // On pourrait aussi ajouter people par défaut si souhaité
                                    // $reservation_url = add_query_arg('people', 2, $reservation_url);
                                ?>
                                    <a href="<?php echo esc_url($reservation_url); ?>" class="button event-button">
                                        <?php esc_html_e('Réserver', 'gastro-starter'); ?>
                                    </a>
                                <?php else : ?>
                                    <button class="button event-button disabled" disabled>
                                        <?php echo ($event_status === 'full') ? esc_html__('Complet', 'gastro-starter') : esc_html__('Fermé', 'gastro-starter'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                <?php endwhile; ?>
            </div>

            <?php the_posts_navigation(); ?>

        <?php else : ?>

            <div class="no-events fade-in">
                <p><?php esc_html_e('Aucun événement n\'est prévu pour le moment. Revenez bientôt !', 'gastro-starter'); ?></p>
            </div>

        <?php endif; ?>
    </div>
</main>

<?php
get_footer();
