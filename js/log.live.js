var CRMLogLive = ( function($) {

    // TODO: remove
    var ActivityLazyLoading = ( function($) {

        ActivityLazyLoading = function(options) {
            var that = this;

            // VARS
            that.list_name = options["names"]["list"];
            that.items_name = options["names"]["items"];
            that.pagind_name = options["names"]["paging"];
            that.log = options["log"];

            // DOM
            that.$wrapper = ( options["$wrapper"] || false );
            that.$list = that.$wrapper.find(that.list_name);
            that.$window = $(window);

            // Handler
            that.onLoad = ( options["onLoad"] || function() {} );

            // DYNAMIC VARS
            that.$paging = that.$wrapper.find(that.pagind_name);
            that.xhr = false;
            that.is_locked = false;

            // INIT
            that.addWatcher();
        };

        ActivityLazyLoading.prototype.addWatcher = function() {
            var that = this,
                window_parent = window.parent;

            that.$window.on("scroll", onScroll);
            if (window_parent && window.frameElement) {
                $(window_parent).on("scroll", onScroll);
            }

            function onScroll() {
                var is_paging_exist = window && ( $.contains(document, that.$paging[0]) );
                if (is_paging_exist && window_parent && window.frameElement) {
                    is_paging_exist = $.contains(window_parent.document, window.frameElement);
                }

                if (is_paging_exist) {
                    that.onScroll();
                } else {
                    that.$window.off("scroll", onScroll);
                    $(window_parent).off("scroll", onScroll);
                }
            }
        };

        ActivityLazyLoading.prototype.onScroll = function() {
            var that = this,
                $window = that.$window,
                scroll_top = $window.scrollTop(),
                display_height = $window.height(),
                paging_top = that.$paging.offset().top;

            if (window.parent && window.frameElement) {
                var $parent_window = $(window.parent);
                display_height = $parent_window.height();
                scroll_top += $parent_window.scrollTop();
                paging_top += $(window.frameElement).offset().top;
            }

            // If we see paging, stop watcher and run load
            if (scroll_top + display_height >= paging_top) {

                if (!that.is_locked) {
                    that.is_locked = true;
                    that.loadNextPage();
                }
            }
        };

        ActivityLazyLoading.prototype.loadNextPage = function() {
            var that = this,
                href = "?module=logLive",
                data = that.log.filtersData.slice(0);

            data.push({
                name: "max_id",
                value: that.$paging.data("max-id")
            });

            data.push({
                name: "timestamp",
                value: that.$list.find(that.items_name).last().data("timestamp")
            });

            if (that.xhr) {
                that.xhr.abort();
            }

            that.xhr = $.get(href, data, function(response) {

                var $temp = $("<div id='c-temp-wrapper' />");
                that.log.$wrapper.after($temp);
                $temp.html(response);

                var $wrapper = $temp,
                    $newItems = $wrapper.find(that.list_name + " " + that.items_name),
                    $newPaging = $wrapper.find(that.pagind_name);

                that.$list.append($newItems);
                that.$paging.after($newPaging);
                that.$paging.remove();
                that.$paging = $newPaging;
                that.is_locked = false;
                //
                that.onLoad();
            });
        };

        return ActivityLazyLoading;

    })(jQuery);

    var Graph = ( function($) {

        Graph = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$hint = that.$wrapper.find(".js-hint-wrapper");
            that.node = that.$wrapper.find(".js-chart-wrapper")[0];
            that.d3node = d3.select(that.node);

            //
            that.show_class = "is-shown";

            // DATA
            that.charts = options.data;
            that.data = getData(that.charts);
            that.group_by = options["group_by"];
            that.locales = options["locales"];

            // VARS
            that.margin = {
                top: 14,
                right: 10,
                bottom: 28,
                left: 34
            };
            that.area = getArea(that.node, that.margin);
            that.column_indent = (that.data[0].length > 200 ? 2 : 4);
            that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);

            // DYNAMIC VARS
            that.svg = false;
            that.defs = false;
            that.x = false;
            that.y = false;
            that.xDomain = false;
            that.yDomain = false;
            that.xAxis = false;
            that.yAxis = false;

            // INIT
            that.initGraph();
        };

        Graph.prototype.initGraph = function() {
            var that = this,
                graphArea = that.area;

            that.initGraphCore();

            if (that.data[0].length > 200) {
                that.$wrapper.addClass("is-large")
            }

            that.svg = that.d3node
                .append("svg")
                .attr("width", graphArea.outer_width)
                .attr("height", graphArea.outer_height);

            // that.defs = that.svg.append("defs");
            //
            that.renderBackground();
            // Render Graphs
            that.renderCharts();
            //
            that.renderAxis();

            // hack, some trigger window resize
            setTimeout(function() {
                $(window).on("resize", watcher);

                function watcher() {
                    var is_exist = $.contains(document, that.$wrapper[0]);
                    if (is_exist) {
                        that.update();
                    } else {
                        $(window).off("resize", watcher);
                    }
                }
            }, 4);
        };

        Graph.prototype.initGraphCore = function() {
            var that = this,
                data = that.data,
                graphArea = that.area;

            var x = that.x = d3.time.scale().range([0, graphArea.inner_width]);
            var y = that.y = d3.scale.linear().range([graphArea.inner_height, 0]);

            that.yDomain = getValueDomain();
            that.xDomain = getTimeDomain();

            x.domain(that.xDomain);
            y.domain(that.yDomain);

            function getValueDomain() {
                var min = d3.min(data, function(chart) {
                    return d3.min(chart, function(point) {
                        return point.value;
                    });
                });
                if (min > 0) {
                    min = 0;
                }
                var max = d3.max(data, function(chart) {
                    return d3.max(chart, function(point) {
                        return (point.value + point.y0);
                    });
                });

                return [min, max];
            }

            function getTimeDomain() {
                var min, max,
                    points_length = data[0].length,
                    first_point = data[0][0].date,
                    second_point = data[0][1].date,
                    last_point = data[0][points_length-1].date,
                    half_time_period = parseInt( ( second_point.getTime() - first_point.getTime() )/2 );

                min = new Date( first_point.getTime() - half_time_period );
                max = new Date( last_point.getTime() + half_time_period );

                return [min, max];
            }
        };

        Graph.prototype.renderAxis = function() {
            var that = this,
                x = that.x,
                y = that.y,
                svg = that.svg;

            that.xAxis = d3.svg.axis()
                .scale(x)
                .orient("bottom")
                .ticks(10);

            that.yAxis = d3.svg.axis()
                .scale(y)
                .innerTickSize(2)
                .orient("right")
                .tickValues( getValueTicks(6, that.yDomain) )
                .tickFormat(function(d) { return d + ""; });

            // Render Осей
            var axis = svg.append("g")
                .attr("class","axis");

            axis.append("g")
                .attr("transform","translate(" + 10 + "," + that.margin.top + ")")
                .attr("class","y")
                .call(that.yAxis);

            axis.append("g")
                .attr("class","x")
                .attr("transform","translate(" + that.margin.left + "," + (that.area.outer_height - that.margin.bottom ) + ")")
                .call(that.xAxis);
        };

        Graph.prototype.renderBackground = function() {
            var that= this,
                width = that.area.inner_width,
                height = that.area.inner_height,
                xTicks = 31,
                yTicks = 5,
                i;

            var background = that.svg.append("g")
                .attr("class", "background")
                .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");

            background.append("rect")
                .attr("width", width)
                .attr("height", height);

            for (i = 0; i <= yTicks; i++) {
                var yVal = 1 + (height - 2) / yTicks * i;
                background.append("line")
                    .attr("x1", 1)
                    .attr("x2", width)
                    .attr("y1", yVal)
                    .attr("y2", yVal)
                ;
            }
        };

        Graph.prototype.renderCharts = function() {
            var that = this,
                svg = that.svg,
                data = that.data;

            var wrapper = svg.selectAll(".c-graph-wrapper")
                .data(data);

            wrapper
                .enter()
                .append("g")
                .attr("class", "c-graph-wrapper")
                .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");

            var rect = wrapper.selectAll(".rect")
                .data( function(chart) {
                    return chart;
                });

            rect
                .enter()
                .append("rect")
                .attr("class", "rect")
                .style("fill", function(data,point_index,chart_index) {
                    var color = that.charts[chart_index].color;
                    return color ? color : false;
                })
                .on("mouseover", onOver)
                .on("mousemove", onMove)
                .on("mouseout", onOut);

            rect
                .transition()
                .duration(1000)
                .attr("x", function(d, i) {
                    return that.x( d.date ) - that.column_width/2;
                })
                .attr("y", function(d) {
                    return ( that.y(d.y0) + that.y(d.value) ) - that.area.inner_height;
                })
                .attr("height", function(d) {
                    return that.area.inner_height - that.y(d.value);
                })
                .attr("width", that.column_width);

            rect.exit().remove();
            wrapper.exit().remove();

            function onOver(d,i,j) {
                that.showHint(d3.event, this, d, that.charts[j]);
            }

            function onMove() {
                that.moveHint(this);
            }

            function onOut() {
                that.hideHint();
            }
        };

        Graph.prototype.update = function(data) {
            var that = this;

            if (!data) {
                return false;
            }

            that.data = getData(data.chart);
            that.group_by = ( data.group_by || "days");

            that.area = getArea(that.node, that.margin);
            that.column_indent = (that.data[0].length > 200 ? 2 : 4);
            that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);
            that.svg
                .attr("width", that.area.outer_width)
                .attr("height", that.area.outer_height);

            if (that.data[0].length > 200) {
                that.$wrapper.addClass("is-large");
            } else {
                that.$wrapper.removeClass("is-large");
            }

            that.initGraphCore();

            that.yAxis
                .scale(that.y)
                .tickValues( getValueTicks(6, that.yDomain) );

            that.svg.selectAll(".axis .y")
                .transition()
                .duration(600)
                .call(that.yAxis);

            that.xAxis
                .scale(that.x);

            that.svg.selectAll(".axis .x")
                .transition()
                .duration(600)
                .call(that.xAxis);

            that.renderCharts();
        };

        Graph.prototype.showHint = function(event, node, point, chart) {
            var that = this,
                $point = $(node),
                point_height = Math.ceil( $point.attr("height") ),
                has_height = ( point_height > 0 );

            if (!has_height) {
                return false;
            }

            var date = point.date,
                $date = that.$hint.find(".c-date"),
                $app = that.$hint.find(".c-app"),
                $count = that.$hint.find(".c-value");

            var hint_text = getHintText(date, that.group_by);

            $date.text(hint_text );
            $app.text(chart.name);
            $count.text(point.value);

            var css = getHintPosition($point);

            that.$hint
                .css(css)
                .addClass(that.show_class);

            function getHintPosition($point) {
                var $window = $(window),
                    window_w = $window.width(),
                    window_h = $window.height(),
                    hint_w = that.$hint.outerWidth(),
                    hint_h = that.$hint.outerHeight(),
                    point_width = Math.ceil( $point.attr("width") ),
                    point_height = Math.ceil( $point.attr("height") ),
                    point_border_w = 2,
                    space = 10;

                var wrapperOffset = that.$wrapper.offset(),
                    pointOffset = $point.offset(),
                    hintOffset = {
                        left: pointOffset.left - wrapperOffset.left + point_width + space,
                        top: pointOffset.top - wrapperOffset.top + ( (point_height < hint_h) ? point_height - hint_h - point_border_w : point_border_w )
                    };

                if (window_w < pointOffset.left + space + hint_w) {
                    hintOffset.left = pointOffset.left - wrapperOffset.left - point_width - hint_w;
                }

                return hintOffset;
            }

            function getHintText(date, group_by) {
                var month = parseInt( date.getMonth() ),
                    result;

                if (group_by == "months") {
                    var months = that.locales["months"];
                    if (months[month]) {
                        result = months[month];
                    } else {
                        months = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
                        result = months[month];
                    }
                } else {
                    var day = date.getDate();
                    day = ( day < 10 ) ? "0" + day : day;

                    month += 1;
                    month = ( month < 10 ) ? "0" + month : month;

                    result = day + "." + month + "." + date.getFullYear();
                }

                return result;
            }
        };

        Graph.prototype.moveHint = function( ) {
            var that = this;
        };

        Graph.prototype.hideHint = function( ) {
            var that = this;

            that.$hint
                .removeAttr("style")
                .removeClass(that.show_class);
        };

        return Graph;

        // Получаем размеры для графика
        function getArea(node, margin) {
            var width = node.offsetWidth,
                height = node.offsetHeight;

            return {
                outer_width: width,
                outer_height: height,
                inner_width: width - margin.left - margin.right,
                inner_height: height - margin.top - margin.bottom
            };
        }

        function getData(charts) {
            var chartsData = [];

            charts = prepareData(charts);

            for (var i = 0; i < charts.length; i++) {
                var chart = charts[i].data,
                    chartData = [];

                for (var j = 0; j < chart.length ; j++) {
                    var point = chart[j],
                        point_value = parseInt( point.value );

                    chartData.push({
                        date: formatDate( point.date ),
                        value: point_value
                    });
                }

                chartsData.push(chartData);
            }

            var stack = d3.layout.stack()
                .offset("zero")
                .values( function(d) { return d; })
                .x(function(d) { return d.date; })
                .y(function(d) { return d.value; });

            return stack(chartsData);

            function formatDate(date_string) {
                var dateArray = date_string.split("-"),
                    year = parseInt(dateArray[0]),
                    month = parseInt(dateArray[1]) - 1,
                    day = parseInt(dateArray[2]);

                return new Date(year, month, day);
            }

            function prepareData(data) {
                var point_length = data[0].data.length;
                if (point_length === 1) {
                    $.each(data, function(index) {
                        var point = data[index].data[0],
                            before_point = {
                                value: 0,
                                date: getLiftDate(point.date, false)
                            },
                            next_point = {
                                value: 0,
                                date: getLiftDate(point.date, true)
                            };

                        data[index].data = [before_point, point, next_point];
                    });
                }

                return data;

                function getLiftDate(date_string, next) {
                    var dateArray = date_string.split("-"),
                        year = parseInt(dateArray[0]),
                        month = parseInt(dateArray[1]) - 1,
                        day = parseInt(dateArray[2]);

                    var one_day = 1000 * 60 * 60 * 24,
                        date = new Date( new Date(year, month, day).getTime() + (next ? one_day : -one_day) );

                    var d_year = parseInt(date.getFullYear()),
                        d_month = parseInt(date.getMonth()) + 1,
                        d_day = parseInt(date.getDate());

                    if (d_day < 10) {
                        d_day = "0" + d_day;
                    }
                    if (d_month < 10) {
                        d_month = "0" + d_month;
                    }

                    return [d_year, d_month, d_day].join("-");
                }
            }
        }

        function getColumnWidth(width, indent, length) {
            var result = null;

            length = length + 1;

            if (width && length) {
                var indent_space = indent * ( length - 1 );
                result = (width - indent_space)/length;
                if (result < 1) {
                    result = 1;
                }
            }

            return result;
        }

        function getValueTicks(length, domain) {
            var min = domain[0],
                max = ( domain[1] || 1 ),
                delta = (max - min) + ( max - min > 1  ? 0 : 1 ),
                period = delta/(length - 1),
                result = [];

            for (var i = 0; i < length; i++) {
                var label = (delta > 10) ? Math.round( i * period ) : (parseInt(  i * period * 10 ) / 10 );
                result.push(label);
            }

            return result;
        }

    })(jQuery);

    CRMLogLive = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$filtersForm = that.$wrapper.find("form.js-filters-form");

        // VARS
        that.chartData = options["chartData"];
        that.chartParams = options["chartParams"];
        that.locales = options["locales"];

        // DYNAMIC VARS
        that.chart = false;
        that.filtersData = that.$filtersForm.serializeArray();

        // INIT
        that.initClass();
    };

    CRMLogLive.prototype.initClass = function() {
        var that = this;

        that.initLogList();

        that.initDateFilter();

        that.initChart();
    };

    CRMLogLive.prototype.initLogList = function() {
        var that = this,
            $wrapper = that.$wrapper.find("#c-activity-section");

        setLast();
        // TODO: remove
        new ActivityLazyLoading({
            $wrapper: $wrapper,
            names: {
                list: ".c-activity-list",
                items: "> li",
                paging: ".c-paging-wrapper"
            },
            log: that,
            onLoad: setLast
        });

        function setLast() {
            var first_class = "is-first",
                last_class = "is-last";

            var $items = $wrapper.find(".c-activity-item." + first_class);
            $items.each( function() {
                var $prev = $(this).prev().prev();
                if (!$prev.hasClass(last_class)) {
                    $prev.addClass(last_class);
                }
            });
        }
    };

    CRMLogLive.prototype.initDateFilter = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-dates-filter"),
            $form = $wrapper.find("form"),
            $list = $wrapper.find(".js-list"),
            $groupField = $wrapper.find(".js-group-field"),
            $timeframeField = $wrapper.find(".js-timeframe-field"),
            $linkText = $wrapper.find(".js-link-text"),
            $hidden = $wrapper.find(".js-hidden-part"),
            is_locked = false;

        var $selectedLi = $list.find(".selected");

        var shown_class = "is-shown";

        $list.on("click", ".js-toggle", function(event) {
            event.preventDefault();
            setFilter( $(this) );
        });

        $form.on("submit", function(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;
                updateChart();
            }
        });

        initDatepickers();

        //

        function setFilter($li) {
            var timeframe = $li.data("timeframe"),
                group = ( $li.data("group") || "days" );

            // Link text
            $linkText.html( $li.find("a").html() );

            if ($selectedLi.length) {
                $selectedLi.removeClass("selected");
            }
            $selectedLi = $li.addClass("selected");

            // Hide menu hack
            $list.hide();
            setTimeout( function() {
                $list.removeAttr("style");
            }, 200);

            // set data
            $timeframeField.val(timeframe);
            $groupField.val(group);

            // Toggle content
            if (timeframe !== "custom") {
                $hidden.removeClass(shown_class);
                updateChart();
            } else {
                $hidden.addClass(shown_class);
            }
        }

        function updateChart() {
            var href = "?module=logLive",
                data = $form.serializeArray();

            data.push({
                name: "chart",
                value: 1
            });

            $.post(href, data, function(response) {
                if (response.status == "ok") {
                    var data = response.data;
                    data.group_by = $groupField.val();
                    that.chart.update(data);
                }
            }, "json").always( function() {
                is_locked = false;
            });
        }

        function initDatepickers() {
            $wrapper.find(".js-datepicker").each( function() {
                var $field = $(this),
                    $altField = $wrapper.find("." + $field.data("selector"));

                $field.datepicker({
                    altField: $altField,
                    altFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });

                //$input.datepicker("setDate", "+1d");
            });
        }
    };

    CRMLogLive.prototype.initChart = function() {
        var that = this;

        var $chartWrapper = that.$wrapper.find(".js-chart-section");

        if ($chartWrapper.length && that.chartData && that.chartData.length) {
            that.chart = new Graph({
                $wrapper: $chartWrapper,
                data: that.chartData,
                group_by: that.chartParams.group_by,
                locales: that.locales
            });
        }
    };

    return CRMLogLive;

})(jQuery);

/** One log item controller */
var CRMLogLiveItem = ( function($) {

    CRMLogLiveItem = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMLogLiveItem.prototype.initClass = function() {
        var that = this;

        $.crm.renderSVG(that.$wrapper);

        that.$wrapper.data("logLiveItem", that);
    };

    return CRMLogLiveItem;

})(jQuery);
