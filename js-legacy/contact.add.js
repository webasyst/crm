var CRMContactEditForm = ( function($) {

    CRMContactEditForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$phoneList = that.$wrapper.find(".js-phone-list");
        that.$emailList = that.$wrapper.find(".js-email-list");

        // VARS
        that.phone_template_html = options["phone_template_html"];
        that.email_template_html = options["email_template_html"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactEditForm.prototype.initClass = function() {
        var that = this;
        //
        that.initAddPhone();
        //
        that.initAddEmail();
        //
        that.initCompanyAutocomplete();
    };

    CRMContactEditForm.prototype.initAddPhone = function() {
        var that = this,
            $list = that.$phoneList;

        that.$wrapper.on("click", ".js-add-phone", addPhone);

        that.$wrapper.on("click", ".js-remove-phone", removePhone);

        $(document).on("addContactPhone", function(event, phone) {
            addPhone(event, phone);
        });

        function addPhone(event, phone) {
            event.preventDefault();

            var html = that.phone_template_html,
                $phone = $(html);

            if (phone) {
                $phone.find(".js-value").val(phone.number).trigger("change");
                $phone.find(".js-ext").val(phone.ext).trigger("change");
            }

            $list.append($phone);
        }

        function removePhone(event) {
            event.preventDefault();
            $(this).closest("li").remove();
        }
    };

    CRMContactEditForm.prototype.initAddEmail = function() {
        var that = this,
            $list = that.$emailList;

        that.$wrapper.on("click", ".js-add-email", addEmail);

        that.$wrapper.on("click", ".js-remove-email", removeEmail);

        $(document).on("addContactEmail", function(event, email) {
            addEmail(event, email);
        });

        function addEmail(event, email) {
            event.preventDefault();

            var html = that.email_template_html,
                $email = $(html);

            if (email) {
                $email.find(".js-value").val(email.name).trigger("change");
                $email.find(".js-ext").val(email.ext).trigger("change");
            }

            $list.append($email);
        }

        function removeEmail(event) {
            event.preventDefault();
            $(this).closest("li").remove();
        }
    };

    CRMContactEditForm.prototype.initCompanyAutocomplete = function () {
        var that = this,
            $visibleField = that.$wrapper.find('[name="contact[company]"]'),
            $hiddenField = that.$wrapper.find('[name="contact[company_contact_id]"]');

        // field can be removed
        if ($visibleField.length) {
            $visibleField.autocomplete({
                appendTo: that.$wrapper,
                source: "?module=autocomplete&type=company",
                minLength: 2,
                focus: function() {
                    return false;
                },
                select: function(event, ui) {
                    var text = $("<div />").text(ui.item.name).text();
                    $visibleField.val(text);
                    $hiddenField.val(ui.item.id > 0 ? ui.item.id : '');
                    return false;
                },
                search: function () {
                    $hiddenField.val("");
                }
            }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
            };
        }
    };

    return CRMContactEditForm;

})(jQuery);

var CRMContactAddDialog = ( function($) {

    CRMContactAddDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.contact_id = options["contact_id"];
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactAddDialog.prototype.initClass = function() {
        var that = this;

        that.$wrapper.find(".js-focus-field").focus();
        //
        that.initToggle();
        //
        that.initContactForm();
        //
        that.initCompanyForm();
    };

    CRMContactAddDialog.prototype.initToggle = function() {
        var that = this,
            active_class = "is-selected",
            $wrapper = that.$wrapper.find(".js-content-toggle-wrapper"),
            $toggle = $wrapper.find(".js-ibutton"),
            $activeContent = that.$wrapper.find(".js-toggle-content." + active_class);

        $toggle.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        });

        $toggle.on("change", setToggle);

        function setToggle() {
            var is_active = ( $toggle.attr("checked") === "checked" ),
                content_id = ( is_active ? "company" : "contact" ),
                $content = that.$wrapper.find(".js-toggle-content[data-content=\"" + content_id + "\"]"),
                on_class = "is-on";

            if (is_active) {
                $wrapper.addClass(on_class);
            } else {
                $wrapper.removeClass(on_class);
            }

            if ($activeContent.length) {
                $activeContent.removeClass(active_class);
            }
            if ($content.length) {
                $activeContent = $content.addClass(active_class);
            }
            that.$wrapper.find(".js-focus-field").focus();

            that.dialog.resize();
        }
    };

    CRMContactAddDialog.prototype.initContactForm = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-toggle-content[data-content=\"contact\"]"),
            $form = $wrapper.find("form");

        initSave();

        initExtendedForm();

        getFormData();

        //

        function initSave() {
            var $errorPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            $form.on("submit", onSubmit);

            function onSubmit(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var formData = getFormData();

                    if (formData.errors.length) {
                        showErrors(formData.errors);
                        is_locked = false;
                    } else {
                        save(formData.data);
                    }
                }
            }

            function save(data) {
                var href = (that.contact_id ? "?module=deal&action=contactUpdate" : "?module=contact&action=save");

                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        that.dialog.close();
                        var content_uri = $.crm.app_url + 'contact/' + response.data.contact.id + '/';
                        $.crm.content.load(content_uri);
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

                    if (name === "name") {
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
        }

        function initExtendedForm() {
            $wrapper.on("click", ".js-extended-form", function() {
                var formData = getFormData(),
                    get_params_array = [];

                get_params_array.push("extended=true");

                $.each(formData.data, function(index, item) {
                    var value = $.trim(item.value);
                    if (value) {
                        get_params_array.push(item.name + "=" + value);
                    }
                });

                var content_uri = $.crm.app_url + "contact/new/?" + get_params_array.join("&");
                $.crm.content.load(content_uri);
            });
        }

        function getFormData() {
            var $phoneList = $wrapper.find(".js-phone-list"),
                $emailList = $wrapper.find(".js-email-list");

            $phoneList.find("li .js-value, li .js-ext").removeAttr("name");
            $emailList.find("li .js-value, li .js-ext").removeAttr("name");

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

            return result;
        }
    };

    CRMContactAddDialog.prototype.initCompanyForm = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-toggle-content[data-content=\"company\"]"),
            $form = $wrapper.find("form");

        initSave();

        initExtendedForm();

        //

        function initExtendedForm() {
            $wrapper.on("click", ".js-extended-form", function() {
                var formData = getFormData(),
                    get_params_array = [];

                get_params_array.push("extended=true");
                get_params_array.push("type=company");

                $.each(formData.data, function(index, item) {
                    var value = $.trim(item.value);
                    if (value) {
                        get_params_array.push(item.name + "=" + value);
                    }
                });

                var content_uri = $.crm.app_url + "contact/new/?" + get_params_array.join("&");
                $.crm.content.load(content_uri);
            });
        }

        function initSave() {
            var is_locked = false,
                $errorsPlace = $wrapper.find(".js-errors-place");

            $form.on("submit", onSubmit);

            function onSubmit(event) {
                event.preventDefault();

                var formData = getData();

                if (formData.errors.length) {
                    showErrors(errors);
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

                $.each(data, function(index, item) {
                    item.value = $.trim(item.value);

                    if (item.name === "contact[company]") {
                        result.data.push({
                            name: "contact[name]",
                            value: item.value
                        });
                    }
                    result.data.push(item);
                });

                var $phoneList = $wrapper.find(".js-phone-list");

                $phoneList.find("li .js-value, li .js-ext").removeAttr("name");

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

                var $emailList = $wrapper.find(".js-email-list");

                $emailList.find("li .js-value, li .js-ext").removeAttr("name");

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
                    name: "contact[is_company]",
                    value: 1
                });

                return result;
            }

            function showErrors(errors) {
                var error_class = "error";

                errors = (errors ? errors : []);

                $.each(errors, function(index, item) {
                    var name = item.name,
                        text = item.value;

                    var $field = $wrapper.find("[name=\"" + name + "\"]");

                    if ( name === "contact[firstname]" && !$field.is(":visible") ) {
                        $field = $wrapper.find(".js-contact-autocomplete");
                    }

                    var $text = $("<span class='c-error' />").addClass("errormsg").text(text);

                    if ($field.length && !$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click change", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    } else {
                        $errorsPlace.append($text);

                        $form.one("submit", function() {
                            remove($text);
                        });
                    }
                });
            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;

                    var href = (that.contact_id ? "?module=deal&action=contactUpdate" : "?module=contact&action=save");

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            that.dialog.close();
                            var content_uri = $.crm.app_url + 'contact/' + response.data.contact.id + '/';
                            $.crm.content.load(content_uri);
                        } else if (response.errors) {
                            showErrors(response.errors);
                        }
                    }, "json").always( function() {
                        is_locked = false;
                    });
                }
            }
        }

        function getFormData() {
            var $phoneList = $wrapper.find(".js-phone-list"),
                $emailList = $wrapper.find(".js-email-list");

            $phoneList.find("li .js-value, li .js-ext").removeAttr("name");
            $emailList.find("li .js-value, li .js-ext").removeAttr("name");

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

            return result;
        }
    };

    return CRMContactAddDialog;

})(jQuery);

var CRMDealParticipantAdd = ( function($) {

    CRMDealParticipantAdd = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$content = that.$wrapper.find(".crm-dialog-content");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.deal_id = options["deal_id"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealParticipantAdd.prototype.initClass = function() {
        var that = this;

        // autofocus on open dialog
        var $field = that.$wrapper.find(".js-contact-autocomplete");
        if ($field.length) {
            $field.focus();
        }

        that.initAddParticipant();
    };

    CRMDealParticipantAdd.prototype.initAddParticipant = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                addParticipant(formData.data);
            }
        }

        function getData() {
            var result = {
                data: that.$form.serializeArray(),
                errors: []
            };

            return result;
        }

        function showErrors(errors) {
            var is_object = (!errors[0]);

            if (is_object) {

                var keys = Object.keys(errors);
                $.each(keys, function(index, item) {
                    var text = errors[item];
                    render(text);
                });

            } else {

                $.each(errors, function(index, item) {
                    var text = item.value;
                    render(text);
                });
            }

            function render(text) {
                var $text = $("<div class=\"line errormsg\" />").html(text);
                that.$content.append($text);

                that.$form.one("submit", function() {
                    $text.remove();
                });
            }
        }

        function addParticipant(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=addParticipantSave";

                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        renderContact(response.data.html);
                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }).always( function() {
                    is_locked = false;
                })
            }
        }

        function renderContact(html) {
            that.dialog.options.onAdd(html);
            that.dialog.close();
        }
    };

    return CRMDealParticipantAdd;

})(jQuery);

/**
 * initialized in ContactForm.inc.html
 * */
var CRMContactAddForm = ( function($) {

    CRMContactAddForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$toggleW = that.$wrapper.find(".js-contact-view-toggle");

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactAddForm.prototype.initClass = function() {
        var that = this;
        //
        that.initToggle();
        //
        that.initContactAutocomplete();
        //
        that.initCompanyAutocomplete();
    };

    CRMContactAddForm.prototype.initContactAutocomplete = function() {
        var that = this,
            $field = that.$wrapper.find(".js-contact-autocomplete"),
            $fields = that.$wrapper.find(".js-field"),
            $idField = that.$wrapper.find(".js-contact-id-field");

        var requested_fields = ['firstname', 'company', 'jobtitle', 'phone', 'email'],
            source = "?module=autocomplete&add_new=true&join=" + requested_fields.join(",");

        var is_selected = false;

        $field.autocomplete({
            appendTo: that.$wrapper,
            source: source,
            minLength: 2,
            html: true,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                is_selected = true;
                onSelect(ui.item);
                $field.blur();
                return false;
            },
            search: function() {
                $idField.val("");
            },
            open: function() {
                is_selected = false;
            },
            change: function() {
                if (!is_selected) {
                    onSelect({
                        id: -1,
                        name: $field.val()
                    });
                }
            }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };

        function onSelect(item) {
            if (item.id > 0) {
                $field.val(item.name);
                $idField.val(item.id);
                setData(item.data);
            } else if (item.id < 0) {
                $idField.val("");
                that.$toggleW.find(".js-show-fio").trigger("click", true);

                $fields.val("");

                var is_email = ( item.name.indexOf("@") >= 0 ),
                    is_phone = ( !!item.name.match(/^\+?\d/) );

                if (is_email) {
                    that.$wrapper.find(".js-email-field").val(item.name).trigger("change");

                } else if (is_phone) {
                    that.$wrapper.find(".js-phone-field").val(item.name).trigger("change");

                } else {
                    that.$wrapper.find(".js-firstname,.js-name").val(item.name).trigger("change");
                }
            }
        }

        function setData(data) {
            $.each(requested_fields, function(i, field) {
                var value = data[field],
                    is_multi = ( field === "phone" || field === "email" ),
                    value_ar = [],
                    $input = null;

                if (is_multi) {
                    value = '';
                    value_ar = (data[field] || []).reverse();
                    while (value == "" && value_ar.length > 0) {
                        value = value_ar.pop()
                    }
                    if ($.isPlainObject(value)) {
                        value = value.value;
                    }
                }

                value = $.trim(value || '');
                if (value == "") {
                    return;
                }

                if (is_multi) {
                    $input = that.$wrapper.find('[name="contact[' + field + '][0][value]"]');
                } else {
                    $input = that.$wrapper.find('[name="contact[' + field + ']"]');
                }
                $input.val(value);
            });

            $fields.attr("disabled", true);
        }

        $field.on("keydown", function(event) {
            var keyCode = event.keyCode;
            if (keyCode !== 13) {
                $fields.attr("disabled", false);
                $fields.val("");
            }
        });
    };

    CRMContactAddForm.prototype.initCompanyAutocomplete = function() {
        var that = this,
            $field = that.$wrapper.find(".js-company-autocomplete"),
            $idField = that.$wrapper.find(".js-company-id-field");

        if ( !($field.length && $idField.length) ) {
            return false;
        }

        $field.autocomplete({
            appendTo: that.$wrapper,
            source: "?module=autocomplete&type=company",
            minLength: 2,
            html: true,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                $field.val(ui.item.name);
                $idField.val(ui.item.id);
                return false;
            },
            search: function() {
                $idField.val("");
            }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };
    };

    CRMContactAddForm.prototype.initToggle = function() {
        var that = this,
            active_class = "is-active",
            $toggleW = that.$toggleW,
            $activeToggle = $toggleW.find("." + active_class),
            $field = that.$wrapper.find(".js-action-field"),
            $fields = that.$wrapper.find(".js-field");

        var $fio = that.$wrapper.find(".js-fio"),
            $comboName = that.$wrapper.find(".js-combo-name");

        $toggleW.on("click", ".c-toggle", setToggle);

        function setToggle(event, reset_form) {
            event.preventDefault();

            reset_form = !reset_form;

            var $toggle = $(this),
                mode = $toggle.data("mode"),
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
                showContent(mode, reset_form);
            }
        }

        function showContent(mode, reset_form) {
            if (reset_form) {
                clearForm();
            }

            if (mode === "fio") {
                $fio.show();
                $comboName.hide();
                $field.val("new");
                $fields.attr("disabled", false);

            } else {
                $fio.hide();
                $comboName.show();
                $field.val("search");
            }

            $(document).trigger("toggleChanged");

            // resize
            $(document).trigger("resizeDialog");
        }

        function clearForm() {
            that.$wrapper.find("input").val("").trigger("change");
        }
    };

    return CRMContactAddForm;

})(jQuery);

/**
 * initialized in CompanyAddForm.inc.html
 * */
var CRMCompanyAddForm = ( function($) {

    CRMCompanyAddForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.phone_template_html = options["phone_template_html"];
        that.email_template_html = options["email_template_html"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCompanyAddForm.prototype.initClass = function() {
        var that = this;

        that.initAddPhone();

        that.initAddEmail();
    };

    CRMCompanyAddForm.prototype.initAddPhone = function() {
        var that = this,
            $list = that.$wrapper.find(".js-phone-list");

        that.$wrapper.on("click", ".js-add-phone", addPhone);

        that.$wrapper.on("click", ".js-remove-phone", removePhone);

        function addPhone(event) {
            event.preventDefault();

            var html = that.phone_template_html,
                $phone = $(html);

            $list.append($phone);
        }

        function removePhone(event) {
            event.preventDefault();
            $(this).closest("li").remove();
        }
    };

    CRMCompanyAddForm.prototype.initAddEmail = function() {
        var that = this,
            $list = that.$wrapper.find(".js-email-list");

        that.$wrapper.on("click", ".js-add-email", addEmail);

        that.$wrapper.on("click", ".js-remove-email", removeEmail);

        $(document).on("addContactEmail", function(event, email) {
            addEmail(event, email);
        });

        function addEmail(event, email) {
            event.preventDefault();

            var html = that.email_template_html,
                $email = $(html);

            if (email) {
                $email.find(".js-value").val(email.name).trigger("change");
                $email.find(".js-ext").val(email.ext).trigger("change");
            }

            $list.append($email);
        }

        function removeEmail(event) {
            event.preventDefault();
            $(this).closest("li").remove();
        }
    };

    return CRMCompanyAddForm;

})(jQuery);
