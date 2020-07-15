var crmSettingsFieldDeleteConfirm = (function ($) {

    var crmSettingsFieldDeleteConfirm = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');

        // VARS
        that.dialog = that.$wrapper.data('dialog');
        that.edit_dialog = that.dialog.options.edit_dialog;
        
        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    crmSettingsFieldDeleteConfirm.prototype.initClass = function () {
        var that = this;
        that.bindEvents();
    };

    crmSettingsFieldDeleteConfirm.prototype.bindEvents = function () {
        var that = this,
            $form = that.$form;

        that.$form.find('.crm-cancel').click(function () {
            that.dialog.close();
            that.edit_dialog.$wrapper.show();
        });

        $form.submit(function (e) {
            e.preventDefault();
            that.save();
        });
    };

    crmSettingsFieldDeleteConfirm.prototype.save = function () {
        var that = this,
            $form = that.$form;

        $.post($form.attr('action'), $form.serialize(), function () {
            that.dialog.close();
            that.edit_dialog.close();
            location.href = $.crm.app_url + 'settings/field/';
        });
    };

    return crmSettingsFieldDeleteConfirm;

})(jQuery);
