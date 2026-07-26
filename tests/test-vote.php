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
	 *
	 * It no longer yields an empty hostname, because a junk header now falls back
	 * to REMOTE_ADDR instead of being used verbatim. What must stay true is that
	 * the junk itself never gets looked up.
	 */
	public function test_non_ip_header_never_reaches_the_resolver() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		Polls_Options::flush();
		$_SERVER['REMOTE_ADDR']          = '203.0.113.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

		$this->assertStringNotContainsString( 'not-an-ip', Polls_Vote::poll_get_hostname() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * With no usable address at all the hostname is empty.
	 */
	public function test_no_address_yields_empty_hostname() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		Polls_Options::flush();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', Polls_Vote::poll_get_hostname() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}


	/**
	 * A forged extra hop must not change who the voter is.
	 *
	 * X-Forwarded-For is a chain the client controls the left of. Using the whole
	 * string as the identity let anyone vote again by appending one more hop.
	 *
	 * @return void
	 */
	public function test_appending_a_hop_does_not_change_identity() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
		$plain                           = Polls_Vote::poll_get_ipaddress();

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';
		$appended                        = Polls_Vote::poll_get_ipaddress();

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1, 172.16.0.9';
		$appended_twice                  = Polls_Vote::poll_get_ipaddress();

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( $plain, $appended );
		$this->assertSame( $plain, $appended_twice );
	}

	/**
	 * The configured header wins, but only when it holds an address.
	 *
	 * @return void
	 */
	public function test_configured_header_takes_the_first_address() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 70.41.3.18';
		$this->assertSame( '203.0.113.7', Polls_Vote::poll_get_raw_ipaddress() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * A junk header falls back to REMOTE_ADDR rather than being used verbatim.
	 *
	 * @return void
	 */
	public function test_junk_header_falls_back_to_remote_addr() {
		Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, still-not-an-ip';
		$this->assertSame( '198.51.100.5', Polls_Vote::poll_get_raw_ipaddress() );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '<script>alert(1)</script>';
		$this->assertSame( '198.51.100.5', Polls_Vote::poll_get_raw_ipaddress() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Proxy headers are ignored unless something opted in.
	 *
	 * @return void
	 */
	public function test_proxy_headers_are_ignored_by_default() {
		Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR']          = '198.51.100.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		$this->assertSame( '198.51.100.5', Polls_Vote::poll_get_raw_ipaddress() );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * The trust filter opts in without naming a header.
	 *
	 * @return void
	 */
	public function test_trust_proxy_filter_opts_in() {
		Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR']          = '198.51.100.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';

		add_filter( 'wp_polls_trust_proxy', '__return_true' );
		$ip = Polls_Vote::poll_get_raw_ipaddress();
		remove_filter( 'wp_polls_trust_proxy', '__return_true' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '203.0.113.7', $ip );
	}

	/**
	 * The default configuration hashes exactly what it always did.
	 *
	 * REMOTE_ADDR is already a bare address, so validating it must not change the
	 * value and orphan every pollsip row recorded before the upgrade.
	 *
	 * @return void
	 */
	public function test_remote_addr_identity_is_unchanged() {
		Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

		$this->assertSame( '203.0.113.10', Polls_Vote::poll_get_raw_ipaddress() );
		$this->assertSame( wp_hash( '203.0.113.10' ), Polls_Vote::poll_get_ipaddress() );
	}
}
