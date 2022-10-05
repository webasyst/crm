'use strict';
var CRMDeal = ( function($) {

    var StickyHeader = ( function($) {

        StickyHeader = function(options) {
            var that = this;

            // DOM
            that.$window = $(window);
            that.$wrapper = options["$wrapper"];
            that.$outer_wrapper = options["$outer_wrapper"];

            // CONST
            that.debug = options["debug"];
            that.indent = { top: 0 };

            // VARS
            that.$clone = null;
            that.offset = that.getOffset();
            that.type = null;

            // INIT
            that.init();
        };

        StickyHeader.prototype.init = function() {
            var that = this;

            that.$clone = renderClone(that.$wrapper);
            function renderClone($wrapper) {
                var $clone = $("<div />");
                $wrapper.before($clone);
                return $clone.hide();
            }

            // INIT

            that.onScroll();

            // EVENTS

            var timeout = 0;
            that.$window.on("resize", function() {
                clearTimeout(timeout);
                timeout = setTimeout( function() {
                    that.reset();
                    that.onScroll();
                }, 50);
            });

            $(document).on("refresh", refreshWatcher);
            function refreshWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.reset();
                    that.onScroll();
                } else {
                    $(document).off("refresh", refreshWatcher);
                }
            }

            that.$window.on("scroll", watcher);
            function watcher() {
                var is_exist = $.contains(that.$window[0].document, that.$wrapper[0]);
                if (is_exist) {
                    that.onScroll();
                } else {
                    that.$window.off("scroll", watcher);
                }
            }
        };

        StickyHeader.prototype.onScroll = function() {
            var that = this;

            var scroll_top = that.$window.scrollTop();

            // DOM
            var $window = that.$window;

            // SIZE
            var display_w = $window.width(),
                display_h = $window.height(),
                offset = that.offset,
                outer_wrapper_h = that.$outer_wrapper.outerHeight(),
                min_w = 0;

            //
            var disable_scroll = ( !(display_w > min_w) || outer_wrapper_h <= that.offset.height);

            if (disable_scroll) {
                that.fixTo("default");

            // если сколлтоп меньше чем начало блока
            } else if (scroll_top <= offset.top - that.indent.top) {
                // ничего не делаем
                that.fixTo("default");

            // доскролили до начала блока
            } else {
                that.fixTo("fix_top");
            }

        };

        /**
         * @param {String} type
         * @param {Object?} options
         * */
        StickyHeader.prototype.fixTo = function(type, options) {
            var that = this,
                active_class = "is-fixed-top";

            type = (type ? type : null);

            if (that.type !== type) {
                if (type === "fix_top") {
                    fixTop();
                } else {
                    reset();
                }

                $(document).trigger("refresh-icons");

                that.type = type;
                that.log(type);
            }

            function reset() {
                that.$wrapper
                    .removeClass(active_class)
                    .removeAttr("style");

                that.$clone.hide().height(0);

                that.$wrapper[0].scrollTop = 0;
            }

            function fixTop() {
                that.$wrapper
                    .removeAttr("style");

                var lift = 20;

                that.$clone.css({
                    height: that.$wrapper.outerHeight()
                }).show();

                that.$wrapper
                    .css({
                        position: "fixed",
                        top: that.indent.top,
                        left: that.offset.left - (lift/2),
                        width: that.offset.width + lift
                    });

                that.$wrapper
                    .addClass(active_class);
            }
        };

        StickyHeader.prototype.reset = function() {
            var that = this;

            that.fixTo("default");

            that.offset = that.getOffset();
        };

        StickyHeader.prototype.getOffset = function() {
            var that = this;

            var offset = that.$wrapper.offset();

            return {
                top: offset.top,
                left: offset.left,
                width: that.$wrapper.outerWidth(),
                height: that.$wrapper.outerHeight()
            };
        };

        /**
         * @param {String} string
         * */
        StickyHeader.prototype.log = function(string) {
            var that = this;

            if (that.debug) {
                console.log(string);
            }
        };

        return StickyHeader;

    })(jQuery);

    var StickySidebar = ( function($) {

        StickySidebar = function(options) {
            var that = this;

            // DOM
            that.$window = $(window);
            that.$wrapper = options["$wrapper"];
            that.$outer_wrapper = options["$outer_wrapper"];

            // CONST
            that.debug = options["debug"];
            that.indent = options["indent"];

            // VARS
            that.$clone = null;
            that.offset = that.getOffset();
            that.type = null;

            // INIT
            that.init();
        };

        StickySidebar.prototype.init = function() {
            var that = this;

            that.$clone = renderClone(that.$wrapper);
            function renderClone($wrapper) {
                var $clone = $("<div />");
                $wrapper.before($clone);
                return $clone.hide();
            }

            // INIT
            that.onScroll();

            // EVENTS

            var timeout = 0;
            that.$window.on("resize", function() {
                clearTimeout(timeout);
                timeout = setTimeout( function() {
                    that.reset();
                    that.onScroll();
                }, 50);
            });

            $(document).on("refresh", refreshWatcher);
            function refreshWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.reset();
                    that.onScroll();
                } else {
                    $(document).off("refresh", refreshWatcher);
                }
            }

            that.$window.on("scroll", watcher);
            function watcher() {
                var is_exist = $.contains(that.$window[0].document, that.$wrapper[0]);
                if (is_exist) {
                    that.onScroll();
                } else {
                    that.$window.off("scroll", watcher);
                }
            }
        };

        StickySidebar.prototype.onScroll = function() {
            var that = this;

            var scroll_top = that.$window.scrollTop(),
                indent = getIndent(that);

            // DOM
            var $window = that.$window;

            // SIZE
            var display_w = $window.width(),
                display_h = $window.height(),
                offset = that.offset,
                outer_wrapper_h = that.$outer_wrapper.outerHeight(),
                min_w = 0;

            //
            var disable_scroll = ( !(display_w > min_w) || outer_wrapper_h <= that.offset.height);

            if (disable_scroll) {
                that.fixTo("default");

            // если сколлтоп меньше чем начало блока
            } else if (scroll_top <= offset.top - indent) {
                // ничего не делаем
                that.fixTo("default");

            // доскролили до начала блока
            } else {
                that.fixTo("fix_top");
            }

        };

        /**
         * @param {String} type
         * @param {Object?} options
         * */
        StickySidebar.prototype.fixTo = function(type, options) {
            var that = this;

            type = (type ? type : null);

            var active_class = "is-fixed";

            if (type === "fix_top") {
                fixTop();
                if (that.type !== type) {
                    that.$wrapper.addClass(active_class);
                }
            } else {
                reset();
                if (that.type !== type) {
                    that.$wrapper.removeClass(active_class);
                }
            }

            that.type = type;
            that.log(type);

            function reset() {
                var height = that.getHeight();

                that.$wrapper.css({
                    position: "static",
                    top: "auto",
                    left: "auto",
                    width: "auto",
                    height: height
                });

                that.$clone.hide().height(0);

                // that.$wrapper[0].scrollTop = 0;
            }

            function fixTop() {
                var height = that.getHeight();

                that.$wrapper.css({
                    position: "fixed",
                    top: getIndent(that),
                    left: that.offset.left,
                    width: that.offset.width,
                    height: height
                });

                that.$clone.css({
                    height: height
                }).show();
            }
        };

        StickySidebar.prototype.reset = function() {
            var that = this;

            that.fixTo("default");

            that.offset = that.getOffset();
        };

        StickySidebar.prototype.getOffset = function() {
            var that = this;

            var offset = that.$wrapper.offset();

            return {
                top: offset.top,
                left: offset.left,
                width: that.$wrapper.outerWidth(),
                height: that.$wrapper.outerHeight()
            };
        };

        /**
         * @param {String} string
         * */
        StickySidebar.prototype.log = function(string) {
            var that = this;

            if (that.debug) {
                console.log(string);
            }
        };

        StickySidebar.prototype.getHeight = function() {
            var that = this,
                result = 0;

            var display_h = that.$window.height();

            var top_lift = getIndent(that);
            if (that.offset.top - that.$window.scrollTop() > top_lift) {
                top_lift = that.offset.top - that.$window.scrollTop();
            }

            var outer_wrapper_bottom_case = that.$outer_wrapper.offset().top + that.$outer_wrapper.outerHeight(),
                visible_height = that.$window.height() + that.$window.scrollTop(),
                bottom_lift = (outer_wrapper_bottom_case - visible_height < 0 ? Math.abs(outer_wrapper_bottom_case - visible_height) : 0 );

            result = (display_h - top_lift - bottom_lift);

            return result;
         };

        return StickySidebar;

        function getIndent(that) {
            var result = 0;

            if (that.indent) {
                result = ( typeof that.indent === "function" ? that.indent() : that.indent );
            }

            return result;
        }

    })(jQuery);

    CRMDeal = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$aside = that.$wrapper.find(".c-deal-aside");
        that.$log_section = that.$wrapper.find('.js-deal-log-section');

        // VARS
        that.locales = options["locales"];
        that.templates = options["templates"];

        that.deal = options.deal || {};
        that.deal_id = that.deal.id || 0;
        that.order_id = options.order_id || 0;
        that.user_id = options["user_id"];
        that.funnel_id = options["funnel_id"];
        that.can_edit_deal = options.can_edit_deal || false;
        that.shop_in_welcome_stage = options.shop_in_welcome_stage || false;

        // DYNAMIC VARS
        that.is_locked = false;
        that.remove_is_locked = false;

        // INIT
        that.init();
    };

    CRMDeal.prototype.init = function() {
        var that = this;
        //
        $.crm.renderSVG( that.$wrapper );
        //
        $.crm.sidebar.reload();

        if (that.can_edit_deal) {

            //
            that.initChangeState();
            //
            that.initCloseDeal();
            //
            that.initReopenDeal();
            //
            that.initAddContact();
            //
            that.initRemoveFiles();
            //
            that.initEditableName();
            //
            that.initChangeDate();
            //
            that.initChangePrice();
            //
            that.initChangeField();
            //
            that.initWYSIWYG();
            //
            that.initChangeDescription();
            //
            that.initChangeUser();
            //
            that.initChangeClient();
            //
            that.initDealDelete();
            //
            that.initAssignTags();
            //
            that.initCreateOrderLink();
            //
            that.initEditOrderLink();
        }

        //
        that.initCall();
        //
        that.initMessages();
        //
        that.initOrderEditShippingDetailLink();

        that.initSticky();

        that.initOrderSection();

        that.initScroll();

        if (that.deal_id) {
            that.initLogSection();
        }

        // EVENTS

        that.$wrapper.on("click", ".js-remove-owner", function(event) {
            event.preventDefault();
            if (!that.remove_is_locked) {
                that.removeOwner( $(this).closest(".c-contact") );
            }
        });

        that.$wrapper.on("click", ".js-remove-contact", function(event) {
            event.preventDefault();
            if (!that.remove_is_locked) {
                that.removeContact( $(this).closest(".c-contact") );
            }
        });

        that.$wrapper.on("click", ".js-add-external-contact", function(event) {
            event.preventDefault();
            that.addExternalContact( $(this) );
        });
    };

    CRMDeal.prototype.initLogSection = function() {
        var that = this,
            deal_id = that.deal_id,
            $log_section = that.$log_section;
        $log_section.on('click', '.js-open-conversation', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (href.indexOf('?') === -1) {
                window.location.href = href + '?deal_id=' + deal_id;
            } else {
                window.location.href = href + '&deal_id=' + deal_id;
            }
        });
    };

    CRMDeal.prototype.initScroll = function() {
        var that = this;

        var $window = $(window),
            $icon = $("<div class=\"c-scroll-action\"><div class=\"c-icon to-top\"></div></div>");

        var is_shown = false;

        //

        var $body = that.$wrapper.find(".c-deal-board"),
            $aside = that.$wrapper.find(".c-deal-aside-wrapper");

        $body.append( $icon );

        onScroll();

        onResize();

        $icon.on("click", function() {
            $("html, body").animate({
                scrollTop: 0
            }, 500);
        });

        $window.on("scroll", scrollWatcher);
        function scrollWatcher() {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                onScroll();
            } else {
                $window.off("scroll", scrollWatcher);
            }
        }

        $window.on("resize", resizeWatcher);
        function resizeWatcher() {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                onResize();
            } else {
                $window.off("resize", resizeWatcher);
            }
        }

        function onScroll() {
            var scroll_top = $window.scrollTop();

            if (scroll_top > 0) {
                if (!is_shown) {
                    $icon.show();
                    is_shown = true;
                }
            } else {
                if (is_shown) {
                    $icon.hide();
                    is_shown = false;
                }
            }
        }

        function onResize() {
            var left = $aside.offset().left;
            $icon.css("right", $window.width() - left);
        }
    };

    CRMDeal.prototype.initSticky = function() {
        var that = this;

        var $header = that.$wrapper.find(".js-page-header"),
            $sidebar = that.$wrapper.find("#js-deal-aside");

        new StickyHeader({
            $wrapper: $header,
            $outer_wrapper: that.$wrapper,
            debug: false
        });

        new StickySidebar({
            $wrapper: $sidebar,
            $outer_wrapper: $sidebar.closest(".c-deal-board"),
            indent: function() {
                return $header.outerHeight();
            },
            debug: false
        });

        onAsideScroll();

        $sidebar.on("scroll", onAsideScroll);
        function onAsideScroll() {
            var scroll_top = $sidebar[0].scrollTop,
                active_class = "is-scrolled";

            if (scroll_top > 0) {
                $sidebar.addClass(active_class);

            } else {
                $sidebar.removeClass(active_class);
            }
        }
    };

    CRMDeal.prototype.initChangeState = function() {
        var that = this;

        var $funnel = that.$wrapper.find(".c-funnel-wrapper"),
            state_active_class = "selected";

        $funnel.on("click", ".c-state-item.js-set-state", onStateClick);

        function onStateClick( event ) {
            event.preventDefault();
            var $state = $(this);

            if ( $state.hasClass(state_active_class) ) {
                return false;
            }

            var new_state_id = $state.data("id");

            changeState(new_state_id);
        }

        function changeState(stage_id) {
            var href = "?module=deal&action=move",
                data = {
                    deal_id: that.deal_id,
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
                            onConfirm: function() {
                                var data = {
                                    deal_id: that.deal_id,
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
            }, "json");

            function refreshDeal() {
                var href = $.crm.app_url + "deal/" + that.deal_id + "/";
                $.crm.content.load(href);
            }
        }
    };

    CRMDeal.prototype.initCloseDeal = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-close-deal", showDialog);

        function showDialog(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=closeDialog",
                    data = {
                        id: that.deal_id
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
    };

    CRMDeal.prototype.initReopenDeal = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-reopen-deal", showDialog);

        function showDialog(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=reopen",
                    data = {
                        id: that.deal_id
                    };

                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        $.crm.content.reload();
                    } else {
                        alert("Error: Deal Reopen");
                    }
                }, "json").always( function() {
                    is_locked = false;
                })
            }
        }
    };

    CRMDeal.prototype.initAddContact = function() {
        var that = this,
            $wrapper = that.$aside.find(".js-add-user-wrapper"),
            $showLink = $wrapper.find(".js-add-contact");

        $showLink.on("click", function(event) {
            event.preventDefault();
            showToggle( $(this), true );
        });

        $wrapper.on("click", ".js-close-add-contact", function(event) {
            event.preventDefault();
            showToggle( $(this) );
        });

        initUserAutocomplete();

        //

        function showToggle( $link, show ) {
            var $wrapper = $link.closest(".c-add-contact"),
                active_class = "is-shown";

            if (show) {
                $showLink.hide();
                $wrapper.addClass(active_class);
            } else {
                $showLink.show();
                $wrapper.removeClass(active_class);
            }
        }

        function initUserAutocomplete() {
            var $fields = that.$aside.find(".js-contact-autocomplete");

            $fields.each( function() {
                var $field = $(this);

                $field.autocomplete({
                    appendTo: that.$aside,
                    source: $.crm.app_url + "?module=autocomplete&type=user&funnel_id=" + that.funnel_id,
                    minLength: 0,
                    html: true,
                    focus: function() {
                        return false;
                    },
                    select: function( event, ui ) {
                        selectUser(ui.item.id, $field.closest(".c-add-contact"));
                        $field.val("");
                        that.$aside.find(".js-close-add-contact").trigger("click");
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

                $field.on("focus", function(){
                    $field.data("uiAutocomplete").search( $field.val() );
                });
            });

            function selectUser(user_id, $contact) {
                var $user = that.$aside.find(".c-contact[data-id=\"" + user_id + "\"]");
                if ($user.length) {
                    markUser($user);
                }
                addContact( user_id, function(html) {
                    var $user = $(html);
                    $contact.before($user);
                    markUser($user);

                    $(document).trigger("refresh");
                });
            }

            function markUser( $users ) {
                var active_class = "is-highlight",
                    time = 3000;

                $users.addClass(active_class);
                setTimeout( function() {
                    $.each($users, function(index, item) {
                        if ($.contains(document, item)) {
                            $(this).removeClass(active_class);
                        }
                    });
                }, time);
            }
        }

        function addContact(contact_id, callback) {
            callback = (callback ? callback : function() {});

            var href = "?module=deal&action=userAdd",
                data = {
                    contact_id: contact_id,
                    deal_id: that.deal_id
                };

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    callback(response.data.html);
                } else if (response.errors) {
                    showErrors(response.errors);
                }
            }, "json");

            function showErrors(errors) {
                errors = (errors ? errors : []);

                $.each(errors, function(index, item) {
                    var name = item.name,
                        text = item.text;

                    var $error = $("<div class=\"errormsg\" />").html(text);

                    $showLink.one("click", function() {
                        $error.remove();
                    });

                    $wrapper.prepend($error);
                });
            }
        }
    };

    CRMDeal.prototype.initRemoveFiles = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".c-files-wrapper"),
            xhr;

        $wrapper.on("click", ".js-remove-file", confirmRemove);

        function confirmRemove(event) {
            event.preventDefault();

            var $link = $(this),
                $file = $link.closest(".c-file"),
                $name = $file.find(".c-name"),
                name = $name.text(),
                file_id = parseInt($file.data("id"));

            if (xhr) {
                xhr.abort();
            }

            var href = "?module=dialogConfirm",
                data = {
                    title: that.locales["remove_file_title"],
                    text: that.locales["remove_file_text"].replace(/%s/, name),
                    ok_button: that.locales["remove_file_button"]
                };

            if (file_id > 0) {
                xhr = $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html,
                        onConfirm: function() {
                            removeFile(file_id, $file);
                        }
                    });
                }).always( function() {
                    xhr = false;
                });
            } else {
                log("File ID is empty");
            }
        }

        function removeFile(file_id, $file) {
            var href = "?module=file&action=delete",
                data = {
                    id: file_id
                };

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    $file.remove();
                } else {
                    log("File Remove Error");
                }
            });
        }
    };

    CRMDeal.prototype.initEditableName = function () {
        var $name = this.$wrapper.find(".js-editable").first();
        if ($name.length <= 0) {
            return;
        }

        var save_url = $.crm.app_url + '?module=deal&action=rename',
            deal_id = this.deal_id,
            xhr = null;

        new CrmEditable({
            $wrapper: $name,
            onSave: function(that) {
                var text = that.$field.val(),
                    do_save = ( text.length && that.text !== text );

                if (do_save) {

                    if (xhr) {
                        xhr.abort();
                    }

                    var href = save_url,
                        data = {
                            id: deal_id,
                            name: text
                        };

                    that.$field.attr("disabled", true);
                    var $loading = $('<i class="icon16 loading"></i>')
                        .css("margin", "0 0 0 4px")
                        .insertAfter( that.$field );

                    xhr = $.post(href, data, function(r) {
                        that.$field.attr("disabled", false);
                        $loading.remove();
                        that.toggle("hide");

                        if (r && r.data && r.data.deal) {
                            that.text = r.data.deal.name;
                            that.$wrapper.text(r.data.deal.name);
                            $.crm.title.set(r.data.deal.name);
                        }

                    });

                } else {
                    if (!text.length) {
                        that.$field.val( that.text );
                    }
                    that.toggle("hide");
                }
            }
        });
    };

    CRMDeal.prototype.initChangeDate = function() {
        var that = this,
            $toggle = that.$wrapper.find(".js-date-toggle"),
            active_class = "is-shown";

        if (!$toggle.length) {
            return false;
        }

        var $link = $toggle.find(".js-link"),
            $span = $toggle.find(".js-span"),
            $input = $toggle.find(".js-datepicker"),
            $altField = $toggle.find(".js-field");

        $toggle.on("click", ".js-set-date", function(event) {
            event.preventDefault();
            showToggle(true);
        });

        $input.on("keydown keypress keyup", function(event) {
            var key = event.keyCode;
            if (key === 27) {
                showToggle();
            }
        });

        $toggle.on("click", ".js-close-edit-date", function() {
            showToggle();
        });

        initDatepicker();

        function showToggle(show) {
            if (show) {
                $toggle.addClass(active_class);
                $input.focus();
            } else {
                $toggle.removeClass(active_class);
                $input.datepicker("hide");
            }
        }

        function initDatepicker() {
            $input.datepicker({
                altField: $altField,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                onSelect: function() {
                    var view_value = $input.val(),
                        server_value = $altField.val(),
                        is_valid = checkDate(view_value);

                    if (is_valid) {
                        onChange(server_value);
                    } else {
                        return false;
                    }
                }
            });

            if ( !$input.val()) {
                $input.datepicker("setDate", "+0d");
            }
        }

        function onChange(value) {
            if (!$input.val()) {
                value = "";
            }

            var href = "?module=deal&action=changeExpectedDate",
                data = {
                    id: that.deal_id,
                    expected_date: value ? value : ""
                };

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    if (response.data && response.data.deal) {
                        var date = response.data.deal.expected_date;
                        renderDate(date);
                    }
                }
            }, "json");
        }

        function renderDate(date) {
            if (date) {
                $link.hide();
                $span.show()
                    .find(".js-name")
                    .text(date);
            } else {
                $span.hide()
                    .find(".js-name")
                    .text("");
                $link.show();
            }

            showToggle();
        }

        function checkDate(string) {
            var format = $.datepicker._defaults.dateFormat,
                is_valid = false;

            try {
                $.datepicker.parseDate(format, string);
                is_valid = true;
            } catch(e) {

            }

            return is_valid;
        }
    };

    CRMDeal.prototype.initChangePrice = function() {
        var that = this,
            $toggle = that.$wrapper.find(".js-price-toggle"),
            active_class = "is-shown";

        if (!$toggle.length) {
            return false;
        }

        // DOM

        var $link = $toggle.find(".js-link"),
            $span = $toggle.find(".js-span"),
            $amount = $toggle.find(".js-price-field"),
            $currency = $toggle.find(".js-currency-field");

        // VARS

        var error_class = "error";

        // EVENTS

        $toggle.on("click", ".js-set-price", function(event) {
            event.preventDefault();
            showToggle(true);
        });

        $toggle.on("click", ".js-save-price", function(event) {
            event.preventDefault();
            prepareSave();
        });

        $toggle.on("click", ".js-cancel", function(event) {
            event.preventDefault();
            showToggle();
        });

        $amount.on("focus", function() {
            if ($amount.hasClass(error_class)) {
                $amount.removeClass(error_class);
            }
        });

        $amount.on('keydown', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                prepareSave();
            }
        });

        // HANDLERS

        function showToggle(show) {
            if (show) {
                $toggle.addClass(active_class);
            } else {
                $toggle.removeClass(active_class);
            }
        }

        function save(amount, currency) {
            var href = "?module=deal&action=changeAmount",
                data = {
                    id: that.deal_id,
                    amount: amount ? amount : "",
                    currency_id: currency ? currency : ""
                };

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    if (response.data && response.data.deal) {
                        var amount = response.data.deal.amount;
                        renderPrice(amount);
                    }
                }
            }, "json");
        }

        function prepareSave() {
            var amount = $amount.val(),
                currency = $currency.val(),
                _amount = false;

            if (amount) {
                _amount = amount.replace(",", ".");
            }

            if (_amount >= 0) {
                save(amount, currency);
            } else {
                $amount.addClass(error_class);
            }
        }

        function renderPrice(amount) {
            if (amount) {
                $link.hide();
                $span.show().text(amount);
            } else {
                $span.hide().text("");
                $link.show();
            }

            showToggle();
        }
    };

    CRMDeal.prototype.initChangeField = function() {
        var that = this,
            $toggle = that.$wrapper.find(".js-field-toggle"),
            $options_section = $('.c-deal-options-section'),
            active_class = "is-shown",
            error_class = "error";

        if (!$toggle.length) {
            return false;
        }

        // DOM

        var $value = $toggle.find(".js-field-value");

        // EVENTS

        $toggle.on("click", ".js-output-value", function(event) {
            event.preventDefault();
            showToggle(this, true);
        });

        $toggle.on("click", ".js-save-field", function(event) {
            event.preventDefault();
            prepareSave(this);
        });

        $toggle.on("click", ".js-cancel", function(event) {
            event.preventDefault();
            $(document).trigger('mousedown');
        });

        $toggle.on("click", ".js-field-value-checkbox", function() {
            prepareSave(this);
        });

        $toggle.on("change", ".js-field-value-select", function(event) {
            event.preventDefault();
            prepareSave(this);
        });

        $toggle.on("click", ".js-field-value-radio-block input", function () {
            prepareSave($(this).closest('.js-field-value-radio-block'));
        });

        $toggle.on("click", ".js-field-value-radio-block .js-clear-link", function (event) {
            event.preventDefault();
            var $link = $(this),
                $block = $link.closest('.js-field-value-radio-block');
            $block.find(':checked').attr('checked', false);
            prepareSave($block);
        });

        $value.on("focus", function() {
            if ($value.hasClass(error_class)) {
                $value.removeClass(error_class);
            }
        });

        $(document).on("mousedown", function(event){
            if ($toggle.has(event.target).length === 0 && !$toggle.is(event.target)) {

                $toggle.each(function (i, el) {
                    var $field = $( el ),
                        textarea = $field.find( "textarea" );

                    if ($field.hasClass('js-textarea')) {
                        textarea.slideUp(function () {
                            $field.removeClass(active_class)
                                .removeAttr('style')
                                .prev().show();
                        });
                    }else{
                        $field.removeClass(active_class);
                    }
                });
            }
        });

        $value.on('keydown', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                if ($(this).hasClass('js-datepicker-field')) {
                    prepareSave($(this).siblings('.js-field-value'));
                } else {
                    prepareSave(this);
                }
            }
        });

        // HANDLERS

        function showToggle(that, show) {
            var $field_toggle = $(that).parents('.js-field-toggle');

            if (show) {
                $field_toggle.find('.c-hidden').css({
                    'height': $(that).height()+8
                });
                if ($(that).closest('.js-field-toggle').hasClass('js-textarea')) {
                    $field_toggle.addClass(active_class).find('.c-hidden').css('width', '100%');
                    $field_toggle.find( "textarea" ).slideDown()
                        .next().find('.icon16')
                        .removeClass('loading').addClass('disk');
                    $field_toggle.css({'width': '100%', 'margin-left': 0}).prev().hide();
                } else {
                    $field_toggle.addClass(active_class);
                    $field_toggle.find('.js-datepicker-field').focus();
                }
            } else {
                $(document).trigger('mousedown');
            }
        }

        initDatepicker();

        function initDatepicker() {
            var $datepicker = $options_section.find(".js-datepicker-field"),
                $alt_field  = $datepicker.siblings(".js-field-value");

            $datepicker.datepicker({
                altField: $alt_field,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                onSelect: function() {
                    var $hidden_field = $(this).siblings('.js-field-value');
                    prepareSave($hidden_field);
                }
            });
        }

        function prepareSave(that) {
            var deal_id    = $options_section.data('deal-id'),
                value      = '',
                field_id = $(that).data('field-id'),
                field_type = $(that).data('field-type'),
                has_error  = false;


            switch (field_type) {
                case 'checkbox':
                    if ($(that).prop('required') && !$(that).prop('checked')) {
                        has_error = true;
                        $(that).addClass(error_class);
                    } else {
                        value = Number($(that).prop('checked'));
                    }
                    break;
                case 'date':
                    var $datepicker = $(that).siblings(".js-datepicker-field"),
                        format      = $.datepicker._defaults.dateFormat,
                        is_valid    = false;

                    try {
                        $.datepicker.parseDate(format, $datepicker.val());
                        is_valid = true;
                    } catch(e) {

                    }

                    if (($datepicker.val() == "" || $datepicker.val().trim() == "" || !is_valid) && $(that).prop('required')) {
                        has_error = true;
                        $datepicker.addClass(error_class);
                    } else {
                        value = $datepicker.val();
                    }
                    break;
                case 'text':
                    var $input = $(that).parents('.c-hidden').find('.js-field-value-text');

                    $input.next().find('.icon16').removeClass('disk').addClass('loading');

                    if (($input.val() == "" || $input.val().trim() == "") && $input.prop('required')) {
                        has_error = true;
                        $(that).parents('.c-hidden').find('.js-field-value-text').addClass(error_class);
                    } else {
                        value = $input.val();
                    }
                    break;
                case 'select':
                    var val      = $(that).val(),
                        is_valid = false;

                    $(that).find('option').each(function(i, el) {
                        if ($(el).val() == val) {
                            is_valid = true;
                        }
                    });

                    if (!is_valid || (val == "" && $(that).prop('required'))) {
                        has_error = true;
                        $(that).addClass(error_class);
                    } else {
                        value = val;
                    }
                    break;
                case 'radio':
                    var $block = $(that);
                    value = $block.find(':checked').val();
                    break;

                case 'number':
                    var val = $(that).val().replace(",", ".");

                    if ((val == "" || isNaN(Number(val))) && $(that).prop('required')) {
                        has_error = true;
                        $(that).addClass(error_class);
                    } else {
                        value = val;
                    }
                    break;
                default:
                    var val = $(that).val();

                    if ((val == "" || val.trim() == "") && $(that).prop('required')) {
                        has_error = true;
                        $(that).addClass(error_class);
                    } else {
                        value = val;
                    }
                    break;
            }

            if (!has_error) {
                var data = {
                    id: deal_id,
                    field_id: field_id,
                    value: value,
                    value_type: field_type
                };

                save(that, data);
            }
        }

        function save(that, data) {
            var href = "?module=deal&action=changeValue";

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    if (response.data && response.data.deal_params) {
                        var value = response.data.deal_params.value;
                        renderField(that, value);
                    }
                } else {
                    $(that).addClass(error_class);
                }
            }, "json");
        }

        function renderField(that, value) {
            var $output = $(that).parents('.js-field-toggle').find('.js-output-value');
            if (value) {
                $output.text(value);
                $(that).removeClass(error_class);
                showToggle(that, false);
            } else {
                $output.text($output.data('empty-text'));
                $(that).siblings('.js-datepicker-field').datepicker( "hide" );
                showToggle(that, false);
            }
        }

        //setEqualHeight();

        function setEqualHeight() {
            var $section = $options_section.find(".js-field-toggle"),
                $c_visible = $section.find('.c-visible'),
                $c_visible_height = $c_visible.height(),
                $c_hidden = $section.find('.c-hidden'),
                $c_hidden_fields_height = $c_hidden.find(':only-child').height(),
                diff_count = $c_visible_height - $c_hidden_fields_height;

            console.log($c_hidden.find(':only-child'),$c_visible)
            if (diff_count > 0) {
                $c_hidden.css('padding-bottom', diff_count)
            }
        }
    };

    CRMDeal.prototype.initWYSIWYG = function() {
        var that = this;

        var $textarea = that.$wrapper.find(".js-wysiwyg");
        if (!$textarea.length) {
            return false;
        }

        $.crm.initWYSIWYG($textarea, {
            keydownCallback: function (e) {

            }
        });
    };

    CRMDeal.prototype.initChangeDescription = function() {
        var that = this,
            $toggle = that.$wrapper.find(".c-description-section .js-description-toggle"),
            active_class = "is-shown",
            error_class = "error";

        if (!$toggle.length) {
            return false;
        }

        // DOM

        var $value = $toggle.find(".js-field-value");

        // EVENTS

        $toggle.on("click", ".js-add-description, .js-edit-description", function(event) {
            event.preventDefault();
            showToggle(true);
        });

        $toggle.on("hover", ".c-description-wrapper", function() {
            if ($toggle.find('.c-description').html() != "") {
                $toggle.find('.js-edit-description').css('visibility', 'visible');
            }
        });

        $toggle.on("mouseleave", ".c-description-wrapper", function() {
            $toggle.find('.js-edit-description').css('visibility', 'hidden');
        });

        $toggle.on("click", ".js-save-description", function(event) {
            event.preventDefault();
            prepareSave(this);
        });

        $toggle.on("click", ".js-cancel", function(event) {
            event.preventDefault();
            showToggle(false);
        });

        $value.on("focus", function() {
            if ($value.hasClass(error_class)) {
                $value.removeClass(error_class);
            }
        });

        // HANDLERS

        function showToggle(show) {
            if (show) {
                $toggle.addClass(active_class);
            } else {
                $toggle.removeClass(active_class);
            }
        }

        function prepareSave() {
            var deal_id = $toggle.find('input[name="data[deal_id]"]').val(),
                value   = $toggle.find('.js-description-value').val();

            var data = {
                id: deal_id,
                value: value,
            };

            save(data);
        }

        function save(data) {
            var href = "?module=deal&action=changeDescription";

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    if (response.data && response.data.deal_description) {
                        var value = response.data.deal_description.value;
                        renderField(value);
                    }
                } else {
                    $(that).addClass(error_class);
                }
            }, "json");
        }

        function renderField(value) {
            var $output = $toggle.find('.js-output-value');
            if (value) {
                $output.find('.c-description').html(value).show();
                $output.find('.js-add-description').hide();
                showToggle(false);
            } else {
                $output.find('.js-add-description').show();
                $output.find('.c-description').html('').hide();
                showToggle(false);
            }
        }
    };

    CRMDeal.prototype.initChangeUser = function() {
        var that = this,
            is_locked = false;

        that.$aside.on("click", ".js-show-combobox", function(event) {
            event.preventDefault();
            var $contact = $(this).closest(".c-contact");
            showToggle($contact, true);
        });

        that.$aside.on("click", ".js-hide-combobox", function(event) {
            event.preventDefault();
            var $contact = $(this).closest(".c-contact");
            showToggle($contact);
        });

        var $inputs = that.$aside.find(".js-owner-autocomplete");
        $inputs.each( function() {
            initContactAutocomplete( $(this) );
        });

        //

        initEmptyUserContact();

        //

        function initContactAutocomplete($field) {
            if (!$field.length) { return false; }

            var $contact = $field.closest(".c-contact");

            $field.autocomplete({
                appendTo: $contact,
                source: "?module=autocomplete&type=user&funnel_id=" + that.funnel_id,
                minLength: 0,
                html: true,
                focus: function() {
                    return false;
                },
                select: function( event, ui ) {
                    changeOwner(ui.item.id, $contact);
                    $field.val("");
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

            $field.on("focus", function(){
                $field.data("uiAutocomplete").search( $field.val() );
            });
        }

        function changeOwner(user_id, $contact) {
            if (is_locked) {
                showToggle($contact);
                return
            }

            var changeUser = function () {
                is_locked = true;

                var href = "?module=deal&action=changeUser",
                    data = {
                        id: that.deal_id,
                        user_contact_id: user_id
                    };

                $.post(href, data, function(response) {

                    if (response.status !== 'ok') {
                        if (response.errors) {
                            console.error(response.errors);
                        } else {
                            console.error(response);
                        }
                        $.crm.alert.show({
                            title: 'Error',
                            button: 'Error'
                        });
                        return;
                    }

                    response.data = response.data || {};

                    if (response.data.deal_access_denied) {
                        window.location.href = $.crm.app_url + 'deal/';
                        return;
                    }

                    if (response.data.html) {
                        var $newContact = $(response.data.html);

                        var $already_added_user = that.$aside.find(".c-users-section .c-contact[data-id=\"" + user_id + "\"]").not($contact);
                        if ($already_added_user.length) {
                            $already_added_user.remove();
                        }

                        //
                        $contact.replaceWith($newContact);

                        //
                        initContactAutocomplete( $newContact.find(".js-owner-autocomplete") );
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }

            changeUserConfirm(user_id, changeUser);
        }

        function changeUserConfirm(user_id, onConfirmed) {
            var href = "?module=deal&action=changeUserConfirm",
                data = {
                    id: that.deal_id,
                    user_contact_id: user_id
                };

            $.post(href, data, "json")
                .done(function(response) {

                    if (response.data && response.data.need_confirm) {
                        new CRMDialog({
                            html: response.data.html || '',
                            onOpen: function ($dialog_wrapper, dialog) {
                                $dialog_wrapper.find('.js-confirm-button').on('click', function () {
                                    onConfirmed();
                                });
                            }
                        });
                        return;
                    }

                    onConfirmed();
                });
        }

        function showToggle($contact, show) {
            var active_class = "is-edit";

            if (show) {
                $contact.addClass(active_class);
            } else {
                $contact.removeClass(active_class);
            }
        }

        function initEmptyUserContact() {
            var is_locked = false,
                $wrapper = that.$aside.find(".js-empty-user-wrapper");

            $wrapper.on("click", ".js-set-user-me", function(event) {
                event.preventDefault();
                setOwner(that.user_id);
            });

            $wrapper.on("click", ".js-set-extended", function(event) {
                event.preventDefault();
                toggleView(true);
            });

            $wrapper.on("click", ".js-unset-extended", function(event) {
                event.preventDefault();
                toggleView(false);
            });

            //

            initAutocomplete();

            //

            function setOwner(id) {
                if (is_locked) {
                    return;
                }

                var changeUser = function () {
                    is_locked = true;
                    var href = "?module=deal&action=changeUser",
                        data = {
                            id: that.deal_id,
                            user_contact_id: id
                        };

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            if (response.data.deal_access_denied) {
                                window.location.href = $.crm.app_url + 'deal/';
                                return;
                            }
                            renderContact( $(response.data.html) );
                        }
                    }, "json").always( function() {
                        is_locked = false;
                    });
                };

                changeUserConfirm(id, changeUser);

                function renderContact($contact) {
                    // delete me if already in list
                    var $me = that.$aside.find(".c-users-section .c-contact[data-id=\"" + that.user_id + "\"]");
                    if ($me.length) {
                        $me.remove();
                    }

                    // delete already existing in participant list
                    that.$aside.find('.c-contact[data-id="' + $contact.data('id') + '"]').remove();

                    // render new
                    $wrapper.after($contact).css("display","none");
                    //
                    that.$aside.find(".js-add-user-wrapper").show();
                    //
                    initContactAutocomplete( $contact.find(".js-owner-autocomplete") );
                }
            }

            function toggleView(show) {
                var active_class = "is-extended";
                if (show) {
                    $wrapper.addClass(active_class);
                } else {
                    $wrapper.removeClass(active_class);
                }
            }

            function initAutocomplete() {
                $wrapper.find(".js-empty-user-autocomplete").each( function() {
                    var $field = $(this);

                    $field.autocomplete({
                        appendTo: $wrapper,
                        source: "?module=autocomplete&type=user&funnel_id=" + that.funnel_id,
                        minLength: 0,
                        html: true,
                        focus: function() {
                            return false;
                        },
                        select: function( event, ui ) {
                            setOwner(ui.item.id);
                            $field.val("");
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

                    $field.on("focus", function(){
                        $field.data("uiAutocomplete").search( $field.val() );
                    });
                });
            }
        }
    };

    CRMDeal.prototype.initChangeClient = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-contacts-section"),
            is_locked = false;

        $wrapper.on("click", ".js-edit-company-owner", function(event) {
            event.preventDefault();

            var $contact = $(this).closest(".c-contact"),
                client_type = $contact.data("type"),
                contact_id = $contact.data("id");

            showDialog($contact, contact_id, client_type);
        });

        var $emptyClient = $wrapper.find(".js-empty-contact-wrapper");
        if ($emptyClient.length) {
            $emptyClient.on("click", ".js-set-company-owner", function(event) {
                event.preventDefault();
                showDialog($emptyClient, "", "contact_owner");
            });
        }

        //

        function showDialog($wrapper, contact_id, client_type) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=deal&action=changeClient",
                    data = {
                        deal_id: that.deal_id,
                        client_type: client_type,
                        contact_id: contact_id
                    };

                $.post(href, data, function(html) {
                    const $dialog = new CRMDialog({
                        html: html,
                        options: {
                            onChange: function(data) {
                                const view_id = $dialog.$block.find('.js-view-toggle > .is-active').data('id');

                                if (view_id === 1) {
                                    $wrapper.replaceWith(data);
                                }else{
                                    $wrapper.replaceWith(data.html);
                                    // deal.contact was changed - so update property
                                    const deal_contact_id = parseInt(that.deal.contact_id) || 0;
                                    if (deal_contact_id === contact_id && data.contact.id !== contact_id) {
                                        that.deal.contact_id = data.contact.id;
                                    }
                                }

                                $wrapper.find(".js-add-company-contact").show();

                                $(document).trigger("refresh");
                            }
                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMDeal.prototype.initDealDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-deal-delete", function(event) {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales["delete_deal_title"],
                text: that.locales["delete_deal_text"],
                button: that.locales["delete_deal_button"],
                onConfirm: dealDelete
            });
        });

        function dealDelete() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=deal&action=delete",
                    data = {
                        id: that.deal_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var content_uri = $.crm.app_url + "deal/";
                        $.crm.content.load(content_uri);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMDeal.prototype.initCall = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-call-dialog", showDialog);

        function showDialog(event) {
            event.preventDefault();

            var $link = $(this),
                contact_id = $link.data("id"),
                phone = $link.data("phone");

            if (!is_locked && contact_id && phone) {
                is_locked = true;

                var href = "?module=call&action=initContactDialog",
                    data = {
                        deal_id: that.deal_id,
                        contact_id: contact_id,
                        phone: phone
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        remain_after_load: true,
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMDeal.prototype.initOrderSection = function() {
        var that = this;

        var $section = that.$wrapper.find(".c-order-section"),
            $details_section = $section.find(".c-order-details"),
            $products_section = $section.find(".c-products-wrapper");

        var active_class = "is-extended";

        $details_section.on("click", ".js-details-toggle", function(event) {
            event.preventDefault();

            var $toggle = $(this),
                $icon = $toggle.find(".icon16");

            var is_active = $details_section.hasClass(active_class);

            var up_class = "darr",
                down_class = "rarr";

            if (is_active) {
                $icon.removeClass(up_class).addClass(down_class);
                $details_section.removeClass(active_class);

            } else {
                $icon.removeClass(down_class).addClass(up_class);
                $details_section.addClass(active_class);
            }

            $(document).trigger("refresh");
        });

        $products_section.on("click", ".js-products-toggle", function(event) {
            event.preventDefault();

            var $toggle = $(this),
                $icon = $toggle.find(".icon16");

            var is_active = $products_section.hasClass(active_class);

            var up_class = "darr",
                down_class = "rarr";

            if (is_active) {
                $icon.removeClass(up_class).addClass(down_class);
                $products_section.removeClass(active_class);

            } else {
                $icon.removeClass(down_class).addClass(up_class);
                $products_section.addClass(active_class);
            }

            $(document).trigger("refresh");
        });
    };

    CRMDeal.prototype.initMessages = function() {
        this.initSendEmail();
        this.initSendSMS();
    };

    CRMDeal.prototype.initSendEmail = function() {
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

                var href = "?module=message&action=writeDealDialog",
                    data = {
                        deal_id: that.deal_id,
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

    CRMDeal.prototype.initSendSMS = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-send-sms-dialog", showDialog);

        function showDialog(event) {
            event.preventDefault();

            var $link = $(this),
                contact_id = $link.data("id"),
                phone = $link.data("phone");

            if (!is_locked && contact_id) {
                is_locked = true;

                var href = "?module=message&action=writeSMSDealDialog",
                    data = {
                        deal_id: that.deal_id,
                        contact_id: contact_id,
                        phone: phone
                    };

                $.get(href, data, function(html) {
                    new CRMDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMDeal.prototype.addExternalContact = function($link) {
        var that = this,
            href = $.crm.app_url + '?module=deal&action=addParticipant';

        $.get(href, { id: that.deal_id }, function (html) {
            new CRMDialog({
                html: html,
                options: {
                    onAdd: function(html) {
                        $link.closest(".c-add-contact").before(html);

                        $(document).trigger("refresh");
                    }
                }
            });
        });
    };

    CRMDeal.prototype.removeOwner = function($contact) {
        var that = this,
            owner_id = $contact.data("id"),
            name = $.trim( $contact.find(".c-name").html() );

        $.crm.confirm.show({
            title: that.locales["remove_owner_title"],
            text: that.locales["remove_owner_text"].replace(/%s/, name),
            button: that.locales["remove_owner_button"],
            onConfirm: function() {
                removeContact(owner_id, that.deal_id);
            }
        });

        function removeContact(owner_id, deal_id) {
            var href = "?module=deal&action=ownerDelete",
                data = {
                    owner_id: owner_id,
                    deal_id: deal_id
                },
                new_owner_area = $(".js-empty-user-wrapper");

            that.remove_is_locked = true;
            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    $contact.remove();
                    new_owner_area.fadeIn();
                }
            }, "json").always( function() {
                that.remove_is_locked = false;
            });
        }
    };

    CRMDeal.prototype.removeContact = function($contact) {
        var that = this,
            contact_id = $contact.data("id"),
            name = $.trim( $contact.find(".c-name").html() );

        $.crm.confirm.show({
            title: that.locales["remove_contact_title"],
            text: that.locales["remove_contact_text"].replace(/%s/, name),
            button: that.locales["remove_contact_button"],
            onConfirm: function() {
                removeContact(contact_id, that.deal_id, $contact.data('role-id'));
            }
        });

        function removeContact(contact_id, deal_id, role_id) {
            var href = "?module=deal&action=contactDelete",
                data = {
                    contact_id: contact_id,
                    deal_id: deal_id,
                    role_id: role_id,
                };

            that.remove_is_locked = true;
            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    $contact.remove();
                }
            }, "json").always( function() {
                that.remove_is_locked = false;
            });
        }
    };

    CRMDeal.prototype.initAssignTags = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.js-assign-tags'),
            url = $.crm.app_url + '?module=dealOperation&action=assignTags&is_assign=1',
            deal_id = parseInt(that.deal_id) || 0;

        $link.click(function (e) {
            e.preventDefault();
            $.get(url, { deal_ids: [deal_id] }, function (html) {
                new CRMDialog({
                    html: html
                });
            });
        })
    };

    CRMDeal.prototype.initOrderEditShippingDetailLink = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $links = $wrapper.find('.js-order-edit-shipping-details-link');

        $links.click(function () {
            var $link = $(this),
                $loading = $link.parent().find('.js-order-edit-shipping-details-loading')
            $loading.show();
            that.loadEditOrderShippingDetailsDialog(that.order_id, {
                onLoad: function () {
                    $loading.hide();
                }
            });
        });
    };

    CRMDeal.prototype.initCreateOrderLink = function () {
        var that = this;

        that.$wrapper.on("click", ".js-create-order-link", function(event) {
            event.preventDefault();

            if (that.shop_in_welcome_stage) {
                $.crm.alert.show({
                    title: that.locales.shop_alert_title || '',
                    text: that.locales.shop_in_welcome_stage || ''
                });
                return;
            }

            var $link = $(this),
                active_class = "is-loading";

            $link.addClass(active_class);
            that.loadEditOrderDialog(0, {
                onLoad: function () {
                    $link.removeClass(active_class);
                }
            });
        });
    };

    CRMDeal.prototype.initEditOrderLink = function () {
        var that = this;

        that.$wrapper.on("click", ".js-edit-order-link", function(event) {
            event.preventDefault();

            if (that.shop_in_welcome_stage) {
                $.crm.alert.show({
                    title: that.locales.shop_alert_title || '',
                    text:  that.locales.shop_in_welcome_stage || '',
                });
                return;
            }

            var $link = $(this),
                active_class = "is-loading";

            $link.addClass(active_class);
            that.loadEditOrderDialog(that.order_id, {
                onLoad: function () {
                    $link.removeClass(active_class);
                }
            });
        });
    };

    /**
     * @param {Integer} order_id If order_id > 0 it is edit mode, otherwise add (create) mode
     * @param {Object} options
     * @param {Function} [options.onLoad]
     */
    CRMDeal.prototype.loadEditOrderDialog = function (order_id, options) {
        var that = this,
            deal = that.deal || {},
            $wrapper = that.$wrapper,
            $dialog = $(that.templates["create_order_dialog_html"]),
            onLoad = options.onLoad || function () {};

        $dialog.hide();

        new CRMDialog({
            html: $dialog,
            onOpen: function () {

                var dialog = this,
                    url = $.crm.backend_url + 'shop/?module=order&action=embededit',
                    $content = $dialog.find('.crm-dialog-content');

                // prepare rest part of url
                if (deal.contact_id > 0) {
                    url += '&customer_id=' + deal.contact_id;
                }
                if (order_id > 0) {
                    url += '&id=' + order_id;
                }

                $content.html('<iframe src="' + url +'" class="c-content-iframe">');

                var $iframe = $content.find('iframe');

                $iframe.one('load', function () {

                    onLoad();

                    dialog.show();
                    dialog.resize();

                    var iframe = $iframe.get(0);

                    var $form = $(iframe.contentWindow.document).find('#order-edit-form');
                    $form.append('<input type="hidden" name="crm_deal[id]" value="' + deal.id + '">');

                    iframe.contentWindow.$.order_edit.container.on('order_edit_save_success', function () {
                        dialog.close();
                        $.crm.content.reload();
                    });

                });
            }
        });

    };

    /**
     * @param {Integer} order_id Must be order_id > 0
     * @param {Object} options
     * @param {Function} [options.onLoad]
     */
    CRMDeal.prototype.loadEditOrderShippingDetailsDialog = function (order_id, options) {
        if (order_id <= 0) {
            console.error('Invalid input parameter');
            return;
        }

        var that = this,
            $wrapper = that.$wrapper,
            $dialog = $wrapper.find('.js-deal-edit-order-shipping-details-dialog').clone(),
            onLoad = options.onLoad || function () {};

        $dialog.hide();

        new CRMDialog({
            html: $dialog,
            onOpen: function () {

                var dialog = this,
                    url = $.crm.backend_url + 'shop/?module=workflow&action=prepare',
                    $content = $dialog.find('.crm-dialog-content')

                $.post(url, { action_id: 'editshippingdetails', id: order_id }, function (html) {
                    $content.html(html);
                    $content.find('.js-form-footer-actions').hide();
                    dialog.show();
                    dialog.resize();

                    var innerContentHeight = $content.find('#wf-ship-form').height();
                    $dialog.find('.crm-dialog-block').height(innerContentHeight + 150);

                    var $loading = $dialog.find('.js-submit-dialog-loading');
                    var $content_submit_button = $content.find('.js-form-footer-actions .js-submit-button');

                    $dialog.find(".js-submit-dialog-button").on("click", function(event) {
                        event.preventDefault();
                        if ($content_submit_button.is(":disabled")) {
                            return false;
                        }

                        // on success form sent
                        $content.bind('formSend', function () {
                            $loading.hide();
                            dialog.close();
                            $.crm.content.reload();
                        });

                        $loading.show();
                        $content_submit_button.trigger('click');
                    });

                    onLoad && onLoad();


                });

            }
        });
    };

    return CRMDeal;

    function log(string) {
        if (console && console.log) {
            console.log(string);
        }
    }

})(jQuery);

var CRMDealChangeWorkflowDialog = ( function($) {

    CRMDealChangeWorkflowDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form").first();

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMDealChangeWorkflowDialog.prototype.initClass = function() {
        var that = this;

        // hide footer at inner form
        that.$wrapper.find(".js-form-footer-actions").hide();

        // trigger submit inner form. submit js inside form template
        that.$wrapper.on("click", ".js-submit-dialog-button", function() {
            that.$form.find(".js-submit-button").trigger("click");
        });

        // close on success submit
        that.$form.on("formSend", function() {
            if (that.dialog.options && that.dialog.options.onShopSubmit) {
                that.dialog.options.onShopSubmit();
            }
            that.dialog.close();
        });
    };

    return CRMDealChangeWorkflowDialog;

})(jQuery);
