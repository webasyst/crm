var CRMSettingsSources = ( function($) {

    CRMSettingsSources = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;

        that.messages = options.messages || {};
        that.messages.enable = that.messages.enable || '';
        that.messages.disable = that.messages.disable || '';
        that.source_type = options.source_type;

            // INIT
        that.initClass();
    };

    CRMSettingsSources.prototype.initClass = function () {
        var that = this,
            $wrapper = that.$wrapper;

        //$.crm.renderSVG($wrapper);

        that.initTabs();

        that.initWebFormLinks();

        that.initSourcesClick();

        that.initDisableLinks();
    };

    CRMSettingsSources.prototype.initTabs = function () {
        var that = this,
            $wrapper = that.$wrapper;

        var activateTab = function (tab) {
            var $tab = $wrapper.find('.c-tab[data-type="' + tab + '"]'),
                $tab_content = $wrapper.find('.c-tab-content[data-type="' + tab + '"]');

            $wrapper.find('.c-tab.selected').removeClass('selected');
            $wrapper.find('.c-tab-content').hide();

            $tab.addClass('selected');
            $tab_content.show();

            $.crm.storage.set('crm/settings/source_tab', tab);
        };

        var tab = $.crm.storage.get('crm/settings/source_tab');
        if (!tab) {
            tab = 'email';
        }

        if (that.source_type) {
            tab = that.source_type;
        }

        activateTab(tab);

        $wrapper.on('click', '.c-tab-link', function (e) {
            e.preventDefault();
            activateTab($(this).closest('.c-tab').data('type'));
        });
    };

    CRMSettingsSources.prototype.initWebFormLinks = function () {
        var that = this,
            $wrapper = that.$wrapper;
        $wrapper.on('click', '.js-c-web-form-link', function () {
            $.crm.storage.set('crm/settings/web_form_referrer', 'settings/sources');
        });
    };

    CRMSettingsSources.prototype.initSourcesClick = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $loader =  $('<span class="icon size-16 loading " ><i class="fas fa-spinner fa-spin"></i></span>');
        $wrapper.on('click', '.c-source-details a', function () {
            $(this).parent().append($loader);
          
        });
    };
   

    CRMSettingsSources.prototype.initDisableLinks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $switchers = $wrapper.find('.js-c-disable-link'),
            xhr = null,
            url = $.crm.app_url + '?module=settingsSource&action=disable';

            $switchers.each(function () {
                var $switch_wrapper = $(this),
                    $item = $switch_wrapper.closest('.c-source'),          
                    id = $item.data('id'),
                    $loading = $item.find('.c-loading'),
                    $switch = $switch_wrapper.find("#switch-" + id);
                    //is_disabled = $item.hasClass('c-is-disabled'),

                    $switch.waSwitch({
                        ready: function (wa_switch) {
                            let $label = wa_switch.$wrapper.siblings('label');
                            wa_switch.$label = $label;
                            wa_switch.active_text = $label.data('active-text');
                            wa_switch.inactive_text = $label.data('inactive-text');
                        },
                        change: function(active, wa_switch) {
                            $loading.show();
                            wa_switch.disable(true);
                            xhr && xhr.abort();
                            xhr = $.post(url, { id: id, disabled: active ? 0 : 1 })
                            .done(function (r) {
                                if (r.status !== 'ok') {
                                    return;
                                }
                                if (active) {
                                    wa_switch.$label.text(wa_switch.inactive_text); 
                                    $item.removeClass('c-is-disabled');
                                } else {
                                    wa_switch.$label.text(wa_switch.active_text);
                                    $item.addClass('c-is-disabled');
                                }
                                wa_switch.disable(false);
                            })
                            .always(function () {
                                xhr = null;
                                $loading.hide();
                            })
                        }
                    });
    
            });
    };

    // STATIC METHODS

    CRMSettingsSources.deleteSource = function (id, options) {
        var messages = options.messages || {};
        var type = options.type || 'email';

        $.crm.confirm.show({

            title: messages['delete_confirm_title'],
            text: messages['delete_confirm_text'],
            button: messages['delete_confirm_button'],

            onConfirm: function() {
                var url = $.crm.app_url + '?module=settings&action=sourceDelete',
                    $dialog_wrapper = $('.crm-confirm-dialog'),
                    $loading = $dialog_wrapper.find('.crm-loading').show(),
                    $button = $dialog_wrapper.find('.js-confirm-dialog').attr('disabled', true);

                $.post(url, { id: id })
                    .always(function () {
                        $.crm.content.load($.crm.app_url + 'settings/message-sources/' + type + '/');
                        $loading.hide();
                        $button.attr('disabled', false);
                    });
                return false;


            }
        });
    };

    return CRMSettingsSources;

})(jQuery);
