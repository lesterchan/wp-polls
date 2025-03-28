<?php
/**
 * WP-Polls Vote Display Functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display Voting Form.
 *
 * @param int  $poll_id         Poll identifier.
 * @param bool $display_loading Display loading indicator.
 * @return string
 */
function display_pollvote( $poll_id, $display_loading = true ) {
	do_action( 'wp_polls_display_pollvote' );
	global $wpdb;
	// Temp Poll Result.
	$temp_pollvote = '';
	// Get Poll Question Data.
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters, pollq_type FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );

	// Poll Question Variables.
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	
	/* translators: 1: Date Format, 2: Time Format */
	$poll_start_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_timestamp ) );
	$poll_expiry = trim( $poll_question->pollq_expiry );
	if ( empty( $poll_expiry ) ) {
		$poll_end_date  = __( 'No Expiry', 'wp-polls' );
	} else {
		/* translators: 1: Date Format, 2: Time Format */
		$poll_end_date  = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;
	$poll_type = sanitize_text_field( $poll_question->pollq_type );

	$template_question = removeslashes( get_option( 'poll_template_voteheader' ) );

	$template_question_variables = array(
		'%POLL_QUESTION%'           => $poll_question_text,
		'%POLL_ID%'                 => $poll_question_id,
		'%POLL_TOTALVOTES%'         => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%'        => $poll_question_totalvoters,
		'%POLL_START_DATE%'         => $poll_start_date,
		'%POLL_END_DATE%'           => $poll_end_date,
		'%POLL_MULTIPLE_ANS_MAX%'   => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1,
	);
	$template_question_variables = apply_filters( 'wp_polls_template_voteheader_variables', $template_question_variables );
	$template_question           = apply_filters( 'wp_polls_template_voteheader_markup', $template_question, $poll_question, $template_question_variables );

	// Get Poll Answers Data.
	list( $order_by, $sort_order ) = _polls_get_ans_sort();
	$poll_answers = $wpdb->get_results( $wpdb->prepare( 
		"SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY " . esc_sql( $order_by ) . ' ' . esc_sql( $sort_order ), 
		$poll_question_id 
	) );
	
	// If There Is Poll Question With Answers.
	if ( $poll_question && $poll_answers ) {
		// Display Poll Voting Form.
		$temp_pollvote .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollvote .= "\t<form id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\" action=\"" . esc_url( isset( $_SERVER['SCRIPT_NAME'] ) ? wp_unslash( $_SERVER['SCRIPT_NAME'] ) : '' ) . "\" method=\"post\">\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"" . wp_create_nonce( 'poll_' . $poll_question_id . '-nonce' ) . "\" /></p>\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_question_id\" /></p>\n";
		if ( $poll_multiple_ans > 0 ) {
			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_question_id\" name=\"poll_multiple_ans_$poll_question_id\" value=\"$poll_multiple_ans\" /></p>\n";
		}
		// Print Out Voting Form Header Template.
		$temp_pollvote .= "\t\t$template_question\n";
		
		// For ranked choice polls, wrap answers in a container with special class.
		$poll_answers_container_class = '';
		if ( 'ranked' === $poll_type ) {
			$poll_answers_container_class = ' class="wp-polls-ranked-choice"';
			$temp_pollvote .= "\t\t<div$poll_answers_container_class>\n";
		}
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables.
			$poll_answer_id = (int) $poll_answer->polla_aid;
			$poll_answer_text = wp_kses_post( removeslashes( $poll_answer->polla_answers ) );
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			$poll_answer_percentage = $poll_question_totalvotes > 0 ? round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 ) : 0;
			$poll_multiple_answer_percentage = $poll_question_totalvoters > 0 ? round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 ) : 0;
			$template_answer = removeslashes( get_option( 'poll_template_votebody' ) );

			$template_answer_variables = array(
				'%POLL_ID%'                         => $poll_question_id,
				'%POLL_ANSWER_ID%'                  => $poll_answer_id,
				'%POLL_ANSWER%'                     => $poll_answer_text,
				'%POLL_ANSWER_VOTES%'               => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%'          => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_CHECKBOX_RADIO%'             => $poll_multiple_ans > 0 ? 'checkbox' : 'radio',
			);

			$template_answer_variables = apply_filters( 'wp_polls_template_votebody_variables', $template_answer_variables );
			$template_answer           = apply_filters( 'wp_polls_template_votebody_markup', $template_answer, $poll_answer, $template_answer_variables );

			// Print Out Voting Form Body Template.
			$temp_pollvote .= "\t\t$template_answer\n";
		}
		// Determine Poll Result URL.
		$poll_result_url = esc_url_raw( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		$poll_result_url = preg_replace( '/pollresult=(\d+)/i', 'pollresult=' . $poll_question_id, $poll_result_url );
		if ( isset( $_GET['pollresult'] ) && 0 === (int) $_GET['pollresult'] ) {
			if ( false !== strpos( $poll_result_url, '?' ) ) {
				$poll_result_url = "$poll_result_url&amp;pollresult=$poll_question_id";
			} else {
				$poll_result_url = "$poll_result_url?pollresult=$poll_question_id";
			}
		}
		
		// Close the ranked choice container if it was opened.
		if ( 'ranked' === $poll_type ) {
			$temp_pollvote .= "\t\t</div>\n";
		}
		// Voting Form Footer Variables.
		$template_footer = removeslashes( get_option( 'poll_template_votefooter' ) );

		$template_footer_variables = array(
			'%POLL_ID%'               => $poll_question_id,
			'%POLL_RESULT_URL%'       => $poll_result_url,
			'%POLL_START_DATE%'       => $poll_start_date,
			'%POLL_END_DATE%'         => $poll_end_date,
			'%POLL_MULTIPLE_ANS_MAX%' => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1,
		);

		$template_footer_variables = apply_filters( 'wp_polls_template_votefooter_variables', $template_footer_variables );
		$template_footer           = apply_filters( 'wp_polls_template_votefooter_markup', $template_footer, $poll_question, $template_footer_variables );

		// Print Out Voting Form Footer Template.
		$temp_pollvote .= "\t\t$template_footer\n";
		$temp_pollvote .= "\t</form>\n";
		$temp_pollvote .= "</div>\n";
		if ( $display_loading ) {
			$poll_ajax_style = get_option( 'poll_ajax_style' );
			if ( 1 === (int) $poll_ajax_style['loading'] ) {
				$temp_pollvote .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"" . plugins_url( 'wp-polls/images/loading.gif' ) . "\" width=\"16\" height=\"16\" alt=\"" . esc_attr__( 'Loading', 'wp-polls' ) . " ...\" title=\"" . esc_attr__( 'Loading', 'wp-polls' ) . " ...\" class=\"wp-polls-image\" />&nbsp;" . esc_html__( 'Loading', 'wp-polls' ) . " ...</div>\n";
			}
		}
	} else {
		$temp_pollvote .= removeslashes( get_option( 'poll_template_disable' ) );
	}
	// Return Poll Vote Template.
	return $temp_pollvote;
}
