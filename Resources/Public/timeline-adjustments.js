(function($) {

TimelineRunner = function() {
	this._eventSources = [];
	this._timeline = null;
}
TimelineRunner.prototype = {

	/**
	 * @type {Array}
	 */
	_eventSources: null,

	_numberOfProfilingRuns: 0,

	_timeline: null,

	_$filterContainer: null,

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
	update: function() {
		this._initializeFilter();
		this._timeline.layout();
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
		}

		for (var i=0; i<this._numberOfProfilingRuns; i++) {
			bandInfos[this._numberOfProfilingRuns + i] = this._createOverviewBand(i, theme);
			bandInfos[this._numberOfProfilingRuns + i].syncWith = i;
			bandInfos[this._numberOfProfilingRuns + i].highlight = true;
		}

		return bandInfos;
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
		this._$filterContainer.append($('<ul class="inputs-list"><li><label><input type="checkbox" name="compactDrawing" checked="checked" /><span>Compact Drawing</span></label></li></ul>'));

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
			that._timeline.paint();
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