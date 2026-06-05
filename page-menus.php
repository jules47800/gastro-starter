<?php
/**
 * Template Name: Tous nos menus
 * Description: Affiche tous les menus uploadés sous forme de slider élégant.
 *
 * @package Gastro_Starter
 */

get_header();
?>

<main id="primary" class="site-main">
    <section class="section menu-list-section">
        <div class="container">
            <div class="section-header" style="text-align:center;">
                <span class="section-tag"><?php echo esc_html__('Notre carte', 'gastro-starter'); ?></span>
                <h2 class="section-title"><?php echo esc_html__('Tous nos menus', 'gastro-starter'); ?></h2>
                <p style="max-width:600px;margin:0 auto 30px;">
                    <?php echo esc_html__('Découvrez l\'ensemble de nos menus, à télécharger ou consulter en ligne.', 'gastro-starter'); ?>
                </p>
            </div>
            <?php
            $args = array(
                'post_type'      => 'daily_menu',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            );
            $menus_query = new WP_Query($args);
            if ($menus_query->have_posts()) : $menus_query->the_post();
                $menu_pdf = get_post_meta(get_the_ID(), '_menu_pdf', true);
                $file_type = '';
                if ($menu_pdf) {
                    $file_info = pathinfo($menu_pdf);
                    $file_type = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
                }
            ?>
                <div class="menu-card" style="background:#fff;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,0.07);padding:30px 20px;text-align:center;max-width:450px;margin:0 auto;">
                    <h3 style="font-size:1.3rem;font-weight:500;margin-bottom:10px;"><?php the_title(); ?></h3>
                    <div style="color:#b5a692;font-size:0.95rem;margin-bottom:15px;">
                        <?php echo get_the_date(); ?>
                    </div>
                    <?php if ($menu_pdf): ?>
                        <?php if (in_array($file_type, ['jpg','jpeg','png'])): ?>
                            <div style="margin-bottom:15px;">
                                <img src="<?php echo esc_url($menu_pdf); ?>" alt="<?php the_title_attribute(); ?>" style="max-width:100%;height:auto;max-height:550px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.08);object-fit:cover;">
                            </div>
                        <?php elseif ($file_type === 'pdf'): ?>
                            <div style="margin-bottom:15px;">
                                <span style="font-size:2.5rem;">📄</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="color:#888;"><?php echo esc_html__('Aucun fichier disponible pour ce menu.', 'gastro-starter'); ?></div>
                    <?php endif; ?>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else: ?>
                <p style="text-align:center;"><?php echo esc_html__('Aucun menu disponible pour le moment.', 'gastro-starter'); ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php get_footer(); ?>