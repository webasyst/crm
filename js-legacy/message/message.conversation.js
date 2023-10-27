var CRMMessageConversationPage = ( function($) {

    CRMMessageConversationPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.locales = options["locales"];
        that.conversation_id = options["conversation_id"];
        that.contact_id = options["contact_id"];
        that.funnel_id = options["funnel_id"];
        that.last_message_id = options["last_message_id"];
        that.check_interval = options["check_interval"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessageConversationPage.prototype.initClass = function() {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        // deal already attached, no need to init selected
        if (!that.$wrapper.find('.js-deal-link').length) {
            that.initSelectDeal();
        }

        //
        that.initDealActions();

        //
        that.initChangeResponsible();
        //
        that.initBackgroundActions();
        //
        that.initMessageActions();
        //
        that.initDelete();
        //
        that.scroll2bottom();
        // for long render blocks
        that.initStickiesAtSection();

        // when click on email link in contact block
        that.initSendEmail();
    };

    CRMMessageConversationPage.prototype.loadDealListByContact = function(callback) {

        callback = callback || function () {};

        var contact_id = this.contact_id;
        if (contact_id <= 0) {
            callback({});
            return;
        }

        var href = '?module=deal&action=byContact&id=' + contact_id;
        $.get(href, "json").always(function (response) {
            if (response && response.status === "ok") {
                callback(response.data || {});
            } else {
                callback({});
            }
        });
    };

    CRMMessageConversationPage.prototype.initSelectDeal = function(options) {
        var that = this;

        options = options || {};

        var $wrapper = options.$wrapper || that.$wrapper;


        // render helper
        var renderContactDeals = function(data) {

            data = data || {};
            var deals = data.deals || {};
            var funnels = data.funnels || {};

            // rendering contact deals
            var deals_count = 0;
            $.each(deals, function (i, deal) {
                $deals_list.prepend(renderDeals(deal, funnels[deal.funnel_id]));
                deals_count++;
            });

            $deal_selector_wrapper.show();

            $deals_dropdown.data('deals_count', deals_count);

            if (deals_count > 0) {
                $deals_dropdown.removeClass('hidden');
                $deal_create_new_single_link.addClass('hidden');
            } else {
                $deal_create_new_single_link.removeClass('hidden');
                $deals_dropdown.addClass('hidden');
            }

            //
            $.crm.renderSVG($wrapper);
        };

        var $deal_selector_wrapper = $wrapper.find('.js-deal-selector-control-wrapper'),
            $deal_form = $deal_selector_wrapper.find('.deal-form'),
            $deal_name = $deal_form.find('.js-deal-name'),
            $deal_name_input = $deal_form.find('.js-deal-name-input'),
            $deal_save = $deal_form.find('.js-save-deal'),
            $deals_dropdown = $deal_form.find('.js-deals-dropdown'),
            $deal_create_new_single_link = $deal_form.find('.js-create-new-deal-link'),
            $deal_remove = $deal_form.find('.js-remove-deal'),
            $deal_empty = $deals_dropdown.find('.js-empty-deal'),
            $visible_link = $deal_form.find('.js-select-deal .js-visible-link .js-text'),
            $select_funnel = $deal_form.find('.js-select-funnel-wrapper'),
            $select_stage = $deal_form.find('.js-select-stage-wrapper'),
            $deals_list = $deal_form.find('.js-deals-list'),
            $deal_id = $deal_form.find('.js-deal-id');

        // Default deal_id - none
        $deal_id.val('none');

        if (typeof options.data === 'undefined') {
            // Load deals by contact
            that.loadDealListByContact(renderContactDeals);
        } else {
            // render predefined list of deals
            renderContactDeals(options.data);
        }

        // New deal
        $deal_form.on('click', '.js-create-new-deal', function () {
            $deal_id.val('0');
            $deals_dropdown.addClass('hidden');
            $deal_create_new_single_link.addClass('hidden');
            $deal_name.removeClass('c-deal-name-hidden');
            $deal_save.attr('title', that.locales['deal_create']).removeClass('hidden');
            $select_funnel.removeClass('hidden');
            $deal_empty.removeClass('c-empty-deal-hidden');
            $deal_name_input.focus();
        });

        // Select old deal
        $deal_form.on('click', '.js-deal-item', function () {
            var new_deal = $(this).find('.js-text').html();
            $visible_link.html(new_deal);
            $deal_id.val($(this).data('deal-id'));
            $deal_save.attr('title', that.locales['deal_add']).removeClass('hidden');
            $select_funnel.addClass('hidden');
            $deal_empty.removeClass('c-empty-deal-hidden');
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

        // Save deal on click button (this button could not exists)
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
            $deal_form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });

        function renderDeals(deal, funnel) {

            if (!deal || deal.id <= 0) {
                return '';
            }

            var deal_id = deal.id,
                deal_name = deal.name || '',
                color = '',
                funnel_deleted_html = '';    // if case if funnel is deleted (empty)

            if (funnel && funnel.stages && funnel.stages[deal.stage_id]) {
                color = funnel.stages[deal.stage_id].color || '';
            }

            if ($.isEmptyObject(funnel)) {
                funnel_deleted_html = '<span class="hint">' + that.locales['funnel_deleted'] + '</span>';
            }

            return '<li><a href="javascript:void(0);" class="js-deal-item" data-deal-id="' + deal_id + '">' +
                        '<span class="js-text"><i class="icon16 funnel-state svg-icon" data-color="' + color + '"></i>' +
                        '<b><i>' + deal_name + '</i></b>' +
                        '</span>' + funnel_deleted_html + '</a>' +
                    '</li>';
        }

        function emptyDeal() {
            $visible_link.html('<b><i>'+ that.locales['deal_empty'] +'</i></b>');
            $deal_id.val('none');
            $deal_name_input.val('');
            $deal_name.addClass('c-deal-name-hidden');
            $deal_save.addClass('hidden').removeAttr('title');
            $deal_empty.addClass('c-empty-deal-hidden');
            $select_funnel.addClass('hidden');
            $deals_list.find('li').removeClass('selected');

            if ($deals_dropdown.data('deals_count') > 0) {
                $deals_dropdown.removeClass('hidden');
                $deal_create_new_single_link.addClass('hidden');
            } else {
                $deals_dropdown.addClass('hidden');
                $deal_create_new_single_link.removeClass('hidden');
            }
        }

        function saveDeal() {
            var $created_deal = $wrapper.find('.js-created-deal'),
                $new_deal_stage_icon = $select_stage.find('.js-visible-link .js-text .funnel-state').clone(),
                new_deal_name = $.trim($deal_name_input.val()),
                data = $deal_form.serializeObject();

            data['conversation_id'] = that.conversation_id;

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

            $deal_form.addClass('deal-form-hidden');
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
            var href = $.crm.app_url + "?module=message&action=conversationAssociateDealSave";
            $.post(href, data, function(res) {
                if (res.status === "ok") {
                    $.crm.content.reload();
                } else {
                    $created_deal.html('');
                    $created_deal.addClass('hidden');
                    emptyDeal();
                    $deal_form.removeClass('deal-form-hidden');
                }
            });
        }

        return {
            submit: function () {
                $deal_form.trigger('submit');
            }
        };
    };

    CRMMessageConversationPage.prototype.initDealActions = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $deal = $wrapper.find('.js-conversation-deal');

        $deal.on('click', '.js-attach-other-deal', function (e) {
            e.preventDefault();

            that.loadDealListByContact(function(data) {
                var dialog_url = $.crm.app_url + '?module=messageConversationDeal&action=attachDialog';
                $.get(dialog_url, { id: that.conversation_id }, function (html) {
                    var $dialog = $(html);
                    new CRMDialog({
                        html: $dialog,
                        onOpen: function () {
                            var $deal_block = $dialog.find('.js-conversation-deal'),
                                deal_selector = that.initSelectDeal({
                                    $wrapper: $deal_block,
                                    data: data
                                });
                            $dialog.find('.js-save-button').on('click', function () {
                                deal_selector.submit();
                            });
                        }
                    });
                });
            });

        });

        var detach_deal_xhr = null;
        $deal.on('click', '.js-detach-deal', function (e) {
            e.preventDefault();

            var name = $deal.find('.js-deal-link').text();
            $.crm.confirm.show({
                title: that.locales["deal_detach_title"],
                text: that.locales["deal_detach_text"].replace(/%s/, name),
                button: that.locales["deal_detach_confirm_button"],
                onConfirm: function() {
                    detach_deal_xhr && detach_deal_xhr.abort();

                    that.loadDealListByContact(function (data) {
                        var url = $.crm.app_url + '?module=messageConversationDeal&action=detach';
                        detach_deal_xhr = $.post(url, { id: that.conversation_id })
                            .done(function (r) {
                                if (r && r.status === 'ok') {
                                    var html = r.data && r.data.html;
                                    $deal.html(html);
                                    that.initSelectDeal({
                                        data: data
                                    });
                                }
                            })
                            .always(function () {
                                detach_deal_xhr = null;
                            });
                    });
                }
            });
        });
    };

    CRMMessageConversationPage.prototype.initStickiesAtSection = function() {
        var that = this;

        var FixedBlock = ( function($) {

            FixedBlock = function(options) {
                var that = this;

                // DOM
                that.$window = $(window);
                that.$wrapper = options["$wrapper"];
                that.$section = options["$section"];
                that.$wrapperContainer = that.$wrapper.parent();

                    // VARS
                that.type = (options["type"] || "bottom");

                // DYNAMIC VARS
                that.offset = {};
                that.$clone = false;
                that.is_fixed = false;

                // INIT
                that.initClass();
            };

            FixedBlock.prototype.initClass = function() {
                var that = this,
                    $window = that.$window,
                    resize_timeout = 0;

                $window.on("resize", function() {
                    clearTimeout(resize_timeout);
                    resize_timeout = setTimeout( function() {
                        that.resize();
                    }, 100);
                });

                $window.on("scroll", watcher);

                that.$wrapper.on("resize", function() {
                    that.resize();
                });

                that.init();

                function watcher() {
                    var is_exist = $.contains($window[0].document, that.$wrapper[0]);
                    if (is_exist) {
                        that.onScroll($window.scrollTop());
                    } else {
                        $window.off("scroll", watcher);
                    }
                }

                that.$wrapper.data("block", that);
            };

            FixedBlock.prototype.init = function() {
                var that = this;

                if (!that.$clone) {
                    var $clone = $("<div />");
                    that.$wrapperContainer.append($clone);
                    that.$clone = $clone;
                }

                that.$clone.hide();

                var offset = that.$wrapper.offset();
                that.offset = {
                    left: offset.left,
                    top: offset.top,
                    width: that.$wrapper.outerWidth(),
                    height: that.$wrapper.outerHeight()
                };
            };

            FixedBlock.prototype.resize = function() {
                var that = this;

                switch (that.type) {
                    case "top":
                        that.fix2top(false);
                        break;
                    case "bottom":
                        that.fix2bottom(false);
                        break;
                }

                var offset = that.$wrapper.offset();
                that.offset = {
                    left: offset.left,
                    top: offset.top,
                    width: that.$wrapper.outerWidth(),
                    height: that.$wrapper.outerHeight()
                };

                that.$window.trigger("scroll");
            };

            /**
             * @param {Number} scroll_top
             * */
            FixedBlock.prototype.onScroll = function(scroll_top) {
                var that = this,
                    window_w = that.$window.width(),
                    window_h = that.$window.height();

                // update top for dynamic content
                that.offset.top = that.$wrapperContainer.offset().top;

                switch (that.type) {
                    case "top":
                        var bottom_case = (that.$section ? ((scroll_top + that.offset.height) < that.$section.height() + that.$section.offset().top) : true),
                            use_top_fix = (that.offset.top < scroll_top && bottom_case);

                        that.fix2top(use_top_fix);
                        break;
                    case "bottom":
                        var use_bottom_fix = (that.offset.top && scroll_top + window_h < that.offset.top + that.offset.height);
                        that.fix2bottom(use_bottom_fix);
                        break;
                }

            };

            /**
             * @param {Boolean|Object} set
             * */
            FixedBlock.prototype.fix2top = function(set) {
                var that = this,
                    fixed_class = "is-top-fixed";

                if (set) {
                    that.$wrapper
                        .css({
                            left: that.offset.left,
                            width: that.offset.width
                        })
                        .addClass(fixed_class);

                    that.$clone.css({
                        height: that.offset.height
                    }).show();

                } else {
                    that.$wrapper.removeClass(fixed_class).removeAttr("style");
                    that.$clone.removeAttr("style").hide();
                }

                that.is_fixed = !!set;
            };

            /**
             * @param {Boolean|Object} set
             * */
            FixedBlock.prototype.fix2bottom = function(set) {
                var that = this,
                    fixed_class = "is-bottom-fixed";

                if (set) {
                    that.$wrapper
                        .css({
                            left: that.offset.left,
                            width: that.offset.width
                        })
                        .addClass(fixed_class);

                    that.$clone.css({
                        height: that.offset.height
                    }).show();

                } else {
                    that.$wrapper.removeClass(fixed_class).removeAttr("style");
                    that.$clone.removeAttr("style").hide();
                }

                that.is_fixed = !!set;
            };

            return FixedBlock;

        })(jQuery);

        // DOM

        var $replyWrapper = that.$wrapper.find(".js-reply-wrapper"),
            $headerWrapper = that.$wrapper.find(".js-conversation-header");

        initReply();

        initHeader();

        initScroll();

        function initReply() {
            var $textarea = $replyWrapper.find(".js-textarea"),
                is_changed = false;

            // EVENTS

            $textarea.on("focus", function() {
                toggleView(true);

                $(document).trigger("unmark-new-messages");
            });

            $textarea.on("blur", function() {
                toggleView(false);
            });

            $textarea.on("change paste keyup", function() {
                var value = $(this).val();
                is_changed = ($.trim(value).length > 0);
            });

            // INIT

            new FixedBlock({
                $wrapper: $replyWrapper,
                type: "bottom"
            });

            // FUNCTIONS

            function toggleView(show) {
                var active_class = "is-extended";
                if (show) {
                    $replyWrapper.addClass(active_class).trigger("resize");
                } else {
                    if (!is_changed) {
                        $replyWrapper.removeClass(active_class).trigger("resize");
                    }
                }
            }
        }

        function initHeader() {
            new FixedBlock({
                $wrapper: $headerWrapper,
                type: "top"
            });
        }

        function initScroll() {
            var $top = $("<div class=\"c-scroll-action to-top to-right\"><div class=\"c-icon to-top\"></div></div>"),
                $bottom = $("<div class=\"c-scroll-action to-bottom to-right\"><div class=\"c-icon to-bottom\"></div></div>");

            $top.appendTo($headerWrapper);
            $bottom.appendTo($replyWrapper);

            $top.on("click", function() {
                $("html, body").animate({
                    scrollTop: 0
                }, 500);
            });

            $bottom.on("click", function() {
                that.scroll2bottom(true, true);
            });
        }
    };

    CRMMessageConversationPage.prototype.initChangeResponsible = function() {
        var that = this,
            is_locked = false,
            $wrapper = that.$wrapper.find('.js-responsible-wrapper'),
            $transfer_input = $wrapper.find('.js-owner-autocomplete'),
            $empty_wrapper = that.$wrapper.find('.js-responsible-empty-wrapper'),
            $empty_input = $empty_wrapper.find('.js-responsible-empty-autocomplete');

        if ($wrapper.length && $empty_input.length) {
            // Init empty input
            initAutocomplete($empty_input);
        }

        if ($transfer_input.length) {
            // Init transfer input
            initAutocomplete($transfer_input);
        }

        $empty_wrapper.on('click', '.js-set-extended, .js-unset-extended', function () {
            $empty_wrapper.toggleClass('is-extended');
            if ($empty_wrapper.hasClass('is-extended')) {
                $empty_input.focus();
            }
        });

        $wrapper.on('click', '.js-show-combobox', function () {
            $wrapper.find('.c-conversation-member').addClass('is-edit');
            $wrapper.find('.js-owner-autocomplete').focus();
        });

        $wrapper.on('click', '.js-hide-combobox', function () {
            $wrapper.find('.c-conversation-member').removeClass('is-edit');
        });

        // Old responsible user
        $wrapper.on('click', '.js-remove-owner', function (e) {
            e.preventDefault();
            var name = $.trim( $wrapper.find('.c-name').html());
            $.crm.confirm.show({
                title: that.locales["remove_owner_title"],
                text: that.locales["remove_owner_text"].replace(/%s/, name),
                button: that.locales["remove_owner_button"],
                onConfirm: function() {
                    removeOwner();
                }
            });
        });

        function initAutocomplete($input) {
            var request_url = "?module=autocomplete&type=user";
            if (that.funnel_id > 0) {
                request_url += "&funnel_id="+ that.funnel_id;
            } else {
                request_url += "&contact_id="+ that.contact_id;
            }

            $input.autocomplete({
                appendTo: $input.parent(),
                source: request_url,
                minLength: 0,
                html: true,
                focus: function() {
                    return false;
                },
                select: function( event, ui ) {
                    setOwner(ui.item.id);
                    $input.val("");
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                var $item = $("<li />");

                $item.addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );

                if (!item.rights) {
                    $item.addClass("is-locked");
                    $item.on("click", function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                    });
                }

                return $item;
            };

            $input.on("focus", function(){
                $input.data("uiAutocomplete").search( $input.val() );
            });
        }

        function setOwner(id) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=message&action=changeConversationUser",
                    data = {
                        action: 'set',
                        id: that.conversation_id,
                        user_contact_id: id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        renderContact( $(response.data.html) );
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }

            function renderContact($contact) {
                $wrapper.html($contact);
                $empty_wrapper.addClass('hidden');

                var $new_transer_input = $wrapper.find('.js-owner-autocomplete');

                if ($new_transer_input.length) {
                    initAutocomplete($new_transer_input);
                }
            }
        }

        function removeOwner() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=message&action=changeConversationUser",
                    data = {
                        action: 'remove',
                        id: that.conversation_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        renderRemove();
                        is_locked = false;
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }

            function renderRemove() {
                $wrapper.html('');
                $empty_wrapper.removeClass('hidden');
            }
        }
    };

    CRMMessageConversationPage.prototype.initBackgroundActions = function() {
        var that = this,
            is_locked = false,
            timeout = 0;

        runner();

        function runner() {
            clearTimeout(timeout);
            timeout = setTimeout(request, that.check_interval);
        }

        function request() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=message&action=conversationIdCheck",
                    data = {
                        id: that.conversation_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var is_changed = (response.data !== that.last_message_id);
                        if (is_changed) {

                            updateMessages().then( function(new_messages) {
                                if (new_messages.length) { that.scroll2bottom(true, true); }
                                runner();
                            });

                        } else {
                            runner();
                        }
                    }
                }, 'json').always( function() {
                    is_locked = false;
                });
            }
        }

        /**
         * @return Promise
         * */
        function updateMessages() {
            var $deferred = $.Deferred();

            $.get(location.href, function(html) {
                var last_message_id = that.last_message_id,
                    $list = that.$wrapper.find(".js-messages-list").first(),
                    $messages = $(html).find(".js-messages-list .js-message-wrapper"),
                    new_messages = [];

                if ($list.length) {
                    $messages.each( function() {
                        var $message = $(this),
                            message_id = $message.data("id");

                        if (message_id > that.last_message_id) {
                            $list.append($message);
                            markMessage($message);

                            new_messages.push({
                                id: message_id,
                                $message: $message
                            });

                            last_message_id = message_id;
                        }
                    });

                    that.last_message_id = last_message_id;
                }

                $deferred.resolve(new_messages);

                function markMessage($message) {
                    var $document = $(document),
                        active_class = "is-new",
                        time = 10000;

                    $message.addClass(active_class);
                    $message.on("hover", unmark);
                    $document.on("unmark-new-messages", unmark);

                    setTimeout(function() {
                        var is_exist = $.contains(document, $message[0]);
                        if (is_exist) { unmark(); }
                    }, time);

                    function unmark() {
                        $message.removeClass(active_class);
                        $message.off("hover", unmark);
                        $document.off("unmark-new-messages", unmark);
                    }
                }
            });

            return $deferred.promise();
        }
    };

    CRMMessageConversationPage.prototype.initMessageActions = function() {
        var that = this;

        // REPLY
        that.$wrapper.on('click', '.js-message-reply', function(event) {
            event.preventDefault();
            showReplayDialog($(this));
        });

        function showReplayDialog($link) {
            var $icon = $link.find(".icon16"),
                message_id = $link.closest(".js-message-wrapper").data('id');

            showLoading(true);

            var href = $.crm.app_url + '?module=message&action=writeReplyDialog',
                data = { id: message_id };

            $.post(href, data, function(html) {
                new CRMDialog({
                    html: html
                });
            }).always( function() {
                showLoading(false);
            });

            function showLoading(show) {
                if (!$icon.length) { return false; }

                var default_class = "rotate-left",
                    loading_class = "loading";

                if (show) {
                    $icon.removeClass(default_class).addClass(loading_class);
                } else {
                    $icon.removeClass(loading_class).addClass(default_class);
                }
            }
        }

        // FORWARD
        that.$wrapper.on('click', '.js-message-forward', function(event) {
            event.preventDefault();
            showForwardDialog($(this));
        });

        function showForwardDialog($link) {
            var $icon = $link.find(".icon16"),
                message_id = $link.closest(".js-message-wrapper").data('id');

            showLoading(true);

            var href = $.crm.app_url + '?module=message&action=writeForwardDialog',
                data = { id: message_id };

            $.post(href, data, function(html) {
                new CRMDialog({
                    html: html
                });
            }).always( function() {
                showLoading(false);
            });

            function showLoading(show) {
                if (!$icon.length) { return false; }

                var default_class = "rotate-right",
                    loading_class = "loading";

                if (show) {
                    $icon.removeClass(default_class).addClass(loading_class);
                } else {
                    $icon.removeClass(loading_class).addClass(default_class);
                }
            }
        }
    };

    CRMMessageConversationPage.prototype.initDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-conversation", function(event) {
            event.preventDefault();

            if (!is_locked) {
                $.crm.confirm.show({
                    title: that.locales["delete_conversation_title"],
                    button: that.locales["delete_conversation_button"],
                    onConfirm: deleteConversation
                });
            }
        });

        function deleteConversation() {
            is_locked = true;

            var href = $.crm.app_url + "?module=message&action=conversationIdDelete",
                data = {
                    id: that.conversation_id
                };

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $.crm.content.load($.crm.app_url + "message/");
                }
            }).always( function() {
                is_locked = false;
            }, "json");
        }
    };

    CRMMessageConversationPage.prototype.initSendEmail = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-message-dialog", showDialog);

        function showDialog(event) {
            event.preventDefault();

            var $link = $(this),
                contact_id = $link.data("id"),
                email = $link.data("email");

            if (!is_locked && contact_id) {
                is_locked = true;

                var href = "?module=message&action=writeNewDialog",
                    data = {
                        contact_id: contact_id,
                        email: email
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    /**
     * @param {Boolean?} force - do now or use timeout
     * @param {Boolean?} animate - use animate
     * */
    CRMMessageConversationPage.prototype.scroll2bottom = function(force, animate) {
        var that = this,
            $window = $(window),
            window_h = $window.height();

        var do_scroll = true;

        if (force) {
            render();

        } else {
            if ($.crm.is_page_loaded) {
                runner();

            } else {
                $window
                    .one("scroll", function() {
                        do_scroll = false;
                    })
                    .one("load", runner);
            }
        }

        function runner() {
            setTimeout( function() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist && do_scroll) { render(); }
            }, 10);
        }

        function render() {
            var document_h = $(document).height();
            if (window_h < document_h) {

                if (animate) {
                    $("html, body").animate({
                        scrollTop: (document_h - window_h)
                    }, 500);

                } else {
                    $window.scrollTop(document_h);
                }
            }
        }
    };

    return CRMMessageConversationPage;

})(jQuery);

var CRMEmailConversationEmailSender = ( function($) {

    CRMEmailConversationEmailSender = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$replySection = that.$wrapper.closest(".js-reply-wrapper");
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$wrapper.find(".js-wysiwyg");
        that.$sender_email_select = that.$wrapper.find(".js-sender-email-select");
        that.$sender_email = that.$wrapper.find(".js-sender-email");

        // VARS
        that.file_template_html = options["file_template_html"];
        that.max_upload_size = options["max_upload_size"];
        that.locales = options["locales"];
        that.hash = options["hash"];
        that.send_action_url = options["send_action_url"];
        that.body = options["body"] || '';
        that.deal_id = options["deal_id"];
        that.is_changed = false;

        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS

        // INIT
        that.initClass();

        that.filesController = that.getFilesController();
    };

    CRMEmailConversationEmailSender.prototype.initClass = function() {
        var that = this;

        that.senderEmailSelect();
        //
        that.initWYSIWYG();
        //
        that.initVisiblityWatcher();
        //
        that.initSave();
        //
        that.initPersonalSettingsDialog();
        //
        that.initEmailCopy();
    };

    CRMEmailConversationEmailSender.prototype.initVisiblityWatcher = function() {
        var that = this,
            $replySection = that.$replySection,
            is_changed = false;

        var $visibleTextarea = $replySection.find(".js-visible-textarea");

        $visibleTextarea.on("focus", function() {
            toggleView(true);
        });

        $replySection.on("click", ".js-revert", function() {
            toggleView(false);
        });

        // if (that.is_changed) {
        //
        // }

        /**
         * @param {Boolean} show
         * */
        function toggleView(show) {
            var active_class = "is-extended";
            if (show) {
                $replySection.addClass(active_class).trigger("resize");
            } else {
                if (!is_changed) {
                    $replySection.removeClass(active_class).trigger("resize");
                }
            }
        }
    };

    CRMEmailConversationEmailSender.prototype.senderEmailSelect = function() {
        var that = this;

        that.$wrapper.on("change", that.$sender_email_select, function(event) {
            event.preventDefault();
            that.$sender_email.val(that.$sender_email_select.val());
        });
    };

    CRMEmailConversationEmailSender.prototype.getFilesController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-files-wrapper");

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = $wrapper.find(".js-drop-area"),
            $dropText = $wrapper.find(".js-drop-text"),
            $fileField = $wrapper.find(".js-drop-field"),
            $uploadList = $wrapper.find(".c-upload-list"),
            file_template_html = that.file_template_html;

        // DATA
        var uri = $.crm.app_url + "?module=file&action=uploadTmp";

        // DYNAMIC VARS
        var files_storage = [],
            upload_file_count = 0,
            hover_timeout = 0;

        // VARS
        var hover_class = "is-hover";

        // Attach
        $fileField.on("change", function(event) {
            event.preventDefault();
            addFiles(this.files);
        });

        // Drop
        $dropArea.on("drop", function(event) {
            event.preventDefault();
            addFiles(event.originalEvent.dataTransfer.files);
        });

        // Drag
        $dropArea.on("dragover", onHover);

        // delete
        $wrapper.on("click", ".js-file-delete", function(event) {
            event.preventDefault();
            deleteFile( $(this).closest(".c-upload-item") )
        });

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
            }
        }

        function renderFile(file) {
            var $uploadItem = $(file_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

            $uploadList.prepend($uploadItem);

            return $uploadItem;
        }

        function deleteFile($file) {
            var result = [];

            $.each(files_storage, function(index, item) {
                if ($file[0] !== item.$file[0]) {
                    result.push(item);
                } else {
                    $file.remove();
                }
            });

            files_storage = result;
        }

        function uploadFiles(data, callback) {
            var is_locked = false;

            var afterUploadFiles = ( callback ? callback : function() {} );

            if (files_storage.length) {
                upload_file_count = files_storage.length;

                $.each(files_storage, function(index, file_item) {
                    uploadFile(file_item);
                });
            } else {
                afterUploadFiles();
            }

            function uploadFile(file_item) {
                is_locked = true;

                var $file = file_item.$file,
                    $bar = $file.find(".js-bar"),
                    $status = $file.find(".js-status");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    request();
                } else {
                    $status.addClass("errormsg").text( that.locales["file_size"] );
                    $file.find(".c-progress-wrapper").remove();
                    is_locked = false;
                    setTimeout( function() {
                        if ($.contains(document, $file[0])) {
                            $file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                afterUploadFiles();
                            }
                        }
                    }, 2000);
                }

                //

                function request() {
                    var formData = new FormData();

                    var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        formData.append("_csrf", csrf);
                    }

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                formData.append(item.name, item.value);
                            }
                        });
                    }

                    formData.append("file_size", file_item.file.size);
                    formData.append("files", file_item.file);
                    formData.append("file_end", 1);

                    // Ajax request
                    $.ajax({
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function(event){
                                if (event.lengthComputable) {
                                    var percent = parseInt( (event.loaded / event.total) * 100 ),
                                        color = getColor(percent);

                                    $bar
                                        .css("background-color", color)
                                        .width(percent + "%");

                                    $status.text(percent + "%");
                                }
                            }, false);
                            return xhr;
                        },
                        url: uri,
                        data: formData,
                        cache: false,
                        contentType: false,
                        processData: false,
                        type: 'POST',
                        success: function(data){
                            $status.text( $status.data("success") );
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    $file.remove();
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
                                }
                            }, 2000);
                        }
                    }).always( function () {
                        is_locked = false;
                    });
                }

                function getColor(percent) {
                    var start = [247,198,174],
                        end = [174,247,196],
                        result = [];

                    for (var i = 0; i < start.length; i++) {
                        var rgb = start[i] + (((end[i] - start[i])/100) * percent);
                        result.push(rgb);
                    }
                    return "rgb(" + result.join(",") + ")";
                }
            }
        }

        function onHover(event) {
            event.preventDefault();
            $dropArea.addClass(hover_class);
            $dropText.text( $dropText.data("hover") );
            clearTimeout(hover_timeout);
            hover_timeout = setTimeout( function () {
                $dropArea.removeClass(hover_class);
                $dropText.text( $dropText.data("default") );
            }, 100);
        }

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMEmailConversationEmailSender.prototype.initWYSIWYG = function() {
        var that = this,
            $textarea = that.$textarea;

        $.crm.initWYSIWYG($textarea, {
            maxHeight: 150,
            allowedAttr: [['section', 'data-role']],
            callbacks: {
                change: function() {
                    that.is_changed = true;
                }
            }
        });

        if (that.body) {
            $textarea.redactor('code.set', that.body);
        }
    };

    CRMEmailConversationEmailSender.prototype.initSave = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", function(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var $submitButton = that.$form.find(".js-submit-button"),
                    $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');

                $submitButton.removeClass("blue").attr("disabled", true);
                $loading.insertBefore($submitButton);

                var data = [
                    {
                        "name": "hash",
                        "value": that.hash
                    }
                ];

                that.filesController.uploadFiles(data, submit);
            }
        });

        var submit = function() {
            var href = that.send_action_url,
                data = that.$form.serializeArray();

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $.crm.content.reload();
                } else {
                    alert("error");
                }
            }, "json").always( function () {
                is_locked = false;
            });
        }
    };

    CRMEmailConversationEmailSender.prototype.initPersonalSettingsDialog = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-personal-settings-dialog", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=email&action=personalSettingsDialog",
                    data = {};

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html,
                        options: {
                            onSave: function(data) {
                                var $editor = that.$textarea.redactor('core.editor');
                                $editor.find('[data-role="c-email-signature"]').html(data['email_signature'] || '');
                                that.$wrapper.find('.js-sender-name').text(data['sender_name'] || '');
                            }
                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMEmailConversationEmailSender.prototype.initEmailCopy = function() {
        var that = this,
            $wrapper = that.$wrapper.find('.email-copy-wrapper'),
            $link_icon = that.$wrapper.find('.js-email-copy-link-icon'),
            $copy_area = $wrapper.find('.email-copy-area'),
            $copy_text = $copy_area.find('.email-copy-text'),
            $copy_input = $copy_area.find('.email-copy-input'),
            $deal_participants_area = $wrapper.find('.deal-participants-area'),
            $wrapper_collapsed = that.$wrapper.find('.email-copy-wrapper-collapsed');

        // Init autocomplete
        $copy_input.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&emailcomplete=true",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var data = '<i class="icon16 userpic20" style="background-image: url('+ ui.item.photo_url +');"></i><b>'+ ui.item.name +'</b>';
                addToCC(ui.item.id, ui.item.email, data);
                $copy_input.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $copy_input.on("focus", function(){
            $copy_input.data("uiAutocomplete").search( $copy_input.val() );
        });
        /* * * * */

        that.$wrapper.on('click', '.js-email-copy-link, .email-copy-text-collapsed', function(e) {
            e.preventDefault();

            $wrapper.toggleClass('email-copy-wrapper-block');

            if ($wrapper.is(':visible')) {
                $link_icon.removeClass('rarr').addClass('darr');
                $copy_input.focus();

                $wrapper_collapsed.removeClass('email-copy-wrapper-collapsed-block'); // Close collapsed, if openig CC editor
            } else {
                $link_icon.removeClass('darr').addClass('rarr');

                if (that.$wrapper.find('.email-copy-text-collapsed').children().length) {
                    $wrapper_collapsed.addClass('email-copy-wrapper-collapsed-block');
                }
            }

            that.$wrapper.closest(".js-reply-wrapper").trigger("resize");
        });

        $copy_area.on('click', function() {
            $copy_input.focus();
        });

        // Add participants in the deal to cc
        $deal_participants_area.on('click', '.email-copy-user', function () {
            var contact_id = $(this).data('cc-contact-id'),
                contact_email = $(this).data('cc-email'),
                contact_data = $(this).html();

            addToCC(contact_id, contact_email, contact_data);
        });

        // Add to cc on <focusout>
        $copy_input.on('focusout', function (e) {
            handlerCC();
        });

        // Add to cc on press [Enter]
        $copy_input.on('keydown', function (e) {
            if (e.keyCode==13) {
                e.preventDefault();
                handlerCC();
            }
        });

        // Remove from cc
        $copy_text.on('click', '.js-remove-cc', function (e) {
            e.preventDefault();
            var $removed = $(this).parent('.email-copy-user'),
                removed_email = $removed.data('email');
            $removed.remove();
            that.$wrapper.find('.email-copy-text-collapsed').children('[data-email="'+removed_email+'"]').remove();
        });

        // Remove from cc last contact on press [Backspace]
        $copy_input.on('keydown', function (e) {
            if (e.keyCode==8 && $copy_input.val().length == 0) {
                var $removed = that.$wrapper.find('.email-copy-text .email-copy-user:last'),
                    removed_email = $removed.data('email');
                $removed.remove();
                that.$wrapper.find('.email-copy-text-collapsed').children('[data-email="'+removed_email+'"]').remove();
                $copy_input.focus(); // for init autocomplete
            }
        });

        function handlerCC() {
            var emails = $.trim( $copy_input.val()).split(/[,:;]/);
            if (emails[0].length) {

                $.each(emails, function( i, email ) {
                    var cc_arr = $.trim(email).split(/\s+/),
                        email_index = false,
                        email = null,
                        name = null;

                    // Find email
                    $.each(cc_arr, function(i, value){
                        if ($.crm.check.email(value) && !email_index) {
                            email = value.replace(/\<|\>/g, '');
                            email_index = i;
                        }
                    });
                    // Delete email from array (if any)
                    if (email_index !== false) {
                        cc_arr.splice(email_index, 1);
                    }
                    // The rest is the name
                    name = cc_arr.join(" ");

                    // If there is both a name and an e-mail address - all is ok.
                    if (email && name) {
                        addToCCWithName(0, email, name);
                    }

                    if (email && !name) {
                        addToCC(0, email, email);
                    }

                    if (name && !email) {
                        $copy_input.addClass('shake animated');
                        setTimeout(function () {
                            $copy_input.removeClass('shake animated');
                        }, 500);
                    }

                    email_index = false;
                    email = null;
                    name = null;

                });
                return false;

            }
        }

        function addToCC(id, email, data) {
            if ((!$copy_text.children('[data-email="'+email+'"]').length || id === "0") && email.length) {
                that.$wrapper.find('.email-copy-input-div').before('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-contact-id="' + $.crm.escape(id) + '" data-email="' + $.crm.escape(email) + '">' + data + ' <a title="'+ that.locales["remove_form_cc"] +'" class="remove-cc js-remove-cc">x</a> <input name="cc[' + $.crm.escape(email) + '][email]" type="hidden" value="' + $.crm.escape(email) + '" /><input name="cc[' + $.crm.escape(email) + '][id]" type="hidden" value="' + $.crm.escape(id) + '" /></div>');
                that.$wrapper.find('.email-copy-text-collapsed').append('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-email="' + $.crm.escape(email) + '">' + data + '</div>');
            }
            $copy_input.val("").focus();
        }

        function addToCCWithName(id, email, name) {
            if ((!$copy_text.children('[data-email="'+email+'"]').length || id === "0") && email.length) {
                that.$wrapper.find('.email-copy-input-div').before('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-contact-id="' + id + '" data-email="' + $.crm.escape(email) + '">' + $.crm.escape(name) + ' <a title="'+ that.locales["remove_form_cc"] +'" class="remove-cc js-remove-cc">x</a> <input name="cc[' + $.crm.escape(email) + '][email]" type="hidden" value="' + $.crm.escape(email) + '" /><input name="cc[' + $.crm.escape(email) + '][id]" type="hidden" value="' + $.crm.escape(id) + '" /><input name="cc[' + $.crm.escape(email) + '][name]" type="hidden" value="' + $.crm.escape(name) + '" /></div>');
                that.$wrapper.find('.email-copy-text-collapsed').append('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-email="' + $.crm.escape(email) + '">' + $.crm.escape(name) + '</div>');
            }
            $copy_input.val("").focus();
        }
    };

    return CRMEmailConversationEmailSender;

})(jQuery);

var CRMImConversationSection = ( function($) {

    CRMImConversationSection = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$form.find('.js-textarea');

        // VARS
        that.send_action_url = options["send_action_url"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMImConversationSection.prototype.initClass = function() {
        var that = this;

        that.initSubmit();
    };

    CRMImConversationSection.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $textarea = that.$textarea;

        $textarea.on("keydown", function(e) {
            var use_enter = (e.keyCode === 13 || e.keyCode === 10);
            if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
                e.preventDefault();
                that.$form.submit();
            }
        });

        $textarea.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46);

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var value = $textarea.val(),
                        position = $textarea.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;

                    $textarea.val(result);
                }

                toggleHeight();

            } else if (is_backspace || is_delete) {
                toggleHeight();

            } else {

                if ($textarea[0].scrollHeight > $textarea.outerHeight()) {
                    toggleHeight();
                }

            }
        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            if (!is_locked) {
                is_locked = true;

                var href = that.send_action_url,
                    data = that.$form.serializeArray();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                    } else {
                        $textarea.addClass('shake animated').focus();
                        setTimeout(function(){
                            $textarea.removeClass('shake animated').focus();
                        },500);
                        is_locked = false;
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        });

        function toggleHeight() {
            $textarea.css("min-height", 0);

            var scroll_h = $textarea[0].scrollHeight,
                limit = (18 * 8 + 8);

            if (scroll_h > limit) {
                scroll_h = limit;
            }

            scroll_h += 2;

            $textarea.css("min-height", scroll_h + "px");

            that.$wrapper.trigger("resize");
        }
    };

    return CRMImConversationSection;

})(jQuery);

var CRMConversationAssociateDealDialog = ( function($) {
    CRMConversationAssociateDealDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$footer = that.$wrapper.find('.js-dialog-footer');
        that.$submit = that.$form.find(".js-submit");
        that.$deal_name = that.$form.find('.js-deal-name');
        that.$deal_funnel = that.$form.find('.js-select-deal-funnel');
        that.$deal_stage = that.$form.find('.js-select-deal-stage');
        that.$deal_id = that.$form.find('.js-deal-id');

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMConversationAssociateDealDialog.prototype.initClass = function() {
        var that = this;

        $.crm.renderSVG(that.$wrapper);
        //
        that.initSelectDeal();
        //
        that.initSubmit();
    };

    CRMConversationAssociateDealDialog.prototype.initSelectDeal = function() {
        var that = this,
            $visible_link = that.$form.find('.js-select-deal .js-visible-link .js-text'),
            $select_funnel = that.$form.find('.js-select-funnel'),
            $deals_list = that.$form.find('.js-deals-list'),
            $deal_name_field = that.$form.find('.js-deal-name-field');

        that.$form.on('click', '.js-create-new-deal', function () {
            that.$submit.addClass('yellow').removeAttr("disabled");
            var new_deal = $(this).find('.js-text').html();
            that.deal_selected = true;
            $select_funnel.removeClass('hidden');
            $deal_name_field.removeClass('hidden');
            that.$deal_name.focus();
            $visible_link.html(new_deal);
            that.$deal_id.val('0');
        });

        that.$form.on('click', '.js-deal-item', function () {
            that.$submit.addClass('yellow').removeAttr("disabled");
            var new_deal = $(this).find('.js-text').html();
            that.deal_selected = true;
            $deals_list.find('li').removeClass('selected');
            $(this).parent().addClass('selected');
            $visible_link.html(new_deal);
            $select_funnel.addClass('hidden');
            $deal_name_field.addClass('hidden');
            that.$deal_name.val("");
            that.$deal_id.val($(this).data('deal-id'));
        });

        $deals_list.on('click', function () {
            $deals_list.hide();
            setTimeout( function() {
                $deals_list.removeAttr("style");
            }, 200);
        });

        //
        that.$form.on('change', '.js-select-deal-funnel', function() {
            that.$form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });
    };

    CRMConversationAssociateDealDialog.prototype.initSubmit = function() {
        var that = this;

        that.$form.on("submit", function(e) {
            e.preventDefault();

            if (!that.$deal_id.val()) {
                that.$wrapper.addClass('shake animated');
                setTimeout(function(){
                    that.$wrapper.removeClass('shake animated');
                },500);
                return false;
            }

            if (that.$deal_id.val() == 0 && !$.trim(that.$deal_name.val())) {
                that.$deal_name.addClass('shake animated').focus();
                setTimeout(function(){
                    that.$deal_name.removeClass('shake animated').focus();
                },500);
                return false;
            }

            submit();
        });

        function submit() {
            var $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>'),
                href = $.crm.app_url + "?module=message&action=conversationAssociateDealSave",
                data = that.$form.serializeArray();

            that.$submit.prop('disabled', true);
            that.$footer.append($loading);

            $.post(href, data, function(res){
                if (res.status === "ok") {
                    $.crm.content.reload();
                } else {
                    that.$submit.prop('disabled', false);
                    $loading.remove();
                }
            });
        }
    };

    return CRMConversationAssociateDealDialog;

})(jQuery);
