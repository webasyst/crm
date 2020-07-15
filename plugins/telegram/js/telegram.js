/**
 * Trigger a callback when 'this' image is loaded:
 * @param {Function} callback
 */
(function($){
    $.fn.TelegramImgLoad = function(callback) {
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

var CRMTelegramPluginSettings = ( function($) {

    CRMTelegramPluginSettings = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.parents('.crm-source-settings-form');
        that.$name_input = that.$wrapper.find('.js-name-input');
        that.$token_input = that.$wrapper.find('.js-access-token-input');
        that.$start_response_textarea = that.$wrapper.find('.start_response_textarea');
        that.$bot_id_input = that.$wrapper.find('.js-bot-id-input');
        that.$username_input = that.$wrapper.find('.js-username-input');
        that.$firstname_input = that.$wrapper.find('.js-firstname-input');
        that.$api_offset_input = that.$wrapper.find('.js-api-offset-input');

        // VARS
        that.action = options["action"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMTelegramPluginSettings.prototype.initClass = function() {
        var that = this;

        that.checkToken();
        //
        that.initStartResponse();
    };

    CRMTelegramPluginSettings.prototype.checkToken = function() {
        var that = this;

        that.$token_input.on('input', function() {
            that.$bot_id_input.val();
            if (that.action == 'create') {
                that.$bot_id_input.val('');
            }
            that.$bot_id_input.val();
            that.$firstname_input.val('');
            that.$username_input.val('');

            var href = $.crm.app_url + "?plugin=telegram&action=checkToken",
                data = {
                    access_token: $(this).val()
                };

            $.post(href, data, function(res) {
                if (res.status == 'ok' && res.data.ok && !res.data.errors) {
                    if (that.action == 'edit' && that.$bot_id_input.val() != res.data.result.id) {
                        // One source — one telegram bot
                        $.crm.alert.show({
                            title: that.locales['alert_title'],
                            text: that.locales['alert_body'],
                            button: that.locales['alert_close']
                        });
                        return false;
                    }
                    that.$bot_id_input.val($.crm.escape(res.data.result.id));
                    that.$firstname_input.val($.crm.escape(res.data.result.first_name));
                    that.$username_input.val($.crm.escape(res.data.result.username));
                    if (!that.$name_input.val().length) {
                        that.$name_input.val('@'+ $.crm.escape(res.data.result.username));
                    }
                    if (that.action == 'create') {
                        // Check old updates and save in source params
                        var updates_href = $.crm.app_url + "?plugin=telegram&action=getOldUpdates";
                        $.post(updates_href, data, function (res) {
                            if (res.status == 'ok' && res.data.last_update_id) {
                                that.$api_offset_input.val($.crm.escape(res.data.last_update_id));
                            }
                        });
                    }
                }
            });
        });
    };

    CRMTelegramPluginSettings.prototype.initStartResponse = function() {
        var that = this,
            div = $('<div></div>');

        // Init Ace
        that.$start_response_textarea.parent().prepend($('<div class="ace"></div>').append(div));
        that.$start_response_textarea.hide();
        var editor = ace.edit(div.get(0));
        // Set options
        editor.commands.removeCommand('find');
        ace.config.set("basePath", wa_url + 'wa-content/js/ace/');
        editor.setTheme("ace/theme/eclipse");
        editor.renderer.setShowGutter(false);
        var session = editor.getSession();
        session.setMode("ace/mode/smarty");
        if (navigator.appVersion.indexOf('Mac') != -1) {
            editor.setFontSize(13);
        } else if (navigator.appVersion.indexOf('Linux') != -1) {
            editor.setFontSize(16);
        } else {
            editor.setFontSize(14);
        }
        if (that.$start_response_textarea.val().length) {
            session.setValue(that.$start_response_textarea.val());
        } else {
            session.setValue(' ');
        }
        editor.setOption("minLines", 1);
        editor.setOption("maxLines", 100);
        session.on('change', function () {
            that.$start_response_textarea.val(editor.getValue());
        });
    };

    return CRMTelegramPluginSettings;

})(jQuery);

var CRMTelegramPluginSenderDialog = ( function($) {

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

    CRMTelegramPluginSenderDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$textarea = options["$textarea"];
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.photo_template_html = options["photo_template_html"];
        that.file_template_html = options["file_template_html"];
        that.message = options.message || {};
        that.chat_id = options.chat_id || 0;
        that.source_id = options.source_id || 0;
        that.locales = options.locales || {};
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];
        that.success_html = options.success_html || '';
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        that.send_action_url = options["send_action_url"];
        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS
        that.photosController = that.getPhotosController();
        that.filesController = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMTelegramPluginSenderDialog.prototype.initClass = function() {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.initWYSIWYG();
        //
        that.initSendMessage();
        //
        that.initErrorCleaner();

        var video = that.$wrapper.find('.crm-telegram-plugin-video');
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
        //
        that.dialog.resize();
    };

    CRMTelegramPluginSenderDialog.prototype.initWYSIWYG = function() {
        var that = this,
            timer = null,
            options = {
                minHeight: 100,
                maxHeight: 100,
                buttons: ['bold', 'italic', 'link'],
                allowedTags: 'b|strong|a|i|em|pre|code'.split('|'),
                uploadImage: false,
                lang: $.crm.lang,
                callbacks: {
                    keydown: function()
                    {
                        sendChatAction();
                    },
                    paste: function () {
                        sendChatAction();
                    }
                }
            };

        that.$textarea.redactor(options);

        function sendChatAction() {
            timer && clearTimeout(timer);
            timer = setTimeout(function(){
                if (that.chat_id && that.source_id) {
                    var href = $.crm.app_url + "?plugin=telegram&action=sendChatAction",
                        data = {
                            chat_id: that.chat_id,
                            source_id: that.source_id,
                            action: 'typing'
                        };

                    $.post(href, data);
                }
            }, 450);
        }
    };

    CRMTelegramPluginSenderDialog.prototype.getPhotosController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-photos-wrapper");

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = $wrapper.find(".js-drop-area"),
            $dropText = $wrapper.find(".js-drop-text"),
            $fileField = $wrapper.find(".js-drop-field"),
            $uploadList = $wrapper.find(".c-upload-list"),
            photo_template_html = that.photo_template_html;

        // DATA
        var uri = that.app_url + "?module=file&action=uploadTmp";

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

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    var type = file.type.split('/');
                    if (type[0] === 'image') {
                        files_storage.push({
                            $file: renderFile(file),
                            file: file
                        });
                    }
                });
                $fileField.val('');
            }
        }

        function renderFile(file) {
            var $uploadItem = $(photo_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

            $uploadList.prepend($uploadItem);

            that.dialog.resize();

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
                                afterUploadFiles();
                            }
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

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                formData.append(item.name, item.value);
                            }
                        });
                    }

                    formData.append("file_size", file_item.file.size);
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
                                        afterUploadFiles()
                                    }
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

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMTelegramPluginSenderDialog.prototype.getFilesController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-files-wrapper");

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = $wrapper.find(".js-drop-area"),
            $dropText = $wrapper.find(".js-drop-text"),
            $fileField = $wrapper.find(".js-drop-field"),
            $uploadList = $wrapper.find(".c-upload-list"),
            file_template_html = that.file_template_html;

        // DATA
        var uri = that.app_url + "?module=file&action=uploadTmp";

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

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                $fileField.val('');
            }
        }

        function renderFile(file) {
            var $uploadItem = $(file_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

            $uploadList.prepend($uploadItem);

            that.dialog.resize();

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

        function uploadFiles(data, callback) {
            var is_locked = false;

            var afterUploadFiles = ( callback ? callback : function() {} );

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
                                afterUploadFiles();
                            }
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

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                formData.append(item.name, item.value);
                            }
                        });
                    }

                    formData.append("file_size", file_item.file.size);
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
                                        afterUploadFiles()
                                    }
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

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMTelegramPluginSenderDialog.prototype.initSendMessage = function () {
        var that = this,
            $form = that.$form,
            $button = that.$button;

        $form.on("submit", function(e) {
            e.preventDefault();

            var xhr = null,
                $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');

            $button.attr('disabled', true).after($loading);

            that.$textarea.redactor('core.editor').prop('contenteditable', 'false').addClass('disable');
            that.$wrapper.find('.js-drop-field').prop('disabled', true).parent().addClass('disable');

            var photos_data = [
                {
                    "name": "hash",
                    "value": 'photos-' + that.hash
                }
            ];

            that.photosController.uploadFiles(photos_data, uploadFiles);

            function onAlways() {
                $button.attr('disabled', false);
                $loading.remove();
                xhr = null;
            }

            function onDone(r) {
                if (r.status !== "ok") {
                    onFail(r);
                    return;
                }

                that.$wrapper.find(".crm-dialog-block").html(that.success_html);
                that.dialog.resize();

                var auto_close_timer = null,
                    old_close_func = that.dialog.onClose;
                that.dialog.onClose = function() {
                    old_close_func();
                    auto_close_timer && clearTimeout(auto_close_timer);
                    auto_close_timer = null;
                    that.crm.content.reload();
                };

                auto_close_timer = setTimeout(function () {
                    that.dialog.close();
                }, 2500);
            }

            function onFail(r) {
                if (r && !$.isEmptyObject(r.errors)) {
                    that.showErrors(r.errors);
                    that.$textarea.redactor('core.editor').prop('contenteditable', 'true').removeClass('disable');
                    that.$wrapper.find('.js-drop-field').prop('disabled', false).parent().removeClass('disable');
                } else {
                    console.error(r ? ["Server error", r] : "Server error");
                }
            }

            function uploadFiles() {
                var files_data = [
                    {
                        "name": "hash",
                        "value": 'files-' + that.hash
                    }
                ];
                that.filesController.uploadFiles(files_data, submit);
            }

            function submit() {
                xhr && xhr.abort();
                xhr = $.post(that.send_action_url, that.$form.serializeArray())
                    .done(onDone)
                    .fail(onFail)
                    .always(onAlways);
            }
        });

        $form.on('click', '.js-cancel-dialog', function () {
            that.$wrapper.css({'display': 'none'});
        });
    };

    CRMTelegramPluginSenderDialog.prototype.initErrorCleaner = function () {
        CRMTelegramPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    CRMTelegramPluginSenderDialog.prototype.showErrors = function (errors) {
        return CRMTelegramPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMTelegramPluginSenderDialog.prototype.clearErrors = function () {
        return CRMTelegramPluginSenderHelper.clearErrors(this.$wrapper);
    };

    return CRMTelegramPluginSenderDialog;

})(jQuery);

var CRMTelegramPluginViewerDialog = ( function($) {

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

    CRMTelegramPluginViewerDialog = function(options) {
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

    CRMTelegramPluginViewerDialog.prototype.initClass = function () {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.initMessageDeleteLink();
        that.initMessageReplyBtn();

        that.$wrapper.find('.crm-telegram-plugin-photo').TelegramImgLoad(function(){
            that.dialog.resize();
        });

        var video = that.$wrapper.find('.crm-telegram-plugin-video');
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

    CRMTelegramPluginViewerDialog.prototype.initMessageReplyBtn = function() {
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

    CRMMessageDeleteLinkMixin.mixInFor(CRMTelegramPluginViewerDialog);

    return CRMTelegramPluginViewerDialog;

})(jQuery);

var CRMTelegramPluginConversationSenderForm = ( function($) {

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

    CRMTelegramPluginConversationSenderForm = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$textarea = options["$textarea"];
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.photo_template_html = options["photo_template_html"];
        that.file_template_html = options["file_template_html"];
        that.message = options.message || {};
        that.chat_id = options.chat_id || 0;
        that.source_id = options.source_id || 0;
        that.locales = options.locales || {};
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        that.send_action_url = options["send_action_url"];
        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS
        that.photosController = that.getPhotosController();
        that.filesController = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMTelegramPluginConversationSenderForm.prototype.initClass = function() {
        var that = this;

        that.initWYSIWYG();
        //
        that.initSendMessage();

        var video = that.$wrapper.find('.crm-telegram-plugin-video');
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

        that.initErrorCleaner();
    };

    CRMTelegramPluginConversationSenderForm.prototype.initWYSIWYG = function() {
        var that = this,
            timer = null,
            options = {
                minHeight: 80,
                maxHeight: 80,
                buttons: ['bold', 'italic', 'link'],
                allowedTags: 'b|strong|a|i|em|pre|code'.split('|'),
                uploadImage: false,
                lang: $.crm.lang,
                callbacks: {
                    enter: function (e) {
                        that.$form.submit();
                        return false;
                    },
                    keydown: function(e)
                    {
                        if ((e.ctrlKey || e.metaKey) && (e.keyCode == 13 || e.keyCode == 10)) {
                            this.keydown.insertBreakLineProcessing(e);
                            return false;
                        }
                        if (e.keyCode == 13) {
                            that.$form.submit();
                            return false;
                        }
                        sendChatAction();
                    },
                    paste: function () {
                        sendChatAction();
                    }
                }
            };

        that.$textarea.redactor(options);

        function sendChatAction() {
            timer && clearTimeout(timer);
            timer = setTimeout(function(){
                if (that.chat_id && that.source_id) {
                    var href = $.crm.app_url + "?plugin=telegram&action=sendChatAction",
                        data = {
                            chat_id: that.chat_id,
                            source_id: that.source_id,
                            action: 'typing'
                        };

                    $.post(href, data);
                }
            }, 450);
        }
    };

    CRMTelegramPluginConversationSenderForm.prototype.getPhotosController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-photos-wrapper");

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = $wrapper.find(".js-drop-area"),
            $dropText = $wrapper.find(".js-drop-text"),
            $fileField = $wrapper.find(".js-drop-field"),
            $uploadList = $wrapper.find(".c-upload-list"),
            photo_template_html = that.photo_template_html;

        // DATA
        var uri = that.app_url + "?module=file&action=uploadTmp";

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

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    var type = file.type.split('/');
                    if (type[0] === 'image') {
                        files_storage.push({
                            $file: renderFile(file),
                            file: file
                        });
                    }
                });
                $fileField.val('');
            }
        }

        function renderFile(file) {
            var $uploadItem = $(photo_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

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
                            //$file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                afterUploadFiles();
                            }
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

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                formData.append(item.name, item.value);
                            }
                        });
                    }

                    formData.append("file_size", file_item.file.size);
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
                                    //$file.remove();
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
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

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMTelegramPluginConversationSenderForm.prototype.getFilesController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-files-wrapper");

        if (!$wrapper.length) { return false; }

        // DOM
        var $dropArea = $wrapper.find(".js-drop-area"),
            $dropText = $wrapper.find(".js-drop-text"),
            $fileField = $wrapper.find(".js-drop-field"),
            $uploadList = $wrapper.find(".c-upload-list"),
            file_template_html = that.file_template_html;

        // DATA
        var uri = that.app_url + "?module=file&action=uploadTmp";

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

        //

        function addFiles( files ) {
            if (files.length) {
                $.each(files, function(index, file) {
                    files_storage.push({
                        $file: renderFile(file),
                        file: file
                    });
                });
                $fileField.val('');
            }
        }

        function renderFile(file) {
            var $uploadItem = $(file_template_html),
                $name = $uploadItem.find(".js-name");

            $name.text(file.name);

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

        function uploadFiles(data, callback) {
            var is_locked = false;

            var afterUploadFiles = ( callback ? callback : function() {} );

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
                            //$file.remove();
                            upload_file_count -= 1;
                            if (upload_file_count <= 0) {
                                afterUploadFiles();
                            }
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

                    if (data && data.length) {
                        $.each(data, function(index, item) {
                            if (item.name && item.value) {
                                formData.append(item.name, item.value);
                            }
                        });
                    }

                    formData.append("file_size", file_item.file.size);
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
                                    //$file.remove();
                                    upload_file_count -= 1;
                                    if (upload_file_count <= 0) {
                                        afterUploadFiles()
                                    }
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

        return {
            uploadFiles: uploadFiles
        }
    };

    CRMTelegramPluginConversationSenderForm.prototype.initSendMessage = function () {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", function(e) {
            if (!is_locked) {
                is_locked = true;
                that.$textarea.redactor('core.editor').prop('contenteditable', 'false').addClass('disable');
                that.$wrapper.find('.js-drop-field').prop('disabled', true).parent().addClass('disable');

                that.clearErrors();

                e.preventDefault();
                var xhr = null;

                var photos_data = [
                    {
                        "name": "hash",
                        "value": 'photos-' + that.hash
                    }
                ];

                that.photosController.uploadFiles(photos_data, uploadFiles);
            }

            function onAlways() {
                that.$textarea.redactor('core.editor').prop('contenteditable', 'true').removeClass('disable');
                that.$wrapper.find('.js-drop-field').prop('disabled', false).parent().removeClass('disable');
                xhr = null;
            }

            function onDone(r) {
                if (r.status !== "ok") {
                    onFail(r);
                    return;
                }

                $.crm.content.reload();
            }

            function onFail(r) {
                is_locked = false;
                that.$textarea.redactor('core.editor').prop('contenteditable', 'true').removeClass('disable').addClass('shake animated');
                that.$wrapper.find('.js-drop-field').prop('disabled', false).parent().removeClass('disable');
                setTimeout(function(){
                    that.$textarea.redactor('core.editor').removeClass('shake animated');
                    that.$textarea.redactor('core.editor').focus();
                    if (r && $.isPlainObject(r) && r.errors && !$.isEmptyObject(r.errors)) {
                        that.showErrors(r.errors);
                    }
                },500);
            }

            function uploadFiles() {
                var files_data = [
                    {
                        "name": "hash",
                        "value": 'files-' + that.hash
                    }
                ];
                that.filesController.uploadFiles(files_data, submit);
            }

            function submit() {
                xhr && xhr.abort();
                xhr = $.post(that.send_action_url, that.$form.serializeArray())
                    .done(onDone)
                    .fail(onFail)
                    .always(onAlways);
            }
        });
    };

    CRMTelegramPluginConversationSenderForm.prototype.initErrorCleaner = function () {
        CRMTelegramPluginSenderHelper.initErrorCleaner(this.$wrapper);
    };

    CRMTelegramPluginConversationSenderForm.prototype.showErrors = function (errors) {
        return CRMTelegramPluginSenderHelper.showErrors(this.$wrapper, errors);
    };

    CRMTelegramPluginConversationSenderForm.prototype.clearErrors = function () {
        return CRMTelegramPluginSenderHelper.clearErrors(this.$wrapper);
    };

    return CRMTelegramPluginConversationSenderForm;

})(jQuery);

// Static singleton helper
var CRMTelegramPluginSenderHelper = {

    showErrors: function ($wrapper, errors) {
        $.each(errors, function (name, error) {
            var $field = $wrapper.find('[name="' + name + '"]'),
                $error = '<em class="errormsg">' + error + '</em>';
            if ($field.length) {
                $field.addClass('error');
                $field.after($error);
            } else {
                $wrapper.find('.js-errors-place').append($error);
            }
        });
    },

    clearErrors: function ($wrapper) {
        $wrapper.find('.error').removeClass('error');
        $wrapper.find('.errormsg').remove();
        $wrapper.find('.js-errors-place').empty();
    },

    initErrorCleaner: function ($wrapper) {

        function handler() {
            CRMTelegramPluginSenderHelper.clearErrors($wrapper);
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
