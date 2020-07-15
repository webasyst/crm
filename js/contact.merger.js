var crmContactMerger = (function ($) {

    var crmContactMerger = function (options) {
        var that = this;

        that.master_id = options.master_id || 0;
        that.slave_ids = options.slave_ids || [];
        that.url = $.crm.app_url + '?module=contact&action=mergeRun';
        that.timer = null;
        that.processId = null;

        that.onDone = options.onDone || function () {};
        that.onError = options.onError || function () {
            throw new Error('Error processing request.');
        };

    };

    crmContactMerger.prototype.process = function () {
        var that = this,
            url = that.url,
            requests = 0,
            post_data = {};

        post_data.master_id = that.master_id;
        post_data.slave_ids = that.slave_ids;

        // Sends messenger and delays next messenger in 3 seconds
        var process = function () {
            that.timer && clearTimeout(that.timer);
            that.timer = setTimeout(process, 3200);
            if (!that.processId || requests >= 2) {
                return;
            }
            post_data.processId = that.processId;
            $.post(url, post_data, null, 'json')
                .done(function (response) {
                    requests--;

                    if (!that.processId || !response.ready) {
                        return;
                    }

                    // Stop sending messengers
                    var pid = that.processId;
                    if (!pid) {
                        return; // race condition is still possible, but not really dangerous
                    }

                    that.timer && clearTimeout(that.timer);
                    that.timer = null;
                    that.processId = null;

                    that.onDone(response);
                })
                .fail(that.onError);
            requests++;

        };

        $.post(url, post_data, null, 'json')
            .done(function (data) {
                    if (!data.processId) {
                        that.onError();
                    }
                    that.processId = data.processId;
                    process();
                })
            .fail(that.onError);

        return this;
    };

    crmContactMerger.prototype.cancel = function () {
        this.timer && clearTimeout(this.timer);
        $.post(this.url, { processId: this.processId, ready: 1 });
        return this;
    };

    // STATIC CLASS METHOD
    crmContactMerger.merge = function (options) {
        var merger = new crmContactMerger(options);
        return merger.process();
    };

    return crmContactMerger;

})(jQuery);
