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
        that.menu_state = options["menu_state"];
        // that.storage_count_name = "crm/sidebar_counts";

        // DYNAMIC VARS
        that.$activeMenuItem = ( that.$wrapper.find("li." + that.selected_class + ":first") || false );
        that.xhr = false;
        that.timer = 0;
        that.bricks_expander_is_hide = false;
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
        that.initTooltips();
        that.initRailToggle();
        that.initExpandBricks();

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

        $(document).on('wa_funnel_save', () => {
            $.crm.sidebar.reload();
        })

        function handleUrl(stateUrl) {
            const startIndex = stateUrl.indexOf('crm/');
            if (startIndex && startIndex > 0) {
                const strUrlTemp = stateUrl.slice(startIndex + 4);
                const endIndex = strUrlTemp.indexOf('/');
                const strUrl = strUrlTemp.slice(0, endIndex);

                let query = '';
                const funnel = strUrlTemp.match(/[?|&]funnel=(\d+)/)?.[1];
                if (funnel) {
                    query = '?funnel=' + funnel;
                }
                if (strUrlTemp.includes('all_funnels=1')) {
                    query = '?all_funnels=1';
                }

                $item = that.$wrapper.find(`a[href*="crm/${strUrl}/${query}"]`);
                if ($item.length === 1) {
                    that.setItem(that.getLiOrBrick($item));
                    $.crm.title.set($item.data('wa-tooltip-content'));
                }
            }
        }
    };

    CRMSidebar.prototype.updateBody = function (html) {
        const that = this;
        const $active_item = that.$body.find('.selected');
        const index = $active_item.index();
        const is_brick = $active_item.hasClass('brick');

        that.$body.html(html);
        if (index !== -1) {
            const $item = that.$body.find(is_brick ? '.brick' : 'li').eq(index).addClass(that.selected_class);
            that.setItem(that.getLiOrBrick($item));
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
        if ($item.length) {
            that.$wrapper.find('#c-sidebar-bricks a').removeClass(that.selected_class);
            that.$body.find('li').removeClass(that.selected_class);
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
            that.setItem(that.getLiOrBrick($link));

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

    CRMSidebar.prototype.getLiOrBrick = function ($link) {
        return $link.hasClass('brick') ? $link : $link.closest('li');
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
            that.updateBody(html);
            that.initUpdater();
            that.initTooltips();
            that.toggleBricksExpander();
        });
    };

    CRMSidebar.prototype.initTooltips = function () {
        var that = this;

        that.$wrapper.find('[data-wa-tooltip-content]').each(function() {

            $(this).waTooltip({placement:"right"});

            if(that.menu_state === 'expanded') {
                $(this).data('tooltip')._promise
                    .then(tippy => tippy.disable())
                    .catch(error => console.error('Tooltip error:', error));
            }
        })
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
            time = 1000 * 60 * 2;

        that.timer = setTimeout(function () {
            if ($.contains(document, that.$wrapper[0])) {
                that.reload(true);
            }
        }, time);
    };

    CRMSidebar.prototype.initRailToggle = function () {
        const that = this;
        const $toggle = that.$wrapper.find('.js-toggle-sidebar');

        // сохраняем начальное состояние
        that._user_menu_state = that.menu_state;

        const applyState = (state, save_setting = false) => {
            const isCollapsedLocal = state === 'collapsed';
            that.$wrapper.toggleClass('rail', isCollapsedLocal);
            $toggle.find('.fa-caret-left').toggleClass('hidden', isCollapsedLocal);
            $toggle.find('.fa-caret-right').toggleClass('hidden', !isCollapsedLocal);

            // tooltips
            that.$wrapper.find('[data-wa-tooltip-content]').each(function () {
                if (state === 'collapsed') {
                    $(this).data('tooltip')?._promise.then((tippy) => tippy.enable());
                } else {
                    $(this).data('tooltip')?._promise.then((tippy) => tippy.disable());
                }
            });

            const $menuNames = that.$wrapper.find('span:not(.icon)');
            that.$wrapper
                .off('transitionstart transitionend')
                .on('transitionstart', (event) => {
                    if(event.target == event.currentTarget) {
                        $menuNames.toggleClass('hidden', !that.$wrapper.hasClass('rail'));
                    }})
                .on('transitionend', (event) => {
                    if(event.target == event.currentTarget) {
                        $menuNames.toggleClass('hidden', that.$wrapper.hasClass('rail'));
                    }
                });

            if (save_setting) {
                that.menu_state = state;
                that._user_menu_state = state;
                saveUserSettings(state);
            }
        };

        const setResponsiveState = () => {
            const width = window.innerWidth;
            const isTablet = width >= 760 && width <= 1024;
            const isMobile = width < 760;

            if (isTablet) {
                $toggle.addClass('hidden');
                applyState('collapsed'); // принудительно, без сохранения
            } else if (isMobile) {
                $toggle.addClass('hidden');
                applyState('expanded'); // принудительно, без сохранения
            } else {
                $toggle.removeClass('hidden');
                applyState(that._user_menu_state); // возвращаем исходное состояние
            }
            that.toggleBricksExpander();
        };

        // Первичная инициализация
        setResponsiveState();

        // Пересчитываем при изменении размеров окна
        $(window).off('resize.sidebar').on('resize.sidebar', setResponsiveState);

        // Клик-обработчик (работает только когда кнопка видна)
        $toggle.off('click').on('click', function () {
            if ($toggle.hasClass('hidden')) return;
            const newState = that.$wrapper.hasClass('rail') ? 'expanded' : 'collapsed';
            applyState(newState, true); // с сохранением
        });

        function saveUserSettings(menu_state) {
            const deferred = $.Deferred();
            const data = { sidebar_menu_state: menu_state };
            const app_url = $.crm.app_url;
            let save_uri = app_url + "?module=sidebar&action=saveMenuState";

            $.post(save_uri, data, "json").always(function () {
                deferred.resolve();
            });

            return deferred.promise();
        }
    };

    CRMSidebar.prototype.initExpandBricks = function() {
        const that = this;

        that.toggleBricksExpander();

        that.$wrapper.on('click', '.js-expand-bricks', function() {
            that.$body.removeClass('bricks-peek');
            $(this).parent().remove();
            that.bricks_expander_is_hide = true;
        });
    };

    CRMSidebar.prototype.toggleBricksExpander = function() {
        const that = this;
        if (that.bricks_expander_is_hide) {
            return;
        }
        const $bricks = that.$body.find('#c-sidebar-bricks');
        const brick_count = $bricks.children().length;
        if (!brick_count) {
            return;
        }

        const brick_height = 72; // px
        const bricks_expander_height = 26; // px
        const offset_height = -10; // px
        const min_bricks_desktop = 2;
        const min_bricks_mobile = 5;

        const max_height = that.$wrapper.height();
        const sections_height = that.$body.find('.c-sidebar-sections').height();
        const footer_height = that.$footer.height();

        let brick_peek_count = Math.max(
            Math.floor((max_height - footer_height - sections_height - bricks_expander_height - offset_height) / brick_height),
            min_bricks_desktop
        );
        if (window.matchMedia('(max-width: 760px)').matches) {
            brick_peek_count = min_bricks_mobile;
        }
        that.$body.css('--max-height-bricks', brick_height * brick_peek_count + 'px');
        that.$body.toggleClass('bricks-peek', brick_count > brick_peek_count);
    };

    return CRMSidebar;

})(jQuery);
