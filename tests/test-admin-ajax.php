<?php
/**
 * Tests for the wp-admin AJAX endpoint.
 *
 * @package WP-Polls
 */

/**
 * WP_Polls_Admin::manage_poll, the wp_ajax_polls-admin handler.
 *
 * A privileged, destructive endpoint reachable by any logged in user: it
 * deletes polls, answers and logs, and opens and closes polls. Nothing checked
 * that it turns away a user without manage_polls, that each branch verifies its
 * own nonce, or that it deletes the right rows.
 *
 * @covers WP_Polls_Admin::manage_poll
 */
class WP_Polls_Admin_Ajax_Test extends WP_Polls_TestCase {

	/**
	 * Make the endpoint reachable from a test.
	 *
	 * The check_ajax_referer() helper calls a bare die( '-1' ) when wp_doing_ajax() is
	 * false, which no test can catch and which takes the runner down with it.
	 * Telling it this is an AJAX request routes the failure through wp_die()
	 * instead, and the AJAX die handler is then replaced with one that throws.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPDieException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);
	}

	/**
	 * Run the endpoint and return whatever it printed.
	 *
	 * The handler ends with wp_die(), which the test suite turns into an
	 * exception, so the output has to be captured around the unwind.
	 *
	 * @param array $post $_POST for the request.
	 * @return string
	 */
	private function call_endpoint( $post ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Building the request the endpoint will then verify.
		$_POST    = array_merge( array( 'action' => 'polls-admin' ), $post );
		$_REQUEST = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification

		$depth  = ob_get_level();
		$output = '';

		try {
			ob_start();
			WP_Polls_Admin::manage_poll();
		} catch ( WPDieException $e ) {
			unset( $e );
		} finally {
			while ( ob_get_level() > $depth ) {
				$output = ob_get_clean() . $output;
			}
			$_POST    = array();
			$_REQUEST = array();
		}

		return $output;
	}

	/**
	 * Count rows in one of the poll tables.
	 *
	 * @param string $table pollsq, pollsa or pollsip.
	 * @return int
	 */
	private function count_rows( $table ) {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->$table}" );
	}

	// --- authorisation ----------------------------------------------------

	/**
	 * A subscriber cannot delete a poll, even with a valid nonce.
	 *
	 * The nonces are only rendered on pages that already require the
	 * capability, but the endpoint has to check the capability itself rather
	 * than treat possession of a nonce as authorisation.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_capability_is_turned_away() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Protected poll' ) );

		// Become the subscriber *before* minting the nonce. Nonces are bound to
		// the user, so a nonce made as the administrator would be rejected here
		// by the nonce check and the test would pass without the capability
		// check existing at all -- which is exactly what it did at first.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$nonce = wp_create_nonce( 'wp-polls_delete-poll' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_polls is the plugin's own capability, added to the administrator role on activation.
		$this->assertFalse( current_user_can( WP_Polls_Admin::CAPABILITY ), 'The fixture user really does lack the capability, or the refusal below proves nothing.' );
		$this->assertSame( 1, wp_verify_nonce( $nonce, 'wp-polls_delete-poll' ), 'the nonce must be valid or this proves nothing' );

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => $nonce,
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsq' ), 'the poll was deleted' );
	}

	/**
	 * A logged out visitor cannot delete a poll.
	 *
	 * @return void
	 */
	public function test_a_logged_out_visitor_is_turned_away() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();

		wp_set_current_user( 0 );
		$nonce = wp_create_nonce( 'wp-polls_delete-poll' );

		$this->assertSame( 1, wp_verify_nonce( $nonce, 'wp-polls_delete-poll' ), 'the nonce must be valid or this proves nothing' );

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => $nonce,
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsq' ) );
	}

	/**
	 * Each branch verifies its own nonce.
	 *
	 * @return void
	 */
	public function test_a_bad_nonce_is_refused() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => 'not-a-nonce',
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsq' ) );
	}

	/**
	 * A nonce for a different action does not unlock this one.
	 *
	 * @return void
	 */
	public function test_a_nonce_for_another_action_is_refused() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_close-poll' ),
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsq' ) );
	}

	// --- deleting a poll --------------------------------------------------

	/**
	 * Deleting a poll takes its answers and its logs with it.
	 *
	 * @return void
	 */
	public function test_deleting_a_poll_removes_its_answers_and_logs() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Doomed poll' ),
			array( array( 'Yes', 1 ), array( 'No', 2 ) )
		);
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0] );

		$output = $this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-poll' ),
			)
		);

		$this->assertSame( 0, $this->count_rows( 'pollsq' ) );
		$this->assertSame( 0, $this->count_rows( 'pollsa' ) );
		$this->assertSame( 0, $this->count_rows( 'pollsip' ) );
		$this->assertStringContainsString( 'Doomed poll', $output );
	}

	/**
	 * Deleting one poll leaves the others alone.
	 *
	 * @return void
	 */
	public function test_deleting_a_poll_leaves_the_others() {
		$this->become_poll_admin();
		$doomed   = $this->make_poll( array( 'pollq_question' => 'Doomed' ) );
		$survivor = $this->make_poll( array( 'pollq_question' => 'Survivor' ) );

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $doomed,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-poll' ),
			)
		);

		global $wpdb;
		$left = $wpdb->get_col( "SELECT pollq_id FROM {$wpdb->pollsq}" );

		$this->assertSame( array( (string) $survivor ), $left );
	}

	/**
	 * Deleting a poll fires the documented action.
	 *
	 * @return void
	 */
	public function test_deleting_a_poll_fires_the_action() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();

		$seen = array();
		add_action(
			'wp_polls_delete_poll',
			function ( $id ) use ( &$seen ) {
				$seen[] = $id;
			}
		);

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-poll' ),
			)
		);

		$this->assertSame( array( $poll_id ), $seen );
	}

	// --- deleting an answer ----------------------------------------------

	/**
	 * Deleting an answer removes it, its logs, and its share of the total.
	 *
	 * @return void
	 */
	public function test_deleting_an_answer_adjusts_the_total() {
		global $wpdb;

		$this->become_poll_admin();
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Answer poll' ),
			array( array( 'Keep', 4 ), array( 'Drop', 6 ) )
		);
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[1] );

		$this->call_endpoint(
			array(
				'do'          => 'Delete Poll Answer',
				'pollq_id'    => $poll_id,
				'polla_aid'   => $answers[1],
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-poll-answer' ),
			)
		);

		$remaining = $wpdb->get_col( $wpdb->prepare( "SELECT polla_answers FROM {$wpdb->pollsa} WHERE polla_qid = %d", $poll_id ) );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) );

		$this->assertSame( array( 'Keep' ), $remaining );
		$this->assertSame( 4, $total, 'the deleted answer votes were not subtracted' );
		$this->assertSame( 0, $this->count_rows( 'pollsip' ) );
	}

	// --- opening and closing ---------------------------------------------

	/**
	 * Closing a poll clears its active flag.
	 *
	 * @return void
	 */
	public function test_closing_a_poll() {
		global $wpdb;

		$this->become_poll_admin();
		$poll_id = $this->make_poll( array( 'pollq_active' => 1 ) );

		$this->call_endpoint(
			array(
				'do'          => 'Close Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_close-poll' ),
			)
		);

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	/**
	 * Opening a poll sets it.
	 *
	 * @return void
	 */
	public function test_opening_a_poll() {
		global $wpdb;

		$this->become_poll_admin();
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );

		$this->call_endpoint(
			array(
				'do'          => 'Open Poll',
				'pollq_id'    => $poll_id,
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_open-poll' ),
			)
		);

		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	// --- deleting logs ----------------------------------------------------

	/**
	 * Deleting all logs empties the log table and nothing else.
	 *
	 * @return void
	 */
	public function test_deleting_all_logs() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0] );
		$this->make_vote_log( $poll_id, $answers[1] );

		$this->call_endpoint(
			array(
				'do'              => 'Delete All Logs',
				'delete_logs_yes' => 'yes',
				'_ajax_nonce'     => wp_create_nonce( 'wp-polls_delete-polls-logs' ),
			)
		);

		$this->assertSame( 0, $this->count_rows( 'pollsip' ) );
		$this->assertSame( 1, $this->count_rows( 'pollsq' ) );
	}

	/**
	 * Without the confirmation flag nothing is deleted.
	 *
	 * @return void
	 */
	public function test_deleting_all_logs_needs_the_confirmation() {
		$this->become_poll_admin();
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0] );

		$this->call_endpoint(
			array(
				'do'          => 'Delete All Logs',
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-polls-logs' ),
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsip' ) );
	}

	/**
	 * Deleting one poll's logs leaves the other poll's alone.
	 *
	 * @return void
	 */
	public function test_deleting_one_polls_logs_is_scoped() {
		$this->become_poll_admin();

		$first   = $this->make_poll( array( 'pollq_question' => 'First' ) );
		$second  = $this->make_poll( array( 'pollq_question' => 'Second' ) );
		$answers = $this->answer_ids( $first );
		$this->make_vote_log( $first, $answers[0] );
		$this->make_vote_log( $second, $this->answer_ids( $second )[0] );

		$this->call_endpoint(
			array(
				'do'              => 'Delete Logs For This Poll Only',
				'pollq_id'        => $first,
				'delete_logs_yes' => 'yes',
				'_ajax_nonce'     => wp_create_nonce( 'wp-polls_delete-poll-logs' ),
			)
		);

		global $wpdb;
		$left = $wpdb->get_col( "SELECT pollip_qid FROM {$wpdb->pollsip}" );

		$this->assertSame( array( (string) $second ), $left );
	}

	/**
	 * An unknown action does nothing at all.
	 *
	 * @return void
	 */
	public function test_an_unknown_action_does_nothing() {
		$this->become_poll_admin();
		$this->make_poll();

		$this->call_endpoint(
			array(
				'do'          => 'Something Else Entirely',
				'_ajax_nonce' => wp_create_nonce( 'wp-polls_delete-poll' ),
			)
		);

		$this->assertSame( 1, $this->count_rows( 'pollsq' ) );
	}
}
