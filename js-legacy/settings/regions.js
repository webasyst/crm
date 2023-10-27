var CRMSettingsRegions = ( function($) {

    var CRMSettingsRegions = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$table = that.$form.find('table');

        // set title
        options.title = options.title || '';
        options.title && $.crm.title.set(options.title);

        // VARS
        that.country_iso3letter = options.country_iso3letter;
        that.messages = options.messages;

        // INIT
        that.initClass();
    };

    CRMSettingsRegions.prototype.initClass = function() {
        var that = this;
        //
        that.initDeleteLinks();
        //
        that.initFavorite();
        //
        that.initAddLink();
        //
        that.initSelector();
        //
        that.initSubmit();

    };

    CRMSettingsRegions.prototype.initDeleteLinks = function () {
        var that = this,
            $table = that.$table;

        // Mark table row for deletion when user clicks delete icon
        $table.on('click', '.delete', function() {
            var tr = $(this).parents('tr');
            var initial_value = tr.find('[name="region_names[]"]').attr('rel');
            if (!initial_value) {
                tr.remove();
                if ($table.find('tbody tr:not(.white):visible').length <= 0) {
                    $table.find('.empty-stub').show();
                }
                return;
            }

            var row = $table.find('tr.template-deleted').clone().removeClass('hidden').removeClass('template-deleted');
            row.find('.insert-name-here').text(initial_value);
            tr.after(row).remove();
        });
    };

    CRMSettingsRegions.prototype.initFavorite = function () {
        var that = this,
            $form = that.$form,
            $table = that.$table;

        // Icon to mark region as favorite
        $table.on('click', '.fav', function() {
            var i = $(this).toggleClass('star').toggleClass('star-empty');
            var fav_sort = i.hasClass('star') ? '1' : '';
            i.siblings('input:hidden').val(fav_sort);

            // Save immediately via AJAX so user does not have to click save
            if (i.parents('.just-added').length <= 0) {
                $.post($form.attr('action'), { fav: 1, country: that.country_iso3letter, region: i.parents('tr').data('origCode'), fav_sort: fav_sort });
            }
        });

        // Icon to mark country as favorite
        $('#favorite-country').click(function() {
            var i = $(this).toggleClass('star').toggleClass('star-empty');
            var fav_sort = i.hasClass('star') ? '1' : '';
            i.siblings('[name="country_fav"]').val(fav_sort);

            // Save immediately via AJAX so user does not have to click save
            $.post($form.attr('action'), { fav: 1, country: that.country_iso3letter, fav_sort: fav_sort });
        });
    };

    CRMSettingsRegions.prototype.initAddLink = function () {
        var that = this,
            $table = that.$table;

        // Link to add new region
        $('#add-region-link').click(function() {
            var row = $table.find('tr.template-new').clone().removeClass('hidden').removeClass('template-new');
            $(this).parents('tr').before(row);
            row.siblings('.empty-stub').hide();
        });
    };

    CRMSettingsRegions.prototype.initSelector = function () {
        var that = this,
            $form = that.$form,
            $selector = $form.find('select');

        // Helper to determine whether the form has changed and we should warn user if he leaves the page
        var getFormValueNoFavs = function() {
            // Ignore fav change since it is saved on the fly
            return $form.serialize().replace(/region_favs%5B%5D=[^&]*&/g, '').replace(/country_fav=[^&]*&/g, '');
        };

        var initial_form_value = getFormValueNoFavs();

        // Reload the page when user changes country in the selector
        $selector.change(function() {
            if (initial_form_value !== getFormValueNoFavs()) {
                var msg = that.messages['confirm_region_not_saved'];
                if (!confirm(msg)) {
                    $selector.val(that.country_iso3letter);
                    return false;
                }
            }
            var new_country = $selector.val();
            $selector.attr('disabled', true);
            $('#c-settings-content').load($.crm.app_url + '?module=settings&action=regions&country='+new_country);
        });
    };

    CRMSettingsRegions.prototype.initSubmit = function () {
        var that = this,
            $form = that.$form;

        $form.submit(function(e) {

            e.preventDefault();

            // Validation
            var errors = false;
            $form.find('.zebra input:visible').each(function() {
                var self = $(this);
                var val = self.val();
                if (!val || val == '0') {
                    self.addClass('error').one('focus', function() {
                        self.removeClass('error');
                    });
                    errors = true;
                }
            });

            if (!errors) {
                $form.find(':submit').attr('disabled', true);
                $.post($form.attr('action'), $form.serialize(), function(r) {
                    $('#c-settings-content').html(r);
                    setTimeout(function() {
                        var s = $('<span><i class="icon16 country yes"></i> '+ that.messages['saved'] +'</span>');
                        $('#regions-form :submit').after(s);
                        s.fadeOut(1500, function() {
                            $(this).remove();
                        });
                    }, 0);
                });
            }

        });
    };

    return CRMSettingsRegions;

})(jQuery);
