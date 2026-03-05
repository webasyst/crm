var CRMSettingsFunnels = ( function($) {

    CRMSettingsFunnels = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // INIT
        that.initClass();
    };

    CRMSettingsFunnels.prototype.initClass = function() {
        var that = this;

        // STATES
        that.sidebar_inited = false;
        that.tabs_inited = false;

        that.init();

        var timer_id = null;
        $(window).on('resize', () => {
            if (timer_id) {
                clearTimeout(timer_id);
            }
            timer_id = setTimeout(() => {
                that.init();
                timer_id = null;
            }, 300);
        });
    };

    CRMSettingsFunnels.prototype.init = function() {
        var that = this;

        const is_mobile = innerWidth <= 760;
        if (is_mobile) {
            if (that.tabs_inited) return;

            that.initTabs();

            that.tabs_inited = true;
        } else {
            if (that.sidebar_inited) return;

            that.initSidebar();

            that.sidebar_inited = true;
        }
    };

    CRMSettingsFunnels.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".c-funnels-section .js-funnels-tabs"),
            $companies = that.$wrapper.find(".c-funnels-wrapper"),
            $list = $companies.find(".c-funnels-list"),
            $activeTab = $list.find(".c-funnel.selected");

        initSetWidth();

        initSlider();

        that.initSort($list, 'x');

        function initSetWidth() {
            var $window = $(window),
                other_w = $section.find(".c-add-wrapper").outerWidth();

            setWidth();

            $window.on("resize", onResize);

            function onResize() {
                var is_exist = $.contains(document, $section[0]);
                if (is_exist) {
                    setWidth();
                } else {
                    $window.off("resize", onResize);
                }
            }

            function setWidth() {
                var section_w = $section.outerWidth(true),
                    max_w = section_w - other_w - 38;
                $companies.css("max-width", max_w + "px");
            }
        }

        function initSlider() {
            $.crm.tabSlider({
                $wrapper: $companies,
                $slider: $list,
                $activeSlide: ($activeTab.length ? $activeTab : false)
            });
        }
    };

    CRMSettingsFunnels.prototype.initSidebar = function() {
        var that = this,
            $list = that.$wrapper.find(".sidebar .c-funnels-list");

        that.initSort($list);
    };

    CRMSettingsFunnels.prototype.initSort = function($list, axis = 'y') {
        let xhr = false;
        let initialSortIds = [];

        $list.uiSortable({
            distance: 10,
            axis,
            items: "> li",
            start: () => {
                initialSortIds = getIds().replace(',,', ',');
            },
            stop: save,
            onUpdate: save
        });

        function save() {
            var href = "?module=settings&action=funnelsSortSave",
                ids = getIds(),
                data = {
                    ids: ids
                };

            if (xhr) {
                xhr.abort();
            }

            xhr = $.post(href, data, function() {
                if (initialSortIds !== ids) {
                    $(document).trigger('wa_funnel_save');
                }
            }).always( function() {
                xhr = false;
            });
        }

        function getIds() {
            var result = [];

            $list.find(".c-funnel").each( function() {
                result.push( $(this).data("id") );
            });

            return result.join(",");
        }
    }

    return CRMSettingsFunnels;

})(jQuery);
