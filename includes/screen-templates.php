<?php
/**
 * Poll Templates admin screen.
 *
 * Every template field is declared in Polls_Settings as a section and a field,
 * so all this file does is open the form, hand it to do_settings_sections()
 * and close it. Saving is handled by the Settings API: the form posts to
 * options.php, which validates the nonce and hands the input to
 * Polls_Settings::sanitize().
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

// Check Whether User Can Manage Polls.
if ( ! current_user_can( Polls_Admin::capability() ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage polls.', 'wp-polls' ), '', array( 'response' => 403 ) );
}
?>
<script type="text/javascript">
/* <![CDATA[*/
(function () {
	// Defaults come from Polls_Templates so this screen and the activation
	// routine cannot drift apart. Before 3.0.0 the markup was written out
	// twice, which is how one copy kept its inline onclick handlers after the
	// other had them removed.
	var pollDefaults = <?php echo wp_json_encode( Polls_Templates::defaults() ); ?>;

	document.addEventListener("click", function (event) {
		var target = event.target;
		if (!target || typeof target.closest !== "function") {
			return;
		}
		var button = target.closest('[data-poll-action="restore-template"]');
		if (!button) {
			return;
		}
		var key = button.getAttribute("data-poll-template");
		var field = document.getElementById("poll_template_" + key);
		if (field && Object.prototype.hasOwnProperty.call(pollDefaults, key)) {
			field.value = pollDefaults[key];
		}
	});
})();
/* ]]> */
</script>
<div class="wrap">
	<h1><?php esc_html_e( 'Poll Templates', 'wp-polls' ); ?></h1>
	<?php settings_errors(); ?>
	<form id="poll_template_form" method="post" action="options.php">
		<?php
		settings_fields( Polls_Settings::GROUP );
		do_settings_sections( Polls_Settings::PAGE_TEMPLATES );
		submit_button();
		?>
	</form>
</div>
