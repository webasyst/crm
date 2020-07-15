var CRMImSourceSettingsVkBlock = ( function($) {

    CRMImSourceSettingsVkBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.source = options.source || {};

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMImSourceSettingsVkBlock.prototype.initClass = function() {
        var that = this;

        if (that.source.id > 0) {
            that.initUrlInputs();
        }
    };

    CRMImSourceSettingsVkBlock.prototype.initUrlInputs = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $input = $wrapper.find('.js-url-input');
        $input.click(function () {
           $(this).select();
        });
    };

    return CRMImSourceSettingsVkBlock;

})(jQuery);
