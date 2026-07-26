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

		$this->assertSame( 'default', $result['bar']['style'] );
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
}
