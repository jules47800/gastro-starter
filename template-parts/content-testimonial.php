<?php
/**
 * Template pour l'affichage des témoignages - Version modernisée
 *
 * @package Gastro_Starter
 */

// Récupération des métadonnées
$rating = get_post_meta(get_the_ID(), 'rating', true);
$author = get_post_meta(get_the_ID(), 'author_name', true);
$author_location = get_post_meta(get_the_ID(), 'author_location', true);
$visit_date = get_post_meta(get_the_ID(), 'visit_date', true);
$review_date = get_post_meta(get_the_ID(), 'review_date', true);
$source = get_post_meta(get_the_ID(), 'testimonial_source', true);
$source_url = get_post_meta(get_the_ID(), 'source_url', true);
$verified = get_post_meta(get_the_ID(), 'verified_review', true);
$featured = get_post_meta(get_the_ID(), 'featured_review', true);
$helpful_count = get_post_meta(get_the_ID(), 'helpful_count', true);
$language = get_post_meta(get_the_ID(), 'review_language', true);

// Permettre aux développeurs de filtrer les métadonnées
$rating = apply_filters('gastro_starter_testimonial_rating', $rating, get_the_ID());
$author = apply_filters('gastro_starter_testimonial_author', $author, get_the_ID());

// Classes CSS personnalisées
$testimonial_class = 'testimonial-item';
if ($source) {
    $testimonial_class .= ' source-' . sanitize_html_class(strtolower($source));
}
if ($featured) {
    $testimonial_class .= ' featured-review';
}
if ($verified) {
    $testimonial_class .= ' verified-review';
}

// Formatage des dates
$formatted_visit_date = '';
$formatted_review_date = '';

if ($visit_date) {
    $visit_timestamp = strtotime($visit_date);
    if ($visit_timestamp) {
        $formatted_visit_date = date_i18n('F Y', $visit_timestamp);
    }
}

if ($review_date) {
    $review_timestamp = strtotime($review_date);
    if ($review_timestamp) {
        $formatted_review_date = date_i18n('j F Y', $review_timestamp);
    }
}

// Configuration des sources
$sources_config = array(
    'google' => array(
        'name' => 'Google Reviews',
        'logo' => 'google.png',
        'color' => '#4285f4'
    ),
    'tripadvisor' => array(
        'name' => 'TripAdvisor',
        'logo' => 'tripadvisor.png',
        'color' => '#00aa6c'
    ),
    'booking' => array(
        'name' => 'Booking.com',
        'logo' => 'booking.png',
        'color' => '#003580'
    ),
    'yelp' => array(
        'name' => 'Yelp',
        'logo' => 'yelp.png',
        'color' => '#ff1744'
    ),
    'facebook' => array(
        'name' => 'Facebook',
        'logo' => 'facebook.png',
        'color' => '#1877f2'
    ),
    'foursquare' => array(
        'name' => 'Foursquare',
        'logo' => 'foursquare.png',
        'color' => '#f94877'
    ),
    'opentable' => array(
        'name' => 'OpenTable',
        'logo' => 'opentable.png',
        'color' => '#da3743'
    ),
    'lafourchette' => array(
        'name' => 'LaFourchette',
        'logo' => 'lafourchette.png',
        'color' => '#2fc7b8'
    )
);

$source_config = isset($sources_config[$source]) ? $sources_config[$source] : null;
?>

<article id="post-<?php the_ID(); ?>" <?php post_class($testimonial_class); ?> itemscope itemtype="https://schema.org/Review">
    
    <?php do_action('gastro_starter_testimonial_before_content'); ?>
    
    <div class="testimonial-card">
        <!-- En-tête du témoignage -->
        <div class="testimonial-header">
            <div class="testimonial-source-info">
                <?php if ($source_config) : ?>
                    <div class="source-badge" style="--source-color: <?php echo esc_attr($source_config['color']); ?>">
                        <?php
                        $logo_path = get_template_directory_uri() . '/assets/images/sources/' . $source_config['logo'];
                        $fallback_logo = get_template_directory_uri() . '/assets/images/' . $source_config['logo'];
                        ?>
                        <img src="<?php echo esc_url($logo_path); ?>" 
                             onerror="this.src='<?php echo esc_url($fallback_logo); ?>'"
                             alt="<?php echo esc_attr($source_config['name']); ?>" 
                             class="source-logo">
                        <span class="source-name"><?php echo esc_html($source_config['name']); ?></span>
                    </div>
                <?php elseif ($source === 'direct') : ?>
                    <div class="source-badge source-direct">
                        <span class="dashicons dashicons-edit"></span>
                        <span class="source-name"><?php _e('Livre d\'or', 'gastro-starter'); ?></span>
                    </div>
                <?php elseif ($source === 'autre' || $source) : ?>
                    <div class="source-badge source-other">
                        <span class="dashicons dashicons-star-filled"></span>
                        <span class="source-name"><?php echo esc_html($source); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($verified) : ?>
                    <span class="verified-badge" title="<?php _e('Avis vérifié', 'gastro-starter'); ?>">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Vérifié', 'gastro-starter'); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($featured) : ?>
                    <span class="featured-badge" title="<?php _e('Avis en vedette', 'gastro-starter'); ?>">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php _e('Vedette', 'gastro-starter'); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($rating && is_numeric($rating)) : ?>
                <div class="rating" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                    <meta itemprop="ratingValue" content="<?php echo esc_attr($rating); ?>">
                    <meta itemprop="bestRating" content="5">
                    <div class="stars-container">
                        <span class="stars" aria-label="<?php echo esc_attr(sprintf(__('Note de %s sur 5', 'gastro-starter'), $rating)); ?>">
                            <?php
                            $rating = min(5, max(0, intval($rating)));
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<span class="star star-filled" aria-hidden="true">★</span>';
                                } else {
                                    echo '<span class="star star-empty" aria-hidden="true">☆</span>';
                                }
                            }
                            ?>
                        </span>
                        <span class="rating-text"><?php echo esc_html($rating . '/5'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Contenu du témoignage -->
        <div class="testimonial-content">
            <blockquote itemprop="reviewBody">
                <?php the_content(); ?>
            </blockquote>
        </div>
        
        <!-- Pied du témoignage -->
        <footer class="testimonial-footer">
            <div class="author-section">
                <div class="author-info">
                    <?php if ($author) : ?>
                        <cite class="author-name" itemprop="author"><?php echo esc_html($author); ?></cite>
                    <?php endif; ?>
                    
                    <?php if ($author_location) : ?>
                        <span class="author-location">
                            <span class="dashicons dashicons-location"></span>
                            <?php echo esc_html($author_location); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($formatted_visit_date || $formatted_review_date) : ?>
                    <div class="date-info">
                        <?php if ($formatted_visit_date) : ?>
                            <span class="visit-date">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php echo esc_html(sprintf(__('Visité en %s', 'gastro-starter'), $formatted_visit_date)); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($formatted_review_date) : ?>
                            <span class="review-date" itemprop="datePublished" content="<?php echo esc_attr($review_date); ?>">
                                <?php echo esc_html($formatted_review_date); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="testimonial-actions">
                <?php if ($helpful_count && $helpful_count > 0) : ?>
                    <span class="helpful-count">
                        <span class="dashicons dashicons-thumbs-up"></span>
                        <?php echo esc_html(sprintf(_n('%d personne trouve cet avis utile', '%d personnes trouvent cet avis utile', $helpful_count, 'gastro-starter'), $helpful_count)); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($source_url) : ?>
                    <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener" class="view-original-link">
                        <span class="dashicons dashicons-external"></span>
                        <?php _e('Voir l\'avis original', 'gastro-starter'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </footer>
    </div>
    
    <?php do_action('gastro_starter_testimonial_after_content'); ?>
    
</article>

<style>
.testimonial-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 24px;
    margin-bottom: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e2e4e7;
    position: relative;
    overflow: hidden;
}

.testimonial-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.featured-review .testimonial-card {
    border-color: #b5a692;
    box-shadow: 0 4px 12px rgba(181, 166, 146, 0.3);
}

.featured-review .testimonial-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #b5a692, #d8cfc0);
}

/* En-tête du témoignage */
.testimonial-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.testimonial-source-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.source-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f8f9fa;
    border-radius: 20px;
    border: 1px solid var(--source-color, #ddd);
    font-size: 12px;
    font-weight: 600;
    color: var(--source-color, #666);
}

.source-badge .source-logo {
    width: 16px;
    height: 16px;
    object-fit: contain;
}

.source-direct,
.source-other {
    background: #f5f1ea;
    color: #b5a692;
    border-color: #b5a692;
}

.verified-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #f0f8f0;
    color: #2d5a2d;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.featured-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #f5f1ea;
    color: #8b7355;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* Notation */
.rating {
    text-align: right;
}

.stars-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stars {
    color: #d4af37;
    font-size: 18px;
    line-height: 1;
}

.star-empty {
    color: #ddd;
}

.rating-text {
    font-size: 14px;
    font-weight: 600;
    color: #666;
}

/* Contenu */
.testimonial-content {
    margin: 20px 0;
}

.testimonial-content blockquote {
    margin: 0;
    padding: 0;
    border: none;
    font-size: 16px;
    line-height: 1.6;
    color: #333;
    font-style: normal;
}

/* Pied du témoignage */
.testimonial-footer {
    border-top: 1px solid #f1f1f1;
    padding-top: 16px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}

.author-section {
    flex: 1;
}

.author-info {
    margin-bottom: 8px;
}

.author-name {
    font-weight: 600;
    color: #23282d;
    font-style: normal;
    font-size: 15px;
}

.author-location {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #666;
    font-size: 13px;
    margin-top: 4px;
}

.date-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: #999;
}

.visit-date {
    display: flex;
    align-items: center;
    gap: 4px;
}

.testimonial-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.helpful-count {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #666;
}

.view-original-link {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #b5a692;
    text-decoration: none;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.view-original-link:hover {
    background-color: rgba(181, 166, 146, 0.1);
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .testimonial-card {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .testimonial-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .rating {
        text-align: left;
    }
    
    .testimonial-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .testimonial-actions {
        align-items: flex-start;
    }
    
    .source-badge {
        font-size: 11px;
        padding: 4px 8px;
    }
}
</style> 