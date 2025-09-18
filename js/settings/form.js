var crmSettingsForm = (function ($) {

    crmSettingsForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('.crm-form-settings-form');
        that.$add_field_dropdown = options.$add_field_dropdown;
        that.$form_fields = that.$wrapper.find('.crm-form-fields');
        that.$form_available_fields = that.$wrapper.find('.crm-available-fields');
        that.$confirmation_checkbox = that.$wrapper.find('.crm-confirmation-checkbox');
        that.$confirmation_enable_text = that.$wrapper.find('.crm-confirmation-enable-text');
        that.$email_confirm_block = that.$wrapper.find('.crm-form-email-confirm-block');
        that.$back_button = that.$wrapper.find('.crm-form-header .js-back-button');


        that.$create_deal_block_wrapper = that.$wrapper.find('.crm-form-settings-block-form_create_deal');


        that.$button = that.$form.find('[type=submit]');
        that.$delete_link = that.$wrapper.find('.crm-delete-form-link');
        that.$copy = that.$wrapper.find('.js-copy');
        that.$external_code = that.$wrapper.find('.js-external-code');
        that.$widget_params = that.$wrapper.find('.js-widget-params');
        that.$widget_manual_code = that.$wrapper.find('.js-widget-manual-code');

        that.$available_deal_related_field = getDealRelatedFields();

        // DOM :: Dialog templates
        that.$edit_dialog_template = that.$wrapper.find('template.crm-settings-form-edit-field-wrapper');
        that.$checkbox_edit_dialog_template = that.$wrapper.find('template.crm-settings-form-edit-checkbox-wrapper');
        //that.$composite_edit_dialog_template = that.$wrapper.find('template.crm-settings-form-edit-composite-field-wrapper');
        that.$paragraph_edit_dialog_template = that.$wrapper.find('template.crm-settings-form-paragraph-edit-wrapper');
        that.$agreement_checkbox_edit_dialog_tempalte = that.$wrapper.find('template.c-settings-form-agreement-checkbox-edit-wrapper');
        that.$button_edit_dialog_template = that.$wrapper.find('template.crm-settings-form-button-edit-wrapper');

        // VARS :: REFERRER
        that.referrer = $.crm.storage.get('crm/settings/web_form_referrer');
        $.crm.storage.del('crm/settings/web_form_referrer');

        // VARS
        that.form = options.form || {};
        that.form.params = that.form.params || {};
        that.form.params.fields = that.form.params.fields || [];
        that.available_fields = options.available_fields || {};
        that.lang = options.lang;
        that.messages = options.messages || {};
        that.captcha_is_invisible = options.captcha_is_invisible || false;
        that.default_checked_fields = getDefaultCheckedFields();
        that.sortable_is_active = false;
		that.csrf = options.csrf || '';

        // DYNAMIC VARS
        that.changed = false;
        that.submit_xhr = null;

        // INIT
        that.initClass();

        // SOME INNER HELPERS

        function getDealRelatedFields() {
            return that.$form_available_fields.find('.crm-form-field[data-form-field-type="deal"]')
                .add(that.$form_available_fields.find('.crm-form-field[data-id="!deal_description"]'))
                .add(that.$form_available_fields.find('.crm-form-field[data-id="!deal_attachments"]'));
        }

        function getDefaultCheckedFields() {
            var default_checked_fields = options.default_checked_fields || [];
            if (that.form.id <= 0 && that.referrer === 'settings/sources') {
                var pos = default_checked_fields.indexOf('password');
                if (pos >= 0) {
                    default_checked_fields[pos] = '!deal_description';
                } else {
                    default_checked_fields.push('!deal_description');
                }
            }
            return default_checked_fields;
        }
    };

    crmSettingsForm.prototype.initClass = function () {
        var that = this;

        // this method must be before iButton initiation
        that.initDealCreateCheckbox();

        // take into account just on or off redactor mode
        that.renderDealDescription();

        that.bindEvents();
        that.initIButtons();
        //that.initAddNewFieldLink();
        that.initAvailableFields();
        that.initDeleteFieldLinks();
        // that.initPreviewButton(); // TODO: delete soon
        that.initFormWidthInput();
        that.initFields();
        that.initSubmit();
        that.initDeleteLink();
        that.initSortable();
        that.initTextEditor();
        that.initCopySmartyHelper();
        that.initExternalCode();
        that.initMessagesBlock();
        that.initCancelLink();
        that.initBlocks();
        that.initMarginsSettings();
        that.initViewModeToggler();
        that.initSubmitPlacement();
        that.initColorSection();
        that.initIconSection();
        that.initFrontendLinkDropdown();
        //that.initBackButton();
    };

    /*crmSettingsForm.prototype.initBackButton = function () {
        var that = this;
        that.$back_button.on('click', function (e) {
            e.preventDefault();
            history.back();
        })
    }*/

    crmSettingsForm.prototype.renderDealDescription = function (field) {
        var that = this,
            $deal_description = that.$form_fields.find('[data-id="!deal_description"]'),
            $textarea = $deal_description.find('textarea');
        if (!field) {
            field = that.getField('!deal_description');
        }
        if (!field || field.checked <= 0 || !$textarea.length) {
            return;
        }
        if (field.redactor) {
            if (!$textarea.data('redactor')) {
                $.crm.initWYSIWYG($textarea, {
                    focus: false,
                    buttons: ['bold', 'italic', 'underline', 'link'],
                    plugins: [],
                    maxHeight: 100,
                    minHeight: 100
                });
            } else {
                var editor = $textarea.redactor('core.editor');
                if (editor && editor.length > 0) {
                    editor[0].attr('placeholder', $textarea.attr('placeholder'));
                }
            }
        } else {
            if ($textarea.data('redactor')) {
                $textarea.redactor("core.destroy");
            }
            $textarea.show();
        }
    };


    crmSettingsForm.prototype.bindEvents = function () {
        var that = this;

        // Watch for input changes
        that.$form.on('change', 'input,textarea,select', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                that.clearValidateErrors();
            }
        });
        that.$form.on('keyup', 'input:text,textarea', function(e) {
            that.setFormChanged();

            if (!e.isTrigger) {
                that.clearValidateErrors();
            }
        });

        that.$form.find('.js-show-external-code').click(function () {
            that.$external_code.slideToggle();
            $(this).find('svg').toggleClass('fa-caret-down fa-caret-up');
        });

        that.$form.find('.js-widget-container').click(function () {
            console.log($(this).val(), that.$widget_manual_code);
            if ($(this).val()) {
                that.$widget_manual_code.slideUp();
                that.$widget_params.slideDown();
            } else {
                that.$widget_params.slideUp();
                that.$widget_manual_code.slideDown();
            }
        });

        that.$widget_params.find('.js-widget-add-path').click(function (event) {
            event.preventDefault();
            const $this = $(event.target);
            const $row = $this.closest('tr');
            const domain = $row.data('domain');
            const $path_wrapper = $('<div class="flexbox middle space-0 js-widget-path-wrapper"></div>');
            const $input = $('<input type="text" class="js-widget-path" data-domain="' + domain + '" value="" placeholder="/*">');
            const $del_button = $('<button class="js-widget-del-path circle light-gray smallest"><i class="fas fa-times"></i></button>');
            $path_wrapper.append($input).append($del_button);
            $row.find('.js-widget-paths').append($path_wrapper);
            $del_button.click(deletePath);
            $input.focus();
        });

        that.$widget_params.find('.js-widget-del-path').click(deletePath);

        function deletePath (event) {
            event.preventDefault();
            $(event.target).closest('.js-widget-path-wrapper').remove();
        }
    };

    crmSettingsForm.prototype.initDealCreateCheckbox = function () {
        var that = this,
            referrer = that.referrer;

        var setUIEnv = function (checked) {

            var $responsible = that.$wrapper.find('.js-responsible-section'),
                responsible_object = $responsible.data('block_object');

            if (checked) {
                that.$available_deal_related_field.removeClass('crm-disabled');
                that.$wrapper.find('.js-hint-about-deal-fields').hide();
                that.$wrapper.find('.js-hint-about-deal-fields').next().addClass('crm-top-bordered');

                responsible_object.reload({});
            } else {
                // delete from preview block "deal" (deal related) fields
                that.$available_deal_related_field
                    .each(function () {
                        var id = $(this).data('id');
                        that.getFormField(id).find('.crm-delete-field-link').trigger('click');
                    });

                // and make each disabled in select menu, (must be done after deleting,
                // cause deleting remove crm-disabled class
                that.$available_deal_related_field.addClass('crm-disabled')

                that.$wrapper.find('.js-hint-about-deal-fields').show();
                that.$wrapper.find('.js-hint-about-deal-fields').next().removeClass('crm-top-bordered');

                responsible_object.reload({ funnel_id: 0 });
            }
        };

        that.$create_deal_block_wrapper.on('toggled', function (event, checked) {
            setUIEnv(checked, true);
        });

        if (that.form.id <= 0 && referrer === 'settings/sources') {
            that.$create_deal_block_wrapper.find(':checkbox').attr('checked', true).trigger('change');
            setUIEnv(true, false);
        }
    };

    crmSettingsForm.prototype.setFormChanged = function (status) {
        var that = this;
        status = status !== undefined ? status : true;
        if (status) {
            that.$button.addClass('yellow');
        } else {
            that.$button.removeClass('yellow');
        }
        that.changed = status;
    };

    crmSettingsForm.prototype.initPreviewButton = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $input = $wrapper.find('.crm-form-preview-submit-button-caption-input'),
            $button = $wrapper.find('.crm-form-preview-submit-button');

        var makeEditable = function (editable) {
            if (editable) {
                $input.show();
                $button.hide();
                $input.val($button.val());
            } else {
                $input.hide();
                $button.show();
            }
        };

        $button.click(function () {
            makeEditable(true);
        });

        $input.blur(function () {
            $button.val($input.val());
            makeEditable(false);
        }).keyup(function (e) {
            e.preventDefault();
            if (e.keyCode == 13) {
                $button.val($input.val());
                makeEditable(false);
            } else if (e.keyCode == 27) {
                makeEditable(false);
            }
        });
    };

    crmSettingsForm.prototype.initViewModeToggler = function () {
        const $toggler = this.$wrapper.find('.js-view-mode-toggler'),
              $preview_block = this.$wrapper.find('.crm-form-preview-block');

        const view_mode_class = {
            desktop: 'crm-preview-mode crm-preview-mode-desktop',
            mobile: 'crm-preview-mode crm-preview-mode-mobile',
        };
        $toggler.waToggle({
            change: (_, el) => {
                $preview_block.removeClass(Object.values(view_mode_class));
                const view_mode = el.dataset.id;
                if (view_mode in view_mode_class) {
                    $preview_block.addClass(view_mode_class[view_mode]);
                }
            }
        });
    };

    crmSettingsForm.prototype.initSubmitPlacement = function () {
        const that = this,
              $fields = that.$wrapper.find('.crm-form-preview-block .crm-form-fields:not(.crm-form-field-submit)');

        const shouldBeSubmitOnLeft = () => {
            if (that.sortable_is_active) {
                return;
            }
            const $last_field = $fields.find('.crm-form-field[data-checked="1"]:not(.crm-form-field-template)').last();
            const $name_field = $last_field.find('.field > .name');
            const submit_to_left = $last_field.hasClass('crm-caption-style-above') || ($name_field.length && ($name_field.is(':hidden') || !$name_field.text().trim()));

            that.getButton().captionplace = submit_to_left ? 'left' : '';
            that.renderButton();
        };
        shouldBeSubmitOnLeft();

        const fields_observer = new MutationObserver(shouldBeSubmitOnLeft);
        fields_observer.observe($fields[0], { childList: true, subtree: true });
    };

    crmSettingsForm.prototype.initFormWidthInput = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $input = $wrapper.find('.crm-form-width-input'),
            $preview_block = $wrapper.find('.crm-form-preview-block');

        $input.keyup(function (e) {
            if (e.keyCode == 13) {
                $input.val(Math.max(Math.min(parseInt($input.val(), 10) || 0, 600), 200));
                $preview_block.width($input.val() * 1 + 68);
            }
        });
        /*
        $input.input(function () {
            $input.val(Math.max(Math.min(parseInt($input.val(), 10) || 0, 600), 200));
            $preview_block.width($input.val() + 68);
        }); */
    };

    crmSettingsForm.prototype.initMarginsSettings = function () {
        var that = this,
            $preview_block = that.$wrapper.find('.crm-form-preview-block'),
            $input = that.$wrapper.find('input[data-css-var]');

        let timer_id = null;
        const debouncedOnChange = function() {
            if (timer_id) {
                clearTimeout(timer_id);
            }
            const $self = $(this);
            timer_id = setTimeout(() => {
                $preview_block.css($self.data('css-var'), $self.val() + $self.data('css-unit'));
                timer_id = null;
            }, 500);
        };
        $input.on('keyup change', debouncedOnChange);
    };

    crmSettingsForm.prototype.initTextEditor = function () {
        const that = this,
            wrapEditor = function ($textarea, buttons, plugins) {
                $textarea.waEditor({
                    focus: false,
                    buttons: buttons, // ['formatting', 'bold', 'italic', 'link'],
                    plugins: plugins, // ['fontcolor', 'fontsize', 'fontfamily', 'alignment', 'inlinestyle'],
                    minHeight: 200,
					imageUpload: '?module=file&action=uploadImage',
                    imageUploadFields: {
                        '_csrf': that.csrf
                    },
                    callbacks: {
                        keydown: function(event) { }, // without this waEditor intercents Ctrl+S event in Redactor
                        change: function () {
                            $textarea.waEditor('sync');
                            that.setFormChanged();
                        }
                    },
                    lang: that.lang
                });
            };

        wrapEditor(
            that.$form.find('[name="form[params][confirm_mail_body]"]'),
            ['formatting', 'bold', 'italic', 'link'],
            ['fontcolor', 'fontsize', 'fontfamily']
        );
        wrapEditor(
            that.$form.find('[name="form[params][html_after_submit]"]'),
            [  'format',
                'inline', 'bold', 'italic', 'underline', 'deleted', 'link', 'image',
                'alignment', 'lists', 'outdent', 'indent',
                'horizontalrule',  'fontcolor', 'fontsize'],
            ['alignment', 'fontcolor', 'fontsize']
        );
        wrapEditor(
            that.$form.find('[name="form[params][after_antispam_confirm_text]"]'),
            [  'format',
                'inline', 'bold', 'italic', 'underline', 'deleted', 'link', 'image',
                'alignment', 'lists', 'outdent', 'indent',
                'horizontalrule',  'fontcolor', 'fontsize'],
            ['alignment', 'fontcolor', 'fontsize']
        );
    };

    crmSettingsForm.prototype.initMessagesBlock = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $messages_block = $wrapper.find('.c-settings-messages-block'),
            $deal_block = $wrapper.find('.js-deal-section');

        that.renderToVariantSelectors();

        $messages_block.on('loadEditor', function () {
            that.renderToVariantSelectors();
        });
        $deal_block.on('changeResponsibleUser', function () {
            that.renderToVariantSelectors();
        });

    };

    crmSettingsForm.prototype.renderToVariantSelectors = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $messages_block = $wrapper.find('.c-settings-messages-block'),
            email_field = that.getField('email'),
            is_email_field_checked = email_field && email_field.checked > 0;

        var disableCheckbox = function ($checkbox, is_disabled) {
            var $label = $checkbox.closest('label');
            if (is_disabled) {
                $checkbox.data('checked', $checkbox.is(':checked'));
                $checkbox.prop('disabled', true).prop('checked', false);
            } else {
                $checkbox.prop('disabled', false).prop('checked', $checkbox.data('checked'));
            }
            if ($checkbox.is(':checked')) {
                $label.addClass('bold');
            } else {
                $label.removeClass('bold');
            }
        };

        $messages_block.find('.c-message-to-selector').each(function () {
            var $selector = $(this),
                $label = $selector.find('.c-label'),
                $list = $selector.find('.c-message-to-variants-list'),
                $client_checkbox = $list.find(':checkbox[data-id="client"]');

            disableCheckbox($client_checkbox, !is_email_field_checked);

            var text = [];
            $list.find(':checkbox:checked:not(:disabled)').each(function () {
                var $el = $(this),
                    $li = $el.closest('li');
                text.push($li.find('.c-variant-name-text').text());
            });

            text = text.length > 0 ? text.join(', ') : $label.data('initial_text');
            $label.text(text);
        });

    };

    crmSettingsForm.prototype.getField = function (id, checked) {
        var that = this,
            fields = that.form.params.fields,
            len = fields.length,
            field = null,
            index = 0;

        if (typeof checked === 'undefined') {
            checked = 1;
        } else {
            checked = parseInt(checked, 10) || 0;
        }

        if (checked <= 0) {
            return null;
        }

        for (var i = 0; i < len; i += 1) {
            if (fields[i].id == id && fields[i].checked == checked) {
                field = fields[i];
                index = i;
                break;
            }
        }

        if (!field) {
            return field;
        }

        field.required = field.required || '';
        field.captionplace = field.captionplace || 'left';
        field.caption = !field.caption && typeof field.caption !== 'string' ? field.name : field.caption;
        field.placeholder = field.placeholder || '';
        return field;
    };

    crmSettingsForm.prototype.getButton = function() {
        var that = this,
            button = that.form.params.button;

        if (!button) {
            return {
                captionplace: '',
                width: 'auto',
                caption: that.form.params.button_caption
            };
        }

        button.captionplace = button.captionplace || '';
        button.width = button.width || 'auto';
        button.caption = button.caption || that.form.params.button_caption;
        return button;
    };

    crmSettingsForm.prototype.getAvailableField = function (id) {
        if (this.available_fields[id]) {
            var field = $.extend({}, this.available_fields[id]);
            if (field.id === '!horizontal_rule') {
                field.captionplace = 'none';
            }
            return field;
        }
        return null;
    };

    crmSettingsForm.prototype.updateAvailableField = function (id, update) {
        if (this.available_fields[id]) {
            $.extend(this.available_fields[id], update);
        }
    };

    crmSettingsForm.prototype.addField = function (id, checked, field) {
        var that = this;
        checked = parseInt(checked, 10) || 0;
        if (checked <= 0 || !field) {
            return;
        }
        that.deleteField(id, checked);
        field = $.extend({}, field, { id: id, checked: checked });
        that.form.params.fields.push(field);
    };

    crmSettingsForm.prototype.deleteField = function (id, checked) {
        var that = this,
            fields = that.form.params.fields,
            len = fields.length;

        checked = parseInt(checked, 10) || 0;

        var new_fields = [];
        for (var i = 0; i < len; i += 1) {
            if (fields[i].id != id || fields[i].checked != checked) {
                new_fields.push(fields[i]);
            }
        }

        // reset checked
        checked = 0;
        len = new_fields.length;
        for (var i = 0; i < len; i += 1) {
            if (new_fields[i].id == id) {
                checked += 1;
                new_fields[i].checked = checked;
            }
        }

        that.form.params.fields = new_fields;
    };

    crmSettingsForm.prototype.updateField = function (id, checked, update) {
        if (checked <= 0) {
            return;
        }
        var that = this,
            field = that.getField(id, checked),
            fields = that.form.params.fields,
            len = that.form.params.fields.length,
            index = -1;

        for (var i = 0; i < len; i += 1) {
            if (fields[i].id == id && fields[i].checked == checked) {
                index = i;
                break;
            }
        }

        if (index < 0) {
            return;
        }

        field = $.extend(field, update);
        that.form.params.fields[index] = field;
        that.setFormChanged();
    };

    crmSettingsForm.prototype.updateButton = function (update) {
        var that = this,
            button = that.getButton();

        button = $.extend(button, update);
        that.form.params.button = button;
        that.form.params.button_caption = button.caption;
        that.setFormChanged();
    };

    crmSettingsForm.prototype.renderField = function (id, checked) {
        var that = this,
            field = that.getField(id, checked),
            $field = that.$form_fields.find('.crm-form-field:not(.crm-form-field-template)[data-id="' + id + '"][data-checked="' + checked + '"]');

        if (!field) {
            $field.remove();
            return;
        }

        if (!$field.length) {
            $field = that.$form_fields.find('.crm-form-field-template.crm-form-field-template[data-id="' + id + '"]').clone();
            $field.removeClass('crm-form-field-template');
            $field.attr('data-checked', checked);
            $field.insertBefore(that.$form_fields.find('.crm-form-field-template:first'));
            $field.show();
        }

        $field.removeClass('crm-caption-style-none crm-caption-style-above')
            .addClass('crm-caption-style-' + field.captionplace);

        if (field.id === '!paragraph') {
            $field.find('.crm-paragraph').html(field.text || '');
            return;
        }

        var encodeHtml = function (html) {
            return html && (''+html).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        };

        $field.find('.js-field-label').html(
            encodeHtml(field.caption) +
            (field.required && field.id !== '!captcha' ? ' *' : '')
        );

        if (field.type === 'Composite' || field.type === 'Address') {
            if (field.subfield_captionplace === 'none') {
                //$field.find('.crm-composite-field').find('.name').hide();
                $field.find('.crm-composite-field').removeClass('horizontal').addClass('no-name').find('.field').removeClass('vertical');
            } else if (field.subfield_captionplace === 'left') {
                //$field.find('.crm-composite-field').find('.name').show();
                $field.find('.crm-composite-field').addClass('horizontal').removeClass('no-name').find('.field').removeClass('vertical');
            } else {
                //$field.find('.crm-composite-field').find('.name').show();
                $field.find('.crm-composite-field').removeClass('horizontal').removeClass('no-name').find('.field').addClass('vertical');
            }
        }

        if (field.type === 'Checkbox') {
            $field.find('.name').html('');
        }

        $field.find(':input:eq(0)').prop('placeholder', field.placeholder || '');
        if (field.id === 'password') {
            $field.find(':input:eq(1)').prop('placeholder', field.placeholder_confirm || '');
            if (field.without_confirm) {
                $field.find('.js-password-confirn').addClass('hidden');
            } else {
                $field.find('.js-password-confirn').removeClass('hidden');
            }
        }

        if (field.id === '!deal_description') {
            that.renderDealDescription(field);
        }
    };

    crmSettingsForm.prototype.renderButton = function () {
        var that = this,
            button = that.getButton(),
            $button = that.$form_fields.find('.crm-form-field[data-id="!button"]');

        $button.find('.js-field-label').toggle(button.captionplace !== 'left');
        $button.find('.crm-form-preview-submit-button')
            .removeClass('wide auto left')
            .addClass([button.width, button.captionplace])
            .text(button.caption || that.form.params.button_caption)
            .show();
    };

    crmSettingsForm.prototype.initFields = function () {
        var that = this;
        that.$form_fields.on('click', '.crm-field-edit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            that.editField($(this).closest('.crm-form-field'));
        });
    };

    crmSettingsForm.prototype.editField = function ($field) {
        var that = this,
            id = $field.data('id'),
            checked = $field.data('checked');

        if (id === '!horizontal_rule') {
            return;
        }

        var dialog_html = null,
            options = {
                html: '',
                id: id,
                checked: checked,
                settingsForm: that
            };

        if (id === '!agreement_checkbox') {
            dialog_html = that.$agreement_checkbox_edit_dialog_tempalte.html();
        } else if (id === '!paragraph') {
            dialog_html = that.$paragraph_edit_dialog_template.html();
        } else if (id === '!button') {
            dialog_html = that.$button_edit_dialog_template.html();
        //} else if ($field.find('.crm-composite-field').data('composite')) {
        //    dialog_html = that.$composite_edit_dialog_template.clone();
        } else if ($field.data('type') === 'Checkbox') {
            dialog_html = that.$checkbox_edit_dialog_template.html();
        } else {
            dialog_html = that.$edit_dialog_template.html();
        }

        options['html'] = dialog_html;

        if (id === '!agreement_checkbox') {
            new crmSettingsFormAgreementCheckboxEditDialog(options);
        } else if (id === '!paragraph') {
            new crmSettingsFormParagraphEditDialog(options);
        } else if (id === '!button') {
            new crmSettingsFormButtonEditDialog(options);
        } else {
            new crmSettingsFormFieldEditDialog(options);
        }

    };

    crmSettingsForm.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            $loading = $form.find('.crm-loading'),
            $status = $form.find('.crm-success-status');

        $form.on('keypress', 'input', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
            }
        });


        $form.submit(function (e) {
            e.preventDefault();
            var data = $form.serializeArray(),
                fields = [];

            $.each(that.form.params.fields, function (field_id, field) {
                if (typeof field === 'undefined') {
                    return;
                }
                if (field.checked > 0) {
                    var index = fields.length;
                    $.each(field, function (param_name, param_value) {
                        if (param_name !== 'html') {
                            fields[index] = fields[field_id] || {};
                            fields[index][param_name] = param_value;
                        }
                    });
                }
            });

            data.push({
                name: 'form[params][fields]',
                value: JSON.stringify(fields)
            });
            data.push({
                name: 'form[params][button]',
                value: JSON.stringify(that.form.params.button)
            });

            that.form.params.widget_domains = [];
            $form.find('.js-widget-domain:checked').each(function () {
                $this = $(this);
                    that.form.params.widget_domains.push($this.val());
            });
            data.push({
                name: 'form[params][widget_domains]',
                value: JSON.stringify(that.form.params.widget_domains)
            });

            that.form.params.widget_path = {};
            $form.find('.js-widget-path').each(function () {
                const domain = $(this).data('domain');
                const path = $(this).val();
                if (!(domain in that.form.params.widget_path)) {
                    that.form.params.widget_path[domain] = [];
                }
                if (!that.form.params.widget_path[domain].includes(path)) {
                    that.form.params.widget_path[domain].push(path);
                }
            });
            data.push({
                name: 'form[params][widget_path]',
                value: JSON.stringify(that.form.params.widget_path)
            });

            that.clearValidateErrors();
            $loading.show();
            that.$button.prop('disabled', true);
            that.submit_xhr && that.submit_xhr.abort();
            that.submit_xhr =
                $.post($form.attr('action'), data)
                    .done(function (r) {
                        if (r.status !== 'ok') {
                            that.showValidateErrors(r.errors || {});
                            $loading.hide();
                            that.$button.prop('disabled', false);
                            return;
                        }
                        $status.show().fadeOut(500);
                        $.crm.content.load($.crm.app_url + 'settings/form/' + r.data.form.id).then(function(){
                            $loading.hide();
                            that.$button.prop('disabled', false);
                            that.setFormChanged(false);

                        });
                    })
                    .fail(function () {
                        that.showValidateErrors({ '': that.messages['unknown_server_error']/*'Unknown server error'*/ });
                        $loading.hide();
                        that.$button.prop('disabled', false);
                    })
                    .always(function () {
                        /*$loading.hide();
                        setTimeout(function () {
                            that.$button.prop('disabled', false);
                        }, 1500);*/
                    });
        });
    };

    crmSettingsForm.prototype.clearValidateErrors = function () {
        var that = this,
            $form = that.$form;
        $form.find('.state-error').removeClass('state-error');
        $form.find('.crm-errors-block').remove();
    };

    crmSettingsForm.prototype.showValidateErrors = function (all_errors) {
        var that = this,
            $form = that.$form,
            plain_errors = {};

        $.each(all_errors || {}, function (name, errors) {
            if (name === 'params') {
                $.each(errors, function (name, error) {
                    plain_errors["form[params][" + name + "]"] = $.isArray(error) ? error : [error];
                });
            } else {
                plain_errors[!name ? "" : "form[" + name + "]"] = $.isArray(errors) ? errors : [errors];
            }
        });

        that.clearValidateErrors();

        $.each(plain_errors, function (name, errors) {
            var $error = $('<div class="crm-errors-block"></div>'),
                $field = !name ? $() : $form.find('[name="' + name + '"]');
            $field.addClass('state-error');
            $.each(errors, function (index, error) {
                $error.append('<span class="errormsg">' + error + '</span>');
            });
            if ($field.length) {
                $field.after($error);
            } else {
                $form.find('.crm-common-errors-block').append($error);
            }
        });
    };

    crmSettingsForm.prototype.initDeleteLink = function () {
        var that = this;
        var messages = that.messages || {};
        that.$delete_link.click(function (e) {
            e.preventDefault();

            $.crm.confirm.show({

                title: messages['delete_confirm_title'],
                text: messages['delete_confirm_text'],
                button: messages['delete_confirm_button'],

                onConfirm: function() {
                    var $dialog_wrapper = $('.crm-confirm-dialog'),
                        $loading = $dialog_wrapper.find('.crm-loading').show(),
                        $button = $dialog_wrapper.find('.js-confirm-dialog').attr('disabled', true);

                    $.post($.crm.app_url + '?module=settings&action=formDelete',
                    { id: that.form.id },)
                        .always(function () {
                            $.crm.content.load($.crm.app_url + 'settings/form/');
                            $loading.hide();
                            $button.attr('disabled', false);
                        });
                    return false;
                }
            });
        })
    };

    crmSettingsForm.prototype.initSortable = function () {
        var that = this;
        that.$form_fields.sortable({
            distance: 5,
            helper: 'clone',
            items: '.crm-form-field:not(:hidden)',
            opacity: 0.75,
            handle: '.sort',
            tolerance: 'pointer',
            containment: that.$form_fields,
            start: () => { that.sortable_is_active = true },
            stop: () => { save(); that.sortable_is_active = false; },
            onUpdate: save,
        });

        function save() {
            var fields = that.form.params.fields,
                map = fields.reduce((obj, f, i) => (obj[f.id+'-'+f.checked]=i,obj), {}),
                new_fields = [],
                $fields = that.$form_fields.find('.crm-form-field:not(.crm-form-field-template)');

            $fields.each(function () {
                var $el = $(this),
                    id = $el.data('id'),
                    checked = $el.data('checked'),
                    key = id + '-' + checked,
                    index = map[key],
                    field = fields[index];

                if (field) {
                    new_fields.push(field);
                }
            });
            that.form.params.fields = new_fields;
            that.setFormChanged();
        }
    };

    crmSettingsForm.prototype.initDeleteFieldLinks = function () {
        var that = this;
        that.$wrapper.on('click', '.crm-delete-field-link', function () {
            var $field = $(this).closest('.crm-form-field');
            that.deleteFormField($field);
        });
    };

    crmSettingsForm.prototype.deleteFormField = function ($field) {

        if ($field.length <= 0) {
            return;
        }

        var that = this,
            id = $field.data('id'),
            checked = $field.data('checked'),
            a_field = that.getAvailableField(id);
        that.$form_available_fields.find('.crm-form-field[data-id="' + id +'"]').removeClass('crm-disabled');
        that.deleteField(id, checked);

        $field.remove();

        if (a_field) {
            that.updateAvailableField(id, { checked: a_field.checked - 1 });
        }

        if (id === 'email') {
            that.disableIButton(that.$confirmation_checkbox);
            that.$confirmation_checkbox.attr('disabled', true);
            that.$confirmation_enable_text.show();

            // always delete password
            that.$form_available_fields.find('.crm-form-field[data-id="password"]').addClass('crm-disabled');
            var $password_field = that.getFormField('password');
            that.deleteFormField($password_field);

            that.renderToVariantSelectors();
        }

        // when delete "company" field remove remove all exclusive company fields and disable them
        if (id === 'company') {
            that.$form_available_fields.find('.crm-form-field[data-person-enabled=0][data-company-enabled=1]')
                .each(function () {
                    var $field = $(this),
                        id = $field.data('id'),
                        $form_field = that.getFormField(id);
                    that.deleteFormField($form_field);
                    $field.addClass('crm-disabled');
                });
        }

        that.setFormChanged();
    };

    crmSettingsForm.prototype.getFormField = function (id, checked) {
        var that = this,
            clz = 'crm-form-field',
            not_clz = 'crm-form-field-template',
            selector = '.%clz%[data-id="%id%"]%extra%:not(.%not_clz%)',
            extra = checked !== undefined ? '[data-id="' + checked + '"]' : '';

        var map = [
            ['%clz%', clz],
            ['%id%', id],
            ['%extra%', extra],
            ['%not_clz%', not_clz]
        ];
        for (var i = 0, n = map.length; i < n; i += 1) {
            selector = selector.replace(map[i][0], map[i][1]);
        }

        return that.$form_fields.find(selector);
    };

    crmSettingsForm.prototype.initAvailableFields = function () {
        var that = this,
            default_checked_fields = that.default_checked_fields,
            $a_fields_block = that.$form_available_fields;

        $a_fields_block.on('click', '.crm-form-field:not(.crm-disabled)', function (e) {
            that.addFormField($(this));
        });

        if (that.form.id <= 0 && default_checked_fields.length > 0) {
            $.each(default_checked_fields, function (i, id) {
                var $field = $a_fields_block.find('.crm-form-field[data-id="' + id + '"]:not(.crm-disabled)');
                that.addFormField($field);
            });
        }
    };

    crmSettingsForm.prototype.addFormField = function ($li) {

        if ($li.length <= 0) {
            return;
        }

        var that = this,
            id = $li.data('id'),
            a_field = that.getAvailableField(id);

        that.$add_field_dropdown.removeClass('is-opened');

        var checked = a_field.checked + 1;
        if (!a_field.is_multi) {
            $li.addClass('crm-disabled');
        }

        var field = that.getField(a_field.id, checked);
        if (!field) {
            that.addField(id, checked, a_field);
        }
        that.updateAvailableField(id, { checked: checked });

        if (id === 'email') {
            that.$confirmation_enable_text.hide();
            that.enableIButton(that.$confirmation_checkbox);
            that.$confirmation_checkbox.attr('disabled', false);
            // check password field
            var password_field = that.getAvailableField('password');
            if (password_field && password_field.checked <= 0) {
                that.$form_available_fields.find('.crm-form-field[data-id="password"]').removeClass('crm-disabled');
            }
        }

        // enable to select exclusive company fields
        if (id === 'company') {
            that.$form_available_fields.find('.crm-form-field.crm-disabled[data-person-enabled=0][data-company-enabled=1]')
                .removeClass('crm-disabled');
        }

        that.renderField(id, checked);

        if (id === '!paragraph') {
            var $field = that.$form_fields.find('.crm-form-field[data-id="' + id + '"][data-checked="' + checked + '"]');
            that.editField($field);
        }

        that.setFormChanged();

        that.renderToVariantSelectors();
    };

    crmSettingsForm.prototype.initAddNewFieldLink = function () {
        var that = this,
            $list = that.$form_available_fields;

        that.$wrapper.on('click', '.crm-add-new-field-link', function (event) {
            event.stopPropagation();
            //$list.toggle();
        });

        $(document)
            .on("keydown", keyWatcher)
            .on("click", clickWatcher);

        function keyWatcher(event) {
            var is_exist = ( $.contains(document, $list[0]) );
            if (is_exist) {
                var code = event.keyCode;
                if (code === 27) {
                    var is_visible = $list.is(":visible");
                    if (is_visible) {
                        $list.hide();
                    }
                }
            } else {
                $(document).off("keypress", keyWatcher);
            }
        }

        function clickWatcher(event) {
            var is_exist = $.contains(document, $list[0]);
            if (is_exist) {
                var is_child_target = $.contains($list[0], event.target),
                    is_target = ($list[0] === event.target),
                    is_visible = $list.is(":visible");

                if (is_visible && !(is_target || is_child_target) ) {
                    $list.hide();
                }
            } else {
                $(document).off("keypress", clickWatcher);
            }
        }
    };

    crmSettingsForm.prototype.initIButtons = function() {
        var that = this;
        that.$wrapper.find("#js-ibutton").each( function() {
            if ($(this).data('iButtonInit')) {
                return $(this);
            }
            that.$switch = $(this).waSwitch({
                ready: function (wa_switch) {
                    let $label = wa_switch.$wrapper.siblings('label');
                    wa_switch.$label = $label;
                    wa_switch.active_text = $label.data('active-text');
                    wa_switch.inactive_text = $label.data('inactive-text');
                },
                change: function(active, wa_switch) {

                    if (active) {
                        if (that.$switch.has(that.$confirmation_checkbox).length){
                            that.$email_confirm_block.slideDown(300);
                            that.$confirmation_checkbox.attr('checked', true);
                        }
                    wa_switch.$label.text(wa_switch.active_text);
                    }
                    else {
                        if (that.$switch.has(that.$confirmation_checkbox).length){
                            that.$email_confirm_block.slideUp(300);
                            that.$confirmation_checkbox.attr('checked', false);
                        }
                     wa_switch.$label.text(wa_switch.inactive_text);
                    }
                }
            });
          //  that.initIButton($(this));
        });
    };
    /*
    CRMSettingsShop.prototype.initToggle = function() {
        $("#toggle-menu").waToggle();
    }*/
/*
    crmSettingsForm.prototype.initIButton = function ($input) {
        if ($input.data('iButtonInit')) {
            return $input;
        }
        return $input.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        }).data('iButtonInit', 1);
    };
*/
    crmSettingsForm.prototype.disableIButton = function () {
        var that = this;
        var switcher = that.$switch.waSwitch("switch");
        switcher.set(false);
        switcher.disable(true);
    };

    crmSettingsForm.prototype.enableIButton = function () {
        var that = this;
        var switcher = that.$switch.waSwitch("switch");
        switcher.disable(false);
    };

    crmSettingsForm.prototype.initCopySmartyHelper = function () {
        var that = this;

        that.$copy.on('click', function () {
            var $self = $(this),
                value = $self.data('content'),
                $temp = $('<input>'),
                copyIcon = '<i class="fas fa-copy"></i>',
                yesIcon = '<i class="fas fa-check-circle"></i>';

            $("body").append($temp);
            $temp.val(value).select();
            document.execCommand("copy");
            $temp.remove();

            $self.html(yesIcon);
            setTimeout(function () {
                $self.html(copyIcon);
            }, 2000);
        });
    };

    crmSettingsForm.prototype.initExternalCode = function () {
        var that = this;
        that.$external_code.find('textarea').click(function () {
            $(this).select();
        });
    };

    crmSettingsForm.prototype.initCancelLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            referrer = that.referrer;

        $wrapper.on('click', '.js-c-cancel-link', function (e) {
            e.preventDefault();
            if (referrer == 'settings/sources') {
                $.crm.storage.set('crm/settings/source_tab', 'form');
                $.crm.content.load($.crm.app_url + 'settings/sources/');
            } else {
                $.crm.content.load($.crm.app_url + 'settings/form/');
            }
        });
    };

    crmSettingsForm.prototype.initIconSection = function() {
        var that = this,
            $iconField = that.$wrapper.find(".c-icon-section .js-icon-field"),
            $iconItem = that.$wrapper.find(".c-icon-section .c-icon-list .js-icon-item");

        $iconItem.on("click", function() {
            var icon = $(this).data("icon");
            $iconItem.removeClass("selected");
            $(this).addClass("selected");
            $iconField.val(icon);
        });
    }

    crmSettingsForm.prototype.initFrontendLinkDropdown = function() {
        $("#dropdown-frontend-link").waDropdown({
            hide: false,
            ready: function ({ $wrapper }) {
                $wrapper.find(".js-copy-frontend-link").on("click", function (e) {
                    e.preventDefault();

                    $.wa.copyToClipboard($wrapper.find('.js-frontend-link').attr('href'));

                    const $button = $(this);
                    const defaultIconClass = $button.data('icon-default');
                    const successIconClass = $button.data('icon-success');
                    const $icon = $button.find('.js-icon svg');
                    if ($icon.length && $icon.hasClass(defaultIconClass)) {
                        $button.addClass($button.data('class-success'));
                        $button.find('.js-text').text($button.data('text-success'));
                        $icon.removeClass(defaultIconClass);
                        $icon.addClass(successIconClass);
                        setTimeout(function () {
                            const $icon = $button.find('.js-icon svg');
                            $button.find('.js-text').text($button.data('text-default'));
                            $button.removeClass($button.data('class-success'));
                            $icon.removeClass(successIconClass);
                            $icon.addClass(defaultIconClass);
                        }, 1000)
                    }
                });
            }
        });
    }

    crmSettingsForm.prototype.initColorSection = function() {
        var that = this,
            //$colorWrapper = that.$wrapper.find(".js-color-selector-wrapper"),
            $colorList = that.$wrapper.find(".c-color-section .c-colors"),
            $colorField = that.$wrapper.find(".c-color-section .js-color-field"),
            $colorPickerWrapper = that.$wrapper.find(".js-toggle-wrapper");

        // VARS
        var active_class = "is-active",
            hidden_class = "is-hidden";

        // CLASSES
        var ColorPicker = ( function($) {

            ColorPicker = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$field = that.$wrapper.find(".js-color-field");
                that.$icon = that.$wrapper.find(".js-toggle");
                that.$colorPicker = that.$wrapper.find(".js-color-picker");
                that.$colors = that.$wrapper.closest(".js-color-selector-wrapper").find(".c-colors");

                // VARS

                // DYNAMIC VARS
                that.is_opened = false;
                that.farbtastic = false;

                // INIT
                that.initClass();
            };

            ColorPicker.prototype.initClass = function() {
                var that = this;

                document.addEventListener('click', (event) => {
                    // Close on click outside the colorpicker
                    const wrapper = that.$wrapper[0];
                    if (wrapper && !wrapper.contains(event.target)) {
                        that.displayToggle( false );
                    }
                });

                document.addEventListener('keydown', (event) => {
                    // Close on the Escape key is pressed
                    if (event.key === 'Escape') {
                        that.displayToggle( false );
                    }
                });

                that.farbtastic = $.farbtastic(that.$colorPicker, function(color) {
                    if (that.$field.val() !== color) {
                        hideColorIcon();
                        that.$field.val( color ).addClass("js-changed").change();
                    }
                });

                that.$wrapper.data("colorPicker", that);

                that.$field.on("change keyup", function() {
                    var color = $(this).val();
                    //
                    that.$icon.css("background-color", color);
                    that.farbtastic.setColor(color);
                });

                that.$icon.on("click", function(event) {
                    event.preventDefault();
                    that.displayToggle( !that.is_opened );
                });

                that.$field.on("click", function() {
                    that.displayToggle(!that.is_opened);
                });

                that.$field.on("keyup", hideColorIcon);

                function hideColorIcon() {
                    var $active = that.$colors.find("." + active_class);
                    if ($active.length) {
                        $active.removeClass(active_class);
                        $active = false;
                    }
                }
            };

            ColorPicker.prototype.displayToggle = function( show ) {
                var that = this;

                if (show) {
                    $colorPickerWrapper.addClass(hidden_class);
                    that.$wrapper.removeClass(hidden_class);
                    that.is_opened = true;
                } else {
                    that.$wrapper.addClass(hidden_class);
                    that.is_opened = false;
                }
            };

            return ColorPicker;

        })(jQuery);

        // EVENTS
        $colorList.on("click", ".js-set-color", setColor);

        $colorField.on("change", function() {
            var color = $(this).val();

            var rgb = getRGB(color);
            if (!rgb) {
                return;
            }
            //var crmColor = new $.crm.color(color),
            //    range = crmColor.getRange();

            function getRGB(color) {
                var rgb = false;
                if (typeof color === "string") {
                    color = color.replace("#","");
                    if (color.length === 3) {
                        rgb = hex2rgb(color[0] + "" + color[0], color[1] + "" + color[1], color[2] + "" + color[2]);
                    } else if (color.length === 6) {
                        rgb = hex2rgb(color[0] + "" + color[1], color[2] + "" + color[3], color[4] + "" + color[5]);
                    }
                } else if (typeof color === "object" && color.length === 3) {
                    rgb = color;
                }
                return rgb;
            }

            // HEX
            function hex2rgb(r,g,b) {
                r = parseInt(r, 16);
                g = parseInt(g, 16);
                b = parseInt(b, 16);

                return (r >= 0 && g >= 0 && b >= 0) ? [r,g,b] : null;
            }
        });

        $colorPickerWrapper.each(function() {
            $wrapper = $(this);
            new ColorPicker({
                $wrapper: $wrapper
            });
        })


        //$colorField.change();

        // HANDLERS
        function setColor(event) {
            event.preventDefault();
            var $color = $(this),
                $section = $color.closest(".js-color-selector-wrapper"),
                $active = $section.find(".c-colors").find("." + active_class);

            if ($active.length) {
                $active.removeClass(active_class)
            }
            $color.addClass(active_class);

            $colorPickerWrapper.addClass(hidden_class);

            var color = $color.data("color");
            $section.find(".js-color-field").val(color).addClass("js-changed").change();
        }

    };

    crmSettingsForm.prototype.initBlocks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $create_deal = $wrapper.find('.js-deal-section'),
            $responsible = $wrapper.find('.js-responsible-section'),
            create_deal_object = $create_deal.data('block_object'),
            responsible_object = $responsible.data('block_object');
        create_deal_object.setResponsibleBlock(responsible_object);
    };

    return crmSettingsForm;

})(jQuery);
