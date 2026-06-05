<?php
/**
 * Template Name: Télécharger Bon Achat PDF
 * Description: Page d'impression du bon d'achat (convertible en PDF)
 */

// Récupérer le code du voucher
$voucher_code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';

if (empty($voucher_code)) {
    wp_die('Code de bon d\'achat manquant');
}

// Récupérer le voucher depuis la base de données
global $wpdb;
$table_name = $wpdb->prefix . 'gastro_starter_vouchers';
$voucher = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE code = %s",
    $voucher_code
));

if (!$voucher) {
    wp_die('Bon d\'achat introuvable');
}

// Calculer la date de validité (1 an après création)
$created = new DateTime($voucher->created_at);
$validity = clone $created;
$validity->modify('+1 year');

// Logo du restaurant
$custom_logo_id = get_theme_mod('custom_logo');
$logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon d'achat Mon Restaurant - <?php echo esc_html($voucher->code); ?></title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #fff;
            padding: 30px 40px;
        }
        
        .voucher-pdf {
            max-width: 700px;
            margin: 0 auto;
            border: 3px solid #1a1a1a;
            padding: 40px 45px;
            background: #ffffff;
            position: relative;
        }
        
        .voucher-pdf::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 1px solid #e8e3d9;
            pointer-events: none;
        }
        
        .pdf-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 2px solid #e8e3d9;
            margin-bottom: 35px;
            position: relative;
            z-index: 1;
        }
        
        .pdf-logo {
            max-width: 160px;
            height: auto;
            margin-bottom: 12px;
        }
        
        .restaurant-name {
            font-size: 28px;
            font-weight: 400;
            letter-spacing: 2px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .voucher-type-label {
            font-size: 12px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #8b8680;
        }
        
        .amount-section {
            text-align: center;
            margin: 40px 0;
            position: relative;
            z-index: 1;
        }
        
        .amount-label {
            font-size: 14px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #8b8680;
            margin-bottom: 15px;
        }
        
        .amount-value {
            font-size: 72px;
            font-weight: 300;
            color: #1a1a1a;
            letter-spacing: -3px;
            line-height: 1;
        }
        
        .details-section {
            padding: 30px 0;
            border-top: 2px solid #e8e3d9;
            border-bottom: 2px solid #e8e3d9;
            margin: 35px 0;
            position: relative;
            z-index: 1;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f4f1eb;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #8b8680;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: 400;
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
            padding: 20px;
            margin: 30px 0;
            border-left: 3px solid #1a1a1a;
            position: relative;
            z-index: 1;
        }
        
        .message-label {
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #8b8680;
            margin-bottom: 10px;
        }
        
        .message-text {
            font-size: 13px;
            font-weight: 300;
            line-height: 1.6;
            color: #1a1a1a;
        }
        
        .footer-section {
            text-align: center;
            padding-top: 30px;
            position: relative;
            z-index: 1;
        }
        
        .footer-info {
            font-size: 12px;
            font-weight: 300;
            color: #8b8680;
            line-height: 1.6;
        }
        
        .validity-highlight {
            background: #f9f6f1;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
        }
        
        .validity-highlight strong {
            font-weight: 400;
            color: #1a1a1a;
        }
        
        .reservation-notice {
            border: 1px dashed #1a1a1a;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
            font-size: 13px;
            background: #fff;
        }
        
        .reservation-notice strong {
            font-weight: 500;
            color: #1a1a1a;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .voucher-pdf {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="voucher-pdf">
        <div class="pdf-header">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Mon Restaurant" class="pdf-logo">
            <?php else: ?>
                <div class="restaurant-name"><?php echo esc_html(strtoupper(get_theme_mod('gastro_starter_restaurant_name', 'Mon Restaurant'))); ?></div>
            <?php endif; ?>
            <div class="voucher-type-label">Bon d'achat</div>
        </div>

        <div class="amount-section">
            <div class="amount-label">Valeur</div>
            <div class="amount-value"><?php echo number_format((int)$voucher->amount_cents / 100, 0, ',', ' '); ?> €</div>
        </div>

        <div class="details-section">
            <div class="detail-row">
                <span class="detail-label">Code bon cadeau</span>
                <span class="detail-value code-value"><?php echo esc_html($voucher->code); ?></span>
            </div>

            <?php if (!empty($voucher->recipient_name)): ?>
            <div class="detail-row">
                <span class="detail-label">Offert à</span>
                <span class="detail-value"><?php echo esc_html($voucher->recipient_name); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($voucher->recipient_email)): ?>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?php echo esc_html($voucher->recipient_email); ?></span>
            </div>
            <?php endif; ?>

            <?php if (empty($voucher->recipient_name) && empty($voucher->recipient_email)): ?>
            <div class="detail-row blank-recipient">
                <span class="detail-label">Offert à</span>
                <span class="detail-value blank-line">__________________________________</span>
            </div>
            <?php endif; ?>

            <div class="detail-row">
                <span class="detail-label">Date d'émission</span>
                <span class="detail-value"><?php echo $created->format('d/m/Y'); ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Valable jusqu'au</span>
                <span class="detail-value"><?php echo $validity->format('d/m/Y'); ?></span>
            </div>
        </div>

        <?php if (!empty($voucher->message)): ?>
        <div class="message-section">
            <div class="message-label">Message personnel</div>
            <div class="message-text"><?php echo nl2br(esc_html($voucher->message)); ?></div>
        </div>
        <?php endif; ?>

        <div class="footer-section">
            <div class="footer-info">
                <strong>Restaurant Mon Restaurant</strong><br>
                <?php echo nl2br(esc_html(get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris'))); ?><br>
                Tél : <?php echo esc_html(get_theme_mod('gastro_starter_restaurant_phone', '05 53 00 00 00')); ?><br>
                www.mon-restaurant.fr
            </div>

            <div class="validity-highlight">
                <strong>Présentez ce bon lors de votre venue</strong><br>
                Le montant sera déduit de votre addition
            </div>
            
            <div class="reservation-notice">
                <strong>Réservation obligatoire</strong>
            </div>
        </div>
    </div>

    <script>
        // Boutons de contrôle (masqués à l'impression)
        window.onload = function() {
            // Ajouter les boutons de contrôle
            var controls = document.createElement('div');
            controls.id = 'print-controls';
            controls.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1000; background: white; padding: 20px; border: 2px solid #1a1a1a; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            controls.innerHTML = '<button onclick="window.print()" style="padding: 12px 24px; background: #1a1a1a; color: white; border: none; cursor: pointer; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-right: 8px;">Imprimer / Enregistrer en PDF</button>' +
                '<button onclick="window.close()" style="padding: 12px 24px; background: white; color: #1a1a1a; border: 1px solid #1a1a1a; cursor: pointer; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Fermer</button>';
            document.body.insertBefore(controls, document.body.firstChild);
            
            // Instructions pour le PDF
            var instructions = document.createElement('div');
            instructions.id = 'pdf-instructions';
            instructions.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #f9f6f1; padding: 16px 24px; border: 1px solid #e8e3d9; max-width: 600px; text-align: center; font-size: 13px; color: #1a1a1a;';
            instructions.innerHTML = '<strong>💡 Pour enregistrer en PDF :</strong> Cliquez sur "Imprimer", puis sélectionnez "Enregistrer au format PDF" comme imprimante de destination.';
            document.body.appendChild(instructions);
        };
    </script>
    
    <style>
        @media print {
            #print-controls,
            #pdf-instructions {
                display: none !important;
            }
        }
    </style>
</body>
</html>
