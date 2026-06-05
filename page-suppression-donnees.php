<?php
/**
 * Template Name: Suppression des Données
 * Template pour la page "Suppression des Données"
 *
 * @package Gastro_Starter
 */

get_header();

// Traitement du formulaire
$form_submitted = false;
$success = false;
$error = false;
$error_message = '';

if (isset($_POST['delete_data_submitted']) && isset($_POST['delete_data_nonce'])) {
    if (wp_verify_nonce($_POST['delete_data_nonce'], 'delete_user_data')) {
        $form_submitted = true;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Rate limiting
        $ip = Gastro_Starter_Security::get_client_ip();
        $rate_limiter = Gastro_Starter_Rate_Limiter::get_instance();
        
        if (!$rate_limiter->check_rate_limit($ip)) {
            $error = true;
            $error_message = __('Trop de tentatives. Veuillez réessayer dans une heure.', 'gastro-starter');
            Gastro_Starter_Error_Messages::log_error('rate_limit', "IP: $ip");
        } elseif (empty($email) || !Gastro_Starter_Security::validate_email($email)) {
            $error = true;
            $error_message = __('Veuillez fournir une adresse email valide.', 'gastro-starter');
            Gastro_Starter_Error_Messages::log_error('invalid_email', "Email: $email");
        } else {
            // Rechercher le client dans la base de données
            global $wpdb;
            $customers_table = $wpdb->prefix . 'customer_stats';
            $reservations_table = $wpdb->prefix . 'reservations';
            
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $customers_table WHERE email = %s",
                $email
            ));
            
            if (!$customer) {
                $error = true;
                $error_message = __('Aucun client trouvé avec cette adresse email.', 'gastro-starter');
                Gastro_Starter_Error_Messages::log_error('customer_not_found', "Email: $email");
            } else {
                // Anonymiser les réservations
                $wpdb->update(
                    $reservations_table,
                    array(
                        'customer_name' => 'Anonyme',
                        'customer_email' => null,
                        'customer_phone' => null,
                        'notes' => null
                    ),
                    array('customer_email' => $email),
                    array('%s', '%s', '%s', '%s'),
                    array('%s')
                );
                
                // Supprimer les statistiques client
                $wpdb->delete($customers_table, array('email' => $email), array('%s'));
                
                $success = true;
                Gastro_Starter_Error_Messages::log_error('data_deleted', "Email: $email - Données supprimées avec succès");
            }
        }
    } else {
        $error = true;
        $error_message = __('Erreur de sécurité. Veuillez réessayer.', 'gastro-starter');
        Gastro_Starter_Error_Messages::log_error('security_error', "Nonce invalide");
    }
}
?>

<main id="main" class="site-main">
    <div class="container">
        <div class="content-area">
            <div class="page-content">
                <?php if ($success): ?>
                    <div class="success-message">
                        <h2><?php esc_html_e('Données supprimées avec succès', 'gastro-starter'); ?></h2>
                        <p><?php esc_html_e('Vos données personnelles ont été supprimées de notre base de données.', 'gastro-starter'); ?></p>
                        <p><?php esc_html_e('Vos anciennes réservations ont été anonymisées.', 'gastro-starter'); ?></p>
                        <p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Retour à l\'accueil', 'gastro-starter'); ?></a></p>
                    </div>
                <?php elseif ($error): ?>
                    <div class="error-message">
                        <?php echo Gastro_Starter_Error_Messages::display_error($error_message); ?>
                    </div>
                <?php else: ?>
                    <div class="deletion-info">
                        <h2><?php esc_html_e('Exercez votre droit à l\'oubli', 'gastro-starter'); ?></h2>
                        <p><?php esc_html_e('Conformément au Règlement Général sur la Protection des Données (RGPD), vous pouvez demander la suppression de vos données personnelles de notre base de données.', 'gastro-starter'); ?></p>
                        <p><?php esc_html_e('Veuillez remplir le formulaire ci-dessous pour initier cette demande. Une fois votre demande traitée, vos informations personnelles et votre historique de visites seront définitivement supprimés.', 'gastro-starter'); ?></p>
                        
                        <div class="important-notice">
                            <h3><?php esc_html_e('Important', 'gastro-starter'); ?></h3>
                            <p><?php esc_html_e('Cette action est irréversible. Après suppression, nous ne pourrons plus récupérer votre historique de fidélité ni vos préférences.', 'gastro-starter'); ?></p>
                            <p><?php esc_html_e('Vos anciennes réservations seront anonymisées pour des raisons statistiques et de gestion.', 'gastro-starter'); ?></p>
                        </div>
                    </div>

                    <form method="post" class="deletion-form">
                        <?php wp_nonce_field('delete_user_data', 'delete_data_nonce'); ?>
                        <input type="hidden" name="delete_data_submitted" value="1">

                        <div class="form-group">
                            <label for="email"><?php esc_html_e('Votre email', 'gastro-starter'); ?></label>
                            <input type="email" id="email" name="email" required placeholder="<?php esc_attr_e('Entrez l\'email utilisé pour vos réservations', 'gastro-starter'); ?>">
                            <p class="field-help"><?php esc_html_e('Veuillez utiliser l\'adresse email avec laquelle vous avez effectué vos réservations.', 'gastro-starter'); ?></p>
                        </div>

                        <div class="form-group form-group-checkbox">
                            <input type="checkbox" id="confirm_deletion" required>
                            <label for="confirm_deletion"><?php esc_html_e('Je confirme vouloir supprimer définitivement mes données personnelles de la base de données du restaurant Mon Restaurant.', 'gastro-starter'); ?></label>
                        </div>

                        <div class="form-submit">
                            <button type="submit" class="deletion-submit"><?php esc_html_e('Supprimer mes données', 'gastro-starter'); ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?> 