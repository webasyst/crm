var CRMMessagesPage = ( function($) {

    CRMMessagesPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.dialog = ( that.$wrapper.data("dialog") || false );

        // VARS
        that.locales = options["locales"];
        that.app_url = options['app_url'] || ($ && $.crm && $.crm.app_url);
        that.is_dialog = ( options["is_dialog"] || false );

        if (!that.is_dialog) {
            that.page = options["page"];
            that.message_ts = options["message_ts"];
        }

        // Messages Operations
        that.settings = options;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessagesPage.prototype.initClass = function() {
        var that = this;
        //
        $.crm.renderSVG(that.$wrapper);
        //
        that.initWriteNewMessage();
        //
        that.initMessageShowBodyLink();
        //
        that.initMessagesByGroupLink();
        //
        that.initSwitchLoader();
        //
        if (!that.is_dialog && !that.page) {
            that.initBackgroundUpdate();
        }
        new CRMPageMessagesOperations(that.settings);
    };

    // PAGE EVENT
    CRMMessagesPage.prototype.initWriteNewMessage = function() {
        var that = this;

        that.$wrapper.on('click', '.js-write-message', function () {
            var url = '?module=message&action=writeNewDialog';
            $.get(url, function(html) {
                new CRMDialog({
                    html: html
                });
            });
        })
    };

    CRMMessagesPage.prototype.initMessagesByGroupLink = function() {
        var that = this,
            dialog = that.dialog;

        that.$wrapper.on("click", ".js-message-by-group", function() {
            var $tr = $(this).parents("tr.c-message-wrapper"),
                last_message_id = $tr.data('last-message-id');
            if (dialog) { dialog.hide(); }

            $.get($(this).data('dialog-url'), function(html) {
                // Init the dialog
                new CRMDialog({
                    html: html,
                    onOpen: function ($dialog_wrapper, dialog) {
                        that.initMessageReplyBtn($dialog_wrapper, dialog);
                        that.initMessageForwardBtn($dialog_wrapper, dialog);

                        $dialog_wrapper.on("click", ".c-message-wrapper a", function(event) {
                            event.preventDefault();
                        });

                        $dialog_wrapper.on("click", ".c-message-wrapper", function(event) {
                            event.preventDefault();

                            var target = event.target,
                                $target = $(target),
                                $sub_tr = $(this),
                                message_id = $sub_tr.data('id'),
                                is_link = $target.attr("href");

                            if (last_message_id == message_id) {
                                $tr.removeClass('bold');
                            }

                            if (!is_link) {
                                var $link = $sub_tr.find(".js-message-show-body");
                                if ($link.length) {
                                    $link.trigger("click");
                                }
                            }
                        });
                    },
                    onClose: function() {
                        if (dialog) {
                            dialog.show();
                        }
                    }
                });
            });
        });
    };

    // DIALOG EVENTS

    CRMMessagesPage.prototype.initMessageShowBodyLink = function() {
        var that = this,
            dialog = that.dialog;

        that.$wrapper.find(".js-message-show-body").on("click", 'td:not(.c-checkbox)', function() {
            var $tr = $(this).parent();

            $.get($tr.data('dialog-url'), function(html) {
                if (dialog) { dialog.hide(); }

                // Init the dialog
                new CRMDialog({
                    html: html,
                    onOpen: function ($dialog_wrapper, dialog) {
                        $tr.removeClass("bold");
                        //
                        that.initMessageReplyBtn($dialog_wrapper, dialog);
                        //
                        that.initMessageForwardBtn($dialog_wrapper, dialog);
                    },
                    onClose: function() {
                        if (dialog) { dialog.show(); }
                    }
                });
            });
        });
    };

    CRMMessagesPage.prototype.initMessageReplyBtn = function($dialog_wrapper, dialog) {
        var that = this,
            $footer = $dialog_wrapper.find('.js-dialog-footer'),
            $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>');

        $dialog_wrapper.on('click', '.js-message-reply', function () {
            var message_id = $(this).data('message-id');
            $footer.append($loading);

            var href = that.app_url+'?module=message&action=writeReplyDialog',
                params = { id: message_id };

            $.post(href, params, function(html) {
                dialog.hide();

                new CRMDialog({
                    html: html,
                    onOpen: function () {
                        $loading.remove();
                    },
                    onClose: function () {
                        $loading.remove();
                        dialog.show();
                    }
                });
            });
        })
    };

    CRMMessagesPage.prototype.initMessageForwardBtn = function($dialog_wrapper, dialog) {
        var that = this,
            $footer = $dialog_wrapper.find('.js-dialog-footer'),
            $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>');

        $dialog_wrapper.on('click', '.js-message-forward', function () {
            var message_id = $(this).data('message-id');
            $footer.append($loading);

            var href = that.app_url+'?module=message&action=writeForwardDialog',
                params = { id: message_id };

            $.post(href, params, function(html) {
                dialog.hide();

                new CRMDialog({
                    html: html,
                    onOpen: function () {
                        $loading.remove();
                    },
                    onClose: function () {
                        dialog.show();
                    }
                });
            });
        })
    };

    // OTHER

    CRMMessagesPage.prototype.initSwitchLoader = function() {
        var that = this;

        that.$wrapper.on('click', '.js-switch-message-view', function () {
            $(this).find('.icon16').attr('class', 'icon16 loading');
        })
    };

    CRMMessagesPage.prototype.initBackgroundUpdate = function() {
        var that = this,
            message_ts = that.message_ts,
            is_locked = false,
            timeout = 0;

        runner();

        function runner() {
            clearTimeout(timeout);
            timeout = setTimeout(request, 120000);
        }

        function request() {
            if (!is_locked) {
                is_locked = true;

                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    var href = "?module=message&action=ts&background_process=1",
                        data = {
                            message_ts: message_ts
                        };

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            var is_changed = (response.data !== message_ts),
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
            return !!($(".crm-dialog-wrapper:visible").length);
        }

        function reload() {
            var content_uri = $.crm.app_url + "message/?view=all&reload=1";
            $.get(content_uri, function(html) {
                $.crm.content.$content.html( html );
            });
        }
    };

    return CRMMessagesPage;

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

        //
        that.initSelectDeal();
        //
        that.initMessageDeleteLink();
    };

    CRMMessageBodyDialog.prototype.initSelectDeal = function() {
        var that = this,
            $deal_form = that.$wrapper.find('.deal-form'),
            $deal_name = $deal_form.find('.js-deal-name'),
            $deal_name_input = $deal_form.find('.js-deal-name-input'),
            $deal_save = $deal_form.find('.js-save-deal'),
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
            var href = '?module=deal&action=byContact&id=' + contact_id;
            $.get(href, function(response) {
                if (response.status === "ok") {
                    // rendering contact deals
                    $.each(response.data.deals, function (i, deal) {
                        $deals_list.prepend(renderDeals(deal,response.data.funnels[deal.funnel_id]));
                    });
                    //
                    $.crm.renderSVG(that.$wrapper);
                }
            }, "json");
        }

        // New deal
        $deal_form.on('click', '.js-create-new-deal', function () {
            $deal_id.val('0');
            $deals_dropdown.addClass('hidden');
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
            $deal_form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });

        function renderDeals(deal,funnel) {
            return '<li><a href="javascript:void(0);" class="js-deal-item" data-deal-id="'+ deal.id +'"><span class="js-text"><i class="icon16 funnel-state svg-icon" data-color="'+ funnel.stages[deal.stage_id].color +'"></i><b><i>'+ deal.name +'</i></b></span></a></li>';
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

    CRMMessageDeleteLinkMixin.mixInFor(CRMMessageBodyDialog);

    return CRMMessageBodyDialog;

})(jQuery);

var CRMMessagesListPage = ( function($) {

    CRMMessagesListPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.page = options["page"];
        that.last_message_id = options["last_message_id"];

        // Messages Operations
        that.settings = options;

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMMessagesListPage.prototype.initClass = function() {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.$wrapper.on("click", ".js-associate-deal", function(event) {
            event.preventDefault();

            var href = $(this).data('dialog-url');

            $.get(href, function(html) {
                new CRMDialog({
                    html: html
                });
            });
        });

        that.$wrapper.on('click', '.js-write-message', function(event) {
            event.preventDefault();

            var url = $.crm.app_url + '?module=message&action=writeNewDialog';

            $.get(url, function(html) {
                new CRMDialog({
                    html: html
                });
            });
        });

        that.$wrapper.on('click', '.js-switch-message-view', function() {
            $(this).find('.icon16').attr('class', 'icon16 loading');
        });

        if (!that.page) {
            that.initBackgroundUpdate();
        }

        new CRMPageMessagesOperations(that.settings);
    };

    CRMMessagesListPage.prototype.initBackgroundUpdate = function() {
        var that = this,
            last_message_id = that.last_message_id,
            is_locked = false,
            timeout = 0;

        runner();

        function runner() {
            clearTimeout(timeout);
            timeout = setTimeout(request, 120000);
        }

        function request() {
            if (!is_locked) {
                is_locked = true;

                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {

                    var href = "?module=message&action=listByConversation",
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
            return !!($(".crm-dialog-wrapper:visible").length);
        }

        function reload() {
            var content_uri = $.crm.app_url + "message/?view=conversation&reload=1";

            $.get(content_uri, function(html) {
                $.crm.content.$content.html( html );
            });
        }
    };

    return CRMMessagesListPage;

})(jQuery);
