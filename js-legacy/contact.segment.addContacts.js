var CRMContactSegmentAddContacts = (function ($) {

    CRMContactSegmentAddContacts = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('.js-submit-button');
        that.$find_contact = that.$wrapper.find('.js-find-contact');
        that.$contact_list = that.$wrapper.find('.c-contact-list');

        // VARS
        that.segment = options.segment || {};
        that.dialog = that.$wrapper.data('dialog');

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactSegmentAddContacts.prototype.initClass = function () {
        var that = this;

        that.initAutocomplete();

        that.initDeleteLinks();

        that.initSubmit();
    };

    CRMContactSegmentAddContacts.prototype.initAutocomplete = function () {
        var that = this,
            $input = that.$find_contact,
            $list = that.$contact_list,
            $template = $list.find('.is-template');

        $input.focus();

        $input.autocomplete({
            source: "?module=autocomplete",
            minLength: 2,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var $item = $template.clone().removeClass('is-template');
                $item.find('.c-contact-id').val(ui.item.id);
                $item.find('.c-contact-name').text(ui.item.name);
                $item.find('.c-contact-icon').css('backgroundImage', 'url(' + ui.item.photo_url + ')');
                $list.append($item.show());
                $input.val('');
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };
    };

    CRMContactSegmentAddContacts.prototype.initDeleteLinks = function () {
        var that = this,
            $wrapper = that.$wrapper;

        $wrapper.on('click', '.c-contact-delete-link', function (e) {
            e.preventDefault();
            $(this).closest('.c-contact-item').remove();
        });
    };

    CRMContactSegmentAddContacts.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form,
            $button = that.$button,
            $loading = $form.find('.c-loading');

        $form.submit(function (e) {
            e.preventDefault();
            $loading.show();
            $button.prop('disabled', true);
            $.post($form.attr('action'), $form.serialize())
                .done(function (r) {
                    if (r.status == 'ok') {
                        $.crm.content.load($.crm.app_url + 'contact/segment/' + that.segment.id + '/');
                    }
                })
                .always(function () {
                    $loading.hide();
                    $button.prop('disabled', false);
                    that.dialog.close();
                });
        });
    };

    return CRMContactSegmentAddContacts;

})(jQuery);
