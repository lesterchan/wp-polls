<?php
/**
 * WP-Polls Template Markup Functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces placeholders in the markup template with given variables.
 *
 * @param string $template  The markup template.
 * @param mixed  $object    The data object.
 * @param array  $variables An associative array where keys are placeholders and values are replacements.
 * @return string The processed template with placeholders replaced by their corresponding values.
 */
function poll_template_vote_markup( $template, $object, $variables ) {
	return str_replace( array_keys( $variables ), array_values( $variables ), $template );
}
