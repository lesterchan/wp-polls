<?php
/**
 * WP-Polls Shortcode Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode for displaying polls archive
 *
 * @param array $atts Shortcode attributes
 * @return string Poll archive output
 */
function poll_page_shortcode($atts) {
	return polls_archive();
}

/**
 * Shortcode for displaying a single poll
 *
 * @param array $atts Shortcode attributes
 * @return string Poll output or note for feed readers
 */
function poll_shortcode( $atts ) {
	$attributes = shortcode_atts( array( 'id' => 0, 'type' => 'vote' ), $atts );
	if( ! is_feed() ) {
		$id = (int) $attributes['id'];

		// To maintain backward compatibility with [poll=1]. Props @tz-ua
		if( ! $id && isset( $atts[0] ) ) {
			$id = (int) trim( $atts[0], '="\'' );
		}

		if( $attributes['type'] === 'vote' ) {
			return get_poll( $id, false );
		} elseif( $attributes['type'] === 'result' ) {
			return display_pollresult( $id );
		}
	} else {
		return __( 'Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls' );
	}
}

/**
 * Register WP-Polls shortcodes
 */
function register_polls_shortcodes() {
    add_shortcode('page_polls', 'poll_page_shortcode');
    add_shortcode('poll', 'poll_shortcode');
}
