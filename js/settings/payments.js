var CRMSettingsPayments = ( function($) {

    CRMSettingsPayments = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$pluginsDropdown = that.$wrapper.find(".js-plugins-dropdown");
        that.$table = that.$wrapper.find(".js-payments-table");

        // VARS
        that.company_id = options["company_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsPayments.prototype.initClass = function() {
        var that = this;
        //
        that.initTabs();
        //
        that.initDelete();
        //
        that.initSortable();

        var $page_body = that.$wrapper.find(".c-payments-list");

        var observer = new MutationObserver( function(mutations) {
            $page_body.trigger("refresh");
        });

        observer.observe($page_body[0], {
            childList: true,
            attributes: true,
            subtree: true
        });
    };

    CRMSettingsPayments.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".c-tabs-wrapper"),
            $companies = that.$wrapper.find(".c-companies-wrapper"),
            $list = $companies.find(".c-companies-list"),
            $activeTab = $list.find(".c-company.selected");

        initSetWidth();

        initSlider();

        //

        function initSetWidth() {
            var $window = $(window),
                other_w = $section.find(".c-add-wrapper").outerWidth(true);

            setWidth();

            $window.on("resize refresh", onResize);

            function onResize() {
                var is_exist = $.contains(document, $section[0]);
                if (is_exist) {
                    setWidth();
                } else {
                    $window.off("resize refresh", onResize);
                }
            }

            function setWidth() {
                var section_w = $section.width(),
                    max_w = section_w - other_w - 10;

                $companies.css("max-width", max_w + "px");
            }
        }

        function initSlider() {
            $.crm.tabSlider({
                $wrapper: $companies,
                $slider: $list,
                $activeSlide: ($activeTab.length ? $activeTab : false )
            });
        }
    };

    CRMSettingsPayments.prototype.initDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-payment", onDelete);

        function onDelete(event) {
            event.preventDefault();
            var $link = $(this),
                $tr = $link.closest("tr"),
                payment_id = $link.data("payment-id"),
                payment_plugin = $link.data("plugin");

            showConfirm();

            function showConfirm() {
                $.crm.confirm.show({
                    title: that.locales["confirm_delete_title"].replace("%payment", $tr.find(".js-name").text()),
                    text: that.locales["confirm_delete_text"],
                    button: that.locales["confirm_delete_button"],
                    onConfirm: remove
                });

                function remove() {
                    if (!is_locked) {
                        is_locked = true;

                        var href = "?module=settings&action=paymentDelete",
                            data = {
                                id: payment_id,
                                company_id: that.company_id
                            };

                        $.post(href, data, function(response) {
                            if (response.status == "ok") {
                                $tr.remove();

                                var $plugin = that.$pluginsDropdown.find("li[data-plugin=\"" + payment_plugin + "\"]");
                                if ($plugin.length) {
                                    $plugin.show();
                                }
                            }
                        }).always( function() {
                            is_locked = false;
                        })
                    }
                }
            }
        }
    };

    CRMSettingsPayments.prototype.initSortable = function() {
        var that = this,
            xhr = false;

        that.$table.sortable({
            distance: 10,
            handle: ".js-sort-toggle",
            helper: "clone",
            items: "tr",
            axis: "y",
            stop: save
        });

        function save() {
            var href = "?module=settings&action=paymentSort",
                ids = getIds(),
                data = {
                    ids: ids,
                    company_id: that.company_id
                };

            if (xhr) {
                xhr.abort();
            }

            xhr = $.post(href, data, function(response) {

            }).always( function() {
                xhr = false;
            });

            function getIds() {
                var result = [];

                that.$table.find("tr").each( function() {
                    result.push($(this).data("id"));
                });

                return result;
            }
        }
    };

    return CRMSettingsPayments;

})(jQuery);

var CRMPaymentEdit = ( function($) {

    CRMPaymentEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$footer = that.$wrapper.find(".js-footer");

        // VARS
        that.instance_id = options["instance_id"];
        that.company_id = options["company_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMPaymentEdit.prototype.initClass = function() {
        var that = this;
        //
        that.initSubmit();
        //
        that.initDelete();
    };

    CRMPaymentEdit.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $form = that.$form;

        $form.on("submit", function(event) {
            event.preventDefault();
            var formData = getData();
            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                submit(formData.data);
            }
        });

        function submit(data) {
            if (!is_locked) {
                is_locked = true;

                var saving = that.locales.saving,
                    $loading = $(saving);
                that.$footer.append($loading);

                var href = "?module=settings&action=paymentSave";

                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        $loading.remove();

                        if (!that.instance_id) {
                            var content_uri = $.crm.app_url + "settings/payment/?company=" + that.company_id;
                            $.crm.content.load(content_uri);
                        } else {
                            var saved = that.locales.saved,
                                $saved = $(saved);
                            that.$footer.append($saved);
                            //
                            setTimeout(function() {
                                var is_exist = $.contains(document, $saved[0]);
                                if (is_exist) {
                                    $saved.remove();
                                }
                            }, 2000);
                        }
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }

        function getData() {
            var result = {
                data: $form.serializeArray(),
                errors: []
            };

            return result;
        }

        function showErrors(errors, ajax_errors) {
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

                var $field = that.$wrapper.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);
                    $text.insertAfter($field);

                    $field
                        .addClass(error_class)
                        .one("focus click", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }
    };

    CRMPaymentEdit.prototype.initDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-payment", onDelete);

        function onDelete(event) {
            event.preventDefault();

            showConfirm();

            function showConfirm() {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=dialogConfirm",
                        data = {
                            title: that.locales["confirm_delete_title"],
                            text: that.locales["confirm_delete_text"],
                            ok_button: that.locales["confirm_delete_button"]
                        };

                    $.post(href, data, function(html) {
                        new CRMDialog({
                            html: html,
                            onConfirm: function() {
                                remove();
                            }
                        });
                    }).always( function() {
                        is_locked = false;
                    })
                }
            }

            function remove() {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=settings&action=paymentDelete",
                        data = {
                            id: that.instance_id,
                            company_id: that.company_id
                        };

                    $.post(href, data, function(response) {
                        if (response.status == "ok") {
                            var content_uri = $.crm.app_url + "settings/payment/?company=" + that.company_id;
                            $.crm.content.load(content_uri);
                        }
                    }).always( function() {
                        is_locked = false;
                    })
                }
            }
        }
    };

    return CRMPaymentEdit;

})(jQuery);