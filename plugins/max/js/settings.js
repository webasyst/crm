/**
 * JavaScript for MAX plugin settings in CRM.
 */
var CRMMaxPluginSettings = ( function($) {

    function CRMMaxPluginSettings(options) {
        this.$wrapper = options.$wrapper;
        this.action = options.action;
        this.init();
    }

    CRMMaxPluginSettings.prototype = {

        init: function() {
            this.bindEvents();
            this.updateCommandsVisibility();
        },

        bindEvents: function() {
            var self = this;

            // Token input - validate on blur
            this.$wrapper.on('blur', '.js-token-input', function() {
                self.validateToken($(this).val());
            });

            // Add command button
            this.$wrapper.on('click', '.js-add-command', function() {
                self.addCommand();
            });

            // Remove command button
            this.$wrapper.on('click', '.js-command-remove', function() {
                self.removeCommand($(this).closest('.js-command'));
            });

            // Command input - update hidden field
            this.$wrapper.on('input', '.js-command-input', function() {
                self.updateCommandHidden($(this));
            });

            // Command checkbox
            this.$wrapper.on('change', '.js-command-checkbox', function() {
                self.updateCommandCheckbox($(this));
            });
        },

        validateToken: function(token) {
            const self = this;
            const $input = this.$wrapper.find('.js-token-input');
            if (!token) {
                $input.removeClass('state-success');
                $input.removeClass('state-error');
                $input.closest('.value').find('.crm-errors-block').remove();
                return;
            }

            $input.addClass('loading');

            $.ajax({
                url: '?plugin=max&action=checkToken',
                type: 'POST',
                data: {
                    token: token
                },
                dataType: 'json',
                success: function(response) {
                    $input.removeClass('loading');
                    $input.closest('.value').find('.crm-errors-block').remove();
                    if (response && response.status == "ok" && response.data) {
                        self.$wrapper.find('.js-bot-id-input').val(response.data.user_id || '');
                        self.$wrapper.find('.js-username-input').val(response.data.username || '');
                        self.$wrapper.find('.js-firstname-input').val(response.data.first_name || '');

                        const $name_input = self.$wrapper.find('.js-name-input');
                        if (!$name_input.val()) {
                            $name_input.val(response.data.first_name || response.data.username || '');
                        }

                        $input.removeClass('state-error');
                        $input.addClass('state-success');
                    } else if (response && response.status == "fail" && response.errors) {
                        $input.removeClass('state-success');
                        $input.addClass('state-error');
                        if (response.data && response.data.message) {
                            $input.closest('.value').append('<div class="crm-errors-block"><span class="errormsg error">' + response.data.message + '</span></div>');
                        }
                    }
                },
                error: function() {
                    $input.removeClass('loading');
                    $input.removeClass('state-success');
                    $input.addClass('state-error');
                }
            });
        },

        addCommand: function() {
            var template = this.$wrapper.find('.js-command-template').html();
            this.$wrapper.find('.js-commands').append(template);
            this.updateCommandsVisibility();
        },

        removeCommand: function($command) {
            $command.remove();
            this.updateCommandsVisibility();
        },

        updateCommandHidden: function($input) {
            var $hidden = $input.closest('.js-command').find('.js-command-hidden');
            $hidden.val($input.val());
        },

        updateCommandCheckbox: function($checkbox) {
            var $hidden = $checkbox.closest('.js-command').find('.js-command-hidden');
            $hidden.val($checkbox.is(':checked') ? '1' : '0');
        },

        updateCommandsVisibility: function() {
            var $commands = this.$wrapper.find('.js-command');
            this.$wrapper.find('.js-add-command').toggle($commands.length < 10);
        }
    };

    return CRMMaxPluginSettings;

})(jQuery);
