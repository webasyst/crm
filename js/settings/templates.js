var CRMSettingsTemplate = (function ($) {

    CRMSettingsTemplate = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find(".js-template-form");
        that.$paramsSection = that.$wrapper.find(".c-params-section");
        that.$param_list = that.$paramsSection.find(".c-params-list");
        that.$textarea = that.$wrapper.find(".js-content-body");

        // VARS
        that.preview_dialog_template = options.preview_dialog_template;
        that.site_app_url = options.site_app_url;
        that.template = options["template"] || {};
        that.param_html = options["param_html"];
        that.template_id = options["template_id"];
        that.locales = options["locales"];
        that.company_count = options["company_count"];

        // INIT
        that.initClass();
    };

    CRMSettingsTemplate.prototype.initClass = function () {
        var that = this;
        //
        that.initHelp();
        //
        that.initBody();
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
        //
        setTimeout( function() {
            that.initElasticFooter();
        }, 300);
        //
        that.$form.on("change", "input, select, textarea", function () {
            that.toggleButton(true);
        });
        //
        that.initResetTemplate();
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
        $reset.on('click', getBasicTemplate);

            function getBasicTemplate() {
                var data = 'template_id=' + that.template_id,
                    href = "?module=settings&action=templatesReset";
                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        $('.js-content-body').text(response.data.template);
                        that.initBody();
                    }
                }, "json").always(function () {
                });
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
                    $loading = $code_value.next('.loading');

                if ($submit.prop('disabled') || !get_attr) {
                    return;
                }

                $loading = $loading.length ? $loading : $('<i class="icon16 loading"></i>');
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
                        $loading.remove();
                    };
                    xhr = $.post($.crm.app_url + '?module=settings&action=fieldTransliterate',
                        {'name[]': name_value},
                        function (r) {
                            clear();
                            if (r.status === 'ok') {
                                if(get_attr) {
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

            if (that.company_count){
                var dialog = $.crm.alert.show({
                    text: that.locales["success_text"],
                    button: that.locales["success_button"]
                });

                setTimeout( function() {
                    var is_exist = $.contains(document, dialog.$wrapper[0]);
                    if (is_exist) {
                        dialog.close();
                    }
                }, 10000);

            } else {
                $.crm.confirm.show({
                    title: that.locales["delete_confirm_title"],
                    text: that.locales["delete_confirm_text"],
                    button: that.locales["delete_confirm_button"],
                    onConfirm: deleteTemplate
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
            is_locked = false;

        that.$form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();
            $(document).trigger("updateEditorTextarea");

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

    CRMSettingsTemplate.prototype.initBody = function () {
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

            setTimeout(function() {
                var editor = $redactor.data("wa_editor");
                editor.on("input", function() {
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
    };

    CRMSettingsTemplate.prototype.initHelp = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $document = $(document),
            $help = $("#wa-editor-help"),
            $help_link = $("#wa-editor-help-link");

        var listener = function (e) {
            // auto-off event-handler from global object
            if ($document.find($wrapper).length <= 0) {
                $document.off('click', listener);
                return;
            }
            // click outside of help area lead to close help area
            var $target = $(e.target);
            if (!$target.is($help) && $help.find($target).length <= 0) {
                $help.hide();
            }
        };

        $document.on('click', listener);

        $help_link.on('click', function () {
            if ($help.is(":visible")) {
                $help.hide();
                return false;
            }

            var url = that.site_app_url + '?module=pages&action=help',
                data = 'app=crm&key=invoice' + '&invoice_template_id=' + that.template_id;
            $help.load(url, data, function () {
                $help.find('#wa-help-wa').remove();
                $help.find('#wa-help-wa-content').remove();
                $help.show();
                $help.find('ul>li.no-tab>p.bold').hide();
                $help.data('loaded', true);
            });
            return false;
        });

        $help.on('click', "div.fields a.inline-link", function (e) {
            e.preventDefault();

            var $el = $(this).find('i'),
                $body = that.$textarea;

            var editor = $body.data("wa_editor");
            if (editor) {
                editor.insert($.trim($el.text()));
            }
        });

    };

    CRMSettingsTemplate.prototype.initPreviewIframe = function () {
        var that = this;

        that.$wrapper.on("click", ".js-show-preview", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            $(document).trigger("updateEditorTextarea");

            //Disable cancel button
            that.toggleButton(false);

            new CRMDialog({
                html: that.preview_dialog_template,
                onOpen: function ($wrapper) {
                    $wrapper.find("#js-preview-content").val( that.$textarea.val() );
                    $wrapper.find(".js-preview-form").trigger("submit");
                }
            });
        }
    };

    CRMSettingsTemplate.prototype.initElasticFooter = function() {
        var that = this;

        // DOM
        var $window = $(window),
            $wrapper = that.$wrapper,
            $header = $wrapper.find(".js-footer-block"),
            $dummy = false,
            is_set = false;

        var active_class = "is-fixed-to-bottom";

        var header_o, header_w, header_h;

        clear();

        $window.on("scroll", useWatcher);
        $window.on("resize", onResize);

        onScroll();

        function useWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onScroll();
            } else {
                $window.off("scroll", useWatcher);
            }
        }

        function onScroll() {
            var scroll_top = $window.scrollTop(),
                use_scroll = header_o.top + header_h > scroll_top + $window.height();

            if (use_scroll) {
                if (!is_set) {
                    is_set = true;
                    $dummy = $("<div />");

                    $dummy.height(header_h).insertAfter($header);

                    $header
                        .css("left", header_o.left)
                        .width(header_w)
                        .addClass(active_class);
                }

            } else {
                clear();
            }
        }

        function onResize() {
            clear();
            $window.trigger("scroll");
        }

        function clear() {
            if ($dummy && $dummy.length) {
                $dummy.remove();
            }
            $dummy = false;

            $header
                .removeAttr("style")
                .removeClass(active_class);

            header_o = $header.offset();
            header_w = $header.outerWidth();
            header_h = $header.outerHeight();

            is_set = false;
        }
    };

    return CRMSettingsTemplate;

})(jQuery);
