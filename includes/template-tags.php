<?php
/**
 * Public template tags.
 *
 * These are the plugin's API. Their names and signatures are relied on by
 * themes and must not change. The tags that simply forward to a class are
 * deliberately not marked deprecated: the readme documents them as the way to
 * use WP-Polls from a theme, so deprecating them would contradict the
 * documentation rather than guide anyone anywhere useful.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

// Function: Get Poll Question Based On Poll ID.
if ( ! function_exists( 'get_poll_question' ) ) {
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id       = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}

// Function: Get Poll Total Questions.
if ( ! function_exists( 'get_pollquestions' ) ) {
	function get_pollquestions( $display = true ) {
		global $wpdb;
		$totalpollq = (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalpollq;
		} else {
			return $totalpollq;
		}
	}
}

// Function: Get Poll Total Answers.
if ( ! function_exists( 'get_pollanswers' ) ) {
	function get_pollanswers( $display = true ) {
		global $wpdb;
		$totalpolla = (int) $wpdb->get_var( "SELECT COUNT(polla_aid) FROM $wpdb->pollsa" );
		if ( $display ) {
			echo $totalpolla;
		} else {
			return $totalpolla;
		}
	}
}

// Function: Get Poll Total Votes.
if ( ! function_exists( 'get_pollvotes' ) ) {
	function get_pollvotes( $display = true ) {
		global $wpdb;
		$totalvotes = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

// Function: Get Poll Votes Based on Poll ID.
if ( ! function_exists( 'get_pollvotes_by_id' ) ) {
	function get_pollvotes_by_id( $poll_id, $display = true ) {
		global $wpdb;
		$poll_id    = (int) $poll_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		if ( $display ) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

// Function: Get Poll Total Voters.
if ( ! function_exists( 'get_pollvoters' ) ) {
	function get_pollvoters( $display = true ) {
		global $wpdb;
		$totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalvoters;
		} else {
			return $totalvoters;
		}
	}
}

// Function: Get Poll Time Based on Poll ID and Date Format.
if ( ! function_exists( 'get_polltime' ) ) {
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id        = (int) $poll_id;
		$timestamp      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = date( $date_format, $timestamp );
		if ( $display ) {
			echo $formatted_date;
		} else {
			return $formatted_date;
		}
	}
}

if ( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}

/**
 * Template tag: render a poll.
 *
 * @param int  $poll_id Poll ID. 0 for the current poll, -1 to disable, -2 for random.
 * @param bool $display Echo when true, return when false.
 * @return string|void
 */
if ( ! function_exists( 'get_poll' ) ) {
	function get_poll( $poll_id = 0, $display = true ) {
		return Polls_Display::get_poll( $poll_id, $display );
	}
}

/**
 * Template tag: render the link to the polls archive.
 *
 * @param bool $display Echo when true, return when false.
 * @return string|void
 */
if ( ! function_exists( 'display_polls_archive_link' ) ) {
	function display_polls_archive_link( $display = true ) {
		return Polls_Display::display_polls_archive_link( $display );
	}
}

/**
 * Template tag: are we on the polls archive page?
 *
 * @return bool
 */
if ( ! function_exists( 'in_pollarchive' ) ) {
	function in_pollarchive() {
		return Polls_Display::in_pollarchive();
	}
}

/**
 * The AJAX vote endpoint.
 *
 * Kept as a global function because the documented theme snippet guards on
 * function_exists( 'vote_poll' ); removing it would silently hide every poll
 * in every theme using that snippet.
 *
 * @return void
 */
if ( ! function_exists( 'vote_poll' ) ) {
	function vote_poll() {
		Polls_Vote::vote_poll();
	}
}
