<?php
/**
 * Tests for the `polls/v1` REST routes.
 *
 * @package WP-Polls
 */

/**
 * The routes are open to logged-out visitors on purpose, which makes the checks
 * inside the callbacks the only thing between the poll and a scripted ballot
 * box. So the nonce, the eligibility rules and the repeat-vote guard are each
 * pinned here rather than assumed from the AJAX path they share.
 */
class WP_Polls_REST_API_Test extends WP_Polls_TestCase {

	/**
	 * Boots the REST server the way core's own REST tests do.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tears the REST server back down so it cannot leak into another test.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch a request against the routes under test.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace.
	 * @param array  $params Body or query parameters.
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, '/' . WP_Polls_API::REST_NAMESPACE . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The nonce the rendered voting form carries for a poll.
	 *
	 * @param int $poll_id Poll.
	 * @return string
	 */
	protected function poll_nonce( $poll_id ) {
		return wp_create_nonce( 'poll_' . $poll_id . '-nonce' );
	}

	// --- registration ----------------------------------------------------

	/**
	 * The routes register under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_namespace_is_the_bare_noun() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/polls/v1', $routes, 'The namespace is polls/v1.' );
		$this->assertArrayNotHasKey( '/wp-polls/v1', $routes, 'The plugin slug is not also claimed as a namespace.' );
		$this->assertSame( 'polls/v1', WP_Polls_API::REST_NAMESPACE, 'And the constant agrees with what was registered.' );
	}

	/**
	 * All three routes are registered.
	 *
	 * @return void
	 */
	public function test_every_route_is_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/polls/v1/poll/(?P<id>\d+)', $routes, 'Reading a poll is routed.' );
		$this->assertArrayHasKey( '/polls/v1/poll/(?P<id>\d+)/result', $routes, 'Reading its result is routed.' );
		$this->assertArrayHasKey( '/polls/v1/poll/(?P<id>\d+)/vote', $routes, 'And voting is routed.' );
	}

	// --- reading ---------------------------------------------------------

	/**
	 * Reading a poll returns its question, answers and totals.
	 *
	 * @return void
	 */
	public function test_reading_a_poll_returns_its_answers_and_totals() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Which one?' ),
			array( array( 'First', 3 ), array( 'Second', 4 ) )
		);

		$response = $this->request( 'GET', '/poll/' . $poll_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Reading a poll succeeds.' );
		$this->assertSame( 'Which one?', $data['question'], 'It carries the question.' );
		$this->assertCount( 2, $data['answers'], 'And both answers.' );
		$this->assertSame( 'First', $data['answers'][0]['text'], 'In the order the poll displays them.' );
		$this->assertSame( 3, $data['answers'][0]['votes'], 'With each answer\'s votes.' );
		$this->assertSame( 7, $data['total_votes'], 'And the poll\'s total.' );
	}

	/**
	 * Reading a poll returns markup, because the templates are the site's.
	 *
	 * @return void
	 */
	public function test_reading_a_poll_returns_rendered_markup() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Rendered poll' ) );

		$data = $this->request( 'GET', '/poll/' . $poll_id )->get_data();

		$this->assertStringContainsString( 'Rendered poll', $data['html'], 'The markup holds the question.' );
		$this->assertTrue( $data['can_vote'], 'A visitor who has not voted is offered the form.' );
	}

	/**
	 * A closed poll offers its result rather than a form.
	 *
	 * @return void
	 */
	public function test_a_closed_poll_is_returned_as_a_result() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );

		$data = $this->request( 'GET', '/poll/' . $poll_id )->get_data();

		$this->assertFalse( $data['is_open'], 'The poll reports itself closed.' );
		$this->assertFalse( $data['can_vote'], 'So nobody is offered a form for it.' );
	}

	/**
	 * An id that matches no poll is a 404, not an empty poll.
	 *
	 * @return void
	 */
	public function test_an_unknown_poll_is_rejected() {
		$response = $this->request( 'GET', '/poll/123456' );

		$this->assertSame( 404, $response->get_status(), 'An id matching no poll is refused by the validator.' );
	}

	// --- voting ----------------------------------------------------------

	/**
	 * A valid vote is recorded against the answer it names.
	 *
	 * @return void
	 */
	public function test_a_vote_is_recorded() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Vote poll' ) );
		$answers = $this->answer_ids( $poll_id );

		$response = $this->request(
			'POST',
			'/poll/' . $poll_id . '/vote',
			array(
				'answers' => array( $answers[0] ),
				'nonce'   => $this->poll_nonce( $poll_id ),
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The vote is accepted.' );

		$after = WP_Polls_Poll::answers( $poll_id );

		$this->assertSame( 1, (int) $after[0]->polla_votes, 'The answer voted for gained a vote.' );
		$this->assertSame( 0, (int) $after[1]->polla_votes, 'The one that was not stayed where it was.' );
		$this->assertSame( 1, (int) WP_Polls_Poll::get( $poll_id )->pollq_totalvotes, 'And the poll total moved with it.' );
	}

	/**
	 * The answers parameter is accepted as the comma string the AJAX path takes.
	 *
	 * @return void
	 */
	public function test_a_vote_accepts_the_comma_separated_form() {
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$this->request(
			'POST',
			'/poll/' . $poll_id . '/vote',
			array(
				'answers' => (string) $answers[0],
				'nonce'   => $this->poll_nonce( $poll_id ),
			)
		);

		$after = WP_Polls_Poll::answers( $poll_id );

		$this->assertSame( 1, (int) $after[0]->polla_votes, 'A client written against the AJAX shape still votes.' );
	}

	/**
	 * Without the poll's nonce the vote is refused and nothing is recorded.
	 *
	 * @return void
	 */
	public function test_a_vote_without_the_nonce_is_refused() {
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$response = $this->request(
			'POST',
			'/poll/' . $poll_id . '/vote',
			array(
				'answers' => array( $answers[0] ),
				'nonce'   => 'not-the-nonce',
			)
		);

		$this->assertSame( 403, $response->get_status(), 'A bad nonce is refused.' );
		$this->assertSame( 'wp_polls_bad_nonce', $response->get_data()['code'], 'And says why.' );
		$this->assertSame( 0, (int) WP_Polls_Poll::answers( $poll_id )[0]->polla_votes, 'No vote was recorded.' );
	}

	/**
	 * A nonce minted for one poll does not vote in another.
	 *
	 * @return void
	 */
	public function test_another_polls_nonce_does_not_vote_here() {
		$target = $this->make_poll();
		$other  = $this->make_poll();

		$response = $this->request(
			'POST',
			'/poll/' . $target . '/vote',
			array(
				'answers' => array( $this->answer_ids( $target )[0] ),
				'nonce'   => $this->poll_nonce( $other ),
			)
		);

		$this->assertSame( 403, $response->get_status(), 'The nonce is scoped to the poll it was minted for.' );
		$this->assertSame( 0, (int) WP_Polls_Poll::answers( $target )[0]->polla_votes, 'So nothing was recorded.' );
	}

	/**
	 * Voting twice is refused, and the second attempt changes nothing.
	 *
	 * @return void
	 */
	public function test_voting_twice_is_refused() {
		WP_Polls_Options::set( 'check_method', 2 );

		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		$vote = function () use ( $poll_id, $answers ) {
			return $this->request(
				'POST',
				'/poll/' . $poll_id . '/vote',
				array(
					'answers' => array( $answers[0] ),
					'nonce'   => $this->poll_nonce( $poll_id ),
				)
			);
		};

		$this->assertSame( 200, $vote()->get_status(), 'The first vote is accepted.' );

		$second = $vote();

		$this->assertSame( 403, $second->get_status(), 'The second is refused.' );
		$this->assertSame( 1, (int) WP_Polls_Poll::answers( $poll_id )[0]->polla_votes, 'And the count did not move again.' );
	}

	/**
	 * A vote naming no answer is refused.
	 *
	 * @return void
	 */
	public function test_a_vote_with_no_answer_is_refused() {
		$poll_id = $this->make_poll();

		$response = $this->request(
			'POST',
			'/poll/' . $poll_id . '/vote',
			array(
				'answers' => array(),
				'nonce'   => $this->poll_nonce( $poll_id ),
			)
		);

		$this->assertSame( 403, $response->get_status(), 'Voting for nothing is refused.' );
		$this->assertSame( 0, (int) WP_Polls_Poll::get( $poll_id )->pollq_totalvoters, 'And records no voter.' );
	}

	/**
	 * A closed poll cannot be voted in.
	 *
	 * @return void
	 */
	public function test_a_closed_poll_cannot_be_voted_in() {
		$poll_id = $this->make_poll( array( 'pollq_active' => 0 ) );
		$answers = $this->answer_ids( $poll_id );

		$response = $this->request(
			'POST',
			'/poll/' . $poll_id . '/vote',
			array(
				'answers' => array( $answers[0] ),
				'nonce'   => $this->poll_nonce( $poll_id ),
			)
		);

		$this->assertSame( 403, $response->get_status(), 'A closed poll refuses the vote.' );
		$this->assertSame( 0, (int) WP_Polls_Poll::answers( $poll_id )[0]->polla_votes, 'And records nothing.' );
	}

	// --- the result ------------------------------------------------------

	/**
	 * The result route renders the result without spending a vote.
	 *
	 * @return void
	 */
	public function test_the_result_route_does_not_record_a_vote() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Result poll' ),
			array( array( 'First', 2 ), array( 'Second', 1 ) )
		);

		$response = $this->request( 'GET', '/poll/' . $poll_id . '/result' );

		$this->assertSame( 200, $response->get_status(), 'The result is served.' );
		$this->assertStringContainsString( 'Result poll', $response->get_data()['html'], 'And holds the question.' );
		$this->assertSame( 3, (int) WP_Polls_Poll::get( $poll_id )->pollq_totalvotes, 'Looking at a result casts no vote.' );
	}

	// --- the AJAX endpoint it sits beside --------------------------------

	/**
	 * The AJAX action stays registered, because sites are still calling it.
	 *
	 * @return void
	 */
	public function test_the_ajax_endpoint_is_still_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_polls', array( 'WP_Polls_Vote', 'ajax_vote' ) ), 'The logged-in AJAX action survives the REST routes.' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_polls', array( 'WP_Polls_Vote', 'ajax_vote' ) ), 'And so does the logged-out one.' );
	}
}
