<?php
/**
 * Template Name: Prize Wheel
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Roue de la Fortune - Mon Restaurant</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('prize-wheel-page'); ?>>

    <div class="wheel-container">
        
        <!-- Header / Logo -->
        <header class="wheel-header">
            <?php 
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
            if (has_custom_logo()) {
                echo '<img src="' . esc_url($logo[0]) . '" alt="' . get_bloginfo('name') . '" class="wheel-logo">';
            } else {
                echo '<h1>' . get_bloginfo('name') . '</h1>';
            }
            ?>
        </header>

        <!-- Step 1: Email Form -->
        <div id="step-email" class="wheel-step active">
            <h2>Tentez votre chance !</h2>
            <p>Entrez votre email pour tourner la roue et gagner une surprise.</p>
            
            <form id="email-form">
                <div class="form-group">
                    <input type="email" id="player-email" placeholder="Votre adresse email" required>
                </div>
                <button type="submit" class="btn-action">Continuer</button>
            </form>
            <p class="small-text">Une seule participation par personne.</p>
        </div>

        <!-- Step 2: Review Gate -->
        <div id="step-review" class="wheel-step">
            <h2>Presque là...</h2>
            <p>Pour débloquer la roue, donnez-nous un avis !</p>
            <p class="small-text">Cela nous aide énormément, merci ! ❤️</p>
            
            <a href="#" id="review-btn" class="btn-action btn-google" target="_blank">
                <span class="dashicons dashicons-star-filled"></span> Donner mon avis
            </a>
            
            <div id="review-check-msg" style="display:none; margin-top: 15px; font-size: 0.9em; color: #666;">
                Vérification en cours...
            </div>
        </div>

        <!-- Step 3: The Wheel -->
        <div id="step-wheel" class="wheel-step">
            <div class="canvas-wrapper">
                <div class="pointer-arrow"></div>
                <canvas id="canvas" width="350" height="350">
                    Canvas not supported, use another browser.
                </canvas>
            </div>
            
            <button id="spin-btn" class="btn-action">LANCER LA ROUE !</button>
        </div>

        <!-- Step 4: Result -->
        <div id="step-result" class="wheel-step">
            <div id="result-content">
                <div id="result-icon" style="font-size: 5rem; margin-bottom: 10px;"></div>
                <h2 id="result-title" style="font-size: 2.5rem; margin-bottom: 10px;"></h2>
                <div id="result-message" style="font-size: 1.3rem; margin-bottom: 30px; font-weight: 500;"></div>
                
                <div id="result-extra-info" class="small-text" style="margin-bottom: 30px;"></div>
                
                <a href="<?php echo home_url(); ?>" class="btn-action" style="background-color: #95a5a6;">Retour à l'accueil</a>
                <div class="confetti-container"></div>
            </div>
        </div>

        <!-- Error / Info Message -->
        <div id="wheel-message" class="wheel-message"></div>

    </div>

    <?php wp_footer(); ?>
</body>
</html>
