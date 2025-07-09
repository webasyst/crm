var crmSettingsFormButtonEditDialog = (function ($) {

    var crmSettingsFormButtonEditDialog = function (options) {
        var that = this;

        var construct = function ($wrapper, dialog) {
            // DOM
            that.$wrapper = $wrapper;
            that.$form = that.$wrapper.find('form');
            that.$button = that.$form.find('[type=submit]');

            // VARS
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

    crmSettingsFormButtonEditDialog.prototype.initClass = function () {
        var that = this,
            $dialog = that.dialog.$block,
            button = that.settingsForm.getButton();

        $.each(button, function (key, val) {
            var $input = $('.crm-field-' + key, $dialog).find('.value').find(':input:not([type=hidden])');
            if ($input.is(':checkbox')) {
                $input.prop('checked', $input.val() == val);
            } else {
                $input.val(val);
            }
        });

        that.bindEvents();
    };

    crmSettingsFormButtonEditDialog.prototype.bindEvents = function () {
        var that = this,
            $form = that.$form,
            button = that.settingsForm.getButton();

        $form.find('.crm-submit-width-switch').waSwitch();

        $form.submit(function (e) {
            e.preventDefault();
            $form.find(':input[name*=params]:not(:disabled):not([type=hidden])').each(function() {
                var $item = $(this),
                    name = $item.attr('name').replace('params[', '').replace(']', ''),
                    value = $item.val();
                if ($item.is(':checkbox')) {
                    button[name] = $item.is(':checked') ? $item.val() : $item.siblings('[type=hidden]').val() || '';
                } else {
                    button[name] = value;
                }
            });
            that.settingsForm.updateButton(button);
            that.settingsForm.renderButton();
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

    crmSettingsFormButtonEditDialog.prototype.setFormChanged = function () {
        var that = this;
        that.$button.addClass('yellow');
    };

    return crmSettingsFormButtonEditDialog;

})(jQuery);
