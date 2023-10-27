var CRMContactSegmentEdit = (function ($) {

    CRMContactSegmentEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(':submit');
        that.$icons_block = that.$wrapper.find('.crm-icons-block');
        that.$icon_input = that.$wrapper.find('[name=icon]');

        // VARS
        that.segment = options.segment || {};
        that.messages = options.messages || {};
        that.dialog = that.$wrapper.data('dialog');

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactSegmentEdit.prototype.initClass = function () {
        var that = this;

        that.$wrapper.find('.crm-name-input').focus();

        //
        that.initIcons();
        //
        that.initSubmit();
        //
        that.bindEvents();
        //
        if (that.segment.id > 0) {
            that.initDeleteLink();
        }
    };

    CRMContactSegmentEdit.prototype.initIcons = function () {
        var that = this;

        that.$icons_block.on('click', 'li a', function (e) {
            e.preventDefault();
            var $el = $(this),
                $li = $el.closest('li'),
                val = $li.data('icon');
            that.$icons_block.find('li.selected').removeClass('selected');
            that.$icon_input.val(val);
            $li.addClass('selected');
            that.clearValidateErrors();
        });

    };

    CRMContactSegmentEdit.prototype.bindEvents = function () {
        var that = this,
            $form = that.$form,
            timer = null;

        $form.on('change', ':input', function () {
            that.clearValidateErrors();
        });

        $form.on('keyup', ':input', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                that.clearValidateErrors();
            }, 200);
        });

    };

    CRMContactSegmentEdit.prototype.showValidateErrors = function (errors) {
        var that = this,
            $form = that.$form;
        $.each(errors || {}, function (name, msg) {
            var $input = $form.find(':input[name="' + name + '"]').addClass('error');
            $input.after('<em class="errormsg">' + msg + '</em>');
        });
    };

    CRMContactSegmentEdit.prototype.clearValidateErrors = function () {
        var that = this,
            $form = that.$form;
        $form.find('.error').removeClass('error');
        $form.find('.errormsg').remove();
    };

    CRMContactSegmentEdit.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            $icon = $form.find('.crm-loading'),
            $button = that.$button;

        $form.submit(function (e) {
            e.preventDefault();

            that.clearValidateErrors();
            $button.attr('disabled', true);
            $icon.show();

            var after = function (r) {
                if (!r.data.segment) {
                    return;
                }

                var just_created = that.segment.id <= 0,
                    is_save_as_filter = that.segment.type == 'search',
                    type = r.data.segment.type === 'category' ? 'category' : 'search',
                    id = r.data.segment.id;

                if (just_created) {
                    if (type === 'search' && !is_save_as_filter) {
                        $.crm.content.load($.crm.app_url + 'contact/search/segment/' + r.data.segment.id + '/');
                        return;
                    }
                    $.crm.storage.set('crm/create/category', { id: id, timestamp: (+new Date()) });
                }

                $.crm.content.load($.crm.app_url + 'contact/segment/' + r.data.segment.id + '/');
            };

            $.post($form.attr('action'), $form.serialize())
                .done(function (r) {
                    if (r.status !== 'ok') {
                        $button.attr('disabled', false);
                        $icon.hide();
                        that.showValidateErrors(r.errors || {});
                        return;
                    }
                    if (!that.dialog.options.afterSave || that.dialog.options.afterSave(r) !== false) {
                        after(r);
                    }
                });
        });
    };

    CRMContactSegmentEdit.prototype.initDeleteLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-delete-link'),
            confirm_xhr = null,
            confirm_url = $.crm.app_url + '?module=dialogConfirm',
            delete_xhr = null,
            delete_url = $.crm.app_url + '?module=contactSegment&action=delete';

        $link.click(function (e) {
            e.preventDefault();

            confirm_xhr && confirm_xhr.abort();

            var data = {
                title: that.messages['delete_confirm_title'],
                text: that.messages['delete_confirm_text'],
                ok_button: that.messages['delete_button']
            };

            confirm_xhr = $.post(confirm_url, data)
                .done(function(html) {

                    that.dialog.close();

                    new CRMDialog({
                        html: html,
                        onConfirm: function() {
                            delete_xhr && delete_xhr.abort();
                            delete_xhr = $.post(delete_url, { id: that.segment.id })
                                .done(function (r) {
                                    if (r.status === 'ok') {
                                        $.crm.content.load($.crm.app_url + 'contact/');
                                    }
                                })
                                .always(function () {
                                    delete_xhr = null;
                                });
                            return false;
                        }
                    });
                })
                .always( function() {
                    confirm_xhr = null;
                });

        });


    };

    return CRMContactSegmentEdit;

})(jQuery);
