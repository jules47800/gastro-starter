<?php
/**
 * Gestion des horaires d'ouverture affichés sur le site
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute la page de réglages dans le menu "Apparence"
 */
function gastro_starter_add_opening_hours_menu() {
    add_theme_page(
        __('Horaires d\'ouverture', 'gastro-starter'),
        __('Horaires d\'ouverture', 'gastro-starter'),
        'manage_options',
        'gastro-starter-opening-hours',
        'gastro_starter_opening_hours_page_html'
    );
}
add_action('admin_menu', 'gastro_starter_add_opening_hours_menu');

/**
 * Enregistre le paramètre pour les horaires
 */
function gastro_starter_register_opening_hours_settings() {
    register_setting('gastro_starter_opening_hours_settings', 'gastro_starter_opening_hours', [
        'type' => 'array',
        'default' => [],
        'sanitize_callback' => 'gastro_starter_sanitize_opening_hours'
    ]);
}
add_action('admin_init', 'gastro_starter_register_opening_hours_settings');

/**
 * Nettoie les données envoyées par le formulaire
 */
function gastro_starter_sanitize_opening_hours($input) {
    $output = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (isset($input[$day])) {
            $output[$day] = sanitize_text_field($input[$day]);
        }
    }
    return $output;
}

/**
 * Affiche le contenu HTML de la page de réglages
 */
function gastro_starter_opening_hours_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap gastro-starter-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php echo esc_html__('Indiquez ici les horaires d\'ouverture tels qu\'ils doivent apparaître sur le site. Vous pouvez utiliser du texte libre (ex: "12h-14h & 19h-22h" ou "Fermé").', 'gastro-starter'); ?></p>
        
        <div class="gastro-starter-admin-card">
            <form action="options.php" method="post">
                <?php
                settings_fields('gastro_starter_opening_hours_settings');
                $options = get_option('gastro_starter_opening_hours', []);
                $days = [
                    'monday'    => __('Lundi', 'gastro-starter'),
                    'tuesday'   => __('Mardi', 'gastro-starter'),
                    'wednesday' => __('Mercredi', 'gastro-starter'),
                    'thursday'  => __('Jeudi', 'gastro-starter'),
                    'friday'    => __('Vendredi', 'gastro-starter'),
                    'saturday'  => __('Samedi', 'gastro-starter'),
                    'sunday'    => __('Dimanche', 'gastro-starter'),
                ];
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($days as $day_key => $day_label) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="gastro_starter_opening_hours_<?php echo esc_attr($day_key); ?>"><?php echo esc_html($day_label); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="gastro_starter_opening_hours_<?php echo esc_attr($day_key); ?>"
                                           name="gastro_starter_opening_hours[<?php echo esc_attr($day_key); ?>]"
                                           value="<?php echo esc_attr($options[$day_key] ?? ''); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(__('Enregistrer les horaires', 'gastro-starter')); ?>
            </form>
        </div>
    </div>
    <?php
} 