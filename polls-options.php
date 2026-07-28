<?php
/**
 * Poll Options admin screen.
 *
 * Every row on this screen is declared in Polls_Settings as a section and a
 * field, so all this file does is open the form, hand it to
 * do_settings_sections() and close it. Saving is handled by the Settings API:
 * the form posts to options.php, which validates the nonce and hands the input
 * to Polls_Settings::sanitize().
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

// Check Whether User Can Manage Polls.
if ( ! current_user_can( 'manage_polls' ) ) {
	die( 'Access Denied' );
}
?>
<script type="text/javascript">
/* <![CDATA[*/
(function () {
	function poll_field_value(id) {
		var field = document.getElementById(id);
		return field ? field.value : "";
	}
	// The background-image for each style comes from the same helper the front
	// end uses, so the preview cannot drift from what actually renders.
	var pollbar_images = <?php echo wp_json_encode( array_combine( Polls_Settings::bar_styles(), array_map( array( 'Polls_Core', 'bar_image' ), Polls_Settings::bar_styles() ) ) ); ?>;
	// The two colour fields are colour inputs, so their value is already the
	// "#rrggbb" CSS wants and they show the colour themselves - there is no
	// separate swatch beside them to keep in step any more.
	function update_pollbar() {
		var pollbar_background = poll_field_value("poll_bar_bg");
		var pollbar_border = poll_field_value("poll_bar_border");
		var pollbar_height = poll_field_value("poll_bar_height") + "px";
		var preview = document.getElementById("wp-polls-pollbar");
		// The preview is the real front end markup under the real front end
		// stylesheet, so setting the four custom properties is the whole job.
		if(preview) {
			var checked_style = document.querySelector("input[name='poll_options[bar][style]']:checked");
			var pollbar_style = checked_style ? checked_style.value : "";
			preview.style.setProperty("--wp-polls-bar-background", pollbar_background);
			preview.style.setProperty("--wp-polls-bar-border", pollbar_border);
			preview.style.setProperty("--wp-polls-bar-height", pollbar_height);
			preview.style.setProperty("--wp-polls-bar-image", pollbar_images[pollbar_style] || "none");
		}
		// The swatches follow the colours too, so the two styles stay comparable
		// while the fields are being edited. Their height stays fixed: it is set
		// in polls-admin-css.css so the gradient is visible at any bar height.
		var swatches = document.querySelectorAll(".wp-polls-swatch");
		for(var i = 0; i < swatches.length; i++) {
			swatches[i].style.setProperty("--wp-polls-bar-background", pollbar_background);
			swatches[i].style.setProperty("--wp-polls-bar-border", pollbar_border);
		}
	}
	document.addEventListener("click", function (event) {
		var target = event.target;
		if(!target || typeof target.closest !== "function") {
			return;
		}
		// Picking a style no longer touches the height field: it used to be set
		// from the height of that style's pollbg.gif, and there is no image now.
		if(target.closest('[data-poll-action="pollbar-style"]')) {
			update_pollbar();
		}
	});
	// "input" rather than "focusout": a colour input reports every change while
	// the picker is open, so the bar follows the colour being chosen instead of
	// waiting for the field to be left.
	document.addEventListener("input", function (event) {
		var target = event.target;
		if(!target || typeof target.closest !== "function") {
			return;
		}
		if(target.closest('[data-poll-action="pollbar-update"]')) {
			update_pollbar();
		}
	});
})();
/* ]]> */
</script>
<div class="wrap">
	<h1><?php esc_html_e( 'Poll Options', 'wp-polls' ); ?></h1>
	<?php settings_errors(); ?>
	<form id="poll_options_form" method="post" action="options.php">
		<?php
		settings_fields( Polls_Settings::GROUP );
		do_settings_sections( Polls_Settings::PAGE_OPTIONS );
		submit_button();
		?>
	</form>
</div>
