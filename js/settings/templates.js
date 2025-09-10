var CRMSettingsTemplate = (function ($) {

    CRMSettingsTemplate = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find(".js-template-form");
        that.$paramsSection = that.$wrapper.find(".c-params-section");
        that.$param_list = that.$paramsSection.find(".c-params-list");
        that.$textarea = that.$wrapper.find(".js-content-body");
        that.$loading_icon = $('<span class="c-notice"><i class="fas fa-spinner wa-animation-spin speed-1000"></i></span>');
        // VARS
        that.preview_dialog_template = options.preview_dialog_template;
        that.site_app_url = options.site_app_url;
        that.template = options["template"] || {};
        that.param_html = options["param_html"];
        that.template_id = options["template_id"];
        that.locales = options["locales"];
        that.company_count = options["company_count"];

        that.ace = null,
            // INIT
            that.initClass();
    };

    CRMSettingsTemplate.prototype.initClass = function () {
        var that = this;
        //
        that.initHelp();

        that.initTabs();
        //
        that.initAce();
        //
        that.initSave();
        //
        that.initDelete();
        //
        that.initParamsSection();
        //
        that.initPreviewIframe();
        //
        that.initIDAutoFiller();

        that.$form.on("change", "input, select, textarea", function () {
            that.toggleButton(true);
        });
        that.$form.on('input change', function () {
            that.toggleButton(true);
        });
        //
        that.initResetTemplate();
    };

    CRMSettingsTemplate.prototype.initTabs = function () {
        var that = this,
            $section = that.$wrapper.find(".c-tabs-wrapper"),
            $companies = that.$wrapper.find(".c-companies-wrapper"),
            $list = $companies.find(".c-companies-list"),
            $activeTab = $list.find(".c-company.selected");

            initSetWidth();
            initSlider();

            function initSetWidth() {
                var $window = $(window),
                    other_w = $section.find(".c-add-wrapper").outerWidth();
    
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
                    var section_w = $section.outerWidth(true),
                        max_w = section_w - other_w - 38;
                    $companies.css("max-width", max_w + "px");
                }
            }

            function initSlider() {
                $.crm.tabSlider({
                    $wrapper: $companies,
                    $slider: $list,
                    $activeSlide: ($activeTab.length ? $activeTab : false)
                });
            }
    };

    CRMSettingsTemplate.prototype.initResetTemplate = function () {
        var that = this,
            $reset = that.$wrapper.find(".js-reset-template"),
            $redactorW = that.$wrapper.find(".js-redactor-wrapper");

        //Activate the reset when press the textarea
        $redactorW.on('keypress', function () {
            $reset.removeClass('hidden');
        });

        //Reset text in js-redactor
        $reset.on('click', getOriginTemplate);

        function getOriginTemplate() {
            var data = 'template_id=' + that.template_id,
                href = "?module=settings&action=templatesReset";
               // ace = that.ace.getSession();
            $.post(href, data, function (response) {
                if (response.status === "ok") {
                    $('.js-content-body').text(response.data.template);
                    $('.js-style-version').val('2');
                    $(that.preview_dialog_template).find('.js-style-version').val('2');
                    that.initAce();
                    that.toggleButton(true);
                }
            }, "json").always(function () {
            });
            $reset.addClass('hidden');
        }
    };

    CRMSettingsTemplate.prototype.initIDAutoFiller = function () {
        var that = this,
            xhr = null,
            transliterateTimer,
            $param_list = that.$param_list,
            $form = that.$form;

        //if the field is changed, turn off auto filler
        $param_list.on('change', 'input.js-code-field', function () {
            $(this).removeClass('auto-filler');
        });

        $param_list.on('keyup', 'input.js-name-field',
            function () {
                var that = $(this),
                    name_value = that.val(),
                    $code_value = that.parents('tr').find('.js-code-field'),
                    get_attr = $code_value.hasClass('auto-filler'),
                    $submit = $form.find('[type="submit"]'),
                    $loading = $code_value.next('.fa-spinner');

                if ($submit.prop('disabled') || !get_attr) {
                    return;
                }
                $loading = $loading.length ? $loading : $('<span class="c-notice"><i class="fas fa-spinner wa-animation-spin speed-1000"></i></span>');
                $loading.insertAfter($code_value);

                $submit.prop('disabled', true);

                transliterateTimer && clearTimeout(transliterateTimer);
                transliterateTimer = setTimeout(function () {
                    var clear = function () {
                        if (xhr) {
                            xhr.abort();
                            xhr = null;
                        }
                        transliterateTimer && clearTimeout(transliterateTimer);
                        $submit.prop('disabled', false);
                        $('.fa-spinner').remove();
                    };
                    xhr = $.post($.crm.app_url + '?module=settings&action=fieldTransliterate',
                        { 'name[]': name_value },
                        function (r) {
                            clear();
                            if (r.status === 'ok') {
                                if (get_attr) {
                                    $code_value.val(r.data);
                                }
                            }
                        },
                        'json');
                }, 300);
            });
    };

    CRMSettingsTemplate.prototype.validate = function () {
        var that = this,
            $param_code = that.$param_list.find('.js-code-field'),
            locales = that.locales;

        that.$param_list.find('.errormsg').remove();
        that.$param_list.find('.error').removeClass('error');

        $param_code.each(checkCode);

        function checkCode() {
            var $input = $(this),
                value = $(this).val(),
                $text = $("<span />").addClass("errormsg");

            //Regular
            var number = value.search(/^\d/),
                symbols = value.search(/[^a-zA-Z0-9_]/);

            if (number >= 0) {
                $input.addClass('error');
                $input.after($text.text(locales['validate_first_num']));
                return;
            }

            if (symbols >= 0) {
                $input.addClass('error');
                $input.after($text.text(locales['validate_symbols']));
                return;
            }

            if ($input.hasClass('error')) {
                return;
            }
            var $copies = $param_code.filter(function () {
                return this.value === $input.val();
            });

            if ($copies.length >= 2) {
                $copies.addClass('error').parent().append($('<span class="errormsg">').text(locales['validate_copies']));
                return;
            }
        }

        //check errors in c-param_list
        if (that.$param_list.find('.error').length) {
            return false;
        } else {
            return true;
        }

    };

    CRMSettingsTemplate.prototype.initDelete = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-template", showConfirm);

        function showConfirm(event) {
            event.preventDefault();

            if (that.company_count) {
                var dialog = $.waDialog.alert({
                    text: that.locales["success_text"],
                    button_title: that.locales["success_button"]
                });

                setTimeout(function () {
                    var is_exist = $.contains(document, dialog.$wrapper[0]);
                    if (is_exist) {
                        dialog.close();
                    }
                }, 10000);

            } else {
                $.waDialog.confirm({
                    title: '<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ' + that.locales["delete_confirm_title"],
                    text: that.locales["delete_confirm_text"],
                    success_button_title: that.locales["delete_confirm_button"],
                    success_button_class: 'danger',
                    cancel_button_title: that.locales["success_button"],
                    cancel_button_class: 'light-gray',
                    onSuccess: deleteTemplate
                });
            }
        }

        function deleteTemplate() {
            var href = "?module=settings&action=templatesDelete",
                data = {
                    id: that.template_id
                };

            if (!is_locked) {
                is_locked = true;
                $.post(href, data, function (response) {
                    var content_uri = $.crm.app_url + "settings/templates/";
                    $.crm.content.load(content_uri);
                }, "json").always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMSettingsTemplate.prototype.initSave = function () {
        var that = this,
            is_locked = false,
            $button = that.$wrapper.find(".js-template-save-button");
        $loading = that.$loading_icon;
        that.$form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();
            $(document).trigger("updateEditorTextarea");

            $loading.insertAfter($button);

            var validate = that.validate();
            if (!validate) {
                return false;
            }

            if (!is_locked) {
                var data = that.$form.serializeArray();

                if (data) {
                    is_locked = true;
                    save(data);
                }
            }
        }

        function save(data) {
            var href = "?module=settings&action=templatesSave";
            $.post(href, data, function (response) {
                if (response.status === "ok") {
                    $('.fa-spinner').remove();
                    var $saved = $('<span class="c-notice"><i class="fas fa-check"></i></span>');
                    $saved.insertAfter($button);
                    var content_uri = $.crm.app_url + "settings/templates/" + response.data.id + "/";
                    $.crm.content.load(content_uri);
                }
            }, "json").always(function () {
                is_locked = false;
            });
        }
    };

    CRMSettingsTemplate.prototype.initParamsSection = function () {
        var that = this;

        // DOM
        var $section = that.$paramsSection;

        $section.on("click", ".js-delete-param", deleteParam);
        $section.on("click", ".js-add-param", addParam);

        function deleteParam(event) {
            event.preventDefault();

            $(this).closest("tr").remove();

            that.toggleButton(true);
        }

        function addParam(event) {
            event.preventDefault();
            var param = that.param_html,
                $param = $(param);

            that.$param_list.append($param);
            that.toggleButton(true);
        }
    };

    CRMSettingsTemplate.prototype.toggleButton = function (set_active) {
        var that = this,
            $button = that.$wrapper.find(".js-template-save-button"),
            $actions = that.$wrapper.find(".js-hidden-actions");

        if (set_active) {
            $button
                .addClass("yellow");

            $actions.show();

        } else {
            $button
                .removeClass("yellow");

            $actions.hide();
        }
    };

    CRMSettingsTemplate.prototype.initAce = function () {
        var that = this,
            $redactorW = that.$wrapper.find(".js-redactor-wrapper");

        $redactorW.each(function (index) {
            var $wrapper = $(this),
                $textarea = $wrapper.find("textarea"),
                $redactor = $("<div class=\"js-redactor\" />");

            var textarea_id = "js-textarea-" + index,
                redactor_id = "js-redactor-" + index;

            $redactor.attr("id", redactor_id).appendTo($wrapper);
            $textarea.attr("id", textarea_id);

            waEditorAceInit({
                "id": textarea_id,
                "ace_editor_container": redactor_id
            });

            if (typeof wa_editor !== "undefined") {
                $redactor.data("wa_editor", wa_editor);
            }

            $textarea.data('wa_editor', $redactor.data('wa_editor'));

            $textarea.on("editorDataUpdated", function () {
                var editor = $redactor.data("wa_editor");
                if (editor) {
                    editor.getSession().setValue($textarea.val());
                }
            });

            setTimeout(function () {
                var editor = $redactor.data("wa_editor");
                editor.on("input", function () {
                    that.toggleButton(true);
                });
            }, 100);

            $(document).on("updateEditorTextarea", function () {
                var editor = $redactor.data("wa_editor");
                if (editor) {
                    var data = editor.getValue();
                    $textarea.val(data).trigger("change");
                }
            });
        }); 
    }

    CRMSettingsTemplate.prototype.initHelp = function () {
        var that = this,
            //$help = $("#wa-editor-help"),
            $help_link = $("#wa-editor-help-link"),
            drawerLoaded = false;

        $help_link.on('click', function (event) {
            event.preventDefault();

            var href = $.crm.app_url +'?module=settings&action=help',
                data = 'app=crm&key=invoice' + '&invoice_template_id=' + that.template_id,
                drawer_html = '';

            if (drawerLoaded) {
                that.drawer.show();
                return false
            }
            const drawer_loader = '<div class="flexbox middle width-100 height-100 spinner-wrapper"><div class="spinner custom-p-16"></div></div>';
            drawer_html = `<div class=\"drawer crm-help\" id=\"\"> <div class=\"drawer-background\"><\/div> <div class=\"drawer-body\"> <a href=\"#\" class=\"drawer-close js-close-drawer\"><i class=\"fas fa-times\"><\/i><\/a> <div class=\"drawer-block\">${drawer_loader}<\/div> <\/div> <\/div> `;
            that.drawer = $.waDrawer({
                html: drawer_html,
                direction: "right",
                onClose: () => handleWaTabs(false)
            });
            $.get(href, data, function (res) {
                $(".drawer .drawer-block").html(res);
                handleWaTabs(true);
                drawerLoaded = true;
            }, 'html');

            /*   drawer.find('#wa-help-wa').remove();
                 drawer.find('#wa-help-wa-content').remove();
                // drawer.show();
                 drawer.find('ul>li.no-tab>p.bold').hide();
                 drawer.data('loaded', true);
                 */
        });

        function handleWaTabs(tabEvent) {
            that.drawerWrapper = $('.drawer');
            that.drawerContent = that.drawerWrapper.find('.drawer-content');
            if (tabEvent) {
                that.drawerWrapper.on('click', "ul.tabs li", toggleWaTabs);
                that.drawerWrapper.on('click', ".wa-help-vars-item", printVars);
                that.drawerWrapper.on('click', ".drawer-background", () => that.drawer.hide());
            }
            else {
                that.drawerWrapper.off('click');
                drawerLoaded = false;
            }
        }

        function toggleWaTabs(event) {
            event.preventDefault();
            if ($(this).hasClass('selected')) {
                return false;
            }

            let idSelected = $(this).attr('id') + '-content';
            $(this).addClass('selected').siblings().removeClass('selected');
            let newId = that.drawerContent.find(`#${idSelected}`);
            newId.siblings().hide();
            newId.show();
        }

        function printVars(event) {
            event.preventDefault();
            $body = that.$textarea;
            var editor = $body.data("wa_editor");
            if (editor) {
                editor.insert($.trim($(this).find('.js-var').text()));
                that.toggleButton(true);
                const $reset = that.$wrapper.find(".js-reset-template");
                $reset.removeClass('hidden');
                that.drawer.hide();
            } 
        }
    };

    CRMSettingsTemplate.prototype.initPreviewIframe = function () {
        var that = this;

        that.$wrapper.on("click", ".js-show-preview", function (event) {
            event.preventDefault();
            const style_version = that.$wrapper.find('.js-style-version').val();
            showDialog(style_version);
        });

        function showDialog(style_version) {
            $(document).trigger("updateEditorTextarea");

            //Disable cancel button
            that.toggleButton(false);

            $.waDialog({
                html: that.preview_dialog_template,
                onOpen: function ($wrapper) {
                    $wrapper.find("#js-preview-content").val(that.$textarea.val());
                    $wrapper.find('.js-style-version').val(style_version);
                    $wrapper.find(".js-preview-form").trigger("submit");
                }
            });

        }
    };

    return CRMSettingsTemplate;

})(jQuery);
