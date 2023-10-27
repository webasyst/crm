var CRMReminders = (function ($) {

    CRMReminders = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$completedSection = that.$wrapper.find(".c-completed-reminders-section");

        // VARS
        that.user_id = options["user_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminders.prototype.initClass = function () {
        var that = this;
        //
        that.initAddReminder();
        //
        that.initReopenReminder();
        //
        that.initElastic();
        //
        if (that.user_id && that.$completedSection.length) {
            that.initCompletedReminders();
        }
        //
        that.initReminderSettings();
        //
        that.initTarget();
    };

    CRMReminders.prototype.initAddReminder = function () {
        var that = this,
            $wrapper = $("#c-add-reminder-form"),
            $textarea = $wrapper.find(".js-textarea"),
            $icon = $wrapper.find(".c-icon-column .icon16"),
            $form = $wrapper.find("form"),
            extended_class = "is-extended",
            is_changed = false,
            is_locked = false;

        $form.on("submit", submit);

        $textarea.on("focus", function () {
            $wrapper.addClass(extended_class);
        });

        $wrapper.on("click", ".js-cancel", close);

        $textarea.on("keyup", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 ),
                value = $textarea.val(),
                active_class = "is-changed";

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    $textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
                $textarea.removeClass(active_class);
            }

            if (!is_enter || event.shiftKey) {
                toggleHeight();
            }
        });

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();
                $form.find("input:submit").trigger("click");
            }
        });

        $(document).on("click", watcher);

        initCombobox($wrapper);
        //
        initTypeToggle($wrapper);
        //
        initDatePicker();
        //
        initTimeToggle();
        //
        initTimePicker();

        function watcher(event) {
            var $target = $(event.target),
                is_exist = $.contains(document, $wrapper[0]),
                is_target = $.contains($wrapper[0], event.target),
                is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                is_date = !!( $target.closest(".ui-datepicker").length ),
                is_empty = !$textarea.val().length;

            if (is_exist) {
                if (!is_target && !is_time && !is_date && is_empty) {
                    close();
                }
            } else {
                $(document).off("click", watcher);
            }
        }

        function close() {
            $textarea.val("");
            $wrapper.removeClass(extended_class);
        }

        function submit(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                $icon.removeClass("c-plus").addClass("loading");

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                        $.crm.sidebar.reload();
                    }
                }, "json").always(function () {
                    is_locked = false;
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        }

        function initCombobox($block) {
            var $wrapper = $block.find(".js-contact-wrapper"),
                $idField = $wrapper.find(".js-field");

            $wrapper.on("click", ".js-show-combobox", function (event) {
                event.stopPropagation();
                showToggle(true);
            });

            $wrapper.on("click", ".js-hide-combobox", function (event) {
                event.stopPropagation();
                showToggle(false);
            });

            initAutocomplete();

            function showToggle(show) {
                var active_class = "is-shown";
                if (show) {
                    $wrapper.addClass(active_class);
                } else {
                    $wrapper.removeClass(active_class);
                }
            }

            function initAutocomplete() {
                var $autocomplete = $wrapper.find(".js-autocomplete");

                $autocomplete
                    .autocomplete({
                        appendTo: $wrapper,
                        position: {my: "right top", at: "right bottom"},
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

            function setContact(user) {
                var $user = $wrapper.find(".js-user");
                if (user["photo_url"]) {
                    $user.find(".icon16").css("background-image", "url(" + user["photo_url"] + ")");
                }
                $user.find(".c-name").text(user.name);
                $idField.val(user.id);
            }
        }

        function initDatePicker() {
            var $datePickers = $wrapper.find(".js-datepicker");

            $datePickers.each(function () {
                var $input = $(this),
                    $altField = $input.parent().find("input[type='hidden']");

                $input.datepicker({
                    altField: $altField,
                    altFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });

                var $icon = $input.parent().find(".calendar");
                $icon.on("click", function () {
                    $input.focus();
                });

                // $input.datepicker("setDate", "+1d");
            });
        }

        function initTimeToggle() {
            var $toggle = $wrapper.find(".js-time-toggle"),
                $field = $toggle.find(".js-timepicker");

            $toggle.on("click", ".js-show-time", function () {
                show(true);
                $field.focus();
            });

            $toggle.on("click", ".js-reset-time", function () {
                $field.val("");
                show();
            });

            function show(show) {
                var active_class = "is-active";
                if (show) {
                    $toggle.addClass(active_class);
                } else {
                    $toggle.removeClass(active_class);
                }
            }
        }

        function initTimePicker() {
            var $timePickers = $wrapper.find(".js-timepicker");
            $timePickers.each(function () {
                var $input = $(this);
                $input.timepicker();
            });
        }

        function initTypeToggle($block) {
            var $wrapper = $block.find(".js-reminder-type-toggle"),
                $visibleLink = $wrapper.find(".js-visible-link"),
                $field = $wrapper.find(".js-field"),
                $menu = $wrapper.find(".menu-v");

            $menu.on("click", "a", function () {
                var $link = $(this);
                $visibleLink.find(".js-text").html($link.html());

                $menu.find(".selected").removeClass("selected");
                $link.closest("li").addClass("selected");

                $menu.hide();
                setTimeout(function () {
                    $menu.removeAttr("style");
                }, 200);

                var id = $link.data("type-id");
                $field.val(id).trigger("change");
            });
        }
    };

    CRMReminders.prototype.initCompletedReminders = function () {
        var that = this,
            $section = that.$completedSection,
            $list = $section.find(".c-reminders-list"),
            is_loaded = false,
            is_open = false,
            is_locked = false;

        // EVENT
        $section.on("click", ".js-load-completed-reminders", onToggleClick);

        //

        function onToggleClick(event) {
            event.preventDefault();
            if (is_loaded) {
                show(false, true);
            } else {
                if (!is_locked) {
                    load();
                }
            }
        }

        function load() {
            var href = "?module=reminder&action=completed",
                data = {
                    user_id: that.user_id
                };

            is_locked = true;

            $.post(href, data, function (html) {
                show(true);
                is_loaded = true;
                $list.append(html);
                initLazyLoading();
                // fix position after change content
                $(window).trigger("scroll");
            }).always(function () {
                is_locked = false;
            });
        }

        function show(show, toggle) {
            var active_class = "is-shown";

            if (toggle) {
                $section.toggleClass(active_class);
                is_open = !is_open;
            } else if (show) {
                $section.addClass(active_class);
                is_open = true;
            } else {
                $section.removeClass(active_class);
                is_open = false;
            }
        }

        function initLazyLoading() {
            var $window = $(window),
                $loader = $list.find(".js-lazy-load"),
                is_locked = false;

            if ($loader.length) {
                $window.on("scroll", use);
            }

            function use() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    if (is_open) {
                        onScroll($loader);
                    }
                } else {
                    $window.off("scroll", use);
                }
            }

            function onScroll($loader) {
                var scroll_top = $(window).scrollTop(),
                    display_h = $window.height(),
                    loader_top = $loader.offset().top;

                if (scroll_top + display_h >= loader_top) {
                    if (!is_locked) {
                        load($loader);
                    }
                }
            }

            function load($loader) {
                var href = "?module=reminder&action=completed",
                    data = {
                        user_id: that.user_id,
                        min_dt: $list.find("> li[data-datetime]:last").data("datetime")
                    };

                is_locked = true;

                $.post(href, data, function (html) {
                    $loader.remove();
                    $list.append(html);
                    initLazyLoading();
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminders.prototype.initElastic = function () {
        var that = this;

        var $wrapper = that.$wrapper,
            $aside = that.$wrapper.find("#js-aside-block"),
            $content = that.$wrapper.find("#js-content-block");

        if ($aside.length) {
            var asideElastic = new CRMElasticBlock({
                $wrapper: $wrapper,
                $aside: $aside,
                $content: $content
            });
        }

        if ($content.length) {
            var contentElastic = new CRMElasticBlock({
                $wrapper: $wrapper,
                $content: $aside,
                $aside: $content
            });
        }

        // fix position after change content
        $(window).trigger("scroll");
    };

    CRMReminders.prototype.initReopenReminder = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-reopen-reminder", function (event) {
            event.preventDefault();

            var $marker = $(this),
                $reminder = $marker.closest(".c-reminder-wrapper");

            $marker.addClass("is-loading");

            reOpen($reminder.data("id"));
        });

        function reOpen(id) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=reminder&action=markAsUndone",
                    data = {
                        id: id
                    };

                $.post(href, data, function (response) {
                    if (response.status == "ok") {
                        $.crm.content.reload();
                    }
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminders.prototype.initReminderSettings = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-settings", function (event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=reminder&action=settings",
                    data = {};

                $.post(href, data, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminders.prototype.initTarget = function() {
        var that = this;

        $(window).load( function() {
            setTimeout( function() {
                var $target = that.$wrapper.find(".c-reminder-wrapper.is-target");
                if ($target.length) {
                    var target_t = $target.offset().top;
                    $(window).scrollTop(target_t - 50);
                }
            }, 100);
        });
    };

    return CRMReminders;

})(jQuery);

var CRMReminder = (function ($) {

    CRMReminder = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$marker = that.$wrapper.find(".c-marker");
        that.$steps = that.$wrapper.find(".c-step");
        that.$view = that.$steps.filter(".is-view");
        that.$edit = that.$steps.filter(".is-edit");
        that.$confirm = that.$steps.filter(".is-confirm");
        that.$textarea = that.$edit.find("textarea");

        // VARS
        that.id = options["id"];
        that.shown_class = "is-shown";

        // DYNAMIC VARS
        that.$activeContent = that.$view;
        that.xhr = false;

        // INIT
        that.initClass();
    };

    CRMReminder.prototype.initClass = function () {
        var that = this;

        that.$wrapper.on("click", ".js-cancel", function (event) {
            event.preventDefault();
            that.toggleContent(that.$view);
        });

        that.$wrapper.on("click", ".js-edit-reminder", function (event) {
            event.preventDefault();

            if (!that.$textarea.data("value")) {
                that.$textarea.data("value", that.$textarea.val());
            } else {
                that.$textarea.val(that.$textarea.data("value"));
            }

            $(document).trigger("reminderEditMode", [that.id]);
            that.toggleContent(that.$edit);
        });

        that.$edit.find("form").on("submit", function () {
            $(document).one("reminderIsChanged", function () {
                $.crm.content.reload();
            });
        });

        that.$wrapper.on("click", ".js-confirm-delete", function (event) {
            event.preventDefault();
            that.remove();
        });

        that.$wrapper.on("click", ".js-remove", function (event) {
            event.preventDefault();
            toggleConfirm(true);
        });

        that.$wrapper.on("click", ".js-confirm-cancel", function (event) {
            event.preventDefault();
            toggleConfirm(false);
        });

        //
        that.initDone();
        //
        that.initQuickDateToggle();
        //
        that.initQuickContentEdit();

        function toggleConfirm(show) {
            var active_class = "is-shown";
            if (show) {
                that.$confirm.addClass(active_class);
            } else {
                that.$confirm.removeClass(active_class);
            }
        }
    };

    CRMReminder.prototype.remove = function () {
        var that = this,
            href = "?module=reminder&action=delete",
            data = {
                id: that.id
            };

        if (that.xhr) {
            that.xhr.abort();
        }

        that.xhr = $.post(href, data, function (response) {
            if (response.status === "ok") {
                that.$wrapper.remove();
                $.crm.content.reload();
                $.crm.sidebar.reload();
            }
        }).always(function () {
            that.xhr = false;
        });
    };

    CRMReminder.prototype.initDone = function () {
        var that = this,
            is_locked = false,
            $reminder = that.$wrapper;

        $reminder.on("click", ".js-mark-done", setDone);

        function setDone(event) {
            $reminder.css('display','none'); // Hide the reminder without waiting for the server's response;
            event.preventDefault();

            var $marker = $(this);

            $marker.addClass("is-done");

            var id = that.id,
                href = "?module=reminder&action=markAsDone",
                data = {
                    id: id
                };

            is_locked = true;

            $.post(href, data, function (response) {
                if (response.status === "ok") {
                    $reminder.remove();
                    $.crm.content.reload();
                    $.crm.sidebar.reload();
                } else {
                    $reminder.css('display',''); // If a bad response is returned from the server, then we will show the reminder back.
                }
            }).always(function () {
                is_locked = false;
            });
        }
    };

    CRMReminder.prototype.toggleContent = function ($content) {
        var that = this;

        if (that.$activeContent.length) {
            that.$activeContent.removeClass(that.shown_class);
        }
        $content.addClass(that.shown_class);
        that.$activeContent = $content;
        if (that.$edit === $content) {
           // that.$wrapper.trigger("editOpen");
            $(document).on("reminderEditMode", function (event, id) {
               if (id !== that.id) that.toggleContent(that.$view);
            });
        }
        else
        {
            $(document).off("reminderEditMode");
        }

    };

    CRMReminder.prototype.initQuickDateToggle = function () {
        var that = this,
            $wrapper = that.$wrapper.find(".js-quick-date-toggle-wrapper"),
            is_opened = false,
            is_locked = false;

        if (!$wrapper.length) {
            return false;
        }

        // DOM
        var $form = $wrapper.find("form"),
            $input = $wrapper.find(".js-date-field");

        // VARS

        // EVENTS

        $wrapper.on("click", ".js-change-date", function (event) {
            event.preventDefault();
            toggleContent(true);
        });

        $wrapper.on("click", ".js-cancel-edit-date", function (event) {
            event.preventDefault();
            toggleContent(false);
        });

        $wrapper.on("click", ".js-save-date", function (event) {
            event.preventDefault();
            save()
        });

        $(document).on("click", clickWatcher);

        function clickWatcher(event) {
            var is_exist = $.contains(document, $wrapper[0]);
            if (is_exist) {
                var is_target = $.contains($wrapper[0], event.target);

                if (is_opened) {
                    var $target = $(event.target),
                        is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                        is_date = !!( $target.closest(".ui-datepicker").length ),
                        is_datepicker_icon = !!($target.hasClass("ui-icon-circle-triangle-e") );

                    if (is_time || is_date || is_datepicker_icon) {
                        return false;
                    }
                }

                if (!is_target && is_opened) {
                    toggleContent(false);
                }
            } else {
                $(document).off("click", clickWatcher);
            }
        }

        // CALLS

        initDatePicker();

        initTimeToggle();

        // FUNCTIONS

        function save() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                that.renderLoading(true);
                console.log(data);
                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                        $.crm.sidebar.reload();
                    }
                }).always(function () {
                    that.renderLoading(false);
                    is_locked = false;
                });
            }
        }

        function toggleContent(show) {
            var active_class = "is-extended";

            if (show) {
                $wrapper.addClass(active_class);
                $input.focus();
                is_opened = true;
            } else {
                $wrapper.removeClass(active_class);
                is_opened = false;
            }
        }

        function initDatePicker() {
            var $altInput = $wrapper.find(".js-alt-date-field");

            $input.datepicker({
                altField: $altInput,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true
            });

            var $icon = $input.parent().find(".calendar");
            $icon.on("click", function () {
                $input.focus();
            });
        }

        function initTimeToggle() {
            var $toggle = $wrapper.find(".js-time-toggle"),
                $field = $toggle.find(".js-timepicker");

            $toggle.on("click", ".js-show-time", function () {
                show(true);
                $field.focus();
            });

            $toggle.on("click", ".js-reset-time", function () {
                $field.val("");
                show();
            });

            var $timePickers = $wrapper.find(".js-timepicker");
            $timePickers.timepicker();

            function show(show) {
                var active_class = "is-active";
                if (show) {
                    $toggle.addClass(active_class);
                } else {
                    $toggle.removeClass(active_class);
                }
            }
        }
    };

    CRMReminder.prototype.initQuickContentEdit = function () {
        var that = this,
            $wrapper = that.$wrapper.find(".js-quick-content-toggle-wrapper");

        if (!$wrapper.length) {
            return false;
        }

        // DOM
        var $form = $wrapper.find("form"),
            $textarea = $form.find(".js-textarea");

        // VARS
        var active_class = "is-changed";

        // DYNAMIC VARS
        var is_changed = false,
            is_locked = false;

        // EVENTS

        $textarea.on("keyup", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 ),
                value = $textarea.val();

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    $textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
                $textarea.removeClass(active_class);
            }

            if (!is_enter || event.shiftKey) {
                toggleHeight();
            }
        });

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();

                if (is_changed) {
                    save();
                }
            }
        });

        $textarea.on("blur", function () {
            if (is_changed) {
                save();
            }
        });

        toggleHeight();

        // FUNCTIONS

        function save() {
            if (!is_locked) {
                is_locked = true;

                that.renderLoading(true);

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        is_changed = false;
                        if (response.data.hasOwnProperty('id')) {
                            let $textarea_edit = that.$wrapper.find('#rtxt-'+ response.data.id);
                            that.$wrapper.find('.js-quick-date-toggle-wrapper').find('[name="data[content]"]').val($textarea.val());
                            $textarea_edit.val($textarea.val());
                        }
                        $textarea
                            .removeClass(active_class)
                            .blur();
                    }
                }).always(function () {
                    is_locked = false;
                    that.renderLoading(false);
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        }
    };

    CRMReminder.prototype.renderLoading = function (is_loading) {
        var that = this,
            $marker = that.$marker,
            load_class = "is-load";

        var $loading = $marker.data("icon");
        if (!$loading) {
            $loading = $("<i class=\"icon16 loading\"></i>");
            $marker.prepend($loading);
            $marker.data("icon", $loading);
        }

        if (is_loading) {
            $marker.addClass(load_class);
            $loading.show();
        } else {
            $marker.removeClass(load_class);
            $loading.hide();
        }
    };

    return CRMReminder;

})(jQuery);

var CRMCompletedReminder = (function ($) {

    CRMCompletedReminder = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$marker = that.$wrapper.find(".c-marker");

        // VARS
        that.marker_html = that.$marker.html();
        that.reminder_id = options["reminder_id"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCompletedReminder.prototype.initClass = function () {
        var that = this;

        that.initQuickContentEdit();
    };

    CRMCompletedReminder.prototype.initQuickContentEdit = function () {
        var that = this,
            $wrapper = that.$wrapper.find(".js-quick-content-toggle-wrapper");

        if (!$wrapper.length) {
            return false;
        }

        // DOM
        var $form = $wrapper.find("form"),
            $textarea = $form.find(".js-textarea");

        // VARS
        var active_class = "is-changed";

        // DYNAMIC VARS
        var is_changed = false,
            is_locked = false;

        // EVENTS

        $textarea.on("keyup", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 ),
                value = $textarea.val();

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    $textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
                $textarea.removeClass(active_class);
            }

            if (!is_enter || event.shiftKey) {
                toggleHeight();
            }
        });

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();

                if (is_changed) {
                    save();
                }
            }
        });

        $textarea.on("blur", function () {
            if (is_changed) {
                save();
            }
        });

        toggleHeight();

        // FUNCTIONS

        function save() {
            if (!is_locked) {
                is_locked = true;

                that.renderLoading(true);

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        is_changed = false;
                        $textarea
                            .removeClass(active_class)
                            .blur();
                    }
                }).always(function () {
                    is_locked = false;
                    that.renderLoading(false);
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        }
    };

    CRMCompletedReminder.prototype.renderLoading = function (is_loading) {
        var that = this,
            $marker = that.$marker,
            load_class = "is-load";

        if (is_loading) {
            $marker.addClass(load_class);
            var loading = "<i class=\"icon16 loading\"></i>";
            $marker.html(loading);
        } else {
            $marker.removeClass(load_class);
            $marker.html(that.marker_html);
        }
    };

    return CRMCompletedReminder;

})(jQuery);

var CRMReminderSettingsDialog = (function ($) {

    CRMReminderSettingsDialog = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$button = that.$wrapper.find(".js-submit-button");
        that.$options = that.$wrapper.find(".js-options-list");
        that.$groups = that.$wrapper.find(".js-group-list");
        that.$select = that.$wrapper.find(".js-select-list");

        // DYNAMIC VARS
        that.dialog = that.$wrapper.data("dialog");
        // INIT
        that.initClass();
    };

    CRMReminderSettingsDialog.prototype.initClass = function () {
        var that = this;

        that.$form.on("change", "input", function () {
            that.toggleButton(true);
        });

        that.$options.on("change", "input", function () {
            var $input = $(this),
                value = $input.val(),
                is_active = ( $input.attr("checked") === "checked" );

            if (is_active) {
                if (value === "groups") {
                    that.$groups.show();
                } else {
                    that.$groups.hide();
                }
            }

            that.dialog.resize();
        });

        that.$form.on("change", '.daily-recap', function () {
            var $inputDaily = $(this),
                status = $inputDaily["0"].checked,
                select = that.$select,
                cron_error  = $(".crm-reminders-recap-error");

            if (!status) {
                select.attr("disabled", true);
                /* Closed error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeOut();
                    that.dialog.resize();
                }
            } else {
                select.attr("disabled", false);
                /* Show error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeIn();
                    that.dialog.resize();
                }
            }
        });

        /* Pop-up settings */
        that.$form.on('change', '.js-pop-up-disabled', function () {
            var pop_up     = $(this),
                pop_up_min = that.$wrapper.find(".js-pop-up-min");

            if (pop_up.is(':checked')) {
                pop_up_min.prop( "readonly", false );
                pop_up_min.focus();
            }
            else {
                pop_up_min.prop( "readonly", true );
            }
        });

        that.initSave();
    };

    CRMReminderSettingsDialog.prototype.initSave = function () {
        var that       = this,
            $form      = that.$form,
            is_locked  = false,
            pop_up     = that.$wrapper.find(".js-pop-up-disabled");

        $(".js-submit-button").on("click", function () {
            if (pop_up.is(':checked')) {
                var pop_up_time = parseInt($(".js-pop-up-min").val());
                if (pop_up_time > 0) {
                    onSubmit();
                } else {
                    showError();
                }
            } else {
                onSubmit();
            }
        });
        function showError() {
            $(".enter-minutes").fadeIn().delay("2000").fadeOut();
        }

        function onSubmit() {
            if (!is_locked) {
                is_locked = true;

                var $loading = $('<i class="icon16 loading" style="vertical-align: middle;margin-left: 10px;"></i>');
                    $loading.appendTo('.crm-actions');

                $(".js-submit-button").prop("disabled", true);

                var href = "?module=reminder&action=settingsSave";

                $.post(href, $form.serializeArray(), function (response) {
                    if (response.status === "ok") {
                        if (that.$options.find(':checked').val() == 'my'){
                            var content_uri = $.crm.app_url + "reminder/";
                            $.crm.content.load(content_uri);
                        } else {
                            $('.loading').remove();
                            var $done = $('<i class="icon16 yes" style="vertical-align: middle;margin-left: 10px;"></i>');
                                $done.appendTo('.crm-actions');

                            setTimeout(function() {
                                that.dialog.close();
                                $.crm.content.reload();
                            }, 1000);
                        }
                    }
                }, "json").always(function () {
                    that.toggleButton(false);
                    is_locked = false;
                });
            }
        }
    };

    CRMReminderSettingsDialog.prototype.toggleButton = function (active) {
        var that = this,
            $button = that.$button;

        if (active) {
            $button.removeClass("green").addClass("yellow");
        } else {
            $button.removeClass("yellow").addClass("green");
        }
    };

    return CRMReminderSettingsDialog;

})(jQuery);

var CRMReminderSettings = (function ($) {

    CRMReminderSettings = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find(".js-submit-button");
        that.$options = that.$wrapper.find(".js-options-list");
        that.$groups = that.$wrapper.find(".js-group-list");
        that.$select = that.$wrapper.find(".js-select-list");

        that.$wrapper.data('instance', that);

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminderSettings.prototype.initClass = function () {
        var that = this;


        that.$options.on("change", "input", function () {
            var $input = $(this),
                value = $input.val(),
                is_active = ( $input.attr("checked") === "checked" );

            if (is_active) {
                if (value === "groups") {
                    that.$groups.show();
                } else {
                    that.$groups.hide();
                }
            }
        });

        that.$wrapper.on("change", '.daily-recap', function () {
            var $inputDaily = $(this),
                status = $inputDaily["0"].checked,
                select = that.$select,
                cron_error  = $(".crm-reminders-recap-error");

            if (!status) {
                select.attr("disabled", true);
                /* Closed error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeOut();
                }
            } else {
                select.attr("disabled", false);
                /* Show error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeIn();
                }
            }
        });

        /* Pop-up settings */
        that.$wrapper.on('change', '.js-pop-up-disabled', function () {
            var pop_up     = $(this),
                pop_up_min = that.$wrapper.find(".js-pop-up-min");

            if (pop_up.is(':checked')) {
                pop_up_min.prop( "readonly", false );
                pop_up_min.focus();
            }
            else {
                pop_up_min.prop( "readonly", true );
            }
        });
    };

    CRMReminderSettings.prototype.validateBeforeSave = function() {
        var that       = this,
            $pop_up     = that.$wrapper.find(".js-pop-up-disabled");

        if ($pop_up.is(':checked')) {
            var pop_up_time = parseInt($(".js-pop-up-min").val());
            if (pop_up_time <= 0) {
                $(".enter-minutes").fadeIn().delay("2000").fadeOut();
                return false;
            }
        }

        return true;

    };

    return CRMReminderSettings;

})(jQuery);
