<?php
/**
 * Admin interface for Capacity Pooling settings
 * 
 * @package Gastro_Starter
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add pooling settings to the reservations admin menu
 */
function gastro_starter_add_pooling_admin_menu() {
    add_submenu_page(
        'gastro-starter-reservations',
        __('Pooling de Capacité', 'gastro-starter'),
        __('Pooling de Capacité', 'gastro-starter'),
        'manage_options',
        'gastro-starter-capacity-pooling',
        'gastro_starter_capacity_pooling_page'
    );
}
add_action('admin_menu', 'gastro_starter_add_pooling_admin_menu');

/**
 * Register settings for pooling
 */
function gastro_starter_register_pooling_settings() {
    register_setting('gastro_starter_pooling_settings', 'gastro_starter_pooling_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    
    register_setting('gastro_starter_pooling_settings', 'gastro_starter_pooling_strategy', [
        'type' => 'string',
        'default' => 'least_filled',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    
    register_setting('gastro_starter_pooling_settings', 'gastro_starter_max_pooling_slots', [
        'type' => 'integer',
        'default' => 5,
        'sanitize_callback' => 'absint'
    ]);
    
    register_setting('gastro_starter_pooling_settings', 'gastro_starter_allow_manual_placement', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
}
add_action('admin_init', 'gastro_starter_register_pooling_settings');

/**
 * Render the capacity pooling settings page
 */
function gastro_starter_capacity_pooling_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle form submission
    if (isset($_POST['gastro_starter_save_pooling_settings'])) {
        check_admin_referer('gastro_starter_pooling_settings_action', 'gastro_starter_pooling_settings_nonce');
        
        update_option('gastro_starter_pooling_enabled', isset($_POST['gastro_starter_pooling_enabled']) ? 1 : 0);
        update_option('gastro_starter_pooling_strategy', sanitize_text_field($_POST['gastro_starter_pooling_strategy']));
        update_option('gastro_starter_max_pooling_slots', absint($_POST['gastro_starter_max_pooling_slots']));
        update_option('gastro_starter_allow_manual_placement', isset($_POST['gastro_starter_allow_manual_placement']) ? 1 : 0);
        
        echo '<div class="notice notice-success"><p>✅ Paramètres de pooling enregistrés avec succès !</p></div>';
    }
    
    // Get current settings
    $pooling_enabled = get_option('gastro_starter_pooling_enabled', true);
    $pooling_strategy = get_option('gastro_starter_pooling_strategy', 'least_filled');
    $max_pooling_slots = get_option('gastro_starter_max_pooling_slots', 5);
    $allow_manual = get_option('gastro_starter_allow_manual_placement', true);
    $capacity = get_option('gastro_starter_restaurant_capacity', 6);
    
    // Get pooling statistics for today
    $pooling_manager = new Gastro_Starter_Capacity_Pooling_Manager();
    $today = date('Y-m-d');
    $stats = $pooling_manager->get_pooling_statistics($today);
    
    ?>
    <div class="wrap">
        <h1>🔗 <?php echo esc_html__('Pooling de Capacité Multi-Créneaux', 'gastro-starter'); ?></h1>
        <p class="description">
            Mutualisez les places disponibles de plusieurs créneaux pour accueillir des groupes plus grands.
        </p>
        
        <!-- Statistiques du jour -->
        <div class="card" style="margin-top: 20px; max-width: 100%;">
            <h2 style="margin-top: 0;">📊 Statistiques du Jour</h2>
            <table class="widefat">
                <tr>
                    <th>Réservations totales</th>
                    <td><strong><?php echo esc_html($stats['total_reservations']); ?></strong></td>
                </tr>
                <tr>
                    <th>Réservations avec pooling</th>
                    <td>
                        <strong><?php echo esc_html($stats['pooled_reservations']); ?></strong>
                        (<?php echo esc_html(number_format($stats['pooling_percentage'], 1)); ?>%)
                    </td>
                </tr>
                <tr>
                    <th>Capacité par créneau</th>
                    <td><strong><?php echo esc_html($capacity); ?> personnes</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- Explication visuelle -->
        <div class="card" style="margin-top: 20px; max-width: 100%; background: #f0f7ff;">
            <h2 style="margin-top: 0;">💡 Comment ça marche ?</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h3>Avant le Pooling</h3>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <p style="margin: 5px 0;"><span style="color: #ff6b6b;">❌ 12:00</span> → 5/6 (reste 1) ← Groupe de 4 refusé</p>
                        <p style="margin: 5px 0;"><span style="color: #ff6b6b;">❌ 12:15</span> → 5/6 (reste 1)</p>
                        <p style="margin: 5px 0;"><span style="color: #ff6b6b;">❌ 12:30</span> → 4/6 (reste 2)</p>
                        <p style="margin-top: 10px; font-weight: bold;">Total disponible : 4 places, mais fragmentées !</p>
                    </div>
                </div>
                <div>
                    <h3>Avec le Pooling ✨</h3>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <p style="margin: 5px 0;"><span style="color: #4ecdc4;">✅ 12:00</span> → 6/6 (utilise +1)</p>
                        <p style="margin: 5px 0;"><span style="color: #4ecdc4;">✅ 12:15</span> → 6/6 (utilise +1)</p>
                        <p style="margin: 5px 0;"><span style="color: #4ecdc4;">✅ 12:30</span> → 6/6 (utilise +2) ⭐ Groupe placé ici</p>
                        <p style="margin-top: 10px; font-weight: bold; color: #4ecdc4;">Groupe de 4 accepté !</p>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="post" action="" style="margin-top: 20px;">
            <?php wp_nonce_field('gastro_starter_pooling_settings_action', 'gastro_starter_pooling_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gastro_starter_pooling_enabled">Activer le Pooling de Capacité</label>
                    </th>
                    <td>
                        <input type="checkbox" name="gastro_starter_pooling_enabled" id="gastro_starter_pooling_enabled" value="1" <?php checked($pooling_enabled, 1); ?>>
                        <p class="description">
                            Permet de combiner les places disponibles de plusieurs créneaux pour accueillir des groupes plus grands.
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="pooling-settings" style="<?php echo $pooling_enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                
                <h2>🎯 Stratégie de Placement</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Stratégie Automatique</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="gastro_starter_pooling_strategy" value="least_filled" <?php checked($pooling_strategy, 'least_filled'); ?>>
                                    <strong>Créneau le moins rempli</strong>  (Recommandé)
                                    <p class="description">Placer le groupe sur le créneau avec le plus d'espace disponible</p>
                                </label>
                                <br><br>
                                <label>
                                    <input type="radio" name="gastro_starter_pooling_strategy" value="earliest" <?php checked($pooling_strategy, 'earliest'); ?>>
                                    <strong>Créneau le plus tôt</strong>
                                    <p class="description">Privilégier les premiers créneaux disponibles</p>
                                </label>
                                <br><br>
                                <label>
                                    <input type="radio" name="gastro_starter_pooling_strategy" value="latest" <?php checked($pooling_strategy, 'latest'); ?>>
                                    <strong>Créneau le plus tard</strong>
                                    <p class="description">Privilégier les derniers créneaux disponibles</p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <h2>⚙️ Paramètres du Pooling</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gastro_starter_max_pooling_slots">Nombre Maximum de Créneaux à Pooler</label>
                        </th>
                        <td>
                            <input type="number" name="gastro_starter_max_pooling_slots" id="gastro_starter_max_pooling_slots" value="<?php echo esc_attr($max_pooling_slots); ?>" min="2" max="10" class="small-text">
                            <span>créneaux</span>
                            <p class="description">
                                Limite le nombre de créneaux pouvant être combinés pour une seule réservation.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gastro_starter_allow_manual_placement">Choix Manuel en Admin</label>
                        </th>
                        <td>
                            <input type="checkbox" name="gastro_starter_allow_manual_placement" id="gastro_starter_allow_manual_placement" value="1" <?php checked($allow_manual, 1); ?>>
                            <p class="description">
                                Permet au restaurateur de choisir manuellement le créneau principal lors de la création d'une réservation.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>📋 Exemple de Fonctionnement</h2>
                <div class="card" style="background: #fff; padding: 15px;">
                    <h3>Scénario : Groupe de 4 personnes</h3>
                    <p><strong>État des créneaux :</strong></p>
                    <ul style="list-style: none; padding-left: 0;">
                        <li>🕐 12:00 → 5/<?php echo $capacity; ?> occupé (reste 1 place)</li>
                        <li>🕐 12:15 → 5/<?php echo $capacity; ?> occupé (reste 1 place)</li>
                        <li>🕐 12:30 → 4/<?php echo $capacity; ?> occupé (reste 2 places)</li>
                    </ul>
                    
                    <p><strong>✨ Avec Pooling :</strong></p>
                    <ol>
                        <li>Le système détecte 1+1+2 = <strong>4 places disponibles au total</strong></li>
                        <li>Selon la stratégie "<?php 
                            $strategies = [
                                'least_filled' => 'créneau le moins rempli',
                                'earliest' => 'créneau le plus tôt',
                                'latest' => 'créneau le plus tard'
                            ];
                            echo $strategies[$pooling_strategy];
                        ?>", le groupe sera placé sur <strong>12:30</strong></li>
                        <li>Les 4 places sont déduites :
                            <ul>
                                <li>12:00 → utilise 1 place → 6/<?php echo $capacity; ?></li>
                                <li>12:15 → utilise 1 place → 6/<?php echo $capacity; ?></li>
                                <li>12:30 → utilise 2 places → 6/<?php echo $capacity; ?> ⭐</li>
                            </ul>
                        </li>
                        <li>✅ <strong>Réservation acceptée !</strong></li>
                    </ol>
                </div>
            
            </div>
            
            <?php submit_button('Enregistrer les Paramètres', 'primary', 'gastro_starter_save_pooling_settings'); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#gastro_starter_pooling_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#pooling-settings').css({'opacity': '1', 'pointer-events': 'auto'});
                } else {
                    $('#pooling-settings').css({'opacity': '0.5', 'pointer-events': 'none'});
                }
            });
        });
        </script>
        
        <style>
        .form-table th {
            width: 280px;
        }
        .form-table label {
            font-weight: normal;
        }
        .form-table p.description {
            margin-top: 5px;
            font-style: italic;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .widefat th {
            text-align: left;
            padding: 10px;
        }
        .widefat td {
            padding: 10px;
        }
        </style>
    </div>
    <?php
}
