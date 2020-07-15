/*
 * Called when user clicks a link to download call record.
 */
window.sipuniHandleDownload = ( function($) { "use strict";

    var SipuniRecord = ( function($) {

        SipuniRecord = function(options) {
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

        SipuniRecord.prototype.initClass = function() {
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

        SipuniRecord.prototype.load = function() {
            var that = this;

            that.setState("loading");

            if (!that.is_locked) {
                that.is_locked = true;

                var href = $.crm.app_url + "?plugin=sipuni&action=getRecordLink",
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

        SipuniRecord.prototype.setState = function(state) {
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

        return SipuniRecord;

    })($);

    function init(event, node, params) {
        event.preventDefault();

        var $link = $(node),
            is_inited = $link.data("inited");

        if (!is_inited) {
            $link.data("inited", true);

            new SipuniRecord({
                $wrapper: $link,
                params: params
            });
        }
    }

    return init;

}(jQuery));

var SipuniAddEmployeesNum = ( function($) {

    SipuniAddEmployeesNum = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(".js-save");
        that.$num_list = that.$form.find('.js-pairs-table');
        that.$input = that.$form.find('.js-empty');
        that.$add = that.$form.find('.js-add');

        // VARS
        that.$template = that.$form.find('.js-template');
        that.user_template_html = options["user_template_html"];
        that.save_hint = options["save_hint"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    SipuniAddEmployeesNum.prototype.initClass = function() {
        var that = this;

        that.initAddNum();
        //
        that.initSubmit();
        //
        var $pairs = that.$wrapper.find(".c-pair-wrapper");
        $pairs.each( function() {
            that.initPair( $(this) );
        });
    };

    SipuniAddEmployeesNum.prototype.initAddNum = function() {
        var that = this;

        that.$add.on('click', function () {
            addNum();
        });
        //
        that.$input.on('focusout', function () {
            addNum();
        });
        //
        that.$input.on('keydown', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                addNum();
            }
        });

        function addNum() {
            var val = $.trim(that.$input.val()),
                $template = that.$template.clone(),
                $t_span = $template.find('.js-number'),
                $t_input = $template.find('.js-input');

            if (val.length !== 3 || val < 200 || val > 999) {
                return false;
            }

            if(val) {
                $t_span.text(val);
                $t_input.val($.crm.escape(val));
                $template.data('number', $.crm.escape(val)).removeClass('js-template').addClass('c-pair-wrapper');
                $template.find('.js-user-add-wrapper').html(that.save_hint);
                that.$template.before($template);
                that.$input.val('').focus();
                that.toggleButton(true);
                var $pairs = that.$wrapper.find(".c-pair-wrapper");
                $pairs.each( function() {
                    that.initPair( $(this) );
                });
            }
        }
    };

    SipuniAddEmployeesNum.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin: 0;"></i>');

        that.$form.on('submit', function (e) {
            e.preventDefault();
            if (!is_locked) {
                is_locked = true;
                that.$button.attr("disabled", true);
                $loading.appendTo(that.$button.parent());

                var href = "?plugin=sipuni&module=settingsSave",
                    data = that.$form.serializeArray();

                $.post(href, data, function (r) {
                    that.$button.attr("disabled", false);
                    is_locked = false;
                    $('i.loading').remove();
                    if (r.status == "ok") {
                        $.crm.content.reload();
                    }
                }, "json").always(function () {
                    that.$button.attr("disabled", false);
                    is_locked = false;
                    $('i.loading').remove();
                });
            }
        });
    };

    SipuniAddEmployeesNum.prototype.initPair = function($pair) {
        var that = this,
            plugin_id = $pair.data("plugin-id"),
            number = $pair.data("number"),
            is_locked = false;

        $pair.on("click", ".js-user-delete", showConfirm);

        $pair.on("click", ".js-delete-num", deletePbxNum);

        initUserAdd();

        function showConfirm(event) {
            event.preventDefault();

            var $link = $(this),
                $user = $link.closest(".c-user-wrapper"),
                $name = $user.find(".js-name"),
                $number = $pair.find(".js-number"),
                name = $name.html();

            $.crm.confirm.show({
                title: that.locales["delete_confirm_title"].replace("%name%", name).replace("%number%", $number.html()),
                text: that.locales["delete_confirm_text"],
                button: that.locales["delete_confirm_button"],
                onConfirm: deleteUser
            });

            function deleteUser() {
                var user_id = $user.data("id");

                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settingsPBX&action=delete",
                        data = {
                            p: plugin_id,
                            n: number,
                            u: user_id
                        };

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            $user.remove();
                        }
                    }, "json").always( function() {
                        is_locked = false;
                    });
                }
            }
        }

        function initUserAdd() {
            var $wrapper = $pair.find(".js-user-add-wrapper"),
                $list = $pair.find(".js-users-list"),
                is_locked = false;

            $wrapper.on("click", ".js-show-combobox", function(event) {
                event.stopPropagation();
                showToggle(true);
            });

            $wrapper.on("click", ".js-hide-combobox", function(event) {
                event.stopPropagation();
                showToggle(false);
            });

            initAutocomplete();

            function showToggle( show ) {
                var active_class = "is-shown";
                if (show) {
                    $wrapper.addClass(active_class);
                } else {
                    $wrapper.removeClass(active_class);
                }
            }

            function initAutocomplete() {
                var $autocomplete = $wrapper.find(".js-autocomplete");

                if ($autocomplete.length) {
                    $autocomplete
                        .autocomplete({
                            appendTo: $wrapper,
                            source: $.crm.app_url + "?module=autocomplete&type=user",
                            minLength: 0,
                            html: true,
                            focus: function () {
                                return false;
                            },
                            select: function (event, ui) {
                                setContact(ui.item);
                                showToggle(false);
                                $autocomplete.val("");
                                return false;
                            }
                        }).data("ui-autocomplete")._renderItem = function (ul, item) {
                        return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
                    };

                    $autocomplete.on("focus", function () {
                        $autocomplete.data("uiAutocomplete").search($autocomplete.val());
                    });
                }
            }

            function setContact(user) {
                var errors = validate(user);
                if (errors) {
                    return false;
                }
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settingsPBX&action=add",
                        data = {
                            p: plugin_id,
                            n: number,
                            u: user.id
                        };

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            renderUser(user);
                        }
                    }).always( function() {
                        is_locked = false;
                    });
                }
            }

            // Let's check if there is already such
            // a user in the list of recipients
            function validate(user) {
                var this_user = $list.find("li[data-id='"+ user.id +"']");
                return this_user.length;
            }

            function renderUser(user) {
                var $user = $(that.user_template_html);
                if (user["photo_url"]) {
                    $user.find(".userpic20").css("background-image", "url(" + user["photo_url"] + ")");
                }
                $user.find(".js-name").text(user.name);
                $user.attr("data-id", user.id);
                $list.append($user);
            }
        }

        function deletePbxNum() {
            var $link = $(this),
                $tr = $link.parents('tr'),
                href = "?module=settingsPBX&action=deleteNumber",
                data = {
                    p: plugin_id,
                    n: number
                };

            $tr.addClass('hidden');

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $tr.remove();
                } else {
                    $tr.removeClass('hidden');
                }
            }, "json").always( function() {
                $tr.removeClass('hidden');
            });
        }
    };

    SipuniAddEmployeesNum.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$button;

        if (is_changed) {
            $button.removeClass("green").addClass("yellow").removeAttr('disabled');
        } else {
            $button.removeClass("yellow").addClass("green").addAttr('disabled');
        }
    };

    return SipuniAddEmployeesNum;

})(jQuery);