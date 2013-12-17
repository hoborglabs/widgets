require([
	'hoborglabs/widget',
	'lib/bonzo',
], function(HoborglabsWidget, Bonzo){

	function LatestVersion() {
		HoborglabsWidget.apply(this, arguments);
		this.counter = 1;
	}
	// extend Hoborglabs main Widget
	LatestVersion.prototype = Object.create( HoborglabsWidget.prototype );

	LatestVersion.prototype.getRCManifest = function() {
		return {
			id: this.id,
			name: "Latest Version",
			elements: [
				{
					name: "button",
					id: "btn1",
					config: {
						text: ["Show Todys Details", "Hide Todays Details"],
					}
				},
				{
					name: "button",
					id: "btn2",
					config: {
						text: ["Show This Week Details", "Hide Show This Week Details"],
					}
				}
			]
		};
	};

	LatestVersion.prototype.remoteCommand = function(data) {
		// check data.element
		if (data.element.id == 'btn1') {
			this.el.toggleClass('widget--hidden');
		} else if (data.element.id == 'btn2') {
			this.el.toggleClass('span8');
			this.el.toggleClass('span20');
			Bonzo(this.widget.get(0).getElementsByClassName('js-details')[0]).toggle();
			Bonzo(this.widget.get(0).getElementsByClassName('js-basic')[0]).toggle();
			Bonzo(this.widget.get(0).getElementsByClassName('js-basic')[1]).toggle();
		}
	};

	// register class to widget store
	if (window.Hoborglabs.Dashboard.widgetClasses) {
		window.Hoborglabs.Dashboard.widgetClasses.LatestVersion = LatestVersion;
	}

	return LatestVersion;
});