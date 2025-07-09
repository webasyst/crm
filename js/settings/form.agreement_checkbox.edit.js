var crmSettingsFormAgreementCheckboxEditDialog = (function ($) {

    var crmSettingsFormAgreementCheckboxEditDialog = function (options) {
        var that = this;

        var construct = function ($wrapper, dialog) {
            // DOM
            that.$wrapper = $wrapper;
            that.$html_label = that.$wrapper.find('.c-html-label-textarea-wrapper textarea');
            that.$field_id = that.$wrapper.find('.c-field-id .value');
            that.$checked_by_default = that.$wrapper.find('.c-field-checked-by-default :checkbox');
            that.$captionplace = that.$wrapper.find('.c-field-captionplace :radio');
            that.$button = that.$wrapper.find('[type=submit]');

            // VARS
            that.id = options.id;   // field id
            that.checked = options.checked;
            that.settingsForm = options.settingsForm;
            that.dialog = dialog;

            that.initClass();
        };


        var prevOnOpen = options.onOpen;
        options.onOpen = function ($wrapper, dialog) {
            prevOnOpen && prevOnOpen($wrapper, dialog);
            construct($wrapper, dialog);
        };

        $.waDialog(options);
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.initClass = function () {
        var that = this,
            field = that.getField(),
            $field_id = that.$field_id;

        $field_id.text(field.name + ' (ID=' + field.id + ')');

        that.initHtmlLabel();
        that.initDefaultChecked();
        that.initSaveButton();

        that.$captionplace.on('click', function () {
            that.setFormChanged();
        }).filter('[value="' + field.captionplace + '"]').prop('checked', true);
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.initHtmlLabel = function () {
        var that = this,
            $html_label = that.$html_label,
            field = that.getField(),
            onChange = function () {
                that.setFormChanged();
            };
        $html_label.val(field.html_label);

        var timer = null;
        $html_label.on('keyup', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                onChange();
            }, 300);
        });

        $html_label.on('change', onChange);
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.initDefaultChecked = function () {
        var that = this,
            field = that.getField(),
            $checked_by_default = that.$checked_by_default;
        $checked_by_default.attr('checked', !!field.default_checked);
        $checked_by_default.on('change', function () {
            that.setFormChanged();
        });
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.getField = function () {
        var that = this,
            field = that.settingsForm.getField(that.id, that.checked);
        field.name = field.name || '';
        field.id = field.id || '';
        field.html_label = field.html_label || '';
        field.default_checked = !field.default_checked || field.default_checked === '0' ? 0 : 1;
        field.html_label_default_href_placeholder = field.html_label_default_href_placeholder || '';
        return field;
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.initSaveButton = function () {
        var that = this,
            $button = that.$button,
            $html_label = that.$html_label,
            $default_by_checked = that.$checked_by_default,
            $captionplace = that.$captionplace;

        $button.click(function (e) {
            e.preventDefault();
            var data = {
                html_label: $html_label.val(),
                default_checked: $default_by_checked.is(':checked') ? 1 : 0,
                captionplace: $captionplace.filter(':checked').val()
            };
            that.settingsForm.updateField(that.id, that.checked, data);
            that.renderField();
            that.dialog.close();
        });
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.renderField = function () {
        var that = this,
            id = that.id,
            checked = that.checked,
            field = that.getField(id, checked),
            $form_fields = that.settingsForm.$form_fields,
            $field = $form_fields.find('.crm-form-field:not(.crm-form-field-template)[data-id="' + id + '"][data-checked="' + checked + '"]');

        if (!field) {
            $field.remove();
            return;
        }

        if (!$field.length) {
            $field = $form_fields.find('.crm-form-field-template.crm-form-field-template[data-id="' + id + '"]').clone();
            $field.removeClass('crm-form-field-template');
            $field.attr('data-checked', checked);
            $field.insertBefore($form_fields.find('.crm-form-field-template:first'));
            $field.show();
        }

        var html_label = field.html_label;
        html_label = html_label.replace(field.html_label_default_href_placeholder, 'javascript:void(0)');

        $field.find('.c-agreement-checkbox-html-label').html(html_label);
        $field.find(':checkbox').attr('checked', !!field.default_checked);
        $field.find('.name').html('');
        $field.removeClass('crm-caption-style-none crm-caption-style-above')
            .addClass('crm-caption-style-' + field.captionplace);
    };

    crmSettingsFormAgreementCheckboxEditDialog.prototype.setFormChanged = function () {
        var that = this;
        that.$button.removeClass('green').addClass('yellow');
    };

    return crmSettingsFormAgreementCheckboxEditDialog;

})(jQuery);
