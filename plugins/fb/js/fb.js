/**
 * Trigger a callback when 'this' image is loaded:
 * @param {Function} callback
 */
(function($){
    $.fn.FbImgLoad = function(callback) {
        return this.each(function() {
            if (callback) {
                if (this.complete || /*for IE 10-*/ $(this).height() > 0) {
                    callback.apply(this);
                }
                else {
                    $(this).on('load', function(){
                        callback.apply(this);
                    });
                }
            }
        });
    };
})(jQuery);

var CRMFbPluginSourceSettingsBlock = ( function($) {

    CRMFbPluginSourceSettingsBlock = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMFbPluginSourceSettingsBlock.prototype.initClass = function() {
        var that = this;

        that.initUrlInputs();
    };

    CRMFbPluginSourceSettingsBlock.prototype.initUrlInputs = function () {
        var that = this,
            $input = that.$wrapper.find('.js-url-input');

        $input.click(function () {
            $(this).select();
        });
    };

    return CRMFbPluginSourceSettingsBlock;

})(jQuery);

/* Reply form in Conversation */
var CRMFbPluginConversationSenderForm = ( function($) {

    CRMFbPluginConversationSenderForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$textarea_wrapper = that.$form.find('.js-textarea-wrapper');
        that.$textarea = that.$textarea_wrapper.find('.js-textarea');
        that.$send_actions = that.$textarea_wrapper.find('.js-send-actions');
        that.$char_count = that.$send_actions.find('.js-char-count');
        that.$send_icon = that.$send_actions.find('.js-message-send');
        that.$attachment_icon = that.$textarea_wrapper.find('.js-message-attachment');

        // VARS
        that.file_template_html = options["file_template_html"];
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];

        // DYNAMIC VARS
        that.files_controller = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMFbPluginConversationSenderForm.prototype.initClass = function() {
        var that = this;

        //
        that.initAutoResize();
        //
        that.initSubmit();

        that.initErrorCleaner();
    };

    CRMFbPluginConversationSenderForm.prototype.initAutoResize = function() {
        var that = this,
            $textarea = that.$textarea,
            char_limit = 1800,
            char_alert = char_limit - 200;

        toogleTextareaWidth();

        $(window).on('resize', toogleTextareaWidth);

        // Text wrapper width
        function toogleTextareaWidth() {
            var textarea_wrapper_width = that.$textarea_wrapper.width(),
                textarea_width = textarea_wrapper_width - (that.$send_actions.width() + that.$attachment_icon.width() + 20);
            that.$textarea.width(textarea_width);
        }

        // Height
        $textarea.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46),
                value = $textarea.val(),
                value_length = value.length,
                new_lines_length = value.split(/\r*\n/).length;

            if (value_length > 0) {
                that.$send_actions.show();
            } else {
                that.$send_actions.hide();
            }

            if (value_length >= char_alert) {
                var limit = char_limit - (value_length + new_lines_length);
                that.$char_count.text(limit).show();
            } else {
                that.$char_count.text('').hide();
            }

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var position = $textarea.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;

                    $textarea.val(result);
                }

                toggleHeight();

            } else if (is_backspace || is_delete) {
                toggleHeight();

            } else {

                if ($textarea[0].scrollHeight > $textarea.outerHeight()) {
                    toggleHeight();
                }

            }

            function toggleHeight() {
                $textarea.css("min-height", 0);

                var scroll_h = $textarea[0].scrollHeight,
                    limit = (18 * 8 + 8);

                if (scroll_h > limit) {
                    scroll_h = limit;
                }

                scroll_h -= 4;

                $textarea.css("min-height", scroll_h + "px");

                that.$wrapper.trigger("resize");
            }
        });
    };

    CRMFbPluginConversationSenderForm.prototype.getFilesController = function() {
        var that = this;
        // DOM
        var $drop_area = that.$textarea_wrapper,
            $default_drop_area = $drop_area.find('.js-default-drop-area'),
            $drop_text_area = $drop_area.find('.js-drop-text'),
            $upload_list = that.$wrapper.find('.js-upload-list'),
            $input_field = that.$wrapper.find(".js-drop-field");

        // VARS
        var file_template_html = that.file_template_html,
            uri = $.crm.app_url + "?module=file&action=uploadTmp";

        // DYNAMIC VARS
        var files_storage = [],
            upload_file_count = 0,
            hover_timeout = 0;

        that.$attachment_icon.on('click', function () {
            $input_field.click();
        });

        // Attach
        $input_field.on("change", function(e) {
            e.preventDefault();
            addFiles(this.files);
            that.$form.submit();
        });

        // Drop
        $drop_area.on("drop", function(e) {
            e.preventDefault();
            addFiles(e.originalEvent.dataTransfer.files);
            that.$form.submit();
        });

        // Drag
        $drop_area.on("dragover", onHover);

        // Delete
        that.$wrapper.on("click", ".js-file-delete", function(e) {
            e.preventDefault();
            deleteFile( $(this).closest(".c-upload-item") )
        });

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                that.$textarea.val('');
                $input_field.val('');
            }
        }

        function renderFile(file) {
            var $upload_item = $(file_template_html),
                $name = $upload_item.find(".js-name");

            $name.text(file.name);
            $upload_list.prepend($upload_item);
            that.$wrapper.trigger("resize");
            return $upload_item;
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
            that.$wrapper.trigger("resize");
        }

        function uploadFiles(data, callback) {
            var is_locked = false,
                afterUploadFiles = ( callback ? callback : function() {} );

            if (files_storage.length) {
                upload_file_count = files_storage.length;

                $.each(files_storage, function(index, file_item) {
                    uploadFile(file_item);
                });
            } else {
                afterUploadFiles();
            }

            function uploadFile(file_item) {
                is_locked = true;

                var $file = file_item.$file,
                    $deleted_icon = $file.find('.js-file-delete'),
                    $status = $file.find(".js-status");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    request();
                } else {
                    $status.addClass("errormsg").text( 'fize_size_error' );
                    $file.find(".c-progress-wrapper").remove();
                    is_locked = false;
                    setTimeout( function() {
                        if ($.contains(document, $file[0])) {
                            $file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                afterUploadFiles();
                            }
                        }
                    }, 1000);
                }

                //

                function request() {
                    var form_data = new FormData(),
                        matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        form_data.append("_csrf", csrf);
                    }

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                form_data.append(item.name, item.value);
                            }
                        });
                    }

                    form_data.append("file_size", file_item.file.size);
                    form_data.append("files", file_item.file);
                    form_data.append("file_end", 1);

                    // Ajax request
                    $.ajax({
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function(e){
                                if (event.lengthComputable) {
                                    var percent = parseInt( (e.loaded / e.total) * 100 );

                                    $status.text(percent + "%");
                                }
                            }, false);
                            return xhr;
                        },
                        url: uri,
                        data: form_data,
                        cache: false,
                        contentType: false,
                        processData: false,
                        type: 'POST',
                        success: function(data){
                            $deleted_icon.removeClass('js-file-delete').find('.icon10').attr("class", "icon10 yes");
                            $status.text(''); // remove percent
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
                                }
                            }, 500);
                        }
                    }).always( function () {
                        is_locked = false;
                    });
                }
            }
        }

        function onHover(e) {
            e.preventDefault();
            $default_drop_area.hide();
            $drop_text_area.show();
            that.$wrapper.trigger("resize");
            clearTimeout(hover_timeout);
            hover_timeout = setTimeout( function () {
                $default_drop_area.show();
                $drop_text_area.hide();
                that.$wrapper.trigger("resize");
            }, 100);
        }

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMFbPluginConversationSenderForm.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $upload_list = that.$wrapper.find('.js-upload-list'),
            $textarea = that.$textarea;

        $textarea.on("keydown", function(e) {
            var use_enter = (e.keyCode === 13 || e.keyCode === 10);
            if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
                e.preventDefault();
                that.$form.submit();
            }
        });

        that.$send_icon.on('click', function () {
            that.$form.submit();
        });

        that.$form.on("submit", function(event) {
            event.preventDefault();
            if (!is_locked) {
                is_locked = true;

                that.clearErrors();

                if ($upload_list.is(':empty') && !$.trim($textarea.val())) {
                    $textarea.addClass('shake animated').focus();
                    setTimeout(function(){
                        $textarea.removeClass('shake animated').focus();
                    },500);
                    is_locked = false;
                    return;
                }
                that.$textarea.prop("readonly", true);
                that.$textarea_wrapper.addClass('is-disabled');
                var files_data = [
                    {
                        "name": "hash",
                        "value": that.hash
                    }
                ];
                that.files_controller.uploadFiles(files_data, submit);

                function submit() {
                    var href = $.crm.app_url + "?plugin=fb&action=sendReply",
                        data = that.$form.serializeArray();

                    $.post(href, data, function (r) {
                        if (r.status === "ok") {
                            that.$textarea.val('');
                            $.crm.content.reload();
                        } else {
                            that.$textarea.prop("readonly", false);
                            that.$textarea_wrapper.removeClass('is-disabled');
                            $textarea.addClass('shake animated').focus();
                            setTimeout(function () {
                                $textarea.removeClass('shake animated').focus();
                                that.showErrors(r && r.errors ? r.errors : {});
                            }, 500);
                            is_locked = false;
                        }
                    }).always(function () {
                        that.$textarea.prop("readonly", false);
                        that.$textarea_wrapper.removeClass('is-disabled');
                        is_locked = false;
                    });
                }
            }
        });
    };

    CRMFbPluginConversationSenderForm.prototype.showErrors = function (errors) {
        return CRMFbPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMFbPluginConversationSenderForm.prototype.clearErrors = function () {
        return CRMFbPluginSenderHelper.clearErrors(this.$wrapper);
    };

    CRMFbPluginConversationSenderForm.prototype.initErrorCleaner = function () {
        return CRMFbPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    return CRMFbPluginConversationSenderForm;

})(jQuery);

/* Viewer dialog ./templates/source/message/ViewerDialog.html */
var CRMFbPluginViewerDialog = ( function($) {

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

    CRMFbPluginViewerDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$button = that.$wrapper.find('.js-reply-button');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.send_dialog = null;
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMFbPluginViewerDialog.prototype.initClass = function () {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.initMessageDeleteLink();
        that.initMessageReplyBtn();

        that.$wrapper.find('.c-fb-image').FbImgLoad(function(){
            that.dialog.resize();
        });

        var video = that.$wrapper.find('.c-fb-video');
        function checkLoad() {
            if (!video[0]) {
                return false;
            }

            if (video[0].readyState === 4) {
                that.dialog.resize();
            } else {
                setTimeout(checkLoad, 100);
            }
        }
        checkLoad();
    };

    CRMFbPluginViewerDialog.prototype.initMessageReplyBtn = function() {
        var that = this,
            $wrapper = that.$wrapper,
            dialog = that.dialog,
            $footer = $wrapper.find('.js-dialog-footer'),
            $loading = $('<i class="icon16 loading" style="vertical-align: middle; margin-left: 6px;"></i>'),
            $button = $wrapper.find('.js-reply-button');

        $button.click(function () {
            $button.attr('disabled', true);
            var onShowSendDialog = function () {
                $loading.remove();
                $button.attr('disabled', false);
                dialog.hide();
            };
            if (that.send_dialog) {
                that.send_dialog.show();
                onShowSendDialog();
            } else {
                loadSendDialog(function() {
                    onShowSendDialog();
                    var onClose = that.dialog.onClose;
                    that.dialog.onClose = function () {
                        onClose.apply(this, arguments);

                        // to prevent recursion in onClose callbacks
                        that.dialog = null;

                        // close send_dialog if not closed yet
                        that.send_dialog && that.send_dialog.close();
                        that.send_dialog = null;
                    };
                });
            }
        });

        function loadSendDialog(whenLoaded) {
            $footer.append($loading);
            var href = that.app_url+'?module=message&action=writeReplyDialog',
                params = { id: that.message.id };
            $.post(href, params, function(html) {
                new CRMDialog({
                    html: html,
                    onOpen: function ($dialog, send_dialog) {
                        that.send_dialog = send_dialog;
                        $dialog.find('.js-cancel-dialog').click(function () {
                            that.send_dialog.hide();
                            dialog.show();
                        });
                        whenLoaded && whenLoaded();
                    },
                    onClose: function () {

                        // to prevent recursion in onClose callbacks
                        that.send_dialog = null;

                        // close dialog if not closed yet
                        that.dialog && that.dialog.close();
                        that.dialog = null;
                    }
                });
            });
        };
    };

    CRMMessageDeleteLinkMixin.mixInFor(CRMFbPluginViewerDialog);

    return CRMFbPluginViewerDialog;

})(jQuery);

/* Sender dialog ./templates/source/message/SenderDialog.html */
var CRMFbPluginSenderDialog = ( function($) {

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

    CRMFbPluginSenderDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$textarea_wrapper = that.$form.find('.js-textarea-wrapper');
        that.$textarea = that.$form.find('.js-textarea');
        that.$attachment_icon = that.$textarea_wrapper.find('.js-message-attachment');
        that.$char_count = that.$form.find('.js-char-count');
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.success_html = options.success_html || '';
        that.file_template_html = options["file_template_html"];
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        // DYNAMIC VARS
        that.files_controller = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMFbPluginSenderDialog.prototype.initClass = function () {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.$textarea_wrapper.on('click', function () {
            that.$textarea.focus();
        });

        that.$textarea.focus();

        that.initSendMessage();
        //
        that.initErrorCleaner();
        //
        that.initResizeTextarea();
    };

    CRMFbPluginSenderDialog.prototype.getFilesController = function() {
        var that = this;
        // DOM
        var $drop_area = that.$textarea_wrapper,
            $default_drop_area = $drop_area.find('.js-default-drop-area'),
            $drop_text_area = $drop_area.find('.js-drop-text'),
            $upload_list = that.$wrapper.find('.js-upload-list'),
            $input_field = that.$wrapper.find(".js-drop-field");

        // VARS
        var file_template_html = that.file_template_html,
            uri = $.crm.app_url + "?module=file&action=uploadTmp";

        // DYNAMIC VARS
        var files_storage = [],
            upload_file_count = 0,
            hover_timeout = 0;

        that.$attachment_icon.on('click', function () {
            $input_field.click();
        });

        // Attach
        $input_field.on("change", function(e) {
            e.preventDefault();
            addFiles(this.files);
            that.$form.submit();
        });

        // Drop
        $drop_area.on("drop", function(e) {
            e.preventDefault();
            addFiles(e.originalEvent.dataTransfer.files);
            that.$form.submit();
        });

        // Drag
        $drop_area.on("dragover", onHover);

        // Delete
        that.$wrapper.on("click", ".js-file-delete", function(e) {
            e.preventDefault();
            deleteFile( $(this).closest(".c-upload-item") )
        });

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                that.$textarea.val('');
                $input_field.val('');
            }
        }

        function renderFile(file) {
            var $upload_item = $(file_template_html),
                $name = $upload_item.find(".js-name");

            $name.text(file.name);
            $upload_list.prepend($upload_item);
            that.$wrapper.trigger("resize");
            return $upload_item;
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
            that.$wrapper.trigger("resize");
        }

        function uploadFiles(data, callback) {
            var is_locked = false,
                afterUploadFiles = ( callback ? callback : function() {} );

            if (files_storage.length) {
                upload_file_count = files_storage.length;

                $.each(files_storage, function(index, file_item) {
                    uploadFile(file_item);
                });
            } else {
                afterUploadFiles();
            }

            function uploadFile(file_item) {
                is_locked = true;

                var $file = file_item.$file,
                    $deleted_icon = $file.find('.js-file-delete'),
                    $status = $file.find(".js-status");

                $file.addClass("is-upload");

                if (that.max_upload_size > file_item.file.size) {
                    request();
                } else {
                    $status.addClass("errormsg").text( 'fize_size_error' );
                    $file.find(".c-progress-wrapper").remove();
                    is_locked = false;
                    setTimeout( function() {
                        if ($.contains(document, $file[0])) {
                            $file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                afterUploadFiles();
                            }
                        }
                    }, 1000);
                }

                //

                function request() {
                    var form_data = new FormData(),
                        matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)")),
                        csrf = matches ? decodeURIComponent(matches[1]) : '';

                    if (csrf) {
                        form_data.append("_csrf", csrf);
                    }

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                form_data.append(item.name, item.value);
                            }
                        });
                    }

                    form_data.append("file_size", file_item.file.size);
                    form_data.append("files", file_item.file);
                    form_data.append("file_end", 1);

                    // Ajax request
                    $.ajax({
                        xhr: function() {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function(e){
                                if (event.lengthComputable) {
                                    var percent = parseInt( (e.loaded / e.total) * 100 );

                                    $status.text(percent + "%");
                                }
                            }, false);
                            return xhr;
                        },
                        url: uri,
                        data: form_data,
                        cache: false,
                        contentType: false,
                        processData: false,
                        type: 'POST',
                        success: function(data){
                            $deleted_icon.removeClass('js-file-delete').find('.icon10').attr("class", "icon10 yes");
                            $status.text(''); // remove percent
                            setTimeout( function() {
                                if ($.contains(document, $file[0])) {
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
                                }
                            }, 500);
                        }
                    }).always( function () {
                        is_locked = false;
                    });
                }
            }
        }

        function onHover(e) {
            e.preventDefault();
            $default_drop_area.hide();
            $drop_text_area.show();
            that.$wrapper.trigger("resize");
            clearTimeout(hover_timeout);
            hover_timeout = setTimeout( function () {
                $default_drop_area.show();
                $drop_text_area.hide();
                that.$wrapper.trigger("resize");
            }, 100);
        }

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMFbPluginSenderDialog.prototype.initResizeTextarea = function () {
        var that = this,
            $textarea = that.$textarea,
            char_limit = 1800,
            char_alert = char_limit - 200;

        $textarea.on("keyup", function(e) {
            var is_enter = (e.keyCode === 13 || e.keyCode === 10),
                is_backspace = (e.keyCode === 8),
                is_delete = (e.keyCode === 46),
                value = $textarea.val(),
                value_length = value.length,
                new_lines_length = value.split(/\r*\n/).length;

            if (value_length >= char_alert) {
                var limit = char_limit - (value_length + new_lines_length);
                that.$char_count.text(limit).show();
            } else {
                that.$char_count.text('').hide();
            }

            if (is_enter && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                if (!e.shiftKey) {
                    var position = $textarea.prop("selectionStart"),
                        left = value.slice(0, position),
                        right = value.slice(position),
                        result = left + "\n" + right;

                    $textarea.val(result);
                }

                toggleHeight();

            } else if (is_backspace || is_delete) {
                toggleHeight();

            } else {

                if ($textarea[0].scrollHeight > $textarea.outerHeight()) {
                    toggleHeight();
                }

            }

            function toggleHeight() {
                $textarea.css("min-height", 0);

                var scroll_h = $textarea[0].scrollHeight - 3,
                    limit = (18 * 8 + 8);

                if (scroll_h > limit) {
                    scroll_h = limit;
                }

                $textarea.css("min-height", scroll_h + "px");

                that.$wrapper.trigger("resize");
            }
        });
    };

    CRMFbPluginSenderDialog.prototype.initSendMessage = function () {
        var that = this,
            is_locked = false,
            $upload_list = that.$wrapper.find('.js-upload-list'),
            $textarea = that.$textarea,
            $form = that.$form,
            $button = that.$button;

        $textarea.on("keydown", function(e) {
            var use_enter = (e.keyCode === 13 || e.keyCode === 10);
            if (use_enter && !(e.ctrlKey || e.metaKey || e.shiftKey) ) {
                e.preventDefault();
                $form.submit();
            }
        });

        $form.on("submit", function(e) {
            e.preventDefault();
            if (!is_locked) {
                is_locked = true;

                if ($upload_list.is(':empty') && !$.trim($textarea.val())) {
                    $textarea.addClass('shake animated').focus();
                    setTimeout(function () {
                        $textarea.removeClass('shake animated').focus();
                    }, 500);
                    is_locked = false;
                    return;
                }
                that.$textarea.prop("readonly", true);
                that.$textarea_wrapper.addClass('is-disabled');

                that.clearErrors();

                var xhr = null,
                    $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');
                $button.attr('disabled', true).after($loading);

                var files_data = [
                    {
                        "name": "hash",
                        "value": that.hash
                    }
                ];
                that.files_controller.uploadFiles(files_data, submit);

                function onAlways() {
                    $button.attr('disabled', false);
                    $loading.remove();
                    xhr = null;
                }

                function onDone(r) {
                    if (r.status === "ok") {
                        that.$wrapper.find(".crm-dialog-block").html(that.success_html);
                        that.dialog.resize();

                        var auto_close_timer = null,
                            old_close_func = that.dialog.onClose;
                        that.dialog.onClose = function () {
                            old_close_func();
                            auto_close_timer && clearTimeout(auto_close_timer);
                            auto_close_timer = null;
                            that.crm.content.reload();
                        };

                        auto_close_timer = setTimeout(function () {
                            that.dialog.close();
                        }, 2500);
                    } else {
                        $textarea.addClass('shake animated').focus();
                        setTimeout(function () {
                            $textarea.removeClass('shake animated').focus();
                        }, 500);

                        if (r.status !== "ok") {
                            onFail(r);
                            return;
                        }
                    }
                }

                function onFail(r) {
                    if (r && $.isPlainObject(r) && !$.isEmptyObject(r.errors)) {
                        that.showErrors(r.errors);
                    } else {
                        console.error(r ? ["Server error", r] : "Server error");
                    }
                }

                function submit() {
                    var href = $.crm.app_url + "?plugin=fb&action=sendReply",
                        data = that.$form.serializeArray();

                    xhr && xhr.abort();
                    xhr = $.post(href, data)
                        .done(onDone)
                        .fail(onFail)
                        .always(onAlways);
                }
            }
        });

        $form.on('click', '.js-cancel-dialog', function () {
            that.$wrapper.css({'display': 'none'});
        });
    };

    CRMFbPluginSenderDialog.prototype.showErrors = function (errors) {
        return CRMFbPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMFbPluginSenderDialog.prototype.clearErrors = function () {
        return CRMFbPluginSenderHelper.clearErrors(this.$wrapper);
    };

    CRMFbPluginSenderDialog.prototype.initErrorCleaner = function () {
        return CRMFbPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    return CRMFbPluginSenderDialog;

})(jQuery);

// Static sender helpers
var CRMFbPluginSenderHelper = {
    showErrors: function ($wrapper, errors) {
        var $error_wrapper = $wrapper.find('.js-fb-error-wrapper');
        $.each(errors, function (name, error) {
            var $field = $wrapper.find('[name="' + name + '"]'),
                $error = '<em class="errormsg">' + error + '</em>';
            if ($field.length) {
                $field.addClass('error');
                $field.after($error);
            } else {
                $error_wrapper.show().append($error);
            }
        });
    },
    clearErrors: function ($wrapper) {
        var $error_wrapper = $wrapper.find('.js-fb-error-wrapper');
        $wrapper.find('.error').removeClass('error');
        $wrapper.find('.errormsg').remove();
        $error_wrapper.hide().empty();
    },
    initErrorCleaner: function ($wrapper) {

        function handler() {
            CRMFbPluginSenderHelper.clearErrors($wrapper);
        }

        var timer = null;
        $wrapper.on('keydown', ':input', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                handler();
            }, 250);
        });
        $wrapper.on('change', ':input', handler);
    }
};
