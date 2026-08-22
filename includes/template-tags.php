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

if ( ! function_exists( 'get_poll_question' ) ) {
	/**
	 * Template tag: the question of one poll.
	 *
	 * @param int $poll_id Poll ID.
	 *
	 * @return string
	 */
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id       = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}

if ( ! function_exists( 'get_pollquestions' ) ) {
	/**
	 * Template tag: how many polls there are.
	 *
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return int|void
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

if ( ! function_exists( 'get_pollanswers' ) ) {
	/**
	 * Template tag: how many poll answers there are, across every poll.
	 *
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return int|void
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

if ( ! function_exists( 'get_pollvotes' ) ) {
	/**
	 * Template tag: how many votes have been cast, across every poll.
	 *
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return int|void
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

if ( ! function_exists( 'get_pollvotes_by_id' ) ) {
	/**
	 * Template tag: how many votes one poll has received.
	 *
	 * @param int  $poll_id Poll ID.
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return int|void
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

if ( ! function_exists( 'get_pollvoters' ) ) {
	/**
	 * Template tag: how many voters have taken part, across every poll.
	 *
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return int|void
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

if ( ! function_exists( 'get_polltime' ) ) {
	/**
	 * Template tag: when one poll was created.
	 *
	 * @param int    $poll_id     Poll ID.
	 * @param string $date_format PHP date format for the answer.
	 * @param bool   $display     Echo when true, return when false.
	 *
	 * @return string|void
	 */
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id        = (int) $poll_id;
		$timestamp      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = gmdate( $date_format, $timestamp );
		if ( $display ) {
			echo esc_html( $formatted_date );
		} else {
			return $formatted_date;
		}
	}
}

if ( ! function_exists( 'removeslashes' ) ) {
	/**
	 * Strip every backslash a stored value accumulated, however many times it
	 * was slashed on the way in.
	 *
	 * @param string $text Stored text.
	 *
	 * @return string
	 */
	function removeslashes( $text ) {
		$text = implode( '', explode( '\\', $text ) );
		return stripslashes( trim( $text ) );
	}
}

if ( ! function_exists( 'get_poll' ) ) {
	/**
	 * Template tag: render a poll.
	 *
	 * @param int  $poll_id Poll ID. 0 for the current poll, -1 to disable, -2 for random.
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return string|void
	 */
	function get_poll( $poll_id = 0, $display = true ) {
		return WP_Polls_Display::get_poll( $poll_id, $display );
	}
}

if ( ! function_exists( 'display_polls_archive_link' ) ) {
	/**
	 * Template tag: render the link to the polls archive.
	 *
	 * @param bool $display Echo when true, return when false.
	 *
	 * @return string|void
	 */
	function display_polls_archive_link( $display = true ) {
		return WP_Polls_Display::display_polls_archive_link( $display );
	}
}

if ( ! function_exists( 'in_pollarchive' ) ) {
	/**
	 * Template tag: are we on the polls archive page?
	 *
	 * @return bool
	 */
	function in_pollarchive() {
		return WP_Polls_Display::in_pollarchive();
	}
}

if ( ! function_exists( 'vote_poll' ) ) {
	/**
	 * The AJAX vote endpoint.
	 *
	 * Kept as a global function because the documented theme snippet guards on
	 * function_exists( 'vote_poll' ); removing it would silently hide every
	 * poll in every theme using that snippet.
	 *
	 * @return void
	 */
	function vote_poll() {
		WP_Polls_Vote::ajax_vote();
	}
}
