<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

$option_names = array(
	'poll_template_voteheader'
	, 'poll_template_votebody'
	, 'poll_template_votefooter'
	, 'poll_template_resultheader'
	, 'poll_template_resultbody'
	, 'poll_template_resultbody2'
	, 'poll_template_resultfooter'
	, 'poll_template_resultfooter2'
	, 'poll_template_disable'
	, 'poll_template_error'
	, 'poll_currentpoll'
	, 'poll_latestpoll'
	, 'poll_archive_perpage'
	, 'poll_ans_sortby'
	, 'poll_ans_sortorder'
	, 'poll_ans_result_sortby'
	, 'poll_ans_result_sortorder'
	, 'poll_logging_method'
	, 'poll_allowtovote'
	, 'poll_archive_show'
	, 'poll_archive_url'
	, 'poll_bar'
	, 'poll_close'
	, 'poll_ajax_style'
	, 'poll_template_pollarchivelink'
	, 'widget_polls'
	, 'poll_archive_displaypoll'
	, 'poll_template_pollarchiveheader'
	, 'poll_template_pollarchivefooter'
	, 'poll_cookielog_expiry'
	, 'widget_polls-widget'
);


if ( is_multisite() ) {
	$ms_sites = wp_get_sites();

	if( 0 < sizeof( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			switch_to_blog( $ms_site['blog_id'] );
			if( sizeof( $option_names ) > 0 ) {
				foreach( $option_names as $option_name ) {
					delete_option( $option_name );
					plugin_uninstalled();
				}
			}
		}
	}

	restore_current_blog();
} else {
	if( sizeof( $option_names ) > 0 ) {
		foreach( $option_names as $option_name ) {
			delete_option( $option_name );
			plugin_uninstalled();
		}
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
	if( sizeof( $table_names ) > 0 ) {
		foreach( $table_names as $table_name ) {
			$table = $wpdb->prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}
}