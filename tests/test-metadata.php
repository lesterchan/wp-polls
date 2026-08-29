<?php
/**
 * WP-Polls' half of the metadata contract.
 *
 * The contract itself is Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php that every one of the
 * nineteen plugins carries. Everything shared lives there. What is left here is
 * what a machine cannot derive from the directory: the version being shipped,
 * the class prefix, the breaks the Upgrade Notice has to name, and the handful
 * of hooks that have to reach into WP-Polls' own classes.
 *
 * @package WP-Polls
 */

/**
 * The shared contract, plus what only WP-Polls can answer.
 */
class WP_Polls_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from WP_POLLS_VERSION, so a bump has to be
	 * made here as well and cannot happen by accident.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '3.0.2';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_Polls';
	}

	/**
	 * Everything a site owner updating from the released version would notice.
	 *
	 * The stored XSS fix, the shared stats_display row, the two screens that
	 * moved, the option rows that were folded up, the two poll templates that
	 * are overwritten, the renamed stylesheet, the three template functions that
	 * no longer exist, the class renames and the two renamed L10n objects.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'stats_display',
			'wp_polls_options',
			'wp_polls_version',
			'wp-polls-settings',
			'polls-options.php',
			'%POLL_ANSWER_IMAGEWIDTH%',
			'%POLL_ANSWER_PERCENTAGE%',
			'%POLL_RESULT_URL%',
			'polls-css.css',
			'wp-polls.css',
			'polls-css-rtl.css',
			'poll_vote()',
			'data-poll-action',
			'onclick',
			'Polls_Core',
			'WP_Polls_',
			'pollsL10n',
			'wpPollsL10n',
			'pollsAdminL10n',
			'wpPollsAdminL10n',
		);
	}

	/**
	 * WP-Polls is one of the seven sharing the WP-Stats surface.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * The one unprefixed WP-Stats row WP-Polls reads but does not own.
	 *
	 * The other shared row, stats_mostlimit, is not on the list: WP-Polls never
	 * read it.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array( WP_Polls_Options::LEGACY_STATS_DISPLAY );
	}

	/**
	 * Write the rows uninstall is expected to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_Polls_Options::save( WP_Polls_Options::defaults() );
		WP_Polls_Options::update_markers();
	}

	/**
	 * Write the wp_polls_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_Polls_Options::update_markers();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_Polls_Settings::sanitize( $input );
	}

	/**
	 * A real settings key to send through the sanitiser beside the poison.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array( 'archive' => array( 'per_page' => 9 ) );
	}

	/**
	 * Register the front-end and admin assets.
	 *
	 * The front end assets only load where a poll shows, so the request is put
	 * into that shape first or nothing is registered and the assertions run
	 * against an empty list. The admin half needs the hook suffix of the
	 * plugin's own screen, or it returns without registering anything.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		$GLOBALS['post'] = get_post( self::factory()->post->create( array( 'post_content' => '[poll]' ) ) );

		WP_Polls::scripts();
		WP_Polls_Admin::enqueue( WP_Polls_Admin::hook_suffix( WP_Polls_Admin::PAGE ) );
	}

	/**
	 * The sanitiser still cleans what the form did post.
	 *
	 * The shared test proves the markers are dropped; that alone would also pass
	 * a sanitiser that returned an empty array, so the fixture key has to come
	 * out the other side as well.
	 *
	 * @return void
	 */
	public function test_the_sanitizer_keeps_cleaning_what_the_form_posted() {
		$clean = $this->sanitize_settings(
			array_merge( $this->settings_fixture(), array( 'version' => '9.9.9' ) )
		);

		$this->assertSame( 9, (int) $clean['archive']['per_page'], 'The sanitiser dropped the field the form posted.' );
	}
}
