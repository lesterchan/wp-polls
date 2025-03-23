<?php
/**
 * WP-Polls Editor Integration
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register editor buttons
 */
function poll_add_editor_buttons() {
    add_action('admin_footer-post-new.php', 'poll_footer_admin');
    add_action('admin_footer-post.php', 'poll_footer_admin');
    add_action('admin_footer-page-new.php', 'poll_footer_admin');
    add_action('admin_footer-page.php', 'poll_footer_admin');
    add_action('init', 'poll_tinymce_addbuttons');
}

// Register the integration with the editor
add_action('admin_init', 'poll_add_editor_buttons');
