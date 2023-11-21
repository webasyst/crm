/** Section controller */
var CRMLog = ( function($) {

    var ActivityLazyLoading = ( function($) {

        ActivityLazyLoading = function(options) {
            var that = this;

            // VARS
            that.list_name = options["names"]["list"];
            that.items_name = options["names"]["items"];
            that.pagind_name = options["names"]["paging"];
            that.log = options["log"];

            // DOM
            that.app_url = options["app_url"];
            that.$wrapper = ( options["$wrapper"] || false );
            that.$list = that.$wrapper.find(that.list_name);
            that.$window = $(window);

            // Handler
            that.onLoad = ( options["onLoad"] || function() {} );

            // DYNAMIC VARS
            that.$paging = that.$wrapper.find(that.pagind_name);
            that.xhr = false;
            that.is_locked = false;

            // INIT
            that.addWatcher();
        };

        ActivityLazyLoading.prototype.addWatcher = function() {
            var that = this,
                window_parent = window.parent;

            that.$window.on("scroll", onScroll);
            if (window_parent && window.frameElement) {
                $(window_parent).on("scroll", onScroll);
            }

            function onScroll() {
                var is_paging_exist = window && ( $.contains(document, that.$paging[0]) );
                try {
                    if (is_paging_exist && window_parent && window.frameElement) {
                        is_paging_exist = $.contains(window_parent.document, window.frameElement);
                    }
                } catch(e) {
                    console.log(e.message);
                }

                if (is_paging_exist) {
                    that.onScroll();
                } else {
                    that.$window.off("scroll", onScroll);
                    $(window_parent).off("scroll", onScroll);
                }
            }
        };

        ActivityLazyLoading.prototype.onScroll = function() {
            var that = this,
                $window = that.$window,
                scroll_top = $window.scrollTop(),
                display_height = $window.height(),
                paging_top = that.$paging.offset().top;

            if (window.parent && window.frameElement) {
                var $parent_window = $(window.parent);
                display_height = $parent_window.height();
                scroll_top += $parent_window.scrollTop();
                paging_top += $(window.frameElement).offset().top;
            }

            // If we see paging, stop watcher and run load
            if (scroll_top + display_height >= paging_top) {

                if (!that.is_locked) {
                    that.is_locked = true;
                    that.loadNextPage();
                }
            }
        };

        ActivityLazyLoading.prototype.loadNextPage = function() {
            var that = this,
                href = that.app_url + "?module=log",
                data = that.log.filtersData.slice(0);

            data.push({
                name: "max_id",
                value: that.$paging.data("max-id")
            });

            data.push({
                name: "timestamp",
                value: that.$list.find(that.items_name).last().data("timestamp")
            });

            if (that.log.deal_id) {
                data.push({
                    name: "deal_id",
                    value: that.log.deal_id
                });
            }

            if (that.log.contact_id) {
                data.push({
                    name: "contact_id",
                    value: that.log.contact_id
                });
            }

            if (that.xhr) {
                that.xhr.abort();
            }

            that.xhr = $.get(href, data, function(response) {

                var $temp = $("<div id='c-temp-wrapper' />");
                that.log.$wrapper.after($temp);
                $temp.html(response);

                var $wrapper = $temp,
                    $newItems = $wrapper.find(that.list_name + " " + that.items_name),
                    $newPaging = $wrapper.find(that.pagind_name);

                that.$list.append($newItems);
                that.$paging.after($newPaging);
                that.$paging.remove();
                that.$paging = $newPaging;
                that.is_locked = false;
                $temp.remove();
                //
                that.onLoad();
            });
        };

        return ActivityLazyLoading;

    })(jQuery);

    // Class for Reminder in Reminder List
    var LogReminder = ( function($) {

        LogReminder = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$marker = that.$wrapper.find(".c-marker");
            that.$steps = that.$wrapper.find(".c-step");
            that.$view = that.$steps.filter(".is-view");
            that.$edit = that.$steps.filter(".is-edit");
            that.$confirm = that.$steps.filter(".is-confirm");
            that.$textarea = that.$edit.find("textarea");

            // VARS
            that.app_url = options["app_url"];
            that.id = options["id"];
            that.log = options["log"];
            that.shown_class = "is-shown";

            // DYNAMIC VARS
            that.$activeContent = that.$view;
            that.xhr = false;

            // INIT
            that.initClass();
        };

        LogReminder.prototype.initClass = function() {
            var that = this;

            that.$wrapper.on("click", ".js-cancel", function(event) {
                event.preventDefault();
                that.toggleContent( that.$view );
            });

            that.$wrapper.on("click", ".js-edit-reminder", function(event) {
                event.preventDefault();

                if (!that.$textarea.data("value")) {
                    that.$textarea.data("value", that.$textarea.val());
                } else {
                    that.$textarea.val( that.$textarea.data("value") );
                }

                that.toggleContent( that.$edit );
            });

            that.$wrapper.on("click", ".js-confirm-delete", function(event) {
                event.preventDefault();
                that.remove();
            });

            that.$wrapper.on("click", ".js-remove", function(event) {
                event.preventDefault();
                toggleConfirm(true);
            });

            that.$wrapper.on("click", ".js-confirm-cancel", function(event) {
                event.preventDefault();
                toggleConfirm(false);
            });

            that.initDone();
            //
            that.initQuickDateToggle();
            //
            that.initQuickContentEdit();
            //

            function toggleConfirm(show) {
                var active_class = "is-shown";
                if (show) {
                    that.$confirm.addClass(active_class);
                } else {
                    that.$confirm.removeClass(active_class);
                }
            }
        };

        LogReminder.prototype.remove = function() {
            var that = this,
                href = that.app_url + "?module=reminder&action=delete",
                data = {
                    id: that.id
                };

            if (that.xhr) {
                that.xhr.abort();
            }

            that.xhr = $.post(href, data, function(response) {
                if (response.status === "ok") {
                    that.$wrapper.remove();
                    $(document).trigger("reminderIsChanged");
                }
            }).always( function() {
                that.xhr = false;
            });
        };

        LogReminder.prototype.initDone = function() {
            var that = this,
                $reminder = that.$wrapper;

            $reminder.on("click", ".js-mark-done", setDone);

            function setDone(event) {
                event.preventDefault();

                var $marker = $(this);

                $marker.addClass("is-done");

                var id = that.id,
                    href = that.app_url + "?module=reminder&action=markAsDone",
                    data = {
                        id: id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        $reminder.remove();
                        $(document).trigger("reminderIsChanged");
                    }
                }, "json").always( function() {
                });
            }
        };

        LogReminder.prototype.toggleContent = function($content) {
            var that = this;

            if (that.$activeContent.length) {
                that.$activeContent.removeClass(that.shown_class);
            }
            $content.addClass(that.shown_class);
            that.$activeContent = $content;
        };

        LogReminder.prototype.initQuickDateToggle = function() {
            var that = this,
                $wrapper = that.$wrapper.find(".js-quick-date-toggle-wrapper"),
                is_opened = false,
                is_locked = false;

            if (!$wrapper.length) {
                return false;
            }

            // DOM
            var $form = $wrapper.find("form"),
                $input = $wrapper.find(".js-date-field");

            // VARS

            // EVENTS

            $wrapper.on("click", ".js-change-date", function(event) {
                event.preventDefault();
                toggleContent(true);
            });

            $wrapper.on("click", ".js-cancel-edit-date", function(event) {
                event.preventDefault();
                toggleContent(false);
            });

            $wrapper.on("click", ".js-save-date", function(event) {
                event.preventDefault();
                save()
            });

            $(document).on("click", clickWatcher);
            function clickWatcher(event) {
                var is_exist = $.contains(document, $wrapper[0]);
                if (is_exist) {
                    var is_target = $.contains($wrapper[0], event.target);

                    if (is_opened) {
                        var $target = $(event.target),
                            is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                            is_date = !!( $target.closest(".ui-datepicker").length || $target.closest(".ui-datepicker-header").length ),
                            is_datepicker_icon = !!($target.hasClass("ui-icon-circle-triangle-e") );

                        if (is_time || is_date || is_datepicker_icon) {
                            return false;
                        }
                    }

                    if (!is_target && is_opened) {
                        toggleContent(false);
                    }
                } else {
                    $(document).off("click", clickWatcher);
                }
            }

            // CALLS

            initDatePicker();

            initTimeToggle();

            // FUNCTIONS

            function save() {
                if (!is_locked) {
                    is_locked = true;

                    var href = "?module=reminder&action=save",
                        data = $form.serializeArray();

                    that.renderLoading(true);

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            $(document).trigger("reminderIsChanged");
                        }
                    }).always( function() {
                        that.renderLoading(false);
                        is_locked = false;
                    });
                }
            }

            function toggleContent(show) {
                var active_class = "is-extended";

                if (show) {
                    $wrapper.addClass(active_class);
                    $input.focus();
                    is_opened = true;
                } else {
                    $wrapper.removeClass(active_class);
                    is_opened = false;
                }
            }

            function initDatePicker() {
                var $altInput = $wrapper.find(".js-alt-date-field");

                $input.datepicker({
                    altField: $altInput,
                    altFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });

                var $icon = $input.parent().find(".calendar");
                $icon.on("click", function() {
                    $input.focus();
                });
            }

            function initTimeToggle() {
                var $toggle = $wrapper.find(".js-time-toggle"),
                    $field = $toggle.find(".js-timepicker");

                $toggle.on("click", ".js-show-time", function() {
                    show(true);
                    $field.focus();
                });

                $toggle.on("click", ".js-reset-time", function() {
                    $field.val("");
                    show();
                });

                var $timePickers = $wrapper.find(".js-timepicker");
                $timePickers.timepicker();

                function show(show) {
                    var active_class = "is-active";
                    if (show) {
                        $toggle.addClass(active_class);
                    } else {
                        $toggle.removeClass(active_class);
                    }
                }
            }
        };

        LogReminder.prototype.initQuickContentEdit = function() {
            var that = this,
                $wrapper = that.$wrapper.find(".js-quick-content-toggle-wrapper");

            if (!$wrapper.length) {
                return false;
            }

            // DOM
            var $form = $wrapper.find("form"),
                $textarea = $form.find(".js-textarea");

            // VARS
            var active_class = "is-changed";

            // DYNAMIC VARS
            var is_changed = false,
                is_locked = false;

            // EVENTS

            $textarea.on("keyup", function(event) {
                var key = event.keyCode,
                    is_enter = ( key === 13 ),
                    value = $textarea.val();

                if (value.length) {
                    if (!is_changed) {
                        is_changed = true;
                        $textarea.addClass(active_class);
                    }
                } else {
                    is_changed = false;
                    $textarea.removeClass(active_class);
                }

                if (!is_enter || event.shiftKey) {
                    toggleHeight();
                }
            });

            $textarea.on("keydown", function(event) {
                var key = event.keyCode,
                    is_enter = ( key === 13 );

                if (is_enter && !event.shiftKey) {
                    event.preventDefault();

                    if (is_changed) {
                        save();
                    }
                }
            });

            $textarea.on("blur", function() {
                if (is_changed) {
                    save();
                }
            });

            // var $body = $(document).find("body");
            // $body.on("click", "a", watcher);
            // function watcher(event) {
            //     var $link = $(this);
            //     var is_exist = $.contains(document, $textarea[0]);
            //     if (is_exist && is_changed) {
            //         event.preventDefault();
            //         event.stopPropagation();
            //
            //         $.crm.confirm.show({
            //             title: that.locales["add_confirm_title"],
            //             text: that.locales["add_confirm_text"],
            //             button: that.locales["add_confirm_button"],
            //             onConfirm: function () {
            //                 $body.off("click", watcher);
            //                 $link.trigger("click");
            //             }
            //         });
            //     } else {
            //         $body.off("click", watcher);
            //     }
            // }
            
            setTimeout(() => {
                toggleHeight();
            }, 100)
            

            // FUNCTIONS

            function save() {
                if (!is_locked) {
                    is_locked = true;

                    that.renderLoading(true);

                    var href = "?module=reminder&action=save",
                        data = $form.serializeArray();

                    $.post(href, data, function(response) {
                        if (response.status === "ok") {
                            is_changed = false;
                            $textarea
                                .removeClass(active_class)
                                .blur();
                        }
                    }).always( function() {
                        is_locked = false;
                        that.renderLoading(false);
                    });
                }
            }

            function toggleHeight() {
                $textarea.css("min-height", 0);
                var scroll_h = $textarea[0].scrollHeight;
                console.log(scroll_h)
                $textarea.css("min-height", scroll_h + "px");
            }
        };

        LogReminder.prototype.renderLoading = function(is_loading) {
            var that = this,
                $marker = that.$marker,
                load_class = "is-load";

            var $loading = $marker.data("icon");
            if ( !$loading ) {
                $loading = $("<i class=\"icon16 loading\"></i>");
                $marker.prepend($loading);
                $marker.data("icon", $loading);
            }

            if (is_loading) {
                $marker.addClass(load_class);
                $loading.show();
            } else {
                $marker.removeClass(load_class);
                $loading.hide();
            }
        };

        return LogReminder;

    })(jQuery);

    CRMLog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$filtersForm = that.$wrapper.find("#c-filters-form");

        // VARS
        that.user_id = options["contact_id"];
        that.contact_id = options["contact_id"];
        that.deal_id = options["deal_id"];
        that.app_url = options["app_url"];
        that.max_upload_size = options["max_upload_size"];
        that.locales = options["locales"];
        that.crm = getCRM();

        // DYNAMIC VARS
        that.filtersData = that.$filtersForm.serializeArray();

        // INIT
        that.initClass();
    };

    CRMLog.prototype.initClass = function() {
        var that = this;
        //
        that.initFilters();
        //
        that.initAddReminder();
        //
        that.initRemindersList();
        //
        that.initNoteForm();
        //
        that.initLogList();

        $(document).on("reminderIsChanged", watcher);
        function watcher() {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                if (that.crm) {
                    that.crm.sidebar.reload();
                }
                that.load();
            } else {
                $(document).off("reminderIsChanged", watcher);
            }
        }

        that.$wrapper.data("log", that);
    };

    CRMLog.prototype.initAddReminder = function() {
        var that = this,
            $wrapper = $("#c-add-reminder-form"),
            $textarea = $wrapper.find(".js-textarea"),
            $icon = $wrapper.find(".c-icon-column .icon16"),
            $form = $wrapper.find("form"),
            extended_class = "is-extended",
            is_changed = false,
            is_locked = false;

        $form.on("submit", submit);

        $textarea.on("focus", function () {
            $wrapper.addClass(extended_class);
        });

        $wrapper.on("click", ".js-cancel", close);

        $textarea.on("keyup", function(event) {
            var key = event.keyCode,
                is_enter = ( key === 13 ),
                value = $textarea.val(),
                active_class = "is-changed";

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    $textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
                $textarea.removeClass(active_class);
            }

            if (!is_enter || event.shiftKey) {
                toggleHeight();
            }
        });

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();
                $form.find("input:submit").trigger("click");
            }
        });

        //

        $(document).on("click", watcher);

        initCombobox($wrapper);
        //
        initTypeToggle($wrapper);
        //
        initDatePicker();
        //
        initTimeToggle();
        //
        initTimePicker();

        function watcher(event) {
            var $target = $(event.target),
                is_exist = $.contains(document, $wrapper[0]),
                is_target = $.contains($wrapper[0], event.target),
                is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                is_date = !!( $target.closest(".ui-datepicker").length || $target.closest(".ui-datepicker-header").length ),
                is_empty = !$textarea.val().length;

            if (is_exist) {
                if (!is_target && !is_time && !is_date && is_empty) {
                    close();
                }
            } else {
                $(document).off("click", watcher);
            }
        }

        function close() {
            $textarea.val("");
            $wrapper.removeClass(extended_class);
        }

        function submit(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;

                $icon.removeClass("c-plus").addClass("loading");

                var href = that.app_url + "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function (response) {
                    if (response.status === "ok") {
                        that.crm.sidebar.reload();
                        that.load();
                    }
                }, "json").always(function () {
                    is_locked = false;
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            console.log(scroll_h)
            $textarea.css("min-height", scroll_h + "px");
        }

        function initCombobox($block) {
            var $wrapper = $block.find(".js-contact-wrapper"),
                $idField = $wrapper.find(".js-field");

            $wrapper.on("click", ".js-show-combobox", function (event) {
                event.stopPropagation();
                showToggle(true);
            });

            $wrapper.on("click", ".js-hide-combobox", function (event) {
                event.stopPropagation();
                showToggle(false);
            });

            initAutocomplete();

            function showToggle(show) {
                var active_class = "is-shown";
                if (show) {
                    $wrapper.addClass(active_class);
                } else {
                    $wrapper.removeClass(active_class);
                }
            }

            function initAutocomplete() {
                var $autocomplete = $wrapper.find(".js-autocomplete");

                $autocomplete
                    .autocomplete({
                        appendTo: $wrapper,
                        position: { my : "right top", at: "right bottom" },
                        source: that.app_url + "?module=autocomplete&type=user",
                        minLength: 0,
                        html: true,
                        focus: function () {
                            return false;
                        },
                        select: function (event, ui) {
                            setContact(ui.item);
                            showToggle(false);
                            $autocomplete.val("");
                            return false;
                        }
                    }).data("ui-autocomplete")._renderItem = function (ul, item) {
                    return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
                };

                $autocomplete.on("focus", function(){
                    $autocomplete.data("uiAutocomplete").search( $autocomplete.val() );
                });
            }

            function setContact(user) {
                var $user = $wrapper.find(".js-user");
                if (user["photo_url"]) {
                    $user.find(".icon16").css("background-image", "url(" + user["photo_url"] + ")");
                }
                $user.find(".c-name").text(user.name);
                $idField.val(user.id);
            }
        }

        function initDatePicker() {
            var $datePickers = $wrapper.find(".js-datepicker");

            $datePickers.each(function () {
                var $input = $(this),
                    $altField = $input.parent().find("input[type='hidden']");

                $input.datepicker({
                    altField: $altField,
                    altFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });

                var $icon = $input.parent().find(".calendar");
                $icon.on("click", function () {
                    $input.focus();
                });

                // $input.datepicker("setDate", "+1d");
            });
        }

        function initTimeToggle() {
            var $toggle = $wrapper.find(".js-time-toggle"),
                $field = $toggle.find(".js-timepicker");

            $toggle.on("click", ".js-show-time", function () {
                show(true);
                $field.focus();
            });

            $toggle.on("click", ".js-reset-time", function () {
                $field.val("");
                show();
            });

            function show(show) {
                var active_class = "is-active";
                if (show) {
                    $toggle.addClass(active_class);
                } else {
                    $toggle.removeClass(active_class);
                }
            }
        }

        function initTimePicker() {
            var $timePickers = $wrapper.find(".js-timepicker");
            $timePickers.each(function () {
                var $input = $(this);
                $input.timepicker();
            });
        }

        function initTypeToggle($block) {
            var $wrapper = $block.find(".js-reminder-type-toggle"),
                $visibleLink = $wrapper.find(".js-visible-link"),
                $field = $wrapper.find(".js-field"),
                $menu = $wrapper.find(".menu-v");

            $menu.on("click", "a", function () {
                var $link = $(this);
                $visibleLink.find(".js-text").html($link.html());

                $menu.find(".selected").removeClass("selected");
                $link.closest("li").addClass("selected");

                $menu.hide();
                setTimeout(function () {
                    $menu.removeAttr("style");
                }, 200);

                var id = $link.data("type-id");
                $field.val(id).trigger("change");
            });
        }
    };

    CRMLog.prototype.initRemindersList = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-reminders-section");

        $wrapper.find(".c-reminder-wrapper").each( function() {
            var $reminder = $(this),
                id = $reminder.data("id");

            new LogReminder({
                $wrapper: $reminder,
                app_url: that.app_url,
                id: id,
                log: that
            });
        });
    };

    CRMLog.prototype.initNoteForm = function() {
        var that = this,
            $wrapper = $("#c-note-section"),
            $statusWrapper = $wrapper.find(".js-status-wrapper"),
            $form = $wrapper.find("form"),
            is_note_created = false,
            is_files_uploaded = false,
            is_locked = false;

        $form.on("submit", function(event) {
            event.preventDefault();
            onSubmit();
        });

        initUploadForm($wrapper);

        //

        function onSubmit() {
            if (!is_locked) {
                is_locked = true;

                $wrapper.trigger("uploadFiles");

                var textarea_val = $wrapper.find("textarea").val();

                if (!textarea_val.length) {
                    is_note_created = true;
                    submitWatcher();

                } else {

                    var href = that.app_url + "?module=note&action=save",
                        data = $form.serializeArray();

                    var $loading = $("<i class=\"icon16 loading\" />");
                        $loading.appendTo($statusWrapper);

                    $.post(href, data, function(response) {
                        if (response.status == "ok") {
                            is_note_created = true;
                            submitWatcher();
                        } else if (response.errors) {
                            alert(response.errors);
                        }
                    }).always( function() {
                        is_locked = false;
                        $loading.remove();
                    });
                }
            }
        }

        function initUploadForm($wrapper) {
            // DOM
            var $dropArea = $wrapper.find(".js-drop-area"),
                $dropText = $wrapper.find(".js-drop-text"),
                $fileField = $wrapper.find(".js-drop-field"),
                $uploadList = $wrapper.find(".c-upload-list"),
                $template = $uploadList.find(".is-template");

            // DATA
            var uri = that.app_url + "?module=file&action=upload",
                deal_id = $wrapper.find("[name='data\[deal_id\]']").val(),
                contact_id = $wrapper.find("[name='data\[contact_id\]']").val();

            // DYNAMIC VARS
            var files_storage = [],
                upload_file_count = 0,
                hover_timeout = 0;

            // VARS
            var hover_class = "is-hover";

            // Attach
            $fileField.on("change", function(event) {
                event.preventDefault();
                addFiles(this.files);
            });

            // Drop
            $dropArea.on("drop", function(event) {
                event.preventDefault();
                addFiles(event.originalEvent.dataTransfer.files);
            });

            // Drag
            $dropArea.on("dragover", onHover);

            // delete
            $wrapper.on("click", ".js-file-delete", function(event) {
                event.preventDefault();
                deleteFile( $(this).closest(".c-upload-item") )
            });

            $wrapper.on("uploadFiles", function() {
                if (files_storage.length) {
                    upload_file_count = files_storage.length;

                    $.each(files_storage, function(index, file_item) {
                        uploadFile(file_item);
                    });
                } else {
                    is_files_uploaded = true;
                }
            });

            function addFiles( files ) {
                if (files.length) {
                    $.each(files, function(index, file) {
                        files_storage.push({
                            $file: renderFile(file),
                            file: file
                        });
                    });
                }
            }

            function renderFile(file) {
                var $uploadItem = $template.clone(),
                    $name = $uploadItem.find(".js-name");

                $name.text(file.name);

                $uploadItem.removeClass("is-template");
                $uploadList.prepend($uploadItem);

                return $uploadItem;
            }

            function deleteFile($file) {
                var result = [];

                $.each(files_storage, function(index, item) {
                    if ($file[0] !== item.$file[0]) {
                        result.push(item);
                    } else {
                        $file.remove();
                    }
                });

                files_storage = result;
            }

            function uploadFile(file_item) {
                is_locked = true;

                var $file = file_item.$file,
                    $bar = $file.find(".js-bar"),
                    $status = $file.find(".js-status");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    request();
                } else {
                    $status.addClass("errormsg").text( that.locales["file_size"] );
                    $file.find(".c-progress-wrapper").remove();
                    is_locked = false;
                    setTimeout( function() {
                        if ($.contains(document, $file[0])) {
                            $file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                is_files_uploaded = true;
                            }
                            submitWatcher();
                        }
                    }, 2000);
                }

                //

                function request() {
                    var formData = new FormData();

                    var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        formData.append("_csrf", csrf);
                    }

                    formData.append("file_size", file_item.file.size);

                    // Vars
                    if (contact_id) {
                        formData.append("contact_id", contact_id);
                    }
                    if (deal_id) {
                        formData.append("deal_id", deal_id);
                    }
                    formData.append("files", file_item.file);
                    formData.append("file_end", 1);

                    // Ajax request
                    $.ajax({
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function(event){
                                if (event.lengthComputable) {
                                    var percent = parseInt( (event.loaded / event.total) * 100 ),
                                        color = getColor(percent);

                                    $bar
                                        .css("background-color", color)
                                        .width(percent + "%");

                                    $status.text(percent + "%");
                                }
                            }, false);
                            return xhr;
                        },
                        url: uri,
                        data: formData,
                        cache: false,
                        contentType: false,
                        processData: false,
                        type: 'POST',
                        success: function(data){
                            $status.text( $status.data("success") );
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    $file.remove();
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        is_files_uploaded = true;
                                    }
                                    submitWatcher();
                                }
                            }, 2000);
                        }
                    }).always( function () {
                        is_locked = false;
                    });
                }

                function getColor(percent) {
                    var start = [247,198,174],
                        end = [174,247,196],
                        result = [];

                    for (var i = 0; i < start.length; i++) {
                        var rgb = start[i] + (((end[i] - start[i])/100) * percent);
                        result.push(rgb);
                    }
                    return "rgb(" + result.join(",") + ")";
                }
            }

            function onHover(event) {
                event.preventDefault();
                $dropArea.addClass(hover_class);
                $dropText.text( $dropText.data("hover") );
                clearTimeout(hover_timeout);
                hover_timeout = setTimeout( function () {
                    $dropArea.removeClass(hover_class);
                    $dropText.text( $dropText.data("default") );
                }, 100);
            }
        }

        function submitWatcher() {
            if (is_note_created && is_files_uploaded) {
                that.load();
            }
        }
    };

    CRMLog.prototype.initLogList = function() {
        var that = this,
            $wrapper = that.$wrapper.find("#c-activity-section");

        setLast();

        new ActivityLazyLoading({
            $wrapper: $wrapper,
            app_url: that.app_url,
            names: {
                list: ".js-activity-list",
                items: "> li",
                paging: ".c-paging-wrapper"
            },
            log: that,
            onLoad: setLast
        });

        function setLast() {
            var first_class = "is-first",
                last_class = "is-last";

            var $items = $wrapper.find(".c-activity-item." + first_class);
            $items.each( function() {
                var $prev = $(this).prev().prev();
                if (!$prev.hasClass(last_class)) {
                    $prev.addClass(last_class);
                }
            });
        }
    };

    CRMLog.prototype.initFilters = function() {
        var that = this,
            $form = that.$filtersForm,
            $wrapper = $form.find(".c-filters-wrapper"),
            $footer = $wrapper.find(".js-actions"),
            $fields = $form.find(".js-field"),
            $resetButton = $wrapper.find(".js-reset-filters"),
            $applyW = $wrapper.find(".js-apply-wrapper");

        var selected_filters = getSelectedFilters();

        $wrapper.on("click", ".js-set-force-filter", function(event) {
            event.stopPropagation();
            setFilterForce( $(this).closest(".js-filter") );
        });

        $wrapper.on("change", ".js-field", function() {
            var $field = $(this),
                $filter = $(this).closest(".js-filter"),
                is_active = ( $field.attr("checked") === "checked" ),
                active_class = "is-active";

            //
            $applyW.show();
            $resetButton.show();

            //
            if (is_active) {
                $filter.addClass(active_class);
            } else {
                $filter.removeClass(active_class);
            }
        });

        $resetButton.on("click", function() {
            resetForm( false );
            load();
        });

        $wrapper.on("click", ".js-cancel-filters", function() {
            setSelectedFilters();
            $applyW.hide();
        });

        $wrapper.on("click", ".js-apply-filters", load);

        function getSelectedFilters() {
            var filters = [];
            $fields.each( function(index, item) {
                var is_active = ( $(this).attr("checked") === "checked" );
                filters.push(is_active);
            });

            return filters;
        }

        function setSelectedFilters() {
            $fields.each( function(index) {
                var $field  = $(this),
                    is_active = ( selected_filters[index] || false );

                $field.attr("checked", is_active).trigger("change");
            });
        }

        function setFilterForce( $filter ) {
            var $field = $filter.find(".js-field");

            resetForm();

            $field.attr("checked", true);

            load();
        }

        function resetForm( use_trigger ) {
            $fields.attr("checked", false);
            if (use_trigger) {
                $fields.trigger("change");
            }
        }

        function load() {
            $footer.append("<i class=\"icon16 loading\" />");
            that.filtersData = $form.serializeArray();
            that.load();
        }
    };

    CRMLog.prototype.load = function() {
        var that = this,
            href = that.app_url + "?module=log",
            data = (that.filtersData || []);

        if (that.contact_id > 0) {
            data.push({
                name: "contact_id",
                value: that.contact_id
            });
        } else if (that.deal_id > 0) {
            data.push({
                name: "deal_id",
                value: that.deal_id
            });
        }

        $.post(href, data, function(html) {
            // jquery datepicker reload fix
            $("#ui-datepicker-div").remove();
            that.$wrapper.replaceWith(html);

            $(window).trigger("resize");
        });
    };

    return CRMLog;

    function getCRM() {
        var crm = false;

        if (window && window.parent && window.parent.$ && window.parent.$.crm) {
            crm = window.parent.$.crm;
        } else if (window.$ && window.$.crm) {
            crm = window.$.crm;
        }

        return crm;
    }

})(jQuery);

/** One log item controller */
var CRMLogItem = ( function($) {

    CRMLogItem = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.app_url = options['app_url'] || ($ && $.crm && $.crm.app_url);

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMLogItem.prototype.initClass = function() {
        var that = this;
        //
        that.initSVG();
        //
        that.initMarker();
        //
        that.initLogMessage();
        //
        that.$wrapper.data("logItem", that);
    };

    CRMLogItem.prototype.initSVG = function($wrapper) {
        var that = this;

        if (!$wrapper) {
            $wrapper = that.$wrapper;
        }

        renderSVG($wrapper);

        function renderSVG($wrapper) {
            // to find all icons and generate svg

            if (typeof d3 !== "object") {
                return false;
            }

            var SVGIcon = ( function($, d3) {

                SVGIcon = function(options) {
                    var that = this;

                    // DOM
                    that.$icon = options["$icon"];
                    that.svg = d3.select(that.$icon[0]).append("svg");

                    // VARS
                    that.type = that.$icon.data("type");

                    // DYNAMIC VARS
                    that.icon_w = that.$icon.outerWidth();
                    that.icon_h = that.$icon.outerHeight();

                    // INIT
                    that.initClass();
                };

                SVGIcon.prototype.initClass = function() {
                    var that = this;

                    that.svg.attr("width", that.icon_w)
                        .attr("height", that.icon_h);

                    if (that.$icon.hasClass("funnel-state")) {
                        that.renderFunnelState();
                    }

                    // save backdoor
                    that.$icon.data("icon", that);
                };

                SVGIcon.prototype.renderFunnelState = function() {
                    var that = this,
                        color = ( that.$icon.data("color") || "#aaa" );

                    var svg = that.svg,
                        group = svg.append("g");

                    group.append("polygon")
                        // .attr("points", "4,16 0,16 3.9,7.9 0,0 4,0 8.7,7.9")
                        .attr("points", getX(4) + "," + getY(16) + " " + getX(0) + "," + getY(16) + " " + getX(3.9) + "," + getY(7.9) + " " + getX(0) + "," + getY(0) + " " + getX(4) + "," + getY(0) + " " + getX(8.7) + "," + getY(7.9))
                        .style("opacity", .33)
                        .style("fill", color);

                    group.append("polygon")
                        // .attr("points", "8,16 4,16 7.9,7.9 4,0 8,0 12.6,7.9")
                        .attr("points", getX(8) + "," + getY(16) + " " + getX(4) + "," + getY(16) + " " + getX(7.9) + "," + getY(7.9) + " " + getX(4) + "," + getY(0) + " " + getX(8) + "," + getY(0) + " " + getX(12.6) + "," + getY(7.9))
                        .style("opacity", .66)
                        .style("fill", color);

                    group.append("polygon")
                        // .attr("points", "11.9,16 7.9,16 11.8,7.9 7.9,0 11.9,0 16,7.9")
                        .attr("points", getX(11.9) + "," + getY(16) + " " + getX(7.9) + "," + getY(16) + " " + getX(11.8) + "," + getY(7.9) + " " + getX(7.9) + "," + getY(0) + " " + getX(11.9) + "," + getY(0) + " " + getX(16) + "," + getY(7.9))
                        .style("fill", color);

                    function getX(x) { return x/16 * that.icon_w; }
                    function getY(y) { return y/16 * that.icon_h; }
                };

                SVGIcon.prototype.refresh = function() {
                    var that = this;

                    that.icon_w = that.$icon.outerWidth();
                    that.icon_h = that.$icon.outerHeight();

                    that.svg
                        .attr("width", that.icon_w)
                        .attr("height", that.icon_h);
                };

                return SVGIcon;

            })(jQuery, d3);

            if (typeof $wrapper === "string") {
                $wrapper = $($wrapper);
            }

            if ($wrapper.length) {
                $wrapper.find(".svg-icon").each( function() {
                    var $icon = $(this),
                        icon = $icon.data("icon");

                    if (icon) {
                        icon.refresh();
                    } else if (SVGIcon) {
                        new SVGIcon({
                            $icon: $icon
                        });
                    }
                });
            }
        }
    };

    CRMLogItem.prototype.initMarker = function() {
        var that = this,
            $marker = that.$wrapper.find(".js-log-marker");
    };

    CRMLogItem.prototype.initLogMessage = function() {
        var that = this,
            $section = that.$wrapper.find(".js-log-message-section"),
            opened_class = "is-opened";

        if (!$section.length) {
            return false;
        }

        var $body = $section.find(".js-log-message-body"),
            $loading = $section.find('.js-loading'),
            id = $section.data('id'),
            url = that.app_url + '?module=log&action=loadMessage&id=' + id,
            xhr = null;

        $section.on("click", ".js-toggle-message-body", function() {
            var $link = $(this);
            if ($section.data('loaded')) {
                $section.toggleClass(opened_class);
                $body.slideToggle(300);
                return;
            }

            xhr && xhr.abort();
            xhr = null;

            $loading.show();
            xhr = $.get(url)
                .done(function (html) {
                    $body.html(html);
                    $body.slideToggle(300);
                    $section.addClass(opened_class);
                    $section.data('loaded', 1);
                    $link.removeClass('bold');
                })
                .always(function () {
                    $loading.hide();
                    xhr = null;
                });
        });

        var rpl_dialog_xhr = null,
            rpl_dialog_url = that.app_url = '?module=message&action=WriteReplyDialog',
            rpl_dialog_params = { id: id };
        $body.on('click', '.js-reply', function (e) {
            e.preventDefault();

            rpl_dialog_xhr && rpl_dialog_xhr.abort();
            $.post(rpl_dialog_url, rpl_dialog_params)
                .done(function (html) {
                    var $ = window.top.$;
                    var $body = $(window.top.document).find('body');
                    $body.append(html);
                })
                .always(function () {
                    rpl_dialog_xhr = null;
                });
        });

        var frw_dialog_xhr = null,
            frw_dialog_url = that.app_url = '?module=message&action=WriteForwardDialog',
            frw_dialog_params = { id: id };
        $body.on('click', '.js-forward', function (e) {
            e.preventDefault();

            frw_dialog_xhr && frw_dialog_xhr.abort();
            $.post(frw_dialog_url, frw_dialog_params)
                .done(function (html) {
                    var $ = window.top.$;
                    var $body = $(window.top.document).find('body');
                    $body.append(html);
                })
                .always(function () {
                    frw_dialog_xhr = null;
                });
        });
    };

    return CRMLogItem;

})(jQuery);

/** Reminder form in log item.
 *  TODO: replace to LogItem */
var CRMTimelineReminder = ( function($) {

    CRMTimelineReminder = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$marker = that.$wrapper.find(".c-activity-point");

        // VARS
        that.marker_html = that.$marker.html();

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTimelineReminder.prototype.initClass = function() {
        var that = this;

        that.initQuickContentEdit();
    };

    CRMTimelineReminder.prototype.initQuickContentEdit = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-quick-content-toggle-wrapper");

        if (!$wrapper.length) {
            return false;
        }

        // DOM
        var $form = $wrapper.find("form"),
            $textarea = $form.find(".js-textarea");

        // VARS
        var active_class = "is-changed";

        // DYNAMIC VARS
        var is_changed = false,
            is_locked = false;

        // EVENTS

        $textarea.on("keyup", function(event) {
            var key = event.keyCode,
                is_enter = ( key === 13 ),
                value = $textarea.val();

            if (value.length) {
                if (!is_changed) {
                    is_changed = true;
                    $textarea.addClass(active_class);
                }
            } else {
                is_changed = false;
                $textarea.removeClass(active_class);
            }

            if (!is_enter || event.shiftKey) {
                toggleHeight();
            }
        });

        $textarea.on("keydown", function(event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();

                if (is_changed) {
                    save();
                }
            }
        });

        $textarea.on("blur", function() {
            if (is_changed) {
                save();
            }
        });

        toggleHeight();

        // FUNCTIONS

        function save() {
            if (!is_locked) {
                is_locked = true;

                that.renderLoading(true);

                var href = "?module=reminder&action=save",
                    data = $form.serializeArray();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        is_changed = false;
                        $textarea
                            .removeClass(active_class)
                            .blur();
                    }
                }).always( function() {
                    is_locked = false;
                    that.renderLoading(false);
                });
            }
        }

        function toggleHeight() {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            console.log(scroll_h)
            $textarea.css("min-height", scroll_h + "px");
        }
    };

    CRMTimelineReminder.prototype.renderLoading = function(is_loading) {
        var that = this,
            $marker = that.$marker,
            load_class = "is-load";

        if (is_loading) {
            $marker.addClass(load_class);
            var loading = "<i class=\"icon16 loading\"></i>";
            $marker.html(loading);
        } else {
            $marker.removeClass(load_class);
            $marker.html(that.marker_html);
        }
    };

    return CRMTimelineReminder;

})(jQuery);

/** Note form in log item.
 *  TODO: replace to LogItem */
var CRMNoteForm = ( function($) {

    CRMNoteForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$log = that.$wrapper.closest(".c-activity-item");
        that.$view = that.$wrapper.find(".c-view");
        that.$edit = that.$wrapper.find(".c-edit");
        that.$confirm = that.$wrapper.find(".c-confirm");
        that.$textarea = that.$edit.find("textarea");

        // VARS
        that.id = options["id"];
        that.app_url = options["app_url"];
        that.shown_class = "is-shown";

        // DYNAMIC VARS
        that.$activeContent = that.$view;
        that.xhr = false;

        // INIT
        that.initClass();
    };

    CRMNoteForm.prototype.initClass = function() {
        var that = this;

        that.$wrapper.on("click", ".js-default", function(event) {
            event.preventDefault();
            that.toggleContent( that.$view );
        });

        that.$log.on("click", ".js-edit-note", function(event) {
            event.preventDefault();

            if (!that.$textarea.data("value")) {
                that.$textarea.data("value", that.$textarea.val());
            } else {
                that.$textarea.val( that.$textarea.data("value") );
            }

            that.toggleContent( that.$edit );
        });

        that.$wrapper.on("click", ".js-save-note", function(event) {
            event.preventDefault();
            that.save();
        });

        that.$wrapper.on("click", ".js-confirm-delete", function(event) {
            event.preventDefault();
            that.remove();
        });

        that.$wrapper.on("click", ".js-remove-note", function(event) {
            event.preventDefault();
            toggleConfirm(true);
        });

        that.$wrapper.on("click", ".js-confirm-cancel", function(event) {
            event.preventDefault();
            toggleConfirm(false);
        });

        function toggleConfirm(show) {
            var active_class = "is-shown";
            if (show) {
                that.$confirm.addClass(active_class);
            } else {
                that.$confirm.removeClass(active_class);
            }
        }
    };

    CRMNoteForm.prototype.remove = function() {
        var that = this,
            href = that.app_url + "?module=note&action=delete",
            data = {
                id: that.id
            };

        if (that.xhr) {
            that.xhr.abort();
        }

        that.xhr = $.post(href, data, function(response) {
            that.$wrapper.closest(".c-activity-item").remove();
        }).always( function() {
            that.xhr = false;
        });
    };

    CRMNoteForm.prototype.save = function() {
        var that = this,
            text = that.$textarea.val(),
            href = that.app_url + "?module=note&action=save",
            data = {
                "data[id]": that.id,
                "data[content]": text
            };

        if (that.xhr) {
            that.xhr.abort();
        }

        that.xhr = $.post(href, data, function(response) {
            var escaped_text = escape(text);

            that.$textarea.data("value", text);
            that.$view.find(".c-text").html( nl2br(escaped_text) );
            that.toggleContent(that.$view);
        }).always( function() {
            that.xhr = false;
        });
    };

    CRMNoteForm.prototype.toggleContent = function( $content ) {
        var that = this;

        if (that.$activeContent.length) {
            that.$activeContent.removeClass(that.shown_class);
        }
        $content.addClass(that.shown_class);
        that.$activeContent = $content;

        if ($content[0] == that.$view[0]) {
            that.$log.removeClass("is-edit");
        }
        if ($content[0] == that.$edit[0]) {
            that.$log.addClass("is-edit");
        }
    };

    return CRMNoteForm;

    function nl2br(string) {
        return string.replace(/(?:\r\n|\r|\n)/g, '<br>');
    }

    function escape(string) {
        return $("<div />").text(string).html();
    }

})(jQuery);
