<?php
/**
 * The WP-CLI command.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists, opens, closes and deletes polls from the command line.
 *
 * Registered as `wp polls` rather than `wp wp-polls`. A `wp-` prefix is a
 * wordpress.org directory convention for keeping one plugin's download page
 * apart from another's, not a command-naming one, and after the `wp` binary it
 * only stutters; WP-CLI's own ecosystem names commands after the brand -- `wp
 * wc`, `wp yoast`, `wp jetpack`. WP-CLI gives a collision to whichever plugin
 * registered last and a bare noun is claimable by anyone; that is the accepted
 * trade for a word as distinctive as `polls`.
 *
 * Every subcommand goes through WP_Polls_Poll, which is the same class the
 * admin screen uses, so a poll deleted here loses its answers, its vote log and
 * its claim on the stored latest-poll id exactly as one deleted through the
 * browser does.
 */
class WP_Polls_Command extends WP_CLI_Command {

	/**
	 * List the polls on this site.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Which polls to list.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - open
	 *   - closed
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List every poll.
	 *     $ wp polls list
	 *
	 *     # List the polls still accepting votes.
	 *     $ wp polls list --status=open
	 *
	 *     # Close every poll, one at a time.
	 *     $ wp polls list --status=open --format=ids | xargs -n1 wp polls close
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		unset( $args );

		$status = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'any';
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$polls  = WP_Polls_Poll::all( $status );

		if ( empty( $polls ) ) {
			// An empty result is not an error, so this stays on the success
			// channel: `wp polls list --status=open` finding nothing is the
			// answer to a reasonable question.
			WP_CLI::success( __( 'No polls found.', 'wp-polls' ) );

			return;
		}

		$rows = array();

		foreach ( $polls as $poll ) {
			$rows[] = array(
				'id'       => (int) $poll->pollq_id,
				'question' => removeslashes( $poll->pollq_question ),
				'status'   => (int) $poll->pollq_active ? __( 'open', 'wp-polls' ) : __( 'closed', 'wp-polls' ),
				'votes'    => (int) $poll->pollq_totalvotes,
				'voters'   => (int) $poll->pollq_totalvoters,
			);
		}

		$this->print_rows( $format, $rows, array( 'id', 'question', 'status', 'votes', 'voters' ) );
	}

	/**
	 * Show one poll and how its answers are doing.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The poll to show.
	 *
	 * [--format=<format>]
	 * : Render the answers in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show poll 3 and its answers.
	 *     $ wp polls get 3
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$poll = $this->poll_or_die( $args[0] );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI::log( removeslashes( $poll->pollq_question ) );

		$rows = array();

		foreach ( WP_Polls_Poll::answers( $poll->pollq_id ) as $answer ) {
			$rows[] = array(
				'id'     => (int) $answer->polla_aid,
				'answer' => removeslashes( $answer->polla_answers ),
				'votes'  => (int) $answer->polla_votes,
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::warning( __( 'This poll has no answers, so nobody can vote in it.', 'wp-polls' ) );

			return;
		}

		$this->print_rows( $format, $rows, array( 'id', 'answer', 'votes' ) );
	}

	/**
	 * Open a poll for voting.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The poll to open.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp polls open 3
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command. Unused.
	 * @return void
	 */
	public function open( $args, $assoc_args ) {
		unset( $assoc_args );

		$poll = $this->poll_or_die( $args[0] );

		if ( (int) $poll->pollq_active ) {
			/* translators: %s: the poll's question. */
			WP_CLI::success( sprintf( __( "Poll '%s' was already open.", 'wp-polls' ), removeslashes( $poll->pollq_question ) ) );

			return;
		}

		WP_Polls_Poll::open( $poll->pollq_id );

		/* translators: %s: the poll's question. */
		WP_CLI::success( sprintf( __( "Poll '%s' is now open.", 'wp-polls' ), removeslashes( $poll->pollq_question ) ) );
	}

	/**
	 * Close a poll to further voting.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The poll to close.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp polls close 3
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command. Unused.
	 * @return void
	 */
	public function close( $args, $assoc_args ) {
		unset( $assoc_args );

		$poll = $this->poll_or_die( $args[0] );

		if ( ! (int) $poll->pollq_active ) {
			/* translators: %s: the poll's question. */
			WP_CLI::success( sprintf( __( "Poll '%s' was already closed.", 'wp-polls' ), removeslashes( $poll->pollq_question ) ) );

			return;
		}

		WP_Polls_Poll::close( $poll->pollq_id );

		/* translators: %s: the poll's question. */
		WP_CLI::success( sprintf( __( "Poll '%s' is now closed.", 'wp-polls' ), removeslashes( $poll->pollq_question ) ) );
	}

	/**
	 * Delete a poll, its answers and its vote log.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The poll to delete.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp polls delete 3
	 *
	 *     # In a script, where there is nobody to answer the prompt.
	 *     $ wp polls delete 3 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		$poll = $this->poll_or_die( $args[0] );

		$question = removeslashes( $poll->pollq_question );

		// Deleting takes the vote log with it and nothing here can put it back,
		// so this is the one subcommand that asks first.
		WP_CLI::confirm(
			/* translators: %s: the poll's question. */
			sprintf( __( "Delete poll '%s' and every vote cast in it?", 'wp-polls' ), $question ),
			$assoc_args
		);

		WP_Polls_Poll::delete( $poll->pollq_id );

		/* translators: %s: the poll's question. */
		WP_CLI::success( sprintf( __( "Poll '%s' deleted.", 'wp-polls' ), $question ) );
	}

	/**
	 * Resolve an id to a poll, or stop the command.
	 *
	 * @param string $poll_id Poll id as it arrived on the command line.
	 * @return object The poll's question row.
	 */
	private function poll_or_die( $poll_id ) {
		$poll = WP_Polls_Poll::get( $poll_id );

		if ( null === $poll ) {
			/* translators: %d: the poll id that was asked for. */
			WP_CLI::error( sprintf( __( 'No poll with id %d.', 'wp-polls' ), (int) $poll_id ) );
		}

		return $poll;
	}

	/**
	 * Print rows, reducing to the first column when ids were asked for.
	 *
	 * WP_CLI\Utils\format_items() hands `ids` straight to implode(), so a row
	 * that is an associative array comes out as the word `Array` and a PHP
	 * warning -- which is what `--format=ids` did here until it was run against
	 * a real WP-CLI rather than reasoned about. Reducing to the first column is
	 * what makes the documented `| xargs` pipes work.
	 *
	 * @param string $format Requested output format.
	 * @param array  $rows   Rows to print.
	 * @param array  $fields Columns, in order. The first is the id.
	 * @return void
	 */
	private function print_rows( $format, array $rows, array $fields ) {
		if ( 'ids' === $format ) {
			WP_CLI\Utils\format_items( $format, wp_list_pluck( $rows, $fields[0] ), $fields );

			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
