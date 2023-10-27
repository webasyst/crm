var CRMContactsOperationExport = (function ($) {

    CRMContactsOperationExport = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-save');
        that.$download_button = that.$wrapper.find('.js-download');
        that.$cancel = that.$wrapper.find('.crm-js-cancel');
        that.$download_file_form = that.$wrapper.find('.crm-download-file-form');

        // VARS
        that.context = options.context || {};
        that.url = $.crm.app_url + '?module=contactOperationExport&action=process';
        that.dialog = that.$wrapper.data('dialog');
        that.$dialog_parent = window.parent.$('.dialog');
        that.$dialog_parent_close = that.$dialog_parent.find('.dialog-close');
        that.pid = '';

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

       /* that.$download_button.click(function (e) {
            e.preventDefault();
    
        });*/

        that.$wrapper.on('click', '.crm-js-cancel', function (e) {
            e.preventDefault();
            that.currentProcess && that.currentProcess.cancel();
            that.$dialog_parent_close[0].click();
        });

        /*$cancel.click(function (e) {
            e.preventDefault();
            that.currentProcess && that.currentProcess.cancel();
            that.$dialog_parent_close[0].click();
        });*/

        if (that.dialog) {
            that.dialog.onCancel = function () {
                that.currentProcess && that.currentProcess.cancel();
                that.dialog.close();
            };
        }
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
            $progress_txt = $wrapper.find('.progressbar-text'),
            processId  = null,
            timer = null,
            complete = false,
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
                $progress_bar_val.css({ width: ""+Math.round(progress_val) + '%'});
            }

            if (progress_val < 100) {
                $progress_txt.text((Math.round((progress_val * 100) / 100) + '%'));   // 2 precision
                
            } else {
                that.$wrapper.find('.crm-process-block .loader').html('<i class="icon fas fa-check yes"></i>');
                $progress_txt.text('100%');
                complete = true;
            }
        }

        // Sends messenger and delays next messenger in 3 seconds
        var process = function () {
            that.$wrapper.find('.dialog-footer--visible').addClass('hidden');
            
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
                            updateProgressBar(response.progress, !complete);
                        }
                            return;
                    }

                    // Stop sending messengers
                    that.pid = processId;
                    if (!that.pid) {
                        return; // race condition is still possible, but not really dangerous
                    }
                    timer && clearTimeout(timer);
                    timer = null;
                    processId = null;
                    var url_action = $.crm.app_url + '?module=contactOperationExport&action=process&processId=' + that.pid;

                    var _csrf = that.$download_file_form.find('input[name="_csrf"]').val();
                    var file_val = that.$download_file_form.find('input[name="file"]').val();
                    $.post(url_action, {'processId': that.pid, '_csrf': _csrf, 'file': file_val},
                        function (response) {
                            var response_obj = JSON.parse(response);
                            var url_download = $.crm.app_url + '?module=contactOperation&action=download&file=' + response_obj.file;
                            that.$download_button.attr('href', url_download);
                        })

                    that.$wrapper.find('.dialog-footer--hidden').removeClass('hidden');
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
