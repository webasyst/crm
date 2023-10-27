var CRMContactsOperationExcludeFromSegment = (function ($) {

    CRMContactsOperationExcludeFromSegment = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$add_new_link = that.$wrapper.find('.crm-add-new-segment-link');

        // VARS
        that.context = options.context || {};
        that.segment = options.segment || {};
        that.dialog = that.$wrapper.data('dialog');

        /**
         * @type CRMContactsSidebar
         */
        that.sidebar = options.sidebar || null;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsOperationExcludeFromSegment.prototype.initClass = function () {
        var that = this;

        that.initStartExclude();
    };

    CRMContactsOperationExcludeFromSegment.prototype.initStartExclude = function () {
        var that = this,
            $button = that.$button,
            url = $.crm.app_url + '?module=contactOperation&action=excludeFromSegmentProcess',
            $loading = that.$wrapper.find('.crm-loading'),
            post_data = $.extend({}, that.context);

        post_data.segment_id = that.segment.id;

        var done = function (data) {
            $loading.hide();
            $button.attr('disabled', false);

            that.segment = data.segment;

            var context = {
                type: 'segment',
                info: {
                    id: that.segment.id
                }
            };
            that.sidebar.updateItem(context, that.segment);
            that.dialog.close();
            $.crm.content.load($.crm.app_url + 'contact/segment/' + that.segment.id + '/');
        };

        var step = function (offset, process_count) {
            var data = $.extend({ offset: offset || 0, process_count: process_count || 0 }, post_data);
            $.post(url, data)
                .done(function (r) {
                    if (r.status == 'ok') {
                        if (r.data.done) {
                            done(r.data);
                        } else {
                            step(r.data.offset, r.data.process_count);
                        }
                    }
                });
        };

        $button.click(function (e) {
            e.preventDefault();
            $loading.show();
            $button.attr('disabled', true);
            step();
        });
    };

    return CRMContactsOperationExcludeFromSegment;

})(jQuery);
