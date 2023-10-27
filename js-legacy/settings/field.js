var crmSettingsField = (function ($) {

    var crmSettingsField = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;

        // VARS
        // DYNAMIC VARS
        // INIT
        that.initClass();
    };

    crmSettingsField.prototype.initClass = function () {
        var that = this;
        that.initSortable();
        that.bindEvents();
    };

    crmSettingsField.prototype.initSortable = function () {
        var that = this,
            href = $.crm.app_url + "?module=settings&action=fieldSaveSort",
            item_index,
            xhr = false,
            $block = that.$wrapper.find('.crm-other-fields');

        $block.sortable({
            handle: '.sort',
            items: '.field',
            axis: 'y',
            tolerance: 'pointer',
            delay: 200,
            start: function(event,ui) {
                item_index = ui.item.index();
            },
            stop: function(event,ui) {
                if (item_index != ui.item.index()) {
                    var fields = getSortArray($block);
                    saveSort(href, { fields: fields });
                }
            }
        });

        function getSortArray($block) {
            return $block.find(".field").map(function() {
                return $.trim($(this).data("id")) || '';
            }).toArray();
        }

        function saveSort(href, data) {
            if (xhr) {
                xhr.abort();
                xhr = null;
            }
            return $.post(href, data, function () {
                xhr = null;
            });
        }
    };

    crmSettingsField.prototype.bindEvents = function () {
        var that = this,
            href = $.crm.app_url + "?module=settings&action=fieldEdit",
            xhr = null;

        that.$wrapper.on('click', '.crm-edit-field-link', function () {
            var $el = $(this);

            if (xhr) {
                xhr.abort();
                xhr = null;
            }

            xhr = $.post(href, { id: $el.data('id') || null }, function(html) {
                new CRMDialog({
                    html: html
                })
            });
        });
    };

    return crmSettingsField;

})(jQuery);
