<?php
/**
 * Voting: eligibility, recording and the rules that stop double voting.
 *
 * @package WP-Polls
 */

/**
 * Vote recording, duplicate detection and the AJAX endpoint.
 *
 * @covers WP_Polls_Vote
 */
class WP_Polls_Vote_Test extends WP_Polls_TestCase {

	/**
	 * Logging by IP so the tests exercise the database path rather than cookies.
	 */
	public function set_up() {
		parent::set_up();
		WP_Polls_Options::set( 'check_method', 2 );
		WP_Polls_Options::set( 'allow_to_vote', 0 );
		WP_Polls_Options::flush();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	/**
	 * A vote increments the answer, the poll total and the voter count.
	 */
	public function test_vote_is_recorded() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answers[0] ) ), 'The answer gains a vote.' );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ), 'The poll total goes up with it.' );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ), 'And the vote is logged.' );
	}

	/**
	 * A row reaches the log whatever the repeat-vote check is set to.
	 *
	 * Until 3.0.0 these were one setting: "Do Not Log" and "Logged By Cookie"
	 * wrote no row, so a site that only wanted a lighter check also lost its
	 * vote log, its Logs screen and its WP-Stats figures -- which is not what
	 * either choice sounds like.
	 */
	public function test_every_check_method_logs_the_vote() {
		global $wpdb;

		// Signed in, and everybody allowed to vote: "Check By Username" refuses a
		// guest outright, because a guest has no username to tell apart from the
		// next one. That is the setting working, not a case to route around.
		wp_set_current_user( self::factory()->user->create() );
		WP_Polls_Options::set( 'allow_to_vote', 2 );

		foreach ( array( 0, 1, 2, 3, 4 ) as $method ) {
			WP_Polls_Options::set( 'check_method', $method );
			WP_Polls_Options::flush();

			$poll_id = $this->make_poll();
			$answers = $this->answer_ids( $poll_id );

			WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

			$this->assertSame(
				1,
				(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ),
				'check method ' . $method . ' wrote no log row'
			);
		}
	}

	/**
	 * A site that would rather not keep the record can say so.
	 */
	public function test_the_log_filter_can_turn_the_row_off() {
		global $wpdb;

		add_filter( 'wp_polls_log_vote', '__return_false' );

		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		remove_filter( 'wp_polls_log_vote', '__return_false' );

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ),
			'the filter did not stop the log row'
		);

		// The vote itself still counts: the tally is a column on the poll, not
		// something read back out of the log.
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answers[0] ) ),
			'the vote itself should still count'
		);
	}

	/**
	 * The same voter cannot vote twice.
	 */
	public function test_second_vote_from_same_ip_is_refused() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->expectException( InvalidArgumentException::class );
		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[1] ) );
	}

	/**
	 * A refused second vote must not have counted.
	 */
	public function test_refused_vote_does_not_count() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
		try {
			WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[1] ) );
		} catch ( InvalidArgumentException $e ) {
			unset( $e );
		}

		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ), 'A refused vote leaves the log as it was.' );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answers[1] ) ), 'And adds nothing to the answer it named.' );
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
		WP_Polls_Vote::vote_poll_process( $poll_a, array( $b_answers[0] ) );
	}

	/**
	 * A closed poll cannot be voted on.
	 */
	public function test_closed_poll_is_refused() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );
		$answers = $this->answer_ids( $poll_id );

		$this->expectException( InvalidArgumentException::class );
		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
	}

	/**
	 * An empty answer list is refused.
	 */
	public function test_empty_answer_list_is_refused() {
		$poll_id = $this->make_poll();

		$this->expectException( InvalidArgumentException::class );
		WP_Polls_Vote::vote_poll_process( $poll_id, array() );
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

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0], $answers[1] ) );

		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ), 'Two answers are two log rows.' );
		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ), 'And two votes.' );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvoters FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ), 'But one voter, which is what the count is there to say.' );
	}

	/**
	 * The cap was enforced in the browser and nowhere else: js/wp-polls.js
	 * counts the ticked boxes, and pollq_multiple did not appear in
	 * class-wp-polls-vote.php at all. So one request could vote for every answer
	 * of a single-choice poll -- each polla_votes gaining one and
	 * pollq_totalvotes gaining N while pollq_totalvoters gained one, which puts
	 * the percentages and %POLL_MOST_ANSWER% permanently wrong.
	 */
	public function test_a_single_choice_poll_refuses_more_than_one_answer() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array( 'pollq_multiple' => 0 ),
			array( array( 'A', 0 ), array( 'B', 0 ), array( 'C', 0 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$this->expectException( InvalidArgumentException::class );

		try {
			WP_Polls_Vote::vote_poll_process( $poll_id, $answers );
		} finally {
			$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ), 'And nothing is recorded on the way out.' );
			$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ), 'Not even a log row.' );
		}
	}

	public function test_a_multiple_answer_poll_refuses_more_than_its_maximum() {
		$poll_id = $this->make_poll(
			array( 'pollq_multiple' => 2 ),
			array( array( 'A', 0 ), array( 'B', 0 ), array( 'C', 0 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$this->expectException( InvalidArgumentException::class );

		WP_Polls_Vote::vote_poll_process( $poll_id, $answers );
	}

	public function test_a_vote_within_the_maximum_is_still_accepted() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array( 'pollq_multiple' => 2 ),
			array( array( 'A', 0 ), array( 'B', 0 ), array( 'C', 0 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0], $answers[1] ) );

		$this->assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ), 'Voting up to the maximum works exactly as it did.' );
	}

	/**
	 * The stored IP is hashed, never the address itself.
	 */
	public function test_logged_ip_is_hashed() {
		global $wpdb;
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT pollip_ip FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) );
		$this->assertNotSame( '203.0.113.10', $stored, 'The address itself is not what is stored.' );
		$this->assertSame( wp_hash( '203.0.113.10' ), $stored, 'The hash of it is.' );
	}

	/**
	 * Reading the IP with REMOTE_ADDR absent must not warn.
	 *
	 * It is absent under WP-CLI and cron, which the display path still reaches.
	 */
	public function test_missing_remote_addr_does_not_warn() {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', WP_Polls_Vote::poll_get_raw_ipaddress(), 'A missing remote address yields nothing rather than a warning.' );
	}

	/**
	 * A non-IP in the trusted header must not reach gethostbyaddr().
	 *
	 * It no longer yields an empty hostname, because a junk header now falls back
	 * to REMOTE_ADDR instead of being used verbatim. What must stay true is that
	 * the junk itself never gets looked up.
	 */
	public function test_non_ip_header_never_reaches_the_resolver() {
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		WP_Polls_Options::flush();
		$_SERVER['REMOTE_ADDR']          = '203.0.113.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

		$this->assertStringNotContainsString( 'not-an-ip', WP_Polls_Vote::poll_get_hostname(), 'Something that is not an address never reaches the resolver.' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * With no usable address at all the hostname is empty.
	 */
	public function test_no_address_yields_empty_hostname() {
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		WP_Polls_Options::flush();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', WP_Polls_Vote::poll_get_hostname(), 'And with no address there is nothing to resolve.' );

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
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
		$plain                           = WP_Polls_Vote::poll_get_ipaddress();

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';
		$appended                        = WP_Polls_Vote::poll_get_ipaddress();

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1, 172.16.0.9';
		$appended_twice                  = WP_Polls_Vote::poll_get_ipaddress();

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( $plain, $appended, 'Appending a hop to the chain does not change who the voter is.' );
		$this->assertSame( $plain, $appended_twice, 'However many hops are appended.' );
	}

	/**
	 * The configured header wins, but only when it holds an address.
	 *
	 * @return void
	 */
	public function test_configured_header_takes_the_first_address() {
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 70.41.3.18';
		$this->assertSame( '203.0.113.7', WP_Polls_Vote::poll_get_raw_ipaddress(), 'The configured header is read, and only its first address.' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * A junk header falls back to REMOTE_ADDR rather than being used verbatim.
	 *
	 * @return void
	 */
	public function test_junk_header_falls_back_to_remote_addr() {
		WP_Polls_Options::set( 'ip_header', 'HTTP_X_FORWARDED_FOR' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.5';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, still-not-an-ip';
		$this->assertSame( '198.51.100.5', WP_Polls_Vote::poll_get_raw_ipaddress(), 'A junk header falls back to the remote address.' );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '<script>alert(1)</script>';
		$this->assertSame( '198.51.100.5', WP_Polls_Vote::poll_get_raw_ipaddress(), 'And an empty one falls back to it too.' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Proxy headers are ignored unless something opted in.
	 *
	 * @return void
	 */
	public function test_proxy_headers_are_ignored_by_default() {
		WP_Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR']          = '198.51.100.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		$this->assertSame( '198.51.100.5', WP_Polls_Vote::poll_get_raw_ipaddress(), 'A proxy header is ignored until the site opts in.' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * The trust filter opts in without naming a header.
	 *
	 * @return void
	 */
	public function test_trust_proxy_filter_opts_in() {
		WP_Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR']          = '198.51.100.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';

		add_filter( 'wp_polls_trust_proxy', '__return_true' );
		$ip = WP_Polls_Vote::poll_get_raw_ipaddress();
		remove_filter( 'wp_polls_trust_proxy', '__return_true' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '203.0.113.7', $ip, 'The filter is how it opts in.' );
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
		WP_Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

		$this->assertSame( '203.0.113.10', WP_Polls_Vote::poll_get_raw_ipaddress(), 'The remote address is used as it stands.' );
		$this->assertSame( wp_hash( '203.0.113.10' ), WP_Polls_Vote::poll_get_ipaddress(), 'And is hashed before it goes anywhere.' );
	}

	/**
	 * The constant opts in without a filter.
	 *
	 * Defining it in the shared run would change what every other IP test asserts,
	 * so this one gets its own process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_trust_proxy_constant_opts_in() {
		define( 'WP_POLLS_TRUST_PROXY', true );

		WP_Polls_Options::set( 'ip_header', '' );
		$_SERVER['REMOTE_ADDR']          = '198.51.100.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';

		$ip = WP_Polls_Vote::poll_get_raw_ipaddress();

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '203.0.113.7', $ip, 'The constant is another way to opt in.' );
	}

	/**
	 * A named header still wins over the constant.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_named_header_beats_the_constant() {
		define( 'WP_POLLS_TRUST_PROXY', true );

		WP_Polls_Options::set( 'ip_header', 'HTTP_CF_CONNECTING_IP' );
		$_SERVER['REMOTE_ADDR']           = '198.51.100.5';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.99';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.7';

		$ip = WP_Polls_Vote::poll_get_raw_ipaddress();

		unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '203.0.113.99', $ip, 'And a named header wins over it, so the more specific setting is what applies.' );
	}
}
