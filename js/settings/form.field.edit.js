var crmSettingsFormFieldEditDialog = (function ($) {

    var crmSettingsFormFieldEditDialog = function (options) {
        var that = this;

        var construct = function ($wrapper, dialog) {
            // DOM
            that.$wrapper = $wrapper;
            that.$form = that.$wrapper.find('form');
            that.$button = that.$form.find('[type=submit]');

            // VARS
            that.id = options.id;   // field id
            that.checked = options.checked; // field checked
            that.settingsForm = options.settingsForm;
            that.dialog = dialog;

            that.initClass();
        };

        var prevOnClose = options.onClose;
        options.onClose = function ($wrapper, dialog) {
            prevOnClose && prevOnClose($wrapper, dialog);
        };

        var prevOnOpen = options.onOpen;
        options.onOpen = function ($wrapper, dialog) {
            prevOnOpen && prevOnOpen($wrapper, dialog);
            construct($wrapper, dialog);
        };

        $.waDialog(options);
    };

    crmSettingsFormFieldEditDialog.prototype.initClass = function () {
        var that = this,
            $dialog = that.dialog.$block,
            field = that.settingsForm.getField(that.id, that.checked);

        $.each(field, function (key, val) {
            if (key === 'id') {
                $('.crm-field-id', $dialog).find('.value').text(field.name + ' (ID=' + val + ')');
                if (val === '!captcha') {
                    $('.js-captcha-settings', $dialog).removeClass('hidden');
                    if (that.settingsForm.captcha_is_invisible) {
                        $('.crm-field-caption', $dialog).addClass('hidden');
                        $('.crm-field-captionplace', $dialog).addClass('hidden');
                    }
                }
            } else if (key !== 'name') {
                var name = '.crm-field-' + key;
                var $input = $(name, $dialog).find('.value').find(':input');
                if ($input.is(':checkbox')) {
                    $input.prop('checked', !!val);
                } else if ($input.is(':radio')) {
                    $input.filter('[value="' + val + '"]').prop('checked', true);
                } else {
                    $input.val(val);
                }
            }
        });

        var disableField = function(field_id) {
            $('.crm-field-' + field_id, $dialog).hide().find(':input').attr('disabled', true);
        };
        var enableField = function(field_id) {
            $('.crm-field-' + field_id, $dialog).show().find(':input').attr('disabled', false);
        };

        // composite subfield caption place
        if (field.type === 'Composite' || field.type === 'Address') {
            enableField('subfield_captionplace');
        } else {
            disableField('subfield_captionplace');
        }

        // placeholder: enable/disable?
        if (field.placeholder_need) {
            enableField('placeholder');
        } else {
            disableField('placeholder');
        }

        // confirm placeholder: enable/disabled?
        if (field.placeholder_need && field.id === 'password') {
            enableField('without_confirm');
            enableField('placeholder_confirm');
        } else {
            disableField('without_confirm');
            disableField('placeholder_confirm');
        }

        if (field.required_always) {
            disableField('required');
        } else {
            enableField('required');
        }

        if (field.id == '!deal_description') {
            enableField('redactor');
        } else {
            disableField('redactor');
        }

        that.bindEvents();
    };

    crmSettingsFormFieldEditDialog.prototype.bindEvents = function () {
        var that = this,
            $form = that.$form,
            field = that.settingsForm.getField(that.id, that.checked);

        $form.submit(function (e) {
            e.preventDefault();
            $form.find(':input[name*=params]:not(:disabled)').each(function() {
                var $item = $(this),
                    name = $item.attr('name').replace('params[', '').replace(']', ''),
                    value = $item.val();
                if ($item.is(':checkbox')) {
                    field[name] = $item.is(':checked') ? true : false;
                } else if ($item.is(':radio')) {
                    if ($item.is(':checked')) {
                        field[name] = value;
                    }
                } else {
                    field[name] = value;
                }
            });
            that.settingsForm.updateField(that.id, that.checked, field);
            that.settingsForm.renderField(that.id, that.checked);
            that.dialog.close();
        });

        // Watch for input changes
        that.$form.on('change', 'input,textarea,select', function() {
            that.setFormChanged();
        });
        that.$form.on('keyup', 'input:text,textarea', function() {
            that.setFormChanged();
        });

    };

    crmSettingsFormFieldEditDialog.prototype.setFormChanged = function () {
        var that = this;
        that.$button.addClass('yellow');
    };

    return crmSettingsFormFieldEditDialog;

})(jQuery);
