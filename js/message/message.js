( function($) {

$.message = $.extend($.message || {}, {
    app_url: false,
    is_page_loaded: false,
    content: false,
    sidebar: false,
    locales: false,
})

})(jQuery);

var MessageContentRouter = ( function($) {

    MessageContentRouter = function(options) {
        var that = this;

        // DOM
        that.$content = options["$content"];

       that.iframe = options["iframe"];
        // VARS
        that.app_url = options['app_url'] || ($ && $.crm && $.crm.app_url);
        that.api_enabled = !!(window.history && window.history.pushState);

        // DYNAMIC VARS
        that.xhr = false;

        // INIT
        that.initClass();
    };

    MessageContentRouter.prototype.initClass = function() {
        var that = this;
        that.animate( true );
    };

    MessageContentRouter.prototype.load = function(content_uri, unset_state, target_props, target_id, $link) {
        var that = this;

        var uri_has_app_url = ( content_uri.indexOf( $.crm.app_url ) >= 0 );
        if (!uri_has_app_url) {
            // TODO:
            alert("Determine the path error");
            return false;
        }
        that.animate( true );

        if (that.xhr) {
            that.xhr.abort();
        }

        $(document).trigger('wa_before_load', {
            content_uri: content_uri
        });

        if (target_id) {
            $(document).trigger('change_active_id', {
                target_id: target_id
            });
        }

        var content_url = $.crm.app_url + '?module=messageConversationId&id=' + target_props;
        that.xhr = $.ajax({
            method: 'GET',
            url: content_url,
            dataType: 'html',
            global: false,
            cache: false
        }).done(function(html) {
            if (that.api_enabled && !unset_state) {
                history.pushState({
                    reload: true,               // force reload history state
                    content_uri: content_uri    // url, string
                }, "", content_uri);
            }

            that.setContent( html );

            that.xhr = false;

        }).fail(function(data) {
            if (data.responseText) {
                    $.crm.alert.show({
                        title: "Error",
                        text: data.responseText,
                        button: "Close",
                    })
            }
        });

        return that.xhr;
    };

    MessageContentRouter.prototype.reload = function(target_id) {
        var that = this,
            content_uri = (that.api_enabled && history.state && history.state.content_uri) ? history.state.content_uri : location.href;

        if (content_uri) {
            return that.load(content_uri, true, target_id);
        } else {
            return $.when(); // a resolved promise
        }
    };

    MessageContentRouter.prototype.setContent = function( html ) {
        var that = this;
        that.$content.html(html);
    };

    MessageContentRouter.prototype.animate = function( show ) {
        var that = this;
        that.$content_wrapper = that.$content.find('#js-message-conversation-page');
        that.$skeleton =  that.$content.find('.skeleton-wrapper');

        var $content_wrapper = that.$content_wrapper,
            $skeleton = that.$skeleton;

            setTimeout( function() {
                if (show) {
                    $skeleton.show();
                }
                else {
                    $skeleton.is(':visible') || that.iframe ? $skeleton.hide() : null;
                }
        }, 10);
    };

    return MessageContentRouter;

})(jQuery);

var CRMDealMessagesDialog = ( function($) {

    CRMDealMessagesDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealMessagesDialog.prototype.initClass = function() {
        var that = this;

        that.$wrapper.on("click", ".c-message-wrapper a", function(event) {
            event.preventDefault();
        });

        that.$wrapper.on("click", ".c-message-wrapper", function(event) {
            event.preventDefault();

            var target = event.target,
                $target = $(target),
                $tr = $(this),
                is_link = $target.attr("href");

            if (!is_link) {
                var $link = $tr.find(".js-message-show-body");
                if ($link.length) {
                    $link.trigger("click");
                }
            }
        });
    };

    return CRMDealMessagesDialog;

})(jQuery);

var CRMMessageBodyDialog = ( function($) {

    CRMMessageBodyDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.is_admin = options["is_admin"];
        that.app_url = options["app_url"];
        that.dialog = ( that.$wrapper.data("dialog") || false );
        that.$button = that.$wrapper.find(".js-close-dialog");

        // VARS
        that.contact_id = options['contact_id'];
        that.message = options['message'] || {};
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessageBodyDialog.prototype.initClass = function() {
        var that = this;

        that.initMessageShowBodyLink();
        //
        if (that.is_admin) {
            that.initSelectDeal();
            //
            that.initMessageDeleteLink();
        }

    };

    CRMMessageBodyDialog.prototype.initSelectDeal = function() {
        var that = this,
            $deal_form = that.$wrapper.find('.deal-form'),
            $deal_name = $deal_form.find('.js-deal-name'),
            $deal_name_input = $deal_form.find('.js-deal-name-input'),
            $deal_save = $deal_form.find('.js-save-deal'),
            $deal_save_details = $deal_form.find('.js-deal-save-details'),
            $deals_dropdown_wrapper = $deal_form.find('.js-select-deal'),
            $deals_dropdown = $deal_form.find('.js-deals-dropdown'),
            $deal_remove = $deal_form.find('.js-remove-deal'),
            $deal_empty = $deals_dropdown.find('.js-empty-deal'),
            $visible_link = $deal_form.find('.js-select-deal .js-visible-link .js-text'),
            $select_funnel = $deal_form.find('.js-select-funnel-wrapper'),
            $select_stage = $deal_form.find('.js-select-stage-wrapper'),
            $deals_list = $deal_form.find('.js-deals-list'),
            $deal_id = $deal_form.find('.js-deal-id'),
            contact_id = that.contact_id;

        // Default deal_id - none
        $deal_id.val('none');

        // Load deals by contact:
        if (contact_id) {
            var href = '?module=deal&action=byContact&only_existing_stage=1&id=' + contact_id;
            $.get(href, function(response) {
                if (response.status === "ok") {
                    // rendering contact deals
                    $.each(response.data.deals, function (i, deal) {
                        $deals_list.prepend(renderDeals(deal,response.data.funnels[deal.funnel_id]));
                    });
                }
            }, "json");
        }

        // New deal
        $deal_form.on('click', '.js-create-new-deal', function () {
            $deal_id.val('0');
            $deals_dropdown_wrapper.addClass('hidden');
            $deals_dropdown.addClass('hidden');
            $deal_name.removeClass('hidden');
            $deal_save.attr('title', that.locales['deal_create']).text(that.locales['deal_create']).removeClass('hidden');
            $deal_save_details.removeClass('hidden');
            $select_funnel.removeClass('hidden');
            $deal_empty.removeClass('hidden');
            $deal_name_input.focus();
        });

        // Select old deal
        $deal_form.on('click', '.js-deal-item', function () {
            var new_deal = $(this).find('.js-text').html();
            $visible_link.html(new_deal);
            $deal_id.val($(this).data('deal-id'));
            $deal_save.attr('title', that.locales['deal_add']).text(that.locales['deal_add']).removeClass('hidden');
            $deal_save_details.removeClass('hidden');
            $select_funnel.addClass('hidden');
            $deal_empty.removeClass('hidden');
            $deals_list.find('li').removeClass('selected');
            $(this).parent().addClass('selected');
        });

        // Hide items in .menu-h .dropdown, by clicking (select) an item
        $deals_list.on('click', function () {
            $deals_list.hide();
            setTimeout( function() {
                $deals_list.removeAttr("style");
            }, 200);
        });

        // Remove deal
        $deal_empty.on('click', function () {
            emptyDeal();
        });
        $deal_remove.on('click', function () {
            emptyDeal();
        });

        // Save deal
        $deal_save.on('click', function (e) {
            e.preventDefault();
            $deal_form.trigger('submit');
        });
        $deal_form.on('submit', function (e) {
            e.preventDefault();
            saveDeal();
        });

        // Load new funnel stages
        $deal_form.on('change', '.js-select-deal-funnel', function() {
            console.log($(this).val());
            $deal_form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });

        function renderDeals(deal,funnel) {
            return '<li><a href="javascript:void(0);" class="js-deal-item" data-deal-id="' + deal.id + '">' +
                    '<i class="' + (funnel.icon || 'fas fa-briefcase') + ' funnel-state" style="color: '+ funnel.stages[deal.stage_id].color +'"></i>'+
                    '<span class="js-text">' + deal.name + (funnel.is_archived == 1 ? `<span class="gray small custom-ml-4 nowrap">${that.locales['archived']}</span>` : '') + '</span>' +
                '</a></li>';
        }

        function emptyDeal() {
            $visible_link.html(''+ that.locales['deal_empty'] +'');
            $deal_id.val('none');
            $deal_name_input.val('');
            $deal_name.addClass('hidden');
            $deal_save.addClass('hidden').removeAttr('title');
            $deal_save_details.addClass('hidden');
            $deal_empty.addClass('hidden');
            $select_funnel.addClass('hidden');
            $deals_list.find('li').removeClass('selected');
            $deals_dropdown_wrapper.removeClass('hidden');
            $deals_dropdown.removeClass('hidden');
        }

        function saveDeal() {
            var $created_deal = that.$wrapper.find('.js-created-deal'),
                $new_deal_stage_icon = $select_stage.find('.js-visible-link .js-text .funnel-state').clone(),
                new_deal_name = $.trim($deal_name_input.val()),
                data = $deal_form.serializeObject();

            data['message_id'] = that.message.id;

            // Validate deal data
            if ($deal_id.val() === 'none') {
                $deal_form.addClass('shake animated');
                setTimeout(function(){
                    $deal_form.removeClass('shake animated');
                },500);
                return false;
            }

            if ($deal_id.val() <= 0 && !new_deal_name) {
                $deal_name.addClass('shake animated');
                setTimeout(function(){
                    $deal_name.removeClass('shake animated');
                    $deal_name_input.focus();
                },500);
                return false;
            }

            $deal_form.addClass('hidden');
            $created_deal.removeClass('hidden');

            // Set deal
            if ($deal_id.val() <= 0) {
                $created_deal.html($new_deal_stage_icon);
                $created_deal.append($.crm.escape(new_deal_name));
            } else {
                var $old_deal = $deals_dropdown.find('.js-visible-link .js-text'),
                    $old_deal_stage_icon = $old_deal.find('.funnel-state').clone(),
                    old_deal_name = $old_deal.find('b i').text();

                $created_deal.html($old_deal_stage_icon);
                $created_deal.append($.crm.escape(old_deal_name));
            }

            // Send data
            var href = $.crm.app_url + "?module=message&action=associateDealSave";
            $.post(href, data, function(res){
                if (res.status === "ok" && res.data.deal_url) {
                    var deal_label = $created_deal.html();
                    $created_deal.html('<a href="'+ res.data.deal_url +'">'+ deal_label +'</a>');
                } else {
                    $created_deal.html('');
                    $created_deal.addClass('hidden');
                    emptyDeal();
                    $deal_form.removeClass('deal-form-hidden');
                }
            });
        }
    };

    CRMMessageBodyDialog.prototype.initMessageShowBodyLink = function() {
        var that = this,
            dialog = that.dialog,
            $dialog_wrapper = that.$wrapper ;

            that.initMessageReplyBtn($dialog_wrapper, dialog);
            //
            that.initMessageForwardBtn($dialog_wrapper, dialog);
            $dialog_wrapper.on('click', 'a', function (event) {
                if ($(this).prop('href') !== 'javascript:void(0);') {
                    dialog.close();
                }

            })
    }

    CRMMessageBodyDialog.prototype.initMessageReplyBtn = function($dialog_wrapper, dialog) {
                var that = this;

                $dialog_wrapper.on('click', '.js-message-reply', function () {
                    var $link = $(this),
                        message_id = $link.data('message-id');
                    showLoading(true);

                    var href = that.app_url+'?module=message&action=writeReplyDialog',
                        params = { id: message_id };

                    $.post(href, params, function(html) {

                        $.waDialog({
                            html: html,
                            onClose: onClose
                        });
                        function onClose() {

                        }
                    }).always( function() {
                        showLoading(false);
                    });

                    function showLoading(show) {
                        var $icon = $link.find(".icon");
                        if (!$icon.length) { return false; }

                        var default_class = "fa-undo",
                            loading_class = "fa-spinner fa-spin";

                        if (show) {
                            $icon.removeClass(default_class).addClass(loading_class);
                        } else {
                            $icon.removeClass(loading_class).addClass(default_class);
                        }
                    }
                })
    };

    CRMMessageBodyDialog.prototype.initMessageForwardBtn = function($dialog_wrapper, dialog) {
                var that = this;

                $dialog_wrapper.on('click', '.js-message-forward', function () {
                    var $link = $(this),
                        message_id = $link.data('message-id');
                        showLoading(true);

                    var href = that.app_url+'?module=message&action=writeForwardDialog',
                        params = { id: message_id };

                    $.post(href, params, function(html) {
                       // dialog.hide();

                        $.waDialog({
                            html: html,
                        });
                    }).always( function() {
                        showLoading(false);
                    });

                    function showLoading(show) {
                        var $icon = $link.find(".icon");
                        if (!$icon.length) { return false; }

                        var default_class = "fa-redo",
                            loading_class = "fa-spinner fa-spin";

                        if (show) {
                            $icon.removeClass(default_class).addClass(loading_class);
                        } else {
                            $icon.removeClass(loading_class).addClass(default_class);
                        }
                    }
                })
    };

    CRMMessageDeleteLinkMixin.mixInFor(CRMMessageBodyDialog);

    return CRMMessageBodyDialog;

})(jQuery);

var CRMMessagesSidebar = ( function($) {

    CRMMessagesSidebar = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$ui = options["$ui"];
        that.no_messages = options["empty_conversations"] === 'true';
        that.$sidebar = that.$wrapper.parent();
        that.$list = that.$wrapper.find('.c-messages-table-section')
        //that.page = options["page"];
        that.$skeleton =  that.$sidebar.find('.skeleton-wrapper');
        that.page_of_item = options["page_of_item"];
        that.current_page = options["current_page"];
        that.active_id = options["active_id"];
        that.last_message_id = options["last_message_id"];
        that.$settings_contact_id = +options["settings_contact_id"];
        that.$settings_deal_id = +options["settings_deal_id"];
        // Messages Operations
        that.settings = options;
        // VARS
        that.iframe = options["iframe"];
        that.noemail = options["noemail"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessagesSidebar.prototype.initClass = function() {
        var that = this;

        that.initLazyLoading();
        that.initSearchHandler();
        that.conversationListHandler(); //for scrolling sidebar to selected item

        new CRMPageMessagesOperations(that.settings);

        if (that.no_messages) {
            that.$wrapper.closest('#c-messages-sidebar').addClass('no-messages-sidebar');
        }

        that.$wrapper.on("click", ".js-associate-deal", function(event) {
            event.preventDefault();

            var href = $(this).data('dialog-url');

            $.get(href, function(html) {
                $.waDialog({
                    html: html
                });
            });
        });

        var is_locked = false;
        that.$wrapper.on('click', '.js-write-message', function(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;
                var url = $.crm.app_url + '?module=message&action=writeNewDialog';
                const contact_id = that.$settings_contact_id ? that.$settings_contact_id : null;
                const deal_id = that.$settings_deal_id ? that.$settings_deal_id : null;
                data = {contact_id: contact_id, deal_id: deal_id};
                $.get(url, data, function(html) {

                    $.waDialog({
                        html: html,
                        onOpen: function() {is_locked = false}
                    });
                });
            }

        });

        that.$wrapper.on('click', '.js-filter-open', function() {
            that.$wrapper.find('.c-message-filter-wrapper').toggle();
        });

        if (that.current_page) {
            that.initBackgroundUpdate();
        }

    };

    CRMMessagesSidebar.prototype.initSearchHandler = function() {
        var that = this,
            $sidebar = that.$sidebar;
        that.$search_wrapper = $sidebar.find(".js-search-wrapper");
        var $search_wrapper = that.$search_wrapper;
            $page_name = $sidebar.find(".js-page-name-header"),
            $autocomplete = $search_wrapper.find(".js-search-field"),
            $search_block = $search_wrapper.find(".state-with-inner-icon");

        if (!that.iframe) {
            $autocomplete
                .autocomplete({
                    appendTo:  $search_wrapper,
                    source: $.crm.app_url + "?module=autocompleteContact",
                    minLength: 2,
                    delay: 300,
                    html: true,
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        selectHandler(ui.item);
                        that.$skeleton.show();
                        $autocomplete.val("");
                        return false;
                    }
                })
                .data("ui-autocomplete")._renderItem = function ($ul, item) {
                    $ul.css("max-height", "70vh");
                    $ul.css("overflow-y", "scroll");
                    $ul.css('max-width', '200px');
                    return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.label + "</div>").appendTo($ul);
                };
        }

            $search_wrapper.on('click', '.js-search-contact-cancel', function() {
                that.$skeleton.show();
                $.crm.content.load($.crm.app_url + 'message/');
            })
            $search_wrapper.on('click', '.js-search-mobile-show', function(event) {
                $search_wrapper.find('.js-search-mobile-show').removeClass('mobile-only').hide();
                $page_name.removeClass('mobile-only').hide();
                $search_block.removeClass('desktop-and-tablet-only');
                $search_wrapper.find('.js-search-contact-hide').show();
            })

            $search_wrapper.on('click', '.js-search-contact-hide', function(event) {
                $search_wrapper.find('.js-search-contact-hide').hide();
                $search_wrapper.find('.js-search-mobile-show').add('mobile-only').show();
                $page_name.addClass('mobile-only').show();
                $search_block.addClass('desktop-and-tablet-only');
            })


         function selectHandler(user) {
            $.crm.content.load($.crm.app_url + 'message/?contact=' + user.id);
        }
    }

    CRMMessagesSidebar.prototype.initBackgroundUpdate = function() {
        var that = this,
            last_message_id = that.last_message_id,
            is_locked = false,
            timeout = 0;

        clearGlobalListeners();

        $(document).on('change_active_id', function(e, data) {
            if (data.target_id) {
                that.active_id = data.target_id;
            }
        });

        $(document).on('msg_sidebar_upd_needed', function() {
            runner(true);
        });

        function clearGlobalListeners() {
            $(document).off('msg_sidebar_upd_needed');
            $(document).off('change_active_id');
        }

        function clearListeners() {
            that.$wrapper.off('click');
            that.$search_wrapper.off('click');
            that.$list.off('click');
            clearTimeout(timeout);
        }

        runner();

        function runner(no_timeout = false) {
            clearTimeout(timeout);
            if (no_timeout) {
                request()
            } else {
                timeout = setTimeout(request, 120000);
            }

        }

        function request() {

            if (!is_locked) {
                is_locked = true;

                var is_exist = $.contains(document, that.$wrapper[0]);

                if (is_exist) {

                    var href = "?module=message&action=listByConversation&background_process=1",
                        data = {
                            check: 1
                        };
                    $.post(href, data, function(response) {

                        if (response.status === "ok") {
                            var is_changed = (response.data !== last_message_id),
                                is_exist = $.contains(document, that.$wrapper[0]);

                            if (is_exist) {
                                is_changed && !isDialogOpen() ? reload() : runner();
                            }
                        }
                    }, "json").always( function() {
                        is_locked = false;
                    });
                }
            }
        }

        function isDialogOpen() {
            return !!($(".dialog-background:visible").length);
        }

        function reload() {
            //$.crm.content.reload();
            const active_id = that.active_id ? 'conversation/' + that.active_id + '/' : '';
            const iframe = that.iframe ? '&iframe=' + that.iframe : '';
            const noemail = that.noemail ? '&noemail=1' : '';
            const contact_id = that.$settings_contact_id ? '&contact='+that.$settings_contact_id : that.$settings_deal_id ? '&deal='+that.$settings_deal_id : '';

            var content_uri = $.crm.app_url + "message/" + active_id + "?reload=1" + contact_id + iframe + noemail;
            var data = {
                ui: that.$ui,
                id: +that.active_id,
                background_process: 1,
                no_need_to_get_the_conversation: 1
            };

            clearGlobalListeners();
            clearListeners();

            $.get(content_uri, data, function(html) {
                const sidebar_list = $(html).find('#c-messages-conversation-list').html();
                that.$wrapper.html( sidebar_list );
            });
        }
    };

    CRMMessagesSidebar.prototype.conversationListHandler = function() {
        var that = this,
            $list = that.$list,
            //$content = that.$content,
            $sidebar = that.$sidebar,
            $hidden_class = 'desktop-and-tablet-only',
            $window = $(window);

        function openConversationList(event) {
            event.preventDefault();
            event.stopPropagation();
            var $link = $(this),
                $content = $sidebar.next();
            let target_link = $link.prop('href');
            let target_id = $link.parent().data('id');

            const contact_id = that.$settings_contact_id ? '&contact='+that.$settings_contact_id : that.$settings_deal_id ? '&deal='+that.$settings_deal_id : '';
            //const iframe = that.iframe ? '&view=chat' : '?view=chat';
            const iframe2 = that.iframe ? '&iframe=1&view=chat' : '&view=chat';
            //target_link += iframe + contact_id;
            target_props = target_id + iframe2 + contact_id;

            /*$(document).trigger('changeActiveConversation', {
                target_id: target_id
            });*/

            $.message.content.load(target_link, false, target_props, target_id, $link);
            $list.find('.selected').removeClass('selected');
            $link.parent().addClass('selected').removeClass('unread');
            if (!$content.is(':visible')) {
                $sidebar.addClass($hidden_class);
                $content.removeClass($hidden_class);
            }
        }

        $list.on('click', 'a', openConversationList);
        $window.on('beforeunload', function() {
            $list.off('click', 'a', openConversationList);
        });

        $(document).ready(function() {
            that.scrollToSelected();
            that.$skeleton.hide();
        })
    }

    CRMMessagesSidebar.prototype.scrollToSelected = function () {
        var that = this,
            $sidebar =  that.$sidebar,
            $list = that.$list,
            $target = $list.find(".js-message-wrapper.selected");
        if ($target.length) {
            var target_t = $target.offset().top;
                target_h = $target.height(),
                viewportHeight = $(window).height(),
                scrollIt = target_t - ((viewportHeight - target_h) / 2);
                $sidebar.scrollTop(scrollIt);
        }
    }

    CRMMessagesSidebar.prototype.initLazyLoading = function () {
        var that = this,
            is_locked = false,
            $window = $(window),
            $sidebar =  that.$sidebar,
            list_height = $sidebar.find('.c-messages-conversation-list').height(),
            $list = that.$list,
            page_of_item = that.page_of_item;

        function checkBigWindow() {
            if (that.current_page == 1 && ($window.height() - 64) > list_height) {
                return true
            }
            else return false
        }

        function startLazyLoading() {
            var $loader = $list.find(".js-lazy-load");

            is_locked = false;

            if (page_of_item && page_of_item > that.current_page) {
                load($loader);
                return
            }

            if (page_of_item && page_of_item == that.current_page) {
                that.scrollToSelected();
            }

            if ($loader.length) {
                if (checkBigWindow()) {
                    useMain();
                    return
                }
                $sidebar.on("scroll touchmove", useMain);
            }



            function useMain() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    onScroll($loader);
                } else {
                    $sidebar.off("scroll touchmove", useMain);
                }
            }
        }

            function onScroll($loader) {
                var scroll_top = $window.scrollTop(),
                    display_h = $window.height(),
                    loader_top = $loader.offset().top;
                if (scroll_top + display_h >= loader_top) {
                    if (!is_locked) {
                        load($loader);
                    }
                }
            }

            function load($loader) {
                var href = $.crm.app_url + '?module=messageListByConversation',
                    contact__id = that.$settings_contact_id ? +that.$settings_contact_id : undefined,
                    deal__id = that.$settings_deal_id ? +that.$settings_deal_id : undefined;
                data = {
                    id: +that.active_id,
                    page: ++that.current_page,
                    contact: contact__id,
                    deal: deal__id,
                    reload: 1,
                    iframe: that.iframe
                };

                is_locked = true;
                $.post(href, data, function (html) {
                    if ($loader) $loader.remove();
                    var $new_list = $(html).find('.c-messages-table-section').html();
                    $list.append($new_list);
                    startLazyLoading();
                }).always(function () {

                });
            }

        startLazyLoading();
    }

    return CRMMessagesSidebar;

})(jQuery);
