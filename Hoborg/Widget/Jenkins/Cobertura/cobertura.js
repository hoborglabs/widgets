// cobertura global function
function mainCodeCoverage(widget) {

	var configEl = widget.get(0).getElementsByClassName('js-config')[0]
	Cobertura.shameD3js({
		width: parseInt(configEl.getAttribute('data-width')),
		height: parseInt(configEl.getAttribute('data-height')),
		svgid: configEl.getAttribute('data-svgid'),
		data: JSON.parse(configEl.getAttribute('data-graph'))
	});
}

Cobertura = {
	shameD3js: function(cfg) {
		console.log('d3js go', cfg);
		var margin = {top: 20, right: 20, bottom: 30, left: 50};

		var x = d3.scale.linear().range([0, cfg.width]),
			xAxis = d3.svg.axis().scale(x).orient("bottom").tickPadding(2);

		var y = d3.scale.linear().range([cfg.height, 0]),
			yAxis = d3.svg.axis().scale(y).orient("left").ticks(5);

		x.domain([50,61]);
		y.domain([0,100]);

		var svg = d3.select("#" + cfg.svgid)
			.attr("width", cfg.width + margin.left + margin.right)
			.attr("height", cfg.height + margin.top + margin.bottom)
			.append("g")
				.attr("transform", "translate(" + margin.left + "," + margin.top + ")");

		svg.append("g")
			.attr("class", "x axis")
			.attr("transform", "translate(0," + cfg.height + ")")
			.call(xAxis);

		svg.append("g")
			.attr("class", "y axis")
			.call(yAxis)
		.append("text")
			.attr("transform", "rotate(-90)")
			.attr("y", 6)
			.attr("dy", ".71em")
			.style("text-anchor", "end")
			.text("Code Coverage (%)");

		var data = cfg.data;
		console.log(data);
		/*
		[
			{
				label: "Classes",
				points: [
				{
					build: 50,
					ratio: 87,
					ratioMax: 90,
					ratioMin: 40,
				},
				{
					build: 51,
					ratio: 98,
					ratioMax: 100,
					ratioMin: 70,
				},
				{
					build: 52,
					ratio: 100,
					ratioMax: 100,
					ratioMin: 100,
				}],
			},
			{
				label: "Else",
				points: [
				{
					build: 50,
					ratio: 67,
					ratioMax: 80,
					ratioMin: 60,
				},
				{
					build: 51,
					ratio: 88,
					ratioMax: 100,
					ratioMin: 70,
				},
				{
					build: 52,
					ratio: 100,
					ratioMax: 100,
					ratioMin: 100,
				}],
			}
		];
		 */

		var line = d3.svg.line()
			.x(function(d) { return x(d.build); })
			.y(function(d) { return y(d.ratio); });
		var colors = d3.scale.category20c();

		// draw bars
		size = 40 / data.length;
		delta = 20 - size;
		for (i in data) {
			points = data[i].points;
			console.log(points);

			svg.selectAll(".bar--"+data[i].label)
				.data(points)
			.enter().append("rect")
				.attr("class", "bar bar--"+data[i].label)
				.style('fill', colors(data[i].label))
				.attr("x", function(d) { return delta + x(d.build) - i * size; })
				.attr("width", size)
				.attr("y", function(d) { return y(d.ratioMax) - 2; })
				.attr("height", function(d) { return 4 + y(d.ratioMin) - y(d.ratioMax); });
		}
		// draw lines on top
		for (i in data) {
			points = data[i].points;
			svg.append("path")
				.datum(points)
				.attr("class", "path path--" + data[i].label)
				.style('stroke', colors(data[i].label))
				.attr("d", line);
		}
	},
};