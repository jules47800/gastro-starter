(function($){
    $(function(){
        var $form = $('.gastro-starter-voucher-form');
        if(!$form.length) return;
        
        // Le champ amount est maintenant un <select> au lieu d'un <input>
        var $amount = $form.find('select[name="amount"]');
        
        function renderTotal(){
            var v = parseInt(($amount.val()||'').toString(),10);
            var txt = isNaN(v) || v <= 0 ? '—' : (v.toFixed(0) + ' €');
            $form.closest('.voucher-card').find('.total-amount').text(txt);
            
            // Animation du total
            var $totalEl = $form.closest('.voucher-card').find('.total-amount');
            if(txt !== '—') {
                $totalEl.css('transform', 'scale(1.1)');
                setTimeout(function(){
                    $totalEl.css('transform', 'scale(1)');
                }, 200);
            }
        }
        
        // Mise à jour du total quand on change la sélection
        $amount.on('change', function(){
            renderTotal();
            
            // Effet de focus sur le montant sélectionné
            $(this).addClass('selected');
        });
        
        // Initialiser le total
        renderTotal();
        
        // Animation des champs au focus
        $form.find('input, select, textarea').on('focus', function(){
            $(this).closest('.field').addClass('field-focused');
        }).on('blur', function(){
            $(this).closest('.field').removeClass('field-focused');
        });
        
        // Validation en temps réel de l'email
        $form.find('input[type="email"]').on('blur', function(){
            var email = $(this).val();
            var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if(email && !isValid) {
                $(this).addClass('error-field');
            } else {
                $(this).removeClass('error-field');
            }
        });
        
        // Compteur de caractères pour le message
        var $message = $form.find('textarea[name="message"]');
        if($message.length) {
            var maxLength = 500;
            var $counter = $('<div class="char-counter"></div>');
            $message.after($counter);
            
            $message.on('input', function(){
                var length = $(this).val().length;
                $counter.text(length + ' / ' + maxLength + ' caractères');
                
                if(length > maxLength) {
                    $(this).val($(this).val().substring(0, maxLength));
                    $counter.addClass('limit-reached');
                } else {
                    $counter.removeClass('limit-reached');
                }
            });
        }
    });
})(jQuery);



