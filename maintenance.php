<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html__('Site en maintenance - Mon Restaurant', 'gastro-starter'); ?></title>
    <style>
        :root {
            --color-primary: #000000;
            --color-secondary: #D4B996;
            --color-beige-light: #F5F0E6;
            --font-primary: 'Playfair Display', serif;
            --font-secondary: 'Montserrat', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-secondary);
            background-color: var(--color-beige-light);
            color: var(--color-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .maintenance-container {
            max-width: 800px;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            margin-bottom: 2rem;
        }

        .logo img {
            max-width: 200px;
            height: auto;
        }

        h1 {
            font-family: var(--font-primary);
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--color-primary);
        }

        .message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #666;
        }

        .date {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--color-secondary);
            margin-bottom: 2rem;
        }

        .contact {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .contact p {
            margin: 0.5rem 0;
        }

        .contact a {
            color: var(--color-primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact a:hover {
            color: var(--color-secondary);
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 1rem;
            }

            h1 {
                font-size: 2rem;
            }

            .message {
                font-size: 1.1rem;
            }
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo">
            <?php 
            if (has_custom_logo()) :
                the_custom_logo();
            else :
            ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/logo.png'); ?>" alt="<?php bloginfo('name'); ?>" width="160" height="50">
                </a>
            <?php endif; ?>
        </div>

        <h1><?php echo esc_html__('Site en maintenance', 'gastro-starter'); ?></h1>

        <div class="message">
            <?php echo esc_html__('Nous travaillons actuellement sur une nouvelle version de notre site web pour vous offrir une meilleure expérience.', 'gastro-starter'); ?>
        </div>

        <div class="date">
            <?php echo esc_html__('Retrouvez-nous début juillet 2025', 'gastro-starter'); ?>
        </div>

        <div class="contact">
            <p><?php echo esc_html__('En attendant, vous pouvez toujours nous contacter :', 'gastro-starter'); ?></p>
            <p><a href="tel:+33602556315">06 02 55 63 15</a></p>
            <p><a href="mailto:contact@mon-restaurant.fr">contact@mon-restaurant.fr</a></p>
            <p>@mon-restaurant</p>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html> 