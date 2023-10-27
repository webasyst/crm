var CRMDealEdit = (function ($) {

    CRMDealEdit = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find("#js-deal-edit-form");
        that.$deal_name_input = that.$form.find('[name="deal[name]"]');

        // VARS
        that.deal_id = options["deal_id"];
        that.locales = options["locales"];
        that.urls = options["urls"];
        that.can_edit_contact = options.can_edit_contact || false;

        // DYNAMIC VARS
        that.suggester_values = {};
        that.contact_mode = options.contact_mode || "edit";

        // INIT
        that.initClass();
    };

    CRMDealEdit.prototype.initClass = function () {
        var that = this;

        //
        that.initChangeCompanyContact();
        //
        that.initEstimatedDate();
        //
        that.initSuggestName();
        //
        that.initChangeFunnel();
        //
        that.initWYSIWYG();
        //
        that.initFieldsBlock();
        //
        that.initSave();
        //
        that.initCombobox();
    };

    CRMDealEdit.prototype.initEstimatedDate = function () {
        var that = this,
            $field = that.$wrapper.find('.c-estimated-close-date-field'),
            $wrapper = $field.find(".js-datepicker-wrapper"),
            $input    = $wrapper.find(".js-datepicker"),
            $altField = $wrapper.find('[name="deal[expected_date]"]');

        $input.datepicker({
            altField: $altField,
            altFormat: "yy-mm-dd",
            changeMonth: true,
            changeYear: true,
            onSelect: checkDate
        });

        $input.on('blur', checkDate);

        $input.on("keydown keypress keyup", function(event) {
            if ( event.which === 13 ) {
                event.preventDefault();
            }
        });

        $input.on("click", ".js-icon", function () {
            $input.focus();
        });

        //

        function checkDate() {
            var format = $.datepicker._defaults.dateFormat,
                is_valid = false;
            try {
                $.datepicker.parseDate(format, $input.val());
                is_valid = true;
            } catch(e) {}
            if (is_valid) {
                $input.data('last-correct-value', $input.val());
                $altField.data('last-correct-value', $altField.val());
            } else {
                $input.val($input.data('last-correct-value') || '');
                $altField.val($altField.data('last-correct-value') || '');
            }
        }
    };

    CRMDealEdit.prototype.initSave = function() {
        var that = this,
            $form = that.$form,
            $errorsPlace = that.$form.find(".js-errors-place"),
            $submit_button = that.$wrapper.find('.js-submit-button'),
            is_locked = false;

        $submit_button.on("click", function() {
            $form.trigger("submit");
        });

        $form.on("submit", onSubmit);

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
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            for (var i = 0; i < data.length - 1; i++) {
                for (var j = i + 1; j < data.length; j++) {
                    if (data[i].name == data[j].name) {
                        if (data[i].value == "") {
                            data.splice(i, 1);
                        } else if (data[j].value == "") {
                            data.splice(j, 1);
                        }
                    }
                }
            }

            $.each(data, function(index, item) {
                var $current_field = $('[name="' + item.name + '"]');

                var is_radio  = $('[name="' + item.name + '"][type="radio"]').length > 0,
                    condition = item.value == "" || item.value.trim() == "";

                if ($($current_field).prop('required') == true && condition) {
                    result.errors.push({
                        name: item.name,
                        value: that.locales["bad_amount"],
                        is_radio: is_radio
                    });
                }

                if (item.name === "deal[amount]") {
                    if (item.value.length) {
                        item.value = $.trim(item.value);
                    }
                    var is_good = validateAmount(item.value);
                    if (!is_good) {
                        result.errors.push({
                            name: item.name,
                            value: that.locales["bad_amount"]
                        });
                    }
                }
                result.data.push(item);
            });

            if (that.contact_mode === "edit") {
                if (that.can_edit_contact) {
                    getContactEditData(result);
                }
            } else {
                getContactReplaceData(result);
            }

            return result;

            function validateAmount(value) {
                var result = false;

                if (value.length) {
                    var _value = value.replace(",", ".");
                    if (_value > 0 || _value === "0") {
                        result = true;
                    }
                }

                return result;
            }
        }

        function showErrors(errors) {
            var error_class = "error";

            if (!errors || !errors[0]) {
                errors = [];
            }

            $.each(errors, function(index, item) {
                var name     = item.name,
                    text     = item.value,
                    is_radio = item.is_radio;

                if (is_radio) {
                    var $field       = that.$wrapper.find("[name=\"" + name + "\"][type='hidden']"),
                        $radio_field = that.$wrapper.find("[name=\"" + name + "\"][type='radio']");
                } else {
                    var $field = that.$wrapper.find("[name=\"" + name + "\"]");
                }

                if ( name === "contact[firstname]" && !$field.is(":visible") ) {
                    $field = that.$wrapper.find(".js-contact-autocomplete");
                }

                var $text = $("<span class='c-error' />").addClass("errormsg").text(text);

                if ($field.length && !$field.hasClass(error_class)) {
                    $field.parent().append($text);

                    $field.addClass(error_class);
                    if (is_radio) {
                        $radio_field.one("focus click change", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                    } else {
                        $field.one("focus click change", function () {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                    }
                } else {
                    $errorsPlace.append($text);

                    $form.one("submit", function() {
                        $text.remove();
                    });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=deal&action=save";
                $submit_button.attr('disabled','disabled').prop('disabled', true);
                var $loading = that.$wrapper.find('.loading');
                $loading.show();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        refreshDeal(response.data.deal.id);

                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    $loading.hide();
                    is_locked = false;
                    $submit_button.removeAttr('disabled').prop('disabled', false);
                });

                function refreshDeal(deal_id) {
                    var content_uri = $.crm.app_url + "deal/" + deal_id + "/";
                    $.crm.content.load(content_uri);
                }
            }
        }

        function getContactEditData(result) {
            var $editContactForm = that.$wrapper.find("#c-contact-edit-form"),
                $form = $editContactForm.closest("form"),
                $phoneList = $form.find(".js-phone-list"),
                $emailList = $form.find(".js-email-list"),
                formData = $form.serializeArray();

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
                    value: that.locales.empty_name
                });
                result.errors.push({
                    name: "contact[name]",
                    value: that.locales.empty_name
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

        function getContactReplaceData(result) {
            var $replaceContactForm = that.$wrapper.find("#c-contact-add-form"),
                $form = $replaceContactForm.closest("form"),
                data = $form.serializeArray();

            var id_is_set = false,
                name_is_set = false;

            $.each(data, function(index, item) {
                var name = item.name,
                    value = item.value;

                if (name === "deal[contact_id]" && value) {
                    id_is_set = true;
                }

                if (name === "contact[firstname]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[lastname]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[middlename]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[name]" && value) {
                    name_is_set = true;
                }

                result.data.push(item);
            });

            if (!id_is_set && !name_is_set) {
                result.errors.push({
                    name: "contact[name]",
                    value: that.locales["empty_name"]
                });
                result.errors.push({
                    name: "contact[firstname]",
                    value: that.locales["empty_name"]
                });
            }
        }
    };

    CRMDealEdit.prototype.initSuggestName = function () {
        var that = this,
            $form = that.$form,
            ns = '.crm-deal-suggestion',
            $deal_name_input = that.$deal_name_input;

        if ($deal_name_input.data('edited')) {
            return;
        }

        $deal_name_input.one('mark-edited' + ns, function () {
            $deal_name_input.data('edited', 1);
            $deal_name_input.off(ns);
            $form.off(ns, '.js-crm-deal-name-suggester-input');
        });

        $deal_name_input.on(
            'keyup' + ns,
            function () {
                var $el = $(this);
                if ($.trim($el.val()).length > 0) {
                    $el.trigger('mark-edited');
                }
            }
        )
        ;

        var xhr = null,
            timer = null;

        var suggestName = function () {
            if (xhr) {
                xhr.abort();
                xhr = null;
            }
            if ($deal_name_input.data('edited')) {
                return;
            }
            xhr = $.post(
                $.crm.app_url + '?module=deal&action=suggestName',
                that.suggester_values,
                function (r) {
                    if (r.status === 'ok' && !$deal_name_input.data('edited')) {
                        $deal_name_input.val($.trim(r.data && r.data.name) || '');
                    }
                }
            );
        };

        $form.on('keyup' + ns + ' change' + ns, '.js-crm-deal-name-suggester-input', function (e) {
            var $el = $(this);

            timer && clearTimeout(timer);
            timer = setTimeout(function () {

                if ($el.hasClass('crm-find-contact-input')) {
                    if (e.type === 'keyup') {
                        return;
                    } else {
                        $el = $form.find('[name="deal[id]"]');
                    }
                }

                var name = $el.attr('name') || '',
                    val = $.trim($el.val());

                if (name) {
                    that.suggester_values[name] = $.trim(that.suggester_values[name]);
                    if (that.suggester_values[name] === val) {
                        return;
                    }
                    that.suggester_values[name] = val;
                }

                suggestName();
            }, 250);
        });

    };

    CRMDealEdit.prototype.initChangeCompanyContact = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-contact-block"),
            active_class = "is-active",
            $toggleW = $wrapper.find(".js-view-toggle"),
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

                that.contact_mode = content_id;

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
            $wrapper.find(".c-hidden." + active_class).removeClass(active_class);
            // render
            $wrapper.find(".c-hidden-" + content_id).addClass(active_class);
        }

    };

    CRMDealEdit.prototype.initChangeFunnel = function() {
        var that = this;

        //
        that.$form.on('change', '.js-select-deal-funnel', function() {
            that.$form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });
    };

    CRMDealEdit.prototype.initWYSIWYG = function() {
        var that = this;

        var $textarea = that.$wrapper.find(".js-wysiwyg");
        if (!$textarea.length) {
            return false;
        }

        $.crm.initWYSIWYG($textarea, {
            keydownCallback: function (e) {
                //if (e.keyCode == 13 && e.ctrlKey) {
                //return addComment(); // Ctrl+Enter disabled
                //}
            }
        });
    };

    CRMDealEdit.prototype.initFieldsBlock = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $section = $wrapper.find('.js-ext-fields-section'),
            active_class = "is-extended";

        $section.on("click", ".js-ext-fields-toggle", function () {
            $section.toggleClass(active_class);
            $('.js-show-fields-button, .js-hide-fields-button').removeAttr('style');
        });
    };

    CRMDealEdit.prototype.initCombobox = function() {
        var that = this;

        var $wrapper = that.$form.find(".js-contact-owner-wrapper"),
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
                    source: that.urls.owner_autocomplete,
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
                $user.find(".userpic20").css("background-image", "url(" + user["photo_url"] + ")");
            }
            $user.find(".c-name").text(user.name);
            $idField.val(user.id);
        }
    };

    return CRMDealEdit;

})(jQuery);
