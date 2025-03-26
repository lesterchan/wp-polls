<?php
/**
 * Poll Manager Admin Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Manager Class
 *
 * Main admin controller class for poll management.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Manager {

	/**
	 * Initialize the admin functionality.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function init() {
		// Register actions for form processing.
		add_action( 'admin_init', array( __CLASS__, 'process_actions' ) );
	}

	/**
	 * Process admin actions.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_actions() {
		// Verify user permissions.
		if ( ! current_user_can( 'manage_polls' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-polls' ) );
		}

		// Process form submissions.
		if ( ! empty( $_POST['do'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['do'] ) );

			switch ( $action ) {
				case __( 'Edit Poll', 'wp-polls' ):
					if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp-polls_edit-poll' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_edit_poll();
					break;

				case __( 'Add Poll', 'wp-polls' ):
					if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp-polls_add-poll' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_add_poll();
					break;
			}
		}

		// Process GET actions.
		if ( ! empty( $_GET['action'] ) ) {
			$action = sanitize_key( $_GET['action'] );

			switch ( $action ) {
				case 'delete-poll':
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp-polls_delete-poll' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_delete_poll();
					break;

				case 'delete-poll-answer':
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp-polls_delete-poll-answer' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_delete_poll_answer();
					break;

				case 'delete-poll-logs':
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp-polls_delete-polls-logs' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_delete_poll_logs();
					break;

				case 'open-poll':
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp-polls_open-poll' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_open_poll();
					break;

				case 'close-poll':
					if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp-polls_close-poll' ) ) {
						wp_die( esc_html__( 'Security check failed.', 'wp-polls' ) );
					}
					self::process_close_poll();
					break;
			}
		}
	}

	/**
	 * Process edit poll form submission.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_edit_poll() {
		// Get form data.
		$poll_id = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
		
		if ( $poll_id <= 0 ) {
			wp_die( esc_html__( 'Invalid poll ID.', 'wp-polls' ) );
		}

		// Poll data.
		$poll_data = array();
		
		// Poll question.
		if ( isset( $_POST['pollq_question'] ) ) {
			$poll_data['question'] = wp_kses_post( trim( wp_unslash( $_POST['pollq_question'] ) ) );
		}
		
		// Poll total votes.
		if ( isset( $_POST['pollq_totalvotes'] ) ) {
			$poll_data['totalvotes'] = (int) $_POST['pollq_totalvotes'];
		}
		
		// Poll total voters.
		if ( isset( $_POST['pollq_totalvoters'] ) ) {
			$poll_data['totalvoters'] = (int) $_POST['pollq_totalvoters'];
		}
		
		// Poll active status.
		if ( isset( $_POST['pollq_active'] ) ) {
			$poll_data['active'] = (int) $_POST['pollq_active'];
		}

		// Poll timestamp.
		$poll_data['timestamp'] = isset( $_POST['poll_timestamp_old'] ) ? intval( $_POST['poll_timestamp_old'] ) : current_time( 'timestamp' );
		
		// Edit poll timestamp.
		$edit_timestamp = isset( $_POST['edit_polltimestamp'] ) && 1 === (int) $_POST['edit_polltimestamp'];
		
		if ( $edit_timestamp ) {
			$poll_data['timestamp'] = WP_Polls_Utility::create_timestamp_from_fields( $_POST, 'pollq_timestamp' );
			
			// Set future polls to inactive.
			if ( $poll_data['timestamp'] > current_time( 'timestamp' ) ) {
				$poll_data['active'] = -1;
			}
		}

		// Poll expiry.
		$poll_expiry_no = isset( $_POST['pollq_expiry_no'] ) && 1 === (int) $_POST['pollq_expiry_no'];
		
		if ( $poll_expiry_no ) {
			$poll_data['expiry'] = 0;
		} else {
			$poll_data['expiry'] = WP_Polls_Utility::create_timestamp_from_fields( $_POST, 'pollq_expiry' );
			
			// If poll expired, set it as inactive.
			if ( $poll_data['expiry'] <= current_time( 'timestamp' ) ) {
				$poll_data['active'] = 0;
			}
			
			// If poll starts in future but expires before it begins, set as inactive.
			if ( $edit_timestamp && $poll_data['expiry'] < $poll_data['timestamp'] ) {
				$poll_data['active'] = 0;
			}
		}

		// Poll multiple answers.
		$poll_multiple_yes = isset( $_POST['pollq_multiple_yes'] ) && 1 === (int) $_POST['pollq_multiple_yes'];
		
		if ( $poll_multiple_yes ) {
			$poll_data['multiple'] = isset( $_POST['pollq_multiple'] ) ? (int) $_POST['pollq_multiple'] : 0;
		} else {
			$poll_data['multiple'] = 0;
		}

		// Process poll answers.
		$poll_answers = array();
		
		// Get existing answer IDs.
		global $wpdb;
		$answer_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC",
				$poll_id
			)
		);
		
		// Update existing answers.
		if ( ! empty( $answer_ids ) ) {
			foreach ( $answer_ids as $answer_id ) {
				$answer_key = 'polla_aid-' . $answer_id;
				$votes_key = 'polla_votes-' . $answer_id;
				
				if ( isset( $_POST[ $answer_key ] ) ) {
					$answer_text = wp_kses_post( trim( wp_unslash( $_POST[ $answer_key ] ) ) );
					$answer_votes = isset( $_POST[ $votes_key ] ) ? (int) $_POST[ $votes_key ] : 0;
					
					$poll_answers[ $answer_id ] = array(
						'text' => $answer_text,
						'votes' => $answer_votes,
					);
				}
			}
		}
		
		// Add new answers.
		$poll_data['new_answers'] = array();
		
		if ( isset( $_POST['polla_answers_new'] ) && is_array( $_POST['polla_answers_new'] ) ) {
			$new_answers = array_map( 'wp_kses_post', array_map( 'trim', array_map( 'wp_unslash', $_POST['polla_answers_new'] ) ) );
			$new_votes = isset( $_POST['polla_answers_new_votes'] ) ? array_map( 'intval', $_POST['polla_answers_new_votes'] ) : array();
			
			foreach ( $new_answers as $index => $new_answer ) {
				if ( ! empty( $new_answer ) ) {
					$poll_data['new_answers'][] = array(
						'text' => $new_answer,
						'votes' => isset( $new_votes[ $index ] ) ? $new_votes[ $index ] : 0,
					);
				}
			}
		}
		
		// Set answers in poll data.
		$poll_data['answers'] = $poll_answers;
		
		// Update poll.
		$result = WP_Polls_Data::update_poll( $poll_id, $poll_data );
		
		// Set up status message.
		$message = $result
			? __( 'Poll updated successfully.', 'wp-polls' )
			: __( 'Error updating poll.', 'wp-polls' );
		
		// Redirect back to edit page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'polls-manager',
				'mode' => 'edit',
				'id' => $poll_id,
				'message' => $result ? 'updated' : 'error',
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process add poll form submission.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_add_poll() {
		// Get poll question.
		$poll_question = isset( $_POST['pollq_question'] ) ? wp_kses_post( trim( wp_unslash( $_POST['pollq_question'] ) ) ) : '';
		
		if ( empty( $poll_question ) ) {
			wp_die( esc_html__( 'Poll question is required.', 'wp-polls' ) );
		}
		
		// Get answers.
		$poll_answers = array();
		
		if ( isset( $_POST['polla_answers'] ) && is_array( $_POST['polla_answers'] ) ) {
			$answers = array_map( 'wp_kses_post', array_map( 'trim', array_map( 'wp_unslash', $_POST['polla_answers'] ) ) );
			
			foreach ( $answers as $answer ) {
				if ( ! empty( $answer ) ) {
					$poll_answers[] = $answer;
				}
			}
		}
		
		if ( empty( $poll_answers ) ) {
			wp_die( esc_html__( 'At least one poll answer is required.', 'wp-polls' ) );
		}
		
		// Poll timestamp.
		$poll_timestamp = current_time( 'timestamp' );
		$poll_timestamp_custom = isset( $_POST['custom_timestamp'] ) && 1 === (int) $_POST['custom_timestamp'];
		
		if ( $poll_timestamp_custom ) {
			$poll_timestamp = WP_Polls_Utility::create_timestamp_from_fields( $_POST, 'pollq_timestamp' );
		}
		
		// Poll active status.
		$poll_active = 1;
		
		if ( $poll_timestamp > current_time( 'timestamp' ) ) {
			$poll_active = -1; // Future poll.
		}
		
		// Poll expiry.
		$poll_expiry = 0;
		$poll_expiry_enable = isset( $_POST['enable_expiry'] ) && 1 === (int) $_POST['enable_expiry'];
		
		if ( $poll_expiry_enable ) {
			$poll_expiry = WP_Polls_Utility::create_timestamp_from_fields( $_POST, 'pollq_expiry' );
			
			// If poll expired, set it as inactive.
			if ( $poll_expiry <= current_time( 'timestamp' ) ) {
				$poll_active = 0;
			}
			
			// If poll starts in future but expires before it begins, set as inactive.
			if ( $poll_timestamp_custom && $poll_expiry < $poll_timestamp ) {
				$poll_active = 0;
			}
		}
		
		// Poll multiple answers.
		$poll_multiple = 0;
		$poll_multiple_yes = isset( $_POST['pollq_multiple_yes'] ) && 1 === (int) $_POST['pollq_multiple_yes'];
		
		if ( $poll_multiple_yes ) {
			$poll_multiple = isset( $_POST['pollq_multiple'] ) ? (int) $_POST['pollq_multiple'] : 0;
			
			// Make sure multiple doesn't exceed number of answers.
			$poll_multiple = min( $poll_multiple, count( $poll_answers ) );
		}
		
		// Create poll data.
		$poll_data = array(
			'question'  => $poll_question,
			'timestamp' => $poll_timestamp,
			'active'    => $poll_active,
			'expiry'    => $poll_expiry,
			'multiple'  => $poll_multiple,
			'answers'   => $poll_answers,
		);
		
		// Add poll.
		$poll_id = WP_Polls_Data::add_poll( $poll_data );
		
		if ( ! $poll_id ) {
			wp_die( esc_html__( 'Error adding poll.', 'wp-polls' ) );
		}
		
		// Redirect to poll edit page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'polls-manager',
				'mode' => 'edit',
				'id' => $poll_id,
				'message' => 'added',
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process delete poll action.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_delete_poll() {
		$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		
		if ( $poll_id > 0 ) {
			WP_Polls_Data::delete_poll( $poll_id );
		}
		
		// Redirect to polls page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'polls-manager',
				'message' => 'deleted',
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process delete poll answer action.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_delete_poll_answer() {
		$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$answer_id = isset( $_GET['aid'] ) ? (int) $_GET['aid'] : 0;
		
		if ( $poll_id > 0 && $answer_id > 0 ) {
			WP_Polls_Data::delete_poll_answer( $answer_id, $poll_id );
		}
		
		// Redirect to poll edit page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'polls-manager',
				'mode' => 'edit',
				'id' => $poll_id,
				'message' => 'answer-deleted',
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process delete poll logs action.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_delete_poll_logs() {
		WP_Polls_Data::delete_all_poll_logs();
		
		// Redirect to polls page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'polls-manager',
				'message' => 'logs-deleted',
			),
			admin_url( 'admin.php' )
		);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process open poll action.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_open_poll() {
		$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		
		if ( $poll_id > 0 ) {
			WP_Polls_Data::update_poll( $poll_id, array( 'active' => 1 ) );
		}
		
		// Redirect to polls page.
		$redirect_url = isset( $_GET['redirect'] ) && 'edit' === $_GET['redirect'] 
			? add_query_arg(
				array(
					'page' => 'polls-manager',
					'mode' => 'edit',
					'id' => $poll_id,
					'message' => 'opened',
				),
				admin_url( 'admin.php' )
			)
			: add_query_arg(
				array(
					'page' => 'polls-manager',
					'message' => 'opened',
				),
				admin_url( 'admin.php' )
			);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process close poll action.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function process_close_poll() {
		$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		
		if ( $poll_id > 0 ) {
			WP_Polls_Data::update_poll( $poll_id, array( 'active' => 0 ) );
		}
		
		// Redirect to polls page.
		$redirect_url = isset( $_GET['redirect'] ) && 'edit' === $_GET['redirect'] 
			? add_query_arg(
				array(
					'page' => 'polls-manager',
					'mode' => 'edit',
					'id' => $poll_id,
					'message' => 'closed',
				),
				admin_url( 'admin.php' )
			)
			: add_query_arg(
				array(
					'page' => 'polls-manager',
					'message' => 'closed',
				),
				admin_url( 'admin.php' )
			);
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the admin page.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function render() {
		$mode = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : '';
		
		switch ( $mode ) {
			case 'edit':
				require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/views/edit-poll.php';
				break;
				
			case 'add':
				require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/views/add-poll.php';
				break;
				
			case 'logs':
				require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/views/poll-logs.php';
				break;
				
			default:
				require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/views/polls-list.php';
				break;
		}
	}

	/**
	 * Display admin messages.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function display_messages() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}
		
		$message = sanitize_key( $_GET['message'] );
		$class = 'updated';
		$content = '';
		
		switch ( $message ) {
			case 'updated':
				$content = __( 'Poll updated successfully.', 'wp-polls' );
				break;
				
			case 'added':
				$content = __( 'Poll added successfully.', 'wp-polls' );
				break;
				
			case 'deleted':
				$content = __( 'Poll deleted successfully.', 'wp-polls' );
				break;
				
			case 'opened':
				$content = __( 'Poll opened successfully.', 'wp-polls' );
				break;
				
			case 'closed':
				$content = __( 'Poll closed successfully.', 'wp-polls' );
				break;
				
			case 'error':
				$class = 'error';
				$content = __( 'Error updating poll.', 'wp-polls' );
				break;
				
			case 'answer-deleted':
				$content = __( 'Poll answer deleted successfully.', 'wp-polls' );
				break;
				
			case 'logs-deleted':
				$content = __( 'Poll logs deleted successfully.', 'wp-polls' );
				break;
		}
		
		if ( ! empty( $content ) ) {
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $content ) . '</p></div>';
		}
	}
}
