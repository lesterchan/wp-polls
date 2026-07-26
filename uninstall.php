<?php
/**
 * Uninstall WP-Polls: drops the three poll tables and deletes every option row.
 *
 * @package WP-Polls
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

require_once __DIR__ . '/includes/class-polls-templates.php';
require_once __DIR__ . '/includes/class-polls-options.php';
require_once __DIR__ . '/includes/class-polls-install.php';

// From 3.0.0 the settings are a single row. The pre-3.0.0 names are still
// listed because an install that was never loaded after upgrading - deleted
// straight from the plugins screen - still has them, and they would otherwise
// be orphaned forever. The list is taken from Polls_Options so it cannot
// drift from the migration's idea of which rows belong to the plugin.
$option_names = array_merge(
	array( Polls_Options::OPTION, 'poll_version', Polls_Install::DB_VERSION_OPTION ),
	array_keys( Polls_Options::legacy_map() ),
	Polls_Options::legacy_extra_rows(),
	array( 'widget_polls', 'widget_polls-widget' )
);


if ( is_multisite() ) {
	// wp_get_sites() was removed in WP 5.1; the floor is 6.0.
	$ms_sites = get_sites( array( 'number' => 0 ) );

	if ( 0 < count( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			switch_to_blog( (int) $ms_site->blog_id );
			if ( count( $option_names ) > 0 ) {
				foreach ( $option_names as $option_name ) {
					delete_option( $option_name );
					plugin_uninstalled();
				}
			}
		}
	}

	restore_current_blog();
} elseif ( count( $option_names ) > 0 ) {
	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
		plugin_uninstalled();
	}
}

/**
 * Delete plugin table when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
	global $wpdb;

	$table_names = array( 'pollsq', 'pollsa', 'pollsip' );
	if ( count( $table_names ) > 0 ) {
		foreach ( $table_names as $table_name ) {
			$table = $wpdb->prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}
}
