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

	/**
	 * Check Who Is Allow To Vote.
	 *
	 * @return mixed
	 */
	public static function check_allowtovote() {
		global $user_ID;
		// Read into a local rather than reassigning the WordPress global.
		$current_user_id = (int) $user_ID;
		$allow_to_vote   = (int) Polls_Options::get( 'allow_to_vote' );
		switch ( $allow_to_vote ) {
			// Guests Only.
			case 0:
				return 0 === $current_user_id;
			// Registered Users Only.
			case 1:
				return $current_user_id > 0;
			// Registered Users And Guests.
			case 2:
			default:
				return true;
		}
	}

	/**
	 * Check voted by cookie or IP.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function check_voted( $poll_id ) {
		$poll_logging_method = (int) Polls_Options::get( 'logging_method' );
		switch ( $poll_logging_method ) {
			// Do Not Log.
			case 0:
				return 0;
			// Logged By Cookie.
			case 1:
				return self::check_voted_cookie( $poll_id );
			// Logged By IP.
			case 2:
				return self::check_voted_ip( $poll_id );
			// Logged By Cookie And IP.
			case 3:
				$check_voted_cookie = self::check_voted_cookie( $poll_id );
				if ( ! empty( $check_voted_cookie ) ) {
					return $check_voted_cookie;
				}
				return self::check_voted_ip( $poll_id );
			// Logged By Username.
			case 4:
				return self::check_voted_username( $poll_id );
		}
	}

	/**
	 * Check Voted By Cookie.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function check_voted_cookie( $poll_id ) {
		$get_voted_aids = 0;
		if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
			$get_voted_aids = explode( ',', sanitize_text_field( wp_unslash( $_COOKIE[ 'voted_' . $poll_id ] ) ) );
			$get_voted_aids = array_map( 'intval', $get_voted_aids );
		}
		return $get_voted_aids;
	}

	/**
	 * Check Voted By IP.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function check_voted_ip( $poll_id ) {
		global $wpdb;
		$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . Polls_Core::now() . '-(pollip_timestamp+0)) < ' . $log_expiry;
		}
		// Check IP From IP Logging Database.
		$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s", $poll_id, self::poll_get_ipaddress() ) . $log_expiry_sql );
		if ( $get_voted_aids ) {
			return $get_voted_aids;
		}

		return 0;
	}

	/**
	 * Check Voted By Username.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function check_voted_username( $poll_id ) {
		global $wpdb, $user_ID;
		// Check IP If User Is Guest.
		if ( ! is_user_logged_in() ) {
			return 1;
		}
		$pollsip_userid = (int) $user_ID;
		$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . Polls_Core::now() . '-(pollip_timestamp+0)) < ' . $log_expiry;
		}
		// Check User ID From IP Logging Database.
		$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d", $poll_id, $pollsip_userid ) . $log_expiry_sql );
		if ( $get_voted_aids ) {
			return $get_voted_aids;
		} else {
			return 0;
		}
	}

	/**
	 * Check Voted To Get Voted Answer.
	 *
	 * @param mixed $poll_id   Value.
	 * @param mixed $polls_ips Value.
	 *
	 * @return mixed
	 */
	public static function check_voted_multiple( $poll_id, $polls_ips ) {
		if ( ! empty( $_COOKIE[ "voted_$poll_id" ] ) ) {
			return array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_COOKIE[ "voted_$poll_id" ] ) ) ) );
		} elseif ( $polls_ips ) {
				return $polls_ips;
		} else {
			return array();
		}
	}

	/**
	 * Get IP Address.
	 *
	 * @return mixed
	 */
	public static function poll_get_raw_ipaddress() {
		// REMOTE_ADDR is absent under WP-CLI and cron, where this is still reached
		// through the poll display path. Reading it unguarded warns on PHP 8.
		$ip = self::valid_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

		$header = (string) Polls_Options::get( 'ip_header', '' );

		/**
		 * Filters whether the usual proxy headers may be trusted.
		 *
		 * Lets the decision be made per request -- trusting the header only when
		 * the request actually arrives from a known load balancer, say -- rather
		 * than once in wp-config.php.
		 *
		 * @param bool $trust Defaults to the WP_POLLS_TRUST_PROXY constant.
		 */
		$trust_proxy = (bool) apply_filters(
			'wp_polls_trust_proxy',
			defined( 'WP_POLLS_TRUST_PROXY' ) && WP_POLLS_TRUST_PROXY
		);

		if ( '' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			$candidate = self::first_valid_ip( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );

			if ( '' !== $candidate ) {
				$ip = $candidate;
			}
		} elseif ( $trust_proxy ) {
			$headers = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
			);

			foreach ( $headers as $name ) {
				if ( empty( $_SERVER[ $name ] ) ) {
					continue;
				}

				$candidate = self::first_valid_ip( sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) ) );

				if ( '' !== $candidate ) {
					$ip = $candidate;
					break;
				}
			}
		}

		return $ip;
	}

	/**
	 * The first syntactically valid IP in a comma separated list.
	 *
	 * X-Forwarded-For is a chain, not an address: "client, proxy1, proxy2". The
	 * client controls the left of it, so the whole string must never be used as
	 * an identity -- appending one more hop yields a different value and a
	 * different hash, which is enough to vote again.
	 *
	 * @param string $value Header value.
	 *
	 * @return string Validated IP, or an empty string.
	 */
	private static function first_valid_ip( $value ) {
		foreach ( explode( ',', $value ) as $candidate ) {
			$candidate = self::valid_ip( trim( $candidate ) );

			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * An address, or an empty string when it is not one.
	 *
	 * @param string $value Candidate address.
	 *
	 * @return string
	 */
	private static function valid_ip( $value ) {
		$ip = filter_var( $value, FILTER_VALIDATE_IP );

		return false === $ip ? '' : $ip;
	}

	/**
	 * Poll get ipaddress.
	 *
	 * @return mixed
	 */
	public static function poll_get_ipaddress() {
		return apply_filters( 'wp_polls_ipaddress', wp_hash( self::poll_get_raw_ipaddress() ) );
	}

	/**
	 * Poll get hostname.
	 *
	 * @return mixed
	 */
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

	/**
	 * Polls acquire lock.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function polls_acquire_lock( $poll_id ) {
		$fp = fopen( self::polls_lock_file( $poll_id ), 'w+' );

		if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
			return false;
		}

		ftruncate( $fp, 0 );
		fwrite( $fp, microtime( true ) );

		return $fp;
	}

	/**
	 * Polls release lock.
	 *
	 * @param mixed $fp      Value.
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function polls_release_lock( $fp, $poll_id ) {
		if ( is_resource( $fp ) ) {
			fflush( $fp );
			flock( $fp, LOCK_UN );
			fclose( $fp );
			wp_delete_file( self::polls_lock_file( $poll_id ) );

			return true;
		}

		return false;
	}

	/**
	 * Polls lock file.
	 *
	 * @param mixed $poll_id Value.
	 *
	 * @return mixed
	 */
	public static function polls_lock_file( $poll_id ) {
		return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
	}

	/**
	 * Vote poll process.
	 *
	 * @param mixed $poll_id        Value.
	 * @param mixed $poll_aid_array Optional.
	 *
	 * @return mixed
	 *
	 * @throws InvalidArgumentException When the poll lock cannot be acquired, when an
	 *                                  answer id does not belong to the poll, or when
	 *                                  the visitor has already voted.
	 */
	public static function vote_poll_process( $poll_id, $poll_aid_array = array() ) {
		global $wpdb, $user_identity, $user_ID;

		do_action( 'wp_polls_vote_poll' );

		// Acquire lock.
		$fp_lock = self::polls_acquire_lock( $poll_id );
		if ( false === $fp_lock ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
		$is_real    = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

		if ( ! $is_real ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Invalid Answer to Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( ! self::check_allowtovote() ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'User is not allowed to vote for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( empty( $poll_aid_array ) ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'No answers given for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( 0 === $poll_id ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Invalid Poll ID. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

		if ( 0 === $is_poll_open ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ) ) );
		}

		$check_voted = self::check_voted( $poll_id );
		if ( ! empty( $check_voted ) ) {
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( ! empty( $user_identity ) ) {
			$pollip_user = $user_identity;
		} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			$pollip_user = sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) );
		} else {
			$pollip_user = __( 'Guest', 'wp-polls' );
		}

		$pollip_user         = sanitize_text_field( $pollip_user );
		$pollip_userid       = $user_ID;
		$pollip_ip           = self::poll_get_ipaddress();
		$pollip_host         = self::poll_get_hostname();
		$pollip_timestamp    = Polls_Core::now();
		$poll_logging_method = (int) Polls_Options::get( 'logging_method' );

		// Only Create Cookie If User Choose Logging Method 1 Or 3.
		if ( 1 === $poll_logging_method || 3 === $poll_logging_method ) {
			$cookie_expiry = (int) Polls_Options::get( 'cookie_expiry' );
			if ( 0 === $cookie_expiry ) {
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
			/* translators: %s: value. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		foreach ( $poll_aid_array as $polla_aid ) {
			// Log Ratings In DB If User Choose Logging Method 2, 3 or 4.
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

		// Release lock.
		self::polls_release_lock( $fp_lock, $poll_id );

		do_action( 'wp_polls_vote_poll_success' );

		return Polls_Display::display_pollresult( $poll_id, $poll_aid_array, false );
	}

	// Function: Vote Poll.

	/**
	 * Vote poll.
	 *
	 * @return mixed
	 */
	public static function vote_poll() {
		global $wpdb, $user_identity, $user_ID;

		if ( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls' ) {
			// Load Headers.
			// Guarded: anything that has already emitted output - a stray newline
			// in another plugin - would otherwise turn this into a PHP warning
			// inside the response body.
			if ( ! headers_sent() ) {
				header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			}

			// Get Poll ID.
			$poll_id = ( isset( $_REQUEST['poll_id'] ) ? (int) $_REQUEST['poll_id'] : 0 );

			// Ensure Poll ID Is Valid.
			if ( 0 === $poll_id ) {
				esc_html_e( 'Invalid Poll ID', 'wp-polls' );
				wp_die( '', '', array( 'response' => null ) );
			}

			// Verify Referer.
			if ( ! check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_' . $poll_id . '_nonce', false ) ) {
				esc_html_e( 'Failed To Verify Referrer', 'wp-polls' );
				wp_die( '', '', array( 'response' => null ) );
			}

			// Which View.
			switch ( isset( $_REQUEST['view'] ) ? sanitize_key( $_REQUEST['view'] ) : '' ) {
				// Poll Vote.
				case 'process':
					try {
						$poll_answer_ids = isset( $_POST[ "poll_$poll_id" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "poll_$poll_id" ] ) ) : '';
						$poll_aid_array  = array_unique( array_map( 'intval', array_map( 'sanitize_key', explode( ',', $poll_answer_ids ) ) ) );
						// Poll markup, not data: the answer text went through
						// wp_kses_post() and %POLL_ANSWER_TEXT% through esc_attr() while
						// the template was assembled, and every id is int cast. Escaping
						// again here would render the markup as text; wp_kses_post()
						// would strip the radio and checkbox inputs the form needs.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo self::vote_poll_process( $poll_id, $poll_aid_array );
					} catch ( Exception $e ) {
						// Escaped at the throw site as well. Every message is plain
						// text plus an integer poll id, so running it through
						// esc_html() twice cannot change it, and the alternative is a
						// path where an exception raised by anything other than this
						// method reaches the browser unescaped.
						echo esc_html( $e->getMessage() );
					}
					break;
				// Poll Result.
				case 'result':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Poll markup assembled and escaped by Polls_Display.
					echo Polls_Display::display_pollresult( $poll_id, 0, false );
					break;
				// Poll Booth Aka Poll Voting Form.
				case 'booth':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Poll markup assembled and escaped by Polls_Display.
					echo Polls_Display::display_pollvote( $poll_id, false );
					break;
			} // End of the view switch.
		} // End of the polls action guard.
		wp_die( '', '', array( 'response' => null ) );
	}
}
