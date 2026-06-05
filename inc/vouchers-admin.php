<?php
if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_vouchers_admin_menu() {
    add_menu_page('Bons d\'achat', 'Bons d\'achat', 'manage_options', 'gastro-starter-vouchers', 'gastro_starter_vouchers_admin_page', 'dashicons-tickets', 59);
    add_submenu_page('gastro-starter-vouchers', 'Réglages Bons d\'achat', 'Réglages', 'manage_options', 'gastro-starter-vouchers-settings', 'gastro_starter_vouchers_settings_page');
    add_submenu_page('gastro-starter-vouchers', 'Configuration Stripe', 'Configuration Stripe', 'manage_options', 'gastro-starter-stripe-settings', 'gastro_starter_stripe_settings_page');
}
add_action('admin_menu', 'gastro_starter_vouchers_admin_menu');

// Alerte si Stripe n'est pas configuré
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    
    $stripe_keys = gastro_starter_get_stripe_api_keys();
    if (!empty($stripe_keys['secret_key'])) return; // Stripe est configuré
    
    $available_amounts = get_option('gastro_starter_voucher_amounts', array());
    if (empty($available_amounts)) return; // Pas de montants configurés
    
    $url = admin_url('admin.php?page=gastro-starter-stripe-settings');
    echo '<div class="notice notice-warning"><p>'
        . '⚠️ Les bons d\'achat sont configurés mais Stripe n\'est pas activé. '
        . '<a href="' . esc_url($url) . '">Configurer Stripe maintenant</a>.'
        . '</p></div>';
});

function gastro_starter_vouchers_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Si on affiche les détails d'un voucher
    if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['voucher_id'])) {
        gastro_starter_voucher_details_page(intval($_GET['voucher_id']));
        return;
    }
    
    if (isset($_POST['gastro_starter_quick_voucher_nonce']) && wp_verify_nonce($_POST['gastro_starter_quick_voucher_nonce'], 'gastro_starter_quick_voucher')) {
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        if ($amount > 0) {
            $created = gastro_starter_create_voucher(array(
                'amount_cents' => $amount * 100,
                'purchaser_name' => isset($_POST['purchaser_name']) ? $_POST['purchaser_name'] : '',
                'purchaser_email' => isset($_POST['purchaser_email']) ? $_POST['purchaser_email'] : '',
                'recipient_name' => isset($_POST['recipient_name']) ? $_POST['recipient_name'] : '',
                'recipient_email' => isset($_POST['recipient_email']) ? $_POST['recipient_email'] : '',
                'message' => isset($_POST['message']) ? $_POST['message'] : ''
            ));
            if (!is_wp_error($created)) {
                echo '<div class="notice notice-success"><p>Bon créé: ' . esc_html($created->code) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($created->get_error_message()) . '</p></div>';
            }
        }
    }
    echo '<div class="wrap">';
    echo '<h1>Bons d\'achat</h1>';
    echo '<h2>Ajout rapide</h2>';
    echo '<form method="post" class="gastro-starter-voucher-quick">';
    wp_nonce_field('gastro_starter_quick_voucher', 'gastro_starter_quick_voucher_nonce');
    echo '<table class="form-table">';
    echo '<tr><th>Montant (€)</th><td>';
    echo '<div class="amount-row">';
    echo '<input type="number" name="amount" class="small-text" min="10" step="5" required placeholder="Ex: 50"> ';
    echo '<span class="amount-chips">';
    foreach ([25,50,75,100,150] as $amt) { echo '<button type="button" class="button button-secondary amount-chip" data-amount="'.$amt.'">'.$amt.'€</button> '; }
    echo '</span>';
    echo '</div>';
    echo '</td></tr>';
    echo '<tr><th>Acheteur</th><td class="two-cols">';
    echo '<input type="text" name="purchaser_name" placeholder="Nom"> ';
    echo '<input type="email" name="purchaser_email" placeholder="Email">';
    echo '</td></tr>';
    echo '<tr><th>Bénéficiaire</th><td class="two-cols">';
    echo '<input type="text" name="recipient_name" placeholder="Nom"> ';
    echo '<input type="email" name="recipient_email" placeholder="Email">';
    echo '</td></tr>';
    echo '<tr><th>Message</th><td><textarea name="message" rows="3" placeholder="Un petit mot pour le bénéficiaire"></textarea></td></tr>';
    echo '</table>';
    submit_button('Créer le bon', 'primary', 'submit', true, array('style' => 'padding-left:18px;padding-right:18px'));
    echo '</form>';

    echo '<script>document.addEventListener("DOMContentLoaded",function(){var chips=document.querySelectorAll(".amount-chip");var input=document.querySelector(".gastro-starter-voucher-quick input[name=amount]");chips.forEach(function(c){c.addEventListener("click",function(){input.value=this.getAttribute("data-amount");input.focus();});});});</script>';
    
    echo '<style>
        .gastro-starter-voucher-quick .amount-row { display: flex; align-items: center; gap: 12px; }
        .gastro-starter-voucher-quick .amount-chips { display: flex; gap: 6px; }
        .gastro-starter-voucher-quick .amount-chip { 
            font-weight: 600; 
            padding: 6px 14px !important;
            height: auto !important;
            line-height: 1.4 !important;
        }
        .gastro-starter-voucher-quick .amount-chip:hover { 
            background: #2271b1; 
            color: white; 
            border-color: #2271b1; 
        }
        .gastro-starter-voucher-quick .two-cols { display: flex; gap: 12px; }
        .gastro-starter-voucher-quick .two-cols input { flex: 1; }
    </style>';


    global $wpdb;
    $table = $wpdb->prefix . 'gastro_starter_vouchers';
    
    // Gestion de la suppression
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['voucher_id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_voucher_' . $_GET['voucher_id'])) {
            $deleted = $wpdb->delete($table, array('id' => intval($_GET['voucher_id'])), array('%d'));
            if ($deleted) {
                echo '<div class="notice notice-success"><p>✅ Bon d\'achat supprimé avec succès.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Erreur lors de la suppression.</p></div>';
            }
        }
    }
    
    // Gestion de la modification du statut
    if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['voucher_id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'toggle_status_' . $_GET['voucher_id'])) {
            $voucher = gastro_starter_get_voucher(intval($_GET['voucher_id']));
            if ($voucher) {
                // Basculer entre paid et redeemed
                if ($voucher->status === 'paid') {
                    $new_status = 'redeemed';
                } elseif ($voucher->status === 'redeemed') {
                    $new_status = 'paid';
                } else {
                    $new_status = 'paid'; // Par défaut
                }
                
                $updated = gastro_starter_mark_voucher_status($voucher->id, $new_status);
                if ($updated) {
                    $status_labels = array(
                        'paid' => 'Payé',
                        'redeemed' => 'Utilisé'
                    );
                    $label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : $new_status;
                    echo '<div class="notice notice-success"><p>✅ Statut mis à jour : <strong>' . esc_html($label) . '</strong></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Erreur lors de la mise à jour du statut.</p></div>';
                }
            }
        }
    }
    
    $rows = $wpdb->get_results("SELECT id, code, amount_cents, status, purchaser_email, recipient_email, purchaser_name, recipient_name, created_at FROM $table ORDER BY id DESC LIMIT 100");
    echo '<h2 style="margin-top:2rem">Liste des bons d\'achat</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Code</th><th>Montant</th><th>Statut</th><th>Acheteur</th><th>Bénéficiaire</th><th>Créé</th><th>Actions</th></tr></thead><tbody>';
    if ($rows) {
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . intval($r->id) . '</td>';
            echo '<td><code style="font-weight:bold;font-size:13px;">' . esc_html($r->code) . '</code></td>';
            echo '<td><strong>' . number_format_i18n($r->amount_cents / 100, 2) . ' €</strong></td>';
            
            // Badge de statut coloré
            $status_colors = array(
                'pending' => '#fbbf24',
                'paid' => '#10b981',
                'redeemed' => '#6b7280',
                'cancelled' => '#ef4444'
            );
            $status_labels = array(
                'pending' => 'En attente',
                'paid' => 'Payé',
                'redeemed' => 'Utilisé',
                'cancelled' => 'Annulé'
            );
            $color = isset($status_colors[$r->status]) ? $status_colors[$r->status] : '#6b7280';
            $label = isset($status_labels[$r->status]) ? $status_labels[$r->status] : ucfirst($r->status);
            echo '<td><span style="display:inline-block;padding:4px 10px;border-radius:12px;background:' . $color . ';color:white;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">' . esc_html($label) . '</span></td>';
            
            // Acheteur : Nom ou Email si nom vide
            $purchaser_display = !empty($r->purchaser_name) ? $r->purchaser_name : $r->purchaser_email;
            echo '<td>' . esc_html($purchaser_display) . '</td>';
            
            // Bénéficiaire : Nom ou Email si nom vide
            $recipient_display = !empty($r->recipient_name) ? $r->recipient_name : $r->recipient_email;
            echo '<td>' . esc_html($recipient_display) . '</td>';
            
            echo '<td>' . esc_html(mysql2date('d/m/Y H:i', $r->created_at)) . '</td>';
            
            // Boutons d'action
            echo '<td class="voucher-actions" style="white-space:nowrap;">';
            
            // Bouton voir détails
            $details_url = add_query_arg(array(
                'page' => 'gastro-starter-vouchers',
                'action' => 'view',
                'voucher_id' => $r->id
            ), admin_url('admin.php'));
            echo '<a href="' . esc_url($details_url) . '" class="button button-small" title="Voir les détails">';
            echo '<span class="dashicons dashicons-visibility" style="font-size:16px;line-height:1.5;"></span>';
            echo '</a> ';
            
            // Bouton changer statut (si payé -> utilisé, si utilisé -> payé)
            if ($r->status === 'paid' || $r->status === 'redeemed') {
                $toggle_url = wp_nonce_url(
                    add_query_arg(array(
                        'page' => 'gastro-starter-vouchers',
                        'action' => 'toggle_status',
                        'voucher_id' => $r->id
                    ), admin_url('admin.php')),
                    'toggle_status_' . $r->id
                );
                $toggle_icon = $r->status === 'paid' ? 'dashicons-yes-alt' : 'dashicons-undo';
                $toggle_title = $r->status === 'paid' ? 'Marquer comme utilisé' : 'Marquer comme non utilisé';
                echo '<a href="' . esc_url($toggle_url) . '" class="button button-small" title="' . esc_attr($toggle_title) . '">';
                echo '<span class="dashicons ' . $toggle_icon . '" style="font-size:16px;line-height:1.5;"></span>';
                echo '</a> ';
            }
            
            // Bouton supprimer
            $delete_url = wp_nonce_url(
                add_query_arg(array(
                    'page' => 'gastro-starter-vouchers',
                    'action' => 'delete',
                    'voucher_id' => $r->id
                ), admin_url('admin.php')),
                'delete_voucher_' . $r->id
            );
            echo '<a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ce bon d\\\'achat ? Cette action est irréversible.\');" title="Supprimer">';
            echo '<span class="dashicons dashicons-trash" style="font-size:16px;line-height:1.5;color:#dc3232;"></span>';
            echo '</a>';
            
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280;">Aucun bon d\'achat pour le moment</td></tr>';
    }
    echo '</tbody></table>';
    
    // CSS pour améliorer l'apparence
    echo '<style>
        .voucher-actions .button { 
            padding: 4px 8px !important; 
            height: auto !important;
            min-height: 0 !important;
            margin-right: 4px;
        }
        .voucher-actions .dashicons {
            margin: 0;
            width: auto;
        }
    </style>';
    
    echo '</div>';
}

function gastro_starter_vouchers_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    if (isset($_POST['gastro_starter_vouchers_settings_nonce']) && wp_verify_nonce($_POST['gastro_starter_vouchers_settings_nonce'], 'gastro_starter_vouchers_settings')) {
        // Enregistrer les montants disponibles
        $amounts = isset($_POST['voucher_amounts']) ? array_map('intval', (array) $_POST['voucher_amounts']) : array();
        $amounts = array_filter($amounts, function($amt) { return $amt > 0; });
        $amounts = array_values(array_unique($amounts));
        sort($amounts);
        
        update_option('gastro_starter_voucher_amounts', $amounts);
        
        // Enregistrer la pick-up line personnalisée
        $custom_pickup = isset($_POST['voucher_pickup_line']) ? sanitize_text_field($_POST['voucher_pickup_line']) : '';
        update_option('gastro_starter_voucher_pickup_line', $custom_pickup);
        
        echo '<div class="notice notice-success"><p>Réglages enregistrés. ' . count($amounts) . ' montant(s) configuré(s).</p></div>';
    }
    
    $amounts = get_option('gastro_starter_voucher_amounts', array());
    if (!is_array($amounts)) { $amounts = array(); }
    
    // Préparer lignes existantes + 5 lignes vides pour faciliter l'ajout
    $rows = $amounts;
    for ($i = 0; $i < 5; $i++) {
        $rows[] = '';
    }
    
    echo '<div class="wrap">';
    echo '<h1>Réglages des bons d\'achat</h1>';
    
    // Afficher un avertissement si aucun montant n'est configuré
    if (empty($amounts)) {
        echo '<div class="notice notice-warning"><p><strong>Attention :</strong> Aucun montant n\'est configuré. Les clients ne pourront pas acheter de bons d\'achat tant que vous n\'aurez pas configuré au moins un montant.</p></div>';
    }
    
    echo '<p>Configurez les montants disponibles pour vos bons d\'achat. Les clients pourront uniquement choisir parmi ces montants.</p>';
    echo '<form method="post">';
    wp_nonce_field('gastro_starter_vouchers_settings', 'gastro_starter_vouchers_settings_nonce');
    
    // Section Pick-up line
    $current_pickup = get_option('gastro_starter_voucher_pickup_line', '');
    echo '<h2>Message d\'accroche</h2>';
    echo '<p>Personnalisez le message qui apparaît en haut de la page des bons-cadeaux. Laissez vide pour utiliser le message automatique selon la saison.</p>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="voucher_pickup_line">Message personnalisé</label></th>';
    echo '<td><input type="text" id="voucher_pickup_line" name="voucher_pickup_line" value="' . esc_attr($current_pickup) . '" class="large-text" placeholder="Laissez vide pour rotation automatique" />';
    echo '<p class="description">Exemples de messages saisonniers automatiques :<br>';
    echo '• <em>"Offrez un moment gourmand pour les fêtes"</em> (Novembre-Décembre)<br>';
    echo '• <em>"La douceur d\'un bon repas pour la Saint-Valentin"</em> (Janvier-Février)<br>';
    echo '• <em>"Célébrez le printemps avec une table au restaurant"</em> (Mars-Mai)<br>';
    echo '• <em>"L\'été se savoure au restaurant"</em> (Juin-Août)<br>';
    echo '• <em>"Un automne gourmand au restaurant"</em> (Septembre-Octobre)</p>';
    echo '</td></tr></table>';
    
    echo '<h2 style="margin-top:30px;">Montants disponibles</h2>';
    echo '<table class="widefat striped" style="max-width:400px">';
    echo '<thead><tr><th>Montant (€)</th></tr></thead><tbody>';
    foreach ($rows as $amount) {
        echo '<tr>'; 
        echo '<td><input type="number" name="voucher_amounts[]" min="1" step="1" value="' . esc_attr($amount) . '" placeholder="Ex: 50" style="width: 100px;" /></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description"><strong>Instructions :</strong></p>';
    echo '<ul class="description">';
    echo '<li>Entrez les montants que vous souhaitez proposer (ex: 25, 50, 75, 100, 150)</li>';
    echo '<li>Les lignes vides seront ignorées lors de l\'enregistrement</li>';
    echo '<li>Les doublons seront automatiquement supprimés</li>';
    echo '<li>N\'oubliez pas de <a href="' . admin_url('admin.php?page=gastro-starter-stripe-settings') . '">configurer Stripe</a> pour accepter les paiements</li>';
    echo '</ul>';
    submit_button('Enregistrer les montants');
    echo '</form>';
    
    // Afficher la liste des montants configurés pour référence rapide
    if (!empty($amounts)) {
        echo '<div style="margin-top: 30px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px;">';
        echo '<h3 style="margin-top: 0;">✅ Montants actuellement configurés</h3>';
        echo '<ul style="display: flex; gap: 10px; flex-wrap: wrap; list-style: none; padding: 0;">';
        foreach ($amounts as $amount) {
            echo '<li style="background: #fff; padding: 8px 16px; border-radius: 4px; border: 2px solid #3b82f6; font-weight: bold; font-size: 16px;">' . number_format_i18n($amount, 0) . ' €</li>';
        }
        echo '</ul>';
        echo '<p class="description">Ces montants sont les seuls disponibles dans le formulaire de bon d\'achat sur le site.</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

function gastro_starter_redeem_voucher_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }
    if (!isset($_POST['voucher_id'])) {
        wp_send_json_error('missing', 400);
    }
    $ok = gastro_starter_mark_voucher_status(intval($_POST['voucher_id']), 'redeemed');
    if ($ok) {
        wp_send_json_success();
    }
    wp_send_json_error('db_error', 500);
}
add_action('wp_ajax_gastro_starter_redeem_voucher', 'gastro_starter_redeem_voucher_ajax');

// Page de détails d'un voucher
function gastro_starter_voucher_details_page($voucher_id) {
    $voucher = gastro_starter_get_voucher($voucher_id);
    
    if (!$voucher) {
        echo '<div class="wrap"><h1>Bon d\'achat introuvable</h1>';
        echo '<p>Ce bon d\'achat n\'existe pas ou a été supprimé.</p>';
        echo '<a href="' . admin_url('admin.php?page=gastro-starter-vouchers') . '" class="button">← Retour à la liste</a>';
        echo '</div>';
        return;
    }
    
    // Gestion de la modification
    if (isset($_POST['gastro_starter_edit_voucher_nonce']) && wp_verify_nonce($_POST['gastro_starter_edit_voucher_nonce'], 'gastro_starter_edit_voucher_' . $voucher_id)) {
        global $wpdb;
        $table = $wpdb->prefix . 'gastro_starter_vouchers';
        
        $updated = $wpdb->update(
            $table,
            array(
                'purchaser_name' => sanitize_text_field($_POST['purchaser_name']),
                'purchaser_email' => sanitize_email($_POST['purchaser_email']),
                'recipient_name' => sanitize_text_field($_POST['recipient_name']),
                'recipient_email' => sanitize_email($_POST['recipient_email']),
                'message' => sanitize_textarea_field($_POST['message']),
                'status' => sanitize_text_field($_POST['status'])
            ),
            array('id' => $voucher_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($updated !== false) {
            echo '<div class="notice notice-success"><p>✅ Bon d\'achat mis à jour avec succès.</p></div>';
            $voucher = gastro_starter_get_voucher($voucher_id); // Recharger les données
        } else {
            echo '<div class="notice notice-error"><p>❌ Erreur lors de la mise à jour.</p></div>';
        }
    }
    
    // Calculer la validité
    $created = new DateTime($voucher->created_at);
    $validity = clone $created;
    $validity->modify('+1 year');
    $is_expired = new DateTime() > $validity;
    
    echo '<div class="wrap">';
    echo '<h1>Détails du bon d\'achat #' . intval($voucher->id) . '</h1>';
    
    echo '<a href="' . admin_url('admin.php?page=gastro-starter-vouchers') . '" class="button" style="margin-bottom:20px;">← Retour à la liste</a>';
    
    // Cartes d'information
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin:20px 0;">';
    
    // Carte Code & Montant
    echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
    echo '<h3 style="margin-top:0;color:#1d2327;">Code & Montant</h3>';
    echo '<div style="font-size:32px;font-weight:700;font-family:monospace;color:#2271b1;margin:15px 0;">' . esc_html($voucher->code) . '</div>';
    echo '<div style="font-size:28px;font-weight:600;color:#1d2327;margin:10px 0;">' . number_format_i18n($voucher->amount_cents / 100, 2) . ' €</div>';
    
    // Lien de téléchargement
    $download_url = home_url('/telecharger-bon-achat?code=' . urlencode($voucher->code));
    echo '<a href="' . esc_url($download_url) . '" target="_blank" class="button button-secondary" style="margin-top:10px;"><span class="dashicons dashicons-printer"></span> Imprimer / PDF</a>';
    echo '</div>';
    
    // Carte Statut
    $status_colors = array(
        'pending' => array('bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'En attente'),
        'paid' => array('bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Payé'),
        'redeemed' => array('bg' => '#e5e7eb', 'text' => '#374151', 'label' => 'Utilisé'),
        'cancelled' => array('bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Annulé')
    );
    $status_info = isset($status_colors[$voucher->status]) ? $status_colors[$voucher->status] : array('bg' => '#f3f4f6', 'text' => '#6b7280', 'label' => ucfirst($voucher->status));
    
    echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
    echo '<h3 style="margin-top:0;color:#1d2327;">Statut & Validité</h3>';
    echo '<div style="display:inline-block;padding:8px 16px;border-radius:20px;background:' . $status_info['bg'] . ';color:' . $status_info['text'] . ';font-weight:600;font-size:14px;margin:10px 0;">' . esc_html($status_info['label']) . '</div>';
    echo '<div style="margin-top:15px;">';
    echo '<strong>Créé le :</strong> ' . mysql2date('d/m/Y à H:i', $voucher->created_at) . '<br>';
    echo '<strong>Valable jusqu\'au :</strong> ' . $validity->format('d/m/Y');
    if ($is_expired) {
        echo ' <span style="color:#dc3232;font-weight:600;">⚠️ Expiré</span>';
    }
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    
    // Formulaire de modification
    echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin:20px 0;box-shadow:0 1px 3px rgba(0,0,0,0.04);">';
    echo '<h2 style="margin-top:0;">Modifier les informations</h2>';
    echo '<form method="post">';
    wp_nonce_field('gastro_starter_edit_voucher_' . $voucher_id, 'gastro_starter_edit_voucher_nonce');
    
    echo '<table class="form-table">';
    
    // Statut
    echo '<tr><th scope="row"><label for="status">Statut</label></th><td>';
    echo '<select name="status" id="status" required>';
    foreach (array('pending' => 'En attente', 'paid' => 'Payé', 'redeemed' => 'Utilisé', 'cancelled' => 'Annulé') as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($voucher->status, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';
    
    // Acheteur
    echo '<tr><th scope="row">Acheteur</th><td>';
    echo '<input type="text" name="purchaser_name" value="' . esc_attr($voucher->purchaser_name) . '" class="regular-text" placeholder="Nom"><br>';
    echo '<input type="email" name="purchaser_email" value="' . esc_attr($voucher->purchaser_email) . '" class="regular-text" placeholder="Email" style="margin-top:8px;">';
    echo '</td></tr>';
    
    // Bénéficiaire
    echo '<tr><th scope="row">Bénéficiaire</th><td>';
    echo '<input type="text" name="recipient_name" value="' . esc_attr($voucher->recipient_name) . '" class="regular-text" placeholder="Nom"><br>';
    echo '<input type="email" name="recipient_email" value="' . esc_attr($voucher->recipient_email) . '" class="regular-text" placeholder="Email" style="margin-top:8px;">';
    echo '</td></tr>';
    
    // Message
    echo '<tr><th scope="row"><label for="message">Message</label></th><td>';
    echo '<textarea name="message" id="message" rows="4" class="large-text">' . esc_textarea($voucher->message) . '</textarea>';
    echo '</td></tr>';
    
    echo '</table>';
    
    submit_button('Enregistrer les modifications', 'primary');
    echo '</form>';
    echo '</div>';
    
    // Informations techniques
    echo '<div style="background:#f9f9f9;border:1px solid #c3c4c7;padding:20px;margin:20px 0;">';
    echo '<h3 style="margin-top:0;">Informations techniques</h3>';
    echo '<table class="widefat striped" style="max-width:600px;">';
    echo '<tr><th style="width:200px;">ID</th><td>' . intval($voucher->id) . '</td></tr>';
    echo '<tr><th>Code</th><td><code>' . esc_html($voucher->code) . '</code></td></tr>';
    echo '<tr><th>Montant (centimes)</th><td>' . intval($voucher->amount_cents) . '</td></tr>';
    if (!empty($voucher->stripe_checkout_session_id)) {
        echo '<tr><th>Session Stripe</th><td><code style="font-size:11px;">' . esc_html($voucher->stripe_checkout_session_id) . '</code></td></tr>';
    }
    echo '<tr><th>Créé le</th><td>' . esc_html($voucher->created_at) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '</div>';
}



// Page de configuration Stripe
function gastro_starter_stripe_settings_page() {
    require_once get_template_directory() . '/inc/stripe-settings-page.php';
}
