/* global cfb, jQuery */
(function ($) {
    'use strict';

    $(document).on('submit', '.cfb-form', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $btn    = $form.find('.cfb-submit-btn');
        var $spin   = $form.find('.cfb-spinner');
        var $msg    = $form.find('.cfb-form-message');
        var formId  = $form.data('form-id');

        // Clear previous errors
        $form.find('.cfb-field-error').text('');
        $form.find('.cfb-field-wrap').removeClass('cfb-has-error');
        $msg.hide().removeClass('cfb-success cfb-error');

        // Client-side required validation
        var hasError = false;
        $form.find('[required]').each(function () {
            if (!$(this).val()) {
                var $wrap = $(this).closest('.cfb-field-wrap');
                $wrap.addClass('cfb-has-error');
                $wrap.find('.cfb-field-error').text($(this).closest('.cfb-field-wrap').find('.cfb-label').text().replace('*', '').trim() + ' é obrigatório.');
                hasError = true;
            }
        });
        if (hasError) return;

        $btn.prop('disabled', true);
        $spin.show();

        var data = $form.serialize();
        data += '&action=cfb_submit&nonce=' + cfb.nonce + '&form_id=' + formId;

        $.post(cfb.ajax_url, data, function (res) {
            $btn.prop('disabled', false);
            $spin.hide();

            if (res.success) {
                $form.find('.cfb-fields-grid, .cfb-submit-wrap').hide();
                $msg.addClass('cfb-success').html(res.data.message).show();

                if (res.data.redirect_url) {
                    setTimeout(function () {
                        window.location.href = res.data.redirect_url;
                    }, 1500);
                }
            } else {
                $msg.addClass('cfb-error').text(res.data.message || 'Erro ao enviar.').show();

                if (res.data.errors) {
                    $.each(res.data.errors, function (name, msg) {
                        var $wrap = $form.find('[data-field="' + name + '"]');
                        $wrap.addClass('cfb-has-error');
                        $wrap.find('.cfb-field-error').text(msg);
                    });
                }
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spin.hide();
            $msg.addClass('cfb-error').text('Erro de conexão. Tente novamente.').show();
        });
    });

    // Copy shortcode on click
    $(document).on('click', '.cfb-shortcode', function () {
        var text = $(this).text().trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        }
    });

}(jQuery));
