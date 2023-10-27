var CRMContactsOperationAddToSegments = (function ($) {

    CRMContactsOperationAddToSegments = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$add_new_link = that.$wrapper.find('.crm-add-new-segment-link');

        // VARS
        that.context = options.context || {};
        that.dialog = that.$wrapper.data('dialog');
        that.url = $.crm.app_url + '?module=contactOperation&action=addToSegments';
        that.is_assign = options.is_assign ? 1 : 0;

        that.onSave = options.onSave || null;

        /**
         * @type CRMContactsSidebar
         */
        that.sidebar = options.sidebar || null;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsOperationAddToSegments.prototype.initClass = function () {
        var that = this;

        if (!that.is_assign) {
            that.initButton();
        }

        //
        that.initSave();
        //
        that.initAddNewLink();
    };

    CRMContactsOperationAddToSegments.prototype.initButton = function () {
        var that = this,
            handler = function () {
                that.$wrapper.find('[name="segment[]"]:checked').length > 0
                    ? that.$button.attr('disabled', false)
                    : that.$button.attr('disabled', true);
            };
        that.$wrapper.on('click', '[name="segment[]"]', handler);
        handler();
    };

    CRMContactsOperationAddToSegments.prototype.initSave = function () {
        var that = this,
            $button = that.$button,
            $loading = that.$wrapper.find('.crm-loading'),
            url = $.crm.app_url + '?module=contactOperation&action=addToSegmentsProcess',
            segment_ids = [],
            post_data = $.extend({}, that.context, true);

        post_data.is_assign = that.is_assign ? 1 : 0;

        var done = function (data) {
            $loading.hide();
            $button.attr('disabled', false);

            if (that.sidebar) {
                $.each(data.segments || {}, function (i, segment) {
                    var context = {
                        type: 'segment',
                        info: {
                            id: segment.id
                        }
                    };
                    that.sidebar.updateItem(context, segment);
                });
            }

            if (post_data.is_assign) {
                $.crm.content.reload();
            } else if (segment_ids.length == 1) {
                $.crm.content.load($.crm.app_url + 'contact/segment/' + segment_ids[0] + '/');
            } else {
                $.crm.content.load($.crm.app_url);
            }
        };

        var step = function (offset) {
            var data = $.extend({ offset: offset || 0 }, post_data);
            $.post(url, data)
                .done(function (r) {
                    if (r.status == 'ok') {
                        if (r.data.done) {
                            done(r.data);
                        } else {
                            step(r.data.offset || 0);
                        }
                    }
                });
        };
        
        $button.click(function (e) {
            e.preventDefault();
            $loading.show();
            $button.attr('disabled', true);

            segment_ids = that.$wrapper.find('[name="segment[]"]:checked').map(function () {
                return $(this).val();
            }).toArray();

            post_data = $.extend({ segment_ids: segment_ids }, post_data);
            if (that.onSave && that.onSave(post_data, that.$wrapper) === false) {
                return;
            }

            step();
        });
    };

    CRMContactsOperationAddToSegments.prototype.reloadDialog = function (onOpen) {
        var that = this;
        $.get(that.url, function (html) {
            that.dialog = new CRMDialog({
                html: html,
                onOpen: function ($dialog) {
                    onOpen && onOpen($dialog);
                    new CRMContactsOperationAddToSegments({
                        '$wrapper': $dialog,
                        'context': that.context,
                        'sidebar': that.sidebar,
                        'onSave': that.onSave
                    });
                }
            });
        });
    };

    CRMContactsOperationAddToSegments.prototype.initAddNewLink = function () {
        var that = this,
            $link = that.$add_new_link;
        $link.click(function (e) {

            e.preventDefault();
            that.$wrapper.find('.crm-add-new-segment-loading').show();

            $.get($.crm.app_url + '?module=contactSegment&action=edit')
                .done(function (html) {

                    that.dialog.close();

                    var dialog = new CRMDialog({
                        html: html,
                        onOpen: function ($wrapper) {
                            $wrapper.find('.js-close-dialog').hide();
                            $wrapper.find('.crm-cancel-link').show().click(function () {
                                $wrapper.find('.crm-cancel-loading').show();
                                that.reloadDialog(function () {
                                    dialog.close();
                                });
                            });
                        },
                        options: {
                            afterSave: function (r) {
                                that.reloadDialog(function ($wrapper) {
                                    dialog.close();
                                    if (r.data.segment) {
                                        $wrapper.find('input[name="segment[]"][value="' + r.data.segment.id + '"]').prop('checked', true);
                                    }

                                });
                                return false;
                            }
                        }
                    });
                });
        });
    };

    return CRMContactsOperationAddToSegments;

})(jQuery);
