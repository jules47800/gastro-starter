<?php
/**
 * Template pour l'affichage des plats du menu
 *
 * @package Gastro_Starter
 */

$price = get_post_meta(get_the_ID(), 'price', true);
$ingredients = get_post_meta(get_the_ID(), 'ingredients', true);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('menu-dish'); ?>>
    <div class="menu-dish-container">
        <?php if (has_post_thumbnail()) : ?>
            <div class="menu-dish-image">
                <?php the_post_thumbnail('gastro-starter-menu'); ?>
            </div>
        <?php endif; ?>
        
        <div class="menu-dish-content">
            <header class="menu-dish-header">
                <h2 class="menu-dish-title"><?php the_title(); ?></h2>
                <?php if ($price) : ?>
                    <span class="menu-dish-price"><?php echo esc_html($price); ?>€</span>
                <?php endif; ?>
            </header>
            
            <div class="menu-dish-description">
                <?php the_excerpt(); ?>
            </div>
            
            <?php if ($ingredients) : ?>
                <div class="menu-dish-ingredients">
                    <p><?php echo esc_html__('Ingrédients:', 'gastro-starter'); ?> <?php echo esc_html($ingredients); ?></p>
                </div>
            <?php endif; ?>
            
            <footer class="menu-dish-footer">
                <?php
                $categories = get_the_terms(get_the_ID(), 'menu_category');
                if ($categories && !is_wp_error($categories)) :
                    ?>
                    <div class="menu-dish-categories">
                        <?php foreach ($categories as $category) : ?>
                            <span class="menu-dish-category"><?php echo esc_html($category->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </footer>
        </div>
    </div>
</article><!-- #post-<?php the_ID(); ?> --> 