<?php
/**
 * WP-Polls AJAX Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process poll vote via AJAX
 */
function vote_poll_process( $poll_id, $poll_aid_array = [] ) {
	global $wpdb, $user_identity, $user_ID;

	do_action( 'wp_polls_vote_poll' );

	// Acquire lock.
	$fp_lock = polls_acquire_lock( $poll_id );
	if ( $fp_lock === false ) {
		throw new InvalidArgumentException( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls'), $poll_id ) );
	}

	// Check if this is a ranked choice poll
	$is_ranked_poll = isset( $_POST['ranked_poll'] ) && sanitize_key( $_POST['ranked_poll'] ) === '1';
	$ranked_order = isset( $_POST['ranked_order'] ) ? sanitize_text_field( $_POST['ranked_order'] ) : '';
	$poll_type = '';
	
	if ( $is_ranked_poll && ! empty( $ranked_order ) ) {
		// Get the poll type to confirm it's actually a ranked choice poll
		$poll_type = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_type FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		
		// If it's truly a ranked poll, use the ranked order data
		if ( 'ranked' === $poll_type ) {
			// For ranked polls, the order matters, so we use the ranked_order parameter
			$poll_aid_array = array_map( 'intval', explode( ',', $ranked_order ) );
		}
	}

	$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
	$is_real = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

	if( !$is_real ) {
		throw new InvalidArgumentException(sprintf(__('Invalid Answer to Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (!check_allowtovote()) {
		throw new InvalidArgumentException(sprintf(__('User is not allowed to vote for Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (empty($poll_aid_array)) {
		throw new InvalidArgumentException(sprintf(__('No answers given for Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if($poll_id === 0) {
		throw new InvalidArgumentException(sprintf(__('Invalid Poll ID. Poll ID #%s', 'wp-polls'), $poll_id));
	}

	$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

	if ($is_poll_open === 0) {
		throw new InvalidArgumentException(sprintf(__( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ));
	}

	$check_voted = check_voted($poll_id);
	if ( !empty( $check_voted ) ) {
		throw new InvalidArgumentException(sprintf(__('You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (!empty($user_identity)) {
		$pollip_user = $user_identity;
	} elseif ( ! empty( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
		$pollip_user = $_COOKIE['comment_author_' . COOKIEHASH];
	} else {
		$pollip_user = __('Guest', 'wp-polls');
	}

	$pollip_user = sanitize_text_field( $pollip_user );
	$pollip_userid = $user_ID;
	$pollip_ip = poll_get_ipaddress();
	$pollip_host = poll_get_hostname();
	$pollip_timestamp = current_time('timestamp');
	$poll_logging_method = (int) get_option('poll_logging_method');

	// Only Create Cookie If User Choose Logging Method 1 Or 3
	if ( $poll_logging_method === 1 || $poll_logging_method === 3 ) {
		$cookie_expiry = (int) get_option('poll_cookielog_expiry');
		if ($cookie_expiry === 0) {
			$cookie_expiry = YEAR_IN_SECONDS;
		}
		setcookie( 'voted_' . $poll_id, implode(',', $poll_aid_array ), $pollip_timestamp + $cookie_expiry, apply_filters( 'wp_polls_cookiepath', SITECOOKIEPATH ) );
	}

	// For ranked polls, handle votes based on rank position
	if ( 'ranked' === $poll_type ) {
		// First, record each answer with its rank position
		// We'll use a weighted voting system where higher ranks get more "points"
		$total_answers = count( $poll_aid_array );
		$rank = 1; // Start with rank 1 (highest)
		
		foreach ( $poll_aid_array as $polla_aid ) {
			// For ranked choice polls, we record each vote with its rank
			// We'll use pollip_aid field to store the answer ID
			// and create a custom field or entry format to store the rank
			
			// Calculate the weight/value of this vote based on rank
			// Higher ranks get more value (e.g., first choice counts more)
			$vote_value = $total_answers - ($rank - 1);
			
			// Update this answer's vote count based on its rank
			$update_polla_votes = $wpdb->query( 
				$wpdb->prepare(
					"UPDATE $wpdb->pollsa SET polla_votes = (polla_votes + %d) WHERE polla_qid = %d AND polla_aid = %d",
					$vote_value,
					$poll_id,
					$polla_aid
				)
			);
			
			// Log the vote with rank information
			if ( $poll_logging_method > 1 ) {
				$wpdb->insert(
					$wpdb->pollsip,
					array(
						'pollip_qid'       => $poll_id,
						'pollip_aid'       => $polla_aid,
						'pollip_ip'        => $pollip_ip,
						'pollip_host'      => $pollip_host,
						'pollip_timestamp' => $pollip_timestamp,
						'pollip_user'      => $pollip_user . ' (Rank: ' . $rank . ')',
						'pollip_userid'    => $pollip_userid
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d'
					)
				);
			}
			
			$rank++; // Move to next rank
		}
		
		// Update the total votes for the poll
		// For ranked polls, each voter contributes multiple votes (one per option)
		$vote_q = $wpdb->query( 
			$wpdb->prepare(
				"UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes + %d), pollq_totalvoters = (pollq_totalvoters + 1) WHERE pollq_id = %d AND pollq_active = 1",
				array_sum(range(1, $total_answers)),
				$poll_id
			)
		);
	} else {
		// For regular polls, process votes as before
		$i = 0;
		foreach ($poll_aid_array as $polla_aid) {
			$update_polla_votes = $wpdb->query( "UPDATE $wpdb->pollsa SET polla_votes = (polla_votes + 1) WHERE polla_qid = $poll_id AND polla_aid = $polla_aid" );
			if (!$update_polla_votes) {
				unset($poll_aid_array[$i]);
			}
			$i++;
		}

		$vote_q = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes+" . count( $poll_aid_array ) . "), pollq_totalvoters = (pollq_totalvoters + 1) WHERE pollq_id = $poll_id AND pollq_active = 1");
		
		// Log regular poll votes
		foreach ($poll_aid_array as $polla_aid) {
			// Log Ratings In DB If User Choose Logging Method 2, 3 or 4
			if ( $poll_logging_method > 1 ){
				$wpdb->insert(
					$wpdb->pollsip,
					array(
						'pollip_qid'       => $poll_id,
						'pollip_aid'       => $polla_aid,
						'pollip_ip'        => $pollip_ip,
						'pollip_host'      => $pollip_host,
						'pollip_timestamp' => $pollip_timestamp,
						'pollip_user'      => $pollip_user,
						'pollip_userid'    => $pollip_userid
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d'
					)
				);
			}
		}
	}

	if (!$vote_q) {
		throw new InvalidArgumentException(sprintf(__('Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls'), $poll_id));
	}

	// Release lock
	polls_release_lock( $fp_lock, $poll_id );

	do_action( 'wp_polls_vote_poll_success' );

	return display_pollresult($poll_id, $poll_aid_array, false);
}

/**
 * Vote Poll AJAX Handler
 */
function vote_poll() {
	global $wpdb, $user_identity, $user_ID;

	if( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls') {
		// Load Headers
		polls_textdomain();
		header('Content-Type: text/html; charset='.get_option('blog_charset').'');

		// Get Poll ID
		$poll_id = (isset($_REQUEST['poll_id']) ? (int) sanitize_key( $_REQUEST['poll_id'] ) : 0);

		// Ensure Poll ID Is Valid
		if($poll_id === 0) {
			_e('Invalid Poll ID', 'wp-polls');
			exit();
		}

		// Verify Referer
		if( ! check_ajax_referer( 'poll_'.$poll_id.'-nonce', 'poll_'.$poll_id.'_nonce', false ) ) {
			_e('Failed To Verify Referrer', 'wp-polls');
			exit();
		}

		// Which View
		switch( sanitize_key( $_REQUEST['view'] ) ) {
			// Poll Vote
			case 'process':
				try {
					$poll_aid_array = array_unique( array_map('intval', array_map('sanitize_key', explode( ',', $_POST["poll_$poll_id"] ) ) ) );
					echo vote_poll_process($poll_id, $poll_aid_array);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
				break;
			// Poll Result
			case 'result':
				echo display_pollresult($poll_id, 0, false);
				break;
			// Poll Booth Aka Poll Voting Form
			case 'booth':
				echo display_pollvote($poll_id, false);
				break;
		} // End switch($_REQUEST['view'])
	} // End if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'polls')
	exit();
}

/**
 * Poll Admin AJAX Functions
 */
function manage_poll() {
	global $wpdb;
	### Form Processing
	if( isset( $_POST['action'] ) && sanitize_key( $_POST['action'] ) === 'polls-admin' ) {
		if( ! empty( $_POST['do'] ) ) {
			// Set Header
			header('Content-Type: text/html; charset='.get_option('blog_charset').'');

			// Decide What To Do
			switch($_POST['do']) {
				// Delete Polls Logs
				case __('Delete All Logs', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-polls-logs');
					if( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes') {
						$delete_logs = $wpdb->query("DELETE FROM $wpdb->pollsip");
						if($delete_logs) {
							echo '<p style="color: green;">'.__('All Polls Logs Have Been Deleted.', 'wp-polls').'</p>';
						} else {
							echo '<p style="color: red;">'.__('An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls').'</p>';
						}
					}
					break;
				// Delete Poll Logs For Individual Poll
				case __('Delete Logs For This Poll Only', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-poll-logs');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					if( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes') {
						$delete_logs = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
						if( $delete_logs ) {
							echo '<p style="color: green;">'.sprintf(__('All Logs For \'%s\' Has Been Deleted.', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
						} else {
							echo '<p style="color: red;">'.sprintf(__('An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
						}
					}
					break;
				// Delete Poll's Answer
				case __('Delete Poll Answer', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-poll-answer');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$polla_aid = (int) sanitize_key( $_POST['polla_aid'] );
					$poll_answers = $wpdb->get_row( $wpdb->prepare( "SELECT polla_votes, polla_answers FROM $wpdb->pollsa WHERE polla_aid = %d AND polla_qid = %d", $polla_aid, $pollq_id ) );
					$polla_votes = (int) $poll_answers->polla_votes;
					$polla_answers = wp_kses_post( removeslashes( trim( $poll_answers->polla_answers ) ) );
					$delete_polla_answers = $wpdb->delete( $wpdb->pollsa, array( 'polla_aid' => $polla_aid, 'polla_qid' => $pollq_id ), array( '%d', '%d' ) );
					$delete_pollip = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id, 'pollip_aid' => $polla_aid ), array( '%d', '%d' ) );
					$update_pollq_totalvotes = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes - $polla_votes) WHERE pollq_id = $pollq_id" );
					if($delete_polla_answers) {
						echo '<p style="color: green;">'.sprintf(__('Poll Answer \'%s\' Deleted Successfully.', 'wp-polls'), $polla_answers).'</p>';
					} else {
						echo '<p style="color: red;">'.sprintf(__('Error In Deleting Poll Answer \'%s\'.', 'wp-polls'), $polla_answers).'</p>';
					}
					break;
				// Open Poll
				case __('Open Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_open-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$open_poll = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 1
						),
						array(
							'pollq_id' => $pollq_id
						),
						array(
							'%d'
						),
						array(
							'%d'
						)
					);
					if( $open_poll ) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Opened', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					} else {
						echo '<p style="color: red;">'.sprintf(__('Error Opening Poll \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}
					break;
				// Close Poll
				case __('Close Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_close-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$close_poll = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 0
						),
						array(
							'pollq_id' => $pollq_id
						),
						array(
							'%d'
						),
						array(
							'%d'
						)
					);
					if( $close_poll ) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Closed', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					} else {
						echo '<p style="color: red;">'.sprintf(__('Error Closing Poll \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}
					break;
				// Delete Poll
				case __('Delete Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$delete_poll_question = $wpdb->delete( $wpdb->pollsq, array( 'pollq_id' => $pollq_id ), array( '%d' ) );
					$delete_poll_answers =  $wpdb->delete( $wpdb->pollsa, array( 'polla_qid' => $pollq_id ), array( '%d' ) );
					$delete_poll_ip =	   $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
					$poll_option_lastestpoll = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'poll_latestpoll'");
					if(!$delete_poll_question) {
						echo '<p style="color: red;">'.sprintf(__('Error In Deleting Poll \'%s\' Question', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}
					if(empty($text)) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Deleted Successfully', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}

					// Update Lastest Poll ID To Poll Options
					update_option( 'poll_latestpoll', polls_latest_id() );
					do_action( 'wp_polls_delete_poll', $pollq_id );
					break;
			}
			exit();
		}
	}
}

// Add action hooks for AJAX functions
add_action('wp_ajax_polls', 'vote_poll');
add_action('wp_ajax_nopriv_polls', 'vote_poll');
add_action('wp_ajax_polls-admin', 'manage_poll');
