var CRMReasonsPage = ( function($) {

    CRMReasonsPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$reasonsList = that.$wrapper.find(".js-reasons-list");
        that.$footer = that.$wrapper.find(".js-footer-actions");
        that.$form = that.$wrapper.find("form");
        that.$freeField = that.$wrapper.find(".js-free-field");
        that.$submitButton = that.$wrapper.find(".js-submit-button");
        that.$hiddenActions = that.$footer.find(".js-hidden-actions");

        // VARS
        that.reason_template_html = options["reason_template_html"];
        that.locales = options["locales"];

        // DYNAMIC VARS
        that.is_changed = false;

        // INIT
        that.initClass();
    };

    CRMReasonsPage.prototype.initClass = function() {
        var that = this;
        //
        that.initSortable();
        //
        that.initAddReason();
        //
        that.initRemoveReason();
        //
        that.initSubmit();

        that.$form.on("change", "input", function() {
            that.toggleButton(true);
        });
    };

    CRMReasonsPage.prototype.initSortable = function() {
        var that = this;

        that.$reasonsList.sortable({
            distance: 10,
            handle: ".js-sort-toggle",
            helper: "clone",
            items: "> li",
            axis: "y",
            change: function () {
                that.toggleButton(true);
            }
        });
    };

    CRMReasonsPage.prototype.initRemoveReason = function() {
        var that = this;

        that.$wrapper.on("click", ".js-delete-reason", removeReason);

        function removeReason(event) {
            event.preventDefault();
            $(this).closest(".c-reason").remove();

            if ( !that.$reasonsList.find(".c-reason").length ) {
                that.$freeField
                    .attr("checked", true)
                    .attr("disabled", true);
            }

            that.toggleButton(true);
        }
    };

    CRMReasonsPage.prototype.initAddReason = function() {
        var that = this;

        that.$wrapper.on("click", ".js-add-reason", addReason);

        function addReason(event) {
            event.preventDefault();

            var reason = that.reason_template_html,
                $reason = $(reason);

            that.$reasonsList.append($reason);

            that.$freeField.attr("disabled", false);

            that.toggleButton(true);
        }
    };

    CRMReasonsPage.prototype.initSubmit = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", function(event) {
            event.preventDefault();
            submit( $(this) );
        });

        function submit( $form ) {
            if (!is_locked) {
                is_locked = true;

                var saving = that.locales.saving,
                    $loading = $(saving);
                that.$footer.append($loading);

                var href = "?module=settings&action=lostReasonsSave",
                    data = getData($form);


                $.post(href, data, function(response) {
                    if (response.status == "ok") {
                        $loading.remove();

                        var saved = that.locales.saved,
                            $saved = $(saved);
                        that.$footer.append($saved);

                        that.toggleButton(false);

                        setTimeout(function() {
                            var is_exist = $.contains(document, $saved[0]);
                            if (is_exist) {
                                $saved.remove();
                            }
                        }, 2000);
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }

        function getData($form) {
            var result = $form.serializeArray();

            if (that.$freeField.is(":disabled")) {
                that.$freeField.attr("disabled", false);
                result = $form.serializeArray();
                that.$freeField.attr("disabled", true);
            }

            addReasons(result);

            function addReasons(result) {
                that.$reasonsList.find(".c-reason").each( function(index) {
                    var $li = $(this),
                        id = $li.data("id"),
                        name = $li.find(".js-name-field").val(),
                        funnel_id = $li.find(".js-funnel-id-field").val();

                    if (name) {
                        if (id) {
                            result.push({
                                name: "reasons[" + index + "][id]",
                                value: id
                            });
                        }
                        result.push({
                            name: "reasons[" + index + "][name]",
                            value: name
                        });
                        result.push({
                            name: "reasons[" + index + "][funnel_id]",
                            value: funnel_id
                        });
                    }
                });
            }

            return result;
        }
    };

    CRMReasonsPage.prototype.toggleButton = function( set_active ) {
        var that = this,
            $button = that.$submitButton,
            $actions = that.$hiddenActions;

        if (set_active) {
            $button
                .removeClass("green")
                .addClass("yellow");

            $actions.show();

        } else {
            $button
                .removeClass("yellow")
                .addClass("green");

            $actions.hide();
        }
    };

    return CRMReasonsPage;

})(jQuery);