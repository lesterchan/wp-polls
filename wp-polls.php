<?php
/**
 * Plugin Name: WP-Polls
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
 * Version: 2.78.0
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * Text Domain: wp-polls
 *
 * @package WP-Polls
 */

/**
 * Copyright 2025 Lester Chan (email : lesterchan@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_POLLS_VERSION', '2.78.0' );
define( 'WP_POLLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_POLLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once WP_POLLS_PLUGIN_DIR . 'includes/functions.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/template-functions.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/shortcodes.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/scripts.php'; // Contains enqueue functions
require_once WP_POLLS_PLUGIN_DIR . 'includes/ajax.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/class-wp-polls-widget.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/database.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/admin.php';
require_once WP_POLLS_PLUGIN_DIR . 'includes/admin/menu.php';
// includes/admin/editor.php is no longer required to avoid function redeclaration issues
// TinyMCE functions are in scripts.php
// includes/enqueue.php is no longer required as scripts.php contains all the enqueue functions

/**
 * Create Text Domain For Translations.
 */
function polls_textdomain() {
	load_plugin_textdomain( 'wp-polls' );
}
add_action( 'plugins_loaded', 'polls_textdomain' );

// Define database tables.
polls_define_tables();

// Register shortcodes.
register_polls_shortcodes();

// Initialize widget.
add_action( 'widgets_init', 'widget_polls_init' );

// Register activation hook.
register_activation_hook( __FILE__, 'polls_activation' );

/**
 * Get Poll Question Based On Poll ID.
 *
 * @since 2.78.0
 *
 * @param int $poll_id The poll ID to retrieve the question for.
 * @return string The poll question text with HTML formatting preserved and slashes removed.
 */
if ( ! function_exists( 'get_poll_question' ) ) {
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}


/**
 * Get Poll Total Questions.
 *
 * @since 2.78.0
 *
 * @param bool $display Optional. Whether to display or return the count. Default true.
 * @return int|void The total number of poll questions if $display is false, otherwise outputs the count.
 * @global wpdb $wpdb WordPress database abstraction object.
 */
/**
 * Get total number of poll questions.
 *
 * @since 2.78.0
 *
 * @param bool $display Optional. Whether to display or return the count. Default true.
 * @return int|void The total number of poll questions if $display is false, otherwise outputs the count.
 * @global wpdb $wpdb WordPress database abstraction object.
 */
if ( ! function_exists( 'get_pollquestions' ) ) {
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
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll answers if $display is false.
 */
if ( ! function_exists( 'get_pollanswers' ) ) {
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
 * Get Poll Total Votes.
 *
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll votes if $display is false.
 */
if ( ! function_exists( 'get_pollvotes' ) ) {
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
 * @param int  $poll_id The poll ID.
 * @param bool $display Whether to display or return the count.
 * @return int|void The number of votes for the specified poll if $display is false.
 */
if ( ! function_exists( 'get_pollvotes_by_id' ) ) {
	function get_pollvotes_by_id( $poll_id, $display = true ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
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
 * @param bool $display Whether to display or return the count.
 * @return int|void The total number of poll voters if $display is false.
 */
if ( ! function_exists( 'get_pollvoters' ) ) {
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
 * @param int    $poll_id     The poll ID.
 * @param string $date_format The date format.
 * @param bool   $display     Whether to display or return the formatted date.
 * @return string|void The formatted date if $display is false.
 */
if ( ! function_exists( 'get_polltime' ) ) {
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$timestamp = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = gmdate( $date_format, $timestamp );
		if ( $display ) {
			echo esc_html( $formatted_date );
		} else {
			return $formatted_date;
		}
	}
}

// Initialize WP-Polls Widget.
add_action( 'widgets_init', 'widget_polls_init' );

/**
 * Remove slashes from a string.
 *
 * @param string $string The string to remove slashes from.
 * @return string The string with slashes removed.
 */
if ( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}
