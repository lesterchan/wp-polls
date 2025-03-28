<?php
/**
 * WP-Polls Admin Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Menu functions are now in includes/admin/menu.php

// Stats functions are now in includes/hooks.php

// Register admin hooks
add_action('admin_menu', 'poll_menu');
// Hooks for polls_wp_stats are now in includes/hooks.php
