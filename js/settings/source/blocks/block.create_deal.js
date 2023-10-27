var CRMSettingsSourceCreateDealBlock = ( function($) {

    CRMSettingsSourceCreateDealBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$funnel = that.$wrapper.find('.js-funnel-id');
        that.$stage = that.$wrapper.find('.js-stage-id');

        // VARS
        that.url = $.crm.app_url + '?module=settingsSourceBlock&action=createDeal';
        that.source = options.source || {};
        that.ns = '.' + $.trim(that.$wrapper.attr('id'));
        that.id = options.id;
        that.namespace = options.namespace || '';
        that.class_id = options.class_id || '';

        // DYNAMIC VARS
        that.responsible_block = null;

        // INIT
        that.initClass();
        that.$wrapper.data('block_object', that);
    };

    /**
     * @param {CRMSettingsSourceResponsibleBlock} block_object
     */
    CRMSettingsSourceCreateDealBlock.prototype.setResponsibleBlock = function (block_object) {
        this.responsible_block = block_object;
    };

    CRMSettingsSourceCreateDealBlock.prototype.initClass = function() {
        var that = this;
        that.$funnel.on('change' + that.ns, function () {
            var $li = that.$stage.closest('.dropdown');
            var $item = $li.find('.js-visible-link');
            $li.find('.menu.with-icons').remove();
            $item.find('.funnel-state').remove();
            $item.prepend('<span class="icon size-16"><i class="fas fa-spinner fa-spin"></i></span>');
            that.reload();
        });
        that.initFunnelsSelect();
        that.initStagesSelect();
    };

    CRMSettingsSourceCreateDealBlock.prototype.initFunnelsSelect = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-funnels-list"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu");

        $menu.on("click", "a", function () {
            var $link = $(this);

            $visibleLink.html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id).trigger("change");
        });

        //$.crm.renderSVG($wrapper);
    };

    CRMSettingsSourceCreateDealBlock.prototype.initStagesSelect = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-stages-list"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id).trigger("change");
        });

        //$.crm.renderSVG($wrapper);
    };

    CRMSettingsSourceCreateDealBlock.prototype.reload = function () {
        var that = this,
            $funnel = that.$funnel,
            $stage = that.$stage,
            url = that.url,
            responsible_block_object = that.responsible_block,
            data = {
                id: that.id,
                funnel_id: $funnel.val(),
                stage_id: $stage.val(),
                namespace: that.namespace
            };

        var d1 = $.Deferred(),
            d2 = $.Deferred();

        $.when(d1, d2).done(function (v1, v2) {
            var block_object = v1,
                responsible_block_object = v2;
            responsible_block_object &&
                block_object.setResponsibleBlock(responsible_block_object);
        });

        $.get(url, data, function (html) {
            var $tmp = $('<div>').html(html),
                $wrapper = $tmp.find('.' + that.class_id);
            that.$wrapper.replaceWith($wrapper);
            d1.resolve($wrapper.data('block_object'));
            that.destroy();
            $tmp.remove();
        });

        if (!responsible_block_object) {
            d2.resolve();
        } else {
            responsible_block_object.reload(
                { funnel_id: data.funnel_id },
                function (responsible_block_object) {
                    d2.resolve(responsible_block_object);
                }
            );
        }

    };

    CRMSettingsSourceCreateDealBlock.prototype.destroy = function () {
        var that = this;
        that.$funnel.off(that.ns);
        that.$wrapper.removeData('block_object');
        $.each(that, function (key) {
            delete that[key];
        });
    };

    return CRMSettingsSourceCreateDealBlock;

})(jQuery);
