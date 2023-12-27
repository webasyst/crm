var CRMPageMessagesOperations = (function ($) {

    CRMPageMessagesOperations = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$bar = that.$wrapper.find(".js-operation-wrapper");
        that.$list = that.$wrapper.find('.js-messages-section');
        that.$list_header = that.$wrapper.find('.c-messages-header');
        that.$checkbox_all = that.$bar.find('#js-operation-main-checkbox');
        that.$checkbox_all_counter = that.$bar.find('.js-operation-badge');
        //that.$content = that.$wrapper.find(".js-messages-conversation-list");
        // VARS
        that.is_admin = options["is_admin"];
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
        that.showOrHideBar();
        //
        that.initOperations();
        //
        that.initItemCheckbox();

       /* $(document).on('changeActiveConversation', function(e, data) {
            var $message = that.$list.find(`.js-message-wrapper[data-id="${data.target_id}"]`);
            console.log($message);
            that.unread_list = that.getSelectedIds().filter(function (id) {
                return that.unread_list.indexOf(id) > -1;
            });
    
            that.unread_counter = that.unread_list.length;
            that.markAsReadList($message);
            that.markAsUnreadList($message);
            that.formBar();
        });*/
       
    };

    CRMPageMessagesOperations.prototype.initCheckboxAll = function () {
        var that = this;
        
        that.$checkbox_all.click(function () {
            var $el = $(this),
                checked = $el.is(':checked');
            if(that.checked_count > 0) {
                if(that.checked_count <= that.getTotalItemsAtPage()) {
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
            //that.showOrHideBar();
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

    CRMPageMessagesOperations.prototype.updateBarCount = function (menuItem, counter, disable_class) {
        if (counter > 0) {
            menuItem.removeClass(disable_class);
            menuItem.find('.crm-count').text(counter);
        }
        else {
            menuItem.addClass(disable_class);
            menuItem.find('.crm-count').text('');
        }
        
    };

    CRMPageMessagesOperations.prototype.showOrHideBar = function () {
        var that = this;

        that.$list_header.on('click', '.js-operations-show', function() {
            if (that.$bar.hasClass('hidden')) {
                showOperations();
            }
        });

        function showOperations() {
            that.$bar.removeClass('hidden');
            that.$list.addClass('active-operations')
        }

        that.$bar.on('click', '.js-operation-hide', function() {
            
            clearOperations();
        });

        function clearOperations() {
            that.$bar.addClass('hidden');
            that.$list.removeClass('active-operations');
            that.unselectAll();
        }
    };

    CRMPageMessagesOperations.prototype.getSelectedIds = function () {
        var that = this;
        var selectedIds = that.$list.find('.c-checkbox :checkbox:checked').map(function () {
            return parseInt($(this).val());
        }).toArray();
        //console.log(selectedIds)
        return selectedIds;
    };

    CRMPageMessagesOperations.prototype.getTotalItemsAtPage = function () {
        var that = this;
       /* if (that.page * that.limit > that.total_count) {
            return that.total_count % that.limit
        }
        return that.limit;*/
        return that.$list.find('.c-checkbox :checkbox').length
    };

    CRMPageMessagesOperations.prototype.formBar = function () {
        var that = this,
            disable_class = 'disabled',
            //$associate_li = that.$bar.find('.crm-operation-li[data-id="associate"]'),
            $detach_li = that.$bar.find('.crm-operation-li[data-id="detach"] a'),
            $read_li = that.$bar.find('.crm-operation-li[data-id="read"] a'),
            $unread_li = that.$bar.find('.crm-operation-li[data-id="unread"] a'),
            $delete_li = that.$bar.find('.crm-operation-li[data-id="delete"] a');

            that.updateBarCount($read_li, that.unread_counter, disable_class);
            that.updateBarCount($unread_li, that.read_counter, disable_class)
            that.updateBarCount($detach_li, that.deal_counter, disable_class)
            that.updateBarCount($delete_li, that.checked_count, disable_class)
        /*if (that.no_deal_counter > 0) {
            $associate_li.show();
            that.updateBarCount($associate_li, that.no_deal_counter)
        }else{
            $associate_li.hide();
        }*/

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
            //that.associateWithDealList($message);
            that.detachFromDealsList($message);

            handleMessageItem($message);

            if(that.checked_count > 0) {
                if(that.checked_count < that.getTotalItemsAtPage()) {
                    that.$checkbox_all.parent().addClass('indeterminate').find('svg').addClass('fa-minus').removeClass('fa-check');
                }else{
                    that.$checkbox_all.prop('checked', true);
                    that.$checkbox_all.parent().removeClass('indeterminate').find('svg').removeClass('fa-minus').addClass('fa-check');
                }
            } else {
                that.$checkbox_all.prop('checked', false);
                that.$checkbox_all.parent().removeClass('indeterminate').find('svg').removeClass('fa-minus').addClass('fa-check');
            }

        });

        // click on checkbox cell
        /*that.$list.on("click", ".c-checkbox", function(event) {

            var $target = $(event.target),
                is_checkbox = $target.is(':checkbox');

            if (is_checkbox) {
                return;
            }

            if (!is_checkbox) {
                var $checkbox = $(this).find('.js-checkbox');
                $checkbox.trigger("change", [!$checkbox.is(':checked')]);
            }
        });*/

        // shift-select on contact items
        /*that.$list.shiftSelectable({
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
        });*/

        function handleMessageItem($message) {

            var $checkbox = $message.find('.js-checkbox'),
                checked = $checkbox.is(':checked');

           // highlightMessageItem($message, checked);
            updateCheckedCounter(checked);

            that.formBar();
            //that.showOrHideBar();
        }

        /*function highlightMessageItem($message, checked) {
            if (checked) {
                $message.addClass(active_class);
            } else {
                $message.removeClass(active_class);
            }
        }*/

        function updateCheckedCounter(checked) {
           /* if (that.is_checked_all) {
                that.is_checked_all = false;
                that.checked_count = that.$list.find('.c-checkbox :checkbox:checked').length;
            } else {*/
                if (checked) {
                    that.checked_count += 1;
                } else {
                    if (that.checked_count > 0) that.checked_count -= 1;
                }
           // }
            that.$checkbox_all_counter.text(that.checked_count);
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
            //$dialog_template = $wrapper.find('.crm-dialog-wrapper.js-detach-conversation'),
            //$dialog = $dialog_template.clone(),
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
            $.waDialog.confirm({
                title: `<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ${confirm_text}`,
                text: $.trim(check_text),
                success_button_title: that.locales.detach_button,
                success_button_class: 'danger',
                cancel_button_title: that.locales.cancel_button,
                cancel_button_class: 'light-gray',
                onSuccess: function () {

                    $.post(url, data_set)
                        .done(function ( response ) {

                            var data_set_key = Object.keys(data_set)[0],
                                response_ids = response.data[data_set_key];

                            if (response_ids.length) {

                                response_ids.forEach(function (id) {

                                   /* var html = '<a href="javascript:void(0);"'
                                        +' class="inline-link small js-associate-deal nowrap"'
                                        +' data-dialog-url="' + $.crm.app_url + '?module=message&action=conversationAssociateDealDialog&conversation_id=' + id + '">'
                                        +'<i class="icon10 add" style="vertical-align: baseline; margin: 0 4px 0 0;"></i>'
                                        +'<b><i>' + that.locales.associate_with_deal + '</i></b>'
                                        +'</a>';

                                    if (view === 'all') {
                                        html = '';
                                    }*/

                                    $('.js-message-wrapper[data-id="' + id + '"]', that.$list)
                                        .data('has-deal', 0)
                                        .attr('data-has-deal', 0)
                                        //.find('.c-column-deal')
                                        //.html(html);

                                    that.no_deal_list.push(id);
                                    that.deal_list = that.deal_list.splice(id, 1);
                                });

                                that.no_deal_counter = that.no_deal_list.length;
                                that.deal_counter = that.deal_list.length;
                                that.formBar();
                            }
                            var $active_id = that.$list.find(`.js-message-wrapper.selected`).data('id');
                            $.message.content.reload($active_id);
                        })
                        .fail(function(response)  {
                            console.error("Detach Deals From Conversations", response);
                        });
                },
                onClose: function ($dialog) {
                    //$dialog.find('.crm-dialog-content').empty()
                }
            })
        };

        checkBeforeOperation().done(function (r) {
            var confirm_text = that.locales.detach_dialog_h2,
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
                            .find('.js-message-wrapper[data-id="' + id + '"]')
                            .removeClass('unread')
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
            //is_selected = $message.hasClass('selected'),
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
                            .find('.js-message-wrapper[data-id="' + id + '"]')
                            .addClass('unread')
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
            //is_selected = $message.hasClass('selected'),
            read = $message.data('read');
            //is_selected = $message.hasClass('selected');

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
            //$dialog_template = $wrapper.find('.js-delete-conversation'),
            //$dialog = $dialog_template.clone(),
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

        var ban_html = that.is_admin ? `
            <label>
                <span class="wa-checkbox">
                    <input type="checkbox" name="ban_checkbox" id="ban_checkbox">
                    <span>
                        <span class="icon">
                            <i class="fas fa-check"></i>
                        </span>
                    </span>
                </span>
                    ${that.locales.ban_text}
            </label>`: '';

        var showConfirmDialog = function (confirm_text, check_text) {

            var text_html = `<div class="flexbox vertical space-16">${ban_html} <span class="text-red">${$.trim(check_text)}</span></div>`;

            $.waDialog.confirm({
                title: `<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ${confirm_text}`,
                text: text_html,
                success_button_title: that.locales.delete_button,
                success_button_class: 'danger',
                cancel_button_title: that.locales.cancel_button,
                cancel_button_class: 'light-gray',
                onSuccess: function ($dialog, dialog_instance) {
                    var is_ban_checked = $dialog.$content.find("#ban_checkbox").prop('checked');
                    var data = data_set;
                    if (is_ban_checked) {
                        data = $.extend({ban_contacts: 1}, data_set, true);
                    }

                    $.post(url, data)
                        .done(function( response ) {

                            var data_set_keys = Object.keys(data),
                                data_set_key = data_set_keys[data_set_keys.length-1],
                                response_ids = response.data[data_set_key];

                            if (response_ids.length) {

                                response_ids.map(function (id) {
                                    $('.js-message-wrapper[data-id="' + id + '"]', that.$list).hide();
                                });

                                that.checked_count = that.checked_count - ids.length;
                                that.total_count = that.total_count - ids.length;

                                that.unselectAll();
                                that.formBar();

                                if (that.total_count < 1) {
                                    that.$list.hide().before('<div class="no-messages">' + that.locales.no_messages + '</div>');
                                    that.unselectAll();
                                    that.$bar.addClass('hidden');
                                    that.$list.removeClass('active-operations');
                                }
                            }
                        })
                        .fail(function(response) {
                            console.error("Delete messages", response);
                        });
                },
                onClose: function ($dialog) {
                    //$dialog.find('.dialog-content').empty()
                }
            })
        };

        checkBeforeOperation().done(function (r) {
            var confirm_text = that.locales.delete_dialog_h2,
                check_text = r.status === 'ok' && r.data && r.data.text || '';
            showConfirmDialog(confirm_text, check_text)
        });
    };


    return CRMPageMessagesOperations;

})(jQuery);
