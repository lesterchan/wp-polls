<?php
/**
 * Poll Backward Compatibility Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Backward_Compatibility Class
 *
 * Provides backward compatibility for legacy functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Backward_Compatibility {

	/**
	 * Initialize backward compatibility functionality.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function init() {
		// Only load if we're not in admin to avoid duplication of functions.
		if ( ! is_admin() ) {
			self::load_legacy_functions();
		}
	}

	/**
	 * Load legacy functions to maintain backward compatibility.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function load_legacy_functions() {
		if ( ! function_exists( 'display_poll_ajax' ) ) {
			/**
			 * Display poll with AJAX.
			 *
			 * @since 2.78.0
			 * @param int  $poll_id The poll ID.
			 * @param bool $display Whether to display or return the result.
			 * @return string|void Poll HTML if $display is false.
			 */
			function display_poll_ajax( $poll_id = 0, $display = true ) {
				return WP_Polls_Template::display_poll_ajax( $poll_id, $display );
			}
		}

		if ( ! function_exists( 'get_poll' ) ) {
			/**
			 * Get poll.
			 *
			 * @since 2.78.0
			 * @param int  $poll_id The poll ID.
			 * @param bool $display Whether to display or return the result.
			 * @return string|void Poll HTML if $display is false.
			 */
			function get_poll( $poll_id = 0, $display = true ) {
				return WP_Polls_Template::display_poll_ajax( $poll_id, $display );
			}
		}

		if ( ! function_exists( 'display_pollresult' ) ) {
			/**
			 * Display poll results.
			 *
			 * @since 2.78.0
			 * @param int  $poll_id The poll ID.
			 * @param bool $display Whether to display or return the result.
			 * @return string|void Poll results HTML if $display is false.
			 */
			function display_pollresult( $poll_id, $display = true ) {
				return WP_Polls_Template::display_pollresult( $poll_id, $display );
			}
		}

		if ( ! function_exists( 'display_pollvote' ) ) {
			/**
			 * Display poll voting form.
			 *
			 * @since 2.78.0
			 * @param int  $poll_id The poll ID.
			 * @param bool $display Whether to display or return the result.
			 * @return string|void Poll voting form HTML if $display is false.
			 */
			function display_pollvote( $poll_id, $display = true ) {
				return WP_Polls_Template::display_pollvote( $poll_id, $display );
			}
		}

		if ( ! function_exists( 'display_polls_archive' ) ) {
			/**
			 * Display polls archive.
			 *
			 * @since 2.78.0
			 * @param int  $archive_limit The number of polls to show.
			 * @param int  $page The page number.
			 * @param bool $display Whether to display or return the result.
			 * @return string|void Polls archive HTML if $display is false.
			 */
			function display_polls_archive( $archive_limit = 0, $page = 0, $display = true ) {
				return WP_Polls_Template::display_polls_archive( $archive_limit, $page, $display );
			}
		}
	}
}
