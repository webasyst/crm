var CRMMessageDeleteLinkMixin = ( function($) {

    CRMMessageDeleteLinkMixin = function () {
        // empty constructor, cause it's mixin
    };

    CRMMessageDeleteLinkMixin.prototype.initMessageDeleteLink = function () {

        var that = this,
            $delete_link = that.$wrapper.find('.js-delete-message');

        $delete_link.on('click', function (event) {
            event.preventDefault();
            message_id = $(this).data('message-id') || ((that.message && that.message.id) ? that.message.id : null);
            $.crm.confirm.show({
                title: that.locales['delete_message'],
                text: that.locales['delete_message_text'],
                button: that.locales['delete'],
                onConfirm: deleteMessage
            });
        });

        function deleteMessage() {
            if (!message_id) {
                console.log(that.locales['message_not_found'] || "Message not found");
                showError(that.locales['message_not_found'] || "Message not found");
                return false;
            }

            var href = $.crm.app_url + "?module=message&action=delete",
                data = {id: message_id};

            var $loading = $('<span class="icon loading"><i class="fas fa-spinner wa-animation-spin"></i></span>');
            var $button = that.$button || $('.js-confirm-button');
            $loading.appendTo($button.parent());

            $button.attr("disabled", true);

            $.post(href, data, function (response) {
                if (response.status === "ok") {
                    $.crm.content.reload();
                    $('.dialog.c-message-show-body-dialog')?.data('dialog')?.close();
                } else {
                    console.log('Error saving Responsible contact classification: ' + arguments[2], arguments);
                    showError("Error saving Responsible contact classification: " + arguments[2]);
                }
            }, "json").always(function () {
                $button.attr("disabled", false);
                $loading.remove();
            });
        }

        function showError(error) {
            if (that.$errorsPlace) {
                var $error = $("<div class=\"errormsg\" />").text(error);
                that.$errorsPlace.append($error);
            }
        }
    };

    CRMMessageDeleteLinkMixin.mixInFor = function (Class) {
        Class.prototype = $.extend(Class.prototype, CRMMessageDeleteLinkMixin.prototype);
        return Class;
    };

    return CRMMessageDeleteLinkMixin;

})(jQuery);
