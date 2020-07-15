var CRMAtolPluginSettings = ( function($) {

    CRMAtolPluginSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form").first();
        that.$submitButton = that.$form.find(".js-submit-form");

        // VARS
        that.company_id = options["company_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMAtolPluginSettings.prototype.initClass = function() {
        var that = this;
        //
        that.initTabs();
        //
        that.initTestConnection();
        //
        that.initSubmit();
        //
        that.toggleButton();
        //
        that.initIButton();

        that.$form.on("change", "input, select, textarea", function() {
            that.toggleButton(true);
        });
    };

    CRMAtolPluginSettings.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".c-tabs-wrapper"),
            $companies = that.$wrapper.find(".c-companies-wrapper"),
            $list = $companies.find(".c-companies-list"),
            $activeTab = $list.find(".c-company.selected");

        initSetWidth();

        initSlider();

        //initSort();

        //

        function initSetWidth() {
            var $window = $(window),
                other_w = $section.find(".c-add-wrapper").outerWidth(true);

            setWidth();

            $window.on("resize", onResize);

            function onResize() {
                var is_exist = $.contains(document, $section[0]);
                if (is_exist) {
                    setWidth();
                } else {
                    $window.off("resize", onResize);
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

        // function initSort() {
        //     $list.sortable({
        //         //helper: "clone",
        //         distance: 10,
        //         items: "> li",
        //         axis: "x",
        //         start: function(event,ui) {
        //         },
        //         stop: function(event,ui) {
        //         }
        //     });
        // }
    };

    CRMAtolPluginSettings.prototype.initIButton = function() {
        var that = this,
            $field = that.$wrapper.find(".js-ibutton").first(),
            $toggleFields = that.$wrapper.find(".js-toggle-fields");

        $field.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        });

        $field.on("change", function() {
            var is_active = ($field.attr('checked') === "checked");
            if (is_active) {
                $toggleFields.slideDown(200);
                $toggleFields.find("input, select").attr("disabled", false);
            } else {
                $toggleFields.slideUp(200);
                $toggleFields.find("input, select").attr("disabled", true);
            }
        });
    };

    CRMAtolPluginSettings.prototype.initTestConnection = function() {
        var that = this,
            xhr = false;

        // DOM
        var $form = that.$form,
            $button = $form.find(".js-atol-pass-button");

        $button.on("click", function(event) {
            event.preventDefault();
            testConnect();
        });

        // Events
        $form.on('keyup', 'input[name="crm_atolonline[pass]"], input[name="crm_atolonline[login]"]', function() {
            watchButton();
        });

        watchButton();

        // Functions

        function testConnect() {
            if (xhr) { xhr.abort(); }

            var href = $.crm.app_url + "?plugin=atolonline&module=checkConnection",
                data = $form.serializeArray(),
                $message = "";

            var $loading = $('<i class="icon16 loading"></i>');
            $button.after($loading);

            xhr = $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $message = $('<span class="js-message" style="color: green;"><i class="icon16 yes js-res"></i>' + that.locales["connection_done"]+ '</span>');
                } else {
                    $message = $('<span class="js-message" style="color: red;">' + response.errors + '</span>');
                }
                $button.after($message);

                setTimeout( function() {
                    if ($.contains(document, $message[0])) {
                        $message.remove();
                    }
                }, 5000);

            }, "json").always( function() {
                $loading.remove();
                xhr = false;
            });
        }

        function watchButton() {
            var $input1 = $form.find('input[name="crm_atolonline[pass]"]'),
                $input2 = $form.find('input[name="crm_atolonline[login]"]'),
                is_filled = $.trim($input1.val()) && $.trim($input2.val());

            $button.attr("disabled", !is_filled);
        }
    };

    CRMAtolPluginSettings.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            $errorsPlace = that.$wrapper.find(".js-errors-place"),
            is_locked = false;

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

            $.each(data, function(index, item) {
                result.data.push(item);
            });

            return result;
        }

        function showErrors(errors) {
            if (typeof errors === "string") {
                errors = [errors];
            } else if (!errors || !errors[0]) {
                errors = [];
            }

            console.log( errors );

            $.each(errors, function(index, text) {
                var $text = $("<span class='c-error' />").addClass("errormsg").text(text);

                $errorsPlace.append($text);

                $form.one("submit", function() {
                    $text.remove();
                });
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=plugins&id=atolonline&action=save",
                    submit_button = $form.find(".js-submit-form"),
                    loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 1em;"></i>');

                submit_button.after(loading);

                $.post(href, data, function(response) {
                    if (response.status === "ok") {

                        var content_uri = $.crm.app_url + "plugins/#/atolonline/company_id=" + that.company_id;
                        $.crm.content.load(content_uri);

                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                    loading.remove();
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMAtolPluginSettings.prototype.toggleButton = function( set_active ) {
        var that = this,
            $button = that.$submitButton;

        if (set_active) {
            $button
                .removeClass("green")
                .addClass("yellow");

        } else {
            $button
                .removeClass("yellow")
                .addClass("green");
        }
    };

    return CRMAtolPluginSettings;

})(jQuery);
