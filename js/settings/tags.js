var CRMSettingsTags = ( function($) {

    CRMSettingsTags = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$footer = that.$wrapper.find(".js-footer-actions");
        that.$submitButton = that.$wrapper.find(".js-submit-button");
        that.$cancelButton = that.$wrapper.find(".js-cancel-button");

        // INIT
        that.initClass();
    };

    CRMSettingsTags.prototype.initClass = function() {
        var that = this;
        //
        that.initSave();
        //
        that.initColorSection();
        //
        that.$cancelButton.hide();
        that.$form.on("change", function() {
            that.toggleButton(true);
        });
    };

    CRMSettingsTags.prototype.initColorSection = function() {
        var that = this,
            //$colorWrapper = that.$wrapper.find(".js-color-selector-wrapper"),
            $colorList = that.$wrapper.find(".c-color-section .c-colors"),
            $colorField = that.$wrapper.find(".c-color-section .js-color-field"),
            $colorPickerWrapper = that.$wrapper.find(".js-toggle-wrapper");

        // VARS
        var active_class = "is-active",
            hidden_class = "is-hidden";

        // CLASSES
        var ColorPicker = ( function($) {

            ColorPicker = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$field = that.$wrapper.find(".js-color-field");
                that.$icon = that.$wrapper.find(".js-toggle");
                that.$colorPicker = that.$wrapper.find(".js-color-picker");
                that.$colors = that.$wrapper.closest(".js-color-selector-wrapper").find(".c-colors");

                // VARS

                // DYNAMIC VARS
                that.is_opened = false;
                that.farbtastic = false;

                // INIT
                that.initClass();
            };

            ColorPicker.prototype.initClass = function() {
                var that = this;

                document.addEventListener('click', (event) => {
                    // Close on click outside the colorpicker
                    const wrapper = that.$wrapper[0];
                    if (wrapper && !wrapper.contains(event.target)) {
                        that.displayToggle( false );
                    }
                });

                document.addEventListener('keydown', (event) => {
                    // Close on the Escape key is pressed
                    if (event.key === 'Escape') {
                        that.displayToggle( false );
                    }
                });

                that.farbtastic = $.farbtastic(that.$colorPicker, function(color) {
                    if (that.$field.val() !== color) {
                        hideColorIcon();
                        that.$field.val( color ).addClass("js-changed").change();
                    }
                });

                that.$wrapper.data("colorPicker", that);

                that.$field.on("change keyup", function() {
                    var color = $(this).val();
                    //
                    that.$icon.css("background-color", color);
                    that.farbtastic.setColor(color);
                });

                that.$icon.on("click", function(event) {
                    event.preventDefault();
                    that.displayToggle( !that.is_opened );
                });

                that.$field.on("click", function() {
                    that.displayToggle(!that.is_opened);
                });

                that.$field.on("keyup", hideColorIcon);

                function hideColorIcon() {
                    var $active = that.$colors.find("." + active_class);
                    if ($active.length) {
                        $active.removeClass(active_class);
                        $active = false;
                    }
                }
            };

            ColorPicker.prototype.displayToggle = function( show ) {
                var that = this;

                if (show) {
                    $colorPickerWrapper.addClass(hidden_class);
                    that.$wrapper.removeClass(hidden_class);
                    that.is_opened = true;
                } else {
                    that.$wrapper.addClass(hidden_class);
                    that.is_opened = false;
                }
            };

            return ColorPicker;

        })(jQuery);

        // EVENTS
        $colorList.on("click", ".js-set-color", setColor);

        $colorField.on("change", function() {
            var color = $(this).val(),
                $tag = $(this).closest(".js-color-selector-wrapper").find(".chip");

            var setHoverBackground = (color) => {
                $tag[0].style.setProperty('--chips-tags-background-color-hover', color ? color + '80' : 'inherit');
            };

            var rgb = getRGB(color);
            if (!rgb) {
                $tag.css("color", "").css("background-color", "")
                    .find("svg").css("color", "");
                setHoverBackground('');
                return;
            }
            //var crmColor = new $.crm.color(color),
            //    range = crmColor.getRange();
            var fontColor = color; // range[1];
            var bgColor = "rgba(" + rgb[0] + "," + rgb[1] + "," + rgb[2] + ", 0.3)";
            $tag.css("color", fontColor).css("background-color", bgColor)
                .find("svg").css("color", fontColor);
            setHoverBackground(fontColor);

            function getRGB(color) {
                var rgb = false;
                if (typeof color === "string") {
                    color = color.replace("#","");
                    if (color.length === 3) {
                        rgb = hex2rgb(color[0] + "" + color[0], color[1] + "" + color[1], color[2] + "" + color[2]);
                    } else if (color.length === 6) {
                        rgb = hex2rgb(color[0] + "" + color[1], color[2] + "" + color[3], color[4] + "" + color[5]);
                    }
                } else if (typeof color === "object" && color.length === 3) {
                    rgb = color;
                }
                return rgb;
            }

            // HEX
            function hex2rgb(r,g,b) {
                r = parseInt(r, 16);
                g = parseInt(g, 16);
                b = parseInt(b, 16);

                return (r >= 0 && g >= 0 && b >= 0) ? [r,g,b] : null;
            }
        });

        $colorPickerWrapper.each(function() {
            $wrapper = $(this);
            new ColorPicker({
                $wrapper: $wrapper
            });
        })


        //$colorField.change();

        // HANDLERS
        function setColor(event) {
            event.preventDefault();
            var $color = $(this),
                $section = $color.closest(".js-color-selector-wrapper"),
                $active = $section.find(".c-colors").find("." + active_class);

            if ($active.length) {
                $active.removeClass(active_class)
            }
            $color.addClass(active_class);

            $colorPickerWrapper.addClass(hidden_class);

            var color = $color.data("color");
            $section.find(".js-color-field").val(color).addClass("js-changed").change();
        }

    };

    CRMSettingsTags.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$submitButton,
            $cancel = that.$cancelButton;

        if (is_changed) {
            $button.addClass("yellow");
            $cancel.show();
        } else {
            $button.removeClass("yellow");
            $cancel.hide();
        }
    };

    CRMSettingsTags.prototype.initSave = function() {
        var that = this;

        that.$form.on("submit", function(event) {
            event.preventDefault();

            const changedData = that.$form.find(".js-changed").serializeArray();
            console.log(changedData);

            $.post("?module=settings&action=tagsSave", changedData, function() {
                $.crm.content.reload();
                if (changedData.length) {
                    $(document).trigger('wa_tags_save');
                }
            });
        });
    };

    return CRMSettingsTags;

})(jQuery);
