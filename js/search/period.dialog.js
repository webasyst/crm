(function ($) {

    function isInIframe() {
        try {
            return window.self !== window.top;
        } catch (e) {
            return true;
        }
    }

    function getIframeViewportPosition($block) {
        var $window = $(window),
            window_w = $window.width(),
            window_h = $window.height(),
            wrapper_w = $block.outerWidth(),
            wrapper_h = $block.outerHeight(),
            pad = 20,
            top = Math.floor((window_h - wrapper_h) / 2),
            left = Math.floor((window_w - wrapper_w) / 2);

        if (left < pad) {
            left = pad;
        }
        if (top < pad) {
            top = pad;
        }
        if (left + wrapper_w > window_w - pad) {
            left = Math.max(pad, window_w - wrapper_w - pad);
        }
        if (top + wrapper_h > window_h - pad) {
            top = Math.max(pad, window_h - wrapper_h - pad);
        }

        return { top: top, left: left };
    }

    $.fn.periodDialog = function (key, value) {

        if (!this.length) {
            return this;
        }

        if (key === 'getStartDate') {
            return getStartDate();
        }
        if (key === 'getEndDate') {
            return getEndDate();
        }
        if (key === 'formatDate') {
            return formatDate.apply(this, Array.prototype.slice.call(arguments, 1));
        }

        var options = {};
        if (typeof key === 'object') {
            this.data('periodDialogOptions', $.extend({
                start_datetime: null,
                end_datetime: null
            }, key));
            options = this.data('periodDialogOptions');
        }

        function init() {
            var $tmpl = $('.crm-dialog-search-period-wrapper.is-template'),
                $html = $tmpl.clone(),
                suffix = (Math.random() + '').slice(2),
                id = 'crm-dialog-search-period-wrapper-' + suffix;
            $('.crm-dialog-search-period-wrapper:not(.is-template)').remove();
            $html.removeClass('is-template');
            $html.attr('id', id);
            if (isInIframe()) {
                $html.addClass('is-iframe-context');
            }
            $html.show();

            var in_iframe = isInIframe(),
                resize_namespace = 'resize.periodDialog-' + suffix,
                dialog_options = {
                    html: $html,
                    onOpen: function ($dialog, dialog) {
                        $dialog.find('.datepicker').datepicker();
                        if (options.start_datetime) {
                            $dialog.find('.datepicker.start').datepicker('setDate', $.datepicker.parseDate('yy-mm-dd', options.start_datetime));
                        }
                        if (options.end_datetime) {
                            $dialog.find('.datepicker.end').datepicker('setDate', $.datepicker.parseDate('yy-mm-dd', options.end_datetime));
                        }

                        var $apply = $dialog.find('.js-apply-action'),
                            $cancel = $dialog.find('.js-cancel-action');

                        $apply.click(function (e) {
                            e.preventDefault();
                            var start = $dialog.find('.start').datepicker('getDate'),
                                end = $dialog.find('.end').datepicker('getDate');
                            $dialog.trigger('select', [start, end]);
                            dialog.close();
                        });

                        $cancel.click(function (e) {
                            e.preventDefault();
                            $dialog.trigger('cancel');
                            dialog.close();
                        });

                        if (in_iframe) {
                            $(window).on(resize_namespace, function () {
                                dialog.resize();
                            });
                        }

                        dialog.resize();
                    },
                    onClose: function () {
                        if (in_iframe) {
                            $(window).off(resize_namespace);
                        }
                    }
                };

            if (in_iframe) {
                dialog_options.position = function (dialog) {
                    return getIframeViewportPosition(dialog.$block);
                };
            }

            $.waDialog(dialog_options);
            this.data('$dialog', $('#' + id))
            return this.data('$dialog');
        }

        function getStartDate(format) {
            var $dialog = get$Dialog(),
                $input = $dialog.find('.datepicker.start'),
                date = new Date($input.datepicker('getDate'));
            return $.datepicker.formatDate(format, date);
        }

        function getEndDate(format) {
            var $dialog = get$Dialog(),
                $input = $dialog.find('.datepicker.end'),
                date = new Date($input.datepicker('getDate'));
            return $.datepicker.formatDate(format, date);
        }

        function formatDate(format, datetime, settings) {
            return $.datepicker.formatDate(format, new Date(datetime), settings);
        }

        function get$Dialog() {
            return this.data('$dialog');
        };

        return init.call(this);

    };
})(jQuery);
