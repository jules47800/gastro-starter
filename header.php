<?php
/**
 * Header moderne - Design épuré inspiré de Chéri Bibi
 * @package Gastro_Starter
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<!-- Preconnect pour optimiser les performances -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

	<!-- Balises meta essentielles -->
	<meta name="author" content="<?php echo esc_attr(get_bloginfo('name')); ?>">

	<!-- Favicon -->
	<link rel="icon" type="image/x-icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.ico">

	<?php
	$fb_pixel_id = get_theme_mod('gastro_starter_facebook_pixel_id', '');
	if ($fb_pixel_id) : ?>
	<!-- Meta Pixel Code -->
	<script>
	!function(f,b,e,v,n,t,s)
	{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};
	if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
	n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];
	s.parentNode.insertBefore(t,s)}(window, document,'script',
	'https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', '<?php echo esc_js($fb_pixel_id); ?>');
	fbq('track', 'PageView');
	</script>
	<noscript><img height="1" width="1" style="display:none"
	src="https://www.facebook.com/tr?id=<?php echo esc_attr($fb_pixel_id); ?>&ev=PageView&noscript=1"
	/></noscript>
	<!-- End Meta Pixel Code -->
	<?php endif; ?>



	<?php wp_head(); ?>
	
	
	<style>
		/* Header ultra-minimaliste */
		.site-header {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			z-index: 1000;
			background: rgba(255, 255, 255, 0.95);
			backdrop-filter: blur(10px);
			padding: var(--spacing-md) 0;
			transition: var(--transition);
		}
		
		.site-branding {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.site-logo a {
			font-size: 1.2rem;
			font-weight: var(--font-weight-light);
			letter-spacing: 2px;
			text-transform: uppercase;
		}
		
		.main-navigation ul {
			display: flex;
			list-style: none;
			gap: var(--spacing-lg);
			margin: 0;
			padding: 0;
		}
		
		.main-navigation a {
			font-size: 0.85rem;
			font-weight: var(--font-weight-normal);
			letter-spacing: 1px;
			text-transform: uppercase;
		}
		
		/* Hamburger menu */
		.mobile-menu-toggle {
			display: none;
			background: none;
			border: none;
			cursor: pointer;
			padding: var(--spacing-xs);
		}
		
		.hamburger-line {
			display: block;
			width: 18px;
			height: 1px;
			background: var(--color-black);
			margin: 4px 0;
			transition: var(--transition);
		}
		
		.mobile-menu-toggle.active .hamburger-line:nth-child(1) {
			transform: rotate(45deg) translate(4px, 4px);
		}
		
		.mobile-menu-toggle.active .hamburger-line:nth-child(2) {
			opacity: 0;
		}
		
		.mobile-menu-toggle.active .hamburger-line:nth-child(3) {
			transform: rotate(-45deg) translate(5px, -5px);
		}
		
		@media (max-width: 768px) {
			.mobile-menu-toggle {
				display: block;
			}
			
			.main-navigation {
				display: none;
				position: absolute;
				top: 100%;
				left: 0;
				right: 0;
				background: var(--color-white);
				padding: var(--spacing-md);
				box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
			}
			
			.main-navigation.active {
				display: block;
			}
			
			.main-navigation ul {
				flex-direction: column;
				gap: var(--spacing-md);
				text-align: center;
			}
		}
		
		/* Animation du header au scroll */
		.site-header.scrolled {
			background: rgba(255, 255, 255, 0.98);
			box-shadow: 0 1px 20px rgba(0, 0, 0, 0.05);
		}
        .language-switcher {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-right: var(--spacing-lg);
        }
        .language-switcher a {
            color: var(--color-text-light);
            text-decoration: none;
        }
        .language-switcher .current-lang {
            font-weight: var(--font-weight-bold);
            color: var(--color-text);
        }
        .language-switcher .lang-separator {
            display: none;
        }

        .flag-icon {
            width: 24px;
            height: 16px;
            display: inline-block;
            vertical-align: middle;
            transition: var(--transition);
            border-radius: 2px;
            box-shadow: 0 0 2px rgba(0,0,0,0.15);
        }

        .language-switcher a .flag-icon {
            filter: grayscale(1);
            opacity: 0.6;
        }

        .language-switcher a:hover .flag-icon {
            filter: grayscale(0);
            opacity: 1;
        }

        .current-lang .flag-icon {
            filter: grayscale(0);
        }

        .header-right-group {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
        }

        @media (max-width: 768px) {
            .header-right-group {
                gap: var(--spacing-md);
            }
            .main-navigation {
                display: none;
            }
            .language-switcher {
                margin-right: 32px;
            }
        }
	</style>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">

	<header class="site-header">
		<div class="container">
			<div class="site-branding">
				<div class="site-logo">
					<?php
					if (has_custom_logo()) {
						the_custom_logo();
					} else {
						?>
						<a href="<?php echo esc_url(home_url('/')); ?>" rel="home" style="text-decoration: none; color: inherit; font-size: 1.2rem; font-weight: 300; letter-spacing: 2px; text-transform: uppercase;">
							<?php bloginfo('name'); ?>
						</a>
						<?php
					}
					?>
				</div>

                <div class="header-right-group">
                    <?php if (function_exists('the_language_switcher')) {
                        the_language_switcher();
                    } ?>

                    <nav class="main-navigation" role="navigation">
                        <ul id="primary-menu">
                            <li><a href="<?php echo home_url('/agenda/'); ?>"><?php _e('Agenda', 'gastro-starter'); ?></a></li>
                            <li><a href="<?php echo home_url('/reserver/'); ?>"><?php _e('Réservation', 'gastro-starter'); ?></a></li>
                            <li><a href="<?php echo home_url('/bon-achat/'); ?>"><?php _e('Bons-cadeaux', 'gastro-starter'); ?></a></li>
                            <li><a href="<?php echo esc_url(get_theme_mod('gastro_starter_instagram_url', 'https://instagram.com/mon-restaurant')); ?>" target="_blank" rel="noopener noreferrer"><?php _e('Instagram', 'gastro-starter'); ?></a></li>
                            <li><a href="mailto:<?php echo esc_attr(get_theme_mod('gastro_starter_restaurant_email', 'contact@mon-restaurant.fr')); ?>"><?php _e('Mail', 'gastro-starter'); ?></a></li>
                        </ul>
                    </nav>

                    <button class="mobile-menu-toggle" aria-label="<?php _e('Menu', 'gastro-starter'); ?>" aria-expanded="false">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </button>
                </div>
			</div>
		</div>
	</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const navigation = document.querySelector('.main-navigation');
    
    if (mobileToggle && navigation) {
        mobileToggle.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            this.setAttribute('aria-expanded', !isExpanded);
            this.classList.toggle('active');
            navigation.classList.toggle('active');
        });
    }
    
    // Header scroll effect
    const header = document.querySelector('.site-header');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
    
    // Fermer le menu mobile lors du clic sur un lien
    const mobileLinks = document.querySelectorAll('.main-navigation a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                mobileToggle.classList.remove('active');
                navigation.classList.remove('active');
                mobileToggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
});
</script> 