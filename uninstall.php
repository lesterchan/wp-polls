<?php
/**
 * Uninstall WP-Polls: drops the three poll tables and deletes every option row.
 *
 * @package WP-Polls
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

require_once __DIR__ . '/includes/class-wp-polls-template.php';
require_once __DIR__ . '/includes/class-wp-polls-options.php';
require_once __DIR__ . '/includes/class-wp-polls-install.php';

// From 3.0.0 the settings are a single row. The pre-3.0.0 names are still
// listed because an install that was never loaded after upgrading - deleted
// straight from the plugins screen - still has them, and they would otherwise
// be orphaned forever. The list is taken from WP_Polls_Options so it cannot
// drift from the migration's idea of which rows belong to the plugin.
$option_names = array_merge(
	array( WP_Polls_Options::OPTION, 'poll_version', WP_Polls_Install::DB_VERSION_OPTION ),
	array_keys( WP_Polls_Options::legacy_map() ),
	WP_Polls_Options::legacy_extra_rows(),
	array( 'widget_polls', 'widget_polls-widget' )
);

if ( is_multisite() ) {
	// wp_get_sites() was removed in WP 5.1; the floor is 6.0.
	//
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	// otherwise stop at the hundredth site and leave every site after it with
	// its options and tables intact while uninstall still reported success.
	//
	// 'fields' => 'ids' because the loop needs the ID and nothing else, so
	// there is no reason to hydrate a WP_Site object per site.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		// restore_current_blog() belongs inside the loop. switch_to_blog()
		// pushes onto a stack, so switching once per site and restoring once
		// afterwards leaves the stack unwound by every site but the first.
		switch_to_blog( (int) $site_id );
		wp_polls_uninstall_site( $option_names );
		restore_current_blog();
	}
} else {
	wp_polls_uninstall_site( $option_names );
}

/**
 * Remove every option row and drop the three tables for the current site.
 *
 * Before 3.0.0 the table drop was called from inside the loop over option
 * names, so it ran once per option rather than once per site - 36 times over,
 * issuing three DROP TABLE statements each.
 *
 * @param array $option_names Option rows belonging to the plugin.
 *
 * @return void
 */
function wp_polls_uninstall_site( $option_names ) {
	global $wpdb;

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	// $wpdb->prefix rather than the registered $wpdb->pollsq: uninstall.php
	// does not load the main plugin file, so nothing has registered the table
	// names. switch_to_blog() updates the prefix, so this stays correct per
	// site on multisite.
	foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $table_name ) {
		$table = $wpdb->prefix . $table_name;
		$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers cannot be bound, and the name is built from $wpdb->prefix and a literal.
	}
}
