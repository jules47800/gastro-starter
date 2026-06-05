<?php
/**
 * Gestionnaire d'emails pour Mon Restaurant
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Email_Manager {
    private static $instance = null;
    private $restaurant_name;
    private $restaurant_address;
    private $restaurant_phone;
    private $restaurant_url;
    private $restaurant_siret;

    private function __construct() {
        $this->restaurant_name = get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant');
        $this->restaurant_address = get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris');
        $this->restaurant_phone = get_theme_mod('gastro_starter_restaurant_phone', '+33 5 53 63 80 80');
        $this->restaurant_url = home_url();
        $this->restaurant_siret = get_theme_mod('gastro_starter_restaurant_siret', '987 558 673');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Envoie un email avec le template Mon Restaurant et retry automatique
     * 
     * @param string|array $to Destinataire(s)
     * @param string $subject Sujet
     * @param string $content Contenu HTML
     * @param array $headers Headers personnalisés
     * @param int $max_attempts Nombre maximum de tentatives (défaut: 3)
     * @param string $email_type Type d'email pour logging
     * @param int $reservation_id ID de réservation associée
     * @return bool Succès de l'envoi
     */
    public function send_email($to, $subject, $content, $headers = array(), $max_attempts = 3, $email_type = 'general', $reservation_id = null) {
        if (empty($headers)) {
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->restaurant_name . ' <contact@mon-restaurant.fr>',
                'Reply-To: contact@mon-restaurant.fr'
            );
        }

        $message = $this->get_email_template($content);

        // Injecter le pixel de tracking
        if (class_exists('Gastro_Starter_Email_Tracking')) {
            $recipient = is_array($to) ? $to[0] : $to;
            $tracking_id = Gastro_Starter_Email_Tracking::register_send($recipient, $subject, $email_type);
            $message = apply_filters('gastro_starter_email_html', $message, $tracking_id);
        }

        return $this->send_with_retry($to, $subject, $message, $headers, $max_attempts, $email_type, $reservation_id);
    }
    
    /**
     * Envoie un email avec retry automatique et logging
     * 
     * @param string|array $to Destinataire(s)
     * @param string $subject Sujet
     * @param string $message Message complet (template appliqué)
     * @param array $headers Headers
     * @param int $max_attempts Nombre maximum de tentatives
     * @param string $email_type Type d'email
     * @param int $reservation_id ID de réservation
     * @return bool Succès de l'envoi
     */
    private function send_with_retry($to, $subject, $message, $headers, $max_attempts = 3, $email_type = 'general', $reservation_id = null) {
        $attempt = 0;
        $sent = false;
        $last_error = '';
        
        // Normaliser $to en string pour le logging
        $recipient_log = is_array($to) ? implode(', ', $to) : $to;
        
        while ($attempt < $max_attempts && !$sent) {
            $attempt++;
            
            error_log("[EMAIL] Tentative $attempt/$max_attempts - Envoi à: $recipient_log - Sujet: $subject");
            
            // Tenter l'envoi
            $sent = wp_mail($to, $subject, $message, $headers);
            
            if (!$sent) {
                $last_error = "Tentative $attempt/$max_attempts échouée";
                error_log("[EMAIL ÉCHEC] $last_error - Destinataire: $recipient_log");
                
                // Attendre avant de réessayer (sauf pour la dernière tentative)
                if ($attempt < $max_attempts) {
                    sleep(2); // Pause de 2 secondes entre les tentatives
                }
            } else {
                error_log("[EMAIL SUCCÈS] Envoyé après $attempt tentative(s) - Destinataire: $recipient_log");
            }
        }
        
        // Logger le résultat final
        if (class_exists('Gastro_Starter_Email_Logger')) {
            Gastro_Starter_Email_Logger::log_email_attempt(
                $recipient_log,
                $subject,
                $sent,
                $email_type,
                $attempt,
                $sent ? '' : $last_error,
                $reservation_id
            );
        }
        
        return $sent;
    }

    /**
     * Génère le template HTML pour les emails
     */
    private function get_email_template($content_html) {
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : get_template_directory_uri() . '/assets/images/logo.png';
        $bg_color = '#f5f1ea';
        $main_bg_color = '#ffffff';
        $text_color = '#333333';
        $accent_color = '#b5a692';
        $footer_text_color = '#666666';

        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($this->restaurant_name) . '</title>
            <style>
                /* Style de base pour les clients qui supportent <style> */
                body {
                    margin: 0;
                    padding: 0;
                    background-color: ' . $bg_color . ';
                    font-family: Arial, sans-serif;
                }
                .email-container {
                    width: 100%;
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: ' . $main_bg_color . ';
                }
                .content-cell {
                    padding: 30px;
                }
                 /* Style pour les détails de réservation */
                .reservation-details {
                    background-color: #f9f7f4;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 25px 0;
                    border: 1px solid #d8cfc0;
                }
                .detail-row {
                    margin-bottom: 12px;
                    font-size: 1rem;
                    line-height: 1.5;
                }
                .detail-label {
                    font-weight: 500;
                    color: ' . $accent_color . ';
                    min-width: 130px;
                    display: inline-block;
                }
                 /* Bouton d\'action */
                .call-to-action {
                    display: inline-block;
                    background-color: ' . $accent_color . ';
                    color: #ffffff !important;
                    text-decoration: none;
                    padding: 12px 28px;
                    border-radius: 25px;
                    font-weight: 500;
                    margin-top: 25px;
                    font-size: 1rem;
                    border: none;
                }
                @media screen and (max-width: 600px) {
                    .content-cell {
                        padding: 15px !important;
                    }
                }
            </style>
        </head>
        <body style="margin: 0; padding: 0; background-color: ' . $bg_color . '; font-family: Arial, sans-serif;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: ' . $bg_color . ';">
                <tr>
                    <td align="center">
                        <table class="email-container" width="100%" border="0" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: ' . $main_bg_color . '; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden;">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #f5f1ea; padding: 30px 20px 20px 20px;">
                                    <a href="' . esc_url($this->restaurant_url) . '">
                                        <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($this->restaurant_name) . '" style="max-width: 120px; margin-bottom: 10px;">
                                    </a>
                                </td>
                            </tr>
                            <!-- Body -->
                            <tr>
                                <td class="content-cell" style="padding: 40px; color: ' . $text_color . '; font-size: 16px; line-height: 1.6;">
                                    ' . $content_html . '
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td align="center" style="background-color: #f5f1ea; padding: 30px; border-top: 1px solid #d8cfc0; font-size: 14px; color: ' . $footer_text_color . ';">
                                    <p style="margin: 0 0 10px 0; font-weight: bold;">' . esc_html($this->restaurant_name) . '</p>
                                    <p style="margin: 0 0 10px 0;">' . esc_html($this->restaurant_address) . '</p>
                                    <p style="margin: 0 0 15px 0;">
                                        <a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $this->restaurant_phone)) . '" style="color: ' . $footer_text_color . '; text-decoration: none;">' . esc_html($this->restaurant_phone) . '</a>
                                        | <a href="' . esc_url($this->restaurant_url) . '" style="color: ' . $footer_text_color . '; text-decoration: none;">' . esc_html(str_replace(['http://', 'https://'], '', $this->restaurant_url)) . '</a>
                                    </p>
                                    <p style="font-size: 12px; color: #888; margin: 20px 0 0 0; line-height: 1.4;">
                                        ' . sprintf(__('SIRET : %s', 'gastro-starter'), esc_html($this->restaurant_siret)) . '<br>
                                        ' . __('Vous recevez cet email suite à une action sur notre site. Conformément à la loi Informatique et Libertés et au RGPD, vous disposez d\'un droit d\'accès et de rectification de vos données.', 'gastro-starter') . '
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }

    /**
     * Génère le HTML complet d'un email à partir de contenu libre
     */
    public function render_template($content_html) {
        return $this->get_email_template($content_html);
    }

    /**
     * Envoie un email de confirmation de réservation
     */
    public function send_reservation_confirmation($reservation) {
        // Récupérer les options
        $table_hold_time = get_option('gastro_starter_table_hold_time', 15);
        $phone_number = $this->restaurant_phone;

        // --- Email au client ---
        $client_content = sprintf(
            '<h2>%s</h2>
            <p>%s %s,</p>
            <p>%s</p>
            <div class="reservation-details">
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
            </div>
            <p style="background-color: #fef9e7; border-left: 4px solid #f7d358; padding: 15px; margin: 20px 0;">
                <strong>%s</strong> %s<br><br>
                %s
            </p>
            <p>%s</p>
            <p class="legal-notice">%s</p>',
            __('Confirmation de votre réservation', 'gastro-starter'),
            __('Bonjour', 'gastro-starter'),
            esc_html($reservation->customer_name),
            __('Votre réservation a bien été confirmée.', 'gastro-starter'),
            __('Date :', 'gastro-starter'),
            date_i18n('l j F Y', strtotime($reservation->reservation_date)),
            __('Heure :', 'gastro-starter'),
            date_i18n('H:i', strtotime($reservation->reservation_time)),
            __('Nombre de personnes :', 'gastro-starter'),
            esc_html($reservation->people),
            __('Référence :', 'gastro-starter'),
            sprintf('RES-%06d', $reservation->id),
            __('Merci de noter :', 'gastro-starter'),
            sprintf(
                __('la table est maintenue pendant %d minutes après l\'heure prévue. Après ce délai, elle pourra être réattribuée.', 'gastro-starter'),
                $table_hold_time
            ),
            sprintf(
                __('En cas d\'annulation, merci de nous contacter au : %s', 'gastro-starter'),
                '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone_number)) . '" style="color: #333; text-decoration: underline;">' . esc_html($phone_number) . '</a>'
            ),
            __('L\'équipe du restaurant', 'gastro-starter'),
            __('Cette confirmation de réservation fait office de contrat entre vous et le restaurant. En cas de litige, elle pourra être utilisée comme preuve de réservation.', 'gastro-starter')
        );

        $client_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->restaurant_name . ' <contact@mon-restaurant.fr>',
            'Reply-To: contact@mon-restaurant.fr'
        );
        
        $client_sent = $this->send_email(
            $reservation->customer_email,
            sprintf(__('Confirmation de votre réservation #%06d - Mon Restaurant', 'gastro-starter'), $reservation->id),
            $client_content,
            $client_headers,
            3, // max_attempts
            'reservation_confirmation', // email_type
            $reservation->id // reservation_id
        );

        // --- Email à l'administrateur ---
        $admin_content = sprintf(
            '<h2>%s</h2>
            <div class="reservation-details">
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
            </div>
            <p><strong>%s :</strong></p>
            <p>%s</p>',
            sprintf(__('Nouvelle réservation #%06d', 'gastro-starter'), $reservation->id),
            __('Référence', 'gastro-starter'),
            sprintf('RES-%06d', $reservation->id),
            __('Client', 'gastro-starter'),
            esc_html($reservation->customer_name),
            __('Email', 'gastro-starter'),
            esc_html($reservation->customer_email),
            __('Téléphone', 'gastro-starter'),
            esc_html($reservation->customer_phone),
            __('Date', 'gastro-starter'),
            date_i18n('l j F Y', strtotime($reservation->reservation_date)),
            __('Heure', 'gastro-starter'),
            date_i18n('H:i', strtotime($reservation->reservation_time)),
            __('Service', 'gastro-starter'),
            __('Réservation', 'gastro-starter'),
            __('Nombre de personnes', 'gastro-starter'),
            esc_html($reservation->people),
            __('Notes', 'gastro-starter'),
            nl2br(esc_html($reservation->notes))
        );

        $admin_users = get_users(array('role' => 'administrator'));
        $admin_emails = array();
        foreach ($admin_users as $admin) {
            $admin_emails[] = $admin->user_email;
        }
        
        $admin_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->restaurant_name . ' <contact@mon-restaurant.fr>',
            'Reply-To: ' . $reservation->customer_name . ' <' . $reservation->customer_email . '>'
        );
        
        $admin_sent = $this->send_email(
            $admin_emails,
            sprintf(__('Nouvelle réservation #%06d - %s personnes le %s', 'gastro-starter'),
                $reservation->id,
                $reservation->people,
                date_i18n('d/m/Y à H:i', strtotime($reservation->reservation_date . ' ' . $reservation->reservation_time))
            ),
            $admin_content,
            $admin_headers,
            3, // max_attempts
            'admin_notification', // email_type
            $reservation->id // reservation_id
        );
        
        return $client_sent;
    }

    /**
     * Envoie un email de rappel de réservation
     */
    public function send_reminder_email($reservation) {
        $content = sprintf(
            '<h2>%s</h2>
            <p>%s %s,</p>
            <p>%s</p>
            <div class="reservation-details">
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
            </div>
            <p>%s</p>
            <p>%s</p>
            <p>%s</p>',
            __('Rappel de votre réservation', 'gastro-starter'),
            __('Bonjour', 'gastro-starter'),
            esc_html($reservation->customer_name),
            __('Nous vous rappelons votre réservation à venir au restaurant Mon Restaurant :', 'gastro-starter'),
            __('Date :', 'gastro-starter'),
            date_i18n('l j F Y', strtotime($reservation->reservation_date)),
            __('Heure :', 'gastro-starter'),
            date_i18n('H:i', strtotime($reservation->reservation_time)),
            __('Nombre de personnes :', 'gastro-starter'),
            esc_html($reservation->people),
            __('Référence :', 'gastro-starter'),
            sprintf('RES-%06d', $reservation->id),
            __('En cas d\'empêchement, merci de nous prévenir au plus tôt par téléphone.', 'gastro-starter'),
            __('Nous avons hâte de vous accueillir !', 'gastro-starter'),
            __('L\'équipe du restaurant', 'gastro-starter')
        );

        return $this->send_email(
            $reservation->customer_email,
            sprintf(__('Rappel : votre réservation au restaurant - %s', 'gastro-starter'),
                date_i18n('l j F', strtotime($reservation->reservation_date))
            ),
            $content,
            array(), // headers par défaut
            3, // max_attempts
            'reminder', // email_type
            $reservation->id // reservation_id
        );
    }

    /**
     * Envoie un email d'annulation de réservation
     */
    public function send_cancellation_email($reservation) {
        $content = sprintf(
            '<h2>%s</h2>
            <p>%s %s,</p>
            <p>%s</p>
            <div class="reservation-details">
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
                <div class="detail-row"><span class="detail-label">%s</span> %s</div>
            </div>
            <p>%s</p>
            <p>%s</p>
            <p>%s</p>
            <p>%s</p>',
            __('Confirmation d\'annulation de réservation', 'gastro-starter'),
            __('Bonjour', 'gastro-starter'),
            esc_html($reservation->customer_name),
            __('Nous confirmons l\'annulation de votre réservation :', 'gastro-starter'),
            __('Date :', 'gastro-starter'),
            date_i18n('l j F Y', strtotime($reservation->reservation_date)),
            __('Heure :', 'gastro-starter'),
            date_i18n('H:i', strtotime($reservation->reservation_time)),
            __('Nombre de personnes :', 'gastro-starter'),
            esc_html($reservation->people),
            __('Référence :', 'gastro-starter'),
            sprintf('RES-%06d', $reservation->id),
            __('Si vous souhaitez effectuer une nouvelle réservation, n\'hésitez pas à nous contacter par téléphone ou à réserver directement sur notre site.', 'gastro-starter'),
            __('Nous espérons avoir le plaisir de vous accueillir prochainement au restaurant.', 'gastro-starter'),
            __('Cordialement,', 'gastro-starter'),
            __('L\'équipe du restaurant', 'gastro-starter')
        );

        return $this->send_email(
            $reservation->customer_email,
            sprintf(__('Annulation de votre réservation #%06d - Mon Restaurant', 'gastro-starter'),
                $reservation->id
            ),
            $content,
            array(), // headers par défaut
            3, // max_attempts
            'cancellation', // email_type
            $reservation->id // reservation_id
        );
    }
}

// Fonction utilitaire pour accéder au gestionnaire d'emails
if (!function_exists('gastro_starter_get_email_manager')) {
function gastro_starter_get_email_manager() {
    return Gastro_Starter_Email_Manager::get_instance();
    }
} 