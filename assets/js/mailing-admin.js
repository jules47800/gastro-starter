(function ($) {
    'use strict';

    var $form = $('#gastro-starter-mailing-form');
    var $hiddenDate = $('#mailing_date_hidden');
    var $manualDate = $('#mailing_date');
    var $kpiGrid = $('#mailing-kpi-grid');
    var $noResults = $('#mailing-no-results');
    var $sendBtn = $('#mailing-send-btn');
    var $previewBtn = $('#mailing-preview-btn');
    var $spinner = $('#mailing-send-spinner');
    var $resultBox = $('#mailing-result');
    var $previewContainer = $('#mailing-preview-container');

    var selectedDate = '';

    // === Variable Tag Insert ===
    $('.var-tag').on('click', function () {
        var varText = $(this).data('var');
        var textarea = document.getElementById('mailing_message');
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        textarea.value = value.substring(0, start) + varText + value.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + varText.length;
        textarea.focus();
    });

    // === Date Chip Selection ===
    $('.date-chip').on('click', function () {
        $('.date-chip').removeClass('active');
        $(this).addClass('active');
        selectedDate = $(this).data('date');
        $hiddenDate.val(selectedDate);
        $manualDate.val('');
        fetchDateInfo();
    });

    // === Manual Date Toggle ===
    $('#toggle-manual-date').on('click', function () {
        $('#manual-date-field').slideToggle(200);
    });

    $manualDate.on('change', function () {
        var val = $(this).val();
        if (val) {
            $('.date-chip').removeClass('active');
            selectedDate = val;
            $hiddenDate.val(val);
            fetchDateInfo();
        }
    });

    // === Status Checkboxes ===
    $('input[name="mailing_statuses[]"]').on('change', function () {
        var $label = $(this).closest('.checkbox-label');
        $label.toggleClass('checked', $(this).is(':checked'));
        if (selectedDate) {
            fetchDateInfo();
        }
    });

    // === Fetch Date Info ===
    function getSelectedStatuses() {
        var statuses = [];
        $('input[name="mailing_statuses[]"]:checked').each(function () {
            statuses.push($(this).val());
        });
        return statuses;
    }

    function fetchDateInfo() {
        var statuses = getSelectedStatuses();

        if (!selectedDate || statuses.length === 0) {
            $kpiGrid.hide();
            $noResults.hide();
            $sendBtn.prop('disabled', true);
            return;
        }

        $.post(gastro_starter_mailing.ajax_url, {
            action: 'gastro_starter_mailing_get_date_info',
            _nonce: gastro_starter_mailing.nonce,
            date: selectedDate,
            statuses: statuses
        }, function (response) {
            if (response.success && response.data.total_emails > 0) {
                $('#kpi-reservations').text(response.data.total_reservations);
                $('#kpi-emails').text(response.data.total_emails);
                $('#kpi-couverts').text(response.data.total_people);
                $kpiGrid.show().find('.kpi-box').addClass('has-data');
                $noResults.hide();
                $sendBtn.prop('disabled', false);
            } else {
                $kpiGrid.hide();
                $noResults.show();
                $sendBtn.prop('disabled', true);
            }
        }).fail(function () {
            $kpiGrid.hide();
            $noResults.hide();
            $sendBtn.prop('disabled', true);
        });
    }

    // === Preview ===
    $previewBtn.on('click', function () {
        var subject = $('#mailing_subject').val();
        var message = $('#mailing_message').val();

        if (!subject || !message) {
            alert(gastro_starter_mailing.i18n.fill_fields);
            return;
        }

        $.post(gastro_starter_mailing.ajax_url, {
            action: 'gastro_starter_mailing_preview',
            _nonce: gastro_starter_mailing.nonce,
            subject: subject,
            message: message
        }, function (response) {
            if (response.success) {
                var iframe = document.getElementById('mailing-preview-frame');
                var doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.open();
                doc.write(response.data.html);
                doc.close();
                $previewContainer.show();
                $previewContainer[0].scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // === Send ===
    $form.on('submit', function (e) {
        e.preventDefault();

        var date = $hiddenDate.val();
        var subject = $('#mailing_subject').val();
        var message = $('#mailing_message').val();
        var statuses = getSelectedStatuses();

        if (!date || !subject || !message || statuses.length === 0) {
            alert(gastro_starter_mailing.i18n.fill_fields);
            return;
        }

        var emailCount = $('#kpi-emails').text();
        var confirmMsg = gastro_starter_mailing.i18n.confirm_send.replace('%d', emailCount);
        if (!confirm(confirmMsg)) {
            return;
        }

        $sendBtn.prop('disabled', true);
        $spinner.addClass('is-active');
        $resultBox.hide();

        $.post(gastro_starter_mailing.ajax_url, {
            action: 'gastro_starter_mailing_send',
            _nonce: gastro_starter_mailing.nonce,
            date: date,
            subject: subject,
            message: message,
            statuses: statuses
        }, function (response) {
            $spinner.removeClass('is-active');

            if (response.success) {
                var cls = response.data.errors > 0 ? 'mailing-notice-warning' : 'mailing-notice-success';
                $resultBox.html(
                    '<div class="mailing-notice ' + cls + '">' +
                    '<strong>' + response.data.message + '</strong>' +
                    '</div>'
                ).show();
                $resultBox[0].scrollIntoView({ behavior: 'smooth' });
            } else {
                $resultBox.html(
                    '<div class="mailing-notice mailing-notice-error">' +
                    '<strong>' + (response.data && response.data.message ? response.data.message : gastro_starter_mailing.i18n.error) + '</strong>' +
                    '</div>'
                ).show();
                $sendBtn.prop('disabled', false);
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            $sendBtn.prop('disabled', false);
            $resultBox.html(
                '<div class="mailing-notice mailing-notice-error">' + gastro_starter_mailing.i18n.error + '</div>'
            ).show();
        });
    });

})(jQuery);
