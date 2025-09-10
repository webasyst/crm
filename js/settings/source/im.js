var CRMSettingsSourceIm = ( function($) {

    CRMSettingsSourceIm = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('[type=submit]');

        // VARS
        that.source = options.source || {};
        that.messages = options.messages || {};

        // DYNAMIC VARS
        that.changed = false;
        that.submit_xhr = null;

        // INIT
        that.initClass();
    };

    CRMSettingsSourceIm.prototype.initClass = function () {
        var that = this;
        that.initBlockToggles();
        that.initSubmit();
        that.initChangeListeners();
        //that.initIButton();
        if (that.source.id > 0) {
            that.initDeleteLink();
        }
    };

    CRMSettingsSourceIm.prototype.initChangeListeners = function () {
        var that = this,
            $form = that.$form;

        // Watch for input changes
        $form.on('change', 'input,textarea,select', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                CRMSettingsSourceIm.clearValidateErrors($form);
            }
        });

        $form.on('keyup', 'input:text,input:password,textarea', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                CRMSettingsSourceIm.clearValidateErrors($form);
            }
        });
    };

    CRMSettingsSourceIm.prototype.initBlockToggles = function () {
        var that = this,
            $wrapper = that.$wrapper;
        /*$wrapper.find('.js-crm-block-toggle').change(function () {
            var $el = $(this),
                $field = $el.closest('.field'),
                $block = $field.find('.js-crm-block');
            if ($el.is(':checked')) {
                $block.show();
            } else {
                $block.hide();
            }
        }).trigger('change');*/

        that.initVerifyToggle();
        that.initDealCreateToggle();
    };

    CRMSettingsSourceIm.prototype.initVerifyToggle = function() {
        var that = this,
        $switches = that.$wrapper.find('.js-crm-value-switch .switch');
        $switches.each(function(index, el){
            $(el).waSwitch({
                ready: function (wa_switch) {
                    let $label = wa_switch.$wrapper.siblings('label');
                    let $input_hidden = wa_switch.$wrapper.siblings('input');
                    let $input = wa_switch.$wrapper.find('input');
                    wa_switch.$label = $label;
                    wa_switch.$input = $input;
                    wa_switch.$input_hidden = $input_hidden;
                    wa_switch.active_text = $label.data('active-text');
                    wa_switch.inactive_text = $label.data('inactive-text');
                },
                change: function(active, wa_switch) {
                    var $field = wa_switch.$wrapper.closest('.fields-group'),
                        $block = $field.find('.params');

                    if (active) {
                        $block.slideDown();
                        wa_switch.$input_hidden.val(1);
                        wa_switch.$input.val(1);
                        wa_switch.$label.text(wa_switch.active_text);
                    }
                    else {
                        $block.slideUp();
                        wa_switch.$input_hidden.val(0);
                        wa_switch.$input.val(0);
                        wa_switch.$label.text(wa_switch.inactive_text);
                    }
                }
            });
        });
    }

    CRMSettingsSourceIm.prototype.initDealCreateToggle = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $block = $wrapper.find('.crm-source-settings-block-create_deal');
        $block.on('toggled', function (e, checked) {
            var $responsible = $wrapper.find('.js-responsible-section'),
                responsible_object = $responsible.data('block_object');
            if (checked) {
                responsible_object.reload({});
            } else {
                responsible_object.reload({ funnel_id: 0 });
            }
        });
    };


    CRMSettingsSourceIm.prototype.setFormChanged = function (status) {
        var that = this;
        status = status !== undefined ? status : true;
        if (status) {
            that.$button.addClass('yellow');
        } else {
            that.$button.removeClass('yellow');
        }
        that.changed = status;
    };

    CRMSettingsSourceIm.prototype.clearValidateErrors = function () {
        var that = this,
            $form = that.$form;
        $form.find('.error').removeClass('error');
        $form.find('.crm-errors-block').remove();
    };

    CRMSettingsSourceIm.prototype.initSubmit = function () {

        var that = this,
            $form = that.$form,
            $buttons = that.$wrapper.find('.c-footer-actions'),
            $loading = $buttons.find('.crm-loading'),
            $button = that.$button,
            $status = $form.find('.crm-success-status'),
            url = $.crm.app_url + '?module=settingsSource&action=save';

        CRMSettingsSourceIm.clearValidateErrors($form);

        $form.submit(function (e) {
            e.preventDefault();

            var post_data = $form.serializeArray();
            post_data.push({
                name: 'id',
                value: that.source.id > 0 ? that.source.id : that.source.provider
            });

            $loading.show();
            $button.prop('disabled', true);

            that.submit_xhr && that.submit_xhr.abort();
            that.submit_xhr = $.post(url, post_data, null, 'json')
                .done(onDone)
                .always(onAlways);

            function onDone(r) {
                if (r.status === 'ok') {
                    that.setFormChanged(false);
                    $status.show().fadeOut(500, function () {
                        if (r.data.source) {
                            $.crm.content.load($.crm.app_url + 'settings/sources/' + r.data.source.id + '/');
                        } else {
                            $.crm.content.reload();
                        }
                    });
                    return;
                }
                CRMSettingsSourceIm.showValidateErrors($form, r.errors || {});
            }

            function onAlways() {
                $loading.hide();
                $button.prop('disabled', false);
                that.submit_xhr = null;
            }

        });
    };

    CRMSettingsSourceIm.prototype.initDeleteLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-delete-source-link');
        $link.click(function (e) {
            e.preventDefault();
            CRMSettingsSources.deleteSource(that.source.id, {
                messages: that.messages,
                type: 'im'
            });
        });
    };

    CRMSettingsSourceIm.prototype.initIButton = function() {
        var that = this;
        /*that.$wrapper.find(".js-ibutton").each( function() {
            var $field = $(this);
            !$field.data('iButton') && $field.iButton({
                labelOn : "",
                labelOff : "",
                classContainer: "c-ibutton ibutton-container mini"
            });
        });*/
        that.$wrapper.find(".c-checkbox .js-ibutton").each( function() {
            var $checkbox = $(this),
            $checkbox_parent = $checkbox.parent();
            $switch_wrapper = $('<span class="switch smaller"></span>');
            $switch_wrapper.prependTo($checkbox_parent);
            $checkbox.clone().appendTo($switch_wrapper);
            $checkbox.remove();

            //$switch_wrapper.addClass('switch');
            that.$switch =  $switch_wrapper.waSwitch({

                ready: function (wa_switch) {
                    let $label = wa_switch.$wrapper.siblings('label');
                    wa_switch.$label = $label;
                    wa_switch.inactive_text = $label.eq(0).text();
                    wa_switch.active_text = $label.eq(1).text();
                    $label.eq(0).removeClass('gray');
                    $label.eq(1).hide();
                },
                change: function(active, wa_switch) {

                    if (active) {
                    wa_switch.$label.text(wa_switch.active_text);
                    }
                    else {
                     wa_switch.$label.text(wa_switch.inactive_text);
                    }
                }
            });
          //  that.initIButton($(this));
        });
    };

    // STATIC METHODS (Because it using outside current instance)
    CRMSettingsSourceIm.clearValidateErrors = function ($wrapper) {
        $wrapper.find('.state-error').removeClass('state-error');
        $wrapper.find('.crm-errors-block').remove();
    };

    CRMSettingsSourceIm.showValidateErrors = function ($wrapper, all_errors) {
        var plain_errors = {};

        $.each(all_errors || {}, function (name, errors) {
            if (name === 'params') {
                $.each(errors, function (name, error) {
                    plain_errors["source[params][" + name + "]"] = $.isArray(error) ? error : [error];
                });
            } else {
                plain_errors[!name ? "" : "source[" + name + "]"] = $.isArray(errors) ? errors : [errors];
            }
        });

        CRMSettingsSourceIm.clearValidateErrors($wrapper);

        $.each(plain_errors, function (name, errors) {
            var $error = $('<div class="crm-errors-block"></div>'),
                $field = !name ? $() : $wrapper.find('[name="' + name + '"]');
            $field.addClass('state-error');
            $.each(errors, function (index, error) {
                $error.append('<span class="errormsg error">' + error + '</span>');
            });
            if ($field.length) {
                $field.after($error);
            } else {
                $wrapper.find('.crm-common-errors-block').append($error);
            }
        });
    };

    return CRMSettingsSourceIm;

})(jQuery);
