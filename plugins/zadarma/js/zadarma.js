/*
 * Called when user clicks a link to download call record.
 */
window.zadarmaHandleDownload = (function ($) {
    "use strict";

    var ZadarmaRecord = (function ($) {

        ZadarmaRecord = function (options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$icon = that.$wrapper.find(".icon16");
            that.$call_wrapper = that.$wrapper.parents('tr[data-id]');
            that.$audio = false; // will be rendered after load;

            // VARS
            that.params = options["params"];

            // DYNAMIC VARS
            that.state = null;
            that.icon_class = "play";
            that.played_class = "on-playing";
            that.locked = false;

            // INIT
            that.initClass();
        };

        ZadarmaRecord.prototype.initClass = function () {
            var that = this;

            that.load();

            that.$wrapper.on("click", function (event) {
                event.preventDefault();

                if (!that.is_locked) {
                    if (that.state === "play") {
                        that.setState("pause");
                    } else {
                        that.setState("play");
                    }

                    if (that.$audio.length) {
                        if (that.$audio.prop('paused')) {
                            that.$audio.trigger('play');
                        } else {
                            that.$audio.trigger('pause');
                        }
                    }
                }
            });
        };

        ZadarmaRecord.prototype.load = function () {
            var that = this;

            that.setState("loading");

            if (!that.is_locked) {
                that.is_locked = true;

                var href = $.crm.app_url + "?plugin=zadarma&action=getRecordLink",
                    data = that.params;

                $.post(href, data, function (response) {
                    // All fine, start downloading
                    if (response.status === 'ok' && response.data.record_url) {
                        render(response.data.record_url);

                        // Something's wrong...
                    } else {
                        console.log('Unable to get record URL', response);
                        if (response.errors) {
                            alert(response.errors.join ? response.errors.join("\n") : response.errors);
                        } else {
                            alert('Error getting record URL');
                        }
                    }
                }, "json")
                    .fail(function (xhr, error_str, exception) {
                        // Not an AJAX from server, or unable to connect
                        console.log('Unable to get record URL: ' + error_str, arguments);
                        alert('Error getting record URL: ' + error_str);
                    })
                    .always(function () {
                        that.is_locked = false;
                    });
            }

            function render(url) {
                var $audio = $('<audio controls><source src="' + url + '" type="audio/mpeg"></audio>');
                that.$audio = $audio;

                $audio.hide().appendTo(that.$wrapper);

                that.setState("pause");

                that.$call_wrapper.addClass(that.played_class);
                $audio.on('ended', function () {
                    that.setState("play");
                    that.$call_wrapper.removeClass(that.played_class);
                });

                $audio[0].play();
            }
        };

        ZadarmaRecord.prototype.setState = function (state) {
            var that = this,
                icon_class;

            switch (state) {
                case "play":
                    icon_class = "play";
                    break;
                case "pause":
                    icon_class = "pause";
                    break;
                case "loading":
                    icon_class = "loading";
                    break;
                default:
                    icon_class = "";
            }

            // render
            that.$icon.removeClass(that.icon_class).addClass(icon_class);

            // set vars
            that.state = state;
            that.icon_class = icon_class;
        };

        return ZadarmaRecord;

    })($);

    function init(event, node, params) {
        event.preventDefault();

        var $link = $(node),
            is_inited = $link.data("inited");

        if (!is_inited) {
            $link.data("inited", true);

            new ZadarmaRecord({
                $wrapper: $link,
                params: params
            });
        }
    }

    return init;

}(jQuery));

var ZadarmaSettings = ( function($) {

    ZadarmaSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(".js-save");

        that.$key = that.$form.find('#zadarma_crm_zadarma_key');
        that.$secret = that.$form.find('#zadarma_crm_zadarma_secret');
        that.$instruction = that.$wrapper.find('.js-zadarma-instruction');

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    ZadarmaSettings.prototype.initClass = function() {
        var that = this;

        if ($.trim(that.$key.val()) && $.trim(that.$secret.val())) {
            that.checkConnections();
        }

        that.$form.on('input', function () {
            that.tweakButton(true);
        });

        //
        that.initSubmit();
    };

    ZadarmaSettings.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            $form_status = $form.find('#plugins-settings-form-status'),
            $parent = $form_status.parents('div.value');

        $form.on('submit', function (e) {
            e.preventDefault();
            var href = "?module=plugins&id=zadarma&action=save",
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

                that.checkConnections();

                setTimeout(function () {
                    $form_status.html('');
                    $parent.removeClass('errormsg successmsg status');
                }, 2000);
            });
        });
    };

    ZadarmaSettings.prototype.checkConnections = function() {
        var that = this,
            $deferred = that.checkApi();

        $deferred.done(function(api_status) {
            if (!api_status) {
                that.$instruction.hide();
                return false;
            }

            that.checkNumbers();
        });
    };

    ZadarmaSettings.prototype.checkApi = function() {
        var that = this,
            $deferred = $.Deferred(),
            $api_status = that.$wrapper.find('.js-api-status'),
            href = "?plugin=zadarma&module=checkApi";

        $.get(href, function (res) {
            if (res.data) {
                $api_status.text($api_status.data('ok')).css({color: 'green'});
            } else {
                $api_status.text($api_status.data('bad')).css({color: 'red'});
            }

            $api_status.show();
            $deferred.resolve(res.data);
        });

        return $deferred;
    };

    ZadarmaSettings.prototype.checkNumbers = function () {
        var that = this,
            href = "?plugin=zadarma&module=pbxUsers";

        $.get(href, function (res) {
            if ($.isEmptyObject(res.data)) {
                that.$instruction.show();
            } else {
                that.$instruction.hide();
            }
        });
    };

    ZadarmaSettings.prototype.tweakButton = function (is_yellow) {
        var that = this,
            $button = that.$button;

        if (is_yellow) {
            $button.removeClass('green').addClass('yellow');
        } else {
            $button.removeClass('yellow').addClass('green');
        }
    };

    return ZadarmaSettings;

})(jQuery);