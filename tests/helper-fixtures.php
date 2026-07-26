<?php
/**
 * Shared fixture helpers.
 *
 * The plugin owns three custom tables, so every test that touches polls needs
 * a deterministic set of rows. WP_UnitTestCase wraps each test in a
 * transaction and rolls back, but only for tables it knows about, so these
 * helpers clear and reseed explicitly.
 *
 * @package WP-Polls
 */

/**
 * Base class carrying the poll fixture.
 */
abstract class WP_Polls_TestCase extends WP_UnitTestCase {

	/**
	 * Empty the three poll tables and reset the option row.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->pollsq}" );
		$wpdb->query( "DELETE FROM {$wpdb->pollsa}" );
		$wpdb->query( "DELETE FROM {$wpdb->pollsip}" );

		delete_option( Polls_Options::OPTION );
		Polls_Options::flush();
		Polls_Options::save( Polls_Options::defaults() );
	}

	/**
	 * Insert a poll and its answers.
	 *
	 * @param array $args    Poll column overrides.
	 * @param array $answers List of [ answer text, votes ] pairs.
	 * @return int Poll ID.
	 */
	protected function make_poll( $args = array(), $answers = array( array( 'Yes', 0 ), array( 'No', 0 ) ) ) {
		global $wpdb;

		$args = array_merge(
			array(
				'pollq_question'    => 'Test question',
				'pollq_timestamp'   => 1000000000,
				'pollq_totalvotes'  => 0,
				'pollq_active'      => 1,
				'pollq_expiry'      => 0,
				'pollq_multiple'    => 0,
				'pollq_totalvoters' => 0,
			),
			$args
		);

		$wpdb->insert( $wpdb->pollsq, $args );
		$poll_id = (int) $wpdb->insert_id;

		$total = 0;
		foreach ( $answers as $answer ) {
			$wpdb->insert(
				$wpdb->pollsa,
				array(
					'polla_qid'     => $poll_id,
					'polla_answers' => $answer[0],
					'polla_votes'   => $answer[1],
				)
			);
			$total += $answer[1];
		}

		if ( $total > 0 && 0 === (int) $args['pollq_totalvotes'] ) {
			$wpdb->update(
				$wpdb->pollsq,
				array(
					'pollq_totalvotes'  => $total,
					'pollq_totalvoters' => $total,
				),
				array( 'pollq_id' => $poll_id )
			);
		}

		return $poll_id;
	}

	/**
	 * The answer IDs of a poll, in insertion order.
	 *
	 * @param int $poll_id Poll ID.
	 * @return array
	 */
	protected function answer_ids( $poll_id ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM {$wpdb->pollsa} WHERE polla_qid = %d ORDER BY polla_aid ASC", $poll_id ) )
		);
	}
}
