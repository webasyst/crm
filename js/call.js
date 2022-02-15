var CRMCallPage = ( function($) {

    CRMCallPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.call_ts = options["call_ts"];
        that.locales = options["locales"];
        that.numbers_assigned = options["numbers_assigned"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCallPage.prototype.initClass = function() {
        var that = this;

        if ($.wa_push) {
            $.wa_push.init();
        }
        //
        $.crm.renderSVG(that.$wrapper);
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
    };

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

            $icon.prop('disabled', true).find('.icon16').removeClass('rotate-right').addClass('loading');

            $.get(href, function(html) {
                // Init the dialog
                var crm_dialog = new CRMDialog({
                    html: html,
                    onOpen: function () {
                        $icon.prop('disabled', false).find('.icon16').removeClass('loading').addClass('rotate-right');
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

        function showConfirm($link) {
            var $call = $link.closest(".c-call-wrapper"),
                id = $call.data("id"),
                title = $link.data("title");

            $.crm.confirm.show({
                title: title,
                text: that.locales["delete_confirm_text"],
                button: that.locales["delete_confirm_button"],
                onConfirm: onConfirm
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

        that.$wrapper.find(".js-associate-deal").on("click", function() {
            var href = $(this).data('dialog-url');

            $.get(href, function(html) {
                new CRMDialog({
                    html: html
                });
            });
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

    return CRMCallPage;

})(jQuery);
