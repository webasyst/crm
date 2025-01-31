(function ($) {

    $.widget("custom.combobox", {

        _create: function () {
            if (this.element.data('custom.combobox.inited')) {
                return;
            }
            this.wrapper = $("<div style='position:relative; width: 280px'>")
                .addClass("custom-combobox")
                .insertAfter(this.element);

            this.element.hide();
            this._createAutocomplete();
            this._createShowAllButton();
            this.element.data('custom.combobox.inited', 1);
        },

        _createAutocomplete: function () {

            this.ns = this.element.attr("name") || 'element-' + ('' + Math.random()).slice(2);
            _other_version_ = true;
            if (!this.element.is('select')) {
                return;
            }
            var selected = this.element.children(":selected"),
                value = selected.val() ? selected.text() : "";

            this.h = 24;    // height of one item in list

            var self = this;
            this.input = $("<input type='search'>")
                .appendTo(this.wrapper)
                .val(value)
                .attr("title", "")
                .addClass("custom-combobox-input ui-widget");

            if (this.element.data('autocomplete') || this.element.data('readonly')) {
                this.input.autocomplete({
                    delay: 200,
                    minLength: 0,
                    html: true,
                    source: $.proxy(this, "_source"),
                    item_clz: 'crm-search-ui-autocomplete-item',
                    width: 270
                });
                this.ul = $(this.input).autocomplete('widget');
            } else {
                this.ul = $();
            }

            this.offset = this.limit = this.element.data('limit');
            this.count = this.element.data('count');
            //setTimeout(() => this.wrapper.width(this.input.width() + 30), 100)
            this.ul.css({
                overflowY: 'auto',
                overflowX: 'hidden',
                maxHeight: this.h * 10
            });

            if (this.element.attr('disabled')) {
                this.input.attr('disabled', true);
            }

            if (this.options.css) {
                this.input.css(this.options.css);
            }

            this.element.find('option.cond').remove();

            if (this.element.data('readonly')) {
                this.input.attr('readonly', true).addClass('readonly').css({
                    cursor: 'pointer'
                }).click(function () {
                    if ($(this).autocomplete("widget").is(":visible")) {
                        $(this).autocomplete('close');
                    } else {
                        self._showSelectItems();
                    }
                    return false;
                });
            }

            this.hidden = $("<input type='hidden'>")
                .appendTo(this.wrapper);

            if (this.options.label !== false) {
                this.label = $('<span class="c-label"></span>').css({
                    top: '3px',
                    left: '-20px',
                    position: 'absolute'
                }).prependTo(this.wrapper);
            } else {
                this.label = $();
            }
            this.element.attr('name', '');
            if (this.element.data('readonly')) {
                this.label.hide();
            }
            if (this.element.attr('disabled')) {
                this.input.attr('disabled', true);
            }
            if (selected.data('op') === '*=') {
                this.label.text('≈');
            }

            if (selected.val() && !selected.hasClass('value')) {
                if (selected.data('period')) {      // data('type') === 'period'
                    this.element.data("value", "");
                    var datetime = selected.val().split('--');
                    this._updatePeriodInput((datetime[0] || '').trim(), (datetime[1] || '').trim());
                } else {
                    this.element.data("value", "");
                    if (this.element.data('pass') === 'value') {
                        this.hidden.attr("name", this.ns).val(selected.val());
                    } else {
                        this.hidden.attr("name", this.ns + "." + selected.val()).val("1");
                    }
                    this.input.val(selected.text());
                    this.label.text('=');
                }
                this._removeIcon();
            } else if (selected.hasClass('value') || selected.text()) {
                this.element.val("");
                var val = selected.hasClass('value') ? (selected.val() || selected.text()) : selected.text();
                var text = selected.hasClass('value') ? selected.text() : val;
                if (selected.data('op') === '*=') {
                    if (!this.element.data('readonly')) {
                        this.hidden.attr("name", this.ns).val(JSON.stringify({
                            op: '*=',
                            val: val
                        }));
                    } else {
                        this.hidden.attr("name", this.ns).val(val);
                    }
                } else {
                    this.hidden.attr("name", this.ns).val(val);
                }
                this.input.val(text);
                if (selected.data('icon')) {
                    this._addIcon(selected.data('icon'));
                }
            } else {
                this.hidden.attr("name", this.ns);
            }

            this.input.bind("autocompleteselect",function (event, ui) {

                if (ui.item.option) {
                    ui.item.option.selected = true;
                    $(this).trigger("select", event, {
                        item: ui.item.option
                    });
                }
                if (ui.item.option && !$(ui.item.option).hasClass('value')) {
                    var opt = $(ui.item.option);
                    if (opt.data('period')) {
                        var $dialog = self._showPeriodDialog();
                        $dialog.bind('select', function (event, start_datetime, end_datetime) {
                            self._updatePeriodInput(start_datetime, end_datetime);
                            self._removeIcon();
                        });
                        $dialog.bind('cancel', function() {
                            self.input.val('');
                            self.hidden.attr('name', self.ns).val('');
                            self.label.text('');
                        });
                    } else {
                        var val = $(ui.item.option).val();
                        if (val) {
                            if (self.element.data('pass') === 'value') {
                                self.hidden.attr("name", self.ns).val(val);
                            } else {
                                self.hidden.attr("name", self.ns + "." + val).val("1");
                            }

                        } else {
                            self.hidden.attr("name", self.ns).val("");
                        }
                        self.input.val($(ui.item.option).text());
                        self.label.text('=');
                        self._removeIcon();
                    }
                } else {
                    var val = ui.item.value;
                    var text = ui.item.text || ui.item.name;
                    if (ui.item.option) {
                        val = $(ui.item.option).val();
                    }
                    self.hidden.attr("name", self.ns).val(val);
                    self.input.val(text);
                    self.label.text('=');
                    if (ui.item.icon) {
                        self._addIcon(ui.item.icon);
                    } else {
                        self._removeIcon();
                    }
                }
                self.element.trigger("change");

                if (self.element.data('readonly')) {
                    setTimeout(function () {
                        self.input.blur();
                    });
                }

                if (typeof self.options.select === 'function') {
                    self.options.select.apply(this, [event, ui]);
                }

                return false;
            }).bind("autocompletechange", function (event, ui) {
                self._removeIfInvalid.call(self, event, ui);
            });

            var input_val = '';
            if (!this.element.data('readonly')) {
                this.input.bind('keydown', function () {
                    input_val = $(this).val();
                });
                this.input.bind('keyup', function () {
                    // changed
                    var val = $(this).val();
                    if (input_val !== val) {
                        self._removeIcon();
                        if (val) {
                            self.label.text('≈');
                            self.hidden.attr("name", self.ns).val(JSON.stringify({
                                val: val,
                                op: '*='
                            }));
                        } else {
                            self.label.text('');
                            self.hidden.attr("name", self.ns).val('');
                        }
                    }
                    if (typeof self.options.keyup === 'function') {
                        self.options.keyup.apply(this, [val, input_val]);
                    }
                    input_val = val;
                });
            }

            this.input.bind('keyup', function (event) {
                if (event.keyCode == 13 && !$(this).data('hold') && $(this).val().trim()) {
                    $(this).trigger('enter');
                }
            });

            this.input.bind('search', function () {
                if (!$(this).val()) {
                    self._removeIcon();
                    self.hidden.attr("name", self.ns).val("");
                    self.label.text('');
                    if (self.ul.is(":visible")) {
                        $(this).autocomplete("search", "");
                        //self._showSelectItems();
                    }
                    if (typeof self.options.clear === 'function') {
                        self.options.clear.apply(this);
                    }
                }
            });

            this.input.bind("autocompleteopen", function (event, ui) {
                var width = self.input.width();
                self.ul.find('li.ui-menu-item').each(function () {
                    var el = $(this),
                        $count = el.find('.count'),
                        $text = el.find('.crm-text');

                    $count.insertBefore($text);

                    var text_el = $text.width(Math.max(width - $count.outerWidth(true) - 30, 0));
                    if (text_el.data('sep')) {
                        el.css({
                            borderTop: '0px solid #ccc'
                        });
                    }
                });
                self.ul.width(width);
            });

            if (!_other_version_) {  //self.ns !== 'contact_info.email'
                this.input.bind("autocompleteopen", function (event, ui) {
                    self.ul.width(self.input.outerWidth(true) - 5);
                    if (self.offset !== undefined && self.count !== undefined) {
                        if (self.offset < self.count) {
                            self.height = self.ul.height();
                            self.ul.unbind('scroll.' + self.ns).bind('scroll.' + self.ns, function () {
                                var it = $(this).find('.dummy-end');
                                if (it.length) {
                                    if ($(this).scrollTop() + self.height >= it.position().top) {
                                        self.input.trigger("autocompleteload");
                                    }
                                } else {
                                    self.ul.unbind('scroll.' + self.ns);
                                }
                            });
                            var h = self.ul.find('.ui-menu-item:first').height();
                            $('<li class="dummy-end ignore-hover" role="menuitem"><a class="ui-corner-all" style="padding: .2em .4em; line-height: 1.5; display: block;"></a></li>').
                                height(h).
                                appendTo(self.ul);
                            self.ul.css({
                                overflowY: 'scroll',
                                overflowX: 'hidden',
                                height: self.height
                            });
                        } else {
                            self.ul.unbind('scroll.' + self.ns);
                            self.ul.css({
                                overflowY: '',
                                overflowX: ''
                            });
                        }
                    }
                    self.ul.find('.sep').closest('li').addClass('ignore-hover');
                    var width = self.ul.width();
                    var scroll = self.ul.css('overflowY') === 'scroll';
                    self.ul.find('li.ui-menu-item').each(function () {
                        var el = $(this);
                        if (el.find('.sep').length) {
                            el.find('.sep').width(scroll ? width - 25 : width - 10);
                        } else {
                            el.find('.crm-text').width(Math.max(width - el.find('.count').outerWidth(true) - 30, 0));
                        }
                    });

                });

                this.input.bind("autocompleteload", function (event) {
                    if (!self.loading && self.offset < self.count) {
                        self.loading = true;
                        self.input.addClass('ui-autocomplete-loading');
                        $.get(self.options.url, {
                            id: self.ns, offset: self.offset, limit: self.limit, term: self.input.val()
                        }, function (r) {
                            if (r.status === 'ok') {
                                var values = r.data.values;
                                var items = [];
                                self.ul.find('.ui-menu-item').each(function () {
                                    items.push($(this).data('item.autocomplete'));
                                });
                                for (var i = 0; i < values.length; i += 1) {
                                    items.push(self._formItem(values[i]));
                                }
                                self.count = r.data.count;
                                self.response(items);
                                self.offset += values.length;
                                if (self.offset >= self.count) {
                                    self.ul.find('.dummy-end').remove();
                                }
                            }
                            self.loading = false;
                            self.input.removeClass('ui-autocomplete-loading');
                        }, 'json');
                    }
                });
            }

            this.input.bind('autocompletefocus', function (event, ui) {
                $(this).val(ui.item.name);
                $(this).data('hold', 1);
                return false;
            });

            this.input.bind('autocompleteclose', function (event, ui) {
                if (event.keyCode === 13) {
                    var input = $(this);
                    setTimeout(function () {
                        input.data('hold', 0);
                    }, 200);
                } else {
                    $(this).data('hold', 0);
                }
            });

            this.input.focus(function () {
                self.arrow.show();
            }).blur(function () {
                if (!self.wrapper.data('mouserover')) {
                    self.arrow.hide();
                }
            });

            this.wrapper.mouseover(function () {
                $(this).data('mouserover', 1);
                self.arrow.show();
            }).mouseout(function () {
                $(this).data('mouserover', 0);
                if (!self.input.is(':focus')) {
                    self.arrow.hide();
                }
            });

        },

        _createShowAllButton: function () {
            var input = this.input,
                hidden = this.hidden,
                ns = this.ns,
                element = this.element,
                self = this;

            if (element.find('option').length) {
                this.arrow = $("<a href='javascript:void(0);' style='margin: -2px 0 0 -20px;'><i class='fas fa-caret-down'></i></a>")
                    .attr("tabIndex", -1)
                    .appendTo(this.wrapper)
                    .removeClass("ui-corner-all")
                    .addClass("custom-combobox-toggle ui-corner-right")
                    .mousedown(function () {
                        input.focus();
                        hidden.attr("name", ns);
                        if (self.ul.is(":hidden")) {
                            self._showSelectItems();
                        } else {
                            input.autocomplete("search", "");
                        }
                        return false;
                    })
                    .hide();

                if (element.data('readonly')) {
                    this.wrapper.find('svg').css({
                        backgroundColor: 'white'
                        // marginTop: 2
                    }).parent().css({
                        // marginLeft: -24,
                        // width: 20
                    });
                }
            } else {
                this.arrow = $();
            }

        },

        _addIcon: function (icon) {
            this.input.after('<img class="flag" style="position: absolute; top: 3px; right: 41px; padding: 2px; background: white;" src="' + icon + '"/>');
        },

        _removeIcon: function () {
            this.input.nextAll('.flag').remove();
        },

        _formItem: function (item, is_readonly) {
            var label = item.label;

            if (is_readonly && !item.value && !item.name) {
                label = '<span style="visibility: hidden">empty</span>';
            }

            if (!_other_version_) {  // this.ns !== 'contact_info.email'
                label = $.wa.encodeHTML(item.name);
            }
            var label = '<span class="crm-text" data-sep="' + (item.sep ? 1 : 0) + '">' + label;
            if (item.icon) {
                label += ' <img src="' + item.icon + '" />';
            }
            label += '</span>';
            if ($.isNumeric(item.count)) {
                label += '<span class="count" style="float: right;">' + item.count + '</span>';
            }
            item.label = label;
            if (!item.value) {
                item.value = item.name;
            }
            return item;
        },

        _showSelectItems: function () {
            var self = this,
                response = null,
                is_readonly = self.element.data('readonly'),
                prev_was_sep = false;
            self.input.autocomplete("search", "");
            response = this.response;
            response(this.element.children("option").map(function () {
                var item = $(this);
                if (!item.hasClass('sep')) {
                    var it = self._formItem({
                        icon: item.data('icon'),
                        count: item.data('count'),
                        label: item.html(),
                        name: item.text(),
                        value: this.value,
                        sep: prev_was_sep,
                        option: this
                    }, is_readonly);
                    prev_was_sep = false;
                    return it;
                } else {
                    prev_was_sep = true;
                }
            }).toArray());
        },

        _source: function (request, response) {
            var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
//            var sep = {
//                label: '<span style="border-top:1px solid #ccc; width: 240px; display: inline-block;" class="sep"></span>',
//                value: '',
//                option: '<option disabled class="sep"></option>'
//            };
            var self = this;
            self.response = response;
            if (_other_version_ && !request.term) {
                response([]);
                return;
            }
            var autocomplete = function (options, after) {
                if (_other_version_) {  // self.ns === 'contact_info.email'
                    options.highlight = 1;
                }
                $.get(self.options.url, options, function (r) {
                    var values = [];
                    var count = 0;
                    if (r && r.status === 'ok') {
                        if ($.isArray(r.data.values) && r.data.values.length) {
                            values = r.data.values;
                        }
                        if (r.data.count) {
                            count = r.data.count;
                        }
                    }
                    for (var i = 0, n = values.length; i < n; i += 1) {
                        values[i] = self._formItem($.extend(values[i], { option: null }));
                    }
                    self.offset = values.length;
                    self.count = count;
                    response(values);
                }, 'json');
            };

            if (_other_version_) {  // this.ns === 'contact_info.email'
                if (!request.term) {
                    response(this.element.children("option").map(function () {
                        var item = $(this);
                        var text = item.html();

                        if (item.hasClass('sep')) {
                            return $.extend({}, sep, {
                                option: this
                            });
                        } else if (this.value && ( !request.term || matcher.test(text) )) {
                            var label = text;
                            if (item.data('icon')) {
                                label += ' <img src="' + item.data('icon') + '" />';
                            }
                            if ($.isNumeric(item.data('count'))) {
                                label += '<span class="count">' + item.data('count') + '</span>';
                            }
                            return {
                                label: label,
                                text: text,
                                value: this.value,
                                option: this
                            };
                        }
                    }).toArray());
                } else {
                    autocomplete({
                        term: request.term,
                        id: this.ns
                    });
                }
            } else {

                if (request.term && this.element.data('autocomplete')) {
                    // make ajax autocomplete
                    autocomplete({
                        term: request.term,
                        id: this.ns
                    }, function (values) {
                        self.ul.height(self.h * values.length);
                        return values;
                    });
                } else {
                    var values = this.element.children("option").map(function () {
                        var item = $(this);
                        var text = item.html();

                        if (item.hasClass('sep')) {
                            return $.extend({}, sep, {
                                option: this
                            });
                        } else if (this.value && ( !request.term || matcher.test(text) )) {
                            var label = text;
                            if (item.data('icon')) {
                                label += ' <img src="' + item.data('icon') + '" />';
                            }
                            if ($.isNumeric(item.data('count'))) {
                                label += '<span class="count">' + item.data('count') + '</span>';
                            }
                            return {
                                label: label,
                                text: text,
                                value: this.value,
                                option: this
                            };
                        }
                    }).toArray();

                    if (!this.element.data('autocomplete')) {
                        response(values);
                    } else {
                        autocomplete({
                            term: '', id: this.ns
                        }, function (r) {
                            if (r.length && values[values.length - 1] && !$(values[values.length - 1].option).hasClass('sep')) {
                                values.push(sep);
                            }
                            values = values.concat(r);
                            self.ul.height(self.h * values.length);
                            return values;
                        });
                    }

                }
            }
        },

        _removeIfInvalid: function (event, ui) {

            // Selected an item, nothing to do
            if (ui.item) {
                return;
            }

            // Search for a match (case-insensitive)
            var that = this,
                $input = that.input,
                value = $input.val(),
                valueLowerCase = value.toLowerCase(),
                valid = false;
            this.element.children("option").each(function () {
                if ($(this).text().toLowerCase() === valueLowerCase) {
                    this.selected = valid = true;
                    return false;
                }
            });

            // Found a match, nothing to do
            if (valid) {
                return;
            }

            var uiAutocomplete = $input.data("uiAutocomplete");
            if (!uiAutocomplete) {
                uiAutocomplete = $input.date("autocomplete");
            }
            if (uiAutocomplete) {
                uiAutocomplete.term = "";
            }
        },

        _updatePeriodInput: function (start_datetime, end_datetime) {

            this.label.text('');
            this.hidden.attr("name", this.ns + ".period").val('');

            var start = $.datepicker.formatDate('dd.mm.yy', new Date(start_datetime));
            var end = $.datepicker.formatDate('dd.mm.yy', new Date(end_datetime));
            var format = 'yy-mm-dd';

            if (start_datetime && end_datetime) {
                this.hidden.val([
                    $.datepicker.formatDate(
                        format,
                        new Date(start_datetime)
                    ),
                    $.datepicker.formatDate(
                        format,
                        new Date(end_datetime)
                    )
                ].join('--'));
                this.input.val(start === end ? start : start + '–' + end);
                this.label.text('=');
            } else if (start_datetime) {
                this.hidden.val(JSON.stringify({
                    val: $.datepicker.formatDate(
                        format,
                        new Date(start_datetime)
                    ),
                    op: '>='
                }));
                this.input.val(start);
                this.label.text('>=');
            } else if (end_datetime) {
                this.hidden.val(JSON.stringify({
                    val: $.datepicker.formatDate(
                        format,
                        new Date(end_datetime)
                    ),
                    op: '<='
                }));
                this.label.text('<=');
            }
        },

        _renderItem: function (ul, item) {
            return $( "<li class='crm-search-ui-menu-item'>" )
                .append( $( "<div>" ).text( item.label ) )
                .appendTo( ul );
        },

        _showPeriodDialog: function () {
            $('#c-combobox-dialog').remove();
            var d = $('<div id="c-combobox-dialog"></div>').appendTo('body');
            return d.periodDialog();
        },

        _destroy: function () {
            this.wrapper.remove();
            this.element.show();
        }
    });
})(jQuery);
