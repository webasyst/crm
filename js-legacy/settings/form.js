var crmSettingsForm = (function ($) {

    crmSettingsForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('.crm-form-settings-form');
        that.$form_fields = that.$wrapper.find('.crm-form-fields');
        that.$form_available_fields = that.$wrapper.find('.crm-available-fields');
        that.$confirmation_checkbox = that.$wrapper.find('.crm-confirmation-checkbox');
        that.$confirmation_enable_text = that.$wrapper.find('.crm-confirmation-enable-text');
        that.$email_confirm_block = that.$wrapper.find('.crm-form-email-confirm-block');


        that.$create_deal_block_wrapper = that.$wrapper.find('.crm-form-settings-block-form_create_deal');


        that.$button = that.$form.find('[type=submit]');
        that.$delete_link = that.$wrapper.find('.crm-delete-form-link');
        that.$copy = that.$wrapper.find('.js-copy.icon16');
        that.$iframe_textarea = that.$wrapper.find('.crm-iframe-code');

        that.$available_deal_related_field = getDealRelatedFields();

        // DOM :: Dialog templates
        that.$edit_dialog_template = that.$wrapper.find('.crm-settings-form-edit-field-wrapper[data-template="1"]');
        that.$paragraph_edit_dialog_template = that.$wrapper.find('.crm-settings-form-paragraph-edit-wrapper[data-template="1"]');
        that.$agreement_checkbox_edit_dialog_tempalte = that.$wrapper.find('.c-settings-form-agreement-checkbox-edit-wrapper[data-template="1"]');

        // VARS :: REFERRER
        that.referrer = $.crm.storage.get('crm/settings/web_form_referrer');
        $.crm.storage.del('crm/settings/web_form_referrer');

        // VARS
        that.form = options.form || {};
        that.form.params = that.form.params || {};
        that.form.params.fields = that.form.params.fields || {};
        that.available_fields = options.available_fields || {};
        that.lang = options.lang;
        that.messages = options.messages || {};
        that.default_checked_fields = getDefaultCheckedFields();

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
        that.initAddNewFieldLink();
        that.initAvailableFields();
        that.initDeleteFieldLinks();
        that.initPreviewButton();
        that.initFormWidthInput();
        that.initStickyButton();
        that.initFields();
        that.initSubmit();
        that.initDeleteLink();
        that.initSortable();
        that.initIButtons();
        that.initTextEditor();
        that.initCopySmartyHelper();
        that.initIframeTextarea();
        that.initMessagesBlock();
        that.initCancelLink();
        that.initBlocks();
    };

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

        that.$confirmation_checkbox.on('change', function () {
            var $el = $(this);
            if ($el.is(':checked')) {
                that.$email_confirm_block.slideDown(200);
            } else {
                that.$email_confirm_block.slideUp(200);
            }
        });


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

        that.$form.find('.crm-iframe-code-block-toggle').click(function () {
            that.$iframe_textarea.toggle();
        });
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
            that.$button.removeClass('green').addClass('yellow');
        } else {
            that.$button.removeClass('yellow').addClass('green');
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

    crmSettingsForm.prototype.initFormWidthInput = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $input = $wrapper.find('.crm-form-width-input'),
            $preview_block = $wrapper.find('.crm-form-preview-block');

        $input.keyup(function (e) {
            if (e.keyCode == 13) {
                $input.val(Math.max(Math.min(parseInt($input.val(), 10) || 0, 600), 200));
                $preview_block.width($input.val());
            }
        });
    };

    crmSettingsForm.prototype.initStickyButton = function () {
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

    crmSettingsForm.prototype.initTextEditor = function () {
        var that = this,
            $text = that.$form.find('[name="form[params][confirm_mail_body]"]');
        $text.waEditor({
            focus: false,
            buttons: ['formatting', 'bold', 'italic', 'link'],
            plugins: ['fontcolor', 'fontsize', 'fontfamily'],
            callbacks: {
                keydown: function(event) { }, // without this waEditor intercents Ctrl+S event in Redactor
                change: function () {
                    $text.waEditor('sync');
                    that.setFormChanged();
                }
            },
            lang: that.lang
        });
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

    crmSettingsForm.prototype.getAvailableField = function (id) {
        if (this.available_fields[id]) {
            var field = $.extend({}, this.available_fields[id]);
            if (field.id === '!horizontal_rule' || field.id === '!paragraph') {
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
            field = that.getField(id, checked);

        field = $.extend(field, update);
        that.form.params.fields[id] = field;
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

        if (field.id === '!paragraph') {
            $field.find('.crm-paragraph').html(field.text || '');
            return;
        }

        var encodeHtml = function (html) {
            return html && (''+html).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        };

        $field.find('.crm-caption').html(
            '<label>' +
                encodeHtml(field.caption) +
                (field.required && field.id !== '!captcha' ? ' *' : '') +
            '</label>'
        );
        $field.removeClass('crm-caption-style-none crm-caption-style-above')
            .addClass('crm-caption-style-' + field.captionplace);

        $field.find(':input:eq(0)').prop('placeholder', field.placeholder || '');
        if (field.id === 'password') {
            $field.find(':input:eq(1)').prop('placeholder', field.placeholder_confirm || '');
        }

        if (field.id === '!deal_description') {
            that.renderDealDescription(field);
        }
    };

    crmSettingsForm.prototype.initFields = function () {
        var that = this;
        that.$form_fields.on('click', '.crm-caption-col, .crm-input-col', function (e) {
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

        var $dialog = null,
            options = {
                html: '',
                id: id,
                checked: checked,
                settingsForm: that
            };

        if (id === '!agreement_checkbox') {
            $dialog = that.$agreement_checkbox_edit_dialog_tempalte.clone();
        } else if (id === '!paragraph') {
            $dialog = that.$paragraph_edit_dialog_template.clone();
        } else {
            $dialog = that.$edit_dialog_template.clone();
        }

        $dialog.removeAttr('data-template').attr('id', 'crm-settings-dialog-wrapper-' + id);
        $dialog.appendTo('body');
        $dialog.show();

        options['html'] = $dialog;

        if (id === '!agreement_checkbox') {
            new crmSettingsFormAgreementCheckboxEditDialog(options);
        } else if (id === '!paragraph') {
            new crmSettingsFormParagraphEditDialog(options);
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

            that.clearValidateErrors();
            $loading.show();
            that.$button.prop('disabled', true);
            that.submit_xhr && that.submit_xhr.abort();
            that.submit_xhr =
                $.post($form.attr('action'), data)
                    .done(function (r) {
                        if (r.status !== 'ok') {
                            that.showValidateErrors(r.errors || {});
                            return;
                        }
                        that.setFormChanged(false);
                        $status.show().fadeOut(500);
                        $.crm.content.load($.crm.app_url + 'settings/form/' + r.data.form.id);
                    })
                    .error(function () {
                        that.showValidateErrors({ '': that.messages['unknown_server_error']/*'Unknown server error'*/ });
                    })
                    .always(function () {
                        $loading.hide();
                        that.$button.prop('disabled', false);
                    });
        });
    };

    crmSettingsForm.prototype.clearValidateErrors = function () {
        var that = this,
            $form = that.$form;
        $form.find('.error').removeClass('error');
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
            $field.addClass('error');
            $.each(errors, function (index, error) {
                $error.append('<em class="errormsg">' + error + '</em>');
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
        that.$delete_link.click(function (e) {
            e.preventDefault();
            if (!confirm(that.messages.confirm_delete)) {
                return;
            }
            $.post(
                $.crm.app_url + '?module=settings&action=formDelete',
                { id: that.form.id },
                function () {
                    $.crm.content.load($.crm.app_url + 'settings/form/');
                }
            );
        });
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
            update: function () {
                var fields = that.form.params.fields,
                    len = fields.length,
                    map = {},
                    new_fields = [],
                    $fields = that.$form_fields.find('.crm-form-field:not(.crm-form-field-template)');
                for (var i = 0; i < len; i += 1) {
                    var key = fields[i].id + '-' + fields[i].checked;
                    map[key] = i;
                }
                $fields.each(function () {
                    var $el = $(this),
                        id = $el.data('id'),
                        checked = $el.data('checked'),
                        key = id + '-' + checked,
                        index = map[key],
                        field = fields[index];
                    new_fields.push(field);
                });
                that.form.params.fields = new_fields;
                that.setFormChanged();
            }
        });
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
            that.disableIButton(that.$confirmation_checkbox).attr('checked', false).trigger('change');
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

        that.$form_available_fields.hide();

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
            $list.toggle();
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
        that.$wrapper.find(".js-ibutton").each( function() {
            that.initIButton($(this));
        });
    };

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

    crmSettingsForm.prototype.disableIButton = function ($ibutton) {
        return this.initIButton($ibutton).iButton('disable', true);
    };

    crmSettingsForm.prototype.enableIButton = function ($ibutton) {
        return this.initIButton($ibutton).iButton('disable', false);
    };

    crmSettingsForm.prototype.initCopySmartyHelper = function () {
        var that = this;

        that.$copy.on('click', function () {
            var $self = $(this),
                value = $self.data('content'),
                $temp = $('<input>');

            $("body").append($temp);
            $temp.val(value).select();
            document.execCommand("copy");
            $temp.remove();

            $self.removeClass('stack').addClass('yes');
            setTimeout(function () {
                $self.removeClass('yes').addClass('stack');
            }, 2000);
        });
    };

    crmSettingsForm.prototype.initIframeTextarea = function () {
        var that = this;
        that.$iframe_textarea.click(function () {
            that.$iframe_textarea.select();
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
