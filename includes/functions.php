<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols
/**
 * WP-Polls Core Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check who is allowed to vote.
 *
 * @global int $user_ID WordPress user ID.
 * @return bool True if user is allowed to vote, false otherwise.
 */
function check_allowtovote() {
	$current_user_id = (int) get_current_user_id();
	$allow_to_vote   = (int) get_option( 'poll_allowtovote' );
	switch ( $allow_to_vote ) {
		// Guests Only.
		case 0:
			if ( $user_ID > 0 ) {
				return false;
			}
			return true;
		// Registered Users Only.
		case 1:
			if ( 0 === $user_ID ) {
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
 * @param int $poll_id The poll ID to check.
 * @return int|array 0 if not voted, array of answer IDs if voted.
 */
function check_voted( $poll_id ) {
	$poll_logging_method = (int) get_option( 'poll_logging_method' );
	switch ( $poll_logging_method ) {
		// Do Not Log.
		case 0:
			return 0;
		// Logged By Cookie.
		case 1:
			return check_voted_cookie( $poll_id );
		// Logged By IP.
		case 2:
			return check_voted_ip( $poll_id );
		// Logged By Cookie And IP.
		case 3:
			$check_voted_cookie = check_voted_cookie( $poll_id );
			if ( ! empty( $check_voted_cookie ) ) {
				return $check_voted_cookie;
			}
			return check_voted_ip( $poll_id );
		// Logged By Username.
		case 4:
			return check_voted_username( $poll_id );
	}
}

/**
 * Check if user has voted by checking cookie.
 *
 * @param int $poll_id The poll ID to check.
 * @return int|array 0 if no cookie found, array of answer IDs if cookie exists.
 */
function check_voted_cookie( $poll_id ) {
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
 * @global wpdb $wpdb WordPress database object.
 * @param int $poll_id The poll ID to check.
 * @return int|array 0 if not voted, array of answer IDs if voted.
 */
function check_voted_ip( $poll_id ) {
	global $wpdb;
	$log_expiry = (int) get_option( 'poll_cookielog_expiry' );
	$cache_key = 'poll_voted_ip_' . $poll_id . '_' . poll_get_ipaddress();
	$get_voted_aids = wp_cache_get( $cache_key );

	if ( false === $get_voted_aids ) {
		if ( $log_expiry > 0 ) {
			$current_time   = strtotime( current_time( 'mysql' ) );
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s AND (%d-(pollip_timestamp+0)) < %d",
					$poll_id,
					poll_get_ipaddress(),
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
					poll_get_ipaddress()
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
 * @global wpdb $wpdb WordPress database object.
 * @global int $user_ID WordPress user ID.
 * @param int $poll_id The poll ID to check.
 * @return int|array 1 if user is not logged in, array of answer IDs if voted, 0 otherwise.
 */
function check_voted_username( $poll_id ) {
	global $wpdb, $user_ID;
	// Check IP If User Is Guest.
	if ( ! is_user_logged_in() ) {
		return 1;
	}
	$pollsip_userid = (int) $user_ID;
	$log_expiry = (int) get_option( 'poll_cookielog_expiry' );

	if ( $log_expiry > 0 ) {
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
	} else {
		return 0;
	}
}

/**
 * Get Poll Question Based On Poll ID.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param int $poll_id The poll ID to retrieve the question for.
 * @return string The poll question text with HTML formatting preserved and slashes removed.
 */
if ( ! function_exists( 'get_poll_question' ) ) {
	/**
	 * Gets poll question for a specific poll ID.
	 *
	 * @param int $poll_id The poll ID to retrieve.
	 * @return string The poll question text.
	 */
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id   = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}

/**
 * Get Poll Total Questions.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll questions if $display is false.
 */
if ( ! function_exists( 'get_pollquestions' ) ) {
	/**
	 * Get the total number of poll questions.
	 *
	 * @param bool $display Whether to display or return the count.
	 * @return int|void The total number of poll questions if $display is false.
	 */
	function get_pollquestions( $display = true ) {
		global $wpdb;
		$totalpollq = (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo esc_html( $totalpollq );
		} else {
			return $totalpollq;
		}
	}
}

/**
 * Get Poll Total Answers.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll answers if $display is false.
 */
if ( ! function_exists( 'get_pollanswers' ) ) {
	/**
	 * Get the total number of poll answers.
	 *
	 * @param bool $display Whether to display or return the count.
	 * @return int|void The total number of poll answers if $display is false.
	 */
	function get_pollanswers( $display = true ) {
		global $wpdb;
		$totalpolla = (int) $wpdb->get_var( "SELECT COUNT(polla_aid) FROM $wpdb->pollsa" );
		if ( $display ) {
			echo esc_html( $totalpolla );
		} else {
			return $totalpolla;
		}
	}
}

/**
 * Get Poll Total Votes across all polls in the system.
 *
 * Retrieves the sum of all votes from all polls in the database. Can either
 * display the value directly or return it for further processing.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param bool $display Optional. Whether to display or return the count. Default true.
 * @return int|void The total number of poll votes if $display is false, void if true.
 */
if ( ! function_exists( 'get_pollvotes' ) ) {
	/**
	 * Get the total number of votes across all polls.
	 *
	 * @param bool $display Whether to display or return the count.
	 * @return int|void The total number of votes if $display is false.
	 */
	function get_pollvotes( $display = true ) {
		global $wpdb;
		$totalvotes = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo esc_html( $totalvotes );
		} else {
			return $totalvotes;
		}
	}
}

/**
 * Get Poll Votes Based on Poll ID.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param int  $poll_id The poll ID.
 * @param bool $display Whether to display or return the count.
 * @return int|void The number of votes for the specified poll if $display is false.
 */
if ( ! function_exists( 'get_pollvotes_by_id' ) ) {
	/**
	 * Get poll votes count for a specific poll ID.
	 *
	 * @param int  $poll_id The poll ID to get votes for.
	 * @param bool $display Whether to display or return the count.
	 * @return int|void Number of votes if $display is false, void if true.
	 */
	function get_pollvotes_by_id( $poll_id, $display = true ) {
		global $wpdb;
		$poll_id    = (int) $poll_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		if ( $display ) {
			echo esc_html( $totalvotes );
		} else {
			return $totalvotes;
		}
	}
}

/**
 * Get Poll Total Voters.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll voters if $display is false.
 */
if ( ! function_exists( 'get_pollvoters' ) ) {
	/**
	 * Get the total number of poll voters across all polls.
	 *
	 * @param bool $display Whether to display or return the count.
	 * @return int|void The total number of voters if $display is false.
	 */
	function get_pollvoters( $display = true ) {
		global $wpdb;
		$totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo esc_html( $totalvoters );
		} else {
			return $totalvoters;
		}
	}
}

/**
 * Get Poll Time Based on Poll ID and Date Format.
 *
 * @global wpdb $wpdb WordPress database object.
 * @param int    $poll_id     The poll ID.
 * @param string $date_format The date format.
 * @param bool   $display     Whether to display or return the formatted date.
 * @return string|void The formatted date if $display is false.
 */
if ( ! function_exists( 'get_polltime' ) ) {
	/**
	 * Get formatted poll time for a specific poll.
	 *
	 * @param int    $poll_id     The poll ID to retrieve time for.
	 * @param string $date_format The format to display the date (default: 'd/m/Y').
	 * @param bool   $display     Whether to echo or return the result (default: true).
	 * @return string|void Formatted date string if $display is false, void if true.
	 */
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$cache_key = 'poll_time_' . $poll_id;
		$timestamp = wp_cache_get( $cache_key );

		if ( false === $timestamp ) {
			$timestamp = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1",
					$poll_id
				)
			);
			wp_cache_set( $cache_key, $timestamp, '', HOUR_IN_SECONDS );
		}

		$formatted_date = gmdate( $date_format, $timestamp );

		if ( $display ) {
			echo esc_html( $formatted_date );
		} else {
			return $formatted_date;
		}
	}
}

/**
 * Check if user voted by getting the voted answer from cookie or IP.
 *
 * @param int   $poll_id   The poll ID to check.
 * @param array $polls_ips Array of poll IPs.
 * @return array Array of voted answer IDs.
 */
function check_voted_multiple( $poll_id, $polls_ips ) {
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
 * Get the raw IP address of the current user.
 *
 * @return string The IP address.
 */
function poll_get_raw_ipaddress() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$poll_options = get_option( 'poll_options' );
	if ( ! empty( $poll_options ) && ! empty( $poll_options['ip_header'] ) && isset( $_SERVER[ $poll_options['ip_header'] ] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER[ $poll_options['ip_header'] ] ) );
	}

	return $ip;
}

/**
 * Get the hashed IP address of the current user.
 *
 * @return string The hashed IP address.
 */
function poll_get_ipaddress() {
	return apply_filters( 'wp_polls_ipaddress', wp_hash( poll_get_raw_ipaddress() ) );
}

/**
 * Get the hostname of the current user.
 *
 * @return string The hostname.
 */
function poll_get_hostname() {
	$ip = poll_get_raw_ipaddress();
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
 * Get the polls archive link with pagination.
 *
 * @param int $page The page number.
 * @return string The archive URL with pagination parameters if needed.
 */
function polls_archive_link( $page ) {
	$polls_archive_url = get_option( 'poll_archive_url' );
	if ( $page > 0 ) {
		if ( strpos( $polls_archive_url, '?' ) !== false ) {
			$polls_archive_url = $polls_archive_url . '&amp;poll_page=' . $page;
		} else {
			$polls_archive_url = $polls_archive_url . '?poll_page=' . $page;
		}
	}
	return $polls_archive_url;
}

/**
 * Display or return the polls archive link.
 *
 * @param bool $display Whether to display or return the link.
 * @return string|void The archive link HTML if $display is false.
 */
function display_polls_archive_link( $display = true ) {
	$template_pollarchivelink = removeslashes( get_option( 'poll_template_pollarchivelink' ) );
	$template_pollarchivelink = str_replace( '%POLL_ARCHIVE_URL%', get_option( 'poll_archive_url' ), $template_pollarchivelink );
	if ( $display ) {
		echo wp_kses_post( $template_pollarchivelink );
	} else {
		return $template_pollarchivelink;
	}
}

/**
 * Check if the current page is a poll archive page.
 *
 * @return bool True if current page is a poll archive, false otherwise.
 */
function in_pollarchive() {
	$poll_archive_url = get_option( 'poll_archive_url' );
	$poll_archive_url_array = explode( '/', $poll_archive_url );
	$poll_archive_url       = $poll_archive_url_array[ count( $poll_archive_url_array ) - 1 ];
	if ( empty( $poll_archive_url ) ) {
		$poll_archive_url = $poll_archive_url_array[ count( $poll_archive_url_array ) - 2 ];
	}
	$current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( strpos( $current_url, strval( $poll_archive_url ) ) === false ) {
		return false;
	}

	return true;
}

/**
 * Get the latest active poll ID.
 *
 * @global wpdb $wpdb WordPress database object.
 * @return int The latest poll ID.
 */
function polls_latest_id() {
	global $wpdb;
	$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1" );
	return (int) $poll_id;
}

/**
 * Set up the scheduled cron job for polls.
 */
function cron_polls_place() {
	wp_clear_scheduled_hook( 'polls_cron' );
	if ( ! wp_next_scheduled( 'polls_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'polls_cron' );
	}
}

/**
 * Check and update polls status based on expiry and scheduled times.
 *
 * @global wpdb $wpdb WordPress database object.
 */
function cron_polls_status() {
	global $wpdb;
	// Close Poll with caching.
	$current_hour = gmdate( 'YmdH' );
	$cache_key = 'wp_polls_close_' . $current_hour;
	$close_polls = wp_cache_get( $cache_key );
	if ( false === $close_polls ) {
		$close_polls = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < %d AND pollq_expiry != 0 AND pollq_active != 0",
				current_time( 'timestamp' )
			)
		);
		wp_cache_set( $cache_key, $close_polls, '', HOUR_IN_SECONDS );
	}
	// Open Future Polls.
	$active_polls = $wpdb->query(
		$wpdb->prepare(
			"UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= %d AND pollq_active = -1",
			current_time( 'timestamp' )
		)
	);
	// Update Latest Poll If Future Poll Is Opened.
	if ( $active_polls ) {
		update_option( 'poll_latestpoll', polls_latest_id() );
	}
}

/**
 * Acquire a lock for poll operations to prevent race conditions.
 *
 * @param int $poll_id The poll ID.
 * @return resource|false File pointer resource on success, false on failure.
 */
function polls_acquire_lock( $poll_id ) {
	$fp = fopen( polls_lock_file( $poll_id ), 'w+' );

	if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
		return false;
	}

	ftruncate( $fp, 0 );
	fwrite( $fp, microtime( true ) );

	return $fp;
}

/**
 * Release a previously acquired poll lock.
 *
 * @param resource $fp      The file pointer resource.
 * @param int      $poll_id The poll ID.
 * @return bool True on success, false on failure.
 */
function polls_release_lock( $fp, $poll_id ) {
	if ( is_resource( $fp ) ) {
		fflush( $fp );
		flock( $fp, LOCK_UN );
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->delete( polls_lock_file( $poll_id ) );
		return true;
	}
	return false;
}

/**
 * Get the path to the lock file for a specific poll.
 *
 * @param int $poll_id The poll ID.
 * @return string The full path to the lock file.
 */
function polls_lock_file( $poll_id ) {
	return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
}

/**
 * Get the sort order for poll answers.
 *
 * @return array Array containing the order by field and sort direction.
 */
function _polls_get_ans_sort() {
	$order_by = get_option( 'poll_ans_sortby' );
	switch ( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = 'desc' === get_option( 'poll_ans_sortorder' ) ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}

/**
 * Get the sort order for poll results.
 *
 * @return array Array containing the order by field and sort direction.
 */
function _polls_get_ans_result_sort() {
	$order_by = get_option( 'poll_ans_result_sortby' );
	switch ( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = 'desc' === get_option( 'poll_ans_result_sortorder' ) ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}

/**
 * Remove slashes from a string.
 *
 * @param string $string The string to remove slashes from.
 * @return string The string with slashes removed.
 */
if ( ! function_exists( 'removeslashes' ) ) {
	/**
	 * Remove slashes from a string.
	 *
	 * @param string $input The string to process.
	 * @return string The string with slashes removed.
	 */
	function removeslashes( $input ) {
		$input = implode( '', explode( '\\', $input ) );
		return stripslashes( trim( $input ) );
	}
}

/**
 * Display date/time selection elements for poll timestamp editing.
 *
 * @param int    $poll_timestamp The timestamp to edit.
 * @param string $fieldname      The base name for the form fields.
 * @param string $display        CSS display property value.
 * @return void
 */
function poll_timestamp( $poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block' ) {
	// Define localized month names.
	$months = array(
		1  => __( 'January' ),
		2  => __( 'February' ),
		3  => __( 'March' ),
		4  => __( 'April' ),
		5  => __( 'May' ),
		6  => __( 'June' ),
		7  => __( 'July' ),
		8  => __( 'August' ),
		9  => __( 'September' ),
		10 => __( 'October' ),
		11 => __( 'November' ),
		12 => __( 'December' ),
	);

	echo '<div id="' . esc_attr( $fieldname ) . '" style="display: ' . esc_attr( $display ) . '">' . "\n";

	// Day.
	$day = (int) gmdate( 'j', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_day">' . "\n";
	for ( $i = 1; $i <= 31; $i++ ) {
		echo '<option value="' . esc_attr( $i ) . '"' . selected( $day, $i, false ) . '>' . esc_html( $i ) . '</option>' . "\n";
	}
	echo '</select>&nbsp;&nbsp;' . "\n";

	// Month.
	$month_num = (int) gmdate( 'n', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_month">' . "\n";
	foreach ( $months as $i => $month_name ) {
		echo '<option value="' . esc_attr( $i ) . '"' . selected( $month_num, $i, false ) . '>' . esc_html( $month_name ) . '</option>' . "\n";
	}
	echo '</select>&nbsp;&nbsp;' . "\n";

	// Year.
	$poll_year = (int) gmdate( 'Y', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_year">' . "\n";
	for ( $i = 2000; $i <= ( $poll_year + 10 ); $i++ ) {
		echo '<option value="' . esc_attr( $i ) . '"' . selected( $poll_year, $i, false ) . '>' . esc_html( $i ) . '</option>' . "\n";
	}
	echo '</select>&nbsp;@' . "\n";

	// Time.
	echo '<span dir="ltr">' . "\n";

	// Hour.
	$hour = (int) gmdate( 'H', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_hour">' . "\n";
	for ( $i = 0; $i < 24; $i++ ) {
		printf(
			'<option value="%s"%s>%02s</option>' . "\n",
			esc_attr( $i ),
			selected( $hour, $i, false ),
			esc_html( $i )
		);
	}
	echo '</select>&nbsp;:' . "\n";

	// Minute.
	$minute = (int) gmdate( 'i', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_minute">' . "\n";
	for ( $i = 0; $i < 60; $i++ ) {
		printf(
			'<option value="%s"%s>%02d</option>' . "\n",
			esc_attr( $i ),
			selected( $minute, $i, false ),
			esc_html( $i )
		);
	}
	echo '</select>&nbsp;:' . "\n";

	// Second.
	$second = (int) gmdate( 's', $poll_timestamp );
	echo '<select name="' . esc_attr( $fieldname ) . '_second">' . "\n";
	for ( $i = 0; $i < 60; $i++ ) { // fixed to 0-59.
		printf(
			'<option value="%s"%s>%02d</option>' . "\n",
			esc_attr( $i ),
			selected( $second, $i, false ),
			esc_html( $i )
		);
	}
	echo '</select>' . "\n";

	echo '</span>' . "\n";
	echo '</div>' . "\n";
}
