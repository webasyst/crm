var CRMSendEmailDialog = ( function($) {

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

    CRMSendEmailDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$textarea = that.$wrapper.find(".js-wysiwyg");
        that.$sender_email_select = that.$wrapper.find(".js-sender-email-select");
        that.$sender_email = that.$wrapper.find(".js-sender-email");
        that.$to_input = that.$wrapper.find('.js-to-input');
        that.$to_id = that.$wrapper.find('.js-to-id');

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.success_html = options["success_html"];
        that.error_html = options["error_html"];
        that.file_template_html = options["file_template_html"];
        that.max_upload_size = options["max_upload_size"];
        that.locales = options["locales"];
        that.hash = options["hash"];
        that.send_action_url = options["send_action_url"];
        that.body = options["body"] || '';
        that.crm = getCRM();
        that.action = options["action"];
        that.deal_id = options["deal_id"];
        that.is_admin = options["is_admin"];

        that.app_url = options["crm_app_url"] || (that.crm && that.crm.app_url);

        if (!that.send_action_url) {
            throw new Error('send_action_url option required');
        }

        // DYNAMIC VARS
        that.filesController = that.getFilesController();

        // INIT
        that.initClass();
    };

    CRMSendEmailDialog.prototype.initClass = function() {
        var that = this;

        //
        that.initWYSIWYG();
        //
        that.initSendMessage();
        //
        that.initPersonalSettingsDialog();
        //
        that.senderEmailSelect();
        //
        that.initEmailCopy();
        //
        if (that.action == "new") {
            if (that.$to_id.val() == 0) {
                that.initEmailTo();
            }
            that.initSelectDeal();
            that.$to_input.focus();
        }
        if (that.action == "forward") {
            that.initEmailTo();
            that.$to_input.focus();
            if (!that.deal_id) {
                that.initSelectDeal();
            }
        }
        if (that.action == "reply" && !that.deal_id) {
            that.initSelectDeal();
        }
        //
        that.dialog.resize();
    };

    CRMSendEmailDialog.prototype.getFilesController = function() {
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

    CRMSendEmailDialog.prototype.initWYSIWYG = function() {
        var that = this,
            $textarea = that.$textarea,
            dialog_height = 0;

        that.crm.initWYSIWYG($textarea, {
            allowedAttr: [ ['section', 'data-role'] ],
            callbacks: {
                change: onChange
            }
        });

        if (that.body) {
            $textarea.redactor('code.set', that.body);
        }

        var old_close_func = that.dialog.onClose;

        that.dialog.onClose = function() {
            old_close_func(arguments);
            destroyRedactor($textarea);
        };

        function onChange() {
            var _dialog_height = that.dialog.$block.height(),
                use_resize = (!dialog_height || dialog_height !== _dialog_height);

            if (use_resize) {
                dialog_height = _dialog_height;
                that.dialog.resize();
            }
        }
    };

    CRMSendEmailDialog.prototype.initSendMessage = function() {
        var that = this,
            is_locked = false,
            $to_email = that.$wrapper.find('.js-to-email'),
            $contact_id = that.$to_id,
            new_contact_name = that.$wrapper.find('.js-to-new-name');

        that.$form.on("submit", function(event) {
            event.preventDefault();

            if ($contact_id.val() == 0 && ( !$.trim(new_contact_name.val()) || !$.trim($to_email.val()) ) && ( that.action == "new" || that.action == "forward") ) {
                $('.js-to-input, .js-to-add-name, .js-to-add-email').addClass('shake animated').focus();
                setTimeout(function(){
                    $('.js-to-input, .js-to-add-name, .js-to-add-email').removeClass('shake animated').focus();
                },500);
                return false;
            }

            if (!$.trim($to_email.val())) {
                that.$to_input.addClass('shake animated').focus();
                setTimeout(function(){
                    that.$to_input.removeClass('shake animated').focus();
                },500);
                return false;
            }

            if (!is_locked) {
                is_locked = true;

                var $submitButton = that.$form.find(".js-submit-button"),
                    $loading = $('<i class="icon16 loading" style="vertical-align: baseline; margin: 0 4px; position: relative; top: 3px;"></i>');

                $submitButton.removeClass("blue").attr("disabled", true);
                $loading.insertAfter($submitButton);

                var data = [
                    {
                        "name": "hash",
                        "value": that.hash
                    }
                ];

                that.filesController.uploadFiles(data, submit);
            }
        });

        var submit = function() {
            var href = that.send_action_url,
                data = that.$form.serializeArray();

            var onFail = function(errors_to_show, log_object) {
                if (!$.isEmptyObject(errors_to_show)) {

                    errors_to_show = $.extend(true, {}, errors_to_show);

                    var title = that.locales.send_error_title || "Send error",
                        text = that.locales.send_error_text || "Can't send email message",
                        $error_html = $(that.error_html).find('.js-error-text').text(text).end(),
                        error = errors_to_show.common;

                    if (that.is_admin) {

                        // known or unknown tech error
                        if (error) {
                            $error_html.find('.js-technical-info-block.js-known-error').show();
                            $error_html.find('.js-technical-info-text').text(error);
                        } else {
                            $error_html.find('.js-technical-info-block.js-unknown-error').show();
                        }

                    }


                    $.crm.alert.show({
                        title: title,
                        text: $error_html.get(0).outerHTML,
                        button: that.locales.close || 'close',
                        button_class: 'red',
                        onOpen: function ($dialog) {
                            // click link for detailed tech info
                            $dialog.find('.js-technical-info-link').one('click', function () {
                                var $link = $(this);
                                $link.remove();
                                $dialog.find('.js-technical-info-text').show();
                            });
                        }
                    });

                    // if there are any other errors, print in log
                    delete errors_to_show.common;
                    if (!$.isEmptyObject(errors_to_show)) {
                        console.log.error(errors_to_show);
                    }
                }
                if (log_object) {
                    console.log.error(log_object);
                }
            };

            $.post(href, data, "json")
                .done(function(response) {
                    if (response.status === "ok") {
                        destroyRedactor(that.$textarea);
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
                    } else {
                        that.dialog.close();

                        if (!$.isEmptyObject(response.errors)) {
                            onFail(response.errors);
                        } else {
                            onFail({ common: 'Send error' }, response);
                        }
                    }
                })
                .always(function () {
                    is_locked = false;
                })
                .fail(function (response) {
                    onFail({}, response);
                    that.dialog.close();
                });
        }
    };

    CRMSendEmailDialog.prototype.initPersonalSettingsDialog = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-show-personal-settings-dialog", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = that.crm.app_url + "?module=email&action=personalSettingsDialog",
                    data = {};

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html,
                        options: {
                            onSave: function(data) {
                                var $editor = that.$textarea.redactor('core.editor');
                                $editor.find('[data-role="c-email-signature"]').html(data['email_signature'] || '');
                                that.$wrapper.find('.js-sender-name').text(data['sender_name'] || '');
                            }
                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMSendEmailDialog.prototype.senderEmailSelect = function() {
        var that = this;

        that.$wrapper.on("change", that.$sender_email_select, function(event) {
            event.preventDefault();
            that.$sender_email.val(that.$sender_email_select.val());
        });
    };

    CRMSendEmailDialog.prototype.initEmailTo = function() {
        var that = this,
            new_name = null,
            new_email = null,
            $to_input = that.$to_input,
            $to_email = that.$wrapper.find('.js-to-email'),
            $to_new_name = that.$wrapper.find('.js-to-new-name'),
            $to_id = that.$to_id,
            $to_name = that.$wrapper.find('.js-to-name'),
            $deal_field = that.$wrapper.find('.js-deal-field'),
            $select_funnel = that.$wrapper.find('.js-select-funnel'),
            $visible_link = that.$wrapper.find('.js-select-deal .js-visible-link .js-text'),
            $deals_list = that.$wrapper.find('.js-deals-list'),
            $create_new_deal = that.$wrapper.find('.js-create-new-deal');

        // Init autocomplete
        $to_input.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&emailcomplete=true",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var item = ui.item,
                    criteria = item.criteria || {},
                    email = criteria.email || item.email,
                    data = '<i class="icon16 userpic20" style="background-image: url('+ item.photo_url +');"></i> <b>' +
                                    item.name + '</b> <span style="color: #999;">&lt;' + email + '&gt;</span>';
                addToContact(item.id, email, data);
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $to_input.on("focus", function(){
            $to_input.data("uiAutocomplete").search( $to_input.val() );
        });
        /* * * * */

        // Add to the recipient is not crm contact (on press [Enter])
        $to_input.on('keydown', function (e) {
            if (e.keyCode==13) {
                e.preventDefault();
                $to_input.blur();
                return false;
            }
        });
        // And on focusout
        $to_input.on('focusout', function (e) {
            e.preventDefault();
            toNotCrmContact();
            return false;
        });

        // Change email TO
        $to_name.on('click', '.js-remove-to-email', function () {
            $(this).parent('.js-email-to-user').remove();
            $to_input.removeClass('hidden').focus();
            $deal_field.addClass('hidden');
            $select_funnel.addClass('hidden');
            $deals_list.html($create_new_deal);
            $visible_link.html('<b><i>'+ that.locales['deal_select'] +'</i></b>');
            $to_email.val("");
            $to_new_name.val("");
            $to_id.val("").trigger('change');
            new_name = null;
            new_email = null;
        });

        // Edit recipient
        that.$wrapper.on('click', '.js-edit-recipient', function () {
            var data = [new_name, new_email],
                val = data.join(" ");

            $to_input.val($.trim(val)).removeClass('hidden').focus();
            $('.js-email-to-user').remove();
            new_name = null;
            new_email = null;
            $to_email.val("");
            $to_new_name.val("");
        });

        //
        that.$wrapper.on('keydown', '.js-to-add-email', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                $(this).blur();
            }
        });
        that.$wrapper.on('focusout', '.js-to-add-email', function () {
            addEmailTo();
        });

        that.$wrapper.on('keydown', '.js-to-add-name', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                $(this).blur();
            }
        });
        that.$wrapper.on('focusout', '.js-to-add-name', function () {
            addNameTo();
        });

        function toNotCrmContact() {
            var to_arr = $.trim($to_input.val()).split(/\s+/),
                email_index = false;

            // Find email
            $.each(to_arr, function(i, value){
                if ($.crm.check.email(value) && !email_index) {
                    new_email = value.replace(/\<|\>/g, '');
                    email_index = i;
                }
            });

            // Delete email from array (if any)
            if (email_index !== false) {
                to_arr.splice(email_index, 1);
            }
            // The rest is the name
            new_name = to_arr.join(" ");

            // If there is both a name and an e-mail address - all is ok.
            if (new_email && new_name) {
                var html = '<span class="js-edit-recipient" title="'+ that.locales['edit_recipient'] +'"><b>'+ $.crm.escape(new_name) +'</b> <span style="color: #999;">&lt;'+ $.crm.escape(new_email) +'&gt;</span></span>';
                addToContact(0, new_email, html);
                $to_new_name.val($.crm.escape(new_name));
            }

            // If there is only a name - add it and show the input for the email
            if (new_name && !new_email) {
                var html = '<span class="js-edit-recipient" title="'+ that.locales['edit_recipient'] +'"><b>'+ $.crm.escape(new_name) +'</b></span> <input class="c-to-input js-to-add-email" type="text" autocomplete="off" placeholder="'+ that.locales["type_email"] +'" style="margin-left: 8px; width: 150px;" />';
                addToContact(0, null, html);
                $to_new_name.val($.crm.escape(new_name));
                $('.js-to-add-email').focus();
                return false;
            }

            // If there is only an emil - add it and show the input for the name
            if (new_email && !new_name) {
                var html = '<input class="c-to-input js-to-add-name" type="text" autocomplete="off" placeholder="'+ that.locales["type_name"] +'" style="margin-right: 8px; width: 150px;" > <span class="js-edit-recipient" title="'+ that.locales['edit_recipient'] +'" style="color: #999;">&lt;'+ $.crm.escape(new_email) +'&gt;</span>';
                addToContact(0, new_email, html);
                $('.js-to-add-name').focus();
                return false;
            }
        }

        function addToContact(id, email, html) {
            $to_name.prepend('<div class="c-email-to-user js-email-to-user">' + html + ' <a title="'+ that.locales["change_recipient"] +'" class="c-remove-to-email js-remove-to-email">x</a></div>');
            $deal_field.removeClass('hidden');
            $to_input.addClass('hidden').val("");
            $to_email.val(email);
            $to_id.val(id).trigger('change');
        }

        function addEmailTo() {
            var input = $('.js-to-add-email'),
                to_email = $.trim(input.val());

            if (to_email.length && $.crm.check.email(to_email) === true) {
                new_email = to_email;
                $to_email.val($.crm.escape(to_email));
                input.before($('<span class="js-edit-recipient" title="'+ that.locales['edit_recipient'] +'" style="color: #999;"></span>').text('<' + to_email + '>')).remove();
            }
        }

        function addNameTo() {
            var to_name = $.trim($('.js-to-add-name').val());
            if (to_name.length) {
                new_name = to_name;
                $to_new_name.val($.crm.escape(to_name));
                $('.js-to-add-name:first-child').before( $('<span class="js-edit-recipient" title="'+ that.locales['edit_recipient'] +'"><b></b></span>').text(to_name) ).remove();
            }
        }
    };

    CRMSendEmailDialog.prototype.initEmailCopy = function() {
        var that = this,
            $wrapper = that.$wrapper.find('.email-copy-wrapper'),
            $link_icon = that.$wrapper.find('.js-email-copy-link-icon'),
            $copy_area = $wrapper.find('.email-copy-area'),
            $copy_text = $copy_area.find('.email-copy-text'),
            $copy_input = $copy_area.find('.email-copy-input'),
            $deal_participants_area = $wrapper.find('.deal-participants-area'),
            $wrapper_collapsed = that.$wrapper.find('.email-copy-wrapper-collapsed');

        // Init autocomplete
        $copy_input.autocomplete({
            source: $.crm.app_url + "?module=autocomplete&emailcomplete=true",
            appendTo: that.$wrapper,
            minLength: 0,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var item = ui.item,
                    data = '<i class="icon16 userpic20" style="background-image: url('+ item.photo_url +');"></i><b>'+ item.name +'</b>',
                    criteria = item.criteria || {};
                addToCC(item.id, criteria.email || item.email, data);
                $copy_input.val("");
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function(ul, item) {
            return $('<li class="ui-menu-item-html"><div>' + item.value + '</div></li>').appendTo(ul);
        };

        $copy_input.on("focus", function(){
            $copy_input.data("uiAutocomplete").search( $copy_input.val() );
        });
        /* * * * */

        that.$wrapper.on('click', '.js-email-copy-link, .email-copy-text-collapsed', function(e) {
            e.preventDefault();
            $wrapper.toggleClass('email-copy-wrapper-block');
            if ($wrapper.is(':visible')) {
                $link_icon.removeClass('rarr').addClass('darr');
                $copy_input.focus();

                $wrapper_collapsed.removeClass('email-copy-wrapper-collapsed-block'); // Close collapsed, if openig CC editor
            } else {
                $link_icon.removeClass('darr').addClass('rarr');

                if ($('.email-copy-text-collapsed').children().length) {
                    $wrapper_collapsed.addClass('email-copy-wrapper-collapsed-block');
                }
            }
        });

        $copy_area.on('click', function() {
            $copy_input.focus();
        });

        // Add participants in the deal to cc
        $deal_participants_area.on('click', '.email-copy-user', function () {
            var contact_id = $(this).data('cc-contact-id'),
                contact_email = $(this).data('cc-email'),
                contact_data = $(this).html();

            addToCC(contact_id, contact_email, contact_data);
        });

        // Add to cc on <focusout>
        $copy_input.on('focusout', function (e) {
            handlerCC();
        });

        // Add to cc on press [Enter]
        $copy_input.on('keydown', function (e) {
            if (e.keyCode==13) {
                e.preventDefault();
                handlerCC();
            }
        });

        // Remove from cc
        $copy_text.on('click', '.js-remove-cc', function (e) {
            e.preventDefault();
            var $removed = $(this).parent('.email-copy-user'),
                removed_email = $removed.data('email');
            $removed.remove();
            $('.email-copy-text-collapsed').children('[data-email="'+removed_email+'"]').remove();
        });

        // Remove from cc last contact on press [Backspace]
        $copy_input.on('keydown', function (e) {
            if (e.keyCode==8 && $copy_input.val().length == 0) {
                var $removed = $('.email-copy-text .email-copy-user:last'),
                    removed_email = $removed.data('email');
                $removed.remove();
                $('.email-copy-text-collapsed').children('[data-email="'+removed_email+'"]').remove();
                $copy_input.focus(); // for init autocomplete
            }
        });

        function handlerCC() {
            var emails = $.trim( $copy_input.val()).split(/[,:;]/);
            if (emails[0].length) {

                $.each(emails, function( i, email ) {
                    var cc_arr = $.trim(email).split(/\s+/),
                        email_index = false,
                        email = null,
                        name = null;

                    // Find email
                    $.each(cc_arr, function(i, value){
                        if ($.crm.check.email(value) && !email_index) {
                            email = value.replace(/\<|\>/g, '');
                            email_index = i;
                        }
                    });
                    // Delete email from array (if any)
                    if (email_index !== false) {
                        cc_arr.splice(email_index, 1);
                    }
                    // The rest is the name
                    name = cc_arr.join(" ");

                    // If there is both a name and an e-mail address - all is ok.
                    if (email && name) {
                        addToCCWithName(0, email, name);
                    }

                    if (email && !name) {
                        addToCC(0, email, email);
                    }

                    if (name && !email) {
                        $copy_input.addClass('shake animated');
                        setTimeout(function () {
                            $copy_input.removeClass('shake animated');
                        }, 500);
                    }

                    email_index = false;
                    email = null;
                    name = null;

                });
                return false;

            }
        }

        function addToCC(id, email, data) {
            if ((!$copy_text.children('[data-email="'+email+'"]').length || id === "0") && email.length) {
                $('.email-copy-input-div').before('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-contact-id="' + $.crm.escape(id) + '" data-email="' + $.crm.escape(email) + '">' + data + ' <a title="'+ that.locales["remove_form_cc"] +'" class="remove-cc js-remove-cc">x</a> <input name="cc[' + $.crm.escape(email) + '][email]" type="hidden" value="' + $.crm.escape(email) + '" /><input name="cc[' + $.crm.escape(email) + '][id]" type="hidden" value="' + $.crm.escape(id) + '" /></div>');
                $('.email-copy-text-collapsed').append('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-email="' + $.crm.escape(email) + '">' + data + '</div>');
            }
            $copy_input.val("").focus();
        }

        function addToCCWithName(id, email, name) {
            if ((!$copy_text.children('[data-email="'+email+'"]').length || id === "0") && email.length) {
                $('.email-copy-input-div').before('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-contact-id="' + id + '" data-email="' + $.crm.escape(email) + '">' + $.crm.escape(name) + ' <a title="'+ that.locales["remove_form_cc"] +'" class="remove-cc js-remove-cc">x</a> <input name="cc[' + $.crm.escape(email) + '][email]" type="hidden" value="' + $.crm.escape(email) + '" /><input name="cc[' + $.crm.escape(email) + '][id]" type="hidden" value="' + $.crm.escape(id) + '" /><input name="cc[' + $.crm.escape(email) + '][name]" type="hidden" value="' + $.crm.escape(name) + '" /></div>');
                $('.email-copy-text-collapsed').append('<div class="email-copy-user" title="' + $.crm.escape(email) + '" data-email="' + $.crm.escape(email) + '">' + $.crm.escape(name) + '</div>');
            }
            $copy_input.val("").focus();
        }
    };

    CRMSendEmailDialog.prototype.initSelectDeal = function() {
        var that = this,
            $visible_link = that.$form.find('.js-select-deal .js-visible-link .js-text'),
            $select_funnel = that.$form.find('.js-select-funnel'),
            $deal_field = that.$wrapper.find('.js-deal-field'),
            $deals_list = that.$form.find('.js-deals-list'),
            deal_id = that.$form.find('.js-deal-id'),
            $contact_id = that.$to_id,
            $empty_deal = that.$form.find('.js-empty-deal');

        that.$form.on('click', '.js-create-new-deal', function () {
            var new_deal = $(this).find('.js-text').html();
            $select_funnel.removeClass('hidden');
            $empty_deal.removeClass('c-empty-deal-hidden');
            $visible_link.html(new_deal);
            deal_id.val('0');
        });

        $empty_deal.on('click', function () {
            $(this).addClass('c-empty-deal-hidden');
            $select_funnel.addClass('hidden');
            $deals_list.find('li').removeClass('selected');
            $visible_link.html('<b><i>'+ that.locales['deal_empty'] +'</i></b>');
            deal_id.val('none');
        });

        that.$form.on('click', '.js-deal-item', function () {
            var new_deal = $(this).find('.js-text').html();
            $deals_list.find('li').removeClass('selected');
            $(this).parent().addClass('selected');
            $visible_link.html(new_deal);
            $empty_deal.removeClass('c-empty-deal-hidden');
            $select_funnel.addClass('hidden');
            deal_id.val($(this).data('deal-id'));
        });

        //
        if (that.action == "reply") {
            loadDeals();
        }
        if (that.action == "new" && $contact_id.val() > 0) {
            loadDeals();
            $deal_field.removeClass('hidden');
        }
        $contact_id.on('change', loadDeals);

        function loadDeals() {
            var id = $contact_id.val();

            deal_id.val('none');
            if (id) {
                var href = '?module=deal&action=byContact&id=' + id;
                $.get(href, function(response) {
                    if (response.status === "ok") {
                        // rendering contact deals
                        $.each(response.data.deals, function (i, deal) {
                            $deals_list.prepend(renderDeals(deal,response.data.funnels[deal.funnel_id]));
                        });
                        //
                        $.crm.renderSVG(that.$wrapper);
                    }
                }, "json");
            }
        }

        //
        $deals_list.on('click', function () {
            $deals_list.hide();
            setTimeout( function() {
                $deals_list.removeAttr("style");
            }, 200);
        });

        //
        that.$form.on('change', '.js-select-deal-funnel', function() {
            that.$form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id=' + $(this).val());
        });

        function renderDeals(deal,funnel) {
            return '<li><a href="javascript:void(0);" class="js-deal-item" data-deal-id="'+ deal.id +'"><span class="js-text"><i class="icon16 funnel-state svg-icon" data-color="'+ funnel.stages[deal.stage_id].color +'"></i><b><i>'+ deal.name +'</i></b></span></a></li>';
        }
    };

    return CRMSendEmailDialog;

    function destroyRedactor($textarea) {
        var redactor = $textarea.data("redactor");
        if (redactor && "core" in redactor) {
            redactor.core.destroy();
        }
    }

})(jQuery);
