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
        that.initToggleSidebar();
       // that.initSetWidth();
    };

    CRMSettings.prototype.initToggleSidebar = function() {
        var that = this,
            $sidebar = that.$wrapper.find('.sidebar:first'),
            //$content = that.$wrapper.find('.content:first'),
            $expandSidebarButton = $sidebar.find('.js-expand-sidebar');
        if ($expandSidebarButton.length) {
            $expandSidebarButton.on('click', function(event) {
                event.preventDefault();
                that.sidebarIsOpen = !that.sidebarIsOpen;
                $sidebar.toggleClass('sidebar-opened');
                $expandSidebarButton.find('.fa-arrow-right').toggleClass('fa-rotate-180');
                $("body").toggleClass("noscroll");
            });
            $(document).on("wa_before_load", removeContentClass);
        }
        function removeContentClass(event, content_uri){
             if(content_uri.content_uri.indexOf( '/settings/' ) < 0) {
                 $("body").removeClass("noscroll");
             }
             $(document).off("wa_before_load", removeContentClass);
             $expandSidebarButton.off('click');
         };
    }

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
                    location_string_slash = location.pathname + '/',
                    max_length = 0,
                    link_index = 0;

                $links.each(function (index) {
                    var $link = $(this);
                    var href = $link.attr("href");
                    var alias = $link.data("alias");
                    var href_length = href.length;

                    if (alias && href == alias) {
                        link_index = index;
                    } else if (location_string.indexOf(href) >= 0 || location_string_slash.indexOf(href) >= 0) {
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

   /* CRMSettings.prototype.initSetWidth = function() {
        var windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        console.log(windowWidth);
        if (windowWidth > 1281 || windowWidth <= 768) return;
        var that = this;
            $window = $(window),
            mainContent = that.$wrapper.find(".article"),
            sidebarFirst_w = $('.c-sidebar-wrapper').outerWidth(true),
            sidebarSettings_w = that.$sidebar.outerWidth(true);
        setWidth();

        $window.on("resize", onResize);

        function onResize() {
      
            var is_exist = $.contains(document, mainContent[0]);
            if (is_exist) {
                setWidth();
            } else {
                $window.off("resize", onResize);
            }
        }

        function setWidth() {
            let waWrapper_w = $('#wa-app').outerWidth(true);
            let sidebarsSum_w = `${Math.floor(waWrapper_w - sidebarFirst_w - sidebarSettings_w) - 1}px`;

            mainContent.css("max-width", sidebarsSum_w);
        }
    }*/

    return CRMSettings;

})(jQuery);
