<?php
/**
 * Mon Restaurant - Prize Wheel Core Logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Prize_Wheel_Core {

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('template_include', [$this, 'load_template']);
        add_action('wp_head', [$this, 'add_seo_noindex']);
        
        // AJAX Handlers
        add_action('wp_ajax_gastro_starter_spin_wheel', [$this, 'handle_spin']);
        add_action('wp_ajax_nopriv_gastro_starter_spin_wheel', [$this, 'handle_spin']);

        // Enqueue Scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue Frontend Scripts
     */
    public function enqueue_scripts() {
        if (get_query_var('prize_wheel')) {
            // CSS
            wp_enqueue_style('gastro-starter-prize-wheel', get_template_directory_uri() . '/assets/css/prize-wheel.css', [], '1.0.0');

            // JS Dependencies
            wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', [], '3.12.2', true);
            wp_enqueue_script('winwheel', 'https://cdn.jsdelivr.net/npm/winwheel@1.0.1/dist/Winwheel.min.js', ['gsap'], '1.0.1', true);
            wp_enqueue_script('canvas-confetti', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js', [], '1.6.0', true);
            
            // Custom JS
            wp_enqueue_script('gastro-starter-prize-wheel', get_template_directory_uri() . '/assets/js/prize-wheel.js', ['jquery', 'winwheel'], '1.0.0', true);

            // Localize
            wp_localize_script('gastro-starter-prize-wheel', 'prizeWheelData', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gastro_starter_spin_nonce'),
                'prizes' => get_option('gastro_starter_prize_wheel_prizes', []),
                'google_review_url' => get_option('gastro_starter_google_review_url', '#'),
                'assets_url' => get_template_directory_uri() . '/assets/'
            ]);
        }
    }

    /**
     * Register Prize Log CPT
     */
    public function register_cpt() {
        register_post_type('prize_log', [
            'labels' => [
                'name' => 'Logs Roue',
                'singular_name' => 'Log Roue',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Will be shown in our custom page or submenu
            'supports' => ['title', 'custom-fields'],
            'capabilities' => [
                'create_posts' => 'do_not_allow', // Only created programmatically
            ],
            'map_meta_cap' => true,
        ]);
    }

    /**
     * Add Rewrite Rule for /roue-de-la-fortune
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^roue-de-la-fortune/?$', 'index.php?prize_wheel=1', 'top');
    }

    /**
     * Add Query Var
     */
    public function add_query_vars($vars) {
        $vars[] = 'prize_wheel';
        return $vars;
    }

    /**
     * Load Custom Template
     */
    public function load_template($template) {
        if (get_query_var('prize_wheel')) {
            $new_template = get_template_directory() . '/templates/page-prize-wheel.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Add Noindex Meta
     */
    public function add_seo_noindex() {
        if (get_query_var('prize_wheel')) {
            echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
        }
    }

    /**
     * Handle Spin Logic (AJAX)
     */
    public function handle_spin() {
        check_ajax_referer('gastro_starter_spin_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Get IP for logging (even if security check is disabled)
        $ip = Gastro_Starter_Security::get_client_ip();

        // 1. Security: Check IP
        if ($this->has_ip_played($ip)) {
             wp_send_json_error(['message' => 'Vous avez déjà tenté votre chance (IP).']);
        }

        // 2. Security: Check Email
        if ($this->has_email_played($email)) {
           wp_send_json_error(['message' => 'Cet email a déjà été utilisé.']);
        }

        // 3. Calculate Prize
        $prizes = get_option('gastro_starter_prize_wheel_prizes', []);
        if (empty($prizes)) {
            wp_send_json_error(['message' => 'Aucun gain configuré.']);
        }

        $selected_prize_index = $this->calculate_winning_prize($prizes);
        $prize = $prizes[$selected_prize_index];

        // 4. Log the win
        $log_id = wp_insert_post([
            'post_type' => 'prize_log',
            'post_status' => 'publish',
            'post_title' => $email . ' - ' . $prize['label'],
        ]);

        update_post_meta($log_id, 'player_email', $email);
        update_post_meta($log_id, 'player_ip', $ip);
        update_post_meta($log_id, 'prize_won', $prize['label']);
        update_post_meta($log_id, 'prize_index', $selected_prize_index);

        // 5. Send Email
        $this->send_prize_email($email, $prize);

        wp_send_json_success([
            'segment_index' => $selected_prize_index, // 0-based index for Winwheel
            'message' => $prize['win_message'] ?? 'Bravo !',
            'label' => $prize['label'],
            'is_win' => $prize['is_win'] ?? true
        ]);
    }

    private function has_ip_played($ip) {
        $args = [
            'post_type' => 'prize_log',
            'meta_query' => [
                [
                    'key' => 'player_ip',
                    'value' => $ip,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ];
        $query = new WP_Query($args);
        return $query->have_posts();
    }

    private function has_email_played($email) {
        $args = [
            'post_type' => 'prize_log',
            'meta_query' => [
                [
                    'key' => 'player_email',
                    'value' => $email,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ];
        $query = new WP_Query($args);
        return $query->have_posts();
    }

    private function calculate_winning_prize($prizes) {
        $total_weight = 0;
        foreach ($prizes as $prize) {
            $total_weight += intval($prize['probability']);
        }

        $rand = rand(1, $total_weight);
        $current_weight = 0;

        foreach ($prizes as $index => $prize) {
            $current_weight += intval($prize['probability']);
            if ($rand <= $current_weight) {
                return $index;
            }
        }

        return 0; // Fallback
    }

    private function send_prize_email($email, $prize) {
        if (empty($prize['is_win'])) return; // Don't send email for losing segments

        $subject = get_option('gastro_starter_prize_email_subject', 'Votre gain chez Mon Restaurant !');
        
        $content = "<h2>Félicitations !</h2>";
        $content .= "<p>Vous avez gagné : <strong>" . esc_html($prize['label']) . "</strong></p>";
        $content .= "<p>" . wp_kses_post($prize['email_message']) . "</p>";
        $content .= "<p>Présentez cet email lors de votre prochaine visite.</p>";

        // Use Email Manager if available for consistent styling
        if (class_exists('Gastro_Starter_Email_Manager')) {
            $email_manager = Gastro_Starter_Email_Manager::get_instance();
            $sent = $email_manager->send_email($email, $subject, $content);
        } else {
            // Fallback
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($email, $subject, $content, $headers);
        }

        if (!$sent) {
            error_log("Prize Wheel: Failed to send email to $email");
        }
    }
}

new Gastro_Starter_Prize_Wheel_Core();
