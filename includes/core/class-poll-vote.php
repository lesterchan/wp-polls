<?php
/**
 * Poll Vote Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Vote Class
 *
 * Handles vote verification and logging functionality.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Vote {

	/**
	 * Check who is allowed to vote.
	 *
	 * @since 2.78.0
	 * @return bool True if user is allowed to vote, false otherwise.
	 */
	public static function can_vote() {
		$current_user_id = (int) get_current_user_id();
		$allow_to_vote   = (int) get_option( 'poll_allowtovote' );
		
		switch ( $allow_to_vote ) {
			// Guests Only.
			case 0:
				if ( 0 < $current_user_id ) {
					return false;
				}
				return true;
				
			// Registered Users Only.
			case 1:
				if ( 0 === $current_user_id ) {
					return false;
				}
				return true;
				
			// Registered Users And Guests.
			case 2:
			default:
				return true;
		}
	}

	/**
	 * Check if a user has already voted based on logging method.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID to check.
	 * @return int|array 0 if not voted, array of answer IDs if voted.
	 */
	public static function has_voted( $poll_id ) {
		$poll_logging_method = (int) get_option( 'poll_logging_method' );
		
		switch ( $poll_logging_method ) {
			// Do Not Log.
			case 0:
				return 0;
				
			// Logged By Cookie.
			case 1:
				return self::check_cookie_vote( $poll_id );
				
			// Logged By IP.
			case 2:
				return self::check_ip_vote( $poll_id );
				
			// Logged By Cookie And IP.
			case 3:
				$check_voted_cookie = self::check_cookie_vote( $poll_id );
				if ( ! empty( $check_voted_cookie ) ) {
					return $check_voted_cookie;
				}
				return self::check_ip_vote( $poll_id );
				
			// Logged By Username.
			case 4:
				return self::check_username_vote( $poll_id );
		}
		
		return 0;
	}

	/**
	 * Check if user has voted by checking cookie.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID to check.
	 * @return int|array 0 if no cookie found, array of answer IDs if cookie exists.
	 */
	public static function check_cookie_vote( $poll_id ) {
		$get_voted_aids = 0;
		
		if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
			$cookie_value   = sanitize_text_field( wp_unslash( $_COOKIE[ 'voted_' . $poll_id ] ) );
			$get_voted_aids = array_map( 'intval', array_map( 'sanitize_key', explode( ',', $cookie_value ) ) );
		}
		
		return $get_voted_aids;
	}

	/**
	 * Check if user has voted by checking IP address.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID to check.
	 * @return int|array 0 if not voted, array of answer IDs if voted.
	 */
	public static function check_ip_vote( $poll_id ) {
		global $wpdb;
		
		$log_expiry = (int) get_option( 'poll_cookielog_expiry' );
		$cache_key = 'poll_voted_ip_' . $poll_id . '_' . WP_Polls_Utility::get_ip_address();
		$get_voted_aids = wp_cache_get( $cache_key );

		if ( false === $get_voted_aids ) {
			if ( 0 < $log_expiry ) {
				$current_time = strtotime( current_time( 'mysql' ) );
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s AND (%d-(pollip_timestamp+0)) < %d",
						$poll_id,
						WP_Polls_Utility::get_ip_address(),
						$current_time,
						$log_expiry
					),
					ARRAY_A
				);
				$get_voted_aids = wp_list_pluck( $results, 'pollip_aid' );
			} else {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s",
						$poll_id,
						WP_Polls_Utility::get_ip_address()
					),
					ARRAY_A
				);
				$get_voted_aids = wp_list_pluck( $results, 'pollip_aid' );
			}
			wp_cache_set( $cache_key, $get_voted_aids, '', HOUR_IN_SECONDS );
		}

		if ( $get_voted_aids ) {
			return $get_voted_aids;
		}

		return 0;
	}

	/**
	 * Check if user has voted by checking username.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID to check.
	 * @return int|array 1 if user is not logged in, array of answer IDs if voted, 0 otherwise.
	 */
	public static function check_username_vote( $poll_id ) {
		global $wpdb;
		
		// Check IP If User Is Guest.
		if ( ! is_user_logged_in() ) {
			return 1;
		}
		
		$pollsip_userid = (int) get_current_user_id();
		$log_expiry = (int) get_option( 'poll_cookielog_expiry' );

		if ( 0 < $log_expiry ) {
			$current_time = current_time( 'timestamp' );
			$get_voted_aids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d AND (%d-(pollip_timestamp+0)) < %d",
					$poll_id,
					$pollsip_userid,
					$current_time,
					$log_expiry
				)
			);
		} else {
			$get_voted_aids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d",
					$poll_id,
					$pollsip_userid
				)
			);
		}

		if ( $get_voted_aids ) {
			return $get_voted_aids;
		}
		
		return 0;
	}

	/**
	 * Check if user voted by getting the voted answer from cookie or IP.
	 *
	 * @since 2.78.0
	 * @param int   $poll_id   The poll ID to check.
	 * @param array $polls_ips Array of poll IPs.
	 * @return array Array of voted answer IDs.
	 */
	public static function check_voted_multiple( $poll_id, $polls_ips ) {
		if ( ! empty( $_COOKIE[ "voted_$poll_id" ] ) ) {
			$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ "voted_$poll_id" ] ) );
			return explode( ',', $cookie_value );
		}

		if ( $polls_ips ) {
			return $polls_ips;
		}

		return array();
	}

	/**
	 * Record a vote in the database.
	 *
	 * @since 2.78.0
	 * @param int   $poll_id  The poll ID.
	 * @param array $answers  Array of answer IDs.
	 * @param bool  $set_cookie Whether to set a cookie.
	 * @return bool True on success, false on failure.
	 */
	public static function add_vote( $poll_id, $answers, $set_cookie = true ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		$answers = array_map( 'intval', (array) $answers );
		$ip = WP_Polls_Utility::get_ip_address();
		$user_id = get_current_user_id();
		$current_time = current_time( 'timestamp' );
		$fp = WP_Polls_Utility::acquire_lock( $poll_id );
		
		if ( false === $fp ) {
			return false;
		}
		
		// Update Poll Total Votes
		$success = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->pollsq SET pollq_totalvotes = pollq_totalvotes+1, pollq_totalvoters = pollq_totalvoters+1 WHERE pollq_id = %d",
				$poll_id
			)
		);
		
		// Update Poll Answers Votes
		foreach ( $answers as $answer_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $wpdb->pollsa SET polla_votes = polla_votes+1 WHERE polla_aid = %d AND polla_qid = %d",
					$answer_id,
					$poll_id
				)
			);
		}
		
		// Log IP
		foreach ( $answers as $answer_id ) {
			$wpdb->insert(
				$wpdb->pollsip,
				array(
					'pollip_qid'       => $poll_id,
					'pollip_aid'       => $answer_id,
					'pollip_ip'        => $ip,
					'pollip_userid'    => $user_id,
					'pollip_timestamp' => $current_time,
				),
				array(
					'%d',
					'%d',
					'%s',
					'%d',
					'%d',
				)
			);
		}
		
		WP_Polls_Utility::release_lock( $fp, $poll_id );
		
		// Set Cookie
		if ( $set_cookie ) {
			$cookie_expiry = (int) get_option( 'poll_cookielog_expiry' );
			if ( 0 < $cookie_expiry ) {
				setcookie( "voted_$poll_id", implode( ',', $answers ), time() + $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN );
			} else {
				setcookie( "voted_$poll_id", implode( ',', $answers ), time() + 30000000, COOKIEPATH, COOKIE_DOMAIN );
			}
		}
		
		return $success;
	}
}
