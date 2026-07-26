<?php
/**
 * Rendering, escaping and the template variable contract.
 *
 * @package WP-Polls
 */

/**
 * Poll and result markup assembled by Polls_Display.
 *
 * @covers Polls_Display
 */
class Test_Polls_Display extends WP_Polls_TestCase {

	/**
	 * The voting form carries the pieces the JavaScript needs.
	 */
	public function test_vote_form_has_the_hooks_the_script_needs() {
		$poll_id = $this->make_poll();
		$html    = Polls_Display::display_pollvote( $poll_id );

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', $html );
		$this->assertStringContainsString( 'data-poll-action="vote"', $html );
		$this->assertStringContainsString( 'data-poll-id="' . $poll_id . '"', $html );
		$this->assertStringContainsString( 'name="wp-polls-nonce"', $html );
	}

	/**
	 * Inline handlers must never appear in rendered output.
	 */
	public function test_rendered_output_has_no_inline_handlers() {
		$poll_id = $this->make_poll();

		$this->assertStringNotContainsStringIgnoringCase( 'onclick', Polls_Display::display_pollvote( $poll_id ) );
		$this->assertStringNotContainsStringIgnoringCase( 'onclick', Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * Every template token is substituted.
	 */
	public function test_no_unreplaced_tokens_remain() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 3 ), array( 'B', 1 ) ) );

		$this->assertStringNotContainsString( '%POLL_', Polls_Display::display_pollvote( $poll_id ) );
		$this->assertStringNotContainsString( '%POLL_', Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * Answer markup allowed by kses survives; scripts do not.
	 */
	public function test_answer_markup_is_filtered_not_stripped() {
		$poll_id = $this->make_poll(
			array(),
			array( array( 'vim <strong>btw</strong>', 1 ), array( 'bad <script>alert(1)</script>', 0 ) )
		);
		$html    = Polls_Display::display_pollvote( $poll_id );

		$this->assertStringContainsString( '<strong>btw</strong>', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $html );
	}

	/**
	 * %POLL_ANSWER_TEXT% sits inside a title attribute, so it must be
	 * attribute-escaped exactly once.
	 *
	 * The regression this guards: htmlspecialchars() defaults to
	 * $double_encode = true, so an answer containing "&" - already &amp; after
	 * kses - rendered as a literal &amp; in the tooltip.
	 */
	public function test_answer_text_is_escaped_once() {
		$poll_id = $this->make_poll( array(), array( array( 'Emacs & "friends"', 1 ) ) );
		$html    = Polls_Display::display_pollresult( $poll_id );

		$this->assertStringContainsString( 'title="Emacs &amp; &quot;friends&quot;', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * A poll that no longer exists renders the disabled template, not a warning.
	 */
	public function test_missing_poll_renders_the_disabled_template() {
		$expected = Polls_Options::get( 'templates.disable' );

		$this->assertSame( $expected, Polls_Display::display_pollvote( 999999 ) );
		$this->assertSame( $expected, Polls_Display::display_pollresult( 999999 ) );
	}

	/**
	 * The documented template variables are all offered to the filter.
	 */
	public function test_votebody_template_variables_are_stable() {
		$poll_id = $this->make_poll();
		$seen    = array();

		add_filter(
			'wp_polls_template_votebody_variables',
			function ( $vars ) use ( &$seen ) {
				$seen = array_keys( $vars );
				return $vars;
			}
		);
		Polls_Display::display_pollvote( $poll_id );
		sort( $seen );

		$this->assertSame(
			array(
				'%POLL_ANSWER%',
				'%POLL_ANSWER_ID%',
				'%POLL_ANSWER_PERCENTAGE%',
				'%POLL_ANSWER_VOTES%',
				'%POLL_CHECKBOX_RADIO%',
				'%POLL_ID%',
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%',
			),
			$seen
		);
	}

	/**
	 * The result markup filter is applied to the returned string.
	 */
	public function test_result_markup_filter_is_applied() {
		$poll_id = $this->make_poll();
		add_filter(
			'wp_polls_result_markup',
			function ( $markup ) {
				return $markup . '<!--MARK-->';
			}
		);

		$this->assertStringContainsString( '<!--MARK-->', Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * A multiple-answer poll renders checkboxes and its limit field.
	 */
	public function test_multiple_answer_poll_renders_checkboxes() {
		$poll_id = $this->make_poll( array( 'pollq_multiple' => 2 ), array( array( 'A', 0 ), array( 'B', 0 ) ) );
		$html    = Polls_Display::display_pollvote( $poll_id );

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'poll_multiple_ans_' . $poll_id, $html );
	}
}
