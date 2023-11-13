var CRMImSourceSendMessageDialog = ( function($) {

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

    CRMImSourceSendMessageDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.success_html = options.success_html || '';
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        that.send_action_url = options["send_action_url"];
        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMImSourceSendMessageDialog.prototype.initClass = function () {
        var that = this;

        that.initSendMessage();

        that.initErrorCleaner();
    };

    CRMImSourceSendMessageDialog.prototype.initErrorCleaner = function () {
        var that = this,
            $wrapper = that.$wrapper;

        function handler() {
            that.clearErrors();
        }

        var timer = null;
        $wrapper.on('keydown', ':input', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                handler();
            }, 250);
        });
        $wrapper.on('change', ':input', handler);
    };

    CRMImSourceSendMessageDialog.prototype.initSendMessage = function () {
        var that = this,
            $form = that.$form,
            $button = that.$button;

        $form.on("submit", function(e) {
            e.preventDefault();

            var xhr = null,
                $loading = $('<span class="icon size-16"><i class="fas fa-spinner wa-animation-spin custom-mr-4"></i></span>')
            $button.attr('disabled', true).after($loading);

            function onAlways() {
                $button.attr('disabled', false);
                $loading.remove();
                xhr = null;
            };

            function onDone(r) {
                if (r.status !== "ok") {
                    onFail(r);
                    return;
                }

                that.$wrapper.find(".dialog-body").html(that.success_html);
                that.dialog.resize();

                var auto_close_timer = null;
                    //old_close_func = that.dialog.onClose;
                that.dialog.onClose = function() {
                    //old_close_func();
                    auto_close_timer && clearTimeout(auto_close_timer);
                    auto_close_timer = null;
                    that.crm.content.reload();
                };

                auto_close_timer = setTimeout(function () {
                    that.dialog.close();
                }, 2500);
            };

            function onFail(r) {
                if (r && !$.isEmptyObject(r.errors)) {
                    that.showErrors(r.errors);
                } else {
                    console.error(r ? ["Server error", r] : "Server error");
                }
            };

            xhr && xhr.abort();
            xhr = $.post(that.send_action_url, that.$form.serializeArray())
                .done(onDone)
                .fail(onFail)
                .always(onAlways);
        });

        $form.on('click', '.js-cancel-dialog', function () {
            that.$wrapper.css({'display': 'none'});
        });
    };

    CRMImSourceSendMessageDialog.prototype.showErrors = function (errors) {
        var that = this,
            $wrapper = that.$wrapper;
        $.each(errors, function (name, error) {
            var $field = $wrapper.find('[name="' + name + '"]'),
                $error = '<span class="errormsg">' + error + '</span>';
            if ($field.length) {
                $field.addClass('state-error');
                $field.after($error);
            } else {
                $wrapper.find('.js-errors-place').append($error);
            }
        });
    };

    CRMImSourceSendMessageDialog.prototype.clearErrors = function () {
        var that = this,
            $wrapper = that.$wrapper;
        $wrapper.find('.state-error').removeClass('state-error');
        $wrapper.find('.errormsg').remove();
        $wrapper.find('.js-errors-place').empty();
    };

    return CRMImSourceSendMessageDialog;

})(jQuery);
