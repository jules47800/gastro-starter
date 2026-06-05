<?php
if (!current_user_can('manage_options')) return;

if (isset($_POST['gastro_starter_stripe_settings_nonce']) && wp_verify_nonce($_POST['gastro_starter_stripe_settings_nonce'], 'gastro_starter_stripe_settings')) {
    update_option('gastro_starter_stripe_test_mode', isset($_POST['test_mode']) ? 1 : 0);
    update_option('gastro_starter_stripe_test_public_key', sanitize_text_field($_POST['test_public_key']));
    update_option('gastro_starter_stripe_test_secret_key', sanitize_text_field($_POST['test_secret_key']));
    update_option('gastro_starter_stripe_test_webhook_secret', sanitize_text_field($_POST['test_webhook_secret']));
    update_option('gastro_starter_stripe_live_public_key', sanitize_text_field($_POST['live_public_key']));
    update_option('gastro_starter_stripe_live_secret_key', sanitize_text_field($_POST['live_secret_key']));
    update_option('gastro_starter_stripe_live_webhook_secret', sanitize_text_field($_POST['live_webhook_secret']));
    echo '<div class="notice notice-success"><p>Configuration Stripe enregistrée.</p></div>';
}

$test_mode = get_option('gastro_starter_stripe_test_mode', true);
$test_public = get_option('gastro_starter_stripe_test_public_key', '');
$test_secret = get_option('gastro_starter_stripe_test_secret_key', '');
$test_webhook = get_option('gastro_starter_stripe_test_webhook_secret', '');
$live_public = get_option('gastro_starter_stripe_live_public_key', '');
$live_secret = get_option('gastro_starter_stripe_live_secret_key', '');
$live_webhook = get_option('gastro_starter_stripe_live_webhook_secret', '');
?>

<div class="wrap">
    <h1>Configuration Stripe</h1>
    <p>Configurez vos clés API Stripe pour accepter les paiements par carte bancaire.</p>
    
    <form method="post">
        <?php wp_nonce_field('gastro_starter_stripe_settings', 'gastro_starter_stripe_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h2>Mode de fonctionnement</h2></th>
            </tr>
            <tr>
                <th><label for="test_mode">Mode Test</label></th>
                <td>
                    <input type="checkbox" id="test_mode" name="test_mode" value="1" <?php checked($test_mode, 1); ?> />
                    <label for="test_mode">Activer le mode test (recommandé pour les tests)</label>
                    <p class="description">En mode test, aucun vrai paiement ne sera effectué. Utilisez les clés de test de Stripe.</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>Clés de Test</h2></th>
            </tr>
            <tr>
                <th><label for="test_public_key">Clé publique Test</label></th>
                <td>
                    <input type="text" id="test_public_key" name="test_public_key" value="<?php echo esc_attr($test_public); ?>" class="regular-text" placeholder="pk_test_..." />
                    <p class="description">Commence par pk_test_</p>
                </td>
            </tr>
            <tr>
                <th><label for="test_secret_key">Clé secrète Test</label></th>
                <td>
                    <input type="password" id="test_secret_key" name="test_secret_key" value="<?php echo esc_attr($test_secret); ?>" class="regular-text" placeholder="sk_test_..." />
                    <p class="description">Commence par sk_test_ (ne partagez JAMAIS cette clé)</p>
                </td>
            </tr>
            <tr>
                <th><label for="test_webhook_secret">Clé secrète Webhook Test</label></th>
                <td>
                    <input type="password" id="test_webhook_secret" name="test_webhook_secret" value="<?php echo esc_attr($test_webhook); ?>" class="regular-text" placeholder="whsec_..." />
                    <p class="description">Commence par whsec_ (fournie par Stripe après création du webhook)</p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h2>Clés de Production (Live)</h2></th>
            </tr>
            <tr>
                <th><label for="live_public_key">Clé publique Live</label></th>
                <td>
                    <input type="text" id="live_public_key" name="live_public_key" value="<?php echo esc_attr($live_public); ?>" class="regular-text" placeholder="pk_live_..." />
                    <p class="description">Commence par pk_live_</p>
                </td>
            </tr>
            <tr>
                <th><label for="live_secret_key">Clé secrète Live</label></th>
                <td>
                    <input type="password" id="live_secret_key" name="live_secret_key" value="<?php echo esc_attr($live_secret); ?>" class="regular-text" placeholder="sk_live_..." />
                    <p class="description">Commence par sk_live_ (ne partagez JAMAIS cette clé)</p>
                </td>
            </tr>
            <tr>
                <th><label for="live_webhook_secret">Clé secrète Webhook Live</label></th>
                <td>
                    <input type="password" id="live_webhook_secret" name="live_webhook_secret" value="<?php echo esc_attr($live_webhook); ?>" class="regular-text" placeholder="whsec_..." />
                    <p class="description">Commence par whsec_ (fournie par Stripe après création du webhook)</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Enregistrer la configuration Stripe'); ?>
    </form>
    
    <!-- Instructions -->
    <div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
        <h3>📘 Comment obtenir vos clés Stripe</h3>
        <ol>
            <li>Connectez-vous à votre compte Stripe sur <a href="https://stripe.com" target="_blank">stripe.com</a></li>
            <li>Allez dans <strong>Développeurs → Clés API</strong></li>
            <li>Copiez vos <strong>Clés de test</strong> (pk_test_... et sk_test_...)</li>
            <li>Pour la production, basculez vers <strong>Mode réel</strong> et copiez les clés Live</li>
            <li>Collez les clés ci-dessus et enregistrez</li>
        </ol>
        <p><strong>⚠️ Important :</strong> Ne partagez jamais vos clés secrètes (sk_). Gardez-les confidentielles.</p>
        
        <h3>🔗 Configuration du Webhook Stripe</h3>
        <p>Pour que Stripe puisse confirmer automatiquement les paiements, vous devez configurer un webhook :</p>
        
        <ol style="margin-left: 20px;">
            <li>Allez dans votre <a href="https://dashboard.stripe.com/test/webhooks" target="_blank">tableau de bord Stripe → Développeurs → Webhooks</a></li>
            <li>Cliquez sur <strong>"Ajouter un point de terminaison"</strong></li>
            <li>Collez cette URL :
                <div style="background: #fff; padding: 12px; border-radius: 4px; margin: 8px 0; border: 1px solid #ddd;">
                    <code style="font-family: monospace; font-size: 13px; word-break: break-all;"><?php echo esc_html(rest_url('gastro-starter/v1/stripe-webhook')); ?></code>
                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js(rest_url('gastro-starter/v1/stripe-webhook')); ?>')" style="margin-left: 10px; padding: 4px 8px; cursor: pointer;">📋 Copier</button>
                </div>
            </li>
            <li>Dans "Événements à envoyer", sélectionnez : <code>checkout.session.completed</code></li>
            <li>Cliquez sur <strong>"Ajouter un point de terminaison"</strong></li>
            <li><strong>Important :</strong> Copiez la <strong>clé de signature du webhook</strong> (whsec_...) qui s'affiche</li>
            <li>Collez cette clé dans le champ <strong>"Clé secrète Webhook Test"</strong> ci-dessus</li>
            <li>Enregistrez la configuration</li>
        </ol>
        
        <div style="background: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; margin: 12px 0; border-radius: 4px;">
            <strong>🔒 Sécurité :</strong> La clé webhook (whsec_...) permet de vérifier que les webhooks viennent réellement de Stripe. 
            Sans cette clé, votre site acceptera les webhooks mais ne vérifiera pas leur authenticité. 
            <strong>Configurez-la obligatoirement en production !</strong>
        </div>
        
        <p style="margin-top: 16px;"><strong>Note :</strong> Vous devrez créer un deuxième webhook avec l'URL ci-dessus quand vous passerez en mode Live, et copier sa clé dans "Clé secrète Webhook Live".</p>
        
        <h3>🧪 Tester les paiements</h3>
        <p>Carte de test : <code>4242 4242 4242 4242</code></p>
        <p>Date d'expiration : n'importe quelle date future (ex: 12/30)</p>
        <p>CVC : n'importe quel code à 3 chiffres (ex: 123)</p>
    </div>
</div>
