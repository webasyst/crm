var CRMVkPluginSenderDialog = ( function($) {

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

    CRMVkPluginSenderDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find(':submit');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.message = options.message || {};
        that.locales = options.locales || {};
        that.success_html = options.success_html || '';
        that.crm = getCRM();

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        that.send_action_url = options["send_action_url"];
        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // photos & files stuff
        that.photo_template_html = options["photo_template_html"];
        that.file_template_html = options["file_template_html"];
        that.hash = options["hash"];
        that.max_upload_size = options["max_upload_size"];

        // DYNAMIC VARS
        that.photosController = that.getPhotosController();
        that.filesController = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMVkPluginSenderDialog.prototype.initClass = function () {
        var that = this;

        that.initSendMessage();

        that.initErrorCleaner();
    };

    // TODO: make one general uploader component and move it in separate js file
    CRMVkPluginSenderDialog.prototype.getPhotosController = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-photos-wrapper");

        if (!$wrapper.length) {
            return false;
        }

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

        function addFiles(files) {
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

    // TODO: make one general uploader component and move it in separate js file
    CRMVkPluginSenderDialog.prototype.getFilesController = function() {
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

    CRMVkPluginSenderDialog.prototype.initErrorCleaner = function () {
        var that = this,
            $wrapper = that.$wrapper;

        function handler() {
            that.clearErrors();
        }

        var timer = null;
        $wrapper.on('keydown', ':input', function () {
            timer && clearTimeout(timer);
            timer = setTimeout(function () {
                handler();
            }, 250);
        });
        $wrapper.on('change', ':input', handler);
    };

    CRMVkPluginSenderDialog.prototype.initSendMessage = function () {
        var that = this,
            $form = that.$form,
            $button = that.$button,
            $textarea = $form.find('.js-textarea'),
            $drop_field = that.$wrapper.find('.js-drop-field').prop('disabled', false).parent(),
            is_locked = false;

        $form.on("submit", function(e) {
            e.preventDefault();
            if (is_locked) {
                return;
            }
            is_locked = true;

            $drop_field.addClass('disable');

            var xhr = null,
                $loading = $();

            var photos_data = [
                {
                    "name": "hash",
                    "value": 'photos-' + that.hash
                }
            ];

            that.photosController.uploadFiles(photos_data, uploadFiles);

            function uploadFiles() {
                var files_data = [
                    {
                        "name": "hash",
                        "value": 'files-' + that.hash
                    }
                ];
                that.filesController.uploadFiles(files_data, submit);
            }

            function onAlways() {
                $button.attr('disabled', false);
                $textarea.attr('disabled', false);
                $loading.remove();
                $drop_field.removeClass('disable');
                xhr = null;
            };

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
            };

            function onFail(r) {
                if (r && !$.isEmptyObject(r.errors)) {
                    that.showErrors(r.errors);
                } else {
                    console.error(r ? ["Server error", r] : "Server error");
                }
                is_locked = false;
            };

            function submit() {
                var url = that.send_action_url,
                    data = that.$form.serializeArray();

                $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');
                $button.attr('disabled', true).after($loading);
                $textarea.attr('disabled', true);
                xhr && xhr.abort();

                xhr = $.post(url, data)
                    .done(onDone)
                    .fail(onFail)
                    .always(onAlways);
            };
        });

        $form.on('click', '.js-cancel-dialog', function () {
            that.$wrapper.css({'display': 'none'});
        });
    };

    CRMVkPluginSenderDialog.prototype.showErrors = function (errors) {
        var that = this,
            $wrapper = that.$wrapper;
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
    };

    CRMVkPluginSenderDialog.prototype.clearErrors = function () {
        var that = this,
            $wrapper = that.$wrapper;
        $wrapper.find('.error').removeClass('error');
        $wrapper.find('.errormsg').remove();
        $wrapper.find('.js-errors-place').empty();
    };

    return CRMVkPluginSenderDialog;

})(jQuery);
