<?php
/**
 * Plugin Name: WP-Polls
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-polls
 * Domain Path: /languages
 *
 * @package WP-Polls
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Version
define( 'WP_POLLS_VERSION', '3.0.0' );
define( 'WP_POLLS_MAIN_FILE', __FILE__ );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once __DIR__ . '/includes/class-polls-templates.php';
require_once __DIR__ . '/includes/class-polls-options.php';
require_once __DIR__ . '/includes/class-polls-settings.php';
require_once __DIR__ . '/includes/class-polls-widget.php';
require_once __DIR__ . '/includes/class-polls-install.php';
require_once __DIR__ . '/includes/class-polls-vote.php';
require_once __DIR__ . '/includes/class-polls-display.php';
require_once __DIR__ . '/includes/class-polls-admin.php';
Polls_Install::init();
Polls_Vote::init();
Polls_Display::init();
Polls_Admin::init();
Polls_Settings::init();


// Create Text Domain For Translations
add_action( 'plugins_loaded', 'polls_textdomain' );
function polls_textdomain() {
	load_plugin_textdomain( 'wp-polls' );
}


// Polls Table Name
// Registering the names in $wpdb->tables is what makes them survive
// switch_to_blog(): wpdb::set_blog_id() rebuilds every registered table name
// against the new prefix. A bare assignment keeps pointing at the site that
// happened to be current when this file loaded.
global $wpdb;
foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $poll_table ) {
	if ( ! in_array( $poll_table, $wpdb->tables, true ) ) {
		$wpdb->tables[] = $poll_table;
	}
	$wpdb->$poll_table = $wpdb->prefix . $poll_table;
}
unset( $poll_table );


// Function: Enqueue Polls JavaScripts/CSS
add_action( 'wp_enqueue_scripts', 'poll_scripts' );
function poll_scripts() {
	if ( @file_exists( get_stylesheet_directory() . '/polls-css.css' ) ) {
		wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/polls-css.css', false, WP_POLLS_VERSION, 'all' );
	} else {
		wp_enqueue_style( 'wp-polls', plugins_url( 'wp-polls/polls-css.css' ), false, WP_POLLS_VERSION, 'all' );
	}
	if ( is_rtl() ) {
		if ( @file_exists( get_stylesheet_directory() . '/polls-css-rtl.css' ) ) {
			wp_enqueue_style( 'wp-polls-rtl', get_stylesheet_directory_uri() . '/polls-css-rtl.css', false, WP_POLLS_VERSION, 'all' );
		} else {
			wp_enqueue_style( 'wp-polls-rtl', plugins_url( 'wp-polls/polls-css-rtl.css' ), false, WP_POLLS_VERSION, 'all' );
		}
	}
	$pollbar = Polls_Options::get( 'bar' );
	// This lands in an inline <style> block on every front end page, so never
	// trust the stored values even though only 'manage_polls' can set them.
	$pollbar_height     = (int) $pollbar['height'];
	$pollbar_background = _polls_sanitize_hex_color( $pollbar['background'] );
	$pollbar_border     = _polls_sanitize_hex_color( $pollbar['border'] );
	if ( $pollbar['style'] === 'use_css' ) {
		$pollbar_css  = '.wp-polls .pollbar {' . "\n";
		$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
		$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
		$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'background: #' . $pollbar_background . ';' . "\n";
		$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
		$pollbar_css .= '}' . "\n";
	} else {
		$pollbar_css  = '.wp-polls .pollbar {' . "\n";
		$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
		$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
		$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'background-image: url(\'' . esc_url( plugins_url( 'wp-polls/images/' . $pollbar['style'] . '/pollbg.gif' ) ) . '\');' . "\n";
		$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
		$pollbar_css .= '}' . "\n";
	}
	wp_add_inline_style( 'wp-polls', $pollbar_css );
	$poll_ajax_style = Polls_Options::get( 'ajax' );
	wp_enqueue_script( 'wp-polls', plugins_url( 'wp-polls/polls-js.js' ), array(), WP_POLLS_VERSION, true );
	wp_localize_script(
		'wp-polls',
		'pollsL10n',
		array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'text_wait'     => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
			'text_valid'    => __( 'Please choose a valid poll answer.', 'wp-polls' ),
			'text_multiple' => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
			'show_loading'  => (int) $poll_ajax_style['loading'],
			'show_fading'   => (int) $poll_ajax_style['fading'],
		)
	);
}


// Function: Short Code For Inserting Polls Archive Into Page
add_shortcode( 'page_polls', 'poll_page_shortcode' );
function poll_page_shortcode( $atts ) {
	return Polls_Display::polls_archive();
}


// Function: Short Code For Inserting Polls Into Posts
add_shortcode( 'poll', 'poll_shortcode' );
function poll_shortcode( $atts ) {
	$attributes = shortcode_atts(
		array(
			'id'   => 0,
			'type' => 'vote',
		),
		$atts
	);
	if ( ! is_feed() ) {
		$id = (int) $attributes['id'];

		// To maintain backward compatibility with [poll=1]. Props @tz-ua
		if ( ! $id && isset( $atts[0] ) ) {
			$id = (int) trim( $atts[0], '="\'' );
		}

		if ( $attributes['type'] === 'vote' ) {
			return Polls_Display::get_poll( $id, false );
		} elseif ( $attributes['type'] === 'result' ) {
			return Polls_Display::display_pollresult( $id );
		}
	} else {
		return __( 'Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls' );
	}
}


// Function: Get Poll Question Based On Poll ID
if ( ! function_exists( 'get_poll_question' ) ) {
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id       = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}


// Function: Get Poll Total Questions
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


// Function: Get Poll Total Answers
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


// Function: Get Poll Total Votes
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

// Function: Get Poll Votes Based on Poll ID
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


// Function: Get Poll Total Voters
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

// Function: Get Poll Time Based on Poll ID and Date Format
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


// Function: Place Cron
function cron_polls_place() {
	wp_clear_scheduled_hook( 'polls_cron' );
	if ( ! wp_next_scheduled( 'polls_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'polls_cron' );
	}
}

// Funcion: Check All Polls Status To Check If It Expires
add_action( 'polls_cron', 'cron_polls_status' );
function cron_polls_status() {
	global $wpdb;
	// Close Poll
	$close_polls = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < '" . current_time( 'timestamp' ) . "' AND pollq_expiry != 0 AND pollq_active != 0" );
	// Open Future Polls
	$active_polls = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= '" . current_time( 'timestamp' ) . "' AND pollq_active = -1" );
	// Update Latest Poll If Future Poll Is Opened
	if ( $active_polls ) {
		$update_latestpoll = Polls_Options::set( 'latest_poll', polls_latest_id() );
	}
	return;
}


// Funcion: Get Latest Poll ID
function polls_latest_id() {
	global $wpdb;
	$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1" );
	return (int) $poll_id;
}


// Class: WP-Polls Widget
// Function: Init WP-Polls Widget
add_action( 'widgets_init', 'widget_polls_init' );
function widget_polls_init() {
	polls_textdomain();
	register_widget( 'Polls_Widget' );
}

if ( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}


// Function: Sanitize A 3 Or 6 Digit Hex Colour Stored Without Its Leading '#'
function _polls_sanitize_hex_color( $color ) {
	$color = substr( trim( (string) $color ), 0, 6 );

	if ( ! preg_match( '/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
		return '000000';
	}

	return $color;
}
