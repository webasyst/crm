var crmSettingsFormParagraphEditDialog = (function ($) {

    var crmSettingsFormParagraphEditDialog = function (options) {
        var that = this;

        var construct = function ($wrapper, dialog) {
            // DOM
            that.$wrapper = $wrapper;
            that.$textarea = that.$wrapper.find('.crm-paragraph-textarea');
            that.$button = that.$wrapper.find('[type=button]');

            // VARS
            that.id = options.id;   // field id
            that.checked = options.checked;
            that.settingsForm = options.settingsForm;
            that.dialog = dialog;

            that.initClass();
        };

        var prevOnClose = options.onClose;
        options.onClose = function ($wrapper, dialog) {
            prevOnClose && prevOnClose($wrapper, dialog);
            $('.redactor-dropdown').remove();
        };

        var prevOnOpen = options.onOpen;
        options.onOpen = function ($wrapper, dialog) {
            prevOnOpen && prevOnOpen($wrapper, dialog);
            construct($wrapper, dialog);
        };

        new CRMDialog(options);
    };

    crmSettingsFormParagraphEditDialog.prototype.initClass = function () {
        var that = this,
            $textarea = that.$textarea,
            field = that.settingsForm.getField(that.id, that.checked);

        $textarea.val(field && field.text || '');
        //
        that.initWYSIWYG();
        //
        that.initSaveButton();
    };


    crmSettingsFormParagraphEditDialog.prototype.initWYSIWYG = function () {
        var that = this,
            $textarea = that.$textarea,
            field = that.settingsForm.getField(that.id, that.checked);

        $.crm.initWYSIWYG($textarea, {
            focus: true,
            //buttons:!!! check dev
            changeCallback: function () {
                that.setFormChanged();
            }
        });
        $textarea.val(field.text || '');
        $textarea.redactor('code.set', field.text || '');
    };

    crmSettingsFormParagraphEditDialog.prototype.initSaveButton = function () {
        var that = this,
            $button = that.$button,
            $textarea = that.$textarea;

        $button.click(function (e) {
            e.preventDefault();
            that.settingsForm.updateField(that.id, that.checked, { text: $textarea.val() });
            that.settingsForm.renderField(that.id, that.checked);
            that.dialog.close();
        });

    };

    crmSettingsFormParagraphEditDialog.prototype.setFormChanged = function () {
        var that = this;
        that.$button.removeClass('green').addClass('yellow');
    };

    return crmSettingsFormParagraphEditDialog;

})(jQuery);
