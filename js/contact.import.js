var CRMContactImportPage = (function ($) {

    CRMContactImportPage = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$iframe = that.$wrapper.find('iframe');

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactImportPage.prototype.initClass = function () {
        var that = this;
        //
        that.initViewToggle();
        //
        that.initSubmit();
    };

    CRMContactImportPage.prototype.initSubmit = function () {
        var that = this,
            $iframe = that.$iframe,
            is_ready = false;

        var $forms = that.$wrapper.find("form");
        $forms.each( function() {
            initForm( $(this) );
        });

        function initForm($form) {
            $form.on("change keydown", "input, select, textarea", function () {
                $form.find("input:submit").attr('disabled', false);
            });

            $form.on("submit", function() {
                $form.find(".loading").show();
                is_ready = true;
            });
        }

        $iframe.on("load", function() {
            if (!is_ready) {
                return false;
            }

            var $el = $(this),
                html = $el.contents().find('body').html(),
                data = null;

            try {
                data = JSON.parse(html);
            } catch (e) {
            }

            if (data && data.errors) {
                that.$wrapper.find(".loading").hide();
                alert(data.errors);
            } else {
                var content_uri = $.crm.app_url + 'contact/import/upload/';
                $.crm.content.load(content_uri);
            }
        });
    };

    CRMContactImportPage.prototype.initViewToggle = function() {
        var that = this,
            active_class = "is-active",
            $toggleW = that.$wrapper.find(".js-view-toggle"),
            $activeToggle = $toggleW.find("." + active_class);

        $toggleW.on("click", ".c-toggle", setToggle);

        function setToggle(event) {
            event.preventDefault();

            var $toggle = $(this),
                content_id = $toggle.data("content"),
                is_active = $toggle.hasClass(active_class);

            if (is_active) {
                return false;
            } else {
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
            that.$wrapper.find(".js-toggle-content." + active_class).removeClass(active_class);
            // render
            var $content = that.$wrapper.find(".js-toggle-content[data-content=\"" + content_id + "\"]");
            if ($content.length) {
                $content.addClass(active_class);
            }
        }
    };

    return CRMContactImportPage;

})(jQuery);