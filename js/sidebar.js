// CRM :: Sidebar
// Initialized in templates/actions/Sidebar.html
var CRMSidebar = (function ($) {

    CRMSidebar = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$ui = options["$ui"];
        that.$body = that.$wrapper.find(".sidebar-body");
        that.$footer = that.$wrapper.find(".sidebar-footer");
        that.$toggler = that.$wrapper.find('.sidebar-mobile-toggle');
        that.$sidebar_open = false;

        // VARS
        that.selected_class = "selected";
        // that.storage_count_name = "crm/sidebar_counts";

        // DYNAMIC VARS
        that.$activeMenuItem = ( that.$wrapper.find("li." + that.selected_class + ":first") || false );
        that.xhr = false;
        that.timer = 0;
        //that.body_visible = false;
        // that.link_count_update_date = false;
        // that.counters = {};
        // that.storageCount = false;

        // INIT
        that.initClass();
    };

    CRMSidebar.prototype.initClass = function () {
        var that = this;
        //
        //that.initElasticBlock();
        //
        that.initUpdater();
        that.initMobileToggle();

        if (!that.$activeMenuItem.length) {
            that.selectLink();
        }
        // click on link
        that.$wrapper.on("click", "li > a", function () {
            var $link = $(this),
                uri = $link.attr("href");
                it_reminder = uri.indexOf('reminder') + 1;
                if (!it_reminder) {
                    $link.find(".js-indicator").remove();
                }

            /*if (uri && uri.substr(0, 11) != 'javascript:' && !$link.hasClass('js-no-highlight')) {
                that.setItem($link.closest("li"));
                $.crm.title.set($link.text());
            }*/
        });

       /* window.addEventListener('popstate', function(event) {
            console.log('popstate');
            is_on_popstate = true;
            //$(document).on('wa_on_popstate', function(event) {
            const stateUrl = event.state.content_uri; //event.currentTarget.URL;
            handleUrl(stateUrl);
        })*/

        $(document).ready(function () {
            const stateUrl = window.location.href;
            handleUrl(stateUrl);
        })

        $(document).on("wa_before_render", function () {
            const stateUrl = window.location.href;
            handleUrl(stateUrl);
        })

        function handleUrl(stateUrl) {
            const startIndex = stateUrl.indexOf('crm/');
            if (startIndex && startIndex > 0) {
                const strUrlTemp = stateUrl.slice(startIndex + 4);
                const endIndex = strUrlTemp.indexOf('/');
                const strUrl = strUrlTemp.slice(0, endIndex);
                $item = ( that.$wrapper.find(`a[href*="${strUrl}"]`) || false );
                that.setItem($item.closest("li"));
                $.crm.title.set($item.data('wa-tooltip-content'));
            }
        }

        const isMdWithCursor = window.matchMedia("(min-width: 761px) and (pointer: fine)").matches;
        if (isMdWithCursor) {
            that.$body.find('li > a').each(function() {
                $(this).waTooltip({placement:"right"});
            })
            that.$footer.find('li > a').each(function() {
                $(this).waTooltip({placement:"right"});
            })
        }
    };

    CRMSidebar.prototype.setItem = function ($item) {
        var that = this;

        if (that.$activeMenuItem.length && that.$activeMenuItem[0] == $item[0]) {
            return false;
        }

        if (that.$activeMenuItem.length) {
            that.$activeMenuItem.removeClass(that.selected_class);
        }

        $item.addClass(that.selected_class);
        that.$activeMenuItem = $item;
    };

    CRMSidebar.prototype.initMobileToggle = function () {
        var that = this;
            that.$toggler.on('click.sidebar touchstart.sidebar', toggleMenu);
            $(document).on('wa_before_render', toggleMenu);
        function toggleMenu(event) {
            that.is_mobile = that.$toggler.is(':visible');
            if (!that.is_mobile) return;
            const is_reload = event.type === 'wa_before_render';
            if (is_reload && !that.$sidebar_open) return;

            if (event) {
                event.preventDefault();
            }

            window.scrollTo({
                top:0,
                behavior: 'smooth'
            });
            that.$toggler.siblings().each((i, el) => {
                if (el.nodeName !== 'SCRIPT' && el.nodeName !== 'STYLE') {
                    $(el).slideToggle(400, function () {
                        const self = $(this);
                        if (self.is(':hidden')) {
                            self.css('display', '');
                            that.$sidebar_open = false;
                        }
                        else {that.$sidebar_open = true;}
                    });
                }
            });
        };
    }

    CRMSidebar.prototype.selectLink = function (uri) {
        var that = this,
            $link;

        if (uri) {
            $link = that.$wrapper.find('a[href="' + uri + '"]:first');
        }

        if ($link && $link.length) {
            that.setItem($link.closest("li"));

        } else {
            var $links = that.$wrapper.find("a[href^='" + $.crm.app_url + "']"),
                location_string = location.pathname,
                max_length = 0,
                link_index = 0;

            $links.each(function (index) {
                var $link = $(this),
                    href = $link.attr("href"),
                    href_length = href.length;

                if (location_string.indexOf(href) >= 0) {
                    if (href_length > max_length) {
                        max_length = href_length;
                        link_index = index;
                    }
                }
            });

            if (link_index || link_index === 0) {
                $link = $links.eq(link_index);
                that.setItem($link.closest("li"));
            }
        }
    };

    CRMSidebar.prototype.reload = function (background) {
        const that = this;
        const app_url = $.crm.app_url;
        let sidebar_uri = app_url + "?module=sidebar&ui=" + that.$ui + "&reload=1";
        if (background) {
            sidebar_uri += '&background_process=1';
        }

        clearTimeout(that.timer);

        if (that.xhr) {
            that.xhr.abort();
        }
        that.xhr = $.get(sidebar_uri, function (html) {
            that.$toggler.off('.sidebar');
            //$(document).off('wa_before_render', toggleMenu);
            that.xhr = false;
            that.$body.html(html);

        });
    };

/*    CRMSidebar.prototype.initElasticBlock = function () {
        var that = this;

        // Init elastic block
        $(document).ready(function () {
            new CRMElasticBlock({
                $wrapper: $("#wa-app"),
                $aside: that.$wrapper,
                $content: $.crm.content.$content
            });

            var $window = $(window);
            if ($window.scrollTop() > 0) {
                $window.trigger("scroll");
            }
        });
    };
*/
    CRMSidebar.prototype.initUpdater = function () {
        var that = this,
            time = 1000 * 60 * 5; //1000 * 60 * 5

        that.timer = setTimeout(function () {
            if ($.contains(document, that.$wrapper[0])) {
                that.reload(true);
            }
        }, time);
    };

    return CRMSidebar;

})(jQuery);
