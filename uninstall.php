<?php
/**
 * Uninstall WP-Polls: drops the three poll tables and deletes every option row.
 *
 * The work itself is WP_Polls_Install::uninstall_site(); this file is the entry
 * point WordPress calls and the loop over sites on a network.
 *
 * @package WP-Polls
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

require_once __DIR__ . '/includes/class-wp-polls-template.php';
require_once __DIR__ . '/includes/class-wp-polls-options.php';
require_once __DIR__ . '/includes/class-wp-polls-install.php';

if ( is_multisite() ) {
	// get_sites(), not wp_get_sites(): that one is deprecated and capped at 100.
	//
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	// otherwise stop at the hundredth site and leave every site after it with
	// its options and tables intact while uninstall still reported success.
	//
	// 'fields' => 'ids' because the loop needs the ID and nothing else, so
	// there is no reason to hydrate a WP_Site object per site.
	$wp_polls_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_polls_site_ids as $wp_polls_site_id ) {
		// restore_current_blog() belongs inside the loop. switch_to_blog()
		// pushes onto a stack, so switching once per site and restoring once
		// afterwards leaves the stack unwound by every site but the first.
		switch_to_blog( (int) $wp_polls_site_id );
		WP_Polls_Install::uninstall_site();
		restore_current_blog();
	}

	unset( $wp_polls_site_ids, $wp_polls_site_id );
} else {
	WP_Polls_Install::uninstall_site();
}
