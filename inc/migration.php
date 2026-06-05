<?php
/**
 * Migration depuis le thème Le Margo vers Gastro Starter
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_migrate_from_le_margo() {
    if (get_option('gastro_starter_migrated_from_le_margo')) {
        return;
    }

    global $wpdb;

    $renames = array(
        'le_margo_rate_limits' => 'gastro_starter_rate_limits',
        'le_margo_vouchers'    => 'gastro_starter_vouchers',
    );

    foreach ($renames as $old => $new) {
        $old_table = $wpdb->prefix . $old;
        $new_table = $wpdb->prefix . $new;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table)) === $old_table) {
            $wpdb->query("RENAME TABLE `{$old_table}` TO `{$new_table}`");
        }
    }

    $options = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'le_margo_%'"
    );
    foreach ($options as $opt) {
        $new_name = str_replace('le_margo_', 'gastro_starter_', $opt->option_name);
        if (!get_option($new_name)) {
            update_option($new_name, maybe_unserialize($opt->option_value));
        }
    }

    $old_mods = get_option('theme_mods_le-margo');
    if ($old_mods && !get_option('theme_mods_gastro-starter')) {
        update_option('theme_mods_gastro-starter', $old_mods);
    }

    update_option('gastro_starter_migrated_from_le_margo', true);
}
add_action('after_switch_theme', 'gastro_starter_migrate_from_le_margo');
