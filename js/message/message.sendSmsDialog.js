var CRMSendSmsDialog = ( function($) {

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

    CRMSendSmsDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$wrapper.find(".js-send-sms-textarea");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.success_html = options["success_html"];
        that.error_html = options["error_html"];
        that.hash = options["hash"];
        that.send_action_url = options["send_action_url"];
        that.crm = getCRM();
        that.action = options["action"];
        that.locales = options["locales"] || {};

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // INIT
        that.initClass();
    };

    CRMSendSmsDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initSenderSelector();
        //
        that.initSendMessage();
        //
        that.dialog.resize();
    };

    CRMSendSmsDialog.prototype.initSenderSelector = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $specified_sender = $wrapper.find('.js-specified-sms-sender');

        $wrapper.find('.js-sms-sender-list').change(function () {
            var $selector = $(this);
            if ($selector.val() === 'specified') {
                $specified_sender.attr('disabled', false).show();
            } else {
                $specified_sender.attr('disabled', true).hide();
            }
        });
    };

    CRMSendSmsDialog.prototype.initSendMessage = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", function(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var $submitButton = that.$form.find(".js-submit-button"),
                    $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');

                $submitButton.removeClass("blue").attr("disabled", true);
                $loading.insertAfter($submitButton);

                var href = that.send_action_url,
                    data = that.$form.serializeArray();

                var onFail = function(log_object) {

                    var title = that.locales.send_error_title || "Send error",
                        text = that.locales.send_error_text || "Can't send sms message",
                        $error_html = $(that.error_html).find('.js-error-text').text(text).end();


                    $.crm.alert.show({
                        title: title,
                        text: $error_html.get(0).outerHTML,
                        button_class: 'red'
                    });

                    if (log_object) {
                        console.error(log_object);
                    }
                };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.$wrapper.find(".crm-dialog-block").html(that.success_html);
                        that.dialog.resize();

                        var auto_close_timer = null,
                            old_close_func = that.dialog.onClose;
                        that.dialog.onClose = function() {
                            old_close_func();
                            auto_close_timer && clearTimeout(auto_close_timer);
                            auto_close_timer = null;
                            that.crm.content.reload();
                        };

                        auto_close_timer = setTimeout(function () {
                            that.dialog.close();
                        }, 2500);
                    } else {
                        that.dialog.close();
                        onFail(response.errors || {});
                    }
                }, "json")
                    .always( function () {
                        is_locked = false;
                    })
                    .error(function (response) {
                        that.dialog.close();
                        onFail(response);
                    });
            }
        });
    };

    return CRMSendSmsDialog;

})(jQuery);
