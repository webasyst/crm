var CRMContactsOperationExport = (function ($) {

    CRMContactsOperationExport = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$cancel = that.$wrapper.find('.crm-js-cancel');
        that.$download_file_form = that.$wrapper.find('.crm-download-file-form');

        // VARS
        that.context = options.context || {};
        that.url = $.crm.app_url + '?module=contactOperationExport&action=process';
        that.dialog = that.$wrapper.data('dialog');

        that.currentProcess = null;

        // INIT
        that.initClass();
    };

    CRMContactsOperationExport.prototype.initClass = function () {
        var that = this;
        //
        that.initStartExport();
    };

    CRMContactsOperationExport.prototype.initStartExport = function () {
        var that = this,
            $button = that.$button,
            $cancel = that.$cancel;

        $button.click(function (e) {
            e.preventDefault();
            $button.attr('disabled', true);
            that.currentProcess = that.process();
        });

        $cancel.click(function (e) {
            e.preventDefault();
            that.currentProcess && that.currentProcess.cancel();
        });

        that.dialog.onCancel = function () {
            that.currentProcess && that.currentProcess.cancel();
        };
    };

    CRMContactsOperationExport.prototype.cancel = function() {
        var that = this;
        that.currentProcess && that.currentProcess.cancel();
    };

    CRMContactsOperationExport.prototype.process = function () {
        var that = this,
            url = that.url,
            $wrapper = that.$wrapper,
            $progress_bar = $wrapper.find('.js-export-contacts-progressbar'),
            $progress_bar_val = $progress_bar.find('.js-export-contacts-progressbar-progress'),
            $progress_txt = $wrapper.find('.js-current-progress-txt'),
            processId  = null,
            timer = null,
            requests = 0,
            post_data = $.extend({}, that.context, true);

        post_data.separator = $wrapper.find('[name=separator]').val();
        post_data.encoding = $wrapper.find('[name=encoding]').val();
        post_data.export_fields_name = $wrapper.find('[name=export_fields_name]').is(':checked') ? 1 : 0;
        post_data.not_export_empty_columns = $wrapper.find('[name=not_export_empty_columns]').is(':checked') ? 1 : 0;

        var updateProgressBar = function(progress_val, animate) {

            if (progress_val <= 0) {
                progress_val = 0;
            } else if (progress_val >= 100) {
                progress_val = 100;
            }

            if (animate) {
                var duration = 250;
                $progress_bar_val.stop();
                $progress_bar_val.clearQueue();
                $progress_bar_val.animate({ width: ""+Math.round(progress_val) + '%' }, {
                    duration: duration,
                    queue: true,
                });
            } else {
                $progress_bar_val.css({ width: progress_val });
            }

            if (progress_val < 100) {
                $progress_txt.text((Math.round((progress_val * 100) / 100) + '%'));   // 2 precision
            } else {
                $progress_txt.html('<span>100% <i class="icon16 yes"></i></span>');
            }
        }

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
                        if (response.progress) {
                            updateProgressBar(response.progress, true);
                        }
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
                        .attr('action', $.crm.app_url + '?module=contactOperationExport&action=process&processId=' + pid)
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

        updateProgressBar(0, false);

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

    return CRMContactsOperationExport;

})(jQuery);
