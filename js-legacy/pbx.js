/* Controller for templates/actions/pbx/PbxIfrLayout.html */
window.PBXFrame = ( function($) {

    // Auto-update list of calls in background once in a while
    var INTERVAL_UPDATE_WITH_CALLS = 11; // sec
    var INTERVAL_UPDATE_NO_CALLS = 173; // sec

    var PBXFrame = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.call_template = options["call_template"];
        that.storage_name = "crm/pbx_removed_calls";
        that.call_live_time = 1000 * 60 * 2;

        // DYNAMIC VARS

        // INIT
        that.initClass();

        PBXFrame.__last_instance = this;
    };

    PBXFrame.prototype.initClass = function() {
        var that = this,
            $document = $(document),
            $calls_wrapper = that.$wrapper;

        // Init accordion
        that.initAccordion();
        // Init closed calls watcher
        that.availabilityWatcher();
        // Draw funnel icons
        that.renderSVG();

        // Update list of calls from time to time
        this.scheduleUpdate = (function() { "use strict";
            var timeout = null;
            return function scheduleUpdate(seconds) {
                if (timeout) clearTimeout(timeout);
                timeout = setTimeout(updateListFromServer, (seconds || INTERVAL_UPDATE_NO_CALLS) * 1000);
            };
        }());
        this.scheduleUpdate(INTERVAL_UPDATE_WITH_CALLS);

        // Change stuff when info about calls come
        $document.on('wa_crm_calls', onCRMCalls);

        // Expand call info when user clicked notification
        $document.on('wa_crm_activate_call', function(evt, call_id) {
            activateCall(call_id);
        });

        // Signal to parent window that we're ready to accept events abount calls
        $document.on("wa_pbx_parent_ready", function() {
            $document
                .trigger("wa_pbx_ready")
                .trigger("wa_pbx_resize");
        });

        // Link "X" to hide the call
        that.$wrapper.on("click", ".js-delete-call", function(event) {
            event.preventDefault();
            event.stopPropagation();

            var $h2 = $(this).closest(".js-call-header");
            var call_id = $h2.data("call-id");
            if (call_id) {
                if ($h2.is('.no-data-stub')) {
                    // Do not remember in localStorage about "New incoming call" stubs removed
                    that.removeCall(call_id);
                } else {
                    // Normal calls are remembered and not shown again after reload
                    removeCall(call_id);
                }
            }
        });

        //

        function onCRMCalls(evt, data) {

            var active_id = getActiveId();

            // Build a hashmap { call_id => $header_element } of all calls in accordion.
            // Immidiately remove pending calls that are dropped.
            var existing_calls = $calls_wrapper.find(".js-call-header").get().reduce(function(hashmap, h2) {
                var $h2 = $(h2);
                var call_id = $h2.data('call-id');
                var old_call_status = $h2.data('call-status');

                if (old_call_status == 'PENDING' && data.call_status[call_id] != 'PENDING' && data.call_status[call_id] != 'CONNECTED') {
                    $h2.next().remove();
                    $h2.remove();
                    return hashmap;
                }

                hashmap[call_id] = $h2;
                return hashmap;
            }, {});

            // Immidiately add new calls as pending
            data.call_ids.forEach(function(id) {
                if (!existing_calls[id]) {
                    var template = that.call_template,
                        $new_elem = $(template);

                    $new_elem.find('[data-call-id]').attr('data-call-id', id);
                    $new_elem.find('[data-call-status]').attr('data-call-status', data.call_status[id]);
                    existing_calls[id] = $new_elem.find(".js-call-header");
                    $calls_wrapper.append($new_elem.children());
                }
            });

            // Notify accordion about our changes
            that.refresh();
            activateCall(active_id);

            // Update accordion via XHR
            updateListFromServer();
        }

        function activateCall(call_id) {
            var active_index = null;
            $calls_wrapper.find(".js-call-header[data-call-id]").each(function(i, h2) {
                if ($(h2).data('call-id') == call_id) {
                    active_index = i;
                    return false;
                }
            });

            if (active_index !== null) {
                that.$wrapper.accordion("option", "active", active_index);
            }
        }

        function getActiveId() {
            var active_index = that.$wrapper.accordion('option', 'active');
            if (active_index !== false) {
                return $calls_wrapper.find(".js-call-header").eq(active_index).data('call-id') || null;
            }
            return null;
        }

        // Load list of calls from server and update accordion
        function updateListFromServer() {
            $.get(window.location.href, function(result) {

                // Determine which call is unfolded
                var active_id = getActiveId();

                // Build a hashmap { call_id => $header_element } of all calls in accordion.
                // Immidiately remove "New incoming call" stubs.
                var old_calls = {};
                $calls_wrapper.find(".js-call-header").each(function(i, h2) {
                    var $h2 = $(h2);
                    var call_id = $h2.data('call-id');
                    if (call_id && !$h2.is('.no-data-stub')) {
                        old_calls[call_id] = $h2;
                    } else {
                        $h2.next().remove();
                        $h2.remove();
                    }
                });

                // Replace with new data
                var new_calls = {};
                var no_new_calls = true;
                $('<div>').html(result).children(".js-call-header").each(function() {
                    var $h2 = $(this);
                    var call_id = $h2.data('call-id');
                    if (old_calls[call_id]) {
                        old_calls[call_id].next().replaceWith($h2.next());
                        old_calls[call_id].replaceWith($h2);
                    } else {
                        var $body = $h2.next();
                        $calls_wrapper.append($h2);
                        $calls_wrapper.append($body);
                    }
                    new_calls[call_id] = $h2;
                    no_new_calls = false;
                });

                // Remove pending calls that are no longer in list
                for(var call_id in old_calls) {
                    if (!new_calls[call_id]) {
                        // Mark call data as outdated
                        old_calls[call_id].addClass('outdated');
                        old_calls[call_id].next().addClass('outdated');

                        // Remove pending call unless opened
                        if (old_calls[call_id].data('call-status') == 'PENDING') {
                            if (active_id === false || active_id != call_id) {
                                old_calls[call_id].next().remove();
                                old_calls[call_id].remove();
                            }
                        }
                    }
                }

                // Remove calls that user clicked "X" to hide
                that.availabilityWatcher();

                // Draw funnel icons
                that.renderSVG();

                // Notify accordion about our changes
                that.refresh();
                activateCall(active_id);

                // Schedule next update from server
                that.scheduleUpdate(no_new_calls ? INTERVAL_UPDATE_NO_CALLS : INTERVAL_UPDATE_WITH_CALLS);
            }, 'html');
        }

        function removeCall(call_id) {
            var local_storage = ( localStorage.getItem(that.storage_name) || "{}" ),
                storage = JSON.parse(local_storage),
                date = new Date();

            storage[call_id] = {
                id: call_id,
                date: date.getTime()
            };

            localStorage.setItem(that.storage_name, JSON.stringify(storage));

            that.removeCall(call_id);
        }
    };

    PBXFrame.prototype.initAccordion = function() {
        var that = this,
            $document = $(document),
            iframe_height_interval = null;

        that.$wrapper.accordion({
            heightStyle: 'content',
            collapsible: true,
            active: false,
            animate: false,
            header: '>h2',
            icons: false,
            beforeActivate: function(evt, ui) {

                // Do not expand call if user clicked a delete icon
                if (evt && evt.srcElement && $(evt.srcElement).is('.js-delete-call')) {
                    return false;
                }

                if (ui.newHeader.length + ui.oldHeader.length < 2) {
                    if (iframe_height_interval) {
                        clearInterval(iframe_height_interval);
                    }
                    iframe_height_interval = setInterval(function() {
                        $document.trigger('wa_pbx_resize');
                    }, 100);
                }
            },
            activate: function(event, ui) {
                if (iframe_height_interval) {
                    clearInterval(iframe_height_interval);
                }
                iframe_height_interval = null;

                $document.trigger('wa_pbx_resize');
            },
            create: function(event, ui) {
                $document.trigger('wa_pbx_resize');
            }
        });
    };

    PBXFrame.prototype.availabilityWatcher = function() {
        var that = this,
            storage = getStorage(),
            storage_keys = ( storage ? Object.keys(storage) : [] );

        if (!storage_keys.length) {
            return false;
        }

        var call_ids = that.getCallIds();

        if (!call_ids.length) {
            localStorage.removeItem(that.storage_name);
            return false;
        }

        $.each(storage_keys, function(index, call_id) {
            var storage_call = storage[call_id],
                current_date = new Date();

            var is_old = (that.call_live_time > ( current_date.getTime() - parseInt(storage_call.date) ) ),
                is_exist = ( call_ids.indexOf(call_id) >= 0 );

            if (is_old && is_exist) {
                that.removeCall(call_id, true);
            }
        });

        function getStorage() {
            var local_storage = ( localStorage.getItem(that.storage_name) || "{}" );
            return JSON.parse(local_storage);
        }
    };

    PBXFrame.prototype.getCallIds = function() {
        var that = this,
            result = [];

        that.$wrapper.find(".js-call-header").each( function() {
            var $header = $(this),
                id = "" + $header.data("call-id");

            result.push(id);
        });

        return result;
    };

    PBXFrame.prototype.removeCall = function(id, force) {
        var that = this,
            animate_class = "is-removing",
            removed_class = "is-removed";

        var $call = that.$wrapper.find("[data-call-id=\"" + id + "\"]");

        if (force) {
            //$call.addClass(removed_class);
            $call.remove();

        } else {
            $call.addClass(animate_class);

            setTimeout( function() {
                var is_exist = $.contains(document, $call[0]);
                if (is_exist) {
                    //$call.addClass(removed_class).removeClass(animate_class);
                    $call.remove();
                    that.refresh();
                }
            }, 250);
        }

        setTimeout( function() {
            var ids = that.getCallIds();
            if (!ids.length) {
                that.$wrapper.html('');
            }
        }, 250);
    };

    PBXFrame.prototype.refresh = function() {
        var that = this;

        that.$wrapper.accordion("refresh");
        $(document).trigger('wa_pbx_resize');
    };

    PBXFrame.prototype.renderSVG = function() {
        var that = this,
            $wrapper = that.$wrapper;

        if (typeof d3 !== "object") {
            return false;
        }

        var SVGIcon = ( function($, d3) {

            SVGIcon = function(options) {
                var that = this;

                // DOM
                that.$icon = options["$icon"];
                that.svg = d3.select(that.$icon[0]).append("svg");

                // VARS
                that.type = that.$icon.data("type");

                // DYNAMIC VARS
                that.icon_w = that.$icon.outerWidth();
                that.icon_h = that.$icon.outerHeight();

                // INIT
                that.initClass();
            };

            SVGIcon.prototype.initClass = function() {
                var that = this;

                that.svg.attr("width", that.icon_w)
                    .attr("height", that.icon_h);

                if (that.$icon.hasClass("funnel-state")) {
                    that.renderFunnelState();
                }

                // save backdoor
                that.$icon.data("icon", that);
            };

            SVGIcon.prototype.renderFunnelState = function() {
                var that = this,
                    color = ( that.$icon.data("color") || "#aaa" );

                var svg = that.svg,
                    group = svg.append("g");

                group.append("polygon")
                    .attr("points", "4,16 0,16 3.9,7.9 0,0 4,0 8.7,7.9")
                    .style("opacity", .33)
                    .style("fill", color);

                group.append("polygon")
                    .attr("points", "8,16 4,16 7.9,7.9 4,0 8,0 12.6,7.9")
                    .style("opacity", .66)
                    .style("fill", color);

                group.append("polygon")
                    .attr("points", "11.9,16 7.9,16 11.8,7.9 7.9,0 11.9,0 16,7.9")
                    .style("fill", color);
            };

            SVGIcon.prototype.refresh = function() {
                var that = this;

                that.icon_w = that.$icon.outerWidth();
                that.icon_h = that.$icon.outerHeight();

                that.svg
                    .attr("width", that.icon_w)
                    .attr("height", that.icon_h);
            };

            return SVGIcon;

        })(jQuery, d3);

        if ($wrapper.length) {
            $wrapper.find(".svg-icon").each( function() {
                var $icon = $(this),
                    icon = $icon.data("icon");

                if (icon) {
                    icon.refresh();
                } else if (SVGIcon) {
                    new SVGIcon({
                        $icon: $icon
                    });
                }
            });
        }
    };

    return PBXFrame;

})(jQuery);