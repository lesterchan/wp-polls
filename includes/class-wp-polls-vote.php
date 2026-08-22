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
class WP_Polls_Vote {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_polls', array( __CLASS__, 'ajax_vote' ) );
		add_action( 'wp_ajax_nopriv_polls', array( __CLASS__, 'ajax_vote' ) );
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
		$allow_to_vote   = (int) WP_Polls_Options::get( 'allow_to_vote' );
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
		$check_method = (int) WP_Polls_Options::get( 'check_method' );
		switch ( $check_method ) {
			// Do Not Check.
			case 0:
				return 0;
			// Check By Cookie.
			case 1:
				return self::check_voted_cookie( $poll_id );
			// Check By IP Address.
			case 2:
				return self::check_voted_ip( $poll_id );
			// Check By Cookie And IP Address.
			case 3:
				$check_voted_cookie = self::check_voted_cookie( $poll_id );
				if ( ! empty( $check_voted_cookie ) ) {
					return $check_voted_cookie;
				}
				return self::check_voted_ip( $poll_id );
			// Check By Username.
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
		$log_expiry     = (int) WP_Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . WP_Polls::now() . '-(pollip_timestamp+0)) < ' . $log_expiry;
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
		$log_expiry     = (int) WP_Polls_Options::get( 'cookie_expiry' );
		$log_expiry_sql = '';
		if ( $log_expiry > 0 ) {
			$log_expiry_sql = ' AND (' . WP_Polls::now() . '-(pollip_timestamp+0)) < ' . $log_expiry;
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

		$header = (string) WP_Polls_Options::get( 'ip_header', '' );

		/**
		 * Filters whether the usual proxy headers may be trusted.
		 *
		 * Lets the decision be made per request -- trusting the header only when
		 * the request actually arrives from a known load balancer, say -- rather
		 * than once in wp-config.php.
		 *
		 * @since 3.0.0
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
		/**
		 * Filters the voter identity recorded against a vote.
		 *
		 * A hash of the address rather than the address itself, so two voters
		 * can be told apart without their IPs being stored.
		 *
		 * @since 2.75.0
		 *
		 * @param string $ipaddress Hashed address.
		 */
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
			/** This filter is documented in includes/class-wp-polls-vote.php */
			return apply_filters( 'wp_polls_hostname', '' );
		}

		$hostname = gethostbyaddr( $ip );
		if ( $hostname === $ip ) {
			$hostname = wp_privacy_anonymize_ip( $ip );
		}

		if ( false !== $hostname ) {
			$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
		}

		/**
		 * Filters the host name recorded against a vote.
		 *
		 * @since 2.75.0
		 *
		 * @param string $hostname Anonymised host name, or an empty string.
		 */
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- An flock() advisory lock; WP_Filesystem abstracts over FTP and SSH and has no locking primitive.
		$fp = fopen( self::polls_lock_file( $poll_id ), 'w+' );

		if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
			return false;
		}

		ftruncate( $fp, 0 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing the lock stamp through the handle flock() holds.
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
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Releasing the flock() handle; the file itself is removed with wp_delete_file().
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
		/**
		 * Filters the path of the advisory lock file guarding one poll's tally.
		 *
		 * @since 2.77.0
		 *
		 * @param string $path    Lock file path.
		 * @param int    $poll_id Poll being voted on.
		 */
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

		/**
		 * Fires when a vote is about to be recorded, before any guard has run.
		 *
		 * @since 2.75.0
		 */
		do_action( 'wp_polls_vote_poll' );

		// Acquire lock.
		$fp_lock = self::polls_acquire_lock( $poll_id );
		if ( false === $fp_lock ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
		$is_real    = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

		if ( ! $is_real ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Invalid Answer to Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		/*
		 * How many answers the poll allows, checked on the server.
		 *
		 * It was checked in the browser and nowhere else: js/wp-polls.js counts
		 * the ticked boxes against poll_multiple_ans_<id> and refuses, and
		 * nothing here consulted pollq_multiple at all -- the column does not
		 * appear in this file. So one request could vote for *every* answer of a
		 * single-choice poll: each polla_votes gained one and pollq_totalvotes
		 * gained N while pollq_totalvoters gained one, which puts the
		 * percentages, %POLL_MOST_ANSWER% and %POLL_LEAST_ANSWER% permanently
		 * wrong. The repeat-vote check bounds it to one inflated ballot per
		 * eligible voter, which is why this is integrity rather than stuffing.
		 *
		 * Zero is single choice rather than unlimited -- the same reading
		 * WP_Polls_Display gives it when it resolves %POLL_MULTIPLE_ANS_MAX%,
		 * and the same one js/wp-polls.js gives it when it decides between
		 * counting ticked boxes and letting a radio group speak for itself.
		 */
		$poll_multiple = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_multiple FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$max_answers   = $poll_multiple > 0 ? $poll_multiple : 1;

		if ( count( $poll_aid_array ) > $max_answers ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: 1: maximum number of answers, 2: poll id. */
						_n(
							'Maximum %1$s answer allowed for Poll ID #%2$s',
							'Maximum %1$s answers allowed for Poll ID #%2$s',
							$max_answers,
							'wp-polls'
						),
						number_format_i18n( $max_answers ),
						$poll_id
					)
				)
			);
		}

		if ( ! self::check_allowtovote() ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'User is not allowed to vote for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( empty( $poll_aid_array ) ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'No answers given for Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( 0 === $poll_id ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Invalid Poll ID. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

		if ( 0 === $is_poll_open ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ) ) );
		}

		$check_voted = self::check_voted( $poll_id );
		if ( ! empty( $check_voted ) ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		if ( ! empty( $user_identity ) ) {
			$pollip_user = $user_identity;
		} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			$pollip_user = sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) );
		} else {
			$pollip_user = __( 'Guest', 'wp-polls' );
		}

		$pollip_user      = sanitize_text_field( $pollip_user );
		$pollip_userid    = $user_ID;
		$pollip_ip        = self::poll_get_ipaddress();
		$pollip_host      = self::poll_get_hostname();
		$pollip_timestamp = WP_Polls::now();
		$check_method     = (int) WP_Polls_Options::get( 'check_method' );

		/*
		 * A cookie only where the repeat check reads one -- and only while one
		 * can still be set.
		 *
		 * headers_sent() as well as the setting: once anything has been written
		 * to the response the call is guaranteed to fail and its only effect is
		 * a "headers already sent" warning. A theme or another plugin echoing
		 * before this runs is enough to trigger it, and a warning printed into
		 * the JSON of an AJAX vote is what breaks the vote.
		 */
		if ( ( 1 === $check_method || 3 === $check_method ) && ! headers_sent() ) {
			$cookie_expiry = (int) WP_Polls_Options::get( 'cookie_expiry' );
			if ( 0 === $cookie_expiry ) {
				$cookie_expiry = YEAR_IN_SECONDS;
			}
			/**
			 * Filters the path the "already voted" cookie is set on.
			 *
			 * @since 2.75.0
			 *
			 * @param string $path Cookie path, SITECOOKIEPATH by default.
			 */
			$cookie_path = apply_filters( 'wp_polls_cookiepath', SITECOOKIEPATH );

			setcookie( 'voted_' . $poll_id, implode( ',', $poll_aid_array ), $pollip_timestamp + $cookie_expiry, $cookie_path );
		}

		$i = 0;
		foreach ( $poll_aid_array as $polla_aid ) {
			$update_polla_votes = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $wpdb->pollsa SET polla_votes = ( polla_votes + 1 ) WHERE polla_qid = %d AND polla_aid = %d",
					$poll_id,
					$polla_aid
				)
			);
			if ( ! $update_polla_votes ) {
				unset( $poll_aid_array[ $i ] );
			}
			++$i;
		}

		$vote_q = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->pollsq SET pollq_totalvotes = ( pollq_totalvotes + %d ), pollq_totalvoters = ( pollq_totalvoters + 1 ) WHERE pollq_id = %d AND pollq_active = 1",
				count( $poll_aid_array ),
				$poll_id
			)
		);
		if ( ! $vote_q ) {
			/* translators: %s: The poll ID. */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls' ), $poll_id ) ) );
		}

		/*
		 * Every vote is recorded, whatever the repeat-vote check is set to.
		 *
		 * Those were one setting until 3.0.0: choosing "Do Not Log" or "Logged
		 * By Cookie" -- now "Do Not Check" and "Check By Cookie" -- meant no row
		 * was written, so the vote log, the Logs screen and the WP-Stats figures
		 * were all empty on a site that had simply picked a lighter check. The
		 * two are not the same question. What a returning visitor is matched
		 * against is a matter of how strict the site wants to be; whether its
		 * votes are recorded at all is not something that should follow from it.
		 */

		/**
		 * Filters whether this vote is written to the poll log.
		 *
		 * The answer's own tally and the poll's totals are columns on the poll
		 * tables and are updated either way; this is the per-vote row behind the
		 * Logs screen, the IP and username checks, and the WP-Stats figures.
		 * Returning false leaves those with nothing to read.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $log     Whether to record the vote.
		 * @param int  $poll_id Poll being voted on.
		 */
		$log_vote = (bool) apply_filters( 'wp_polls_log_vote', true, $poll_id );

		foreach ( $poll_aid_array as $polla_aid ) {
			if ( $log_vote ) {
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

		/**
		 * Fires once a vote has been recorded and the lock released.
		 *
		 * @since 2.70.0
		 */
		do_action( 'wp_polls_vote_poll_success' );

		return WP_Polls_Display::display_pollresult( $poll_id, $poll_aid_array, false );
	}

	// Function: Vote Poll.

	/**
	 * Vote poll.
	 *
	 * @return mixed
	 */
	public static function ajax_vote() {
		global $wpdb, $user_identity, $user_ID;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The endpoint calls check_ajax_referer() above before reaching this switch.
		if ( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls' ) {
			// Load Headers.
			// Guarded: anything that has already emitted output - a stray newline
			// in another plugin - would otherwise turn this into a PHP warning
			// inside the response body.
			if ( ! headers_sent() ) {
				header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			}

			// Get Poll ID.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The endpoint calls check_ajax_referer() above before reaching this switch.
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
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Poll markup assembled and escaped by WP_Polls_Display.
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
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Poll markup assembled and escaped by WP_Polls_Display.
					echo WP_Polls_Display::display_pollresult( $poll_id, 0, false );
					break;
				// Poll Booth Aka Poll Voting Form.
				case 'booth':
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Poll markup assembled and escaped by WP_Polls_Display.
					echo WP_Polls_Display::display_pollvote( $poll_id, false );
					break;
			} // End of the view switch.
		} // End of the polls action guard.
		wp_die( '', '', array( 'response' => null ) );
	}
}
