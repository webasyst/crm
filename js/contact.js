/**
 * initialized in ContactId.html */
var CRMContactPage = ( function($) {

    CRMContactPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$tabsW = that.$wrapper.find(".t-profile-tabs");

        // VARS
        that.photo_dialog_url = options.photo_dialog_url;
        that.locales = options["locales"];
        that.contact_id = ( options["contact_id"] || false );
        that.editable = options["editable"] || false;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactPage.prototype.initClass = function() {
        var that = this;

        that.initTabChange();
        //
        that.initAddCompanyContactLink();
        //
        that.initResponsibleLink();
        that.initClassifyAccessLink();
        that.initChangePhotoLink();
        that.initTopInfoFields();
        // events with link under header
        that.initEmployee();
        //
        that.initAssignTags();
        //
        that.initSegments();
        // Load additional script into 'info' tab to modify behaviour of 'team' app scripts
        that.initInfoTab();
        //
        if (that.editable) {
            that.initEditLink();
            that.initDeleteLink();
            that.initEditableName();
        }

        //
        that.initRemoveFiles();
        //
        that.initFixedHeader();
        //
        that.editInterception();
        //
        that.deleteInterception();
        //
        that.initMessage();
        //
        that.initCall();
    };

    CRMContactPage.prototype.editInterception = function() {
        var that = this,
            page_href = location.pathname.replace($.crm.app_url, ""),
            href_array = page_href.split("/");

        $(window).load( function() {
            if (href_array.indexOf("edit") >= 0) {
                that.$wrapper.find(".profile-header-links .js-edit-link").trigger("click");
            }
        });
    };

    CRMContactPage.prototype.deleteInterception = function() {
        var that = this,
            page_href = location.pathname.replace($.crm.app_url, ""),
            href_array = page_href.split("/");

        $(window).load( function() {
            if (href_array.indexOf("delete") >= 0) {
                that.$wrapper.find(".profile-header-links .js-delete-link").trigger("click");
            }
        });
    };

    CRMContactPage.prototype.initTopInfoFields = function() {
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

    CRMContactPage.prototype.initChangePhotoLink = function() {
        var that = this;

        // Open photo editor when user clicks on "Change photo" link
        that.$wrapper.find('.photo-change-link a').click(function() {
            $.get(that.photo_dialog_url, function(html) {

                // Init the dialog
                var crm_dialog = new CRMDialog({
                    html: html
                });

                // When photo editor dialog changes something, update the contact photo
                crm_dialog.$wrapper.on('photo_updated photo_deleted', function(evt, data) {
                    that.$wrapper.find('.c-userpic').attr('src', data.url);
                });

            }, 'html');
        });

    };

    CRMContactPage.prototype.initTabChange = function() {
        var that = this,
            api_enabled = ( window.history && window.history.pushState );

        that.$wrapper.find(".t-profile-tabs").on("click", ".t-tab a", changeTab);

        function changeTab(event) {
            event.preventDefault();
            if (api_enabled) {
                var $link = $(this),
                    tab_id = $link.data('tab-id'),
                    profile_uri = window.location.href.match(/^.*\/(id|contact)\/[^\/]+/);

                if (!profile_uri || !tab_id) {
                    return;
                }

                var uri = profile_uri[0] + '/' + tab_id + '/';
                history.replaceState({
                    reload: true,
                    content_uri: uri
                }, "", uri);
            }
        }
    };

    CRMContactPage.prototype.initEditLink = function() {
        var that = this;

        // Activate editor when user clicks the edit link
        that.$wrapper.find(".profile-header-links .edit-link").on("click", function() {

            // Animate scroll to tabs
            var $tab = that.$wrapper.find('.t-tab a[data-tab-id="info"]').closest('.t-tab');
            $('html, body').animate({
                scrollTop: $tab.offset().top
            }, 500);

            // Switch to the tab, then turn on the editor when tab contents are ready
            var tabs_controller = that.$wrapper.find(".t-profile-tabs").data('tabs_controller');
            tabs_controller.switchToTab('info', function($iframe) {
                return typeof $iframe[0].contentWindow.$.wa.contactEditor.switchMode === 'function';
            }).then(function($iframe) {
                $iframe[0].contentWindow.$.wa.contactEditor.switchMode('edit');
            });
        });

        // Reload after contact profile changes
        // When data in Contact Info tab is saved, update the block above calendar
        var $profile_tabs_iframes = that.$wrapper.find('.t-profile-tabs-iframes');
        $profile_tabs_iframes.on('contact_saved', function(evt, data) {
            $.crm.content.reload();
            $profile_tabs_iframes.children().hide();
            var tabs_controller = that.$wrapper.find('.t-profile-tabs').data('tabs_controller');
            tabs_controller.showTabHtml('__internal', '<div class="block double-padded"><i class="icon16 loading"></i></div>');
        });
    };

    CRMContactPage.prototype.initInfoTab = function() {
        var interval = null;
        this.$wrapper.find('.t-profile-tabs .t-tab[data-tab-id="info"] a').on('tab_content_updated', function() {
            if (interval) clearInterval(interval);
            interval = setInterval(function() {

                // Get the 'info' iframe
                var $iframe = $('#c-profile-page .t-profile-tabs-iframes iframe').filter(function() {
                    return 'info' == $(this).data('tab-id');
                });

                // Make sure it's not a loading screen
                try {
                    if (!$iframe[0].contentWindow.$.wa.contactEditor) {
                        return;
                    }
                } catch (e) {
                    return;
                }

                if (interval) clearInterval(interval);
                interval = null;

                // Load contact.info.js into iframe
                var match = $('script[src*="wa-apps/crm"]:first').attr('src').match(/^(.*\/wa-apps\/crm\/)[^\?]*(\?.*)/);///
                var app_static_url = match[1], version = match[2];

                // Load jquery UI and init script for autocomplete into iframe
                $iframe[0].contentWindow.$('head').append('<link href="'+app_static_url+'js/jquery/jquery-ui.css'+version+'" rel="stylesheet" type="text/css">');
                [ 'js/jquery/jquery-ui.min.js',
                  'js/crm.autocomplete.js',
                  'js/contact.info.js'
                ].forEach(function(fname) {
                    $iframe[0].contentWindow.$('head').append('<script src="'+app_static_url+fname+version+'"></scr'+'ipt>');
                });

            }, 100);
        });
    };

    CRMContactPage.prototype.initAddCompanyContactLink = function () {
        var that = this,
            $link = that.$wrapper.find('.js-add-company-contact');

        $link.on('click', function () {
            $.get($(this).data('dialog-url'), function(html) {
                // Init the dialog
                var crm_dialog = new CRMDialog({
                    html: html
                });
            });
        });
    };

    CRMContactPage.prototype.initResponsibleLink = function() {
        var that = this;
        that.$wrapper.find(".profile-header-links .responsible-link").on("click", function() {
            $.get($(this).data('dialog-url'), function(html) {
                // Init the dialog
                var crm_dialog = new CRMDialog({
                    remain_after_load: true,
                    html: html
                });
            });
        });

    };

    CRMContactPage.prototype.initDeleteLink = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-delete-link'),
            contact_id = that.contact_id,
            url = $.crm.app_url + '?module=contactOperation&action=delete',
            $dialog_template = $wrapper.find('.crm-contact-operation-delete-checking'),
            $dialog = $dialog_template.clone();

        $link.click(function (e) {
            e.preventDefault();

            new CRMDialog({
                html: $dialog.show(),
                onOpen: function ($dialog) {
                    var dialog = this;
                    $dialog.find('.crm-cancel').click(function () {
                        dialog.close();
                    });

                    $.get(url, { contact_ids: [contact_id], is_contact_page: 1 }, function (html) {
                        new CRMDialog({
                            html: html,
                            onOpen: function () {
                                dialog.close();
                            }
                        });
                    })
                }
            });

        });
    };

    CRMContactPage.prototype.initClassifyAccessLink = function() {
        var that = this;
        that.$wrapper.find(".profile-header-links .classify-access-link").on("click", function() {
            $.get($(this).data('dialog-url'), function(html) {
                // Init the dialog
                var crm_dialog = new CRMDialog({
                    remain_after_load: true,
                    html: html
                });
            });
        });

    };

    CRMContactPage.prototype.initEmployee = function() {
        var that = this,
            xhr = false;

        that.$wrapper.on("click", ".js-view-employees", viewEmployee);

        that.$wrapper.on("click", ".js-add-employee", addEmployee);

        function viewEmployee(event) {
            event.preventDefault();

            if (that.$tabsW.length) {
                $("html, body").scrollTop(that.$tabsW.offset().top);
            }
        }

        function addEmployee(event) {
            event.preventDefault();

            var href = $.crm.app_url + "?module=contact&action=addEmployee",
                data = {
                    company_contact_id: that.contact_id
                };

            if (xhr) {
                xhr.abort();
            }

            xhr = $.post(href, data, function(html) {
                new CRMDialog({
                    html: html
                });
            });
        }
    };

    CRMContactPage.prototype.initAssignTags = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-contact-assign-tags'),
            url = $.crm.app_url + '?module=contactOperation&action=assignTags&is_assign=1',
            contact_id = parseInt(that.contact_id) || 0;
        $link.click(function (e) {
            e.preventDefault();
            $.get(url, { contact_ids: [contact_id] }, function (html) {
                new CRMDialog({
                    html: html
                });
            });
        })
    };

    CRMContactPage.prototype.initSegments = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $link = $wrapper.find('.crm-contact-assign-segments'),
            url = $.crm.app_url + '?module=contactOperation&action=addToSegments&is_assign=1',
            contact_id = parseInt(that.contact_id) || 0;

        $link.click(function (e) {
            e.preventDefault();
            $.get(url, { contact_ids: [contact_id] }, function (html) {
                new CRMDialog({
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

            $link.find(".icon16").removeClass("folder-dynamic").addClass("loading");

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
                    icon = "<i class=\"icon16 " + ( item.icon ? item.icon : "folder-dynamic" ) + "\"></i>";
                    text = "<span>" + item.name + "</span>";
                }

                var link = "<a href=\"" + href + "\">" + icon + text + "</a>";

                if (set_separator) {
                    link = "," + link;
                }

                $list.append(link);
                set_separator = true;
            });
        }
    };

    CRMContactPage.prototype.initEditableName = function() {
        var $name = this.$wrapper.find(".js-name-editable").first();
        if ($name.length <= 0) {
            return;
        }

        var contact_id = this.contact_id;
        new CrmEditable({
            $wrapper: $name,
            saveChanged: true,
            saveNonempty: true,
            placeholder: $name.data('editable-placeholder'),
            onSave: function(that) {
                if (that.isLoading()) {
                    return;
                } else if (!that.isChanged() || !$.trim(that.getText())) {
                    that.hide();
                    return;
                }

                that.loading();
                var new_text = that.getText();
                $.post($.crm.app_url + '?module=contact&action=profileSave', {
                    data: JSON.stringify({ name: new_text }),
                    id: contact_id
                }).always(function() {
                    that.setText(new_text);
                    that.loading(false).hide();
                    $.crm.content.reload();
                });
            }
        });
    };

    CRMContactPage.prototype.initRemoveFiles = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-files-list");

        $wrapper.on("click", ".js-remove-file", confirmRemove);

        function confirmRemove(event) {
            event.preventDefault();

            var $link = $(this),
                $file = $link.closest(".c-file"),
                $name = $file.find(".c-name"),
                name = $name.text(),
                file_id = parseInt($file.data("id"));

            if (file_id > 0) {
                $.crm.confirm.show({
                    title: that.locales["remove_file_title"],
                    text: that.locales["remove_file_text"].replace(/%s/, name),
                    button: that.locales["remove_file_button"],
                    onConfirm: function() {
                        removeFile(file_id, $file);
                    }
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

    CRMContactPage.prototype.initFixedHeader = function() {
        var that = this;

        // DOM
        var $window = $(window),
            $wrapper = that.$wrapper.find(".js-profile"),
            $header = that.$wrapper.find(".js-short-profile");

        // VARS
        var wrapper_offset = $wrapper.offset(),
            color = $wrapper.css("background-color"),
            over_class = "is-over",
            fixed_class = "is-fixed";

        var color_array = color.replace("rgba(", "").replace("rgb(", "").replace(")", "").split(",");
        color_array = $.map(color_array, function(item) {
            return parseInt(item);
        });
        if (color_array.length <= 3) {
            color_array.push(0);
        } else {
            color_array[3] = 0;
        }

        // DYNAMIC VARS
        var is_fixed = false;

        $header.on("click", ".js-short-responsible-link", function() {
            $wrapper.find(".profile-header-links .js-responsible-link").trigger("click");
        });

        $header.on("click", ".js-short-access-link", function() {
            $wrapper.find(".profile-header-links .js-access-link").trigger("click");
        });

        $header.on("click", ".js-short-edit-link", function() {
            $wrapper.find(".profile-header-links .js-edit-link").trigger("click");
        });

        $header.on("click", ".js-short-delete-link", function() {
            $wrapper.find(".profile-header-links .js-delete-link").trigger("click");
        });

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
            var set_fixed = ( scroll_top > wrapper_offset.top + 70 );
            if (set_fixed) {

                $header
                    .css({
                        left: wrapper_offset.left,
                        width: $wrapper.outerWidth() + "px"
                    })
                    .addClass(fixed_class);

                var header_hover_class = "is-hover";
                if (scroll_top > wrapper_offset.top + $wrapper.outerHeight() - $header.outerHeight() ) {
                    $header.removeClass(header_hover_class)
                        .css("background", color);
                } else {
                    var gradient_style = "linear-gradient(to bottom, " + color + " 0%, " + color + " 70%, rgba(" + color_array.join(",") + ") 100%)";
                    $header.addClass(header_hover_class)
                        .css("background", gradient_style);
                }

                $wrapper.addClass(over_class);

                is_fixed = true;

            } else {

                $header
                    .removeAttr("style")
                    .removeClass(fixed_class);

                $wrapper.removeClass(over_class);

                is_fixed = false;
            }
        }

    };

    CRMContactPage.prototype.initMessage = function() {
        this.initSendEmail();
        this.initSendSMS();
    };

    CRMContactPage.prototype.initSendEmail = function() {
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
                    new CRMDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMContactPage.prototype.initSendSMS = function() {
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
                    new CRMDialog({
                        html: html
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMContactPage.prototype.initCall = function() {
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

    return CRMContactPage;

    function log( array ) {
        if (console && console.log) {
            console.log( array );
        }
    }

})(jQuery);

/**
 * initialized in ContactNew.html */
var CRMNewContactPage = ( function($) {

    CRMNewContactPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$contactSection = that.$wrapper.find(".js-contact-section");
        that.$accessSection = that.$wrapper.find(".js-access-section");
        that.$segmentsSection = that.$wrapper.find(".js-segments-section");
        that.$dealSection = that.$wrapper.find(".js-deal-section");
        that.$errorsSection = that.$wrapper.find(".js-errors-section");
        that.$extendedFieldsW = that.$wrapper.find(".js-contact-extended-fields");

        // VARS
        that.call_id = options["call_id"];
        that.funnels = options["funnels"];
        that.locales = options["locales"];
        that.contact_data = options["contact_data"];
        that.stage_template_html = options["stage_template_html"];
        that.extended_fields = options["extended_fields"] || {};

        // DYNAMIC VARS
        that.is_extended = ( options["is_extended"] || false );
        that.selected_segments = false;
        that.create_deal = false;

        // INIT
        that.initClass();
    };

    CRMNewContactPage.prototype.initClass = function() {
        var that = this;
        //
        $.crm.renderSVG(that.$wrapper);
        //
        if (that.contact_data) {
            that.setContactData(that.contact_data);
        }
        //
        that.initContactToggle();
        //
        that.initAccessToggle();
        //
        that.initSegmentsToggle();
        //
        that.initDealToggle();
        //
        that.initDealSection();
        //
        if (that.$extendedFieldsW.length) {
            that.initExtendedFields();
        }
        //
        that.initCreate();
        //
        that.initHeaderActions();
        //
        that.initWYSIWYG();
        //
        that.initContactUpdateDialog();
    };

    CRMNewContactPage.prototype.initContactToggle = function() {
        var that = this,
            active_class = "is-extended";

        that.$contactSection.on("click", ".js-extended-toggle", function(event) {
            event.preventDefault();
            var is_active = ( that.$contactSection.hasClass(active_class) );

            if (!is_active) {
                that.$contactSection.addClass(active_class);
                that.is_extended = true;
            } else {
                that.$contactSection.removeClass(active_class);
                that.is_extended = false;
            }
        });
    };

    CRMNewContactPage.prototype.initAccessToggle = function() {
        var that = this,
            active_class = "is-extended";

        var $field = that.$accessSection.find(".js-ibutton");

        $field.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        });

        $field.on("change", function() {
            var is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                that.$accessSection.addClass(active_class);
                that.selected_access = true;
            } else {
                that.$accessSection.removeClass(active_class);
                that.selected_access = false;
            }
        });
    };

    CRMNewContactPage.prototype.initSegmentsToggle = function() {
        var that = this,
            active_class = "is-extended";

        var $field = that.$segmentsSection.find(".js-ibutton");

        $field.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        });

        $field.on("change", function() {
            var is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                that.$segmentsSection.addClass(active_class);
                that.selected_segments = true;
            } else {
                that.$segmentsSection.removeClass(active_class);
                that.selected_segments = false;
            }
        });
    };

    CRMNewContactPage.prototype.initDealToggle = function() {
        var that = this,
            active_class = "is-extended";

        var $field = that.$dealSection.find(".js-ibutton");

        $field.iButton({
            labelOn : "",
            labelOff : "",
            classContainer: "c-ibutton ibutton-container mini"
        });

        $field.on("change", function() {
            var is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                that.$dealSection.addClass(active_class);
                that.create_deal = true;
            } else {
                that.$dealSection.removeClass(active_class);
                that.create_deal = false;
            }
        });
    };

    CRMNewContactPage.prototype.initDealSection = function() {
        var that = this;

        // DOM
        var $funnelSelect = that.$wrapper.find(".js-funnels-list");


        //

        initChangeFunnel();

        initChangeStage();

        initEstimatedDate();

        initChangeCompanyContact();

        //

        function initChangeFunnel() {
            var $wrapper = $funnelSelect,
                $visibleLink = $wrapper.find(".js-visible-link"),
                $field = $wrapper.find(".js-field"),
                $menu = $wrapper.find(".menu-v");

            $menu.on("click", "a", function() {
                var $link = $(this);
                $visibleLink.find(".js-text").html($link.html());

                $menu.find(".selected").removeClass("selected");
                $link.closest("li").addClass("selected");

                $menu.hide();
                setTimeout( function() {
                    $menu.removeAttr("style");
                }, 200);

                var id = $link.data("id");
                $field.val(id).trigger("change");

                var funnel = ( that.funnels[id] || false );
                if (funnel) {
                    $wrapper.trigger("changeFunnel", funnel);
                }
            });

            $.crm.renderSVG($wrapper);
        }

        function initChangeStage() {
            var $funnelWrapper = that.$wrapper.find(".js-funnels-list"),
                $wrapper = that.$wrapper.find(".js-funnel-stages-list"),
                $visibleLink = $wrapper.find(".js-visible-link"),
                $field = $wrapper.find(".js-field"),
                $menu = $wrapper.find(".menu-v");

            $menu.on("click", "a", function () {
                var $link = $(this);
                $visibleLink.find(".js-text").html($link.html());

                $menu.find(".selected").removeClass("selected");
                $link.closest("li").addClass("selected");

                $menu.hide();
                setTimeout( function() {
                    $menu.removeAttr("style");
                }, 200);

                var id = $link.data("id");
                $field.val(id);
            });

            $funnelWrapper.on("changeFunnel", function(event, funnel) {
                renderStages(funnel.stages);
            });

            function renderStages(stages) {
                $menu.html("");

                $.each(stages, function(index, stage) {
                    var stage_template = that.stage_template_html;
                    var name = $("<div />").text(stage.name).html();

                    stage_template = stage_template
                        .replace("%id%", stage.id)
                        .replace("%color%", stage.color)
                        .replace("%name%", name);

                    var $stage = $(stage_template);

                    $menu.append($stage);
                });

                $.crm.renderSVG($wrapper);

                $menu.find("li:first-child a").trigger("click");
            }
        }

        function initEstimatedDate() {
            var $wrapper = that.$wrapper.find(".js-datepicker-wrapper"),
                $input    = $wrapper.find(".js-datepicker"),
                $altField = $wrapper.find('[name="deal[expected_date]"]');

            $input.datepicker({
                altField: $altField,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                onSelect: checkDate
            });

            $input.on('blur', checkDate);

            $input.on("keydown keypress keyup", function(event) {
                if ( event.which === 13 ) {
                    event.preventDefault();
                }
            });

            $wrapper.on("click", ".js-icon", function () {
                $input.focus();
            });

            //

            function checkDate() {
                var format = $.datepicker._defaults.dateFormat,
                    is_valid = false;
                try {
                    $.datepicker.parseDate(format, $input.val());
                    is_valid = true;
                } catch(e) {}
                if (is_valid) {
                    $input.data('last-correct-value', $input.val());
                    $altField.data('last-correct-value', $altField.val());
                } else {
                    $input.val($input.data('last-correct-value') || '');
                    $altField.val($altField.data('last-correct-value') || '');
                }
            }
        }

        function initChangeCompanyContact() {
            var $wrapper = that.$wrapper.find(".js-contact-block"),
                active_class = "is-active",
                $toggleW = $wrapper.find(".js-view-toggle"),
                $activeToggle = $toggleW.find("." + active_class);

            $toggleW.on("click", ".c-toggle", setToggle);

            function setToggle(event) {
                event.preventDefault();

                var $toggle = $(this),
                    content_id = $toggle.data("id"),
                    is_active = $toggle.hasClass(active_class);

                if (is_active) {
                    return false;
                } else {

                    that.contact_mode = content_id;

                    // clear
                    if ($activeToggle.length) {
                        $activeToggle.removeClass(active_class);
                    }
                    // render link
                    $toggle.addClass(active_class);
                    $activeToggle = $toggle;
                    // render content
                    showContent(content_id);
                }
            }

            function showContent(content_id) {
                // clear
                $wrapper.find(".c-hidden." + active_class).removeClass(active_class);
                // render
                $wrapper.find(".c-hidden-" + content_id).addClass(active_class);
            }

        }
    };

    CRMNewContactPage.prototype.initCreate = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-submit-form", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            removeErrors();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                };

            getContactData();

            if (that.$extendedFieldsW.length && that.is_extended) {
                getExtendedFieldsData();
            }

            if (that.selected_access) {
                getAccessData();
            }

            if (that.selected_segments) {
                getSegmentsData();
            }

            if (that.create_deal) {
                getDealData();
            }

            return result;

            function getContactData() {
                var $contactForm = that.$contactSection.find("form.c-general-form"),
                    $phoneList = $contactForm.find(".js-phone-list"),
                    $emailList = $contactForm.find(".js-email-list");

                $phoneList.find("li .js-value, li .js-ext").removeAttr("name");
                $emailList.find("li .js-value, li .js-ext").removeAttr("name");

                var contactData = $contactForm.serializeArray();

                var fio_names = ["contact[firstname]","contact[middlename]","contact[lastname]","contact[name]"],
                    fio_is_empty = true;

                $.each(contactData, function(index, item) {
                    item.value = $.trim(item.value);

                    result.data.push(item);

                    if ($.trim(item.value).length > 0) {
                        if (fio_names.indexOf(item.name) >= 0) {
                            fio_is_empty = false;
                        }
                    }
                });

                if (fio_is_empty) {
                    var $field = that.$contactSection.find("[name=\"contact[firstname]\"]");
                    if ($field.length) {
                        result.errors.push({
                            name: "contact[firstname]",
                            value: that.locales["empty_contact_name"]
                        });
                    } else {
                        $field = that.$contactSection.find("[name=\"contact[name]\"]");
                        if ($field.length) {
                            result.errors.push({
                                name: "contact[name]",
                                value: that.locales["empty_contact_name"]
                            });
                        }
                    }
                }

                $phoneList.find("li").each( function(index) {
                    var $li = $(this),
                        $phoneField = $li.find(".js-value"),
                        $extField = $li.find(".js-ext"),
                        phone = $phoneField.val(),
                        ext = $extField.val();

                    if (phone) {
                        var phone_name = "contact[phone][" + index + "][value]";
                        $phoneField.attr("name", phone_name);
                        result.data.push({
                            name: phone_name,
                            value: phone
                        });

                        var phone_ext_name = "contact[phone][" + index + "][ext]";
                        $extField.attr("name", phone_ext_name);
                        result.data.push({
                            name: phone_ext_name,
                            value: ext
                        });
                    }
                });

                $emailList.find("li").each( function(index) {
                    var $li = $(this),
                        $emailField = $li.find(".js-value"),
                        $extField = $li.find(".js-ext"),
                        email = $emailField.val(),
                        ext = $extField.val();

                    if (email) {
                        var email_name = "contact[email][" + index + "][value]";
                        $emailField.attr("name", email_name);
                        result.data.push({
                            name: email_name,
                            value: email
                        });

                        var email_ext_name = "contact[email][" + index + "][ext]";
                        $extField.attr("name", email_ext_name);
                        result.data.push({
                            name: email_ext_name,
                            value: ext
                        });
                    }
                });
            }

            function getAccessData() {
                var $activeOption = that.$wrapper.find(".js-option-field:checked"),
                    $vaultsToggle = that.$wrapper.find(".js-vault-toggle"),
                    $ownersList = that.$wrapper.find(".js-owners-list");
                if ($activeOption.length) {
                    var value = $activeOption.val();

                    if (value === "vaults") {
                        var vault_id = $vaultsToggle.find(".js-field").val();

                        result.data.push({
                            name: "vault_id",
                            value: vault_id
                        });
                    } else if (value === "owners") {
                        var $owners = $ownersList.find(".c-owner");

                        if (!$owners.length) {
                            result.errors.push({
                                name: "owner[autocomplete]"
                            });
                        }

                        var owners = $owners.each( function() {
                            var $owner = $(this),
                                owner_id = $owner.data("id");

                            if (owner_id > 0) {
                                result.data.push({
                                    name: "owners[]",
                                    value: owner_id
                                });
                            }
                        });
                    }
                }
            }

            function getSegmentsData() {
                var $segmentsForm = that.$segmentsSection.find("form"),
                    segmentsData = $segmentsForm.serializeArray();

                $.each(segmentsData, function(index, item) {
                    result.data.push(item);
                });
            }

            function getDealData() {
                var $dealForm = that.$dealSection.find("form"),
                    dealData = $dealForm.serializeArray();

                var name_is_empty = true;

                $.each(dealData, function(index, item) {
                    result.data.push(item);

                    if ($.trim(item.value).length > 0) {
                        if (item.name === "deal[name]") {
                            name_is_empty = false;
                        }
                    }
                });

                if (name_is_empty) {
                    result.errors.push({
                        name: "deal[name]",
                        value: that.locales["empty_deal_name"]
                    });
                }
            }

            function getExtendedFieldsData() {
                var $wrapper = that.$extendedFieldsW,
                    formData = $wrapper.data("getFormData")();

                $.each(formData.data, function(index, item) {
                    result.data.push(item);
                });

                $.each(formData.errors, function(index, item) {
                    result.errors.push(item);
                });
            }
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                errors = ajax_errors;
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$wrapper.find(":input[name=\"" + name + "\"]");
                var $text = $("<div />").addClass("errormsg").text(text);
                if ($field.length) {
                    if (!$field.hasClass(error_class)) {
                        $field.parent().append($text);
                        $field
                            .addClass(error_class)
                            .one("focus click change", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    }
                } else {
                    that.$errorsSection.append($text);
                }
            });
        }

        function removeErrors() {
            that.$errorsSection.html("");
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=contact&action=newSave";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var content_uri = $.crm.app_url + "contact/" + response.data.contact.id + "/";
                        $.crm.content.load(content_uri);
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMNewContactPage.prototype.initExtendedFields = function() {
        var that = this,
            $wrapper = that.$extendedFieldsW,
            $form = $wrapper.find("form");

        initDynamicLists();

        initAddressLists();

        initBankLists();

        initDatepickers();

        $wrapper.data("getFormData", getFormData);

        //

        function initDynamicLists() {
            var $lists = $wrapper.find(".js-dynamic-field-wrapper");

            $lists.each(initList);

            function initList() {
                var $listW = $(this),
                    $list = $listW.find(".js-list"),
                    template_html = $list.find(".c-item")[0].outerHTML;

                $listW.on("click", ".js-add", addItem);

                $listW.on("click", ".js-remove", removeItem);

                $list.on("change", ".js-ext-toggle", function() {
                    var $toggle = $(this),
                        $item = $toggle.closest(".c-item"),
                        $field = $item.find(".js-ext-field");

                    if ( $toggle.val() === "custom" ) {
                        $field.attr("disabled", false).show();
                    } else {
                        $field.attr("disabled", true).hide();
                    }
                });

                function addItem() {
                    $list.append( template_html );
                }

                function removeItem() {
                    $(this).closest(".c-item").remove();
                }
            }

            $wrapper.on("prepareSave", setNames);

            function setNames() {
                $lists.each( function() {
                    var $listW = $(this),
                        $list = $listW.find(".js-list"),
                        $items = $list.find(".c-item");

                    $items.each( function(index) {
                        var $item = $(this),
                            $fields = $item.find("[data-name-pattern]");

                        $fields.each( function() {
                            var $field = $(this),
                                pattern = $field.data("name-pattern");

                            if (pattern) {
                                var name = pattern.replace("%index%", index);
                                $field.attr("name", name);
                            }
                        });
                    });
                });
            }
        }

        function initBankLists() {
            var $banksW = $wrapper.find(".js-bank-section");
            if (!$banksW.length) {
                return false;
            }

            var $list = $banksW.find(".js-bank-list"),
                template_html = $banksW.find(".js-bank-wrapper")[0].outerHTML;

            $banksW.on("click", ".js-add", function() {
                $list.append( template_html );
            });

            $list.on("click", ".js-remove", function () {
                $(this).closest(".js-bank-wrapper").remove();
            });

            $banksW.on("change", ".js-ext-toggle", function() {
                var $toggle = $(this),
                    $bank = $toggle.closest(".js-bank-wrapper"),
                    $field = $bank.find(".js-ext-field");

                if ( $toggle.val() === "custom" ) {
                    $field.attr("disabled", false).show();
                } else {
                    $field.attr("disabled", true).hide();
                }
            });

            $wrapper.on("prepareSave", setNames);

            function setNames() {
                $list.each( function() {
                    var $list = $(this),
                        $items = $list.find(".js-bank-wrapper");

                    $items.each( function(index) {
                        var $item = $(this),
                            $fields = $item.find("[data-name-pattern]");

                        $fields.each( function() {
                            var $field = $(this),
                                pattern = $field.data("name-pattern");

                            if (pattern) {
                                var name = pattern.replace("%index%", index);
                                $field.attr("name", name);
                            }
                        });
                    });
                });
            }
        }

        /**
         * Duplicate of waContactConditionalField->getHtmlOne inner js
         * @param options
         */
        function initConditionalField(options) {
            options = options || {};

            var $W = that.$wrapper;

            var event_ns = 'c-conditional-field-' + ('' + Math.random()).slice(2);
            var input_name = options.input_name;

            options.parent_field = options.parent_field || '';
            options.parent_options = options.parent_options || {};
            options.index = options.index || 0;

            if (!options.parent_field || $.isEmptyObject(options.parent_options)) {
                return;
            }

            var parts = options.parent_field.split(':');
            if (parts.length <= 1) {
                return;
            }

            var parent_field_selector = '[name="contact[' + parts[0] + '][' + options.index + '][' + parts[1] + ']"]';

            var parent_field = $W.find(parent_field_selector);

            if (parent_field.length <= 0) {
                return;
            }

            options.show_empty_option = options.required;

            var values = options.parent_options;
            var select;

            var input = $W.find(':input[name="'+input_name+'"]').first();

            if (input.length <= 0) {
                return;
            }
            if (input.is('input')) {
                select = input.next();
            } else {
                select = input;
                input = select.prev();
            }

            var showInput = function() {
                if (!input[0].hasAttribute('name')) {
                    input.attr('name', select.attr('name'))
                    select[0].removeAttribute('name');
                }
                input.show().val('');
                select.hide();
            };

            var getVal = function() {
                if (input.is(':visible')) {
                    return input.val();
                } else {
                    return select.val();
                }
            };

            // Parent field on-change handler
            var handler = function() {
                var old_val = getVal();
                var parent_value = $(this).val().toLowerCase();
                if (options.hide_unmatched) {
                    input.closest('.field').show();
                }
                if (values && values[parent_value]) {
                    var option_values = values[parent_value];
                    input.hide();
                    select.show().children().remove();
                    if (options.show_empty_option) {
                        select.append($('<option value=""></option>'));
                    }
                    for (var i = 0; i < option_values.length; i++) {
                        select.append($('<option></option>').attr('value', option_values[i]).text(option_values[i]));
                    }
                    select.val(old_val);
                    if (input[0].hasAttribute('name')) {
                        select.attr('name', input.attr('name'));
                        input[0].removeAttribute('name');
                    }
                } else if (options.hide_unmatched) {
                    showInput();
                    input.val('');
                    input.closest('.field').hide();
                } else {
                    if (!input.is(':visible')) {
                        showInput();
                        input.val(old_val);
                    }
                }
            };
            handler.call(parent_field);

            var wrapper = parent_field.closest('.field');
            if (wrapper.length) {
                wrapper.off('.' + event_ns);
                wrapper.on('change.' + event_ns, parent_field_selector, handler);
            } else {
                parent_field.off('.' + event_ns);
                parent_field.on('change.' + event_ns, handler);
            }
        }

        function initAddressLists() {

            function initAddressSection($addressW) {

                function init() {

                    // for custom subfields: init data-name-pattern attrs and delete name attrs (that has been generated by system)
                    $addressW.find('.js-custom-sub-field-line').each(function() {
                        var $line = $(this),
                            name_pattern = $line.data('name-pattern');

                        $line.find(':input').each(function() {
                            var $input = $(this);
                            $input.attr('data-name-pattern', name_pattern);
                            $input.removeAttr('name');
                        });
                    });

                    $addressW.find('.js-custom-sub-field-line[data-type="Select"]').each(function () {
                        var $line = $(this);
                        if (!$line.data('sub-type')) {
                            var $select = $line.find('select');
                            $select.find('option[value=""]').text($line.data('label') + '...');
                        }
                    });

                    $addressW.find('.js-custom-sub-field-line[data-type="Checkbox"]').each(function () {
                        var $line = $(this),
                            $checkbox = $line.find(':checkbox');
                        $checkbox.wrap('<label>');
                        var $label = $checkbox.parent();
                        $label.append('<span>');
                        $label.find('span').append(' ' + $line.data('label'));

                    });
                }

                // IMPORTANT: this function MUST BE first through all other blocks, cause prepare html structure
                init();

                var extended_fields = that.extended_fields || {},
                $list = $addressW.find(".js-address-list"),
                    template_html = $addressW.find(".js-address-wrapper")[0].outerHTML;

                // set for :input proper name based on data-name-pattern
                function setNames() {
                    $list.each( function() {
                        var $list = $(this),
                            $items = $list.find(".js-address-wrapper");

                        $items.each( function(index) {
                            var $item = $(this),
                                $fields = $item.find("[data-name-pattern]");

                            $fields.each( function() {
                                var $field = $(this),
                                    pattern = $field.data("name-pattern");

                                if (pattern) {
                                    var name = pattern.replace("%index%", index);
                                    $field.attr("name", name);

                                    if ($field.is('input')) {
                                        var $fieldW = $field.closest('.js-custom-sub-field-line');
                                        if ($fieldW.is('[data-type="Conditional"]')) {
                                            var field_id = $fieldW.data('field-id'),
                                                subfield_id = $fieldW.data('subfield-id');
                                            var options = extended_fields[field_id]['fields'][subfield_id];
                                            if (options) {
                                                initConditionalField($.extend(options, {
                                                    input_name: name,
                                                    index: index
                                                }));
                                            }
                                        }
                                    }


                                }
                            });
                        });
                    });
                }

                $wrapper.on("prepareSave", setNames);

                // we must call setNames, so radio inputs work correctly (cause group of radio inputs must have same names)
                setNames();

                $addressW.on("click", ".js-add", function() {
                    $list.append( template_html );
                    // we must call setNames, so radio inputs work correctly (cause group of radio inputs must have same names)
                    setNames();
                });

                $addressW.on("click", ".js-remove", function () {
                    $(this).closest(".js-address-wrapper").remove();
                });

                $addressW.on("click", ".js-clear-selected-radio-value", function () {
                    var $line = $(this).closest('.js-custom-sub-field-line');
                    $line.find(':radio:checked').attr('checked', false);
                });

                $addressW.on("change", ".js-ext-toggle", function() {
                    var $toggle = $(this),
                        $address = $toggle.closest(".js-address-wrapper"),
                        $field = $address.find(".js-ext-field");

                    if ( $toggle.val() === "custom" ) {
                        $field.attr("disabled", false).show();
                    } else {
                        $field.attr("disabled", true).hide();
                    }
                });

                $addressW.on("change", ".js-country-toggle", function() {
                    var $toggle = $(this),
                        $address = $toggle.closest(".js-address-wrapper"),
                        $regionSelect = $address.find(".js-region-select"),
                        $regionField = $address.find(".js-region-field"),
                        country = $toggle.val(),
                        xhr = false;

                    if (country) {
                        setRegions(country);
                    } else {
                        showSelect(false);
                    }

                    function setRegions() {
                        if (xhr) {
                            xhr.abort();
                        }

                        var href = $.crm.backend_url + "?module=profile&action=regions",
                            data = {
                                country: country
                            };

                        xhr = $.post(href, data, function(response) {
                            if (response.status === "ok") {
                                if (!$.isEmptyObject(response.data.options)) {
                                    showSelect(response.data);
                                } else {
                                    showSelect(false);
                                }
                            }
                        }).always( function() {
                            xhr = false;
                        });
                    }

                    function showSelect(data) {
                        $regionSelect.html("");
                        $regionField.val("");

                        if (data && Object.keys(data).length) {
                            $regionSelect.attr("disabled", false).show();
                            $regionField.attr("disabled", true).hide();

                            $.each(data.oOrder, function(index, region_id) {
                                var region_name = data.options[region_id];
                                if (region_name) {
                                    var option = "<option value=\"" + region_id + "\">" + region_name + "</option>";
                                    $regionSelect.append(option);
                                }
                            });

                        } else {
                            $regionSelect.attr("disabled", true).hide();
                            $regionField.attr("disabled", false).show();
                        }
                    }
                });
            }

            $wrapper.find(".js-address-section").each(function () {
                initAddressSection($(this));
            });
        }

        function initDatepickers() {
            $wrapper.find('.js-datepicker input:text').each(function() {
                var $input_text = $(this);
                var $input_hidden = $input_text.siblings('input[type="hidden"]');

                $input_text.datepicker({
                    altField: $input_hidden,
                    altFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true,
                    onSelect: checkDate
                });
                $input_text.on('blur', checkDate);
                $input_text.on("keydown keypress keyup", function(event) {
                    if (event.which === 13) {
                        event.preventDefault();
                    }
                });

                function checkDate() {
                    try {
                        $.datepicker.parseDate($.datepicker._defaults.dateFormat, $input_text.val());

                        // input is valid!
                        $input_text.data('last-correct-value', $input_text.val());
                        $input_hidden.data('last-correct-value', $input_hidden.val());

                    } catch(e) {

                        // invalid input!
                        $input_text.val($input_text.data('last-correct-value') || '');
                        $input_hidden.val($input_hidden.data('last-correct-value') || '');

                    }
                }
            });
        }

        function getFormData() {
            $wrapper.trigger("prepareSave");

            var data = $form.serializeArray(),
                result = {
                    data: [],
                    errors: []
                };

            $.each(data, function(index, item) {
                result.data.push(item);
            });

            return result;
        }
    };

    CRMNewContactPage.prototype.setContactData = function(contact_data) {
        var that = this;

        $.each(contact_data, function(name, value) {
            if (typeof value === "string" || typeof value === "number") {
                var $field = that.$wrapper.find("[name=\"contact[" + name + "]\"]");
                if ($field.length) {
                    $field.val(value).trigger("change");
                }
            } else {
                if (name === "phone") {
                    $.each(value, function(index, number) {
                        var phone = {
                            number: number.value,
                            ext: number.ext
                        };
                        $(document).trigger("addContactPhone", phone);
                    });
                }
                if (name === "email") {
                    $.each(value, function(index, _email) {
                        var email = {
                            name: _email.value,
                            ext: _email.ext
                        };
                        $(document).trigger("addContactEmail", email);
                    });
                }
            }
        });
    };

    CRMNewContactPage.prototype.initHeaderActions = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-personal-settings-dialog", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=contact&action=createSettings",
                    data = {};

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

    CRMNewContactPage.prototype.initWYSIWYG = function() {
        var that = this;

        var $areas = that.$wrapper.find(".js-wysiwyg");
        if (!$areas.length) {
            return false;
        }

        $areas.each( function() {
            var $textarea = $(this);

            $.crm.initWYSIWYG($textarea, {
                keydownCallback: function (e) {
                    //if (e.keyCode == 13 && e.ctrlKey) {
                    //return addComment(); // Ctrl+Enter disabled
                    //}
                }
            });
        })
    };

    CRMNewContactPage.prototype.initContactUpdateDialog = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-update-dialog", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=contact&action=updateDialog",
                    data = {
                        call_id: that.call_id
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

    return CRMNewContactPage;

})(jQuery);

/** Controller for access dialog on the create contact page
 *  initialized in /contact/ContactNew.html */
var CRMContactAccessDialogNew = ( function($) {

    CRMContactAccessDialogNew = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$optionsList = that.$wrapper.find(".js-options-list");
        that.$vaultsToggle = that.$wrapper.find(".js-vault-toggle");
        that.$ownersList = that.$wrapper.find(".js-owners-list");
        that.$submitButton = that.$wrapper.find(".js-save");
        that.$errorsPlace = that.$wrapper.find(".js-errors-place");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.owner_template_html = options["owner_template_html"];
        that.contact_id = options["contact_id"];
        that.owners = options["owners"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactAccessDialogNew.prototype.initClass = function() {
        var that = this;

        //
        that.initChangeOptions();
        // Initialize contact autocomplete
        that.initAutocomplete();
        //
        that.initVaultToggle();
    };

    CRMContactAccessDialogNew.prototype.initChangeOptions = function() {
        var that = this,
            active_class = "is-active",
            $activeOption = that.$optionsList.find(".c-option." + active_class);

        // Show/hide list of owners when user changes vault radio
        that.$optionsList.on("change", ".js-option-field", function() {
            var $field = $(this),
                $option = $field.closest(".c-option"),
                is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                if ($activeOption.length) {
                    $activeOption.removeClass(active_class);
                }

                $activeOption = $option.addClass(active_class);
            }
        });
    };

    CRMContactAccessDialogNew.prototype.initAutocomplete = function() {
        var that = this;

        var $field = that.$ownersList.find(".js-autocomplete");

        $field.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var $owner = findOwner(ui.item.id) || addOwner(ui.item);
                markOwner($owner);
                $field.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $field.on("focus", function(){
            $field.data("uiAutocomplete").search( $field.val() );
        });

        // Remove owner when user clicks delete icon
        that.$ownersList.on("click", ".js-delete-owner", function() {
            $(this).closest(".c-owner").remove();
        });

        function findOwner(user_id) {
            var $owner = that.$ownersList.find(".c-owner[data-id=\"" + user_id + "\"]");
            if ($owner.length) {
                return $owner;
            }
        }

        function addOwner(user) {
            var template = that.owner_template_html;

            template = template
                .replace(/%id%/, user.id)
                .replace("%name%", escapeHtml(user.name))
                .replace("%photo_url%", user.photo_url);

            var $template = $(template);

            that.$ownersList.append($template);

            return $template;
        }

        function markOwner($user) {
            var active_class = "highlighted";

            $user.addClass(active_class);
            setTimeout(function() {
                $user.removeClass(active_class);
            }, 2000);
        }

        function escapeHtml(string) {
            return $('<div></div>').text(string).html();
        }
    };

    CRMContactAccessDialogNew.prototype.initVaultToggle = function() {
        var that = this,
            $wrapper = that.$vaultsToggle,
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id);
        });

        $.crm.renderSVG($wrapper);
    };

    return CRMContactAccessDialogNew;

})(jQuery);

/** Controller for Responsible user
 *  initialized in /contact/ContactResponsibleDialog.html */
var CRMContactResponsibleDialog = ( function($) {

    CRMContactResponsibleDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$responsibleList = that.$wrapper.find(".js-responsible-list");
        that.$submitButton = that.$wrapper.find(".js-save");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.locales = options["locales"];
        that.responsible_template_html = options["responsible_template_html"];

        // INIT
        that.initClass();
    };

    CRMContactResponsibleDialog.prototype.initClass = function() {
        var that = this;

        // Data is changed
        that.$wrapper.on("change", "input", function() {
            that.toggleButton(true);
        });
        //
        that.initSubmit();
        //
        that.initClearResponsible();
        //
        that.initAutocomplete();
    };

    CRMContactResponsibleDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=contact&action=responsibleSave",
                    data = getData();

                if (!data) {
                    is_locked = false;
                    return false;
                }

                var $loading = $('<span class="icon loading"><i class="fas fa-spinner wa-animation-spin"></i></span>');
                    $loading.appendTo(that.$submitButton.parent());

                that.$submitButton.attr("disabled", true);

                $.post(href, data, function(response) {
                    if (response.data.result === "ok") {
                        $.crm.content.reload().then( function() {
                            that.dialog.close();
                        });
                    } else if (response.data.result === "no_vault_access") {
                        $('.no-access-error').html(response.data.message);
                        setTimeout(function() {
                            that.dialog.close();
                        }, 5000);
                    }
                }, "json").always( function() {
                    that.$submitButton.attr("disabled", false);
                    that.toggleButton(false);
                    $loading.remove();
                    is_locked = false;
                });
            }
        }

        function getData() {
            var contact_id = $("#contact_id").val();
            var responsible_id = $("#responsible_id").val();
            var result = {
              contact_id: contact_id,
              responsible_id: responsible_id
            };
            return result;
        }

        function showError(error) {
            var $error = $("<div class=\"errormsg\" />").text(error);
            that.$errorsPlace.append($error);
        }
    };


    CRMContactResponsibleDialog.prototype.initClearResponsible = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-clear-responsible", function(event) {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales["clear_responsible_title"],
                text: that.locales["clear_responsible_text"],
                button: that.locales["clear_responsible_button"],
                onConfirm: clearResponsible
            });
        });

        function clearResponsible() {

            var href = $.crm.app_url + "?module=contact&action=responsibleSave",
                data = getData();

            var $loading = $('<span class="icon loading"><i class="fas fa-spinner wa-animation-spin"></i></span>');
                $loading.appendTo(that.$submitButton.parent());

            that.$submitButton.attr("disabled", true);

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    $.crm.content.reload().then( function() {
                        that.dialog.close();
                    });
                } else {
                    console.log('Error saving Responsible contact classification: '+arguments[2], arguments);
                    showError("Error saving Responsible contact classification: " + arguments[2]);
                }
            }, "json").always( function() {
                that.$submitButton.attr("disabled", false);
                that.toggleButton(false);
                $loading.remove();
                is_locked = false;
            });
        }

        function getData() {
            var contact_id = $("#contact_id").attr('value');
            var result = {
                  contact_id: contact_id,
                  responsible_id: 0
            };
            return result;
        }

        function showError(error) {
            var $error = $("<div class=\"errormsg\" />").text(error);
            that.$errorsPlace.append($error);
        }
    };

    CRMContactResponsibleDialog.prototype.initAutocomplete = function() {
        var that = this;
        var contact_id = $("#contact_id").val();

        var $field = that.$responsibleList.find(".js-autocomplete");

        $field.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user&contact_id=" + contact_id,
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var $responsible_user = findResponsible(ui.item.id) || addResponsible(ui.item);
                markOwner($responsible_user);
                that.toggleButton(true);
                $field.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
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

        // Удаляем ответственного пользователя
        that.$responsibleList.on("click", ".js-delete-responsible", function() {
            $(this).closest(".c-responsible").remove(); // Удаляем ответственного
            $("#responsible_id").val("");
            $(".js-input").css("display",""); // показываем инпат
            $(".js-autocomplete").focus();
            that.$submitButton.attr("disabled", true);
            that.toggleButton(true); // Меняем ей класс
        });

        function findResponsible(user_id) {
            var $responsible = that.$responsibleList.find(".c-responsible[data-id=\"" + user_id + "\"]");
            if ($responsible.length) {
                return $responsible;
            }
        }

        // Добавляем ответственного пользователя
        function addResponsible(user) {
            var template = that.responsible_template_html;

            template = template
                .replace(/%id%/, user.id)
                .replace("%name%", escapeHtml(user.name))
                .replace("%photo_url%", user.photo_url);

            $("#responsible_id").val(user.id);

            var $template = $(template);

            that.$responsibleList.append($template);

            $(".js-input").css("display","none"); // прячем инпат
            that.$submitButton.attr("disabled", false);
            that.toggleButton(true);

            return $template;
        }

        function escapeHtml(string) {
            return $('<div></div>').text(string).html();
        }

        function markOwner($user) {
            var active_class = "highlighted";

            $user.addClass(active_class);
            setTimeout(function() {
                $user.removeClass(active_class);
            }, 2000);
        }
    };

    CRMContactResponsibleDialog.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$submitButton;

        if (is_changed) {
            $button.addClass("yellow");
        } else {
            $button.removeClass("yellow");
        }
    };

    return CRMContactResponsibleDialog;

})(jQuery);

/** Controller for access dialog
 *  initialized in /contact/ContactAccessDialog.html */
var CRMContactAccessDialog = ( function($) {

    CRMContactAccessDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$optionsList = that.$wrapper.find(".js-options-list");
        that.$vaultsToggle = that.$wrapper.find(".js-vault-toggle");
        that.$ownersList = that.$wrapper.find(".js-owners-list");
        that.$submitButton = that.$wrapper.find(".js-save");
        that.$errorsPlace = that.$wrapper.find(".js-errors-place");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.owner_template_html = options["owner_template_html"];
        that.contact_id = options["contact_id"];
        that.owners = options["owners"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactAccessDialog.prototype.initClass = function() {
        var that = this;

        // Data is changed
        that.$wrapper.on("change", "input", function() {
            that.toggleButton(true);
        });
        //
        that.initChangeOptions();
        //
        that.initSubmit();
        // Initialize contact autocomplete
        that.initAutocomplete();
        //
        that.initVaultToggle();
    };

    CRMContactAccessDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                removeErrors();

                var href = $.crm.app_url + "?module=contact&action=accessSave",
                    data = getData();

                if (!data) {
                    is_locked = false;
                    return false;
                }

                var $loading = $('<span class="icon loading"><i class="fas fa-spinner wa-animation-spin"></i></span>');
                $loading.appendTo(that.$submitButton.parent());

                that.$submitButton.attr("disabled", true);

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.reload().then( function() {
                            that.dialog.close();
                        });
                    } else {
                        console.log('Error saving contact access classification: '+arguments[2], arguments);
                        showError("Error saving contact access classification: " + arguments[2]);
                    }
                }, "json").always( function() {
                    that.$submitButton.attr("disabled", false);
                    $loading.remove();
                    that.toggleButton(false);
                    is_locked = false;
                });
            }
        }

        function getData() {
            var result = [];

            var $activeOption = that.$wrapper.find(".js-option-field:checked");
            if ($activeOption.length) {
                var value = $activeOption.val();

                if (value === "vaults") {
                    var vault_id = that.$vaultsToggle.find(".js-field").val();

                    result.push({
                        name: "vault_id",
                        value: vault_id
                    });
                }

                if (value === "owners") {
                    var $owners = that.$ownersList.find(".c-owner");

                    if (!$owners.length) {
                        showError(that.locales["empty_owner"]);
                        return false;
                    }

                    var owners = $owners.each( function() {
                        var $owner = $(this),
                            owner_id = $owner.data("id");

                        if (owner_id > 0) {
                            result.push({
                                name: "owners[]",
                                value: owner_id
                            });
                        }
                    });
                }

                if (value === "all") {
                    result.push({
                        name: "vault_id",
                        value: "0"
                    });
                }

                var $employees = that.$wrapper.find(".js-employees-field");
                if ( $employees.length && ( $employees.attr("checked") === "checked") ) {
                    result.push({
                        name: "employees",
                        value: "on"
                    });
                }

                result.push({
                    name: "contact_id",
                    value: that.contact_id
                });
            }

            return result;
        }

        function showError(error) {
            var $error = $("<div class=\"errormsg\" />").text(error);
            that.$errorsPlace.append($error);
        }

        function removeErrors() {
            that.$errorsPlace.html("");
        }
    };

    CRMContactAccessDialog.prototype.initChangeOptions = function() {
        var that = this,
            active_class = "is-active",
            $activeOption = that.$optionsList.find(".c-option." + active_class);

        // Show/hide list of owners when user changes vault radio
        that.$optionsList.on("change", ".js-option-field", function() {
            var $field = $(this),
                $option = $field.closest(".c-option"),
                is_active = ( $field.attr("checked") === "checked" );

            if (is_active) {
                if ($activeOption.length) {
                    $activeOption.removeClass(active_class);
                }

                $activeOption = $option.addClass(active_class);
            }

            that.dialog.resize();
        });
    };

    CRMContactAccessDialog.prototype.initAutocomplete = function() {
        var that = this;

        var $field = that.$ownersList.find(".js-autocomplete");

        $field.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var $owner = findOwner(ui.item.id) || addOwner(ui.item);
                markOwner($owner);
                that.toggleButton(true);
                $field.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $field.on("focus", function(){
            $field.data("uiAutocomplete").search( $field.val() );
        });

        // Remove owner when user clicks delete icon
        that.$ownersList.on("click", ".js-delete-owner", function() {
            $(this).closest(".c-owner").remove();
            that.toggleButton(true);
        });

        function findOwner(user_id) {
            var $owner = that.$ownersList.find(".c-owner[data-id=\"" + user_id + "\"]");
            if ($owner.length) {
                return $owner;
            }
        }

        function addOwner(user) {
            var template = that.owner_template_html;

            template = template
                .replace(/%id%/g, user.id)
                .replace("%name%", escapeHtml(user.name))
                .replace("%photo_url%", user.photo_url);

            var $template = $(template);

            that.$ownersList.append($template);

            that.dialog.resize();

            return $template;
        }

        function markOwner($user) {
            var active_class = "highlighted";

            $user.addClass(active_class);
            setTimeout(function() {
                $user.removeClass(active_class);
            }, 2000);
        }

        function escapeHtml(string) {
            return $('<div></div>').text(string).html();
        }
    };

    CRMContactAccessDialog.prototype.toggleButton = function(is_changed) {
        var that = this,
            $button = that.$submitButton;

        if (is_changed) {
            $button.removeClass("green").addClass("yellow");
        } else {
            $button.removeClass("yellow").addClass("green");
        }
    };

    CRMContactAccessDialog.prototype.initVaultToggle = function() {
        var that = this,
            $wrapper = that.$vaultsToggle,
            $visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-field"),
            $menu = $wrapper.find(".menu-v");

        $menu.on("click", "a", function () {
            var $link = $(this);
            $visibleLink.find(".js-text").html($link.html());

            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            $menu.hide();
            setTimeout( function() {
                $menu.removeAttr("style");
            }, 200);

            var id = $link.data("id");
            $field.val(id);
        });

        $.crm.renderSVG($wrapper);
    };

    return CRMContactAccessDialog;

})(jQuery);

/**
 * initialized in ContactCreateSettings.html */
var CRMContactCreateSettings = ( function($) {

    CRMContactCreateSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactCreateSettings.prototype.initClass = function() {
        var that = this;

        that.initSave();
    };

    CRMContactCreateSettings.prototype.initSave = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index, item) {
                result.data.push(item);
            });

            return result;
        }

        function showErrors(errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            console.log( errors );
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=contact&action=createSettingsSave";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.close();
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }

    };

    return CRMContactCreateSettings;

})(jQuery);

/**
 * initialized in contact/ContactAddEmployee.html */
var CRMContactAddEmployeeDialog = ( function($) {

    CRMContactAddEmployeeDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.contact_id = options["contact_id"];
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactAddEmployeeDialog.prototype.initClass = function() {
        var that = this;

        that.initAddEmployee();
    };

    CRMContactAddEmployeeDialog.prototype.initAddEmployee = function() {
        var that = this,
            $form = that.$wrapper.find("#c-contact-add-employee-form"),
            $errorsPlace = that.$wrapper.find(".js-errors-place"),
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();
            if (formData.errors.length) {
                showErrors(formData.errors);

            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                data: $form.serializeArray(),
                errors: []
            };

            return result;
        }

        function showErrors(errors) {
            var is_object = (!errors[0]);

            if (is_object) {

                var keys = Object.keys(errors);
                $.each(keys, function(index, item) {
                    var text = errors[item];
                    render(text);
                });

            } else {

                $.each(errors, function(index, item) {
                    var text = item.value;
                    render(text);
                });
            }

            function render(text) {
                var $text = $("<div class=\"line errormsg\" />").html(text);
                $errorsPlace.append($text);

                that.$form.one("submit", function() {
                    $text.remove();
                });
            }
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=contact&action=addEmployeeSave";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                        that.dialog.close();

                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMContactAddEmployeeDialog;

})(jQuery);

/**
 * initialized in contact/ContactUpdateDialog.html */
var CRMContactUpdateDialog = ( function($) {

    CRMContactUpdateDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$dialog = that.$wrapper.data("dialog");
        that.$footer = that.$wrapper.find(".js-dialog-footer");
        that.$submitButton = that.$wrapper.find(".js-submit-button");

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactUpdateDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initAutocomplete();
        //
        that.initSubmit();
    };



    CRMContactUpdateDialog.prototype.initAutocomplete = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-autocomplete-wrapper"),
            $autocomplete = $wrapper.find(".js-autocomplete"),
            $idField = $wrapper.find(".js-field"),
            $selectHtmlField = that.$wrapper.find(".js-field-autocomplete");

        function setContactData(contact) {
            const name = escapeHtml(contact.name);
            return contactHtml = `        
            <div class="c-column c-column-image">
                <a class="flexbox middle" href="${$.crm.app_url}${contact.link}" target="_top" data-link="top" >
                    <img src="${contact.photo_url}" alt="${name}">
                </a>
            </div>
            <div class="c-column middle nowrap">
                <a href="${$.crm.app_url}${contact.link}" target="_top" data-link="top" title="${name}">${name}</a>
            </div>`
        }

        const escapeHtml = unsafe => {
            return unsafe
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        };

        $autocomplete
            .autocomplete({
                appendTo: $wrapper,
                source: $.crm.app_url + "?module=autocomplete",
                minLength: 2,
                delay: 300,
                html: true,
                focus: function () {
                    return false;
                },
                select: function (event, ui) {
                    var text = $("<div />").text(ui.item.name).text();
                    $autocomplete.val(text).blur();
                    $idField.val(ui.item.id).trigger("change");
                    $selectHtmlField.html(setContactData(ui.item));
                    return false;
                }
            })
            .data("ui-autocomplete")._renderItem = function (ul, item) {
                ul.css("max-height", "50vh");
                ul.css("overflow-y", "scroll");
                return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.label + "</div>").appendTo(ul);
            };

        $idField.on("change", function() {
            that.$submitButton.attr("disabled", false);
        });
    };

    CRMContactUpdateDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            $errorPlace = that.$wrapper.find(".js-errors-place"),
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index, item) {
                result.data.push(item);
            });

            return result;
        }

        function showErrors(errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = $form.find("[name=\"" + name + "\"]"),
                    $text = $("<span />").addClass("errormsg").text(text);

                if ($field.length) {
                    if (!$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    }
                } else {
                    $errorPlace.append($text);
                    $form.one("submit", function() {
                        $text.remove();
                    });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=contact&action=update";
                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.$wrapper.find(".js-field-autocomplete").data('success', true);
                       that.$dialog.close();
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }

    };

    return CRMContactUpdateDialog;

})(jQuery);

/** Controller for Add company contact Dialog
 *  initialized in /contact/ContactAddCompanyContactDialog.html */
var CRMContactAddCompanyContactDialog = ( function($) {

    CRMContactAddCompanyContactDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$company_value = that.$wrapper.find(".js-company-value");
        that.$submit = that.$wrapper.find(".js-submit");
        that.$footer = that.$wrapper.find(".js-dialog-footer");

        that.$company_id_input = that.$wrapper.find('.js-company-id');
        that.$company_name_input = that.$wrapper.find('.js-autocomplete-company');

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // INIT
        that.initClass();
    };

    CRMContactAddCompanyContactDialog.prototype.initClass = function() {
        var that = this;

        that.$form.on('click', '.js-company-item', function () {
            that.$submit.prop('disabled', false);
        });
        //
        that.initAutoComplete();
        //
        that.initSubmit();
    };

    CRMContactAddCompanyContactDialog.prototype.initAutoComplete = function () {
        var that = this,
            $selected_company = that.$wrapper.find('.js-selected-company'),
            $change_company = that.$wrapper.find('.js-change-company'),
            $label_new_company = that.$wrapper.find('.js-label-new-company');

        // Init autocomplete
        that.$company_name_input.autocomplete({
            source: function( request, response ) {
                var href = "?module=autocomplete&type=company";
                $.post(href, request, function(data) {
                    if ($.isEmptyObject(data) && request.term) {
                        $label_new_company.css({'color':'#999'});
                    } else {
                        $label_new_company.css({'color':'#FFF'});
                    }
                    response(data);
                }, "json");
            },
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                that.$company_id_input.val(ui.item.id);
                $selected_company.html('<i class="icon userpic size-20" style="background-image: url('+ ui.item.photo_url +');"></i>').append($("<span />").text(ui.item.name)).removeClass('hidden');
                that.$company_name_input.addClass('hidden');
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };
        that.$company_name_input.on("focus", function(){
            that.$company_name_input.data("uiAutocomplete").search( that.company_name );
        });

        $change_company.on('click', function () {
            that.$company_id_input.val(0);
            $selected_company.html('').addClass('hidden');
            that.$company_name_input.removeClass('hidden').focus();
        });
    };

    CRMContactAddCompanyContactDialog.prototype.initSubmit = function() {
        var that = this;

        that.$form.on("submit", function(e) {
            e.preventDefault();
            var id = that.$company_id_input.val(),
                name = that.$company_name_input.val();

            if(id == 0 && !$.trim(name)) {
                that.$company_value.addClass('shake animated');
                that.$company_name_input.focus();
                setTimeout(function(){
                    that.$company_value.removeClass('shake animated');
                    that.$company_name_input.focus();
                },500);
                return false;
            }

            submit();
        });

        function submit() {
            var $loading = $('<span class="icon loading"><i class="fas fa-spinner wa-animation-spin"></i></span>'),
                href = $.crm.app_url + "?module=contact&action=addCompanyContactSave",
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

    return CRMContactAddCompanyContactDialog;

})(jQuery);
