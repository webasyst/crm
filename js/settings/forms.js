var CRMSettingsForms = ( function($) {

    CRMSettingsForms = function (options) {
        var that = this;

        that.messages = options.messages || {};

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('[type=submit]');
        that.$delete_link = that.$wrapper.find('.crm-delete-form-link');

        // DYNAMIC VARS
        that.submit_xhr = null;

        // INIT
        that.initClass();
    };

    CRMSettingsForms.prototype.initClass = function () {
        var that = this;
        //
       // $.crm.renderSVG(that.$wrapper);

       that.initDeleteLink();
    };

    CRMSettingsForms.prototype.initDeleteLink = function () {
        const that = this;
        that.$delete_link.click(function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            const form_id = $(this).closest('[data-id]').data('id');

            $.crm.confirm.show({
                title: that.messages['delete_confirm_title'],
                text: that.messages['delete_confirm_text'],
                button: that.messages['delete_confirm_button'],

                onConfirm: function() {
                    const $dialog_wrapper = $('.crm-confirm-dialog'),
                        $loading = $dialog_wrapper.find('.crm-loading').show(),
                        $button = $dialog_wrapper.find('.js-confirm-dialog').attr('disabled', true);

                    $.post($.crm.app_url + '?module=settings&action=formDelete', { id: form_id })
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

    return CRMSettingsForms;

})(jQuery);
