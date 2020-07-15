var CRMMessageDeleteLinkMixin = ( function($) {

    CRMMessageDeleteLinkMixin = function () {
        // empty constructor, cause it's mixin
    };

    CRMMessageDeleteLinkMixin.prototype.initMessageDeleteLink = function () {

        var that = this,
            $delete_link = that.$wrapper.find('.js-delete-message');

        $delete_link.on('click', function () {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales['delete_message'],
                text: that.locales['delete_message_text'],
                button: that.locales['delete'],
                onConfirm: deleteMessage
            });
        });

        function deleteMessage() {

            var href = $.crm.app_url + "?module=message&action=delete",
                data = {id: that.message.id};

            var $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 12px;"></i>');
            $loading.appendTo(that.$button.parent());

            that.$button.attr("disabled", true);

            $.post(href, data, function (response) {
                if (response.status === "ok") {
                    $.crm.content.reload();
                } else {
                    console.log('Error saving Responsible contact classification: ' + arguments[2], arguments);
                    showError("Error saving Responsible contact classification: " + arguments[2]);
                }
            }, "json").always(function () {
                that.$button.attr("disabled", false);
                $loading.remove();
            });
        }

        function showError(error) {
            var $error = $("<div class=\"errormsg\" />").text(error);
            that.$errorsPlace.append($error);
        }
    };

    CRMMessageDeleteLinkMixin.mixInFor = function (Class) {
        Class.prototype = $.extend(Class.prototype, CRMMessageDeleteLinkMixin.prototype);
        return Class;
    };

    return CRMMessageDeleteLinkMixin;

})(jQuery);
