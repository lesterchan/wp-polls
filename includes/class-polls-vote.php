<?php
/**
 * Vote eligibility, vote recording and the public voting endpoint.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides who may vote, records votes and serves the AJAX endpoint.
 */
class Polls_Vote {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_polls', array( __CLASS__, 'vote_poll' ) );
		add_action( 'wp_ajax_nopriv_polls', array( __CLASS__, 'vote_poll' ) );
	}

	// Function: Check Who Is Allow To Vote
	public static function check_allowtovote() {
		global $user_ID;
		$user_ID       = (int) $user_ID;
		$allow_to_vote = (int) Polls_Options::get( 'allow_to_vote' );
		switch ( $allow_to_vote ) {
			// Guests Only
			case 0:
				if ( $user_ID > 0 ) {
					return false;
				}
				return true;
				break;
			// Registered Users Only
			case 1:
				if ( $user_ID === 0 ) {
					return false;
				}
				return true;
				break;
			// Registered Users And Guests
			case 2:
			default:
				return true;
		}
	}

	// Funcrion: Check Voted By Cookie Or IP
	public static function check_voted( $poll_id ) {
		$poll_logging_method = (int) Polls_Options::get( 'logging_method' );
		switch ( $poll_logging_method ) {
			// Do Not Log
			case 0:
				return 0;
				break;
			// Logged By Cookie
			case 1:
				return self::check_voted_cookie( $poll_id );
				break;
			// Logged By IP
			case 2:
				return self::check_voted_ip( $poll_id );
				break;
			// Logged By Cookie And IP
			case 3:
				$check_voted_cookie = self::check_voted_cookie( $poll_id );
				if ( ! empty( $check_voted_cookie ) ) {
					return $check_voted_cookie;
				}
				return self::check_voted_ip( $poll_id );
				break;
			// Logged By Username
			case 4:
				return self::check_voted_username( $poll_id );
				break;
		}
	}

	// Function: Check Voted By Cookie
	public static function check_voted_cookie( $poll_id ) {
		$get_voted_aids = 0;
		if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
			$get_voted_aids = explode( ',', $_COOKIE[ 'voted_' . $poll_id ] );
			$get_voted_aids = array_map( 'intval', array_map( 'sanitize_key', $get_voted_aids ) );
		}
		return $get_voted_aids;
	}

	// Function: Check Voted By IP
	public static function check_voted_ip( $poll_id ) {
		global $wpdb;
		$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . current_time( 'timestamp' ) . '-(pollip_timestamp+0)) < ' . $log_expiry;
		}
		// Check IP From IP Logging Database
		$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s", $poll_id, self::poll_get_ipaddress() ) . $log_expiry_sql );
		if ( $get_voted_aids ) {
			return $get_voted_aids;
		}

		return 0;
	}

	// Function: Check Voted By Username
	public static function check_voted_username( $poll_id ) {
		global $wpdb, $user_ID;
		// Check IP If User Is Guest
		if ( ! is_user_logged_in() ) {
			return 1;
		}
		$pollsip_userid = (int) $user_ID;
		$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . current_time( 'timestamp' ) . '-(pollip_timestamp+0)) < ' . $log_expiry;
		}
		// Check User ID From IP Logging Database
		$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d", $poll_id, $pollsip_userid ) . $log_expiry_sql );
		if ( $get_voted_aids ) {
			return $get_voted_aids;
		} else {
			return 0;
		}
	}

	// Function: Check Voted To Get Voted Answer
	public static function check_voted_multiple( $poll_id, $polls_ips ) {
		if ( ! empty( $_COOKIE[ "voted_$poll_id" ] ) ) {
			return explode( ',', $_COOKIE[ "voted_$poll_id" ] );
		} elseif ( $polls_ips ) {
				return $polls_ips;
		} else {
			return array();
		}
	}

	// Function: Get IP Address
	public static function poll_get_raw_ipaddress() {
		// REMOTE_ADDR is absent under WP-CLI and cron, where this is still reached
		// through the poll display path. Reading it unguarded warns on PHP 8.
		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? esc_attr( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_header = Polls_Options::get( 'ip_header', '' );
		if ( ! empty( $ip_header ) && ! empty( $_SERVER[ $ip_header ] ) ) {
			$ip = esc_attr( wp_unslash( $_SERVER[ $ip_header ] ) );
		}

		return $ip;
	}

	public static function poll_get_ipaddress() {
		return apply_filters( 'wp_polls_ipaddress', wp_hash( self::poll_get_raw_ipaddress() ) );
	}

	public static function poll_get_hostname() {
		$ip = self::poll_get_raw_ipaddress();

		// gethostbyaddr() warns on anything that is not an IP, which includes the
		// empty string when REMOTE_ADDR is absent and whatever a spoofable proxy
		// header happens to contain.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return apply_filters( 'wp_polls_hostname', '' );
		}

		$hostname = gethostbyaddr( $ip );
		if ( $hostname === $ip ) {
			$hostname = wp_privacy_anonymize_ip( $ip );
		}

		if ( false !== $hostname ) {
			$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
		}

		return apply_filters( 'wp_polls_hostname', $hostname );
	}

	public static function polls_acquire_lock( $poll_id ) {
		$fp = fopen( self::polls_lock_file( $poll_id ), 'w+' );

		if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
			return false;
		}

		ftruncate( $fp, 0 );
		fwrite( $fp, microtime( true ) );

		return $fp;
	}

	public static function polls_release_lock( $fp, $poll_id ) {
		if ( is_resource( $fp ) ) {
			fflush( $fp );
			flock( $fp, LOCK_UN );
			fclose( $fp );
			unlink( self::polls_lock_file( $poll_id ) );

			return true;
		}

		return false;
	}

	public static function polls_lock_file( $poll_id ) {
		return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
	}

	public static function vote_poll_process( $poll_id, $poll_aid_array = array() ) {
		global $wpdb, $user_identity, $user_ID;

		do_action( 'wp_polls_vote_poll' );

		// Acquire lock
		$fp_lock = self::polls_acquire_lock( $poll_id );
		if ( $fp_lock === false ) {
			throw new InvalidArgumentException( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
		$is_real    = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

		if ( ! $is_real ) {
			throw new InvalidArgumentException( sprintf( __( 'Invalid Answer to Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		if ( ! self::check_allowtovote() ) {
			throw new InvalidArgumentException( sprintf( __( 'User is not allowed to vote for Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		if ( empty( $poll_aid_array ) ) {
			throw new InvalidArgumentException( sprintf( __( 'No answers given for Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		if ( $poll_id === 0 ) {
			throw new InvalidArgumentException( sprintf( __( 'Invalid Poll ID. Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

		if ( $is_poll_open === 0 ) {
			throw new InvalidArgumentException( sprintf( __( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ) );
		}

		$check_voted = self::check_voted( $poll_id );
		if ( ! empty( $check_voted ) ) {
			throw new InvalidArgumentException( sprintf( __( 'You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		if ( ! empty( $user_identity ) ) {
			$pollip_user = $user_identity;
		} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			$pollip_user = $_COOKIE[ 'comment_author_' . COOKIEHASH ];
		} else {
			$pollip_user = __( 'Guest', 'wp-polls' );
		}

		$pollip_user         = sanitize_text_field( $pollip_user );
		$pollip_userid       = $user_ID;
		$pollip_ip           = self::poll_get_ipaddress();
		$pollip_host         = self::poll_get_hostname();
		$pollip_timestamp    = current_time( 'timestamp' );
		$poll_logging_method = (int) Polls_Options::get( 'logging_method' );

		// Only Create Cookie If User Choose Logging Method 1 Or 3
		if ( $poll_logging_method === 1 || $poll_logging_method === 3 ) {
			$cookie_expiry = (int) Polls_Options::get( 'cookie_expiry' );
			if ( $cookie_expiry === 0 ) {
				$cookie_expiry = YEAR_IN_SECONDS;
			}
			setcookie( 'voted_' . $poll_id, implode( ',', $poll_aid_array ), $pollip_timestamp + $cookie_expiry, apply_filters( 'wp_polls_cookiepath', SITECOOKIEPATH ) );
		}

		$i = 0;
		foreach ( $poll_aid_array as $polla_aid ) {
			$update_polla_votes = $wpdb->query( "UPDATE $wpdb->pollsa SET polla_votes = (polla_votes + 1) WHERE polla_qid = $poll_id AND polla_aid = $polla_aid" );
			if ( ! $update_polla_votes ) {
				unset( $poll_aid_array[ $i ] );
			}
			++$i;
		}

		$vote_q = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes+" . count( $poll_aid_array ) . "), pollq_totalvoters = (pollq_totalvoters + 1) WHERE pollq_id = $poll_id AND pollq_active = 1" );
		if ( ! $vote_q ) {
			throw new InvalidArgumentException( sprintf( __( 'Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls' ), $poll_id ) );
		}

		foreach ( $poll_aid_array as $polla_aid ) {
			// Log Ratings In DB If User Choose Logging Method 2, 3 or 4
			if ( $poll_logging_method > 1 ) {
				$wpdb->insert(
					$wpdb->pollsip,
					array(
						'pollip_qid'       => $poll_id,
						'pollip_aid'       => $polla_aid,
						'pollip_ip'        => $pollip_ip,
						'pollip_host'      => $pollip_host,
						'pollip_timestamp' => $pollip_timestamp,
						'pollip_user'      => $pollip_user,
						'pollip_userid'    => $pollip_userid,
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
					)
				);
			}
		}

		// Release lock
		self::polls_release_lock( $fp_lock, $poll_id );

		do_action( 'wp_polls_vote_poll_success' );

		return Polls_Display::display_pollresult( $poll_id, $poll_aid_array, false );
	}

	// Function: Vote Poll

	public static function vote_poll() {
		global $wpdb, $user_identity, $user_ID;

		if ( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls' ) {
			// Load Headers
			Polls_Core::polls_textdomain();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) . '' );

			// Get Poll ID
			$poll_id = ( isset( $_REQUEST['poll_id'] ) ? (int) sanitize_key( $_REQUEST['poll_id'] ) : 0 );

			// Ensure Poll ID Is Valid
			if ( $poll_id === 0 ) {
				_e( 'Invalid Poll ID', 'wp-polls' );
				exit();
			}

			// Verify Referer
			if ( ! check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_' . $poll_id . '_nonce', false ) ) {
				_e( 'Failed To Verify Referrer', 'wp-polls' );
				exit();
			}

			// Which View
			switch ( sanitize_key( $_REQUEST['view'] ) ) {
				// Poll Vote
				case 'process':
					try {
						$poll_answer_ids = isset( $_POST[ "poll_$poll_id" ] ) ? $_POST[ "poll_$poll_id" ] : '';
						$poll_aid_array  = array_unique( array_map( 'intval', array_map( 'sanitize_key', explode( ',', $poll_answer_ids ) ) ) );
						echo self::vote_poll_process( $poll_id, $poll_aid_array );
					} catch ( Exception $e ) {
						echo $e->getMessage();
					}
					break;
				// Poll Result
				case 'result':
					echo Polls_Display::display_pollresult( $poll_id, 0, false );
					break;
				// Poll Booth Aka Poll Voting Form
				case 'booth':
					echo Polls_Display::display_pollvote( $poll_id, false );
					break;
			} // End switch($_REQUEST['view'])
		} // End if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'polls')
		exit();
	}
}
