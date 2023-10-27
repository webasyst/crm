var CRMSettingsSourceEmail = ( function($) {

    CRMSettingsSourceEmail = function (options) {
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

    CRMSettingsSourceEmail.prototype.initClass = function () {
        var that = this;
        that.initBlockToggles();
        that.initStickyButton();
        that.initSubmit();
        that.initSecureCheckboxes();
        that.initChangeListeners();
        //that.initIButton();
        if (that.source.id > 0) {
            that.initDeleteLink();
        }
        that.initBlocks();
    };

    CRMSettingsSourceEmail.prototype.initSecureCheckboxes = function () {
        var that = this,
            $checkboxes = that.$form.find('.c-secure-checkbox');
        $checkboxes.click(function () {
            var $el = $(this);
            if ($el.is(':checked')) {
                $checkboxes.not($el).attr('checked', false);
            }
        });
    };

    CRMSettingsSourceEmail.prototype.initChangeListeners = function () {
        var that = this,
            $form = that.$form;

        // Watch for input changes
        $form.on('change', 'input,textarea,select', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                CRMSettingsSourceEmail.clearValidateErrors($form);
            }
        });

        $form.on('keyup', 'input:text,input:password,textarea', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                CRMSettingsSourceEmail.clearValidateErrors($form);
            }
        });
    };

    CRMSettingsSourceEmail.prototype.initBlockToggles = function () {
        var that = this,
            $wrapper = that.$wrapper;
        $wrapper.find('.js-crm-block-toggle').change(function () {
            var $el = $(this),
                $field = $el.closest('.field'),
                $block = $field.find('.js-crm-block');
            if ($el.is(':checked')) {
                $block.show();
            } else {
                $block.hide();
            }
        }).trigger('change');

        that.initDealCreateToggle();
    };

    CRMSettingsSourceEmail.prototype.initDealCreateToggle = function() {
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

    CRMSettingsSourceEmail.prototype.initStickyButton = function () {
        var that = this;

        that.$wrapper.find('.crm-form-buttons').sticky({
            fixed_css: { bottom: 0, 'z-index': 100 },
            fixed_class: 'sticky-bottom-shadow',
            showFixed: function(e) {
                e.element.css('min-height', e.element.height());
                e.fixed_clone.empty().append(e.element.children());
            },
            hideFixed: function(e) {
                e.fixed_clone.children().appendTo(e.element);
            },
            updateFixed: function(e, o) {
                this.width(e.element.width());
            }
        });
    };

    CRMSettingsSourceEmail.prototype.setFormChanged = function (status) {
        var that = this;
        status = status !== undefined ? status : true;
        if (status) {
            that.$button.removeClass('green').addClass('yellow');
        } else {
            that.$button.removeClass('yellow').addClass('green');
        }
        that.changed = status;
    };

    CRMSettingsSourceEmail.prototype.clearValidateErrors = function () {
        var that = this,
            $form = that.$form;
        $form.find('.error').removeClass('error');
        $form.find('.crm-errors-block').remove();
    };

    CRMSettingsSourceEmail.prototype.initSubmit = function () {

        var that = this,
            $form = that.$form,
            $buttons = that.$wrapper.find('.crm-form-buttons'),
            $loading = $buttons.find('.crm-loading'),
            $button = that.$button,
            $status = $form.find('.crm-success-status'),
            url = $.crm.app_url + '?module=settingsSource&action=save';

        CRMSettingsSourceEmail.clearValidateErrors($form);

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
                .fail(onFail)
                .always(onAlways);

            function onDone(r) {

                if (!r) {
                    onAlways();
                    onFail();
                    return;
                }

                if (r.status != 'ok') {
                    CRMSettingsSourceEmail.showValidateErrors($form, r.errors || {});
                    return;
                }

                that.setFormChanged(false);
                $status.show().fadeOut(500, function () {
                    if (r.data.source) {
                        $.crm.content.load($.crm.app_url + 'settings/sources/' + r.data.source.id + '/');
                    } else {
                        $.crm.content.reload();
                    }
                });

            }

            function onFail() {
                CRMSettingsSourceEmail.showValidateErrors($form, { '': that.messages['connection_failed'] });
            }

            function onAlways() {
                $loading.hide();
                $button.prop('disabled', false);
                that.submit_xhr = null;
            }

        });
    };

    CRMSettingsSourceEmail.prototype.initDeleteLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-delete-source-link');
        $link.click(function (e) {
            e.preventDefault();
            CRMSettingsSources.deleteSource(that.source.id, {
                messages: that.messages
            });
        });
    };

   /* CRMSettingsSourceEmail.prototype.initIButton = function() {
        var that = this;
        that.$wrapper.find(".js-ibutton").each( function() {
            var $field = $(this);
            !$field.data('iButton') && $field.iButton({
                labelOn : "",
                labelOff : "",
                classContainer: "c-ibutton ibutton-container mini"
            });
        });
    };*/

    CRMSettingsSourceEmail.prototype.initBlocks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $create_deal = $wrapper.find('.js-deal-section'),
            $responsible = $wrapper.find('.js-responsible-section'),
            create_deal_object = $create_deal.data('block_object'),
            responsible_object = $responsible.data('block_object');
        create_deal_object.setResponsibleBlock(responsible_object);
    };

    // STATIC METHODS (Because it using outside current instance)
    CRMSettingsSourceEmail.clearValidateErrors = function ($wrapper) {
        $wrapper.find('.error').removeClass('error');
        $wrapper.find('.crm-errors-block').remove();
    };

    CRMSettingsSourceEmail.showValidateErrors = function ($wrapper, all_errors) {
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

        CRMSettingsSourceEmail.clearValidateErrors($wrapper);

        $.each(plain_errors, function (name, errors) {
            var $error = $('<div class="crm-errors-block"></div>'),
                $field = !name ? $() : $wrapper.find('[name="' + name + '"]');
            $field.addClass('error');
            $.each(errors, function (index, error) {
                $error.append('<em class="errormsg">' + error + '</em>');
            });
            if ($field.length) {
                $field.after($error);
            } else {
                $wrapper.find('.crm-common-errors-block').append($error);
            }
        });
    };

    return CRMSettingsSourceEmail;

})(jQuery);
