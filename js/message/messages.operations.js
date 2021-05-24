var CRMPageMessagesOperations = (function ($) {

    CRMPageMessagesOperations = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$bar = that.$wrapper.find(".js-operations-wrapper");
        that.$list = that.$wrapper.find('#js-messages-table');
        that.$list_header = that.$list.find('.js-list-header');
        that.$checkbox_all = that.$wrapper.find('.js-checkbox-all');
        that.$content = that.$wrapper.find(".js-messages-page");

        // VARS
        that.page = options.page || 1;
        that.limit = options.limit || 30;
        that.total_count = options.total_count || 0;
        that.total_items_at_page_count = that.getTotalItemsAtPage() || 0;
        that.view = options.view || 'all';
        that.locales = options.locales || [];

        // DYNAMIC VARS
        that.checked_count = 0;

        that.read_list = [];
        that.unread_list = [];

        that.read_counter = 0;
        that.unread_counter = 0;

        that.deal_list = [];
        that.deal_counter = 0;

        that.no_deal_list = [];
        that.no_deal_counter = 0;

        that.is_shown = that.checked_count > 0;
        that.is_checked_all = false;

        // INIT
        that.initClass();
    };

    CRMPageMessagesOperations.prototype.initClass = function () {
        var that = this;
        //
        that.initCheckboxAll();
        //
        that.initOperations();
        //
        that.initItemCheckbox();

        that.initElasticHeader();
    };

    CRMPageMessagesOperations.prototype.initElasticHeader = function() {
        var that = this;

        // DOM
        var $window = $(window),
            $wrapper = that.$wrapper.find(".js-messages-section"),
            $header = that.$bar;

        // VARS
        var wrapper_offset = $wrapper.offset(),
            header_w = $header.outerWidth(),
            fixed_class = "is-fixed";

        // DYNAMIC VARS
        var is_fixed = false;

        // INIT

        $window
            .on("scroll", scrollWatcher)
            .on("resize", resizeWatcher);

        function scrollWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onScroll( $window.scrollTop() );
            } else {
                $window.off("scroll", scrollWatcher);
            }
        }

        function resizeWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onResize();
            } else {
                $window.off("resize", resizeWatcher);
            }
        }

        function onScroll(scroll_top) {
            var set_fixed = ( scroll_top > wrapper_offset.top );

            if (set_fixed) {

                $header
                    .addClass(fixed_class)
                    .css({
                        left: wrapper_offset.left,
                        width: header_w
                    });

                is_fixed = true;

            } else {

                $header
                    .removeClass(fixed_class)
                    .removeAttr("style");

                is_fixed = false;
            }
        }

        function onResize() {
            header_w = $wrapper.outerWidth();

            if (is_fixed) {
                $header.width(header_w);
            }
        }
    };

    CRMPageMessagesOperations.prototype.initCheckboxAll = function () {
        var that = this;

        that.$checkbox_all.click(function () {
            var $el = $(this),
                checked = $el.is(':checked');

            if(that.checked_count > 0) {
                if(that.checked_count < that.total_items_at_page_count || that.checked_count === that.total_items_at_page_count) {
                    that.unselectAll();
                }else{
                    that.selectAll();
                }
            }else{
                $el.prop('checked', checked);
                if (checked) {
                    that.selectAll();
                } else {
                    that.unselectAll();
                }
            }

            that.formBar();
            that.showOrHideBar();
        });
    };

    CRMPageMessagesOperations.prototype.initOperations = function () {
        var that = this;
        that.$bar.on('click', '.crm-operation-link', function (e) {
            e.preventDefault();
            var $link = $(this),
                op = $link.data('id');

            switch (op) {
                case 'associate':
                    that.associateWithDeal();
                    break;
                case 'detach':
                    that.detachFromDeals();
                    break;
                case 'read':
                    that.markAsRead();
                    break;
                case 'unread':
                    that.markAsUnread();
                    break;
                case 'delete':
                    that.deleteConversation();
                    break;
                default:
                    break;
            }
        });
    };

    CRMPageMessagesOperations.prototype.updateBarCount = function (menuItem, counter) {
        menuItem.find('.crm-count').text('(' + counter + ')');
    };

    CRMPageMessagesOperations.prototype.showOrHideBar = function () {
        var that = this;
        if (that.checked_count > 0) {
            if (!that.is_shown) {
                that.$bar.addClass('is-shown');
                that.$list_header.removeClass('is-shown');
                that.is_shown = true;
            }
            that.formBar();
        } else {
            if (that.is_shown) {
                that.$bar.removeClass('is-shown');
                that.$list_header.addClass('is-shown');
                that.is_shown = false;
            }
        }
    };

    CRMPageMessagesOperations.prototype.getSelectedIds = function () {
        var that = this;
        return that.$list.find('.c-checkbox :checkbox:checked').map(function () {
            return parseInt($(this).val());
        }).toArray();
    };

    CRMPageMessagesOperations.prototype.getTotalItemsAtPage = function () {
        var that = this;
        if (that.page * that.limit > that.total_count) {
            return that.total_count % that.limit
        }
        return that.limit;
    };

    CRMPageMessagesOperations.prototype.formBar = function () {
        var that = this,
            $associate_li = that.$bar.find('.crm-operation-li[data-id="associate"]'),
            $detach_li = that.$bar.find('.crm-operation-li[data-id="detach"]'),
            $read_li = that.$bar.find('.crm-operation-li[data-id="read"]'),
            $unread_li = that.$bar.find('.crm-operation-li[data-id="unread"]'),
            $delete_li = that.$bar.find('.crm-operation-li[data-id="delete"]');

        if (that.unread_counter > 0) {
            $read_li.show();
            that.updateBarCount($read_li, that.unread_counter)
        }else{
            $read_li.hide();
        }

        if (that.read_counter > 0) {
            $unread_li.show();
            that.updateBarCount($unread_li, that.read_counter)
        }else{
            $unread_li.hide();
        }

        if (that.no_deal_counter > 0) {
            $associate_li.show();
            that.updateBarCount($associate_li, that.no_deal_counter)
        }else{
            $associate_li.hide();
        }

        if (that.deal_counter > 0) {
            $detach_li.show();
            that.updateBarCount($detach_li, that.deal_counter)
        }else{
            $detach_li.hide();
        }

        if (that.checked_count > 0) {
            $delete_li.show();
            that.updateBarCount($delete_li, that.checked_count)
        }else{
            $delete_li.hide();
        }
    };

    CRMPageMessagesOperations.prototype.initItemCheckbox = function () {
        var that = this,
            active_class = "is-active";

        that.$list.on("change", ".js-checkbox", function (event, checked) {

            var $checkbox = $(this),
                $message = $checkbox.closest('.js-message-wrapper');

            if (typeof checked !== 'undefined') {
                $checkbox.attr('checked', checked);
            }

            that.markAsReadList($message);
            that.markAsUnreadList($message);
            that.associateWithDealList($message);
            that.detachFromDealsList($message);

            handleMessageItem($message);

            if(that.checked_count > 0) {
                if(that.checked_count < that.limit && that.checked_count !== that.total_items_at_page_count) {
                    that.$checkbox_all.prop('indeterminate', true);
                }else{
                    that.$checkbox_all.prop('indeterminate', false).prop('checked', true);
                }
            }else{
                that.$checkbox_all.prop('indeterminate', false).prop('checked', false);
            }

        });

        // click on checkbox cell
        that.$list.on("click", ".c-checkbox", function(event) {

            var $target = $(event.target),
                is_checkbox = $target.is(':checkbox');

            if (is_checkbox) {
                return;
            }

            if (!is_checkbox) {
                var $checkbox = $(this).find('.js-checkbox');
                $checkbox.trigger("change", [!$checkbox.is(':checked')]);
            }
        });

        // shift-select on contact items
        that.$list.shiftSelectable({
            selector: '.js-message-wrapper',
            behavior_type: 'vertical',
            onSelect: function ($message, event) {
                var $target = $(event.target),
                    is_link = !!($target.is('a') || $target.closest("a").length),
                    is_first = event.extra.is_first,
                    is_last = event.extra.is_last;
                if (is_link || is_first || is_last) {
                    return;
                }

                var $checkbox = $message.find('.js-checkbox');

                // first of all check checkbox
                $checkbox.attr('checked', true);

                // and only than update inner state invariants
                $checkbox.trigger('change');
            }
        });

        function handleMessageItem($message) {

            var $checkbox = $message.find('.js-checkbox'),
                checked = $checkbox.is(':checked');

            highlightMessageItem($message, checked);
            updateCheckedCounter(checked);

            that.formBar();
            that.showOrHideBar();
        }

        function highlightMessageItem($message, checked) {
            if (checked) {
                $message.addClass(active_class);
            } else {
                $message.removeClass(active_class);
            }
        }

        function updateCheckedCounter(checked) {
            if (that.is_checked_all) {
                that.is_checked_all = false;
                that.checked_count = that.$list.find('.c-checkbox :checkbox:checked').length;
            } else {
                if (checked) {
                    that.checked_count += 1;
                } else {
                    that.checked_count -= 1;
                }
            }
        }
    };

    CRMPageMessagesOperations.prototype.selectAll = function () {
        var that = this;
        that.$list
            .find('.c-checkbox :checkbox')
            .prop('checked', true)
            .trigger("change", [true]);
        that.checked_count = that.getSelectedIds().length;
        that.is_checked_all = true;
    };

    CRMPageMessagesOperations.prototype.unselectAll = function () {
        var that = this;
        that.$list
            .find('.c-checkbox :checkbox')
            .prop('checked', false)
            .trigger("change", [false]);
        that.$checkbox_all.prop('checked', false);
        that.checked_count = 0;
        that.is_checked_all = false;
    };

    CRMPageMessagesOperations.prototype.associateWithDeal = function () {
        var that = this,
            ids = that.no_deal_list;

        if (ids.length === 1) {
            that.$list
                .find('tr[data-id="' + ids[0] + '"] .js-associate-deal')
                .trigger('click');
        } else {
            var href = '/webasyst/crm/?module=message&action=conversationAssociateDealDialog&conversation_id=' + ids[0];

            $.get(href, function(html) {
                new CRMDialog({
                    html: html
                });
            });
        }
    };

    CRMPageMessagesOperations.prototype.associateWithDealList = function ($message) {

        var that = this,
            has_deal = $message.data('has-deal'),
            id = $message.data('id');

        if (!has_deal && that.no_deal_list.indexOf(id) === -1) {
            that.no_deal_list.push(id);
        }

        that.no_deal_list = that.getSelectedIds().filter(function (id) {
            return that.no_deal_list.indexOf(id) > -1;
        });

        that.no_deal_counter = that.no_deal_list.length;
    };

    CRMPageMessagesOperations.prototype.detachFromDeals = function () {

        var that = this,
            $wrapper = that.$wrapper,
            $dialog_template = $wrapper.find('.crm-dialog-wrapper.js-detach-conversation'),
            $dialog = $dialog_template.clone(),
            view = that.view,
            ids = that.deal_list,
            data_set = {'message_ids': ids},
            action = 'detachDeals';

        if (view === 'conversation') {
            data_set = {'conversation_ids': ids};
            action = 'detachDealsFromConversations';
        }

        var url = $.crm.app_url + '?module=messageOperation&action=' + action;

        var checkBeforeOperation = function () {
            // Only conversations support checking before action
            if (view === 'conversation') {
                var data = $.extend({check: 1}, data_set, true);
                return $.post(url, data);
            } else {
                return $.Deferred().resolve({});
            }
        };

        var showConfirmDialog = function (confirm_text, check_text) {
            new CRMDialog({
                html: $dialog.show(),
                onOpen: function ($dialog) {
                    var $content = $dialog.find('.crm-dialog-content');
                    $content.find('.js-confirm-text').html(confirm_text);

                    // show/hide "check" text
                    var $check_text = $content.find('.js-check-text').hide();
                    check_text = $.trim(check_text);
                    if (check_text.length > 0) {
                        $check_text.find('.js-text').text(check_text);
                        $check_text.show();
                    }
                },
                onConfirm: function () {

                    $.post(url, data_set)
                        .done(function ( response ) {

                            var data_set_key = Object.keys(data_set)[0],
                                response_ids = response.data[data_set_key];

                            if (response_ids.length) {

                                response_ids.forEach(function (id) {

                                    var html = '<a href="javascript:void(0);"'
                                        +' class="inline-link small js-associate-deal nowrap"'
                                        +' data-dialog-url="' + $.crm.app_url + '?module=message&action=conversationAssociateDealDialog&conversation_id=' + id + '">'
                                        +'<i class="icon10 add" style="vertical-align: baseline; margin: 0 4px 0 0;"></i>'
                                        +'<b><i>' + that.locales.associate_with_deal + '</i></b>'
                                        +'</a>';

                                    if (view === 'all') {
                                        html = '';
                                    }

                                    $('tr[data-id="' + id + '"]', that.$list)
                                        .data('has-deal', 0)
                                        .attr('data-has-deal', 0)
                                        .find('.c-column-deal')
                                        .html(html);

                                    that.no_deal_list.push(id);
                                    that.deal_list = that.deal_list.splice(id, 1);
                                });

                                that.no_deal_counter = that.no_deal_list.length;
                                that.deal_counter = that.deal_list.length;
                                that.formBar();
                            }
                        })
                        .fail(function(response)  {
                            console.error("Detach Deals From Conversations", response);
                        });
                },
                onClose: function ($dialog) {
                    $dialog.find('.crm-dialog-content').empty()
                }
            })
        };

        checkBeforeOperation().done(function (r) {
            var confirm_text = '<h2>' + that.locales.detach_dialog_h2 + '</h2>',
                check_text = r.status === 'ok' && r.data && r.data.text || '';
            showConfirmDialog(confirm_text, check_text)
        });
    };

    CRMPageMessagesOperations.prototype.detachFromDealsList = function ($message) {

        var that = this,
            has_deal = $message.data('has-deal'),
            id = $message.data('id');

        if (has_deal && that.deal_list.indexOf(id) === -1) {
            that.deal_list.push(id);
        }

        that.deal_list = that.getSelectedIds().filter(function (id) {
            return that.deal_list.indexOf(id) > -1;
        });

        that.deal_counter = that.deal_list.length;
    };

    CRMPageMessagesOperations.prototype.markAsRead = function () {

        var that = this,
            url = $.crm.app_url + '?module=messageOperation&action=markAsRead',
            ids = that.unread_list,
            view = that.view,
            data_set = {'message_ids': ids};

        if (view === 'conversation') {
            data_set = {'conversation_ids': ids};
        }

        $.post(url, data_set)
            .done(function( response ) {

                var data_set_key = Object.keys(data_set)[0],
                    response_ids = response.data[data_set_key];

                if (response_ids.length) {
                    response_ids.map(function (id) {
                        that.$list
                            .find('tr[data-id="' + id + '"]')
                            .removeClass('bold')
                            .data('read', 1)
                            .attr('data-read', 1);
                        that.read_list.push(id);
                        that.unread_list = that.unread_list.splice(id, 1);
                    });

                    that.read_counter = that.read_list.length;
                    that.unread_counter = that.unread_list.length;
                    that.formBar();
                }
            })
            .fail(function(response) {
                console.error( "Mark as read", response );
            });
    };

    CRMPageMessagesOperations.prototype.markAsReadList = function ($message) {

        var that = this,
            id = $message.data('id'),
            read = $message.data('read');

        if (read === 1 && that.read_list.indexOf(id) === -1) {
            that.read_list.push(id);
        }

        that.read_list = that.getSelectedIds().filter(function (id) {
            return that.read_list.indexOf(id) > -1;
        });

        that.read_counter = that.read_list.length;
    };

    CRMPageMessagesOperations.prototype.markAsUnread = function () {

        var that = this,
            url = $.crm.app_url + '?module=messageOperation&action=markAsUnread',
            ids = that.read_list,
            view = that.view,
            data_set = {'message_ids': ids};

        if (view === 'conversation') {
            data_set = {'conversation_ids': ids};
        }

        $.post(url, data_set)
            .done(function( response ) {

                var data_set_key = Object.keys(data_set)[0],
                    response_ids = response.data[data_set_key];

                if (response_ids.length) {

                    response_ids.map(function (id) {
                        that.$list
                            .find('tr[data-id="' + id + '"]')
                            .addClass('bold')
                            .data('read', 0)
                            .attr('data-read', 0);
                        that.unread_list.push(id);
                        that.read_list = that.read_list.splice(id, 1);
                    });

                    that.unread_counter = that.unread_list.length;
                    that.read_counter = that.read_list.length;
                    that.formBar();
                }
            })
            .fail(function(response) {
                console.error("Mark as unread", response);
            });
    };

    CRMPageMessagesOperations.prototype.markAsUnreadList = function ($message) {
        var that = this,
            id = $message.data('id'),
            read = $message.data('read');

        if (read === 0 && that.unread_list.indexOf(id) === -1) {
            that.unread_list.push(id);
        }

        that.unread_list = that.getSelectedIds().filter(function (id) {
            return that.unread_list.indexOf(id) > -1;
        });

        that.unread_counter = that.unread_list.length;
    };

    CRMPageMessagesOperations.prototype.deleteConversation = function () {

        var that = this,
            $wrapper = that.$wrapper,
            $dialog_template = $wrapper.find('.js-delete-conversation'),
            $dialog = $dialog_template.clone(),
            view = that.view,
            ids = that.getSelectedIds(),
            data_set = {'message_ids': ids},
            action = 'delete';

        if (view === 'conversation') {
            data_set = {'conversation_ids': ids};
            action = 'deleteConversations';
        }

        var url = $.crm.app_url + '?module=messageOperation&action=' + action;

        var checkBeforeOperation = function () {
            // Only conversations support checking before action
            if (view === 'conversation') {
                var data = $.extend({check: 1}, data_set, true);
                return $.post(url, data);
            } else {
                return $.Deferred().resolve({});
            }
        };

        var showConfirmDialog = function (confirm_text, check_text) {
            new CRMDialog({
                html: $dialog.show(),
                onOpen: function ($dialog) {
                    var $content = $dialog.find('.crm-dialog-content');
                    $content.find('.js-confirm-text').html(confirm_text);

                    // show/hide "check" text
                    var $check_text = $content.find('.js-check-text').hide();
                    check_text = $.trim(check_text);
                    if (check_text.length > 0) {
                        $check_text.find('.js-text').text(check_text);
                        $check_text.show();
                    }
                },
                onConfirm: function () {

                    $.post(url, data_set)
                        .done(function( response ) {

                            var data_set_key = Object.keys(data_set)[0],
                                response_ids = response.data[data_set_key];

                            if (response_ids.length) {

                                response_ids.map(function (id) {
                                    $('tr[data-id="' + id + '"]', that.$list).hide();
                                });

                                that.checked_count = that.checked_count - ids.length;
                                that.total_count = that.total_count - ids.length;

                                that.unselectAll();
                                that.formBar();

                                if (that.total_count < 1) {
                                    that.$list.hide().before('<div class="no-messages">' + that.locales.no_messages + '</div>');
                                    that.unselectAll();
                                    that.hideBar();
                                }
                            }
                        })
                        .fail(function(response) {
                            console.error("Delete messages", response);
                        });
                },
                onClose: function ($dialog) {
                    $dialog.find('.crm-dialog-content').empty()
                }
            })
        };

        checkBeforeOperation().done(function (r) {
            var confirm_text = '<h2>' + that.locales.delete_dialog_h2 + '</h2>',
                check_text = r.status === 'ok' && r.data && r.data.text || '';
            showConfirmDialog(confirm_text, check_text)
        });
    };


    return CRMPageMessagesOperations;

})(jQuery);
