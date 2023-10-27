var CRMImSourceMessageDialog = ( function($) {

    // helper
    function getCRM() {
        var crm = false;
        if (window && window.parent && window.parent.$ && window.parent.$.crm) {
            crm = window.parent.$.crm;
        } else if (window.$ && window.$.crm) {
            crm = window.$.crm;
        }
        return crm;
    }

    CRMImSourceMessageDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-reply-button');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.send_dialog = null;
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMImSourceMessageDialog.prototype.initClass = function () {
        var that = this;

        that.initMessageDeleteLink();
        that.initMessageReplyBtn();
    };

    CRMImSourceMessageDialog.prototype.initMessageReplyBtn = function() {
        var that = this,
            $wrapper = that.$wrapper,
            dialog = that.dialog,
            $footer = $wrapper.find('.js-dialog-footer'),
            $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>'),
            $button = $wrapper.find('.js-reply-button');

        $button.click(function () {
            $button.attr('disabled', true);
            var onShowSendDialog = function () {
                $loading.remove();
                $button.attr('disabled', false);
                dialog.hide();
            };
            if (that.send_dialog) {
                that.send_dialog.show();
                onShowSendDialog();
            } else {
                loadSendDialog(function() {
                    onShowSendDialog();
                    var onClose = that.dialog.onClose;
                    that.dialog.onClose = function () {
                        onClose.apply(this, arguments);

                        // to prevent recursion in onClose callbacks
                        that.dialog = null;

                        // close send_dialog if not closed yet
                        that.send_dialog && that.send_dialog.close();
                        that.send_dialog = null;
                    };
                });
            }
        });

        function loadSendDialog(whenLoaded) {
            $footer.append($loading);
            var href = that.app_url+'?module=message&action=writeReplyDialog',
                params = { id: that.message.id };
            $.post(href, params, function(html) {
                new CRMDialog({
                    html: html,
                    onOpen: function ($dialog, send_dialog) {
                        that.send_dialog = send_dialog;
                        $dialog.find('.js-cancel-dialog').click(function () {
                            that.send_dialog.hide();
                            dialog.show();
                        });
                        whenLoaded && whenLoaded();
                    },
                    onClose: function () {

                        // to prevent recursion in onClose callbacks
                        that.send_dialog = null;

                        // close dialog if not closed yet
                        that.dialog && that.dialog.close();
                        that.dialog = null;
                    }
                });
            });
        };
    };

    CRMMessageDeleteLinkMixin.mixInFor(CRMImSourceMessageDialog);

    return CRMImSourceMessageDialog;

})(jQuery);
