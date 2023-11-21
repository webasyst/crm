var CRMCallRedirectDialog = ( function($) {

    CRMCallRedirectDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('.js-submit');
        that.$autocomplete = that.$form.find('.js-autocomplete');
        that.$other_radio = that.$form.find('.js-other-candidate');

        that.$number = that.$form.find('.js-number');

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMCallRedirectDialog.prototype.initClass = function() {
        var that = this;

        that.initAutocomplete();
        //
        that.initSelectCandidate();
        //
        that.initSubmit();
    };

    CRMCallRedirectDialog.prototype.initAutocomplete = function() {
        var that = this,
            $other_candidate = that.$form.find('.js-candidate'),
            $delete_candidate = $other_candidate.find('.js-delete-candidate');

        that.$autocomplete.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&type=user&phonecomplete=true",
            appendTo: that.$autocomplete.parent(),
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                that.$number.val(ui.item.phone);
                $other_candidate.find('.js-label').html(ui.item.label);
                that.$autocomplete.css('display','none');
                $other_candidate.removeClass('hidden');
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };
        that.$autocomplete.on("focus", function(){
            $(this).data("uiAutocomplete").search( $(this).val() );
        });

        // Remove other candidate
        $delete_candidate.on('click', function () {
            that.$number.val("");
            $other_candidate.addClass('hidden');
            that.$autocomplete.val('').removeAttr('style').focus();
        });
    };

    CRMCallRedirectDialog.prototype.initSelectCandidate = function() {
        var that = this,
            $other_lbl = that.$form.find('.js-other-lbl'),
            $other_candidate = that.$form.find('.js-candidate'),
            $number_type = that.$form.find('.js-number-type');

        that.$form.on('change', ":radio[name='redirect[to]']", function () {
            // external number
            if (that.$other_radio.prop('checked')) {
                $number_type.val('external');
                that.$number.val("");
                that.$autocomplete.removeAttr('style').focus();
                $other_lbl.css('display', 'none');
            // interior number
            } else {
                $number_type.val('interior');
                that.$number.val($(this).val());
                that.$autocomplete.css('display', 'none');
                $other_candidate.addClass('hidden');
                that.$autocomplete.val('');
                $other_lbl.removeAttr('style');
            }

            that.$button.prop('disabled', false);
        });
    };

    CRMCallRedirectDialog.prototype.initSubmit = function() {
        var that = this,
            $name = that.$form.find('.js-field-name');

        that.$form.on('submit', function (e) {
            e.preventDefault();

            var call_id = that.$wrapper.find('.js-call-id').val(),
                number = that.$number.val();
            if (!$.trim(number)) {
                number = $.trim(that.$autocomplete.val());
            }

            if (!$.trim(number)) {
                $name.addClass('shake animated');
                setTimeout(function(){
                    $name.removeClass('shake animated');
                },500);

                return false;
            }

            submit();

            function submit() {
                var number_type = that.$form.find('.js-number-type').val(),
                    $footer = that.$wrapper.find('.js-dialog-footer'),
                    $loading = $('<span class="icon size-16 loading"><i class="fas fa-spinner fa-spin"></i></span>');

                that.$button.prop('disabled', true);
                $footer.append($loading);

                var href = $.crm.app_url+'?module=call&action=redirect',
                    data = { call_id: call_id, number_type: number_type, number: number };

                $.post(href, data, function (r) {
                    if (r.status === "ok") {
                        $footer.find('.svg-inline--fa.fa-spinner').removeClass('fa-spinner fa-spin').addClass('fa-check text-green');
                        that.$wrapper.hide();
                        setTimeout(function () {
                            $.crm.content.reload();
                        }, 2000);
                    }
                });
            }
        });
    };


    return CRMCallRedirectDialog;

})(jQuery);