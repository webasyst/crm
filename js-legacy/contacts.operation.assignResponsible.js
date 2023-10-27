/** Controller for Responsible user
 *  initialized in /contact/ContactOperationAssignResponsible.html */
var CRMContactsOperationAssignResponsible = ( function($) {

    CRMContactsOperationAssignResponsible = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$responsibleList = that.$wrapper.find(".js-responsible-list");
        that.$submitButton = that.$wrapper.find(".js-save");
        that.$close_dialog = options['Close_dialog'];
        that.$errorh1 = options['Error_h1'];

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.responsible_template_html = options["responsible_template_html"];

        // INIT
        that.initClass();
    };

    CRMContactsOperationAssignResponsible.prototype.initClass = function() {
        var that = this;

        // Data is changed
        that.$wrapper.on("change", "input", function() {
            that.toggleButton(true);
        });
        //
        that.initSubmit();
        //
        that.initAutocomplete();
    };

    CRMContactsOperationAssignResponsible.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=contactOperation&action=assignResponsibleProcess",
                    data = getData();

                if (!data) {
                    is_locked = false;
                    return false;
                }

                var $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin: 0;"></i>');
                $loading.appendTo(that.$submitButton.parent());

                that.$submitButton.attr("disabled", true);
                var responsible_id = $("#responsible_id").val();
                if (responsible_id) {
                    var redirect_url = $.crm.app_url + 'contact/responsible/' + responsible_id + '/';
                } else {
                    var redirect_url = $.crm.app_url + 'contact/';
                }

                $.post(href, data, function(response) {
                    if (response.data.result === "ok") {
                        $.crm.content.reload().then( function() {
                            that.dialog.close();
                            $.crm.content.load(redirect_url);
                        });
                    } else if (response.data.result === "no_vault_access") {
                        $('.crm-dialog-header h1').html(that.$errorh1);
                        $('.crm-dialog-content p, .js-responsible-list').html('');
                        $('.crm-dialog-footer').html('<a class="button js-close-dialog" href="javascript:void(0);">'+that.$close_dialog+'</a>')
                        $('.no-access-error').html(response.data.message);
                        $.each(response.data.users, function(i, user){
                            $('.no-access-users').append('<a href="'+ user.id +'" style="display: block; margin-top: 10px;"><i class="icon16 userpic20" style="background-image: url('+ user.photo +');"></i><span>'+ user.name +'</span></a>');
                        });
                    }
                }, "json").always( function() {
                    that.$submitButton.attr("disabled", false);
                    that.toggleButton(false);
                    $loading.remove();
                    is_locked = false;
                });
            }
        }

        function getData() {
            var responsible_id = $("#responsible_id").val(),
                contact_ids = $("#contact_ids").val(),
                result = {
                    contact_ids: contact_ids,
                    responsible_id: responsible_id
                };
            return result;
        }

        function showError(error) {
            var $error = $("<div class=\"errormsg\" />").text(error);
            that.$errorsPlace.append($error);
        }
    };

    CRMContactsOperationAssignResponsible.prototype.initAutocomplete = function() {
        var that = this;

        var $field = that.$responsibleList.find(".js-autocomplete");

        $field.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var $responsible_user = findResponsible(ui.item.id) || addResponsible(ui.item);
                markOwner($responsible_user);
                that.toggleButton(true);
                $field.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $field.on("focus", function(){
            $field.data("uiAutocomplete").search( $field.val() );
        });

        // Remove responsible user
        that.$responsibleList.on("click", ".js-delete-responsible", function() {
            $(this).closest(".c-responsible").remove();
            $("#responsible_id").val("");
            $(".js-input").css("display",""); // show input
            $(".js-autocomplete").focus();
            that.$submitButton.attr("disabled", true);
            that.toggleButton(true); // chenge class
        });

        function findResponsible(user_id) {
            var $responsible = that.$responsibleList.find(".c-responsible[data-id=\"" + user_id + "\"]");
            if ($responsible.length) {
                return $responsible;
            }
        }

        // Добавляем ответственного пользователя
        function addResponsible(user) {
            var template = that.responsible_template_html;

            template = template
                .replace(/%id%/, user.id)
                .replace("%name%", escapeHtml(user.name))
                .replace("%photo_url%", user.photo_url);

            $("#responsible_id").val(user.id);

            var $template = $(template);

            that.$responsibleList.append($template);

            $(".js-input").css("display","none");
            that.$submitButton.attr("disabled", false);
            that.toggleButton(true);

            return $template;
        }

        function escapeHtml(string) {
            return $('<div></div>').text(string).html();
        }

        function markOwner($user) {
            var active_class = "highlighted";

            $user.addClass(active_class);
            setTimeout(function() {
                $user.removeClass(active_class);
            }, 2000);
        }
    };

    CRMContactsOperationAssignResponsible.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$submitButton;

        if (is_changed) {
            $button.removeClass("green").addClass("yellow");
        } else {
            $button.removeClass("yellow").addClass("green");
        }
    };

    return CRMContactsOperationAssignResponsible;

})(jQuery);