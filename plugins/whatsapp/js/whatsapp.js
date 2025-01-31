var CRMWhatsappPluginSettings = ( function($) {

    CRMWhatsappPluginSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$name_input = that.$wrapper.find('.js-name-input');
        that.$api_endpoint_input = that.$wrapper.find('.js-api-endpoint-input');
        that.$token_input = that.$wrapper.find('.js-access-token-input');
        that.$phone_id_input = that.$wrapper.find('.js-access-phone-id-input');
        that.$credentials_input = that.$wrapper.find('.js-credentials-input');
        that.$valid_credentials_marker_input = that.$wrapper.find('.js-valid-credentials-marker-input');
        that.$copy_button = that.$wrapper.find('.js-copy');

        that.action = options["action"];
        that.locales = options["locales"];

        that.initClass();
    };

    CRMWhatsappPluginSettings.prototype.initClass = function() {
        var that = this;

        that.checkToken();
        that.initCopyUrlToClipboard();
    };

    CRMWhatsappPluginSettings.prototype.checkToken = function() {
        var that = this;
        var timeout = null;

        that.$credentials_input.on('input', function() {
            timeout && clearTimeout(timeout);
            timeout = setTimeout(function () {
                callCheck();
            }, 2000);
        });

        const callCheck = () => {
            var api_endpoint = that.$api_endpoint_input.val();
            var access_token = that.$token_input.val();
            var phone_id = that.$phone_id_input.val();
            var $ok_marker = that.$wrapper.find('.js-credentials-ok-marker');
            var $fail_marker = that.$wrapper.find('.js-credentials-fail-marker');
            var $spinner = that.$wrapper.find('.js-credentials-spinner');
            $ok_marker.hide();
            $fail_marker.hide();
            that.$valid_credentials_marker_input.val('');
            if (!access_token || !phone_id) {
                return;
            }

            $('input[type="submit"]').prop('disabled', true);
            that.$credentials_input.removeClass('state-success state-error');
            $spinner.show();
            var href = $.crm.app_url + "?plugin=whatsapp&action=checkToken",
                data = {
                    access_token: access_token,
                    phone_id: phone_id,
                    api_endpoint: api_endpoint
                };

            $.post(href, data, function(res) {
                if (res.status !== 'ok' || !!res.data.error || !res.data.display_phone_number || !res.data.verified_name) {
                    $fail_marker.fadeIn();
                    that.$credentials_input.addClass('state-error');
                    var alert_text = (!!res.errors.message) ? 
                        '<p>' + that.locales['alert_body'] + '</p><p class="hint"><em>' + res.errors.message + '</em></p>' :
                        '<p>' + that.locales['alert_fail_body'] + '</p><p class="hint"><em>' + res.errors.description + '</em></p>';
                    $.crm.alert.show({
                        title: that.locales['alert_title'],
                        text: alert_text,
                        button: that.locales['alert_close']
                    });
                } else {
                    that.$valid_credentials_marker_input.val('1');
                    $ok_marker.fadeIn();
                    that.$credentials_input.addClass('state-success');
                    $('input[type="submit"]').prop('disabled', false);
                    if (!that.$name_input.val().length) {
                        that.$name_input.val($.crm.escape(res.data.verified_name) + ': ' + $.crm.escape(res.data.display_phone_number));
                    }
                }
                $spinner.hide();
                setTimeout(() => {
                    $ok_marker.hide();
                    $fail_marker.hide();
                }, 3000);
            });
        }
    };

    CRMWhatsappPluginSettings.prototype.initCopyUrlToClipboard = function() {
        var that = this;
        that.$copy_button.on('click', function() {
            copyToClipboard($(this).parent().find('.js-copy-value').text());
            const $button = $(this).find(".js-copy-button");
            const $marker = $(this).find(".js-copy-marker");
            $button.hide();
            $marker.fadeIn();
            setTimeout(() => {
                $marker.hide();
                $button.fadeIn();
            }, 3000);
        });

        const unsecuredCopyToClipboard = (text) => { const textArea = document.createElement("textarea"); textArea.value=text; const holder= document.getElementById("js-copy-textarea-place"); holder.appendChild(textArea); textArea.focus();textArea.select(); try{document.execCommand('copy')}catch(err){console.error('Unable to copy to clipboard',err)}holder.removeChild(textArea)};

        const copyToClipboard = (content) => {
            if (window.isSecureContext && navigator.clipboard) {
                navigator.clipboard.writeText(content);
            } else {
                unsecuredCopyToClipboard(content);
            }
        };
    };

    return CRMWhatsappPluginSettings;
})(jQuery);


var CRMWhatsappPluginTemplatesDropdownItem = ( function($) {
    CRMWhatsappPluginTemplatesDropdownItem = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        that.header_html = options["header_html"];
        that.footer_html = options["footer_html"];

        that.initClass();
    };

    CRMWhatsappPluginTemplatesDropdownItem.prototype.initClass = function() {
        var that = this;
        
        that.$wrapper.on('click', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var conversation_id = $this.data('conversation-id');
            var contact_id = $this.data('contact-id');
            var source_id = $this.data('source-id');

            $drawer = $.waDrawer({
                header: that.header_html,
                content: "<div class='flexbox middle width-100 height-100 spinner-wrapper'><div class='spinner custom-p-16'></div></div>",
                footer: that.footer_html,
                direction: "left",
                width: "23rem",
                onOpen: function($drawer, drawer_instance) {
                    $drawer.find(".drawer-body").append('<a href="#" class="drawer-close js-close-drawer"><i class="fas fa-times"></i></a>');
                    var href = $.crm.app_url + "?plugin=whatsapp&module=templatesDialog&contact_id=" + contact_id + "&conversation_id=" + conversation_id + "&source_id=" + source_id;
                    $.get(href, function(html) {
                        $drawer.find(".drawer-content").html(html);
                        new CRMWhatsappPluginTemplates({
                            $wrapper: $drawer,
                            wrapper_instance: drawer_instance
                        })
                    });
                }
            });
        });
    };

    return CRMWhatsappPluginTemplatesDropdownItem;
})(jQuery);

var CRMWhatsappPluginTemplates = ( function($) {

    CRMWhatsappPluginTemplates = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.wrapper_instance = options["wrapper_instance"];

        that.$template = that.$wrapper.find(".js-template");
        that.$item = that.$template.parent();
        that.$example = that.$wrapper.find(".js-example");
        that.$example_body = that.$example.find(".js-example-body");
        that.$example_vars = that.$wrapper.find(".js-example-vars");
        that.$example_vars_wrapper = that.$wrapper.find(".js-example-vars-wrapper");
        that.$submit_button = that.$wrapper.find(".js-send-template");
        that.$form = that.$wrapper.find(".js-template-form");
        that.$template_name_input = that.$form.find(".js-template-name");
        that.$template_lang_input = that.$form.find(".js-template-lang");
        that.$template_error = that.$wrapper.find(".js-template-error");

        that.initClass();
    };

    CRMWhatsappPluginTemplates.prototype.initClass = function() {
        var that = this;
        that.$template.on("click", function() {
            var $this = $(this).parent();
            that.$item.removeClass("selected");
            $this.addClass("selected");
            that.$example_body.html($this.data("body"));
            that.$example.show();
            that.$example_vars.html("");
            that.$template_error.text("");
            that.$template_name_input.val($this.data("name"));
            that.$template_lang_input.val($this.data("lang"));
            var header_vars = $this.data("header-vars");
            header_vars.forEach((element, index) => {
                var number = index + 1;
                that.$example_vars.append(varInputHtml("header", element, number));
            });
            var body_vars = $this.data("body-vars");
            body_vars.forEach((element, index) => {
                var number = index + 1;
                that.$example_vars.append(varInputHtml("body", element, number));
            });
            if (body_vars.length > 0 || header_vars.length > 0) {
                that.$example_vars_wrapper.show();
                setTimeout(() => {
                    that.$example_vars.find(".js-example-var").first().focus();
                }, 100);
            } else {
                that.$example_vars_wrapper.hide();
            }

            handleButton();
        });

        that.$example_vars.on("input", function(e) {
            that.$template_error.text("");
            if (e.key === 'Enter') {
                that.$example_vars.find(".js-example-var").last().focus();
            } else {
                $param = that.$example_body.find(".js-whatsapp-" + e.target.dataset.type + "-param-" + e.target.dataset.id).text(e.target.value || e.target.placeholder);
                if (e.target.value) {
                    $param.removeClass("state-caution");
                } else {
                    $param.addClass("state-caution");
                }
            }
            handleButton();
        });

        that.$submit_button.on("click", function() {
            $this = $(this);
            $this.attr('disabled', true).addClass("disabled");
            var $example_var = that.$example_vars.find(".js-example-var");
            if ($example_var.filter(function( index, element ) { return element.value == ""; }).length > 0 
                || that.$template_name_input.val() == "" 
                || that.$template_lang_input.val() == ""
            ) {
                return;
            }
            var data = that.$form.serializeArray();
            $example_var.attr('disabled', true).addClass("disabled");
            $this.find(".js-icon").hide();
            $this.find(".js-load-icon").show();
            $.post($.crm.app_url + "?plugin=whatsapp&module=templatesSend", data, function(response) {
                if (response.status === "ok") {
                    that.$item.removeClass("selected");
                    that.$example.hide();
                    that.$example_vars_wrapper.hide();
                    that.$example_body.html("");
                    $(document).trigger('msg_conversation_update');
                    that.wrapper_instance.close();
                } else {
                    $example_var.removeAttr("disabled").removeClass("disabled");
                    $this.removeAttr("disabled").removeClass("disabled");
                    that.$template_error.text(response.errors.error_description);
                }
                $this.find(".js-icon").show();
                $this.find(".js-load-icon").hide();
            });
        });

        function varInputHtml(type, element, number) {
            return '<div class="field custom-mt-4"><div class="name small desktop-only" style="width: 10px;"><i class="far fa-circle"></i></div><div class="value"><input class="smaller js-example-var" style="width: 100%;" type="text" value="" name="' + type + '_params[' + number + ']" data-type="' + type + '" data-id="' + number + '" placeholder="' + element + '"></div></div>';
        }

        function handleButton() {
            var $example_var = that.$example_vars.find(".js-example-var");
            if ($example_var.filter(function( index, element ) { return element.value == ""; }).length == 0 
                || that.$template_name_input.val() == "" 
                || that.$template_lang_input.val() == ""
            ) {
                that.$submit_button.removeAttr("disabled").removeClass("disabled");
            } else {
                that.$submit_button.attr('disabled', true).addClass("disabled");
            }
        }
    }

    return CRMWhatsappPluginTemplates;
})(jQuery);