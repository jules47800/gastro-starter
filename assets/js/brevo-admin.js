/**
 * Brevo Newsletter Admin — Gastro Starter
 * Gestion du repeater de menu, prévisualisation, et envoi de newsletters
 */
(function($) {
    'use strict';

    var BrevoAdmin = {
        init: function() {
            this.bindEvents();
            this.updateContactCount();
        },

        bindEvents: function() {
            // Repeater: ajouter un plat
            $(document).on('click', '#brevo-add-menu-item', this.addMenuItem);
            // Repeater: supprimer un plat
            $(document).on('click', '.brevo-remove-item', this.removeMenuItem);
            // Audience: changement
            $(document).on('change', 'input[name="email_audience"]', this.updateContactCount);
            // Image picker hero
            $(document).on('click', '#brevo-choose-image', this.openMediaPicker);
            $(document).on('click', '#brevo-remove-image', this.removeImage);
            // Image picker menu (remplace les items)
            $(document).on('click', '#brevo-choose-menu-image', this.openMenuImagePicker);
            $(document).on('click', '#brevo-remove-menu-image', this.removeMenuImage);
            // Gallery picker
            $(document).on('click', '.brevo-choose-gallery', this.openGalleryPicker);
            $(document).on('click', '.brevo-remove-gallery', this.removeGalleryImage);
            // Onglets FR/EN
            $(document).on('click', '.brevo-tab', this.switchTab);
            // Switch de mode menu (items / image)
            $(document).on('change', 'input[name="menu_mode"]', this.switchMenuMode);
            // Prévisualiser
            $(document).on('click', '#brevo-preview-btn', this.previewEmail);
            // Fermer la modal
            $(document).on('click', '#brevo-modal-close, #brevo-modal-overlay', this.closeModal);
            // Envoyer
            $(document).on('click', '#brevo-send-btn', this.sendNewsletter);
            // Envoyer test
            $(document).on('click', '#brevo-send-test-btn', this.sendTestEmail);
            // Rendre le menu sortable
            this.initSortable();
        },

        /**
         * Bascule entre les onglets FR/EN
         */
        switchTab: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var target = $btn.data('tab');
            var $section = $btn.closest('.brevo-section');
            $section.find('.brevo-tab').removeClass('active');
            $btn.addClass('active');
            $section.find('.brevo-tab-panel').removeClass('active');
            $section.find('.brevo-tab-panel[data-panel="' + target + '"]').addClass('active');
        },

        /**
         * Bascule entre le mode "liste de plats" et "image du menu"
         */
        switchMenuMode: function() {
            var mode = $('input[name="menu_mode"]:checked').val() || 'items';
            $('.brevo-menu-panel').hide();
            $('.brevo-menu-panel[data-menu-panel="' + mode + '"]').show();
            $('.brevo-switch-option').removeClass('active');
            $('input[name="menu_mode"]:checked').closest('.brevo-switch-option').addClass('active');
        },

        /**
         * Repeater de menu dynamique
         */
        addMenuItem: function(e) {
            e.preventDefault();
            var $list = $('#brevo-menu-items-list');
            var index = $list.children().length;
            var template = '<div class="brevo-menu-item" data-index="' + index + '">'
                + '<div class="brevo-menu-item-header">'
                + '<span class="brevo-menu-item-number">' + String(index + 1).padStart(2, '0') + '</span>'
                + '<button type="button" class="brevo-remove-item button-link" title="Supprimer">Supprimer</button>'
                + '</div>'
                + '<input type="text" name="email_menu_items[' + index + '][name]" placeholder="Nom du plat (FR)" class="widefat brevo-item-name" />'
                + '<input type="text" name="email_menu_items[' + index + '][description]" placeholder="Description courte (FR)" class="widefat brevo-item-desc" />'
                + '<input type="text" name="email_menu_items[' + index + '][name_en]" placeholder="Dish name (EN) — optional" class="widefat brevo-item-name-en" />'
                + '<input type="text" name="email_menu_items[' + index + '][description_en]" placeholder="Short description (EN) — optional" class="widefat brevo-item-desc-en" />'
                + '</div>';
            $list.append(template);
            BrevoAdmin.reindexMenuItems();
        },

        removeMenuItem: function(e) {
            e.preventDefault();
            $(this).closest('.brevo-menu-item').slideUp(200, function() {
                $(this).remove();
                BrevoAdmin.reindexMenuItems();
            });
        },

        reindexMenuItems: function() {
            $('#brevo-menu-items-list .brevo-menu-item').each(function(i) {
                $(this).attr('data-index', i);
                $(this).find('.brevo-menu-item-number').text(String(i + 1).padStart(2, '0'));
                $(this).find('.brevo-item-name').attr('name', 'email_menu_items[' + i + '][name]');
                $(this).find('.brevo-item-desc').attr('name', 'email_menu_items[' + i + '][description]');
                $(this).find('.brevo-item-name-en').attr('name', 'email_menu_items[' + i + '][name_en]');
                $(this).find('.brevo-item-desc-en').attr('name', 'email_menu_items[' + i + '][description_en]');
            });
        },

        initSortable: function() {
            if ($.fn.sortable) {
                $('#brevo-menu-items-list').sortable({
                    handle: '.brevo-menu-item-header',
                    placeholder: 'brevo-sortable-placeholder',
                    update: function() {
                        BrevoAdmin.reindexMenuItems();
                    }
                });
            }
        },

        /**
         * Compteur de contacts en temps réel
         */
        updateContactCount: function() {
            var audience = $('input[name="email_audience"]:checked').val() || 'newsletter';
            var $count = $('#brevo-contact-count');
            $count.text('...');

            $.post(ajaxurl, {
                action: 'gastro_starter_brevo_get_contact_count',
                _nonce: brevoAdmin.nonce,
                audience: audience
            }, function(response) {
                if (response.success) {
                    $count.text(response.data.count + ' contacts');
                } else {
                    $count.text('Erreur');
                }
            });
        },

        /**
         * Media picker pour l'image hero
         */
        openMediaPicker: function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Image de l\'email',
                button: { text: 'Utiliser cette image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#email_image_id').val(attachment.id);
                $('#brevo-image-preview').html(
                    '<img src="' + attachment.sizes.medium.url + '" style="max-width: 100%; height: auto; border-radius: 4px;" />'
                );
                $('#brevo-remove-image').show();
            });

            frame.open();
        },

        removeImage: function(e) {
            e.preventDefault();
            $('#email_image_id').val('');
            $('#brevo-image-preview').html('<p class="brevo-muted">L\'image à la une de l\'événement sera utilisée par défaut.</p>');
            $(this).hide();
        },

        /**
         * Media picker pour l'image menu (remplace la liste d'items)
         */
        openMenuImagePicker: function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Image du menu',
                button: { text: 'Utiliser cette image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var sizeUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url
                            : ((attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url);
                $('#email_menu_image_id').val(attachment.id);
                $('#brevo-menu-image-preview').html(
                    '<img src="' + sizeUrl + '" style="max-width: 100%; height: auto; border-radius: 6px;" />'
                );
                $('#brevo-remove-menu-image').show();
            });

            frame.open();
        },

        removeMenuImage: function(e) {
            e.preventDefault();
            $('#email_menu_image_id').val('');
            $('#brevo-menu-image-preview').html('<p class="brevo-muted brevo-center">Aucune image menu sélectionnée.</p>');
            $(this).hide();
        },

        openGalleryPicker: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var targetId = $btn.data('target');
            
            var frame = wp.media({
                title: 'Image de la galerie',
                button: { text: 'Utiliser cette image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.id);
                var sizeUrl = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.sizes.thumbnail.url;
                $('.gallery-preview-' + targetId.replace('email_gallery_', '')).html(
                    '<img src="' + sizeUrl + '" style="max-width: 100%; height: auto; border-radius: 4px;" />'
                );
                $btn.siblings('.brevo-remove-gallery').show();
            });

            frame.open();
        },

        removeGalleryImage: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var targetId = $btn.data('target');
            var side = targetId === 'email_gallery_img1' ? 'gauche' : 'droite';

            $('#' + targetId).val('');
            $('.gallery-preview-' + targetId.replace('email_gallery_', '')).html('<p class="brevo-muted brevo-center">Image (' + side + ')</p>');
            $btn.hide();
        },

        /**
         * Collecte les données du formulaire pour les requêtes AJAX
         */
        collectFormData: function() {
            var data = {
                _nonce: brevoAdmin.nonce,
                post_id: brevoAdmin.postId,
                // FR
                email_subtitle: $('input[name="email_subtitle"]').val(),
                email_accroche: $('textarea[name="email_accroche"]').val(),
                email_places: $('input[name="email_places"]').val(),
                email_citation: $('textarea[name="email_citation"]').val(),
                email_citation_author: $('input[name="email_citation_author"]').val(),
                email_vins_text: $('textarea[name="email_vins_text"]').val(),
                email_vins_price: $('input[name="email_vins_price"]').val(),
                // EN (fallback FR si vides)
                email_title_en: $('input[name="email_title_en"]').val() || '',
                email_subtitle_en: $('input[name="email_subtitle_en"]').val() || '',
                email_accroche_en: $('textarea[name="email_accroche_en"]').val() || '',
                email_places_en: $('input[name="email_places_en"]').val() || '',
                email_citation_en: $('textarea[name="email_citation_en"]').val() || '',
                email_citation_author_en: $('input[name="email_citation_author_en"]').val() || '',
                email_vins_text_en: $('textarea[name="email_vins_text_en"]').val() || '',
                // Images
                email_image_id: $('#email_image_id').val(),
                email_menu_image_id: $('#email_menu_image_id').val() || '',
                email_gallery_img1: $('#email_gallery_img1').val(),
                email_gallery_img2: $('#email_gallery_img2').val(),
                audience: $('input[name="email_audience"]:checked').val() || 'newsletter'
            };

            // Collecter les items du menu (avec variantes EN)
            var menuItems = [];
            $('#brevo-menu-items-list .brevo-menu-item').each(function() {
                var name = $(this).find('.brevo-item-name').val();
                var description = $(this).find('.brevo-item-desc').val();
                var nameEn = $(this).find('.brevo-item-name-en').val();
                var descEn = $(this).find('.brevo-item-desc-en').val();
                if (name) {
                    menuItems.push({
                        name: name,
                        description: description,
                        name_en: nameEn || '',
                        description_en: descEn || ''
                    });
                }
            });
            data.email_menu_items = JSON.stringify(menuItems);

            // Si l'utilisateur a choisi le mode "image", on vide les items côté serveur pour éviter l'ambiguïté
            var menuMode = $('input[name="menu_mode"]:checked').val() || 'items';
            if (menuMode === 'image') {
                // On garde les items en DB mais on les ignore côté rendu (menu_image prioritaire dans generate_email_html)
                // Rien de spécial à faire ici : l'image est déjà envoyée via email_menu_image_id.
            } else {
                // Mode "items" : on vide email_menu_image_id pour que l'image ne soit pas affichée
                data.email_menu_image_id = '';
            }

            return data;
        },

        /**
         * Prévisualisation de l'email
         */
        previewEmail: function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Chargement...');

            var lang = $('input[name="preview_lang"]:checked').val() || 'fr';
            var data = BrevoAdmin.collectFormData();
            data.action = 'gastro_starter_brevo_preview_email';
            data.lang = lang;

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    var langBadge = lang === 'en' ? ' <span style="font-size:11px;background:#1a1a1a;color:#fff;padding:2px 7px;border-radius:3px;vertical-align:middle;">EN</span>' : ' <span style="font-size:11px;background:#e8e3d9;color:#2d2824;padding:2px 7px;border-radius:3px;vertical-align:middle;">FR</span>';
                    // Afficher la modal
                    var modal = '<div id="brevo-modal-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;display:flex;align-items:center;justify-content:center;">'
                        + '<div style="background:#fff;width:660px;max-width:95vw;max-height:90vh;overflow:auto;border-radius:8px;position:relative;">'
                        + '<div style="position:sticky;top:0;background:#fff;padding:12px 20px;border-bottom:1px solid #e8e3d9;display:flex;justify-content:space-between;align-items:center;z-index:1;">'
                        + '<strong>Prévisualisation' + langBadge + ' — ' + response.data.subject + '</strong>'
                        + '<button type="button" id="brevo-modal-close" class="button-link" style="font-size:20px;cursor:pointer;">×</button>'
                        + '</div>'
                        + '<div style="padding:0;">'
                        + '<iframe id="brevo-preview-iframe" style="width:100%;height:75vh;border:none;"></iframe>'
                        + '</div>'
                        + '</div>'
                        + '</div>';

                    $('body').append(modal);

                    // Écrire le HTML dans l'iframe
                    var iframe = document.getElementById('brevo-preview-iframe');
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(response.data.html);
                    doc.close();
                } else {
                    alert('Erreur: ' + response.data.message);
                }
            }).always(function() {
                $btn.prop('disabled', false).text('Prévisualiser');
            });
        },

        closeModal: function(e) {
            if (e.target.id === 'brevo-modal-overlay' || e.target.id === 'brevo-modal-close') {
                $('#brevo-modal-overlay').remove();
            }
        },

        /**
         * Envoi de la newsletter
         */
        sendNewsletter: function(e) {
            e.preventDefault();

            var audience = $('input[name="email_audience"]:checked').val() || 'newsletter';
            var countText = $('#brevo-contact-count').text();
            var label = audience === 'all' ? 'TOUS les contacts' : 'les inscrits newsletter';

            // Vérifier si l'événement est publié
            if (brevoAdmin.postStatus !== 'publish') {
                var modal = '<div id="brevo-draft-modal-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.55);z-index:100000;display:flex;align-items:center;justify-content:center;">'
                    + '<div style="background:#fff;width:480px;max-width:95vw;border-radius:8px;padding:32px;box-shadow:0 8px 40px rgba(0,0,0,0.2);">'
                    + '<p style="font-size:13px;font-weight:700;color:#b5451b;margin:0 0 10px;text-transform:uppercase;letter-spacing:1px;">Événement non publié</p>'
                    + '<p style="font-size:14px;color:#2d2824;margin:0 0 20px;line-height:1.55;">Les clients qui cliqueront sur le bouton <strong>Réserver</strong> dans l\'email arriveront sur une <strong>page introuvable</strong> (404).<br><br>Publiez l\'événement avant d\'envoyer la newsletter.</p>'
                    + '<div style="display:flex;gap:10px;flex-wrap:wrap;">'
                    + '<a href="' + brevoAdmin.publishUrl + '" class="button button-primary" style="text-decoration:none;">Publier l\'événement</a>'
                    + '<button type="button" id="brevo-draft-send-anyway" class="button button-secondary" style="border-color:#ccc;color:#666;">Envoyer quand même</button>'
                    + '<button type="button" id="brevo-draft-cancel" class="button-link" style="margin-left:auto;color:#999;font-size:12px;align-self:center;">Annuler</button>'
                    + '</div>'
                    + '</div>'
                    + '</div>';
                $('body').append(modal);

                $('#brevo-draft-cancel').on('click', function() {
                    $('#brevo-draft-modal-overlay').remove();
                });
                $('#brevo-draft-send-anyway').on('click', function() {
                    $('#brevo-draft-modal-overlay').remove();
                    BrevoAdmin.doSendNewsletter(audience, label, countText);
                });
                return;
            }

            if (!confirm('Vous allez envoyer cette newsletter à ' + label + ' (' + countText + ').\n\nCette action est irréversible. Continuer ?')) {
                return;
            }

            BrevoAdmin.doSendNewsletter(audience, label, countText);
        },

        doSendNewsletter: function(audience, label, countText) {
            if (!confirm('Vous allez envoyer cette newsletter à ' + label + ' (' + countText + ').\n\nCette action est irréversible. Continuer ?')) {
                return;
            }

            var $btn = $('#brevo-send-btn');
            var $status = $('#brevo-send-status');
            $btn.prop('disabled', true).text('Envoi en cours...');
            $status.html('<span style="color: #8b8680;">Envoi en cours, veuillez patienter...</span>');

            var data = BrevoAdmin.collectFormData();
            data.action = 'gastro_starter_brevo_send_email';

            $.post(ajaxurl, data, function(response) {
                if (response.success || (response.data && response.data.sent > 0)) {
                    var msg = response.data.message;
                    var now = new Date();
                    msg += ' — ' + now.toLocaleDateString('fr-FR') + ' à ' + now.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                    $status.html('<span class="brevo-status-success">' + msg + '</span>');
                } else {
                    $status.html('<span class="brevo-status-error">' + (response.data ? response.data.message : 'Erreur inconnue') + '</span>');
                }
            }).fail(function() {
                $status.html('<span class="brevo-status-error">Erreur de connexion au serveur</span>');
            }).always(function() {
                $btn.prop('disabled', false).text('Envoyer la newsletter');
            });
        },

        /**
         * Envoi d'un email de test
         */
        sendTestEmail: function(e) {
            e.preventDefault();
            var testEmail = $('#brevo-test-email').val();
            if (!testEmail) {
                alert('Veuillez saisir une adresse email de test.');
                return;
            }

            var $btn = $(this);
            var $status = $('#brevo-test-status');
            $btn.prop('disabled', true).text('Envoi...');

            var lang = $('input[name="preview_lang"]:checked').val() || 'fr';
            var data = BrevoAdmin.collectFormData();
            data.action = 'gastro_starter_brevo_send_test_email';
            data.test_email = testEmail;
            data.lang = lang;

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    $status.html('<span class="brevo-status-success">' + response.data.message + '</span>');
                } else {
                    $status.html('<span class="brevo-status-error">' + response.data.message + '</span>');
                }
            }).fail(function() {
                $status.html('<span class="brevo-status-error">Erreur réseau</span>');
            }).always(function() {
                var langLabel = $('input[name="preview_lang"]:checked').val() === 'en' ? 'Envoyer le test EN' : 'Envoyer le test';
                $btn.prop('disabled', false).text(langLabel);
            });
        }
    };

    $(document).ready(function() {
        if (typeof brevoAdmin !== 'undefined') {
            BrevoAdmin.init();
        }
    });

})(jQuery);
