<?php
/**
 * Template pour le formulaire de réservation rapide
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

function gastro_starter_quick_reservation_form() {
    $restaurant_capacity = get_option('gastro_starter_restaurant_capacity', 4);
    ?>
    <div class="postbox">
        <h2 class="hndle"><span><?php echo esc_html__('Réservation rapide', 'gastro-starter'); ?></span></h2>
        <div class="inside">
            <form method="post" class="quick-add-form">
                <?php wp_nonce_field('quick_add_reservation'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="customer_name"><?php echo esc_html__('Client', 'gastro-starter'); ?></label></th>
                        <td class="customer-autocomplete-wrap">
                            <input type="text" id="customer_name" name="customer_name" class="regular-text" required autocomplete="off">
                            <div class="customer-autocomplete-list" id="customer-autocomplete-list"></div>
                        </td>
                        
                        <th scope="row"><label for="customer_phone"><?php echo esc_html__('Téléphone', 'gastro-starter'); ?></label></th>
                        <td><input type="tel" id="customer_phone" name="customer_phone" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customer_email"><?php echo esc_html__('Email', 'gastro-starter'); ?></label></th>
                        <td colspan="3"><input type="email" id="customer_email" name="customer_email" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reservation_date"><?php echo esc_html__('Date', 'gastro-starter'); ?></label></th>
                        <td><input type="date" id="reservation_date" name="reservation_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required></td>
                        
                        <th scope="row"><label for="people"><?php echo esc_html__('Personnes', 'gastro-starter'); ?></label></th>
                        <td><input type="number" id="people" name="people" min="1" value="2" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reservation_time"><?php echo esc_html__('Heure', 'gastro-starter'); ?></label></th>
                        <td>
                            <select id="reservation_time" name="reservation_time" required>
                                <option value=""><?php echo esc_html__('Choisissez d\'abord une date', 'gastro-starter'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes"><?php echo esc_html__('Notes', 'gastro-starter'); ?></label></th>
                        <td colspan="3"><textarea id="notes" name="notes" rows="2" class="large-text"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="quick_add_reservation" class="button button-primary">
                        <?php echo esc_html__('Ajouter la réservation', 'gastro-starter'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <style>
    .postbox {
        background: #fff;
        border: 1px solid #e2e4e7;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-radius: 8px;
        margin-bottom: 30px;
    }

    .postbox .hndle {
        border-bottom: 1px solid #e2e4e7;
        padding: 15px 20px;
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }

    .postbox .inside {
        padding: 20px;
        margin: 0;
    }

    .quick-add-form .form-table {
        border-collapse: separate;
        border-spacing: 0 15px;
        margin: 0;
    }

    .quick-add-form .form-table th {
        width: 120px;
        padding: 10px 15px 10px 0;
        font-weight: 500;
        color: #1d2327;
        vertical-align: middle;
    }

    .quick-add-form .form-table td {
        padding: 0 15px;
        vertical-align: middle;
    }

    .quick-add-form input[type="text"],
    .quick-add-form input[type="tel"],
    .quick-add-form input[type="date"],
    .quick-add-form input[type="number"],
    .quick-add-form select,
    .quick-add-form textarea {
        width: 100%;
        max-width: 250px;
        padding: 8px 12px;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.4;
        transition: all 0.2s ease;
    }

    .quick-add-form input[type="date"] {
        width: 150px;
    }

    .quick-add-form input[type="number"] {
        width: 80px;
    }

    .quick-add-form select {
        background-color: #fff;
        height: 36px;
    }

    .quick-add-form textarea {
        max-width: 100%;
        min-height: 80px;
    }

    .quick-add-form input:focus,
    .quick-add-form select:focus,
    .quick-add-form textarea:focus {
        border-color: #b5a692;
        box-shadow: 0 0 0 1px #b5a692;
        outline: none;
    }

    .quick-add-form .submit {
        margin: 20px 0 0;
        padding: 15px 0 0;
        border-top: 1px solid #e2e4e7;
    }

    .quick-add-form .button-primary {
        background: #b5a692;
        border-color: #b5a692;
        color: #fff;
        padding: 8px 20px;
        height: auto;
        line-height: 1.4;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .quick-add-form .button-primary:hover,
    .quick-add-form .button-primary:focus {
        background: #a39483;
        border-color: #a39483;
    }

    /* Autocomplete */
    .customer-autocomplete-wrap {
        position: relative;
    }

    .customer-autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        max-width: 250px;
        background: #fff;
        border: 1px solid #dcdcde;
        border-top: none;
        border-radius: 0 0 4px 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
        max-height: 240px;
        overflow-y: auto;
    }

    .customer-autocomplete-list.open {
        display: block;
    }

    .customer-autocomplete-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.15s;
    }

    .customer-autocomplete-item:last-child {
        border-bottom: none;
    }

    .customer-autocomplete-item:hover,
    .customer-autocomplete-item.active {
        background: #f5f2ee;
    }

    .customer-autocomplete-item .ac-name {
        font-weight: 500;
        color: #1d2327;
        font-size: 14px;
    }

    .customer-autocomplete-item .ac-detail {
        font-size: 12px;
        color: #757575;
        margin-top: 2px;
    }

    /* Responsive */
    @media screen and (max-width: 782px) {
        .quick-add-form .form-table,
        .quick-add-form .form-table tbody,
        .quick-add-form .form-table tr,
        .quick-add-form .form-table th,
        .quick-add-form .form-table td {
            display: block;
            width: 100%;
            padding: 5px 0;
        }

        .quick-add-form .form-table th {
            padding-bottom: 0;
        }

        .quick-add-form input[type="text"],
        .quick-add-form input[type="tel"],
        .quick-add-form input[type="date"],
        .quick-add-form input[type="number"],
        .quick-add-form select,
        .quick-add-form textarea {
            max-width: 100%;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const timeSelect = $('#reservation_time');
        const dateInput = $('#reservation_date');
        const peopleInput = $('#people');
        
        // NOUVEAU : Utiliser le système d'horaires par jour
        const dailySchedule = <?php echo json_encode(get_option('gastro_starter_daily_schedule', array())); ?>;
        
        // Fonction pour générer les créneaux horaires
        function generateTimeSlots(startTime, endTime, interval = 15) {
            const slots = [];
            const start = new Date('2000-01-01T' + startTime + ':00');
            const end = new Date('2000-01-01T' + endTime + ':00');
            
            let current = new Date(start);
            while (current < end) {
                const timeString = current.toTimeString().slice(0, 5);
                slots.push(timeString);
                current.setMinutes(current.getMinutes() + interval);
            }
            
            return slots;
        }
        
        function updateAvailableTimes() {
            const selectedDate = dateInput.val();
            const selectedPeople = parseInt(peopleInput.val()) || 0;
            
            timeSelect.empty().append('<option value=""><?php echo esc_js(__('Chargement...', 'gastro-starter')); ?></option>');
            
            if (!selectedDate) {
                timeSelect.empty().append('<option value=""><?php echo esc_js(__('Choisissez d\'abord une date', 'gastro-starter')); ?></option>');
                return;
            }
            
            // Vérifier si le restaurant est ouvert ce jour-là
            const dateObj = new Date(selectedDate);
            const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const dayKey = days[dateObj.getDay()];
            const daySchedule = dailySchedule[dayKey];
            
            if (!daySchedule || !daySchedule.open) {
                timeSelect.empty().append('<option value=""><?php echo esc_js(__('Restaurant fermé à cette date', 'gastro-starter')); ?></option>');
                return;
            }
            
            // Utiliser l'URL AJAX de WordPress avec paramètres corrects
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'GET',
                data: {
                    action: 'gastro_starter_get_availability',
                    date: selectedDate,
                    source: 'admin' // Indiquer que l'appel vient de l'admin
                },
                success: function(response) {
                    timeSelect.empty();
                    
                    if (response.success) {
                        const availableSlots = response.data.available_slots;
                        
                        if (availableSlots && availableSlots.length > 0) {
                            availableSlots.forEach(function(slot) {
                                // Pour l'admin, on affiche tous les créneaux sans vérifier la capacité restante.
                                timeSelect.append(
                                    $('<option>', {
                                        value: slot.time,
                                        text: slot.time
                                    })
                                );
                            });
                        } else {
                            timeSelect.append('<option value=""><?php echo esc_js(__('Aucun créneau configuré pour ce jour', 'gastro-starter')); ?></option>');
                        }
                    } else {
                        timeSelect.append('<option value=""><?php echo esc_js(__('Erreur de chargement des créneaux', 'gastro-starter')); ?></option>');
                    }
                },
                error: function() {
                    timeSelect.empty().append('<option value=""><?php echo esc_js(__('Erreur de communication avec le serveur.', 'gastro-starter')); ?></option>');
                }
            });
        }
        
        // Déclencher la mise à jour lors des changements
        dateInput.on('change', updateAvailableTimes);
        peopleInput.on('change', updateAvailableTimes);
        
        // Déclencher automatiquement au chargement si une date est sélectionnée
        if (dateInput.val()) {
            updateAvailableTimes();
        }

        // ─── Autocomplétion client ─────────────────────────────────────────────
        const nameInput = $('#customer_name');
        const acList = $('#customer-autocomplete-list');
        let acTimeout = null;
        let acIndex = -1;

        nameInput.on('input', function() {
            clearTimeout(acTimeout);
            const term = this.value.trim();
            if (term.length < 2) {
                acList.removeClass('open').empty();
                return;
            }
            acTimeout = setTimeout(function() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'gastro_starter_customer_autocomplete',
                        nonce: '<?php echo wp_create_nonce('gastro_starter_reservation_edit'); ?>',
                        term: term
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            acIndex = -1;
                            let html = '';
                            response.data.forEach(function(c) {
                                const detail = [c.phone, c.email].filter(Boolean).join(' — ');
                                html += '<div class="customer-autocomplete-item" data-name="' + $('<span>').text(c.name).html() + '" data-phone="' + $('<span>').text(c.phone).html() + '" data-email="' + $('<span>').text(c.email).html() + '">';
                                html += '<div class="ac-name">' + $('<span>').text(c.name).html() + '</div>';
                                if (detail) html += '<div class="ac-detail">' + $('<span>').text(detail).html() + '</div>';
                                html += '</div>';
                            });
                            acList.html(html).addClass('open');
                        } else {
                            acList.removeClass('open').empty();
                        }
                    }
                });
            }, 300);
        });

        acList.on('click', '.customer-autocomplete-item', function() {
            nameInput.val($(this).data('name'));
            $('#customer_phone').val($(this).data('phone'));
            $('#customer_email').val($(this).data('email'));
            acList.removeClass('open').empty();
        });

        nameInput.on('keydown', function(e) {
            const items = acList.find('.customer-autocomplete-item');
            if (!items.length || !acList.hasClass('open')) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                acIndex = Math.min(acIndex + 1, items.length - 1);
                items.removeClass('active').eq(acIndex).addClass('active');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                acIndex = Math.max(acIndex - 1, 0);
                items.removeClass('active').eq(acIndex).addClass('active');
            } else if (e.key === 'Enter' && acIndex >= 0) {
                e.preventDefault();
                items.eq(acIndex).trigger('click');
            } else if (e.key === 'Escape') {
                acList.removeClass('open').empty();
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.customer-autocomplete-wrap').length) {
                acList.removeClass('open').empty();
            }
        });
    });
    </script>
    <?php
} 