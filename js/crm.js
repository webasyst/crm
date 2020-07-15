// MAIN APP CONTROLLER
( function($) {
    $.ajaxSetup({
        cache: false
    });

    // Set up CSRF
    $(document).ajaxSend(function(event, xhr, settings) {
        if (settings.crossDomain || (settings.type||'').toUpperCase() !== 'POST') {
            return;
        }
        var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)"));
        var csrf = matches ? decodeURIComponent(matches[1]) : '';
        if (!settings.data && settings.data !== 0) {
            settings.data = '';
        }
        if (typeof(settings.data) === "string") {
            if (settings.data.indexOf('_csrf=') == -1) {
                settings.data += (settings.data.length > 0 ? '&' : '') + '_csrf=' + csrf;
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            }
        } else if (typeof(settings.data) === "object") {
            if (window.FormData && settings.data instanceof window.FormData) {
                if ('function' == typeof settings.data.set) {
                    settings.data.set('_csrf', csrf);
                } else {
                    settings.data.append('_csrf', csrf);
                }
            } else {
                settings.data['_csrf'] = csrf;
            }
        }
    });

    $(document).ajaxError(function(e, xhr, settings, exception) {

        // Session timeout, show login page
        if (xhr.getResponseHeader('wa-session-expired')) {
            return window.location.reload();
        }

        // Ignore 502 error in background process
        if (xhr.status === 502 && exception == 'abort' || (settings.url && settings.url.indexOf('background_process') >= 0) || (settings.data && settings.data.indexOf('background_process') >= 0)) {
            console && console.log && console.log('Notice: XHR failed on load: '+ settings.url);
            return;
        }

        // Generic error page
        else if (xhr.status !== 200 && xhr.responseText) {
            // Show error in development mode
            var is_debug_model = $.crm && $.crm.is_debug,
                html = xhr.responseText;

            if (is_debug_model) {
                html = $.crm.confirm.template
                    .replace('%title%', 'XHR error ' + xhr.status)
                    .replace('%text%', xhr.responseText)
                    .replace('%button%', 'Close');
            }

            var dialog = new CRMDialog({ html: html });
            dialog.$wrapper.find('.wa-exception-debug-dump #Trace pre').each(function() {
                var $pre = $(this);
                var new_html = $pre.html().replace(/^(#(#|\d+)\s+(wa-system|index\.php|\{main\}).*)$/gm, '<span style="color:#999">$1</span>');
                $pre.html(new_html);
            });

            // it is not crm dialog, just write responseText into document
            if (html.indexOf('crm-dialog-wrapper') === -1) {
                document.open("text/html");
                document.write(xhr.responseText); // !!! throws an "Access denied" exception in IE9
                document.close();
            }
        }
    });

    $.crm = $.extend($.crm || {}, {
        lang: false,
        app_url: false,
        backend_url: false,
        is_debug: false,
        is_page_loaded: false,
        content: false,
        sidebar: false,
        storage: new $.store(),
        locales: false,
        confirm: {
            show: showConfirm,
            // will be set at <head>
            template: ""
        },
        alert: {
            show: showAlert,
            // will be set at <head>
            template: ""
        },
        title: {
            pattern: "CRM — %s",
            set: function( title_string ) {
                if (title_string) {
                    var state = history.state;
                    if (state) {
                        state.title = title_string;
                    }
                    document.title = $.crm.title.pattern.replace("%s", title_string);
                }
            }
        },
        check: {
            email: function(email) {
                var result = false;
                if (email.length > 0 && (email.match(/.+?\@.+/g) || []).length >= 1) {
                    result = true;
                }
                return result;
            }
        },
        encodeHTML: function (html) {
            return html && (''+html).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        },
        renderSVG: function($wrapper) {
            // to find all icons and generate svg

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

                    } else if (that.$icon.hasClass("funnel")) {
                        that.renderFunnel();

                    } else if (that.$icon.hasClass("funnel-1")) {
                        that.renderFunnel(1);
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
                    // .attr("points", "4,16 0,16 3.9,7.9 0,0 4,0 8.7,7.9")
                        .attr("points", getX(4) + "," + getY(16) + " " + getX(0) + "," + getY(16) + " " + getX(3.9) + "," + getY(7.9) + " " + getX(0) + "," + getY(0) + " " + getX(4) + "," + getY(0) + " " + getX(8.7) + "," + getY(7.9))
                        .style("opacity", .33)
                        .style("fill", color);

                    group.append("polygon")
                    // .attr("points", "8,16 4,16 7.9,7.9 4,0 8,0 12.6,7.9")
                        .attr("points", getX(8) + "," + getY(16) + " " + getX(4) + "," + getY(16) + " " + getX(7.9) + "," + getY(7.9) + " " + getX(4) + "," + getY(0) + " " + getX(8) + "," + getY(0) + " " + getX(12.6) + "," + getY(7.9))
                        .style("opacity", .66)
                        .style("fill", color);

                    group.append("polygon")
                    // .attr("points", "11.9,16 7.9,16 11.8,7.9 7.9,0 11.9,0 16,7.9")
                        .attr("points", getX(11.9) + "," + getY(16) + " " + getX(7.9) + "," + getY(16) + " " + getX(11.8) + "," + getY(7.9) + " " + getX(7.9) + "," + getY(0) + " " + getX(11.9) + "," + getY(0) + " " + getX(16) + "," + getY(7.9))
                        .style("fill", color);

                    function getX(x) { return x/16 * that.icon_w; }
                    function getY(y) { return y/16 * that.icon_h; }
                };

                SVGIcon.prototype.renderFunnel = function(type) {
                    var that = this,
                        color = ( that.$icon.data("color") || "#aaa" ),
                        $color = $.crm.color(color),
                        hover_color = ( that.$icon.data("hover-color") || $color.getTone(20) ),
                        stroke_width = ( that.$icon.data("stroke-width") || 0 ),
                        use_hover = ( that.$icon.data("hover") || false ),
                        points;

                    var svg = that.svg,
                        group = svg.append("g");

                    var polygon = group.append("polygon")
                        .attr("class", "is-animated")
                        .attr("points", getPoints())
                        .attr("transform", "translate(" + stroke_width/2 + "," + stroke_width/2 + ")")
                        .style("fill", color);

                    if (stroke_width > 0) {
                        var stroke = $color.getTone(-20);

                        polygon
                            .style("stroke", stroke)
                            .style("stroke-width", stroke_width)
                    }

                    if (use_hover) {
                        var node = polygon.node(),
                            $item = $(node).closest(".c-state-item ");

                        $item.on("mouseenter", function() {
                            polygon.style("fill", hover_color);
                        });

                        $item.on("mouseleave", function() {
                            polygon.style("fill", color)
                        });
                    }

                    initRefresh();

                    function getPoints() {
                        var result = [],
                            max_x = that.icon_w,
                            max_y = that.icon_h,
                            delta = 6,
                            point_1, point_2, point_3, point_4, point_5, point_6;

                        if (stroke_width > 0) {
                            max_x -= stroke_width;
                            max_y -= stroke_width;
                        }

                        point_1 = "0,0";
                        point_2 = (max_x - delta) + ",0";
                        point_3 = max_x + "," + (max_y/2);
                        point_4 = (max_x - delta) + "," + max_y;
                        point_5 = "0," + max_y;
                        point_6 = delta + "," + max_y/2;

                        result.push(point_1);
                        result.push(point_2);
                        result.push(point_3);
                        result.push(point_4);
                        result.push(point_5);

                        if (!type || type !== 1) {
                            result.push(point_6);
                        }

                        return result.join(" ");
                    }

                    function initRefresh() {
                        var is_locked = false;

                        $(window).on("resize refresh-icons", onResize);

                        function onResize() {
                            if (!is_locked) {
                                is_locked = true;
                                setTimeout(refresh, 100);
                            }
                        }

                        function refresh() {
                            var if_exist = $.contains(document, that.$icon[0]);
                            if (if_exist) {
                                that.refresh();
                                polygon.attr("points", getPoints());
                            } else {
                                $(window).off("resize refresh-icons", onResize);
                            }
                            is_locked = false;
                        }
                    }
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

            if (typeof $wrapper === "string") {
                $wrapper = $($wrapper);
            }

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
        },
        tabSlider: function(options) {
            var Slider = ( function($) {

                Slider = function(options) {
                    var that = this;

                    // DOM
                    that.$wrapper = options["$wrapper"];
                    that.$slider = options["$slider"];
                    that.$activeSlide = ( options["$activeSlide"] || false);

                    // VARS

                    // DYNAMIC VARS
                    that.type_class = false;
                    that.left = 0;
                    that.wrapper_w = false;
                    that.slider_w = false;

                    // INIT
                    that.initClass();
                };

                Slider.prototype.initClass = function() {
                    var that = this,
                        $window = $(window);

                    // INIT

                    that.detectSliderWidth();
                    //
                    that.initStartPosition();

                    // EVENTS

                    $window.on("resize", onResize);
                    //
                    that.$wrapper.on("click", ".c-action", function(event) {
                        event.preventDefault();
                        var $link = $(this);
                        if ($link.hasClass("left")) {
                            that.moveSlider( false );
                        }
                        if ($link.hasClass("right")) {
                            that.moveSlider( true );
                        }
                    });

                    // FUNCTIONS

                    function onResize() {
                        var is_exist = $.contains(document, that.$wrapper[0]);
                        if (is_exist) {
                            var is_change = ( that.wrapper_w !== that.$wrapper.outerWidth() );
                            if (is_change) {
                                that.reset();
                            }
                        } else {
                            $window.off("resize", onResize);
                        }
                    }
                };

                Slider.prototype.initStartPosition = function() {
                    var that = this,
                        start_left = 0;

                    if (that.$activeSlide.length) {
                        var slide_w = that.$activeSlide.outerWidth(),
                            delta = Math.floor(Math.abs(that.$wrapper.offset().left - that.$activeSlide.offset().left));

                        if (delta + slide_w > that.wrapper_w) {
                            start_left = delta - 40;
                        }
                    }

                    if (start_left) {
                        that.start_left = start_left;
                        that.moveSlider(true, start_left);
                    } else {
                        that.showArrows();
                    }
                };

                Slider.prototype.detectSliderWidth = function() {
                    var that = this;

                    that.wrapper_w = that.$wrapper.outerWidth();
                    that.slider_w = that.$slider.outerWidth();
                };

                Slider.prototype.showArrows = function() {
                    var that = this;

                    if (that.left >= 0) {
                        if (that.wrapper_w < that.slider_w) {
                            setType("type-1");
                        } else {
                            setType();
                        }
                    } else {
                        if (that.wrapper_w < (that.slider_w - Math.abs(that.left) ) ) {
                            setType("type-2");
                        } else {
                            setType("type-3");
                        }
                    }

                    function setType( type_class ) {
                        if (that.type_class) {
                            that.$wrapper.removeClass(that.type_class);
                        }
                        if (type_class) {
                            that.$wrapper.addClass(type_class);
                            that.type_class = type_class;
                        }
                    }
                };

                Slider.prototype.setLeft = function( left ) {
                    var that = this;

                    if (!(Math.abs(left) > 0)) {
                        left = 0;
                    }

                    that.$slider.css("left", (left ? left + "px" : 0) );

                    that.left = left;
                };

                Slider.prototype.moveSlider = function(right, left) {
                    var that = this,
                        step = ( left ? left : 100 ),
                        delta = (that.slider_w - that.wrapper_w),
                        new_left = 0;

                    if (delta > 0) {
                        new_left = Math.abs(that.left) + ( right ? step : -step );
                        if (new_left > delta ) {
                            new_left = delta;
                        } else if (new_left < 0) {
                            new_left = 0;
                        }
                    }

                    that.setLeft(-new_left);
                    that.showArrows();
                };

                Slider.prototype.reset = function() {
                    var that = this;

                    //
                    that.setLeft(0);
                    //
                    that.detectSliderWidth();
                    //
                    that.showArrows();
                };

                return Slider;

            })($);

            return new Slider(options);
        },
        color: function(color) {
            // color format library

            var Color = ( function() {

                Color = function(color) {
                    var that = this;

                    // VARS
                    that.hex = color;
                    that.rgb = getRGB(that.hex);

                    // DYNAMIC VARS
                };

                Color.prototype.getRange = function() {
                    var that = this,
                        lab = (that.rgb ? rgb2lab(that.rgb) : false);

                    var minL = 40,
                        maxL = 90;

                    if (lab) {
                        var start_rgb = lab2rgb([maxL, lab[1], lab[2]]),
                            end_rgb = lab2rgb([minL, lab[1], lab[2]]);

                        var start_hex = rgb2hex(start_rgb),
                            end_hex = rgb2hex(end_rgb);

                        var result = [start_hex, end_hex];

                        result.getColor = function(percent) {
                            if ( percent >= 0 && percent <= 100 ) {
                                var r_start = start_rgb[0],
                                    r_end = end_rgb[0],
                                    g_start = start_rgb[1],
                                    g_end = end_rgb[1],
                                    b_start = start_rgb[2],
                                    b_end = end_rgb[2];

                                var r = Math.floor(r_start + (r_end - r_start)* percent/100),
                                    g = Math.floor(g_start + (g_end - g_start)* percent/100),
                                    b = Math.floor(b_start + (b_end - b_start)* percent/100);

                                return rgb2hex([r,g,b]);
                            } else {
                                return null;
                            }
                        };

                        return result;
                    } else {
                        return null;
                    }
                };

                Color.prototype.getTone = function( lift ) {
                    var that = this,
                        hsb = rgb2hsb(that.rgb);

                    hsb.s = format( hsb.s * (1 + lift/100) );
                    hsb.b = format( hsb.b * (1 + lift/100) );

                    var rgb = hsvToRgb(hsb.h/360,hsb.s/100,hsb.b/100);

                    return rgb2hex(rgb);

                    function format( number ) {
                        if (number > 100) {
                            number = 100;
                        } else if (number < 0) {
                            number = 0;
                        }
                        return number;
                    }
                };

                return Color;

                function getRGB(color) {
                    var rgb = false;
                    if (typeof color === "string") {
                        color = color.replace("#","");
                        if (color.length === 3) {
                            rgb = hex2rgb(color[0] + "" + color[0], color[1] + "" + color[1], color[2] + "" + color[2]);
                        } else if (color.length === 6) {
                            rgb = hex2rgb(color[0] + "" + color[1], color[2] + "" + color[3], color[4] + "" + color[5]);
                        }
                    } else if (typeof color === "object" && color.length === 3) {
                        rgb = color;
                    }
                    return rgb;
                }

                // HEX
                function hex2rgb(r,g,b) {
                    r = parseInt(r, 16);
                    g = parseInt(g, 16);
                    b = parseInt(b, 16);

                    return (r >= 0 && g >= 0 && b >= 0) ? [r,g,b] : null;
                }

                function rgb2hex(rgb) {
                    rgb[0] = parseInt(rgb[0].toFixed());
                    rgb[1] = parseInt(rgb[1].toFixed());
                    rgb[2] = parseInt(rgb[2].toFixed());

                    var r = rgb[0].toString(16),
                        g = rgb[1].toString(16),
                        b = rgb[2].toString(16);

                    if (r.length >= 0 && g.length >= 0 && b.length >= 0) {
                        return "#" + addZero(r) + addZero(g) + addZero(b);
                    } else {
                        return null;
                    }

                    function addZero(string) {
                        return ( string.length == 1 ? "0" + string : string );
                    }
                }

                // LAB
                function lab2rgb(lab){
                    var y = (lab[0] + 16) / 116,
                        x = lab[1] / 500 + y,
                        z = y - lab[2] / 200,
                        r, g, b;

                    x = 0.95047 * ((x * x * x > 0.008856) ? x * x * x : (x - 16/116) / 7.787);
                    y = 1.00000 * ((y * y * y > 0.008856) ? y * y * y : (y - 16/116) / 7.787);
                    z = 1.08883 * ((z * z * z > 0.008856) ? z * z * z : (z - 16/116) / 7.787);

                    r = x *  3.2406 + y * -1.5372 + z * -0.4986;
                    g = x * -0.9689 + y *  1.8758 + z *  0.0415;
                    b = x *  0.0557 + y * -0.2040 + z *  1.0570;

                    r = (r > 0.0031308) ? (1.055 * Math.pow(r, 1/2.4) - 0.055) : 12.92 * r;
                    g = (g > 0.0031308) ? (1.055 * Math.pow(g, 1/2.4) - 0.055) : 12.92 * g;
                    b = (b > 0.0031308) ? (1.055 * Math.pow(b, 1/2.4) - 0.055) : 12.92 * b;

                    return [Math.max(0, Math.min(1, r)) * 255,
                        Math.max(0, Math.min(1, g)) * 255,
                        Math.max(0, Math.min(1, b)) * 255]
                }

                function rgb2lab(rgb){
                    var r = rgb[0] / 255,
                        g = rgb[1] / 255,
                        b = rgb[2] / 255,
                        x, y, z;

                    r = (r > 0.04045) ? Math.pow((r + 0.055) / 1.055, 2.4) : r / 12.92;
                    g = (g > 0.04045) ? Math.pow((g + 0.055) / 1.055, 2.4) : g / 12.92;
                    b = (b > 0.04045) ? Math.pow((b + 0.055) / 1.055, 2.4) : b / 12.92;

                    x = (r * 0.4124 + g * 0.3576 + b * 0.1805) / 0.95047;
                    y = (r * 0.2126 + g * 0.7152 + b * 0.0722) / 1.00000;
                    z = (r * 0.0193 + g * 0.1192 + b * 0.9505) / 1.08883;

                    x = (x > 0.008856) ? Math.pow(x, 1/3) : (7.787 * x) + 16/116;
                    y = (y > 0.008856) ? Math.pow(y, 1/3) : (7.787 * y) + 16/116;
                    z = (z > 0.008856) ? Math.pow(z, 1/3) : (7.787 * z) + 16/116;

                    return [(116 * y) - 16, 500 * (x - y), 200 * (y - z)]
                }

                // HSB
                function hsvToRgb(h, s, v){
                    var r, g, b;

                    var i = Math.floor(h * 6);
                    var f = h * 6 - i;
                    var p = v * (1 - s);
                    var q = v * (1 - f * s);
                    var t = v * (1 - (1 - f) * s);

                    switch(i % 6){
                        case 0: r = v, g = t, b = p; break;
                        case 1: r = q, g = v, b = p; break;
                        case 2: r = p, g = v, b = t; break;
                        case 3: r = p, g = q, b = v; break;
                        case 4: r = t, g = p, b = v; break;
                        case 5: r = v, g = p, b = q; break;
                    }

                    return [r * 255, g * 255, b * 255];
                }

                function rgb2hsb(rgb) {
                    var rr, gg, bb,
                        r = rgb[0]/255,
                        g = rgb[1]/255,
                        b = rgb[2]/255,
                        h, s,
                        v = Math.max(r, g, b),
                        diff = v - Math.min(r, g, b),
                        diffc = function(c){
                            return (v - c) / 6 / diff + 1 / 2;
                        };

                    if (diff == 0) {
                        h = s = 0;
                    } else {
                        s = diff / v;
                        rr = diffc(r);
                        gg = diffc(g);
                        bb = diffc(b);

                        if (r === v) {
                            h = bb - gg;
                        }else if (g === v) {
                            h = (1 / 3) + rr - bb;
                        }else if (b === v) {
                            h = (2 / 3) + gg - rr;
                        }
                        if (h < 0) {
                            h += 1;
                        }else if (h > 1) {
                            h -= 1;
                        }
                    }
                    return {
                        h: Math.round(h * 360),
                        s: Math.round(s * 100),
                        b: Math.round(v * 100)
                    };
                }

            })();

            return new Color(color);
        },
        initWYSIWYG: function($textarea, options) {
            if (!$textarea || !$textarea.length || $textarea.data('redactor')) {
                return $textarea;
            }

            options = $.extend({
                minHeight: 130,
                //paragraphy: false,
                //convertDivs: false,
                buttons: [  //'format',
                            'inline', 'bold', 'italic', 'underline', 'deleted', 'lists', 'outdent', 'indent', 'image', 'link',
                            //'table', 'alignment',
                            'horizontalrule',  'fontcolor', 'fontsize', 'fontfamily'],
                plugins: ['fontcolor', 'fontsize', 'fontfamily'/*, 'alignment', 'inlinestyle', 'table'*/],
                allowedTags: 'a|b|i|u|pre|blockquote|p|strong|em|del|strike|span|ul|ol|li|div|span|br'.split('|'),
                uploadImage: false,
                lang: this.lang
            }, options || {});

            $textarea.redactor(options);

            $(document).one("wa_before_render", function() {
                if ($.contains(document, $textarea[0]) && $textarea.data('redactor')) {
                    $textarea.redactor("core.destroy");
                }
            });

            return $textarea;
        },
        escape: function(string) {
            return $("<div />").text(string).html();
        },
        runBackgroundWorker: function (options) {
            var coef_1 = Math.floor(Math.random() * 100) / 100,
                coef_2 = Math.floor(Math.random() * 100) / 100,
                delay = options.delay || 10000,
                half_delay = delay / 2,
                id = options.id || ('' + Math.random()).slice(2),
                timer = null,
                xhr = null,
                url = options.url;

            if (!url) {
                console.error('Empty url');
                return;
            }

            delay = delay + half_delay * coef_1;
            if (delay <= 200) {
                console.error('Delay value too low');
                return;
            }

            var process = function () {
                try {

                    var run = function () {

                        timer && clearTimeout(timer);
                        xhr && xhr.abort();

                        xhr = $.post(url, { process_id: id });
                        xhr.always(function () {
                            xhr = null;
                            timer = setTimeout(run, delay);
                        });
                        xhr.error(function () {
                            return false;
                        });
                    };

                    timer = setTimeout(run, 500 + 300 * coef_2);
                } catch (Error) {
                    console.error(['source email worker fail', Error]);
                }
            };

            process();
        }
    });

    function showConfirm(options) {
        var title = ( options.title || ""),
            text = ( options.text || ""),
            button = ( options.button || ""),
            onConfirm = ( options.onConfirm || function() {}),
            onCancel = ( options.onCancel || function() {}),
            onClose = ( options.onClose || function() {});

        var template = $.crm.confirm.template;

        template = template
            .replace("%title%", title)
            .replace("%text%", text)
            .replace("%button%", button);

        if (template) {
            return new CRMDialog({
                html: template,
                onConfirm: onConfirm,
                onCancel: onCancel,
                onClose: onClose
            });
        }
    }

    function showAlert(options) {
        var title = ( options.title || ""),
            text = ( options.text || ""),
            button = ( options.button || ($.crm.locales.close || '') ),
            button_class = ( options.button_class || "gray" );

        var template = $.crm.alert.template;

        template = template
            .replace("%title%", title)
            .replace("%text%", text)
            .replace("%button%", button);

        if (!template) {
            throw "Not found alert dialog";
        }

        var dialog_options = $.extend({}, options, true);
        dialog_options.html = template;

        var dialog = new CRMDialog(dialog_options);

        dialog.$wrapper.find('.js-close-dialog').addClass(button_class)

        return dialog;
    }

})(jQuery);

// Whereas .serializeArray() serializes a form into an array, .serializeObject()
// serializes a form into an (arguably more useful) object.
(function($,undefined){
    $.fn.serializeObject = function(){
        var obj = {};

        $.each( this.serializeArray(), function(i,o){
            var n = o.name,
                v = o.value;

            obj[n] = obj[n] === undefined ? v
                : $.isArray( obj[n] ) ? obj[n].concat( v )
                    : [ obj[n], v ];
        });

        return obj;
    };

})(jQuery);

// DRAG & DROP GLOBAL CUSTOM EVENTS
( function($) {

    var timeout = null,
        is_entered = false;

    $(document).on("dragover", onDragOver);

    $(document).on("drop", function(event) {
        event.preventDefault();
    });

    function onDragOver(event) {
        event.preventDefault();
        if (!timeout)  {
            if (!is_entered) {
                is_entered = true;
                $(document).trigger("myDragEnter");
            }
        } else {
            clearTimeout(timeout);
        }

        timeout = setTimeout(function () {
            timeout = null;
            is_entered = false;
            $(document).trigger("myDragLeave");
        }, 100);
    }

})(jQuery);

// CRM :: ContentRouter
// Initialized in layouts/Default.html
var ContentRouter = ( function($) {

    ContentRouter = function(options) {
        var that = this;

        // DOM
        that.$window = $(window);
        that.$content = options["$content"];

        // VARS
        that.api_enabled = !!(window.history && window.history.pushState);

        // DYNAMIC VARS
        that.xhr = false;
        that.is_enabled = true;
        that.need_confirm = false;

        // INIT
        that.initClass();
    };

    ContentRouter.prototype.initClass = function() {
        var that = this;
        //
        that.bindEvents();
    };

    ContentRouter.prototype.bindEvents = function() {
        var that = this;

        // When user clicks a link that leads to app backend, load content via XHR instead.
        var full_app_url = window.location.origin + $.crm.app_url;

        $(document).on("click", "a", function(event) {
            var $link = $(this),
                href = $link.attr("href");

            // hack for jqeury ui links without href attr
            if (!href) {
                $link.attr("href", "javascript:void(0);");
                href = $link.attr("href");
            }

            var stop_load = $link.hasClass("js-disable-router"),
                is_app_url = ( this.href.substr(0, full_app_url.length) == full_app_url ),
                is_normal_url = ( !(href === "#" || href.substr(0, 11) === "javascript:") ),
                use_content_router = ( that.is_enabled && !stop_load && is_app_url && is_normal_url );

            if (!event.ctrlKey && !event.shiftKey && !event.metaKey && use_content_router) {
                event.preventDefault();

                var content_uri = this.href;

                if (that.need_confirm) {
                    $.crm.confirm.show({
                        title: $.crm.locales["unsaved_dialog_title"],
                        text: $.crm.locales["unsaved_dialog_text"],
                        button: $.crm.locales["unsaved_dialog_button"],
                        onConfirm: function() {
                            $(document).trigger("unsavedChanges", false);
                            that.load(content_uri);
                        }
                    })

                } else {
                    that.load(content_uri);
                }

            }
        });

        // Click on header app icon
        $("#wa-app-crm").on("click", "a", function(event) {
            event.stopPropagation();
        });

        // Click on header app icon
        if (that.api_enabled) {
            window.onpopstate = function(event) {
                event.stopPropagation();
                that.onPopState(event);
            };
        }

        $(document).on("unsavedChanges", function(event, _need_confirm) {
            that.need_confirm = !!_need_confirm;
        });
    };

    ContentRouter.prototype.load = function(content_uri, unset_state) {
        var that = this;

        var uri_has_app_url = ( content_uri.indexOf( $.crm.app_url ) >= 0 );
        if (!uri_has_app_url) {
            // TODO:
            alert("Determine the path error");
            return false;
        }

        that.animate( true );

        if (that.xhr) {
            that.xhr.abort();
        }

        $(document).trigger('wa_before_load', {
            // for which these data ?
            content_uri: content_uri
        });

        that.xhr = $.ajax({
            method: 'GET',
            url: content_uri,
            dataType: 'html',
            global: false,
            cache: false
        }).done(function(html) {
            if (that.api_enabled && !unset_state) {
                history.pushState({
                    reload: true,               // force reload history state
                    content_uri: content_uri    // url, string
                }, "", content_uri);
            }

            that.setContent( html );
            that.animate( false );
            that.xhr = false;

            $(document).trigger("wa_loaded");
        }).fail(function(data) {
            if (data.responseText) {
                var href = "?module=dialogConfirm",
                    data = {
                        title: "Error",
                        text: data.responseText,
                        ok_button: "Close"
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html
                    });
                });
            }
        });

        return that.xhr;
    };

    ContentRouter.prototype.reload = function() {
        var that = this,
            content_uri = (that.api_enabled && history.state && history.state.content_uri) ? history.state.content_uri : location.href;

        if (content_uri) {
            return that.load(content_uri, true);
        } else {
            return $.when(); // a resolved promise
        }
    };

    ContentRouter.prototype.setContent = function( html ) {
        var that = this;

        $(document).trigger("wa_before_render");

        that.$content.html( html );
    };

    ContentRouter.prototype.onPopState = function(event) {
        var that = this,
            state = ( event.state || false );

        if (state) {
            if (!state.content_uri) {
                // TODO:
                alert("Determine the path error");
                return false;
            }

            $(document).trigger("wa_before_load");

            // CONTENT
            if (state.reload) {
                that.reload( state.content_uri );
            } else if (state.content) {
                that.setContent( state.content );
            }

            // TITLE
            if (state.title) {
                $.crm.title.set(state.title);
            }

            // SIDEBAR
            // $.crm.sidebar.selectLink( state.content_uri );

            $(document).trigger("wa_loaded");
        } else {
            location.reload();
        }
    };

    ContentRouter.prototype.animate = function( show ) {
        var that = this,
            $content = that.$content;

        //$(".router-loading-indicator").remove();
        //
        //if (show) {
        //    var $header = $content.find(".t-content-header h1"),
        //        loading = '<i class="icon16 loading router-loading-indicator"></i>';
        //
        //    if ($header.length) {
        //        $header.append(loading);
        //    }
        //}
    };

    return ContentRouter;

})(jQuery);

// CRM :: Editable
// Helper used in many places. (group, profile)
var CrmEditable = ( function($) {

    var CrmEditable = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.save = ( options["onSave"] || function() {} );
        that.render = ( options["onRender"] || false );
        that.placeholder = options.placeholder || '';

        // DYNAMIC VARS
        that.is_empty = that.$wrapper.hasClass("is-empty");
        that.text = that.is_empty ? "" : $.trim(that.$wrapper.text());
        that.$field = false;
        that.is_edit = false;

        // INIT
        that.initClass();
    };

    CrmEditable.prototype.initClass = function() {
        var that = this;
        //
        that.$field = that.renderField();
        //
        that.bindEvents();
    };

    CrmEditable.prototype.bindEvents = function() {
        var that = this;

        that.$wrapper.on("click", function() {
            that.toggle();
        });

        // This flag is to make sure onSave does not
        // incidentally calls itself again.
        var save_in_progress = false;

        var ignore_blur_save = false;
        that.$field.on("blur", function() {
            if (!ignore_blur_save && !save_in_progress) {
                save_in_progress = true;
                that.save(that);
                save_in_progress = false;
            }
            ignore_blur_save = false;
        });

        that.$field.on("keyup", function(event) {
            var is_enter = ( event.keyCode === 13 ),
                is_escape = ( event.keyCode === 27 );

            if (is_enter && !save_in_progress) {
                save_in_progress = true;
                that.save(that);
                ignore_blur_save = true;
                save_in_progress = false;
            } else if (is_escape) {
                that.$field.val(that.text);
                that.toggle("hide");
                ignore_blur_save = true;
            }
        });
    };

    CrmEditable.prototype.renderField = function() {
        var that = this,
            text = that.text,
            $field = $('<input class="bold" type="text" name="" />');

        if (that.placeholder) {
            $field.attr('placeholder', that.placeholder);
        }
        if (!that.is_empty) {
            $field.val(text);
        }

        $field.hide();

        that.$wrapper.after($field);

        if (that.render) {
            that.render(that, $field);
        }

        return $field;
    };

    CrmEditable.prototype.toggle = function( show ) {
        var that = this;

        var is_edit = (show !== "hide");
        if (is_edit) {
            setWidth();
            that.$wrapper.hide();
            that.$field.show().focus();
        } else {
            that.$wrapper.show();
            that.$field.hide();
        }

        that.is_edit = is_edit;

        function setWidth() {
            var wrapper_w = that.$wrapper.parent().width(),
                text_w = that.$wrapper.width(),
                min = Math.floor(wrapper_w/2),
                max = wrapper_w - 10,
                field_w;

            field_w = ( text_w < min ? min : text_w );
            field_w = ( text_w > max ) ? max : field_w;

            that.$field.width(field_w);
        }
    };

    CrmEditable.prototype.loading = function(is_show) {
        this.$field.siblings('.crm-editable-loading').remove();
        if (arguments.length && !is_show) {
            this.$field.prop("disabled", false);
        } else {
            this.$field.after('<i class="icon16 loading crm-editable-loading"></i>');
            this.$field.prop("disabled", true);
        }
        return this;
    };

    CrmEditable.prototype.isLoading = function(is_show) {
        return this.$field.siblings('.crm-editable-loading').length > 0;
    };

    CrmEditable.prototype.getText = function() {
        return this.$field.val();
    };

    CrmEditable.prototype.setText = function(new_text, wrapper_text) {
        this.text = new_text;
        this.$field.val(new_text);
        this.$wrapper.text(wrapper_text === undefined ? new_text : wrapper_text);
        return this;
    };

    CrmEditable.prototype.hide = function() {
        this.reset();
        this.loading(false);
        this.toggle('hide');
        return this;
    };

    CrmEditable.prototype.reset = function() {
        this.setText(this.text, this.$wrapper.text());
        return this;
    };

    CrmEditable.prototype.isChanged = function() {
        return this.getText() !== this.text;
    };

    return CrmEditable;

})($);
