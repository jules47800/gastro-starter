<?php
/**
 * Template pour le formulaire de réservation - Version 2.0 aligné sur l'ancien style
 * Nouveau système d'horaires par jour sans distinction déjeuner/dîner
 *
 * @package Gastro_Starter
 */
?>

<div class="reservation-form-container">
    <?php if (isset($_GET['reservation_success']) && $_GET['reservation_success'] == '1'): ?>
        <div class="reservation-response success" style="display:block;">
            <h3><?php echo esc_html__('Réservation envoyée avec succès !', 'gastro-starter'); ?></h3>
            <p><?php echo esc_html__('Nous avons bien reçu votre demande de réservation. Vous allez recevoir un email de confirmation dans quelques instants.', 'gastro-starter'); ?></p>
            <p><?php echo esc_html__('Nous vous contacterons rapidement pour confirmer votre réservation.', 'gastro-starter'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['reservation_error']) && !empty($_GET['reservation_error'])): ?>
        <div class="reservation-response error" style="display:block;">
            <?php echo wp_kses_post(urldecode($_GET['reservation_error'])); ?>
        </div>
    <?php endif; ?>

    <!-- Badges de réassurance minimalistes -->
    <div class="trust-badges">
        <div class="trust-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span><?php _e('Confirmation automatique', 'gastro-starter'); ?></span>
        </div>
        <div class="trust-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span><?php _e('100% sécurisé', 'gastro-starter'); ?></span>
        </div>
    </div>

    <form id="reservation-form" class="clean-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="send_reservation">
        <?php wp_nonce_field('send_reservation_nonce', 'reservation_nonce'); ?>

        <!-- 1. DATE EN PREMIER -->
        <div id="reservation-event-banner" class="reservation-event-banner" style="display:none;" role="complementary" aria-live="polite"></div>

        <div class="form-row">
            <div class="form-field">
                <label for="date"><?php _e('Date', 'gastro-starter'); ?></label>
                <input type="text" id="date" name="date" class="datepicker" placeholder="<?php _e('JJ/MM/AAAA', 'gastro-starter'); ?>" required readonly>
            </div>
            <div class="form-field">
                <!-- Vide pour alignement -->
            </div>
        </div>

        <!-- 2. NOMBRE DE PERSONNES EN DEUXIÈME -->
        <div class="form-row">
            <div class="form-field">
                <label for="people"><?php _e('Personnes', 'gastro-starter'); ?></label>
                <select id="people" name="people" required>
                    <option value="" disabled selected><?php _e('Sélectionnez...', 'gastro-starter'); ?></option>
                    <?php for ($i = 1; $i <= 10; $i++) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i > 1 ? esc_html__('personnes', 'gastro-starter') : esc_html__('personne', 'gastro-starter'); ?></option>
                    <?php endfor; ?>
                    <option value="more"><?php echo esc_html__('Plus de 10 personnes', 'gastro-starter'); ?></option>
                </select>
                <div class="capacity-info"></div>
                <div id="group-cta" style="display:none;margin-top:10px;background:#fff3cd;color:#856404;padding:10px 14px;border-radius:6px;font-size:0.98em;">
                    <strong><?php echo esc_html__('Pour les groupes de 4 personnes ou plus, merci de nous appeler pour garantir la meilleure expérience !', 'gastro-starter'); ?></strong><br>
                    <a href="tel:+33602556315" class="btn" style="margin-top:7px;min-width:160px;display:inline-block;">📞 <?php echo esc_html__('Appeler le restaurant', 'gastro-starter'); ?></a>
                </div>
            </div>
            <div class="form-field">
                <!-- Vide pour alignement -->
            </div>
        </div>

        <!-- 3. CRÉNEAU EN TROISIÈME (caché par défaut) -->
        <div id="time-selection-section" style="display:none;">
            <div class="form-row">
                <div class="form-field full-width">
                    <label for="time"><?php _e('Horaire', 'gastro-starter'); ?></label>
                    <select id="time" name="time" required disabled>
                        <option value=""><?php _e('Choisissez d\'abord une date et le nombre de personnes', 'gastro-starter'); ?></option>
                    </select>
                    <div class="time-availability"></div>
                </div>
            </div>
        </div>


        <div class="availability-notice" style="background:#f8f9fa;border:1px solid #dee2e6;padding:16px;border-radius:4px;margin:24px 0;font-size:0.95em;color:#495057;">
            <strong style="color:#212529;">💡 <?php echo esc_html__('Aucune disponibilité pour votre groupe ?', 'gastro-starter'); ?></strong><br>
            <?php echo esc_html__('Le système ne voit pas toutes nos possibilités. Si vous êtes plus nombreux que ce qu\'il affiche, appelez-nous : on trouve souvent une table !', 'gastro-starter'); ?>
            <br>
            <a href="tel:+33602556315" style="margin-top:10px;display:inline-block;color:#212529;text-decoration:underline;">📞 <?php echo esc_html__('06 02 55 63 15', 'gastro-starter'); ?></a>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="customer_name"><?php _e('Nom', 'gastro-starter'); ?></label>
                <input type="text" id="customer_name" name="customer_name" placeholder="<?php _e('Nom et prénom', 'gastro-starter'); ?>" required>
            </div>
            <div class="form-field">
                <label for="customer_phone"><?php _e('Téléphone', 'gastro-starter'); ?></label>
                <input type="tel" id="customer_phone" name="customer_phone" placeholder="<?php _e('Pour vous contacter', 'gastro-starter'); ?>" required>
            </div>
        </div>

        <div class="form-field full-width">
            <label for="customer_email"><?php _e('Email', 'gastro-starter'); ?></label>
            <input type="email" id="customer_email" name="customer_email" placeholder="<?php _e('Pour confirmation', 'gastro-starter'); ?>" required>
        </div>

        <div class="form-field full-width">
            <div class="reservation-extra-info">
                <strong><?php _e('Allergies ou végétarien ?', 'gastro-starter'); ?></strong> — <?php _e('Merci de préciser toute allergie ou demande végétarienne dans les notes. Nous adaptons nos plats avec plaisir !', 'gastro-starter'); ?>
            </div>
            <label for="notes"><?php _e('Notes spéciales (optionnel)', 'gastro-starter'); ?></label>
            <textarea id="notes" name="notes" placeholder="<?php _e('Allergies, occasion spéciale...', 'gastro-starter'); ?>"></textarea>
        </div>

        <div class="form-section-divider"></div>

        <div class="form-checkboxes rgpd-section">
            <h4 class="rgpd-title"><?php _e('Préférences & Consentement', 'gastro-starter'); ?></h4>
            
            <label class="checkbox-label" for="accept_reminder">
                <input type="checkbox" id="accept_reminder" name="accept_reminder" value="1" checked>
                <span class="checkmark"></span>
                <?php _e('Recevoir un rappel par email avant ma réservation', 'gastro-starter'); ?>
            </label>
            
            <label class="checkbox-label" for="newsletter">
                <input type="checkbox" id="newsletter" name="newsletter" value="1">
                <span class="checkmark"></span>
                <?php _e('S\'inscrire à notre newsletter pour nos actualités et offres', 'gastro-starter'); ?>
            </label>
            
            <div class="form-section-divider-small"></div>

            <label class="checkbox-label" for="consent_data_processing">
                <input type="checkbox" id="consent_data_processing" name="consent_data_processing" value="1" required>
                <span class="checkmark"></span>
                <?php 
                $privacy_policy_url = get_privacy_policy_url();
                printf(
                    wp_kses_post(__('J\'ai lu et j\'accepte la <a href="%s" target="_blank">politique de confidentialité</a> du site.*', 'gastro-starter')),
                    esc_url($privacy_policy_url)
                );
                ?>
            </label>
            
            <p class="privacy-notice-required" style="font-size: 0.75rem; color: #dc3545; margin: 8px 0 0 28px; font-style: italic;">
                <?php _e('* Mention obligatoire : Vos données personnelles sont collectées et traitées conformément à notre politique de confidentialité.', 'gastro-starter'); ?>
            </p>
        </div>

        <div class="form-group-hidden">
            <input type="text" name="reservation_hp" id="reservation_hp" value="">
        </div>

        <div class="gdpr-notice">
            <p><?php printf(
                wp_kses_post(__('En soumettant ce formulaire, vous acceptez notre <a href="%1$s" target="_blank">politique de confidentialité</a>. Vous pouvez à tout moment exercer vos droits RGPD via notre <a href="%2$s" target="_blank">formulaire de suppression de données</a>.', 'gastro-starter')),
                home_url('/politique-confidentialite'),
                home_url('/suppression-donnees')
            ); ?></p>
        </div>

        <button type="submit" class="reserve-btn" disabled>
            <span class="btn-text"><?php _e('Je réserve ma table', 'gastro-starter'); ?></span>
            <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
        <div class="reservation-response" style="display: none;"></div>
    </form>
</div>

<script>
// Ce script peut être déplacé dans un fichier JS externe si nécessaire.
document.addEventListener('DOMContentLoaded', function() {
    const timeAvailabilityDiv = document.querySelector('.time-availability');
    const timeSelect = document.getElementById('time');

    if (timeAvailabilityDiv && timeSelect) {
        timeAvailabilityDiv.addEventListener('click', function(e) {
            const target = e.target.closest('.time-slot.selectable');
            if (target) {
                const time = target.dataset.time;
                
                // Mettre à jour la valeur du select
                timeSelect.value = time;
                
                // Déclencher l'événement 'change' pour la validation
                timeSelect.dispatchEvent(new Event('change'));
                
                // Mettre à jour la classe 'selected'
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected');
                });
                target.classList.add('selected');
            }
        });
    }
});
</script> 