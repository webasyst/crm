var CRMSettingsPbxPage = ( function($) {

    CRMSettingsPbxPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.locales = options["locales"];
        that.user_template_html = options["user_template_html"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsPbxPage.prototype.initClass = function() {
        var that = this;

        //
        var $pairs = that.$wrapper.find(".c-pair-wrapper");
        $pairs.each( function() {
            that.initPair( $(this) );
        });
    };

    CRMSettingsPbxPage.prototype.initPair = function($pair) {
        var that = this,
            plugin_id = $pair.data("plugin-id"),
            number = $pair.data("number"),
            is_locked = false;

        $pair.on("click", ".js-user-delete", showConfirm);

        $pair.on("click", ".js-delete-pbx-num", deletePbxNum);

        initUserAdd();

        function showConfirm(event) {
            event.preventDefault();

            var $link = $(this),
                $user = $link.closest(".c-user-wrapper"),
                $name = $user.find(".js-name"),
                $number = $pair.find(".js-number"),
                name = $name.html();

            $.waDialog.confirm({
                title: that.locales["delete_confirm_title"].replace("%name%", name).replace("%number%", $number.html()),
                text: that.locales["delete_confirm_text"],
                success_button_title: that.locales["delete_confirm_button"],
                success_button_class: 'danger',
                cancel_button_title: that.locales["dialog_error_button"],
                cancel_button_class: 'light-gray',
                onSuccess: deleteUser
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
                        else {
                            showErrors(response.errors)
                        }
                    }).always( function() {
                        is_locked = false;
                    });
                }
            }

            function renderUser(user) {
                var $user = $(that.user_template_html);
                if (user["photo_url"]) {
                    $user.find(".userpic i").css("background-image", "url(" + user["photo_url"] + ")");
                }
                $user.find(".js-name").text(user.name);
                $user.data("id", user.id);
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

        function showErrors(errors) {
            $.each(errors, function (index, item) {
                $.waDialog.alert({
                    title: that.locales["dialog_error_title"],
                    text: item,
                    button_title: that.locales["dialog_error_button"],
                    button_class: 'warning',
                });
                    setTimeout(()=> {$('.js-dialog-close').click();}, 5000)
                })
        }
    };

    return CRMSettingsPbxPage;

})(jQuery);