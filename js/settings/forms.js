var CRMSettingsForms = ( function($) {

    CRMSettingsForms = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('[type=submit]');

        // DYNAMIC VARS
        that.submit_xhr = null;

        // INIT
        that.initClass();
    };

    CRMSettingsForms.prototype.initClass = function () {
        var that = this;
        //
        $.crm.renderSVG(that.$wrapper);
    };

    return CRMSettingsForms;

})(jQuery);
