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
 * Settings API registration and the sanitize callback both screens share.
 *
 * @covers Polls_Settings
 */
class Test_Polls_Settings extends WP_Polls_TestCase {

	/**
	 * Saving the Options screen must not disturb the templates.
	 */
	public function test_options_save_preserves_templates() {
		Polls_Options::set( 'templates.votefooter', 'SENTINEL' );
		Polls_Options::flush();

		$result = Polls_Settings::sanitize(
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
		Polls_Options::set( 'bar.background', 'aabbcc' );
		Polls_Options::set( 'ip_header', 'HTTP_X_REAL_IP' );
		Polls_Options::flush();

		$result = Polls_Settings::sanitize(
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
		Polls_Options::set( 'templates.voteheader', 'KEEP ME' );
		Polls_Options::flush();

		$result = Polls_Settings::sanitize( array( 'templates' => array( 'disable' => 'x' ) ) );

		$this->assertSame( 'KEEP ME', $result['templates']['voteheader'] );
	}

	/**
	 * An unchecked checkbox is absent from the payload, and means zero.
	 */
	public function test_absent_checkbox_becomes_zero() {
		$result = Polls_Settings::sanitize( array( 'ajax' => array( 'loading' => 1 ) ) );

		$this->assertSame( 1, (int) $result['ajax']['loading'] );
		$this->assertSame( 0, (int) $result['ajax']['fading'] );
	}

	/**
	 * Values outside the whitelist are rejected, not stored.
	 */
	public function test_invalid_values_are_rejected() {
		$result = Polls_Settings::sanitize(
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
		$result = Polls_Settings::sanitize( 'not an array' );

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
		$out = Polls_Settings::sanitize_template(
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
		$out = Polls_Settings::sanitize_template( 'voteheader', '<p>ok</p><script>alert(1)</script>' );

		$this->assertStringNotContainsStringIgnoringCase( '<script', $out );
		$this->assertStringContainsString( '<p>ok</p>', $out );
	}

	/**
	 * The setting is registered under the group both screens post to.
	 */
	public function test_setting_is_registered() {
		Polls_Settings::register();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Polls_Options::OPTION, $registered );
	}

	/**
	 * Writing the option reschedules the cron job.
	 *
	 * The expiry setting decides the schedule, and the save happens on
	 * options.php. The callback used to be added while the Poll Options screen
	 * rendered, so it was never registered on the request that did the saving.
	 */
	public function test_saving_the_option_reschedules_the_cron() {
		wp_clear_scheduled_hook( 'polls_cron' );
		$this->assertFalse( wp_next_scheduled( 'polls_cron' ) );

		Polls_Options::set( 'cookie_expiry', 3600 );

		$this->assertNotFalse( wp_next_scheduled( 'polls_cron' ) );
	}

	/**
	 * Both screens exist as registered sections carrying registered fields.
	 *
	 * Nothing on either screen is written out as table markup, so an empty
	 * bucket here is an empty screen there.
	 */
	public function test_both_screens_are_registered_as_sections_and_fields() {
		Polls_Settings::register();

		global $wp_settings_sections, $wp_settings_fields;

		foreach ( array( Polls_Settings::PAGE_OPTIONS, Polls_Settings::PAGE_TEMPLATES ) as $page ) {
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
	 * to Polls_Options::template_keys() could be saved by the sanitiser and
	 * still have nowhere to edit it.
	 */
	public function test_every_template_key_has_exactly_one_field() {
		Polls_Settings::register();

		global $wp_settings_fields;

		$ids = array();
		foreach ( $wp_settings_fields[ Polls_Settings::PAGE_TEMPLATES ] as $section ) {
			$ids = array_merge( $ids, array_keys( $section ) );
		}

		foreach ( Polls_Options::template_keys() as $key ) {
			$this->assertContains( 'poll_template_' . $key, $ids, $key . ' has no field on the screen' );
		}

		$this->assertCount( count( Polls_Options::template_keys() ), $ids );
	}

	/**
	 * Registering a second time replaces the rows rather than repeating them.
	 *
	 * Registration runs on admin_init, and the render helper calls it again the
	 * way wp-admin does before including a screen.
	 */
	public function test_registering_twice_does_not_duplicate_rows() {
		Polls_Settings::register();

		global $wp_settings_sections, $wp_settings_fields;

		$sections = count( $wp_settings_sections[ Polls_Settings::PAGE_OPTIONS ] );
		$fields   = count( $wp_settings_fields[ Polls_Settings::PAGE_OPTIONS ]['wp_polls_bar'] );

		Polls_Settings::register();

		$this->assertCount( $sections, $wp_settings_sections[ Polls_Settings::PAGE_OPTIONS ] );
		$this->assertCount( $fields, $wp_settings_fields[ Polls_Settings::PAGE_OPTIONS ]['wp_polls_bar'] );
	}
}
