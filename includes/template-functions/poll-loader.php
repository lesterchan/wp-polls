<?php
/**
 * WP-Polls Poll Loader Functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Poll.
 *
 * @param int  $temp_poll_id Poll identifier.
 * @param bool $display Whether to display the poll output.
 * @return string
 */
function get_poll( $temp_poll_id = 0, $display = true ) {
	global $wpdb, $polls_loaded;
	
	// Poll Result Link.
	if ( isset( $_GET['pollresult'] ) ) {
		if ( ! isset( $_GET['poll_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['poll_nonce'] ) ), 'poll_nonce_action' ) ) {
			exit( 'Nonce verification failed' );
		}
		$pollresult_id = (int) wp_unslash( sanitize_text_field( $_GET['pollresult'] ) );
	} else {
		$pollresult_id = 0;
	}
	
	$temp_poll_id = (int) $temp_poll_id;
	
	// Check Whether Poll Is Disabled.
	if ( -1 === (int) get_option( 'poll_currentpoll' ) ) {
		if ( $display ) {
			echo wp_kses_post( removeslashes( get_option( 'poll_template_disable' ) ) );
			return '';
		}

		return wp_kses_post( removeslashes( get_option( 'poll_template_disable' ) ) );
	}
	
	do_action( 'wp_polls_get_poll' );
	
	// Hardcoded Poll ID Is Not Specified.
	switch ( $temp_poll_id ) {
		// Random Poll.
		case -2:
			$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1" );
			break;
		// Latest Poll.
		case 0:
			// Random Poll.
			if ( -2 === (int) get_option( 'poll_currentpoll' ) ) {
				$random_poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1" );
				$poll_id = (int) $random_poll_id;
				if ( $pollresult_id > 0 ) {
					$poll_id = $pollresult_id;
				} elseif ( isset( $_POST['poll_id'] ) && (int) $_POST['poll_id'] > 0 ) {
					$poll_id = (int) wp_unslash( sanitize_text_field( $_POST['poll_id'] ) );
				}
			// Current Poll ID Is Not Specified.
			} elseif ( 0 === (int) get_option( 'poll_currentpoll' ) ) {
				// Get Latest Poll ID.
				$poll_id = (int) get_option( 'poll_latestpoll' );
			} else {
				// Get Current Poll ID.
				$poll_id = (int) get_option( 'poll_currentpoll' );
			}
			break;
		// Take Poll ID From Arguments.
		default:
			$poll_id = $temp_poll_id;
	}

	// Assign All Loaded Poll To $polls_loaded.
	if ( empty( $polls_loaded ) ) {
		$polls_loaded = array();
	}
	if ( ! in_array( $poll_id, $polls_loaded, true ) ) {
		$polls_loaded[] = $poll_id;
	}

	// User Click on View Results Link.
	if ( $pollresult_id === $poll_id ) {
		if ( $display ) {
			echo wp_kses_post( display_pollresult( $poll_id ) );
			return '';
		}
		return display_pollresult( $poll_id );
	} else {
		// Check Whether User Has Voted.
		$poll_active = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_active = (int) $poll_active;
		$check_voted = check_voted( $poll_id );
		$poll_close = 0;
		if ( 0 === $poll_active ) {
			$poll_close = (int) get_option( 'poll_close' );
		}
		if ( 2 === $poll_close ) {
			if ( $display ) {
				echo '';
				return '';
			}
			return '';
		}
		if ( 1 === $poll_close || (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ) {
			if ( $display ) {
				echo wp_kses_post( display_pollresult( $poll_id, $check_voted ) );
				return '';
			}
			return display_pollresult( $poll_id, $check_voted );
		} elseif ( 3 === $poll_close || false === check_allowtovote() ) {
			$disable_poll_js = '<script type="text/javascript">jQuery(document).ready(function($) { jQuery("#polls-' . esc_js( $poll_id ) . '").replaceWith("' . esc_js( removeslashes( get_option( 'poll_template_disable' ) ) ) . '"); });</script>';
			if ( $display ) {
				echo wp_kses_post( display_pollvote( $poll_id ) ) . wp_kses_post( $disable_poll_js );
				return '';
			}
			return display_pollvote( $poll_id ) . $disable_poll_js;
		} else {
			// Get poll expiry date if any.
			$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_expiry FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
			$poll_close_date = '';
			if ( $poll_question && ! empty( $poll_question->pollq_expiry ) ) {
				/* translators: 1: Date Format, 2: Time Format */
				$poll_close_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_expiry ) );
			}
			
			if ( $display ) {
				echo wp_kses_post( display_pollvote( $poll_id ) );
				echo '<div id="polls-' . esc_attr( $poll_id ) . '-loading" class="wp-polls-loading"><img src="' . esc_url( plugins_url( 'wp-polls/images/loading.gif' ) ) . '" width="16" height="16" alt="' . esc_attr__( 'Loading', 'wp-polls' ) . ' ..." title="' . esc_attr__( 'Loading', 'wp-polls' ) . ' ..." class="wp-polls-image" />&nbsp;' . esc_html__( 'Loading', 'wp-polls' ) . ' ...</div>';
				return '';
			}
			$voting_output = display_pollvote( $poll_id );
			return $voting_output;
		}
	}
}
