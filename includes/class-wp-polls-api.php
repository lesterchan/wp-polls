<?php
/**
 * The REST API routes.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes reading a poll, reading its result and voting over the REST API.
 *
 * The namespace is the bare noun `polls/v1` rather than the plugin slug. A
 * `wp-` prefix is a wordpress.org directory convention for keeping one plugin's
 * download page apart from another's; it says nothing about what the plugin
 * should call the things it registers, and the ecosystem names those after the
 * brand rather than the directory entry. Another plugin can claim the same bare
 * noun and WordPress will not detect it; that is the accepted trade.
 *
 * **These routes are added beside `wp_ajax_polls`, not in place of it.** That
 * action stays registered and stays supported: a theme, a cached script or a
 * site's own JavaScript may be calling it, and those are in exactly the
 * position of a post containing a shortcode -- nobody here can survey them. The
 * two entry points share one implementation and neither is the other's caller.
 *
 * Voting is deliberately open to logged-out visitors, because that is what the
 * plugin is for. `permission_callback` therefore answers true and **eligibility
 * is enforced inside the callback**, by the same checks the AJAX path runs: the
 * per-poll nonce, `check_allowtovote()` for who may vote at all, and
 * `vote_poll_process()` for whether this visitor has already voted. A
 * permission callback that returned false for a guest would close the poll to
 * the people it exists to ask.
 */
class WP_Polls_API {

	/**
	 * The REST namespace every route is registered under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'polls/v1';

	/**
	 * Register the routes once the REST API is up.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Existence is deliberately *not* a validate_callback. A failed validator
		// is a 400, which is the right answer for a parameter whose domain is
		// fixed -- wp-sweep validates a sweep name that way, and a name outside
		// its list really is a malformed request. A poll id is not that: it is
		// well formed and names a resource that may have been deleted, which is
		// a 404. So each callback resolves the poll through poll_or_404().
		$id = array(
			'id' => array(
				'required' => true,
			),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'poll/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_poll' ),
				'permission_callback' => '__return_true',
				'args'                => $id,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'poll/(?P<id>\d+)/result',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_result' ),
				'permission_callback' => '__return_true',
				'args'                => $id,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'poll/(?P<id>\d+)/vote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'vote' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$id,
					array(
						'answers' => array(
							'required'    => true,
							'description' => __( 'Answer ids being voted for.', 'wp-polls' ),
						),
						'nonce'   => array(
							'required'    => true,
							'description' => __( 'The poll_<id>-nonce this poll was rendered with.', 'wp-polls' ),
						),
					)
				),
			)
		);
	}

	/**
	 * Resolve an id to a poll, or the error the response should carry.
	 *
	 * @param int $poll_id Poll being asked for.
	 * @return object|WP_Error The poll's question row, or a 404.
	 */
	private function poll_or_404( $poll_id ) {
		$poll = WP_Polls_Poll::get( $poll_id );

		if ( null === $poll ) {
			return new WP_Error(
				'wp_polls_no_such_poll',
				__( 'Invalid Poll ID', 'wp-polls' ),
				array( 'status' => 404 )
			);
		}

		return $poll;
	}

	/**
	 * Return a poll, its answers and the markup a visitor should see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_poll( $request ) {
		$poll_id = (int) $request['id'];
		$poll    = $this->poll_or_404( $poll_id );

		if ( is_wp_error( $poll ) ) {
			return $poll;
		}

		$voted = WP_Polls_Vote::check_voted( $poll_id );

		// A visitor who has already voted, or who is not allowed to, is sent the
		// result rather than a form they cannot submit -- the same decision the
		// shortcode makes when it renders the poll into a page.
		$show_result = ! empty( $voted ) || ! WP_Polls_Vote::check_allowtovote() || ! (int) $poll->pollq_active;

		$answers = array();

		foreach ( WP_Polls_Poll::answers( $poll_id ) as $answer ) {
			$answers[] = array(
				'id'    => (int) $answer->polla_aid,
				'text'  => removeslashes( $answer->polla_answers ),
				'votes' => (int) $answer->polla_votes,
			);
		}

		return rest_ensure_response(
			array(
				'id'           => $poll_id,
				'question'     => removeslashes( $poll->pollq_question ),
				'answers'      => $answers,
				'total_votes'  => (int) $poll->pollq_totalvotes,
				'total_voters' => (int) $poll->pollq_totalvoters,
				'multiple'     => (int) $poll->pollq_multiple,
				'is_open'      => (bool) (int) $poll->pollq_active,
				'has_voted'    => ! empty( $voted ),
				'can_vote'     => ! $show_result,
				// The markup is returned as well as the data because a poll's
				// templates are the site's to edit: a client that rebuilt the
				// markup from the data would silently ignore every one of them.
				'html'         => $show_result
					? WP_Polls_Display::display_pollresult( $poll_id, 0, false )
					: WP_Polls_Display::display_pollvote( $poll_id, false ),
			)
		);
	}

	/**
	 * Return a poll's result, whether or not this visitor has voted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_result( $request ) {
		$poll_id = (int) $request['id'];
		$poll    = $this->poll_or_404( $poll_id );

		if ( is_wp_error( $poll ) ) {
			return $poll;
		}

		return rest_ensure_response(
			array(
				'id'   => $poll_id,
				'html' => WP_Polls_Display::display_pollresult( $poll_id, 0, false ),
			)
		);
	}

	/**
	 * Record a vote and return the result markup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function vote( $request ) {
		$poll_id = (int) $request['id'];
		$poll    = $this->poll_or_404( $poll_id );

		if ( is_wp_error( $poll ) ) {
			return $poll;
		}

		// The same nonce the AJAX path checks, and the same one the rendered
		// voting form already carries. Weakening this because the caller is REST
		// rather than admin-ajax would be a security change dressed as a port.
		if ( ! wp_verify_nonce( (string) $request['nonce'], 'poll_' . $poll_id . '-nonce' ) ) {
			return new WP_Error(
				'wp_polls_bad_nonce',
				__( 'Failed To Verify Referrer', 'wp-polls' ),
				array( 'status' => 403 )
			);
		}

		try {
			$html = WP_Polls_Vote::vote_poll_process( $poll_id, $this->answer_ids( $request['answers'] ) );
		} catch ( Exception $e ) {
			// vote_poll_process() throws for every refusal a voter can cause --
			// closed poll, already voted, nothing selected, too many selected --
			// and its messages are already escaped plain text.
			return new WP_Error(
				'wp_polls_vote_refused',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'id'   => $poll_id,
				'html' => $html,
			)
		);
	}

	/**
	 * Normalise the answers parameter into a list of unique ids.
	 *
	 * Accepts an array or the comma separated string the AJAX path takes, so a
	 * client written against either shape works.
	 *
	 * @param mixed $answers Answers as submitted.
	 * @return array
	 */
	private function answer_ids( $answers ) {
		if ( ! is_array( $answers ) ) {
			$answers = explode( ',', (string) $answers );
		}

		return array_values( array_unique( array_map( 'intval', $answers ) ) );
	}
}
