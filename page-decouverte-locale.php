<?php
/**
 * Template pour la page "Découvrir Notre Ville"
 * Template Name: Découvrir Notre Ville
 *
 * @package Gastro_Starter
 */

get_header();
?>

<main id="primary" class="site-main eymet-page">
    <!-- Hero visuel plein écran -->
    <section class="eymet-hero" style="--eymet-hero:url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/town-square.jpg' ); ?>');">
        <div class="eymet-hero__overlay"></div>
        <div class="eymet-hero__inner">
            <h1 class="eymet-hero__title"><?php echo esc_html__("Découvrez notre ville", 'gastro-starter'); ?></h1>
            <p class="eymet-hero__subtitle"><?php echo esc_html(get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant') . ', ' . __("au cœur de la ville.", 'gastro-starter')); ?></p>
            <div class="eymet-hero__actions">
                <a class="hero-card__button hero-card__button--primary" href="<?php echo esc_url( home_url('/reserver/') ); ?>"><?php echo esc_html__('Réserver', 'gastro-starter'); ?></a>
                <?php
                $phone_display = get_theme_mod('gastro_starter_restaurant_phone', '01 23 45 67 89');
                $phone_href = preg_replace('/\s+/', '', $phone_display);
                ?>
                <a class="hero-card__button" href="tel:<?php echo esc_attr($phone_href); ?>">
                    <?php echo esc_html__('Contact : ', 'gastro-starter'); ?><?php echo esc_html($phone_display); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Accroches rapides / cartes -->
    <section class="eymet-highlights">
        <div class="eymet-grid">
            <article class="eymet-card">
                <h3><?php echo esc_html__('Notre patrimoine', 'gastro-starter'); ?></h3>
                <p><?php echo esc_html__('Une ville riche en histoire et en architecture, à découvrir à deux pas du restaurant.', 'gastro-starter'); ?></p>
            </article>
            <article class="eymet-card">
                <h3><?php echo esc_html__('Le marché local', 'gastro-starter'); ?></h3>
                <p><?php echo esc_html__('Producteurs locaux, couleurs et ambiance chaleureuse — les saveurs de notre terroir.', 'gastro-starter'); ?></p>
            </article>
            <article class="eymet-card">
                <h3><?php echo esc_html__('Les environs', 'gastro-starter'); ?></h3>
                <p><?php echo esc_html__('Vignobles, rivières, villages classés — de belles escapades à portée de main.', 'gastro-starter'); ?></p>
            </article>
        </div>
    </section>

    <!-- Histoire brève -->
    <section class="eymet-story">
        <div class="eymet-story__inner">
            <h2><?php echo esc_html__("Notre histoire", 'gastro-starter'); ?></h2>
            <div class="eymet-story__content">
                <p><strong><?php echo esc_html__('Les origines', 'gastro-starter'); ?></strong><br><?php echo esc_html__("Découvrez l'histoire de notre ville et de ses traditions culinaires.", 'gastro-starter'); ?></p>
                <p><strong><?php echo esc_html__('Le terroir', 'gastro-starter'); ?></strong><br><?php echo esc_html__("Un patrimoine gastronomique riche, des produits locaux d'exception.", 'gastro-starter'); ?></p>
                <p><strong><?php echo esc_html__("Aujourd'hui", 'gastro-starter'); ?></strong><br><?php echo esc_html__("Une destination vivante : marché animé, événements culturels, et une belle table pour vous accueillir.", 'gastro-starter'); ?></p>
            </div>
        </div>
    </section>

    <!-- Mini galerie -->
    <section class="eymet-gallery">
        <div class="eymet-gallery__grid">
            <?php
            $images = [
                'town-square.jpg',
                'restaurant-exterior.jpg',
                'ambiance-soiree-restaurant.jpg',
                'terrasse-restaurant.jpg',
                'restaurant-interieur-ambiance.jpg',
                'produits-locaux.jpg',
            ];
            foreach ($images as $img) :
                $src = get_template_directory_uri() . '/assets/images/' . $img;
            ?>
                <figure class="eymet-gallery__item">
                    <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr(sprintf(__('%s', 'gastro-starter'), pathinfo($img, PATHINFO_FILENAME))); ?>" loading="lazy" />
                </figure>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Plan d'accès -->
    <section class="eymet-map">
        <div class="eymet-map__inner">
            <h2><?php echo esc_html__('Nous trouver', 'gastro-starter'); ?></h2>
            <p class="eymet-map__address"><?php echo nl2br(esc_html( get_theme_mod('gastro_starter_restaurant_address', "1 rue de la Gastronomie\n75001 Paris") )); ?></p>
            <div class="eymet-map__frame">
                <?php
                $map_query = urlencode(get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris'));
                ?>
                <iframe title="<?php echo esc_attr(sprintf(__('Carte — %s', 'gastro-starter'), get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant'))); ?>" src="https://www.google.com/maps?q=<?php echo $map_query; ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <!-- Appel à l'action -->
    <section class="eymet-cta">
        <div class="eymet-cta__inner">
            <h2><?php echo esc_html__("Envie de découvrir notre cuisine ?", 'gastro-starter'); ?></h2>
            <p><?php echo esc_html__("Réservez votre table et profitez d'un moment gourmand.", 'gastro-starter'); ?></p>
            <div class="eymet-cta__actions">
                <a class="hero-card__button hero-card__button--primary" href="<?php echo esc_url( home_url('/reserver/') ); ?>"><?php echo esc_html__('Réserver maintenant', 'gastro-starter'); ?></a>
                <a class="hero-card__button" href="<?php echo esc_url( home_url('/') ); ?>"><?php echo esc_html__("Retour à l'accueil", 'gastro-starter'); ?></a>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
