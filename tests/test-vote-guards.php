<?php
/**
 * Tests for who may vote and who has already voted.
 *
 * @package WP-Polls
 */

/**
 * The vote de-duplication decision, across all five logging methods.
 *
 * This is what the plugin is actually for: everything else can be wrong and the
 * poll still works, but if these return the wrong answer a poll can be stuffed
 * or a legitimate voter locked out. The IP *derivation* was already covered;
 * nothing covered what gets *decided* with it.
 *
 * @covers WP_Polls_Vote::check_voted
 * @covers WP_Polls_Vote::check_allowtovote
 */
class WP_Polls_Vote_Guards_Test extends WP_Polls_TestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		WP_Polls_Options::set( 'ip_header', '' );
		WP_Polls_Options::set( 'cookie_expiry', 0 );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( 0 === strpos( $name, 'voted_' ) ) {
				unset( $_COOKIE[ $name ] );
			}
		}
		parent::tear_down();
	}

	/**
	 * Record a vote from the current test IP.
	 *
	 * @param int $poll_id   Poll ID.
	 * @param int $answer_id Answer ID.
	 * @param int $user_id   Voter user ID.
	 * @return void
	 */
	private function log_vote_from_current_ip( $poll_id, $answer_id, $user_id = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->pollsip,
			array(
				'pollip_qid'       => $poll_id,
				'pollip_aid'       => $answer_id,
				'pollip_ip'        => WP_Polls_Vote::poll_get_ipaddress(),
				'pollip_host'      => 'localhost',
				'pollip_timestamp' => WP_Polls::now(),
				'pollip_user'      => 'Guest',
				'pollip_userid'    => $user_id,
			)
		);
	}

	// --- check_allowtovote() ---------------------------------------------

	/**
	 * "Guests only" turns registered users away.
	 *
	 * @return void
	 */
	public function test_guests_only_rejects_logged_in_users() {
		WP_Polls_Options::set( 'allow_to_vote', 0 );

		wp_set_current_user( 0 );
		$this->assertTrue( WP_Polls_Vote::check_allowtovote(), 'With guests only, a logged out visitor may vote.' );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertFalse( WP_Polls_Vote::check_allowtovote(), 'With guests only, a logged in user may not.' );
	}

	/**
	 * "Registered users only" turns guests away.
	 *
	 * @return void
	 */
	public function test_registered_only_rejects_guests() {
		WP_Polls_Options::set( 'allow_to_vote', 1 );

		wp_set_current_user( 0 );
		$this->assertFalse( WP_Polls_Vote::check_allowtovote(), 'With registered only, a guest may not vote.' );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertTrue( WP_Polls_Vote::check_allowtovote(), 'With registered only, a logged in user may.' );
	}

	/**
	 * "Everyone" lets both through.
	 *
	 * @return void
	 */
	public function test_everyone_may_vote() {
		WP_Polls_Options::set( 'allow_to_vote', 2 );

		wp_set_current_user( 0 );
		$this->assertTrue( WP_Polls_Vote::check_allowtovote(), 'With everyone allowed, a guest may vote.' );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertTrue( WP_Polls_Vote::check_allowtovote(), 'With everyone allowed, a logged in user may vote too.' );
	}

	// --- logging method 0: do not log ------------------------------------

	/**
	 * With logging off nobody is ever considered to have voted.
	 *
	 * @return void
	 */
	public function test_logging_off_never_reports_a_previous_vote() {
		WP_Polls_Options::set( 'check_method', 0 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->log_vote_from_current_ip( $poll_id, $answers[0] );
		$_COOKIE[ 'voted_' . $poll_id ] = (string) $answers[0];

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'With logging off there is never a previous vote to find.' );
	}

	// --- logging method 1: cookie ----------------------------------------

	/**
	 * The cookie names the answers already chosen.
	 *
	 * @return void
	 */
	public function test_cookie_logging_reads_the_cookie() {
		WP_Polls_Options::set( 'check_method', 1 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'With no cookie there is no previous vote.' );

		$_COOKIE[ 'voted_' . $poll_id ] = $answers[0] . ',' . $answers[1];

		$this->assertSame( array( $answers[0], $answers[1] ), WP_Polls_Vote::check_voted( $poll_id ), 'And with one, the answers it holds are what comes back.' );
	}

	/**
	 * A cookie for a different poll does not count.
	 *
	 * @return void
	 */
	public function test_cookie_logging_is_per_poll() {
		WP_Polls_Options::set( 'check_method', 1 );
		$first  = $this->make_poll();
		$second = $this->make_poll();

		$_COOKIE[ 'voted_' . $first ] = '1';

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $second ), 'A cookie for one poll says nothing about another.' );
	}

	/**
	 * A forged cookie cannot smuggle anything but integers through.
	 *
	 * @return void
	 */
	public function test_cookie_values_are_cast_to_integers() {
		WP_Polls_Options::set( 'check_method', 1 );
		$poll_id = $this->make_poll();

		$_COOKIE[ 'voted_' . $poll_id ] = '3,<script>alert(1)</script>,7';

		$this->assertSame( array( 3, 0, 7 ), WP_Polls_Vote::check_voted( $poll_id ), 'Cookie values are cast, so nothing from a cookie reaches a query as a string.' );
	}

	// --- logging method 2: IP --------------------------------------------

	/**
	 * A logged vote from the same address is found.
	 *
	 * @return void
	 */
	public function test_ip_logging_finds_a_previous_vote() {
		WP_Polls_Options::set( 'check_method', 2 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'With no logged vote there is no previous vote.' );

		$this->log_vote_from_current_ip( $poll_id, $answers[0] );

		$this->assertSame( array( (string) $answers[0] ), WP_Polls_Vote::check_voted( $poll_id ), 'And with one, the answer it holds is what comes back.' );
	}

	/**
	 * A vote from a different address does not count.
	 *
	 * @return void
	 */
	public function test_ip_logging_is_per_address() {
		WP_Polls_Options::set( 'check_method', 2 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->log_vote_from_current_ip( $poll_id, $answers[0] );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.77';

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'A vote from one address says nothing about another.' );
	}

	/**
	 * Once the log expiry has passed the vote no longer blocks.
	 *
	 * @return void
	 */
	public function test_ip_logging_respects_the_expiry() {
		global $wpdb;

		WP_Polls_Options::set( 'check_method', 2 );
		WP_Polls_Options::set( 'cookie_expiry', 3600 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->log_vote_from_current_ip( $poll_id, $answers[0] );
		$this->assertNotSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'Inside the expiry the vote is still found.' );

		// Push the logged vote back beyond the window.
		$wpdb->update(
			$wpdb->pollsip,
			array( 'pollip_timestamp' => WP_Polls::now() - 7200 ),
			array( 'pollip_qid' => $poll_id )
		);

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'And past it there is nothing to find.' );
	}

	// --- logging method 3: cookie then IP --------------------------------

	/**
	 * The cookie is consulted first and short circuits the query.
	 *
	 * @return void
	 */
	public function test_cookie_and_ip_prefers_the_cookie() {
		WP_Polls_Options::set( 'check_method', 3 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$_COOKIE[ 'voted_' . $poll_id ] = (string) $answers[1];
		$this->log_vote_from_current_ip( $poll_id, $answers[0] );

		$this->assertSame( array( $answers[1] ), WP_Polls_Vote::check_voted( $poll_id ), 'With both, the cookie is what answers.' );
	}

	/**
	 * With no cookie it falls through to the address.
	 *
	 * @return void
	 */
	public function test_cookie_and_ip_falls_back_to_the_address() {
		WP_Polls_Options::set( 'check_method', 3 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->log_vote_from_current_ip( $poll_id, $answers[0] );

		$this->assertSame( array( (string) $answers[0] ), WP_Polls_Vote::check_voted( $poll_id ), 'And with no cookie it falls back to the address.' );
	}

	/**
	 * Deleting the cookie is not enough to vote again under method 3.
	 *
	 * This is the whole point of combining the two: the visitor controls the
	 * cookie, so the address has to be checked as well.
	 *
	 * @return void
	 */
	public function test_clearing_the_cookie_does_not_allow_another_vote() {
		WP_Polls_Options::set( 'check_method', 3 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$_COOKIE[ 'voted_' . $poll_id ] = (string) $answers[0];
		$this->log_vote_from_current_ip( $poll_id, $answers[0] );

		unset( $_COOKIE[ 'voted_' . $poll_id ] );

		$this->assertNotSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'Clearing the cookie still leaves the logged vote, so it is not a way round the guard.' );
	}

	// --- logging method 4: username --------------------------------------

	/**
	 * Guests are always blocked when logging by username.
	 *
	 * @return void
	 */
	public function test_username_logging_blocks_guests_outright() {
		WP_Polls_Options::set( 'check_method', 4 );
		$poll_id = $this->make_poll();

		wp_set_current_user( 0 );

		$this->assertSame( 1, WP_Polls_Vote::check_voted( $poll_id ), 'Logging by username blocks a guest outright rather than letting them through.' );
	}

	/**
	 * A logged in user who has not voted is allowed.
	 *
	 * @return void
	 */
	public function test_username_logging_allows_a_new_user() {
		global $user_ID;

		WP_Polls_Options::set( 'check_method', 4 );
		$poll_id = $this->make_poll();

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$user_ID = $user_id; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.WP.GlobalVariablesOverride.Prohibited -- The function reads this global.

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'A user who has not voted has no previous vote.' );
	}

	/**
	 * The same user is found again, from any address.
	 *
	 * @return void
	 */
	public function test_username_logging_finds_the_user_from_a_new_address() {
		global $user_ID;

		WP_Polls_Options::set( 'check_method', 4 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$user_ID = $user_id; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.WP.GlobalVariablesOverride.Prohibited -- The function reads this global.

		$this->log_vote_from_current_ip( $poll_id, $answers[0], $user_id );

		// Somewhere else entirely; the user is what is being matched.
		$_SERVER['REMOTE_ADDR'] = '198.51.100.77';

		$this->assertSame( array( (string) $answers[0] ), WP_Polls_Vote::check_voted( $poll_id ), 'The same user is found from a new address, because the user is what is matched.' );
	}

	/**
	 * A different user is not blocked by somebody else's vote.
	 *
	 * @return void
	 */
	public function test_username_logging_is_per_user() {
		global $user_ID;

		WP_Polls_Options::set( 'check_method', 4 );
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$voter = self::factory()->user->create();
		$this->log_vote_from_current_ip( $poll_id, $answers[0], $voter );

		$other = self::factory()->user->create();
		wp_set_current_user( $other );
		$user_ID = $other; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.WP.GlobalVariablesOverride.Prohibited -- The function reads this global.

		$this->assertSame( 0, WP_Polls_Vote::check_voted( $poll_id ), 'And a different user is not blocked by a vote somebody else cast.' );
	}
}
