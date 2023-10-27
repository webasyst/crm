var crmSettingsFieldEdit = (function ($) {

    var crmSettingsFieldEdit = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');

        // VARS
        that.dialog = that.$wrapper.data('dialog');
        that.field = options.field || null;
        that.locales = options.locales;

        // DYNAMIC VARS
        // INIT
        that.initClass();
    };

    crmSettingsFieldEdit.prototype.initClass = function () {
        var that = this;
        //
        var $first_input = that.$form.find('.crm-local-input-wrapper').eq(0).find('input'),
            first_input_val = $first_input.val();
        $first_input.focus().val('').val(first_input_val);

        that.bindEvents();
        //
        that.editSubFields();

        if (!that.field) {
            that.initIDAutoFiller();
        }
    };

    crmSettingsFieldEdit.prototype.bindEvents = function () {
        var that = this,
            $form = that.$form;

        $form.on('change', '.crm-field-type-select', function () {
            var $el = $(this),
                val = $el.val(),
                $txt_wrapper = $form.find('.crm-values-textarea-wrapper').hide();
            if (val === 'Select' || val === 'Radio') {
                $txt_wrapper.show();
            }
        });

        $form.on('click', '.crm-add-name-another-language', function () {
            var $el = $(this),
                id = $el.data('id'),
                region = $el.data('nameRegion'),
                $main_wrapper = $form.find('.crm-local-input-wrapper'),
                $clone = $main_wrapper.clone();

            $clone
                .find('input')
                    .attr('name', 'name[' + id + ']')
                    .val('')
                    .attr('disabled', false)
                    .attr('data-main-locale', '')
                    .attr('data-error-id', id)
                    .removeClass('error')
                    .end()
                .find('.crm-name-region')
                    .text(region)
                    .end()
                .find('.errormsg')
                    .text('')
                    .end()
                .insertAfter($main_wrapper);
            $clone.find('input').focus();

            $el.hide();

            if ($form.find('.crm-add-name-another-language:not(:hidden)').length <= 0) {
                $form.find('.crm-add-name-another-language-wrapper').hide();
            }
        });

        that.initSubmit();

        if (that.field) {
            if (that.field.editable) {
                that.initDeleteLink();
            } else {
                that.initDisableLink();
            }
        }
    };

    crmSettingsFieldEdit.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            xhr = null;
        $form.submit(function (e) {
            e.preventDefault();
            if (xhr) {
                xhr.abort();
                xhr = null;
            }
            xhr = that.save();
        });
    };

    crmSettingsFieldEdit.prototype.initDeleteLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            xhr = null,
            href = $.crm.app_url + '?module=settings&action=fieldDeleteConfirm';

        $wrapper.on('click', '.crm-field-delete', function (e) {
            e.preventDefault();
            xhr = $.post(href, { id: that.field.id }, function(html) {
                new CRMDialog({
                    html: html,
                    options: {
                        edit_dialog: that.dialog
                    },
                    onOpen: function() {
                        that.dialog.$wrapper.hide();
                    }
                })
            });
        });
    };

    crmSettingsFieldEdit.prototype.initDisableLink = function () {
        var that = this,
            $form = that.$form,
            xhr = null;

        $form.on('click', '.crm-field-enable,.crm-field-disable', function (e) {
            e.preventDefault();

            if (xhr) {
                xhr.abort();
                xhr = null;
            }

            var $el = $(this),
                data = {
                    enable: $el.hasClass('crm-field-enable')
                };

            xhr = $.post($form.attr('action'), data, function (r) {
                if (r.status == 'ok') {
                    that.dialog.close();
                    location.href = $.crm.app_url + 'settings/field/';
                    return;
                }
            });
        });
    };

    crmSettingsFieldEdit.prototype.save = function () {
        var that = this,
            $form = that.$form;

        var $button = $(".crm-dialog-edit-field-wrapper").find(".js-save"),
            $loading = $('<i class="icon16 loading" style="vertical-align: middle;margin-left: 10px;"></i>');
        $('.loading').remove(); // remove old .loading

        $button.prop('disabled', true);

        // Validation
        var validation_passed = true;
        $form.find('.errormsg').text('');
        $form.find('.error').removeClass('error');
        $('[name$="[localized_names]"]').each(function() {
            var self = $(this);
            if (!self.val() && self.parents('.template').length <= 0) {
                if (self.closest('tr').find('[name$="[_disabled]"]:checked').length) {
                    validation_passed = false;
                    self.addClass('error').parent().append($('<em class="errormsg"></em>').text(that.locales["field_is_required"]));
                }
            }
        });

        if (!validation_passed) {
            $button.attr('disabled', false);
            return false;
        }

        $loading.appendTo($button.parent());

        return $.post($form.attr('action'), $form.serialize(), function (r) {

            if (r.status == 'ok') {
                $('.loading').remove();
                var $done = $('<i class="icon16 yes" style="vertical-align: middle;margin-left: 10px;"></i>');
                $.crm.content.reload();
                $done.appendTo($button.parent());
                setTimeout(function() {
                    that.dialog.close();
                    return;
                }, 1000);
            }

            if (r.status !== 'ok' && r.errors) {
                $button.removeProp('disabled');
                $('.loading').remove();
                for (var i = 0, l = r.errors.length; i < l; i += 1) {
                    var e = r.errors[i];
                    if (typeof e === 'string') {
                        $form.find('.errormsg.crm-common-errors').append(e);
                    } else if (typeof e === 'object') {
                        for (var k in e) {
                            if (e.hasOwnProperty(k)) {
                                var input = $form.find('[data-error-id="' + k + '"]');
                                input.addClass('error');
                                input.nextAll('.errormsg:first').text(e[k]);

                                $form.one('input, keydown', '.error', function () {
                                    $(this).removeClass('error')
                                        .nextAll('.errormsg:first').empty();
                                });
                            }
                        }
                    }
                }
                $form.find('[type=submit]').attr('disabled', false);
            }

        });
    };

    crmSettingsFieldEdit.prototype.initIDAutoFiller = function () {
        var that = this,
            transliterateTimer,
            $form = that.$form,
            $main_loc_input = $form.find('input[name^="name["][data-main-locale]'),
            $id_val_input = $form.find('input[name="id_val"]'),
            xhr = null,
            ns = '.crm-id-auto-filler';

        $id_val_input.on(
            'keydown.check_edited',
            function() {
                var $el = $(this);
                $el.data('val', $el.val());
            })
            .on(
            'keyup.check_edited',
            function() {
                var $el = $(this);
                if ($el.val() && $el.val() != $el.data('value')) {
                    $el.off('.check_edited');
                    $el.data('edited', 1);
                }
            });

        if ($id_val_input.prop('disabled') || $id_val_input.data('edited')) {
            return;
        }

        $form.on('keydown' + ns, 'input[name^="name["]',
            function() {
                var $input = $(this),
                    $submit = $form.find('[type="submit"]'),
                    $loading = $id_val_input.next('.loading');

                if (!$input.data('main-locale') && $main_loc_input.val()) {
                    return;
                }

                if ($id_val_input.prop('disabled') || $id_val_input.data('edited')) {
                    $form.off(ns);
                    return;
                }

                $submit.prop('disabled', true);

                $loading = $loading.length ? $loading : $('<i class="icon16 loading"></i>');
                $loading.insertAfter($id_val_input);

                transliterateTimer && clearTimeout(transliterateTimer);
                transliterateTimer = setTimeout(function () {

                    var clear = function () {
                        if (xhr) {
                            xhr.abort();
                            xhr = null;
                        }
                        transliterateTimer && clearTimeout(transliterateTimer);
                        $submit.prop('disabled', false);
                        $loading.remove();
                    };

                    if ($id_val_input.data('edited')) {
                        clear();
                        return;
                    }

                    xhr = $.post($.crm.app_url + '?module=settings&action=fieldTransliterate',
                        $form.find('input[name^="name["]').serialize(),
                        function (r) {
                            clear();
                            if (r.status === 'ok' && !$id_val_input.data('edited')) {
                                $id_val_input.val(r.data);
                            }
                        },
                        'json');

                }, 300);

            }
        );
    };


    crmSettingsFieldEdit.prototype.editSubFields = function () {
        var that = this,
            $wrapper = $('.crm-dialog-edit-field-wrapper'),
            $sub_table = $wrapper.find('.subfields-list > .ui-sortable'),
            max_field = 1;

        $sub_table.sortable({
            items : ".field-row",
            handle : ".js-subfield-sort",
            axis: 'y',
            update: function(event) {
                toggleButton(true);
            }
        });

        // Link to add new subfield
        $sub_table.on('click', 'a.js-add-subfield', function() {
            // Clone row template
            var tmpl = $sub_table.find('.field-row.template'),
                tr = tmpl.clone().insertBefore(tmpl).removeClass('template').removeClass('hidden');

            that.dialog.resize();

            // Replace field id placeholder with generated field id
            var fid = '__'+max_field;
            max_field++;
            tr.find('[name]').each(function() {
                var self = $(this);
                self.attr('name', self.attr('name').replace(/%FID%/g, fid));
            });
            tr.data('fieldId', fid);
            tr.find('select.type-selector').change();
            toggleButton(true);
            return false;
        });

        // Edit subfield
        $wrapper.on('click', '.edit', function() {
            $(this).parents('tr').addClass('editor-on').removeClass('editor-off');
            toggleButton(true);
            return false;
        });

        // Delete subfield
        $wrapper.on('click', '.js-delete-subfield', function() {
            var tr = $(this).closest('tr');
            if (tr.hasClass('just-added')) {
                tr.remove();
                return false;
            }

            $.crm.confirm.show({
                title: that.locales["delete_subfield_title"],
                text: that.locales["delete_subfield_text"],
                button: that.locales["delete_subfield_button"],
                onConfirm: function () {
                    tr.addClass('editor-off').removeClass('editor-on');
                    var name = tr.find('input:hidden[name$="[_disabled]"]').attr('name').replace("[_disabled]", "[_deleted]");
                    $('.js-field-form-edit').append($('<input type="hidden" name="" value="1">').attr('name', name));
                    tr.children().children(':not(label)').remove();
                    tr.find('label').addClass('gray').addClass('strike');
                    toggleButton(true);
                }
            });
        });

        // Just resize on click to 'add item'
        $sub_table.on('click', 'a.add-item', function() {
            that.dialog.resize();
        });

        // Load appropriate settings block when user changes field type
        $wrapper.on('change', 'select.type-selector', function() {
            var select = $(this);
            var tr = select.closest('tr');
            var table = tr.closest('table');
            var adv_settings_block = tr.find('.field-advanced-settings').html('<i class="icon16 loading"></i>');
            $.post($.crm.app_url + '?module=settings&action=fieldEditor', {
                ftype: select.val(),
                fid: tr.data('fieldId'),
                parent: table.data('fieldParent') || '',
                prefix: table.data('fieldPrefix')
            }, function(res) {
                adv_settings_block.html(res);
                toggleButton(true);
            });
        });

        $wrapper.on('change', ":checkbox, .name-input", function() {
            toggleButton(true);
        });

        function toggleButton(is_changed) {
            var button = $(".crm-dialog-edit-field-wrapper").find(".js-save");
            if (is_changed) {
                button.removeClass("green").addClass("yellow");
                button.removeAttr("disabled");
            } else {
                button.removeClass("yellow").addClass("green");
            }
        }
    };


    return crmSettingsFieldEdit;

})(jQuery);
