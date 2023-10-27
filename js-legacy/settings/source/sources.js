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

        $.crm.renderSVG($wrapper);

        that.initTabs();

        that.initWebFormLinks();

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

    CRMSettingsSources.prototype.initDisableLinks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            xhr = null,
            url = $.crm.app_url + '?module=settingsSource&action=disable';

        $wrapper.on('click', '.js-c-disable-link', function (e) {
            e.preventDefault();

            var $link = $(this),
                $item = $link.closest('.c-source'),
                id = $item.data('id'),
                is_disabled = $item.hasClass('c-is-disabled'),
                $loading = $item.find('.c-loading');

            xhr && xhr.abort();
            $loading.show();

            xhr = $.post(url, { id: id, disabled: is_disabled ? 0 : 1 })
                .done(function (r) {
                    if (r.status !== 'ok') {
                        return;
                    }
                    if (r.data.disabled) {
                        $link.text(that.messages.enable);
                        $item.addClass('c-is-disabled');
                    } else {
                        $link.text(that.messages.disable);
                        $item.removeClass('c-is-disabled');
                    }
                })
                .always(function () {
                    xhr = null;
                    $loading.hide();
                })
        });
    };

    // STATIC METHODS

    CRMSettingsSources.deleteSource = function (id, options) {
        var messages = options.messages || {};

        $.crm.confirm.show({

            title: messages['delete_confirm_title'],
            text: messages['delete_confirm_text'],
            button: messages['delete_confirm_button'],

            onConfirm: function () {

                var url = $.crm.app_url + '?module=settings&action=sourceDelete',
                    $loading = this.$wrapper.find('.crm-loading').show(),
                    $button = this.$wrapper.find('.js-confirm-dialog').attr('disabled', true);

                $.post(url, { id: id })
                    .always(function () {
                        $.crm.content.load($.crm.app_url + 'settings/sources/');
                        $loading.hide();
                        $button.attr('disabled', false);
                    });

                return false;


            }
        });
    };

    return CRMSettingsSources;

})(jQuery);
