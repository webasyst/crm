var CRMDealCloseDialog = ( function($) {

    CRMDealCloseDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$reasonSelect = that.$wrapper.find(".js-reason-select");
        that.$reasonFieldW = that.$wrapper.find(".js-reason-text");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.deal_id = options["deal_id"];
        that.reason_require = options["reason_require"];

        // DYNAMIC VARS
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    CRMDealCloseDialog.prototype.initClass = function() {
        var that = this;

        that.$wrapper.on("click", ".js-set-won", function(event) {
            event.preventDefault();
            that.setWon();
        });

        that.$wrapper.on("click", ".js-show-form", function(event) {
            event.preventDefault();
            that.showLostForm();
        });

        that.$wrapper.on("click", ".js-set-lost", function(event) {
            event.preventDefault();
            that.$form.trigger("submit");
        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            that.setLost();
        });

        that.$reasonSelect.on("change", function() {
            var value = $(this).val();
            if (value) {
                that.$reasonFieldW.hide();
            } else {
                that.$reasonFieldW.show();
            }
        });
    };

    CRMDealCloseDialog.prototype.showLostForm = function(show) {
        var that = this,
            $visible = that.$wrapper.find(".c-visible"),
            $hidden = that.$wrapper.find(".c-hidden");

        if (show) {
            $visible.show();
            $hidden.hide();
        } else {
            $visible.hide();
            $hidden.show();
        }

        that.dialog.resize();
    };

    CRMDealCloseDialog.prototype.setWon = function() {
        var that = this;

        if (!that.is_locked) {
            that.is_locked = true;

            var href = "?module=deal&action=close",
                data = {
                    id: that.deal_id,
                    action: "WON"
                };

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    that.afterRequest(href, data, response);
                }
            }).always( function() {
                that.is_locked = false;
            });
        }
    };

    CRMDealCloseDialog.prototype.setLost = function() {
        var that = this;

        if (!that.is_locked) {
            that.is_locked = true;

            var href = "?module=deal&action=close",
                data = getData();

            if (data) {
                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.afterRequest(href, data, response);
                    }
                }).always( function() {
                    that.is_locked = false;
                });
            } else {
                that.is_locked = false;
            }
        }

        function getData() {
            var result = that.$form.serializeArray();

            result.push({
                "name": "id",
                "value": that.deal_id
            });

            result.push({
                "name": "action",
                "value": "LOST"
            });

            if (that.reason_require) {
                var value = that.$reasonSelect.val();

                if (!value) {
                    var error_class = "error";

                    if (that.$reasonFieldW.is(":visible")) {
                        var $input = that.$reasonFieldW.find("input"),
                            input_value = $input.val();

                        if (!input_value) {
                            $input.addClass(error_class).one("focus", function() {
                                $(this).removeClass(error_class);
                            });

                            result = false;
                        }
                    } else {
                        that.$reasonSelect.addClass(error_class).one("change", function() {
                            $(this).removeClass(error_class);
                        });

                        result = false;
                    }
                }
            }

            return result;
        }
    };

    CRMDealCloseDialog.prototype.afterRequest = function(href, data, response) {
        var that = this;

        if (response.data["dialog_html"] && response.data["dialog_html"]["html"]) {
            new CRMDialog({
                html: response.data["dialog_html"]["html"],
                onOpen: function($dialog) {
                    var $form = $dialog.find('form');
                    if ($form.length && !$.isEmptyObject(data)) {
                        $.each(data, function (_, item) {
                            $form.append('<input type="hidden" name="crm_change_workflow_data['+ item.name +']" value="'+ item.value +'">')
                        });
                    }
                },
                options: {
                    onShopSubmit: refreshDeal
                },
                onConfirm: function() {
                    data.push({
                        name: "force_execute",
                        value: 1
                    });
                    $.post(href, data, refreshDeal);
                }
            });
        } else {
            refreshDeal();
        }

        function refreshDeal() {
            that.dialog.close();
            $.crm.content.reload();
        }
    };

    return CRMDealCloseDialog;

})(jQuery);
