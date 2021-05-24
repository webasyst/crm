var CRMSettingsSourceResponsibleBlock = ( function($) {

    CRMSettingsSourceResponsibleBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$group = that.$wrapper.find('.crm-group-id');
        that.$user = that.$wrapper.find('.crm-user');
        that.$user_id = that.$wrapper.find('.crm-responsible-contact_id');
        that.$user_info = that.$wrapper.find('.crm-user-info-block');
        that.$user_delete_link = that.$user_info.find('.crm-delete-link');

        // VARS
        that.url = $.crm.app_url + '?module=settingsSourceBlock&action=responsible';
        that.ns = '.' + $.trim(that.$wrapper.attr('id'));
        that.source = options.source || {};
        that.funnel_id = that.source.funnel_id;
        that.namespace = options.namespace || '';
        that.class_id = options.class_id || '';

        // INIT
        that.initClass();
        that.$wrapper.data('block_object', that);
    };

    CRMSettingsSourceResponsibleBlock.prototype.initClass = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $group = that.$group,
            $user = that.$user,
            $user_id = that.$user_id,
            $user_delete_link = that.$user_delete_link,
            $user_info = that.$user_info,
            user_id_val = $user_id.val();

        var showUserInput = function () {
            $user.show().attr('disabled', false).attr('required', true);
        };

        var hideUserInput = function () {
            $user.hide().attr('disabled', true).attr('required', false);
        };

        $group.on('change' + that.ns, function () {
            var $el = $(this),
                $group_hint = $el.parents('.crm-fields').find('.js-group-hint');

            $user_id.val('');
            if ($el.val() === 'personally') {
                showUserInput();
            } else {
                hideUserInput();
                $user_info.hide();
            }
            if ($el.val() === 'personally' || $el.val() === '') {
                $group_hint.hide();
            } else {
                $group_hint.show();
            }
            if ($el.val() > 0) {
                $user_id.val(-$el.val());
            }

            if (user_id_val != $user_id.val()) {
                $wrapper.trigger('changeResponsibleUser', [user_id_val, $user_id.val()]);
                user_id_val = $user_id.val();
            }

        });

        $user_delete_link.on('click' + that.ns, function (e) {
            e.preventDefault();
            $user_id.val('');
            showUserInput();
            $user_info.hide();
        });

        $user.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user&funnel_id=" + that.funnel_id,
            minLength: 0,
            html: true,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                $user.val("");
                if (ui.item.rights <= 0) {
                    return false;
                }
                hideUserInput();
                $user_info.show();
                $user_info.find('.crm-user-icon').css('background-image', 'url(' + ui.item.photo_url + ')');
                $user_info.find('.crm-user-name').text(ui.item.name).attr("title", ui.item.name);
                $user_id.val(ui.item.id);

                if (user_id_val != $user_id.val()) {
                    $wrapper.trigger('changeResponsibleUser', [user_id_val, $user_id.val()]);
                    user_id_val = $user_id.val();
                }

                return false;
            }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            var $item = $("<li />");

            $item.addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );

            if (that.funnel_id > 0 && !item.rights) {
                $item.addClass("is-locked");
                $item.on("click" + that.ns, function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                });
            }

            return $item;
        };

        $user.on("focus" + that.ns, function(){
            $user.data("uiAutocomplete").search( $user.val() );
        });
    };

    CRMSettingsSourceResponsibleBlock.prototype.reload = function (data, afterReload) {
        var that = this,
            $user_info = this.$user_info,
            $loading = $('<i class="icon16 loading"></i>'),
            url = that.url;

        if ($user_info.is(':visible')) {
            that.$user_delete_link.hide();
            $loading.css({
                marginTop: '-16px',
                marginRight: '-16px',
                float: 'right'
            });
        }

        $user_info.after($loading);

        data = data || {};
        data.namespace = that.namespace;

        if (typeof data.funnel_id === 'undefined') {
            data.funnel_id = that.funnel_id;
        }

        data.responsible_contact_id = that.$user_id.val();
        data.group_id = that.$group.val();

        $loading.show();

        $.get(url, data, function (html) {
            var $tmp = $('<div>').html(html),
                $wrapper = $tmp.find('.' + that.class_id);
            that.$wrapper.replaceWith($wrapper);

            (function (block_object) {
                afterReload && afterReload(block_object);
                block_object = null;
            })($wrapper.data('block_object'));

            $tmp.remove();
            that.destroy();
        });
    };

    CRMSettingsSourceResponsibleBlock.prototype.destroy = function () {
        var that = this;
        that.$user_delete_link.off(that.ns);
        that.$group.off(that.ns);
        that.$wrapper.removeData('block_object');
        if (that.$user.data('ui-autocomplete')) {
            that.$user.autocomplete('destroy');
            that.$user.removeData('ui-autocomplete');
        }
        $.each(that, function (key) {
            delete that[key];
        });
    };

    return CRMSettingsSourceResponsibleBlock;

})(jQuery);
