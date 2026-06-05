<?php
/**
 * Gestion des notifications et des annulations de réservation
 */

/**
 * Envoyer un email de rappel
 */
function gastro_starter_send_reminder_email($reservation_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // Récupérer les informations de la réservation
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $reservation_id
    ));
    
    if (!$reservation || $reservation->reminder_sent) {
        return false;
    }
    
    $email_manager = Gastro_Starter_Email_Manager::get_instance();
    
    // Préparer le message
    $subject = __('Rappel de votre réservation - Mon Restaurant', 'gastro-starter');
    
    $content = '<h2>' . __('Rappel de votre réservation', 'gastro-starter') . '</h2>';
    $content .= '<p>Bonjour ' . esc_html($reservation->customer_name) . ',</p>';
    $content .= '<p>Rappel de votre réservation au restaurant <strong>Mon Restaurant</strong> :</p>';
    $content .= '<div class="reservation-details">'
        . '<div class="detail-row"><span class="detail-label">' . __('Date :', 'gastro-starter') . '</span> ' . esc_html(date_i18n('l j F Y', strtotime($reservation->reservation_date))) . '</div>'
        . '<div class="detail-row"><span class="detail-label">' . __('Heure :', 'gastro-starter') . '</span> ' . esc_html(date_i18n('H:i', strtotime($reservation->reservation_time))) . '</div>'
        . '<div class="detail-row"><span class="detail-label">' . __('Nombre de personnes :', 'gastro-starter') . '</span> ' . intval($reservation->people) . '</div>'
        . '</div>';
    $content .= '<p>Pour toute modification ou annulation, veuillez nous contacter directement.</p>';
    $content .= '<p>Nous avons hâte de vous accueillir !</p>';
    $content .= '<p>L\'équipe du restaurant</p>';
    
    $sent = $email_manager->send_email($reservation->customer_email, $subject, $content);
    
    if ($sent) {
        // Marquer le rappel comme envoyé
        $wpdb->update(
            $table_name,
            array('reminder_sent' => 1),
            array('id' => $reservation_id)
        );
        return true;
    }
    
    return false;
}

/**
 * Envoyer un email de relance post-visite (J+3)
 */
function gastro_starter_send_followup_email($reservation_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';

    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $reservation_id
    ));

    if (!$reservation || $reservation->followup_email_sent || $reservation->status !== 'confirmed') {
        return false;
    }

    if (empty($reservation->customer_email)) {
        return false;
    }

    $email_manager = Gastro_Starter_Email_Manager::get_instance();

    $first_name = explode(' ', trim($reservation->customer_name))[0];
    $visit_date = date_i18n('l j F', strtotime($reservation->reservation_date));

    $subject = 'Merci pour votre visite au restaurant !';

    $content = '<h2>Merci pour votre visite !</h2>';
    $content .= '<p>Bonjour ' . esc_html($first_name) . ',</p>';
    $content .= '<p>Merci d\'être venu(e) au restaurant ' . esc_html($visit_date) . ' ! Nous espérons que vous avez passé un excellent moment.</p>';
    $content .= '<p>Votre avis compte beaucoup pour nous. Si vous avez apprécié l\'expérience, un petit mot sur Google nous aide énormément :</p>';

    $google_url = get_option('gastro_starter_followup_google_url', 'https://g.page/r/CaJeDfuzM41pEAE/review');
    $content .= '<div style="text-align:center; margin: 25px 0;">';
    $content .= '<a href="' . esc_url($google_url) . '" style="display:inline-block; background-color:#b5a692; color:#ffffff; text-decoration:none; padding:12px 25px; border-radius:25px; font-weight:500; font-size:14px;">Laisser un avis Google</a>';
    $content .= '</div>';

    $content .= '<p>Et pour ne rien manquer de nos soirées, événements et nouveautés :</p>';

    $content .= '<div style="text-align:center; margin: 25px 0;">';
    $content .= '<a href="https://instagram.com/mon-restaurant" style="display:inline-block; background-color:#3a3c36; color:#ffffff; text-decoration:none; padding:12px 25px; border-radius:25px; font-weight:500; font-size:14px;">Suivre sur Instagram</a>';
    $content .= '&nbsp;&nbsp;';
    $content .= '<a href="https://www.facebook.com/profile.php?id=100027654893543" style="display:inline-block; background-color:#1877F2; color:#ffffff; text-decoration:none; padding:12px 25px; border-radius:25px; font-weight:500; font-size:14px;">Suivre sur Facebook</a>';
    $content .= '</div>';

    $content .= '<p style="margin-top:30px;">À très bientôt au restaurant !</p>';
    $content .= '<p>L\'équipe du restaurant</p>';

    $sent = $email_manager->send_email(
        $reservation->customer_email,
        $subject,
        $content,
        array(),
        3,
        'followup',
        $reservation_id
    );

    if ($sent) {
        $wpdb->update(
            $table_name,
            array('followup_email_sent' => 1),
            array('id' => $reservation_id)
        );
    }

    return $sent;
}

/**
 * Annuler une réservation
 */
function gastro_starter_cancel_reservation($reservation_id, $nonce) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    
    // Vérifier le nonce
    if (!wp_verify_nonce($nonce, 'cancel_reservation_' . $reservation_id)) {
        return new WP_Error('invalid_nonce', 'Lien d\'annulation invalide');
    }
    
    // Récupérer la réservation
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $reservation_id
    ));
    
    if (!$reservation) {
        return new WP_Error('not_found', 'Réservation non trouvée');
    }
    
    // Vérifier si la réservation peut être annulée (24h avant)
    $reservation_time = strtotime($reservation->reservation_date . ' ' . $reservation->reservation_time);
    if (time() > ($reservation_time - 24 * 3600)) {
        return new WP_Error('too_late', 'L\'annulation n\'est plus possible (moins de 24h avant)');
    }
    
    // Mettre à jour le statut
    $result = $wpdb->update(
        $table_name,
        array('status' => 'cancelled'),
        array('id' => $reservation_id)
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Erreur lors de l\'annulation');
    }
    
    $email_manager = Gastro_Starter_Email_Manager::get_instance();

    // Envoyer un email de confirmation d'annulation
    $subject = __('Confirmation d\'annulation - Mon Restaurant', 'gastro-starter');
    
    $content = '<h2>' . __('Votre réservation a été annulée', 'gastro-starter') . '</h2>';
    $content .= '<p>Bonjour ' . esc_html($reservation->customer_name) . ',</p>';
    $content .= '<p>Votre réservation du ' . esc_html(date_i18n('l j F Y', strtotime($reservation->reservation_date))) . ' à ' . esc_html(date_i18n('H:i', strtotime($reservation->reservation_time))) . ' pour ' . intval($reservation->people) . ' personnes a bien été annulée.</p>';
    $content .= '<p>Nous espérons vous accueillir prochainement.</p>';
    $content .= '<p>L\'équipe du restaurant</p>';
    
    $email_manager->send_email($reservation->customer_email, $subject, $content);
    
    // Vérifier la liste d'attente
    gastro_starter_check_waiting_list($reservation->reservation_date, $reservation->reservation_time);
    
    return true;
}

/**
 * Ajouter à la liste d'attente
 */
function gastro_starter_add_to_waiting_list($reservation_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $waiting_list_table = $wpdb->prefix . 'reservations_waiting_list';
    
    // Récupérer la réservation
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $reservation_id
    ));
    
    if (!$reservation) {
        return false;
    }
    
    // Ajouter à la liste d'attente
    $result = $wpdb->insert(
        $waiting_list_table,
        array(
            'reservation_id' => $reservation_id,
            'date_added' => current_time('mysql'),
            'status' => 'waiting'
        )
    );
    
    if ($result === false) {
        return false;
    }
    
    $email_manager = Gastro_Starter_Email_Manager::get_instance();

    // Envoyer un email au client
    $subject = __('Liste d\'attente - Mon Restaurant', 'gastro-starter');
    
    $content = '<h2>' . __('Votre demande est sur liste d\'attente', 'gastro-starter') . '</h2>';
    $content .= '<p>Bonjour ' . esc_html($reservation->customer_name) . ',</p>';
    $content .= '<p>Votre demande de réservation du ' . esc_html(date_i18n('l j F Y', strtotime($reservation->reservation_date))) . ' à ' . esc_html(date_i18n('H:i', strtotime($reservation->reservation_time))) . ' pour ' . intval($reservation->people) . ' personnes a été placée sur liste d\'attente.</p>';
    $content .= '<p>Nous vous contacterons dès qu\'une place se libère.</p>';
    $content .= '<p>L\'équipe du restaurant</p>';
    
    $email_manager->send_email($reservation->customer_email, $subject, $content);
    
    return true;
}

/**
 * Vérifier la liste d'attente après une annulation
 */
function gastro_starter_check_waiting_list($date, $time) {
    global $wpdb;
    $waiting_list_table = $wpdb->prefix . 'reservations_waiting_list';
    $reservations_table = $wpdb->prefix . 'reservations';
    
    // Récupérer la première réservation en attente
    $waiting_reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT w.*, r.* FROM $waiting_list_table w 
        JOIN $reservations_table r ON w.reservation_id = r.id 
        WHERE r.reservation_date = %s 
        AND r.reservation_time = %s 
        AND w.status = 'waiting' 
        ORDER BY w.date_added ASC 
        LIMIT 1",
        $date,
        $time
    ));
    
    if ($waiting_reservation) {
        // Mettre à jour le statut de la réservation
        $wpdb->update(
            $reservations_table,
            array('status' => 'confirmed'),
            array('id' => $waiting_reservation->reservation_id)
        );
        
        // Mettre à jour la liste d'attente
        $wpdb->update(
            $waiting_list_table,
            array('status' => 'confirmed'),
            array('id' => $waiting_reservation->id)
        );
        
        $email_manager = Gastro_Starter_Email_Manager::get_instance();

        // Envoyer un email au client
        $subject = __('Réservation confirmée - Mon Restaurant', 'gastro-starter');
        
        $content = '<h2>' . __('Votre réservation est confirmée', 'gastro-starter') . '</h2>';
        $content .= '<p>Bonjour ' . esc_html($waiting_reservation->customer_name) . ',</p>';
        $content .= '<p>Une place s\'est libérée ! Votre réservation du ' . esc_html(date_i18n('l j F Y', strtotime($waiting_reservation->reservation_date))) . ' à ' . esc_html(date_i18n('H:i', strtotime($waiting_reservation->reservation_time))) . ' pour ' . intval($waiting_reservation->people) . ' personnes est maintenant confirmée.</p>';
        $content .= '<p>Nous avons hâte de vous accueillir !</p>';
        $content .= '<p>L\'équipe du restaurant</p>';
        
        $email_manager->send_email($waiting_reservation->customer_email, $subject, $content);
    }
}

/**
 * Créer la table de liste d'attente lors de l'activation du thème
 */
function gastro_starter_create_waiting_list_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations_waiting_list';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        reservation_id bigint(20) NOT NULL,
        date_added datetime NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'waiting',
        PRIMARY KEY  (id),
        KEY reservation_id (reservation_id),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'gastro_starter_create_waiting_list_table'); 