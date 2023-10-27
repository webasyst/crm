var CRMSettings = ( function($) {

    CRMSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$sidebar = options["$sidebar"];

        // VARS

        // DYNAMIC VARS
        that.$activeMenuItem = false;

        // INIT
        that.initClass();
    };

    CRMSettings.prototype.initClass = function() {
        var that = this;

        that.initMenu();
    };

    CRMSettings.prototype.initMenu = function() {
        var that = this,
            $sidebar = that.$sidebar,
            selected_class = "selected";

        var $activeMenuItem = ( $sidebar.find("li." + selected_class + ":first") || false );

        //
        if (!$activeMenuItem.length) {
            selectLink();
        }

        $sidebar.on("click", "li > a", function() {
            onLinkClick( $(this) );
        });

        function onLinkClick( $link ) {
            var uri = $link.attr("href");

            if (uri && uri.substr(0, 11) != 'javascript:' && !$link.hasClass('js-no-highlight')) {
                setItem( $link.closest("li") );
                $.crm.title.set( $link.text() );
            }
        }

        function setItem( $item ) {
            if ($activeMenuItem.length && $activeMenuItem[0] == $item[0]) {
                return false;
            }

            if ($activeMenuItem.length) {
                $activeMenuItem.removeClass(selected_class);
            }

            $item.addClass(selected_class);
            $activeMenuItem = $item;
        }

        function selectLink( uri ) {
            var $link;

            if (uri) {
                $link = $sidebar.find('a[href="' + uri + '"]:first');
            }

            if ($link && $link.length) {
                setItem( $link.closest("li") );

            } else {
                var $links = $sidebar.find("a[href^='" + $.crm.app_url + "']"),
                    location_string = location.pathname,
                    max_length = 0,
                    link_index = 0;

                $links.each(function (index) {
                    var $link = $(this);
                    var href = $link.attr("href");
                    var alias = $link.data("alias");
                    var href_length = href.length;

                    if (alias && href == alias) {
                        link_index = index;
                    } else if (location_string.indexOf(href) >= 0) {
                        if ( href_length > max_length ) {
                            max_length = href_length;
                            link_index = index;
                        }
                    }
                });

                if (link_index || link_index === 0) {
                    $link = $links.eq(link_index);
                    setItem( $link.closest("li") );
                }
            }
        }
    };

    return CRMSettings;

})(jQuery);
