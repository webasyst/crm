var CRMReminderFormEdit = ( function($) {

    CRMReminderFormEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.reminder_id = options["reminder_id"];
        that.locales = options["locales"];
        that.app_url = options["app_url"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminderFormEdit.prototype.initClass = function() {
        var that = this;
        //
        that.initDatePicker();
        //
        that.initTimeToggle();
        //
        that.initTimePicker();
        //
        that.initCombobox();
        //
        that.initTypeToggle();
        //
        that.initSubmit();
    };

    CRMReminderFormEdit.prototype.initCombobox = function() {
        var that = this;

        var $wrapper = that.$form.find(".js-contact-wrapper"),
            $idField = $wrapper.find(".js-field");

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

            $autocomplete
                .autocomplete({
                    appendTo: $wrapper,
                    position: { my : "right top", at: "right bottom" },
                    source: that.app_url + "?module=autocomplete&type=user",
                    minLength: 0,
                    html: true,
                    focus: function() {
                        return false;
                    },
                    select: function( event, ui ) {
                        setContact(ui.item);
                        showToggle(false);
                        $autocomplete.val("");
                        return false;
                    }
                }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                    return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
                };

            $autocomplete.on("focus", function(){
                $autocomplete.data("uiAutocomplete").search( $autocomplete.val() );
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
    };

    CRMReminderFormEdit.prototype.initDatePicker = function() {
        var that = this,
            $datePickers = that.$form.find(".js-datepicker");

        $datePickers.each(function() {
            var $input = $(this),
                $altField = $input.parent().find("input[type='hidden']");

            $input.datepicker({
                altField: $altField,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true
            });

            var $icon = $input.parent().find(".calendar");
            $icon.on("click", function() {
                $input.focus();
            });

            if (!that.reminder_id) {
                $input.datepicker("setDate", "+1d");
            }
        });
    };

    CRMReminderFormEdit.prototype.initTimeToggle = function() {
        var that = this;

        var $toggle = that.$form.find(".js-time-toggle"),
            $field = $toggle.find(".js-timepicker");

        $toggle.on("click", ".js-show-time", function() {
            show(true);
            $field.focus();
        });

        $toggle.on("click", ".js-reset-time", function() {
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
    };

    CRMReminderFormEdit.prototype.initTimePicker = function() {
        var that = this;

        var $timePickers = that.$form.find(".js-timepicker");
        $timePickers.each( function() {
            var $input = $(this);
            $input.timepicker();
        });
    };

    CRMReminderFormEdit.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index,item) {
                if (item.value.length) {
                    result.data.push(item);
                // } else {
                //     result.errors.push({
                //         name: item.name,
                //         value: that.locales["empty"]
                //     });
                }
            });

            return result;
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                var keys = Object.keys(ajax_errors);
                $.each(keys, function(index, name) {
                    errors.push({
                        name: name,
                        value: ajax_errors[name]
                    })
                });
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);

                    that.$wrapper.append($text);

                    $field
                        .addClass(error_class)
                        .one("focus click change", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = that.app_url + "?module=reminder&action=save";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        // for log.html. reload
                        $(document).trigger("reminderIsChanged");

                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminderFormEdit.prototype.initTypeToggle = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-reminder-type-toggle"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("type-id");
            $field.val(id).trigger("change");
        });
    };

    return CRMReminderFormEdit;

})(jQuery);