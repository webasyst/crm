// CRM :: Sidebar
// Initialized in templates/actions/Sidebar.html
var CRMSidebar = (function ($) {

    CRMSidebar = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$ui = options["$ui"];
        // VARS
        that.selected_class = "selected";
        // that.storage_count_name = "crm/sidebar_counts";

        // DYNAMIC VARS
        that.$activeMenuItem = ( that.$wrapper.find("li." + that.selected_class + ":first") || false );
        that.xhr = false;
        that.timer = 0;
        // that.link_count_update_date = false;
        // that.counters = {};
        // that.storageCount = false;

        // INIT
        that.initClass();
    };

    CRMSidebar.prototype.initClass = function () {
        var that = this;
        //
        that.initElasticBlock();
        //
        that.initUpdater();
        //
        that.initSearch();
        //
        that.initRecent();
        //
        if (!that.$activeMenuItem.length) {
            that.selectLink();
        }
        // click on link
        that.$wrapper.on("click", "li > a", function () {
            var $link = $(this),
                uri = $link.attr("href");

            $link.find(".js-indicator").remove();

            if (uri && uri.substr(0, 11) != 'javascript:' && !$link.hasClass('js-no-highlight')) {
                if ($link.find('.icon16').attr('class') !== 'icon16 loading') {
                    var old_icon = $link.find('.icon16').attr('class'),
                        old_style = $link.find('.icon16').attr('style');
                    // put the loader icon
                    $link.find('.icon16').attr('class', 'icon16 loading').attr('style', '');

                    //return after loading the page
                    $(document).on('wa_loaded', function () {
                        $link.find('.icon16').attr('class', old_icon).attr('style', old_style);
                        $link.find(".highlighted").remove();
                    });
                }

                that.setItem($link.closest("li"));
                $.crm.title.set($link.text());
            }
        });
    };

    CRMSidebar.prototype.setItem = function ($item) {
        var that = this;

        if (that.$activeMenuItem && that.$activeMenuItem[0] == $item[0]) {
            return false;
        }

        if (that.$activeMenuItem) {
            that.$activeMenuItem.removeClass(that.selected_class);
        }

        $item.addClass(that.selected_class);
        that.$activeMenuItem = $item;
    };

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
        let sidebar_uri = app_url + "?module=sidebar&ui=" + that.$ui;
        if (background) {
            sidebar_uri += '&background_process=1';
        }

        clearTimeout(that.timer);

        if (that.xhr) {
            that.xhr.abort();
        }

        that.xhr = $.get(sidebar_uri, function (html) {
            that.xhr = false;
            that.$wrapper.replaceWith(html);
        });
    };

    CRMSidebar.prototype.initElasticBlock = function () {
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

    CRMSidebar.prototype.initUpdater = function () {
        var that = this,
            time = 1000 * 60 * 5;

        that.timer = setTimeout(function () {
            if ($.contains(document, that.$wrapper[0])) {
                that.reload(true);
            }
        }, time);
    };

    CRMSidebar.prototype.initRecent = function () {
        var that = this,
            $wrapper = that.$wrapper.find(".js-recent-wrapper"),
            $pinList = $wrapper.find(".js-pinned-list"),
            $unpinList = $wrapper.find(".js-recent-list"),
            $heading = $wrapper.find(".heading"),
            $headingIcon = $heading.find(".icon16");

        $wrapper.on("click", ".js-pin-recent", pinRecent);

        $wrapper.on("click", ".js-unpin-recent", unpinRecent);

        $wrapper.on("click", ".js-recent-heading-hidden", visibleRecent);

        $wrapper.on("click", ".js-recent-heading-visible", hiddenRecent);

        function pinRecent(event) {
            event.preventDefault();
            event.stopPropagation();

            var $icon = $(this),
                $li = $icon.closest("li"),
                contact_id = $li.data("id");

            var href = "?module=recent&action=pin",
                data = {
                    contact_id: contact_id
                };

            $.post(href, data, function (response) {
                if (response.status == "ok") {
                    $icon
                        .removeClass("js-pin-recent")
                        .addClass("js-unpin-recent")
                        .removeClass("star-empty")
                        .addClass("star");

                    var list_top = $pinList.offset().top,
                        list_h = $pinList.outerHeight(),
                        li_top = $li.offset().top;

                    var delta = list_top + list_h + 3 - li_top;

                    $li.addClass("highlighted");

                    if (delta) {
                        var top = delta + "px";
                        $li.css({
                            "-webkit-transform": "translate(0," + top + ")",
                            "transform": "translate(0," + top + ")"
                        });
                    }

                    setTimeout(function () {
                        $li
                            .removeClass("highlighted")
                            .removeAttr("style");

                        $li.appendTo($pinList);
                    }, 400);
                }
            });
        }

        function unpinRecent(event) {
            event.preventDefault();
            event.stopPropagation();

            var $icon = $(this),
                $li = $icon.closest("li"),
                contact_id = $li.data("id");

            var href = "?module=recent&action=unpin",
                data = {
                    contact_id: contact_id
                };

            $.post(href, data, function (response) {
                if (response.status == "ok") {
                    $li.remove();
                }
            });
        }

        function visibleRecent() {
            $wrapper.removeClass("js-recent-fold-hidden").addClass("js-recent-fold-visible");
            $heading.removeClass("js-recent-heading-hidden").addClass("js-recent-heading-visible");
            $headingIcon.attr("class", "icon16 darr");

            var href = "?module=recent&action=fold",
                data = {
                    fold_hidden: 0
                };

            $.post(href, data);

            $(window).trigger("scroll");
        }

        function hiddenRecent() {
            $wrapper.removeClass("js-recent-fold-visible").addClass("js-recent-fold-hidden");
            $heading.removeClass("js-recent-heading-visible").addClass("js-recent-heading-hidden");
            $headingIcon.attr("class", "icon16 rarr");

            var href = "?module=recent&action=fold",
                data = {
                    fold_hidden: 1
                };

            $.post(href, data);

            $(window).trigger("scroll");
        }
    };

    CRMSidebar.prototype.initSearch = function () {
        var that = this,
            $form = that.$wrapper.find(".js-search-form"),
            $field = $form.find(".js-search-field");

        $form.on("submit", function (event) {
            event.preventDefault();
            var search_string = $field.val();

            search_string = search_string.replace(/\//g, "\\/").replace(/&/g, "\\&").replace(/\+/g, "");

            if (!(search_string.length && $.trim(search_string) )) {
                return false;
            }

            var content_uri = $.crm.app_url + "contact/search/result/contact_info." + ( search_string.indexOf("@") >= 0 ? "email" : "name.name" ) + "*=" + encodeURIComponent(search_string);

            $.crm.content.load(content_uri);

            $field.val("");
        });

        $field.autocomplete({
            appendTo: $form,
            classes: {
                "ui-autocomplete": "c-search-results-list"
            },
            source: "?module=autocompleteSidebar",
            minLength: 2,
            html: true,
            focus: function () {
                return false;
            },
            select: function (event, ui) {
                var link = ui.item.link;
                if (link) {
                    var content_uri = $.crm.app_url + link;
                    $.crm.content.load(content_uri);
                }
                $field.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function (ul, item) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
        };
    };

    return CRMSidebar;

})(jQuery);
