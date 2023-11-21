var CRMSettingsVaults = ( function($) {

    CRMSettingsVaults = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$list = that.$wrapper.find(".js-vaults-list");

        // VARS
        that.locales = options["locales"];
        that.vault_template_html = options["vault_template_html"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMSettingsVaults.prototype.initClass = function() {
        var that = this;
        //
        that.initEdit();
        //
        that.initSort();
    };

    CRMSettingsVaults.prototype.initEdit = function() {
        var that = this,
            xhr = false;

        that.$wrapper.on("click", ".js-vault-edit", function() {
            var $vault = $(this).closest(".c-vault-item"),
                vault_id = $vault.data("id");

            showDialog(vault_id, $vault);
        });

        that.$wrapper.on("click", ".js-vault-add", function() {
            showDialog();
        });

        function showDialog(vault_id, $vault) {
            if (xhr) {
                xhr.abort();
            }

            var href = "?module=settings&action=vaultDialog",
                data = {
                    id: ( vault_id ? vault_id : "" )
                };

            xhr = $.post(href, data, function(html) {
              $.waDialog({
                    html: html,
                    options: {
                        onSave: function(id, name, color) {
                            if ( !($vault && $vault.length) ) {
                                $vault = $(that.vault_template_html);
                                $vault
                                    .data("id", id)
                                    .appendTo(that.$list)
                            }

                            $vault.find(".js-name").text(name);
                            $vault.find(".js-color").css("background-color", color);
                        },
                        onDelete: function () {
                            $vault.remove();
                        }
                    }
                });
            }).always( function() {
                xhr = false;
            });
        }
    };

    CRMSettingsVaults.prototype.initSort = function() {
        var that = this,
            xhr = false;

        that.$list.sortable({
            distance: 10,
            handle: ".js-sort-toggle",
            helper: "clone",
            items: "> .c-vault-item",
            axis: "y",
            stop: save,
            onUpdate: save
        });

        function save(ui, ui2) {
            var $item = ui.item ? $(ui.item) : $(ui2.item);
            if (xhr) {
                xhr.abort();
            }

            var href = "?module=settings&action=vaultsSave",
                data = {
                    vaults: getSortData()
                };

            var $saving = $(that.locales.saving);
                $saving.appendTo($item);

            xhr = $.post(href, data, function(response) {
                if (response.status == "ok") {
                    var $saved = $(that.locales.saved);
                    $saved.appendTo($item);

                    setTimeout(function() {
                        var is_exist = $.contains(document, $saved[0]);
                        if (is_exist) {
                            $saved.remove();
                        }
                    }, 2000);
                }
            }).always( function() {
                $saving.remove();
                xhr = false;
            });
        }

        function getSortData() {
            var result = [];

            that.$list.find(".c-vault-item").each( function() {
                var id = $(this).data("id");
                if (id) {
                    result.push(id);
                }
            });

            return result;
        }
    };

    return CRMSettingsVaults;

})(jQuery);

var CRMVaultEdit = ( function($) {

    CRMVaultEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.locales = options["locales"];
        that.count = options["count"];
        that.vault_id = options["vault_id"];
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMVaultEdit.prototype.initClass = function() {
        var that = this;
        //
        that.initDelete();
        //
        that.initSave();
        //
        that.initColorPicker();
        //
        that.initNoAccessList();
    };

    CRMVaultEdit.prototype.initDelete = function() {
        var that = this;

        that.$wrapper.on("click", ".js-vault-delete", showConfirm);

        function showConfirm(event) {
            event.preventDefault();

            var id = that.vault_id;

            $.waDialog.confirm({
                title: `<i class=\"fas fa-exclamation-triangle smaller state-error\"></i> ${that.locales["confirm_delete_title"]}?`,
                text: that.locales["confirm_delete_text"],
                success_button_title: `${that.locales["confirm_delete_button"]}`,
                success_button_class: 'danger',
                cancel_button_title: `${that.locales["confirm_cancel_button"]}`,
                cancel_button_class: 'light-gray',
                onSuccess: function() {
                    deleteVault(id, function() {
                        that.dialog.options.onDelete();
                        that.dialog.close();
                    });
                }
            });
        }

        function deleteVault(id, callback) {
            var href = "?module=settings&action=vaultDialogDelete",
                data = {
                    id: id
                };

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    if (callback && (typeof callback === "function")) {
                        callback();
                    }
                }
            });
        }
    };

    CRMVaultEdit.prototype.initSave = function() {
        var that = this,
            $form = that.$form,
            is_locked = false,
            vault_name = "",
            vault_color = "#aaa";

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index, item) {
                if (item.name === "data[name]") {
                    vault_name = item.value;
                }
                if (item.name === "data[color]") {
                    vault_color = item.value;
                }
                result.data.push(item);
            });

            return result;
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                var keys = Object.keys(ajax_errors);
                $.each(keys, function(index, name) {
                    errors.push({
                        name: name,
                        value: ajax_errors[name]
                    })
                });
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);

                    // var field_o = $field.offset(),
                    //     wrapper_o = that.$wrapper.offset(),
                    //     top = field_o.top - wrapper_o.top + $field.outerHeight(),
                    //     left = field_o.left - wrapper_o.left;
                    //
                    // $text.css({
                    //     left: left + "px",
                    //     top: top + "px"
                    // });

                    that.$wrapper.append($text);

                    $field
                        .addClass(error_class)
                        .one("focus click change", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=settings&action=vaultDialogSave";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        console.log( that.dialog );
                        that.dialog.options.onSave(response.data.id, vault_name, vault_color);
                        that.dialog.close();
                    } else if (response.errors) {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMVaultEdit.prototype.initColorPicker = function() {
        var that = this;

        var ColorPicker = ( function($) {

            ColorPicker = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$field = options["$field"];
                that.$icon = options["$icon"];
                that.$colorPicker = options["$colorPicker"];

                // VARS

                // DYNAMIC VARS
                that.is_opened = false;
                that.farbtastic = false;

                // INIT
                that.initClass();
            };

            ColorPicker.prototype.initClass = function() {
                var that = this;

                that.farbtastic = $.farbtastic(that.$colorPicker, function(color) {
                    that.$field.val( color ).change();
                });

                that.$wrapper.data("colorPicker", that);

                that.bindEvents();
            };

            ColorPicker.prototype.bindEvents = function() {
                var that = this;

                that.$icon.on("click", function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    // show current
                    that.displayToggle( !that.is_opened );
                });

                //

                that.$colorPicker.on("click", function(event) {
                    event.stopPropagation();
                });

                //

                that.$field.on("click", function(event) {
                    event.stopPropagation();
                });

                that.$field.on("focus", function() {
                    if (!that.is_opened) {
                        that.displayToggle(true);
                    }
                });

                that.$field.on("change keyup", function() {
                    var color = $(this).val();
                    //
                    that.$icon.css("background-color", color);
                    that.farbtastic.setColor(color);
                });

                //

                $(document).on("click", function() {
                    that.displayToggle(false);
                });

                $(document).on("colorPickerIsOpened", function() {
                    if (that.is_opened) {
                        that.displayToggle(false);
                    }
                });
            };

            ColorPicker.prototype.displayToggle = function( show ) {
                var that = this,
                    shown_class = "is-shown",
                    $colorPicker = that.$colorPicker;

                if (show) {
                    $(document).trigger("colorPickerIsOpened", that);

                    $colorPicker.addClass(shown_class);
                    that.is_opened = true;
                } else {
                    $(document).trigger("colorPickerIsClosed", that);

                    $colorPicker.removeClass(shown_class);
                    that.is_opened = false;
                }
            };

            return ColorPicker;

        })(jQuery);

        that.$wrapper.find(".js-color-toggle").each( function() {
            var $wrapper = $(this);

            new ColorPicker({
                $wrapper: $wrapper,
                $field: $wrapper.find(".js-field"),
                $icon: $wrapper.find(".js-toggle"),
                $colorPicker: $wrapper.find(".js-color-picker")
            });
        });

    };

    CRMVaultEdit.prototype.initNoAccessList = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-no-access-wrapper"),
            active_class = "is-active";

        $wrapper.on("click", ".js-show-access-list", function(event) {
            event.preventDefault();
            $wrapper.addClass(active_class);
            that.dialog.resize();
        });

        $wrapper.on("click", ".js-hide-access-list", function(event) {
            event.preventDefault();
            $wrapper.removeClass(active_class);
            that.dialog.resize();
        });
    };

    return CRMVaultEdit;

})(jQuery);