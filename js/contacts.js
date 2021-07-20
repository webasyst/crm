var CRMContactsPage = (function ($) {

    CRMContactsPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$content = that.$wrapper.find("#js-content-block");

        // VARS
        that.is_admin = options["is_admin"];
        that.context = ( options["context"] || {} );
        that.view = options["view"] || "thumbs";
        that.total_count = options.total_count || 0;
        that.page_count = options.page_count || 0;
        that.locales = options["locales"];

        // DYNAMIC VARS
        /**
         * @type CRMContactsSidebar
         */
        that.sidebar = that.$wrapper.find(".c-contacts-sidebar").data('sidebar_controller');

        // INIT
        that.initClass();
    };

    CRMContactsPage.prototype.initClass = function () {
        var that = this;

        //
        that.initElastic();
        //
        if (that.context.type === 'segment') {
            that.initEditableName($.crm.app_url + "?module=contactSegment&action=save&is_rename=1", that.context);
        }

        if (that.view === 'list') {
            that.initContactsColumnsSection();
            that.initContactsTable();
        }

        if (that.view === 'thumbs') {
            that.initContactsThumbs();
        }

        that.initOperationsMenu();

        if (that.context.type === 'segment') {
            that.initSegmentContext();
        } else if (that.context.type === 'search') {
            that.initCreateFilterLink();
        }

        that.$wrapper.on("click", ".pager a", function() {
            $(this).replaceWith("<i class=\"icon16 loading\"></i>");
            $(document).one("wa_loaded", function() {
                $("html, body").scrollTop(0);
            });
        });

        //
        that.initTableSlider();
        //
        that.initHoverNames();
        //
        that.initDroppable();
    };

    CRMContactsPage.prototype.initEditableName = function (save_url, context) {
        var $name = this.$wrapper.find(".js-name-editable").first();
        if ($name.length <= 0) {
            return;
        }

        var crm_contact = this,
            $loading = false,
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
                            id: context.info.id,
                            name: text
                        };

                    that.$field.attr("disabled", true);

                    if (!$loading) {
                        $loading = $('<i class="icon16 loading"></i>')
                            .css("margin", "0 0 0 4px")
                            .insertAfter( that.$field );
                    }

                    xhr = $.post(href, data, function(r) {
                        if (r && r.data && r.data.segment) {
                            that.text = r.data.segment.name;
                            that.$wrapper.text(r.data.segment.name);
                            crm_contact.sidebar.updateItem(context, {
                                name: r.data.segment.name
                            });
                        }
                    }, "json").always( function() {
                        that.$field.attr("disabled", false);
                        that.toggle("hide");
                        if ($loading.length) {
                            $loading.remove();
                            $loading = false;
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

    CRMContactsPage.prototype.initElastic = function() {
        var that = this;

        var $wrapper = that.$wrapper,
            $aside = that.$wrapper.find("#js-aside-block"),
            $content = that.$content;

        new CRMElasticBlock({
            $wrapper: $wrapper,
            $aside: $aside,
            $content: $content
        });

        new CRMElasticBlock({
            $wrapper: $wrapper,
            $content: $aside,
            $aside: $content
        });
    };

    CRMContactsPage.prototype.initContactsColumnsSection = function () {
        var that = this,
            $section = that.$wrapper.find(".js-contacts-columns-section"),
            $area = $section.find(".js-load-here"),
            area_html = $area.html(),
            active_class = "is-active",
            is_locked = false;

        $section.on("click", ".js-show-contact-columns-dialog", function(event) {
            var is_opened = $section.hasClass(active_class);
            if (is_opened) {
                toggleContent(false);
            } else {
                showDialog(event);
            }
        });

        $(document).on("click", watcher);

        $section.on("close", function() {
            toggleContent(false);
        });

        function watcher(event) {
            var is_exist = $.contains(document, $section[0]);
            if (is_exist) {
                var is_open = $section.hasClass(active_class),
                    is_target = $.contains($section[0], event.target);

                if (is_open && !is_target) {
                    toggleContent();
                }
            } else {
                $(document).off("click", watcher);
            }
        }

        function showDialog(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + '?module=contact&action=columns',
                    data = {};

                $area.html(area_html);
                toggleContent(true);

                $.post(href, data, function(html) {
                    $area.html(html);
                }).always( function() {
                    is_locked = false;
                });
            }
        }

        function toggleContent(show) {
            if (show) {
                $section.addClass(active_class);
            } else {
                $section.removeClass(active_class);
            }
        }
    };

    CRMContactsPage.prototype.initContactsTable = function () {
        var that = this;

        initColumnWidthToggle();

        function initColumnWidthToggle() {
            var $toggles = that.$wrapper.find(".js-width-toggle-wrapper");

            $toggles.each( function() {
                var $toggle = $(this),
                    full_column_id = $toggle.data("fullColumnId");

                $toggle.on("click", ".js-set-width", function() {
                    var width = $(this).data("id"),
                        url = $.crm.app_url + '?module=contactColumns&action=saveWidth',
                        data = {
                            width: width,
                            full_column_id: full_column_id
                        };
                    $.post(url, data, function() {
                        $.crm.content.reload();
                    });
                });
            })
        }
    };

    CRMContactsPage.prototype.initContactsThumbs = function () {
        var that = this;

    };

    CRMContactsPage.prototype.initOperationsMenu = function () {
        var that = this;
        new CRMContactsOperations({
            $wrapper: that.$wrapper,
            total_count: that.total_count,
            page_count: that.page_count,
            context: that.context,
            sidebar: that.sidebar,
            is_admin: that.is_admin,
            view: that.view
        });
    };

    CRMContactsPage.prototype.initSegmentContext = function () {
        var that = this,
            $wrapper = that.$wrapper;

        that.initSegmentLinks();

        that.sidebar.updateItem(that.context, {
            count: that.context.info.count
        });

        var just_created_category = $.crm.storage.get('crm/create/category') || {},
            timestamp = (+new Date()),
            diff = 30000;

        $.crm.storage.del('crm/create/category');

        // show add-contacts dialog for new segment list if needed
        if (that.context.info.type === 'category') {
            var $add_contacts_link = $wrapper.find('.js-segment-add-contacts');
            if (just_created_category.id == that.context.info.id && timestamp - just_created_category.timestamp <= diff) {
                $add_contacts_link.click();
            }
        }
    };

    CRMContactsPage.prototype.initSegmentLinks = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $list = $wrapper.find(".js-segment-actions-list"),
            is_locked = false,
            xhr = false;

        $list.on("click", ".js-show-edit-dialog", showEditDialog);

        $list.on("click", ".js-show-archive-dialog", archiveSegment);

        $list.on("click", ".js-show-delete-confirm", showDeleteConfirm);

        $list.on("click", "a", function() {
            $list.hide();
            setTimeout( function() {
                $list.removeAttr("style");
            }, 200);
        });

        if (that.context.info.type === 'category') {
            $list.on("click", ".js-segment-add-contacts", showAddContactsDialog);
        }

        //

        function showEditDialog(event) {
            event.preventDefault();

            if (xhr) {
                xhr.abort();
            }

            var href = $.crm.app_url + '?module=contactSegment&action=edit',
                data = {
                    id: that.context.info.id
            };

            xhr = $.post(href, data, function(html) {
                new CRMDialog({
                    html: html
                });
            }).always( function() {
                xhr = false;
            });
        }

        function showAddContactsDialog(event) {
            event.preventDefault();

            if (xhr) {
                xhr.abort();
            }

            var href = $.crm.app_url + '?module=contactSegment&action=addContacts',
                data = {
                    id: that.context.info.id
                };

            xhr = $.post(href, data, function(html) {
                new CRMDialog({
                    html: html
                });
            }).always( function() {
                xhr = false;
            });
        }

        function archiveSegment(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                var $li = $(this).closest('.crm-segment-archive-li'),
                    is_archived = $li.hasClass('archived');

                $li.removeClass('archived not-archived').addClass('loading');

                var href = $.crm.app_url + '?module=contactSegment&action=archive',
                    data = {
                        id: that.context.info.id,
                        archive: is_archived ? 0 : 1
                    };

                $.post(href, data, function() {
                    $li.addClass(is_archived ? 'not-archived' : 'archived');
                }).always( function() {
                    $li.removeClass('loading');
                    is_locked = false;
                });
            }
        }

        function showDeleteConfirm() {
            if (!is_locked) {
                is_locked = true;

                $.crm.confirm.show({
                    title: that.locales["delete_segment_title"],
                    text: that.locales["delete_segment_text"],
                    button: that.locales["delete_segment_button"],
                    onConfirm: deleteSegment
                });
            }

            function deleteSegment() {
                var href = $.crm.app_url + '?module=contactSegment&action=delete',
                    data = {
                        id: that.context.info.id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.load($.crm.app_url + 'contact/');
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMContactsPage.prototype.initCreateFilterLink = function () {
        var that = this,
            $wrapper = that.$wrapper;
        $wrapper.find('.crm-create-filter-link').click(function (e) {
            e.preventDefault();
            var $link = $(this),
                hash = $link.data('hash'),
                url = $.crm.app_url + '?module=contactSegment&action=edit',
                data = {
                    type: 'search',
                    hash: hash,
                    name: $wrapper.find('.crm-contact-list-name').text()
                };
            $.get(url, data)
                .done(function (html) {
                    new CRMDialog({
                        html: html
                    });
                });
        });
    };

    CRMContactsPage.prototype.initTableSlider = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-contacts-section");

        if (!$wrapper.length) {
            return false;
        }

        //DOM
        var $body = $wrapper.find(".js-slider-body"),
            $headerW = $wrapper.find(".js-header-table-wrapper"),
            $header = $headerW.find(".js-header-table"),
            $arrowsWrapper = $wrapper.find(".js-arrows-wrapper"),
            $arrowLeft = $arrowsWrapper.find(".js-arrow-left"),
            $arrowRight = $arrowsWrapper.find(".js-arrow-right");

        // VARS

        // DYNAMIC VARS
        var wrapper_width = $wrapper.outerWidth(),
            body_width = $body.outerWidth(),
            is_changed = false,
            left = 0;

        showArrows();

        initElastic();

        $arrowLeft.on("click", function() {
            move(false);
        });

        $arrowRight.on("click", function() {
            move(true);
        });

        $(window).on("resize", watcher);

        function watcher() {
            var is_exist = $.contains(document, $wrapper[0]);
            if (is_exist) {
                update();
            } else {
                $(window).off("resize", watcher);
            }
        }

        //

        function update() {
            wrapper_width = $wrapper.outerWidth();
            body_width = $body.outerWidth();

            showArrows();

            if (is_changed) {
                is_changed = false;
                left = 0;

                render(left);
            }
        }

        function showArrows() {
            var is_enabled = body_width > wrapper_width,
                active_class = "is-active";

            if (is_enabled) {
                var is_left_corner = !(left > 0),
                    is_right_corner = (left + wrapper_width >= body_width),
                    is_center = (left + wrapper_width < body_width);

                if (is_left_corner) {
                    $arrowLeft.removeClass(active_class);
                    $arrowRight.addClass(active_class);
                } else if (is_right_corner) {
                    $arrowLeft.addClass(active_class);
                    $arrowRight.removeClass(active_class);
                } else if (is_center) {
                    $arrowLeft.addClass(active_class);
                    $arrowRight.addClass(active_class);
                }
            } else {
                $arrowLeft.removeClass(active_class);
                $arrowRight.removeClass(active_class);
            }
        }

        function move(right) {
            var lift = wrapper_width/3,
                new_left = 0;

            if (right) {
                new_left = left + lift;
                if (new_left > body_width - wrapper_width) {
                    new_left = body_width - wrapper_width;
                }
            } else {
                new_left = left - lift;
                if (new_left < 0) {
                    new_left = 0;
                }
            }

            left = new_left;
            is_changed = true;

            render(new_left);

            showArrows();
        }

        function render(left) {
            var left_string = "-" + left + "px";

            $body.data("isLeftSet", (left > 0));

            $body.css({
                "-webkit-transform": "translate(" + left_string + ",0)",
                "transform": "translate(" + left_string + ",0)"
            });

            $header.css({
                "-webkit-transform": "translate(" + left_string + ",0)",
                "transform": "translate(" + left_string + ",0)"
            });
        }

        function initElastic() {

            // DOM
            var $window = $(window),
                $arrows = $arrowsWrapper;

            // VARS
            var wrapper_offset = $wrapper.offset(),
                fixed_class = "is-fixed";

            // DYNAMIC VARS
            var is_fixed = false;

            // INIT

            $window.on("scroll", scrollWatcher);

            function scrollWatcher() {
                var is_exist = $.contains(document, $arrows[0]);
                if (is_exist) {
                    onScroll( $window.scrollTop() );
                } else {
                    $window.off("scroll", scrollWatcher);
                }
            }

            function onScroll(scroll_top) {
                var set_fixed = ( scroll_top > wrapper_offset.top ),
                    is_content_more_than_window = ( that.$content.height() >  $window.height() );

                if (set_fixed && is_content_more_than_window) {

                    $headerW
                        .css({
                            left: wrapper_offset.left
                        })
                        .width( $wrapper.outerWidth() )
                        .addClass(fixed_class);
                    $arrows.addClass(fixed_class);

                    is_fixed = true;

                } else {

                    $headerW
                        .removeAttr("style")
                        .removeClass(fixed_class);
                    $arrows.removeClass(fixed_class);

                    is_fixed = false;
                }
            }
        }
    };

    CRMContactsPage.prototype.initHoverNames = function() {
        var that = this,
            $section = that.$wrapper.find(".js-contacts-section");

        if (!$section.length) {
            return false;
        }

        var $hint = $section.find(".c-hint-wrapper"),
            $table = that.$wrapper.find(".js-contacts-table");

        var table_offset = $table.offset(),
            show_class = "is-shown",
            timeout = 0;

        $table.on("mouseenter", "tr", function() {
            showHint( $(this) );
        });

        $table.on("mouseleave", "tr", function() {
            hideHint();
        });

        $hint.on("mouseenter", function () {
            clearTimeout(timeout);
        });

        $hint.on("mouseleave", hideHint);

        function showHint( $tr ) {
            var use_hint = $table.data("isLeftSet");

            if (!use_hint) {
                return false;
            }

            clearTimeout(timeout);

            var name = $tr.find(".c-name").html(),
                offset = $tr.find("td:first").offset(),
                lift = 10;

            $hint.html(name)
                .css({
                    top: offset.top + lift - (table_offset.top - 28),
                    left: lift
                })
                .addClass(show_class);
        }

        function hideHint() {
            timeout = setTimeout( function () {
                $hint.removeClass(show_class).html("");
            }, 500);
        }
    };

    CRMContactsPage.prototype.initDroppable = function() {
        var that = this,
            $sidebar = that.$wrapper.find(".js-sidebar"),
            $dropZones = $sidebar.find(".js-segment-droparea"),
            hover_class = "is-hovered",
            xhr = false;

        $dropZones.each( function() {
            var $drop = $(this);
            $drop.droppable({
                tolerance: "pointer",
                hoverClass: hover_class,
                over: function(event, ui) {
                    var is_drag_item = ui.draggable.hasClass("ui-draggable");
                    if (!is_drag_item) {
                        $drop.removeClass(hover_class);
                    }
                },
                drop: function(event, ui) {
                    var segment_id = $(this).data('id'),
                        user_ids = [];

                    var id = ui.helper.data("user-id"),
                        ids = ui.helper.data("user-ids");

                    id = (typeof id !== "undefined" ? id+"" : null);
                    ids = (typeof ids !== "undefined" ? ids+"" : null);

                    if (id) {
                        user_ids.push(id);

                    } else if (ids) {
                        if (ids.indexOf(",") > 0) {
                            user_ids = ids.split(",");
                        } else {
                            user_ids.push(ids);
                        }
                    }

                    if (segment_id > 0 && user_ids.length > 0) {
                        addUserToSegment(segment_id, user_ids);
                    }
                }
            });
        });

        function addUserToSegment(segment_id, user_ids) {
            var url = $.crm.app_url + '?module=contactSegment&action=addContactsSave',
                data = { id: segment_id, contact_id: user_ids };
            $.post(url, data, 'json')
                .done(function (r) {
                    if (r.status !== 'ok' || !r.data.segment) {
                        return;
                    }
                    that.sidebar.updateItem({ type: 'segment', info: { id: segment_id } }, {
                        count: r.data.segment.count
                    });
                });
        }
    };

    return CRMContactsPage;

})(jQuery);

var CRMContactsColumnsDialog = ( function($) {

    CRMContactsColumnsDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$activeList = that.$wrapper.find(".js-active-list");
        that.$unactiveList = that.$wrapper.find(".js-unactive-list");

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsColumnsDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initSubmit();
        //
        that.initSort();
        //
        that.initComboList();

        that.$wrapper.on("click", ".js-close-dialog", function() {
            $(this).closest(".js-contacts-columns-section").trigger("close");
        });
    };

    CRMContactsColumnsDialog.prototype.initSubmit = function() {
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

                var href = $.crm.app_url + "?module=contactColumns&action=save",
                    data = getData();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $.crm.content.reload();
                    } else {
                        alert("Error");
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }

        function getData() {
            var data = $form.serializeArray();

            $.each(data, function(index, item) {
                item.value = index + 1;
            });

            return data;
        }
    };

    CRMContactsColumnsDialog.prototype.initSort = function() {
        var that = this;

        that.$activeList.sortable({
            distance: 10,
            handle: ".c-toggle",
            helper: "clone",
            items: ".js-item",
            axis: "y"
        });
    };

    CRMContactsColumnsDialog.prototype.initComboList = function() {
        var that = this;

        that.$activeList.on("change", ".c-field", function() {
            var $field = $(this),
                $item = $field.closest(".js-item"),
                is_checked = ( $field.attr("checked") === "checked" );

            move($item, is_checked);
        });

        that.$unactiveList.on("change", ".c-field", function() {
            var $field = $(this),
                $item = $field.closest(".js-item"),
                is_checked = ( $field.attr("checked") === "checked" );

            move($item, is_checked);
        });

        function move($item, active) {
            if (active) {
                $item.appendTo(that.$activeList);
            } else {
                $item.appendTo(that.$unactiveList);
            }
        }
    };

    return CRMContactsColumnsDialog;

})(jQuery);

var CRMThumbContact = ( function($) {

    CRMThumbContact = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.user_id = that.$wrapper.data("user-id");
        that.can_be_drag = options["can_be_drag"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMThumbContact.prototype.initClass = function() {
        var that = this;

        if (that.can_be_drag) {
            that.initDraggable();
        }
    };

    CRMThumbContact.prototype.initDraggable = function() {
        var that = this,
            $dropZone = $("#c-contacts-sidebar .js-segment-droparea"),
            drop_class = "c-drop-here";

        that.$wrapper.draggable({
            handle: ".js-userpic",
            delay: 200,
            cursorAt: { top: 0, left: 0 },

            helper: function () {
                var $items = $("#c-users-list .js-move-user.is-active"),
                    ids = $items.map(function () {
                        return $(this).data("user-id");
                    }).toArray();

                if (ids.length > 1) {
                    return '<div id="c-users-helper" data-user-ids="' + ids.join(",") + '"><span class="indicator red">' + ids.length + '</span></div>'
                }

                return $(this).clone().addClass('is-clone');

            },

            start: function() {
                if ($dropZone.length) {
                    $dropZone.addClass(drop_class);
                }
            },
            stop: function(event, ui) {
                var $helper = ui.helper,
                    $clone = $helper.clone(),
                    time = 300;

                $clone
                    .insertAfter( $helper )
                    .fadeOut( time * .9 );

                setTimeout( function() {
                    $clone.remove()
                }, time);

                if ($dropZone.length) {
                    $dropZone.removeClass(drop_class);
                }
            }
        });
    };

    return CRMThumbContact;

})(jQuery);

var CRMTableContact = ( function($) {

    CRMTableContact = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.user_id = that.$wrapper.data("user-id");
        that.can_be_drag = options["can_be_drag"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTableContact.prototype.initClass = function() {
        var that = this;

        if (that.can_be_drag) {
            that.initDraggable();
        }
    };

    CRMTableContact.prototype.initDraggable = function() {
        var that = this,
            $dropZone = $("#c-contacts-sidebar .js-segment-droparea"),
            $tableUsers = $(".js-contacts-table .js-move-user"),
            drop_class = "c-drop-here";

        that.$wrapper.draggable({
            helper: function () {
                var ids = [],
                    result = "";

                $tableUsers.filter(".is-active").each( function () {
                    var id = $(this).data("user-id");
                    if (id) {
                        ids.push(id);
                    }
                });

                if (ids.length <= 1) {
                    ids.push(that.user_id);
                    var userpic = that.$wrapper.find(".userpic")[0].outerHTML;
                    result = '<div id="c-users-helper" data-user-ids="' + ids.join(",") + '">' + userpic + '</div>';
                } else {
                    result = '<div id="c-users-helper" data-user-ids="' + ids.join(",") + '"><span class="indicator red">' + ids.length + '</span></div>'
                }

                return result;
            },
            appendTo: "body",
            delay: 200,
            cursorAt: { top: 0, left: 0 },
            start: function(event, ui) {
                ui.helper.addClass("is-clone");

                if ($dropZone.length) {
                    $dropZone.addClass(drop_class);
                }
            },
            stop: function(event, ui) {
                var $helper = ui.helper,
                    $clone = $helper.clone(),
                    time = 300;

                $clone
                    .insertAfter( $helper )
                    .fadeOut( time * .9 );

                setTimeout( function() {
                    $clone.remove()
                }, time);

                if ($dropZone.length) {
                    $dropZone.removeClass(drop_class);
                }
            }
        });
    };

    return CRMTableContact;

})(jQuery);
