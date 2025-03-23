<?php
/**
 * WP-Polls Admin Menu Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

### Function: Poll Administration Menu
add_action( 'admin_menu', 'poll_menu' );
function poll_menu() {
	add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php', '', 'dashicons-chart-bar' );

	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Manage Polls', 'wp-polls'), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Add Poll', 'wp-polls'), __( 'Add Poll', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-add.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Options', 'wp-polls'), __( 'Poll Options', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-options.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Templates', 'wp-polls'), __( 'Poll Templates', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-templates.php' );
}
