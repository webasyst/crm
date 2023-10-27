var CRMContactsSidebar = (function ($) {

    CRMContactsSidebar = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$segmentsLists = that.$wrapper.find('.c-segment-list');
        that.$add_new_segment_link = that.$wrapper.find('.c-create-new-segment');

        // VARS
        that.is_admin = options["is_admin"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsSidebar.prototype.initClass = function () {
        var that = this;
        //
        that.initSortable();
        //
        that.initAddLink();
        //
        that.initAddNewSegmentLink();
        //
        that.initHighlight();
        //
        that.initSelectedLink();
        //
        that.initArchivedToggles();
        //
        that.initScrollTopOnClick();
    };

    CRMContactsSidebar.prototype.initSortable = function() {
        var that = this,
            href = $.crm.app_url + "?module=contact&action=sortSave",
            item_index,
            xhr = false;

        that.$segmentsLists.each(function () {
            var $segmentsList = $(this);
            var shared = $segmentsList.data('shared') ? 1 : 0;
            if (!that.is_admin && shared) {
                return;
            }
            $segmentsList.sortable({
                distance: 10,
                items: "> li:not(.sort-disabled)",
                axis: "y",
                delay: 200,
                tolerance: "pointer",
                start: function(event,ui) {
                    item_index = ui.item.index();
                },
                stop: function(event,ui) {
                    ui.item.removeAttr("style");
                    if (item_index != ui.item.index()) {
                        var sortArray = getSortArray($segmentsList);
                        saveSort(href, {
                            segments: sortArray,
                            shared: shared
                        });
                    }
                }
            })
        });

        function getSortArray($list) {
            return $list.find("> li").map(function() {
                var id = parseInt($(this).data("id"), 10);
                return id > 0 ? id : false;
            }).toArray();
        }

        function saveSort(href, data) {
            if (xhr) {
                xhr.abort();
                xhr = false;
            }
            xhr = $.post(href, data, function() {
                xhr = false;
            });
        }

    };

    CRMContactsSidebar.prototype.initAddLink = function () {
        var that = this,
            is_locked = false;

        var $add_link = that.$wrapper.find('.js-add-link'),
            $icon = $add_link.find(".icon16"),
            class_before = $icon.attr("class");

        $add_link.on('click', function () {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=contact&action=add",
                    data = {};

                $icon.attr("class", "icon16 loading");

                $.post(href, data, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                    $icon.attr("class", class_before);
                });
            }
        });
    };

    CRMContactsSidebar.prototype.initAddNewSegmentLink = function () {
        var that = this,
            $link = that.$add_new_segment_link;

        $link.click(function (e) {
            e.preventDefault();
            $.get($.crm.app_url + '?module=contactSegment&action=edit')
                .done(function (html) {
                    new CRMDialog({
                        html: html
                    });
                })
                .always(function () {
                    $link.find(".icon16").hide();
                });
        });
    };

    CRMContactsSidebar.prototype.updateItem = function(context, data) {
        var that = this;

        if (context.type === 'segment') {
            var $li = that.$segmentsLists.find('li[data-id="' + context.info.id + '"]');
            if (!$li.length) {
                return;
            }
            if (data.name !== undefined) {
                $li.find('.name').text(data.name || '');
            }
            if (data.count !== undefined) {
                $li.find('.count').text(data.count || '');
            }
        }
    };

    CRMContactsSidebar.prototype.initHighlight = function(context, data) {
        var $all_contats = $('.all-contacts-link');

        this.$wrapper.find('a[href^="'+$.crm.app_url+'"]').each(function(i, a) {
            if (location.pathname == a.pathname) {
                var $a = $(a);
                var $highlight = $a.closest('li');
                if (!$highlight.length) {
                    $highlight = $a;
                }
                $all_contats.removeClass('selected');
                $highlight.addClass('selected');
                return false;
            } else {
                $all_contats.addClass('selected');
            }
        });
    };

    CRMContactsSidebar.prototype.initSelectedLink = function() {
        var that = this;
        if (!sessionStorage.getItem("selected_contacts")) {
            sessionStorage.setItem("selected_contacts", "[]");
        }

        // DOM
        var $selected_link = this.$wrapper.find('.js-selected-contacts'),
            $selected_count = $selected_link.find('.count'),

        // VARS
            selected_contacts = sessionStorage.getItem("selected_contacts"),
            selected_count = JSON.parse(selected_contacts).length;

        if (selected_count) {
            $selected_count.text(selected_count);
            $selected_link.removeClass('js-selected-contacts-hidden');
        }

        $selected_link.on('click', function (e) {
            e.preventDefault();

            var href = $.crm.app_url + "?module=contact&action=selected",
                data = sessionStorage.getItem("selected_contacts");

            $.post(href, {selected_ids: data}, function(html) {
                if (html) {
                    history.pushState(null,null,$.crm.app_url + "contact/selected/");
                    $(document).find('#c-content-block').html(html);
                    that.initHighlight();
                }
            });
        })
    };

    CRMContactsSidebar.prototype.initArchivedToggles = function () {
        var that = this,
            $wrappers = that.$segmentsLists.find(".c-archived-section");

        $wrappers.each(initSection);

        function initSection() {
            var $wrapper = $(this);

            $wrapper.on("click", ".js-show-list", function() {
                toggleList(true);
            });

            $wrapper.on("click", ".js-hide-list", function() {
                toggleList(false);
            });

            function toggleList(show) {
                var active_class = "is-shown";

                if (show) {
                    $wrapper.addClass(active_class);
                } else {
                    $wrapper.removeClass(active_class);
                }
            }
        }
    };

    CRMContactsSidebar.prototype.initScrollTopOnClick = function() {
        var that = this;

        that.$wrapper.on("click", "a:not(.js-add-link)", function() {
            var $link = $(this),
                is_enabled = !$link.hasClass("js-stop-scroll");

            // Magic
            $link.find(".icon16").attr("class", "icon16 loading").attr("style", "");

            if (is_enabled) {
                $(window).scrollTop(0);
            }
        });
    };

    return CRMContactsSidebar;

})(jQuery);

CRMContactsSidebar.initCollapsibleSidebar = function() {
    var blocks = ["mylists-my","tags","vaults","responsibles","admin"];

    $.each(blocks, function(){
        var status = localStorage.getItem('collapsible_sidebar_'+this);
        if (status == 1) {
            $("#collapsible-"+this).find(".collapsible").css({"display":"none"});
            $("#collapsible-"+this).find(".icon16").removeClass("darr").addClass("rarr");
        }
    });

    $(".c-contacts-sidebar").on("click", ".collapse-handler", function () {
        var block_name = $(this).parents('.block').data('block'),
            block = $(this).parents('.block'),
            icon = $(block).find(".icon16"),
            collapsible = $(block).find(".collapsible");

        if (collapsible.is(':visible')) {
            $(collapsible).css({"display":"none"});
            $(icon).removeClass("darr").addClass("rarr");
            updateCollapsibleSidebar(block_name,1);
        } else {
            $(collapsible).css({"display":""});
            $(icon).removeClass("rarr").addClass("darr");
            updateCollapsibleSidebar(block_name,0);
        }
    });

    function updateCollapsibleSidebar(block_name,status) {
        localStorage.setItem('collapsible_sidebar_'+block_name, status);
    }
};
