<?php
/**
 * Rendering, escaping and the template variable contract.
 *
 * @package WP-Polls
 */

/**
 * Poll and result markup assembled by WP_Polls_Display.
 *
 * @covers WP_Polls_Display
 */
class WP_Polls_Display_Test extends WP_Polls_TestCase {

	/**
	 * The voting form carries the pieces the JavaScript needs.
	 */
	public function test_vote_form_has_the_hooks_the_script_needs() {
		$poll_id = $this->make_poll();
		$html    = WP_Polls_Display::display_pollvote( $poll_id );

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

		$this->assertStringNotContainsStringIgnoringCase( 'onclick', WP_Polls_Display::display_pollvote( $poll_id ) );
		$this->assertStringNotContainsStringIgnoringCase( 'onclick', WP_Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * Every template token is substituted.
	 */
	public function test_no_unreplaced_tokens_remain() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 3 ), array( 'B', 1 ) ) );

		$this->assertStringNotContainsString( '%POLL_', WP_Polls_Display::display_pollvote( $poll_id ) );
		$this->assertStringNotContainsString( '%POLL_', WP_Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * Answer markup allowed by kses survives; scripts do not.
	 */
	public function test_answer_markup_is_filtered_not_stripped() {
		$poll_id = $this->make_poll(
			array(),
			array( array( 'vim <strong>btw</strong>', 1 ), array( 'bad <script>alert(1)</script>', 0 ) )
		);
		$html    = WP_Polls_Display::display_pollvote( $poll_id );

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
		// The stock template no longer uses %POLL_ANSWER_TEXT%: it labelled the
		// bar's tooltip, and the bar is decorative now. The variable is still
		// substituted for templates that ask for it, so the escaping is pinned
		// through one of those rather than dropped along with the tooltip.
		WP_Polls_Options::set( 'templates.resultbody', '<li title="%POLL_ANSWER_TEXT%">%POLL_ANSWER%</li>' );

		$poll_id = $this->make_poll( array(), array( array( 'Emacs & "friends"', 1 ) ) );
		$html    = WP_Polls_Display::display_pollresult( $poll_id );

		$this->assertStringContainsString( 'title="Emacs &amp; &quot;friends&quot;"', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * A poll that no longer exists renders the disabled template, not a warning.
	 */
	public function test_missing_poll_renders_the_disabled_template() {
		$expected = WP_Polls_Options::get( 'templates.disable' );

		$this->assertSame( $expected, WP_Polls_Display::display_pollvote( 999999 ) );
		$this->assertSame( $expected, WP_Polls_Display::display_pollresult( 999999 ) );
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
		WP_Polls_Display::display_pollvote( $poll_id );
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

		$this->assertStringContainsString( '<!--MARK-->', WP_Polls_Display::display_pollresult( $poll_id ) );
	}

	/**
	 * A multiple-answer poll renders checkboxes and its limit field.
	 */
	public function test_multiple_answer_poll_renders_checkboxes() {
		$poll_id = $this->make_poll( array( 'pollq_multiple' => 2 ), array( array( 'A', 0 ), array( 'B', 0 ) ) );
		$html    = WP_Polls_Display::display_pollvote( $poll_id );

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'poll_multiple_ans_' . $poll_id, $html );
	}

	// --- the result bar ---------------------------------------------------

	/**
	 * The bar is a track holding a fill, and is hidden from screen readers.
	 *
	 * The percentage and the vote count are already in the text beside it, so an
	 * ARIA-labelled bar would only make a screen reader read them twice.
	 */
	public function test_the_result_bar_is_a_track_and_a_fill() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 3 ), array( 'B', 1 ) ) );
		$html    = WP_Polls_Display::display_pollresult( $poll_id );

		$this->assertStringContainsString( '<div class="wp-polls-bar" aria-hidden="true">', $html );
		$this->assertStringContainsString( '<div class="wp-polls-bar-fill"', $html );
		$this->assertStringNotContainsString( 'class="pollbar"', $html );
	}

	/**
	 * An answer with every vote fills the bar completely.
	 *
	 * This was clamped to 99% because the border and margin on the old single
	 * div pushed a full width bar past its container.
	 */
	public function test_a_unanimous_answer_fills_the_bar() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 5 ), array( 'B', 0 ) ) );
		$html    = WP_Polls_Display::display_pollresult( $poll_id );

		$this->assertStringContainsString( 'width: 100%', $html );
		$this->assertStringNotContainsString( 'width: 99%', $html );
	}

	/**
	 * An answer with no votes renders an empty fill rather than a 1% sliver.
	 */
	public function test_an_unvoted_answer_has_an_empty_bar() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 5 ), array( 'B', 0 ) ) );
		$html    = WP_Polls_Display::display_pollresult( $poll_id );

		$this->assertStringContainsString( 'width: 0%', $html );
		$this->assertStringNotContainsString( 'width: 1%', $html );
	}

	/**
	 * Every bar matches the percentage printed beside it.
	 *
	 * With wp_polls_round_percentage on, the last answer's printed percentage
	 * gets a rounding buffer added so the column sums to 100. The bar width was
	 * computed before that happened and never saw the buffer, so the last answer
	 * printed one number and drew a different one.
	 */
	public function test_every_bar_matches_the_percentage_beside_it() {
		add_filter( 'wp_polls_round_percentage', '__return_true' );

		// Three answers at one vote each: 33 + 33 + 33 = 99, so the last is
		// buffered up to 34.
		$poll_id = $this->make_poll( array(), array( array( 'A', 1 ), array( 'B', 1 ), array( 'C', 1 ) ) );
		$html    = WP_Polls_Display::display_pollresult( $poll_id );

		preg_match_all( '/\((\d+)%/', $html, $printed );
		preg_match_all( '/width: (\d+)%/', $html, $drawn );

		$this->assertNotEmpty( $printed[1] );
		$this->assertSame( $printed[1], $drawn[1] );
		// Proves the buffer actually fired, so the comparison above is not vacuous.
		$this->assertContains( '34', $printed[1] );
	}

	/**
	 * %POLL_ANSWER_IMAGEWIDTH% is gone from the stock result templates.
	 *
	 * It is no longer substituted either, so a template still holding it would
	 * emit the literal token into a style attribute. The upgrade replaces both
	 * result templates outright, which is what makes that unreachable - this
	 * pins the half of that guarantee which lives in the defaults.
	 */
	public function test_the_stock_result_templates_use_the_percentage() {
		foreach ( array( 'resultbody', 'resultbody2' ) as $key ) {
			$template = WP_Polls_Template::get_default( $key );

			$this->assertStringNotContainsString( '%POLL_ANSWER_IMAGEWIDTH%', $template, $key );
			$this->assertStringContainsString( 'width: %POLL_ANSWER_PERCENTAGE%%', $template, $key );
		}
	}

	/**
	 * The archive reports the same percentages as the poll itself.
	 *
	 * The rounding buffer used to be unconditional in the archive while the poll
	 * only applied it when wp_polls_round_percentage was filtered on, so the two
	 * disagreed on every default install.
	 */
	public function test_the_archive_percentages_match_the_poll() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 1 ), array( 'B', 1 ), array( 'C', 1 ) ) );

		preg_match_all( '/\((\d+)%/', WP_Polls_Display::display_pollresult( $poll_id ), $poll );
		preg_match_all( '/\((\d+)%/', WP_Polls_Display::polls_archive(), $archive );

		$this->assertNotEmpty( $poll[1] );
		$this->assertSame( $poll[1], $archive[1] );
		// Unbuffered by default: three answers at one vote each stay at 33.
		$this->assertNotContains( '34', $archive[1] );
	}

	/**
	 * Turning the filter on buffers the archive as well as the poll.
	 */
	public function test_the_archive_buffers_when_the_filter_is_on() {
		add_filter( 'wp_polls_round_percentage', '__return_true' );

		$poll_id = $this->make_poll( array(), array( array( 'A', 1 ), array( 'B', 1 ), array( 'C', 1 ) ) );

		preg_match_all( '/\((\d+)%/', WP_Polls_Display::display_pollresult( $poll_id ), $poll );
		preg_match_all( '/\((\d+)%/', WP_Polls_Display::polls_archive(), $archive );

		$this->assertSame( $poll[1], $archive[1] );
		$this->assertContains( '34', $archive[1] );
	}

	/**
	 * The archive escapes %POLL_ANSWER_TEXT% once, like the poll does.
	 *
	 * 3.0.0 fixed the double encoding on the poll and missed the archive, which
	 * kept its own htmlspecialchars() call.
	 */
	public function test_the_archive_escapes_answer_text_once() {
		WP_Polls_Options::set( 'templates.resultbody', '<li title="%POLL_ANSWER_TEXT%">%POLL_ANSWER%</li>' );
		$this->make_poll( array(), array( array( 'Emacs & "friends"', 1 ) ) );

		$archive = WP_Polls_Display::polls_archive();

		$this->assertStringContainsString( 'title="Emacs &amp; &quot;friends&quot;"', $archive );
		$this->assertStringNotContainsString( '&amp;amp;', $archive );
	}

	/**
	 * The archive draws the same width as the poll itself.
	 *
	 * The two paths computed the bar width differently - the poll clamped 100 to
	 * 99, the archive scaled every width to 90% of the percentage - so the same
	 * answer drew a visibly shorter bar in the archive than on the poll.
	 */
	public function test_the_archive_bar_matches_the_poll_bar() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 5 ), array( 'B', 5 ) ) );

		$poll    = WP_Polls_Display::display_pollresult( $poll_id );
		$archive = WP_Polls_Display::polls_archive();

		$this->assertStringContainsString( 'width: 50%', $poll );
		$this->assertStringContainsString( 'width: 50%', $archive );
		$this->assertStringNotContainsString( 'width: 45%', $archive );
	}
}
