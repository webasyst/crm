var CRMDealsExport = (function ($) {

    CRMDealsExport = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$cancel = that.$wrapper.find('.crm-js-cancel');
        that.$download_file_form = that.$wrapper.find('.crm-download-file-form');

        // VARS
        that.ids = options.ids || [];
        that.url = $.crm.app_url + '?module=dealExport&action=process';
        that.dialog = that.$wrapper.data('dialog');

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealsExport.prototype.initClass = function () {
        var that = this;
        //
        that.initStartExport();
    };

    CRMDealsExport.prototype.initStartExport = function () {
        var that = this,
            $button = that.$button,
            $cancel = that.$cancel,
            process = null;

        $button.click(function (e) {
            e.preventDefault();
            $button.attr('disabled', true);
            process = that.process();
        });

        $cancel.click(function (e) {
            e.preventDefault();
            process && process.cancel();
        });
    };

    CRMDealsExport.prototype.process = function () {
        var that = this,
            url = that.url,
            $wrapper = that.$wrapper,
            processId  = null,
            timer = null,
            requests = 0,
            post_data = {};

        post_data.separator = $wrapper.find('[name=separator]').val();
        post_data.encoding = $wrapper.find('[name=encoding]').val();
        post_data.export_fields_name = $wrapper.find('[name=export_fields_name]').is(':checked') ? 1 : 0;
        post_data.not_export_empty_columns = $wrapper.find('[name=not_export_empty_columns]').is(':checked') ? 1 : 0;
        post_data.ids = [].concat(that.ids);    // clone of ids

        // Sends messenger and delays next messenger in 3 seconds
        var process = function () {
            timer && clearTimeout(timer);
            timer = setTimeout(process, 3200);
            if (!processId || requests >= 2) {
                return;
            }
            post_data.processId = processId;
            $.post(url, post_data,
                function (response) {
                    requests--;

                    if (!processId || !response.ready) {
                        return;
                    }

                    // Stop sending messengers
                    var pid = processId;
                    if (!pid) {
                        return; // race condition is still possible, but not really dangerous
                    }

                    timer && clearTimeout(timer);
                    timer = null;
                    processId = null;

                    that.$download_file_form
                        .attr('action', that.url + '&processId=' + pid)
                        .appendTo('body')
                        .submit(function () {
                            setTimeout(function () {
                                // back form to its place
                                that.$download_file_form.appendTo(that.$wrapper.find('.crm-dialog-content'));
                                that.dialog.close();
                            }, 200);
                        })
                        .submit();

                },
                'json'
            );
            requests++;

        };

        that.$wrapper.find('.crm-start-block').hide();
        that.$wrapper.find('.crm-process-block').show();

        $.post(url, post_data, function (data) {
            if (!data.processId) {
                alert('Error processing request.');
            }
            processId = data.processId;
            process();
        }, 'json');

        return {
            cancel: function () {
                timer && clearTimeout(timer);
                $.post(url, { processId: processId, ready: 1 });
            }
        }
    };

    return CRMDealsExport;

})(jQuery);
