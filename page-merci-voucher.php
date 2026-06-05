<?php
/**
 * Template Name: Merci Voucher
 * Description: Page de confirmation après achat d'un bon d'achat
 */

get_header();

// Récupérer les paramètres de l'URL
$voucher_code = isset($_GET['voucher']) ? sanitize_text_field($_GET['voucher']) : '';
$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
$payment_status = isset($_GET['payment']) ? sanitize_text_field($_GET['payment']) : '';

// Récupérer le voucher
$voucher = null;
if (!empty($voucher_code)) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gastro_starter_vouchers';
    $voucher = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE code = %s",
        $voucher_code
    ));
}
?>

<div class="merci-voucher-page">
    <div class="container">
        
        <?php if ($payment_status === 'success' && $voucher): ?>
            
            <!-- En-tête de confirmation -->
            <div class="confirmation-header">
                <div class="success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h1>Paiement confirmé !</h1>
                <p class="subtitle">Votre bon d'achat est prêt</p>
            </div>

            <!-- Carte du bon d'achat -->
            <div class="voucher-card-preview">
                <div class="voucher-card-inner">
                    <div class="voucher-card-header">
                        <div class="logo-area">
                            <?php 
                            $custom_logo_id = get_theme_mod('custom_logo');
                            if ($custom_logo_id) {
                                echo wp_get_attachment_image($custom_logo_id, 'full', false, array('class' => 'voucher-logo'));
                            } else {
                                echo '<span class="restaurant-name">Mon Restaurant</span>';
                            }
                            ?>
                        </div>
                        <div class="voucher-type">Bon d'achat</div>
                    </div>

                    <div class="voucher-amount-display">
                        <?php echo number_format((int)$voucher->amount_cents / 100, 0, ',', ' '); ?> €
                    </div>

                    <div class="voucher-details">
                        <div class="voucher-code-section">
                            <span class="code-label">Code bon cadeau</span>
                            <span class="code-value"><?php echo esc_html($voucher->code); ?></span>
                        </div>

                        <?php if (!empty($voucher->recipient_name)): ?>
                            <div class="voucher-recipient">
                                <span class="recipient-label">Offert à</span>
                                <span class="recipient-name"><?php echo esc_html($voucher->recipient_name); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($voucher->recipient_email)): ?>
                            <div class="voucher-recipient">
                                <span class="recipient-label">Email</span>
                                <span class="recipient-name"><?php echo esc_html($voucher->recipient_email); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($voucher->message)): ?>
                            <div class="voucher-message">
                                <span class="message-label">Message</span>
                                <p class="message-text"><?php echo nl2br(esc_html($voucher->message)); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="voucher-validity">
                            <span class="validity-label">Valable jusqu'au</span>
                            <span class="validity-date">
                                <?php 
                                $created = new DateTime($voucher->created_at);
                                $validity = clone $created;
                                $validity->modify('+1 year');
                                echo $validity->format('d/m/Y');
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="voucher-reservation-notice">
                        <strong>Réservation obligatoire</strong>
                    </div>

                    <div class="voucher-footer">
                        <span>Restaurant Mon Restaurant</span>
                        <span><?php echo esc_html(get_theme_mod('gastro_starter_restaurant_address', '1 rue de la Gastronomie, 75001 Paris')); ?></span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="voucher-actions">
                <a href="<?php echo home_url('/telecharger-bon-achat?code=' . urlencode($voucher->code)); ?>" class="button primary download-btn" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimer / Sauvegarder en PDF
                </a>
                <button onclick="window.print()" class="button secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimer cette page
                </button>
            </div>

            <!-- Informations email -->
            <div class="email-confirmation-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <div>
                    <p><strong>Un email de confirmation vous a été envoyé</strong></p>
                    <p>Vérifiez votre boîte mail (<?php echo esc_html($voucher->purchaser_email); ?>)</p>
                    <p class="help-text">Pensez à vérifier vos courriers indésirables</p>
                </div>
            </div>

            <!-- Comment utiliser -->
            <div class="voucher-usage-instructions">
                <h2>Comment utiliser ce bon d'achat ?</h2>
                <div class="instructions-grid">
                    <div class="instruction-item">
                        <div class="instruction-number">1</div>
                        <h3>Réservez votre table</h3>
                        <p>La réservation est obligatoire. Contactez-nous ou réservez en ligne pour planifier votre visite.</p>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-number">2</div>
                        <h3>Présentez votre code</h3>
                        <p>Montrez le bon d'achat (PDF ou imprimé) lors de votre venue</p>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-number">3</div>
                        <h3>Profitez</h3>
                        <p>Le montant sera déduit de votre addition</p>
                    </div>
                </div>
            </div>

            <!-- Liens utiles -->
            <div class="useful-links">
                <a href="<?php echo home_url('/reserver'); ?>" class="button secondary">Réserver une table</a>
                <a href="<?php echo home_url('/'); ?>" class="button text-link">Retour à l'accueil</a>
            </div>

        <?php elseif ($payment_status === 'cancelled'): ?>
            
            <!-- Paiement annulé -->
            <div class="confirmation-header cancelled">
                <div class="error-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                </div>
                <h1>Paiement annulé</h1>
                <p class="subtitle">Votre commande n'a pas été finalisée</p>
            </div>

            <div class="cancelled-info">
                <p>Vous avez annulé le processus de paiement. Aucun montant n'a été débité.</p>
                <p>Vous pouvez recommencer si vous le souhaitez.</p>
            </div>

            <div class="useful-links">
                <a href="<?php echo home_url('/bon-achat'); ?>" class="button primary">Recommencer</a>
                <a href="<?php echo home_url('/'); ?>" class="button text-link">Retour à l'accueil</a>
            </div>

        <?php else: ?>
            
            <!-- Erreur -->
            <div class="confirmation-header error">
                <h1>Erreur</h1>
                <p>Impossible de retrouver votre bon d'achat</p>
            </div>

            <div class="useful-links">
                <a href="<?php echo home_url('/bon-achat'); ?>" class="button primary">Acheter un bon d'achat</a>
                <a href="<?php echo home_url('/'); ?>" class="button text-link">Retour à l'accueil</a>
            </div>

        <?php endif; ?>

    </div>
</div>

<style>
/* Page de confirmation voucher - Style Mon Restaurant */
.merci-voucher-page {
    padding: 60px 24px;
    background: var(--color-cream);
}

.merci-voucher-page .container {
    max-width: 800px;
    margin: 0 auto;
    display: grid;
    gap: 40px;
}

/* En-tête confirmation */
.confirmation-header {
    text-align: center;
    padding: 40px 0;
}

.confirmation-header h1 {
    font-size: 36px;
    font-weight: 300;
    margin: 20px 0 8px;
    color: var(--color-primary);
}

.confirmation-header .subtitle {
    font-size: 16px;
    font-weight: 300;
    color: var(--color-warm-gray);
    margin: 0;
}

.success-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--color-white);
    border: 2px solid var(--color-beige-dark);
    color: #27ae60;
}

.error-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--color-white);
    border: 2px solid var(--color-beige-dark);
    color: #dc2626;
}

/* Carte du voucher */
.voucher-card-preview {
    background: var(--color-white);
    border: 1px solid var(--color-beige-dark);
    padding: 40px;
    position: relative;
}

.voucher-card-preview::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,.01) 2px, rgba(0,0,0,.01) 4px);
    pointer-events: none;
}

.voucher-card-inner {
    position: relative;
    z-index: 1;
}

.voucher-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--color-beige-dark);
    margin-bottom: 32px;
}

.voucher-logo {
    max-height: 40px;
    width: auto;
}

.restaurant-name {
    font-size: 20px;
    font-weight: 400;
    color: var(--color-primary);
}

.voucher-type {
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--color-warm-gray);
}

.voucher-amount-display {
    font-size: 72px;
    font-weight: 300;
    text-align: center;
    color: var(--color-primary);
    margin: 40px 0;
    letter-spacing: -2px;
}

.voucher-details {
    display: grid;
    gap: 24px;
    padding: 32px 0;
    border-top: 1px solid var(--color-beige-dark);
}

.voucher-code-section,
.voucher-recipient,
.voucher-validity {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.code-label,
.recipient-label,
.validity-label,
.message-label {
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-warm-gray);
}

.code-value {
    font-size: 14px;
    font-weight: 400;
    letter-spacing: 1.5px;
    font-family: 'Courier New', monospace;
    color: var(--color-primary);
}

.recipient-name,
.validity-date {
    font-size: 15px;
    font-weight: 400;
    color: var(--color-primary);
}

.voucher-message {
    display: grid;
    gap: 8px;
    padding: 16px;
    background: var(--color-cream);
    border-left: 2px solid var(--color-beige-dark);
}

.message-text {
    font-size: 14px;
    font-weight: 300;
    line-height: 1.6;
    color: var(--color-primary);
    margin: 0;
}

.voucher-footer {
    display: flex;
    justify-content: space-between;
    padding-top: 24px;
    border-top: 1px solid var(--color-beige-dark);
    margin-top: 32px;
    font-size: 12px;
    font-weight: 300;
    color: var(--color-warm-gray);
}

/* Notice de réservation */
.voucher-reservation-notice {
    text-align: center;
    padding: 16px;
    margin-top: 32px;
    border-top: 1px solid var(--color-beige-dark);
    border-bottom: 1px solid var(--color-beige-dark);
}
.voucher-reservation-notice strong {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--color-primary);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.voucher-reservation-notice span {
    display: none;
}

/* Actions */
.voucher-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.voucher-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px 24px;
}

.voucher-actions .button.primary {
    background: var(--color-primary);
    color: var(--color-white);
    border: none;
}

.voucher-actions .button.secondary {
    background: var(--color-white);
    color: var(--color-primary);
    border: 1px solid var(--color-beige-dark);
}

.voucher-actions .button:hover {
    opacity: 0.85;
}

/* Info email */
.email-confirmation-info {
    display: flex;
    gap: 16px;
    padding: 20px 24px;
    background: var(--color-white);
    border: 1px solid var(--color-beige-dark);
    border-left: 3px solid var(--color-primary);
}

.email-confirmation-info svg {
    flex-shrink: 0;
    color: var(--color-primary);
    margin-top: 2px;
}

.email-confirmation-info p {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 300;
    color: var(--color-primary);
}

.email-confirmation-info p strong {
    font-weight: 400;
}

.email-confirmation-info .help-text {
    font-size: 12px;
    color: var(--color-warm-gray);
    font-style: italic;
}

/* Instructions */
.voucher-usage-instructions {
    background: var(--color-white);
    border: 1px solid var(--color-beige-dark);
    padding: 40px;
}

.voucher-usage-instructions h2 {
    font-size: 20px;
    font-weight: 400;
    margin: 0 0 32px;
    text-align: center;
    color: var(--color-primary);
}

.instructions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 32px;
}

.instruction-item {
    text-align: center;
}

.instruction-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-beige-dark);
    font-size: 16px;
    font-weight: 400;
    color: var(--color-primary);
    margin-bottom: 16px;
}

.instruction-item h3 {
    font-size: 15px;
    font-weight: 400;
    margin: 0 0 8px;
    color: var(--color-primary);
}

.instruction-item p {
    font-size: 13px;
    font-weight: 300;
    line-height: 1.6;
    color: var(--color-warm-gray);
    margin: 0;
}

/* Liens utiles */
.useful-links {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
}

.useful-links .button {
    min-width: 250px;
    text-align: center;
}

.useful-links .text-link {
    background: none;
    border: none;
    color: var(--color-warm-gray);
    text-decoration: underline;
    padding: 8px 16px;
}

/* Info annulation */
.cancelled-info {
    background: var(--color-white);
    border: 1px solid var(--color-beige-dark);
    padding: 32px;
    text-align: center;
}

.cancelled-info p {
    font-size: 15px;
    font-weight: 300;
    line-height: 1.8;
    color: var(--color-primary);
    margin: 0 0 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .merci-voucher-page {
        padding: 40px 16px;
    }

    .confirmation-header h1 {
        font-size: 28px;
    }

    .voucher-card-preview {
        padding: 24px;
    }

    .voucher-amount-display {
        font-size: 56px;
        margin: 24px 0;
    }

    .voucher-actions {
        grid-template-columns: 1fr;
    }

    .instructions-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .useful-links .button {
        width: 100%;
        min-width: auto;
    }
}

/* Print styles */
@media print {
    .voucher-actions,
    .email-confirmation-info,
    .voucher-usage-instructions,
    .useful-links,
    header,
    footer {
        display: none !important;
    }

    .merci-voucher-page {
        background: white;
        padding: 0;
    }

    .voucher-card-preview {
        border: 2px solid #000;
        page-break-inside: avoid;
    }
}
</style>

<?php get_footer(); ?>
