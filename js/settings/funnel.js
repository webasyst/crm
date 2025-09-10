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
        that.initArchive();
        //
        that.initColorSection();
        //
        that.initIconSection();
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

                // Helper function to hide active color icon
                var hideColorIcon = function() {
                    if ($activeColor.length) {
                        $activeColor.removeClass(active_class);
                        $activeColor = false;
                    }
                };

                that.farbtastic = $.farbtastic(that.$colorPicker, function(color) {
                    if (that.$field.val() !== color) {
                        hideColorIcon();
                        that.$field.val( color ).change();
                    }
                });

                that.$wrapper.data("colorPicker", that);

                // Optimized field change handler
                var updateColor = function() {
                    var color = that.$field.val();
                    that.$icon.css("background-color", color);
                    that.farbtastic.setColor(color);
                };

                that.$field.on("change keyup", updateColor);

                that.$icon.on("click", function(event) {
                    event.preventDefault();
                    that.displayToggle( !that.is_opened );
                });

                that.$field.on("focus", function() {
                    if (!that.is_opened) {
                        that.displayToggle( true );
                    }
                });

                that.$field.on("keyup", hideColorIcon);

                // Optimized document event handler for ESC and click outside
                that.documentHandler = function(event) {
                    if (!that.is_opened) return;

                    // Check if wrapper still exists in DOM
                    var wrapperEl = that.$wrapper[0];
                    if (!$.contains(document, wrapperEl)) {
                        that.cleanup();
                        return;
                    }

                    // ESC key handler
                    if (event.type === 'keyup' && event.keyCode === 27) {
                        event.preventDefault();
                        event.stopPropagation();
                        that.displayToggle(false);
                        return;
                    }

                    // Click outside handler
                    if (event.type === 'click') {
                        var target = event.target;
                        // Check if click is inside ColorPicker (more efficient than jQuery closest)
                        var isClickInside = wrapperEl.contains(target) ||
                                           target === wrapperEl ||
                                           $(target).closest(that.$wrapper).length > 0;

                        if (!isClickInside) {
                            event.preventDefault();
                            event.stopPropagation();
                            that.displayToggle(false);
                        }
                    }
                };

                // Single event listener for both ESC and click outside
                document.addEventListener('keyup', that.documentHandler, true);
                document.addEventListener('click', that.documentHandler, true);

                // Cleanup method
                that.cleanup = function() {
                    document.removeEventListener('keyup', that.documentHandler, true);
                    document.removeEventListener('click', that.documentHandler, true);
                };
            };

            ColorPicker.prototype.displayToggle = function( show ) {
                // If closing ColorPicker, save current color first
                if (!show && this.is_opened && this.farbtastic) {
                    this.saveCurrentColor();
                }

                this.$wrapper.toggleClass("is-hidden", !show);
                this.is_opened = show;
            };

            // Save current color from Farbtastic to field
            ColorPicker.prototype.saveCurrentColor = function() {
                if (this.farbtastic && this.is_opened) {
                    var currentColor = this.farbtastic.color;
                    if (currentColor && this.$field.val() !== currentColor) {
                        this.$field.val(currentColor).change();
                    }
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

    CRMSettingsFunnel.prototype.initIconSection = function() {
        var that = this,
            $iconField = that.$wrapper.find(".c-icon-section .js-icon-field"),
            $iconItem = that.$wrapper.find(".c-icon-section .c-icon-list .js-icon-item");

        $iconItem.on("click", function() {
            var icon = $(this).data("icon");
            $iconItem.removeClass("selected");
            $(this).addClass("selected");
            $iconField.val(icon);
        });
    }

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
            $loading = $('<span class="c-notice"><i class="fas fa-spinner wa-animation-spin speed-1000 js-loading"></i></span>'),
            $saved = $('<span class="c-notice"><i class="fas fa-check"></i></span>'),
            $button = that.$wrapper.find(".js-funnel-save-button");


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

            $loading.insertAfter($button);

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $loading.remove();
                    $saved.insertAfter($button);
                   // $loading.removeClass('loading').addClass('yes').show();
                    var content_uri = $.crm.app_url + "settings/funnels/" + response.data.id + "/";
                    $.crm.content.load(content_uri);
                    $(document).trigger('wa_funnel_save');
                } else {
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
            $.waDialog.confirm({
                title: `<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ${that.locales["delete_confirm_title"]}?`,
                text: that.locales["delete_confirm_text"],
                success_button_title: that.locales["delete_confirm_button"],
                success_button_class: 'danger',
                cancel_button_title: `${that.locales["cancel_button"]}`,
                cancel_button_class: 'light-gray',
                onSuccess: deleteFunnel
            });
        }

        function deleteFunnel() {
            var href = "?module=settings&action=funnelDelete",
                data = {
                    id: that.funnel_id
                };

            if (!is_locked) {
                is_locked = true;
                $.post(href, data, function(response) {
                    var content_uri = $.crm.app_url + "settings/funnels/";
                    $.crm.content.load(content_uri);
                    $(document).trigger('wa_funnel_save');
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMSettingsFunnel.prototype.initArchive = function() {
        var that = this,
            is_locked = false;

        const $button = that.$wrapper.find(".js-archive-funnel");
        const state = $button.data("state");
        $button.on("click", showArchiveConfirm);

        function showArchiveConfirm(event) {
            event.preventDefault();
            if (state === "archived") {
                archiveFunnel();
                return;
            }
            $.waDialog.confirm({
                title: that.locales["archive_confirm_title"],
                text: that.locales["archive_confirm_text"],
                success_button_title: that.locales["archive_confirm_button"],
                success_button_class: 'brown',
                cancel_button_title: `${that.locales["cancel_button"]}`,
                cancel_button_class: 'light-gray',
                onSuccess: archiveFunnel
            });
        }

        function archiveFunnel() {
            var href = "?module=settings&action=" + (state === "archived" ? "funnelRestore" : "funnelArchive"),
                data = {
                    id: that.funnel_id
                };

            if (!is_locked) {
                is_locked = true;
                $.post(href, data, function(response) {
                    var content_uri = $.crm.app_url + "settings/funnels/";
                    $.crm.content.load(content_uri);
                    $(document).trigger('wa_funnel_save');
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
