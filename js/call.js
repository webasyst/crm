var CRMCallPage = ( function($) {

    CRMCallPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.call_ts = options["call_ts"];
        that.locales = options["locales"];
        that.iframe = options["iframe"];
        that.is_lazy_load = options["is_lazy_load"];
        that.current_page = options["current_page"];
        that.numbers_assigned = options["numbers_assigned"];
        that.started_contact = options["contact"];
        that.started_deal = options["deal"];
        that.href_prop = '';
        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCallPage.prototype.initClass = function() {
        var that = this;
        var id = null;
        var id2 = null;
        if ($.wa_push) {
            $.wa_push.init();
        }
        //
        //$.crm.renderSVG(that.$wrapper);
        //
        that.initRedirectCall();
        //
        that.initDeleteCall();

        // Show dialog to subscribe for push if user has not subbed yet
        // and has PBX numbers assigned in settings.
        if (that.numbers_assigned && $.wa_push) {
            $.wa_push.init();
        }
        //
        that.initAssociateDeal();
        //
        that.initBackgroundReload();
        //
        that.initFinishCallLinks();

        that.initLazyLoading();

        that.initCustomAudioPlayer();

        that.initDotsDetail();

        if (!that.iframe && !that.is_lazy_load) {
            that.initContactUpdateDialog();
            that.initContactCreateDialog();
            that.initFilterDrawer();
        }


        //that.initDownloadLink();

       /* $(document).on('wa_loaded', function () {
            that.initDownloadLink();
         });*/
    };

    CRMCallPage.prototype.initContactCreateDialog = function() {
        var that = this,
            is_locked = false;

            that.escapeHtml = function(unsafe)
            {
                return unsafe
                     .replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#039;");
             }

        that.$wrapper.on("click", ".js-show-create-dialog", function(event) {
            event.preventDefault();
            const call_number = that.escapeHtml('' + $(event.target).data('number'));
            showDialog(call_number);
        });

        function showDialog(call_number) {

                var href = $.crm.app_url + "frame/contact-new/?phone=" + call_number;

                const dialog_html = `<div class="dialog" id="call-create-contact-dialog">
                <div class="dialog-background"></div>
                <div class="dialog-body" style="width: 761px">

                    <div class="dialog-content" style="padding: 0;">
                        <iframe src="${href}" frameborder="0" style="height: 94vh; width: 100%;">
                        </iframe>
                    </div>
                </div>
                </div>`;

                $.waDialog({
                    html: dialog_html,
                    onOpen: function($dialog, dialog_instance) {
                        $dialog.find('.dialog-body').css('top', '2.5%');
                        $dialog.find('.dialog-content').css('min-height', '95vh');
                    }
                });
        }
    };

    CRMCallPage.prototype.initContactUpdateDialog = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-update-dialog", function(event) {
            event.preventDefault();
            const $call_wrapper = $(event.target);
            const call_id = $call_wrapper.data('id');
            showDialog(call_id, $call_wrapper);
        });

        function showDialog(call_id, $call_wrapper) {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=contact&action=updateDialog",
                    data = {
                        call_id: call_id
                    };
                $.post(href, data, function(html) {
                    $.waDialog({
                        html: html,
                        onClose: function($dialog, dialog_inst) {
                            var $contactField = $dialog.$content.find('.js-field-autocomplete');
                            var isSubmit = $contactField.data('success');
                            if (isSubmit) {
                                var contactHtml = $contactField.html();
                                $call_wrapper.closest('.c-column-phone').find('.c-user-wrapper .flexbox.middle.space-8').html(contactHtml);
                            }

                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMCallPage.prototype.initFilterDrawer = function() {

        var that = this,
            $filter_button = that.$wrapper.find(".js-filter-button"),
            $filter_reload_button = that.$wrapper.find(".js-reload-filter-button"),
            $drawer_content = that.$wrapper.find(".c-call-filter"),
            //$close_drawer = $drawer_content.find(".js-close-drawer"),
            $filter_form = $drawer_content.find(".c-call-filter-form"),

            $autocomplete_client = $drawer_content.find(".js-search-field-contact"),
            $autocomplete_client_w = $autocomplete_client.parent(),
            $autocomplete_deal = $drawer_content.find(".js-search-field-deal"),
            $autocomplete_deal_w = $autocomplete_deal.parent(),
            $contact_wrapper = $drawer_content.find(".js-call-filter-contact"),

            $deal_wrapper = $drawer_content.find(".js-call-filter-deal"),
            started_form_data = $filter_form.serializeArray(),
            started_form_data_text = $filter_form.serialize(),
            is_full_height = that.$wrapper.height() < $('.content.blank.c-shadowed-content').height(),
            default_form_data_text = 'direction=all&status=ALL&timeframe=null&deal=&contact=&start=&end=',
            default_form_data = [
                {name: 'direction', value: 'all'},
                {name: 'status', value: 'ALL'},
                {name: 'contact', value: ''},
                {name: 'deal', value: ''},
                {name: 'timeframe', value: 'null'},
                {name: 'start', value: ''},
                {name: 'end', value: ''},
                {name: 'user', value: ''}
                ],
            drawerLoaded = false;


        $filter_button.on('click', function (event) {
        event.preventDefault();
        openDrawer();

        });

        $filter_reload_button.on('click', function (event) {
            event.preventDefault();
            reloadFilter();
        });

        $drawer_content.on('click', '.js-close-drawer', function (event) {
            event.preventDefault();
            closeDrawer();
        });

        $drawer_content.on('click', '.js-apply-filter', function (event) {
            event.preventDefault();
            applyFilter();
        });

        $drawer_content.on('click', '.js-reset-filter', function (event) {
            event.preventDefault();
            resetFilter();
        });

        $drawer_content.on('click', '.js-restore-filter', function (event) {
            event.preventDefault();
            restoreFilter();
        });

        $drawer_content.on('click', '.js-call-filter-contact-cancel', function (event) {
            event.preventDefault();
            cancelContact();
        });

        $drawer_content.on('click', '.js-call-filter-deal-cancel', function (event) {
            event.preventDefault();
            cancelDeal();
        });

        function resetFilter() {
            if (default_form_data_text !== $filter_form.serialize()){
            updateFilterData(default_form_data);
            }
        }

        function restoreFilter() {

            if (started_form_data_text !== $filter_form.serialize()){
                updateFilterData(started_form_data);
            }
            closeDrawer();
        }

        function openDrawer() {
            $drawer_content.show();
            $('#wa-app').addClass('content-on-top');
            setTimeout(() => {
                $drawer_content.removeClass('is-hide');
                !is_full_height ? $('body').addClass('is-locked').css("padding-right", "17px") : null;
            }, 50);
        }

        function closeDrawer() {
            $drawer_content.addClass('is-hide');
            setTimeout(() => {
                $drawer_content.hide();
                $('body').removeClass('is-locked').css("padding-right", "");
                $('#wa-app').removeClass('content-on-top');
            }, 300);

        }

        function applyFilter(is_search = false) {

            var form_data = $filter_form.serializeArray();

            form_data.forEach((d, i) => {
                if (d.value !== '' && d.value !== 'null' ) {
                    if (i === 4 && d.value === 'custom')
                    {
                        var $datepicker_wrapper = $drawer_content.find(".js-datepicker");

                        if ($datepicker_wrapper.eq(0).val() === '') {
                        form_data[5].value = '';
                        }
                        if ($datepicker_wrapper.eq(1).val() === '') {
                            form_data[6].value = '';
                        }
                    }
                    that.href_prop = that.href_prop + (i == 0 ? '' : '&') + d.name + '=' + d.value; //(i == 0 ? '?' : '&')
                }
            })
            $.crm.content.load($.crm.app_url + 'call/?' + that.href_prop);

            var $loader = $('<span/>').addClass('icon size-20').append("<i/>").addClass('fas fa-spinner wa-animation-spin speed-1000 ');

            if (!is_search) {
                var $filter_buttom = $drawer_content.find('.c-filter-buttom');
                $filter_buttom.append($loader);
                $filter_buttom.children().each( function() {
                    var $field = $(this);
                    $field.prop("disabled",true);
                });
                $(document).one('wa_loaded', function () {
                    $loader.remove();
                    $filter_buttom.children().each( function() {
                        var $field = $(this);
                        $field.prop("disabled",false);
                    });
                    closeDrawer();
                })
            }

            else {
                $filter_button.prop("disabled",true).append($loader);
            }

        }

        function updateFilterData(data) {
            var $directions_filter = $drawer_content.find(`#dropdown-directions-filter [data-filter='${data[0]['value']}']`),
                $state_filter = $drawer_content.find(`#dropdown-states-filter [data-filter='${data[1]['value']}']`),
                $user_filter = $drawer_content.find(`#dropdown-responsible-filter [data-filter='${data[7]['value']}']`),
                $date_filter = $drawer_content.find(`#dropdown-date-filter [data-timeframe='${data[4]['value']}']`),
                switch_data = $filter_form.serializeArray(),
                $datepicker_wrapper = $drawer_content.find(".js-datepicker"),
                $deal_switch = $drawer_content.find('#switch-deal-off');

            $directions_filter.trigger('click');
            $state_filter.trigger('click');
            $user_filter.trigger('click');

            (data[2]['value'] === '') ? cancelContact() : renderContact(that.started_contact, data[2]['value'], $contact_wrapper, $autocomplete_client);
            (data[3]['value'] === '' || data[3]['value'] === '0') ? cancelDeal() : renderDeal(that.started_deal, data[3]['value']);

            if (data[3]['value'] !== switch_data[3]['value'] && (switch_data[3]['value'] === '0' || data[3]['value'] === '0')) {
                $deal_switch.trigger('click');
            }

            $datepicker_wrapper.eq(0).datepicker("setDate", new Date(data[5]['value']));
            $datepicker_wrapper.eq(1).datepicker("setDate", new Date(data[6]['value']));
            $date_filter.trigger('click');
        }

        function reloadFilter() {
            $filter_reload_button.find('svg').removeClass('fa-times').addClass('fa-spinner fa-spin');
           $.crm.content.load($.crm.app_url + 'call/?direction=all&status=ALL');
        }

        function initSearchFilter() {
            var $search_client_input = that.$wrapper.find(".js-search-field-client"),
                $search_client_input_w = $search_client_input.parent();
                $search_contact_wrapper = that.$wrapper.find(".js-call-filter-search");
                $search_contact_cancel = that.$wrapper.find(".c-call-filter-contact .js-call-filter-contact-cancel");
                $search_client_input
                .autocomplete({
                    appendTo:  $search_client_input_w,
                    //position: {my: "right top", at: "right bottom"},
                    source: $.crm.app_url + "?module=autocompleteContact&with_email=1&with_phone=1",
                    minLength: 0,
                    html: true,
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        renderContact(ui.item, ui.item.id, $search_contact_wrapper, $search_client_input_w);
                        applyFilter(true);
                        return false;
                    }
                })
                .data("ui-autocomplete")._renderItem = function ($ul, item) {
                    $ul.css("max-height", "70vh");
                    $ul.css("overflow-y", "scroll");
                    return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.label + "</div>").appendTo($ul);
                };

            $search_contact_cancel.on('click', function (event) {
                event.preventDefault();
                cancelSearch();
            });

            function cancelSearch() {
                $search_client_input_w.show();
                $search_contact_wrapper.html('');
                //$drawer_content.find('#input-contact').val('');
            }
        }

        initSearchFilter();

        $autocomplete_client
                .autocomplete({
                    appendTo:  $autocomplete_client_w,
                    //position: {my: "right top", at: "right bottom"},
                    source: $.crm.app_url + "?module=autocompleteContact&with_email=1&with_phone=1",
                    minLength: 0,
                    html: true,
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        renderContact(ui.item, ui.item.id, $contact_wrapper, $autocomplete_client);
                        return false;
                    }
                })
                .data("ui-autocomplete")._renderItem = function ($ul, item) {
                    $ul.css("max-height", "50vh");
                    $ul.css("overflow-y", "scroll");
                    $ul.css('max-width', '350px');
                    return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.label + "</div>").appendTo($ul);
                };

        $autocomplete_deal
            .autocomplete({
                appendTo:  $autocomplete_deal_w,
                //position: {my: "right top", at: "right bottom"},
                source: $.crm.app_url + "?module=autocompleteDeal",
                minLength: 0,
                html: true,
                focus: function () {
                    return false;
                },
                select: function (event, ui) {
                    renderDeal(ui.item, ui.item.id);
                    return false;
                }
                }).data("ui-autocomplete")._renderItem = function (ul, item) {
                return $("<li />").addClass("ui-menu-item-html")
                    .append(`<div><i class="${item.funnel.icon} custom-mr-4" style="color: ${item?.stage?.color};"></i>${that.escapeHtml(item.name)}</div>`).appendTo(ul);
            };

            function renderContact(contact, id, $wrapper, $input) {
                var contact_name = contact.name;
                var contact_html = `<div class="c-user-wrapper">
                    <div class="flexbox space-8">
                        <div class="c-column c-column-image">
                            <div class="flexbox middle">
                                <img src="${contact.userpic}" alt="${contact_name}">
                            </div>
                        </div>
                    <div class="c-column nowrap">
                            <div class="c-column--name">${contact_name}</div>
                            <div class="c-jobtitle hint">${$input.val() && contact.email ? contact.email : ''}</div>
                            <div class="c-jobtitle hint">${$input.val() && contact.phone ? contact.phone : ''}</div>
                    </div>
                    </div>
                </div>
                <span class="icon cursor-pointer js-call-filter-contact-cancel"><i class="fas fa-times-circle"></i></span>`;
                $drawer_content.find('#input-contact').val(id);
                $input.val() ? $input.val("") : null;
                $wrapper.html(contact_html);
                $input.hide();
            }

            function cancelContact() {
                $autocomplete_client.show();
                $contact_wrapper.html('');
                $drawer_content.find('#input-contact').val('');
            }

            function renderDeal(deal, id) {
                var deal_name = that.escapeHtml(deal.name);
                var deal_html = `
                <div class="c-deal-wrapper nowrap">
                    <div class="flexbox space-8 ">
                        <i class="${deal.funnel.icon}" style="color: ${deal.stage ? deal.stage.color : ''}"></i>
                    </div>
                    <div>
                        <div class="c-deal-wrapper--name">${deal_name}</div>
                        <div>
                            <span class="hint">${deal.create_datetime ? (deal.create_datetime).split(' ')[0] : ''}</span>
                            ${deal.closed_datetime ? '<span> - </span>' : ''}
                            <span class="hint">${deal.closed_datetime ? (deal.closed_datetime).split(' ')[0] : ''}</span>
                        </div>
                        <div class="hint">${deal.amount ? Math.ceil(+deal.amount) : ''} ${deal.currency_id}</div>
                    </div>
                </div>
                <span class="icon js-call-filter-deal-cancel"><i class="fas fa-times-circle"></i></span>`;
                $drawer_content.find('#input-deal').val(id);
                $autocomplete_deal.val("");
                $deal_wrapper.html(deal_html);
                $autocomplete_deal.hide();
            }

            function cancelDeal() {
                $autocomplete_deal.show();
                $deal_wrapper.html('');
                $drawer_content.find('#input-deal').val('');
            }

            function initDatepickers() {
                $drawer_content.find(".js-datepicker").each( function() {
                    var $field = $(this),
                        $altField = $drawer_content.find("." + $field.data("selector"));

                    $field.datepicker({
                        altField: $altField,
                        altFormat: "yy-mm-dd",
                        changeMonth: true,
                        changeYear: true
                    });

                    //$input.datepicker("setDate", "+1d");
                });
            }

            initDatepickers();

        //TODO update lazy load with
    }

    CRMCallPage.prototype.initDownloadLink = function() {
        var that = this;
            $records_array = that.$wrapper.find('.c-call-wrapper .c-call-record-link');
            setTimeout(function() {
                $records_array.eq(0).trigger('click' );
            }, 2000)
    }

    CRMCallPage.prototype.initCustomAudioPlayer = function() {
        var that = this;
        let slideUpTimeout = null;
        that.$wrapper.on('click', '.c-call-record-link', function (e, get_link) {
            var $target = $(this);
                play = $target.find('.play').length || null,
                loading = $target.find('.loading').length || null,
                pause = $target.find('.pause').length || null,
                $call_wrapper = $target.closest('.c-call-wrapper'),
                $call_id = $call_wrapper.data('id'),
                $column_audio = $call_wrapper.find('.c-column-audio'),
                $progressbar_new = $call_wrapper.find('#c-audio-slider'),
                $progressbar_item = $progressbar_new[0],
                $duration = $call_wrapper.find('.c-audio-duration'),
                $current_time = $call_wrapper.find('.c-audio-current-time');

            let raf = null;

            const showRangeProgress = (rangeInput) => {
                $column_audio[0].style.setProperty('--seek-before-width', rangeInput.value / rangeInput.max * 100 + '%');

            }
            const calculateTime = (secs) => {
                const minutes = Math.floor(secs / 60);
                const seconds = Math.floor(secs % 60);
                const returnedSeconds = seconds < 10 ? `0${seconds}` : `${seconds}`;
                return `${minutes}:${returnedSeconds}`;
            }

            const displayDuration = (audio) => {
                $duration.text(calculateTime(audio.duration));
            }

            const setSliderMax = (audio) => {
                $progressbar_item.max = Math.floor(audio.duration);
            }

            if (loading || pause) {

                id = setInterval(getAudio, 50);
                function getAudio() {
                    var $audio = $target.find('audio');
                    if ($audio.length && $audio[0].readyState > 0) {

                        renderPlayer($audio);
                    }
                  /*  else {
                        clearTimeout(slideUpTimeout);
                        clearInterval(id);
                        $audio[0].addEventListener('loadedmetadata', () => {
                            renderPlayer();
                        });
                    }*/
                }

                function renderPlayer($audio) {

                    clearTimeout(slideUpTimeout);
                    clearInterval(id);

                    var $audio_el = $audio[0];

                    $column_audio.slideDown(200);
                    displayDuration($audio_el);
                    setSliderMax($audio_el);

                    $progressbar_new.on('input', (e) => {
                        $current_time.text(calculateTime($progressbar_item.value));
                        showRangeProgress(e.target);
                    // if(!audio.paused) {
                            cancelAnimationFrame(raf);
                    // }
                    });

                    $progressbar_new.on('input', () => {
                        $audio_el.currentTime = $progressbar_item.value;
                        requestAnimationFrame(whilePlaying);
                    });

                    const whilePlaying = () => {
                        $progressbar_item.value = Math.floor($audio_el.currentTime);

                        $current_time.text(calculateTime($progressbar_item.value));
                        $column_audio[0].style.setProperty('--seek-before-width', `${$progressbar_item.value / $progressbar_item.max * 100}%`);
                        raf = requestAnimationFrame(whilePlaying);
                        if ($audio_el.currentTime == $audio_el.duration) {
                            //clearInterval(id2);
                            cancelAnimationFrame(raf);
                            slideUpTimeout = setTimeout(() => $column_audio.slideUp(200), 3000);
                        }
                    }

                    const displayBufferedAmount = () => {
                        const bufferedAmount = Math.floor($audio_el.buffered.end($audio_el.buffered.length - 1));

                        $column_audio[0].style.setProperty('--buffered-width', `${(bufferedAmount / $progressbar_item.max) * 100}%`);
                    }

                    $audio_el.addEventListener('progress', displayBufferedAmount)
                    function run() {
                    displayBufferedAmount();
                    requestAnimationFrame(whilePlaying);


                    }
                    run();
                }
            }

            if (play) {
                slideUpTimeout = setTimeout(() => $column_audio.slideUp(200), 3000);
                $progressbar_new.off('change');
                $progressbar_new.off('input');
                cancelAnimationFrame(raf);
            }
        })
    }

    CRMCallPage.prototype.initDotsDetail = function() {
        var that = this;
        that.$wrapper.on("click", ".js-dots", function(event) {
            var $target = $(this);

                $detail = $target.siblings('.c-dots-detail'),
                $detail_wrapper = $detail.closest('.js-dots-wrapper');

                $detail.toggle();
                $detail_wrapper.toggleClass('open');

            const detailHeight = $detail.height();
            const detailWidth = $detail.width();
            getHeightToBottom($detail_wrapper[0]) < (detailHeight + 30) ? $detail.css("bottom", "30px") : $detail.css("bottom", "unset");
            getWidthToLeft($detail_wrapper[0]) > (detailWidth + 30) ? $detail.css("left", "0") : $detail.css("right", "0");
        });

        function getHeightToBottom(elem) {
            let box = elem.getBoundingClientRect();
            return window.innerHeight - box.bottom;
        }

        function getWidthToLeft(elem) {
            let box = elem.getBoundingClientRect();
            return window.innerWidth - box.left;
        }

        $(document).on("click", closeDotsMenu);

        function closeDotsMenu(event) {
            var $target = $(event.target),
                $is_dots_visible = that.$wrapper.find(".js-dots-wrapper.open"),
                is_dots = !!( $target.closest(".c-dots-detail").length );

            if ($is_dots_visible.length && !is_dots) {
                $is_dots_visible.each(function(){
                    var is_edit_target = $.contains($(this)[0], event.target);
                    if (!is_edit_target) {
                        $(this).removeClass('open');
                        $(this).find(".c-dots-detail").hide()
                    }
                })
            }
        }

    }


    CRMCallPage.prototype.initFinishCallLinks = function() {
        var that = this;
        that.$wrapper.on('click', '.js-finish-call', function (e) {
            var $link = $(this),
                url = $.crm.app_url + '?module=call&action=finish',
                $call = $link.closest(".c-call-wrapper"),
                id = $call.data("id");

            if (id <= 0 || $link.data('loading')) {
                return;
            }

            $link.data('loading', 1);
            $link.find('.loading').show();
            $link.find('.yes-bw').hide();

            $.post(url, { id: id }, function (r) {
                if (r.status === 'ok') {
                    var $html = $('<div>').html(r.data.html),
                        $new_call = $html.find('.c-call-wrapper[data-id="' + id + '"]'),
                        $state_column = $new_call.find('.c-column-state');
                    $call.find('.c-column-state').replaceWith($state_column);
                    $html.remove();
                } else {
                    $link.data('loading', 0);
                    $link.find('.loading').hide();
                    $link.find('.yes-bw').show();
                }
            }).error(function () {
                $link.data('loading', 0);
                $link.find('.loading').hide();
                $link.find('.yes-bw').show();
            });
        });
    };

    CRMCallPage.prototype.initRedirectCall = function() {
        var that = this;

        that.$wrapper.on("click", ".js-redirect-call", function (event) {
            var id = $(this).parents('.c-call-wrapper').data('id'),
                href = $.crm.app_url + '?module=call&action=redirectDialog&id='+id,
                $icon = $(this);

            $icon.prop('disabled', true).find('.svg-inline--fa').removeClass('fa-exchange-alt').addClass('fa-spinner fa-spin');

            $.get(href, function(html) {
                // Init the dialog
                var crm_dialog = $.waDialog({
                    html: html,
                    onOpen: function () {
                        $icon.prop('disabled', false).find('.svg-inline--fa').removeClass('fa-spinner fa-spin').addClass('fa-exchange-alt');
                    }
                });
            });
        });
    };

    CRMCallPage.prototype.initDeleteCall = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-call", function(event) {
            event.preventDefault();
            showConfirm( $(this) );
        });

        function showConfirm($elem) {
            var $link = $elem.find(".c-delete-call"),
                $call = $link.closest(".c-call-wrapper"),
                id = $call.data("id"),
                title = $link.data("title");
                $.waDialog.confirm({
                    title: `<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ${title}?`,
                    text: that.locales["delete_confirm_text"],
                    success_button_title: that.locales["delete_confirm_button"],
                    success_button_class: 'danger custom-mr-4',
                    cancel_button_title: `${that.locales["delete_cancel_button"]}`,
                    cancel_button_class: 'light-gray',
                    onSuccess: onConfirm
                });

            function onConfirm() {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=call&action=delete",
                        data = {
                            id: id
                        };

                    var $icon = $link.find(".delete");
                    $icon.removeClass("delete").addClass("loading");

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            $call.remove();
                        }
                    }).always( function() {
                        $icon.removeClass("loading").addClass("delete");
                        is_locked = false;
                    });
                }
            }
        }
    };

    CRMCallPage.prototype.initAssociateDeal = function () {
        var that = this;
            //is_locked = false;

            that.$wrapper.on("click", ".js-associate-deal", function(e) {
              //  if (!is_locked) {
                  //  is_locked = true;
                    var href = $(this).data('dialog-url'),
                        $button = $(this);
                        $button.prop('disabled', true)
                    $.get(href, function(html) {
                        $.waDialog({
                            html: html,
                            onOpen: function ($dialog, dial_inst) {
                                $button.prop('disabled', false);
                            }
                        });
                    });
               //  }
            });
    };

    CRMCallPage.prototype.initBackgroundReload = function() {
        var that = this,
            is_locked = false,
            timeout = 0;

        runner();

        function runner() {
            clearTimeout(timeout);
            timeout = setTimeout(request, 10000);
        }

        function request() {
            var unfinished_listening = false,
                $audios = that.$wrapper.find('audio');

            $.each($audios, function (i, audio) {
                var audio_object = $(audio)[0];

                if (!audio_object.ended) {
                    unfinished_listening = true;
                }
            });

            // Do not update the call list if there are recordings that have not yet been listened to until the end
            if (unfinished_listening) {
                runner();
                return false;
            }

            if (!is_locked) {
                is_locked = true;

                var href = "?module=call&action=ts&background_process=1",
                    data = {
                        call_ts: that.call_ts
                    };

                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        var is_exist = $.contains(document, that.$wrapper[0]);
                        if (is_exist) {
                            var is_changed = (response.data !== that.call_ts);
                            if (is_changed) {
                                $.crm.content.reload();
                            } else {
                                runner();
                            }
                        }
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMCallPage.prototype.initLazyLoading = function () {
        var that = this,
            is_locked = false;

        function startLazyLoading() {
            var $window = $(window),
                $list = that.$wrapper.find("#js-calls-table"),
                $loader = that.$wrapper.find(".js-lazy-load");

            if ($loader.length) {
                $window.on("scroll", useMain);
            }

            function useMain() {
                var is_exist = $.contains(document, $loader[0]);
                if (is_exist) {
                    onScroll($loader);
                } else {
                    $window.off("scroll", useMain);
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
                const params = (window.location.href).split('/crm/call/')[1];
                const href = $.crm.app_url + '?module=call' + (params ? '&' + params.slice(1) : '');
                data = {
                    lazy: 1,
                    page: ++that.current_page
                };
                is_locked = true;
                console.log(href);


                $.post(href, data, function (html) {
                    var $new_list = $(html).find('#js-calls-table').html();
                    $list.append($new_list);
                    $(document).trigger("wa_loaded");
                    if ($loader) $loader.remove();
                    startLazyLoading();

                }).always(function () {
                    is_locked = false;
                });

            }
        }

        startLazyLoading();

    }


    return CRMCallPage;

})(jQuery);
