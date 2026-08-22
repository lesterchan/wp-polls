<?php
/**
 * The one WP-Polls settings screen.
 *
 * Settings and Templates are two tabs of it rather than two submenu
 * entries: they are one setting, posted by one form, and the standard allows a
 * plugin's settings exactly one page. Every row on both tabs is declared in
 * WP_Polls_Settings as a section and a field, so all this screen does is draw
 * the tabs, open the form, hand it to do_settings_sections() and close it.
 * Saving is the Settings API's: the form posts to options.php, which checks the
 * nonce and hands the input to WP_Polls_Settings::sanitize().
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class WP_Polls_Screen_Settings {

	/**
	 * Render the settings screen, on whichever tab was asked for.
	 *
	 * @return void
	 */
	public static function render() {
		// Check Whether User Can Manage Polls.
		if ( ! current_user_can( WP_Polls_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage polls.', 'wp-polls' ), '', array( 'response' => 403 ) );
		}

		$poll_current_tab = WP_Polls_Settings::current_tab();
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Poll Settings', 'wp-polls' ); ?></h1>
		<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( WP_Polls_Settings::tabs() as $poll_tab => $poll_tab_label ) : ?>
			<a class="nav-tab<?php echo $poll_tab === $poll_current_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( WP_Polls_Settings::tab_url( $poll_tab ) ); ?>"><?php echo esc_html( $poll_tab_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form id="poll_settings_form" method="post" action="options.php">
		<?php
		// settings_fields() emits the referer field, and the referer is this
		// page's own URL, so options.php sends the browser back to the tab it
		// was saved from rather than to the first one.
		settings_fields( WP_Polls_Settings::GROUP );
		do_settings_sections( WP_Polls_Settings::tab_bucket( $poll_current_tab ) );
		submit_button();
		?>
	</form>
</div>
		<?php
	}
}
