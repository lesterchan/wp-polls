<?php
/**
 * The poll operations, with no presentation attached to them.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and changes polls on behalf of every screen and endpoint that has one.
 *
 * The admin screen, the WP-CLI command and the REST route all want to open,
 * close, delete and read a poll, and before this class existed the admin was
 * the only one that could: its handler interleaved `$wpdb` calls with the
 * `<div class="notice">` markup announcing what they had done, so a second
 * caller could only copy the queries. Three copies of "delete a poll" is three
 * chances to forget that deleting one also clears its answers, its vote log and
 * the stored latest-poll id.
 *
 * So every method here returns data or a boolean and **prints nothing**. What
 * to say about the result belongs to the caller, because the three of them do
 * not agree: a notice div, a `WP_CLI::success()` line and a JSON body.
 */
class WP_Polls_Poll {

	/**
	 * Fetch a poll's question row.
	 *
	 * @param int $poll_id Poll to read.
	 * @return object|null The row, or null when no poll has that id.
	 */
	public static function get( $poll_id ) {
		global $wpdb;

		$poll = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $wpdb->pollsq WHERE pollq_id = %d", (int) $poll_id )
		);

		return $poll ? $poll : null;
	}

	/**
	 * Fetch a poll's answer rows, in the order the poll displays them.
	 *
	 * @param int $poll_id Poll to read the answers of.
	 * @return array Answer rows, empty when the poll has none or does not exist.
	 */
	public static function answers( $poll_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC", (int) $poll_id )
		);
	}

	/**
	 * Fetch every poll, newest first.
	 *
	 * @param string $status Which polls to return: 'open', 'closed' or 'any'.
	 * @return array Question rows.
	 */
	public static function all( $status = 'any' ) {
		global $wpdb;

		// Ordering by id rather than by pollq_timestamp is deliberate: the
		// timestamp column is a varchar holding a Unix time, so MySQL sorts it
		// as text and 9 sorts after 10000000000.
		if ( 'open' === $status ) {
			return $wpdb->get_results( "SELECT * FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_id DESC" );
		}

		if ( 'closed' === $status ) {
			return $wpdb->get_results( "SELECT * FROM $wpdb->pollsq WHERE pollq_active = 0 ORDER BY pollq_id DESC" );
		}

		return $wpdb->get_results( "SELECT * FROM $wpdb->pollsq ORDER BY pollq_id DESC" );
	}

	/**
	 * Whether a poll with this id exists.
	 *
	 * @param int $poll_id Poll to look for.
	 * @return bool
	 */
	public static function exists( $poll_id ) {
		return null !== self::get( $poll_id );
	}

	/**
	 * Open a poll for voting.
	 *
	 * @param int $poll_id Poll to open.
	 * @return bool Whether the row was changed.
	 */
	public static function open( $poll_id ) {
		return self::set_active( $poll_id, 1 );
	}

	/**
	 * Close a poll to further voting.
	 *
	 * @param int $poll_id Poll to close.
	 * @return bool Whether the row was changed.
	 */
	public static function close( $poll_id ) {
		return self::set_active( $poll_id, 0 );
	}

	/**
	 * Set a poll's open/closed flag.
	 *
	 * @param int $poll_id Poll to change.
	 * @param int $active  1 to open, 0 to close.
	 * @return bool Whether the row was changed.
	 */
	private static function set_active( $poll_id, $active ) {
		global $wpdb;

		// $wpdb->update() answers 0 both for "no such poll" and for "the poll
		// was already in this state", so a caller wanting to tell those apart
		// asks exists() first rather than reading a falsey return as absence.
		$updated = $wpdb->update(
			$wpdb->pollsq,
			array( 'pollq_active' => (int) $active ),
			array( 'pollq_id' => (int) $poll_id ),
			array( '%d' ),
			array( '%d' )
		);

		return (bool) $updated;
	}

	/**
	 * Delete a poll, its answers and its vote log.
	 *
	 * @param int $poll_id Poll to delete.
	 * @return bool Whether the question row was deleted.
	 */
	public static function delete( $poll_id ) {
		global $wpdb;

		$poll_id = (int) $poll_id;

		$deleted = $wpdb->delete( $wpdb->pollsq, array( 'pollq_id' => $poll_id ), array( '%d' ) );

		// The answers and the vote log go whether or not the question row was
		// there to delete. A poll whose question has already gone but whose
		// answers have not is the state this is most useful for clearing up.
		$wpdb->delete( $wpdb->pollsa, array( 'polla_qid' => $poll_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $poll_id ), array( '%d' ) );

		// The stored id is what the widget and [poll id=-1] resolve to, so it
		// has to be recomputed here rather than left pointing at a poll that no
		// longer exists.
		WP_Polls_Options::set( 'latest_poll', WP_Polls::polls_latest_id() );

		/**
		 * Fires after a poll and its answers and logs have been deleted.
		 *
		 * @since 2.70.0
		 *
		 * @param int $pollq_id Poll that was deleted.
		 */
		do_action( 'wp_polls_delete_poll', $poll_id );

		return (bool) $deleted;
	}
}
