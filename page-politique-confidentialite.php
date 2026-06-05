<?php
/**
 * Template Name: Politique de Confidentialité
 * Template pour la page "Politique de Confidentialité"
 *
 * @package Gastro_Starter
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><?php esc_html_e('Politique de Confidentialité', 'gastro-starter'); ?></h1>
        </div>
    </div>

    <div class="page-content">
        <div class="container">
            <div class="privacy-policy-content">
                <h2><?php esc_html_e('Protection de vos données personnelles', 'gastro-starter'); ?></h2>
                <p><?php esc_html_e('Le restaurant Mon Restaurant s\'engage à protéger la confidentialité de vos données personnelles conformément au Règlement Général sur la Protection des Données (RGPD).', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('1. Données collectées', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous collectons les informations suivantes lorsque vous effectuez une réservation :', 'gastro-starter'); ?></p>
                <ul>
                    <li><?php esc_html_e('Nom et prénom', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Adresse email', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Numéro de téléphone', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Détails de la réservation (date, heure, nombre de personnes, demandes spéciales)', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Historique des visites', 'gastro-starter'); ?></li>
                </ul>

                <h3><?php esc_html_e('2. Utilisation des données', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous utilisons vos données pour :', 'gastro-starter'); ?></p>
                <ul>
                    <li><?php esc_html_e('Gérer votre réservation', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Vous envoyer des confirmations et rappels', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Identifier les clients fidèles et proposer un programme VIP (après 5 visites)', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Personnaliser votre expérience', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Vous contacter en cas de modification de votre réservation', 'gastro-starter'); ?></li>
                </ul>
                <p><?php esc_html_e('Si vous avez consenti à recevoir notre newsletter, nous utiliserons votre email pour vous envoyer des actualités et offres spéciales du restaurant.', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('3. Conservation des données', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous conservons vos données pendant une durée de 3 ans à compter de votre dernière réservation, afin de maintenir votre historique de visite et votre statut de fidélité.', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('4. Vos droits', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Conformément au RGPD, vous disposez des droits suivants concernant vos données personnelles :', 'gastro-starter'); ?></p>
                <ul>
                    <li><?php esc_html_e('Droit d\'accès à vos données', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Droit de rectification', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Droit à l\'effacement (« droit à l\'oubli »)', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Droit à la limitation du traitement', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Droit d\'opposition', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Droit à la portabilité des données', 'gastro-starter'); ?></li>
                </ul>

                <h3><?php esc_html_e('5. Exercer vos droits', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Pour exercer vos droits ou pour toute question relative à la protection de vos données, vous pouvez :', 'gastro-starter'); ?></p>
                <ul>
                    <li><?php esc_html_e('Utiliser notre formulaire de contact', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Nous envoyer un email à contact@mon-restaurant.fr', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Nous contacter par téléphone au +33 5 53 63 80 80', 'gastro-starter'); ?></li>
                    <li><?php esc_html_e('Nous écrire à l\'adresse : Restaurant Mon Restaurant, 6 avenue du 6 juin 1944, Notre Ville 24500', 'gastro-starter'); ?></li>
                </ul>
                <p><?php esc_html_e('Nous nous efforcerons de répondre à votre demande dans un délai d\'un mois.', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('6. Sécurité des données', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données personnelles contre la perte, l\'accès non autorisé, la divulgation, l\'altération et la destruction.', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('7. Partage des données', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous ne partageons pas vos données personnelles avec des tiers, sauf lorsque cela est nécessaire pour l\'exécution de nos services ou lorsque nous sommes légalement tenus de le faire.', 'gastro-starter'); ?></p>

                <h3><?php esc_html_e('8. Modifications de notre politique de confidentialité', 'gastro-starter'); ?></h3>
                <p><?php esc_html_e('Nous pouvons mettre à jour cette politique de confidentialité de temps à autre. Toute modification sera publiée sur cette page avec une date de révision mise à jour.', 'gastro-starter'); ?></p>
                <p><?php esc_html_e('Dernière mise à jour : ', 'gastro-starter'); echo date_i18n('d/m/Y'); ?></p>
            </div>
        </div>
    </div>
</main>

<?php
get_footer(); 