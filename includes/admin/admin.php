<?php
/**
 * WP-Polls Admin Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Function: Poll Administration Menu
 */
function poll_menu() {
	add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php', '', 'dashicons-chart-bar' );

	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Manage Polls', 'wp-polls'), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Add Poll', 'wp-polls'), __( 'Add Poll', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-add.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Options', 'wp-polls'), __( 'Poll Options', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-options.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Templates', 'wp-polls'), __( 'Poll Templates', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-templates.php' );
}

/**
 * Function: Plug Into WP-Stats
 */
function polls_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'polls_page_admin_general_stats' );
	add_filter( 'wp_stats_page_plugins', 'polls_page_general_stats' );
}

/**
 * Function: Add WP-Polls General Stats To WP-Stats Page Options
 * 
 * @param string $content Stats page content
 * @return string Modified stats page content
 */
function polls_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if( (int) $stats_display['polls'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" checked="checked" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	}
	return $content;
}

/**
 * Function: Add WP-Polls General Stats To WP-Stats Page
 * 
 * @param string $content Stats page content
 * @return string Modified stats page content
 */
function polls_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if( (int)  $stats_display['polls'] === 1) {
		$content .= '<p><strong>'.__('WP-Polls', 'wp-polls').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions(false), 'wp-polls'), number_format_i18n(get_pollquestions(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers(false), 'wp-polls'), number_format_i18n(get_pollanswers(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', get_pollvotes(false), 'wp-polls'), number_format_i18n(get_pollvotes(false))).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}

// Register admin hooks
add_action('admin_menu', 'poll_menu');
add_action('plugins_loaded', 'polls_wp_stats');
