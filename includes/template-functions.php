<?php
/**
 * WP-Polls Template Functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include modular template function files.
 * These files contain implementation of the main template functions.
 */

// Markup utility functions.
require_once dirname( __FILE__ ) . '/template-functions/markup.php';

// Poll voting form display.
require_once dirname( __FILE__ ) . '/template-functions/vote-display.php';

// Poll results display.
require_once dirname( __FILE__ ) . '/template-functions/results-display.php';

// Poll loader.
require_once dirname( __FILE__ ) . '/template-functions/poll-loader.php';
