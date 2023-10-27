var CRMContactsMergePage = ( function($) {

    CRMContactsMergePage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$table = that.$wrapper.find(".js-duplicates-table");
        that.$form = that.$wrapper.find("form");
        that.$merge_field = that.$wrapper.find('.crm-search-duplicates-by-field');
        that.$auto_merge_link = that.$wrapper.find('.crm-auto-merge-duplicates-link');
        that.$auto_merge_start_button = that.$wrapper.find('.crm-auto-merge-duplicates-start');
        that.$auto_merge_pause_button = that.$wrapper.find('.crm-auto-merge-duplicates-break');
        that.$auto_merge_resume_button = that.$wrapper.find('.crm-auto-merge-duplicates-resume');

        // VARS
        that.is_admin = options.is_admin;
        that.groups_count = options.groups_count;
        that.messages = options.messages;

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMContactsMergePage.prototype.initClass = function() {
        var that = this;
        //
        that.initSubmit();
        //
        that.initAutoMerge();
        //
        that.initGroupMergeLinks();
    };

    CRMContactsMergePage.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form;

        $form.on("submit", function (e) {
            e.preventDefault();
            $(this).find('.js-icon').remove().end()
                   .find('.js-loading').show();
            var field = that.$merge_field.val();
            location.href = $.crm.app_url +'?module=contactMergeDuplicates&field='+ field +($.crm.iframe ? '&iframe=1' : '');
        });
    };

    CRMContactsMergePage.prototype.initAutoMerge = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $merge_field = that.$merge_field,
            $auto_merge_block = that.$wrapper.find('.crm-auto-merge-duplicates-start-text'),
            $progress_block = that.$wrapper.find('.crm-auto-merge-duplicates-progress'),
            $link = that.$auto_merge_link,
            $table = that.$table,
            $start_button = that.$auto_merge_start_button,
            $pause_button = that.$auto_merge_pause_button,
            $resume_button = that.$auto_merge_resume_button,
            df = null,
            in_pause = false;

        $link.on("click", function(e) {
            e.preventDefault();
            $auto_merge_block.toggle();
            $start_button.toggle();
        });

        var setProgressMessage = function (count, total_count) {
            var template = that.messages.progress,
                percentage = Math.round(parseFloat(count / total_count) * 1000) / 10,
                message = template
                    .replace(":percentage:", percentage || 0)
                    .replace(":count:", count)
                    .replace(":total_count:", total_count);
            $progress_block.find('.crm-text').text(message);
        };

        var runProcess = function() {
            var field = $merge_field.val(),
                count = 0,
                total_count = that.groups_count;

            $pause_button.show();
            $start_button.hide();
            $resume_button.hide();
            $progress_block.show();

            $wrapper.find('.pager').hide();

            var workupLink = function($link, response) {
                if (response.result && response.result.total_count === response.result.merged_count) {
                    var contact_url = $.crm.app_url + 'contact/' + response.master.id + '/';
                    $link.closest('tr').find('td:not(:first)').css({
                        textDecoration: 'line-through'
                    }).end().find('td:first').html(
                        '<a href="' + contact_url + '" target="_blank">' + $.crm.encodeHTML(response.master.name) + '</a>'
                    );
                } else {
                    $link.addClass('crm-partial');
                }

                var message = response.message || that.messages.done || 'Done';

                var td = $link.closest('td').css({
                    textDecoration: ''
                }).html('<span class="float-right" style="margin-right: 10px;">' + message + '</span>');
                td.append($link.hide().addClass('crm-finished'));
            };

            var loadMergeDuplicates = function () {
                var offset = $wrapper.find('.crm-merge.crm-js-partial').length,
                    url = $.crm.app_url + '?module=contact&action=mergeDuplicates';
                $.get(url, {offset: offset, field: field, no_js: 1})
                    .done(function (html) {
                        if (!html) {
                            done();
                            return;
                        }
                        var $tmp = $('<div>').html(html),
                            $tbody = $tmp.find('.crm-duplicates-table tbody'),
                            $rows = $tbody.find('.crm-mergeduplicates-row');
                        if ($rows.length) {
                            $table.find('tbody').append($rows);
                            step();
                        } else {
                            done();
                        }
                        $tmp.remove();
                    });
            };

            var mergeContacts = function($link) {
                var url = $.crm.app_url + '?module=contact&action=mergeDuplicatesGetContacts',
                    value = $link.attr('data-field-value'); // not using data(fieldValue) cause of implicit type casting (i.e. 01 -> 1)
                $.get(url, {
                    field: field,
                    value: $link.attr('data-field-value'),
                    master_slaves: 1
                }, function (r) {
                    if (r && r.status === 'ok' && !$.isEmptyObject(r.data)) {
                        merge($link, r.data);
                    } else {
                        $link.addClass('crm-finished');
                        df.resolve();
                    }
                }, 'json').fail(function () {
                    df.resolve();
                });
            };

            var merge = function($link, data) {
                crmContactMerger.merge({
                    master_id: data.master,
                    slave_ids: data.slaves,
                    onDone: function (r) {
                        workupLink($link, r);
                        df.resolve();
                    },
                    onError: function () {
                        df.resolve();
                    }
                });
            };

            var pause = function () {
                if (!in_pause) {
                    step();
                } else {
                    setTimeout(pause, 1000);
                }
            };

            var step = function () {
                var $link = $wrapper.find('.crm-merge:not(.crm-finished):first');
                if (!$link.length) {
                    if (count < total_count) {
                        loadMergeDuplicates();
                    } else {
                        done();
                    }
                    return;
                }

                $link.parent().find('.crm-loading').css('opacity', 1);
                df = new $.Deferred();

                df.fail(function () {
                    $pause_button.hide();
                    $resume_button.show();
                });

                df.done(function () {
                    count += 1;
                    setProgressMessage(count, total_count);

                    if (!in_pause) {
                        step();
                    } else {
                        pause();
                    }
                });

                mergeContacts($link);
            };

            var done = function () {
                $start_button.hide();
                $resume_button.hide();
                $pause_button.hide();
                $progress_block.hide();
                that.$wrapper.find('.crm-attention-message').hide();
                that.$wrapper.find('.crm-done-message').show();
            };

            step();
        };


        // init process buttons

        $start_button.click(function (e) {
            e.preventDefault();
            runProcess();
        });
        $resume_button.click(function (e) {
            e.preventDefault();
            $pause_button.show();
            $resume_button.hide();
            in_pause = false;
        });
        $pause_button.click(function (e) {
            e.preventDefault();
            in_pause = true;
            $pause_button.hide();
            $resume_button.show();
        });

        setProgressMessage(0, that.groups_count);

    };

    CRMContactsMergePage.prototype.initGroupMergeLinks = function() {
        var that = this;
        let iframe = new URLSearchParams(document.location.search).get('iframe');
        if (window.parent && iframe) {
            that.$table.find(".js-merge-group-link").on("click", function (e) {
                e.preventDefault();
                if (window.parent.history && window.parent.history.pushState) {
                    window.parent.history.pushState({ reload: true }, '', this.href);
                } else {
                    window.parent.location.href = this.href;
                }
            });
        }
    }

    return CRMContactsMergePage;

})(jQuery);
