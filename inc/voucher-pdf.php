<?php
/**
 * Générateur de PDF pour les bons d'achat
 * Utilise une approche HTML to PDF simple
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Générer un PDF de bon d'achat
 * @param object $voucher Objet voucher de la base de données
 * @return string Chemin du fichier PDF généré
 */
function gastro_starter_generate_voucher_pdf($voucher) {
    // Calculer la date de validité
    $created = new DateTime($voucher->created_at);
    $validity = clone $created;
    $validity->modify('+1 year');
    
    // Logo
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
    
    // Convertir le logo en base64 pour l'embarquer dans le PDF
    $logo_base64 = '';
    if ($logo_url) {
        $logo_path = str_replace(home_url('/'), ABSPATH, $logo_url);
        if (file_exists($logo_path)) {
            $logo_data = file_get_contents($logo_path);
            $logo_base64 = 'data:image/' . pathinfo($logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($logo_data);
        }
    }
    
    // Générer le HTML du PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 40px; }
            body {
                font-family: 'DejaVu Sans', Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background: #fff;
            }
            .voucher-container {
                max-width: 650px;
                margin: 0 auto;
                border: 4px solid #1a1a1a;
                padding: 50px 40px;
                position: relative;
            }
            .voucher-container::before {
                content: '';
                position: absolute;
                top: 12px;
                left: 12px;
                right: 12px;
                bottom: 12px;
                border: 1px solid #e8e3d9;
            }
            .header {
                text-align: center;
                padding-bottom: 30px;
                border-bottom: 2px solid #e8e3d9;
                margin-bottom: 40px;
            }
            .logo {
                max-width: 180px;
                height: auto;
                margin-bottom: 15px;
            }
            .restaurant-name {
                font-size: 28px;
                font-weight: bold;
                letter-spacing: 3px;
                color: #1a1a1a;
                margin: 10px 0;
            }
            .voucher-type {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 4px;
                color: #8b8680;
            }
            .amount-section {
                text-align: center;
                margin: 50px 0;
            }
            .amount-label {
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 2px;
                color: #8b8680;
                margin-bottom: 15px;
            }
            .amount-value {
                font-size: 80px;
                font-weight: 300;
                color: #1a1a1a;
                line-height: 1;
            }
            .details {
                padding: 30px 0;
                border-top: 2px solid #e8e3d9;
                border-bottom: 2px solid #e8e3d9;
                margin: 40px 0;
            }
            .detail-row {
                display: table;
                width: 100%;
                padding: 12px 0;
                border-bottom: 1px solid #f4f1eb;
            }
            .detail-row:last-child {
                border-bottom: none;
            }
            .detail-label {
                display: table-cell;
                width: 40%;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                color: #8b8680;
            }
            .detail-value {
                display: table-cell;
                text-align: right;
                font-size: 14px;
                color: #1a1a1a;
            }
            .code-value {
                font-size: 14px;
                font-weight: 400;
                font-family: 'Courier New', monospace;
                letter-spacing: 2px;
            }
            .blank-line {
                border-bottom: 1px solid #8b8680;
                min-width: 250px;
                padding-bottom: 4px;
            }
            .message-label {
                background: #f9f6f1;
                padding: 25px;
                margin: 30px 0;
                border-left: 3px solid #1a1a1a;
            }
            .message-label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                color: #8b8680;
                margin-bottom: 10px;
            }
            .message-text {
                font-size: 13px;
                line-height: 1.6;
                color: #1a1a1a;
            }
            .footer {
                text-align: center;
                padding-top: 30px;
            }
            .footer-info {
                font-size: 11px;
                color: #8b8680;
                line-height: 1.8;
            }
            .validity-box {
                background: #f9f6f1;
                padding: 15px;
                margin-top: 25px;
                text-align: center;
                font-size: 12px;
            }
            .validity-box strong {
                color: #1a1a1a;
            }
            .reservation-notice {
                border: 1px dashed #1a1a1a;
                padding: 15px;
                margin-top: 15px;
                text-align: center;
                font-size: 12px;
                background: #fff;
            }
        </style>
    </head>
    <body>
        <div class="voucher-container">
            <div class="header">
                <?php if ($logo_base64): ?>
                    <img src="<?php echo $logo_base64; ?>" alt="Mon Restaurant" class="logo">
                <?php else: ?>
                    <div class="restaurant-name"><?php echo esc_html(strtoupper(get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant'))); ?></div>
                <?php endif; ?>
                <div class="voucher-type">Bon d'achat</div>
            </div>

            <div class="amount-section">
                <div class="amount-label">Valeur</div>
                <div class="amount-value"><?php echo number_format((int)$voucher->amount_cents / 100, 0, ',', ' '); ?> €</div>
            </div>

            <div class="details">
                <div class="detail-row">
                    <div class="detail-label">Code bon cadeau</div>
                    <div class="detail-value code-value"><?php echo esc_html($voucher->code); ?></div>
                </div>

                <?php if (!empty($voucher->recipient_name)): ?>
                <div class="detail-row">
                    <div class="detail-label">Offert à</div>
                    <div class="detail-value"><?php echo esc_html($voucher->recipient_name); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($voucher->recipient_email)): ?>
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo esc_html($voucher->recipient_email); ?></div>
                </div>
                <?php endif; ?>

                <?php if (empty($voucher->recipient_name) && empty($voucher->recipient_email)): ?>
                <div class="detail-row blank-recipient">
                    <div class="detail-label">Offert à</div>
                    <div class="detail-value blank-line">__________________________________</div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Date d'émission</div>
                    <div class="detail-value"><?php echo $created->format('d/m/Y'); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Valable jusqu'au</div>
                    <div class="detail-value"><?php echo $validity->format('d/m/Y'); ?></div>
                </div>
            </div>

            <?php if (!empty($voucher->message)): ?>
            <div class="message-box">
                <div class="message-label">Message personnel</div>
                <div class="message-text"><?php echo nl2br(esc_html($voucher->message)); ?></div>
            </div>
            <?php endif; ?>

            <div class="footer">
                <div class="footer-info">
                    <strong>Restaurant Mon Restaurant</strong><br>
                    <?php echo nl2br(esc_html(get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris'))); ?><br>
                    Tél : <?php echo esc_html(get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00')); ?><br>
                    www.mon-restaurant.fr
                </div>

                <div class="validity-box">
                    <strong>Présentez ce bon lors de votre venue</strong><br>
                    Le montant sera déduit de votre addition
                </div>

                <div class="reservation-notice">
                    <strong>Réservation obligatoire</strong>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    return $html;
}

/**
 * Envoyer le PDF du voucher par email
 * @param int $voucher_id ID du voucher
 * @param string $to Email du destinataire
 * @return bool Succès de l'envoi
 */
function gastro_starter_email_voucher_pdf($voucher_id, $to) {
    $voucher = gastro_starter_get_voucher($voucher_id);
    if (!$voucher) {
        return false;
    }
    
    // Générer le HTML du PDF
    $pdf_html = gastro_starter_generate_voucher_pdf($voucher);
    
    // Pour l'instant, on envoie juste le lien de téléchargement
    // Dans une version avancée, on pourrait utiliser une bibliothèque comme TCPDF ou Dompdf
    
    $download_url = home_url('/telecharger-bon-achat?code=' . urlencode($voucher->code));
    
    $email_manager = gastro_starter_get_email_manager();
    $subject = '🎁 Votre bon cadeau Mon Restaurant - ' . $voucher->code;
    
    $content = '<h2>🎁 Votre bon cadeau est prêt</h2>'
        . '<p>Bonjour' . (!empty($voucher->purchaser_name) ? ' ' . esc_html($voucher->purchaser_name) : '') . ',</p>'
        . '<p>Merci pour votre achat. Votre bon cadeau d\'une valeur de <strong>' . number_format((int)$voucher->amount_cents / 100, 2, ',', ' ') . ' €</strong> est maintenant disponible.</p>'
        . '<p><strong>Code bon cadeau :</strong> ' . esc_html($voucher->code) . '</p>'
        . '<p style="margin: 30px 0; text-align: center;"><a href="' . esc_url($download_url) . '" style="display: inline-block; padding: 14px 28px; background: #1a1a1a; color: #fff; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; border-radius: 4px;">📥 Télécharger le PDF</a></p>'
        . '<p style="text-align: center; font-size: 12px; color: #8b8680; margin-top: 8px;">💡 Cliquez sur "Télécharger le PDF" puis "Imprimer" et choisissez "Enregistrer au format PDF"</p>'
        . '<p>Vous pouvez également consulter et imprimer votre bon d\'achat en <a href="' . esc_url(home_url('/merci-voucher?voucher=' . urlencode($voucher->code) . '&payment=success')) . '">cliquant ici</a>.</p>'
        . '<p>Présentez ce code lors de votre venue au restaurant. Le montant sera déduit de votre addition.</p>';
    
    return $email_manager->send_email($to, $subject, $content);
}

