var CRMWebSocket = ( function($) {

    CRMWebSocket = function(options) {
        var that = this;
        that.app_url = options['app_url'] || ($ && $.crm && $.crm.app_url);
        that.channel = options["channel"];
        that.onmessage = options["onmessage"];
        that.onerror = options["onerror"];
        that.ws = null;
        that.initWS();
    };

    CRMWebSocket.prototype.initWS = function() {
        var that = this;
        $.get(that.app_url + '?module=ws&action=url&channel=' + that.channel, data => {
            if (data.status == 'ok') {
                console.log(data.data.ws_url);
                that.ws = new WebSocket(data.data.ws_url);
                that.ws.onclose = function () { 
                    console.log("WebSocket connection closed");
                    that.initWS();
                };
                that.ws.onmessage = that.onmessage;

                that.ws.addEventListener("error", (event) => {
                    console.log("WebSocket error: ", event);
                    if (typeof that.onerror === 'function') {
                        that.onerror("WebSocket error");
                    }
                });
            } else {
                if (typeof that.onerror === 'function') {
                    that.onerror("WebSocket connection fail");
                } else {
                    console.log("WebSocket connection fail");
                }
            }
        });
    }

    CRMWebSocket.prototype.sendMessage = function(message) {
        var that = this;
        try {
            that.ws.send(message);
            return true;
        } catch (e) {
            if (typeof that.onerror === 'function') {
                that.onerror(e);
            } else {
                console.log(e);
            }
            return false;
        }
    }

    CRMWebSocket.prototype.close = function() {
        var that = this;
        if (that.ws) {
            that.ws.onclose = function () { 
                console.log("Close WebSocket");
            };
            that.ws.close();
        }
    }
    
    return CRMWebSocket;
})(jQuery);