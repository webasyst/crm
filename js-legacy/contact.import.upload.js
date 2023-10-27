var crmContactImportUpload = (function ($) {

    var crmContactImportUpload = function (options) {

        var $wrapper = options['$wrapper'],
            $form = $wrapper.find('form'),
            $table = $wrapper.find('.crm-import-upload-table'),
            $button = $wrapper.find('.crm-import-upload-button'),
            $counter = $wrapper.find('.crm-total-count-column .crm-count');

        var that = this;
        that.$wrapper = $wrapper;
        that.$form = $form;

        var fieldInfo = options.fieldInfo;
        var separatorRegex = /[\s~!@#\$%\^&\*\(\)_\+\|\\=\-`\{\}:"<>\?\[\];',\.\/]+/g;

        // Field type select lists
        var colState = function(col, state) {
            col += 1;
            if (state) {
                $table.find("td:nth-child("+col+")").addClass('crm-highlight-cell');
                $button.attr('disabled', false);
            } else {
                $table.find("td:nth-child("+col+")").removeClass('crm-highlight-cell');
            }
        };
        $("select", $table).each(function (i) {
            $(this).change(function () {
                colState(i, $(this).val());
            });
        });

        // First line checkbox
        var flCheckboxOnChange = function () {
            if ($(this).is(":checked")) {
                $table.find("tbody tr:first").removeClass('crm-ignored-row');
                $counter.html((parseInt($counter.html(), 10) || 0) + 1);
            } else {
                $table.find("tbody tr:first").addClass('crm-ignored-row');
                $counter.html((parseInt($counter.html(), 10) - 1) || "");
            }
        };
        $("input[name='first_line']", $form).change(flCheckboxOnChange);

        // Try to guess field for each column using the first line
        var headerFound = false;
        var $selects = $table.find('select');
        $table.find('tbody tr:first-child td').each(function (k, v) {
            var bestGuess = null, // field id, string, matches an <option value="..."> in field selects
                bgLength = 0,
                bgLocalized = false;
            var value = $(v).text();
            var sValue = value.toLowerCase().replace(separatorRegex, '');
            for (var id in fieldInfo) {
                var info = fieldInfo[id];
                var match = false;
                var mLength = 0;
                var mLocalized = false;

                // check if cell contains field id at the begining
                if (id.length > bgLength && !bgLocalized && sValue.indexOf(id) == 0) {
                    mLength = id.length;
                    match = true;
                }

                // Check if cell contains localized field name at the begining
                var locName = info.name.toLowerCase().replace(separatorRegex, '');

                if (locName && locName.length > bgLength && sValue.indexOf(locName) == 0) {
                    mLength = locName.length;
                    match = true;
                    mLocalized = true;
                }

                if (!match) {
                    continue;
                }

                // We've got a match. Need to check if extension and/or subfields also match
                var subfield = null, ext = null;
                if (info.fields) {
                    for(var f in info.fields) {
                        // id match?
                        if (sValue.indexOf(f) >= 0) {
                            subfield = f;
                            mLength += f.length;
                            break;
                        }
                        // localized name match?
                        if (sValue.indexOf(info.fields[f].name.toLowerCase().replace(separatorRegex, '')) >= 0) {
                            subfield = f;
                            mLength += info.fields[f].name.length;
                            break;
                        }
                    }

                    if (!subfield) {
                        // no match, sad but true
                        continue;
                    }
                }

                if (info.ext) {
                    for(var e in info.ext) {
                        // id match?
                        if (sValue.indexOf(e) >= 0) {
                            ext = e;
                            break;
                        }
                        // localized name match?
                        if (sValue.indexOf(info.ext[e].toLowerCase().replace(separatorRegex, '')) >= 0) {
                            ext = e;
                            break;
                        }
                    }
                }

                bestGuess = id+(subfield ? ':'+subfield : '')+(ext ? '.'+ext : '');
                bgLength = mLength;
                bgLocalized = mLocalized;
            }

            if (bestGuess && bgLength) {
                $selects.eq(k).val(bestGuess);
                colState(k, true);
                headerFound = true;
            }
        });

        // Is header is found, no need to import the first csv line
        if (headerFound) {
            flCheckboxOnChange.call($form.find("input[name='first_line']").attr('checked', false));
        }

        that.initCreateDealsSettings();

        that.initAddToSegments();

        $form.submit(function (e) {
            e.preventDefault();

            var $require = $wrapper.find('.crm-warning-require-primary-fields').hide();

            var required_chosen = false;
            $table.find("select").each(function () {
                var v = $(this).val();
                if (v == 'firstname' || v == 'lastname' || v == 'company' || v.substr(0,5) == 'email') {
                    required_chosen = true;
                    return false;
                }
            });

            if (!required_chosen) {
                $require.show();
                return;
            }

            $wrapper.find('.crm-loading').show();

            var process = new crmContactImportProcess({
                '$wrapper': $wrapper,
                'need_validation': $form.find('[name=validate]').is(':checked'),
                'messages': options.messages || {}
            });
            process.run();
        });

    };

    crmContactImportUpload.prototype.initCreateDealsSettings = function () {
        var that = this,
            $form = that.$form,
            $checkbox = $form.find('[name="create_deals"]'),
            $deal_block = $form.find('.crm-deals-settings-wrapper'),
            $funnel = $form.find('[name="deal_funnel_id"]'),
            $stage = $form.find('[name="deal_stage_id"]');

        $checkbox.click(function () {
            var $el = $(this);
            if ($el.is(':checked')) {
                $deal_block.show();
            } else {
                $deal_block.hide();
            }
        });

        $funnel.change(function () {
            $stage.load('?module=contactImportUpload&action=stagesByFunnel&id=' + $(this).val());
        });
    };

    crmContactImportUpload.prototype.initAddToSegments = function () {
        var that = this,
            $form = that.$form,
            $checkbox = $form.find('[name="add_to_segments"]');
        $checkbox.click(function () {

            if (!$(this).is(':checked')) {
                $('.c-add-to-segments-names', $form).html('');
                return;
            }

            var url = $.crm.app_url + '?module=contactOperation&action=AddToSegments';
            $.get(url, function (html) {
                var dialog = new CRMDialog({
                    html: html,
                    onOpen: function ($dialog) {
                        new CRMContactsOperationAddToSegments({
                            '$wrapper': $dialog,
                            'onSave': function (data, $dialog) {
                                var dialog = $dialog.data('dialog'),
                                    names = [],
                                    inputs = [],
                                    $items = $('.c-segment-item', $dialog);

                                for (var i = 0; i < data.segment_ids.length; i += 1) {
                                    var id = data.segment_ids[i],
                                        $item = $items.filter('[data-id="' + id + '"]');
                                    names.push($item.find('.c-name').text());
                                    inputs.push('<input type="hidden" name="segment_id[]" value="' + id + '">');
                                }

                                names = '<span>' + names.join(',') + '</span>';
                                inputs = inputs.join(' ');

                                $('.c-add-to-segments-names', $form).html(names + inputs);

                                dialog.close();

                                return false;
                            }
                        });
                    }
                });
            });
        });
    };

    var crmContactImportProcess = function (options) {
        var that = this;

        that.$wrapper = options['$wrapper'];
        that.$form = that.$wrapper.find('form');
        that.processId = null;
        that.$loading = that.$wrapper.find('.crm-loading');
        that.timer = null;
        that.messages = options.messages || {};

        that.url = $.crm.app_url + '?module=contact&action=importProcess' + (options.need_validation ? '&need_validation=1' : '');

        that.$progress_bar_dialog = that.$wrapper.find('.crm-import-progressbar-dialog');
        that.$progress_bar = that.$progress_bar_dialog.find('.crm-progressbar')
        
    };

    crmContactImportProcess.prototype.run = function () {
        var that = this,
            $form = that.$form;

        var step = function () {
            if (!that.processId) {
                return;
            }
            $.get(that.url, { processid: that.processId, t: Math.random() },
                function (response) {

                    that.timer && clearTimeout(that.timer);
                    that.timer = null;

                    var $progress_bar_val = that.$progress_bar.find('.ui-progressbar-value');
                    $progress_bar_val.stop();
                    $progress_bar_val.clearQueue();

                    if (response.ready) {
                        $progress_bar_val.animate({ width: '100%' }, {
                            duration: 500,
                            complete: function () {

                                // tell server to remove temporary files
                                $.post(that.url, { processid: that.processId, file: 1 }, 'json');

                                if (response.rowsRejected > 0) {
                                    if (response.rowsAdded <= 0) {
                                        alert(that.messages.no_imported);
                                    } else {
                                        alert(that.messages.some_imported);
                                    }
                                } else {
                                    alert(that.messages.all_imported);
                                }

                                if (response.rowsAdded > 0) {
                                    location.href = $.crm.app_url + 'contact/import/result/' + response.timeStart + '/';
                                } else {
                                    location.href = $.crm.app_url + 'contact/import/';
                                }
                            },
                            queue: false
                        });

                        return;
                    }

                    $progress_bar_val.animate({ width: ""+Math.round( response.done * 100.0 / response.total ) + '%' }, {
                        duration: 500,
                        queue: false
                    });

                    that.timer = setTimeout(step, 3000 + (Math.random() - 0.5) * 400);

                },
                'json');
        };

        $.post(that.url, $form.serializeArray(), function (data) {
            that.processId = data.processId;
            if (that.processId) {
                that.$progress_bar_dialog.show();
                that.$progress_bar.progressbar({ value: 0 });
                step();
            }
        }, 'json');
    };


    return crmContactImportUpload;

})(jQuery);
