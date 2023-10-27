var CRMNotificationEdit = (function ($) {

    CRMNotificationEdit = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$footer = that.$wrapper.find(".js-footer-actions");

        that.messages = options.messages || {};
        that.messages.enable = that.messages.enable || '';
        that.messages.disable = that.messages.disable || '';

        //
        that.$name = that.$wrapper.find(".js-notification-name");
        that.$subject = that.$wrapper.find(".js-notification-subject");
        that.$emailBody = that.$wrapper.find(".js-email-body");
        that.$smsBody = that.$wrapper.find(".js-sms-body");
        that.$recipient = that.$wrapper.find('.js-recipient-list');

        that.$sender = that.$wrapper.find('.js-sender-list');
        that.$sender_block = that.$wrapper.find('.js-senders-block');

        that.$sms_sender = that.$wrapper.find('.js-sms-sender-list');
        that.$sms_sender_block = that.$wrapper.find('.js-sms-senders-block');

        // VARS
        that.notifications = options["notifications"];
        that.locales = options["locales"];
        that.notification = options["notification"] || {};
        that.notification_id = that.notification && that.notification.id;
        that.notification_event = that.notification && that.notification.event;
        that.site_app_url = options.site_app_url;
        that.recipients = options['recipients'];
        that.sender = options['sender'].split('|', 2);
        that.user_data = options['user_data'];
        that.dialog_template = options['dialog_template'];
        that.success_html = options['success_html'];

        // DYNAMIC VARS
        that.transport = '';

        // INIT
        that.initClass();
    };

    CRMNotificationEdit.prototype.initClass = function () {
        var that = this;


        if (that.notification.id <= 0) {
            that.initTransport();
        }

        //
        that.initRecipient();
        //
        that.initBody();
        //
        // only for new notification
        if (!that.notification_id) {
            that.initChangeEvent();
        }
        //
        that.initSubmit();
        //
        that.initDelete();
        //
        that.initFooterActions();
        //
        that.initHelp();
        //
        that.initSender();
        //
        that.initSendTest();
    };

    CRMNotificationEdit.prototype.initSendTest = function () {
        var that = this,
            is_locked = false,
            contact = null;

        that.$wrapper.on("click", ".js-send-test", sendNotification);

        function sendNotification(event) {
            event.preventDefault();

            $(document).trigger("updateEditorTextarea");

            $.crm.confirm.show({
                title: that.locales["send_confirm_title"],
                text: getHtml(),
                button: that.locales["send_confirm_button"],
                onConfirm: request
            });

            function getHtml() {
                var $template = $(that.dialog_template),
                    transportVal = that.$wrapper.find(".js-transport-toggle").filter(":checked").first().val();

                if (transportVal == 'sms') {
                    contact = that.user_data['phone'];
                } else {
                    contact = that.user_data['email'];
                }
                $template.find('.js-user-contact').attr('value', contact);

                return $template["0"].outerHTML;
            }

            function request(dialog) {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settingsNotifications&action=SendTest",
                        $form = that.$form,
                        data = $form.serializeArray();

                    data.push({
                        name: 'data[contact]',
                        value: dialog.$block.find('.js-user-contact').val()
                    });

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            dialog.$wrapper.find(".crm-dialog-block").html(that.success_html);
                            dialog.resize();

                            setTimeout( function() {
                                var is_exist = $.contains(document, dialog.$wrapper[0]);
                                if (is_exist) {
                                    dialog.close();
                                }
                            }, 10000);
                        }
                    }, "json").always( function() {
                        is_locked = false;
                    });
                }

                return false;
            }
        }
    };

    CRMNotificationEdit.prototype.initSender = function () {
        var that = this,
            notification = that.notification,
            $transportToggle = that.$wrapper.find(".js-transport-toggle");

        function getTransportVal() {
            if (notification && notification.id > 0) {
                return notification.transport || 'email';
            }
            return $transportToggle.filter(":checked").first().val();
        }

        function onChange() {
            if (getTransportVal() === 'email') {
                showEmailSenders(that.$sender.val() === 'specified');
                hideSmsSenders();
            } else {
                showSmsSenders(that.$sms_sender.val() === 'specified');
                hideEmailSenders();
            }
        }

        function showEmailSenders(is_specified) {
            // inputs for specified sender (name and email parts)
            var $name = that.$sender_block.find('.js-specified-sender-name'),
                $email = that.$sender_block.find('.js-specified-sender-email');

            that.$sender_block.show();
            that.$sender.attr('disabled', false);

            if (is_specified) {
                $name.attr('disabled', false).show();
                $email.attr('disabled', false).show();
                if (that.sender[0] !== 'system') {
                    $email.val(that.sender[0]);
                    $name.val(that.sender[1]);
                }
            } else {
                $name.attr('disabled', true).hide();
                $email.attr('disabled', true).hide();
            }
        }

        function hideEmailSenders() {
            that.$sender_block.hide();
            that.$sender.attr('disabled', true);
            that.$sender_block.find('.js-specified-sender-name').attr('disabled', true).hide();
            that.$sender_block.find('.js-specified-sender-email').attr('disabled', true).hide();
        }

        function showSmsSenders(is_specified) {
            // input for specified sender
            var $sender = that.$sms_sender_block.find('.js-specified-sms-sender');

            that.$sms_sender_block.show();
            that.$sms_sender.attr('disabled', false);

            if (is_specified) {
                $sender.attr('disabled', false).show();
                if (that.sender[0] !== 'system') {
                    $sender.val(that.sender[0]);
                }
            } else {
                $sender.attr('disabled', true).hide();
            }
        }

        function hideSmsSenders() {
            that.$sms_sender_block.hide();
            that.$sms_sender.attr('disabled', true);
            that.$sms_sender_block.find('.js-specified-sms-sender').attr('disabled', true).hide();
        }

        // init state
        if (getTransportVal() === 'email') {
            showEmailSenders(that.$sender.val() === 'specified');
            hideSmsSenders();
        } else {
            showSmsSenders(that.$sms_sender.val() === 'specified');
            hideEmailSenders();
        }

        // init transport toggle
        $transportToggle.on("change", onChange);

        // init sender selectors
        that.$sender.on("change", onChange);
        that.$sms_sender.on("change", onChange);

    };

    CRMNotificationEdit.prototype.initRecipient = function () {
        var that = this,
            recipient = that.notification.recipient,
            recipients_list = that.recipients,
            $recipientToggle = that.$recipient,
            $transportToggle = that.$wrapper.find(".js-transport-toggle");

        $recipientToggle.on("change", onChange);
        $transportToggle.on("change", onChange);

        // Initial values
        if (!recipients_list[recipient]) {
            that.$recipient.val("other");
            setRecipient("other");
            that.$wrapper.find(".c-recipient-content[data-id=\"" + $transportToggle.filter(":checked").first().val() + "\"]").val(recipient);
        }

        function onChange() {
            setRecipient(that.$recipient.val());
        }

        function setRecipient(recipientValue) {
            var transport = $transportToggle.filter(":checked").first().val();

            if (!transport && that.notification.id > 0) {
                transport = that.notification.transport;
            }

            var $content = that.$wrapper.find(".c-recipient-content[data-id=\"" + transport + "\"]");

            var $recipient_input = that.$wrapper.find(".c-recipient-content");
            $recipient_input.hide().attr("disabled", true);

            if (recipientValue === 'other') {
                $content.show();
                $content.attr("disabled", false)
            } else {
                $content.hide();
            }
        }
    };

    CRMNotificationEdit.prototype.initChangeEvent = function () {
        var that = this,
            $eventToggle = that.$wrapper.find(".js-event-toggle"),
            $companySelector = that.$wrapper.find(".js-event-company"),
            $fieldsGroup = that.$wrapper.find(".js-fields-group");

        $eventToggle.on("change", onChange);

        function onChange() {
            var event_id = $.trim($(this).val());

            that.notification_event = event_id;

            var notification = that.notifications[event_id];
            if (notification) {
                // names
                that.$name.val(notification.name);
                that.$subject.val(notification.subject);

                // body
                that.$emailBody.val(notification.body).trigger("editorDataUpdated");
                that.$smsBody.val(notification.sms).trigger("editorDataUpdated");

                if (notification.recipient) {
                    that.$recipient.val(notification.recipient);
                } else {
                    that.$recipient.val('client');
                }
            }

            if (event_id.substr(0, 8) === 'invoice.') {
                $companySelector.removeAttr('disabled').show();
            } else {
                $companySelector.attr('disabled', true).hide();
            }

            $fieldsGroup.show();
        }
    };

    CRMNotificationEdit.prototype.initTransport = function () {
        var that = this,
            $transportToggle = that.$wrapper.find(".js-transport-toggle"),
            $activeContent = false,
            active_class = "is-active";

        setTransport($transportToggle.filter(":checked").first());

        $transportToggle.on("change", function () {
            setTransport($(this));
            that.$emailBody.trigger("editorDataUpdated");
            that.$smsBody.trigger("editorDataUpdated");
        });

        function setTransport($input) {
            var value = $input.val();

            that.transport = value;

            var $content = that.$wrapper.find(".c-transport-content[data-id=\"" + value + "\"]");

            if ($activeContent) {
                $activeContent.removeClass(active_class);
                $activeContent.find("input, textarea").attr("disabled", true);
            } else {
                that.$wrapper.find(".c-transport-content").find("input, textarea").attr("disabled", true);
            }

            $content.find("input, textarea").attr("disabled", false);
            $activeContent = $content.addClass(active_class);
        }
    };

    CRMNotificationEdit.prototype.initBody = function () {
        var that = this,
            $redactorW = that.$wrapper.find(".js-redactor-wrapper");

        $redactorW.each(function (index) {
            var $wrapper = $(this),
                $textarea = $wrapper.find("textarea"),
                $redactor = $("<div class=\"js-redactor\" />");

            var textarea_id = "js-textarea-" + index,
                redactor_id = "js-redactor-" + index;

            $redactor.attr("id", redactor_id).appendTo($wrapper);
            $textarea.attr("id", textarea_id);

            waEditorAceInit({
                "id": textarea_id,
                "ace_editor_container": redactor_id
            });

            if (typeof wa_editor !== "undefined") {
                $redactor.data("wa_editor", wa_editor);
            }

            $textarea.data('wa_editor', $redactor.data('wa_editor'));

            $textarea.on("editorDataUpdated", function () {
                var editor = $redactor.data("wa_editor");
                if (editor) {
                    editor.getSession().setValue($textarea.val());
                }
            });

            $(document).on("updateEditorTextarea", function () {
                var editor = $redactor.data("wa_editor");
                if (editor) {
                    var data = editor.getValue();
                    $textarea.val(data).trigger("change");
                }
            });
        });
    };

    CRMNotificationEdit.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();
            $(document).trigger("updateEditorTextarea");

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {

            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function (index, item) {
                result.data.push(item);
            });

            return result;
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                var keys = Object.keys(ajax_errors);
                $.each(keys, function (index, name) {
                    errors.push({
                        name: name,
                        value: ajax_errors[name]
                    })
                });
            }

            $.each(errors, function (index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);

                    // var field_o = $field.offset(),
                    //     wrapper_o = that.$wrapper.offset(),
                    //     top = field_o.top - wrapper_o.top + $field.outerHeight(),
                    //     left = field_o.left - wrapper_o.left;
                    //
                    // $text.css({
                    //     left: left + "px",
                    //     top: top + "px"
                    // });

                    that.$wrapper.append($text);

                    $field
                        .addClass(error_class)
                        .one("focus click change", function () {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=notificationsEditSave";

                var saving = that.locales.saving,
                    $loading = $(saving);
                that.$footer.append($loading);

                $.post(href, data, 'json')
                    .done(function (response) {

                        if (response.status !== "ok") {
                            showErrors(response.errors || {});
                            return;
                        }

                        if (that.notification_id <= 0) {
                            var content_uri = $.crm.app_url + "settings/notifications/edit/" + response.data.notification.id + "/";
                            $.crm.content.load(content_uri);
                            return;
                        }

                        var saved = that.locales.saved,
                            $saved = $(saved);
                        that.$footer.append($saved);

                        setTimeout(function () {
                            var is_exist = $.contains(document, $saved[0]);
                            if (is_exist) {
                                $saved.remove();
                            }
                        }, 2000);

                        $(document).trigger("formIsSaved");
                    })
                    .always(function () {
                        $loading.remove();
                        is_locked = false;
                    });
            }
        }
    };

    CRMNotificationEdit.prototype.initDelete = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-remove-notification", deleteNotification);

        function deleteNotification(event) {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales["delete_confirm_title"],
                text: that.locales["delete_confirm_text"],
                button: that.locales["delete_confirm_button"],
                onConfirm: request
            });

            function request(dialog) {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settings&action=notificationsDelete",
                        data = {
                            id: that.notification_id
                        };

                    $.post(href, data, function (response) {
                        if (response.status === "ok") {
                            var content_uri = $.crm.app_url + "settings/notifications/";
                            $.crm.content.load(content_uri);
                        }
                    }).always(function () {
                        is_locked = false;
                        dialog.close();
                    });
                }
                return false;
            }
        }
    };

    CRMNotificationEdit.prototype.initFooterActions = function () {
        var that = this,
            $footer = that.$footer,
            $submitButton = $footer.find(".js-submit-button");

        that.$wrapper.on("change", "input, textarea, select", function () {
            setActive();
        });

        $(document).on("formIsSaved", function () {
            setActive(true);
        });

        function setActive(green) {
            var default_class = "green",
                changed_class = "yellow";

            if (green) {
                $submitButton
                    .removeClass(changed_class)
                    .addClass(default_class);
            } else {
                $submitButton
                    .removeClass(default_class)
                    .addClass(changed_class);
            }
        }


    };

    CRMNotificationEdit.prototype.initHelp = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $document = $(document),
            $help = $("#wa-editor-help"),
            $help_link = $("#wa-editor-help-link");

        var listener = function (e) {
            // auto-off event-handler from global object
            if ($document.find($wrapper).length <= 0) {
                $document.off('click', listener);
                return;
            }
            // click outside of help area lead to close help area
            var $target = $(e.target);
            if (!$target.is($help) && $help.find($target).length <= 0) {
                $help.hide();
            }
        };

        $document.on('click', listener);

        $help_link.on('click', function () {
            if ($help.is(":visible")) {
                $help.hide();
                return false;
            }

            if ($help.data('loaded.' + that.notification_event)) {
                $help.show();
                return false;
            }

            var url = that.site_app_url + '?module=pages&action=help',
                data = 'app=crm&key=notification.' + that.notification_event;
            $help.load(url, data, function () {
                $help.find('#wa-help-wa').remove();
                $help.find('#wa-help-wa-content').remove();
                $help.show();
                $help.find('ul>li.no-tab>p.bold').hide();
                $help.data('loaded.' + that.notification_event, true);
            });
            return false;
        });

        $help.on('click', "div.fields a.inline-link", function (e) {
            e.preventDefault();

            var $el = $(this).find('i'),
                $body = that.$emailBody;

            if (that.transport === 'sms') {
                $body = that.$smsBody;
            }

            var editor = $body.data("wa_editor");
            if (editor) {
                editor.insert($.trim($el.text()));
            }
        });

    };

    return CRMNotificationEdit;

})
(jQuery);

var CRMNotificationStatus = (function ($) {

    CRMNotificationStatus = function (options) {
        var that = this;

// DOM
        that.$wrapper = options["$wrapper"];

        that.messages = options.messages || {};
        that.messages.enable = that.messages.enable || '';
        that.messages.disable = that.messages.disable || '';

// VARS
        that.notifications = options["notifications"];

// INIT
        that.initDisableLinks();
    };


    CRMNotificationStatus.prototype.initDisableLinks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            xhr = null,
            url = $.crm.app_url + '?module=settingsNotifications&action=status';

        $wrapper.on('click', '.js-c-disable-link', function (e) {
            e.preventDefault();

            var $link = $(this),
                $item = $link.closest('.c-notification'),
                id = $item.data('id'),
                is_disabled = $item.hasClass('c-is-disabled'),
                $loading = $item.find('.c-loading');

            xhr && xhr.abort();
            $loading.show();

            xhr = $.post(url, {id: id, status: is_disabled ? 0 : 1})
                .done(function (r) {
                    if (r.status !== 'ok') {
                        return;
                    }
                    var is_disabled = r.data.notification.status > 0 ? 0 : 1;
                    if (is_disabled) {
                        $link.text(that.messages.enable);
                        $item.addClass('c-is-disabled');
                    } else {
                        $link.text(that.messages.disable);
                        $item.removeClass('c-is-disabled');
                    }
                })
                .always(function () {
                    xhr = null;
                    $loading.hide();
                })
        });
    };

    return CRMNotificationStatus;

})(jQuery);
