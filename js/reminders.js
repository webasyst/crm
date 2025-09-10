var CRMReminders = (function ($) {


    CRMReminders = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$sidebar = options["$sidebar"];
        that.$iframe = options["iframe"];
        that.$completedSection = that.$wrapper.find(".c-completed-reminders-section");

        // VARS
        that.user_id = options["is_all_reminders"] ? 'all' : options["user_id"];
        that.setting_contact_id = options["setting_contact_id"];
        that.setting_deal_id = options["setting_deal_id"];
        that.current_page = options["current_page"];
        that.assign_to_user = options["assign_to_user"];
        that.locales = options["locales"];
        that.click_reminder_event = false;
        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminders.prototype.initClass = function () {
        var that = this;
        //
        that.initToggleSidebar();
        that.initAddReminder();
        that.initLazyLoading();
        that.initReopenReminder();

        if (that.user_id && that.$completedSection.length) {
            that.initCompletedReminders();
        }
        //
        //that.initReminderSettings();
        //
        that.initTarget();

        if (!that.$iframe) {

            $(window).on('beforeunload', function(e) {
                localStorage.setItem('scrollpos', that.$sidebar.scrollTop());
            })

            that.$sidebar.on('click', '.c-user-wrapper', function() {
                $(document).off('is_completed_loaded_false');
                $(window).off('resize');
                that.$wrapper.find('.skeleton-wrapper').show();
                $('html, body').scrollTop(0);
                that.click_reminder_event = true;
                localStorage.setItem('scrollpos', that.$sidebar.scrollTop());
            })
              //  that.initElastic();
        }


    };


    CRMReminders.prototype.initToggleSidebar = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $expandSidebarButton = $wrapper.find('.js-expand-sidebar');
        if ($expandSidebarButton.length) {
            $expandSidebarButton.on('click', function(event) {
                event.preventDefault();
                $wrapper.addClass('sidebar-opened');
            });
        }
        $(document).on("wa_before_load", handlePreload);

        function handlePreload(event, content_uri){
            if(content_uri.content_uri.indexOf( '/reminder/' ) >= 0) {
                if ($wrapper.hasClass('sidebar-opened')) $wrapper.removeClass('sidebar-opened');
                $wrapper.find('.skeleton-wrapper').show();
            }
            $(document).off("wa_before_load", handlePreload);
        };
    }

    CRMReminders.prototype.initAddReminder = function () {
        var that = this,
            $wrapper = that.$wrapper.find("#c-add-reminder-form"),
            $form = $wrapper.find("form"),
            $textarea = $wrapper.find(".js-textarea"),
            $hidden_form = $wrapper.find('.c-hidden-form');
            $plus_icon = $wrapper.find(".c-icon-column"),
            $wrapperContact = $wrapper.find(".js-contact-wrapper"),
            extended_class = "is-extended",
            wrapper_is_open = false,
            is_locked = false,
            is_first_click = true,
            has_errors = true;

            var initialFormValues = $form.serialize();



        $textarea.on("focus", function () {
            if (!that.$wrapper.find('.c-completed-reminders-section').hasClass('is-shown')) {
                $hidden_form.slideDown(300);
                $wrapper.addClass(extended_class);
                wrapper_is_open = true;
                if (is_first_click) {
                    initCombobox($wrapperContact);
                    initSearchDeal();
                    is_first_click = false;
                }

                $(document).on("click", watcher);
            }
        });

        $textarea.on("input", toggleHeight);

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );
            if (wrapper_is_open) {
                if (is_enter && !event.shiftKey) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (checkFormChange()) {
                        close();
                    }
                    else {
                        $form.trigger('submit');
                    }
                }
            }
        });

        $plus_icon.on('click', function () {
            if (wrapper_is_open) {
                if (!checkFormChange()) {
                $form.trigger("submit");
                }
                else {
                    close();
                }
            }
            else {
                $textarea.focus();
            }
        })

        $wrapper.on('click', '.js-save',function (event) {
            event.preventDefault();
                if (!checkFormChange()) {
                    $form.trigger("submit");
                }
                else {
                    close();
                }
        });

        $wrapper.on('click', '.js-cancel',function (event) {
            event.preventDefault();
            clear();
        });

        function checkFormChange() {
            return initialFormValues === $form.serialize();
        };

        $(document).on("keydown", key_watcher);
        //
        initTypeToggle($wrapper);
        //
        initDatePicker();
        //
        initTimeToggle();
        //
        initTimePicker();

        function watcher(event) {
            event.stopPropagation();
            var $target = $(event.target),
                is_exist = $.contains(document, $wrapper[0]),
                is_target = $.contains($wrapper[0], event.target),
                is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                is_date = !!( $target.closest(".ui-datepicker").length ) || !!( $target.closest(".ui-corner-all").length ),
                is_contact_visible = $wrapperContact.hasClass('is-shown'),
                is_contact = !!( $target.closest(".js-contact-wrapper").length );
                //is_plus = !!( $target.closest(".c-plus").length );
            if (is_exist) {
                if (!is_target && !is_time && !is_date) {
                    if (checkFormChange()) {
                        close();
                    }
                }
                if (is_contact_visible && !is_contact) {
                    showToggleCombobox();
                }

            } else {
                $(document).off("click", watcher);
            }
        }

        function key_watcher(event) {
            event.stopPropagation();

            if (!that.$wrapper.find('.c-completed-reminders-section').hasClass('is-shown')) {
                var $target = $(event.target),
                    key = event.keyCode,
                    is_enter = ( key === 13 ),
                    is_esc = ( key === 27 );

                if (is_enter && !wrapper_is_open) {
                    //event.preventDefault();
                    //const is_edited = $target.hasClass('c-text-edited');
                    const is_completed = $target.hasClass('c-text-field');
                    const is_edited = !!($(".c-step.is-edit.is-shown").length);
                    if (!is_edited && !is_completed) {
                        event.preventDefault();
                        $textarea.focus();
                    }
                }

                if (is_esc) {
                    event.preventDefault();
                    event.stopPropagation();
                    clear();
                    $(".c-reminder-wrapper").trigger("escKeyPress");
                }
            }
        }

        function close() {
            $hidden_form.slideUp(300);
            $textarea.blur();
            $wrapper.removeClass(extended_class);
            wrapper_is_open = false;
            $(document).off("click", watcher);
        }

        function clear() {
            if (!checkFormChange()) {
            $textarea.val('');
            $wrapper.find(".menu a[data-type-id='OTHER']").trigger('click');
            that.$date_clear.trigger('click');
            that.$time_clear.trigger('click');
            that.clearDeal();
            that.setContact(that.assign_to_user);
            if (has_errors) {
                $form.find(`[name="data[content]"`).trigger('clear_error');
                has_errors = false;
            }
            toggleHeight();
            }
            close();
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray(),
                type_other = false;

            $.each(data, function(index, item) {
                if (item.value.length) {
                    result.data.push(item);
                    if (item.value === "OTHER") type_other = true;
                }

            });
            if (type_other) {
                const content_value = result.data.filter(x => x.name === 'data[content]');
                if (!content_value.length || !/\S/.test(content_value[0].value)) {
                    result.errors.push({name: 'content', value: that.locales["empty"]})
                };
                //
            }
            return result;
        }

        function showErrors(errors) {
            var error_class = "error";
            errors = (errors ? errors : []);

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value,
                    $field = $form.find(`[name="data[${name}]"`);

                if (!$field.hasClass(error_class)) {
                    var $text = $("<span />").addClass("errormsg").text(text);
                    $wrapper.find('.flexbox.vertical.width-100').append($text);
                    $field
                        .addClass(error_class)
                        .one("focus click change clear_error", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                            has_errors = false;
                        });
                        has_errors = true;
                }
            });
        }

        $form.on("submit", submit);

        function submit(event, without_reload) {
            event.preventDefault();
            if (!is_locked) {
                is_locked = true;
                var href = "?module=reminder&action=add",
                    formData = getData();
                    data = formData.data;

                if (checkFormChange()) return;
                if (formData.errors.length) {
                    showErrors(formData.errors);
                    is_locked = false;
                    return;
                }
                $plus_icon.addClass("loading");
                var loading = '<span class="icon size-20"><i class="fas fa-spinner fa-spin"></i></span>';
                $plus_icon.html(loading);

                $form.find('.js-save').attr('disabled', true);
                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        if (!without_reload) {
                            !that.$iframe ? $.crm.sidebar.reload() : null;
                            /*if (that.user_id) {
                                var content_uri = $.crm.app_url + 'reminder/' + that.user_id + '&?page=max';
                                $.crm.content.load(content_uri, true)
                                return
                            }*/
                            $.crm.content.reload();
                        }
                    }
                    else {
                        console.error(response.errors);
                    }
                }, "json").always(function () {
                    is_locked = false;
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        }

        function initCombobox($block) {
            var $idField = $block.find(".js-contact-field");
            var $combobox = $block.find(".c-combobox");

            $block.on("click", ".js-show-combobox", function (event) {
                event.stopPropagation();
                showToggleCombobox(true);
            });

            $block.on("click", ".js-hide-combobox", function (event) {
                event.stopPropagation();
                showToggleCombobox(false);
            });

            initAutocomplete();

            function initAutocomplete() {
                var $autocomplete = $block.find(".js-autocomplete");
                $autocomplete
                    .autocomplete({
                        appendTo:  $combobox,
                        //position: {my: "right top", at: "right bottom"},
                        source: $.crm.app_url + "?module=autocomplete&type=user",
                        minLength: 0,
                        html: true,
                        focus: function () {
                            return false;
                        },
                        select: function (event, ui) {
                            that.setContact(ui.item);
                            showToggleCombobox(false);
                            $autocomplete.val("");
                            return false;
                        }
                    }).data("ui-autocomplete")._renderItem = function (ul, item) {
                    return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
                };

                $autocomplete.on("focus", function () {
                    $autocomplete.data("uiAutocomplete").search($autocomplete.val());
                });
            }

            that.setContact = function(user) {
                var $user = $block.find(".js-user");
                if (user["photo_url"]) {
                    $user.find(".icon.size-24").css("background-image", "url(" + user["photo_url"] + ")");
                }
                $user.find(".c-name").text(user.name);
                $idField.val(user.id);
            }
        }

        function showToggleCombobox(show) {
            var active_class = "is-shown";
            if (show) {
                $wrapperContact.addClass(active_class);
                $wrapperContact.find('.js-autocomplete').focus();
            } else {
                $wrapperContact.removeClass(active_class);
            }
        }

        function initSearchDeal () {
            var $deal_wrapper = $form.find(".c-deal-wrapper"),
                $deal_input = $form.find('[name="data[deal_id]"]'),
                $contact_input = $form.find('[name="data[contact_id]"]'),
                $search_wrapper = $deal_wrapper.find(".c-search-contact-wrapper"),
                $deal_item = $deal_wrapper.find(".c-deal-item"),
                $field = $deal_wrapper.find(".js-autocomplete-deal");

            $field.autocomplete({
                appendTo: $search_wrapper,
                position: { my : "right top", at: "right bottom" },
                source: $.crm.app_url + "?module=autocompleteSidebar",
                minLength: 0,
                html: true,
                focus: function () {
                    return false;
                },
                select: function (event, ui) {
                    setDeal(ui.item)
                    $field.val('');
                    $search_wrapper.find('.ui-autocomplete').hide();
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function (ul, item) {
                return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
            };

            $deal_wrapper.on('click', function(event) {
                var $target = $(event.target);

                if (!$search_wrapper.is(':visible')) {
                    var is_pen = !!( $target.closest(".js-open-search").length );
                    if (is_pen){
                        $search_wrapper.addClass('is-shown');
                        $field.focus();
                    }
                }
                else {
                   var is_delete = !!( $target.closest(".js-reset-deal").length );
                   if (is_delete){
                    that.clearDeal();
                }
                }
            })

            that.clearDeal = function() {
                $deal_item.html('');
                $search_wrapper.removeClass('is-hidden is-shown');
                $contact_input.val('');
                $deal_input.val('');
            }

            function setDeal(deal) {

                const deal_id_icon = deal["photo_url"] ? `<span class="icon size-18 rounded js-open-search" style="background-image: url(${deal["photo_url"]});"></span>`
                : '';

                const deal_id_string =
                `<a class="flexbox middle c-user" href="javascript:void(0);">
                ${deal_id_icon}
                <span class="c-user-name">&nbsp;${deal.name}</span>
                </a>
                <div class="hint custom-pl-12 js-open-search cursor-pointer">
                    <span class="icon size-14"><i class="fas fa-pen" title="[\`edit\`]"></i></span>
                </div>`;
                $search_wrapper.addClass('is-hidden').removeClass('is-shown');
                $deal_item.html(deal_id_string);
                if (deal["photo_url"]) {
                    $contact_input.val(deal.id);
                    $deal_input.val('');
                } else {
                    $deal_input.val(deal.id);
                    $contact_input.val('');
                }
            }
        };


        function initDatePicker() {
            var $datePickers = $wrapper.find(".js-datepicker");

            $datePickers.each(function () {
                var $input = $(this);
                    //$altField = $input.parent().find("input[type='hidden']");

                $input.datepicker({
                    //altField: $altField,
                    //altFormat: "yy-mm-dd",
                    //dateFormat: "d MM",
                    changeMonth: true,
                    changeYear: true,
                    showOtherMonths: true,
                    selectOtherMonths: true,
                    gotoCurrent: true,
                    //showButtonPanel: true,
                });

                var $input_wrapper = $input.parent(),
                    $icon = $input_wrapper.find(".calendar");
                    that.$date_clear = $input_wrapper.find(".js-reset-date");

                $icon.on("click", function () {
                    $input.focus();
                });

                $input.on('change', function(){
                    if(this.value.length>0){
                        this.style.width = ((this.value.length) * 7) + 'px';
                    }else{
                      this.style.width = ((this.getAttribute('placeholder').length + 1) * 8) + 'px';
                    }
                    if ($input.val() !== '') {
                        $input_wrapper.addClass('is-active');
                    }
                })

                that.$date_clear.on("click", function () {
                    $input.val("");
                    $input.trigger('change');
                    $input_wrapper.removeClass('is-active');
                });

                // $input.datepicker("setDate", "+1d");
            });
        }

        function initTimeToggle() {
            var $toggle = $wrapper.find(".js-time-toggle"),
                $field = $toggle.find(".js-timepicker");
                that.$time_clear = $toggle.find(".js-reset-time");

            $field.on('change', function() {
                show(true);
                if ($field.val() == "") show();
            })

            $toggle.on("click", ".js-show-time", function () {
                $field.focus();
            });

            that.$time_clear.on("click", function () {
                $field.val("");
                show();
            });

            function show(show) {
                var active_class = "is-active";
                if (show) {
                    $toggle.addClass(active_class);
                } else {
                    $toggle.removeClass(active_class);
                }
            }
        }

        function initTimePicker() {
            var $timePickers = $wrapper.find(".js-timepicker");
            $timePickers.each(function () {
                var $input = $(this);
                $input.timepicker();
            });
        }

        function initTypeToggle($block) {
            var $wrapper = $block.find(".js-reminder-type-toggle"),
                //$visibleLink = $wrapper.find(".js-visible-link"),
                $field = $wrapper.find(".js-type-field"),
                $menu = $wrapper.find(".menu");

            $wrapper.waDropdown();

            $menu.on("click", "a", function () {
                var $link = $(this);
                $wrapper.find(".js-text").html($link.html());

                $menu.find(".selected").removeClass("selected");
                $link.closest("li").addClass("selected");

                var id = $link.data("type-id");
                $field.val(id);//.trigger("change");
            });
        }
    };

    CRMReminders.prototype.initLazyLoading = function () {
        var that = this,
            is_locked = false;

        function startLazyLoading() {

            var $window = $(window),
                $window_scroll = $window; //that.$iframe ? $(window.top.document).find('.crmContent') : $(window),
                $list = that.$wrapper.find("#c-main-reminders-list"),
                $loader = $list.find(".js-lazy-load");

            if ($loader.length) {
                if ($window.height() > 0) {

                    if (that.current_page == 1 && $window.height() > $list.height()) {
                        useMain();
                    }
                    else {
                        $window_scroll.on("scroll", useMain);
                    }
                }
                else {
                    setTimeout(()=> startLazyLoading(), 100);
                }

            }

            function useMain() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    onScroll($loader);
                } else {
                    //2я попытка, в некоторых случаях теряется переменная лоадера
                    $loader = $list.find(".js-lazy-load");
                    is_exist = $.contains(document, $loader[0]);
                    if (is_exist) {
                        onScroll($loader);
                    } else {
                        $window_scroll.off("scroll", useMain);
                    }
                }
            }

            function onScroll($loader) {
                var scroll_top = $(window).scrollTop(),
                    display_h = $window.height(),
                    loader_top = $loader.offset().top;
                if (scroll_top + display_h >= loader_top) {
                    if (!is_locked) {
                        load($loader);
                    }
                }
            }

            function load($loader) {
                var href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '') + (that.$iframe ? '&iframe=1' : '');
                var href = $.crm.app_url + '?module=reminder&action=actual' + href_params;
                data = {
                    user_id: that.user_id,
                    page: ++that.current_page,
                    //deal: that.setting_deal_id,
                    //contact: that.setting_contact_id
                };

                is_locked = true;
                $.post(href, data, function (html) {
                    if (!that.click_reminder_event) {
                        if ($loader) $loader.remove();
                        $list.append(html);
                        startLazyLoading();
                    }

                }).always(function () {
                    is_locked = false;
                });
            }
        }

        startLazyLoading();
    }

    CRMReminders.prototype.initCompletedReminders = function () {
        var that = this,
            $section = that.$completedSection,
            //$dropdown_icon = $section.find(".sort-down"),
            $list = $section.find(".c-reminders-list"),
            $drawer_background = that.$wrapper.find('.drawer'),
            $article_body = that.$wrapper.find('.article-body'),
            $header = $section.find(".c-actions"),
            $sort_icon = $header.find('.sort-down'),
            $loader = $('<span/>').addClass('icon size-16').append("<i/>").addClass('fas fa-spinner wa-animation-spin speed-1000 text-gray'),
            is_loaded = false,
            is_open = false,
            is_locked = false;

            if (!that.$iframe) {
                adjustWidth();

            $(window).on('resize', function() {
                  adjustWidth();
                })
            }
            function adjustWidth() {
                var parentwidth = $article_body.width();
                $section.width(parentwidth);
                }

            $(document).on('is_completed_loaded_false', function(event, data) {
                if (data) {
                    //append new compl text from response
                    if (data.new_text) $section.find('.js-completed-reminders-btn-text').text(data.new_text);

                    if ($section.hasClass('hidden')) $section.removeClass('hidden');
                    //reduce sidebar counters
                    reduceCounter(data.user_id, data.state);
                } else {
                    is_loaded = false;
                }
             });

        // EVENT
        $section.on("click", ".js-load-completed-reminders", onToggleClick);

        //

        function reduceCounter(user_id, state) {
            var $sidebar_item_detail = that.$sidebar.find(`[data-id='${user_id}'] .details`);
            var $sidebar_all_detail = that.$sidebar.find(`[data-id='all'] .details`);

            var $sidebar_item_count_all = $sidebar_item_detail.find('.count.all');
            var $sidebar_all_count_all = $sidebar_all_detail.find('.count.all');
            $sidebar_item_count_all.text($sidebar_item_count_all.text() - 1);
            $sidebar_all_count_all.text($sidebar_all_count_all.text() - 1);

            if (state == 'overdue' || state == 'burn') {
                //logic for user counter decrease
                var $sidebar_item_count_overdue = $sidebar_item_detail.find('.count.overdue');
                var $sidebar_item_count_burn = $sidebar_item_detail.find('.count.burn');
                if ($sidebar_item_count_overdue.length){
                    var item_overdue_count = $sidebar_item_count_overdue.data('due-count');
                    var item_overdue_text_count = +$sidebar_item_count_overdue.text();
                    state == 'overdue' ? $sidebar_item_count_overdue.data('due-count', --item_overdue_count) : null;
                    $sidebar_item_count_overdue.text(--item_overdue_text_count);
                    if (item_overdue_count <= 0) {
                        $sidebar_item_count_overdue.removeClass('overdue').addClass('burn');
                    }
                    if (item_overdue_text_count <= 0) {
                        $sidebar_item_count_overdue.remove()
                    }
                }
                else if ($sidebar_item_count_burn.length) {
                   var item_burn_text_count = +$sidebar_item_count_burn.text();
                   $sidebar_item_count_burn.text(--item_burn_text_count);
                    if (item_burn_text_count <= 0) {
                        $sidebar_item_count_burn.remove();
                    }
                }

                //logic for all-user counter decrease
                var $sidebar_all_count_overdue = $sidebar_all_detail.find('.count.overdue');
                var $sidebar_all_count_burn = $sidebar_all_detail.find('.count.burn');
                if ($sidebar_all_count_overdue.length){
                    var all_overdue_count = $sidebar_all_count_overdue.data('due-count');
                    var all_overdue_text_count = +$sidebar_all_count_overdue.text();
                    state == 'overdue' ? $sidebar_all_count_overdue.data('due-count', --all_overdue_count) : null;
                    $sidebar_all_count_overdue.text(--all_overdue_text_count);
                    if (all_overdue_count <= 0) {
                        $sidebar_all_count_overdue.removeClass('overdue').addClass('burn');
                    }
                    if (all_overdue_text_count <= 0) {
                        $sidebar_all_count_overdue.remove()
                    }
                }
                else if ($sidebar_all_count_burn.length) {
                   var all_burn_text_count = +$sidebar_all_count_burn.text();
                   $sidebar_all_count_burn.text(--all_burn_text_count);
                    if (all_burn_text_count <= 0) {
                        $sidebar_all_count_burn.remove();
                    }
                }
            }

        }

        function onToggleClick(event) {
            event.preventDefault();

            if (is_loaded || is_open) {
                show(false, true);
            } else {
                if (!is_locked) {
                    load();
                }
            }
        }

        function load() {
            var href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '');
            var href = "?module=reminder&action=completed" + href_params,
                data = {
                    user_id: that.user_id
                };

            is_locked = true;
            $header.append($loader);
            $sort_icon.hide();

            $.post(href, data, function (html) {

                is_loaded = true;
                $list.html(html);
                initLazyLoading();
                $header.children().last().remove();
                $sort_icon.show();
                // fix position after change content
                $(window).trigger("scroll");
                show(true);

            }).always(function () {
                is_locked = false;
            });
        }

        function show(show, toggle) {
            var active_class = "is-shown";

            if (is_open) {
                setTimeout(()=>{
                    $drawer_background.hide();
                    $article_body.css('overflow','unset');
                    if (!that.$iframe) $article_body.css('max-height', 'unset');
                }, 300)

                $(document).off('click', completedClickWatcher)
            }
            else {
                $article_body.css('overflow','hidden');
                if (!that.$iframe) $article_body.css('max-height', 'calc(100vh - 4rem)');
                $drawer_background.show();

                $(document).on('click', completedClickWatcher)
            }

            if (toggle) {
                $section.toggleClass(active_class);
                is_open = !is_open;
                $list.slideToggle(300);
            } else if (show) {
                $section.addClass(active_class);
                is_open = true;
                $list.slideDown(300);
            } else {
                $section.removeClass(active_class);
                is_open = false;
                $list.slideUp(300);
            }
        }

        function completedClickWatcher(e) {
            e.preventDefault();

            is_target = $.contains($section[0], e.target);
            if (!is_target) {
                show(false);
            }
        }

        function initLazyLoading() {
            var $window = $(window),
                $loader = $list.find(".js-lazy-load"),
                is_locked = false;
            if ($loader.length) {
                $list.on("scroll", use);
            }

            function use() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    if (is_open) {
                        onScroll($loader);
                    }
                } else {
                    $list.off("scroll", use);
                }
            }

            function onScroll($loader) {
                var scroll_top = $list.scrollTop(),
                    display_h = $list.height(),
                    loader_top = $loader.offset().top;
                    list_top = $list.offset().top;

                if (scroll_top + display_h >= loader_top - list_top) {
                    if (!is_locked) {
                        load($loader);
                    }
                }
            }

            function load($loader) {
                var href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '');
                var href = "?module=reminder&action=completed" + href_params,
                    data = {
                        user_id: that.user_id,
                        min_dt: $list.find("> li[data-datetime]:last").data("datetime")
                    };

                is_locked = true;
                $.post(href, data, function (html) {
                    $loader.remove();
                    $list.append(html);
                    initLazyLoading();
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminders.prototype.initReopenReminder = function () {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-reopen-reminder", function (event) {
            event.preventDefault();

            var $marker = $(this),
                $reminder = $marker.closest(".c-reminder-wrapper");

            var loading = '<span class="icon size-20"><i class="fas fa-spinner fa-spin"></i></span>';
            $marker.addClass("is-loading");
            $marker.html(loading);

            reOpen($reminder.data("id"));
        });

        function reOpen(id) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=reminder&action=markAsUndone",
                    data = {
                        id: id
                    };

                $.post(href, data, function (response) {
                    if (response.status == "ok") {
                        if (data.id) {
                            var href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '');
                            var content_uri = $.crm.app_url + 'reminder/' + (that.user_id ? that.user_id : 'all') + '/?highlight_id=' + data.id  + (that.$iframe ? '&iframe=1' : '') + href_params;
                            $.crm.content.load(content_uri, false);
                            return
                        }
                        $.crm.content.reload();
                    }
                }).always(function () {
                    is_locked = false;
                });
            }
        }
    };

    CRMReminders.prototype.initTarget = function() {
        var that = this,
            $window = $(window);
            if (!that.$iframe) {
                var scrollpos = localStorage.getItem('scrollpos');
                if (scrollpos) that.$sidebar.scrollTop(scrollpos);
            }

                /* var $target = that.$sidebar.find(".c-user-wrapper.selected");
                if ($target.length) {
                   var target_t = $target.offset().top,
                        viewport_t = that.$sidebar.offset().top,
                        target_h = $target.height(),
                        viewportHeight = that.$sidebar.height(),
                        scrollIt = (target_t - viewport_t) - ((viewportHeight - target_h) / 2);
	                    //$window.scrollTop(scrollIt);
                        if (scrollIt > 0) {
                            that.$sidebar.scrollTop(scrollIt);
                        }
                }*/

        $(document).ready(function() {
            that.$wrapper.find('.skeleton-wrapper').hide(); //remove skeleton on doc.ready

            setTimeout( function() {
                var $target = that.$wrapper.find(".c-reminder-wrapper.highlighted");
                if ($target.length) {
                    var target_t = $target.offset().top;
                        target_h = $target.height(),
                        viewportHeight = $window.height(),
                        scrollIt = target_t - ((viewportHeight - target_h) / 2);
                        $('html, body').animate({scrollTop: scrollIt + 'px'}, 300);
                }
            }, 100);

            setTimeout( function() {
                var $is_target = that.$wrapper.find(".c-reminder-wrapper.is-target");
                if ($is_target.length) {
                    var is_target_t = $is_target.offset().top;
                    $window.scrollTop(is_target_t - 50);
                }
            }, 100);


        });
    };

    return CRMReminders;

})(jQuery);

var CRMReminder = (function ($) {

    CRMReminder = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$marker = that.$wrapper.find(".c-marker");
        that.marker_html = that.$marker.html();
        that.$steps = that.$wrapper.find(".c-step");
        that.$view = that.$steps.filter(".is-view");
        that.$edit = that.$steps.filter(".is-edit");
        that.$confirm = that.$steps.filter(".is-confirm");
        that.$textarea = that.$edit.find("textarea");
        that.$dots_button = that.$wrapper.find(".js-dots-wrapper");
        that.$dots_detail = that.$wrapper.find(".c-dots-detail");
        that.$main_wrapper = that.$wrapper.closest('.article-body');
        // VARS
        that.id = options["id"];
        that.$iframe = options["iframe"];
        that.current_page = options["current_page"];
        that.user_id = options["user_id"];
        that.reminder_user_id = options["reminder_user_id"];

        that.shown_class = "is-shown";
        that.setting_contact_id = options["setting_contact_id"];
        that.setting_deal_id = options["setting_deal_id"];
        that.state =  options["state"];

        // DYNAMIC VARS
        that.$activeContent = that.$view;
        that.xhr = false;

        // INIT
        that.initClass();
        that.setPadding();

            //
        //that.initDotsDetail();
    };

    CRMReminder.prototype.initClass = function () {
        var that = this;

        that.$wrapper.on("click", function (event) {

            event.preventDefault();

            if (!that.$main_wrapper.find('.c-completed-reminders-section').hasClass('is-shown')) {

                var $target = $(event.target);

                if (!!( $target.closest(".c-deal-wrapper a").length )) {
                    return;
                }

                if (!!( $target.closest(".js-mark-done").length )) {
                    that.setDone();
                    return;
                }

                if (!!( $target.closest(".js-confirm-delete").length )) {
                    that.remove();
                    return;
                }

                if (!!( $target.closest(".js-remove").length )) {
                    toggleConfirm(true);
                    that.$dots_detail.hide();
                    return;
                }

                if (!!( $target.closest(".js-edit").length )) {
                    that.$dots_detail.hide();
                }

                if (!!( $target.closest(".js-confirm-cancel").length )) {
                    toggleConfirm(false);
                    return;
                }

                if (!!( $target.closest(".js-dots-wrapper").length )) {
                     that.initDotsDetail();
                     return;
                 }

                /*if (!!( $target.closest(".js-cancel").length )) {
                    that.toggleContent(that.$view);
                    return;
                }*/

                if (!that.$edit.is(':visible')) {
                    that.toggleContent(that.$edit);
                    var is_textarea = !!( $target.closest(".js-float-text").length );
                    if (is_textarea) {
                            that.$edit.find(".js-textarea").focus();
                    }

                }
            }


        });

        that.$wrapper.on("reminderIsChanged", function () {
            that.toggleContent(that.$view);
            //$.crm.sidebar.reload();
            if (that.id) {

                var href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '');
                var content_uri = $.crm.app_url + 'reminder/' + (that.user_id ? that.user_id : 'all') + '/?highlight_id=' + that.id + (that.$iframe ? '&iframe=1' : '') + href_params;
                $.crm.content.load(content_uri, true).done(function(){
                    $('.c-reminders-list-loader').hide();
                });
                $(document).off('is_completed_loaded_false');
                $(window).off('resize');
                return;
            }
            $.crm.content.reload().done(function(){
                $('.c-reminders-list-loader').hide();
            });

        });

        that.$wrapper.on("reminderNotChanged", function () {
            that.toggleContent(that.$view);
        });

        that.$wrapper.on("updatePadding", function () {
            that.setPadding(true);
        });

        //   that.initQuickDateToggle();
        //
        //   that.initQuickContentEdit();

        function toggleConfirm(show) {
            var active_class = "is-shown";
            if (show) {
                that.$confirm.addClass(active_class);
            } else {
                that.$confirm.removeClass(active_class);
            }
        }
    };

    CRMReminder.prototype.setPadding = function(update) {
        var that = this,
            $wrapper = that.$wrapper;
        $quick_content = $wrapper.find(".js-quick-content-toggle-wrapper");
        if ( $quick_content.height() === 0 || $wrapper.find(".c-footer").height() === 0) {
            $quick_content.css("padding-bottom", "0");
            return;
        }
            update ? $quick_content.css("padding-bottom", "0.5rem") : null;
    }

    CRMReminder.prototype.setDone = function () {
        var that = this;
        var $reminder = that.$wrapper;
        var $marker = $(this);

        $reminder.css('display','none'); // Hide the reminder without waiting for the server's response;
        $marker.addClass("is-done");

        $(document).trigger('is_completed_loaded_false', false);

        var id = that.id,
            href_params = (that.setting_deal_id > 0 ? '&deal=' + that.setting_deal_id : '') + (that.setting_contact_id > 0 ? '&contact=' + that.setting_contact_id : '');
            href = "?module=reminder&action=markAsDone" + href_params,
            data = {
                id: id,
                user_id: that.user_id ? that.user_id : 'all'
            };

        is_locked = true;
        $.post(href, data, function (response) {
            if (response.status === "ok") {
                const new_count_text = response?.data?.completed_title;
                $reminder.remove();
                if (!that.$iframe) {
                    //reduceCounter();
                    $.crm.sidebar.reload();
                }
                //trigger is_loaded false
                $(document).trigger('is_completed_loaded_false', {user_id: that.reminder_user_id, state: that.state, new_text: new_count_text});

                $(window).trigger('scroll');

            } else {
                $reminder.css('display',''); // If a bad response is returned from the server, then we will show the reminder back.
            }
        }).always(function () {
            is_locked = false;
        });

    }

    CRMReminder.prototype.initDotsDetail = function() {
        var that = this;
            that.$dots_detail.toggle();
            const detailHeight = that.$dots_detail.height();

            getHeightToBottom(that.$dots_button[0]) < (detailHeight + 30) ? that.$dots_detail.css("bottom", "38px") : that.$dots_detail.css("bottom", '-' + ( detailHeight + 8) + 'px');

            var is_dots_visible = that.$dots_detail.is(':visible');

            if (is_dots_visible) {
                $(document).on("click", closeDots);
            }
            else {
                $(document).off("click", closeDots);
            }


        function closeDots(event) {
            var $target = $(event.target),
                is_dots = !!( $target.closest(".c-dots-detail").length ),
                is_dots_button = !!( $target.closest(".js-dots-wrapper").length );
            if (!is_dots && !is_dots_button) {
                that.$dots_detail.hide();
                }
        }

        function getHeightToBottom(elem) {
            let box = elem.getBoundingClientRect();
            return window.innerHeight - box.bottom;
        }
    }

    CRMReminder.prototype.remove = function () {
        var that = this,
            href = "?module=reminder&action=delete",
            data = {
                id: that.id
            };

        if (that.xhr) {
            that.xhr.abort();
        }

        that.xhr = $.post(href, data, function (response) {
            if (response.status === "ok") {
                that.$wrapper.remove();
                $.crm.content.reload();
                !that.$iframe ? $.crm.sidebar.reload() : null;
            }
        }).always(function () {
            that.xhr = false;
        });
    };

    CRMReminder.prototype.toggleContent = function ($content) {
        var that = this;

        if (that.$activeContent.length) {
            that.$activeContent.removeClass(that.shown_class);
        }
        $content.addClass(that.shown_class);
        that.$activeContent = $content;
        $content.focus();
        if (that.$edit === $content) {
            that.$wrapper.trigger("editOpen").addClass("editOpen");
        }
        else {
            that.$wrapper.removeClass("editOpen");
        }
    };

    return CRMReminder;

})(jQuery);

var CRMCompletedReminder = (function ($) {

    CRMCompletedReminder = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$marker = that.$wrapper.find(".c-marker");

        // VARS
        that.marker_html = that.$marker.html();
        that.reminder_id = options["reminder_id"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCompletedReminder.prototype.initClass = function () {
        var that = this;

        that.initQuickContentEdit();
    };

   CRMCompletedReminder.prototype.initQuickContentEdit = function () {
        var that = this,
            $wrapper = that.$wrapper.find(".js-top-content-toggle-wrapper");

            if ( $wrapper.height() === 0) {
                $wrapper.css("padding-bottom", "0");
            }

        if (!$wrapper.length) {
            return false;
        }

        // DOM
        var $form = $wrapper.find("form"),
            $textarea = $form.find(".js-textarea");

        // VARS
        var active_class = "is-changed";

        // DYNAMIC VARS
        var is_changed = false,
            is_locked = false;

        // EVENTS

        $textarea.on("keyup", function (event) {
            var //key = event.keyCode,
                //is_enter = ( key === 13 ),
                value = $textarea.val();

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    //$textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
               // $textarea.removeClass(active_class);
            }

            /*if (!is_enter || event.shiftKey) {
                toggleHeight();
            }*/
        });

           $textarea.on("input", toggleHeight);


        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();

                if (is_changed) {
                    save();
                }
            }
        });

        $textarea.on("blur", function () {
            if (is_changed) {
                save();
            }
        });

        setTimeout(() => {
            if ($textarea.length) toggleHeight();
         }, 300)


        // FUNCTIONS

        function save() {
            if (!is_locked) {
                is_locked = true;

                that.renderLoading(true);

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        is_changed = false;
                        $textarea
                            .removeClass(active_class)
                            .blur();
                    }
                }).always(function () {
                    is_locked = false;
                    that.renderLoading(false);
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        }
    };

    CRMCompletedReminder.prototype.renderLoading = function (is_loading) {
        var that = this,
            $marker = that.$marker,
            load_class = "is-load";

        if (is_loading) {
            $marker.addClass(load_class);
            var loading = '<i class="fas fa-spinner fa-spin"></i>';
            $marker.html(loading);
        } else {
            $marker.removeClass(load_class);
            $marker.html(that.marker_html);
        }
    };

    return CRMCompletedReminder;

})(jQuery);

var CRMReminderSettingsDialog = (function ($) {

    CRMReminderSettingsDialog = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$button = that.$wrapper.find(".js-submit-button");
        that.$options = that.$wrapper.find(".js-options-list");
        that.$groups = that.$wrapper.find(".js-group-list");
        that.$select = that.$wrapper.find(".js-select-list");

        // DYNAMIC VARS
        that.dialog = that.$wrapper.data("dialog");
        // INIT
        that.initClass();
    };

    CRMReminderSettingsDialog.prototype.initClass = function () {
        var that = this;

        that.$form.on("change", "input", function () {
            that.toggleButton(true);
        });

        that.$options.on("change", "input", function () {
            var $input = $(this),
                value = $input.val(),
                is_active = ( $input.attr("checked") === "checked" );

            if (is_active) {
                if (value === "groups") {
                    that.$groups.show();
                } else {
                    that.$groups.hide();
                }
            }

            that.dialog.resize();
        });

        that.$form.on("change", '.daily-recap', function () {
            var $inputDaily = $(this),
                status = $inputDaily["0"].checked,
                select = that.$select,
                cron_error  = $(".crm-reminders-recap-error");

            if (!status) {
                select.attr("disabled", true);
                /* Closed error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeOut();
                    that.dialog.resize();
                }
            } else {
                select.attr("disabled", false);
                /* Show error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeIn();
                    that.dialog.resize();
                }
            }
        });

        /* Pop-up settings */
        that.$form.on('change', '.js-pop-up-disabled', function () {
            var pop_up     = $(this),
                pop_up_min = that.$wrapper.find(".js-pop-up-min");

            if (pop_up.is(':checked')) {
                pop_up_min.prop( "readonly", false );
                pop_up_min.focus();
            }
            else {
                pop_up_min.prop( "readonly", true );
            }
        });

        that.initSave();
    };

    CRMReminderSettingsDialog.prototype.initSave = function () {
        var that       = this,
            $form      = that.$form,
            is_locked  = false,
            pop_up     = that.$wrapper.find(".js-pop-up-disabled");

        $(".js-submit-button").on("click", function () {
            if (pop_up.is(':checked')) {
                var pop_up_time = parseInt($(".js-pop-up-min").val());
                if (pop_up_time > 0) {
                    onSubmit();
                } else {
                    showError();
                }
            } else {
                onSubmit();
            }
        });
        function showError() {
            $(".enter-minutes").fadeIn().delay("2000").fadeOut();
        }

        function onSubmit() {
            if (!is_locked) {
                is_locked = true;

                //var $loading = $('<i class="icon16 loading" style="vertical-align: middle;margin-left: 10px;"></i>');
                  //  $loading.appendTo('.crm-actions');

                $(".js-submit-button").prop("disabled", true);

                var href = "?module=reminder&action=settingsSave";

                $.post(href, $form.serializeArray(), function (response) {
                    if (response.status === "ok") {
                        if (that.$options.find(':checked').val() == 'my'){
                            var content_uri = $.crm.app_url + "reminder/";
                            $.crm.content.load(content_uri);
                        } else {
                          /*  $('.loading').remove();
                            var $done = $('<i class="icon16 yes" style="vertical-align: middle;margin-left: 10px;"></i>');
                                $done.appendTo('.crm-actions');*/

                            setTimeout(function() {
                                that.dialog.close();
                                $.crm.content.reload();
                            }, 1000);
                        }
                    }
                }, "json").always(function () {
                    that.toggleButton(false);
                    is_locked = false;
                });
            }
        }
    };

    CRMReminderSettingsDialog.prototype.toggleButton = function (active) {
        var that = this,
            $button = that.$button;

        if (active) {
            $button.addClass("yellow");
        } else {
            $button.removeClass("yellow");
        }
    };

    return CRMReminderSettingsDialog;

})(jQuery);

var CRMReminderSettings = (function ($) {

    CRMReminderSettings = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find(".js-submit-button");
        that.$options = that.$wrapper.find(".js-options-list");
        that.$groups = that.$wrapper.find(".js-group-list");
        that.$select = that.$wrapper.find(".js-select-list");

        that.$wrapper.data('instance', that);

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminderSettings.prototype.initClass = function () {
        var that = this;


        that.$options.on("change", "input", function () {
            var $input = $(this),
                value = $input.val(),
                is_active = ( $input.attr("checked") === "checked" );

            if (is_active) {
                if (value === "groups") {
                    that.$groups.show();
                } else {
                    that.$groups.hide();
                }
            }
        });

        that.$wrapper.on("change", '.daily-recap', function () {
            var $inputDaily = $(this),
                status = $inputDaily["0"].checked,
                select = that.$select,
                cron_error  = $(".crm-reminders-recap-error");

            if (!status) {
                select.attr("disabled", true);
                /* Closed error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeOut();
                }
            } else {
                select.attr("disabled", false);
                /* Show error with cron */
                if (cron_error.length) {
                    $(cron_error).fadeIn();
                }
            }
        });

        /* Pop-up settings */
        that.$wrapper.on('change', '.js-pop-up-disabled', function () {
            var pop_up     = $(this),
                pop_up_min = that.$wrapper.find(".js-pop-up-min");

            if (pop_up.is(':checked')) {
                pop_up_min.prop( "readonly", false );
                pop_up_min.focus();
            }
            else {
                pop_up_min.prop( "readonly", true );
            }
        });
    };

    CRMReminderSettings.prototype.validateBeforeSave = function() {
        var that       = this,
            $pop_up     = that.$wrapper.find(".js-pop-up-disabled");

        if ($pop_up.is(':checked')) {
            var pop_up_time = parseInt($(".js-pop-up-min").val());
            if (pop_up_time <= 0) {
                $(".enter-minutes").fadeIn().delay("2000").fadeOut();
                return false;
            }
        }

        return true;

    };

    return CRMReminderSettings;

})(jQuery);
