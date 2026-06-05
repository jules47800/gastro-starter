<?php
/**
 * Template pour l'annulation de réservation
 */

get_header();

// Récupérer l'ID de réservation et le nonce depuis l'URL
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

// Traiter l'annulation si le formulaire est soumis
if (isset($_POST['confirm_cancel']) && check_admin_referer('cancel_reservation_' . $reservation_id)) {
    $result = gastro_starter_cancel_reservation($reservation_id, $nonce);
    
    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
    } else {
        $success = true;
    }
}

// Récupérer les informations de la réservation
global $wpdb;
$table_name = $wpdb->prefix . 'reservations';
$reservation = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE id = %d",
    $reservation_id
));

?>

<div class="container">
    <div class="cancel-reservation-page">
        <?php if (!isset($success)) : ?>
            <?php if (isset($error_message)) : ?>
                <div class="error-message">
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($reservation) : ?>
                <h1><?php _e('Annuler votre réservation', 'gastro-starter'); ?></h1>
                
                <div class="reservation-details">
                    <p><?php _e('Vous êtes sur le point d\'annuler la réservation suivante :', 'gastro-starter'); ?></p>
                    
                    <ul>
                        <li><strong><?php _e('Date :', 'gastro-starter'); ?></strong> <?php echo date_i18n('d/m/Y', strtotime($reservation->reservation_date)); ?></li>
                        <li><strong><?php _e('Heure :', 'gastro-starter'); ?></strong> <?php echo esc_html($reservation->reservation_time); ?></li>
                        <li><strong><?php _e('Nombre de personnes :', 'gastro-starter'); ?></strong> <?php echo intval($reservation->people); ?></li>
                    </ul>
                    
                    <form method="post" class="cancel-form">
                        <?php wp_nonce_field('cancel_reservation_' . $reservation_id); ?>
                        <input type="hidden" name="confirm_cancel" value="1">
                        <button type="submit" class="button cancel-button"><?php _e('Confirmer l\'annulation', 'gastro-starter'); ?></button>
                        <a href="<?php echo home_url(); ?>" class="button back-button"><?php _e('Retour', 'gastro-starter'); ?></a>
                    </form>
                </div>
            <?php else : ?>
                <div class="error-message">
                    <?php _e('Réservation non trouvée.', 'gastro-starter'); ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="success-message">
                <h1><?php _e('Réservation annulée', 'gastro-starter'); ?></h1>
                <p><?php _e('Votre réservation a bien été annulée. Un email de confirmation vous a été envoyé.', 'gastro-starter'); ?></p>
                <a href="<?php echo home_url(); ?>" class="button"><?php _e('Retour à l\'accueil', 'gastro-starter'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cancel-reservation-page {
    max-width: 600px;
    margin: 2rem auto;
    padding: 2rem;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.reservation-details {
    margin: 2rem 0;
}

.reservation-details ul {
    list-style: none;
    padding: 0;
    margin: 1rem 0;
}

.reservation-details li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.cancel-form {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

.button {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
}

.cancel-button {
    background-color: #dc3545;
    color: white;
}

.back-button {
    background-color: #6c757d;
    color: white;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.success-message {
    text-align: center;
    padding: 2rem;
}

.success-message h1 {
    color: #28a745;
    margin-bottom: 1rem;
}
</style>

<?php get_footer(); ?> 