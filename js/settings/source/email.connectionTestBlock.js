var CRMSettingsSourceEmailConnectionTestBlock = ( function($) {

    CRMSettingsSourceEmailConnectionTestBlock = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;

        // VARS
        that.source = options.source || {};
        that.url = options.url || ($.crm.app_url + '?module=settingsSource&action=testConnection');

        // INIT
        that.initClass();
    };

    CRMSettingsSourceEmailConnectionTestBlock.prototype.initClass = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $block = $wrapper.find('.c-test-connection-button-block'),
            $loading = $block.find('.c-loading'),
            $success_message = $block.find('.c-test-connection-success-message'),
            $fail_message = $block.find('.c-test-connection-fail-message'),
            xhr = null,
            $inputs = $wrapper.find('.js-connection-settings-input'),
            $checkboxes = $wrapper.find('.js-connection-settings-checkbox'),
            $button = $wrapper.find('.c-test-connection-button');

        var initBlock = function () {
            var number_of_inputs = $inputs.length;
            var isAllInputsFilled = function () {
                var filled_count = 0;
                $inputs.each(function () {
                    if ($.trim($(this).val()).length > 0) {
                        filled_count += 1;
                    }
                });
                if (that.source.id <= 0) {
                    return filled_count == number_of_inputs;
                } else {
                    // presume password has already been filled
                    return filled_count == number_of_inputs - 1;
                }
            };

            var getSnapshot = function () {
                var values = [];
                $inputs.each(function () {
                    values.push($.trim($(this).val()));
                });
                $checkboxes.each(function () {
                    values.push($(this).is(':checked') ? '1' : '0');
                });
                return values.join('@');
            };

            // for tracking had values changed
            var snapshot = getSnapshot();

            var hadValuesChanged = function () {
                var new_snapshot = getSnapshot(),
                    changed = new_snapshot !== snapshot;
                snapshot = new_snapshot;
                return changed;
            };

            var onValuesChanged = function () {
                $block.show();
                $button.show();
                $success_message.hide();
                $fail_message.hide();
            };

            var inputsChangeHandler = function () {
                if (!isAllInputsFilled()) {
                    $block.hide();
                    return;
                }
                if (hadValuesChanged()) {
                    onValuesChanged();
                }
            };

            var timer = null;
            $inputs
                .on('change', inputsChangeHandler)
                .on('keydown', function () {
                    timer && clearTimeout(timer);
                    timer = setTimeout(function () {
                        inputsChangeHandler();
                    }, 250);
                });
            $checkboxes.on('change', function () {
                if (hadValuesChanged()) {
                    onValuesChanged();
                }
            });
        };

        var testConnection = function () {
            var url = that.url;

            var serialize = function () {

                // helper for filling data
                var getValue = function ($el) {
                    var name = $.trim($el.attr('name'));
                    var result = name.match(/^source\[params\]\[(.+)\]$/);
                    return {
                        name: result[1],
                        value: $el.val()
                    };
                };

                var data = [];
                $inputs.each(function () {
                    data.push(getValue($(this)));
                });
                $checkboxes.each(function () {
                    var $el = $(this);
                    if ($el.is(':checked')) {
                        data.push(getValue($el));
                    }
                });

                if (that.source.id > 0) {
                    data.push({
                        name: 'id',
                        value: that.source.id
                    });
                } else {
                    data.push({
                        name: 'id',
                        value: that.source.provider
                    });
                }

                return data;
            };

            // preparation before send query
            xhr && xhr.abort();
            $success_message.hide();
            $fail_message.hide();
            $loading.show();
            $inputs.attr('disabled', true);

            var onDone = function (r) {
                if (r && r.status == 'ok') {
                    $success_message.show();
                    return;
                }
                onFail();
                CRMSettingsSourceEmail.showValidateErrors($wrapper, r.errors || {});
            };

            var onFail = function () {
                $fail_message.show();
            };

            var onAlways = function () {
                xhr = null;
                $inputs.attr('disabled', false);
                $loading.hide();
            };

            $.post(url, serialize(), null, 'json')
                .done(onDone)
                .fail(onFail)
                .always(onAlways);

        };

        var initSubmit = function () {
            $button.click(function (e) {
                e.preventDefault();
                testConnection();
            });
        };

        // alive UI of block
        initBlock();
        // test connection on click button
        initSubmit();
    };

    return CRMSettingsSourceEmailConnectionTestBlock;

})(jQuery);
