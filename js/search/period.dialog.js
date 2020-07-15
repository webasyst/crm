(function ($) {
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
            $html.appendTo('body');
            $html.show();
            new CRMDialog({
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
                }
            });
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
