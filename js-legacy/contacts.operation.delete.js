var CRMContactsOperationDelete = (function ($) {

    CRMContactsOperationDelete = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.crm-delete');

        // VARS
        that.context = options.context || {};
        that.dialog = that.$wrapper.data('dialog');
        that.is_contact_page = options.is_contact_page || 0;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsOperationDelete.prototype.initClass = function () {
        var that = this;
        //
        that.initStartDelete();
    };


    CRMContactsOperationDelete.prototype.initStartDelete = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $button = that.$button,
            $loading = $('.crm-loading', $wrapper),
            url = $.crm.app_url + '?module=contactOperation&action=deleteProcess',
            post_data = $.extend({}, that.context, true);

        $button.click(function (e) {
            e.preventDefault();

            $loading.show();
            $button.attr('disabled', true);

            $.post(url, post_data)
                .done(function (r) {
                        that.dialog.close();
                        if (r.status === 'ok') {
                            if (that.is_contact_page) {
                                $.crm.content.load($.crm.app_url + 'contact/');
                            } else {
                                $.crm.content.reload();
                            }
                            $.crm.sidebar.reload();
                        }
                    })
                .always(function () {
                    $loading.hide();
                    $button.attr('disabled', false);
                });
        });
    };

    return CRMContactsOperationDelete;

})(jQuery);
