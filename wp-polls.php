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
require_once __DIR__ . '/includes/class-polls-core.php';
require_once __DIR__ . '/includes/template-tags.php';
Polls_Install::init();
Polls_Vote::init();
Polls_Display::init();
Polls_Admin::init();
Polls_Core::init();
Polls_Settings::init();


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
