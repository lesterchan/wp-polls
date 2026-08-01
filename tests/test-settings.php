<?php
/**
 * The Settings API sanitize callback.
 *
 * Both admin screens write into the same option row and the Settings API
 * hands the callback only the fields the submitting form rendered, so the
 * merge is load-bearing: get it wrong and saving Poll Options wipes all
 * fifteen templates.
 *
 * @package WP-Polls
 */

/**
 * Settings API registration and the sanitize callback both tabs share.
 *
 * @covers WP_Polls_Settings
 */
class WP_Polls_Settings_Test extends WP_Polls_TestCase {

	/**
	 * Saving the Options screen must not disturb the templates.
	 */
	public function test_options_save_preserves_templates() {
		WP_Polls_Options::set( 'templates.votefooter', 'SENTINEL' );
		WP_Polls_Options::flush();

		$result = WP_Polls_Settings::sanitize(
			array(
				'bar'     => array(
					'style'      => 'default',
					'background' => 'aabbcc',
					'border'     => 'ddeeff',
					'height'     => 14,
				),
				'archive' => array( 'per_page' => 9 ),
			)
		);

		$this->assertSame( 'SENTINEL', $result['templates']['votefooter'] );
		$this->assertSame( 'aabbcc', $result['bar']['background'] );
		$this->assertSame( 9, (int) $result['archive']['per_page'] );
	}

	/**
	 * And the reverse: saving Templates must not disturb the options.
	 */
	public function test_templates_save_preserves_options() {
		WP_Polls_Options::set( 'bar.background', 'aabbcc' );
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_REAL_IP' );
		WP_Polls_Options::flush();

		$result = WP_Polls_Settings::sanitize(
			array( 'templates' => array( 'disable' => 'Nothing here' ) )
		);

		$this->assertSame( 'aabbcc', $result['bar']['background'] );
		$this->assertSame( 'HTTP_X_REAL_IP', $result['ip_header'] );
		$this->assertSame( 'Nothing here', $result['templates']['disable'] );
	}

	/**
	 * A template the form did not submit keeps its stored value.
	 */
	public function test_untouched_template_is_preserved() {
		WP_Polls_Options::set( 'templates.voteheader', 'KEEP ME' );
		WP_Polls_Options::flush();

		$result = WP_Polls_Settings::sanitize( array( 'templates' => array( 'disable' => 'x' ) ) );

		$this->assertSame( 'KEEP ME', $result['templates']['voteheader'] );
	}

	/**
	 * The AJAX style toggles are gone, and nothing puts them back.
	 *
	 * They chose whether to show a loading indicator and whether to fade the
	 * poll while a vote was in flight. Telling somebody their vote is being
	 * processed is feedback rather than decoration, and a setting whose "off"
	 * position is a worse plugin is a setting to remove; the fade now follows
	 * prefers-reduced-motion, which is the visitor's answer rather than the
	 * site owner's.
	 */
	public function test_the_ajax_style_settings_are_gone() {
		$this->assertArrayNotHasKey( 'ajax', WP_Polls_Options::all(), 'the AJAX style keys are back' );

		$result = WP_Polls_Settings::sanitize( array( 'ajax' => array( 'loading' => 1 ) ) );

		$this->assertArrayNotHasKey( 'ajax', $result, 'the sanitizer accepted a setting that no longer exists' );
	}

	/**
	 * Values outside the whitelist are rejected, not stored.
	 */
	public function test_invalid_values_are_rejected() {
		$result = WP_Polls_Settings::sanitize(
			array(
				'bar'  => array(
					'style'      => '../../etc/passwd',
					'background' => 'zzzzzz',
					'height'     => -5,
				),
				'sort' => array( 'answers_by' => 'polla_aid; DROP TABLE' ),
			)
		);

		$this->assertSame( 'gradient', $result['bar']['style'] );
		$this->assertSame( '000000', $result['bar']['background'] );
		$this->assertGreaterThanOrEqual( 1, (int) $result['bar']['height'] );
		$this->assertSame( 'polla_aid', $result['sort']['answers_by'] );
	}

	/**
	 * Garbage input returns the stored option rather than destroying it.
	 */
	public function test_non_array_input_returns_current_option() {
		$result = WP_Polls_Settings::sanitize( 'not an array' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'templates', $result );
	}

	/**
	 * An onclick attribute must not survive a template save; data-poll-* must.
	 *
	 * This is the XSS fix from 3.0.0 - if the allow list ever regains onclick,
	 * this fails.
	 */
	public function test_template_sanitiser_strips_onclick_but_keeps_data_attributes() {
		$out = WP_Polls_Settings::sanitize_template(
			'votefooter',
			'<input type="button" onclick="poll_vote(1)" data-poll-id="%POLL_ID%" data-poll-action="vote" />'
		);

		$this->assertStringNotContainsStringIgnoringCase( 'onclick', $out );
		$this->assertStringContainsString( 'data-poll-action="vote"', $out );
		$this->assertStringContainsString( 'data-poll-id="%POLL_ID%"', $out );
	}

	/**
	 * Scripts never survive a template save.
	 */
	public function test_template_sanitiser_strips_scripts() {
		$out = WP_Polls_Settings::sanitize_template( 'voteheader', '<p>ok</p><script>alert(1)</script>' );

		$this->assertStringNotContainsStringIgnoringCase( '<script', $out );
		$this->assertStringContainsString( '<p>ok</p>', $out );
	}

	/**
	 * The setting is registered under the group both tabs post to.
	 */
	public function test_setting_is_registered() {
		WP_Polls_Settings::register();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( WP_Polls_Options::OPTION, $registered );
	}

	/**
	 * Writing the option reschedules the cron job.
	 *
	 * The expiry setting decides the schedule, and the save happens on
	 * options.php. The callback used to be added while the Poll Options tab
	 * rendered, so it was never registered on the request that did the saving.
	 */
	public function test_saving_the_option_reschedules_the_cron() {
		wp_clear_scheduled_hook( 'polls_cron' );
		$this->assertFalse( wp_next_scheduled( 'polls_cron' ) );

		WP_Polls_Options::set( 'cookie_expiry', 3600 );

		$this->assertNotFalse( wp_next_scheduled( 'polls_cron' ) );
	}

	/**
	 * Both screens exist as registered sections carrying registered fields.
	 *
	 * Nothing on either screen is written out as table markup, so an empty
	 * bucket here is an empty screen there.
	 */
	public function test_both_screens_are_registered_as_sections_and_fields() {
		WP_Polls_Settings::register();

		global $wp_settings_sections, $wp_settings_fields;

		foreach ( array( WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ), WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_TEMPLATES ) ) as $page ) {
			$this->assertArrayHasKey( $page, $wp_settings_sections, $page . ' registered no sections' );
			$this->assertNotEmpty( $wp_settings_sections[ $page ], $page . ' registered no sections' );
			$this->assertArrayHasKey( $page, $wp_settings_fields, $page . ' registered no fields' );
			$this->assertNotEmpty( $wp_settings_fields[ $page ], $page . ' registered no fields' );
		}
	}

	/**
	 * Every template the plugin stores has a field of its own, and no more.
	 *
	 * The screen used to list the fifteen textareas by hand, so a template added
	 * to WP_Polls_Options::template_keys() could be saved by the sanitiser and
	 * still have nowhere to edit it.
	 */
	public function test_every_template_key_has_exactly_one_field() {
		WP_Polls_Settings::register();

		global $wp_settings_fields;

		$ids = array();
		foreach ( $wp_settings_fields[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_TEMPLATES ) ] as $section ) {
			$ids = array_merge( $ids, array_keys( $section ) );
		}

		foreach ( WP_Polls_Options::template_keys() as $key ) {
			$this->assertContains( 'poll_template_' . $key, $ids, $key . ' has no field on the screen' );
		}

		$this->assertCount( count( WP_Polls_Options::template_keys() ), $ids );
	}

	/**
	 * Registering a second time replaces the rows rather than repeating them.
	 *
	 * Registration runs on admin_init, and the render helper calls it again the
	 * way wp-admin does before including a screen.
	 */
	public function test_registering_twice_does_not_duplicate_rows() {
		WP_Polls_Settings::register();

		global $wp_settings_sections, $wp_settings_fields;

		$sections = count( $wp_settings_sections[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ) ] );
		$fields   = count( $wp_settings_fields[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ) ]['wp_polls_bar'] );

		WP_Polls_Settings::register();

		$this->assertCount( $sections, $wp_settings_sections[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ) ] );
		$this->assertCount( $fields, $wp_settings_fields[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ) ]['wp_polls_bar'] );
	}
}
