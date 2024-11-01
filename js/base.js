jQuery(document).ready(function($) {
	$("#wpe-enable-dashboard").click(function() {
		$("#wpe-settings-wrapper").hide();
		$("#wpe-enable-settings").removeClass("wpe-button-switch-primary");
		$("#wpe-enable-dashboard").addClass("wpe-button-switch-primary");
		$("#wpe-dashboard-wrapper").show();
	});

	$(".wpe-go-to-settings").click(function() {
		$("#wpe-dashboard-wrapper").hide();
		$("#wpe-enable-dashboard").removeClass("wpe-button-switch-primary");
		$("#wpe-enable-settings").addClass("wpe-button-switch-primary");
		$("#wpe-settings-wrapper").show();
	});
});
