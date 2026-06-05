<?php
/**
 * Mise à jour de la structure de la table des réservations pour le pooling
 */

if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_update_reservations_table_for_pooling() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // Vérifier si les colonnes existent déjà
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $existing_columns = array_map(function($col) {
        return $col->Field;
    }, $columns);
    
    // Supprimer anciennes colonnes overbooking si elles existent
    if (in_array('is_overbooking', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN is_overbooking");
    }
    if (in_array('overbooking_approved', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN overbooking_approved");
    }
    if (in_array('placement_strategy', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN placement_strategy");
    }
    
    // Ajouter les nouvelles colonnes pour le pooling
    if (!in_array('is_pooled', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_pooled TINYINT(1) DEFAULT 0");
        error_log("Colonne 'is_pooled' ajoutée à la table {$table_name}");
    }
    
    if (!in_array('pooling_data', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN pooling_data TEXT NULL");
        error_log("Colonne 'pooling_data' ajoutée à la table {$table_name}");
    }
    
    if (!in_array('parent_reservation_id', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN parent_reservation_id INT NULL");
        error_log("Colonne 'parent_reservation_id' ajoutée à la table {$table_name}");
    }
    
    // Modifier la colonne status pour supporter 'phantom'
    $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    
    // Ajouter un index pour parent_reservation_id
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
    $has_parent_index = false;
    foreach ($indexes as $index) {
        if ($index->Key_name === 'idx_parent' || $index->Column_name === 'parent_reservation_id') {
            $has_parent_index = true;
            break;
        }
    }
    
    if (!$has_parent_index) {
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_parent (parent_reservation_id)");
        error_log("Index 'idx_parent' ajouté à la table {$table_name}");
    }
    
    // Ajouter index pour is_pooled
    $has_pooled_index = false;
    foreach ($indexes as $index) {
        if ($index->Key_name === 'idx_pooled' || $index->Column_name === 'is_pooled') {
            $has_pooled_index = true;
            break;
        }
    }
    
    if (!$has_pooled_index) {
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_pooled (is_pooled)");
        error_log("Index 'idx_pooled' ajouté à la table {$table_name}");
    }
}

// Exécuter la mise à jour au chargement du thème
add_action('after_setup_theme', 'gastro_starter_update_reservations_table_for_pooling');

