var YandextelephonySettings = (function ($) {

    YandextelephonySettings = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(".js-save");

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    YandextelephonySettings.prototype.initClass = function () {
        var that = this;

        that.checkApi();

        that.$form.on('input', function () {
            that.tweakButton(true);
        });
        //
        that.initSubmit();
    };

    YandextelephonySettings.prototype.checkApi = function() {
        var that = this,
            $api_status = that.$wrapper.find('.js-api-status'),
            href = "?plugin=yandextelephony&module=checkApi";

        $.get(href, function (res) {
            if (res.data) {
                $api_status.text($api_status.data('ok')).css({color: 'green'});
            } else {
                $api_status.text($api_status.data('bad')).css({color: 'red'});
            }

            $api_status.show();
        });
    };

    YandextelephonySettings.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            $form_status = $form.find('#plugins-settings-form-status'),
            $parent = $form_status.parents('div.value');

        $form.on('submit', function (e) {
            e.preventDefault();
            var href = "?module=plugins&id=yandextelephony&action=save",
                data = $form.serializeArray(),
                $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-right: 6px;"></i>');

            $form_status.html($loading);

            $.post(href, data, function (r) {
                $parent.removeClass('errormsg successmsg status');
                if (r.status == 'ok' && r.data.message) {
                    that.tweakButton();
                    $parent.addClass('successmsg');
                    $form_status.find(".icon16").attr("class", "icon16 yes");
                    $form_status.append($.crm.escape(r.data.message));
                } else {
                    $parent.addClass('errormsg');
                    $form_status.find(".icon16").attr("class", "icon16 no");
                    $form_status.append('Error');
                }

                that.checkApi();

                setTimeout(function () {
                    $form_status.html('');
                    $parent.removeClass('errormsg successmsg status');
                }, 2000);
            });
        });
    };

    YandextelephonySettings.prototype.tweakButton = function (is_yellow) {
        var that = this,
            $button = that.$button;

        if (is_yellow) {
            $button.removeClass('green').addClass('yellow');
        } else {
            $button.removeClass('yellow').addClass('green');
        }
    };

    return YandextelephonySettings;

})(jQuery);