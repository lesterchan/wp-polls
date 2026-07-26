<?php
/**
 * Voting: eligibility, recording and the rules that stop double voting.
 *
 * @package WP-Polls
 */

/**
 * Vote recording, duplicate detection and the AJAX endpoint.
 *
 * @covers Polls_Vote
 */
class Test_Polls_Vote extends WP_Polls_TestCase {

	/**
	 * Logging by IP so the tests exercise the database path rather than cookies.
	 */
	public function set_up() {
		parent::set_up();
		Polls_Options::set( 'logging_method', 2 );
		Polls_Options::set( 'allow_to_vote', 0 );
		Polls_Options::flush();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	/**
	 * A vote increments the answer, the poll total and the voter count.
	 */
	public function test_vote_is_recorded() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answers[0] ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ) );
	}

	/**
	 * The same voter cannot vote twice.
	 */
	public function test_second_vote_from_same_ip_is_refused() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->expectException( InvalidArgumentException::class );
		Polls_Vote::vote_poll_process( $poll_id, array( $answers[1] ) );
	}

	/**
	 * A refused second vote must not have counted.
	 */
	public function test_refused_vote_does_not_count() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
		try {
			Polls_Vote::vote_poll_process( $poll_id, array( $answers[1] ) );
		} catch ( InvalidArgumentException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answers[1] ) ) );
	}

	/**
	 * An answer belonging to a different poll is rejected.
	 *
	 * Without this check a crafted request could inflate any answer in the
	 * database through any poll's endpoint.
	 */
	public function test_answer_from_another_poll_is_refused() {
		$poll_a    = $this->make_poll();
		$poll_b    = $this->make_poll();
		$b_answers = $this->answer_ids( $poll_b );

		$this->expectException( InvalidArgumentException::class );
		Polls_Vote::vote_poll_process( $poll_a, array( $b_answers[0] ) );
	}

	/**
	 * A closed poll cannot be voted on.
	 */
	public function test_closed_poll_is_refused() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );
		$answers = $this->answer_ids( $poll_id );

		$this->expectException( InvalidArgumentException::class );
		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
	}

	/**
	 * An empty answer list is refused.
	 */
	public function test_empty_answer_list_is_refused() {
		$poll_id = $this->make_poll();

		$this->expectException( InvalidArgumentException::class );
		Polls_Vote::vote_poll_process( $poll_id, array() );
	}

	/**
	 * A multiple-answer poll records one log row per answer but one voter.
	 */
	public function test_multiple_answer_poll_counts_voters_once() {
		global $wpdb;
		$poll_id = $this->make_poll(
			array( 'pollq_multiple' => 2 ),
			array( array( 'A', 0 ), array( 'B', 0 ), array( 'C', 0 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0], $answers[1] ) );

		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ) );
		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvoters FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ) );
	}

	/**
	 * The stored IP is hashed, never the address itself.
	 */
	public function test_logged_ip_is_hashed() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT pollip_ip FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) );
		$this->assertNotSame( '203.0.113.10', $stored );
		$this->assertSame( wp_hash( '203.0.113.10' ), $stored );
	}

	/**
	 * Reading the IP with REMOTE_ADDR absent must not warn.
	 *
	 * It is absent under WP-CLI and cron, which the display path still reaches.
	 */
	public function test_missing_remote_addr_does_not_warn() {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', Polls_Vote::poll_get_raw_ipaddress() );
	}

	/**
	 * A non-IP in the trusted header must not reach gethostbyaddr().
	 */
	public function test_non_ip_header_yields_empty_hostname() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		Polls_Options::flush();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

		$this->assertSame( '', Polls_Vote::poll_get_hostname() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}
}
