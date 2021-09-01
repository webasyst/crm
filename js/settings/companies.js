var CRMSettingsCompanies = ( function($) {

    CRMSettingsCompanies = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$footer = that.$wrapper.find(".js-footer-actions");
        that.$company = that.$wrapper.find(".c-company-section");
        that.$taxList = that.$company.find(".c-options-list");
        that.$logoWrapper = that.$wrapper.find(".js-logo-section");
        that.$submitButton = that.$wrapper.find(".js-submit-button");

        // VARS
        that.locales = options["locales"];
        that.company_id = options["company_id"];
        that.tax_option_template = options["tax_option_template"];

        that.empty_class = "is-empty";

        // DYNAMIC VARS
        that.start_left = 0;
        that.logo = false;
        that.files = [];

        // INIT
        that.initClass();
    };

    CRMSettingsCompanies.prototype.initClass = function() {
        var that = this;
        //
        that.initTabs();
        //
        that.initTaxActions();
        //
        that.initLogoChange();
        //
        that.initSubmit();
        //
        that.initDeleteCompany();
        //
        that.initTemplatesSection();
        //
        that.$wrapper.on("change", "input, select, textarea", function() {
            that.toggleButton(true);
        });
    };

    CRMSettingsCompanies.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".c-tabs-wrapper"),
            $companies = that.$wrapper.find(".c-companies-wrapper"),
            $list = $companies.find(".c-companies-list"),
            $activeTab = $list.find(".c-company.selected");

        initSetWidth();

        initSlider();

        initSort();

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

        function initSort() {
            var xhr = false;

            $list.sortable({
                //helper: "clone",
                distance: 10,
                items: "> li",
                axis: "x",
                start: function(event,ui) {
                },
                stop: save
            });

            function save() {
                var href = "?module=settings&action=companiesSortSave",
                    ids = getIds(),
                    data = {
                        ids: ids
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

                    $list.find(".c-company").each( function() {
                        result.push( $(this).data("id") );
                    });

                    return result.join(",");
                }
            }
        }


    };

    CRMSettingsCompanies.prototype.initTaxActions = function() {
        var that = this,
            $taxField = that.$company.find(".js-tax-name"),
            $taxList = that.$taxList;

        that.$company.on("click", ".js-delete-option", function(event) {
            event.preventDefault();
            $(this).closest("li").remove();

            if (!$taxField.find("li").length) {
                $taxField.attr("required", false);
            }

            that.toggleButton(true);
        });

        that.$company.on("click", ".js-add-option", addOption);

        initSort();

        function addOption(event) {
            event.preventDefault();

            var html = that.tax_option_template,
                $html = $(html);

            $html.appendTo($taxList);

            $taxField.attr("required", true);

            that.toggleButton(true);
        }

        function initSort() {
            $taxList.sortable({
                handler: ".js-sort-toggle",
                helper: "clone",
                distance: 10,
                items: "> li",
                axis: "y",
                start: function(event,ui) {
                },
                stop: function(event,ui) {
                }
            });
        }
    };

    CRMSettingsCompanies.prototype.initLogoChange = function() {
        var that = this,
            timeout = false,
            is_entered = false;

        // DOM
        var $wrapper = that.$logoWrapper,
            $droparea = $wrapper.find(".js-drop-area"),
            $field = $droparea.find(".js-field"),
            $image = $wrapper.find(".js-image");

        var highlighted_class = "is-highlighted",
            hover_class = "is-hover";

        // EVENTS

        // drop event
        $droparea.on("drop", onDrop);
        // drop event
        $field.on("change", onChange);
        // delete event
        $wrapper.on("click", ".js-delete-logo", onDeleteClick);

        $(document).on("myDragEnter", myDragEnterWatcher);

        function myDragEnterWatcher() {
            var is_exist = $.contains(document, $droparea[0]);
            if (is_exist) {
                $droparea.addClass(highlighted_class);
            } else {
                $(document).off("myDragEnter", watcher);
            }
        }

        $(document).on("myDragLeave", myDragLeaveWatcher);

        function myDragLeaveWatcher() {
            var is_exist = $.contains(document, $droparea[0]);
            if (is_exist) {
                $droparea.removeClass(highlighted_class);
            } else {
                $(document).off("myDragLeave", watcher);
            }
        }

        $droparea.on("dragover", onDragOver);

        function onDragOver(event) {
            event.preventDefault();
            if (!timeout)  {
                if (!is_entered) {
                    is_entered = true;
                    $droparea.addClass(hover_class);
                }
            } else {
                clearTimeout(timeout);
            }

            timeout = setTimeout(function () {
                timeout = null;
                is_entered = false;
                $droparea.removeClass(hover_class);
            }, 100);
        }

        // FUNCTIONS

        function onDrop(event) {
            event.stopPropagation();

            var files = event.originalEvent.dataTransfer.files;
            if (files.length) {
                attachFile(files);
                that.toggleButton(true);
            }
        }

        function onChange() {
            var files = this.files;
            if (files.length) {
                attachFile(files);
                that.toggleButton(true);
            }
        }

        function attachFile(files) {
            var file = files[0],
                image_type = /^image\/(png|jpe?g|gif)$/,
                is_image = ( file.type.match(image_type) );

            if (is_image) {
                var reader = new FileReader();
                reader.onload = function(event) {
                    $image.attr("src", event.target.result);
                    that.$logoWrapper.removeClass(that.empty_class);
                };
                reader.readAsDataURL(file);

                that.logo = file;
            }
        }

        function onDeleteClick(event) {
            event.preventDefault();

            $image.attr("src", "");

            var field_name = $field.attr("name");
            if (field_name) {
                var $hidden = $("<input type=\"hidden\" />").attr("name", field_name).val("delete");
                $field.attr("name", "").data("name", field_name).after($hidden);
            }

            $wrapper.addClass(that.empty_class);

            that.logo = false;

            that.toggleButton(true);
        }
    };

    CRMSettingsCompanies.prototype.initSubmit = function() {
        var that = this,
            $form = that.$wrapper.find(".js-form"),
            $errorsPlace = that.$wrapper.find(".js-errors-place"),
            $taxList = that.$taxList,
            is_locked = false;

        $form.on("submit", onSubmit);

        $form.on("keydown keypress keyup", "input", function(event) {
            var key = event.keyCode;
            if (key === 13) {
                event.preventDefault();
            }
        });

        function onSubmit(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=companiesSave",
                    data = prepareData(),
                    formData = getFormData(data);

                var $loading = $(that.locales.loading);
                $loading.insertAfter(that.$submitButton);

                $.ajax({
                    url: href,
                    data: formData,
                    dataType: "json",
                    processData: false,
                    contentType: false,
                    type: 'POST',
                    success: function(response){
                        if (response.status === "ok") {
                            var $saved = $(that.locales["saved_html"]);
                            $saved.insertAfter(that.$submitButton);

                            if (!that.company_id) {
                                var content_uri = $.crm.app_url + "settings/companies/" + response.data.id + "/";
                                $.crm.content.load(content_uri);
                            } else {
                                $.crm.content.reload();
                            }
                        } else if (response.errors) {
                            showErrors(response.errors);
                        }
                    }
                }).always( function () {
                    $loading.remove();
                    is_locked = false;
                });

                function getFormData(data) {
                    var formData = new FormData();

                    $.each(data, function(index, item) {
                        formData.append(item.name, item.value);
                    });

                    $.each(that.files, function(index, item) {
                        formData.append("images[" + item.code + "]", item.file);
                    });

                    if (that.logo) {
                        formData.append("logo", that.logo);
                    }

                    var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        formData.append("_csrf", csrf);
                    }

                    return formData;
                }
            }

            function prepareData() {
                $taxList.find(".c-tax-option").each( function(index) {
                    var $tax = $(this),
                        $type = $tax.find(".js-type"),
                        $percent = $tax.find(".js-percent");

                    $type.attr("name", "company[tax_options][" + index + "][tax_type]");
                    $percent.attr("name", "company[tax_options][" + index + "][tax_percent]");
                });

                return $form.serializeArray();
            }

            function showErrors(errors) {
                var error_class = "error";

                errors = (errors ? errors : []);

                $.each(errors, function(index, item) {
                    var name = item.name,
                        text = item.value;

                    var $field = that.$wrapper.find("[name=\"" + name + "\"]");

                    $field.removeClass(error_class);
                    $field.parent().find('span.errormsg').remove();

                    if ( name === "contact[firstname]" && !$field.is(":visible") ) {
                        $field = that.$wrapper.find(".js-contact-autocomplete");
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
        }
    };

    CRMSettingsCompanies.prototype.initDeleteCompany = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-company-delete", showDialog);

        function showDialog(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=companyDeleteDialog",
                    data = {
                        id: that.company_id
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html,
                        options: {
                            onDelete: function() {
                                var content_uri = $.crm.app_url + "settings/companies/";
                                $.crm.content.load(content_uri);
                            }
                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMSettingsCompanies.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$submitButton,
            $cancel = that.$footer.find(".js-edit-actions");

        if (is_changed) {
            $button.addClass("yellow").removeClass("green");
            $cancel.show();
        } else {
            $button.removeClass("yellow").addClass("green");
            $cancel.hide();
        }
    };

    CRMSettingsCompanies.prototype.initTemplatesSection = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".c-templates-section"),
            $optionSection = $wrapper.find(".js-template-options-wrapper"),
            xhr = false;

        initTemplatesToggle();

        initSlider();

        $wrapper.find(".c-color-section").each( function() {
            initColorSection( $(this) );
        });

        //
        $wrapper.find(".js-image-section").each( function() {
            initParamImageSection( $(this) );
        });

        //

        function initTemplatesToggle() {
            var active_class = "is-active",
                $activeTemplate = $wrapper.find(".js-template-wrapper." + active_class);

            $wrapper.on("click", ".js-template-wrapper", function(event) {
                event.preventDefault();
                setTemplate( $(this) );
            });

            function setTemplate( $template ) {
                if ($template.hasClass(active_class)) {
                    return false;
                }

                var template_id = $template.data("id");

                // set checkbox
                var $field = $template.find(".js-field");
                $field.attr("checked", true).trigger("change");

                // render template
                if ($activeTemplate.length) {
                    $activeTemplate.removeClass(active_class);
                }
                $activeTemplate = $template.addClass(active_class);

                updateTemplateOptions(template_id, that.company_id);
            }
        }

        function updateTemplateOptions(template_id, company_id) {
            if (xhr) { xhr.abort(); }

            var href = "?module=settings&action=companiesRenderParams",
                data = {
                    company_id: company_id,
                    template_id: template_id
                };

            xhr = $.post(href, data, function(html) {
                $optionSection.html(html);

                $optionSection.find(".c-color-section").each( function() {
                    initColorSection( $(this) );
                });

                $optionSection.find(".js-image-section").each( function() {
                    initParamImageSection( $(this) );
                });

            }).always( function() {
                xhr = false;
            });
        }

        function initColorSection($colorSection) {
            // DOM
            var $colorList = $colorSection.find(".c-colors"),
                $colorField = $colorSection.find(".js-color-field");

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

                    that.$field.on("focus", function() {
                        if (!that.is_opened) {
                            that.displayToggle( true );
                        }
                    });

                    that.$field.on("keyup", hideColorIcon);

                    function hideColorIcon() {
                        if ($activeColor.length) {
                            $activeColor.removeClass(active_class);
                            $activeColor = false;
                        }
                    }

                    $(document).on("keyup", watcher);

                    function watcher(event) {
                        var is_exist = $.contains(document, that.$wrapper[0]);
                        if (is_exist) {
                            var code = event.keyCode;
                            if (code === 27) {
                                if (that.is_opened) {
                                    that.displayToggle(false);
                                }
                            }
                        } else {
                            $(document).off("keyup", watcher);
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

            // INITS
            var colorPicker = new ColorPicker({
                $wrapper: $colorSection.find(".js-toggle-wrapper").first()
            });

            // EVENTS
            $colorList.on("click", ".js-set-color", setColor);

            $colorField.trigger("change");

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

                if (colorPicker.is_opened) {
                    colorPicker.displayToggle(false);
                }
            }
        }

        function initParamImageSection($wrapper) {
            var timeout = false,
                is_entered = false;

            // DOM
            var $droparea = $wrapper.find(".js-drop-area"),
                $field = $droparea.find(".js-field"),
                $image = $wrapper.find(".js-image");

            var highlighted_class = "is-highlighted",
                hover_class = "is-hover";

            // EVENTS

            // drop event
            $droparea.on("drop", onDrop);
            // drop event
            $field.on("change", onChange);
            // delete event
            $wrapper.on("click", ".js-delete-image", onDeleteClick);

            $(document).on("myDragEnter", myDragEnterWatcher);

            function myDragEnterWatcher() {
                var is_exist = $.contains(document, $droparea[0]);
                if (is_exist) {
                    $droparea.addClass(highlighted_class);
                } else {
                    $(document).off("myDragEnter", watcher);
                }
            }

            $(document).on("myDragLeave", myDragLeaveWatcher);

            function myDragLeaveWatcher() {
                var is_exist = $.contains(document, $droparea[0]);
                if (is_exist) {
                    $droparea.removeClass(highlighted_class);
                } else {
                    $(document).off("myDragLeave", watcher);
                }
            }

            $droparea.on("dragover", onDragOver);

            function onDragOver(event) {
                event.preventDefault();
                if (!timeout)  {
                    if (!is_entered) {
                        is_entered = true;
                        $droparea.addClass(hover_class);
                    }
                } else {
                    clearTimeout(timeout);
                }

                timeout = setTimeout(function () {
                    timeout = null;
                    is_entered = false;
                    $droparea.removeClass(hover_class);
                }, 100);
            }

            // FUNCTIONS

            function onDrop(event) {
                event.stopPropagation();

                var files = event.originalEvent.dataTransfer.files;
                if (files.length) {
                    attachFile(files);
                    that.toggleButton(true);
                }
            }

            function onChange() {
                var files = this.files;
                if (files.length) {
                    attachFile(files);
                    that.toggleButton(true);
                }
            }

            function attachFile(files) {
                var file = files[0],
                    image_type = /^image\/(png|jpe?g|gif)$/,
                    is_image = ( file.type.match(image_type) );

                if (is_image) {
                    var reader = new FileReader();
                    reader.onload = function(event) {
                        $image.attr("src", event.target.result);
                        $wrapper.removeClass(that.empty_class);
                    };
                    reader.readAsDataURL(file);

                    var attached_file = {
                        code: $wrapper.data("code"),
                        type: "param_image",
                        file: file
                    };

                    that.files.push(attached_file);

                    removeAttachedFile();

                    $wrapper.data("file", attached_file);
                }
            }

            function removeAttachedFile() {
                var attached_file = $wrapper.data("file");
                if (attached_file) {
                    var index = that.files.indexOf(attached_file);
                    that.files.splice(index, 1);
                    $wrapper.data("file", false);
                }
            }

            function onDeleteClick(event) {
                event.preventDefault();

                $image.attr("src", "");

                var field_name = $field.attr("name");
                if (field_name) {
                    var $hidden = $("<input type=\"hidden\" />").attr("name", field_name).val("delete");
                    $field.attr("name", "").data("name", field_name).after($hidden);
                }

                $wrapper.addClass(that.empty_class);

                removeAttachedFile();

                that.toggleButton(true);
            }
        }

        function initSlider() {
            var Slider = ( function($) {

                Slider = function(options) {
                    var that = this;

                    // DOM
                    that.$wrapper = options["$wrapper"];
                    that.$slider = options["$slider"];
                    that.$activeSlide = ( options["$activeSlide"] || false);

                    // VARS

                    // DYNAMIC VARS
                    that.type_class = false;
                    that.left = 0;
                    that.wrapper_w = false;
                    that.slider_w = false;

                    // INIT
                    that.initClass();
                };

                Slider.prototype.initClass = function() {
                    var that = this,
                        $window = $(window);

                    // INIT

                    that.detectSliderWidth();
                    //
                    that.initStartPosition();

                    // EVENTS

                    $window.on("resize", onResize);
                    //
                    that.$wrapper.on("click", ".c-slider-arrow", function(event) {
                        event.preventDefault();
                        var $link = $(this);
                        if ($link.hasClass("left")) {
                            that.moveSlider( false );
                        }
                        if ($link.hasClass("right")) {
                            that.moveSlider( true );
                        }
                    });

                    // FUNCTIONS

                    function onResize() {
                        var is_exist = $.contains(document, that.$wrapper[0]);
                        if (is_exist) {
                            var is_change = ( that.wrapper_w !== that.$wrapper.outerWidth() );
                            if (is_change) {
                                that.reset();
                            }
                        } else {
                            $window.off("resize", onResize);
                        }
                    }
                };

                Slider.prototype.initStartPosition = function() {
                    var that = this,
                        start_left = 0;

                    if (that.$activeSlide.length) {
                        var slide_w = that.$activeSlide.outerWidth(),
                            delta = Math.floor(Math.abs(that.$wrapper.offset().left - that.$activeSlide.offset().left));

                        if (delta + slide_w > that.wrapper_w) {
                            start_left = delta - 40;
                        }
                    }

                    if (start_left) {
                        that.start_left = start_left;
                        that.moveSlider(true, start_left);
                    } else {
                        that.showArrows();
                    }
                };

                Slider.prototype.detectSliderWidth = function() {
                    var that = this;

                    that.wrapper_w = that.$wrapper.outerWidth();
                    that.slider_w = that.$slider.outerWidth();
                };

                Slider.prototype.showArrows = function() {
                    var that = this;

                    if (that.left >= 0) {
                        if (that.wrapper_w < that.slider_w) {
                            setType("type-1");
                        } else {
                            setType();
                        }
                    } else {
                        if (that.wrapper_w < (that.slider_w - Math.abs(that.left) ) ) {
                            setType("type-2");
                        } else {
                            setType("type-3");
                        }
                    }

                    function setType( type_class ) {
                        if (that.type_class) {
                            that.$wrapper.removeClass(that.type_class);
                        }
                        if (type_class) {
                            that.$wrapper.addClass(type_class);
                            that.type_class = type_class;
                        }
                    }
                };

                Slider.prototype.setLeft = function( left ) {
                    var that = this;

                    if (!(Math.abs(left) > 0)) {
                        left = 0;
                    }

                    that.$slider.css({
                        "-webkit-transform": "translate(" + (left ? left + "px" : 0) + ", 0)",
                        "transform": "translate(" + (left ? left + "px" : 0) + ", 0)"
                    });

                    that.left = left;
                };

                Slider.prototype.moveSlider = function(right, left) {
                    var that = this,
                        step = ( left ? left : parseInt(that.wrapper_w/2) ),
                        delta = (that.slider_w - that.wrapper_w),
                        new_left = 0;

                    if (delta > 0) {
                        new_left = Math.abs(that.left) + ( right ? step : -step );
                        if (new_left > delta ) {
                            new_left = delta;
                        } else if (new_left < 0) {
                            new_left = 0;
                        }
                    }

                    that.setLeft(-new_left);
                    that.showArrows();
                };

                Slider.prototype.reset = function() {
                    var that = this;

                    //
                    that.setLeft(0);
                    //
                    that.detectSliderWidth();
                    //
                    that.showArrows();
                };

                return Slider;

            })($);

            new Slider({
                $wrapper: $wrapper.find(".c-templates-slider"),
                $slider: $wrapper.find(".c-templates-list")
            });
        }
    };

    return CRMSettingsCompanies;

})(jQuery);

var CRMCompanyDeleteDialog = ( function($) {

    CRMCompanyDeleteDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.company_id = options["company_id"];
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCompanyDeleteDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initDelete();
    };

    CRMCompanyDeleteDialog.prototype.initDelete = function() {
        var that = this,
            $form = that.$wrapper.find("form"),
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
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

        function showErrors(ajax_errors, errors) {
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

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);

                    that.$wrapper.append($text);

                    $field
                        .addClass(error_class)
                        .one("focus click change", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=companyDelete";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var content_uri = $.crm.app_url + "settings/companies/";
                        $.crm.content.load(content_uri);
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMCompanyDeleteDialog;

})(jQuery);
