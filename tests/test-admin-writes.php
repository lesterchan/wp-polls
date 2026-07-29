<?php
/**
 * Tests for creating and editing polls through the admin screens.
 *
 * @package WP-Polls
 */

/**
 * The POST handlers in screen-add.php and screen-manage.php.
 *
 * The existing admin page tests render these screens; nothing exercised the
 * branch at the top of each file that writes to the database. Those handlers
 * are the ones that read a dozen superglobals apiece, and they are in the files
 * where every bulk-regex accident of this release landed.
 *
 * @covers WP_Polls_Admin
 */
class WP_Polls_Admin_Writes_Test extends WP_Polls_TestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->become_poll_admin();
	}

	/**
	 * A time broken into the six fields the date pickers post.
	 *
	 * @param string $prefix Field name prefix, pollq_timestamp or pollq_expiry.
	 * @param int    $offset Seconds from now.
	 * @return array
	 */
	private function date_fields( $prefix, $offset = 0 ) {
		$time = WP_Polls::now() + $offset;

		return array(
			$prefix . '_day'    => gmdate( 'j', $time ),
			$prefix . '_month'  => gmdate( 'n', $time ),
			$prefix . '_year'   => gmdate( 'Y', $time ),
			$prefix . '_hour'   => gmdate( 'G', $time ),
			$prefix . '_minute' => (int) gmdate( 'i', $time ),
			$prefix . '_second' => (int) gmdate( 's', $time ),
		);
	}

	/**
	 * The POST body for adding a poll.
	 *
	 * @param string $question Poll question.
	 * @param array  $answers  Answer texts.
	 * @param array  $extra    Field overrides.
	 * @return array
	 */
	private function add_poll_post( $question, $answers = array( 'Yes', 'No' ), $extra = array() ) {
		return array_merge(
			array(
				'do'              => 'Add Poll',
				'_wpnonce'        => wp_create_nonce( 'wp-polls_add-poll' ),
				'pollq_question'  => $question,
				'polla_answers'   => $answers,
				'pollq_expiry_no' => 1,
			),
			$this->date_fields( 'pollq_timestamp', -60 ),
			$extra
		);
	}

	/**
	 * The row for a poll question.
	 *
	 * @param string $question Poll question.
	 * @return object|null
	 */
	private function find_poll( $question ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->pollsq} WHERE pollq_question = %s", $question )
		);
	}

	/**
	 * The answer texts of a poll, in order.
	 *
	 * @param int $poll_id Poll ID.
	 * @return array
	 */
	private function answer_texts( $poll_id ) {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare( "SELECT polla_answers FROM {$wpdb->pollsa} WHERE polla_qid = %d ORDER BY polla_aid ASC", $poll_id )
		);
	}

	// --- adding -----------------------------------------------------------

	/**
	 * Adding a poll writes the question and its answers.
	 *
	 * @return void
	 */
	public function test_add_poll_inserts_question_and_answers() {
		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post( 'Which editor?', array( 'vim', 'emacs', 'nano' ) )
		);

		$poll = $this->find_poll( 'Which editor?' );

		$this->assertNotNull( $poll, 'the poll was not inserted' );
		$this->assertSame( array( 'vim', 'emacs', 'nano' ), $this->answer_texts( $poll->pollq_id ) );
		$this->assertSame( 0, (int) $poll->pollq_totalvotes );
	}

	/**
	 * An empty question inserts nothing.
	 *
	 * @return void
	 */
	public function test_add_poll_rejects_an_empty_question() {
		global $wpdb;

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post( '   ', array( 'Yes', 'No' ) )
		);

		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->pollsq}" ) );
	}

	/**
	 * Adding without a valid nonce is refused.
	 *
	 * @return void
	 */
	public function test_add_poll_requires_a_nonce() {
		global $wpdb;

		$post             = $this->add_poll_post( 'Unauthorised poll' );
		$post['_wpnonce'] = 'not-a-nonce';

		try {
			$this->render_admin_page( 'includes/screen-add.php', array(), $post );
			$this->fail( 'the request was not refused' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->pollsq}" ) );
	}

	/**
	 * Markup in a question is filtered rather than stored raw.
	 *
	 * @return void
	 */
	public function test_add_poll_filters_markup_in_the_question() {
		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post( 'Tags <em>ok</em> <script>alert(1)</script>' )
		);

		global $wpdb;
		$stored = $wpdb->get_var( "SELECT pollq_question FROM {$wpdb->pollsq} LIMIT 1" );

		$this->assertStringContainsString( '<em>ok</em>', $stored );
		$this->assertStringNotContainsString( '<script>', $stored );
	}

	/**
	 * A poll dated in the future is created inactive.
	 *
	 * @return void
	 */
	public function test_add_poll_in_the_future_is_not_active_yet() {
		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post( 'Future poll', array( 'Yes', 'No' ), $this->date_fields( 'pollq_timestamp', DAY_IN_SECONDS ) )
		);

		$poll = $this->find_poll( 'Future poll' );

		$this->assertNotNull( $poll );
		$this->assertSame( -1, (int) $poll->pollq_active );
	}

	/**
	 * A poll whose end date has passed is created closed.
	 *
	 * @return void
	 */
	public function test_add_poll_with_a_past_expiry_is_closed() {
		$post = $this->add_poll_post( 'Expired poll' );
		unset( $post['pollq_expiry_no'] );
		$post = array_merge( $post, $this->date_fields( 'pollq_expiry', -DAY_IN_SECONDS ) );

		$this->render_admin_page( 'includes/screen-add.php', array(), $post );

		$poll = $this->find_poll( 'Expired poll' );

		$this->assertNotNull( $poll );
		$this->assertSame( 0, (int) $poll->pollq_active );
	}

	/**
	 * The multiple answer limit is only stored when multiple answers are on.
	 *
	 * @return void
	 */
	public function test_add_poll_stores_the_multiple_answer_limit() {
		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post(
				'Multi poll',
				array( 'a', 'b', 'c' ),
				array(
					'pollq_multiple_yes' => 1,
					'pollq_multiple'     => 2,
				)
			)
		);
		$this->assertSame( 2, (int) $this->find_poll( 'Multi poll' )->pollq_multiple );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			$this->add_poll_post(
				'Single poll',
				array( 'a', 'b' ),
				array(
					'pollq_multiple_yes' => 0,
					'pollq_multiple'     => 2,
				)
			)
		);
		$this->assertSame( 0, (int) $this->find_poll( 'Single poll' )->pollq_multiple );
	}

	// --- editing ----------------------------------------------------------

	/**
	 * The POST body for editing a poll.
	 *
	 * @param int   $poll_id   Poll ID.
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function edit_poll_post( $poll_id, $overrides = array() ) {
		return array_merge(
			array(
				'do'                 => 'Edit Poll',
				'_wpnonce'           => wp_create_nonce( 'wp-polls_edit-poll' ),
				'pollq_id'           => $poll_id,
				'pollq_question'     => 'Edited question',
				'pollq_active'       => 1,
				'pollq_totalvotes'   => 0,
				'pollq_totalvoters'  => 0,
				'poll_timestamp_old' => 1000000000,
				'pollq_expiry_no'    => 1,
			),
			$overrides
		);
	}

	/**
	 * Editing rewrites the question.
	 *
	 * @return void
	 */
	public function test_edit_poll_updates_the_question() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Original question' ) );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			),
			$this->edit_poll_post( $poll_id )
		);

		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) );

		$this->assertSame( 'Edited question', $stored );
	}

	/**
	 * Editing rewrites the existing answers in place.
	 *
	 * @return void
	 */
	public function test_edit_poll_updates_existing_answers() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Answers poll' ),
			array( array( 'Old A', 1 ), array( 'Old B', 2 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			),
			$this->edit_poll_post(
				$poll_id,
				array(
					'polla_aid-' . $answers[0]   => 'New A',
					'polla_votes-' . $answers[0] => 1,
					'polla_aid-' . $answers[1]   => 'New B',
					'polla_votes-' . $answers[1] => 2,
				)
			)
		);

		$this->assertSame( array( 'New A', 'New B' ), $this->answer_texts( $poll_id ) );
	}

	/**
	 * Editing can append answers.
	 *
	 * @return void
	 */
	public function test_edit_poll_appends_new_answers() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Growing poll' ),
			array( array( 'A', 0 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			),
			$this->edit_poll_post(
				$poll_id,
				array(
					'polla_aid-' . $answers[0]   => 'A',
					'polla_votes-' . $answers[0] => 0,
					'polla_answers_new'          => array( 'B', 'C' ),
					'polla_answers_new_votes'    => array( 0, 0 ),
				)
			)
		);

		$this->assertSame( array( 'A', 'B', 'C' ), $this->answer_texts( $poll_id ) );
	}

	/**
	 * Editing without a valid nonce is refused and changes nothing.
	 *
	 * @return void
	 */
	public function test_edit_poll_requires_a_nonce() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Untouched question' ) );

		$post             = $this->edit_poll_post( $poll_id );
		$post['_wpnonce'] = 'not-a-nonce';

		try {
			$this->render_admin_page(
				'includes/screen-manage.php',
				array(
					'mode' => 'edit',
					'id'   => (string) $poll_id,
				),
				$post
			);
			$this->fail( 'the request was not refused' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) );

		$this->assertSame( 'Untouched question', $stored );
	}

	/**
	 * Markup in an edited question is filtered too.
	 *
	 * @return void
	 */
	public function test_edit_poll_filters_markup_in_the_question() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Before' ) );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			),
			$this->edit_poll_post( $poll_id, array( 'pollq_question' => 'After <script>alert(1)</script>' ) )
		);

		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) );

		$this->assertStringNotContainsString( '<script>', $stored );
	}
}
