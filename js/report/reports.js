var CRMReportPage = ( function($) {

    CRMReportPage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.funnel_id = options["funnel_id"];
        that.funnel_color = options["funnel_color"];
        that.group_by = options["group_by"];
        that.locales = options["locales"];
        that.chartsData = options["chartsData"];
        that.stages_data = options["stages_data"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMReportPage.prototype.initClass = function() {
        var that = this;
        //
        that.initSVG();
        //
        if (typeof d3 !== "undefined") {
            if (that.chartsData) {
                that.initChart();
            }

            // for test
            // that.stages_data = getTestData();

            if (that.stages_data) {
                that.initStageCharts(that.stages_data);
            }
        }
        //
        that.initDateFilter();
        that.initTagsFilter();

        function getTestData() {
            var stages = [getState(1), getState(2), getState(3), getState(4)];

            return {
                stages: stages,
                charts: [getChartData(1),getChartData(2)]
            };

            function getChartData(index) {
                var result;

                if (index === 1) {
                    result = [{
                        name: "верх. общее кол-во",
                        data: getPoints()
                    }, {
                        name: "верх. зависшие",
                        data: getPoints()
                    }];

                } else {
                    result = {
                        name: "низ. график времени",
                        data: getPoints()
                    }
                }

                return result;

                function getPoints() {
                    var result = [];

                    for (var i = 0; i < stages.length; i++) {
                        var stage = stages[i];
                        result.push(getPoint(stage.id));
                    }

                    return result;

                    function getPoint() {
                        var result;

                        if (index === 1) {
                            result = {
                                stage_id: index,
                                // base_text: Math.floor(Math.random() * 30) + " шт.",
                                over_text: Math.floor(Math.random() * 100) + " " + ( Math.round( Math.random() ) < 1 ? "ч." : "дн."),
                                value: 10 + Math.floor( Math.random() * 20 )
                            }

                        } else {
                            result = {
                                stage_id: index,
                                base_text: Math.floor(Math.random() * 30) + " шт.",
                                over_text: Math.floor(Math.random() * 100) + " " + ( Math.round( Math.random() ) < 1 ? "ч." : "дн."),
                                sub_text: Math.floor(Math.random() * 30) + " шт.",
                                value: 10 + Math.floor( Math.random() * 20 )
                            }
                        }

                        return result;
                    }
                }
            }

            function getState(index) {
                return {
                    id: "" + index,
                    name: "Стадия " + index,
                    color: getColor(index)
                };

                /**
                 * @param {Number} index
                 * */
                function getColor(index) {
                    var color_rgb = [3,6,9],
                        result = [];

                    for (var i = 0; i < color_rgb.length; i++) {
                        var part = (color_rgb[i] + index) * 8;
                        result.push(part.toString(16));
                    }

                    return "#" + result.join("");
                }
            }
        }
    };

    CRMReportPage.prototype.initTagsFilter = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-tags-filter"),
            $show_all_btn = $wrapper.find(".js-show-all"),
            $popular_part = $wrapper.find(".js-popular-tags-part"),
            $hidden_part = $wrapper.find(".js-hidden-tags-part");
            $show_all_btn.on('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                $popular_part.remove();
                $show_all_btn.remove();
                $hidden_part.slideDown(300);
            })
    }

    CRMReportPage.prototype.initDateFilter = function() {
        var that = this,
            $wrapper = that.$wrapper.find(".js-dates-filter"),
            $form = $wrapper.find("form"),
            $list = $wrapper.find(".js-list"),
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
            var timeframe = $li.data("timeframe");

            // Link text
            $linkText.html( $li.find("a").html() );

            if ($selectedLi.length) {
                $selectedLi.removeClass("selected");
            }
            $selectedLi = $li.addClass("selected");

            // Hide menu hack
            // $list.hide();
            // setTimeout( function() {
            //     $list.removeAttr("style");
            // }, 200);

            // Toggle content
            if (timeframe !== "custom") {
                $hidden.removeClass(shown_class);
            } else {
                $hidden.addClass(shown_class);
            }
        }

        function updateChart() {
            var href = "?module=report",
                data = $form.serialize();

            var content_uri = $.crm.app_url + "report/?" + data;
            $.crm.content.load(content_uri);
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

    CRMReportPage.prototype.initSVG = function() {
        var that = this;

        if (typeof d3 !== "object") {
            return false;
        }

        var SVGRectangle = ( function($, d3) {

            SVGRectangle = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.svg = d3.select(that.$wrapper[0]).append("svg");

                // VARS
                that.start = parseInt(that.$wrapper.data("start"));
                that.end = parseInt(that.$wrapper.data("end"));
                that.color = that.$wrapper.data("color");

                // DYNAMIC VARS
                that.polygon = false;
                that.area = getArea(that);

                // INIT
                that.initClass();
            };

            SVGRectangle.prototype.initClass = function() {
                var that = this;

                that.svg.attr("width", that.area.width)
                    .attr("height", that.area.height);

                that.renderRectangle();

                // save backdoor
                that.$wrapper.data("svg", that);

                $(window).on("resize", watcher);

                function watcher() {
                    var is_exist = ( $.contains(document, that.$wrapper[0]) );
                    if (is_exist) {
                        that.refresh();
                    } else {
                        $(window).off("resize", watcher);
                    }
                }
            };

            SVGRectangle.prototype.renderRectangle = function() {
                var that = this,
                    svg = that.svg,
                    group = svg.append("g");

                var points = getPoints(that);

                that.polygon = group.append("polygon")
                    .attr("points", points)
                    .attr("transform","translate(" + that.area.left + "," + that.area.top + ")")
                    .style("fill", that.color);
            };

            SVGRectangle.prototype.refresh = function() {
                var that = this;

                that.area = getArea(that);

                that.svg.attr("width", that.area.width)
                    .attr("height", that.area.height);

                that.polygon
                    .attr("points", getPoints(that) )
                    .attr("transform","translate(" + that.area.left + "," + that.area.top + ")");
            };

            return SVGRectangle;

            function getArea(that) {
                var $wrapper = that.$wrapper,
                    percent =that.start/100,
                    result = {};

                result.width = $wrapper.outerWidth();
                result.height = $wrapper.outerHeight();
                result.inner_width = parseInt(result.width * percent);
                result.inner_height = result.height;
                result.top = result.height - result.inner_height;
                result.left = parseInt((result.width - result.inner_width)/2);

                return result;
            }

            function getPoints(that) {
                var result = [];
                var lift = parseInt( ( (that.start - that.end)/(100 * 2) ) * that.area.width );

                result.push("0,0");
                result.push(that.area.inner_width + ",0");
                result.push( (that.area.inner_width - lift) + "," + that.area.height);
                result.push( lift + "," + that.area.height);

                return result.join(" ");
            }

        })(jQuery, d3);

        that.$wrapper.find(".js-svg-bar").each( function() {
            var $wrapper = $(this);

            new SVGRectangle({
                $wrapper: $wrapper
            });
        });
    };

    CRMReportPage.prototype.initChart = function() {
        var that = this,
            $section = that.$wrapper.find(".js-graph-section"),
            time_lift_percent = 5/100;

        if (!$section.length) {
            return false;
        }

        var SumGraph = ( function($) {

            SumGraph = function(options) {
                var that = this;

                //

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$hint = that.$wrapper.find(".c-hint-wrapper");
                that.node = that.$wrapper.find(".js-graph")[0];
                that.d3node = d3.select(that.node);
                //
                that.show_class = "is-shown";

                // DATA
                that.charts = options.data;
                that.data = getData(that.charts);
                that.color = options["color"];
                that.group_by = options["group_by"];
                that.locales = options["locales"];

                // VARS
                that.margin = {
                    top: 14,
                    right: 10,
                    bottom: 20,
                    left: 34
                };
                that.area = getArea(that.node, that.margin);
                that.column_indent = 4;
                that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);
                that.axisIndent = 2;

                // DYNAMIC VARS
                that.svg = false;
                that.defs = false;
                that.x = false;
                that.y = false;
                that.svgLine = false;
                that.svgArea = false;
                that.xDomain = false;
                that.yDomain = false;

                // INIT
                that.initGraph();
            };

            SumGraph.prototype.initGraph = function() {
                var that = this,
                    graphArea = that.area;

                that.initGraphCore();

                that.svg = that.d3node
                    .append("svg")
                    .attr("width", graphArea.outer_width)
                    .attr("height", graphArea.outer_height);

                // that.defs = that.svg.append("defs");
                //
                that.renderBackground();
                // Render Graphs
                $.each(that.data, function(index, data) {
                    var i = (that.data.length - 1 - index);
                    that.renderChart(that.data[i], that.charts[i]);
                });
                //
                that.renderAxis();
            };

            SumGraph.prototype.initGraphCore = function() {
                var that = this,
                    data = that.data,
                    graphArea = that.area;

                var x = that.x = d3.time.scale().range([0, graphArea.inner_width]);
                var y = that.y = d3.scale.linear().range([graphArea.inner_height, 0]);

                that.yDomain = getValueDomain();
                that.xDomain = getTimeDomain();

                x.domain(that.xDomain);
                y.domain(that.yDomain);

                that.svgArea = d3.svg.area()
                    .interpolate("monotone")
                    .x(function(d) { return x(d.date); })
                    .y(function(d) { return y(d.value + d.y0) - that.axisIndent; })
                    .y0( function(d) { return y(0); });

                that.svgLine = d3.svg.line()
                    .interpolate("monotone")
                    .x(function(d) { return x(d.date); })
                    .y(function(d) { return y(d.value + d.y0) - that.axisIndent; });

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
                        last_point = data[0][points_length-1].date,
                        time_lift = (last_point.getTime() - first_point.getTime()) * time_lift_percent ; // 10%

                    min = new Date( first_point.getTime() - time_lift);
                    max = new Date( last_point.getTime() + time_lift);

                    return [min, max];
                }
            };

            SumGraph.prototype.renderAxis = function() {
                var that = this,
                    x = that.x,
                    y = that.y,
                    svg = that.svg;

                var xAxis = d3.svg.axis()
                    .scale(x)
                    .orient("bottom")
                    .ticks(10);

                var yAxis = d3.svg.axis()
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
                    .call(yAxis);

                axis.append("g")
                    .attr("class","x")
                    .attr("transform","translate(" + that.margin.left + "," + (that.area.outer_height - that.margin.bottom ) + ")")
                    .call(xAxis);
            };

            SumGraph.prototype.renderBackground = function() {
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

            SumGraph.prototype.renderChart = function(data, chart) {
                var that = this,
                    svg = that.svg,
                    color = chart.color;

                var path = svg.append("g")
                    .attr("class", "c-graph-wrapper")
                    .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")")
                    .datum(data);

                var area = path
                    .append("path")
                    .style("stroke", function() {
                        return (that.charts.length > 1 ?  "" : that.color);
                    })
                    .style("fill", function() {
                        return ( color ? color : that.color);
                    })
                    .attr("class", "area")
                    .attr("d", that.svgArea(data) )
                    .on("mouseover", onOver)
                    .on("mousemove", onMove)
                    .on("mouseout", onOut);

                // var line = path.append("path")
                //     .style("stroke", function(d, i, j) {
                //         return that.color;
                //     })
                //     .attr("class", "line")
                //     .attr("d", that.svgLine(data) );

                // var wrapper = svg.selectAll(".c-graph-wrapper")
                //     .data(data);

                // wrapper
                //     .enter()
                //     .append("g")
                //     .attr("class", "c-graph-wrapper")
                //     .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");
                //
                // var rect = wrapper.selectAll(".rect")
                //     .data( function(chart) {
                //         return chart;
                //     });
                //
                // rect
                //     .enter()
                //     .append("rect")
                //     .attr("class", "rect")
                //     .style("fill", function(data,point_index,chart_index) {
                //         var color = that.charts[chart_index].color;
                //         return color ? color : false;
                //     })
                //     .on("mouseover", onOver)
                //     .on("mousemove", onMove)
                //     .on("mouseout", onOut);
                //
                // rect
                //     .transition()
                //     .duration(1000)
                //     .attr("x", function(d, i) {
                //         return that.x( d.date ) - that.column_width/2;
                //     })
                //     .attr("y", function(d) {
                //         return ( that.y(d.y0) + that.y(d.value) ) - that.area.inner_height;
                //     })
                //     .attr("height", function(d) {
                //         return that.area.inner_height - that.y(d.value);
                //     })
                //     .attr("width", that.column_width);
                //
                // rect.exit().remove();
                // wrapper.exit().remove();

                function onOver(d) {
                    that.showHint(d3.event, this, chart);
                }

                function onMove() {
                    that.moveHint(this);
                }

                function onOut() {
                    that.hideHint();
                }
            };

            SumGraph.prototype.update = function( app_id ) {
                var that = this;

                that.data = getData(that.charts, app_id);

                that.initGraphCore();

                var yAxis = d3.svg.axis()
                    .scale(that.y)
                    .innerTickSize(2)
                    .orient("right")
                    .tickValues( getValueTicks(6, that.yDomain) )
                    .tickFormat(function(d) { return d + ""; });

                that.svg.selectAll(".axis .y")
                    .call(yAxis);

                that.renderCharts();
            };

            SumGraph.prototype.showHint = function(event, node, chart) {
                var that = this,
                    show_hint = false;

                var $name = that.$hint.find(".js-name"),
                    $date = that.$hint.find(".js-date"),
                    $count = that.$hint.find(".js-value");

                // var hint_text = getHintText(date, that.group_by);
                // $date.text(hint_text );
                // $count.text(point.value);

                if (chart.name) {
                    $name.text(chart.name);

                    var css = getHintPosition(event, that);

                    that.$hint
                        .css(css)
                        .addClass(that.show_class);
                }
            };

            SumGraph.prototype.moveHint = function( ) {
                var that = this,
                    is_visible = that.$hint.hasClass(that.show_class);

                if (is_visible) {
                    var css = getHintPosition(event, that);
                    that.$hint.css(css);
                }
            };

            SumGraph.prototype.hideHint = function( ) {
                var that = this;

                that.$hint
                    .removeAttr("style")
                    .removeClass(that.show_class);
            };

            return SumGraph;

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
            }

            function getColumnWidth(width, indent, length) {
                var result = null;

                length = length + 1;

                if (width && length) {
                    var indent_space = indent * ( length - 1 );
                    result = (width - indent_space)/length;
                    if (result < 0) {
                        result = 0;
                    }
                }

                return result;
            }

            function getValueTicks(length, domain) {
                var min = domain[0],
                    max = ( domain[1] || 1 ),
                    delta = (max - min) + 1,
                    period = delta/(length - 1),
                    result = [];

                for (var i = 0; i < length; i++) {
                    var label = (delta > 10) ? Math.round( i * period ) : (parseInt(  i * period * 10 ) / 10 );
                    result.push(label);
                }

                return result;
            }

            function getHintPosition(event, that) {
                var $window = $(window),
                    window_w = $window.width(),
                    window_h = $window.height(),
                    hint_w = that.$hint.outerWidth(),
                    hint_h = that.$hint.outerHeight(),
                    point_width = 0,
                    point_height = 0,
                    point_border_w = 2,
                    space = 10;

                var wrapperOffset = that.$wrapper.offset(),
                    pointOffset = { top: event.pageY, left: event.pageX},
                    hintOffset = {
                        left: pointOffset.left - wrapperOffset.left + point_width + space,
                        top: pointOffset.top - wrapperOffset.top + ( (point_height < hint_h) ? point_height - hint_h - point_border_w : point_border_w )
                    };

                if (window_w < hintOffset.left + hint_w) {
                    hintOffset.left = pointOffset.left - (hint_w + space);
                }

                return hintOffset;
            }

        })(jQuery);

        var AmountGraph = ( function($) {

            AmountGraph = function(options) {
                var that = this;

                //

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$hint = that.$wrapper.find(".c-hint-wrapper");
                that.node = that.$wrapper.find(".js-graph")[0];
                that.d3node = d3.select(that.node);
                //
                that.show_class = "is-shown";

                // DATA
                that.charts = options.data;
                that.data = getData(that.charts);
                that.color = options["color"];
                that.group_by = options["group_by"];
                that.locales = options["locales"];

                // VARS
                that.margin = {
                    top: 8,
                    right: 10,
                    bottom: 4,
                    left: 34
                };
                that.area = getArea(that.node, that.margin);
                that.column_indent = (that.data[0].length > 200 ) ? 2 : 4;
                that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);

                // DYNAMIC VARS
                that.svg = false;
                that.defs = false;
                that.x = false;
                that.y = false;
                that.xDomain = false;
                that.yDomain = false;

                // INIT
                that.initGraph();
            };

            AmountGraph.prototype.initGraph = function() {
                var that = this,
                    graphArea = that.area;

                that.initGraphCore();

                if (that.data[0].length > 200) {
                    that.$wrapper.addClass("is-large");
                } else {
                    that.$wrapper.removeClass("is-large");
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
            };

            AmountGraph.prototype.initGraphCore = function() {
                var that = this,
                    data = that.data,
                    graphArea = that.area;

                var x = that.x = d3.time.scale().range([0, graphArea.inner_width]);
                var y = that.y = d3.scale.linear().range([0, graphArea.inner_height]);

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
                        last_point = data[0][points_length-1].date,
                        time_lift = (last_point.getTime() - first_point.getTime()) * time_lift_percent;

                    min = new Date( first_point.getTime() - time_lift);
                    max = new Date( last_point.getTime() + time_lift);

                    return [min, max];
                }
            };

            AmountGraph.prototype.renderAxis = function() {
                var that = this,
                    x = that.x,
                    y = that.y,
                    svg = that.svg;

                var xAxis = d3.svg.axis()
                    .scale(x)
                    .orient("bottom")
                    .ticks(10);

                var yAxis = d3.svg.axis()
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
                    .call(yAxis);

                // axis.append("g")
                //     .attr("class","x")
                //     .attr("transform","translate(" + that.margin.left + "," + (that.area.outer_height - that.margin.bottom ) + ")")
                //     .call(xAxis);
            };

            AmountGraph.prototype.renderBackground = function() {
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

            AmountGraph.prototype.renderCharts = function() {
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

                var fill_color = false;
                if (that.color) {
                    var rgb_fill_color = $.crm.color(that.color).rgb;
                    rgb_fill_color.push(0.5);
                    fill_color = "rgba(" + rgb_fill_color.join(",") + ")";
                }

                rect
                    .enter()
                    .append("rect")
                    .attr("class", "rect")
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
                        return that.y(d.y0);
                    })
                    .attr("height", function(d) {
                        return that.y(d.value);
                    })
                    .style("stroke", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? "" : that.color);
                    })
                    .style("fill", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? chart.color : fill_color);
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

            AmountGraph.prototype.update = function( app_id ) {
                var that = this;

                that.data = getData(that.charts, app_id);
                that.column_indent = (that.data[0].length > 200 ) ? 2 : 4;
                if (that.data[0].length > 200) {
                    that.$wrapper.addClass("is-large");
                } else {
                    that.$wrapper.removeClass("is-large");
                }

                that.initGraphCore();

                var yAxis = d3.svg.axis()
                    .scale(that.y)
                    .innerTickSize(2)
                    .orient("right")
                    .tickValues( getValueTicks(6, that.yDomain) )
                    .tickFormat(function(d) { return d + ""; });

                that.svg.selectAll(".axis .y")
                    .call(yAxis);

                that.renderCharts();
            };

            AmountGraph.prototype.showHint = function(event, node, point, chart) {
                var that = this,
                    $point = $(node),
                    point_height = Math.ceil( $point.attr("height") ),
                    has_height = ( point_height > 0 );

                if (!has_height) {
                    return false;
                }

                var date = point.date,
                    $name = that.$hint.find(".js-name"),
                    $date = that.$hint.find(".js-date"),
                    $count = that.$hint.find(".js-value");

                var hint_text = getHintText(date, that.group_by);

                if (chart.name) {
                    $name.text(chart.name + ": ").show();
                } else {
                    $name.hide();
                }
                $date.text(hint_text );
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

                    if (window_w < hintOffset.left + hint_w) {
                        hintOffset.left = pointOffset.left - (hint_w + space);
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

            AmountGraph.prototype.moveHint = function( ) {
                var that = this;
            };

            AmountGraph.prototype.hideHint = function( ) {
                var that = this;

                that.$hint
                    .removeAttr("style")
                    .removeClass(that.show_class);
            };

            return AmountGraph;

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
            }

            function getColumnWidth(width, indent, length) {
                var result = null;

                length = length + 1;

                if (width && length) {
                    var indent_space = indent * ( length - 1 );
                    result = (width - indent_space)/length;
                    if (result < 1) {
                        result = 1;
                    } else if (result > 20) {
                        result = 20;
                    }
                }

                return result;
            }

            function getValueTicks(length, domain) {
                var min = domain[0],
                    max = ( domain[1] || 1 ),
                    delta = ( (max - min) > 0 ? (max - min) : 1 ),
                    period = delta/(length - 1),
                    result = [];

                for (var i = 0; i < length; i++) {
                    var label = (delta > 10) ? Math.round( i * period ) : (parseInt(  i * period * 10 ) / 10 );
                    label = ( parseInt(label) > 0 ? parseInt(label) : 1 );
                    result.push(label);
                }

                return result;
            }

        })(jQuery);

        var sumGraph = new SumGraph({
            $wrapper: $section.find(".js-sum-graph"),
            data: prepareData(that.chartsData["sum"], that.group_by),
            color: that.funnel_color,
            group_by: that.group_by,
            locales: that.locales
        });

        var amountGraph = new AmountGraph({
            $wrapper: $section.find(".js-amount-graph"),
            data: prepareData(that.chartsData["qty"], that.group_by),
            color: that.funnel_color,
            group_by: that.group_by,
            locales: that.locales
        });

        /**
         * @param {Object} data
         * @param {String} group_by
         * @return {Object}
         * */
        function prepareData(data, group_by) {
            $.each(data, function(index) {
                var chart = data[index];
                if (chart.data.length === 1) {
                    var point = chart.data[0],
                        before_point = getLiftedPoint(point, false, group_by),
                        next_point = getLiftedPoint(point, true, group_by);

                    console.log( point );
                    console.log( before_point );
                    console.log( next_point );

                    var new_data = [before_point].concat(chart.data);
                    new_data.push(next_point);

                    chart.data = new_data;


                    console.log( chart.data );
                }
            });

            return data;

            /**
             * @param {Object} point
             * @param {Boolean} next
             * @param {String} group_by
             * @return {Object}
             * */
            function getLiftedPoint(point, next, group_by) {
                return {
                    date: getLiftDate(point.date, next, group_by),
                    value: 0
                };

                /**
                 * @param {Date} date
                 * @param {Boolean} next
                 * @param {String} group_by
                 * @return {String}
                 * */
                function getLiftDate(date, next, group_by) {
                    var date_array = string2array(date),
                        lifted_date_array;

                    if (group_by === "years") {
                        lifted_date_array = date_array.slice();
                        lifted_date_array[0] = lifted_date_array[0] + (next ? 1 : -1);

                    } else if (group_by === "months") {
                        lifted_date_array = date_array.slice();
                        lifted_date_array[1] = lifted_date_array[1] + (next ? 1 : -1);

                    } else {
                        lifted_date_array = date_array.slice();
                        lifted_date_array[2] = lifted_date_array[2] + (next ? 1 : -1);
                    }

                    lifted_date_array[1] = (lifted_date_array[1] < 10 ? "0" : "") + lifted_date_array[1];
                    lifted_date_array[2] = (lifted_date_array[2] < 10 ? "0" : "") + lifted_date_array[2];

                    return lifted_date_array.join("-");

                    function string2date(date_string) {
                        var dateArray = date_string.split("-"),
                            year = parseInt(dateArray[0]),
                            month = parseInt(dateArray[1]) - 1,
                            day = parseInt(dateArray[2]);

                        return new Date(year, month, day);
                    }

                    function string2array(date_string) {
                        var dateArray = date_string.split("-"),
                            year = parseInt(dateArray[0]),
                            month = parseInt(dateArray[1]),
                            day = parseInt(dateArray[2]);

                        return [year, month, day];
                    }

                    function date2string(date) {
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
        }
    };

    CRMReportPage.prototype.initStageCharts = function(data) {
        var that = this,
            $section = that.$wrapper.find(".js-stage-graph-section"),
            stages = data.stages;

        if (!$section.length || !stages.length) { return false; }

        var xDomain = [0, stages.length * 2];

        var red_color = "#cc270e";

        var TopGraph = ( function($) {

            TopGraph = function(options) {
                var that = this;

                //

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$hint = that.$wrapper.find(".c-hint-wrapper");
                that.node = that.$wrapper.find(".js-graph")[0];
                that.d3node = d3.select(that.node);
                //
                that.show_class = "is-shown";

                console.log( options.data );

                // DATA
                that.charts = formatCharts(options.data);
                that.data = getData(that.charts);
                that.color = options["color"];
                that.group_by = options["group_by"];
                that.locales = options["locales"];

                // VARS
                that.margin = {
                    top: 20,
                    right: 0,
                    bottom: 0,
                    left: 0
                };
                that.area = getArea(that.node, that.margin);
                that.column_indent = 8;
                that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);

                // DYNAMIC VARS
                that.svg = false;
                that.defs = false;
                that.x = false;
                that.y = false;
                that.xDomain = false;
                that.yDomain = false;

                // INIT
                that.initGraph();
            };

            TopGraph.prototype.initGraph = function() {
                var that = this,
                    graphArea = that.area;

                that.initGraphCore();

                that.svg = that.d3node
                    .append("svg")
                    .attr("width", graphArea.outer_width)
                    .attr("height", graphArea.outer_height);

                that.renderCharts();
            };

            TopGraph.prototype.initGraphCore = function() {
                var that = this,
                    data = that.data,
                    graphArea = that.area;

                var x = that.x = d3.scale.linear().range([0, graphArea.inner_width]);
                var y = that.y = d3.scale.linear().range([0, graphArea.inner_height]);

                that.yDomain = getValueDomain();
                that.xDomain = xDomain;

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
                            return (point.value);
                        });
                    });

                    return [min, max];
                }
            };

            TopGraph.prototype.renderCharts = function() {
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
                    .attr("class", "rect");

                rect
                    .transition()
                    .duration(1000)
                    .attr("x", function(d, i) {
                        return that.x( d.index ) - that.column_width/2;
                    })
                    .attr("y", function(d) {
                        return that.area.inner_height - that.y(d.value);
                    })
                    .attr("height", function(d) {
                        return that.y(d.value);
                    })
                    .style("stroke", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? "" : that.color);
                    })
                    .style("fill", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? chart.color : that.color);
                    })
                    .attr("width", that.column_width);

                var base_text = wrapper.selectAll(".base-text")
                    .data( function(chart) {
                        return chart;
                    });

                base_text
                    .enter()
                    .append("text")
                    .attr("class", "base-text");

                base_text
                    .transition()
                    .duration(1000)
                    .attr("text-anchor", "middle")
                    .attr("x", function(d) {
                        return that.x( d.index );
                    })
                    .attr("y", function(d) {
                        return that.area.inner_height - 10;
                    })
                    .text( function (d, i, j) {
                        var result = "";
                        var original_point = that.charts[j].data[i];
                        if (original_point) {
                            result = original_point.base_text;
                        }
                        return result;
                    });

                var over_text = wrapper.selectAll(".over-text")
                    .data( function(chart) {
                        return chart;
                    });

                over_text
                    .enter()
                    .append("text")
                    .attr("class", "base-text");

                over_text
                    .transition()
                    .duration(1000)
                    .attr("text-anchor", "middle")
                    .attr("x", function(d) {
                        return that.x( d.index );
                    })
                    .attr("y", function(d) {
                        return that.area.inner_height - that.y(d.value) - 6;
                    })
                    .style("fill", function(d,i,j) {
                        // var chart = that.charts[j];
                        // return (chart.color ? chart.color : "");
                        return ( j === 1 ? red_color : "");
                    })
                    .text( function (d, i, j) {
                        var result = "";
                        var original_point = that.charts[j].data[i];
                        if (original_point) {
                            result = original_point.over_text;
                        }
                        return result;
                    });

                base_text.exit().remove();
                over_text.exit().remove();
                rect.exit().remove();
                wrapper.exit().remove();
            };

            return TopGraph;

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
                var result = [];

                for (var i = 0; i < charts.length; i++) {
                    var chart = charts[i].data,
                        chartData = [];

                    for (var j = 0; j < chart.length ; j++) {
                        var point = chart[j],
                            point_value = parseInt( point.value );

                        chartData.push({
                            index: 1 + (2 * j),
                            value: point_value
                        });
                    }

                    result.push(chartData);
                }

                // var stack = d3.layout.stack()
                //     .offset("zero")
                //     .values( function(d) { return d; })
                //     .x(function(d) { return d.index; })
                //     .y(function(d) { return d.value; });

                // return stack(result);

                return result;
            }

            function getColumnWidth(width, indent, length) {
                var result = null;

                if (width && length) {
                    var indent_space = indent * ( length - 1 );
                    result = (width - indent_space)/length;
                    if (result < 1) {
                        result = 1;
                    }
                }

                return result;
            }

            function formatCharts(charts) {
                for (var i = 0; i < charts.length; i++) {
                    var chart = charts[i];

                    if (i === 1 && !chart.color) {
                        chart.color = "#e07d6e";
                    }
                }

                return charts;
            }

        })(jQuery);

        var BottomGraph = ( function($) {

            BottomGraph = function(options) {
                var that = this;

                //

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$hint = that.$wrapper.find(".c-hint-wrapper");
                that.node = that.$wrapper.find(".js-graph")[0];
                that.d3node = d3.select(that.node);
                //
                that.show_class = "is-shown";

                // DATA
                that.charts = options.data;
                that.data = getData(that.charts);
                that.color = options["color"];
                that.group_by = options["group_by"];
                that.locales = options["locales"];

                // VARS
                that.margin = {
                    top: 0,
                    right: 0,
                    bottom: 20,
                    left: 0
                };
                that.area = getArea(that.node, that.margin);
                that.column_indent = 8;
                that.column_width = getColumnWidth(that.area.inner_width, that.column_indent, that.data[0].length);

                // DYNAMIC VARS
                that.svg = false;
                that.defs = false;
                that.x = false;
                that.y = false;
                that.xDomain = false;
                that.yDomain = false;

                // INIT
                that.initGraph();
            };

            BottomGraph.prototype.initGraph = function() {
                var that = this,
                    graphArea = that.area;

                that.initGraphCore();

                that.svg = that.d3node
                    .append("svg")
                    .attr("width", graphArea.outer_width)
                    .attr("height", graphArea.outer_height);

                that.renderCharts();
            };

            BottomGraph.prototype.initGraphCore = function() {
                var that = this,
                    data = that.data,
                    graphArea = that.area;

                var x = that.x = d3.scale.linear().range([0, graphArea.inner_width]);
                var y = that.y = d3.scale.linear().range([0, graphArea.inner_height]);

                that.yDomain = getValueDomain();
                that.xDomain = xDomain;

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
            };

            BottomGraph.prototype.renderCharts = function() {
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
                    .attr("class", "rect");

                rect
                    .transition()
                    .duration(1000)
                    .attr("x", function(d, i) {
                        return that.x( d.index ) - that.column_width/2;
                    })
                    .attr("y", function(d) {
                        return that.y(d.y0);
                    })
                    .attr("height", function(d) {
                        return that.y(d.value);
                    })
                    .style("stroke", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? "" : that.color);
                    })
                    .style("fill", function(d, index, chart_index) {
                        var chart = that.charts[chart_index];
                        return (chart.color ? chart.color : that.color);
                    })
                    .attr("width", that.column_width);

                var base_text = wrapper.selectAll(".base-text")
                    .data( function(chart) {
                        return chart;
                    });

                base_text
                    .enter()
                    .append("text")
                        .attr("class", "base-text")
                        .text( function(d, i, j) {
                            var result = "";
                            var original_point = that.charts[j].data[i];
                            if (original_point) {
                                result = original_point.base_text;
                            }
                            return result;
                        })
                        .append("tspan")
                            .style("fill", red_color)
                            .text( function(d, i, j) {
                                var result = "",
                                    point = that.charts[j].data[i];

                                if (point && point.sub_text) {
                                    result = " (" + point.sub_text + ")";
                                }

                                return result;
                            });

                base_text
                    .transition()
                    .duration(1000)
                    .attr("text-anchor", "middle")
                    .attr("x", function(d) {
                        return that.x( d.index );
                    })
                    .attr("y", 16);

                var over_text = wrapper.selectAll(".over-text")
                    .data( function(chart) {
                        return chart;
                    });

                over_text
                    .enter()
                    .append("text")
                    .attr("class", "over-text");

                over_text
                    .transition()
                    .duration(1000)
                    .attr("text-anchor", "middle")
                    .attr("x", function(d) {
                        return that.x( d.index );
                    })
                    .attr("y", function(d) {
                        var result = that.y(d.y0) + that.y(d.value) + 16;
                        if (result < 34) { result = 34; }
                        return result;
                    })
                    .text( function (d, i, j) {
                        var result = "";
                        var original_point = that.charts[j].data[i];
                        if (original_point) {
                            result = original_point.over_text;
                        }
                        return result;
                    });

                base_text.exit().remove();
                over_text.exit().remove();
                rect.exit().remove();
                wrapper.exit().remove();
            };

            return BottomGraph;

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
                var result = [];

                for (var i = 0; i < charts.length; i++) {
                    var chart = charts[i].data,
                        chartData = [];

                    for (var j = 0; j < chart.length ; j++) {
                        var point = chart[j],
                            point_value = parseInt( point.value );

                        chartData.push({
                            index: 1 + (2 * j),
                            value: point_value
                        });
                    }

                    result.push(chartData);
                }

                var stack = d3.layout.stack()
                    .offset("zero")
                    .values( function(d) { return d; })
                    .x(function(d) { return d.index; })
                    .y(function(d) { return d.value; });

                return stack(result);
            }

            function getColumnWidth(width, indent, length) {
                var result = null;

                if (width && length) {
                    var indent_space = indent * ( length - 1 );
                    result = (width - indent_space)/length;
                    if (result < 1) {
                        result = 1;
                    }
                }

                return result;
            }

        })(jQuery);

        // top
        var $topChart = $section.find(".js-graph-wrapper-1");
        if ($topChart.length) {
            new TopGraph({
                $wrapper: $topChart,
                data: data.charts[0],
                color: "#d0d0d0",
                group_by: that.group_by,
                locales: that.locales
            });
        }

        // bottom
        var $bottomChart = $section.find(".js-graph-wrapper-2");
        if ($bottomChart.length) {
            new BottomGraph({
                $wrapper: $bottomChart,
                data: [data.charts[1]],
                color: "#b0b0b0",
                group_by: that.group_by,
                locales: that.locales
            });
        }
    };

    return CRMReportPage;

})(jQuery);