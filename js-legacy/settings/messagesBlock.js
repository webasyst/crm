var CRMSettingsMessagesBlock = ( function($) {

    CRMSettingsMessagesBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.namespace = options.namespace || '';
        that.type = options.type || 'form';

        // INIT
        that.initClass();
    };

    CRMSettingsMessagesBlock.prototype.initClass = function() {
        var that = this,
            $wrapper = that.$wrapper,
            namespace = that.namespace,
            url = $.crm.app_url + '?module=settings&action=emailTemplateEditor';

        that.renderCheckboxLabel();

        $wrapper.on('click', '.js-crm-add-message-checkbox', function () {
            var $el = $(this),
                $template = $wrapper.find('.crm-template'),
                $block = $template.clone();
            $block.removeClass('crm-template');
            $wrapper.find('.crm-one-message:last').after($block.show());

            var last_i = $wrapper.find('.crm-editor-wrapper[data-i]').last().data('i');
            if (last_i) {
                last_i = parseInt(last_i+'', 10);
            } else if (last_i !== 0) {
                last_i = -1;
            }
            var i = last_i + 1;
            $block.find('.crm-editor-wrapper').data('i', ''+i).attr('data-i', ''+i);

            var post_data = {
                input_name: namespace + '['+i+'][tmpl]',
                to_name: namespace + '['+i+'][to]',
                sourcefrom_name: namespace + '['+i+'][sourcefrom]',
                add_attachments_name: namespace + '['+i+'][add_attachments]',
                type: that.type
            };
            $.post(url, post_data, function (html) {
                $block.find('.crm-editor-wrapper').html(html);
                $wrapper.trigger('loadEditor', [i, $block]);
            });
            that.renderCheckboxLabel();
            $el.prop('checked', false);
        });

        $wrapper.on('click', '.js-crm-remove-message-checkbox', function () {
            var $el = $(this),
                $block = $el.closest('.crm-one-message');
            $block.remove();
            that.renderCheckboxLabel();
        });
    };

    CRMSettingsMessagesBlock.prototype.renderCheckboxLabel = function () {
        var that = this,
            $wrapper = that.$wrapper;
        if ($wrapper.find('.crm-one-message:not(.crm-template)').length > 0) {
            $wrapper.find('.js-crm-when-no-messages').hide();
            $wrapper.find('.js-crm-when-messages').show();
        } else {
            $wrapper.find('.js-crm-when-no-messages').show();
            $wrapper.find('.js-crm-when-messages').hide();
        }
    };

    return CRMSettingsMessagesBlock;

})(jQuery);
