(function($) {
    $.crm = $.crm || {};
    $.crm.search = {

        state: null,
        url: $.crm.app_url + '?module=contactSearch',

        serialize: function (form, id, index, ignore_encoding) {
            var that = this;
            var o = {};
            var data = form.serializeArray();

            // parse to temporary hierarchy object
            for (var i = 0, n = data.length; i < n; i += 1) {
                var value = (data[i].value || '').trim();
                try {
                    value = JSON.parse(value);
                } catch (e) {
                }
                var name = data[i].name;
                if (!name || !value || ($.isPlainObject(value) && $.isEmptyObject(value))) {
                    continue;
                }
                if (id && name.indexOf(id) !== 0) {
                    continue;
                }
                if (index !== undefined && name.indexOf('[' + index + ']') === -1) {
                    continue;
                }

                // must be string
                var val = '' + ($.isPlainObject(value) ? value['val'] : value);
                val = $.trim(val);
                if (!val) {
                    continue;
                }

                var op = $.isPlainObject(value) ? (value['op'] || '=') : '=';
                val = val.replace(/\//g, '\\/').replace(/&/, '\\&');

                var token = name.split('.');
                var p = o;
                for (var j = 0, l = token.length - 1; j < l; j += 1) {
                    var t = (token[j] || '').trim();
                    if (typeof p[t] === "undefined" || typeof p[t] !== 'object') {
                        p[t] = {};
                    }
                    p = p[t];
                }
                var t = token[token.length - 1];
                if ($.isArray(p[t])) {
                    p[t].push({
                        val: val, op: op
                    });
                } else if (!$.isPlainObject(p[t])) {
                    p[t] = [
                        {
                            val: val, op: op
                        }
                    ];
                }
            }

            // flatting object to 1d hash-map
            var flat = function (o, h, key) {
                if ($.isPlainObject(o)) {
                    for (var k in o) {
                        if (o.hasOwnProperty(k)) {
                            flat(o[k], h, key ? (key + '.' + k) : k);
                        }
                    }
                } else if (typeof o !== 'undefined') {
                    h[key] = o;
                }
            };
            var h = {};
            flat(o, h, '');

            // result data array
            var r = [];
            for (var k in h) {
                if (h.hasOwnProperty(k)) {
                    if ($.isArray(h[k]) && h[k].length > 1) {
                        for (var i = 0, n = h[k].length; i < n; i += 1) {
                            var val = h[k][i].val;
                            if (!ignore_encoding) {
                                val = that.encodeURIComponent(h[k][i].val);
                            }
                            r.push(k + '[' + i + ']' + h[k][i].op + val);
                        }
                    } else {
                        var val = h[k][0].val;
                        if (!ignore_encoding) {
                            val = that.encodeURIComponent(h[k][0].val);
                        }
                        r.push(k + h[k][0].op + val);
                    }
                }
            }

            return r.join('&');
        },

        encodeURIComponent: function(val) {
            val = encodeURIComponent(val);
            // workaround apache AllowEncodedSlashes OFF mode
            // see http://www.leakon.com/archives/865
            val = val.replace(new RegExp('%2F', 'g'), '%252F').replace(new RegExp('%5C', 'g'), '%255C');
            return val;
        },

        indexBlocks: function (id) {
            var map = { };
            var items = $('#c-search-block .js-field[data-multiple="1"]');
            if (id) {
                items = items.filter('[data-id="' + id + '"]');
            }
            items.each(function () {
                var item = $(this);
                var id = item.data('id');
                var index = map[id] !== undefined ? ++map[id] : map[id] = 0;
                item.attr('data-index', index);
                item.data('index', index);
                item.find(':input').each(function () {
                    var input = $(this);
                    var name = input.attr('name').replace(/\[\d+\]/, '').replace(id, id + '[' + index + ']');
                    input.attr('name', name);
                });
            });
        }

    };
})(jQuery);


