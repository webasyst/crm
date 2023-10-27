var CRMSettingsFunnel = ( function($) {

    CRMSettingsFunnel = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$stagesSection = that.$wrapper.find(".c-stages-section");
        that.$stages = that.$stagesSection.find(".c-stages-list");

            // VARS
        that.funnel_id = options["funnel_id"];
        that.stage_html = options["stage_html"];
        that.locales = options["locales"];

        // INIT
        that.initClass();
    };

    CRMSettingsFunnel.prototype.initClass = function() {
        var that = this;
        //
        that.initSave();
        //
        that.initDelete();
        //
        that.initColorSection();
        //
        that.initStagesSection();
        //
        that.initNoAccessList();
        //
        that.$form.on("input", function() {
            that.toggleButton(true);
        });
    };

    CRMSettingsFunnel.prototype.initColorSection = function() {
        var that = this,
            $startColorField = that.$wrapper.find(".js-start-color-field"),
            $endColorField = that.$wrapper.find(".js-end-color-field");

        // DOM
        var $colorList = that.$wrapper.find(".c-color-section .c-colors"),
            $colorField = that.$wrapper.find(".c-color-section .js-color-field"),
            $colorStages = that.$wrapper.find(".c-color-section .c-stages");

        // VARS
        var active_class = "is-active";

        // DYNAMIC VARS
        var $activeColor = $colorList.find("." + active_class);

        // CLASSES
        var ColorPicker = ( function($) {

            ColorPicker = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$field = $colorField;
                that.$icon = that.$wrapper.find(".js-toggle");
                that.$colorPicker = that.$wrapper.find(".js-color-picker");

                // VARS

                // DYNAMIC VARS
                that.is_opened = false;
                that.farbtastic = false;

                // INIT
                that.initClass();
            };

            ColorPicker.prototype.initClass = function() {
                var that = this;

                that.farbtastic = $.farbtastic(that.$colorPicker, function(color) {
                    if (that.$field.val() !== color) {
                        hideColorIcon();
                        that.$field.val( color ).change();
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
                    if ($activeColor.length) {
                        $activeColor.removeClass(active_class);
                        $activeColor = false;
                    }
                }
            };

            ColorPicker.prototype.displayToggle = function( show ) {
                var that = this,
                    hidden_class = "is-hidden";

                if (show) {
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
            renderStageColors($colorField.val());
        });

        // INITS
        new ColorPicker({
            $wrapper: that.$wrapper.find(".js-toggle-wrapper").first()
        });

        $colorField.change();

        $.crm.renderSVG(that.$wrapper);

        // HANDLERS
        function setColor(event) {
            event.preventDefault();
            var $color = $(this);

            if ($activeColor.length) {
                $activeColor.removeClass(active_class)
            }
            $color.addClass(active_class);
            $activeColor = $color;

            var color = $color.data("color");
            $colorField.val(color).change();
        }

        function renderStageColors(color) {
            var $stages = $colorStages.find(".c-stage .c-ornament .svg-icon"),
                stages_count = $stages.length;

            if (color.length === 4 || color.length === 7) {
                var $crmColor = new $.crm.color(color),
                    range = $crmColor.getRange();

                // Set
                $startColorField.val(range[0]);
                $endColorField.val(range[1]);

                // render
                $stages.each( function(index) {
                    var percent = Math.floor((index + 1) * 100/stages_count),
                        color = range.getColor(percent);

                    var $icon = $(this),
                        $svg = $icon.find("svg"),
                        is_rendered = $svg.length;

                    if (is_rendered) {
                        $svg.find("polygon").css("fill", color);
                    } else {
                        $icon.data("color", color);
                    }
                });
            }
        }
    };

    CRMSettingsFunnel.prototype.initStagesSection = function() {
        var that = this;

        // DOM
        var $section = that.$stagesSection,
            $list = that.$stages;

        $list.on("click", ".js-delete-stage", deleteStage);

        $list.on("keydown keypress keyup", "input", function(event) {
            var key = event.keyCode;
            if (key === 13) {
                event.preventDefault();
            }
        });

        $section.on("click", ".js-add-stage", addStage);

        $list.sortable({
            distance: 10,
            handle: ".js-sort-toggle",
            helper: "clone",
            items: "> li",
            axis: "y",
            start: function(event,ui) {
            },
            stop: function(event,ui) {
                that.toggleButton(true);
            }
        });

        initStageTime($list);

        function deleteStage(event) {
            event.preventDefault();
            var items_count = that.$stages.find("li").length;
            if (items_count > 1) {
                $(this).closest(".c-stage").remove();
            }

            that.toggleButton(true);
        }

        function addStage(event) {
            event.preventDefault();
            var stage = that.stage_html,
                $stage = $(stage);

            that.$stages.append($stage);

            initStageTime($stage);

            that.toggleButton(true);
        }

        function initStageTime($wrapper) {

            $wrapper.find(".js-time-limit-wrapper").each( function() {
                init($(this));
            });

            function init($wrapper) {
                var $visibleField = $wrapper.find(".js-visible-time-field"),
                    $hiddenField = $wrapper.find(".js-hidden-time-field"),
                    $select = $wrapper.find(".js-time-period-select");

                $wrapper.on("click", ".js-show-time", function() {
                    toggleSection(true);
                });

                $wrapper.on("click", ".js-remove-limit", function () {
                    toggleSection(false);
                });

                $visibleField.on("change", calculate);

                $select.on("change", calculate);

                $visibleField.on("keyup", function() {
                    var $field = $(this),
                        value = $field.val(),
                        formatted_value = parseInt( value.replace(",", "").replace(".", "") );

                    if (isNaN(formatted_value)) {
                        $field.val("");
                    } else if (formatted_value) {
                        $field.val(formatted_value);
                    } else {
                        $field.val("");
                    }
                });

                setData();

                function setData() {
                    var value = $hiddenField.val();
                    if (value && parseInt(value) > 0) {
                        if ((parseInt(value) % 24) === 0) {
                            $select.val("24");
                            $visibleField.val( parseInt(value)/24 );
                        } else {
                            $select.val("1");
                        }
                    }
                }

                function calculate() {
                    var count = parseInt( $visibleField.val() ),
                        period = parseInt( $select.val() ),
                        result = "";

                    if (count > 0 && period > 0) {
                        result = count * period;
                    }

                    $hiddenField.val(result);
                }

                function toggleSection(show) {
                    var active_class = "is-extended";
                    if (show) {
                        $wrapper.addClass(active_class);
                        $visibleField.focus();
                    } else {
                        $wrapper.removeClass(active_class);
                        $visibleField.val('');
                        $hiddenField.val('');
                        that.toggleButton(true);
                    }
                }
            }
        }
    };

    CRMSettingsFunnel.prototype.initSave = function() {
        var that = this,
            is_locked = false,
            $loading = that.$wrapper.find(".js-loading");

        that.$form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();
            if (!is_locked) {
                var data = getData();
                if (data) {
                    is_locked = true;
                    save(data);
                }
            }
        }

        function getData() {
            var data = that.$form.serializeArray();

            that.$stages.find("li").each( function(index) {
                var $li = $(this),
                    id = $li.data("id");

                data.push({
                    name: "stages[" + index + "][name]",
                    value: $li.find(".js-name-field").val()
                });

                if (id) {
                    data.push({
                        name: "stages[" + index + "][id]",
                        value: id
                    });
                }
            });

            return data;
        }

        function save(data) {
            var href = "?module=settings&action=funnelSave";

            $loading.removeClass('yes').removeClass('no').addClass('loading').show();

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $loading.removeClass('loading').addClass('yes').show();
                    var content_uri = $.crm.app_url + "settings/funnels/" + response.data.id + "/";
                    $.crm.content.load(content_uri);
                } else {
                    $loading.hide();
                }
            }, "json").always( function() {
                is_locked = false;
            });
        }
    };

    CRMSettingsFunnel.prototype.initDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-funnel", showConfirm);

        function showConfirm(event) {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales["delete_confirm_title"],
                text: that.locales["delete_confirm_text"],
                button: that.locales["delete_confirm_button"],
                onConfirm: deleteFunnel
            });
        }

        function deleteFunnel() {
            var href = "?module=settings&action=funnelDelete",
                data = {
                    id: that.funnel_id
                };

            if (!is_locked) {
                is_locked = true;
                $.post(href,data, function(response) {
                    var content_uri = $.crm.app_url + "settings/funnels/";
                    $.crm.content.load(content_uri);
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMSettingsFunnel.prototype.toggleButton = function( set_active ) {
        var that = this,
            $button = that.$wrapper.find(".js-funnel-save-button"),
            $actions = that.$wrapper.find(".js-hidden-actions");

        if (set_active) {
            $button
                .removeClass("green")
                .addClass("yellow");

            $actions.show();

        } else {
            $button
                .removeClass("yellow")
                .addClass("green");

            $actions.hide();
        }
    };

    CRMSettingsFunnel.prototype.initNoAccessList = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-no-access-wrapper"),
            active_class = "is-active";

        $wrapper.on("click", ".js-show-access-list", function(event) {
            event.preventDefault();
            $wrapper.addClass(active_class);
        });

        $wrapper.on("click", ".js-hide-access-list", function(event) {
            event.preventDefault();
            $wrapper.removeClass(active_class);
        });
    };

    return CRMSettingsFunnel;

})(jQuery);