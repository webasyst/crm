var CRMCallAssociateDealDialog = ( function($) {
    CRMCallAssociateDealDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$footer = that.$wrapper.find('.js-dialog-footer');
        that.$submit = that.$form.find(".js-submit");
        that.$deal_name = that.$form.find('.js-deal-name');
        that.$deal_funnel = that.$form.find('.js-select-deal-funnel');
        that.$deal_stage = that.$form.find('.js-select-deal-stage');
        that.$deal_id = that.$form.find('.js-deal-id');
        // VARS
      //  that.dialog = $('.dialog').data('dialog');
        
        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCallAssociateDealDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initSelectDeal();
        //
        that.initSubmit();
    };

    CRMCallAssociateDealDialog.prototype.initSelectDeal = function() {
        var that = this,
            $visible_link = that.$form.find('.js-select-deal .js-visible-link'),
            $select_funnel = that.$form.find('.js-select-funnel'),
            $deals_list = that.$form.find('.js-deals-list'),
            $deal_name_field = that.$form.find('.js-deal-name-field');

        that.$form.on('click', '.js-create-new-deal', function () {
            that.$submit.addClass('yellow').removeAttr("disabled");
            var new_deal = $(this).find('.js-text').html();
            that.deal_selected = true;
            $select_funnel.removeClass('hidden');
            $deal_name_field.removeClass('hidden');
            that.$deal_name.focus();
            $visible_link.html(new_deal);
            that.$deal_id.val('0');
        });

        that.$form.on('click', '.js-deal-item', function () {
            that.$submit.addClass('yellow').removeAttr("disabled");
            var new_deal = $(this).find('.js-text').html();
            that.deal_selected = true;
            $deals_list.find('li').removeClass('selected');
            $(this).parent().addClass('selected');
            $visible_link.html(new_deal);
            $select_funnel.addClass('hidden');
            $deal_name_field.addClass('hidden');
            that.$deal_name.val("");
            that.$deal_id.val($(this).data('deal-id'));
        });

        /*$deals_list.on('click', function () {
            $deals_list.hide();
            setTimeout( function() {
                $deals_list.removeAttr("style");
            }, 200);
        });*/

        //
        that.$form.on('change', '.js-select-deal-funnel', function() {
            that.$form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });
    };

    CRMCallAssociateDealDialog.prototype.initSubmit = function() {
        var that = this,
            $deal_field = that.$wrapper.find('.js-deal-value');

        that.$form.on("submit", function(e) {
            e.preventDefault();

            if (!that.$deal_id.val()) {
                $deal_field.addClass('shake animated').focus();
                setTimeout(function(){
                    $deal_field.removeClass('shake animated').focus();
                },500);
                return false;
            }

            if (that.$deal_id.val() == 0 && !$.trim(that.$deal_name.val())) {
                that.$deal_name.addClass('shake animated').focus();
                setTimeout(function(){
                    that.$deal_name.removeClass('shake animated').focus();
                },500);
                return false;
            }

            submit();
        });

        function submit() {
            var $loading = $('<span class="icon size-16 loading"><i class="fas fa-spinner fa-spin"></i></span>'),
                href = $.crm.app_url + "?module=call&action=associateDealSave",
                data = that.$form.serializeArray();

            that.$submit.prop('disabled', true);
            that.$footer.append($loading);

            $.post(href, data, function(res){
                if (res.status === "ok") {
                   // that.$wrapper.hide();
                    $.crm.content.reload();
                    $('.dialog.c-call-associate-deal').data('dialog').close();
                  //  that.dialog.close();
                } else {
                    that.$submit.prop('disabled', false);
                    $loading.remove();
                }
            });
        }
    };

    return CRMCallAssociateDealDialog;

})(jQuery);
