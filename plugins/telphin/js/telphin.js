/*
 * Called when user clicks a link to download call record.
 */
window.telphinHandleDownload = ( function($) { "use strict";

    var TelphinRecord = ( function($) {

        TelphinRecord = function(options) {
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

        TelphinRecord.prototype.initClass = function() {
            var that = this;

            that.load();

            that.$wrapper.on("click", function(event) {
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

        TelphinRecord.prototype.load = function() {
            var that = this;

            that.setState("loading");

            if (!that.is_locked) {
                that.is_locked = true;

                var href = $.crm.app_url + "?plugin=telphin&action=getRecordLink",
                    data = that.params;

                $.post(href, data, function(response) {
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
                .fail( function(xhr, error_str, exception) {
                    // Not an AJAX from server, or unable to connect
                    console.log('Unable to get record URL: ' + error_str, arguments);
                    alert('Error getting record URL: '+error_str);
                })
                .always( function() {
                    that.is_locked = false;
                });
            }

            function render(url) {
                var $audio = $('<audio controls><source src="' + url + '" type="audio/mpeg"></audio>');
                that.$audio = $audio;

                $audio.hide().appendTo(that.$wrapper);

                that.setState("pause");

                that.$call_wrapper.addClass(that.played_class);
                $audio.on('ended', function() {
                    that.setState("play");
                    that.$call_wrapper.removeClass(that.played_class);
                });

                $audio[0].play();
            }
        };

        TelphinRecord.prototype.setState = function(state) {
            var that = this,
                icon_class;

            switch(state) {
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

        return TelphinRecord;

    })($);

    function init(event, node, params) {
        event.preventDefault();

        var $link = $(node),
            is_inited = $link.data("inited");

        if (!is_inited) {
            $link.data("inited", true);

            new TelphinRecord({
                $wrapper: $link,
                params: params
            });
        }
    }

    return init;

}(jQuery));

var CRMTelphinPluginSettings = ( function($) {

    CRMTelphinPluginSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$ask_wp = that.$wrapper.find('.js-telphin-ask');
        that.$request_wp = that.$wrapper.find('.js-telphin-request');
        that.$settings_wp = that.$wrapper.find('.js-telphin-settings');

        // VARS
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTelphinPluginSettings.prototype.initClass = function() {
        var that = this;

        //
        that.checkApi();
        //
        that.initAsk();
        //
        that.saveRequest();
        //
        that.saveSettings();
    };

    CRMTelphinPluginSettings.prototype.checkApi = function() {
        var that = this,
            $api_status = that.$wrapper.find('.js-api-status'),
            href = "?plugin=telphin&module=checkApi";

        $.get(href, function (res) {
            if (res.data) {
                $api_status.text($api_status.data('ok')).css({color: 'green'});
            } else {
                $api_status.text($api_status.data('bad')).css({color: 'red'});
            }

            $api_status.show();
        });
    };

    CRMTelphinPluginSettings.prototype.initAsk = function() {
        var that = this,
            $request_btn = that.$ask_wp.find('.js-request'),
            $cancel_request_btn = that.$request_wp.find('.js-request-cancel'),
            $settings_btn = that.$ask_wp.find('.js-settings');

        // Show request form
        $request_btn.on('click', function () {
            that.$ask_wp.addClass('hidden');
            that.$request_wp.removeClass('hidden');
            that.$request_wp.find('#telphin_crm_telphin_person').focus();
        });

        // Hidden requrst form
        $cancel_request_btn.on('click', function () {
            that.$request_wp.addClass('hidden');
            that.$ask_wp.removeClass('hidden');
        });

        // Show settings form and save telphin_ask param in plugin settings
        $settings_btn.on('click', function () {
            that.$ask_wp.addClass('hidden');
            that.$settings_wp.removeClass('hidden');
            that.$settings_wp.find('#telphin_crm_telphin_api_app_id').focus();
            var href = '?plugin=telphin&action=saveAsk',
                data = {telphin_ask: 1};
            $.post(href, data);
        });
    };

    CRMTelphinPluginSettings.prototype.saveRequest = function() {
        var that = this,
            $form = that.$request_wp.find('form');

            $form.on('submit', function (e) {
                e.preventDefault();
                var href = '?plugin=telphin&action=saveRequestData',
                    data = $form.serializeArray();
                $.post(href, data, function (r) {
                    if (r.status == 'fail' && r.errors) {
                        $.each(r.errors, function (i, v) {
                            var $field = $form.find("#telphin_crm_telphin_"+v);
                            $field.addClass('shake animated');
                            setTimeout(function(){
                                $field.removeClass('shake animated');
                            },500);
                        });
                    }
                    if (r.status == 'ok') {
                        $.crm.alert.show({
                            title: that.locales['alert_title'],
                            text: that.locales['alert_body'],
                            button: that.locales['alert_close'],
                            onClose: function() { $.crm.content.reload(); }
                        });
                    }

                });
            });
    };

    CRMTelphinPluginSettings.prototype.saveSettings = function() {
        var that = this,
            $form = that.$settings_wp.find('form'),
            $form_status = $form.find('#plugins-settings-form-status'),
            $parent = $form_status.parents('div.value');

        $form.on('submit', function (e) {
            e.preventDefault();
            var href = "?module=plugins&id=telphin&action=save",
                data = $form.serializeArray(),
                $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-right: 6px;"></i>');

            $form_status.html($loading);

            $.post(href, data, function (r) {
                $parent.removeClass('errormsg successmsg status');
                if (r.status == 'ok' && r.data.message) {
                    $parent.addClass('successmsg');
                    $form_status.find(".icon16").attr("class", "icon16 yes");
                    $form_status.append($.crm.escape(r.data.message));
                } else {
                    $parent.addClass('errormsg');
                    $form_status.find(".icon16").attr("class", "icon16 no");
                    $form_status.append(that.locales['save_error']);
                }

                that.checkApi();

                setTimeout(function () {
                    $form_status.html('');
                    $parent.removeClass('errormsg successmsg status');
                }, 2000);
            });
        });

    };

    return CRMTelphinPluginSettings;

})(jQuery);