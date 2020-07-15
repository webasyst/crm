var crmContactMerge = (function ($) {

    var crmContactMerge = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$button = that.$wrapper.find('.crm-merge-submit');
        that.slave_ids = options.slave_ids || [];
        that.messages = options.messages || {};

        // VARS
        that.is_admin = options.is_admin;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    crmContactMerge.prototype.initClass = function () {
       var that = this;
       that.initSelectors();
       that.initMergeButton();

       that.$wrapper.on("click", ".crm-contact-row-wrapper", function(event) {
           var $input = $(this).find(".crm-selector");
           if ($input[0] !== event.target) {
               $input.click();
           }
       });
    };

    crmContactMerge.prototype.initSelectors = function () {
        var that = this;
        that.$wrapper.on('click', '.crm-selector', function (e) {
            var $el = $(this),
                $wrapper = $el.closest('.crm-contact-row-wrapper'),
                $row = $wrapper.find('.crm-contact-row'),
                $description = that.$wrapper.find('.crm-merge-description');

            that.$wrapper.find('.crm-js-hide-when-not-selected').hide();
            that.$wrapper.find('.crm-selected').removeClass('crm-selected');
            $row.addClass('crm-selected');
            $row.find('.crm-js-hide-when-not-selected').show();

            var description = $wrapper.find('.crm-merge-description-for-master').html();
            $description.html(description);

            if ($description.find('.crm-js-not-allowed-as-master').length) {
                that.$button.prop('disabled', true);
            } else {
                that.$button.prop('disabled', false);
            }
        });
    };

    crmContactMerge.prototype.initMergeButton = function () {
        var that = this,
            url = $.crm.app_url + '?module=contact&action=mergeRun',
            slave_ids = that.slave_ids,
            $loading = that.$wrapper.find('.crm-loading');

        that.$button.click(function (e) {
            e.preventDefault();
            var master_id = that.$wrapper.find(".crm-selector:checked").val();
            if (!master_id) {
                alert(that.messages.choose_master);
                return;
            }
            if (slave_ids.indexOf(master_id) >= 0 && slave_ids.length === 1) {
                alert(that.messages.choose_master);
                return;
            }
            that.$button.attr('disabled', true);
            $loading.show();

            crmContactMerger.merge({
                master_id: master_id,
                slave_ids: slave_ids,
                onDone: function () {
                    $.crm.storage.del('crm/merge/field');
                    var content_uri = $.crm.app_url + "contact/" + master_id + "/";
                    $.crm.content.load(content_uri);
                },
                onError: function () {
                    that.$button.attr('disabled', false);
                    $loading.hide();
                }
            });
        });
    };

    return crmContactMerge;

})(jQuery);
