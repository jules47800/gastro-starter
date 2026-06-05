<?php
/**
 * Classe d'envoi d'emails Soirées Spéciales via l'API Brevo
 *
 * Génère le HTML du template et envoie en masse via l'API transactionnelle Brevo.
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Brevo_Sender {

    private $api_key;
    private $sender_name;
    private $sender_email;
    private $api_url = 'https://api.brevo.com/v3/smtp/email';

    public function __construct() {
        $this->api_key      = get_option('gastro_starter_brevo_api_key', '');
        $this->sender_name  = get_option('gastro_starter_brevo_sender_name', 'Mon Restaurant');
        $this->sender_email = get_option('gastro_starter_brevo_sender_email', 'contact@mon-restaurant.fr');
    }

    /**
     * Vérifie si Brevo est correctement configuré
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Récupère les contacts selon l'audience choisie
     *
     * @param string $audience 'all' ou 'newsletter'
     * @return array Liste d'emails (rétro-compatibilité)
     */
    public function get_contacts($audience = 'newsletter') {
        $contacts = $this->get_contacts_with_phones($audience);
        return array_map(function($c) { return $c['email']; }, $contacts);
    }

    /**
     * Récupère les contacts avec leur dernier numéro de téléphone connu
     *
     * @param string $audience 'all' ou 'newsletter'
     * @return array Tableau de ['email' => ..., 'phone' => ...]
     */
    public function get_contacts_with_phones($audience = 'newsletter') {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'customer_stats';
        $res_table   = $wpdb->prefix . 'reservations';

        $newsletter_where = ($audience === 'all') ? '' : 'AND cs.newsletter = 1';

        $query = "
            SELECT
                cs.email,
                (SELECT r.customer_phone
                 FROM $res_table r
                 WHERE r.customer_email = cs.email
                   AND r.customer_phone IS NOT NULL
                   AND r.customer_phone != ''
                 ORDER BY r.created_at DESC
                 LIMIT 1
                ) AS phone
            FROM $stats_table cs
            WHERE cs.email IS NOT NULL AND cs.email != ''
            $newsletter_where
        ";

        $rows = $wpdb->get_results($query, ARRAY_A);
        $contacts = [];
        if (!is_array($rows)) return $contacts;

        foreach ($rows as $r) {
            if (!empty($r['email']) && is_email($r['email'])) {
                $contacts[] = [
                    'email' => $r['email'],
                    'phone' => isset($r['phone']) ? (string) $r['phone'] : '',
                ];
            }
        }
        return $contacts;
    }

    /**
     * Compte les contacts selon l'audience
     *
     * @param string $audience 'all' ou 'newsletter'
     * @return int
     */
    public function count_contacts($audience = 'newsletter') {
        return count($this->get_contacts_with_phones($audience));
    }

    /**
     * Détecte la langue préférée d'un contact (FR par défaut)
     * Heuristique : indicatif téléphonique international + TLD email anglophone.
     *
     * @param string $email
     * @param string $phone
     * @return string 'fr' | 'en'
     */
    public function detect_language($email = '', $phone = '') {
        // 1) Indicatif téléphonique (source la plus fiable)
        $phone_clean = preg_replace('/[^0-9+]/', '', (string) $phone);
        if (!empty($phone_clean) && strpos($phone_clean, '+') === 0) {
            // Indicatifs anglophones usuels
            $en_prefixes = ['+44', '+353', '+61', '+64', '+1'];
            foreach ($en_prefixes as $prefix) {
                if (strpos($phone_clean, $prefix) === 0) {
                    return 'en';
                }
            }
            // Indicatifs francophones explicites → FR
            $fr_prefixes = ['+33', '+32', '+41', '+352', '+377', '+262', '+590', '+594', '+596'];
            foreach ($fr_prefixes as $prefix) {
                if (strpos($phone_clean, $prefix) === 0) {
                    return 'fr';
                }
            }
        }

        // 2) TLD email (uniquement ceux explicitement anglophones — .com est trop ambigu)
        if (!empty($email) && strpos($email, '@') !== false) {
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            $en_tlds = ['uk', 'co.uk', 'ie', 'au', 'com.au', 'nz', 'co.nz', 'us'];
            foreach ($en_tlds as $tld) {
                if (substr($domain, -strlen($tld) - 1) === '.' . $tld || $domain === $tld) {
                    return 'en';
                }
            }
        }

        // Par défaut : FR
        return 'fr';
    }

    /**
     * Envoie la newsletter Soirées Spéciales pour un événement donné
     *
     * @param int    $post_id  ID du post event
     * @param string $audience 'all' ou 'newsletter'
     * @return array ['success' => bool, 'sent' => int, 'errors' => int, 'message' => string]
     */
    public function send_newsletter($post_id, $audience = 'newsletter') {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'sent'    => 0,
                'errors'  => 0,
                'message' => __('Clé API Brevo non configurée', 'gastro-starter'),
            ];
        }

        $contacts = $this->get_contacts_with_phones($audience);
        if (empty($contacts)) {
            return [
                'success' => false,
                'sent'    => 0,
                'errors'  => 0,
                'message' => __('Aucun contact trouvé pour cette audience', 'gastro-starter'),
            ];
        }

        // Pré-générer les deux versions (FR/EN) pour éviter de régénérer à chaque email
        $rendered = [
            'fr' => [
                'html'    => $this->generate_email_html($post_id, 'fr'),
                'subject' => $this->generate_subject($post_id, 'fr'),
            ],
            'en' => [
                'html'    => $this->generate_email_html($post_id, 'en'),
                'subject' => $this->generate_subject($post_id, 'en'),
            ],
        ];

        // Envoyer par batches
        $batch_size = 50;
        $batches = array_chunk($contacts, $batch_size);
        $total_sent = 0;
        $total_errors = 0;
        $error_messages = [];

        foreach ($batches as $batch_index => $batch) {
            $result = $this->send_batch($batch, $rendered);

            $total_sent   += $result['sent'];
            $total_errors += $result['errors'];

            if ($result['errors'] > 0 && !empty($result['message'])) {
                $error_messages[] = sprintf('Batch %d: %s', $batch_index + 1, $result['message']);
            }

            // Petit délai entre les batches pour ne pas surcharger l'API
            if ($batch_index < count($batches) - 1) {
                usleep(200000); // 200ms
            }
        }

        // Logger le résultat
        $this->log_send_result($post_id, $total_sent, $total_errors, $audience);

        // Mettre à jour les meta du post
        update_post_meta($post_id, 'email_sent_at', current_time('mysql'));
        update_post_meta($post_id, 'email_sent_count', $total_sent);
        update_post_meta($post_id, 'email_sent_errors', $total_errors);
        update_post_meta($post_id, 'email_sent_audience', $audience);

        $success = $total_errors === 0;
        $message = sprintf(
            __('%d emails envoyés avec succès', 'gastro-starter'),
            $total_sent
        );
        if ($total_errors > 0) {
            $message .= sprintf(__(', %d erreurs', 'gastro-starter'), $total_errors);
        }

        return [
            'success'  => $success,
            'sent'     => $total_sent,
            'errors'   => $total_errors,
            'message'  => $message,
            'details'  => $error_messages,
        ];
    }

    /**
     * Envoie un batch d'emails via l'API Brevo
     * Chaque email est envoyé individuellement pour permettre le tracking Brevo
     * et la personnalisation de la langue selon le contact.
     *
     * @param array $contacts Tableau de ['email' => ..., 'phone' => ...]
     * @param array $rendered ['fr' => ['html'=>, 'subject'=>], 'en' => ['html'=>, 'subject'=>]]
     * @return array ['sent' => int, 'errors' => int, 'message' => string]
     */
    private function send_batch($contacts, $rendered) {
        $sent = 0;
        $errors = 0;
        $last_error = '';

        foreach ($contacts as $contact) {
            $email = is_array($contact) ? ($contact['email'] ?? '') : (string) $contact;
            $phone = is_array($contact) ? ($contact['phone'] ?? '') : '';
            if (empty($email)) continue;

            $lang    = $this->detect_language($email, $phone);
            $subject = $rendered[$lang]['subject'] ?? $rendered['fr']['subject'];
            $html    = $rendered[$lang]['html']    ?? $rendered['fr']['html'];

            $payload = [
                'sender'      => [
                    'name'  => $this->sender_name,
                    'email' => $this->sender_email,
                ],
                'to'          => [['email' => $email]],
                'subject'     => $subject,
                'htmlContent' => $html,
                'headers'     => [
                    'X-Mailin-Tag'      => 'becfin-newsletter',
                    'X-Mailin-Language' => $lang,
                ],
            ];

            $response = wp_remote_post($this->api_url, [
                'headers' => [
                    'api-key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => json_encode($payload),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $errors++;
                $last_error = $response->get_error_message();
                error_log("[BREVO] Erreur envoi à $email ($lang): " . $last_error);
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                $errors++;
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $last_error = isset($body['message']) ? $body['message'] : "HTTP $code";
                error_log("[BREVO] Erreur API pour $email ($lang) ($code): " . $last_error);
                continue;
            }

            $sent++;
        }

        return [
            'sent'    => $sent,
            'errors'  => $errors,
            'message' => $last_error,
        ];
    }

    /**
     * Génère le sujet de l'email
     *
     * @param int    $post_id
     * @param string $lang 'fr' ou 'en'
     */
    public function generate_subject($post_id, $lang = 'fr') {
        $title = get_the_title($post_id);

        // Titre EN optionnel saisi par l'admin
        if ($lang === 'en') {
            $title_en = get_post_meta($post_id, 'email_title_en', true);
            if (!empty($title_en)) $title = $title_en;
        }

        $event_date = get_post_meta($post_id, 'event_date', true);

        $label = ($lang === 'en') ? 'Special Evening' : 'Soirée Spéciale';

        if ($event_date) {
            $date_obj = new DateTime($event_date);
            if ($lang === 'en') {
                $formatted_date = $date_obj->format('j F');
            } else {
                $formatted_date = date_i18n('j F', $date_obj->getTimestamp());
            }
            return sprintf('%s — %s — %s', $label, $title, $formatted_date);
        }

        return sprintf('%s — %s — Mon Restaurant', $label, $title);
    }

    /**
     * Retourne les chaînes traduites de l'email
     */
    private function get_email_translations($lang = 'fr') {
        if ($lang === 'en') {
            return [
                'html_lang'        => 'en',
                'meta_title'       => 'Special Evening — Mon Restaurant',
                'preview_suffix'   => 'at our restaurant.',
                'default_subtitle' => 'Special Evening',
                'default_places'   => 'Limited seating',
                'time_prefix'      => 'From ',
                'info_time'        => 'Time',
                'info_price'       => 'Price',
                'info_places'      => 'Seats',
                'menu_label'       => 'The menu',
                'cta_button'       => 'Book my seat',
                'cta_phone'        => 'or call us at',
                'wine_label'       => 'Wine pairing',
                'gift_title'       => 'Gift a Soirées Spéciales evening',
                'gift_desc'        => 'Our gift cards are available online — perfect for a special occasion.',
                'gift_link'        => 'Discover our gift cards →',
                'gift_url_path'    => '/bon-achat/',
                'footer_tagline'   => 'Restaurant — Notre Ville',
                'footer_note'      => "You are receiving this email because you are subscribed to Mon Restaurant's newsletter.",
                'unsubscribe'      => 'Unsubscribe',
                'privacy'          => 'Privacy policy',
                'privacy_path'     => '/politique-confidentialite/',
                'social_instagram' => 'Instagram',
                'social_website'   => 'Website',
                'social_email'     => 'Email',
            ];
        }
        return [
            'html_lang'        => 'fr',
            'meta_title'       => 'Soirée Spéciale — Mon Restaurant',
            'preview_suffix'   => 'au restaurant.',
            'default_subtitle' => 'Soirée Spéciale',
            'default_places'   => 'Places limitées',
            'time_prefix'      => 'À partir de ',
            'info_time'        => 'Horaire',
            'info_price'       => 'Tarif',
            'info_places'      => 'Places',
            'menu_label'       => 'Le menu',
            'cta_button'       => 'Réserver ma place',
            'cta_phone'        => 'ou appelez-nous au',
            'wine_label'       => 'Accord mets &amp; vins',
            'gift_title'       => 'Offrez une soirée Soirées Spéciales',
            'gift_desc'        => 'Nos bons-cadeaux sont disponibles en ligne, à offrir pour une occasion spéciale.',
            'gift_link'        => 'Découvrir les bons-cadeaux →',
            'gift_url_path'    => '/bon-achat/',
            'footer_tagline'   => 'Restaurant — Notre Ville',
            'footer_note'      => 'Vous recevez cet email car vous êtes inscrit(e) à la newsletter du restaurant.',
            'unsubscribe'      => 'Se désinscrire',
            'privacy'          => 'Politique de confidentialité',
            'privacy_path'     => '/politique-confidentialite/',
            'social_instagram' => 'Instagram',
            'social_website'   => 'Site web',
            'social_email'     => 'Email',
        ];
    }

    /**
     * Génère le HTML complet de l'email à partir des meta du post event
     *
     * @param int    $post_id ID du post event
     * @param string $lang    'fr' ou 'en'
     * @return string HTML complet
     */
    public function generate_email_html($post_id, $lang = 'fr') {
        $t = $this->get_email_translations($lang);

        // ===== Données FR (source principale) =====
        $title           = get_the_title($post_id);
        $event_date      = get_post_meta($post_id, 'event_date', true);
        $event_time      = get_post_meta($post_id, 'event_time', true);
        $event_price     = get_post_meta($post_id, 'event_price', true);
        $subtitle        = get_post_meta($post_id, 'email_subtitle', true) ?: $t['default_subtitle'];
        $accroche        = get_post_meta($post_id, 'email_accroche', true) ?: get_the_excerpt($post_id);
        $places          = get_post_meta($post_id, 'email_places', true) ?: $t['default_places'];
        $menu_items      = get_post_meta($post_id, 'email_menu_items', true) ?: [];
        $citation        = get_post_meta($post_id, 'email_citation', true) ?: '';
        $citation_author = get_post_meta($post_id, 'email_citation_author', true) ?: "L'équipe du restaurant";
        $vins_text       = get_post_meta($post_id, 'email_vins_text', true) ?: '';
        $vins_price      = get_post_meta($post_id, 'email_vins_price', true) ?: '';

        // ===== Variantes EN (fallback sur FR si vides) =====
        if ($lang === 'en') {
            $title_en           = get_post_meta($post_id, 'email_title_en', true);
            $subtitle_en        = get_post_meta($post_id, 'email_subtitle_en', true);
            $accroche_en        = get_post_meta($post_id, 'email_accroche_en', true);
            $places_en          = get_post_meta($post_id, 'email_places_en', true);
            $citation_en        = get_post_meta($post_id, 'email_citation_en', true);
            $citation_author_en = get_post_meta($post_id, 'email_citation_author_en', true);
            $vins_text_en       = get_post_meta($post_id, 'email_vins_text_en', true);

            if (!empty($title_en))           $title           = $title_en;
            if (!empty($subtitle_en))        $subtitle        = $subtitle_en;
            if (!empty($accroche_en))        $accroche        = $accroche_en;
            if (!empty($places_en))          $places          = $places_en;
            if (!empty($citation_en))        $citation        = $citation_en;
            if (!empty($citation_author_en)) $citation_author = $citation_author_en;
            if (!empty($vins_text_en))       $vins_text       = $vins_text_en;

            // Items menu EN
            if (!empty($menu_items) && is_array($menu_items)) {
                foreach ($menu_items as $i => $item) {
                    if (!empty($item['name_en']))        $menu_items[$i]['name']        = $item['name_en'];
                    if (!empty($item['description_en'])) $menu_items[$i]['description'] = $item['description_en'];
                }
            }
        }

        // Image menu (remplace les items si définie)
        $menu_image_id = get_post_meta($post_id, 'email_menu_image_id', true);
        $menu_image_url = $menu_image_id ? wp_get_attachment_image_url($menu_image_id, 'large') : '';

        // Galerie
        $gallery_img1_id = get_post_meta($post_id, 'email_gallery_img1', true);
        $gallery_img2_id = get_post_meta($post_id, 'email_gallery_img2', true);

        // Image hero
        $image_id = get_post_meta($post_id, 'email_image_id', true);
        if ($image_id) {
            $hero_image = wp_get_attachment_image_url($image_id, 'large');
        } elseif (has_post_thumbnail($post_id)) {
            $hero_image = get_the_post_thumbnail_url($post_id, 'large');
        } else {
            $hero_image = home_url('/wp-content/themes/gastro-starter/assets/images/ambiance-soiree-restaurant.jpg');
        }

        // Formatage de la date
        $day = '';
        $month = '';
        if ($event_date) {
            $date_obj = new DateTime($event_date);
            $day = $date_obj->format('d');
            if ($lang === 'en') {
                $month = strtoupper($date_obj->format('M'));
            } else {
                $month = date_i18n('M', $date_obj->getTimestamp());
            }
        }

        // Formatage de l'heure (toujours au format HHhMM en FR, HH:MM en EN)
        $formatted_time = '';
        if ($event_time) {
            $time_obj = DateTime::createFromFormat('H:i', $event_time);
            if (!$time_obj) $time_obj = DateTime::createFromFormat('H:i:s', $event_time);
            if ($time_obj) {
                if ($lang === 'en') {
                    $formatted_time = $t['time_prefix'] . $time_obj->format('H:i');
                } else {
                    $formatted_time = $t['time_prefix'] . str_replace(':', 'h', $time_obj->format('H:i'));
                }
            }
        }

        // URL de réservation
        $reservation_url = home_url('/reserver/');
        if ($event_date) {
            $formatted_date_param = date('d/m/Y', strtotime($event_date));
            $reservation_url = add_query_arg('date', $formatted_date_param, $reservation_url);
        }

        // Logo
        $logo_url = home_url('/wp-content/themes/gastro-starter/assets/images/logo.png');
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full') ?: $logo_url;
        }

        // Téléphone
        $phone = get_theme_mod('gastro_starter_restaurant_phone', '05 53 24 00 35');
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);

        // Construire le HTML de la galerie d'ambiance
        $gallery_html = '';
        if ($gallery_img1_id && $gallery_img2_id) {
            $img1_url = wp_get_attachment_image_url($gallery_img1_id, 'large');
            $img2_url = wp_get_attachment_image_url($gallery_img2_id, 'large');
            
            if ($img1_url && $img2_url) {
                $gallery_html = '
          <!-- GALERIE D\'AMBIANCE -->
          <tr>
            <td style="padding: 20px 40px 10px 40px;" class="content-padding">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td valign="top" width="48%" style="width: 48%; padding-right: 2%;">
                    <img src="' . esc_url($img1_url) . '" alt="Ambiance 1" style="display: block; width: 100%; height: auto; border-radius: 4px; object-fit: cover; aspect-ratio: 4/5;" />
                  </td>
                  <td valign="top" width="48%" style="width: 48%; padding-left: 2%;">
                    <img src="' . esc_url($img2_url) . '" alt="Ambiance 2" style="display: block; width: 100%; height: auto; border-radius: 4px; object-fit: cover; aspect-ratio: 4/5;" />
                  </td>
                </tr>
              </table>
            </td>
          </tr>';
            }
        }

        // Construire le HTML du menu — soit une image, soit les items texte
        $menu_html = '';
        $has_menu_content = false;

        if (!empty($menu_image_url)) {
            // Option "image menu" : remplace complètement la liste d'items
            $has_menu_content = true;
            $menu_alt = ($lang === 'en') ? 'Menu' : 'Menu de la soirée';
            $menu_html = '
          <tr>
            <td style="padding: 0 40px 30px 40px;" class="content-padding">
              <img src="' . esc_url($menu_image_url) . '" alt="' . esc_attr($menu_alt) . '" style="display: block; width: 100%; max-width: 520px; height: auto; border-radius: 4px; margin: 0 auto;" />
            </td>
          </tr>';
        } elseif (!empty($menu_items) && is_array($menu_items)) {
            $has_menu_content = true;
            foreach ($menu_items as $i => $item) {
                $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                $name = esc_html($item['name'] ?? '');
                $desc = esc_html($item['description'] ?? '');
                $separator = ($i < count($menu_items) - 1) ? '
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 18px;">
                <tr><td style="border-top: 1px solid #f4f1eb;"></td></tr>
              </table>' : '';

                $menu_html .= '
          <tr>
            <td style="padding: 0 40px 20px 40px;" class="content-padding">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td style="width: 40px; vertical-align: top; padding-top: 2px;">
                    <p style="margin: 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 22px; font-weight: 400; color: #e8e3d9;">' . $num . '</p>
                  </td>
                  <td style="vertical-align: top; padding-left: 10px;">
                    <p style="margin: 0 0 4px 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 18px; font-weight: 500; color: #1a1a1a; line-height: 1.3;">' . $name . '</p>
                    <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 300; color: #8b8680; line-height: 1.5;">' . $desc . '</p>
                  </td>
                </tr>
              </table>' . $separator . '
            </td>
          </tr>';
            }
        }

        // Construire le bloc citation
        $citation_html = '';
        if (!empty($citation)) {
            $citation_html = '
          <tr>
            <td style="padding: 0;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9f6f1;">
                <tr>
                  <td style="padding: 45px 50px; text-align: center;" class="content-padding">
                    <p style="margin: 0 0 10px 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 40px; font-weight: 400; color: #e8e3d9; line-height: 1;">&ldquo;</p>
                    <p style="margin: 0 0 15px 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 18px; font-weight: 400; font-style: italic; color: #2d2824; line-height: 1.6; max-width: 420px; display: inline-block;">' . esc_html($citation) . '</p>
                    <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 400; color: #8b8680; letter-spacing: 1px;">— ' . esc_html($citation_author) . '</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>';
        }

        // Construire le bloc vins
        $vins_html = '';
        if (!empty($vins_text)) {
            $vins_desc = esc_html($vins_text);
            if (!empty($vins_price)) {
                $vins_desc .= ' (' . esc_html($vins_price) . ')';
            }
            $vins_html = '
          <tr>
            <td style="padding: 40px 40px 30px 40px; text-align: center;" class="content-padding">
              <p style="margin: 0 0 8px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 500; color: #8b8680; text-transform: uppercase; letter-spacing: 3px;">' . $t['wine_label'] . '</p>
              <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; font-weight: 300; color: #2d2824; line-height: 1.6; max-width: 440px; display: inline-block;">' . $vins_desc . '</p>
            </td>
          </tr>
          <tr>
            <td style="padding: 0 40px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr><td style="border-top: 1px solid #e8e3d9;"></td></tr>
              </table>
            </td>
          </tr>';
        }

        // Préparer un petit helper pour générer une ligne d'info pratique (sans emoji, avec libellé élégant)
        $build_info_row = function($label, $value, $is_last = false) {
            $pb = $is_last ? '0' : '14px';
            return '
                      <tr>
                        <td style="padding-bottom: ' . $pb . ';">
                          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                              <td width="90" style="width: 90px; vertical-align: middle;">
                                <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 10px; font-weight: 500; color: #8b8680; text-transform: uppercase; letter-spacing: 2px;">' . esc_html($label) . '</p>
                              </td>
                              <td style="vertical-align: middle;">
                                <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; font-weight: 400; color: #2d2824;">' . esc_html($value) . '</p>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>';
        };

        // Assembler les rows des infos pratiques (seulement celles ayant une valeur)
        $infos_rows = [];
        if (!empty($formatted_time)) $infos_rows[] = [$t['info_time'],   $formatted_time];
        if (!empty($event_price))    $infos_rows[] = [$t['info_price'],  $event_price];
        if (!empty($places))         $infos_rows[] = [$t['info_places'], $places];

        $infos_html = '';
        $count_infos = count($infos_rows);
        foreach ($infos_rows as $idx => $row) {
            $infos_html .= $build_info_row($row[0], $row[1], ($idx === $count_infos - 1));
        }

        // Assemblage final du template
        $html = '<!DOCTYPE html>
<html lang="' . esc_attr($t['html_lang']) . '" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>' . esc_html($t['meta_title']) . '</title>
  <style type="text/css">
    * { margin: 0; padding: 0; }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
    @import url(\'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap\');
    @media screen and (max-width: 600px) {
      .email-container { width: 100% !important; max-width: 100% !important; }
      .hero-image { height: 260px !important; }
      .hero-title { font-size: 32px !important; line-height: 38px !important; }
      .content-padding { padding-left: 20px !important; padding-right: 20px !important; }
      .cta-button { padding: 16px 36px !important; font-size: 13px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f1eb; font-family: \'Inter\', Arial, Helvetica, sans-serif;">
  <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all;">
    ' . esc_html($subtitle) . ' — ' . esc_html($title) . ' ' . esc_html($t['preview_suffix']) . '
  </div>

  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f1eb;">
    <tr>
      <td align="center" style="padding: 30px 10px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; background-color: #ffffff;">

          <!-- HEADER / LOGO -->
          <tr>
            <td style="padding: 35px 40px 20px 40px; text-align: center; background-color: #ffffff;">
              <a href="' . esc_url(home_url()) . '" target="_blank" style="text-decoration: none;">
                <img src="' . esc_url($logo_url) . '" width="200" alt="Mon Restaurant — Restaurant Atelier" style="display: inline-block; width: 200px; max-width: 200px; height: auto;" />
              </a>
            </td>
          </tr>

          <!-- Séparateur -->
          <tr>
            <td style="padding: 0 40px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr><td style="border-top: 1px solid #e8e3d9;"></td></tr>
              </table>
            </td>
          </tr>

          <!-- HERO IMAGE -->
          <tr>
            <td style="padding: 0;">
              <img src="' . esc_url($hero_image) . '" width="600" height="400" alt="' . esc_attr($title) . '" class="hero-image" style="display: block; width: 100%; height: 400px; object-fit: cover;" />
            </td>
          </tr>

          <!-- DATE + TITRE -->
          <tr>
            <td style="padding: 0; background-color: #ffffff;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td style="text-align: center; padding: 40px 40px 10px 40px;" class="content-padding">

                    <!-- Badge de date -->
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto 25px auto;">
                      <tr>
                        <td style="width: 80px; height: 80px; background-color: #1a1a1a; text-align: center; vertical-align: middle;">
                          <p style="margin: 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 30px; font-weight: 600; color: #ffffff; line-height: 1.1;">' . esc_html($day) . '</p>
                          <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 10px; font-weight: 500; color: #8b8680; text-transform: uppercase; letter-spacing: 2px;">' . esc_html($month) . '</p>
                        </td>
                      </tr>
                    </table>

                    <!-- Étiquette -->
                    <p style="margin: 0 0 12px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 500; color: #8b8680; text-transform: uppercase; letter-spacing: 3px;">' . esc_html($subtitle) . '</p>

                    <!-- Titre -->
                    <h1 class="hero-title" style="margin: 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 36px; font-weight: 500; color: #1a1a1a; line-height: 1.2; letter-spacing: 0.5px;">' . esc_html($title) . '</h1>

                    <!-- Point rouge signature -->
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 15px auto;">
                      <tr>
                        <td style="width: 8px; height: 8px; background-color: #e74c3c; border-radius: 50%; font-size: 0; line-height: 0;">&nbsp;</td>
                      </tr>
                    </table>

                    <!-- Accroche -->
                    <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 15px; font-weight: 300; color: #8b8680; line-height: 1.7; max-width: 440px; display: inline-block;">' . esc_html($accroche) . '</p>

                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- INFOS PRATIQUES -->
          <tr>
            <td style="padding: 30px 40px;" class="content-padding">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9f6f1; border: 1px solid #e8e3d9;">
                <tr>
                  <td style="padding: 28px 30px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">'
                    . $infos_html . '
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>'

        // GALERIE
        . $gallery_html

        // MENU
        . ($has_menu_content ? '
          <tr>
            <td style="padding: 0 40px 10px 40px;" class="content-padding">
              <p style="margin: 0 0 20px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 500; color: #8b8680; text-transform: uppercase; letter-spacing: 3px; text-align: center;">' . esc_html($t['menu_label']) . '</p>
            </td>
          </tr>' . $menu_html : '')

        // CTA RÉSERVER — Centré y compris sous Outlook (VML conditionnel + table align="center")
        . '
          <tr>
            <td align="center" style="padding: 30px 40px 15px 40px;" class="content-padding">
              <!--[if mso]>
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"><tr><td align="center" style="padding:0;">
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . esc_url($reservation_url) . '" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="0%" stroke="f" fillcolor="#1a1a1a">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:\'Inter\',Arial,sans-serif;font-size:12px;font-weight:500;letter-spacing:2px;text-transform:uppercase;">' . esc_html($t['cta_button']) . '</center>
              </v:roundrect>
              </td></tr></table>
              <![endif]-->
              <!--[if !mso]><!-- -->
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto;">
                <tr>
                  <td align="center" bgcolor="#1a1a1a" style="background-color: #1a1a1a;">
                    <a href="' . esc_url($reservation_url) . '" target="_blank" class="cta-button" style="display: inline-block; padding: 16px 44px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 500; color: #ffffff; background-color: #1a1a1a; text-decoration: none; text-transform: uppercase; letter-spacing: 2px; text-align: center; mso-hide:all;">' . esc_html($t['cta_button']) . '</a>
                  </td>
                </tr>
              </table>
              <!--<![endif]-->
            </td>
          </tr>
          <tr>
            <td style="padding: 0 40px 40px 40px; text-align: center;" class="content-padding">
              <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 300; color: #8b8680; line-height: 1.5;">' . esc_html($t['cta_phone']) . ' <a href="tel:+33' . esc_attr(ltrim($phone_clean, '0')) . '" style="color: #2d2824; text-decoration: none; font-weight: 400;">' . esc_html($phone) . '</a></p>
            </td>
          </tr>'

        // CITATION
        . $citation_html

        // VINS
        . $vins_html

        // BONS CADEAUX
        . '
          <tr>
            <td style="padding: 30px 40px; text-align: center;" class="content-padding">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border: 1px solid #e8e3d9;">
                <tr>
                  <td style="padding: 25px 30px;">
                    <p style="margin: 0 0 6px 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 20px; font-weight: 500; color: #1a1a1a;">' . esc_html($t['gift_title']) . '</p>
                    <p style="margin: 0 0 15px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 300; color: #8b8680; line-height: 1.5;">' . esc_html($t['gift_desc']) . '</p>
                    <a href="' . esc_url(home_url($t['gift_url_path'])) . '" target="_blank" style="display: inline-block; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 400; color: #1a1a1a; text-decoration: none; border-bottom: 1px solid #1a1a1a; padding-bottom: 2px; letter-spacing: 0.5px;">' . esc_html($t['gift_link']) . '</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="padding: 0;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #1a1a1a;">
                <tr>
                  <td style="padding: 40px 40px 20px 40px; text-align: center;" class="content-padding">
                    <p style="margin: 0 0 5px 0; font-family: \'Cormorant Garamond\', Georgia, serif; font-size: 24px; font-weight: 500; color: #ffffff; letter-spacing: 1px;">Mon Restaurant<span style="color: #e74c3c;">.</span></p>
                    <p style="margin: 0 0 20px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 10px; font-weight: 400; color: #8b8680; text-transform: uppercase; letter-spacing: 2px;">' . esc_html($t['footer_tagline']) . '</p>
                    <p style="margin: 0 0 5px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 300; color: #8b8680; line-height: 1.6;">6 avenue du 6 juin 1944</p>
                    <p style="margin: 0 0 15px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 300; color: #8b8680; line-height: 1.6;">75001 Paris</p>
                    <p style="margin: 0 0 20px 0;">
                      <a href="tel:+33' . esc_attr(ltrim($phone_clean, '0')) . '" style="font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 400; color: #ffffff; text-decoration: none;">' . esc_html($phone) . '</a>
                    </p>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto;">
                      <tr>
                        <td style="padding: 0 10px;"><a href="https://instagram.com/mon-restaurant" target="_blank" style="font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 400; color: #8b8680; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">' . esc_html($t['social_instagram']) . '</a></td>
                        <td style="width: 1px; background-color: #333333; font-size: 0;">&nbsp;</td>
                        <td style="padding: 0 10px;"><a href="' . esc_url(home_url()) . '" target="_blank" style="font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 400; color: #8b8680; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">' . esc_html($t['social_website']) . '</a></td>
                        <td style="width: 1px; background-color: #333333; font-size: 0;">&nbsp;</td>
                        <td style="padding: 0 10px;"><a href="mailto:contact@mon-restaurant.fr" style="font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 400; color: #8b8680; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">' . esc_html($t['social_email']) . '</a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding: 0 40px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="border-top: 1px solid #333333;"></td></tr></table></td>
                </tr>
                <tr>
                  <td style="padding: 20px 40px 30px 40px; text-align: center;" class="content-padding">
                    <p style="margin: 0 0 8px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 300; color: #555555; line-height: 1.5;">' . esc_html($t['footer_note']) . '</p>
                    <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; font-weight: 300; color: #555555; line-height: 1.5;">
                      <a href="{{ unsubscribe }}" style="color: #8b8680; text-decoration: underline;">' . esc_html($t['unsubscribe']) . '</a>
                      &nbsp;·&nbsp;
                      <a href="' . esc_url(home_url($t['privacy_path'])) . '" style="color: #8b8680; text-decoration: underline;">' . esc_html($t['privacy']) . '</a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

        return $html;
    }

    /**
     * Logger les résultats d'envoi
     */
    private function log_send_result($post_id, $sent, $errors, $audience) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_logs';

        $wpdb->insert($table, [
            'recipient'    => sprintf('Newsletter Soirées Spéciales (audience: %s)', $audience),
            'subject'      => $this->generate_subject($post_id),
            'email_type'   => 'becfin_newsletter',
            'status'       => $errors === 0 ? 'success' : 'partial',
            'attempts'     => 1,
            'error_message'=> $errors > 0 ? sprintf('%d erreurs sur %d envois', $errors, $sent + $errors) : '',
            'reservation_id' => null,
            'sent_at'      => current_time('mysql'),
        ]);

        error_log(sprintf(
            '[BREVO NEWSLETTER] Event #%d — %d envoyés, %d erreurs (audience: %s)',
            $post_id, $sent, $errors, $audience
        ));
    }
}
