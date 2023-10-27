var CRMDealChangeClient = ( function($) {

    CRMDealChangeClient = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.deal_id = options["deal_id"];
        that.contact_id = options["contact_id"];
        that.type = options["type"];
        that.can_edit_contact = options.can_edit_contact || false;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealChangeClient.prototype.initClass = function() {
        var that = this;
        //
        that.initViewToggle();
        //
        if (that.can_edit_contact) {
            that.initContactChangeData();
        }
        //
        that.initSwitchContact();
    };

    CRMDealChangeClient.prototype.initViewToggle = function() {
        var that = this,
            active_class = "is-active",
            $toggleW = that.$wrapper.find(".js-view-toggle"),
            $activeToggle = $toggleW.find("." + active_class);

        $toggleW.on("click", ".c-toggle", setToggle);

        function setToggle(event) {
            event.preventDefault();

            var $toggle = $(this),
                content_id = $toggle.data("id"),
                is_active = $toggle.hasClass(active_class);

            if (is_active) {
                return false;
            } else {
                // clear
                if ($activeToggle.length) {
                    $activeToggle.removeClass(active_class);
                }
                // render link
                $toggle.addClass(active_class);
                $activeToggle = $toggle;
                // render content
                showContent(content_id);
            }
        }

        function showContent(content_id) {
            // clear
            that.$wrapper.find(".c-hidden." + active_class).removeClass(active_class);
            // render
            that.$wrapper.find(".c-hidden-" + content_id).addClass(active_class);
            // resize
            that.dialog.resize();
        }
    };

    CRMDealChangeClient.prototype.initContactChangeData = function() {
        var that = this,
            $errorPlace = that.$wrapper.find(".js-errors-place"),
            $form = that.$wrapper.find("#c-contact-edit-form").closest("form"),
            is_locked = false,
            $phoneList = $form.find(".js-phone-list"),
            $emailList = $form.find(".js-email-list");

        that.$wrapper.on("click", ".js-save-changed-data", function(event) {
            event.preventDefault();
            $form.trigger("submit");
        });

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var formData = getData();

                if (formData.errors.length) {
                    showErrors(formData.errors);
                    is_locked = false;
                } else {
                    save(formData.data);
                }
            }
        }

        function getData() {
            var formData = $form.serializeArray(),
                result = {
                    data: [],
                    errors: []
                };

            var fio_names = ["contact[firstname]","contact[middlename]","contact[lastname]","contact[name]"],
                fio_is_empty = true;

            $.each(formData, function(index, item) {

                result.data.push({
                    name: item.name,
                    value: item.value
                });

                if ($.trim(item.value).length > 0) {
                    if (fio_names.indexOf(item.name) >= 0) {
                        fio_is_empty = false;
                    }
                }
            });

            if (fio_is_empty) {
                result.errors.push({
                    name: "contact[firstname]",
                    value: "Name is empty"
                });
                result.errors.push({
                    name: "contact[name]",
                    value: "Name is empty"
                });
            }

            $phoneList.find("li").each( function(index) {
                var $li = $(this),
                    $phoneField = $li.find(".js-value"),
                    $extField = $li.find(".js-ext"),
                    phone = $phoneField.val(),
                    ext = $extField.val();

                if (phone) {
                    var phone_name = "contact[phone][" + index + "][value]";
                    $phoneField.attr("name", phone_name);
                    result.data.push({
                        name: phone_name,
                        value: phone
                    });

                    var phone_ext_name = "contact[phone][" + index + "][ext]";
                    $extField.attr("name", phone_ext_name);
                    result.data.push({
                        name: phone_ext_name,
                        value: ext
                    });
                }
            });

            $emailList.find("li").each( function(index) {
                var $li = $(this),
                    $emailField = $li.find(".js-value"),
                    $extField = $li.find(".js-ext"),
                    email = $emailField.val(),
                    ext = $extField.val();

                if (email) {
                    var email_name = "contact[email][" + index + "][value]";
                    $emailField.attr("name", email_name);
                    result.data.push({
                        name: email_name,
                        value: email
                    });

                    var email_ext_name = "contact[email][" + index + "][ext]";
                    $extField.attr("name", email_ext_name);
                    result.data.push({
                        name: email_ext_name,
                        value: ext
                    });
                }
            });

            result.data.push({
                name: "deal_id",
                value: that.deal_id
            });

            return result;
        }

        function save(data) {
            var href = (that.contact_id ? "?module=deal&action=contactUpdate" : "?module=contact&action=save");

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    that.dialog.options.onChange(response.data.html);
                    that.dialog.close();
                } else if (response.errors) {
                    showErrors(response.errors);
                }
            }, "json").always( function() {
                is_locked = false;
            });
        }

        function showErrors(errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                if (name === "contact[name]") {
                    name = "contact[firstname]";
                }

                var $field = $form.find("[name=\"" + name + "\"]"),
                    $text = $("<span />").addClass("errormsg").text(text);

                if ($field.length) {
                    if (!$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    }
                } else {
                    $errorPlace.append($text);
                    $form.one("submit", function() {
                        $text.remove();
                    });
                }
            });
        }

    };

    CRMDealChangeClient.prototype.initSwitchContact = function() {
        var that = this,
            $errorPlace = that.$wrapper.find(".js-errors-place"),
            $switchFormC = that.$wrapper.find("#c-contact-add-form"),
            $form = $switchFormC.closest("form"),
            is_locked = false;

        that.$wrapper.on("click", ".js-switch-contact", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();
            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                data: $form.serializeArray(),
                errors: []
            };

            return result;
        }

        function showErrors(errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                if (name === "contact[name]") {
                    name = "contact[firstname]";
                }

                var $field = $form.find("[name=\"" + name + "\"]"),
                    $text = $("<span />").addClass("errormsg").text(text);

                if ($field.length) {
                    if (!$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    }
                } else {
                    $errorPlace.append($text);
                    that.$wrapper.one("click", ".js-switch-contact", function() {
                        $text.remove();
                    });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=personUpdate";

                data.push({
                    name: "deal_id",
                    value: that.deal_id
                });

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.options.onChange(response.data);
                        that.dialog.close();
                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMDealChangeClient;

})(jQuery);
