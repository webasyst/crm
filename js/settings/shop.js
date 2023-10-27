var CRMSettingsShop = ( function($) {

    CRMSettingsShop = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('[type=submit]');

        // VARS
        that.source = options.source || {};
        that.messages = options.messages || {};

        // DYNAMIC VARS
        that.submit_xhr = null;

        // INIT
        that.initClass();
    };

    CRMSettingsShop.prototype.initClass = function () {
        var that = this;
        //
        that.initToggle();
        //
        that.initSubmit();
        // 
        that.initStoreToggle();
        //
        that.initFooterToggle();
    };

    CRMSettingsShop.prototype.initToggle = function() {
        $("#toggle-menu").waToggle();
    }

    CRMSettingsShop.prototype.initStoreToggle = function() {
        var that = this,
            $wrappers = that.$wrapper.find(".js-ibutton-wrapper");

        $wrappers.each(function () {
            var $wrapper = $(this),
                $toggle = $wrapper.find("#c-storefront-switch");

                $toggle.waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function(active, wa_switch) {
                        var $block = $toggle.closest('.c-storefront').find('.c-storefront-params-block'); 
                        if (active) {
                        wa_switch.$label.text(wa_switch.active_text);
                        $block.slideDown(300);
                        }
                        else {
                         wa_switch.$label.text(wa_switch.inactive_text); 
                         $block.slideUp(300);
                        }
                    }
                });

        });
    };

    CRMSettingsShop.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            $button = that.$button,
            $loading = $form.find('.c-loading'),
            url = $form.attr('action');

        $form.submit(function (e) {
            e.preventDefault();
            $loading.show();
            $button.attr('disabled', true);

            that.submit_xhr && that.submit_xhr.abort();
            that.submit_xhr = $.post(url, $form.serialize())
                .done(function () {
                    $.crm.content.load($.crm.app_url + 'settings/shop/');
                })
                .always(function () {
                    $button.attr('disabled', false);
                    $loading.hide();
                });
        });
    };

    CRMSettingsShop.prototype.initFooterToggle = function() {
        var that = this,
            active_class = "is-changed",
            $footer = that.$wrapper.find(".js-footer-actions"),
            $button = $footer.find(".js-submit-button");

        that.$wrapper.on("change keydown", "input, textarea, select", function() {
            toggle(true);
        });

        function toggle(changed) {
            if (changed) {
                $button.addClass("yellow");
                $footer.addClass(active_class);
            } else {
                $button.removeClass("yellow");
                $footer.removeClass(active_class);
            }
        }
    };

    return CRMSettingsShop;

})(jQuery);

var CRMSettingsShopWorkflowPage = ( function($) {

    CRMSettingsShopWorkflowPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form").first();

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsShopWorkflowPage.prototype.initClass = function() {
        var that = this;
        //
        $.crm.renderSVG(that.$wrapper);
        //
        that.initToggle();
        //
        that.initTabs();
        //
        that.initFooterToggle();
        //
        that.initSubmit();
    };

    CRMSettingsShopWorkflowPage.prototype.initToggle = function() {
        $("#toggle-menu").waToggle();
    }

    CRMSettingsShopWorkflowPage.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".js-funnels-tabs"),
            $funnels = that.$wrapper.find(".c-funnels-wrapper"),
            $list = $funnels.find(".c-funnels-list"),
            $activeTab = $list.find(".c-funnel.selected");

        initSetWidth();

        initSlider();

        // initSort();

        //

        function initSetWidth() {
            var $window = $(window);
               // other_w = $section.find(".c-add-wrapper").outerWidth();

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
                    max_w = section_w;

                $funnels.css("max-width", max_w + "px");
            }
        }

        function initSlider() {
            $.crm.tabSlider({
                $wrapper: $funnels,
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

    CRMSettingsShopWorkflowPage.prototype.initFooterToggle = function() {
        var that = this,
            active_class = "is-changed",
            $footer = that.$wrapper.find(".js-footer-actions"),
            $button = $footer.find(".js-submit-button");

        that.$wrapper.on("change keydown", "input, textarea, select", function() {
            toggle(true);
        });

        function toggle(changed) {
            if (changed) {
                $button.addClass("yellow");
                $footer.addClass(active_class);
            } else {
                $button.removeClass("yellow");
                $footer.removeClass(active_class);
            }
        }
    };

    CRMSettingsShopWorkflowPage.prototype.initSubmit = function() {
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
            var error_class = "error";

            if (!errors || !errors[0]) {
                errors = [];
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$wrapper.find("[name=\"" + name + "\"]"),
                    $text = $("<span class='c-error' />").addClass("errormsg").text(text);

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
                        $text.remove();
                    });
                }
            });
        }

        function request(data) {

            if (!is_locked) {
                is_locked = true;
 
                var href = $.crm.app_url + "?module=settings&action=shopWorkflowSave",
                    $form = that.$form,
                    $loading = $form.find('.c-loading');
                    $loading.show();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                    $loading.hide();
                });
            }
        }
    };

    return CRMSettingsShopWorkflowPage;

})(jQuery);