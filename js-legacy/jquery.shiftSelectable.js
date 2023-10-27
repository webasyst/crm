(function($) {
    "use strict";

    $.fn.shiftSelectable = function () {
        var $context = this,
            args = arguments;

        if ($context.length > 1) {
            $context.each(function () {
                $.fn.shiftSelectable.apply($(this), args);
            });
            return;
        }

        var options = $context.data('shiftSelectable');

        if ($.isPlainObject(args[0])) {

            if ($.isPlainObject(options)) {
                // plugin has already called for this $context
                return;
            }

            options = args[0];
        }

        var ns = options.ns || (Math.random() + '').slice(2);

        // destroy call
        if (args[0] === 'destroy') {
            $context.off('.' + ns);
            $context.data('shiftSelectable');
            return;
        }

        var $pivot_item,
            selector = options.selector,
            onSelect = options.onSelect;

        // Behavior type vertical/horizontal/mixed. Default is vertical
        var behavior_type = options.behavior_type;
        if (behavior_type !== 'vertical' && behavior_type !== 'horizontal' && behavior_type !== 'mixed') {
            behavior_type = 'vertical';
        }

        // Default is true
        var disable_text_selection = options.disable_text_selection;
        disable_text_selection = disable_text_selection !== undefined ? disable_text_selection : true;
        if (disable_text_selection) {
            disbleTextSelection();
        }


        $context.on('click.' + ns, selector, function (event) {
            var $item = $(this),
                shiftKey = event.shiftKey;

            if (!$pivot_item) {
                $pivot_item = $item;
                return;
            }

            if (shiftKey) {
                if (isDirectionDown($pivot_item, $item)) {
                    selectRange($pivot_item, $item, event);
                } else {
                    selectRange($item, $pivot_item, event);
                }
            }

            $pivot_item = $item;
        });

        function isDirectionDown($first, $last) {
            var first_offset = $first.offset(),
                last_offset = $last.offset();
            if (behavior_type === 'vertical') {
                return last_offset.top > first_offset.top;
            } else if (behavior_type === 'horizontal') {
                return last_offset.left > first_offset.left;
            } else {
                return last_offset.top > first_offset.top || last_offset.left > first_offset.left;
            }
        }

        function selectRange($first, $last, event)
        {
            var in_selection = false,
                first = $first.get(0),
                last = $last.get(0);
            $context.find(selector).each(function () {
                var is_first = this == first,
                    is_last = this == last;

                if (is_first) {
                    in_selection = true;
                }
                if (in_selection) {
                    event.extra = {
                        is_first: is_first,
                        is_last: is_last
                    };
                    onSelect && onSelect($(this), event);
                }
                if (is_last) {
                    in_selection = false;
                    return false;
                }
            });
        }

        function disbleTextSelection() {
            $context
                .attr('unselectable', 'on')
                .css({
                    '-moz-user-select':'-moz-none',
                    '-o-user-select':'none',
                    '-khtml-user-select':'none',
                    '-webkit-user-select':'none',
                    '-ms-user-select':'none',
                    'user-select':'none'})
                .on('selectstart', false)
                .on('mousedown', false);
        }

    };
})(jQuery);
