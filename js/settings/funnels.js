var CRMSettingsFunnels = ( function($) {

    CRMSettingsFunnels = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsFunnels.prototype.initClass = function() {
        var that = this;

        //
        that.initTabs();
    };

    CRMSettingsFunnels.prototype.initTabs = function() {
        var that = this,
            $section = that.$wrapper.find(".js-funnels-tabs"),
            $companies = that.$wrapper.find(".c-funnels-wrapper"),
            $list = $companies.find(".c-funnels-list"),
            $activeTab = $list.find(".c-funnel.selected");

        initSetWidth();

        initSlider();

        initSort();

        //

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

        function initSort() {
            var xhr = false;

            $list.sortable({
                //helper: "clone",
                distance: 10,
                items: "> li",
                axis: "x",
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

                xhr = $.post(href, data, function(response) {

                }).always( function() {
                    xhr = false;
                });

                function getIds() {
                    var result = [];

                    $list.find(".c-funnel").each( function() {
                        result.push( $(this).data("id") );
                    });

                    return result.join(",");
                }
            }
        }
    };

    return CRMSettingsFunnels;

})(jQuery);