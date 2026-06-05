<?php
/* Template Name: Bon d'achat */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main id="primary" class="site-main voucher-landing">
    <!-- Hero Section Minimaliste -->
    <section class="voucher-hero-section">
        <div class="hero-carousel-bg">
            <div class="carousel-track">
                <?php
                $gallery_images = get_option('gastro_starter_homepage_gallery', []);
                if (!empty($gallery_images)) {
                    $carousel_images = array_slice($gallery_images, 0, 6);
                    foreach ($carousel_images as $image) {
                        $image_id = $image['id'];
                        $image_url = wp_get_attachment_image_url($image_id, 'large');
                        if ($image_url) {
                            echo '<div class="carousel-slide" style="background-image:url(' . esc_url($image_url) . ')"></div>';
                        }
                    }
                }
                ?>
            </div>
            <div class="hero-overlay"></div>
        </div>
        <div class="voucher-container hero-container">
            <div class="hero-content">
                <span class="hero-label">Bons d'achat</span>
                <h1 class="hero-title">Offrez un moment<br>au restaurant</h1>
                <p class="hero-pickup"><?php 
                    if (function_exists('gastro_starter_get_voucher_pickup_line')) {
                        echo esc_html(gastro_starter_get_voucher_pickup_line());
                    }
                ?></p>
                <p class="hero-subtitle">Un bon d'achat pour partager une expérience gourmande. Valable un an, personnalisable et envoyé instantanément.</p>
                
                <div class="hero-benefits">
                    <div class="benefit-item">Envoi immédiat</div>
                    <div class="benefit-item">Valable 1 an</div>
                    <div class="benefit-item">Paiement sécurisé</div>
                </div>
                
                <a href="#voucher-form" class="hero-cta">Commander un bon-cadeau</a>
            </div>
        </div>
    </section>

    <!-- Formulaire de commande -->
    <section class="voucher-form-section" id="voucher-form">
        <div class="voucher-container">
            <?php get_template_part('template-parts/voucher', 'form'); ?>
        </div>
    </section>

    <!-- Comment ça marche -->
    <section class="voucher-how-section">
        <div class="voucher-container">
            <h2 class="section-title">Comment ça marche</h2>
            <div class="steps-grid">
                <div class="step-item">
                    <div class="step-num">1</div>
                    <h3>Choisissez</h3>
                    <p>Sélectionnez le montant de votre bon d'achat</p>
                </div>
                <div class="step-item">
                    <div class="step-num">2</div>
                    <h3>Personnalisez</h3>
                    <p>Ajoutez un message pour le bénéficiaire</p>
                </div>
                <div class="step-item">
                    <div class="step-num">3</div>
                    <h3>Payez</h3>
                    <p>Paiement sécurisé par carte bancaire</p>
                </div>
                <div class="step-item">
                    <div class="step-num">4</div>
                    <h3>Recevez</h3>
                    <p>Le bon est envoyé instantanément par email</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Informations pratiques -->
    <section class="voucher-info-section">
        <div class="voucher-container">
            <div class="info-grid">
                <div class="info-block">
                    <h3>Validité</h3>
                    <p>Tous nos bons d'achat sont valables 1 an à partir de la date d'achat.</p>
                </div>
                <div class="info-block">
                    <h3>Utilisation</h3>
                    <p>Présentez simplement le code reçu par email lors de votre venue au restaurant.</p>
                </div>
                <div class="info-block">
                    <h3>Cumul</h3>
                    <p>Vous pouvez utiliser plusieurs bons d'achat lors d'une même visite.</p>
                </div>
                <div class="info-block">
                    <h3>Remboursement</h3>
                    <p>Les bons d'achat ne sont ni remboursables ni échangeables contre des espèces.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* Reset padding pour page bons-cadeaux */
.voucher-landing {
    padding: 0 !important;
    margin: 0 !important;
}

/* Variables héritées du thème */
:root {
    --voucher-spacing: 60px;
    --voucher-spacing-mobile: 40px;
}

/* Smooth scroll */
html {
    scroll-behavior: smooth;
}

/* Hero Section - Style minimaliste Mon Restaurant */
.voucher-hero-section {
    position: relative;
    padding: 120px 20px 80px;
    min-height: 70vh;
    display: flex;
    align-items: center;
    overflow: hidden;
}

.hero-carousel-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.hero-carousel-bg .carousel-track {
    display: flex;
    height: 100%;
    transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.hero-carousel-bg .carousel-slide {
    min-width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgb(0 0 0 / 75%) 0%, rgb(0 0 0 / 30%) 100%);
    z-index: 2;
}

.hero-container {
    position: relative;
    z-index: 3;
}

.voucher-container {
    max-width: 800px;
    margin: 0 auto;
}

.hero-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.hero-label {
    display: inline-block;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--color-warm-gray);
    margin-bottom: 20px;
    background: rgba(255,255,255,0.95);
    padding: 6px 16px;
    border-radius: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.hero-title {
    font-size: 48px;
    font-weight: 300;
    line-height: 1.3;
    color: var(--color-primary);
    margin: 0 0 20px 0;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 12px rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.85);
    padding: 12px 24px;
    display: inline-block;
    border-radius: 4px;
}

.hero-pickup {
    font-size: 18px;
    line-height: 1.5;
    color: var(--color-primary);
    font-weight: 400;
    margin: 20px auto 16px;
    font-style: italic;
    background: rgba(255,255,255,0.9);
    padding: 8px 20px;
    display: inline-block;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.hero-subtitle {
    font-size: 16px;
    line-height: 1.7;
    color: var(--color-warm-gray);
    max-width: 600px;
    margin: 0 auto 40px;
    font-weight: 400;
    background: rgba(255,255,255,0.9);
    padding: 16px 24px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.hero-benefits {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.benefit-item {
    font-size: 13px;
    color: var(--color-primary);
    letter-spacing: 0.5px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.95);
    border-radius: 4px;
    font-weight: 400;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.hero-cta {
    display: inline-block;
    margin-top: 32px;
    padding: 16px 40px;
    background: var(--color-primary);
    color: var(--color-white);
    text-decoration: none;
    font-size: 14px;
    font-weight: 400;
    letter-spacing: 1px;
    text-transform: uppercase;
    border: 1px solid var(--color-primary);
    transition: all 0.3s;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.hero-cta:hover {
    background: transparent;
    color: var(--color-primary);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    transform: translateY(-2px);
}

/* Form Section */
.voucher-form-section {
    padding: var(--voucher-spacing) 20px;
    background: var(--color-beige);
}

/* How It Works */
.voucher-how-section {
    padding: var(--voucher-spacing) 20px;
    background: var(--color-white);
    border-top: 1px solid var(--color-beige-dark);
    border-bottom: 1px solid var(--color-beige-dark);
}

.section-title {
    text-align: center;
    font-size: 24px;
    font-weight: 300;
    margin-bottom: 50px;
    color: var(--color-primary);
    letter-spacing: 0.5px;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 40px;
    max-width: 700px;
    margin: 0 auto;
}

.step-item {
    text-align: center;
}

.step-num {
    width: 40px;
    height: 40px;
    margin: 0 auto 20px;
    border: 1px solid var(--color-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 400;
    color: var(--color-primary);
}

.step-item h3 {
    font-size: 16px;
    font-weight: 400;
    margin-bottom: 12px;
    color: var(--color-primary);
}

.step-item p {
    font-size: 14px;
    color: var(--color-warm-gray);
    line-height: 1.6;
    font-weight: 300;
}

/* Info Section */
.voucher-info-section {
    padding: var(--voucher-spacing) 20px;
    background: var(--color-cream);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    max-width: 700px;
    margin: 0 auto;
}

.info-block {
    padding: 25px;
    background: var(--color-white);
    border: 1px solid var(--color-beige-dark);
}

.info-block h3 {
    font-size: 14px;
    font-weight: 400;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    color: var(--color-primary);
    text-transform: uppercase;
}

.info-block p {
    font-size: 14px;
    color: var(--color-warm-gray);
    line-height: 1.6;
    margin: 0;
    font-weight: 300;
}

/* Responsive */
@media (max-width: 768px) {
    .voucher-hero-section {
        padding: 80px 20px 60px;
        min-height: 60vh;
    }
    
    .hero-title {
        font-size: 36px;
        padding: 12px 20px;
    }
    
    .hero-pickup {
        font-size: 17px;
        padding: 8px 18px;
    }
    
    .hero-subtitle {
        font-size: 16px;
        padding: 14px 20px;
        font-weight: 400;
    }
    
    .hero-label {
        font-size: 11px;
    }
    
    .hero-benefits {
        gap: 16px;
    }
    
    .benefit-item {
        font-size: 13px;
        padding: 8px 14px;
    }
    
    .hero-cta {
        font-size: 14px;
        padding: 14px 32px;
    }
    
    .steps-grid,
    .info-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .voucher-form-section,
    .voucher-how-section,
    .voucher-info-section {
        padding: var(--voucher-spacing-mobile) 20px;
    }
    
    .steps-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const track = document.querySelector('.hero-carousel-bg .carousel-track');
    const slides = document.querySelectorAll('.hero-carousel-bg .carousel-slide');
    
    if (!track || slides.length === 0) return;
    
    let currentIndex = 0;
    const totalSlides = slides.length;
    
    // Auto-rotation toutes les 5 secondes
    setInterval(function() {
        currentIndex = (currentIndex + 1) % totalSlides;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
    }, 5000);
});
</script>

<?php get_footer(); ?>


