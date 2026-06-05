/**
 * JavaScript pour l'interface d'administration des réservations - Gastro Starter
 * Gestion des plages horaires multiples et interface dynamique
 */

(function($) {
    'use strict';

    // Variables globales
    let rangeCounter = {};

    /**
     * Initialisation du script
     */
    function init() {
        setupToggleSwitches();
        setupTimeRanges();
        setupFormValidation();
        // setupRealTimePreview(); // Commenté pour permettre le surbooking depuis l'admin
        initStatWidgets();
        initEmailTest();
        initSmoothScroll();
        handleActionScrolling();
        initEditReservation();
    }

    /**
     * Configuration des switches d'ouverture/fermeture
     */
    function setupToggleSwitches() {
        $('.day-toggle input[type="checkbox"]').on('change', function() {
            const daySchedule = $(this).closest('.schedule-day').find('.day-schedule');
            const isOpen = $(this).is(':checked');
            
            if (isOpen) {
                daySchedule.removeClass('day-closed').addClass('day-open');
            } else {
                daySchedule.removeClass('day-open').addClass('day-closed');
            }
            
            updateSlotsPreview($(this).closest('.schedule-day'));
        });
    }

    /**
     * Configuration des plages horaires
     */
    function setupTimeRanges() {
        // Initialiser le compteur pour chaque jour
        $('.schedule-day').each(function() {
            const dayKey = $(this).data('day');
            rangeCounter[dayKey] = $(this).find('.time-range-row').length;
        });

        // Gestion de l'ajout de plages
        $(document).on('click', '.add-range', function() {
            const dayKey = $(this).data('day');
            addTimeRange(dayKey);
        });

        // Gestion de la suppression de plages
        $(document).on('click', '.remove-range', function(e) {
            e.preventDefault();
            const rangeRow = $(this).closest('.time-range-row');
            const dayKey = $(this).data('day');

            rangeRow.fadeOut(300, function() {
                $(this).remove();
                reindexTimeRanges(dayKey);
                
                const dayElement = $(`.schedule-day[data-day="${dayKey}"]`);
                updateSlotsPreview(dayElement);
            });
        });

        // Mise à jour en temps réel des créneaux
        $(document).on('change', 'input[type="time"], select[name*="slot_interval"]', function() {
            const dayElement = $(this).closest('.schedule-day');
            updateSlotsPreview(dayElement);
        });
    }

    /**
     * Ajouter une nouvelle plage horaire
     */
    function addTimeRange(dayKey) {
        const container = $(`.time-ranges-container[data-day="${dayKey}"]`);
        const newIndex = rangeCounter[dayKey]++;
        
        const newRange = `
            <div class="time-range-row" data-index="${newIndex}">
                <div class="time-inputs">
                    <div class="time-input">
                        <label>Début</label>
                        <input type="time" name="gastro_starter_daily_schedule[${dayKey}][time_ranges][${newIndex}][start]" value="12:00">
                    </div>
                    <div class="time-input">
                        <label>Fin</label>
                        <input type="time" name="gastro_starter_daily_schedule[${dayKey}][time_ranges][${newIndex}][end]" value="14:00">
                    </div>
                </div>
                <button type="button" class="remove-range" data-day="${dayKey}" data-index="${newIndex}">Supprimer</button>
            </div>
        `;
        
        container.append(newRange);
        
        // Mettre à jour l'aperçu des créneaux
        const dayElement = container.closest('.schedule-day');
        updateSlotsPreview(dayElement);
        
        // Animation d'apparition
        container.find('.time-range-row:last').hide().fadeIn(300);
    }

    /**
     * Supprimer une plage horaire
     */
    function removeTimeRange(dayKey, index) {
        const rangeRow = $(`.time-range-row[data-index="${index}"]`);
        if (!rangeRow.length) return;
        rangeRow.fadeOut(300, function() {
            $(this).remove();
            reindexTimeRanges(dayKey);
            const dayElement = $(`.schedule-day[data-day="${dayKey}"]`);
            updateSlotsPreview(dayElement);
        });
    }

    /**
     * Réindexer les plages horaires après suppression
     */
    function reindexTimeRanges(dayKey) {
        const container = $(`.time-ranges-container[data-day="${dayKey}"]`);
        let newIndex = 0;
        
        container.find('.time-range-row').each(function() {
            $(this).attr('data-index', newIndex);
            $(this).find('input[name*="[start]"]').attr('name', `gastro_starter_daily_schedule[${dayKey}][time_ranges][${newIndex}][start]`);
            $(this).find('input[name*="[end]"]').attr('name', `gastro_starter_daily_schedule[${dayKey}][time_ranges][${newIndex}][end]`);
            $(this).find('.remove-range').attr('data-index', newIndex);
            newIndex++;
        });
        
        rangeCounter[dayKey] = newIndex;
    }

    /**
     * Mettre à jour l'aperçu des créneaux en temps réel
     */
    function updateSlotsPreview(dayElement) {
        const isOpen = dayElement.find('.day-toggle input[type="checkbox"]').is(':checked');
        const slotsList = dayElement.find('.slots-list');
        
        if (!isOpen) {
            slotsList.html('<span class="day-closed-text">Jour fermé</span>');
            return;
        }
        
        // Collecter les plages horaires
        const timeRanges = [];
        dayElement.find('.time-range-row').each(function() {
            const start = $(this).find('input[name*="[start]"]').val();
            const end = $(this).find('input[name*="[end]"]').val();
            if (start && end) {
                timeRanges.push({ start, end });
            }
        });
        
        // Collecter l'intervalle
        const interval = parseInt(dayElement.find('select[name*="slot_interval"]').val()) || 30;
        
        // Générer les créneaux
        const allSlots = [];
        timeRanges.forEach(range => {
            const slots = generateTimeSlots(range.start, range.end, interval);
            allSlots.push(...slots);
        });
        
        // Trier les créneaux
        allSlots.sort();
        
        // Afficher l'aperçu
        if (allSlots.length > 0) {
            const exampleSlots = allSlots.slice(0, 5);
            const preview = `
                <span class="slot-count">${allSlots.length} créneaux</span>
                <div class="slots-example">
                    ${exampleSlots.join(', ')}${allSlots.length > 5 ? '...' : ''}
                </div>
            `;
            slotsList.html(preview);
        } else {
            slotsList.html('<span class="day-closed-text">Aucun créneau configuré</span>');
        }
    }

    /**
     * Générer les créneaux horaires (version JavaScript)
     */
    function generateTimeSlots(startTime, endTime, intervalMinutes) {
        const slots = [];
        const start = new Date(`2000-01-01T${startTime}:00`);
        const end = new Date(`2000-01-01T${endTime}:00`);
        
        let current = new Date(start);
        
        while (current < end) {
            slots.push(current.toTimeString().slice(0, 5));
            current.setMinutes(current.getMinutes() + intervalMinutes);
        }
        
        return slots;
    }

    /**
     * Configuration de la validation des formulaires
     */
    function setupFormValidation() {
        $('form').on('submit', function(e) {
            let isValid = true;
            const errors = [];
            
            // Vérifier que chaque jour ouvert a au moins une plage horaire
            $('.schedule-day').each(function() {
                const isOpen = $(this).find('.day-toggle input[type="checkbox"]').is(':checked');
                const hasRanges = $(this).find('.time-range-row').length > 0;
                
                if (isOpen && !hasRanges) {
                    const dayName = $(this).find('.day-header h3').text();
                    errors.push(`${dayName} : Veuillez ajouter au moins une plage horaire.`);
                    isValid = false;
                }
            });
            
            // Vérifier la cohérence des heures
            $('.time-range-row').each(function() {
                const start = $(this).find('input[name*="[start]"]').val();
                const end = $(this).find('input[name*="[end]"]').val();
                
                if (start && end && start >= end) {
                    errors.push('L\'heure de fin doit être postérieure à l\'heure de début.');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Erreurs de validation :\n' + errors.join('\n'));
            }
        });
    }

    /**
     * Configuration de l'aperçu en temps réel
     */
    function setupRealTimePreview() {
        // Mise à jour automatique lors des changements
        $(document).on('input change', '.time-range-row input, .slot-interval select', function() {
            const dayElement = $(this).closest('.schedule-day');
            updateSlotsPreview(dayElement);
        });
    }

    // ========================================
    // FONCTIONS EXISTANTES (conservées)
    // ========================================

    // Gestion des widgets statistiques cliquables
    function initStatWidgets() {
        $('.stat-box-clickable').on('click', function(e) {
            var $this = $(this);
            var hasItems = $this.attr('data-has-items');
            var href = $this.attr('href');
            
            console.log('Widget cliqué:', {
                hasItems: hasItems,
                href: href,
                element: $this
            });
            
            // Si le widget n'a pas d'éléments, empêcher la navigation
            if (hasItems === 'false') {
                e.preventDefault();
                return false;
            }
            
            // Pour les widgets avec des éléments, ajouter un feedback visuel
            if (hasItems === 'true') {
                $this.addClass('stat-loading');
                
                // Si c'est un lien interne, laisser le comportement normal
                if (href && href.indexOf('gastro-starter-reservations') !== -1) {
                    return true;
                }
            }
        });
    }
    
    // Gestion du test d'email
    function initEmailTest() {
        $('#test-email-btn').on('click', function() {
            var $btn = $(this);
            var $result = $('#test-email-result');
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Test en cours...');
            $result.html('<div class="notice notice-info inline"><p>⏳ Test d\'envoi d\'email en cours...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gastro_starter_test_email'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>✅ ' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>❌ ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>❌ Erreur lors du test AJAX</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Tester l\'envoi d\'emails');
                }
            });
        });
    }
    
    // Gestion du smooth scroll
    function initSmoothScroll() {
        $('a[href*="#"]').click(function(e) {
            if (this.hash !== '') {
                var target = $(this.hash);
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 50
                    }, 600);
                }
            }
        });
    }
    
    // Auto-scroll après actions
    function handleActionScrolling() {
        // Si une action vient d'être effectuée, scroll vers la liste des réservations
        if (window.location.search.includes('action=') && window.location.search.includes('id=')) {
            setTimeout(function() {
                var $target = $('#reservations-list');
                if ($target.length) {
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 80
                    }, 800);
                }
            }, 500);
        }
        
        // Si le filtre pending est actif, mettre en évidence le widget et scroll vers la liste
        if (window.location.search.includes('status_filter=pending')) {
            console.log('Filtre pending actif - mise en évidence du widget');
            $('.stat-box-clickable').eq(1).addClass('stat-active'); // Le widget "En attente" est le 2ème
            setTimeout(function() {
                var $target = $('#reservations-list');
                if ($target.length) {
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 80
                    }, 600);
                }
            }, 300);
        }
        
        // Si on affiche les réservations d'aujourd'hui uniquement
        var urlParams = new URLSearchParams(window.location.search);
        var today = new Date().toISOString().split('T')[0];
        if (urlParams.get('view') === 'day' && urlParams.get('date_filter') === today) {
            console.log('Vue jour + date aujourd\'hui - mise en évidence du widget Aujourd\'hui');
            $('.stat-box-clickable').eq(0).addClass('stat-active'); // Le widget "Aujourd\'hui" est le 1er
        }
    }

    /**
     * Fonctions utilitaires
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Ignorer cette notification.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend(notification);
        
        // Auto-dismiss après 5 secondes
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Gestion des erreurs AJAX
     */
    function handleAjaxError(xhr, status, error) {
        console.error('Erreur AJAX:', error);
        showNotification('Une erreur est survenue. Veuillez réessayer.', 'error');
    }

    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        init();
        
        // Mise à jour initiale des aperçus
        $('.schedule-day').each(function() {
            updateSlotsPreview($(this));
        });

        // NOUVEAU : Initialisation du calendrier pour les vacances (Version robuste)
        if (typeof flatpickr !== 'undefined') {
            const holidaysInput = document.getElementById('gastro_starter_holiday_dates');
            const holidaysCalendarContainer = document.getElementById('gastro_starter_holiday_dates_calendar');

            if (holidaysInput && holidaysCalendarContainer) {
                flatpickr(holidaysCalendarContainer, {
                    mode: "multiple",
                    dateFormat: "Y-m-d", // Format de sauvegarde standard
                    locale: 'fr',
                    minDate: "today",
                    showMonths: 2,
                    inline: true, // Afficher le calendrier directement
                    // Pré-sélectionner les dates déjà enregistrées
                    defaultDate: holidaysInput.value.split(',').filter(Boolean),
                    // Mettre à jour le champ caché quand une date est modifiée
                    onChange: function(selectedDates, dateStr, instance) {
                        holidaysInput.value = selectedDates
                            .map(date => instance.formatDate(date, "Y-m-d"))
                            .join(',');
                    }
                });
            }
        }
        
        // Debugging console pour les développeurs
        console.log('Admin réservations Gastro Starter initialisé avec système d\'horaires avancé');
    });

    /**
     * Export des fonctions pour utilisation externe
     */
    window.GastroStarterAdmin = {
        addTimeRange,
        removeTimeRange,
        updateSlotsPreview,
        showNotification
    };

    // ================================
    // ÉDITION DE RÉSERVATION (MODAL)
    // ================================
    function initEditReservation() {
        $(document).on('click', '.edit-button', function(e) {
            const id = $(this).data('reservation-id');
            openEditModal(id);
        });
        // Support tactile (iPad/tablette)
        addTapHandler('.edit-button', function(target){
            const id = $(target).data('reservation-id');
            openEditModal(id);
        });
    }

    function buildModal() {
        let $modal = $('#gastro-starter-edit-modal');
        if ($modal.length) return $modal;
        $modal = $(`
            <div id="gastro-starter-edit-modal" class="gastro-starter-modal" style="display:none;">
                <div class="gastro-starter-modal-backdrop"></div>
                <div class="gastro-starter-modal-dialog">
                    <div class="gastro-starter-modal-header">
                        <h2>${(gastro_starter_res_admin && gastro_starter_res_admin.i18n && gastro_starter_res_admin.i18n.editTitle) || 'Modifier la réservation'}</h2>
                        <button type="button" class="gastro-starter-modal-close">×</button>
                    </div>
                    <div class="gastro-starter-modal-body">
                        <form id="gastro-starter-edit-form">
                            <input type="hidden" name="id" />
                            <div class="form-grid">
                                <label>Date
                                    <input type="date" name="reservation_date" required />
                                </label>
                                <label>Heure
                                    <input type="time" name="reservation_time" required />
                                </label>
                                <label>Personnes
                                    <input type="number" name="people" min="1" max="20" required />
                                </label>
                                <label>Nom
                                    <input type="text" name="customer_name" required />
                                </label>
                                <label>Email
                                    <input type="email" name="customer_email" />
                                </label>
                                <label>Téléphone
                                    <input type="text" name="customer_phone" />
                                </label>
                                <label>Notes
                                    <textarea name="notes" rows="3"></textarea>
                                </label>
                            </div>
                        </form>
                        <div class="gastro-starter-modal-status" style="margin-top:8px;"></div>
                    </div>
                    <div class="gastro-starter-modal-footer">
                        <button type="button" class="button button-secondary gastro-starter-cancel">${(gastro_starter_res_admin && gastro_starter_res_admin.i18n && gastro_starter_res_admin.i18n.cancel) || 'Annuler'}</button>
                        <button type="button" class="button button-primary gastro-starter-save">${(gastro_starter_res_admin && gastro_starter_res_admin.i18n && gastro_starter_res_admin.i18n.save) || 'Enregistrer'}</button>
                    </div>
                </div>
            </div>
        `);
        $('body').append($modal);
        $modal.on('click', '.gastro-starter-modal-close, .gastro-starter-cancel, .gastro-starter-modal-backdrop', function() {
            closeModal();
        });
        $modal.on('click', '.gastro-starter-save', function() {
            submitEditForm();
        });
        return $modal;
    }

    function openEditModal(id) {
        const $modal = buildModal();
        $modal.find('.gastro-starter-modal-status').html('');
        $modal.fadeIn(120);

        $.ajax({
            url: (gastro_starter_res_admin && gastro_starter_res_admin.ajax_url) || ajaxurl,
            type: 'GET',
            data: {
                action: 'gastro_starter_get_reservation',
                security: gastro_starter_res_admin && gastro_starter_res_admin.nonce,
                id: id
            },
            success: function(resp) {
                if (!resp || !resp.success || !resp.data) {
                    showStatus('Erreur de chargement', 'error');
                    return;
                }
                fillForm(resp.data);
            },
            error: handleAjaxError
        });
    }

    function fillForm(data) {
        const $form = $('#gastro-starter-edit-form');
        $form.find('[name="id"]').val(data.id);
        $form.find('[name="reservation_date"]').val(data.reservation_date);
        $form.find('[name="reservation_time"]').val((data.reservation_time || '').slice(0,5));
        $form.find('[name="people"]').val(data.people);
        $form.find('[name="customer_name"]').val(data.customer_name);
        $form.find('[name="customer_email"]').val(data.customer_email || '');
        $form.find('[name="customer_phone"]').val(data.customer_phone || '');
        $form.find('[name="notes"]').val(data.notes || '');
    }

    function submitEditForm() {
        const $form = $('#gastro-starter-edit-form');
        const formData = $form.serializeArray();
        const payload = {};
        formData.forEach(i => payload[i.name] = i.value);
        payload.action = 'gastro_starter_update_reservation';
        payload.security = gastro_starter_res_admin && gastro_starter_res_admin.nonce;

        $.ajax({
            url: (gastro_starter_res_admin && gastro_starter_res_admin.ajax_url) || ajaxurl,
            type: 'POST',
            data: payload,
            success: function(resp) {
                if (resp && resp.success) {
                    showStatus((gastro_starter_res_admin && gastro_starter_res_admin.i18n && gastro_starter_res_admin.i18n.updated) || 'Réservation mise à jour.', 'success');
                    // Mise à jour visuelle (personnes et notes) sans rechargement
                    const id = payload.id;
                    const $row = $(".edit-button[data-reservation-id='"+id+"']").closest('tr');
                    $row.find('.column-people .people-count').text(payload.people);
                    if (payload.notes && payload.notes.trim() !== '') {
                        $row.find('.column-notes .notes-text').text(payload.notes);
                        if ($row.find('.column-notes .notes-content').length === 0) {
                            $row.find('.column-notes').html('<div class="notes-content"><span class="notes-icon dashicons dashicons-admin-comments"></span><span class="notes-text"></span></div>');
                            $row.find('.column-notes .notes-text').text(payload.notes);
                        }
                    } else {
                        $row.find('.column-notes').html('<span class="no-notes">—</span>');
                    }
                    setTimeout(closeModal, 650);
                } else {
                    showStatus((gastro_starter_res_admin && gastro_starter_res_admin.i18n && gastro_starter_res_admin.i18n.error) || 'Erreur lors de la mise à jour.', 'error');
                }
            },
            error: handleAjaxError
        });
    }

    function showStatus(msg, type) {
        const $box = $('#gastro-starter-edit-modal .gastro-starter-modal-status');
        $box.html(`<div class="notice notice-${type} inline"><p>${msg}</p></div>`);
    }

    function closeModal() {
        $('#gastro-starter-edit-modal').fadeOut(100);
    }

    // Fiabiliser les taps sur les autres boutons d'action (liens)
    addTapHandler('.reservations-table .action-buttons a', function(target){
        const href = target.getAttribute('href');
        if (href) {
            // Déclencher le click natif, fallback navigation si nécessaire
            try { target.click(); return; } catch(_e) {}
            window.location.href = href;
        }
    });

    // Gestion générique du "tap" (fiable en environnement tactile)
    function addTapHandler(selector, onTap) {
        let startX = 0, startY = 0, startTime = 0;
        const TAP_MOVE_THRESHOLD = 10; // px
        const TAP_TIME_THRESHOLD = 500; // ms

        $(document).on('touchstart', selector, function(e){
            const t = e.originalEvent.touches && e.originalEvent.touches[0];
            if (!t) return;
            startX = t.pageX;
            startY = t.pageY;
            startTime = Date.now();
        });

        $(document).on('touchend', selector, function(e){
            const t = e.originalEvent.changedTouches && e.originalEvent.changedTouches[0];
            if (!t) return;
            const dx = Math.abs(t.pageX - startX);
            const dy = Math.abs(t.pageY - startY);
            const dt = Date.now() - startTime;
            const isTap = dx < TAP_MOVE_THRESHOLD && dy < TAP_MOVE_THRESHOLD && dt < TAP_TIME_THRESHOLD;
            if (!isTap) return; // geste de scroll -> ne rien faire
            e.preventDefault();
            onTap(this);
        });
    }

})(jQuery); 