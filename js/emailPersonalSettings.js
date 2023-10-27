var CRMEmailPersonalSettingsDialog = ( function($) {

    // helper
    function getCRM() {
        var crm = false;
        if (window && window.parent && window.parent.$ && window.parent.$.crm) {
            crm = window.parent.$.crm;
        } else if (window.$ && window.$.crm) {
            crm = window.$.crm;
        }
        return crm;
    }

    CRMEmailPersonalSettingsDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$wrapper.find(".js-wysiwyg-field");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.crm = getCRM();

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMEmailPersonalSettingsDialog.prototype.initClass = function() {
        var that = this;
        //
        that.initWYSIWYG();
        //
        that.initSave();
    };

    CRMEmailPersonalSettingsDialog.prototype.initSave = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", function(event) {
            event.preventDefault();
            submit();
        });

        function submit() {
            if (!is_locked) {
                is_locked = true;

                var data = that.$form.serialize();
                var href = that.crm.app_url + "?module=email&action=personalSettingsSave";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.options.onSave(response.data || {});
                        that.dialog.close();
                    }
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMEmailPersonalSettingsDialog.prototype.initWYSIWYG = function() {
        var that = this,
            $textarea = that.$textarea;

        that.crm.initWYSIWYG($textarea, {
            minHeight: 130,
            maxHeight: 130,
            keydownCallback: function (e) {
                //if (e.keyCode == 13 && e.ctrlKey) {
                //return addComment(); // Ctrl+Enter disabled
                //}
            }
        });

        //var old_close_func = that.dialog.onClose;

        that.dialog.onClose = function() {
            //old_close_func(arguments);
            destroyRedactor($textarea);
        };
    };

    return CRMEmailPersonalSettingsDialog;

    function destroyRedactor($textarea) {
        var redactor = $textarea.data("redactor");
        if (redactor && "core" in redactor) {
            redactor.core.destroy();
        }
    }

})(jQuery);
