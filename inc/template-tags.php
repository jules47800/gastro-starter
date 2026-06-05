<?php
/**
 * Fonctions de template personnalisées pour ce thème
 *
 * @package Gastro_Starter
 */

if (!function_exists('gastro_starter_posted_on')) :
	/**
	 * Affiche la date de publication du post.
	 */
	function gastro_starter_posted_on() {
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
		if (get_the_time('U') !== get_the_modified_time('U')) {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
		}

		$time_string = sprintf(
			$time_string,
			esc_attr(get_the_date(DATE_W3C)),
			esc_html(get_the_date()),
			esc_attr(get_the_modified_date(DATE_W3C)),
			esc_html(get_the_modified_date())
		);

		echo '<span class="posted-on">' . $time_string . '</span>';
	}
endif;

if (!function_exists('gastro_starter_posted_by')) :
	/**
	 * Affiche le nom de l'auteur.
	 */
	function gastro_starter_posted_by() {
		echo '<span class="byline"> ' . esc_html__('par', 'gastro-starter') . ' <span class="author vcard"><a class="url fn n" href="' . esc_url(get_author_posts_url(get_the_author_meta('ID'))) . '">' . esc_html(get_the_author()) . '</a></span></span>';
	}
endif;

if (!function_exists('gastro_starter_entry_footer')) :
	/**
	 * Affiche les tags et catégories pour les posts.
	 */
	function gastro_starter_entry_footer() {
		// Masquer pour les pages.
		if ('post' === get_post_type()) {
			$categories_list = get_the_category_list(esc_html__(', ', 'gastro-starter'));
			if ($categories_list) {
				printf('<span class="cat-links">' . esc_html__('Catégories: %1$s', 'gastro-starter') . '</span>', $categories_list);
			}

			$tags_list = get_the_tag_list('', esc_html_x(', ', 'list item separator', 'gastro-starter'));
			if ($tags_list) {
				printf('<span class="tags-links">' . esc_html__('Tags: %1$s', 'gastro-starter') . '</span>', $tags_list);
			}
		}

		if (!is_single() && !post_password_required() && (comments_open() || get_comments_number())) {
			echo '<span class="comments-link">';
			comments_popup_link(
				sprintf(
					wp_kses(
						/* translators: %s: post title */
						__('Laisser un commentaire<span class="screen-reader-text"> sur %s</span>', 'gastro-starter'),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post(get_the_title())
				)
			);
			echo '</span>';
		}

		edit_post_link(
			sprintf(
				wp_kses(
					/* translators: %s: Name of current post. Only visible to screen readers */
					__('Modifier<span class="screen-reader-text">%s</span>', 'gastro-starter'),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				wp_kses_post(get_the_title())
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
endif;

if (!function_exists('gastro_starter_post_thumbnail')) :
	/**
	 * Affiche l'image mise en avant pour un post.
	 */
	function gastro_starter_post_thumbnail() {
		if (post_password_required() || is_attachment() || !has_post_thumbnail()) {
			return;
		}

		if (is_singular()) :
			?>

			<div class="post-thumbnail">
				<?php the_post_thumbnail(); ?>
			</div><!-- .post-thumbnail -->

		<?php else : ?>

			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php
				the_post_thumbnail(
					'post-thumbnail',
					array(
						'alt' => the_title_attribute(
							array(
								'echo' => false,
							)
						),
					)
				);
				?>
			</a>

			<?php
		endif;
	}
endif;

/**
 * Retourne une image mise en avant avec une taille spécifique
 */
function gastro_starter_get_featured_image($post_id, $size = 'thumbnail') {
    if (has_post_thumbnail($post_id)) {
        $image_id = get_post_thumbnail_id($post_id);
        $image = wp_get_attachment_image_src($image_id, $size);
        return $image[0];
    }
    return '';
}

/**
 * Affiche une pagination propre
 */
function gastro_starter_pagination() {
    $args = array(
        'prev_text' => '<span class="nav-prev">' . esc_html__('« Précédent', 'gastro-starter') . '</span>',
        'next_text' => '<span class="nav-next">' . esc_html__('Suivant »', 'gastro-starter') . '</span>',
    );
    the_posts_pagination($args);
} 