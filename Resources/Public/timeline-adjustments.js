(function($) {

TimelineRunner = function() {
	this._eventSources = [];
	this._timeline = null;
	this._memoryData = {};
	this._detailed = false;
}

TimelineRunner.MemoryUsageDecorator = function(params) {
	this._timelineRunner = params.timelineRunner;
	this._memoryDataIndex = params.dataset;
	this._detailed = params.detailed;
	this._leftOffset = null;
	this._width = null;
	this._height = null;
	this._paper = null;

};
TimelineRunner.MemoryUsageDecorator.prototype = {
	initialize: function(band, timeline) {
		this._band = band;
		this._timeline = timeline;
		this._$layerDiv = null;
	},
	paint: function() {
		var memoryData = this._timelineRunner._memoryData[this._memoryDataIndex];
		if (!memoryData) return;

		if (this._timelineRunner._$filterContainer.find('input:checkbox[name="showMemory"]').is(':checked')) {
			this._setupDrawingSurface();
			this._drawMemoryGraph();
		} else {
			this._removeDrawingSurface();
		}
	},
	_setupDrawingSurface: function() {
		var memoryData = this._timelineRunner._memoryData[this._memoryDataIndex];

		this._removeDrawingSurface();

	    this._$layerDiv = $(this._band.createLayerDiv(10));
		this._leftOffset = this._band.dateToPixelOffset(new Date(0));
		this._width = this._band.dateToPixelOffset(new Date(memoryData[memoryData.length - 1].time)) - this._leftOffset + 80;
		this._height = this._$layerDiv.height();

		this._paper = new Raphael(this._$layerDiv.get(0), this._width, this._height);
		this._$layerDiv.find('svg').css({
			position: 'absolute',
			left: this._leftOffset + 'px',
			top: 0
		});
	},
	_removeDrawingSurface: function() {
		if (this._$layerDiv != null) {
			this._band.removeLayerDiv(this._$layerDiv.get(0));
			this._$layerDiv = null;
	    }
	},
	_drawMemoryGraph: function() {
		var samplingPoint, pointX, pointY, dataPoint,
			memoryData = this._timelineRunner._memoryData[this._memoryDataIndex];

		// Find maximum memory
		var maximumMemory = 0;
		for (var i=0, l=memoryData.length; i<l; i++) {
			if (memoryData[i].mem > maximumMemory) {
				maximumMemory = memoryData[i].mem;
			}
		}

		// Build up line
		var points = [];
		for (var i=0, l=memoryData.length; i<l; i++) {
			samplingPoint = memoryData[i];
			pointY = this._height - (samplingPoint.mem / maximumMemory) * this._height;
			pointX = this._band.dateToPixelOffset(new Date(samplingPoint.time)) - this._leftOffset;
			points.push(pointX + ',' + pointY);
			if (this._detailed) {
				dataPoint = this._paper.circle(pointX, pointY, 2);
				dataPoint.attr('fill', '#7779FF');
				dataPoint.attr('stroke', 'none');
			}
		}
		var path = this._paper.path('M 0,' + this._height + ' L ' + points.join(' '));
		path.attr('stroke', '#7779FF');
		path.attr('stroke-width', 2);

		this._drawLegend(1/4, maximumMemory);
		this._drawLegend(1/2, maximumMemory);
		this._drawLegend(3/4, maximumMemory);
		this._drawLegend(1, maximumMemory);
	},
	_drawLegend: function(percentage, maximumMemory) {
		var yOffset = (this._height - percentage*this._height);
		var path = this._paper.path('M 0,' + yOffset + ' L ' + this._width + ',' + yOffset);
		path.attr('stroke', '#7779FF');
		path.attr('stroke-width', 1);
		path.attr('stroke-dasharray', '- ');

		var t = this._paper.text(this._width, yOffset + 5, Math.round(maximumMemory*percentage / 1024) + ' kB');
		t.attr('fill', '#7779FF');
		t.attr('text-anchor', 'end');
	},
	softPaint: function() {
	}
}

TimelineRunner.prototype = {

	/**
	 * @type {Array}
	 */
	_eventSources: null,

	_numberOfProfilingRuns: 0,

	_timeline: null,

	_$filterContainer: null,
	_memoryData: null,

	/**
	 * Name of currently highlighted filter
	 */
	_currentlyHighlightedFilter: null,

	/***********************************
	 * SECTION: Public API
	 ***********************************/
	run: function(numberOfProfilingRuns, $timelineContainer, $filterContainer) {
		this._numberOfProfilingRuns = numberOfProfilingRuns;
		this._monkeyPatchTimelineSource();
		var bandInfos = this._setupTimeline();
		this._timeline = Timeline.create($timelineContainer.get(0), bandInfos);
		this._$filterContainer = $filterContainer;
	},

	addEvent: function(eventSourceIndex, event) {
		this._eventSources[eventSourceIndex].add(event);
	},
	// Triggered after all events have been added
	update: function() {
		this._initializeFilter();
		this._timeline.layout();
	},
	setMemory: function(eventSourceIndex, memoryData) {
		this._memoryData[eventSourceIndex] = memoryData;
	},

	/***********************************
	 * SECTION: Setting up timeline
	 ***********************************/

	_monkeyPatchTimelineSource: function () {
		var that = this;
		// TODO: if we need to define custom date/time intervals, we can do as shown here:
		// http://www.nickrabinowitz.com/projects/timemap/opencontext/oc.js

		// Adjust Millisecond rendering in timeline
		var originalLabellerFunction = Timeline.GregorianDateLabeller.prototype.defaultLabelInterval;
		Timeline.GregorianDateLabeller.prototype.defaultLabelInterval = function(date, intervalUnit) {
			// call original function if we're not dealing with new unit
			if (intervalUnit === Timeline.DateTime.MILLISECOND) {
				var emphasized = (date.getTime() % 1000 === 0);
				return {emphasized: emphasized, text:date.getTime()};
			} else {
				return originalLabellerFunction.call(this, date, intervalUnit);
			}
		};

		// Adjust info bubble
		Timeline.DefaultEventSource.Event.prototype.fillInfoBubble = function(elmt, theme, labeller) {
			$(elmt).append($('<h2>' + this._title + '</h2>'));
			if (this.isInstant()) {
				$(elmt).append($('<div>' + this._start.getTime() + ' ms</div>'));
			} else {
				$(elmt).append($('<div>' + this._start.getTime() + ' ms - ' + this._end.getTime() + 'ms (' + (this._end.getTime() - this._start.getTime()) + ' ms) </div>'));
			}

			var tableCells = '';
			$.each(this._description, function(key, value) {
				tableCells += '<tr><th>' + key + '</th><td>' + value + '</td></tr>';
			});
			if (tableCells.length) {
				$(elmt).append($('<table>' + tableCells + '</table>'));
			}
		};

		Timeline.OriginalEventPainter.prototype._findFreeTrack = function(event, rightEdge) {
			var isCompactDrawingModeEnabled = that._$filterContainer.find('input:checkbox[name="compactDrawing"]').is(':checked');
			var trackAttribute = event.getTrackNum();
			if (trackAttribute != null) {
				return trackAttribute; // early return since event includes track number
			}

			// normal case: find an open track
			for (var i = 0; i < this._tracks.length; i++) {
				var t = this._tracks[i];
				if ((t >= rightEdge && isCompactDrawingModeEnabled) || (t > rightEdge)) {
					break;
				}
			}
			return i;
		};

	},

	_setupTimeline: function() {
		for (var i=0; i<this._numberOfProfilingRuns; i++) {
			this._eventSources[i] = new Timeline.DefaultEventSource();
		}

		var theme = Timeline.ClassicTheme.create();
		theme.event.tape.height = 10;
		theme.autoWidth = true;

		var bandInfos = [];

		for (var i=0; i<this._numberOfProfilingRuns; i++) {
			bandInfos[i] = this._createBand(i, theme);
			bandInfos[i].decorators = this._createDecorators({
				dataset: i,
				detailed: true
			});
		}

		for (var i=0; i<this._numberOfProfilingRuns; i++) {
			bandInfos[this._numberOfProfilingRuns + i] = this._createOverviewBand(i, theme);
			bandInfos[this._numberOfProfilingRuns + i].syncWith = i;
			bandInfos[this._numberOfProfilingRuns + i].highlight = true;
			bandInfos[this._numberOfProfilingRuns + i].decorators = this._createDecorators({dataset: i, detailed: false});
		}

		return bandInfos;
	},
	_createDecorators: function(params) {
		var decorators = [];
		params.timelineRunner = this;

		decorators[0] = new TimelineRunner.MemoryUsageDecorator(params);

		return decorators;
	},
	_createBand: function(eventSourceIndex, theme) {
		return Timeline.createBandInfo({
			width:          "85%",
			intervalUnit:   Timeline.DateTime.MILLISECOND,
			multiple: 100,
			intervalPixels: 1,
			eventSource:    this._eventSources[eventSourceIndex],
			date: new Date(0),
			theme: theme,
			zoomIndex: 8,
			zoomSteps: [
				{pixelsPerInterval: 25,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 18,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 12,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 8,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 6,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 4,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 3,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 2,  unit: Timeline.DateTime.MILLISECOND},
				{pixelsPerInterval: 1,  unit: Timeline.DateTime.MILLISECOND}
			]
		});
	},
	_createOverviewBand: function(eventSourceIndex) {
		return Timeline.createBandInfo({
			width:          "15%",
			intervalUnit:   Timeline.DateTime.SECOND,
			intervalPixels: 100,
			eventSource:    this._eventSources[eventSourceIndex],
			overview: true
		});
	},

	/***********************************
	 * SECTION: Filtering
	 ***********************************/
	_initializeFilter: function() {
		var event, category, i, it, $inputList, $listEl, $checkbox, that = this;

		// Get all filter categories
		var filters = {};
		for (i=0; i<this._eventSources.length; i++) {
			it = this._eventSources[i].getAllEventIterator();

			while (event = it.next()) {
				category = event._title.split(':')[0];
				filters[category] = true;
			}
		}

		// Draw filters
		$inputList = $('<ul class="inputs-list filters"></ul>');
		this._$filterContainer.append($inputList);
		$.each(filters, function(key, value) {
				$listEl = $('<li><label></label></li>');
				$inputList.append($listEl);

				$checkbox = $('<input type="checkbox" name="filters" />').attr('value', key);

				if (value) {
					$checkbox.attr('checked', 'checked');
				}

				$listEl.find('label').append($checkbox);
				$listEl.find('label').append($('<span />').html(key));
		});

		this._$filterContainer.append($('<hr />'));
		this._$filterContainer.append($('<ul class="inputs-list displaySettings"></ul>'));
		this._$filterContainer.find('.displaySettings').append($('<li><label><input type="checkbox" name="compactDrawing" checked="checked" /><span>Compact Drawing</span></label></li>'));
		this._$filterContainer.find('.displaySettings').append($('<li><label><input type="checkbox" name="showMemory" checked="checked" /><span>Show Memory Consumption</span></label></li>'));

		this._$filterContainer.find('ul.filters li').mouseover(function() {
			var val = $(this).find('input:checkbox').attr('value');
			window.setTimeout(function() {
				if (val !== that._currentlyHighlightedFilter) {
					that._currentlyHighlightedFilter = val;
					that._timeline.paint();
				}
			}, 300);
		});
		this._$filterContainer.find('ul.filters li').mouseout(function() {
			var val = $(this).find('input:checkbox').attr('value');
			window.setTimeout(function() {
				if (val === that._currentlyHighlightedFilter) {
					that._currentlyHighlightedFilter = null;
					that._timeline.paint();
				}
			}, 300);
		});

		this._$filterContainer.find('input:checkbox').change(function() {
			for (i=0; i<that._numberOfProfilingRuns; i++) {
				// WORKAROUND for *shrinking* the timeline width if necessary. On paint(), it will recalculate the number of needed event tracks anyways.
				that._timeline.getBand(i)._eventTracksNeeded = 0;
			}
			that._timeline.paint();
			that._timeline.layout();
		});

		// Set up filtering
		for (i=0; i<this._numberOfProfilingRuns; i++) {
			this._timeline.getBand(i).getEventPainter().setFilterMatcher(function(event) {
				return that._filterMatcher(event);
			});
			this._timeline.getBand(i).getEventPainter().setHighlightMatcher(function(event) {
				return that._highlightMatcher(event);
			});
		}
	},

	/**
	 * Timeline filter matcher
	 */
	_filterMatcher: function(event) {
		var category = event._title.split(':')[0];
		return this._$filterContainer.find('input:checkbox[name="filters"][value="' + category + '"]').is(':checked');
	},
	_highlightMatcher: function(event) {
		var category = event._title.split(':')[0];
		if (category === this._currentlyHighlightedFilter) {
			return 0;
		}
		return -1;
	}
};
})(jQuery);