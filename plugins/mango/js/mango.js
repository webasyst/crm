/*
 * Called when user clicks a link to download call record.
 */
window.mangoHandleDownload = ( function($) { "use strict";

    var MangoRecord = ( function($) {

        MangoRecord = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$icon = that.$wrapper.find(".icon16");
            that.$audio = false; // will be rendered after load;

            // VARS
            that.params = options["params"];

            // DYNAMIC VARS
            that.state = null;
            that.icon_class = "play";
            that.locked = false;

            // INIT
            that.initClass();
        };

        MangoRecord.prototype.initClass = function() {
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

        MangoRecord.prototype.load = function() {
            var that = this;

            that.setState("loading");

            if (!that.is_locked) {
                that.is_locked = true;

                var href = $.crm.app_url + "?plugin=mango&action=getRecordLink",
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

                $audio.on('ended', function() {
                    that.setState("play");
                });

                $audio[0].play();
            }
        };

        MangoRecord.prototype.setState = function(state) {
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

        return MangoRecord;

    })($);

    function init(event, node, params) {
        event.preventDefault();

        var $link = $(node),
            is_inited = $link.data("inited");

        if (!is_inited) {
            $link.data("inited", true);

            new MangoRecord({
                $wrapper: $link,
                params: params
            });
        }
    }

    return init;

}(jQuery));