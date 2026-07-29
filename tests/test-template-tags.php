<?php
/**
 * The documented theme API and the shortcodes.
 *
 * These names are the plugin's public contract. A theme calling get_poll() or
 * guarding on function_exists( 'vote_poll' ) must keep working across the
 * restructure, so these tests are about the names existing as much as about
 * what they return.
 *
 * @package WP-Polls
 */

/**
 * The global template tags themes call directly.
 *
 * @covers ::get_poll
 */
class WP_Polls_Template_Tags_Test extends WP_Polls_TestCase {

	/**
	 * Every documented tag is callable as a global function.
	 */
	public function test_documented_template_tags_exist() {
		foreach ( array(
			'get_poll',
			'get_poll_question',
			'get_pollquestions',
			'get_pollanswers',
			'get_pollvotes',
			'get_pollvotes_by_id',
			'get_pollvoters',
			'get_polltime',
			'display_polls_archive_link',
			'in_pollarchive',
			'vote_poll',
			'removeslashes',
		) as $tag ) {
			$this->assertTrue( function_exists( $tag ), $tag . '() must remain a global function' );
		}
	}

	/**
	 * The counting tags return integers when asked not to echo.
	 */
	public function test_counting_tags_return_values() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 3 ), array( 'B', 2 ) ) );

		$this->assertSame( 1, get_pollquestions( false ) );
		$this->assertSame( 2, get_pollanswers( false ) );
		$this->assertSame( 5, get_pollvotes( false ) );
		$this->assertSame( 5, get_pollvotes_by_id( $poll_id, false ) );
	}

	/**
	 * The question comes back filtered from get_poll_question().
	 */
	public function test_get_poll_question() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Which <em>editor</em>?' ) );

		$this->assertSame( 'Which <em>editor</em>?', get_poll_question( $poll_id ) );
	}

	/**
	 * Calling get_poll() renders the poll it is given.
	 */
	public function test_get_poll_renders() {
		$poll_id = $this->make_poll();
		$html    = get_poll( $poll_id, false );

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', $html );
	}

	/**
	 * The [poll] shortcode renders, including the legacy [poll=N] form.
	 */
	public function test_poll_shortcode() {
		$poll_id = $this->make_poll();

		$this->assertStringContainsString( 'wp-polls', do_shortcode( '[poll id="' . $poll_id . '"]' ) );
		$this->assertStringContainsString( 'wp-polls', do_shortcode( '[poll=' . $poll_id . ']' ) );
	}

	/**
	 * A type="result" shortcode renders the results rather than the form.
	 */
	public function test_poll_shortcode_result_type() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 1 ) ) );
		$html    = do_shortcode( '[poll id="' . $poll_id . '" type="result"]' );

		$this->assertStringNotContainsString( 'data-poll-action="vote"', $html );
		$this->assertStringContainsString( 'wp-polls-bar', $html );
	}

	/**
	 * The archive shortcode renders.
	 */
	public function test_page_polls_shortcode() {
		$this->make_poll();

		$this->assertStringContainsString( 'wp-polls-archive', do_shortcode( '[page_polls]' ) );
	}
}
