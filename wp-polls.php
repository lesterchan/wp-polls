<?php
/**
 * Plugin Name: WP-Polls
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
 * Version: 3.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
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
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Versions. WP_POLLS_VERSION is the plugin's own; WP_POLLS_DB_VERSION is the
// schema counter, bumped whenever the CREATE TABLE statements or the indexes
// change so the schema work runs once on the next load.
define( 'WP_POLLS_VERSION', '3.0.0' );
define( 'WP_POLLS_DB_VERSION', '1' );

// Identity and paths. The paths are derived from this file so the plugin keeps
// working if its directory is renamed; nothing user visible depends on that
// name any more, because every admin page slug is a literal.
define( 'WP_POLLS_SLUG', 'wp-polls' );
define( 'WP_POLLS_MAIN_FILE', __FILE__ );
define( 'WP_POLLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_POLLS_URL', plugin_dir_url( __FILE__ ) );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once __DIR__ . '/includes/class-wp-polls-template.php';
require_once __DIR__ . '/includes/class-wp-polls-options.php';
require_once __DIR__ . '/includes/class-wp-polls-settings.php';
require_once __DIR__ . '/includes/class-wp-polls-widget.php';
require_once __DIR__ . '/includes/class-wp-polls-install.php';
require_once __DIR__ . '/includes/class-wp-polls-vote.php';
require_once __DIR__ . '/includes/class-wp-polls-display.php';
require_once __DIR__ . '/includes/class-wp-polls-admin.php';
require_once __DIR__ . '/includes/class-wp-polls-wpstats.php';
require_once __DIR__ . '/includes/class-wp-polls.php';
require_once __DIR__ . '/includes/template-tags.php';
WP_Polls_Install::init();
WP_Polls_Vote::init();
WP_Polls_Display::init();
WP_Polls_Admin::init();
WP_Polls_WPStats::init();
WP_Polls::init();
WP_Polls_Settings::init();


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
