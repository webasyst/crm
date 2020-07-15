var CRMSettingsSourceWithContactBlock = ( function($) {

    CRMSettingsSourceWithContactBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.segments_list_stages = options.segments_list_stages || {};

        // INIT
        that.init();
    };

    CRMSettingsSourceWithContactBlock.prototype.init = function() {
        var that = this;
        that.initAddToSegments();
    };

    CRMSettingsSourceWithContactBlock.prototype.initAddToSegments = function () {
        var that = this,
            $wrapper = that.$wrapper,
            states = that.segments_list_stages,
            $link = $wrapper.find('.c-segments-ul-fold-link'),
            $ul = $wrapper.find('.c-segments-ul');

        $link.click(function () {
            var state = $link.data('state');
            if (state === 'fold') {
                state = 'unfold';
            } else {
                state = 'fold';
            }
            var text = (states[state] || {}).link_text || '';

            // update link properties
            $link.data('state', state).find('.c-link-text').text(text);

            // toggle ul
            if (state === 'fold') {
                $ul.slideUp();
            } else {
                $ul.slideDown();
            }
        });
    };

    return CRMSettingsSourceWithContactBlock;

})(jQuery);
