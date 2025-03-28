<?php
/**
 * WP Polls Template Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Template Class
 *
 * Handles all template-related functionality for displaying polls.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Template {

	/**
	 * Display or get poll with AJAX.
	 *
	 * @since 2.78.0
	 * @param int  $poll_id The poll ID. Default 0.
	 * @param bool $display Whether to display or return. Default true.
	 * @return string|void HTML output of the poll if $display is false.
	 */
	public static function display_poll_ajax( $poll_id = 0, $display = true ) {
		$poll_id = (int) $poll_id;
		
		if ( 0 === $poll_id ) {
			$poll_id = WP_Polls_Core::get_latest_poll_id();
		}
		
		$output = '';
		
		if ( $poll_id > 0 ) {
			$poll = WP_Polls_Data::get_poll( $poll_id );
			
			if ( $poll ) {
				$user_voted = WP_Polls_Vote::has_voted( $poll_id );
				$user_can_vote = WP_Polls_Vote::can_vote();
				
				if ( 0 === $user_voted && $user_can_vote && 1 === (int) $poll->pollq_active ) {
					$output .= self::display_pollvote( $poll_id, false );
				} else {
					$output .= self::display_pollresult( $poll_id, false );
				}
			} else {
				$output .= self::get_error_message();
			}
		} else {
			$output .= self::get_error_message();
		}
		
		if ( $display ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		return $output;
	}

	/**
	 * Display or get poll voting form.
	 *
	 * @since 2.78.0
	 * @param int  $poll_id The poll ID.
	 * @param bool $display Whether to display or return.
	 * @return string|void HTML output of the poll voting form if $display is false.
	 */
	public static function display_pollvote( $poll_id, $display = true ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		$poll = WP_Polls_Data::get_poll( $poll_id );
		
		if ( ! $poll ) {
			return self::get_error_message();
		}
		
		$poll_question = WP_Polls_Utility::remove_slashes( $poll->pollq_question );
		$poll_answers = $poll->answers;
		$poll_multiple = (int) $poll->pollq_multiple;
		
		$template_vote_header = get_option( 'poll_template_voteheader' );
		$template_vote_body = get_option( 'poll_template_votebody' );
		$template_vote_footer = get_option( 'poll_template_votefooter' );
		
		$template_vote_header = str_replace( '%POLL_QUESTION%', $poll_question, $template_vote_header );
		$template_vote_header = str_replace( '%POLL_ID%', $poll_id, $template_vote_header );
		
		$template_vote_footer = str_replace( '%POLL_ID%', $poll_id, $template_vote_footer );
		$template_vote_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', $poll_multiple, $template_vote_footer );
		$template_vote_footer = str_replace( '%POLL_VOTE_BUTTON%', __( 'Vote', 'wp-polls' ), $template_vote_footer );
		
		$output = '';
		$output .= "<div id=\"polls-$poll_id\" class=\"wp-polls\">\n";
		$output .= "\t<form id=\"polls_form_$poll_id\" class=\"wp-polls-form\" action=\"" . esc_url( home_url() ) . "/\" method=\"post\">\n";
		$output .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_id}_nonce\" name=\"wp-polls-nonce\" value=\"" . esc_attr( wp_create_nonce( 'wp-polls-nonce' ) ) . "\" /></p>\n";
		$output .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_id\" /></p>\n";
		
		if ( 0 < $poll_multiple ) {
			$output .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_id\" name=\"poll_multiple_ans_$poll_id\" value=\"$poll_multiple\" /></p>\n";
		}
		
		// Print Out Vote Header Template.
		$output .= "\t\t$template_vote_header\n";
		
		// For Poll Vote Body Template.
		$template_vote_body = str_replace( '%POLL_ID%', $poll_id, $template_vote_body );
		$output_vote_body = '';
		
		// Multiple Answers.
		$multiple = '';
		if ( $poll_multiple > 0 ) {
			$multiple = 'multiple="multiple"';
			$count = 1;
		} else {
			$count = 0;
		}
		
		// Loop through answers.
		$i = 1;
		
		if ( $poll_answers ) {
			foreach ( $poll_answers as $poll_answer ) {
				// Poll Answer Variables.
				$answer_id = (int) $poll_answer->polla_aid;
				$answer_text = wp_kses_post( stripslashes( $poll_answer->polla_answers ) );
				$answer_votes = (int) $poll_answer->polla_votes;
				
				// Check whether the poll is multiple or not.
				if ( 0 === $count ) {
					$input_type = 'radio';
					$input_name = "poll_$poll_id";
					$input_id = "poll-answer-$answer_id";
					$input_value = $answer_id;
				} else {
					$input_type = 'checkbox';
					$input_name = "poll_{$poll_id}[]";
					$input_id = "poll-answer-$answer_id-$count";
					$input_value = $answer_id . '-' . $count;
				}
				
				// Replace Variables In Vote Body Template.
				$replace_vote_body = $template_vote_body;
				$replace_vote_body = str_replace( '%POLL_ANSWER_ID%', $answer_id, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER%', $answer_text, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER_INPUT_ID%', $input_id, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER_INPUT_NAME%', $input_name, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER_INPUT_VALUE%', $input_value, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER_INPUT_TYPE%', $input_type, $replace_vote_body );
				$replace_vote_body = str_replace( '%POLL_ANSWER_VOTES%', number_format_i18n( $answer_votes ), $replace_vote_body );
				
				// Filter To Allow Other Plugins To Add Custom Content.
				$template_variables = array(
					'%POLL_ID%'                  => $poll_id,
					'%POLL_ANSWER_ID%'           => $answer_id,
					'%POLL_ANSWER%'              => $answer_text,
					'%POLL_ANSWER_INPUT_ID%'     => $input_id,
					'%POLL_ANSWER_INPUT_NAME%'   => $input_name,
					'%POLL_ANSWER_INPUT_VALUE%'  => $input_value,
					'%POLL_ANSWER_INPUT_TYPE%'   => $input_type,
					'%POLL_MULTIPLE_ANSWER_MAX%' => $poll_multiple,
					'%POLL_ANSWER_VOTES%'        => number_format_i18n( $answer_votes ),
				);
				
				$replace_vote_body = apply_filters( 'wp_polls_template_votebody_markup', $replace_vote_body, $poll_answer, $template_variables );
				
				// Add Answer To Vote Body Template.
				$output_vote_body .= "\t\t\t$replace_vote_body\n";
				
				// Increase Count.
				$i++;
				$count++;
			}
		}
		
		// Print Out Vote Body Template.
		$output .= "\t\t$output_vote_body\n";
		
		// Print Out Footer Template.
		$output .= "\t\t$template_vote_footer\n";
		$output .= "\t</form>\n";
		$output .= "</div>\n";
		
		if ( $display ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $output;
	}
	
	/**
	 * Display or get poll results.
	 *
	 * @since 2.78.0
	 * @param int  $poll_id The poll ID.
	 * @param bool $display Whether to display or return the HTML.
	 * @return string|void HTML output of the poll results if $display is false.
	 */
	public static function display_pollresult( $poll_id, $display = true ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		$poll = WP_Polls_Data::get_poll( $poll_id );
		
		if ( ! $poll ) {
			return self::get_error_message();
		}
		
		$poll_question = WP_Polls_Utility::remove_slashes( $poll->pollq_question );
		$poll_answers = $poll->answers;
		$poll_multiple = (int) $poll->pollq_multiple;
		$poll_start_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll->pollq_timestamp ) );
		$poll_end_date = '';
		
		if ( ! empty( $poll->pollq_expiry ) ) {
			$poll_end_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll->pollq_expiry ) );
		}
		
		$template_results_header = get_option( 'poll_template_resultheader' );
		$template_results_body = get_option( 'poll_template_resultbody' );
		$template_results_footer = get_option( 'poll_template_resultfooter' );
		
		if ( 0 === (int) $poll->pollq_active ) {
			$template_results_footer = get_option( 'poll_template_resultfooter2' );
		}
		
		$template_results_header = str_replace( '%POLL_QUESTION%', $poll_question, $template_results_header );
		$template_results_header = str_replace( '%POLL_ID%', $poll_id, $template_results_header );
		$template_results_header = str_replace( '%POLL_TOTALVOTES%', $poll->pollq_totalvotes, $template_results_header );
		$template_results_header = str_replace( '%POLL_TOTALVOTERS%', $poll->pollq_totalvoters, $template_results_header );
		$template_results_header = str_replace( '%POLL_START_DATE%', $poll_start_date, $template_results_header );
		$template_results_header = str_replace( '%POLL_END_DATE%', $poll_end_date, $template_results_header );
		
		$template_results_footer = str_replace( '%POLL_ID%', $poll_id, $template_results_footer );
		$template_results_footer = str_replace( '%POLL_TOTALVOTES%', $poll->pollq_totalvotes, $template_results_footer );
		$template_results_footer = str_replace( '%POLL_TOTALVOTERS%', $poll->pollq_totalvoters, $template_results_footer );
		$template_results_footer = str_replace( '%POLL_START_DATE%', __( 'Poll Start Date:', 'wp-polls' ) . ' ' . $poll_start_date, $template_results_footer );
		
		if ( ! empty( $poll_end_date ) ) {
			$template_results_footer = str_replace( '%POLL_END_DATE%', __( 'Poll End Date:', 'wp-polls' ) . ' ' . $poll_end_date, $template_results_footer );
		} else {
			$template_results_footer = str_replace( '%POLL_END_DATE%', '', $template_results_footer );
		}
		
		if ( $poll_multiple > 0 ) {
			$template_results_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', $poll_multiple, $template_results_footer );
			$template_results_footer = str_replace( '%POLL_MULTIPLE_ANS_MSG%', sprintf( _n( 'You may select up to %s answer', 'You may select up to %s answers', $poll_multiple, 'wp-polls' ), number_format_i18n( $poll_multiple ) ), $template_results_footer );
		} else {
			$template_results_footer = str_replace( '%POLL_MULTIPLE_ANS_MSG%', '', $template_results_footer );
		}
		
		$output = '';
		$output .= "<div id=\"polls-$poll_id\" class=\"wp-polls\">\n";
		
		// Print Out Results Header Template.
		$output .= "\t$template_results_header\n";
		
		// For Poll Results Body Template.
		$template_results_body = str_replace( '%POLL_ID%', $poll_id, $template_results_body );
		$template_results_body = str_replace( '%POLL_TOTALVOTES%', number_format_i18n( $poll->pollq_totalvotes ), $template_results_body );
		$template_results_body = str_replace( '%POLL_TOTALVOTERS%', number_format_i18n( $poll->pollq_totalvoters ), $template_results_body );
		$template_results_body = str_replace( '%POLL_START_DATE%', $poll_start_date, $template_results_body );
		$template_results_body = str_replace( '%POLL_END_DATE%', $poll_end_date, $template_results_body );
		
		$output_results_body = '';
		
		// Variables needed for calculation.
		$poll_most_answer = '';
		$poll_most_votes = 0;
		$poll_most_percentage = 0;
		$poll_totalvotes = (int) $poll->pollq_totalvotes;
		$poll_answers_results = array();
		
		// Sort array to get the answer with the most votes.
		if ( ! empty( $poll_answers ) ) {
			// Get the poll answer with most votes.
			foreach ( $poll_answers as $poll_answer ) {
				// Determine if the current answer has the most votes.
				if ( $poll_answer->polla_votes > $poll_most_votes ) {
					$poll_most_answer = $poll_answer->polla_answers;
					$poll_most_votes = $poll_answer->polla_votes;
					if ( $poll_totalvotes > 0 ) {
						$poll_most_percentage = round( ( $poll_most_votes / $poll_totalvotes ) * 100 );
					}
				}
				
				// Add answers to array for future processing.
				$poll_answers_results[] = array(
					'aid'        => $poll_answer->polla_aid,
					'answers'    => $poll_answer->polla_answers,
					'votes'      => $poll_answer->polla_votes,
					'percentage' => ( $poll_totalvotes > 0 ) ? round( ( $poll_answer->polla_votes / $poll_totalvotes ) * 100 ) : 0,
				);
			}
		}
		
		// Sort the poll answers by votes.
		if ( ! empty( $poll_answers_results ) ) {
			list( $order_by, $sort_order ) = WP_Polls_Data::get_results_sort();
			
			if ( 'polla_votes' === $order_by ) {
				usort(
					$poll_answers_results,
					function( $a, $b ) use ( $sort_order ) {
						if ( 'desc' === $sort_order ) {
							return $b['votes'] - $a['votes'];
						} else {
							return $a['votes'] - $b['votes'];
						}
					}
				);
			} elseif ( 'polla_aid' === $order_by ) {
				usort(
					$poll_answers_results,
					function( $a, $b ) use ( $sort_order ) {
						if ( 'desc' === $sort_order ) {
							return $b['aid'] - $a['aid'];
						} else {
							return $a['aid'] - $b['aid'];
						}
					}
				);
			} elseif ( 'polla_answers' === $order_by ) {
				usort(
					$poll_answers_results,
					function( $a, $b ) use ( $sort_order ) {
						if ( 'desc' === $sort_order ) {
							return strcasecmp( $b['answers'], $a['answers'] );
						} else {
							return strcasecmp( $a['answers'], $b['answers'] );
						}
					}
				);
			} elseif ( 'RAND()' === $order_by ) {
				shuffle( $poll_answers_results );
			}
		}
		
		// Get the output of the results body.
		foreach ( $poll_answers_results as $poll_answer_result ) {
			// Variables.
			$answer_id = $poll_answer_result['aid'];
			$answer_text = wp_kses_post( stripslashes( $poll_answer_result['answers'] ) );
			$answer_votes = (int) $poll_answer_result['votes'];
			$answer_percentage = $poll_answer_result['percentage'];
			
			// Replace variables in results body.
			$replace_results_body = $template_results_body;
			$replace_results_body = str_replace( '%POLL_ANSWER_ID%', $answer_id, $replace_results_body );
			$replace_results_body = str_replace( '%POLL_ANSWER%', $answer_text, $replace_results_body );
			$replace_results_body = str_replace( '%POLL_ANSWER_TEXT%', $answer_text, $replace_results_body );
			$replace_results_body = str_replace( '%POLL_ANSWER_VOTES%', number_format_i18n( $answer_votes ), $replace_results_body );
			$replace_results_body = str_replace( '%POLL_ANSWER_PERCENTAGE%', $answer_percentage, $replace_results_body );
			$replace_results_body = str_replace( '%POLL_ANSWER_IMAGEWIDTH%', ( $answer_percentage > 0 ) ? $answer_percentage . '%' : '1px', $replace_results_body );
			
			// Add to output.
			$output_results_body .= "\t\t$replace_results_body\n";
		}
		
		// Print out most votes.
		$template_results_body = str_replace( '%POLL_MOST_ANSWER%', wp_kses_post( $poll_most_answer ), $template_results_body );
		$template_results_body = str_replace( '%POLL_MOST_VOTES%', number_format_i18n( $poll_most_votes ), $template_results_body );
		$template_results_body = str_replace( '%POLL_MOST_PERCENTAGE%', $poll_most_percentage, $template_results_body );
		
		// Print out no vote yet.
		if ( 0 === $poll_totalvotes ) {
			$output_results_body = "\t\t<li>" . __( 'No votes yet', 'wp-polls' ) . '</li>' . "\n";
		}
		
		// Results body.
		$output .= "\t$output_results_body\n";
		
		// Results footer.
		$output .= "\t$template_results_footer\n";
		$output .= "</div>\n";
		
		if ( $display ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		return $output;
	}

	/**
	 * Get error message for poll display.
	 *
	 * @since 2.78.0
	 * @return string Error message HTML.
	 */
	public static function get_error_message() {
		$poll_error_template = removeslashes( get_option( 'poll_template_error' ) );
		$poll_error = str_replace( '%POLL_ERROR_MSG%', __( 'Error: Poll Not Found.', 'wp-polls' ), $poll_error_template );
		
		return $poll_error;
	}

	/**
	 * Display or get the poll archive.
	 *
	 * @since 2.78.0
	 * @param int  $archive_limit The number of polls to display. Default 0 (all).
	 * @param int  $page The page number. Default 0.
	 * @param bool $display Whether to display or return the HTML. Default true.
	 * @return string|void HTML output of the poll archive if $display is false.
	 */
	public static function display_polls_archive( $archive_limit = 0, $page = 0, $display = true ) {
		global $wpdb;
		
		$output = '';
		
		// Whether to display the poll in HTML or XML format.
		$archive_display = (int) get_option( 'poll_archive_displaypoll' );
		
		// Check what type of archive to display.
		if ( 0 === $archive_display ) {
			$output = self::archive_no_poll();
		} elseif ( 1 === $archive_display ) {
			$output = self::archive_polls_question_only( $archive_limit, $page );
		} else {
			$output = self::archive_polls_question_with_result( $archive_limit, $page );
		}
		
		if ( $display ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		return $output;
	}

	/**
	 * Get the poll archive without poll content.
	 *
	 * @since 2.78.0
	 * @return string HTML of archive without polls.
	 */
	private static function archive_no_poll() {
		global $wpdb;
		
		// Poll Header.
		$poll_header_text = get_option( 'poll_template_pollarchiveheader' );
		$poll_header = '';
		if ( ! empty( $poll_header_text ) ) {
			$poll_header = "<div id=\"polls-head-1\" class=\"polls-head\">$poll_header_text</div>\n";
		}
		
		// Poll Footer.
		$poll_footer_text = get_option( 'poll_template_pollarchivefooter' );
		$poll_footer = '';
		if ( ! empty( $poll_footer_text ) ) {
			$poll_footer = "<div id=\"polls-foot-1\" class=\"polls-foot\">$poll_footer_text</div>\n";
		}
		
		return $poll_header . $poll_footer;
	}

	/**
	 * Get the poll archive with question only.
	 *
	 * @since 2.78.0
	 * @param int $archive_limit The number of polls to display.
	 * @param int $page The page number.
	 * @return string HTML of archive with poll questions.
	 */
	private static function archive_polls_question_only( $archive_limit, $page ) {
		global $wpdb;
		
		// Poll Variables.
		$poll_page_size = (int) get_option( 'poll_archive_perpage' );
		$poll_total_count = (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE 1=1" );
		
		// Determine range for query.
		if ( $archive_limit > 0 ) {
			$poll_page_size = $archive_limit;
		}
		
		// Calculate paging.
		$poll_offset = ( $page > 0 ) ? ( $page * $poll_page_size ) - $poll_page_size : 0;
		$total_pages = ceil( $poll_total_count / $poll_page_size );
		
		// Get polls.
		$polls = WP_Polls_Data::get_polls(
			array(
				'per_page' => $poll_page_size,
				'page'     => $page > 0 ? $page : 1,
				'orderby'  => 'pollq_id',
				'order'    => 'desc',
			)
		);
		
		// Poll Header.
		$poll_header_text = get_option( 'poll_template_pollarchiveheader' );
		$poll_header = '';
		if ( ! empty( $poll_header_text ) ) {
			$poll_header = "<div id=\"polls-head-1\" class=\"polls-head\">$poll_header_text</div>\n";
		}
		
		// Determine poll questions output.
		$output = '';
		$i = 1;
		
		if ( $polls ) {
			foreach ( $polls as $poll ) {
				// Post Date/Time.
				$poll_time = gmdate( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $poll->pollq_timestamp );
				$poll_question = WP_Polls_Utility::remove_slashes( $poll->pollq_question );
				$poll_id = (int) $poll->pollq_id;
				
				// Poll Question.
				$output .= "<div id=\"polls-$poll_id-ans\" class=\"polls-ans\"><strong>#$poll_id: $poll_question</strong></div>\n";
				$output .= "<div id=\"polls-$poll_id-ans-meta\" class=\"polls-ans-meta\">$poll_time</div>\n";
			}
		} else {
			$output .= '<p>' . __( 'No polls found.', 'wp-polls' ) . '</p>';
		}
		
		// Poll Footer.
		$poll_footer_text = get_option( 'poll_template_pollarchivefooter' );
		$poll_footer = '';
		if ( ! empty( $poll_footer_text ) ) {
			// Add paging if needed.
			if ( $total_pages > 1 ) {
				$pagination = '<div class="wp-polls-paging">';
				$pagination .= __( 'Pages:', 'wp-polls' ) . ' ';
				
				for ( $j = 1; $j <= $total_pages; $j++ ) {
					if ( $page === $j ) {
						$pagination .= "<strong>$j</strong> ";
					} else {
						$pagination .= '<a href="' . esc_url( WP_Polls_Utility::get_archive_link( $j ) ) . "\" title=\"" . __( 'Page', 'wp-polls' ) . " $j\">$j</a> ";
					}
				}
				
				$pagination .= "</div>\n";
				$poll_footer_text = str_replace( '%POLL_ARCHIVE_NAVIGATION%', $pagination, $poll_footer_text );
			} else {
				$poll_footer_text = str_replace( '%POLL_ARCHIVE_NAVIGATION%', '', $poll_footer_text );
			}
			
			$poll_footer = "<div id=\"polls-foot-$i\" class=\"polls-foot\">$poll_footer_text</div>\n";
		}
		
		// Return output.
		return $poll_header . $output . $poll_footer;
	}

	/**
	 * Get the poll archive with questions and results.
	 *
	 * @since 2.78.0
	 * @param int $archive_limit The number of polls to display.
	 * @param int $page The page number.
	 * @return string HTML of archive with poll questions and results.
	 */
	private static function archive_polls_question_with_result( $archive_limit, $page ) {
		global $wpdb;
		
		// Poll Variables.
		$poll_page_size = (int) get_option( 'poll_archive_perpage' );
		$poll_total_count = (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE 1=1" );
		
		// Determine range for query.
		if ( $archive_limit > 0 ) {
			$poll_page_size = $archive_limit;
		}
		
		// Calculate paging.
		$poll_offset = ( $page > 0 ) ? ( $page * $poll_page_size ) - $poll_page_size : 0;
		$total_pages = ceil( $poll_total_count / $poll_page_size );
		
		// Get polls.
		$polls = WP_Polls_Data::get_polls(
			array(
				'per_page'        => $poll_page_size,
				'page'            => $page > 0 ? $page : 1,
				'orderby'         => 'pollq_id',
				'order'           => 'desc',
				'include_answers' => true,
			)
		);
		
		// Poll Header.
		$poll_header_text = get_option( 'poll_template_pollarchiveheader' );
		$poll_header = '';
		if ( ! empty( $poll_header_text ) ) {
			$poll_header = "<div id=\"polls-head-1\" class=\"polls-head\">$poll_header_text</div>\n";
		}
		
		// Determine poll questions output.
		$output = '';
		$i = 1;
		
		if ( $polls ) {
			foreach ( $polls as $poll ) {
				// Poll variables.
				$poll_id = (int) $poll->pollq_id;
				$poll_question = WP_Polls_Utility::remove_slashes( $poll->pollq_question );
				$poll_time = gmdate( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $poll->pollq_timestamp );
				
				// Display poll question.
				$output .= "<div id=\"polls-$poll_id\" class=\"polls-question\">\n";
				$output .= "\t<div id=\"polls-$poll_id-q\" class=\"polls-q\"><strong>#$poll_id: $poll_question</strong></div>\n";
				$output .= "\t<div id=\"polls-$poll_id-meta\" class=\"polls-meta\">$poll_time</div>\n";
				
				// Display poll results.
				$output .= "\t" . self::display_pollresult( $poll_id, false ) . "\n";
				$output .= "</div>\n";
			}
		} else {
			$output .= '<p>' . __( 'No polls found.', 'wp-polls' ) . '</p>';
		}
		
		// Poll Footer.
		$poll_footer_text = get_option( 'poll_template_pollarchivefooter' );
		$poll_footer = '';
		if ( ! empty( $poll_footer_text ) ) {
			// Add paging if needed.
			if ( $total_pages > 1 ) {
				$pagination = '<div class="wp-polls-paging">';
				$pagination .= __( 'Pages:', 'wp-polls' ) . ' ';
				
				for ( $j = 1; $j <= $total_pages; $j++ ) {
					if ( $page === $j ) {
						$pagination .= "<strong>$j</strong> ";
					} else {
						$pagination .= '<a href="' . esc_url( WP_Polls_Utility::get_archive_link( $j ) ) . "\" title=\"" . __( 'Page', 'wp-polls' ) . " $j\">$j</a> ";
					}
				}
				
				$pagination .= "</div>\n";
				$poll_footer_text = str_replace( '%POLL_ARCHIVE_NAVIGATION%', $pagination, $poll_footer_text );
			} else {
				$poll_footer_text = str_replace( '%POLL_ARCHIVE_NAVIGATION%', '', $poll_footer_text );
			}
			
			$poll_footer = "<div id=\"polls-foot-$i\" class=\"polls-foot\">$poll_footer_text</div>\n";
		}
		
		// Return output.
		return $poll_header . $output . $poll_footer;
	}
}
