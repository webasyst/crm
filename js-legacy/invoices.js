var CRMInvoices = ( function($) {

    CRMInvoices = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$content = that.$wrapper.find("#js-inner-content");
        that.$sidebar = that.$wrapper.find("#js-aside-block");
        that.$invoiceList = that.$sidebar.find(".js-invoices-list");

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMInvoices.prototype.initClass = function() {
        var that = this;
        //
        that.initElastic();
        //
        that.initContentRouter();
        //
        that.initLazy();
        //
        that.initInvoiceList();
        //
        that.initInvoiceSearch();
    };

    CRMInvoices.prototype.initElastic = function() {
        var that = this;

        var $wrapper = that.$wrapper,
            $aside = that.$sidebar,
            $content = that.$content;

        var asideElastic = new CRMElasticBlock({
            $wrapper: $wrapper,
            $aside: $aside,
            $content: $content
        });

        var contentElastic = new CRMElasticBlock({
            $wrapper: $wrapper,
            $content: $aside,
            $aside: $content
        });

        initElastic();

        // fix position after change content
        $(window).trigger("scroll");

        function initElastic() {

            // DOM
            var $window = $(window),
                $wrapper = $aside,
                $header = $aside.find(".js-aside-header");

            // VARS
            var wrapper_offset = $wrapper.offset(),
                header_h = $header.outerHeight(),
                fixed_class = "is-fixed";

            // DYNAMIC VARS
            var $space = false,
                is_fixed = false;

            // INIT

            $window.on("scroll", scrollWatcher);

            function scrollWatcher() {
                var is_exist = $.contains(document, $header[0]);
                if (is_exist) {
                    onScroll( $window.scrollTop() );
                } else {
                    $window.off("scroll", scrollWatcher);
                }
            }

            function onScroll(scroll_top) {
                var set_fixed = ( scroll_top > wrapper_offset.top );
                if (set_fixed) {

                    if (!$space) {
                        $space = $("<div class=\"c-space-wrapper\" />").height(header_h);
                        $header.after($space);
                    }

                    $header
                        .css({
                            left: wrapper_offset.left
                        })
                        .width( $wrapper.outerWidth() )
                        .addClass(fixed_class);

                    is_fixed = true;

                } else {

                    if ($space && $space.length) {
                        $space.remove();
                    }

                    $header
                        .removeAttr("style")
                        .removeClass(fixed_class);

                    is_fixed = false;
                }
            }
        }
    };

    CRMInvoices.prototype.initContentRouter = function() {
        var that = this,
            $content = that.$content.find(".js-inner-content"),
            need_confirm = false,
            is_enabled = true,
            api_enabled = !!(history && history.pushState),
            xhr = false;

        that.$wrapper.on("click", "a", function(event) {
            var $link = $(this),
                content_uri = ( $link.hasClass("js-disable-router") ? $link.attr("href") : false );

            if (event.ctrlKey || event.shiftKey || event.metaKey) {

            } else if (content_uri) {
                event.preventDefault();

                if (is_enabled) {

                    if (need_confirm) {
                        $.crm.confirm.show({
                            title: $.crm.locales["unsaved_dialog_title"],
                            text: $.crm.locales["unsaved_dialog_text"],
                            button: $.crm.locales["unsaved_dialog_button"],
                            onConfirm: function() {
                                $(document).trigger("unsavedChanges", false);
                                load(content_uri);
                            }
                        })

                    } else {
                        load(content_uri);
                    }
                }
            }
        });

        $(document).on("unsavedChanges", function(event, _need_confirm) {
            need_confirm = !!_need_confirm;
        });

        function load(content_uri) {
            var data = {
                "content_only": 1
            };

            if (xhr) {
                xhr.abort();
            }

            $(document).trigger("wa_before_load");

            xhr = $.get(content_uri, data, function(html) {

                if (api_enabled) {
                    history.pushState({
                        reload: true,               // force reload history state
                        content_uri: content_uri    // url, string
                    }, "", content_uri);
                }

                $(document).trigger("wa_before_render");

                $content.html(html);

                $(document).trigger("wa_loaded");

            }).fail( function(html) {
                if (html.responseText) {
                    showError(html.responseText);
                }
            }).always( function() {
                that.xhr = false;
            });

            function showError(text) {
                var href = "?module=dialogConfirm",
                    data = {
                        title: "Error",
                        text: text,
                        ok_button: "Close"
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html
                    });
                });
            }
        }
    };

    CRMInvoices.prototype.initLazy = function() {
        var that = this;

        var $window = $(window),
            $list = that.$invoiceList,
            $loader = that.$wrapper.find(".js-lazyload"),
            is_locked = false;

        if ($loader.length) {
            $window.on("scroll", use);

            if ($loader.offset().top < $window.height()) {
                $window.trigger("scroll");
            }
        }

        function use() {
            var is_exist = $.contains(document, $loader[0]);
            if (is_exist) {
                onScroll();
            } else {
                $window.off("scroll", use);
            }
        }

        function onScroll() {
            var scroll_top = $(window).scrollTop(),
                display_h = $window.height(),
                loader_top = $loader.offset().top;

            if (scroll_top + display_h >= loader_top ) {
                if (!is_locked) {
                    load();
                }
            }
        }

        function load() {
            var href = "?module=invoice&action=sidebar",
                data = {
                    offset: $loader.data("offset")
                };

            var invoice_id = getInvoiceId();
            if (invoice_id) {
                data.invoice_id = invoice_id;
            }

            is_locked = true;

            $.post(href, data, function(html) {
                var $html = $(html),
                    $newLoading = $html.find(".js-lazyload"),
                    $items = $html.find(".c-invoice");

                if ($newLoading.length) {
                    $newLoading.insertAfter($loader);
                    $loader.remove();
                    $loader = $newLoading;
                } else {
                    $loader.remove();
                }

                $list.append($items);
            }).always( function() {
                is_locked = false;
            });
        }
    };

    CRMInvoices.prototype.initInvoiceList = function() {
        var that = this,
            selected_class = "selected",
            $activeItem = that.$invoiceList.find(".c-invoice." + selected_class);

        // On first init invoice view page
        $(document).on("viewInvoice", function(event, invoice_id) {
            if ($activeItem.length) {
                $activeItem.removeClass(selected_class);
            }

            if (invoice_id) {
                searchInvoice(invoice_id);
            }
        });

        function searchInvoice(invoice_id) {
            that.$invoiceList.find(".c-invoice").each( function() {
                var $invoice = $(this),
                    _invoice_id = $invoice.data("id");

                if (invoice_id == _invoice_id) {
                    $invoice.addClass(selected_class);
                    $activeItem = $invoice;
                }
            });
        }
    };

    CRMInvoices.prototype.initInvoiceSearch = function() {
        var that = this;

        var $autocomplete = that.$wrapper.find(".js-search-field");

        $autocomplete
            .autocomplete({
                appendTo: that.$sidebar,
                classes: {
                    "ui-autocomplete": "c-search-results-list"
                },
                source: that.app_url + "?module=invoiceAutocomplete",
                minLength: 1,
                html: true,
                focus: function() {
                    return false;
                },
                select: function( event, ui ) {
                    var content_uri = $.crm.app_url + "invoice/" + ui.item.id + "/";
                    $.crm.content.load(content_uri);
                    $autocomplete.val("");
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };
    };

    return CRMInvoices;

    function getInvoiceId() {
        var result = false;

        var $invoice = $("#c-invoice-page");
        if ($invoice.length) {
            var invoice_id = $invoice.data("id");
            if (invoice_id) {
                result = invoice_id;
            }
        }

        return result;
    }

})(jQuery);