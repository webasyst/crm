
var CRMMessageConversationPage = ( function($) {

    CRMMessageConversationPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.locales = options["locales"];
        that.conversation_id = options["conversation_id"];
        that.contact_id = options["contact_id"];
        that.filter_contact_id = options["filter_contact_id"];
        that.filter_deal_id = options["filter_deal_id"];
        that.funnel_id = options["funnel_id"];
        that.last_message_id = options["last_message_id"];
        that.check_interval = options["check_interval"];
        that.$replySection = that.$wrapper.find(".c-reply-section");
        that.iframe = options["iframe"];
        that.old_message_id = options["old_message_id"];
        that.$dropdown_name = that.$wrapper.find("#dropdown-c-name");
        that.short_link = options["short_link"];
        that.do_confirm_verification = options["do_confirm_verification"];
        // DYNAMIC VARS
        // INIT
        that.initClass();
    };

    CRMMessageConversationPage.prototype.handleYaMaps = function($messages) {
        // yandex maps fix hack
        var $ya_maps = $messages.find('.js-map-block');
        if ($ya_maps.length > 0) {
            setTimeout(() => hackYaMaps($ya_maps), 200);
        }

        function hackYaMaps($ya_maps, $last_call = false) {
            if ($ya_maps.has( ":last-child" ).length === $ya_maps.length) {
                $ya_maps.children().not( ":last-child" ).remove();
                if (!$last_call) {
                    setTimeout(() => hackYaMaps($ya_maps, true), 500);
                }
            } else {
                setTimeout(() => hackYaMaps($ya_maps), 200);
            }
        }
    }

    CRMMessageConversationPage.prototype.initClass = function() {
        var that = this;

        // deal already attached, no need to init selected
        if (!that.$wrapper.find('.js-deal-link').length) {
            that.initSelectDeal();
        }

        if (that.short_link) {
            that.setLinkWithId();
        }

        that.initDealActions();

        that.initChangeResponsible();
        //
        that.initBackgroundActions();
        //
       // that.initMessageActions();
        //
        that.initDelete();
        //
        that.initMessageDeleteLink();

        function iOS() {
            return [
              'iPad Simulator',
              'iPhone Simulator',
              'iPod Simulator',
              'iPad',
              'iPhone',
              'iPod'
            ].includes(navigator.platform)
            // iPad on iOS 13 detection
            || (navigator.userAgent.includes("Mac") && "ontouchend" in document)
          }

        function onImagesLoaded() {
            var images = that.$wrapper[0].getElementsByTagName("img"),
            videos = that.$wrapper[0].getElementsByTagName("video");
            var loaded = images.length + videos.length;

            /*if (iOS()) {
                setTimeout(function() {
                    const doc = document.documentElement;
                    doc.style.setProperty('--doc-height', 'calc(100vh - 20px)')
                  }, 1);
            }*/
            /*setTimeout(function() {
                const width = $(window).width();
                if (width <= 760) $('.sidebar.rail.width-adaptive').css('position', 'relative').css('top', '0');
              }, 1);*/

            const documentHeight = () => {
                const doc = document.documentElement
            doc.style.setProperty('--doc-height', window.innerHeight + 'px')
            console.log(window.innerHeight);
            }
            //$(window).on('resize', documentHeight)
           // documentHeight()

            function checkLoadingData() {
                if (loaded == 0) {
                    that.scroll2bottom(false, false);
                    return
                }
            }

            checkLoadingData();

             for (var i = 0; i < videos.length; i++) {
                if (videos[i].readyState >= 3) {
                    loaded--;
                    checkLoadingData();
                }
                else {

                    videos[i].addEventListener('loadeddata', function() {
                        loaded--;
                        checkLoadingData();
                    });

                    videos[i].addEventListener("error", function() {
                        loaded--;
                        checkLoadingData();
                    });
                }
            }

            for (var i = 0; i < images.length; i++) {
                if (images[i].complete) {
                    loaded--;
                    checkLoadingData();
                }
                else {

                    images[i].addEventListener("load", function() {
                        loaded--;
                        checkLoadingData();
                    });

                    images[i].addEventListener("error", function() {
                        loaded--;
                        checkLoadingData();
                    });
                }
            }

            window.document.documentElement.dispatchEvent(new CustomEvent('wa-gallery-load', {
                detail: {
                    forceFullPreview: true,
                },
            }));
        }

        $(document).ready(onImagesLoaded);

        // for long render bloc

        //that.initStickiesAtSection();

        // when click on email link in contact block
        //that.initSendEmail();

       // that.initVisiblityWatcher();

        that.initSingleMessage();

        that.initToggleSidebar();

        that.initLazyLoading();

        that.initBlockquotToggle();

        that.initScrollToBottom();

        that.initAuxDropdown();

    };

    CRMMessageConversationPage.prototype.setLinkWithId = function () {
        var that = this,
            current_url = window.location.href;

        if (current_url.slice(-8).includes('message')) {
            const new_url = current_url + 'conversation/' + that.conversation_id + '/';
            history.replaceState({ //replace location url and history
                reload: true,
                content_uri: new_url
            }, "", new_url  );
        }
    }

    CRMMessageConversationPage.prototype.initAuxDropdown = function () {
        var that = this,
            $dropdown_auth = that.$wrapper.find(".js-request-auth");

        $dropdown_auth.click(function () {
            if (that.do_confirm_verification) {
                $.waDialog.confirm({
                    title: that.locales["verify_confirm_title"],
                    text: that.locales["verify_confirm_text"]
                        + '<div class="custom-mt-24"><label><span class="wa-checkbox"><input type="checkbox" value="1" class="js-verification-not-show-confirm-checkbox"><span><span class="icon"><i class="fas fa-check"></i></span></span></span> '
                        + that.locales["verify_no_more_confirmation_label"]
                        + '</label></div>',
                    success_button_title: that.locales["verify_confirm_button"],
                    cancel_button_title: $.crm.locales['cancel'],
                    cancel_button_class: 'light-gray',
                    onSuccess: function() {
                        const no_more_confirmation = $(".js-verification-not-show-confirm-checkbox").is(':checked');
                        requestVerification(no_more_confirmation);
                    }
                });
            } else {
                requestVerification();
            }
        });

        function requestVerification(no_more_confirmation = false) {
            var href = '?module=message&action=verification',
                params = {
                    message_id: that.last_message_id,
                    no_more_confirmation: no_more_confirmation ? 1 : 0
                };

            $.post(href, params, function(data) {
                if (data.status == "ok") {
                    $(document).trigger('msg_conversation_update');
                } else {
                    $.waDialog.alert({
                        text: data.errors.message || "Unknown error",
                        button_title: that.locales["close"],
                        button_class: 'gray',
                    });
                }
            });
        }
    }

    CRMMessageConversationPage.prototype.initBlockquotToggle = function () {
        var that = this,
            $messages = that.$wrapper.find('.c-email-messages-list').find('.c-message-body');

        $.each($messages, function (i, message) {

            if (!$(message).find('.js-blockquote-toggle').length) {
                const $bqElements = $.merge($(message).children('blockquote'), $(message).children(':not(blockquote)').children('blockquote'));

                $.each($bqElements, function (i, bq) {

                    var $blockquote_icon = $('<span class="button light-gray custom-mb-4 size-14 js-blockquote-toggle"><i class="fas fa-ellipsis-h"></i></span>');
                    $blockquote_icon.insertBefore(bq);
                    $blockquote_icon.on('click', function(event) {
                        $(bq).slideToggle(400);
                    });
                })
            }
        });
    }

    CRMMessageConversationPage.prototype.initLazyLoading = function () {
        var that = this,
            is_locked = false,
            //$window = $(window),
            $list = that.$wrapper.find('.js-messages-list'),
            $blank_list = that.$wrapper.find('.c-conversation-body--blank'),
            $transparent_cover = $list.find('.js-messages-list--transparent-layer'),
            message_count = $list.find('.js-message-wrapper').length,
            prevScrollH = that.$wrapper.prop('scrollHeight');

            that.current_page = 1;


        function startLazyLoading() {
            var $loader = $list.find(".js-lazy-load");

            is_locked = false;
            if (that.current_page == 1 && message_count < 10 ) $loader.remove();
            else if ($loader.length) {
                $list.on("scroll.lazy touchmove.lazy", useMain);
                that.$wrapper.one("bigWindowEvent.lazy", useMain);
            }

            function useMain() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    onScroll($loader);
                } else {
                    $list.off("scroll.lazy touchmove.lazy", useMain);
                }
            }
        }

            function onScroll($loader) {

                var scroll_top = $list.scrollTop();

                if (scroll_top <= 0) {
                    if (!is_locked) {
                        $list.off(".lazy");
                        load($loader);
                    }
                }
            }

            function load($loader) {
                var href = $.crm.app_url + '?module=messageConversationId&id=' + that.conversation_id + '&old_message_id=' + that.old_message_id;
                is_locked = true;

                $.ajax({
                    url: href,
                    type: 'POST',
                    dataType: 'html',
                    beforeSend: function(){
                        $transparent_cover.show();
                        prevScrollH = $list.prop('scrollHeight');
                    },
                    complete: function(){
                        //$transparent_cover.hide();
                        //that.$wrapper.css('overflow-y', "overlay");
                    },
                    data: {
                        delay: 1
                    },
                    success: function(data){
                        var $new_list = $(data).find('.js-messages-list');
                        var $new_list_elements = $(data).find('.js-message-wrapper');
                        var $new_list_count = $new_list_elements.length;
                        if ($new_list_count) {
                            that.old_message_id = $new_list_elements.eq(0).data('id');
                            if ($new_list_count < 10) $new_list.find(".js-lazy-load").remove();
                            var $new_list_html = $new_list.html();
                            $blank_list.append($new_list_html)
                            onImagesLoaded();

                            function onImagesLoaded() {

                                var images = $blank_list[0].getElementsByTagName("img");
                                var newImagesCount = images.length;

                                if (newImagesCount == 0) {
                                    scrollToTop();
                                    return
                                }

                                for (var i = 0; i < images.length; i++) {
                                    if (images[i].complete) {
                                        newImagesCount--;
                                    }
                                    else {
                                        images[i].addEventListener("load", function() {
                                            newImagesCount--;
                                            if (newImagesCount == 0) {
                                                scrollToTop()
                                            }
                                            window.document.documentElement.dispatchEvent(new CustomEvent('wa-gallery-load', {
                                                detail: {
                                                    forceFullPreview: true,
                                                    timeout: 500
                                                },
                                            }));
                                        });

                                        images[i].addEventListener("error", function() {
                                            newImagesCount--;
                                            if (newImagesCount == 0) {
                                                scrollToTop()
                                            }
                                        });
                                    }
                                    if (newImagesCount == 0) {
                                        scrollToTop()
                                    }
                                }
                            }

                            function scrollToTop() {
                                if ($loader) $loader.remove();
                                $list.prepend($blank_list.html());

                                that.handleYaMaps($list);
                                that.initBlockquotToggle();

                                $blank_list.html('');
                                $list.scrollTop($list.prop('scrollHeight') - prevScrollH);
                                prevScrollH = $list.prop('scrollHeight');
                                $transparent_cover.hide();
                                startLazyLoading();
                            }
                        } else {
                            if ($loader) $loader.remove();
                        }
                        that.current_page++;
                        startLazyLoading();
                    }
                });
            }

        startLazyLoading();
        $(document).on('new_message_lazy', (event) => {
            startLazyLoading();
        });
    }

    CRMMessageConversationPage.prototype.initVisiblityWatcher = function() {
        var that = this,
            $replySection = that.$replySection,
            is_changed = false;

        $replySection.on("click", ".js-revert", function() {
            toggleView(false);
        });

        $replySection.on("click", ".js-message-resize", function() {
            toggleView(false);
        });

        $replySection.on("click", ".js-message-resize-open", function() {
            toggleView(true);
        });


        /**
         * @param {Boolean} show
         * */
        function toggleView(show) {
            var active_class = "is-extended";
            if (show) {
                $replySection.addClass(active_class).trigger("resize");
                that.$wrapper.addClass('scroll-lock');
            } else {
                if (!is_changed) {
                    $replySection.removeClass(active_class).trigger("resize");
                    that.$wrapper.removeClass('scroll-lock');
                }
            }
        }
    };

    CRMMessageConversationPage.prototype.loadDealListByContact = function(callback) {

        callback = callback || function () {};

        var contact_id = this.contact_id;
        if (contact_id <= 0 || this.iframe) {
            callback({});
            return;
        }

        var href = '?module=deal&action=byContact&only_existing_stage=1&id=' + contact_id;
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

        };

        var $deal_selector_wrapper = $wrapper.find('.js-deal-selector-control-wrapper'),
            $deal_form = $deal_selector_wrapper.find('.deal-form'),
            $deal_name = $deal_form.find('.js-deal-name'),
            $deal_name_input = $deal_form.find('.js-deal-name-input'),
            $deal_save_button = $wrapper.closest('.js-conversation-deal-attach-dialog').find('.js-save-button'),
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
            $deal_save_button.addClass('yellow');
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

            $deal_save_button.addClass('yellow');
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
                        '<span class="flexbox js-text"><i class="' + (funnel?.icon || 'fas fa-briefcase') + ' funnel-state custom-mr-4" style="color: ' + color +'"></i>' +
                            '<span class="deal-item--name break-word custom-ml-2">' +
                                deal_name + (funnel?.is_archived == 1 ? `<span class="gray small custom-ml-4 nowrap">${that.locales['archived']}</span>` : '') +
                            '</span>' +
                        '</span>' + funnel_deleted_html +
                    '</a></li>';
        }

        function emptyDeal() {
            $visible_link.html(that.locales['deal_empty']);
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
                    old_deal_name = $old_deal.find('.deal-item--name').text();

                $created_deal.html($old_deal_stage_icon);
                $created_deal.append($.crm.escape(old_deal_name));
            }

            // Send data
            var href = $.crm.app_url + "?module=message&action=conversationAssociateDealSave";
            $.post(href, data, function(res) {
                if (res.status === "ok") {

                    $wrapper.closest('.dialog').data('dialog').close();
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
            $deal_second = $wrapper.find('#dropdown-c-deal');

            $deal.on("click", ".js-associate-deal", function(event) {
                event.preventDefault();
                var href = $(this).data('dialog-url');

                $.get(href, function(html) {
                    $.waDialog({
                        html: html
                    });
                });
            });

        $deal.on('click', '.js-attach-other-deal', function (e) {
            setTimeout(() => {
                $deal_second.find('.dropdown-toggle').trigger('click')
            }, 50)
        })

        $deal_second.on('click', '.js-return-back', function (e) {
            setTimeout(() => {
                that.$dropdown_name.find('.dropdown-toggle.c-name').trigger('click')
            }, 50)
        })

        $deal_second.on('click', '.js-attach-other-deal', function (e) {
            e.preventDefault();
            var name = $deal.find('.js-deal-link').text();
            that.loadDealListByContact(function(data) {
                var dialog_url = $.crm.app_url + '?module=message&action=conversationAssociateDealDialog';
                $.get(dialog_url, { conversation_id: that.conversation_id }, function (html) {
                    var $dialog = $(html);
                    $.waDialog({
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

                            openDetachDialog($dialog, name);
                        }
                    });
                });
            });

        });

        $deal_second.on('click', '.js-detach-deal', function (e) {
            e.preventDefault();
            var detach_deal_xhr = null;
            var name = $deal.find('.js-deal-link--name').text();
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
                                    $.crm.content.reload();
                                }
                            })
                            .always(function () {
                                detach_deal_xhr = null;
                            });
                    });
                }
            });
        })

        function openDetachDialog($dialog, name) {
            var detach_deal_xhr = null;
            $dialog.on('click', '.js-detach-deal', function (e) {
                e.preventDefault();

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
                                        $.crm.content.reload();
                                        $dialog.data('dialog').close();
                                    }
                                })
                                .always(function () {
                                    detach_deal_xhr = null;
                                });
                        });
                    }
                });
            });
        }
    };

    CRMMessageConversationPage.prototype.initChangeResponsible = function() {
        var that = this,
            is_locked = false,
            $contact_wrapper = that.$wrapper.find('.js-conversation-contact'),
            $open_dialog_button = that.$wrapper.find('.js-open-responsible-dialog'),
            $contact_second = that.$wrapper.find('#dropdown-c-contact');
            $responsible_wrapper_dialog = that.$wrapper.find('.js-conversation-responsible-wrapper');
            html_content = $responsible_wrapper_dialog.html();

            $contact_wrapper.on('click', '.js-open-second-menu', function (e) {
                setTimeout(() => {
                    $contact_second.find('.dropdown-toggle').trigger('click')
                }, 50)
            })

            $contact_second.on('click', '.js-return-back', function (e) {
                setTimeout(() => {
                    that.$dropdown_name.find('.dropdown-toggle.c-name').trigger('click')
                }, 50)

            })

            $contact_second.on('click', '.js-remove-owner', function (e) {
                e.preventDefault();
                var name = $.trim( $responsible_wrapper_dialog.find('.c-name').html());
                $.crm.confirm.show({
                    title: that.locales["remove_owner_title"],
                    text: that.locales["remove_owner_text"].replace(/%s/, name),
                    button: that.locales["remove_owner_button"],
                    onConfirm: function() {
                        removeOwner();
                    }
                })
            })

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
                            is_locked = false;
                        }
                    }, "json").always( function() {
                        is_locked = false;
                        $.crm.content.reload();
                    });
                }
            }

        const html = `<div class="dialog dialog-conversation-responsible" id="my-dialog">
        <div class="dialog-background"></div>
        <div class="dialog-body">
        <div class="dialog-header"><h3><i class="fas fa-cogs text-blue smaller"></i> ${that.locales["owner"]}</h3></div>
        <div class="dialog-content">
        ${html_content}
        </div>
        <div class="dialog-footer">
            <button class="button light-gray js-cancel" type="button">${that.locales["close"]}</button>
        </div>
        </div>`

        $open_dialog_button.on('click', function () {

            $.waDialog({
                html: html,
                onOpen: onOpenDialog
            });

        })

        function onOpenDialog($dialog, dialog_instance) {
            // клик по кнопке закрыть
            $dialog.on("click", ".js-cancel", function(event) {
                event.preventDefault();
                // закрываем диалог
                dialog_instance.close();
            });

            var $wrapper = $dialog.find('.js-responsible-wrapper'),
            $transfer_input = $wrapper.find('.js-owner-autocomplete'),
            $empty_wrapper = $dialog.find('.js-responsible-empty-wrapper'),
            $empty_input = $empty_wrapper.find('.js-responsible-empty-autocomplete');

            if ($empty_input.length) {
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

            $wrapper.on('click', '.c-conversation-member .profile', function () {
                dialog_instance.close();
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
                    dialog_instance.close();
                    $.crm.content.reload();
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
                    dialog_instance.close();
                    $.crm.content.reload();
                });
            }

            function renderRemove() {
                $wrapper.html('');
                $empty_wrapper.removeClass('hidden');
            }
        }
        }
    };

    CRMMessageConversationPage.prototype.triggerChatUpdate = function() {
        $(document).trigger('msg_conversation_update');
    }

    CRMMessageConversationPage.prototype.initBackgroundActions = function() {
        var that = this,
            is_locked = false,
            timeout = 0;

        $(document).on('wa_before_load', function(){
            clearTimeout(timeout);
            $(document).off('wa_before_load');
        });

        $(document).on('msg_conversation_updated', function() {
            setTimeout(function() {
                that.initBlockquotToggle();
                that.scroll2bottom(true, true);
            }, 500);
        });

        $(document).on('msg_conversation_update', function() {
            updateMessages().then( function(new_messages) {
                if (new_messages.length) {
                    that.initBlockquotToggle();
                    that.scroll2bottom(true, true);
                }
                runner();
            });
        });

        runner();

        function runner(no_timeout = false) {
            clearTimeout(timeout);
            if (no_timeout) {
                request()
            } else {
                timeout = setTimeout(request, that.check_interval);
            }

        }

        function request() {
            if (!is_locked) {
                is_locked = true;
                var href = "?module=message&action=conversationIdCheck&background_process=1",
                    data = {
                        id: that.conversation_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var is_changed = (1 * response.data !== 1 * that.last_message_id);
                        if (is_changed) {
                            updateMessages().then( function(new_messages) {
                                if (new_messages.length) {
                                    that.initBlockquotToggle();
                                    that.scroll2bottom(true, true);
                                }
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

            $.get(location.href, { background_process: 1 }, function(html) {
                var last_message_id = that.last_message_id,
                    $list = that.$wrapper.find(".js-messages-list").first(),
                    $messages = $(html).find(".js-messages-list .js-message-wrapper"),
                    new_messages = [];

                if ($list.length) {
                    $messages.each( function() {
                        var $message = $(this),
                            $verification = false,
                            message_id = $message.data("id");

                        if ($message.hasClass('verification_message')) {
                            $verification = $message.data("verification");
                        }

                        if (message_id > that.last_message_id) {
                            $list.append($message);
                            markMessage($message);
                            if ($verification) $(document).trigger('updateProfile', {contact_id : $verification})

                            new_messages.push({
                                id: message_id,
                                $message: $message
                            });

                            last_message_id = message_id;
                        }
                    });

                    that.last_message_id = last_message_id;

                    that.handleYaMaps($list);
                    window.document.documentElement.dispatchEvent(new CustomEvent('wa-gallery-load', {
                        detail: {
                            forceFullPreview: true,
                            timeout: 500
                        },
                    }));
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
            var href_param_contact = (that.filter_contact_id ? 'contact=' + that.filter_contact_id : that.filter_deal_id ? 'deal=' + that.filter_deal_id : null),
                href_param_iframe = (that.iframe ? '?iframe=1' : ''),
                href_param_contact = (href_param_contact ? (that.iframe ? '&' : '?') + href_param_contact : ''),
                $sidebar = that.$wrapper.closest('#c-messages-page').find('#c-messages-sidebar'),
                reload_link = $.crm.app_url + "message/" + href_param_iframe + href_param_contact;
                if ($sidebar.is(':visible')) {
                    var $next_conversation = $sidebar.find(`.c-message-wrapper[data-id='${that.conversation_id}']`).next('.c-message-wrapper');
                    reload_link = $next_conversation.length ? $next_conversation.find('a.item').prop('href') + href_param_contact : reload_link;
                }

                var href = $.crm.app_url + "?module=message&action=conversationIdDelete",
                    data = {
                        id: that.conversation_id
                    };

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $.crm.content.load(reload_link);
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
                    $.waDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMMessageConversationPage.prototype.initSingleMessage = function () {
        var that = this,
            is_locked = false,
            $textarea = that.$wrapper.find(".js-textarea");

        that.$wrapper.on("click", ".js-open-detail-message", function(event) {
            event.preventDefault();
            if (!is_locked ) {
                is_locked = true;
                var href = $(this).data('dialog-url');

                $.get(href, function(html) {
                    $.waDialog({
                        html: html,
                        onClose: function () {
                            is_locked = false;
                        }
                    });
                });
            }
        });

        that.$wrapper.on('click', '.js-message-im-reply', function (event) {
            event.preventDefault();
            var $link = $(this),
                message_id = $link.data('message-id'),
                message_body = $link.closest('.js-message-wrapper').find('.js-message-body').text().trim();

            $textarea.val('> ' + message_body.replace(/\n/g, '\n> ').replace(/ +/g, ' ') + '\n' + $textarea.val());
            toggleHeight();
            $textarea.focus();

            function toggleHeight() {
                $textarea.css("min-height", 'auto');
                var scroll_h = $textarea[0].scrollHeight;
                if (scroll_h < 250) {
                    $textarea.css("min-height", scroll_h + "px")
                } else {
                    $textarea.css("min-height", "250px")
                };
            }
        });

        that.$wrapper.on('click', '.js-message-reply', function (event) {
            event.preventDefault();
            if (!is_locked ) {
                is_locked = true;
                var $link = $(this),
                    message_id = $link.data('message-id'),
                    href = that.app_url+'?module=message&action=writeReplyDialog',
                    params = { id: message_id };

                $.post(href, params, function(html) {
                    $.waDialog({
                        html: html,
                        onClose: function () {
                            is_locked = false;
                        }
                    });
                });
            }
        });

        that.$wrapper.on('click', '.js-message-forward', function (event) {
            event.preventDefault();
            if (!is_locked ) {
                is_locked = true;
                var $link = $(this),
                    message_id = $link.data('message-id'),
                    href = that.app_url+'?module=message&action=writeForwardDialog',
                    params = { id: message_id };

                $.post(href, params, function(html) {
                    $.waDialog({
                        html: html,
                        onClose: function () {
                            is_locked = false;
                        }
                    });
                });
            }
        });

        that.$wrapper.on('click', '.js-associate-message-deal', function (event) {
            event.preventDefault();
            var href = $(this).data('dialog-url');

            $.get(href, function(html) {
                $.waDialog({
                    html: html
                });
            });
        });

        that.$wrapper.on('click', '.js-detach-message-deal', function (e) {
            e.preventDefault();
            var href = $(this).data('dialog-url');
            $.get(href, function(html) {
                $.waDialog({
                    html: html,
                    onOpen: function ($dialog, dialog) {
                        const $form = $dialog.find('form');
                        const $loading = $('<div class="spinner"></div>');
                        const $submit = $dialog.find('.js-submit');
                        const $footer = $dialog.find('.js-footer');
                        $form.on('submit', function (ev) {
                            ev.preventDefault();
                            const data = $form.serialize();
                            const href = $.crm.app_url + '?module=message&action=dealDetach';

                            $submit.prop('disabled', true);
                            $footer.append($loading);

                            $.post(href, data, function(res){
                                if (res.status === "ok") {
                                    $.crm.content.reload();
                                    dialog.close();
                                } else {
                                    $submit.prop('disabled', false);
                                    $loading.remove();
                                }
                            });
                        });
                    }
                });
            });
        })
    };

    CRMMessageConversationPage.prototype.initScrollToBottom = function () {
        var that = this,
            $list = that.$wrapper.find('.js-messages-list'),
            $scrollButton = that.$wrapper.find('.js-messages-list--to-bottom-button');

            $list.on("scroll touchmove", useMainScroll);

        function useMainScroll() {
            var is_exist = $.contains(document,  $scrollButton[0]);
            if (is_exist) {
                onScroll();
            } else {
                $list.off("scroll touchmove", useMainScroll);
            }
        }

        $scrollButton.on('click', () => that.scroll2bottom(true, true));

        $(document).on('scroll_button_event', (event, additionalData) => {
            that.old_message_id = additionalData.old_message_id;
            that.last_message_id = additionalData.last_message_id;
            that.scroll2bottom(true, true)
        });

        function onScroll() {

            var $listHeight = $list.get(0).scrollHeight;
            var scrollBottom = $listHeight - $list.scrollTop() - $list.height();
            if (scrollBottom <= 50 && $scrollButton.is(':visible')) {
                $scrollButton.hide();
            }
            else if (scrollBottom > 50 && $scrollButton.is(':hidden')) {
                $scrollButton.show();
            }
        }

    };

    /**
     * @param {Boolean?} force - do now or use timeout
     * @param {Boolean?} animate - use animate
     * */
    CRMMessageConversationPage.prototype.scroll2bottom = function(force, animate) {
        var that = this,
            $window = that.iframe ? $(top.window) : $(window),
            window_h = $window.height();

        var do_scroll = true;

        if (force) {
            render();

        } else {

            if ($.crm.is_page_loaded || that.iframe) {

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
            }, 0);
        }

        function render() {
            var $page_content = that.$wrapper.find('.js-messages-list');
            var $page_content_for_scroll = $page_content;
            var document_h_offset = $page_content.height();
            var document_h = $page_content.get(0).scrollHeight;
            if (document_h > 0) {
                if (document_h_offset < document_h) {
                    if (animate) {
                        $page_content_for_scroll.animate({
                            scrollTop: (document_h)
                        }, 500);
                        $.message.content.animate(false);
                    } else {
                        $page_content_for_scroll.scrollTop(document_h);
                        $.message.content.animate(false);
                    }
                }
                else {
                    that.$wrapper.trigger('bigWindowEvent.lazy');
                    $.message.content.animate(false);
                }
            }
            else {
                setTimeout(()=> render(), 100);
            }
        }
    };

    CRMMessageConversationPage.prototype.initToggleSidebar = function() {
        var that = this,
            $content = that.$wrapper.parent().parent(),
            $hidden_class = 'desktop-and-tablet-only',
            $expandSidebarButton = that.$wrapper.find('.js-expand-sidebar');
        if ($expandSidebarButton.length) {
            $expandSidebarButton.on('click', function(event) {
                event.preventDefault();
                var $sidebar = $content.prev();
                if ($content.is(':visible')) {
                    $content.addClass($hidden_class);
                    $sidebar.removeClass($hidden_class);
                    const history = window.history.state;
                    const new_url = history.content_uri.replace('?view=chat', '').replace('&view=chat', '');
                    history.content_uri = new_url;
                    window.history.replaceState(history, history.title, new_url);
                }

            });
        }
    }

    CRMMessageDeleteLinkMixin.mixInFor(CRMMessageConversationPage);

    return CRMMessageConversationPage;

})(jQuery);

var CRMMessagesProfileAdditional = ( function($) {

    CRMMessagesProfileAdditional = function(options) {
        var that = this;

        // DOM
        that.$main_wrapper = options["$wrapper"];
        that.contact_id = options["contact_id"];
        that.$wrapper = that.$main_wrapper.find("#c-messages-info-additional");

        that.initClass();
    }

    CRMMessagesProfileAdditional.prototype.initClass = function() {
        var that = this;
        var is_locked = false;
        var width = $(window).width();

        if (width >= 1024) {
            appendIframe(that.contact_id);
            is_locked = true;
        }
        else {
            $(window).on('resize', function(){
                if (!is_locked && $(this).width() !== width ) {
                    width = $(this).width();
                    if (width >= 1024) {
                        appendIframe(that.contact_id);
                        is_locked = true;
                    }
                  }
            })
        }

        $(document).on('updateProfile', (event, additionalData) => {
            that.$main_wrapper.find('iframe').remove();
            appendIframe(additionalData.contact_id);
        })

        function appendIframe(contact_id) {
            var iframe = `<iframe frameborder="0" src="${$.crm.app_url}frame/contact/${contact_id}" style="width:100%; background-color: var(--background-color);"></iframe>`
            that.$main_wrapper.prepend(iframe);
            var $iframe = that.$main_wrapper.find('iframe');
            $iframe.on('load', setIframeHeight);
        }

        function setIframeHeight() {
            var $iframe = $(this)
            var $iframe_content = $iframe.contents().find('#app');
            if ($iframe_content.length) {

                const resizeObserver = new ResizeObserver(entries => {
                    const content_height = entries[0].target.scrollHeight + 1;
                    $iframe.css('height', content_height);
                    if (content_height > 300) {
                        that.$wrapper.removeClass('hidden');
                    }
                });
                  resizeObserver.observe( $iframe_content[0]);
            }
            else {
                setTimeout(() => setIframeHeight(), 100);
            }

            $iframe.on('beforeShowModal', () => {
                $iframe.addClass('height-full')
                that.$main_wrapper.addClass('freeze-sidebar')
            });

            $iframe.on('beforeCloseModal', () => {
                $iframe.removeClass('height-full')
                that.$main_wrapper.removeClass('freeze-sidebar')
            });
        }

        that.initTopInfoFields();
        that.initAdditionalContentLink();
    }

    CRMMessagesProfileAdditional.prototype.initTopInfoFields = function() {
        var that = this,
            $list = that.$wrapper.find(".js-info-list"),
            $link_list = that.$wrapper.find(".js-drawer-list"),
            draw_locked = false;

        that.$wrapper.on("click", ".js-info-toggle", toggleContent);

        function toggleContent(event) {
            event.preventDefault();
            var $toggle = $(this);

            if (!draw_locked) drawAdditionalLinks();

            $toggle.toggleClass("is-active");
            $list.slideToggle();
            if ($toggle.hasClass('is-active')){
                that.$main_wrapper.animate({scrollTop: that.$main_wrapper[0].scrollHeight}, 450);
            }
        }

        function drawAdditionalLinks() {
            var href = $.crm.app_url + '?module=contact&action=tabs&id=' + that.contact_id;
            draw_locked = true;
            that.item_html = {};
            $.get(href)
            .done(function(response) {
                $.each(response.data.tabs, function (i, item) {
                    var item_count = !!item.count ? `<span class="hint custom-ml-4">${item.count}</span>` : '';
                    var link_html = `<li class="custom-mb-16 bold">
                    <a href="javascript:void(0);" class="js-drawer-list-link js-drawer-list--${item.app_id} additional" data-dialog-url="${item.url || ''}" data-class-name="custom" data-id="${item.app_id}">
                    <span class="js-drawer-list-link--header" >${item.title}</span>${item_count}</a></li>`;
                    $link_list.append(link_html);
                    that.item_html[item.app_id] = item.html;
                });
                $.each(response.data.counters, function (i, item) {
                    if (!!item.value) {
                        $link_list.find(`.js-drawer-list-counter--${item.name}`).text(item.value);
                    }
                });
                $link_list.find('.skeleton').hide();
                that.$main_wrapper.animate({scrollTop: that.$main_wrapper[0].scrollHeight}, 450);
            });
        }
    };

    CRMMessagesProfileAdditional.prototype.initAdditionalContentLink = function () {
        var that = this,
            is_locked = false;
        that.$wrapper.on("click", ".js-drawer-list-link", function(event) {
            event.preventDefault();
            if (!is_locked ) {
                is_locked = true;
                var href = $(this).data('dialog-url');
                var is_additional_link = $(this).hasClass('additional');
                var data_id = $(this).data('id') || null;
                var extraClass = $(this).data('class-name') || '';
                var drawer_header = $(this).find('.js-drawer-list-link--header').text();

                const drawer_loader = '<div class="flexbox middle width-100 height-100 spinner-wrapper"><div class="spinner custom-p-16"></div></div>';
                drawer_html = `<div class=\"drawer crm-help crm-message-additional ${extraClass}\" id=\"\"> <div class=\"drawer-background\"><\/div> <div class=\"drawer-body\"><header class=\"drawer-header\"><h3>${drawer_header}<\/h3><\/header><a href=\"#\" class=\"drawer-close js-close-drawer\"><i class=\"fas fa-times\"><\/i><\/a><div class=\"drawer-block\">${drawer_loader}<\/div><\/div><\/div>`;
                var iframe_html = `<iframe frameborder="0" src="${href}" style="width:100%;  height: 100vh; background-color: var(--background-color);"></iframe>`;
                if (is_additional_link) {
                    iframe_html = `<iframe frameborder="0" style="width:100%; height: 100vh; background-color: var(--background-color-blank);"></iframe>`;
                }

                that.drawer = $.waDrawer({
                    html: drawer_html,
                    direction: "right",
                    onOpen: function ($drawer, drawer_instance) {
                        var $drawer_block = $drawer.find('.drawer-block');
                        $drawer_block.append(iframe_html);
                        const $iframe = $drawer.find('iframe');
                        if ($iframe.length) {
                            $iframe.on('load', function() {
                                $drawer.find('.spinner-wrapper').remove();
                                $($iframe).contents().on('click', 'a', function(){

                                    if ($(this).data('link')) {
                                        drawer_instance.close()
                                    }
                                })
                            })
                            $iframe.on('beforeShowModal', () => {
                                $iframe.addClass('z-10')
                            });
                            $iframe.on('beforeCloseModal', () => {
                                $iframe.removeClass('z-10')
                            });
                            if (is_additional_link) handleAdditionalIframe($iframe, href, data_id);
                        }
                        else {
                            $drawer.find('.spinner-wrapper').remove();
                        }


                    },
                    onClose: function () {
                        is_locked = false;
                    }
                });

            }
        });

        function handleAdditionalIframe($iframe, href, data_id) {
            var $iframe_content = $iframe.contents();
            var $iframe_head = $iframe_content.find('head');
            var $iframe_body = $iframe_content.find('body');

            // Load jquery UI and init script for autocomplete into iframe
            $iframe_content.find('html').attr('data-theme', document.documentElement.getAttribute("data-theme")).addClass('blank');
            $iframe_head.append(
                `<meta charset="utf-8">
                <link rel="icon" href="data:;base64,iVBORw0KGgo=">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="viewport-fit=cover, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
                <link href="${wa_url}wa-content/css/wa/wa-2.0.css" rel="stylesheet" type="text/css">
                <script src="${wa_url}wa-content/js/jquery/jquery-3.6.0.min.js"><\/script>`
            );
            $iframe_body.addClass('blank custom-px-16');
            function setTopLinks() {
                $iframe_body.append(`
                    <script>
                    (() => {
                        setTimeout( function() {
                            Array.from(document.getElementsByTagName('a')).forEach(l => {
                                l.setAttribute('target', '_top');
                            });
                        }, 200);
                    })();
                    <\/script>`);
            }

            if (href) {
                $.ajax({
                    method: 'GET',
                    url: href,
                    dataType: 'html',
                    global: false,
                    cache: false
                })
                .done(function (html) {
                    $iframe_body.html(html);
                    setTopLinks();
                })
                .fail(function(data) {
                    if (data.statusText) {
                        $iframe_body.html(data.statusText);
                    }
                })
                .always(function () {
                    $iframe.trigger('load');
                });
            }
            else if (data_id) {
                const item_html = that.item_html[data_id];
                $iframe_body.html(item_html);
                setTopLinks();
                $iframe.trigger('load');
            }
            else {
                $iframe_body.html('<div>Not found</div>');
                $iframe.trigger('load');
            }
        }
    };

    return CRMMessagesProfileAdditional;

})(jQuery);

var CRMMessagesProfile = ( function($) {

    CRMMessagesProfile = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.photo_dialog_url = options.photo_dialog_url;
        that.locales = options["locales"];
        that.icons_array = {
            "folder": "<i class=\"fas fa-folder\"></i>",
            "search": "<i class=\"fas fa-search\"></i>",
            "user": "<i class=\"fas fa-user\"></i>",
            "blog": "<i class=\"fas fa-file-image\"></i>",
            "notebook": "<i class=\"fas fa-file\"></i>",
            "lock": "<i class=\"fas fa-lock\"></i>",
            "lock-unlocked": "<i class=\"fas fa-lock-open\"></i>",
            "broom": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#clean\"></use></svg></i>",
            "star": "<i class=\"fas fa-star\"></i>",
            "livejournal": "<i class=\"fas fa-pencil-alt\"></i>",
            "contact": "<i class=\"fas fa-users\"></i>",
            "lightning": "<i class=\"fas fa-bolt\"></i>",
            "light-bulb": "<i class=\"fas fa-lightbulb\"></i>",
            "pictures": "<i class=\"fas fa-images\"></i>",
            "reports": "<i class=\"fas fa-chart-bar\"></i>",
            "books": "<i class=\"fas fa-book\"></i>",
            "marker": "<i class=\"fas fa-map-marker-alt\"></i>",
            "lens": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#lens\"></use></svg></i>",
            "alarm-clock": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#alarm\"></use></svg></i>",
            "animal-monkey": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#monkey\"></use></svg></i>",
            "anchor": "<i class=\"fas fa-anchor\"></i>",
            "bean": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#coffee-beans\"></use></svg></i>",
            "car": "<i class=\"fas fa-car\"></i>",
            "disk": "<i class=\"fas fa-save\"></i>",
            "cookie": "<i class=\"fas fa-cookie\"></i>",
            "burn": "<i class=\"fas fa-radiation-alt\"></i>",
            "clapperboard": "<i class=\"icon\"><svg><use xlink:href=\"{$wa_url}wa-apps/blog/img/sprites/icons.svg?v={$wa->version()}#clapper\"></use></svg></i>",
            "bug": "<i class=\"fas fa-bug\"></i>",
            "clock": "<i class=\"fas fa-clock\"></i>",
            "cup": "<i class=\"fas fa-coffee\"></i>",
            "home": "<i class=\"fas fa-home\"></i>",
            "fruit": "<i class=\"fas fa-apple-alt\"></i>",
            "luggage": "<i class=\"fas fa-briefcase\"></i>",
            "guitar": "<i class=\"fas fa-guitar\"></i>",
            "smiley": "<i class=\"fas fa-grin\"></i>",
            "sport-soccer": "<i class=\"fas fa-futbol\"></i>",
            "target": "<i class=\"fas fa-bullseye\"></i>",
            "medal": "<i class=\"fas fa-award\"></i>",
            "phone": "<i class=\"fas fa-phone\"></i>",
            "store": "<i class=\"fas fa-store\"></i>",
            "basket": "<i class=\"fas fa-shopping-basket\"></i>",
            "pencil": "<i class=\"fas fa-pen-alt\"></i>",
            "lifebuoy": "<i class=\"fas fa-life-ring \"></i>",
            "screen": "<i class=\"fas fa-tablet-alt\"></i>",
            "noname": "<i class=\"fas fa-user-friends\"></i>"
        };
        that.contact_id = ( options["contact_id"] || false );
        that.editable = options["editable"] || false;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessagesProfile.prototype.initClass = function() {
        var that = this;

        //that.initTabChange();
        //
        //that.initAddCompanyContactLink();
        //
        that.initResponsibleLink();
        that.initTopInfoFields();
        that.initMessage();
        that.initAssignTags();
        that.initSegments();
    };

    CRMMessagesProfile.prototype.initResponsibleLink = function() {
        var that = this;
        that.$wrapper.find(".profile-responsible-link .responsible-link").on("click", function() {
            $.get($(this).data('dialog-url'), function(html) {
                // Init the dialog
                var crm_dialog = $.waDialog({
                    remain_after_load: true,
                    html: html
                });
            });
        });

    };

    CRMMessagesProfile.prototype.initTopInfoFields = function() {
        var that = this,
            $list = that.$wrapper.find(".js-info-list");

        that.$wrapper.on("click", ".js-info-toggle", toggleContent);

        function toggleContent(event) {
            event.preventDefault();

            var $toggle = $(this);

            $toggle.toggleClass("is-active");
            $list.slideToggle();

        }
    };

    CRMMessagesProfile.prototype.initMessage = function() {
        this.initSendEmail();
        this.initSendSMS();
    };

    CRMMessagesProfile.prototype.initSendEmail = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-message-dialog", showDialog);

        function showDialog(e) {
            e.preventDefault();

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
                    $.waDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMMessagesProfile.prototype.initSendSMS = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-send-sms-dialog", showDialog);

        function showDialog(e) {
            e.preventDefault();

            var $link = $(this),
                contact_id = $link.data("id"),
                phone = $link.data("phone");

            if (!is_locked && contact_id) {
                is_locked = true;

                var href = "?module=message&action=writeSMSNewDialog",
                    data = {
                        contact_id: contact_id,
                        phone: phone
                    };

                $.get(href, data, function(html) {
                    $.waDialog({
                        html: html,
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMMessagesProfile.prototype.initAssignTags = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-contact-assign-tags'),
            url = $.crm.app_url + '?module=contactOperation&action=assignTags&is_assign=1',
            contact_id = parseInt(that.contact_id) || 0;
        $link.click(function (e) {
            e.preventDefault();
            $.get(url, { contact_ids: [contact_id] }, function (html) {
                $.waDialog({
                    html: html
                });
            });
        })
    };

    CRMMessagesProfile.prototype.initSegments = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-contact-assign-segments'),
            url = $.crm.app_url + '?module=contactOperation&action=addToSegments&is_assign=1',
            contact_id = parseInt(that.contact_id) || 0;

        $link.click(function (e) {
            e.preventDefault();
            $.get(url, { contact_ids: [contact_id] }, function (html) {
                $.waDialog({
                    html: html,
                    onOpen: function ($dialog) {
                        new CRMContactsOperationAddToSegments({
                            '$wrapper': $dialog,
                            'context': { contact_ids: [contact_id] },
                            'is_assign': 1
                        });
                    }
                });
            });
        });

        var xhr = false;

        that.$wrapper.on("click", ".js-show-dynamic-segments", showDynamicSegments);

        function showDynamicSegments(event) {
            event.preventDefault();
            var $link = $(this);

            $link.find(".icon").removeClass("fa-filter").addClass("fa-spinner fa-spin");

            if (xhr) {
                xhr.abort();
            }

            var href = "?module=contact&action=segmentSearch",
                data = {
                    id: that.contact_id
                };

            xhr = $.post(href, data, function(response) {
                if (response.status == "ok") {
                    renderSegments(response.data.segments);
                    $link.remove();
                }
            }).always( function() {
                xhr = false;
            });
        }

        function renderSegments(segments) {
            var $list = that.$wrapper.find(".js-segment-list");
            var set_separator = ($list.find("a").length);

            if (!segments.length) {
                segments.push({
                    id: false,
                    name: that.locales["no_segments"]
                });
            }

            $.each(segments, function(index, item) {
                var href = "javascript:void(0);",
                    icon = "",
                    text = "<span class=\"hint\">" + item.name + "</span>";

                if (item.id) {
                    href = $.crm.app_url + "contact/segment/" + item.id + "/";
                    icon = that.icons_array[item.icon] ? that.icons_array[item.icon] : that.icons_array["noname"];
                    text = "<span>" + item.name + "</span>";
                }

                var link = "<a href=\"" + href + "\">" + icon + text + "</a>";

                if (set_separator) {
                    link = ", " + link;
                }

                $list.append(link);
                set_separator = true;
            });
        }
    };

    return CRMMessagesProfile;

})(jQuery);


var CRMEmailConversationEmailSender = ( function($) { //форма ответа email

    CRMEmailConversationEmailSender = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$replySection = that.$wrapper.closest(".c-reply-section");
        that.$resize_button = that.$replySection.find(".js-message-resize"),
        that.$skeleton_wrapper = that.$wrapper.closest(".c-messages-conversation-wrapper").find(".skeleton-wrapper");
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$wrapper.find(".js-wysiwyg");
        that.$textarea_small = that.$wrapper.find(".js-visible-textarea-small");
        that.$sender_email_select = that.$wrapper.find(".js-sender-email-select");
        that.$sender_email_select_val = that.$sender_email_select.val();
        that.$sender_email = that.$wrapper.find(".js-sender-email");
        that.$subject_input = that.$replySection.find('input[name="subject"]');
        that.subject_input_val = that.$subject_input.val();
        that.$copy_wrapper = that.$wrapper.find(".email-copy-wrapper");
        that.$copy_wrapper_collapsed = that.$wrapper.find(".email-copy-wrapper-collapsed");

        // VARS
        that.file_template_html = options["file_template_html"];
        that.max_upload_size = options["max_upload_size"];
        that.locales = options["locales"];
        that.hash = options["hash"];
        that.send_action_url = options["send_action_url"];
        that.body = options["body"] || '';
        that.deal_id = options["deal_id"];
        that.is_changed = false;
        that.is_locked = false;
        that.body_old = that.body;

        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS

        // INIT
        that.initClass();

        that.filesController = that.getFilesController();

        that.disableResizer = function(disable) {
            if (disable) {
                that.is_changed = true;
                that.$resize_button.addClass('is-disabled');
            }
            else {
                that.is_changed = false;
                that.$resize_button.removeClass('is-disabled');
            }
        }
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
            $textarea = that.$textarea,
            $replySection = that.$replySection,
            $wrapper_main = $(".c-messages-page-content-wrapper");

        $replySection.on("click", ".js-revert", function() {
            $textarea.redactor('code.set', that.body);
            that.disableResizer(false);
            toggleView(false);
            that.$body_redactor_p = that.$wrapper.find('.redactor-layer.redactor-styles.redactor-layer-img-edit section p').eq(0);
            that.$subject_input.val(that.subject_input_val);
            that.$sender_email_select.val(that.$sender_email_select_val).change();
            that.$copy_wrapper.find(".email-copy-user").each(function() {$(this).remove()});
            that.$copy_wrapper_collapsed.find(".email-copy-user").each(function() {$(this).remove()});
            if (that.$copy_wrapper.hasClass('email-copy-wrapper-block')) that.$wrapper.find(".js-email-copy-link").click();
            that.$copy_wrapper_collapsed.removeClass('email-copy-wrapper-collapsed-block');
        });

        $replySection.on("click", ".js-message-resize", function() {
            toggleView(false);
        });

        $replySection.on("click", ".js-message-resize-open", function() {
            var $body_redactor = that.$wrapper.find('.redactor-layer.redactor-styles.redactor-layer-img-edit');
            that.body = $body_redactor.html();
            toggleView(true);
            that.disableResizer(false);
        });

        that.$subject_input.on("change", function(event) {
            that.disableResizer(true);
        });

        /**
         * @param {Boolean} show
         * */
        function toggleView(show) {
            var active_class = "is-extended";
            if (show) {
                $replySection.addClass(active_class).trigger("resize");
                $wrapper_main.addClass('scroll-lock');
            } else {
                if (!that.is_changed) {
                    $replySection.removeClass(active_class).trigger("resize");
                    $wrapper_main.removeClass('scroll-lock');
                }
            }
        }
    };
    CRMEmailConversationEmailSender.prototype.senderEmailSelect = function() {
        var that = this;

        that.$wrapper.on("change", that.$sender_email_select, function(event) {
            event.preventDefault();
            that.$sender_email.val(that.$sender_email_select.val());
            that.disableResizer(true);
        });
    };
    CRMEmailConversationEmailSender.prototype.getFilesController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-files-wrapper"),
            $submitButtonSmall = that.$wrapper.find('.c-visible .js-save-button');

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = that.$wrapper.find(".js-default-drop-area"),
            $fileField = that.$wrapper.find(".js-drop-field"),
            $uploadList = that.$wrapper.find(".c-upload-list"),
            file_template_html = that.file_template_html;

        // DATA
        var uri = $.crm.app_url + "?module=file&action=uploadTmp";

        // DYNAMIC VARS
        var upload_file_count = 0,
            hover_timeout = 0;

        that.files_storage = [];

        // VARS
        var hover_class = "is-hover";

        that.$form.on("click", '.js-message-attachment', function () {
            $fileField.click();
        });

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

        document.onpaste = function (event) {
            var items = (event.clipboardData || event.originalEvent.clipboardData).files;
            addFiles(items);
        }

        // delete
        that.$form.on("click", ".js-file-delete", function(event) {
            event.preventDefault();
            deleteFile( $(this).closest(".c-upload-item") );
        });

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    that.files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                $fileField.val('');
            }
        }

        function renderFile(file) {
            var $uploadItem = $(file_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

            $uploadList.prepend($uploadItem);

            if ($submitButtonSmall.prop('disabled')) $submitButtonSmall.prop('disabled', false)

            return $uploadItem;
        }

        function deleteFile($file) {
            var result = [];

            $.each(that.files_storage, function(index, item) {
                if ($file[0] !== item.$file[0]) {
                    result.push(item);
                } else {
                    $file.remove();
                }
            });

            that.files_storage = result;
        }

        function uploadFiles(data, callback) {

            var afterUploadFiles = ( callback ? callback : function() {} );

            if (that.files_storage.length) {
                upload_file_count = that.files_storage.length;

                $.each(that.files_storage, function(index, file_item) {

                    uploadFile(file_item);
                });
            } else {
                afterUploadFiles();
            }

            function uploadFile(file_item) {

                var $file = file_item.$file,
                    $bar = $file.find(".js-bar"),
                    $success_icon = $bar.find(".js-progress-success-icon"),
                    $status = $file.find(".js-status");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    request();
                } else {
                    var $submitButton = that.$form.find(".js-submit-button");
                    $status.addClass("errormsg");
                    $file.removeClass("is-upload");
                    that.is_locked = false;
                    $submitButtonSmall.find('svg').removeClass('fa-spinner fa-spin').addClass('fa-arrow-up');
                    $submitButton.attr("disabled", false);
                    that.$textarea_small.attr("disabled", false);
                    $fileField.prop("disabled", false);
                    that.$form.find('button').prop("disabled", false);

                }

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
                                    var percent = parseInt( (event.loaded / event.total) * 100 );
                                    $bar.css('--progress-value', percent);
                                    if (percent === 100) {
                                        $bar.css('background', 'transparent');
                                        $success_icon.show();
                                    }
                                    //$status.text(percent + "%");
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
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
                                }
                            }, 2000);
                        }
                    }).always( function () {
                        that.is_locked = false;
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
            clearTimeout(hover_timeout);
            hover_timeout = setTimeout( function () {
                $dropArea.removeClass(hover_class);
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
            maxHeight: 50,
            allowedAttr: [['section', 'data-role']],
            callbacks: {
                change: function() {
                    that.disableResizer(true);
                }
            }
        });

        if (that.body) {
            $textarea.redactor('code.set', that.body);
        }
        that.$body_redactor_p = that.$wrapper.find('.redactor-layer.redactor-styles.redactor-layer-img-edit section p').eq(0);
    };

    CRMEmailConversationEmailSender.prototype.initSave = function() {
        var that = this,
            $textarea_small = that.$textarea_small,
            $submitButtonSmall = that.$wrapper.find('.c-visible .js-save-button'),
            $submitButton = that.$form.find(".js-submit-button"),
            $dropField = that.$form.find(".js-drop-field"),
            $main_wrapper = $(".c-messages-page-content-wrapper");
            that.$message_list = $main_wrapper.find(".js-messages-list");

        $(document).on('wa_before_load', function(){
            $(document).off('wa_before_load');
        });

        $textarea_small.on("keydown", function(e) {
        var use_enter = (e.keyCode === 13 || e.keyCode === 10);
        if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
            e.preventDefault();
            if ($textarea_small.val() === '' && !that.files_storage.length) {
                $textarea_small.addClass('shake animated');
                setTimeout(function(){
                    $textarea_small.removeClass('shake animated');
                },500);
            }
            else {
                that.$form.submit();
            }
        }

        });

        $textarea_small.on("focus", function(e) {
            that.$replySection.addClass('focused')
        })

        $textarea_small.on("blur", function(e) {
            that.$replySection.removeClass('focused')
        })

        $textarea_small.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46);

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var value = $textarea_small.val(),
                        position = $textarea_small.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;
                        $textarea_small.val(result);
                }
            }
            else {
                if ($textarea_small.val() === '' && !that.files_storage.length) {
                    if (!$submitButtonSmall.prop('disabled')) $submitButtonSmall.prop('disabled', true)
                }
                else {

                    if ($submitButtonSmall.prop('disabled')) $submitButtonSmall.prop('disabled', false)
                }
            }
            toggleHeight();
            that.$body_redactor_p.html($textarea_small.val()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;")
                .replace(/\n/g, '<br>'));
        });

        function toggleHeight() {

            $textarea_small.css("min-height", 'auto');
            var scroll_h = $textarea_small[0].scrollHeight;
            if (scroll_h < 250) {$textarea_small.css("min-height", scroll_h + "px")}
            else {$textarea_small.css("min-height", "250px")};
        }

        that.$form.on("submit", function(event) {
            event.preventDefault();

            if (!that.is_locked) {
                that.is_locked = true;

                var $loading = $('<span class="icon size-16"><i class="fas fa-spinner wa-animation-spin custom-mr-4"></i></span>');
                $submitButtonSmall.find('svg').removeClass('fa-arrow-up').addClass('fa-spinner fa-spin');
                $submitButton.attr("disabled", true);
                $loading.insertBefore($submitButton);
                that.$form.find('button').prop("disabled", true);

                var data = [
                    {
                        "name": "hash",
                        "value": that.hash
                    }
                ];

                that.filesController.uploadFiles(data, submit);
            }
        });

        function submit() {
            var href = that.send_action_url,
                $extendedFormLoading = $('<div class="spinner custom-ml-16 js-spinner"></div>'),
                $extendedFormButton = that.$wrapper.find(".js-extended-form-actions").find(".js-save-button"),
                data = that.$form.serializeArray();

            $dropField.prop("disabled", true);
            $textarea_small.attr("disabled", true);
            $extendedFormButton.attr("disabled", true);
            $extendedFormLoading.insertAfter(that.$wrapper.find(".js-extended-form-drop-area"));
            $.ajax({
                method: 'POST',
                url: href,
                data: data,
                dataType: 'json',
            }).done(function(response) {
                if (response.status == 'ok') {
                    const response_html = jQuery.parseJSON(response.data.html);
                    const response_id = response.data.id ? response.data.id :  response.data.message_id;
                    loadNewList(response_id);

                    clearInputForm();
                    //onImagesLoaded(response_id);

                    $submitButtonSmall.attr("disabled", true);
                    $submitButton.attr("disabled", true);
                    that.body = that.body_old;
                    that.$replySection.find(".js-revert").trigger("click");
                    toggleHeight();
                    if ($('#c-messages-page #c-messages-sidebar').length) {
                        $(document).trigger('msg_sidebar_upd_needed');
                    }
                    updateSendReply(response_html);
                } else {
                    failSend(response);
                }
            }).fail(function(data) {
                failSend(data);
            }).always(function () {
                that.is_locked = false;
                $extendedFormLoading.remove();
                $extendedFormButton.removeAttr("disabled");
            });

        }

        function failSend(response) {
            var error_box = that.$form.find('#tooltip-error-message'),
                error_msg = Object.values(response.errors);
                error_box.addClass('errormsg');
                $.each(error_msg, function(i, err){
                    error_box.append(`<span><i class="fas fa-exclamation-triangle state-error"></i> <span class="small">${err}</span></span>`);
                })
            clearInputForm();
            $submitButtonSmall.attr("disabled", true);
            $submitButton.attr("disabled", true);
            that.body = that.body_old;
            that.$replySection.find(".js-revert").trigger("click");
            toggleHeight();

            that.is_locked = false;
            if (response.errors) {
                console.error(response.errors);
            }

            that.$textarea.on('focus', clearErrors);
            $textarea_small.on('focus', clearErrors);
            that.$form.on('change', clearErrors);
        }

        function updateSendReply(response_html) {
            var $body_redactor = that.$wrapper.find('.redactor-layer.redactor-styles.redactor-layer-img-edit');
            var $body_redactor_b = $body_redactor.find('blockquote').eq(0);
            var $body_redactor_p = $body_redactor.find('> p').eq(1);
            var response_html_body = $(response_html).find('.c-message-body').html();
            var response_html_client = $(response_html).find('.c-contact-link--name').html();
            var response_html_date = $(response_html).find('.c-date.hint').data('time');
            $body_redactor_b.html(response_html_body);
            var new_sign = `${response_html_date}, ${response_html_client} ${that.locales['wrote']}:`;
            $body_redactor_p.html(new_sign);
        }


        function loadNewList(last_message_id) {
            var $conversation_wrapper = $main_wrapper.find('#js-message-conversation-page'),
                conversation_id = $conversation_wrapper.data('conv-id'),
                $blank_list = $conversation_wrapper.find('.c-conversation-body--blank'),
                $transparent_cover = $conversation_wrapper.find('.js-messages-list--transparent-layer'),
                href = $.crm.app_url + '?module=messageConversationId&id=' + conversation_id;
                //is_locked = true;

            $.ajax({
                url: href,
                type: 'POST',
                dataType: 'html',
                beforeSend: function(){
                    //$transparent_cover.show();
                    //prevScrollH = that.$wrapper.prop('scrollHeight');
                },
                complete: function(){
                    //$transparent_cover.hide();
                    //that.$wrapper.css('overflow-y', "overlay");
                },
                data: {
                    delay: 1
                },
                success: function(data){
                    var $new_list = $(data).find('.js-messages-list'),
                        $new_list_elements = $(data).find('.js-message-wrapper'),
                        old_message_id = $new_list_elements.eq(0).data('id'),
                        $new_list_count = $new_list_elements.length;

                    if ($new_list_count) {
                        //that.old_message_id = $new_list_elements.eq(0).data('id');
                        if ($new_list_count < 10) $new_list.find(".js-lazy-load").remove();
                        var $new_list_html = $new_list.html();
                        $blank_list.append($new_list_html)
                        $(document).trigger('msg_conversation_updated');
                        onImagesLoaded();

                        function onImagesLoaded() {

                                var images = $blank_list[0].getElementsByTagName("img"),
                                videos = $blank_list[0].getElementsByTagName("video");
                                var loaded = images.length + videos.length;
                                function checkLoadingData() {
                                    if (loaded == 0) {
                                        that.$message_list.html($blank_list.html());
                                        //that.$message_list.append(response_html);
                                        $blank_list.html('');
                                        //$transparent_cover.hide();
                                        $(document).trigger('scroll_button_event', {old_message_id: old_message_id, last_message_id: last_message_id});
                                        $(document).trigger('new_message_lazy');
                                        return
                                    }
                                }

                                checkLoadingData();

                                 for (var i = 0; i < videos.length; i++) {
                                    if (videos[i].readyState >= 3) {
                                        loaded--;
                                        checkLoadingData();
                                    }
                                    else {

                                        videos[i].addEventListener('loadeddata', function() {
                                            loaded--;
                                            checkLoadingData();
                                        });

                                        videos[i].addEventListener("error", function() {
                                            loaded--;
                                            checkLoadingData();
                                        });
                                    }
                                }

                                for (var i = 0; i < images.length; i++) {
                                    if (images[i].complete) {
                                        loaded--;
                                        checkLoadingData();
                                    }
                                    else {

                                        images[i].addEventListener("load", function() {
                                            loaded--;
                                            checkLoadingData();
                                        });

                                        images[i].addEventListener("error", function() {
                                            loaded--;
                                            checkLoadingData();
                                        });
                                    }
                                }
                            }

                    }
                    else {
                       // if ($loader) $loader.remove();
                    }

                   // startLazyLoading();
                }
            });
        }

        function clearErrors() {
            var error_box = that.$form.find('#tooltip-error-message');
            error_box.removeClass('errormsg').html('');
            $textarea_small.off('focus', clearErrors);
            that.$textarea.off('focus', clearErrors);
            that.$form.off('change', clearErrors);
        }

        function clearInputForm() {

            $submitButtonSmall.find('svg').removeClass('fa-spinner fa-spin').addClass('fa-arrow-up');
            $submitButton.attr("disabled", false);
            $textarea_small.attr("disabled", false);
            $dropField.prop("disabled", false);
            that.$form.find('button').prop("disabled", false);
            $textarea_small.val('');
            var width = $(window).width();
            if (width > 760) $textarea_small.focus();
            if (that.files_storage.length) {
                var $exist_files = that.$form.find(".c-upload-item");
                $.each($exist_files, function(index, item) {
                    item.remove();
                });
                that.files_storage = [];
            }
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
                    $.waDialog({
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
                var data = '<i class="icon userpic" style="background-image: url('+ ui.item.photo_url +');"></i><b>'+ ui.item.name +'</b>';
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
            var $link_icon = that.$wrapper.find('.js-email-copy-link-icon');
            $wrapper.toggleClass('email-copy-wrapper-block');

            if ($wrapper.is(':visible')) {
                $link_icon.removeClass('fa-caret-right').addClass('fa-caret-down');
                $copy_input.focus();

                $wrapper_collapsed.removeClass('email-copy-wrapper-collapsed-block'); // Close collapsed, if openig CC editor
            } else {
                $link_icon.removeClass('fa-caret-down').addClass('fa-caret-right');

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

var CRMImConversationSection = ( function($) { //форма ответа IM

    CRMImConversationSection = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$form.find('.js-textarea');
        that.$replySection = that.$wrapper.closest(".c-reply-section");
        that.$submitButton = that.$form.find('.js-save-button');
        that.$attachment_icon = that.$form.find('.js-message-attachment');
        that.$skeleton_wrapper = that.$wrapper.closest(".c-messages-conversation-wrapper").find(".skeleton-wrapper");
        // VARS
        that.send_action_url = options["send_action_url"];

        that.file_template_html = options["file_template_html"];
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];
        that.is_images_enabled = options["is_images_enabled"],
        that.is_files_enabled = options["is_files_enabled"]
        that.hover_class = "is-hover";
        // DYNAMIC VARS
        that.files_controller = that.getFilesController();
        that.is_locked = false;
        // INIT
        that.initClass();
    };

    CRMImConversationSection.prototype.initClass = function() {
        var that = this;

        that.initSubmit();
    };

    CRMImConversationSection.prototype.getFilesController = function() {
        var that = this;
        // DOM
        var $drop_area = that.$form.find('.js-default-drop-area'),
            $upload_list = that.$form.find('.js-upload-list'),
            $input_field = that.$form.find(".js-drop-field");

        // VARS
        var file_template_html = that.file_template_html,
            uri = $.crm.app_url + "?module=file&action=uploadTmp";

        // DYNAMIC VARS
        that.files_storage = [];
        var upload_file_count = 0,
            hover_timeout = 0;

        that.$attachment_icon.on('click', function () {
            $input_field.click();
        });

        // Attach
        $input_field.on("change", function(e) {
            e.preventDefault();
            addFiles(this.files);
        });

        // Drop
        $drop_area.on("drop", function(e) {
            e.preventDefault();
            addFiles(e.originalEvent.dataTransfer.files);
        });

        // Drag
        $drop_area.on("dragover", onHover);

        document.onpaste = function (event) {
            var items = (event.clipboardData || event.originalEvent.clipboardData).files;
            addFiles(items);
        }
        // Delete
        that.$wrapper.on("click", ".js-file-delete", function(e) {
            e.preventDefault();
            deleteFile( $(this).closest(".c-upload-item") )

            if (!that.files_storage.length && that.$textarea.val() === '') {
                if (!that.$submitButton.prop('disabled')) that.$submitButton.prop('disabled', true)
            }
        });

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    that.files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                $input_field.val('');
            }
        }

        function renderFile(file) {
            var $upload_item = $(file_template_html),
                $name = $upload_item.find(".js-name");
            $name.text(file.name);
            $upload_list.prepend($upload_item);

            if (that.$submitButton.prop('disabled')) that.$submitButton.prop('disabled', false)

            return $upload_item;
        }

        function deleteFile($file) {
            var result = [];

            $.each(that.files_storage, function(index, item) {
                if ($file[0] !== item.$file[0]) {
                    result.push(item);
                } else {
                    $file.remove();
                }
            });

            that.files_storage = result;
        }

        function uploadFiles(data, dataType, callback) {
            var afterUploadFiles = ( callback ? callback : function() {} );
            if (that.files_storage.length) {
                upload_file_count = that.files_storage.length;

                $.each(that.files_storage, function(index, file_item) {
                    uploadFile(file_item);
                });
            } else {

                afterUploadFiles();
            }

            function uploadFile(file_item) {

                const fileType = file_item.file.type.split('/')[0] === 'image' ? 'image' : 'file';
                const fileTypeCheck = dataType ? fileType === dataType : true;

                var $file = file_item.$file,
                    $deleted_icon = $file.find('.js-file-delete'),
                    $status = $file.find(".js-status"),
                    $success_icon = $file.find(".js-progress-success-icon"),
                    $bar = $file.find(".js-bar");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    if (fileTypeCheck) request()
                    else {
                        upload_file_count -= 1;
                        if (upload_file_count <= 0) {
                            afterUploadFiles();
                        }
                    }
                } else {
                    $status.addClass("errormsg");
                    $file.removeClass("is-upload");
                    that.is_locked = false;
                    that.$submitButton.prop('disabled', false);
                    that.$submitButton.find('svg').removeClass('fa-spinner fa-spin').addClass('fa-arrow-up');
                    that.$textarea.attr("disabled", false);
                    $input_field.prop("disabled", false);
                    that.$form.find('button').prop("disabled", false);
                }

                function request() {
                    var form_data = new FormData(),
                        matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        form_data.append("_csrf", csrf);
                    }

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                form_data.append(item.name, item.value);
                            }
                        });
                    }

                    form_data.append("file_size", file_item.file.size);
                    form_data.append("files", file_item.file);
                    form_data.append("file_end", 1);

                    // Ajax request
                    $.ajax({
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function(e){
                                if (e.lengthComputable) {
                                    var percent = parseInt( (e.loaded / e.total) * 100 );

                                    $bar.css('--progress-value', percent);
                                    if (percent === 100) {
                                        $bar.css('background', 'transparent');
                                        $success_icon.show();
                                    }
                                }
                            }, false);
                            return xhr;
                        },
                        url: uri,
                        data: form_data,
                        cache: false,
                        contentType: false,
                        processData: false,
                        type: 'POST',
                        success: function(data){
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
                                }
                            }, 250);
                        }
                    }).always( function () {
                        is_locked = false;
                    });
                }
            }
        }

        function onHover(event) {
            event.preventDefault();
            $drop_area.addClass(that.hover_class);
            clearTimeout(hover_timeout);
            hover_timeout = setTimeout( function () {
                $drop_area.removeClass(that.hover_class);

            }, 100);
        }

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMImConversationSection.prototype.initSubmit = function() {
        var that = this,
            $textarea = that.$textarea,
            $submitButton = that.$submitButton,
            $upload_list = that.$form.find('.js-upload-list'),
            files_prefix = that.is_images_enabled ? 'files-' : '',
            $input_field = that.$form.find(".js-drop-field"),
            type_prefix = that.is_images_enabled ? 'file' : false;
            $main_wrapper = $(".c-messages-page-content-wrapper");
            that.$message_list = $main_wrapper.find(".js-messages-list");
            that.is_locked = false;

         $(document).on('wa_before_load', function(){
             $(document).off('wa_before_load');
         });

        $textarea.on("keydown", function(e) {
            var use_enter = (e.keyCode === 13 || e.keyCode === 10);
            if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
                e.preventDefault();
                if ($textarea.val() === '' && !that.files_storage.length) {
                    $textarea.addClass('shake animated');
                    setTimeout(function(){
                        $textarea.removeClass('shake animated');
                    },500);
                }
                else {
                    that.$form.submit();
                }
            }
        });

        $textarea.on("focus", function(e) {
            that.$replySection.addClass('focused')
        })

        $textarea.on("blur", function(e) {
            that.$replySection.removeClass('focused')
        })

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
            }
            else {
                if ($textarea.val() === '' && !that.files_storage.length) {
                    if (!$submitButton.prop('disabled')) $submitButton.prop('disabled', true)
                }
                else {
                    if ($submitButton.prop('disabled')) $submitButton.prop('disabled', false)
                }
            }
            toggleHeight();

        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            if (!that.is_locked) {
                that.is_locked = true;
                $submitButton.prop('disabled', true);
                $submitButton.find('svg').removeClass('fa-arrow-up').addClass('fa-spinner fa-spin');

                that.$form.find('button').prop("disabled", true);

                    if (that.is_images_enabled || that.is_files_enabled) {

                        var files_data = [
                            {
                                "name": "hash",
                                "value": files_prefix + that.hash
                            }
                        ];

                        if (that.is_images_enabled) {
                            var photos_data = [
                                {
                                    "name": "hash",
                                    "value": 'photos-' + that.hash
                                }
                            ];

                            that.files_controller.uploadFiles(photos_data, 'image', uploadFilesData);
                        }

                        else {
                            uploadFilesData();
                        }

                        function uploadFilesData() {
                            that.files_controller.uploadFiles(files_data, type_prefix, submit);
                        }

                    } else {
                        submit();
                    }

                    function submit() {

                        var href = that.send_action_url,
                            data = that.$form.serializeArray();
                            $textarea.attr("disabled", true);
                            $input_field.prop("disabled", true);

                        $.post(href, data, function(response) {
                            if (response.status === "ok") {
                                    $(document).trigger('msg_conversation_update');
                                    clearInput();

                                    $submitButton.prop('disabled', true);
                                    toggleHeight();
                                    if ($('#c-messages-page #c-messages-sidebar').length) {
                                        $(document).trigger('msg_sidebar_upd_needed');
                                    }

                            } else {
                                console.error(response);
                                var error_box = that.$form.find('#tooltip-error-message'),
                                    error_msg = Object.values(response.errors);
                                    error_box.addClass('errormsg');
                                    $.each(error_msg, function(i, err){
                                        error_box.append(`<span><i class="fas fa-exclamation-triangle state-error"></i> <span class="small">${err}</span></span>`);
                                    })

                                $textarea.addClass('shake animated');
                                setTimeout(function(){
                                    $textarea.removeClass('shake animated');
                                },500);
                                that.is_locked = false;
                                clearInput();
                                $submitButton.prop('disabled', true);
                                toggleHeight();

                                $textarea.on('focus', clearErrors);
                                that.$form.on('change', clearErrors);
                            }
                        }).always( function() {
                            that.is_locked = false;
                        });
                    }
                }

                function clearErrors() {
                    var error_box = that.$form.find('#tooltip-error-message');
                    error_box.removeClass('errormsg').html('');
                    that.$form.off('change', clearErrors);
                    $textarea.off('focus', clearErrors);
                }

                function clearInput() {

                    $submitButton.prop('disabled', false);
                    $submitButton.find('svg').removeClass('fa-spinner fa-spin').addClass('fa-arrow-up');
                    $textarea.attr("disabled", false);
                    $input_field.prop("disabled", false);
                    that.$form.find('button').prop("disabled", false);
                    $textarea.val('');

                    var width = $(window).width();
                    if (width > 760) {
                        $textarea.focus();
                    }
                    if (that.files_storage.length) {
                        var $exist_files = that.$form.find(".c-upload-item");
                        $.each($exist_files, function(index, item) {
                            item.remove();
                        });
                        that.files_storage = [];
                    }
                }
        });

        function toggleHeight() {

            $textarea.css("min-height", 'auto');
            var scroll_h = $textarea[0].scrollHeight;
                if (scroll_h < 250) {
                $textarea.css("min-height", scroll_h + "px")}
                else {$textarea.css("min-height", "250px")};

        }

    };

    return CRMImConversationSection;

})(jQuery);

var CRMAssociateDealDialog = ( function($) {
    CRMAssociateDealDialog = function(options) {
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
        that.$deal_save_details = that.$form.find('.js-deal-save-details'),

        // VARS
        that.$dialog = that.$wrapper.data("dialog");
        that.submit_url = options["submit_url"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMAssociateDealDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initSelectDeal();
        //
        that.initSubmit();
    };

    CRMAssociateDealDialog.prototype.initSelectDeal = function() {
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
            that.$deal_save_details.removeClass('hidden');
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
            that.$deal_save_details.removeClass('hidden');
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

    CRMAssociateDealDialog.prototype.initSubmit = function() {
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
                that.$deal_name.addClass('shake animated');
                setTimeout(function(){
                    that.$deal_name.removeClass('shake animated');
                },500);
                return false;
            }

            submit();
        });

        function submit() {
            var $loading = $('<span class="icon size-16"><i class="fas fa-spinner wa-animation-spin custom-mr-4"></i></span>'),
                href = that.submit_url,
                data = that.$form.serializeArray();

            that.$submit.prop('disabled', true);
            that.$footer.append($loading);

            $.post(href, data, function(res){
                if (res.status === "ok") {
                    $.crm.content.reload();
                    that.$dialog.close();
                } else {
                    that.$submit.prop('disabled', false);
                    $loading.remove();
                }
            });
        }
    };

    return CRMAssociateDealDialog;

})(jQuery);
