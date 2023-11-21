var CRMSettingsCurrencies = (function ($) {

    CRMSettingsCurrencies = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$currenciesList = that.$wrapper.find(".js-currencies-list");
        that.$currencySelect = that.$wrapper.find(".js-add-currency");

        // VARS
        that.currencies_is_locked = options["currencies_is_locked"];
        that.currency_template_html = options["currency_template_html"];
        that.copy_shop_currencies_dialog_html = options["copy_shop_currencies_dialog_html"];
        that.locales = options["locales"];
        that.currencies = options["currencies"];
        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsCurrencies.prototype.initClass = function () {
        var that = this;

        if (!that.currencies_is_locked) {
            //
            that.initCurrencyAdd();
            //
            that.initCurrencyRemove();
            //
            that.initCurrenciesSort();
            //
            that.initChangePrimary();
            //
            that.initCurrencyEdit();

        }

        that.initShopToggle();
    };

    CRMSettingsCurrencies.prototype.initCurrencyAdd = function () {

        var that = this;

        that.$currencySelect
            .on("change", addCurrency)
            .on("removeCurrency", function (event, code) {
                toggleCurrency(code);
            });

        function addCurrency() {
            var $select = $(this),
                code = $select.val(),
                currency = that.currencies[code],
                title = currency.title,
                sign = currency.sign;

            $select.attr("disabled", true);

            var data = {
                code: code,
                title: title,
                sign: sign
            };

            renderCurrency(data);
        }


        function renderCurrency(data) {
            var new_currency = that.currency_template_html,
                $newCurrency = $(new_currency),
                edit_class = "is-edit";

            $newCurrency.data("code", data.code);
            $newCurrency.find(".js-name").text(data.title);
            $newCurrency.find(".js-current-code").text(data.code);
            $newCurrency.find(".js-sign").text(data.sign);
            $newCurrency.find(".js-save-currency").addClass('js-save-new');
            $newCurrency.addClass(edit_class);
            that.$currenciesList.append($newCurrency);
            $newCurrency.find('.c-rate').select();
        }

        function toggleCurrency(code, hide) {
            code = code.toLowerCase();

            that.$currencySelect.find("option").each(function () {
                var $option = $(this),
                    option_code = $option.attr("value").toLowerCase();

                if (option_code === code) {
                    $option.attr("disabled", !!hide);
                }
            });
        }
    };

    CRMSettingsCurrencies.prototype.initCurrencyRemove = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-remove-currency", removeContact);

        function removeContact(event) {
            event.preventDefault();

            var $currency = $(this).closest(".c-currency"),
                currency_title = $currency.data("title"),
                code = $currency.data("code");

            if (!code) {
                return false;
            }

            showConfirm();

            function showConfirm() {
                if (!is_locked) {
                    is_locked = true;

                $.waDialog.confirm({
                    title: '<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ' + that.locales["confirm_delete_title"].replace("%currency_name", currency_title),
                    text: that.locales["confirm_delete_text"],
                    success_button_title: that.locales["confirm_delete_button"],
                    success_button_class: 'danger',
                    cancel_button_title: that.locales["confirm_cancel_button"],
                    cancel_button_class: 'light-gray',
                    onSuccess: function() {
                        remove();
                    }
                });
                
                is_locked = false;
                }
            }

            function remove() {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settings&action=currenciesDelete",
                        data = {
                            code: code
                        };

                    $.post(href, data, function (response) {
                        if (response.status == "ok") {
                            that.$currencySelect.trigger("removeCurrency", code);
                            $currency.remove();
                        }
                    }).always(function () {
                        is_locked = false;
                    })
                }
            }
        }
    };

    CRMSettingsCurrencies.prototype.initCurrenciesSort = function () {
        var that = this,
            xhr = false;
        
        var $currenciesList = that.$wrapper.find(".js-currencies-list");
        $currenciesList.sortable({
            distance: 10,
            handle: ".js-sort-toggle",
            helper: "clone",
            items: "> .c-currency",
            axis: "y",
            stop: save,
            onUpdate: save,
            
        });

        function save() {
            if (xhr) {
                xhr.abort();
            }

            var href = "?module=settings&action=currenciesSort",
                data = {
                    codes: getSortData()
                };

            xhr = $.post(href, data, function (response) {
            }).always(function () {
                xhr = false;
            });
        }

        function getSortData() {
            var result = [];

            that.$currenciesList.find(".c-currency").each(function () {
                var code = $(this).data("code");
                if (code) {
                    result.push(code);
                }
            });

            return result;
        }
    };

    CRMSettingsCurrencies.prototype.initShopToggle = function () {
        var that = this,
            $toggle_input = that.$wrapper.find(".js-shop-currency-toggle"),
            $switch = that.$wrapper.find("#shop-currency-switch");
        is_locked = false;
        if (!$toggle_input.length) { return false; }
        var use_shop_available = ($toggle_input.data("use-shop") || false);

        $switch.waSwitch({
            change: function (active, wa_switch) {
                if (!is_locked) {
                    if (active) {
                        if (use_shop_available) {

                            $.waDialog.confirm({
                                title: '<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ' + that.locales["confirm_shop_title"],
                                text: that.locales["confirm_shop_text"],
                                success_button_title: that.locales["confirm_shop_button"],
                                success_button_class: 'danger',
                                cancel_button_title: `${that.locales["confirm_cancel_button"]}`,
                                cancel_button_class: 'light-gray',
                                onSuccess: function() {
                                    useShopCurrencies(true);
                                },
                                onCancel: unsetToggle,
                            });

                        } else {
                            showConvertDialog();
                        }
                    } else {
                        useShopCurrencies(false);
                    }
                }
            }
        });

        var switcher = $switch.waSwitch("switch");

        // EVENTS
        function unsetToggle() {
            switcher.set(false);
        }

        // FUNCTIONS

        function showConvertDialog() {

            var dialog = $.waDialog({
                html: that.copy_shop_currencies_dialog_html
            });

            var $form = dialog.$wrapper.find("form");

            $form.on("submit", function (event) {
                event.preventDefault();
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settings&action=currenciesShopCopy",
                        data = $form.serializeArray();

                    $.post(href, data, function () {
                        $.crm.content.reload();

                    }).always(function () {
                        is_locked = false;
                    });
                }
            });

            $form.on("click", ".js-cancel-button", unsetToggle);
        }

        function useShopCurrencies(use) {
            if (!is_locked) { // && use_shop_available
                is_locked = true;

                var href = "?module=settings&action=currenciesShopCopy",
                    data = (!use ? { disable: 1 } : {});

                $.post(href, data, function (response) {
                    $.crm.content.reload();

                }).always(function () {
                    // $toggle.attr("disabled", false);

                    is_locked = false;
                });
            }
        }
    };

    CRMSettingsCurrencies.prototype.initChangePrimary = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-change-primary", function (event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=currenciesPrimary",
                    data = {};

                $.post(href, data, function (html) {
                    $.waDialog({
                        html: html,
                        options: {
                            onChange: function (code) {
                                $.crm.content.reload();
                            }
                        }
                    });
                }).always(function () {
                    is_locked = false;
                })
            }
        }
    };

    CRMSettingsCurrencies.prototype.initCurrencyEdit = function () {
        var that = this,
            is_locked = false,
            edit_class = "is-edit",
            $select = that.$currencySelect;

        that.$wrapper.on("click", ".js-edit-currency", editContact);

        that.$wrapper.on("click", ".js-save-currency", saveCurrency);

        that.$wrapper.on("click", ".js-cancel", setDefault);

        that.$wrapper.on("keyup", ".js-rate", function (event) {
            var key = event.keyCode,
                is_enter = (key === 13);

            if (is_enter) {
                $(this).closest(".c-currency").find(".js-save-currency").trigger("click");
            }
        });

        function editContact(event) {
            event.preventDefault();

            var $currency = $(this).closest(".c-currency");

            $currency.addClass(edit_class);
        }

        function saveCurrency(event) {
            event.preventDefault();

            var $currency = $(this).closest(".c-currency"),
                is_locked = false,
                edit_class = "is-edit",
                href = "?module=settings&action=currenciesSave",
                newCurrency = $currency.find('.js-save-new');

            if (newCurrency.length > 0) {
                href = "?module=settings&action=currenciesAdd";
            }

            if (!is_locked) {
                is_locked = true;

                var $rate = $currency.find(".js-rate"),
                    rate = $rate.val();

                rate = rate.replace(",", ".");
                var rateNumAfterDecimal = String(rate).split('.')[1];

                if (rateNumAfterDecimal) {
                    var rateLen = rateNumAfterDecimal.length;
                }

                if (!(rate > 0) || rate > 9999999) {
                    var error_class = "error";
                    $rate.addClass(error_class)
                        .one("click", function () {
                            $rate.removeClass(error_class);
                        });

                    is_locked = false;
                    return false;
                }

                var data = {
                    code: $currency.data("code"),
                    rate: rate
                };

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        $currency.toggleClass(edit_class)
                            .find(".js-rate-text").text(rate);
                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }).always(function () {
                    is_locked = false;
                    $select.attr("disabled", false);
                    $select.find(":selected").attr("disabled", true);
                    $select.prop("selectedIndex", 0);
                });
            }

            function showErrors(errors) {
                var error_class = "error";

                errors = (errors ? errors : []);

                $.each(errors, function (index, item) {
                    var name = item.name,
                        text = item.value;

                    var $field = $currency.find("[name=\"" + name + "\"]"),
                        $text = $("<span />").addClass("errormsg").text(text);

                    if ($field.length) {
                        if (!$field.hasClass(error_class)) {
                            $field.closest("div").append($text);

                            $field
                                .addClass(error_class)
                                .one("focus click", function () {
                                    $field.removeClass(error_class);
                                    $text.remove();
                                });
                        }
                    }
                });
            }
        }

        function setDefault(event) {
            event.preventDefault();

            var $currency = $(this).closest(".c-currency"),
                newCurrency = $currency.find('.js-save-new');

            if (newCurrency.length > 0) {
                $currency.remove();
                $select.attr("disabled", false);
                $select.prop("selectedIndex", 0);
            } else {
                $currency.removeClass(edit_class);
            }

        }
    };

    return CRMSettingsCurrencies;

})(jQuery);

var CRMSettingsCurrenciesPrimary = (function ($) {

    CRMSettingsCurrenciesPrimary = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$toggle = that.$wrapper.find(".js-currencies-toggle");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.currency_code = options["currency_code"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsCurrenciesPrimary.prototype.initClass = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-change-currency", changeCurrency);

        function changeCurrency(event) {
            event.preventDefault();

            var code = that.$toggle.val(),

                is_changed = (code !== that.currency_code);
            if (is_changed) {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settings&action=currenciesPrimarySave",
                        data = {
                            code: code
                        };

                    $.post(href, data, function (response) {
                        if (response.status == "ok") {
                            that.dialog.options.onChange(code);
                            that.dialog.close();
                        }
                    }).always(function () {
                        is_locked = false;
                    });
                }
            } else {
                that.dialog.close();
            }
        }
    };

    return CRMSettingsCurrenciesPrimary;

})(jQuery);