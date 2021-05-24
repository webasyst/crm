var CRMContactsOperations = (function ($) {

    CRMContactsOperations = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$bar = that.$wrapper.find(".js-operations-wrapper");
        that.$list = that.$wrapper.find('.js-contacts-list');
        that.$list_header = that.$list.find('.c-list-header');
        that.$checkbox_all = that.$wrapper.find('.js-checkbox-all');
        that.$content = that.$wrapper.find("#js-content-block");
        that.$selected_link = that.$wrapper.find('.js-selected-contacts');
        that.$selected_count = that.$selected_link.find('.count');
        that.$remove_from_selected_list = that.$bar.find('[data-id="remove_from_selected_list"]');

        // VARS
        that.page = options.page || 1;
        that.limit = options.limit || 30;
        that.total_items_at_page_count = that.getTotalItemsAtPage() || 0;
        that.total_count = options.total_count || 0;
        that.page_count = options.page_count || 0;
        that.context = options.context || {};
        that.selected_page = $(document).find('#contact_selected_page').length;

        that.is_category_segment =
            that.context.type === 'segment' &&
            that.context.info &&
            that.context.info.type === 'category';

        that.view = options.view || '';

        /**
         * @type CRMContactsSidebar
         */
        that.sidebar = options.sidebar || null;

        // DYNAMIC VARS
        that.checked_count = 0;
        that.is_shown = that.checked_count > 0;
        that.is_checked_all = false;

        // INIT
        that.initClass();
    };

    CRMContactsOperations.prototype.initClass = function () {
        var that = this;
        //
        that.initCheckboxAll();
        //
        that.initOperations();
        //
        that.initItemCheckbox();
        //
        that.initElasticHeader();

        if (that.selected_page > 0) {
            that.$remove_from_selected_list.show();
        }
    };

    CRMContactsOperations.prototype.initCheckboxAll = function () {
        var that = this;
        that.$checkbox_all.click(function () {
            var $el = $(this),
                checked = $el.is(':checked');

            if(that.checked_count > 0) {
                that.unselectAll();
            }else{
                $el.prop('checked', checked);
                if (checked) {
                    that.selectAll();
                } else {
                    that.unselectAll();
                }
            }

            that.formBar();
            that.showOrHideBar();
        });
    };

    CRMContactsOperations.prototype.initOperations = function () {
        var that = this;
        that.$bar.on('click', '.crm-operation-link', function (e) {
            e.preventDefault();
            var $link = $(this),
                op = $link.data('id');
            switch (op) {
                case 'add_to_segments':
                    that.addToSegmentsOperation();
                    break;
                case 'export':
                    that.exportOperation();
                    break;
                case 'merge':
                    that.mergeOperation();
                    break;
                case 'delete':
                    that.deleteOperation();
                    break;
                case 'exclude_from_list':
                    that.excludeFromListOperation();
                    break;
                case 'assign_tags':
                    that.assignTagsOperation();
                    break;
                case 'assign_responsible':
                    that.assignResponsibleOperation();
                    break;
                case 'remove_from_selected_list':
                    that.removeFromSelectedListOperation();
                    break;
                default:
                    break;
            }
        });
    };

    CRMContactsOperations.prototype.showBar = function () {
        var that = this;
        if (!that.is_shown) {
            that.$bar.addClass('is-shown');
            that.$list_header.removeClass('is-shown');
            that.is_shown = true;
        }
    };

    CRMContactsOperations.prototype.hideBar = function () {
        var that = this;
        if (that.is_shown) {
            that.$bar.removeClass('is-shown');
            that.$list_header.addClass('is-shown');
            that.is_shown = false;
        }
    };

    CRMContactsOperations.prototype.updateBarCount = function () {
        var that = this;
        that.$bar.find('.crm-count').text('(' + that.checked_count + ')');
    };

    CRMContactsOperations.prototype.showOrHideBar = function () {
        var that = this;
        if (that.checked_count > 0) {
            that.showBar();
            that.updateBarCount();
        } else {
            that.hideBar();
        }
    };

    CRMContactsOperations.prototype.getSelectedContactList = function () {
        var selected_contacts = sessionStorage.getItem('selected_contacts');

        if (selected_contacts) {
            return JSON.parse(selected_contacts);
        }
        return null;
    };

    CRMContactsOperations.prototype.setSelectedContacts = function () {
        var that = this;
        if (!that.getSelectedContactList()) {
            sessionStorage.setItem("selected_contacts", "[]");
        }

        if(that.selected_page > 0) {
            return;
        }

        var selected_ids = that.getSelectedContactList(),
            new_ids = that.getSelectedContactIds();

        $.each(new_ids, function (i, value) {
            if ($.inArray(value, selected_ids) < 0) {
                selected_ids[selected_ids.length] = value;
            }
        });

        sessionStorage.setItem('selected_contacts', JSON.stringify(selected_ids));

        that.showOrHideSelectedLink();
    };

    CRMContactsOperations.prototype.delSelectedContact = function (deleted_id) {
        var that = this,
            selected_ids = that.getSelectedContactList(),
            index_id = selected_ids.indexOf(deleted_id);

        selected_ids.splice(index_id, 1);

        sessionStorage.setItem('selected_contacts', JSON.stringify(selected_ids));

        that.showOrHideSelectedLink();
    };

    // Update counter for Selected list in contacts sidebar
    CRMContactsOperations.prototype.updateSelectedCount = function () {
        var that = this,
            selected_count = that.getSelectedContactList().length;
        that.$selected_count.text(selected_count);
    };

    CRMContactsOperations.prototype.showOrHideSelectedLink = function () {
        var that = this;
        that.updateSelectedCount();
        var selected_contacts = that.getSelectedContactList();
        if (selected_contacts.length) {
            that.showSelectedLink();
        } else {
            that.hideSelectedLink();
        }
    };

    CRMContactsOperations.prototype.showSelectedLink = function () {
        var that = this;
        that.$selected_link.removeClass('js-selected-contacts-hidden');
    };

    CRMContactsOperations.prototype.hideSelectedLink = function () {
        var that = this;
        that.$selected_link.addClass('js-selected-contacts-hidden');
    };

    CRMContactsOperations.prototype.getSelectedContactIds = function () {
        var that = this;
        return that.$list.find('.c-checkbox :checkbox:checked').map(function () {
            var $el = $(this);
            return $el.val();
        }).toArray();
    };

    CRMContactsOperations.prototype.getTotalItemsAtPage = function () {
        var that = this;
        if (that.page * that.limit > that.total_count) {
            return that.total_count % that.limit
        }
        return that.limit;
    };

    CRMContactsOperations.prototype.formBar = function () {
        var that = this,
            $merge_li = that.$bar.find('.crm-operation-li[data-id="merge"]'),
            $delete_li = that.$bar.find('.crm-operation-li[data-id="delete"]'),
            $assign_responsible_li = that.$bar.find('.crm-operation-li[data-id="assign_responsible"]'),
            $exclude_li = that.$bar.find('.crm-operation-li[data-id="exclude_from_list"]');

        if (that.checked_count <= 1 || that.checked_count > that.page_count) {
            $merge_li.hide();
        } else {
            $merge_li.show();
        }

        if (that.checked_count > that.page_count) {
            $delete_li.hide();
        } else {
            $delete_li.show();
        }

        if (that.checked_count > that.page_count) {
            $assign_responsible_li.hide();
        } else {
            $assign_responsible_li.show();
        }

        if (that.is_category_segment && that.context.can_edit) {
            $exclude_li.show();
        } else {
            $exclude_li.hide();
        }
    };

    CRMContactsOperations.prototype.initItemCheckbox = function () {
        var that = this,
            active_class = "is-active";

        // change checkbox, take into account $checkbox_all logic
        that.$list.on("change", ".js-checkbox", function (event, checked) {
            var $checkbox = $(this),
                $contact = $checkbox.closest('.js-contact-wrapper');

            if (typeof checked !== 'undefined') {
                $checkbox.attr('checked', checked);
            }

            handleContactItem($contact);

            if(that.checked_count > 0) {
                if(that.checked_count < that.limit && that.checked_count !== that.total_items_at_page_count) {
                    that.$checkbox_all.prop('indeterminate', true);
                }else{
                    that.$checkbox_all.prop('indeterminate', false).prop('checked', true);
                }
            }else{
                that.$checkbox_all.prop('indeterminate', false).prop('checked', false);
            }
        });

        // click on contact item
        that.$list.on("click", ".js-contact-wrapper", function(event) {
            var $target = $(event.target),
                is_link = !!($target.is('a') || $target.closest("a").length),
                is_checkbox = $target.is(':checkbox');

            // ignore link click
            if (is_link) {
                return;
            }

            var $contact = $(this),
                $checkbox = null;
            if (!is_checkbox) {
                $checkbox = $contact.find('.js-checkbox');
                $checkbox.trigger("change", [!$checkbox.is(':checked')]);
            }

        });

        // shift-select on contact items
        that.$list.shiftSelectable({
            selector: '.js-contact-wrapper',
            behavior_type: that.view === 'thumbs' ? 'mixed' : 'vertical',
            onSelect: function ($contact, event) {
                var $target = $(event.target),
                    is_link = !!($target.is('a') || $target.closest("a").length),
                    is_first = event.extra.is_first,
                    is_last = event.extra.is_last;
                if (is_link || is_first || is_last) {
                    return;
                }
                $contact.find('.js-checkbox').attr('checked', true);
                handleContactItem($contact);
            }
        });

        function handleContactItem($contact) {

            var $checkbox = $contact.find('.js-checkbox'),
                checked = $checkbox.is(':checked'),
                checkbox_id = $checkbox.val();

            highlightContactItem($contact, checked);
            updateCheckedCounter(checked);

            that.formBar();
            that.showOrHideBar();
            that.showOrHideSelectedLink();

            if (that.selected_page > 0) {
                return;
            }

            if (checked) {
                that.setSelectedContacts();
            } else {
                that.delSelectedContact(checkbox_id);
            }
        }

        function highlightContactItem($contact, checked) {
            if (checked) {
                $contact.addClass(active_class);
            } else {
                $contact.removeClass(active_class);
            }
        }

        function updateCheckedCounter(checked) {
            if (that.is_checked_all) {
                that.is_checked_all = false;
                that.checked_count = that.$list.find('.c-checkbox :checkbox:checked').length;
            } else {
                if (checked) {
                    that.checked_count += 1;
                } else {
                    that.checked_count -= 1;
                }
            }
        }
    };

    CRMContactsOperations.prototype.removeFromSelectedListOperation = function () {
        var that = this,
        removedList = that.$list.find('.c-checkbox :checkbox:checked');

        $.each(removedList, function () {
            that.delSelectedContact(this.value);
            $(this).parents('.js-contact-wrapper').remove();
        });

        that.$checkbox_all.prop('checked', false);
        that.checked_count = 0;
        that.is_checked_all = false;
        that.showOrHideBar();

        if (!that.getSelectedContactList().length) {
            var $operations_bar = that.$wrapper.find('.js-header-table-wrapper'),
                $no_contacts = that.$wrapper.find('.c-no-contacts');

            $no_contacts.removeClass('hidden');
            $operations_bar.remove();
        }
    };

    CRMContactsOperations.prototype.selectAll = function () {
        var that = this;
        that.$list.find('.c-checkbox :checkbox').prop('checked', true).trigger("change", [true]);
        that.checked_count = that.total_count;
        that.is_checked_all = true;
        that.setSelectedContacts();
    };

    CRMContactsOperations.prototype.unselectAll = function () {
        var that = this;
        that.$list.find('.c-checkbox :checkbox').prop('checked', false).trigger("change", [false]);
        that.$checkbox_all.prop('checked', false);
        that.checked_count = 0;
        that.is_checked_all = false;
    };

    CRMContactsOperations.prototype.getSelectedContext = function () {
        var that = this,
            context = $.extend({
                total_count: that.total_count,
                page_count: that.page_count,
                checked_count: that.checked_count,
                is_checked_all: that.is_checked_all ? 1 : 0,
                contact_ids: null
            }, that.context);
        if (!context.is_checked_all) {
            context.contact_ids = that.getSelectedContactIds();
        }
        return context;
    };

    CRMContactsOperations.prototype.addToSegmentsOperation = function () {
        var that = this,
            url = $.crm.app_url + '?module=contactOperation&action=addToSegments';
        $.get(url, function (html) {
            new CRMDialog({
                html: html,
                onOpen: function ($dialog) {
                    new CRMContactsOperationAddToSegments({
                        '$wrapper': $dialog,
                        'context': that.getSelectedContext(),
                        'sidebar': that.sidebar
                    });
                }
            });
        });
    };

    CRMContactsOperations.prototype.exportOperation = function () {
        var that = this,
            url = $.crm.app_url + '?module=contactOperation&action=export',
            context = that.getSelectedContext(),
            count = context.checked_count;
        $.get(url, { checked_count: count } , function (html) {
            var exportOperation = null;
            new CRMDialog({
                html: html,
                esc: false,
                onOpen: function ($dialog) {
                    exportOperation = new CRMContactsOperationExport({
                        '$wrapper': $dialog,
                        'context': that.getSelectedContext()
                    });
                },
                onClose: function () {
                    exportOperation && exportOperation.cancel();
                }
            });
        });
    };

    CRMContactsOperations.prototype.mergeOperation = function () {
        var that = this;
        if (that.checked_count <= 1 || that.checked_count > that.page_count) {
            return;
        }
        var contact_ids = that.getSelectedContactIds(),
            ids = contact_ids.join(',');
        $.crm.content.load($.crm.app_url + 'contact/merge/?ids=' + ids);
    };

    CRMContactsOperations.prototype.deleteOperation = function () {
        var that = this;
        if (that.checked_count > that.page_count) {
            return;
        }
        var url = $.crm.app_url + '?module=contactOperation&action=delete',
            $wrapper = that.$wrapper,
            context = that.getSelectedContext(),
            $dialog_template = $wrapper.find('.crm-contact-operation-delete-checking'),
            $dialog = $dialog_template.clone();

        new CRMDialog({
            html: $dialog.show(),
            onOpen: function ($dialog) {
                var dialog = this;
                $dialog.find('.crm-cancel').click(function () {
                    dialog.close();
                });

                $.get(url, context, function (html) {
                    new CRMDialog({
                        html: html,
                        onOpen: function () {
                            dialog.close();
                        }
                    });
                })
            }
        });

    };

    CRMContactsOperations.prototype.excludeFromListOperation = function () {
        var that = this;
        if (!that.is_category_segment) {
            return;
        }
        var url = $.crm.app_url + '?module=contactOperation&action=excludeFromSegment',
            context = that.getSelectedContext(),
            segment = that.context.info,
            count = context.checked_count;
        $.get(url, { id: segment.id, checked_count: count }, function (html) {
            new CRMDialog({
                html: html,
                onOpen: function ($dialog) {
                    new CRMContactsOperationExcludeFromSegment({
                        '$wrapper': $dialog,
                        'segment': that.context.info,
                        'context': that.getSelectedContext(),
                        'sidebar': that.sidebar
                    })
                }
            });
        });
    };

    CRMContactsOperations.prototype.assignTagsOperation = function () {
        var that = this,
            url = $.crm.app_url + '?module=contactOperation&action=assignTags',
            data = $.extend({}, that.getSelectedContext(), true);
        if (!data.is_checked_all && data.checked_count == 1) {
            url += '&is_assign=1';
        }
        $.post(url, data, function (html) {
            new CRMDialog({
                html: html
            });
        });
    };

    CRMContactsOperations.prototype.assignResponsibleOperation = function () {
        var that = this,
            url = $.crm.app_url + '?module=contactOperation&action=assignResponsible',
            data = $.extend({}, that.getSelectedContext(), true);
        $.post(url, data, function (html) {
            new CRMDialog({
                html: html
            });
        });
    };

    CRMContactsOperations.prototype.initElasticHeader = function() {
        var that = this;

        // DOM
        var $window = $(window),
            $wrapper = that.$wrapper.find(".js-contacts-section"),
            $header = that.$bar;

        // VARS
        var wrapper_offset = $wrapper.offset(),
            header_w = $header.outerWidth(),
            fixed_class = "is-fixed";

        // DYNAMIC VARS
        var is_fixed = false;

        // INIT

        $window
            .on("scroll", scrollWatcher)
            .on("resize", resizeWatcher);

        function scrollWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onScroll( $window.scrollTop() );
            } else {
                $window.off("scroll", scrollWatcher);
            }
        }

        function resizeWatcher() {
            var is_exist = $.contains(document, $header[0]);
            if (is_exist) {
                onResize();
            } else {
                $window.off("resize", resizeWatcher);
            }
        }

        function onScroll(scroll_top) {
            var set_fixed = ( scroll_top > wrapper_offset.top ),
                is_content_more_than_window = ( that.$content.height() >  $window.height() );

            if (set_fixed && is_content_more_than_window) {

                $header
                    .addClass(fixed_class)
                    .css({
                        left: wrapper_offset.left,
                        width: header_w
                    });

                is_fixed = true;

            } else {

                $header
                    .removeClass(fixed_class)
                    .removeAttr("style");

                is_fixed = false;
            }
        }

        function onResize() {
            header_w = $wrapper.outerWidth();

            if (is_fixed) {
                $header.width(header_w);
            }
        }
    };

    return CRMContactsOperations;

})(jQuery);
