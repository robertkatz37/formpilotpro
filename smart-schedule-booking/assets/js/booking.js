jQuery(function($){
    'use strict';

    $('.sfb-form').each(function(){
        var $form = $(this);

        $form.on('change', 'input[type="checkbox"], input[type="radio"]', function(){
            var name = $(this).attr('name');
            if ($(this).attr('type') === 'radio') {
                $form.find('input[name="' + name + '"]').each(function(){
                    $(this).closest('.sfb-option-label').removeClass('is-checked');
                });
            }
            $(this).closest('.sfb-option-label').toggleClass('is-checked', this.checked);
        });

        $form.on('input change', '.sfb-input, .sfb-select, .sfb-textarea', function(){
            $(this).removeClass('sfb-error');
            var name = $(this).attr('name');
            $form.find('.sfb-field-error[data-field="' + name + '"]').removeClass('visible').text('');
        });

        $form.on('submit', function(e){
            var hasErrors = false;

            $form.find('.sfb-field-error').removeClass('visible').text('');
            $form.find('.sfb-error').removeClass('sfb-error');

            $form.find('[required]').each(function(){
                var $field = $(this);
                var name = $field.attr('name');
                var type = $field.attr('type');
                var value = $.trim($field.val());

                if (type === 'radio') {
                    if (!$form.find('input[name="' + name + '"]:checked').length) {
                        showError($field, name, 'This field is required.');
                        hasErrors = true;
                    }
                    return;
                }

                if (type === 'checkbox') {
                    if (!$form.find('input[name="' + name + '"]:checked').length) {
                        showError($field, name, 'Please select at least one option.');
                        hasErrors = true;
                    }
                    return;
                }

                if (!value) {
                    showError($field, name, 'This field is required.');
                    hasErrors = true;
                    return;
                }

                if ($field.attr('type') === 'email') {
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        showError($field, name, 'Please enter a valid email address.');
                        hasErrors = true;
                    }
                }
            });

            if (hasErrors) {
                e.preventDefault();
                var $first = $form.find('.sfb-field-error.visible').first();
                if ($first.length) {
                    $('html, body').animate({
                        scrollTop: $first.offset().top - 100
                    }, 400);
                }
            }
        });

        function showError($field, name, message) {
            $field.addClass('sfb-error');
            $form.find('.sfb-field-error[data-field="' + name + '"]')
                .text(message)
                .addClass('visible');
        }
    });
});
