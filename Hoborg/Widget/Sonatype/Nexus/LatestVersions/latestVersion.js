require([
	'hoborglabs/widget'
], function(HoborglabsWidget){

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
						text: ["Show Details", "Hide Details"],
					}
				},
				{
					name: "button",
					id: "btn2",
					config: {
						text: ["Show Details", "Hide Details"],
					}
				}
			]
		};
	};

	LatestVersion.prototype.remoteCommand = function(data) {
		// check data.element
		if (data.element.id == 'btn1') {

		} else if (data.element.id == 'btn1') {

		}
	};

	// register class to widget store
	if (window.Hoborglabs.Dashboard.widgetClasses) {
		window.Hoborglabs.Dashboard.widgetClasses.LatestVersion = LatestVersion;
	}

	return LatestVersion;
});