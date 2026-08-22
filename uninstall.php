<?php
/**
 * Uninstall WP-Polls: drops the three poll tables and deletes every option row.
 *
 * WordPress loads this file, and nothing else of the plugin, when the plugin is
 * deleted. Everything it does lives beside the installer it undoes, so the two
 * cannot drift apart and the work is reachable from the test suite.
 *
 * @package WP-Polls
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-wp-polls-template.php';
require_once __DIR__ . '/includes/class-wp-polls-options.php';
require_once __DIR__ . '/includes/class-wp-polls-install.php';

WP_Polls_Install::uninstall();
