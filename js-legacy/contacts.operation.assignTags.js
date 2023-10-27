var CRMContactsOperationAssignTags = (function ($) {

    CRMContactsOperationAssignTags = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$input = that.$wrapper.find('.crm-tags-input');

        // VARS
        that.context = options.context || {};
        that.dialog = that.$wrapper.data('dialog');
        that.default_text = that.$input.attr('placeholder');
        that.is_assign = options.is_assign || false;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsOperationAssignTags.prototype.initClass = function () {
        var that = this;
        //
        that.initTagsInput();
        //
        that.initPopularTags();
        //
        that.initSave();
    };

    CRMContactsOperationAssignTags.prototype.initTagsInput = function () {
        var that = this,
            $input = that.$input,
            default_text = that.default_text,
            autocomplete_url = $.crm.app_url + '?module=contact&action=tags';

        $input.tagsInput({
            defaultText: default_text,
            width: that.$wrapper.find('.crm-dialog-content').width() - 16,
            autocomplete_url: '',
            autocomplete: {
                html: true,
                source: function (request, response) {
                    $.getJSON(autocomplete_url, { term: request.term },
                        function (r) {
                            if (r.status === 'ok') {
                                response(r.data.tags || []);
                            } else {
                                response([]);
                            }
                        });
                }
            }
        });
    };

    CRMContactsOperationAssignTags.prototype.initPopularTags = function () {
        var that = this,
            $input = that.$input,
            $popular_tags = that.$wrapper.find('.crm-popular-tags');

        $popular_tags.find('.crm-popular-tag-item-link').click(function () {
            var $link = $(this),
                val = $.trim($link.text());
            $input.removeTag(val);
            $input.addTag(val);
        });
    };

    CRMContactsOperationAssignTags.prototype.initSave = function () {
        var that = this,
            $button = that.$button,
            $input = that.$input,
            url = $.crm.app_url + '?module=contactOperation&action=assignTagsProcess',
            context = $.extend({}, that.context, true),
            $loading = that.$wrapper.find('.crm-loading');

        $button.click(function (e) {

            e.preventDefault();
            $loading.show();
            $button.attr('disabled', true);

            var $current = $('#' + $input.attr('id') + '_tag'),
                current_val = $.trim($current.val()),
                post_data = context;

            if (current_val && current_val !== that.default_text) {
                $input.addTag(current_val);
            }

            post_data.tags = $.trim($input.val());
            post_data.is_assign = that.is_assign ? 1 : 0;

            var done = function () {
                $loading.hide();
                $button.attr('disabled', false);
                $.crm.content.reload();
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

            step();

        });

    };

    return CRMContactsOperationAssignTags;

})(jQuery);
