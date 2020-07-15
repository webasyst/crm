/*
 * Called when user clicks a link to download call record.
 */
window.gravitelHandleDownload = (function ($) {
    "use strict";

    var GravitelRecord = (function ($) {

        GravitelRecord = function (options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$call_wrapper = that.$wrapper.parents('tr[data-id]');
            that.$icon = that.$wrapper.find(".icon16");
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

        GravitelRecord.prototype.initClass = function () {
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

        GravitelRecord.prototype.load = function () {
            var that = this;

            that.setState("loading");

            if (!that.is_locked) {
                that.is_locked = true;

                var href = $.crm.app_url + "?plugin=gravitel&action=getRecordLink",
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

        GravitelRecord.prototype.setState = function (state) {
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

        return GravitelRecord;

    })($);

    function init(event, node, params) {
        event.preventDefault();

        var $link = $(node),
            is_inited = $link.data("inited");

        if (!is_inited) {
            $link.data("inited", true);

            new GravitelRecord({
                $wrapper: $link,
                params: params
            });
        }
    }

    return init;

}(jQuery));

var GravitelSettings = (function ($) {

    GravitelSettings = function (options) {
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

    GravitelSettings.prototype.initClass = function () {
        var that = this;

        that.checkApi();

        that.$form.on('input', function () {
            that.tweakButton(true);
        });

        //
        that.initCrmKeyGenerator();
        //
        that.initSubmit();
    };

    GravitelSettings.prototype.initCrmKeyGenerator = function () {
        var that = this,
            $link = that.$wrapper.find('.js-generate'),
            $input = that.$wrapper.find('#gravitel_crm_gravitel_crm_key');

        $link.on('click', function () {
            var now = new Date(),
                new_key = now * Math.random() * 2 * 5;
            $input.val(new_key);
            that.tweakButton(true);
        });

    };

    GravitelSettings.prototype.checkApi = function() {
        var that = this,
            $api_status = that.$wrapper.find('.js-api-status'),
            href = "?plugin=gravitel&module=checkApi";

        $.get(href, function (res) {
            if (res.data) {
                $api_status.text($api_status.data('ok')).css({color: 'green'});
            } else {
                $api_status.text($api_status.data('bad')).css({color: 'red'});
            }

            $api_status.show();
        });
    };

    GravitelSettings.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            $form_status = $form.find('#plugins-settings-form-status'),
            $parent = $form_status.parents('div.value');

        $form.on('submit', function (e) {
            e.preventDefault();
            var href = "?module=plugins&id=gravitel&action=save",
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

    GravitelSettings.prototype.tweakButton = function (is_yellow) {
        var that = this,
            $button = that.$button;

        if (is_yellow) {
            $button.removeClass('green').addClass('yellow');
        } else {
            $button.removeClass('yellow').addClass('green');
        }
    };

    return GravitelSettings;

})(jQuery);
