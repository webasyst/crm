var CRMTwitterPluginSettings = ( function($) {

    CRMTwitterPluginSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.parents('.crm-source-settings-form');

        // Inputs
        that.$name = that.$wrapper.find('.js-name-input');
        that.$consumer_key = that.$wrapper.find('.js-consumer-key-input');
        that.$consumer_secret = that.$wrapper.find('.js-consumer-secret-input');
        that.$access_token = that.$wrapper.find('.js-access-token-input');
        that.$access_token_secret = that.$wrapper.find('.js-access-token-secret-input');

        // Hidden inputs
        that.$account_name = that.$wrapper.find('.js-account-name-input');
        that.$username = that.$wrapper.find('.js-username-input');
        that.$userid = that.$wrapper.find('.js-userid-input');
        that.$last_direct_id = that.$wrapper.find('.js-last-direct-id-input');
        that.$last_mention_id = that.$wrapper.find('.js-last-mention-id-input');

        // VARS
        that.action = options["action"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTwitterPluginSettings.prototype.initClass = function() {
        var that = this;

        that.checkKeys();
    };

    CRMTwitterPluginSettings.prototype.checkKeys = function() {
        var that = this;

        that.$consumer_key.on('input', sendRequest);
        that.$consumer_secret.on('input', sendRequest);
        that.$access_token.on('input', sendRequest);
        that.$access_token_secret.on('input', sendRequest);

        function sendRequest() {
            var consumer_key = $.trim(that.$consumer_key.val()),
                consumer_secret = $.trim(that.$consumer_secret.val()),
                access_token = $.trim(that.$access_token.val()),
                access_token_secret = $.trim(that.$access_token_secret.val());

            //
            that.$account_name.val('');
            that.$username.val('');
            if (that.action == 'create') {
                that.$userid.val('');
            }
            //

            if (consumer_key.length > 10 && consumer_secret.length > 10 && access_token.length > 10 && access_token_secret.length > 10) {
                var href = $.crm.app_url + "?plugin=twitter&action=checkKeys",
                    data = {
                        consumer_key: consumer_key,
                        consumer_secret: consumer_secret,
                        access_token: access_token,
                        access_token_secret: access_token_secret
                    };

                $.post(href, data, function (res) {
                    if (res.status == 'ok' && res.data.screen_name) {
                        if (that.action == 'edit' && that.$userid.val() != res.data.id) {
                            // One source — one twitter user
                            $.crm.alert.show({
                                title: that.locales['alert_title'],
                                text: that.locales['alert_body'],
                                button: that.locales['alert_close']
                            });
                            return false;
                        }

                        that.$account_name.val($.crm.escape(res.data.name));
                        that.$username.val($.crm.escape(res.data.screen_name));
                        that.$userid.val($.crm.escape(res.data.id));

                        if (!that.$name.val().length) {
                            that.$name.val('@' + $.crm.escape(res.data.screen_name));
                        }

                        if (that.action == 'create') {
                            // Check old updates and save in source params
                            var updates_href = $.crm.app_url + "?plugin=twitter&action=getOldUpdates";
                            data.username = that.$username.val();
                            $.post(updates_href, data, function (res) {
                                if (res.status == 'ok' && res.data.last_direct_id && res.data.last_mention_id) {
                                    that.$last_direct_id.val($.crm.escape(res.data.last_direct_id));
                                    that.$last_mention_id.val($.crm.escape(res.data.last_mention_id));
                                }
                            });
                        }
                    }
                });
            }
        }
    };

    return CRMTwitterPluginSettings;

})(jQuery);

/* Conversation reply form in ./templates/source/message/ConversationReplyForm.html */
var CRMTwitterPluginConversationSenderForm = ( function($) {

    CRMTwitterPluginConversationSenderForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options['$wrapper'];
        that.$form = that.$wrapper.find('form');
        that.$textarea = that.$form.find('.js-textarea');

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTwitterPluginConversationSenderForm.prototype.initClass = function() {
        var that = this;

        that.initSubmit();

        that.initErrorCleaner();
    };

    CRMTwitterPluginConversationSenderForm.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $textarea = that.$textarea;

        $textarea.on("keydown", function(e) {
            var use_enter = (e.keyCode === 13 || e.keyCode === 10);
            if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
                e.preventDefault();
                that.$form.submit();
            }
        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            that.clearErrors();
            if (!is_locked) {

                is_locked = true;

                if (!$.trim($textarea.val())) {
                    $textarea.addClass('shake animated').focus();
                    setTimeout(function(){
                        $textarea.removeClass('shake animated').focus();
                    },500);
                    is_locked = false;
                    return;
                }

                var href = $.crm.app_url + "?plugin=twitter&action=sendReply",
                    data = that.$form.serializeArray();

                $.post(href, data, function(r) {
                    if (r.status === "ok") {
                        $.crm.content.reload();
                    } else {
                        that.showErrors(r.errors);
                        $textarea.addClass('shake animated').focus();
                        setTimeout(function(){
                            $textarea.removeClass('shake animated').focus();
                        },500);
                        is_locked = false;
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        });

        $textarea.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46);

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var value = $textarea.val(),
                        position = $textarea.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;

                    $textarea.val(result);
                }

                toggleHeight();

            } else if (is_backspace || is_delete) {
                toggleHeight();

            } else {

                if ($textarea[0].scrollHeight > $textarea.outerHeight()) {
                    toggleHeight();
                }

            }
        });

        function toggleHeight() {
            $textarea.css("min-height", 0);

            var scroll_h = $textarea[0].scrollHeight,
                limit = (18 * 8 + 8);

            if (scroll_h > limit) {
                scroll_h = limit;
            }

            scroll_h += 2;

            $textarea.css("min-height", scroll_h + "px");

            that.$wrapper.trigger("resize");
        }
    };

    CRMTwitterPluginConversationSenderForm.prototype.showErrors = function (errors) {
        return CRMTwitterPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMTwitterPluginConversationSenderForm.prototype.clearErrors = function () {
        return CRMTwitterPluginSenderHelper.clearErrors(this.$wrapper);
    };

    CRMTwitterPluginConversationSenderForm.prototype.initErrorCleaner = function () {
        return CRMTwitterPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    return CRMTwitterPluginConversationSenderForm;

})(jQuery);

/* Viewer dialog ./templates/source/message/TwitterImSourceMessageViewerDialog.html */
var CRMTwitterPluginViewerDialog = ( function($) {

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

    CRMTwitterPluginViewerDialog = function(options) {
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

    CRMTwitterPluginViewerDialog.prototype.initClass = function () {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.initMessageDeleteLink();
        that.initMessageReplyBtn();

        that.$wrapper.find('.crm-telegram-plugin-photo').TelegramImgLoad(function(){
            that.dialog.resize();
        });
    };

    CRMTwitterPluginViewerDialog.prototype.initMessageReplyBtn = function() {
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

    CRMMessageDeleteLinkMixin.mixInFor(CRMTwitterPluginViewerDialog);

    return CRMTwitterPluginViewerDialog;

})(jQuery);

/* Sender dialog ./templates/source/message/TwitterImSourceMessageSenderDialog.html */
var CRMTwitterPluginSenderDialog = ( function($) {

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

    CRMTwitterPluginSenderDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$textarea = that.$form.find('.js-textarea');
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.success_html = options.success_html || '';
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTwitterPluginSenderDialog.prototype.initClass = function () {
        var that = this;

        that.$textarea.focus();

        that.initSendMessage();
        //
        that.initErrorCleaner();
        //
        that.initResizeTextarea();
    };

    CRMTwitterPluginSenderDialog.prototype.initResizeTextarea = function () {
        var that = this,
            $textarea = that.$textarea;

        $textarea.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46);

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var value = $textarea.val(),
                        position = $textarea.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;

                    $textarea.val(result);
                }

                toggleHeight();

            } else if (is_backspace || is_delete) {
                toggleHeight();

            } else {

                if ($textarea[0].scrollHeight > $textarea.outerHeight()) {
                    toggleHeight();
                }

            }

            function toggleHeight() {
                $textarea.css("min-height", 0);

                var scroll_h = $textarea[0].scrollHeight,
                    limit = (18 * 8 + 8);

                if (scroll_h > limit) {
                    scroll_h = limit;
                }

                scroll_h += 2;

                $textarea.css("min-height", scroll_h + "px");

                that.$wrapper.trigger("resize");
            }
        });
    };

    CRMTwitterPluginSenderDialog.prototype.initSendMessage = function () {
        var that = this,
            $textarea = that.$textarea,
            $form = that.$form,
            $button = that.$button;

        $form.on("submit", function(e) {
            e.preventDefault();

            var xhr = null,
                $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>')
            $button.attr('disabled', true).after($loading);

            function onAlways() {
                $button.attr('disabled', false);
                $loading.remove();
                xhr = null;
            }

            function onDone(r) {
                if (r.status === "ok") {
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

                    $textarea.addClass('shake animated').focus();
                    setTimeout(function(){
                        $textarea.removeClass('shake animated').focus();
                    },500);

                    if (r.status !== "ok") {
                        onFail(r);
                        return;
                    }
                }
            }

            function onFail(r) {
                if (r && !$.isEmptyObject(r.errors)) {
                    that.showErrors(r.errors);
                } else {
                    console.error(r ? ["Server error", r] : "Server error");
                }
            }

            var href = $.crm.app_url + "?plugin=twitter&action=sendReply",
                data = that.$form.serializeArray();

            xhr && xhr.abort();
            xhr = $.post(href, data)
                .done(onDone)
                .fail(onFail)
                .always(onAlways);
        });

        $form.on('click', '.js-cancel-dialog', function () {
            that.$wrapper.css({'display': 'none'});
        });
    };

    CRMTwitterPluginSenderDialog.prototype.showErrors = function (errors) {
        return CRMTwitterPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMTwitterPluginSenderDialog.prototype.clearErrors = function () {
        return CRMTwitterPluginSenderHelper.clearErrors(this.$wrapper);
    };

    CRMTwitterPluginSenderDialog.prototype.initErrorCleaner = function () {
        return CRMTwitterPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    return CRMTwitterPluginSenderDialog;

})(jQuery);

// Static sender helpers
var CRMTwitterPluginSenderHelper = {
    showErrors: function ($wrapper, errors) {
        var $error_wrapper = $wrapper.find('.js-twitter-error-wrapper');
        $.each(errors, function (name, error) {
            var $field = $wrapper.find('[name="' + name + '"]'),
                $error = '<em class="errormsg">' + error + '</em>';
            if ($field.length) {
                $field.addClass('error');
                $field.after($error);
            } else {
                $error_wrapper.show().append($error);
            }
        });
    },
    clearErrors: function ($wrapper) {
        var $error_wrapper = $wrapper.find('.js-twitter-error-wrapper');
        $wrapper.find('.error').removeClass('error');
        $wrapper.find('.errormsg').remove();
        $error_wrapper.hide().empty();
    },
    initErrorCleaner: function ($wrapper) {

        function handler() {
            CRMTwitterPluginSenderHelper.clearErrors($wrapper);
        }

        var timer = null;
        $wrapper.on('keydown', ':input', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                handler();
            }, 250);
        });
        $wrapper.on('change', ':input', handler);
    }
};
