// Various formatters.
var formatNumber = d3.format(",d"),
	formatDate = d3.time.format("%B %d, %Y"),
	formatTime = d3.time.format("%I:%M %p");

// A nest operator, for grouping the flight list.
var nestByDate = d3.nest()
	.key(function(d) { return d3.time.day(d.date); });

// Create the crossfilter for the relevant dimensions and groups.
var profileCrossfilter = crossfilter(window.profileData),
	all = profileCrossfilter.groupAll();

var charts = [];

var startTimeDimension = profileCrossfilter.dimension(function(d) {return d.startTime});

/*for (var optionName in window.options) {
	var optionDomain = [];
	for (var i in window.options[optionName]) {
		optionDomain.push(i);
	}
	var crossfilterOptions = {}

	var sizeOfOneBar = 240 / optionDomain.length;

	var dimension = profileCrossfilter.dimension(function(d) { return d[optionName] });
	var dimensionGroup = dimension.group();
	charts.push(
		barChart(sizeOfOneBar - 3)
			.dimension(dimension)
			.group(dimensionGroup)
			.x(d3.scale.ordinal()
				.domain(optionDomain)
					// the second parameter is the total WIDTH
					// "10" in this case is the size of each bar.
				.range([0, optionDomain.length*sizeOfOneBar]))
	);
}*/

for (var calculationName in window.calculations) {
	var calculationOptions = window.calculations[calculationName];
	var crossfilterOptions = calculationOptions.crossfilter;

	// one bar
	var rangeOfOneBar = crossfilterOptions.max / crossfilterOptions.numberOfBars;
	var sizeOfOneBar = 240 / crossfilterOptions.numberOfBars;

	var dimension = profileCrossfilter.dimension(function(d) { return Math.floor(d[calculationName] / rangeOfOneBar) * rangeOfOneBar });
	var dimensionGroup = dimension.group();
	charts.push(
		barChart(sizeOfOneBar - 3)
			.dimension(dimension)
			.group(dimensionGroup)
			.x(d3.scale.linear()
				.domain([crossfilterOptions.min-rangeOfOneBar, crossfilterOptions.max+rangeOfOneBar])
					// the second parameter is the total WIDTH
				.rangeRound([0, (crossfilterOptions.width ? crossfilterOptions.width : 240)]))
	);
}

// Given our array of charts, which we assume are in the same order as the
// .chart elements in the DOM, bind the charts to the DOM and render them.
// We also listen to the chart's brush events to update the display.
var chart = d3.selectAll(".chart")
	.data(charts)
	.each(function(chart) { chart.on("brush", renderAll).on("brushend", renderAll); });

// Render the initial lists.
var list = d3.selectAll(".list")
	.data([recordList]);

// Render the total.
d3.selectAll("#total")
	.text(formatNumber(profileCrossfilter.size()));

renderAll();

// Renders the specified chart or list.
function render(method) {
	d3.select(this).call(method);
}

// Whenever the brush moves, re-rendering everything.
function renderAll() {
	chart.each(render);
	list.each(render);
	d3.select("#active").text(formatNumber(all.value()));
}

window.filter = function(filters) {
	filters.forEach(function(d, i) { charts[i].filter(d); });
	renderAll();
};

window.reset = function(i) {
	charts[i].filter(null);
	renderAll();
};

function recordList(div) {
	var records = startTimeDimension.top(40);

	div.each(function() {
		var recordSelection = d3.select(this).selectAll(".record")
			.data(records, function(d) { return d.startTime; });

		var recordSelectionEnter = recordSelection.enter().append("tr")
			.attr("class", "record")

		recordSelection.exit().remove();

		recordSelectionEnter.append("td").html(function(d) {
			return '<input type="radio" name="file1" value="' + d['id'] + '" />'
				+  '<input type="radio" name="file2" value="' + d['id'] + '" />';
		});

		recordSelectionEnter.append("td").html(function(d) {
			return '<a href="' + window.uris.timelineDetails + '?file1=' + d['id'] + '" class="btn small">Timeline &raquo;</a>'
				+  '<a href="' + window.uris.xhprofDetails + '?run=' + d['id'] + '" class="btn small">XHProf &raquo;</a>'
		});

		for (var optionName in window.options) {
			recordSelectionEnter.append("td")
				.text(function(d) {if (typeof d[optionName] == 'string') return d[optionName]});
		}
		for (var calculationName in window.calculations) {
			if (window.calculations[calculationName].nameOfRowToDisplayInsteadInTable) {
				calculationName = window.calculations[calculationName].nameOfRowToDisplayInsteadInTable;
			}
			recordSelectionEnter.append("td")
				.text(function(d) {return d[calculationName]});
		}

		recordSelection.order()
	});
}

function barChart(barWidth) {
	if (!barChart.id) barChart.id = 0;

	var margin = {top: 10, right: 10, bottom: 20, left: 10},
		x,
		y = d3.scale.linear().range([100, 0]),
		id = barChart.id++,
		axis = d3.svg.axis().orient("bottom"),
		brush = d3.svg.brush(),
		brushDirty,
		dimension,
		group,
		round;

	function chart(div) {
		var width = x.range()[1],
			height = y.range()[0];

		y.domain([0, (group.top(1).length > 0 ? group.top(1)[0].value : 0)]);

		div.each(function() {
			var div = d3.select(this),
				g = div.select("g");

			// Create the skeletal chart.
			if (g.empty()) {
				div.select(".title").append("a")
					.attr("href", "javascript:reset(" + id + ")")
					.attr("class", "reset")
					.text("reset")
					.style("display", "none");

				g = div.append("svg")
					.attr("width", width + margin.left + margin.right)
					.attr("height", height + margin.top + margin.bottom)
				.append("g")
					.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

				g.append("clipPath")
					.attr("id", "clip-" + id)
				.append("rect")
					.attr("width", width)
					.attr("height", height);

				g.selectAll(".bar")
					.data(["background", "foreground"])
				.enter().append("path")
					.attr("class", function(d) { return d + " bar"; })
					.datum(group.all());

				g.selectAll(".foreground.bar")
					.attr("clip-path", "url(#clip-" + id + ")");

				g.append("g")
					.attr("class", "axis")
					.attr("transform", "translate(0," + height + ")")
					.call(axis);

				// Initialize the brush component with pretty resize handles.
				var gBrush = g.append("g").attr("class", "brush").call(brush);
				gBrush.selectAll("rect").attr("height", height);
				gBrush.selectAll(".resize").append("path").attr("d", resizePath);
			}

			// Only redraw the brush if set externally.
			if (brushDirty) {
				brushDirty = false;
				g.selectAll(".brush").call(brush);
				div.select(".title a").style("display", brush.empty() ? "none" : null);
				if (brush.empty()) {
					g.selectAll("#clip-" + id + " rect")
						.attr("x", 0)
						.attr("width", width);
				} else {
					var extent = brush.extent();
					g.selectAll("#clip-" + id + " rect")
						.attr("x", x(extent[0]))
						.attr("width", x(extent[1]) - x(extent[0]));
				}
			}

			g.selectAll(".bar").attr("d", barPath);
		});

		function barPath(groups) {
			var path = [],
				i = -1,
				n = groups.length,
				d;
			while (++i < n) {
				d = groups[i];
				path.push("M", x(d.key), ",", height, "V", y(d.value), "h", barWidth, "V", height);
			}
			if (path.length == 0) {
				path.push("M0,0");
			}
			return path.join("");
		}

		function resizePath(d) {
			var e = +(d == "e"),
				x = e ? 1 : -1,
				y = height / 3;
			return "M" + (.5 * x) + "," + y
				+ "A6,6 0 0 " + e + " " + (6.5 * x) + "," + (y + 6)
				+ "V" + (2 * y - 6)
				+ "A6,6 0 0 " + e + " " + (.5 * x) + "," + (2 * y)
				+ "Z"
				+ "M" + (2.5 * x) + "," + (y + 8)
				+ "V" + (2 * y - 8)
				+ "M" + (4.5 * x) + "," + (y + 8)
				+ "V" + (2 * y - 8);
		}
	}

	brush.on("brushstart.chart", function() {
		var div = d3.select(this.parentNode.parentNode.parentNode);
		div.select(".title a").style("display", null);
	});

	brush.on("brush.chart", function() {
		var g = d3.select(this.parentNode),
			extent = brush.extent();
		if (round) g.select(".brush")
				.call(brush.extent(extent = extent.map(round)))
			.selectAll(".resize")
				.style("display", null);
		g.select("#clip-" + id + " rect")
			.attr("x", x(extent[0]))
			.attr("width", x(extent[1]) - x(extent[0]));
		dimension.filterRange(extent);
	});

	brush.on("brushend.chart", function() {
		if (brush.empty()) {
			var div = d3.select(this.parentNode.parentNode.parentNode);
			div.select(".title a").style("display", "none");
			div.select("#clip-" + id + " rect").attr("x", null).attr("width", "100%");
			dimension.filterAll();
		}
	});

	chart.margin = function(_) {
		if (!arguments.length) return margin;
		margin = _;
		return chart;
	};

	chart.x = function(_) {
		if (!arguments.length) return x;
		x = _;
		axis.scale(x);
		brush.x(x);
		return chart;
	};

	chart.y = function(_) {
		if (!arguments.length) return y;
		y = _;
		return chart;
	};

	chart.dimension = function(_) {
		if (!arguments.length) return dimension;
		dimension = _;
		return chart;
	};

	chart.filter = function(_) {
		if (_) {
			brush.extent(_);
			dimension.filterRange(_);
		} else {
			brush.clear();
			dimension.filterAll();
		}
		brushDirty = true;
		return chart;
	};

	chart.group = function(_) {
		if (!arguments.length) return group;
		group = _;
		return chart;
	};

	chart.round = function(_) {
		if (!arguments.length) return round;
		round = _;
		return chart;
	};

	return d3.rebind(chart, brush, "on");
}