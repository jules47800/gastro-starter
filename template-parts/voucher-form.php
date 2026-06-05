<?php
if (!defined('ABSPATH')) { exit; }

$error = isset($_GET['voucher_error']) ? sanitize_text_field($_GET['voucher_error']) : '';
if ($error) {
    echo '<div class="voucher-error">' . esc_html($error) . '</div>';
}

// Récupérer les montants disponibles depuis la configuration admin
$available_amounts = get_option('gastro_starter_voucher_amounts', array());

// Si aucun montant n'est configuré, utiliser des montants par défaut
if (empty($available_amounts)) {
    $available_amounts = array(25, 50, 75, 100, 150);
}

// Vérifier que Stripe est configuré
$stripe_keys = gastro_starter_get_stripe_api_keys();
$stripe_configured = !empty($stripe_keys['secret_key']);
?>

<div class="voucher-card">
    <form id="gastro-starter-voucher-form" class="gastro-starter-voucher-form">
        <?php wp_nonce_field('gastro_starter_voucher_nonce', 'voucher_nonce'); ?>
        <input type="text" name="hp_field" value="" style="display:none" tabindex="-1" autocomplete="off">
        
        <div class="field">
            <label>Montant du bon d'achat</label>
            <div class="amount-input-row">
                <select name="amount" required class="voucher-amount-select">
                    <option value="">-- Choisissez un montant --</option>
                    <?php foreach ($available_amounts as $amt) : ?>
                        <option value="<?php echo intval($amt); ?>">
                            <?php echo number_format_i18n($amt, 0); ?> €
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <small class="help">Sélectionnez le montant de votre bon d'achat.</small>
        </div>
        
        <div class="grid">
            <div class="field">
                <label>Votre nom</label>
                <input type="text" name="purchaser_name" placeholder="Votre nom">
            </div>
            <div class="field">
                <label>Votre email</label>
                <input type="email" name="purchaser_email" placeholder="vous@example.com" required>
            </div>
        </div>
        
        <div class="grid">
            <div class="field">
                <label>Nom du bénéficiaire</label>
                <input type="text" name="recipient_name" placeholder="Nom (optionnel)">
            </div>
            <div class="field">
                <label>Email du bénéficiaire <span class="optional-badge">Optionnel</span></label>
                <input type="email" name="recipient_email" placeholder="beneficiaire@example.com">
            </div>
        </div>
        <small class="help recipient-help">💡 Si vous renseignez l'email, le bénéficiaire recevra une copie du bon cadeau par email.</small>
        
        <div class="field">
            <label>Message</label>
            <textarea name="message" rows="4" placeholder="Un petit mot qui apparaîtra sur le bon"></textarea>
        </div>
        
        <div class="voucher-summary">
            <span>Total</span>
            <strong class="total-amount">—</strong>
        </div>

        <div class="actions">
            <?php if ($stripe_configured) : ?>
                <button type="submit" class="button primary" id="voucher-submit-btn">
                    💳 Procéder au paiement
                </button>
                <p class="voucher-hint">Paiement sécurisé via Stripe</p>
            <?php else : ?>
                <div class="voucher-warning">
                    ⚠️ Le paiement n'est pas configuré. Veuillez configurer vos clés Stripe dans l'administration.
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$stripe_configured && current_user_can('manage_options')) : ?>
        <div class="voucher-error">
            <strong>Configuration requise (admin seulement) :</strong>
            Allez dans <a href="<?php echo admin_url('admin.php?page=gastro-starter-stripe-settings'); ?>">Bons d'achat → Configuration Stripe</a> pour configurer vos clés API.
        </div>
    <?php endif; ?>
</div>

<?php if ($stripe_configured) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('gastro-starter-voucher-form');
    var submitBtn = document.getElementById('voucher-submit-btn');
    
    if (!form || !submitBtn) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validation
        var amount = form.querySelector('[name="amount"]').value;
        var email = form.querySelector('[name="purchaser_email"]').value;
        
        if (!amount || !email) {
            alert('Veuillez remplir les champs obligatoires');
            return;
        }
        
        // Désactiver le bouton
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Création en cours...';
        
        // Préparer les données
        var formData = new FormData(form);
        
        // Créer le voucher + session Stripe en une seule requête
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: new URLSearchParams({
                'action': 'gastro_starter_create_voucher_and_checkout',
                'nonce': formData.get('voucher_nonce'),
                'amount': formData.get('amount'),
                'purchaser_name': formData.get('purchaser_name') || '',
                'purchaser_email': formData.get('purchaser_email'),
                'recipient_name': formData.get('recipient_name') || '',
                'recipient_email': formData.get('recipient_email') || '',
                'message': formData.get('message') || '',
                'hp_field': formData.get('hp_field') || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.url) {
                console.log('✅ Redirection vers Stripe Checkout');
                submitBtn.innerHTML = '✅ Redirection vers le paiement...';
                window.location.href = data.data.url;
            } else {
                alert('Erreur : ' + (data.data.message || 'Impossible de créer le paiement'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '💳 Procéder au paiement';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion. Veuillez réessayer.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '💳 Procéder au paiement';
        });
    });
});
</script>
<?php endif; ?>


