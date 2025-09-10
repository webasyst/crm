var crmSettingsFormParagraphEditDialog = (function ($) {

    var crmSettingsFormParagraphEditDialog = function (options) {
        var that = this;

        var construct = function ($wrapper, dialog) {
            // DOM
            that.$wrapper = $wrapper;
            that.$textarea = that.$wrapper.find('.crm-paragraph-textarea');
            that.$captionplace = that.$wrapper.find('.c-field-captionplace :radio');
            that.$button = that.$wrapper.find('[type=submit]');

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

        $.waDialog(options);
    };

    crmSettingsFormParagraphEditDialog.prototype.initClass = function () {
        var that = this,
            $textarea = that.$textarea,
            field = that.settingsForm.getField(that.id, that.checked);

        console.log(field);

        $textarea.val(field && field.text || '');
        //
        that.initWYSIWYG();
        //
        that.initSaveButton();

        that.$captionplace.on('click', function () {
            that.setFormChanged();
        }).filter('[value="' + field.captionplace + '"]').prop('checked', true);
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
            $textarea = that.$textarea,
            $captionplace = that.$captionplace;

        $button.click(function (e) {
            e.preventDefault();
            that.settingsForm.updateField(that.id, that.checked, { 
                text: $textarea.val(), 
                captionplace: $captionplace.filter(':checked').val()
            });
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
