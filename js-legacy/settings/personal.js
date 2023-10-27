var CRMSettingsPersonal = ( function($) {

    CRMSettingsPersonal = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS

        // INIT
        that.initClass();
    };

    CRMSettingsPersonal.prototype.initClass = function() {
        var that = this;
        //
        that.initStickyButton();
        //
        that.initFooterToggle();
        //
        that.initSubmit();
    };

    CRMSettingsPersonal.prototype.initStickyButton = function () {
        var that = this,
            $app_wrapper = $('#wa-app'),
            app_wrapper_height = $app_wrapper.height(),
            app_wrapper_offset = ($app_wrapper.offset() || {}).top,
            window_height = $(window).height();

        if (app_wrapper_height + app_wrapper_offset < window_height) {
            return;
        }

        that.$wrapper.find('.crm-form-buttons').sticky({
            fixed_css: { bottom: 0, 'z-index': 9 },
            fixed_class: 'sticky-bottom-shadow',
            showFixed: function(e) {
                e.element.css('min-height', e.element.height());
                e.fixed_clone.empty().append(e.element.children());
            },
            hideFixed: function(e) {
                e.fixed_clone.children().appendTo(e.element);
            },
            updateFixed: function(e, o) {
                this.width(e.element.width());
            }
        });
    };

    CRMSettingsPersonal.prototype.initSubmit = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $reminder_settings = $wrapper.find('.c-reminder-settings-wrapper'),
            reminder_settings = $reminder_settings.data('instance'),
            $form = that.$form,
            $loading = $form.find('.crm-loading');

        $form.submit(function (e) {
            e.preventDefault();

            $loading.show();

            if (reminder_settings) {
                if (reminder_settings.validateBeforeSave() === false) {
                    $loading.hide();
                    return;
                }
            }

            $.post($form.attr('action'), $form.serialize(), function(response) {
                if (response.status === "ok") {
                    $.crm.content.reload();
                }
            }).always(
                function () {
                    $form.find('.crm-loading').hide();
                }
            );
        });
    };

    CRMSettingsPersonal.prototype.initFooterToggle = function() {
        var that = this,
            active_class = "is-changed",
            $footer = that.$wrapper.find(".js-footer-actions"),
            $button = $footer.find(".js-submit-button");

        that.$wrapper.on("change keydown", "input, textarea, select", function() {
            toggle(true);
        });

        function toggle(changed) {
            if (changed) {
                $button.removeClass("green").addClass("yellow");
                $footer.addClass(active_class);
            } else {
                $button.removeClass("yellow").addClass("green");
                $footer.removeClass(active_class);
            }
        }
    };

    return CRMSettingsPersonal;

})(jQuery);
