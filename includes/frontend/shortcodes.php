<?php
/**
 * Frontend Shortcodes
 *
 * @package WP-Polls
 * @since 2.79.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register frontend shortcodes.
 *
 * @since 2.79.0
 * @return void
 */
function wp_polls_register_frontend_shortcodes() {
    // These shortcodes are already registered in includes/shortcodes.php:
    // - [poll] - Display a specific poll
    // - [polls_archives] - Display poll archive
    // - [polls_list] - Display list of polls
    
    // Register our new shortcodes for frontend poll creation
    add_shortcode( 'polls_create', array( 'WP_Polls_Frontend', 'display_poll_creation_form' ) );
}
add_action( 'init', 'wp_polls_register_frontend_shortcodes' );

/**
 * Display your polls shortcode.
 * 
 * @since 2.79.0
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function wp_polls_my_polls_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'limit' => 5,
        'title' => __( 'My Polls', 'wp-polls' ),
        'empty_message' => __( 'You have not created any polls yet.', 'wp-polls' ),
        'login_required' => 'yes',
        'login_message' => __( 'You must be logged in to view your polls.', 'wp-polls' ),
    ), $atts, 'polls_my' );
    
    // Check if user is logged in if required
    if ( 'yes' === $atts['login_required'] && ! is_user_logged_in() ) {
        return '<div class="polls-frontend-message polls-login-message">' . esc_html( $atts['login_message'] ) . '</div>';
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    
    // Get polls created by current user (based on user meta)
    $user_polls = get_user_meta( $current_user->ID, 'wp_polls_created', true );
    
    if ( empty( $user_polls ) || ! is_array( $user_polls ) ) {
        return '<div class="polls-frontend-empty">' . esc_html( $atts['empty_message'] ) . '</div>';
    }
    
    // Sort by newest first and limit
    $user_polls = array_slice( array_reverse( $user_polls ), 0, intval( $atts['limit'] ) );
    
    // Build output
    $output = '<div class="polls-frontend-my-polls">';
    $output .= '<h2 class="polls-frontend-title">' . esc_html( $atts['title'] ) . '</h2>';
    
    foreach ( $user_polls as $poll_id ) {
        // Get poll data
        $poll = WP_Polls_Data::get_poll( $poll_id );
        
        if ( ! $poll ) {
            continue;
        }
        
        $output .= '<div class="polls-frontend-poll-item">';
        $output .= '<h3 class="poll-question">' . esc_html( $poll->pollq_question ) . '</h3>';
        
        // Poll stats
        $votes = $poll->pollq_totalvotes;
        $timestamp = $poll->pollq_timestamp;
        $is_active = (bool) $poll->pollq_active;
        
        $output .= '<div class="poll-meta">';
        $output .= '<span class="poll-date">' . esc_html( date_i18n( get_option( 'date_format' ), $timestamp ) ) . '</span>';
        $output .= '<span class="poll-votes">' . sprintf( _n( '%s vote', '%s votes', $votes, 'wp-polls' ), number_format_i18n( $votes ) ) . '</span>';
        $output .= '<span class="poll-status ' . ( $is_active ? 'active' : 'inactive' ) . '">' . 
                   ( $is_active ? esc_html__( 'Active', 'wp-polls' ) : esc_html__( 'Closed', 'wp-polls' ) ) . 
                   '</span>';
        $output .= '</div>';
        
        // View poll link
        $output .= '<div class="poll-actions">';
        $output .= '<a href="#" class="view-poll" data-id="' . esc_attr( $poll_id ) . '">' . esc_html__( 'View Results', 'wp-polls' ) . '</a>';
        $output .= '</div>';
        
        // Poll preview container (will be populated by JavaScript)
        $output .= '<div class="poll-preview" id="poll-preview-' . esc_attr( $poll_id ) . '" style="display: none;"></div>';
        
        $output .= '</div>'; // .polls-frontend-poll-item
    }
    
    $output .= '</div>'; // .polls-frontend-my-polls
    
    // Add JavaScript to handle viewing polls
    $output .= '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $(".view-poll").on("click", function(e) {
                e.preventDefault();
                var pollId = $(this).data("id");
                var previewDiv = $("#poll-preview-" + pollId);
                
                if (previewDiv.is(":empty")) {
                    // Load poll content
                    previewDiv.html("<p>Loading poll...</p>");
                    
                    $.ajax({
                        url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",
                        type: "POST",
                        data: {
                            action: "polls",
                            view: "result",
                            poll_id: pollId
                        },
                        success: function(response) {
                            previewDiv.html(response);
                        },
                        error: function() {
                            previewDiv.html("<p>Error loading poll.</p>");
                        }
                    });
                }
                
                // Toggle visibility
                previewDiv.slideToggle();
            });
        });
    </script>';
    
    return $output;
}
add_shortcode( 'polls_my', 'wp_polls_my_polls_shortcode' );
