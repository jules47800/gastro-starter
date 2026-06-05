<?php
/**
 * Footer ultra-minimaliste
 * @package Gastro_Starter
 */
?>

    </main><!-- #main -->

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <?php
                    $custom_logo_id = get_theme_mod('custom_logo');
                    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : get_template_directory_uri() . '/assets/images/logo.png';
                    ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant')); ?>" style="height: 40px; width: auto; margin-bottom: 10px;">
                    <p><?php echo esc_html(get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris')); ?></p>
                </div>

                <div class="footer-hours">
                    <?php
                    if (function_exists('gastro_starter_display_opening_hours')) {
                        gastro_starter_display_opening_hours();
                    }
                    ?>
                </div>

                <div class="footer-contact">
                    <p>T: <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00'))); ?>"><?php echo esc_html(get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00')); ?></a></p>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-links">
                    <a href="<?php echo home_url('/reserver/'); ?>">Réservation</a>
                    <a href="<?php echo home_url('/bon-achat/'); ?>">Bons-cadeaux</a>
                    <a href="<?php echo esc_url(get_theme_mod('gastro_starter_instagram_url', 'https://instagram.com/mon-restaurant')); ?>" target="_blank" rel="noopener">Instagram</a>
                    <a href="mailto:<?php echo esc_attr(get_theme_mod('gastro_starter_restaurant_email', 'contact@mon-restaurant.fr')); ?>">Mail</a>
                </div>
            </div>
        </div>
    </footer>

</div><!-- #page -->

<!-- Image Modal -->
<div id="image-modal" class="image-modal-overlay">
    <span class="close-modal-btn">&times;</span>
    <img class="modal-content" id="modal-image-content">
    <div id="modal-caption"></div>
</div>

<?php wp_footer(); ?>

</body>
</html> 