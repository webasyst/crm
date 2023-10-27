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

        that.showOnlyDesktopClass = 'desktop-and-tablet-only'
        that.patternsBeginContent = [
            ".+\\d+\\/",
            ".+new\\/"
        ];

        that.initWaLoading();

        that.filtersDropdown();
        //
        that.initContentRouter();
        //
        that.initLazy();
        //
        that.initInvoiceList();
        //
        that.initInvoiceSearch();

        that.initMobileEvents();
    };


    CRMInvoices.prototype.hideContentMobile = function() {
        this.$content.addClass(this.showOnlyDesktopClass);
        this.$sidebar.removeClass(this.showOnlyDesktopClass);
    }

    CRMInvoices.prototype.showContentMobile = function() {
        this.$sidebar.addClass(this.showOnlyDesktopClass);
        this.$content.removeClass(this.showOnlyDesktopClass);
    }

    CRMInvoices.prototype.initMobileEvents = function() {
        $(document).on("click", ".js-invoice-hide-mobile", () => {
            this.hideContentMobile();
        });

        const { pathname } = window.location;

        for (const pattern of this.patternsBeginContent) {
            if (new RegExp(pattern, "g").test(pathname)) {
                this.showContentMobile();
                break;
            }
        }
    }

    CRMInvoices.prototype.filtersDropdown = function() {
        $(".dropdown", this.$sidebar).waDropdown({
            hover: false,
            items: ".menu > li > a",
            change: function(e) {
                if (e.type !== "touchend") return false;

                const $link = $(e.target).closest('a');
                if (!$link.length) return false;

                const href = $link.attr('href');
                if (href) {
                    window.location = href;
                }
            }
        });
    }

    CRMInvoices.prototype.initWaLoading = function() {
        var waLoading = $.waLoading();
        var $wrapper = $(document),
            locked_class = "is-locked";

        $wrapper
            .on("wa_before_load.invoices", function() {
                waLoading.show();
                waLoading.animate(10000, 100, false);
                $wrapper.addClass(locked_class);
            })
            .on("wa_loading.invoices", function(event, xhr_event) {
                var percent = (xhr_event.loaded / xhr_event.total) * 100;
                waLoading.set(percent);
            })
            .on("wa_abort.invoices", function() {
                waLoading.abort();
                $wrapper.removeClass(locked_class);
            })
            .on("wa_loaded.invoices", function() {
                waLoading.done();
                $wrapper.removeClass(locked_class);
            });

        $('#c-invoices-page').one('remove', function () {
            $wrapper.off(".invoices");
            waLoading.$bar.remove();
            waLoading.$wrapper.remove();
            $wrapper.removeClass(locked_class);
        })
    },

    CRMInvoices.prototype.initContentRouter = function() {
        var that = this,
            $content = that.$content.find(".js-inner-content"),
            need_confirm = false,
            is_enabled = true,
            api_enabled = !!(history && history.pushState),
            xhr = false;

        that.$wrapper.on("click", "a", function(event) {
            const $link = $(this),
                  content_uri = ( $link.hasClass("js-disable-router") ? $link.attr("href") : false );

            const isMobile = () => that.$content.is(':hidden');

            if (!content_uri) return true;

            event.preventDefault();
            if (is_enabled) {

                if (need_confirm) {
                    $.waDialog.confirm({
                        title: $.crm.locales["unsaved_dialog_title"],
                        text: $.crm.locales["unsaved_dialog_text"],
                        success_button_title: $.crm.locales["unsaved_dialog_button"],
                        success_button_class: 'danger',
                        cancel_button_title: $.crm.locales['cancel'],
                        cancel_button_class: 'light-gray',
                        onSuccess: function() {
                            $(document).trigger("unsavedChanges", false);
                            load(content_uri);
                        }
                    });

                } else {
                    load(content_uri).then(() => {
                        if (isMobile()) {
                            that.showContentMobile();
                        }
                    });
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

            xhr = $.ajax({
                method: 'GET',
                url: content_uri,
                data: data,
                dataType: 'html',
                global: false,
                cache: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.addEventListener("progress", function(event) {
                        $(document).trigger("wa_loading", event);
                    }, false);

                    xhr.addEventListener("abort", function(event) {
                        $(document).trigger("wa_abort");
                    }, false);

                    return xhr;
                }
            })
            .always( function() {
                that.xhr = false;
            })
            .done(function(html) {
                if (api_enabled) {
                    history.pushState({
                        reload: true,               // force reload history state
                        content_uri: content_uri    // url, string
                    }, "", content_uri);
                }

                $(document).trigger("wa_before_render");

                $content.html(html);

                $(document).trigger("wa_loaded");
            })
            .fail( function(html) {
                $(document).trigger("wa_abort");
                if (html.responseText) {
                    showError(html.responseText);
                }
            });

            return xhr;

            function showError(text) {
                const data = {
                    title: "Error",
                    text: text,
                    button_title: "Close"
                };

                $.waDialog.alert(data);
            }
        }
    };

    CRMInvoices.prototype.initLazy = function() {
        let $list = this.$invoiceList,
            observer = null,
            $loader = this.$wrapper.find(".js-lazyload"),
            is_locked = false;

        this.$invoiceList.on('touchstart', function () {
            $("body").css({overflow: "hidden"})
            $(this).closest('.sidebar-body').css({overflow: "overlay"});
            setTimeout(()  => {
                $("body").css({overflow: "auto"})
            })
        });

        if (!$loader.length) return;

        function initObserve () {
            observer = new IntersectionObserver((entries) => {
                if (entries[0].intersectionRatio <= 0) return;
                load();
            });

            observer.observe($loader[0]);
        }
        initObserve();

        function load() {
            if (is_locked) return;

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

                observer.unobserve($loader[0]);

                if ($newLoading.length) {
                    $newLoading.insertAfter($loader);
                    $loader.remove();
                    $loader = $newLoading;

                    observer.observe($loader[0]);
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
                source: $.crm.app_url + "?module=invoiceAutocomplete",
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
