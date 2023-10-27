var CRMCallInitContactDialog = ( function($) {

    CRMCallInitContactDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$footer = that.$wrapper.find('.js-dialog-footer');
        that.$form = that.$wrapper.find('form');
        that.$call_button = that.$form.find('.js-init-call');
        that.$content = that.$form.find('.js-content');
        that.$numbers_list = that.$form.find('.js-numbers-list');
        that.$plugin_id = that.$form.find('.js-plugin-id');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.call_id = 0;
        that.call_ready = options['call_ready'];
        that.locales = options['locales'];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCallInitContactDialog.prototype.initClass = function() {
        var that = this;

        //
        if (that.call_ready === 'ready') {
            that.initCall();
        } else {
            that.selectExtensionNumber();
            that.submit();
        }
    };

    /**
     * Just selector
     */
    CRMCallInitContactDialog.prototype.selectExtensionNumber = function() {
        var that = this;

        that.$form.on('click', '.js-number-item', function () {
            var $item = $(this),
                plugin_id = $item.data('plugin-id');

            that.$plugin_id.val($.crm.escape(plugin_id));
            that.$call_button.prop('disabled', false);
        });
    };

    /**
     * Small validation of entered data.
     */
    CRMCallInitContactDialog.prototype.submit = function() {
        var that = this;

        that.$form.on('submit', function (e) {
            e.preventDefault();

            var plugin_id = that.$plugin_id.val();

            if (!plugin_id) {
                that.$numbers_list.addClass('shake animated');
                setTimeout(function(){
                    that.$numbers_list.removeClass('shake animated');
                },500);
                return false;
            }

            that.initCall();
        });
    };

    /**
     * The method sends a request to the plugin to start a new outgoing call.
     * Then a new call is made in CRM.
     */
    CRMCallInitContactDialog.prototype.initCall = function() {
        var that = this;

        var $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>'),
            href = $.crm.app_url + "?module=call&action=initNew",
            data = that.$form.serializeArray();

        that.$call_button.prop('disabled', true);
        that.$footer.append($loading);

        $.post(href, data)
            .done(function(res){
                if (res.status === "ok") {
                    that.call_id = res.data.call_id;
                    that.callChecker();
                } else {
                    console.error(res);
                }
            })
            .fail(function () {
                that.dialog.close();
            })
            .always(function () {
                that.$call_button.prop('disabled', false);
                $loading.remove();
            });
    };

    /**
     * The method updates the call status every 2 seconds.
     */
    CRMCallInitContactDialog.prototype.callChecker = function() {
        var that = this,
            call_id = that.call_id,
            $close_button = '<input class="button js-close-dialog" type="submit" value="'+ that.locales['Close'] +'">',
            $status_pending = '<div class="c-call-pending">'+ that.locales['call_pending'] +'<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i></div>',
            $status_connected = '<div style="color: #00ff00;">'+ that.locales['call_connected'] +'</div>',
            $status_finished = '<div style="color: #5159c3;">'+ that.locales['call_finished'] +'</div>';

        that.$content.html($status_pending);
        that.$footer.html($close_button);

        callCheck();
        var intervalID = setInterval(callCheck, 2000);

        function callCheck() {
            var href = $.crm.app_url + "?module=call&action=checkStatus",
                data = {id: call_id};

            $.post(href, data, function(res){
                try {
                    if (res.errors) {
                        clearInterval(intervalID);
                        that.$content.html($status_finished);
                        setTimeout(function () {
                            that.dialog.close();
                            $.crm.content.reload();
                        }, 3000);
                        console.log(res.errors.message);
                    }

                    if (res.data.status_id === "PENDING") {
                        that.$content.html($status_pending);
                    } else if (res.data.status_id === "CONNECTED") {
                        that.$content.html($status_connected);
                    } else {
                        that.$content.html($status_finished);
                        clearInterval(intervalID);
                        setTimeout(function () {
                            that.dialog.close();
                            $.crm.content.reload();
                        }, 3000);
                    }
                } catch (e) {
                    clearInterval(intervalID);
                    console.log('Error while updating call status');
                    console.log(e);
                }
            });
        }
    };

    return CRMCallInitContactDialog;

})(jQuery);
