<?php
/**
 * WP-Polls Hook Registrations
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Text domain is loaded in the main plugin file wp-polls.php.

// Define database tables.
polls_define_tables();

// Register shortcodes.
register_polls_shortcodes();

// Initialize widget.
add_action( 'widgets_init', 'widget_polls_init' );

// Register activation hook.
register_activation_hook( WP_POLLS_PLUGIN_DIR . 'wp-polls.php', 'polls_activation');

/**
 * Add WP-Polls General Stats To WP-Stats Page.
 *
 * @return void
 */
function polls_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'polls_page_admin_general_stats' );
	add_filter( 'wp_stats_page_plugins', 'polls_page_general_stats' );
}
add_action( 'plugins_loaded','polls_wp_stats' );

/**
 * Add WP-Polls General Stats to WP-Stats Page Options.
 *
 * @param string $content The existing content string.
 * @return string Modified content string.
 */
function polls_page_admin_general_stats( $content ) {
	$stats_display = get_option( 'stats_display' );
	if ( 1 === (int) $stats_display['polls'] ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" checked="checked" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	}
	return $content;
}

// Add WP-Polls General Stats To WP-Stats Page.
/**
 * Add polls statistics to the general stats page.
 *
 * @param string $content The existing content string.
 * @return string Modified content string.
 */
function polls_page_general_stats( $content ) {
	$stats_display = get_option( 'stats_display' );
	if ( 1 === (int) $stats_display['polls'] ) {
		$content .= '<p><strong>' . __( 'WP-Polls', 'wp-polls' ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		// translators: %s: Number of polls created.
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions( false ), 'wp-polls' ), number_format_i18n( get_pollquestions( false ) ) ) . '</li>' . "\n";
		// translators: %s: Number of poll answers.
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers( false ), 'wp-polls' ), number_format_i18n( get_pollanswers( false ) ) ) . '</li>' . "\n";
		// translators: %s: Number of votes cast.
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', get_pollvotes( false ), 'wp-polls' ), number_format_i18n( get_pollvotes( false ) ) ) . '</li>' . "\n";
		$content .= '</ul>' . "\n";
	}
	return $content;
}

// Add action hooks for AJAX functions.
add_action( 'wp_ajax_polls', 'vote_poll' );
add_action( 'wp_ajax_nopriv_polls', 'vote_poll' );
add_action( 'wp_ajax_polls-admin', 'manage_poll' );

// Cron Jobs.
add_action( 'polls_cron', 'cron_polls_status' );
