<?php
/**
 * WP-Polls Results Display Functions.
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display Results Form.
 *
 * @param int   $poll_id         Poll identifier.
 * @param array $user_voted      User voted answers.
 * @param bool  $display_loading Display loading indicator.
 * @return string
 */
function display_pollresult( $poll_id, $user_voted = array(), $display_loading = true ) {
	global $wpdb;
	do_action( 'wp_polls_display_pollresult', $poll_id, $user_voted );
	$poll_id = (int) $poll_id;
	// User Voted.
	if ( empty( $user_voted ) ) {
		$user_voted = array();
	}
	if ( is_array( $user_voted ) ) {
		$user_voted = array_map( 'intval', $user_voted );
	} else {
		$user_voted = array( (int) $user_voted );
	}

	// Temp Poll Result.
	$temp_pollresult = '';
	// Most/Least Variables.
	$poll_most_answer = '';
	$poll_most_votes = 0;
	$poll_most_percentage = 0;
	$poll_least_answer = '';
	$poll_least_votes = 0;
	$poll_least_percentage = 0;
	// Get Poll Question Data.
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_active, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	// No poll could be loaded from the database.
	if ( ! $poll_question ) {
		return removeslashes( get_option( 'poll_template_disable' ) );
	}
	// Poll Question Variables.
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_question_active = (int) $poll_question->pollq_active;
	
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
	$template_question = removeslashes( get_option( 'poll_template_resultheader' ) );
	$template_variables = array(
		'%POLL_QUESTION%' => $poll_question_text,
		'%POLL_ID%' => $poll_question_id,
		'%POLL_TOTALVOTES%' => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%' => $poll_question_totalvoters,
		'%POLL_START_DATE%' => $poll_start_date,
		'%POLL_END_DATE%' => $poll_end_date,
	);
	if ( $poll_multiple_ans > 0 ) {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
	} else {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
	}
	
	$template_variables = apply_filters( 'wp_polls_template_resultheader_variables', $template_variables );
	$template_question  = apply_filters( 'wp_polls_template_resultheader_markup', $template_question, $poll_question, $template_variables );

	// Get Poll Answers Data.
	list( $order_by, $sort_order ) = _polls_get_ans_result_sort();
	$poll_answers = $wpdb->get_results( $wpdb->prepare( 
		"SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY " . esc_sql( $order_by ) . ' ' . esc_sql( $sort_order ), 
		$poll_question_id 
	) );
	
	// If There Is Poll Question With Answers.
	if ( $poll_question && $poll_answers ) {
		// Store The Percentage Of The Poll.
		$poll_answer_percentage_array = array();
		// Is The Poll Total Votes or Voters 0?
		$poll_totalvotes_zero = $poll_question_totalvotes <= 0;
		$poll_totalvoters_zero = $poll_question_totalvoters <= 0;
		// Print Out Result Header Template.
		$temp_pollresult .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollresult .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables.
			$poll_answer_id = (int) $poll_answer->polla_aid;
			$poll_answer_text = wp_kses_post( removeslashes( $poll_answer->polla_answers ) );
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			// Calculate Percentage And Image Bar Width.
			$poll_answer_percentage = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $poll_answer_votes > 0 ) {
				$poll_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 );
				$poll_multiple_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 );
				$poll_answer_imagewidth = round( $poll_answer_percentage );
				if ( 100 === $poll_answer_imagewidth ) {
					$poll_answer_imagewidth = 99;
				}
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer.
			$round_percentage = apply_filters( 'wp_polls_round_percentage', false );
			if ( $round_percentage && 0 === $poll_multiple_ans ) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if ( count( $poll_answer_percentage_array ) === count( $poll_answers ) ) {
					$percentage_error_buffer = 100 - array_sum( $poll_answer_percentage_array );
					$poll_answer_percentage += $percentage_error_buffer;
					if ( $poll_answer_percentage < 0 ) {
						$poll_answer_percentage = 0;
					}
				}
			}

			$template_variables = array(
				'%POLL_ID%' => $poll_question_id,
				'%POLL_ANSWER_ID%' => $poll_answer_id,
				'%POLL_ANSWER%' => $poll_answer_text,
				'%POLL_ANSWER_TEXT%' => htmlspecialchars( wp_strip_all_tags( $poll_answer_text ) ),
				'%POLL_ANSWER_VOTES%' => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%' => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_ANSWER_IMAGEWIDTH%' => $poll_answer_imagewidth,
			);
			$template_variables = apply_filters( 'wp_polls_template_resultbody_variables', $template_variables );

			// Let User See What Options They Voted.
			if ( in_array( $poll_answer_id, $user_voted, true ) ) {
				// Results Body Variables.
				$template_answer = removeslashes( get_option( 'poll_template_resultbody2' ) );
				$template_answer = apply_filters( 'wp_polls_template_resultbody2_markup', $template_answer, $poll_answer, $template_variables );
			} else {
				// Results Body Variables.
				$template_answer = removeslashes( get_option( 'poll_template_resultbody' ) );
				$template_answer = apply_filters( 'wp_polls_template_resultbody_markup', $template_answer, $poll_answer, $template_variables );
			}

			// Print Out Results Body Template.
			$temp_pollresult .= "\t\t$template_answer\n";

			// Get Most Voted Data.
			if ( $poll_answer_votes > $poll_most_votes ) {
				$poll_most_answer = $poll_answer_text;
				$poll_most_votes = $poll_answer_votes;
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data.
			if ( 0 === $poll_least_votes ) {
				$poll_least_votes = $poll_answer_votes;
			}
			if ( $poll_answer_votes <= $poll_least_votes ) {
				$poll_least_answer = $poll_answer_text;
				$poll_least_votes = $poll_answer_votes;
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables.
		$template_variables = array(
			'%POLL_START_DATE%' => $poll_start_date,
			'%POLL_END_DATE%' => $poll_end_date,
			'%POLL_ID%' => $poll_question_id,
			'%POLL_TOTALVOTES%' => number_format_i18n( $poll_question_totalvotes ),
			'%POLL_TOTALVOTERS%' => number_format_i18n( $poll_question_totalvoters ),
			'%POLL_MOST_ANSWER%' => $poll_most_answer,
			'%POLL_MOST_VOTES%' => number_format_i18n( $poll_most_votes ),
			'%POLL_MOST_PERCENTAGE%' => $poll_most_percentage,
			'%POLL_LEAST_ANSWER%' => $poll_least_answer,
			'%POLL_LEAST_VOTES%' => number_format_i18n( $poll_least_votes ),
			'%POLL_LEAST_PERCENTAGE%' => $poll_least_percentage,
		);
		if ( $poll_multiple_ans > 0 ) {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
		} else {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
		}
		$template_variables = apply_filters( 'wp_polls_template_resultfooter_variables', $template_variables );

		if ( ! empty( $user_voted ) || 0 === $poll_question_active || ! check_allowtovote() ) {
			$template_footer = removeslashes( get_option( 'poll_template_resultfooter' ) );
			$template_footer = apply_filters( 'wp_polls_template_resultfooter_markup', $template_footer, $poll_question, $template_variables );
		} else {
			$template_footer = removeslashes( get_option( 'poll_template_resultfooter2' ) );
			$template_footer = apply_filters( 'wp_polls_template_resultfooter2_markup', $template_footer, $poll_question, $template_variables );
		}

		// Print Out Results Footer Template.
		$temp_pollresult .= "\t\t$template_footer\n";
		$temp_pollresult .= "\t\t<input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"" . wp_create_nonce( 'poll_' . $poll_question_id . '-nonce' ) . "\" />\n";
		$temp_pollresult .= "</div>\n";
		if ( $display_loading ) {
			$poll_ajax_style = get_option( 'poll_ajax_style' );
			if ( 1 === (int) $poll_ajax_style['loading'] ) {
				$temp_pollresult .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"" . plugins_url( 'wp-polls/images/loading.gif' ) . "\" width=\"16\" height=\"16\" alt=\"" . esc_attr__( 'Loading', 'wp-polls' ) . " ...\" title=\"" . esc_attr__( 'Loading', 'wp-polls' ) . " ...\" class=\"wp-polls-image\" />&nbsp;" . esc_html__( 'Loading', 'wp-polls' ) . " ...</div>\n";
			}
		}
	} else {
		$temp_pollresult .= removeslashes( get_option( 'poll_template_disable' ) );
	}
	// Return Poll Result.
	return apply_filters( 'wp_polls_result_markup', $temp_pollresult );
}
