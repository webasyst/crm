var CRMDealsFunnel = ( function($) {

    CRMDealsFunnel = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$header = that.$wrapper.find("#js-funnel-header");
        that.$actionsHeader = that.$wrapper.find("#js-actions-header");
        that.$tableContent = that.$wrapper.find(".js-table-content");

        // VARS
        that.funnel_id = options["funnel_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS
        that.selected_deals = [];

        // INIT
        that.initClass();
    };

    CRMDealsFunnel.prototype.initClass = function() {
        var that = this;
        //
        that.initFixedHeader();
        //
        that.initDealsMove();
        //
        that.initMassiveActionsHeader();
        //
        that.initMassActions();
    };

    CRMDealsFunnel.prototype.initFixedHeader = function() {
        var that = this,
            $window = $(window),
            $header = that.$header,
            $dummy = false,
            is_set = false;

        var active_class = "is-fixed-to-top";

        var header_o = $header.offset(),
            header_w = $header.width(),
            header_h = $header.height();

        $window.on("scroll", useWatcher);
        $window.on("resize", onResize);

        function useWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onScroll();
            } else {
                $window.off("scroll", useWatcher);
            }
        }

        function onScroll() {
            var scroll_top = $window.scrollTop();

            if (header_o.top < scroll_top) {
                if (!is_set) {
                    is_set = true;
                    $dummy = $("<div />");

                    $dummy.height(header_h).insertAfter($header);

                    $header
                        .width(header_w)
                        .addClass(active_class);
                }

            } else {
                clear();
            }
        }

        function onResize() {
            clear();
            $window.trigger("scroll");
        }

        function clear() {
            if ($dummy && $dummy.length) {
                $dummy.remove();
            }
            $dummy = false;

            $header
                .removeAttr("style")
                .removeClass(active_class);

            header_w = $header.width();
            header_h = $header.height();

            is_set = false;
        }

    };

    CRMDealsFunnel.prototype.initDealsMove = function() {
        var that = this,
            is_locked = false,
            $activeStage = false;

        var load_class = "is-loading",
            locked_class = "is-wrapper";

        var $deals = that.$wrapper.find(".c-deal-wrapper"),
            $stages = that.$wrapper.find(".js-drop-area");

        $deals.draggable({
            handle: ".js-drag-toggle",
            revert: true,
            start: function() {
                var $deal = $(this),
                    $stage = $deal.closest(".js-drop-area");
                $deal
                    .data("stage-index", $stage.index() )
                    .data("stage-id", $stage.data("id") )
                    .draggable( "option", "revert", true);

                $activeStage = $stage.addClass(locked_class);
            }
        });

        $stages.droppable({
            tolerance: "pointer",
            drop: function(event, ui) {
                var $stage = $(this),
                    $deal = $(ui.draggable),
                    stage_id = $stage.data("id"),
                    deal_stage_id = $deal.data("stage-id"),
                    deal_id = $deal.data("id");

                if (deal_stage_id !== stage_id) {
                    $deal.draggable("option", "revert", false);
                    move(deal_id, stage_id, $deal, $stage);
                }

                if ($activeStage.length) {
                    $activeStage.removeClass(locked_class);
                    $activeStage = false;
                }
            }
        });

        function move(deal_id, stage_id, $deal, $stage) {
            if (!is_locked) {
                is_locked = true;

                var $stageBefore = $deal.closest(".js-drop-area");

                $deals.draggable("disable");

                $deal
                    .addClass(load_class)
                    .removeAttr("style")
                    .prependTo($stage);

                var href = "?module=deal&action=move",
                    data = {
                        deal_id: deal_id,
                        stage_id: stage_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        if (response.data["dialog_html"] && response.data["dialog_html"]["html"]) {

                            new CRMDialog({
                                html: response.data["dialog_html"]["html"],
                                options: {
                                    onShopSubmit: refreshDeal
                                },
                                onCancel: function() {
                                    $deal
                                        .removeClass(load_class)
                                        .prependTo($stageBefore);
                                },
                                onConfirm: function() {
                                    var data = {
                                        deal_id: deal_id,
                                        stage_id: stage_id,
                                        force_execute: 1
                                    };

                                    $.post(href, data, refreshDeal);
                                }
                            });

                        } else {
                            refreshDeal();
                        }
                    }
                }, "json").always( function() {
                    $deals.draggable("enable");
                    is_locked = false;
                });
            }

            function refreshDeal() {
                $.crm.content.reload();
            }
        }
    };

    CRMDealsFunnel.prototype.initMassiveActionsHeader = function() {
        var that = this,
            $window = $(window),
            $header = that.$actionsHeader,
            $dummy = false,
            is_set = false;

        var active_class = "is-fixed-to-bottom",
            hidden_class = "is-hidden";

        var header_o = $header.offset(),
            header_w = $header.width(),
            header_h = $header.height();

        $window.on("scroll", useWatcher);
        $window.on("resize", onResize);

        function useWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                if (that.selected_deals.length) {
                    if ($header.hasClass(hidden_class)) {
                        $header.removeClass(hidden_class);
                    }

                    onScroll();
                } else {
                    clear();

                    if (!$header.hasClass(hidden_class)) {
                        $header.addClass(hidden_class);
                    }
                }
            } else {
                $window.off("scroll", useWatcher);
            }
        }

        function onScroll() {
            var scroll_top = $window.scrollTop(),
                use_scroll = header_o.top + header_h > scroll_top + $window.height();

            if (use_scroll) {
                if (!is_set) {
                    is_set = true;
                    $dummy = $("<div />");

                    $dummy.height(header_h).insertAfter($header);

                    $header
                        .width(header_w)
                        .addClass(active_class);
                }

            } else {
                clear();
            }
        }

        function onResize() {
            clear();
            $window.trigger("scroll");
        }

        function clear() {
            if ($dummy && $dummy.length) {
                $dummy.remove();
            }
            $dummy = false;

            $header
                .removeAttr("style")
                .removeClass(active_class);

            header_w = $header.width();
            header_h = $header.height();

            is_set = false;
        }
    };

    CRMDealsFunnel.prototype.initMassActions = function() {
        var that = this;

        // DOM
        var $header = that.$actionsHeader,
            $checkAll = $header.find(".js-checkbox-all");

        // VARS
        var active_class = "is-active";

        // DYNAMIC VARS
        var $activeDeals = that.selected_deals;

        // EVENTS

        // click on deal item
        that.$wrapper.on("click", ".js-deal-wrapper", function(event) {
            var $target = $(event.target),
                is_link = !!($target.is('a') || $target.closest("a").length),
                is_checkbox = $target.is(':checkbox');
            if (is_link) {
                return;
            }
            var $deal = $(this),
                $checkbox = $deal.find('.js-check-deal');
            if (!is_checkbox) {
                $checkbox.attr('checked', !$checkbox.is(':checked'));
            }
            dealItemHandle($deal);
        });

        // shift-select on deal items
        that.$tableContent.find('.js-state-column')
            .shiftSelectable({
                selector: '.js-deal-wrapper',
                onSelect: function ($deal, event) {
                    var $target = $(event.target),
                        is_link = !!($target.is('a') || $target.closest("a").length),
                        is_first = event.extra.is_first,
                        is_last = event.extra.is_last;
                    if (is_link || is_first || is_last) {
                        return;
                    }
                    $deal.find('.js-check-deal').attr('checked', true);
                    dealItemHandle($deal);
                }
            });

        // check all logic
        $checkAll.on("change", function() {
            var is_all_checked = $(this).is(':checked'),
                $deals = that.$wrapper.find(".js-deal-wrapper");
            $deals.each( function() {
                var $deal = $(this),
                    $checkbox = $deal.find('.js-check-deal'),
                    is_checked = $checkbox.is(':checked');
                if (is_checked !== is_all_checked) {
                    $checkbox.attr("checked", is_all_checked);
                    dealItemHandle($deal);
                }
            });
        });

        // FUNCTIONS

        function dealItemHandle($deal) {
            var $checkbox = $deal.find('.js-check-deal'),
                checked = $checkbox.is(':checked');
            if (checked) {
                $deal.addClass(active_class);
                $activeDeals.push($deal[0]);
            } else {
                $deal.removeClass(active_class);
                var index = $activeDeals.indexOf($deal[0]);
                $activeDeals.splice(index, 1);
            }

            watcher();

            // for initMassiveActionsHeader
            $(window).trigger("scroll");
        }

        function watcher() {
            var count = $activeDeals.length;
            if ($activeDeals.length > 0) {
                $header.find(".js-count").text(count);

                showOpen();
                showMerge();
                showDelete();
            }

            function showOpen() {
                var open_deals_ids = getDealsIds("open"),
                    $open = $header.find(".js-deals-close").closest(".c-action"),
                    $openCount = $open.find(".js-open-count");

                if (open_deals_ids.length) {
                    $openCount.text(open_deals_ids.length);
                    $open.show();
                } else {
                    $open.hide();
                }
            }

            function showMerge() {
                var $merge = $header.find(".js-deals-merge").closest(".c-action");
                if ($activeDeals.length > 1) {
                    $merge.show();
                } else {
                    $merge.hide();
                }
            }

            function showDelete() {
                var can_delete = false;
                $($activeDeals).each(function () {
                    if ($(this).data('can-delete')) {
                        can_delete = true;
                        return false; // break
                    }
                });
                var $delete = $header.find('.js-deals-delete').closest('.c-action')
                if (can_delete) {
                    $delete.show();
                } else {
                    $delete.hide();
                }
            }
        }

        function getDealsIds(filter_name) {
            var ids = [];

            $.each($activeDeals, function(index, deal) {
                var $deal = $(deal),
                    id = $deal.data("id");

                if (filter_name) {
                    var filter = $deal.data(filter_name);
                    if (!filter) {
                        return;
                    }
                }

                if (id) {
                    ids.push(id);
                }
            });

            return ids;
        }

        // MERGE

        $header.on("click", ".js-deals-merge", goToMerge);

        function goToMerge(event) {
            event.preventDefault();

            var ids = getDealsIds(),
                content_uri = $.crm.app_url + "deal/merge/?ids=" + ids.join(",");

            $.crm.content.load(content_uri);
        }

        // DELETE

        initDelete();

        function initDelete() {
            var is_delete_locked = false;

            $header.on("click", ".js-deals-delete", deleteDeals);

            function deleteDeals(event) {
                event.preventDefault();

                $.crm.confirm.show({
                    title: that.locales["delete_confirm_title"].replace("%s", $activeDeals.length),
                    text: that.locales["delete_confirm_text"],
                    button: that.locales["delete_confirm_button"],
                    onConfirm: onConfirm
                });

                function onConfirm(dialog) {
                    if (!is_delete_locked) {
                        is_delete_locked = true;

                        var href = $.crm.app_url + "?module=deal&action=delete",
                            data = getData();

                        $.post(href, data, function(response) {
                            if (response.status === "ok") {
                                removeDeals();
                                dialog.close();
                                $.crm.content.reload();
                                $.crm.sidebar.reload();
                            }
                        }).always( function() {
                            is_delete_locked = false;
                        });
                    }
                    return false;

                    function getData() {
                        var result = [],
                            ids = getDealsIds();

                        $.each(ids, function(index, id) {
                            result.push({
                                name: "id[]",
                                value: id
                            });
                        });

                        return result;
                    }

                    function removeDeals() {
                        var deals = $activeDeals.map( function(deal) {
                            return deal;
                        });

                        $.each(deals, function(index, deal) {
                            var $deal = $(deal);
                            $deal.find(".js-check-deal").attr("checked", false).trigger("change");
                            $deal.remove();
                        });
                    }
                }
            }
        }

        // CHANGE RESPONSIBLE

        initChangeResponsible();

        function initChangeResponsible() {
            var is_change_res_locked = false;

            $header.on("click", ".js-deals-change-responsible", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_change_res_locked) {
                    is_change_res_locked = true;

                    var href = "?module=deal&action=changeResponsible",
                        ids = getDealsIds(),
                        data = {
                            ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {
                        new CRMDialog({
                            html: html
                        });
                    }).always( function() {
                        is_change_res_locked = false;
                    });
                }
            }
        }

        // CLOSE

        initCloseDeal();

        function initCloseDeal() {
            var is_locked = false;

            $header.on("click", ".js-deals-close", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=deal&action=massClose",
                        ids = getDealsIds("open"),
                        data = {
                            ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {

                        new CRMDialog({
                            html: html
                        });

                    }).always( function() {
                        is_locked = false;
                    })
                }
            }
        }

        // CHANGE FUNNEL

        initChangeFunnel();

        function initChangeFunnel() {
            var is_locked = false;

            $header.on("click", ".js-deals-change-funnel", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=deal&action=changeFunnel",
                        ids = getDealsIds(),
                        data = {
                            deal_ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {
                        new CRMDialog({
                            html: html
                        })
                    }).always( function() {
                        is_locked = false;
                    });
                }
            }
        }

        // init export

        initExport();

        function initExport() {
            var is_locked = false;

            $header.on("click", ".js-deals-export", exportDeals);

            function exportDeals(event) {
                event.preventDefault();

                if (is_locked) {
                    return;
                }

                is_locked = true;

                var ids = getDealsIds(),
                    url = $.crm.app_url + "?module=deal&action=export";
                $.post(url, { ids: ids }, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always(function () {
                    is_locked = false;
                });
            }
        }

        // init tags

        initTags();

        function initTags() {
            var is_locked = false;

            $header.on("click", ".js-deals-set-tags", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (is_locked) {
                    return;
                }

                is_locked = true;

                var deal_ids = getDealsIds(),
                    url = $.crm.app_url + "?module=dealOperation&action=assignTags",
                    data = {
                        deal_ids: deal_ids
                    };

                if (deal_ids.length === 1) {
                    data.is_assign = 1;
                }

                $.post(url, data, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    return CRMDealsFunnel;

})(jQuery);

var CRMDealsList = ( function($) {

    CRMDealsList = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$tableWrapper = that.$wrapper.find(".js-deals-table-wrapper");
        that.$tableHeaderW = that.$wrapper.find(".js-table-header-wrapper");
        that.$tableHeader = that.$wrapper.find(".js-table-header");
        that.$tableActions = that.$wrapper.find(".js-table-actions");
        that.$tableBody = that.$wrapper.find("#c-table-body");

        // VARS
        that.funnel_id = options["funnel_id"];
        that.stage_id = options["stage_id"];
        that.contact_id = options["contact_id"];
        that.offset = options["offset"];
        that.locales = options["locales"];
        that.limit = options["limit"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealsList.prototype.initClass = function() {
        var that = this;
        //
        that.initLazy();
        //
        that.initElasticHeader();
        //
        that.initMassActions();
    };

    CRMDealsList.prototype.initLazy = function() {
        var that = this;

        var $window = $(window),
            $table = that.$tableBody,
            $loader = that.$wrapper.find(".js-lazyload"),
            is_locked = false;

        if ($loader.length) {
            $window.on("scroll", use);

            if ($loader.offset().top < $window.height()) {
                $window.trigger("scroll");
            }
        }

        function use() {
            var is_exist = $.contains(document, $loader[0]);
            if (is_exist) {
                onScroll();
            } else {
                $window.off("scroll", use);
            }
        }

        function onScroll() {
            var scroll_top = $(window).scrollTop(),
                display_h = $window.height(),
                loader_top = $loader.offset().top;

            if (scroll_top + display_h >= loader_top ) {
                if (!is_locked) {
                    load();
                }
            }
        }

        function load() {
            var href = "?module=deal&action=list",
                data = {
                    offset: $loader.data("offset")
                };

            if (that.stage_id) {
                data.stage = that.stage_id;
            }

            if (that.contact_id) {
                data.user = that.contact_id;
            }

            is_locked = true;

            $.post(href, data, function(html) {
                var $newLoading = $(html).find(".js-lazyload");

                if ($newLoading.length) {
                    that.limit += data.offset;
                    $newLoading.insertAfter($loader);
                    $loader.remove();
                    $loader = $newLoading;
                } else {
                    $loader.remove();
                }

                $(html).find(".c-deal-wrapper").insertAfter($table.find("tr:last"));
            }).always( function() {
                is_locked = false;
            });
        }
    };

    CRMDealsList.prototype.initElasticHeader = function() {
        var that = this;

        // DOM
        var $window = $(window),
            $wrapper = that.$tableWrapper,
            $header = that.$tableHeaderW;

        // VARS
        var wrapper_offset = $wrapper.offset(),
            fixed_class = "is-fixed";

        // DYNAMIC VARS
        var is_fixed = false;

        // INIT

        $window.on("scroll", scrollWatcher);

        function scrollWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onScroll( $window.scrollTop() );
            } else {
                $window.off("scroll", scrollWatcher);
            }
        }

        function onScroll(scroll_top) {
            var set_fixed = ( scroll_top > wrapper_offset.top );
            if (set_fixed) {

                $header
                    .css({
                        left: wrapper_offset.left
                    })
                    .width( $wrapper.outerWidth() )
                    .addClass(fixed_class);

                is_fixed = true;

            } else {

                $header
                    .removeAttr("style")
                    .removeClass(fixed_class);

                is_fixed = false;
            }
        }
    };

    CRMDealsList.prototype.initMassActions = function() {
        var that = this,
            $activeDeals = [],
            active_class = "is-active";

        var $header = that.$tableHeader,
            $actions = that.$tableActions;

        var $checkAll = that.$tableHeaderW.find(".js-checkbox-all");

        // click on deal item
        that.$tableBody.on("click", ".js-deal-wrapper", function(event) {
            var $target = $(event.target),
                $deal = $(this),
                $checkbox = $deal.find('.js-check-deal'),
                $checkbox_wrapper = $checkbox.closest("td");

            var is_link = !!($target.is('a') || $target.closest("a").length),
                is_checkbox_wrapper = !!($target.is('.c-column-checkbox') || $target.closest(".c-column-checkbox").length),
                is_checkbox = $target.is(':checkbox');

            if (is_checkbox) {
                dealItemHandle($deal);
                return true;

            } else if (is_link) {
                return true;

            } else if (is_checkbox_wrapper) {
                $checkbox.attr('checked', !$checkbox.is(':checked'));
                dealItemHandle($deal);
            }
        });

        that.$tableBody.shiftSelectable({
            selector: '.js-deal-wrapper',
            onSelect: function ($deal, event) {
                var $target = $(event.target),
                    is_link = !!($target.is('a') || $target.closest("a").length),
                    is_first = event.extra.is_first,
                    is_last = event.extra.is_last;
                if (is_link || is_first || is_last) {
                    return;
                }
                $deal.find('.js-check-deal').attr('checked', true);
                dealItemHandle($deal);
            }
        });

        // check all logic
        $checkAll.on("change", function() {

            var $el = $(this),
                is_all_checked = $el.is(':checked'),
                $deals = that.$wrapper.find(".js-deal-wrapper"),
                checked_count = $deals.find('.js-check-deal').filter(':checked').length;

            if (checked_count > 0) {
                if(checked_count < that.limit || checked_count === that.limit) {
                    unselectAll($deals);
                }else{
                    selectAll($deals);
                }
            }else{
                if (is_all_checked) {
                    selectAll($deals);
                } else {
                    unselectAll($deals);
                }
            }

            $deals.each( function() {
                dealItemHandle($(this));
            });
        });

        function selectAll($deals) {
            $deals
                .find('.js-check-deal')
                .prop('checked', true)
                .trigger("change", [true]);
        }

        function unselectAll($deals) {
            $deals
                .find('.js-check-deal')
                .prop('checked', false)
                .trigger("change", [false]);
        }

        function dealItemHandle($deal) {
            var $checkbox = $deal.find('.js-check-deal'),
                checked = $checkbox.is(':checked');
            if (checked) {
                $deal.addClass(active_class);
                $activeDeals.push($deal[0]);
            } else {
                $deal.removeClass(active_class);
                var index = $activeDeals.indexOf($deal[0]);
                $activeDeals.splice(index, 1);
            }
            watcher();
        }

        function watcher() {
            var count = $activeDeals.length;
            if (count > 0) {
                $header.hide();
                $actions
                    .show()
                    .find(".js-count").text(count);

                showOpen();
                showMerge();
                showDelete();

                if(count < that.limit) {
                    $checkAll.prop('indeterminate', true);
                }else{
                    $checkAll.prop('indeterminate', false).prop('checked', true);
                }

            } else {
                $header.show();
                $actions.hide();
                $checkAll.prop('indeterminate', false).prop('checked', false);
            }

            function showOpen() {
                var open_deals_ids = getDealsIds("open"),
                    $open = $actions.find(".js-deals-close").closest(".c-operation-li"),
                    $openCount = $open.find(".js-open-count");

                if (open_deals_ids.length) {
                    $openCount.text(open_deals_ids.length);
                    $open.show();
                } else {
                    $open.hide();
                }
            }

            function showMerge() {
                var $merge = $actions.find(".js-deals-merge").closest(".c-operation-li");
                if ($activeDeals.length > 1) {
                    $merge.show();
                } else {
                    $merge.hide();
                }
            }

            function showDelete() {
                var can_delete = false;
                $($activeDeals).each(function () {
                    if ($(this).data('can-delete')) {
                        can_delete = true;
                        return false; // break
                    }
                });
                var $delete = $actions.find('.js-deals-delete').closest('.c-operation-li')
                if (can_delete) {
                    $delete.show();
                } else {
                    $delete.hide();
                }
            }
        }

        function getDealsIds(filter_name) {
            var ids = [];

            $.each($activeDeals, function(index, deal) {
                var $deal = $(deal),
                    id = $deal.data("id");

                if (filter_name) {
                    var filter = $deal.data(filter_name);
                    if (!filter) {
                        return;
                    }
                }

                if (id) {
                    ids.push(id);
                }
            });

            return ids;
        }

        // MERGE

        $actions.on("click", ".js-deals-merge", goToMerge);

        function goToMerge(event) {
            event.preventDefault();

            var ids = getDealsIds(),
                content_uri = $.crm.app_url + "deal/merge/?ids=" + ids.join(",");

            $.crm.content.load(content_uri);
        }

        // DELETE

        initDelete();

        function initDelete() {
            var is_delete_locked = false;

            $actions.on("click", ".js-deals-delete", deleteDeals);

            function deleteDeals(event) {
                event.preventDefault();

                $.crm.confirm.show({
                    title: that.locales["delete_confirm_title"].replace("%s", $activeDeals.length),
                    text: that.locales["delete_confirm_text"],
                    button: that.locales["delete_confirm_button"],
                    onConfirm: onConfirm
                });

                function onConfirm(dialog) {
                    if (!is_delete_locked) {
                        is_delete_locked = true;

                        var href = $.crm.app_url + "?module=deal&action=delete",
                            data = getData();

                        $.post(href, data, function(response) {
                            if (response.status === "ok") {
                                removeDeals();
                                dialog.close();
                                $header.show();
                                $actions.hide();
                                $checkAll.prop('indeterminate', false).prop('checked', false);
                                $.crm.content.reload();
                                $.crm.sidebar.reload();
                            }
                        }).always( function() {
                            is_delete_locked = false;
                        });
                    }
                    return false;

                    function getData() {
                        var result = [],
                            ids = getDealsIds();

                        $.each(ids, function(index, id) {
                            result.push({
                                name: "id[]",
                                value: id
                            });
                        });

                        return result;
                    }

                    function removeDeals() {
                        var deals = $activeDeals.map( function(deal) {
                            return deal;
                        });

                        $.each(deals, function(index, deal) {
                            var $deal = $(deal);
                            $deal.find(".js-check-deal").attr("checked", false).trigger("change");
                            $deal.remove();
                        });
                    }
                }
            }
        }

        // CHANGE RESPONSIBLE

        initChangeResponsible();

        function initChangeResponsible() {
            var is_change_res_locked = false;

            $actions.on("click", ".js-deals-change-responsible", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_change_res_locked) {
                    is_change_res_locked = true;

                    var href = "?module=deal&action=changeResponsible",
                        ids = getDealsIds(),
                        data = {
                            ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {
                        new CRMDialog({
                            html: html
                        });
                    }).always( function() {
                        is_change_res_locked = false;
                    });
                }
            }
        }

        // CLOSE

        initCloseDeal();

        function initCloseDeal() {
            var is_locked = false;

            $actions.on("click", ".js-deals-close", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=deal&action=massClose",
                        ids = getDealsIds("open"),
                        data = {
                            ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {

                        new CRMDialog({
                            html: html
                        });

                    }).always( function() {
                        is_locked = false;
                    })
                }
            }
        }

        // CHANGE FUNNEL

        initChangeFunnel();

        function initChangeFunnel() {
            var is_locked = false;

            $actions.on("click", ".js-deals-change-funnel", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=deal&action=changeFunnel",
                        ids = getDealsIds(),
                        data = {
                            deal_ids: ids.join(",")
                        };

                    $.post(href, data, function(html) {
                        new CRMDialog({
                            html: html
                        })
                    }).always( function() {
                        is_locked = false;
                    });
                }
            }
        }

        // init export
        initExport();

        function initExport() {
            var is_locked = false;

            $actions.on("click", ".js-deals-export", exportDeals);

            function exportDeals(event) {
                event.preventDefault();

                if (is_locked) {
                    return;
                }

                is_locked = true;

                var ids = getDealsIds(),
                    url = $.crm.app_url + "?module=deal&action=export";
                $.post(url, { ids: ids }, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always(function () {
                    is_locked = false;
                });
            }
        }

        // init tags
        initTags();

        function initTags() {
            var is_locked = false;

            $actions.on("click", ".js-deals-set-tags", showDialog);

            function showDialog(event) {
                event.preventDefault();

                if (is_locked) {
                    return;
                }

                is_locked = true;

                var deal_ids = getDealsIds(),
                    url = $.crm.app_url + "?module=dealOperation&action=assignTags",
                    data = {
                        deal_ids: deal_ids
                    };

                if (deal_ids.length === 1) {
                    data.is_assign = 1;
                }

                $.post(url, data, function (html) {
                    new CRMDialog({
                        html: html
                    });
                }).always(function () {
                    is_locked = false;
                });
            }
        }

    };

    return CRMDealsList;

})(jQuery);

var CRMDealsMergePage = ( function($) {

    CRMDealsMergePage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$submitButton = that.$wrapper.find(".js-submit-button");

        // VARS

        // DYNAMIC VARS
        that.is_deal_set = false;
        that.$activeDeal = false;

        // INIT
        that.initClass();
    };

    CRMDealsMergePage.prototype.initClass = function() {
        var that = this;
        //
        that.initSubmit();
        //
        that.initSelectDeal();
    };

    CRMDealsMergePage.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=mergeRun",
                    data = $form.serializeArray();

                $.post(href, data, function(response) {
                    var content_uri = $.crm.app_url + "deal/" + getDealId(data) + "/";
                    let iframe = new URLSearchParams(document.location.search).get('iframe');
                    if (window.parent && iframe) {
                        window.parent.location = content_uri;
                    } else {
                        $.crm.content.load(content_uri);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }

            function getDealId(data) {
                var result = "";

                $.each(data, function(index, item) {
                    if (item.name === "master_id") {
                        result = item.value;
                        return false;
                    }
                });

                return result;
            }
        }

    };

    CRMDealsMergePage.prototype.initSelectDeal = function() {
        var that = this;

        that.$wrapper.on("change", ".js-field", function() {
            var $field = $(this),
                is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                selectDeal( $field.closest(".js-deal") );
            }
        });

        that.$wrapper.on("click", ".js-deal", function(event) {
            var $input = $(this).find(".js-field"),
                is_input = ($input[0] == event.target),
                is_link = !!$(event.target).attr("href");

            if (!is_input && !is_link) {
                $input.trigger("click");
            }
        });

        function selectDeal( $deal ) {
            var active_class = "is-active";

            if (that.$activeDeal) {
                that.$activeDeal.removeClass(active_class);
            }

            that.$activeDeal = $deal.addClass(active_class);

            if (!that.is_deal_set) {
                that.$submitButton.attr("disabled", false);
                that.is_deal_set = true;
            }
        }
    };

    return CRMDealsMergePage;

})(jQuery);

var CRMDealsChangeResponsibleDialog = ( function($) {

    CRMDealsChangeResponsibleDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$submitButton = that.$wrapper.find(".js-submit-button");

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS
        that.is_responsive_set = false;

        // INIT
        that.initClass();
    };

    CRMDealsChangeResponsibleDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initSubmit();
        //
        that.initChangeResponsible();
    };

    CRMDealsChangeResponsibleDialog.prototype.initChangeResponsible = function() {
        var that = this;

        var $wrapper = that.$wrapper.find(".js-users-list"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = that.$wrapper.find(".js-user-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this),
                $user = $link.closest(".js-user");

            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $user.addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $user.data("id");

            $field.val(id).trigger("change");

            if (!that.is_responsive_set) {
                that.$submitButton.attr("disabled", false);
                that.is_responsive_set = true;
            }
        });
    };

    CRMDealsChangeResponsibleDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=changeResponsibleRun",
                    data = $form.serializeArray();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.close();
                        $.crm.content.reload();
                    } else {
                        alert("errors");
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMDealsChangeResponsibleDialog;

})(jQuery);

var CRMDealsCloseDialog = ( function($) {

    CRMDealsCloseDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$reasonSelect = that.$wrapper.find(".js-reason-select");
        that.$reasonFieldW = that.$wrapper.find(".js-reason-text");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.deal_ids = options["deal_ids"];
        that.reason_require = options["reason_require"];

        // DYNAMIC VARS
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    CRMDealsCloseDialog.prototype.initClass = function() {
        var that = this;

        that.$wrapper.on("click", ".js-set-won", function(event) {
            event.preventDefault();
            that.setWon();
        });

        that.$wrapper.on("click", ".js-show-form", function(event) {
            event.preventDefault();
            that.showLostForm();
        });

        that.$wrapper.on("click", ".js-set-lost", function(event) {
            event.preventDefault();
            that.$form.trigger("submit");
        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            that.setLost();
        });

        that.$reasonSelect.on("change", function() {
            var value = $(this).val();
            if (value) {
                that.$reasonFieldW.hide();
            } else {
                that.$reasonFieldW.show();
            }
        });
    };

    CRMDealsCloseDialog.prototype.showLostForm = function(show) {
        var that = this,
            $visible = that.$wrapper.find(".c-visible"),
            $hidden = that.$wrapper.find(".c-hidden");

        if (show) {
            $visible.show();
            $hidden.hide();
        } else {
            $visible.hide();
            $hidden.show();
        }

        that.dialog.resize();
    };

    CRMDealsCloseDialog.prototype.setWon = function() {
        var that = this;

        if (!that.is_locked) {
            that.is_locked = true;

            var href = "?module=deal&action=massCloseRun",
                data = {
                    deal_ids: that.deal_ids.join(","),
                    action: "WON"
                };

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    that.dialog.close();
                    $.crm.content.reload();
                }
            }).always( function() {
                that.is_locked = false;
            })
        }
    };

    CRMDealsCloseDialog.prototype.setLost = function() {
        var that = this;

        if (!that.is_locked) {
            that.is_locked = true;

            var href = "?module=deal&action=massCloseRun",
                data = getData();

            if (data) {
                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        that.dialog.close();
                        $.crm.content.reload();
                    }
                }).always( function() {
                    that.is_locked = false;
                });
            } else {
                that.is_locked = false;
            }
        }

        function getData() {
            var result = that.$form.serializeArray();

            result.push({
                "name": "deal_ids",
                "value": that.deal_ids.join(",")
            });

            result.push({
                "name": "action",
                "value": "LOST"
            });

            if (that.reason_require) {
                var value = that.$reasonSelect.val();

                if (!value) {
                    var error_class = "error";

                    if (that.$reasonFieldW.is(":visible")) {
                        var $input = that.$reasonFieldW.find("input"),
                            input_value = $input.val();

                        if (!input_value) {
                            $input.addClass(error_class).one("focus", function() {
                                $(this).removeClass(error_class);
                            });

                            result = false;
                        }
                    } else {
                        that.$reasonSelect.addClass(error_class).one("change", function() {
                            $(this).removeClass(error_class);
                        });

                        result = false;
                    }
                }
            }

            return result;
        }
    };

    return CRMDealsCloseDialog;

})(jQuery);

var CRMDealsChangeFunnelDialog = ( function($) {

    CRMDealsChangeFunnelDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.funnels = options["funnels"];
        that.stage_template_html = options["stage_template_html"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealsChangeFunnelDialog.prototype.initClass = function() {
        var that = this;

        that.initChangeFunnel();

        that.initChangeStage();

        that.initSubmit();

        $.crm.renderSVG(that.$wrapper);
    };

    CRMDealsChangeFunnelDialog.prototype.initChangeFunnel = function() {
        var that = this;

        var $wrapper = that.$wrapper.find(".js-funnels-list"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id).trigger("change");

            loadStages(id);
        });

        function loadStages(id) {
            var funnel = ( that.funnels[id] || false );
            if (funnel) {
                $wrapper.trigger("changeFunnel", funnel);
            }
        }
    };

    CRMDealsChangeFunnelDialog.prototype.initChangeStage = function() {
        var that = this;

        var $funnelWrapper = that.$wrapper.find(".js-funnels-list"),
            $wrapper = that.$wrapper.find(".js-funnel-stages-list"),
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id);
        });

        $funnelWrapper.on("changeFunnel", function(event, funnel) {
            renderStages(funnel.stages);
        });

        function renderStages(stages) {
            $menu.html("");

            $.each(stages, function(index, stage) {
                var stage_template = that.stage_template_html;
                var name = $("<div />").text(stage.name).html();

                stage_template = stage_template
                    .replace("%id%", stage.id)
                    .replace("%color%", stage.color)
                    .replace("%name%", name);

                var $stage = $(stage_template);

                $menu.append($stage);
            });

            $.crm.renderSVG($wrapper);

            $menu.find("li:first-child a").trigger("click");
        }
    };

    CRMDealsChangeFunnelDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;
                var href = "?module=deal&action=changeFunnelRun",
                    data = $form.serializeArray();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.close();
                        $.crm.content.reload();
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMDealsChangeFunnelDialog;

})(jQuery);

/**
 * Class for reminders filter at DealFunnel.html header
 * */
var CRMDealRemindersFilter = ( function($) {

    CRMDealRemindersFilter = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$menu = that.$wrapper.find(".js-hidden-list");
        that.$buttons = that.$menu.find(".js-buttons");

        // VARS
        that.apply_url_pattern = options["redirect_uri"];
        that.filters = options["filters"];

        // DYNAMIC VARS
        that.selected_filters = options["selected_filters"];
        that.redirect_uri = that.apply_url_pattern;

        // OTHER
        that.selected_ids_before = Object.keys(that.selected_filters);

        // INIT
        that.initClass();
    };

    CRMDealRemindersFilter.prototype.initClass = function() {
        var that = this;

        that.$menu.on("change", ".js-checkbox", function() {
            that.setItem( $(this) );
        });

        that.$menu.on("click", ".js-show-all", function(event) {
            event.preventDefault();
            that.$menu.find(".js-checkbox").attr("checked", false).trigger("change");
            that.update();
        });

        that.$menu.on("click", ".js-revert", function(event) {
            event.preventDefault();

            that.$menu.find(".js-checkbox").each( function() {
                var $field = $(this),
                    $li = $field.closest(".c-item"),
                    id = $li.data("id");

                console.log( id, (that.selected_ids_before.indexOf(id) >= 0) );

                $field.attr("checked", (that.selected_ids_before.indexOf(id) >= 0)).trigger("change");
            });
        });

        that.$menu.on("click", ".js-submit", function(event) {
            event.preventDefault();
            that.update();
        });
    };

    CRMDealRemindersFilter.prototype.setItem = function($field) {
        var that = this;

        var $li = $field.closest(".c-item");

        var id = $li.data("id"),
            active_class = "selected",
            is_active = !!that.selected_filters[id],
            is_selected = $field.is(":checked");

        if (is_selected) {
            $li.addClass(active_class);
        } else {
            $li.removeClass(active_class);
        }

        if (is_selected) {
            if (!is_active) {
                that.selected_filters[id] = that.filters[id];
            }
        } else {
            if (is_active) {
                delete that.selected_filters[id];
            }
        }

        that.setApplyUrl();
        that.watcher();
    };

    CRMDealRemindersFilter.prototype.watcher = function() {
        var that = this,
            selected_ids_array = Object.keys(that.selected_filters);

        var is_changed = isChanged(selected_ids_array, that.selected_ids_before);
        if (is_changed) {
            that.$buttons.show();
        } else {
            that.$buttons.hide();
        }

        /**
         * @param {Array} ids_array
         * @param {Array} target_ids_array
         * @return {Boolean}
         * */
        function isChanged(ids_array, target_ids_array) {
            var result = true;
            if (ids_array.length === target_ids_array.length) {
                result = false;

                $.each(target_ids_array, function(index, id) {
                    var in_array = (ids_array.indexOf(id) >= 0);
                    if (!in_array) {
                        result = true;
                        return false;
                    } else {
                        result = false;
                    }
                });
            }

            return result;
        }
    };

    CRMDealRemindersFilter.prototype.setApplyUrl = function() {
        var that = this,
            ids = Object.keys(that.selected_filters);

        that.redirect_uri = that.apply_url_pattern.replace("reminder=none", (ids.length ? "reminder=" + ids.join("-") : "reminder=none"));
    };

    CRMDealRemindersFilter.prototype.update = function() {
        var that = this;

        $.crm.content.load(that.redirect_uri);
    };

    return CRMDealRemindersFilter;

})(jQuery);
