var CRMReminderFormEdit = ( function($) {

    CRMReminderFormEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$main_wrapper = options["$main_wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$loader_spinner = $('.c-reminders-list-loader');

        // VARS
        that.reminder_id = options["reminder_id"];
        that.locales = options["locales"];
        that.app_url = options["app_url"];
        that.initialFormValues = that.$form.serialize();
        //that.initialFormValuesArr = that.$form.serializeArray();
        that.initialFormData = options["reminder_data"];
        that.tempFormData =  jQuery.extend(true, {}, that.initialFormData);
        that.form_is_change = false;
        that.is_first_click = true;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReminderFormEdit.prototype.initClass = function() {
        var that = this;
        //
        that.initDatePicker();

        that.initTextArea();
        //
        that.initTimeToggle();
        //
        that.initTimePicker();
        //
        that.initTypeToggle();
        //
        that.initSubmit();
        //
        that.initClickWatcher();
    };

    CRMReminderFormEdit.prototype.initCombobox = function() {
        var that = this;

        var $wrapper = that.$form.find(".js-contact-wrapper"),
            $idField = $wrapper.find(".js-contact-field");

        $wrapper.on("click", ".js-show-combobox", function(event) {
            //event.stopPropagation();
            showToggle(true);
        });

        $wrapper.on("click", ".js-hide-combobox", function(event) {
            event.stopPropagation();
            showToggle(false);
        });

        initAutocomplete();

        function showToggle( show ) {
            var active_class = "is-shown";
            if (show) {
                $wrapper.addClass(active_class);
                $wrapper.find('.js-autocomplete').focus();
            } else {
                $wrapper.removeClass(active_class);
            }
        }

        function initAutocomplete() {
            var $autocomplete = $wrapper.find(".js-autocomplete");

            $autocomplete
                .autocomplete({
                    appendTo: $wrapper,
                    //position: { my : "right top", at: "right bottom" },
                    source: that.app_url + "?module=autocomplete&type=user",
                    minLength: 0,
                    html: true,
                    focus: function() {
                        return false;
                    },
                    select: function( event, ui ) {
                        that.setContact(ui.item);
                        showToggle(false);
                        $autocomplete.val("");
                        that.form_is_change = true;
                        return false;
                    }
                }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                    return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
                };

            $autocomplete.on("focus", function(){
                $autocomplete.data("uiAutocomplete").search( $autocomplete.val() );
            });
        }

        that.setContact = function(user) {
            var $user = $wrapper.find(".js-user");
            if (user["photo_url"]) {
                $user.find(".icon.size-24").css("background-image", "url(" + user["photo_url"] + ")").html('');
            }
            $user.find(".c-name").text(user.name);
            $idField.val(user.id);
        }
    };

    CRMReminderFormEdit.prototype.initSearchDeal = function () {
        var that = this,
            $deal_wrapper = that.$form.find(".c-deal-wrapper"),
            $deal_input = that.$form.find('[name="data[deal_id]"]'),
            $contact_input = that.$form.find('[name="data[contact_id]"]'),
            $search_wrapper = $deal_wrapper.find(".c-search-contact-wrapper"),
            $deal_item = $deal_wrapper.find(".c-deal-item"),
            $field = $deal_wrapper.find(".js-autocomplete-deal");
            
        $field.autocomplete({
            appendTo: $search_wrapper,
            position: { my : "right top", at: "right bottom" },
            source: that.app_url + "?module=autocompleteSidebar",
            minLength: 0,
            html: true,
            focus: function (event, ui) {
                return false;
            },
            select: function (event, ui) {
                that.setDeal(ui.item)
                $field.val('');
                $search_wrapper.find('.ui-autocomplete').hide(); 
                that.form_is_change = true;
                $deal_input.trigger('click');
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function (ul, item) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>" + item.value + "</div>").appendTo(ul);
        };

        $deal_wrapper.on('click', function(event) {
            event.preventDefault();

            var $target = $(event.target);
            if (!$search_wrapper.is(':visible')) {
                var is_pen = !!( $target.closest(".js-open-search").length ); 
                if (is_pen){
                    $search_wrapper.addClass('is-shown');
                    $field.focus();
                }
            }
            else {
                var is_delete = !!( $target.closest(".js-reset-deal").length );         
                if (is_delete) {
                    that.clearDeal()
                }
            }
        })

        that.clearDeal = function() {
            $deal_item.html('');
            $search_wrapper.removeClass('is-hidden is-shown');
            $contact_input.val('');
            $deal_input.val('');
            that.form_is_change = true;
            $deal_input.trigger('click');
            that.tempFormData.contact.id = '';
            that.tempFormData.contact.name = '';
            that.tempFormData.contact.photo_url = '';
        }

        $field.on("focus", function(){
            $field.data("uiAutocomplete").search( $field.val() );
        });
         
        that.setDeal = function(deal, unload = false) {
            const deal_id_icon = deal["photo_url"] ? `<span class="icon size-18 rounded js-open-search custom-mr-4" style="background-image: url(${deal["photo_url"]});"></span>`
            : (deal["icon"] ? `<span class="custom-mr-4" style="color: ${deal["color"]};"><i class="${deal["icon"]}"></i></span>` : '');
            const deal_link = that.app_url + (deal["photo_url"]? 'contact/' : 'deal/') + deal.id;
            const deal_id_string = 
            `<a class="flexbox middle c-user" href="${!unload ? 'javascript:void(0);' : deal_link}">
            ${deal_id_icon}
            <span class="c-user-name">${deal.name}</span>
            </a>
            ${!unload ? `<div class="hint custom-pl-12 js-open-search cursor-pointer">
                <span class="icon size-14"><i class="fas fa-pen" title="[\`edit\`]"></i></span>
            </div>`: ''}`;

            if (unload) return deal_id_string;

            $search_wrapper.addClass('is-hidden').removeClass('is-shown');
            $deal_item.html(deal_id_string);

            if (deal["photo_url"]) {
                $contact_input.val(deal.id);
                $deal_input.val('');
                that.tempFormData.contact.photo_url = deal["photo_url"]
            } else {
                $deal_input.val(deal.id);
                $contact_input.val('');
                that.tempFormData.contact.photo_url = '';
            }
            that.tempFormData.contact.id = deal.id;
            that.tempFormData.contact.name = deal.name;
        }
    };



    CRMReminderFormEdit.prototype.initTextArea = function() {
        var that = this,
            $textarea = that.$wrapper.find(".js-textarea");
            that.$textField = that.$main_wrapper.find(".js-float-text");
            //$form = that.$form;

        $textarea.css("min-height", that.$textField.height() + 'px');

        $textarea.on("input", toggleHeight);

        $textarea.on("focusout", function () {
            that.form_is_change = true;
        });

        $textarea.on("keydown", function (event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );
                //is_esc = ( key === 27 );

            if (is_enter && !event.shiftKey) {
                event.preventDefault();
                event.stopPropagation();
                that.$form.trigger('submit');
            }

           /* if (is_esc) {
                event.preventDefault();
                event.stopPropagation();
                that.$clear();
            }*/
        });

          function toggleHeight() {
           
            //$textarea.css("height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("height", scroll_h + "px");
        }
    }

    CRMReminderFormEdit.prototype.initDatePicker = function() {
        var that = this,
            $datePickers = that.$form.find(".js-datepicker");
            

        $datePickers.each(function() {
            var $input = $(this);
                //$altField = $input.parent().find("input[type='hidden']");

            if(this.value.length>0) {
                this.style.width = ((this.value.length) * 7) + 'px';
            }

            $input.datepicker({
                //altField: $altField,
                //altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                showOtherMonths: true,
                selectOtherMonths: true,
                gotoCurrent: true,
                //showButtonPanel: true,

            });

            var $input_wrapper = $input.parent(),
                $icon = $input_wrapper.find(".calendar"),
                $date_clear = $input_wrapper.find(".js-reset-date");

            $icon.on("click", function() {
                $input.focus();
            });

            if (!that.reminder_id) {
                $input.datepicker("setDate", "+1d");
            }

            $input.on('change', function(){
                if(this.value.length>0){
                    this.style.width = ((this.value.length) * 7) + 'px';
                }else{
                  this.style.width = ((this.getAttribute('placeholder').length + 1) * 8) + 'px';
                }
                if ($input.val() !== '') {
                    $input_wrapper.addClass('is-active');
                }
                that.form_is_change = true;
                $input_wrapper.trigger('click');
            })

            $date_clear.on("click", function () {
                $input.val("");
                $input.trigger('change');
                $input_wrapper.removeClass('is-active');
            });
        });
    };

    CRMReminderFormEdit.prototype.initTimeToggle = function() {
        var that = this;

        var $toggle = that.$form.find(".js-time-toggle"),
            $field = $toggle.find(".js-timepicker");

            $field.on('change', function() {
                show(true);
                if ($field.val() == "") show();
                that.form_is_change = true;
            })

            $toggle.on("click", ".js-show-time", function () {
                $field.focus();
            }); 

            $toggle.on("click", ".js-reset-time", function () {
                $field.val("");
                show();
                that.form_is_change = true;
            });

        function show(show) {
            var active_class = "is-active";
            if (show) {
                $toggle.addClass(active_class);
            } else {
                $toggle.removeClass(active_class);
            }
        }
    };

    CRMReminderFormEdit.prototype.initTimePicker = function() {
        var that = this;

        var $timePickers = that.$form.find(".js-timepicker");
            $timePickers.each( function() {
            var $input = $(this);
            $input.timepicker();
        });
    };

    CRMReminderFormEdit.prototype.initClickWatcher = function() {
        var that = this,
            //$form = that.$form,
            $main_wrapper = that.$main_wrapper,
            $wrapper = that.$wrapper,
            $textarea = that.$wrapper.find(".js-textarea");
            //is_locked = false;

        $main_wrapper.on("editOpen", function () {
            $(".c-reminder-wrapper.highlighted").removeClass('highlighted');   
            if (that.is_first_click) {
                that.initCombobox();
                that.initSearchDeal();
                that.is_first_click = false;
                toggleHeight();
            }
            $(document).on("click", that.editClickWatcher);
            $main_wrapper.on("escKeyPress", that.$clear);
        });

        function toggleHeight() {
           
            $textarea.css("height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("height", scroll_h + "px");
        }

        that.close_edit = function() {

            $main_wrapper.trigger("reminderNotChanged");
            that.form_is_change = false;

            $(document).off("click", that.editClickWatcher);
            $main_wrapper.off("escKeyPress", that.$clear);
        }


        that.editClickWatcher = function(event) {
            var is_exist = $.contains(document, $wrapper[0]);
            if (is_exist) {
                /* editMode click save*/
                var $target = $(event.target),
                    is_edited = $wrapper.is(':visible');
                if (is_edited) {
                    var is_edit_target = $.contains($main_wrapper[0], event.target),
                        is_reminder_wrapper = !!( $target.closest(".js-edit-reminder").length ),
                        is_time = !!( $target.closest(".ui-timepicker-wrapper").length ),
                        is_date = !!( $target.closest(".ui-datepicker").length ) || !!( $target.closest(".ui-corner-all").length ),
                        is_contact_visible = $wrapper.find(".js-contact-wrapper").hasClass('is-shown'),
                        is_contact = !!( $target.closest(".js-contact-wrapper").length ),
                        is_search_visible = $wrapper.find(".c-search-contact-wrapper").hasClass('is-shown'),
                        is_search = !!( $target.closest(".c-deal-wrapper").length );

                    if (!is_edit_target && !is_time && !is_date ) {
                        //event.preventDefault();
                        if (that.checkFormChange()) {
                        that.close_edit();
                        }
                        else {
                            if ( is_reminder_wrapper ) that.close_edit();
                        }
                    }

                    /*else {
                        if (that.form_is_change) {
                            console.log("submit:" + that.form_is_change);
                            if (!that.checkFormChange(true)) {
                                that.$submit_reminder(false);
                            }
                        }
                    }*/

                    if (is_contact_visible && !is_contact) {
                        $wrapper.find(".js-contact-wrapper").removeClass('is-shown');
                    }

                    if (is_search_visible && !is_search) {
                        $wrapper.find(".c-search-contact-wrapper").removeClass('is-shown');
                    }
                }

            } else {
                $(document).off("click", that.editClickWatcher);
                $main_wrapper.off("escKeyPress", that.$clear);
            }
        }
    }

    CRMReminderFormEdit.prototype.initTypeToggle = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-reminder-type-toggle"),
            //$visibleLink = $wrapper.find(".js-visible-link"),
            $field = $wrapper.find(".js-type-field"),
            $menu = $wrapper.find(".menu");

        $wrapper.waDropdown();

        $menu.on("click", "a", function () {
            var $link = $(this);
            $wrapper.find(".js-text").html($link.html());
            $menu.find(".selected").removeClass("selected");
            $link.closest("li").addClass("selected");

            var id = $link.data("type-id");
            $field.val(id).trigger("change");
            that.form_is_change = true;
        });
    };

    CRMReminderFormEdit.prototype.checkFormChange = function(temp) {
        var that = this;
          const formValues = that.$form.serialize();
       /* if (temp) { //if needed oninput update data
            const result =  that.changedFormValues === formValues;
            if (!result) that.changedFormValues = formValues;
            //console.log('is Same:' + result);
            return result;
        }*/

        if (temp) { //if needed check date or time or user changes
            const data = that.initialFormData;
            const formValuesArr = that.$form.serializeArray();
            const result =  {all: true, date_time: true};
            result.all = that.initialFormValues === formValues;
            const date = formValuesArr.filter(n => n['name'] === 'data[due_date]')[0]['value'];
            const time = formValuesArr.filter(n => n['name'] === 'data[due_time]')[0]['value'];
            const user_contact_id = formValuesArr.filter(n => n['name'] === 'data[user_contact_id]')[0]['value'];
            result.date_time = (data.due_date === date && data.due_time === time && data.user_contact.id === user_contact_id) ? true : false;
            return result;
        }
        return that.initialFormValues === formValues;
    };

    CRMReminderFormEdit.prototype.initSubmit = function() {
        var that = this,
            is_locked = false,
            $form = that.$form,
            $main_wrapper = that.$main_wrapper,
            $wrapper = that.$wrapper,
            $save_button = $form.find(".js-save");
            $cancel_button = $form.find(".js-cancel");
            $has_errors = false;

        $save_button.on('click', function(event) {
            event.stopPropagation();
            //event.preventDefault();
            that.$form.trigger('submit');
        });

        $cancel_button.on('click', function(event) {
            event.stopPropagation();
            event.preventDefault();
            that.$clear();
        });

        that.$clear = function() {
            const $textarea = $form.find(".js-textarea");
            const $date_input = $form.find(".js-datepicker");
            const $time_input = $form.find(".js-timepicker");
            const is_search_hidden = $form.find(".c-search-contact-wrapper").hasClass('is-hidden');
            const idField = $form.find(".js-contact-field");

            if (!that.checkFormChange()) {

                const data = that.initialFormData;
                $textarea.val(data.content);
                $wrapper.find(`.menu a[data-type-id='${data.type}']`).trigger('click');
                $date_input.val(data.due_date).trigger('change');
                $time_input.val(data.due_time).trigger('change');
                if (data.contact.id) {that.setDeal(data.contact)}
                else if (is_search_hidden) {that.clearDeal()};
                if (idField !== data.user_contact.id) {that.setContact(data.user_contact)};

                if ($has_errors) {
                    $form.find(`[name="data[content]"`).trigger('clear_error'); 
                    $has_errors = false;
                }
                toggleHeight($textarea);
            }
            that.close_edit();
        }

        function toggleHeight($textarea) {
           
            $textarea.css("height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("height", scroll_h + "px");
        }

        $form.on('submit', function(event) {
            event.preventDefault();
            const form_check = that.checkFormChange(true);
            if (form_check.all) { //no form changed
                that.close_edit();
                return;
            }
             //form changed
            if (form_check.date_time) { //no date or time or user changed
                that.$submit_reminder();
                return;
            }
            //changed date or time or user
            that.$submit_reminder(true);
        });

        that.$submit_reminder = function(reload) {
            if ($has_errors) {
                $form.find(`[name="data[content]"`).trigger('clear_error'); 
                $has_errors = false;
            }
            var formData = getData();
            if (formData.errors.length) { 
                showErrors(false, formData.errors);
            } else {
                //if (reload) that.$loader_spinner.show();
                request(formData.data, reload);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray(),
                time = false;
                type_other = false;

            $.each(data, function(index, item) {
                if (item.value.length) {
                    result.data.push(item);
                    if (item.name === "data[due_time]") time = true;
                    if (item.value === "OTHER") type_other = true;
                }
                // } else {
                //     result.errors.push({
                //         name: item.name,
                //         value: that.locales["empty"]
                //     });
              
            });
            if (result.data.length < (time? 5 : type_other ? 4 : 3)) {
                result.errors.push({name: 'content', value: that.locales["at_least"]})
            }
            if (type_other) {
                const content_value = result.data.filter(x => x.name === 'data[content]');
                if (!content_value.length || !/\S/.test(content_value[0].value)) {
                    result.errors.push({name: 'content', value: that.locales["empty"]})
                };
                //
            }
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
                    text = item.value,
                    $field = $form.find(`[name="data[${name}]"`);

                if (!$field.hasClass(error_class)) {
                    var $text = $("<span />").addClass("errormsg").text(text);
                    $wrapper.find('.flexbox.vertical.width-100').append($text);
                    $field
                        .addClass(error_class)
                        .one("focus click change clear_error", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                        $has_errors = true;
                }
            });
        }

        function request(data, reload) {
            if (!is_locked) {
                is_locked = true;
                var href = that.app_url + "?module=reminder&action=save";
                $loader = $('<span class="icon"><i class="fas fa-spinner wa-animation-spin"></i></span>'),
                $wrapper.find('.c-actions-button.js-save').append($loader);
                $.post(href, data, function(response) {

                    if (response.status === "ok") {
                       if (reload) {
                        $main_wrapper.trigger("reminderIsChanged");
                        $(document).off("click", that.editClickWatcher);
                        $main_wrapper.off("escKeyPress", that.$clear);
                        }
                        else { //change view mode data
                            changeViewModeData(data);
                        }
                        that.initialFormValues = that.$form.serialize();
                        that.form_is_change = false;
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    $loader.remove();
                    is_locked = false;
                });
            }
        }

        function changeViewModeData(data) {

            const content = data.filter(x => x['name'] === "data[content]");
            const type = data.filter(x => x['name'] === "data[type]")[0]['value'];
            if (that.tempFormData.content !== content) {
                if (content.length) {
                    that.tempFormData.content = content[0]['value'];
                    that.$textField.html(nl2br(content[0]['value']));
                }
                else {
                    that.tempFormData.content = '';
                    that.$textField.html('');
                }
            }
            
            if (that.tempFormData.type !== type) {
                const $type_button_edit = that.$wrapper.find(".js-reminder-type-toggle button"),
                    $type_button = that.$main_wrapper.find("button.no-hover");
                if (type === "OTHER") {
                    $type_button.remove();
                }
                else if ($type_button.length) {
                    $type_button.html($type_button_edit.html());
                }
                else {
                    const type_button_html = `<button class="button button-slim smallest rounded light-gray no-hover">${$type_button_edit.html()}</button>`
                    that.$main_wrapper.find('.is-view .c-footer').prepend(type_button_html);
                }
                that.tempFormData.type = type;
            }

            if (that.tempFormData.contact.id !==  that.initialFormData.contact.id && that.tempFormData.contact.name !==  that.initialFormData.contact.name) {
                const $deal_show = that.$main_wrapper.find(".is-view .c-deal-wrapper");
                if (that.tempFormData.contact.id !== '') {
                    $deal_show.html(that.setDeal(that.tempFormData.contact, true));
                    //$deal_show.find('.js-open-search').remove();
                } 
                else {$deal_show.html('')}
            }

            that.initialFormData =  jQuery.extend(true, {}, that.tempFormData);
            $main_wrapper.addClass('highlighted');
            $main_wrapper.trigger("reminderNotChanged");
            $main_wrapper.trigger("updatePadding");
        }


        function nl2br (str, is_xhtml) {
            var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>'; // Adjust comment to avoid issue on phpjs.org display
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
          }
    };

    return CRMReminderFormEdit;

})(jQuery);