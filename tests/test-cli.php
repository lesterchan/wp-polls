<?php
/**
 * Tests for the `wp polls` WP-CLI command.
 *
 * @package WP-Polls
 */

/**
 * The command deletes polls and closes them, with no browser and no nonce in
 * front of it, so every subcommand is pinned here.
 *
 * The WP_CLI facade these tests read is the stand-in from helper-wp-cli.php:
 * it records what the command reported instead of printing it, and its error()
 * throws, because the real one exits and every line after a call to it is
 * unreachable.
 */
class WP_Polls_CLI_Test extends WP_Polls_TestCase {

	/**
	 * Clears everything the stand-in recorded for the previous test.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes     = array();
		WP_CLI::$warnings      = array();
		WP_CLI::$logs          = array();
		WP_CLI::$confirmations = array();
		WP_CLI::$commands      = array();
		WP_CLI::$items         = array();
	}

	/**
	 * Runs one subcommand the way WP-CLI would.
	 *
	 * @param string $subcommand Method to call.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function run_command( $subcommand, $args = array(), $assoc_args = array() ) {
		$command = new WP_Polls_Command();
		$command->$subcommand( $args, $assoc_args );
	}

	/**
	 * The rows the last format_items() call was given.
	 *
	 * @return array
	 */
	protected function listed_rows() {
		$this->assertNotEmpty( WP_CLI::$items, 'The command formatted a table.' );

		$last = end( WP_CLI::$items );

		return $last['items'];
	}

	// --- registration ----------------------------------------------------

	/**
	 * The command registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_command_registers_as_polls() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		WP_Polls::register_command();

		$this->assertArrayHasKey( 'polls', WP_CLI::$commands, 'The command is registered as `wp polls`.' );
		$this->assertSame( 'WP_Polls_Command', WP_CLI::$commands['polls'], 'WP_Polls_Command is what handles it.' );
		$this->assertArrayNotHasKey( 'wp-polls', WP_CLI::$commands, 'The plugin slug is not also claimed as a command.' );
	}

	// --- list ------------------------------------------------------------

	/**
	 * Listing returns every poll, newest first.
	 *
	 * @return void
	 */
	public function test_list_returns_every_poll_newest_first() {
		$older = $this->make_poll( array( 'pollq_question' => 'Older' ) );
		$newer = $this->make_poll( array( 'pollq_question' => 'Newer' ) );

		$this->run_command( 'list_' );

		$rows = $this->listed_rows();

		$this->assertCount( 2, $rows, 'Both polls are listed.' );
		$this->assertSame( $newer, $rows[0]['id'], 'The newest poll is listed first.' );
		$this->assertSame( $older, $rows[1]['id'], 'The older poll follows it.' );
	}

	/**
	 * --status filters to the polls still taking votes.
	 *
	 * @return void
	 */
	public function test_list_status_open_excludes_closed_polls() {
		$open   = $this->make_poll( array( 'pollq_question' => 'Open one' ) );
		$closed = $this->make_poll(
			array(
				'pollq_question' => 'Closed one',
				'pollq_active'   => 0,
			)
		);

		$this->run_command( 'list_', array(), array( 'status' => 'open' ) );

		$rows = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $open, $rows, 'The open poll is listed.' );
		$this->assertNotContains( $closed, $rows, 'The closed poll is not.' );
	}

	/**
	 * --status=closed is the other half of the same filter.
	 *
	 * @return void
	 */
	public function test_list_status_closed_excludes_open_polls() {
		$open   = $this->make_poll( array( 'pollq_question' => 'Open one' ) );
		$closed = $this->make_poll(
			array(
				'pollq_question' => 'Closed one',
				'pollq_active'   => 0,
			)
		);

		$this->run_command( 'list_', array(), array( 'status' => 'closed' ) );

		$rows = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $closed, $rows, 'The closed poll is listed.' );
		$this->assertNotContains( $open, $rows, 'The open poll is not.' );
	}

	/**
	 * An empty site is reported as a success, not an error.
	 *
	 * @return void
	 */
	public function test_list_with_no_polls_is_not_an_error() {
		$this->run_command( 'list_' );

		$this->assertNotEmpty( WP_CLI::$successes, 'Finding nothing is reported on the success channel.' );
		$this->assertEmpty( WP_CLI::$items, 'No table is printed when there is nothing to put in it.' );
	}

	// --- get -------------------------------------------------------------

	/**
	 * Getting a poll prints its question and its answers with their votes.
	 *
	 * @return void
	 */
	public function test_get_prints_the_question_and_its_answers() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Which one?' ),
			array( array( 'First', 3 ), array( 'Second', 4 ) )
		);

		$this->run_command( 'get', array( $poll_id ) );

		$this->assertContains( 'Which one?', WP_CLI::$logs, 'The question is printed above the answers.' );

		$rows = $this->listed_rows();

		$this->assertCount( 2, $rows, 'Both answers are listed.' );
		$this->assertSame( 'First', $rows[0]['answer'], 'The answers keep the order the poll displays them in.' );
		$this->assertSame( 3, $rows[0]['votes'], 'Each answer carries its own vote count.' );
		$this->assertSame( 4, $rows[1]['votes'], 'And so does the second.' );
	}

	/**
	 * A poll nobody can vote in says so rather than printing an empty table.
	 *
	 * @return void
	 */
	public function test_get_warns_when_a_poll_has_no_answers() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Answerless' ), array() );

		$this->run_command( 'get', array( $poll_id ) );

		$this->assertNotEmpty( WP_CLI::$warnings, 'A poll with no answers is warned about.' );
		$this->assertEmpty( WP_CLI::$items, 'No empty table is printed for it.' );
	}

	/**
	 * An id that matches nothing stops the command.
	 *
	 * @return void
	 */
	public function test_get_errors_on_an_unknown_poll() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'get', array( 123456 ) );
	}

	// --- open and close --------------------------------------------------

	/**
	 * Closing a poll flips the stored flag.
	 *
	 * @return void
	 */
	public function test_close_closes_an_open_poll() {
		$poll_id = $this->make_poll();

		$this->run_command( 'close', array( $poll_id ) );

		$this->assertSame( 0, (int) WP_Polls_Poll::get( $poll_id )->pollq_active, 'The poll is closed afterwards.' );
		$this->assertNotEmpty( WP_CLI::$successes, 'And the command says so.' );
	}

	/**
	 * Opening a closed poll flips it back.
	 *
	 * @return void
	 */
	public function test_open_opens_a_closed_poll() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );

		$this->run_command( 'open', array( $poll_id ) );

		$this->assertSame( 1, (int) WP_Polls_Poll::get( $poll_id )->pollq_active, 'The poll is open afterwards.' );
	}

	/**
	 * Closing an already closed poll is reported, not treated as a failure.
	 *
	 * A script closing every poll it can find should not care that one of them
	 * was closed already, so this stays on the success channel and the row is
	 * left as it was.
	 *
	 * @return void
	 */
	public function test_closing_an_already_closed_poll_is_a_success() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );

		$this->run_command( 'close', array( $poll_id ) );

		$this->assertNotEmpty( WP_CLI::$successes, 'It reports success rather than an error.' );
		$this->assertSame( 0, (int) WP_Polls_Poll::get( $poll_id )->pollq_active, 'The poll is still closed.' );
	}

	/**
	 * Closing one poll leaves the others alone.
	 *
	 * @return void
	 */
	public function test_close_touches_only_the_poll_it_was_given() {
		$closed   = $this->make_poll( array( 'pollq_question' => 'Target' ) );
		$survivor = $this->make_poll( array( 'pollq_question' => 'Bystander' ) );

		$this->run_command( 'close', array( $closed ) );

		$this->assertSame( 1, (int) WP_Polls_Poll::get( $survivor )->pollq_active, 'The other poll is still open.' );
	}

	// --- delete ----------------------------------------------------------

	/**
	 * Deleting takes the poll, its answers and its vote log.
	 *
	 * @return void
	 */
	public function test_delete_removes_the_poll_its_answers_and_its_log() {
		global $wpdb;

		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0] );

		$this->run_command( 'delete', array( $poll_id ), array( 'yes' => true ) );

		$this->assertNull( WP_Polls_Poll::get( $poll_id ), 'The question row is gone.' );
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) ),
			'Its answers went with it.'
		);
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsip WHERE pollip_qid = %d", $poll_id ) ),
			'And so did its vote log.'
		);
	}

	/**
	 * Deleting fires the documented action once, with the poll that went.
	 *
	 * @return void
	 */
	public function test_delete_fires_the_documented_action() {
		$poll_id = $this->make_poll();

		$seen = array();
		add_action(
			'wp_polls_delete_poll',
			function ( $id ) use ( &$seen ) {
				$seen[] = $id;
			}
		);

		$this->run_command( 'delete', array( $poll_id ), array( 'yes' => true ) );

		$this->assertSame( array( $poll_id ), $seen, 'The action fires once, with the poll that was deleted.' );
	}

	/**
	 * Without --yes the command asks, and a script that cannot answer deletes
	 * nothing.
	 *
	 * @return void
	 */
	public function test_delete_without_yes_asks_first_and_deletes_nothing() {
		$poll_id = $this->make_poll();

		try {
			$this->run_command( 'delete', array( $poll_id ) );
			$this->fail( 'The command stops at the confirmation instead of deleting.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertNotEmpty( WP_CLI::$confirmations, 'It asked before doing anything.' );
		$this->assertNotNull( WP_Polls_Poll::get( $poll_id ), 'And the poll is still there.' );
	}

	/**
	 * Deleting one poll leaves the others where they were.
	 *
	 * @return void
	 */
	public function test_delete_touches_only_the_poll_it_was_given() {
		$doomed   = $this->make_poll( array( 'pollq_question' => 'Target' ) );
		$survivor = $this->make_poll( array( 'pollq_question' => 'Bystander' ) );

		$this->run_command( 'delete', array( $doomed ), array( 'yes' => true ) );

		$this->assertNull( WP_Polls_Poll::get( $doomed ), 'The named poll is gone.' );
		$this->assertNotNull( WP_Polls_Poll::get( $survivor ), 'The other one is not.' );
	}

	/**
	 * Deleting the newest poll moves the stored latest-poll id back.
	 *
	 * The widget and [poll id=-1] both resolve through that stored id, so a
	 * delete that left it pointing at the poll it had just removed would render
	 * nothing on the front end.
	 *
	 * @return void
	 */
	public function test_delete_recomputes_the_stored_latest_poll() {
		$older = $this->make_poll( array( 'pollq_question' => 'Older' ) );
		$newer = $this->make_poll( array( 'pollq_question' => 'Newer' ) );

		WP_Polls_Options::set( 'latest_poll', $newer );

		$this->run_command( 'delete', array( $newer ), array( 'yes' => true ) );

		$this->assertSame(
			$older,
			(int) WP_Polls_Options::get( 'latest_poll' ),
			'The stored latest poll falls back to the one still standing.'
		);
	}
}
