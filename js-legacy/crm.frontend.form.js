var crmFrontendForm = function (uid, submit_to_iframe, options) {
    "use strict";

    options.validate_messages = options.validate_messages || {};
    var $ = options.jQuery;

    var $wrapper = $('#' + uid),
        $after_block = $wrapper.find('.crm-after-submit-block'),
        $form = $wrapper.find('form'),
        $inputsUser = $form.find(':input'),
        $inputsReq = $form.find(':input.crm-required-input'),
        $loading = $form.find('.loading'),
        $error = $form.find('.crm-error-msg-block'),
        $error_common = $form.find('.crm-error-common'),
        $captcha_div = $form.find('[data-id="!captcha"]'),
        $captcha_refresh = $form.find('.wa-captcha-refresh'),
        $submit = $form.find(':submit'),
        namespace = 'crm-frontend-form',
        $attachments = $('.crm-attachments', $form),
        $deal_description = $('.crm-deal-description', $form),
        $iframe = $wrapper.find('iframe');

    $wrapper.css({
        'min-height': $wrapper.height()
    });

    var renderErrors = function ($error, errors) {
        $error.show().html($.map(errors, function (error) {
            return '<div class="crm-error-msg">' + error + '</div>'
        }));
    };

    var validate = function($inputs) {
        
        var is_valid = true;

        $('.crm-error-msg').hide();

        $inputs.each(function() {

            var $el = $(this),
                val = $.trim($el.val()),
                $fld = $el.closest('.crm-form-field'),
                fld_id = $fld.data('id');

            $el.removeClass('crm-error');

            if ($el.hasClass('crm-required-input') && val.length <= 0) {
                $el.addClass('crm-error');
                renderErrors($fld.find('.crm-error-msg-block'), [options.validate_messages.required || '']);
                is_valid = false;
                return;
            }
            if ($el.hasClass('crm-email-input')) {
                if (val.length > 0 && !/(.+)@(.+){2,}\.(.+){2,}/.test(val)) {
                    $el.addClass('crm-error');
                    renderErrors($fld.find('.crm-error-msg-block'), [options.validate_messages.email || '']);
                    is_valid = false;
                    return;
                }
            }
            if ($el.attr('name') === 'crm_form[password_confirm]') {
                if ($inputs.filter('input[name="crm_form[password]"]').val() !== $el.val()) {
                    $el.addClass('crm-error');
                    renderErrors($fld.find('.crm-error-msg-block'), [options.validate_messages.passwords_not_match || '']);
                    is_valid = false;
                    return;
                }
            }
            if (fld_id === '!agreement_checkbox' && !$el.is(':checked')) {
                $el.closest('.c-agreement-checkbox-wrapper').addClass('crm-error');
                is_valid = false;
                return;
            }
        });
        return is_valid;
    };

    var clearValidateErrors = function () {
        $form.find('.crm-error-msg').hide().text('');
        $form.find('.crm-error').removeClass('crm-error');
    };

    $inputsUser
        .on('click.' + namespace, clearValidateErrors)
        .on('change.' + namespace, clearValidateErrors);
    
    var normalizeErrorResult = function (errors) {
        var res = [];
        $.each(typeof errors === 'string' ? [errors] : errors, function (k, v) {
            if (typeof v === 'string' && $.trim(v).length > 0) {
                res.push(v);
            }
        });
        return res;
    };

    var showValidateErrors = function (errors) {
        $.each(errors || {}, function (field_id, errors) {
            var $er = $error.filter('[data-uid="' + field_id + '"]');
            if (!$er.length) {
                $er = $error.filter('[data-id="' + field_id + '"]');
            }
            if (!$er.length) {
                $er = $error_common;
            }
            if (!$.isNumeric(field_id)) {
                $.each(field_id.split(','), function (i, fld_id) {
                    fld_id = $.trim(fld_id);
                    if (fld_id.length > 0) {
                        var $fld = $form.find('.crm-form-field[data-uid="' + fld_id + '"]');
                        if (!$fld.length) {
                            $fld = $form.find('.crm-form-field[data-id="' + fld_id + '"]');
                        }
                        if ($fld.data('id') == '!agreement_checkbox') {
                            $fld.find('.c-agreement-checkbox-wrapper').addClass('crm-error');
                        } else {
                            $fld.find(':input').addClass('crm-error');
                        }
                    }
                });
            }

            renderErrors($er, normalizeErrorResult(errors));
        });
    };

    var intiSubmitForm = function () {

        var onDone = function (r) {

            if (r.status != 'ok') {
                var errors = r.errors || {},
                    assignments = r.assignments	|| {};

                showValidateErrors(errors);

                if (errors['!captcha'] && !assignments['captcha_hash']) {
                    refreshCaptcha();
                }

                if (assignments['captcha_hash']) {
                    $captcha_div.html('<input type="hidden" name="crm_form[captcha_hash]" value="' + assignments['captcha_hash'] + '">');
                    return false;
                }

                return;
            }

            if (r.data.hasOwnProperty('redirect')) {
                window.top.location.replace(r.data.redirect);
            } else {
                $form.hide();
                $after_block.height($wrapper.outerHeight());
                $after_block.html(r.data.html).show();
            }
        };

        var onFail = function (error) {
            $error_common.show().text(options.messages.server_error);
            if (error && console && console.error) {
                console.error(error);
            }
        };

        var onAlways = function () {
            $loading.hide();
            $submit.prop('disabled', false);
        };

        var refreshCaptcha = function () {
            $captcha_refresh.trigger('click');
        };

        $form.on('submit', function () {

            clearValidateErrors();

            $error.text('').hide();
            var is_valid = validate($form.find($inputsUser));
            if (!is_valid) {
                return false;
            }

            $loading.show();
            $submit.prop('disabled', true);

            $iframe.one('load', function() {
                var r = $.trim($iframe.contents().find("body").html());
                if (r.length <= 0) {
                    onFail();
                    onAlways();
                    refreshCaptcha();
                    return;
                }
                try {
                    r = $.parseJSON(r);
                    onDone(r);
                    onAlways();
                } catch (e) {
                    onFail(e);
                    onAlways();
                }
            });
            // allow form to submit via its target iframe
            return true;

        });
    };

    intiSubmitForm();
};
