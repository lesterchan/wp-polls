<?php
/**
 * WP-Polls Core Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

### Function: Check Who Is Allow To Vote
function check_allowtovote() {
	global $user_ID;
	$user_ID = (int) $user_ID;
	$allow_to_vote = (int) get_option( 'poll_allowtovote' );
	switch($allow_to_vote) {
		// Guests Only
		case 0:
			if($user_ID > 0) {
				return false;
			}
			return true;
			break;
		// Registered Users Only
		case 1:
			if($user_ID === 0) {
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


### Funcrion: Check Voted By Cookie Or IP
function check_voted($poll_id) {
	$poll_logging_method = (int) get_option( 'poll_logging_method' );
	switch($poll_logging_method) {
		// Do Not Log
		case 0:
			return 0;
			break;
		// Logged By Cookie
		case 1:
			return check_voted_cookie($poll_id);
			break;
		// Logged By IP
		case 2:
			return check_voted_ip($poll_id);
			break;
		// Logged By Cookie And IP
		case 3:
			$check_voted_cookie = check_voted_cookie($poll_id);
			if(!empty($check_voted_cookie)) {
				return $check_voted_cookie;
			}
			return check_voted_ip($poll_id);
			break;
		// Logged By Username
		case 4:
			return check_voted_username($poll_id);
			break;
	}
}


### Function: Check Voted By Cookie
function check_voted_cookie( $poll_id ) {
	$get_voted_aids = 0;
	if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
		$get_voted_aids = explode( ',', $_COOKIE[ 'voted_' . $poll_id ] );
		$get_voted_aids = array_map( 'intval', array_map( 'sanitize_key', $get_voted_aids ) );
	}
	return $get_voted_aids;
}


### Function: Check Voted By IP
function check_voted_ip( $poll_id ) {
	global $wpdb;
	$log_expiry = (int) get_option( 'poll_cookielog_expiry' );
	$log_expiry_sql = '';
	if( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time('timestamp') . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check IP From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s", $poll_id, poll_get_ipaddress() ) . $log_expiry_sql );
	if( $get_voted_aids ) {
		return $get_voted_aids;
	}

	return 0;
}


### Function: Check Voted By Username
function check_voted_username($poll_id) {
	global $wpdb, $user_ID;
	// Check IP If User Is Guest
	if ( ! is_user_logged_in() ) {
		return 1;
	}
	$pollsip_userid = (int) $user_ID;
	$log_expiry = (int) get_option( 'poll_cookielog_expiry' );
	$log_expiry_sql = '';
	if( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time('timestamp') . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check User ID From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d", $poll_id, $pollsip_userid ) . $log_expiry_sql );
	if($get_voted_aids) {
		return $get_voted_aids;
	} else {
		return 0;
	}
}

### Function: Get Poll Question Based On Poll ID
if(!function_exists('get_poll_question')) {
	function get_poll_question($poll_id) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}


### Function: Get Poll Total Questions
if(!function_exists('get_pollquestions')) {
	function get_pollquestions($display = true) {
		global $wpdb;
		$totalpollq = (int) $wpdb->get_var("SELECT COUNT(pollq_id) FROM $wpdb->pollsq");
		if($display) {
			echo $totalpollq;
		} else {
			return $totalpollq;
		}
	}
}


### Function: Get Poll Total Answers
if(!function_exists('get_pollanswers')) {
	function get_pollanswers($display = true) {
		global $wpdb;
		$totalpolla = (int) $wpdb->get_var("SELECT COUNT(polla_aid) FROM $wpdb->pollsa");
		if($display) {
			echo $totalpolla;
		} else {
			return $totalpolla;
		}
	}
}


### Function: Get Poll Total Votes
if(!function_exists('get_pollvotes')) {
	function get_pollvotes($display = true) {
		global $wpdb;
		$totalvotes = (int) $wpdb->get_var("SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq");
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

### Function: Get Poll Votes Based on Poll ID
if(!function_exists('get_pollvotes_by_id')) {
	function get_pollvotes_by_id($poll_id, $display = true) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare("SELECT pollq_totalvotes FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id));
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}


### Function: Get Poll Total Voters
if(!function_exists('get_pollvoters')) {
	function get_pollvoters($display = true) {
		global $wpdb;
		$totalvoters = (int) $wpdb->get_var("SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq");
		if($display) {
			echo $totalvoters;
		} else {
			return $totalvoters;
		}
	}
}

### Function: Get Poll Time Based on Poll ID and Date Format
if ( ! function_exists( 'get_polltime' ) ) {
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$timestamp = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = date( $date_format, $timestamp );
		if ( $display ) {
			echo $formatted_date;
		} else {
			return $formatted_date;
		}
	}
}


### Function: Check Voted To Get Voted Answer
function check_voted_multiple($poll_id, $polls_ips) {
	if(!empty($_COOKIE["voted_$poll_id"])) {
		return explode(',', $_COOKIE["voted_$poll_id"]);
	} else {
		if($polls_ips) {
			return $polls_ips;
		} else {
			return array();
		}
	}
}

### Function: Get IP Address
function poll_get_raw_ipaddress() {
	$ip = esc_attr( $_SERVER['REMOTE_ADDR'] );
	$poll_options = get_option( 'poll_options' );
	if ( ! empty( $poll_options ) && ! empty( $poll_options['ip_header'] ) && ! empty( $_SERVER[ $poll_options['ip_header'] ] ) ) {
		$ip = esc_attr( $_SERVER[ $poll_options['ip_header'] ] );
	}

	return $ip;
}

function poll_get_ipaddress() {
	return apply_filters( 'wp_polls_ipaddress', wp_hash( poll_get_raw_ipaddress() ) );
}

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

### Function: Polls Archive Link
function polls_archive_link($page) {
	$polls_archive_url = get_option('poll_archive_url');
	if($page > 0) {
		if(strpos($polls_archive_url, '?') !== false) {
			$polls_archive_url = "$polls_archive_url&amp;poll_page=$page";
		} else {
			$polls_archive_url = "$polls_archive_url?poll_page=$page";
		}
	}
	return $polls_archive_url;
}

### Function: Displays Polls Archive Link
function display_polls_archive_link($display = true) {
	$template_pollarchivelink = removeslashes(get_option('poll_template_pollarchivelink'));
	$template_pollarchivelink = str_replace("%POLL_ARCHIVE_URL%", get_option('poll_archive_url'), $template_pollarchivelink);
	if($display) {
		echo $template_pollarchivelink;
	} else{
		return $template_pollarchivelink;
	}
}

### Check If In Poll Archive Page
function in_pollarchive() {
	$poll_archive_url = get_option('poll_archive_url');
	$poll_archive_url_array = explode('/', $poll_archive_url);
	$poll_archive_url = $poll_archive_url_array[count($poll_archive_url_array)-1];
	if(empty($poll_archive_url)) {
		$poll_archive_url = $poll_archive_url_array[count($poll_archive_url_array)-2];
	}
	$current_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
	if ( strpos( $current_url, strval( $poll_archive_url ) ) === false ) {
		return false;
	}

	return true;
}

### Funcion: Get Latest Poll ID
function polls_latest_id() {
	global $wpdb;
	$poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1");
	return (int) $poll_id;
}

### Function: Place Cron
function cron_polls_place() {
	wp_clear_scheduled_hook('polls_cron');
	if (!wp_next_scheduled('polls_cron')) {
		wp_schedule_event(time(), 'hourly', 'polls_cron');
	}
}

### Funcion: Check All Polls Status To Check If It Expires
function cron_polls_status() {
	global $wpdb;
	// Close Poll
	$close_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < '".current_time('timestamp')."' AND pollq_expiry != 0 AND pollq_active != 0");
	// Open Future Polls
	$active_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= '".current_time('timestamp')."' AND pollq_active = -1");
	// Update Latest Poll If Future Poll Is Opened
	if($active_polls) {
		$update_latestpoll = update_option('poll_latestpoll', polls_latest_id());
	}
	return;
}

function polls_acquire_lock( $poll_id ) {
	$fp = fopen( polls_lock_file( $poll_id ), 'w+' );

	if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
		return false;
	}

	ftruncate( $fp, 0 );
	fwrite( $fp, microtime( true ) );

	return $fp;
}

function polls_release_lock( $fp, $poll_id ) {
	if ( is_resource( $fp ) ) {
		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		unlink( polls_lock_file( $poll_id ) );

		return true;
	}

	return false;
}

function polls_lock_file( $poll_id ) {
	return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
}

function _polls_get_ans_sort() {
	$order_by = get_option( 'poll_ans_sortby' );
	switch( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = get_option( 'poll_ans_sortorder' ) === 'desc' ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}

function _polls_get_ans_result_sort() {
	$order_by = get_option( 'poll_ans_result_sortby' );
	switch( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = get_option( 'poll_ans_result_sortorder' ) === 'desc' ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}

if( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}

// Edit Timestamp Options
function poll_timestamp($poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block') {
	global $month;
	echo '<div id="'.$fieldname.'" style="display: '.$display.'">'."\n";
	$day = (int) gmdate('j', $poll_timestamp);
	echo '<select name="'.$fieldname.'_day" size="1">'."\n";
	for($i = 1; $i <=31; $i++) {
		if($day === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$month2 = (int) gmdate('n', $poll_timestamp);
	echo '<select name="'.$fieldname.'_month" size="1">'."\n";
	for($i = 1; $i <= 12; $i++) {
		if ($i < 10) {
			$ii = '0'.$i;
		} else {
			$ii = $i;
		}
		if($month2 === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$month[$ii]</option>\n";
		} else {
			echo "<option value=\"$i\">$month[$ii]</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$year = (int) gmdate('Y', $poll_timestamp);
	echo '<select name="'.$fieldname.'_year" size="1">'."\n";
	for($i = 2000; $i <= ($year+10); $i++) {
		if($year === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;@'."\n";
	echo '<span dir="ltr">'."\n";
	$hour = (int) gmdate('H', $poll_timestamp);
	echo '<select name="'.$fieldname.'_hour" size="1">'."\n";
	for($i = 0; $i < 24; $i++) {
		if($hour === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;:'."\n";
	$minute = (int) gmdate('i', $poll_timestamp);
	echo '<select name="'.$fieldname.'_minute" size="1">'."\n";
	for($i = 0; $i < 60; $i++) {
		if($minute === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}

	echo '</select>&nbsp;:'."\n";
	$second = (int) gmdate('s', $poll_timestamp);
	echo '<select name="'.$fieldname.'_second" size="1">'."\n";
	for($i = 0; $i <= 60; $i++) {
		if($second === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>'."\n";
	echo '</span>'."\n";
	echo '</div>'."\n";
}
