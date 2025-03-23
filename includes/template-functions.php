<?php
/**
 * WP-Polls Template Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

### Function: Get Poll
function get_poll($temp_poll_id = 0, $display = true) {
	global $wpdb, $polls_loaded;
	// Poll Result Link
	if(isset($_GET['pollresult'])) {
		$pollresult_id = (int) $_GET['pollresult'];
	} else {
		$pollresult_id = 0;
	}
	$temp_poll_id = (int) $temp_poll_id;
	// Check Whether Poll Is Disabled
	if((int) get_option('poll_currentpoll') === -1) {
		if($display) {
			echo removeslashes(get_option('poll_template_disable'));
			return '';
		}

		return removeslashes(get_option('poll_template_disable'));
	// Poll Is Enabled
	} else {
		do_action('wp_polls_get_poll');
		// Hardcoded Poll ID Is Not Specified
		switch($temp_poll_id) {
			// Random Poll
			case -2:
				$poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1");
				break;
			// Latest Poll
			case 0:
				// Random Poll
				if((int) get_option('poll_currentpoll') === -2) {
					$random_poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1");
					$poll_id = (int) $random_poll_id;
					if($pollresult_id > 0) {
						$poll_id = $pollresult_id;
					} elseif((int) $_POST['poll_id'] > 0) {
						$poll_id = (int) $_POST['poll_id'];
					}
				// Current Poll ID Is Not Specified
				} elseif((int) get_option('poll_currentpoll') === 0) {
					// Get Lastest Poll ID
					$poll_id = (int) get_option('poll_latestpoll');
				} else {
					// Get Current Poll ID
					$poll_id = (int) get_option('poll_currentpoll');
				}
				break;
			// Take Poll ID From Arguments
			default:
				$poll_id = $temp_poll_id;
		}
	}

	// Assign All Loaded Poll To $polls_loaded
	if(empty($polls_loaded)) {
		$polls_loaded = array();
	}
	if(!in_array($poll_id, $polls_loaded, true)) {
		$polls_loaded[] = $poll_id;
	}

	// User Click on View Results Link
	if($pollresult_id === $poll_id) {
		if($display) {
			echo display_pollresult($poll_id);
		} else {
			return display_pollresult($poll_id);
		}
	// Check Whether User Has Voted
	} else {
		$poll_active = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_active = (int) $poll_active;
		$check_voted = check_voted( $poll_id );
		$poll_close = 0;
		if( $poll_active === 0 ) {
			$poll_close = (int) get_option( 'poll_close' );
		}
		if( $poll_close === 2 ) {
			if( $display ) {
				echo '';
			} else {
				return '';
			}
		}
		if( $poll_close === 1 || (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ) {
			if($display) {
				echo display_pollresult($poll_id, $check_voted);
			} else {
				return display_pollresult($poll_id, $check_voted);
			}
		} elseif( $poll_close === 3 || ! check_allowtovote() ) {
			$disable_poll_js = '<script type="text/javascript">jQuery("#polls_form_'.$poll_id.' :input").each(function (i){jQuery(this).attr("disabled","disabled")});</script>';
			if($display) {
				echo display_pollvote($poll_id).$disable_poll_js;
			} else {
				return display_pollvote($poll_id).$disable_poll_js;
			}
		} elseif( $poll_active === 1 ) {
			if($display) {
				echo display_pollvote($poll_id);
			} else {
				return display_pollvote($poll_id);
			}
		}
	}
}

add_filter( 'wp_polls_template_voteheader_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votebody_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votefooter_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultheader_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultbody_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultbody2_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultfooter_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultfooter2_markup', 'poll_template_vote_markup', 10, 3) ;

function poll_template_vote_markup( $template, $object, $variables ) {
	return str_replace( array_keys( $variables ), array_values( $variables ), $template ) ;
}


### Function: Display Voting Form
function display_pollvote($poll_id, $display_loading = true) {
	do_action('wp_polls_display_pollvote');
	global $wpdb;
	// Temp Poll Result
	$temp_pollvote = '';
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters, pollq_type FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );

	// Poll Question Variables
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_question->pollq_timestamp));
	$poll_expiry = trim($poll_question->pollq_expiry);
	if(empty($poll_expiry)) {
		$poll_end_date  = __('No Expiry', 'wp-polls');
	} else {
		$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;
	$poll_type = sanitize_text_field( $poll_question->pollq_type );

	$template_question = removeslashes(get_option('poll_template_voteheader'));

	$template_question_variables = array(
		'%POLL_QUESTION%'           => $poll_question_text,
		'%POLL_ID%'                 => $poll_question_id,
		'%POLL_TOTALVOTES%'         => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%'        => $poll_question_totalvoters,
		'%POLL_START_DATE%'         => $poll_start_date,
		'%POLL_END_DATE%'           => $poll_end_date,
		'%POLL_MULTIPLE_ANS_MAX%'   => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1
	);
	$template_question_variables = apply_filters( 'wp_polls_template_voteheader_variables', $template_question_variables );
	$template_question  		 = apply_filters( 'wp_polls_template_voteheader_markup', $template_question, $poll_question, $template_question_variables );


	// Get Poll Answers Data
	list($order_by, $sort_order) = _polls_get_ans_sort();
	$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// If There Is Poll Question With Answers
	if($poll_question && $poll_answers) {
		// Display Poll Voting Form
		$temp_pollvote .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollvote .= "\t<form id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\" action=\"" . sanitize_text_field( $_SERVER['SCRIPT_NAME'] ) ."\" method=\"post\">\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" /></p>\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_question_id\" /></p>\n";
		if($poll_multiple_ans > 0) {
			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_question_id\" name=\"poll_multiple_ans_$poll_question_id\" value=\"$poll_multiple_ans\" /></p>\n";
		}
		// Print Out Voting Form Header Template
		$temp_pollvote .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
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

			// Print Out Voting Form Body Template
			$temp_pollvote .= "\t\t$template_answer\n";
		}
		// Determine Poll Result URL
		$poll_result_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
		$poll_result_url = preg_replace('/pollresult=(\d+)/i', 'pollresult='.$poll_question_id, $poll_result_url);
		if(isset($_GET['pollresult']) && (int) $_GET['pollresult'] === 0) {
			if(strpos($poll_result_url, '?') !== false) {
				$poll_result_url = "$poll_result_url&amp;pollresult=$poll_question_id";
			} else {
				$poll_result_url = "$poll_result_url?pollresult=$poll_question_id";
			}
		}
		// Voting Form Footer Variables
		$template_footer = removeslashes(get_option('poll_template_votefooter'));

		$template_footer_variables = array(
			'%POLL_ID%'               => $poll_question_id,
			'%POLL_RESULT_URL%'       => $poll_result_url,
			'%POLL_START_DATE%'       => $poll_start_date,
			'%POLL_END_DATE%'         => $poll_end_date,
			'%POLL_MULTIPLE_ANS_MAX%' => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1
		);

		$template_footer_variables = apply_filters( 'wp_polls_template_votefooter_variables', $template_footer_variables );
		$template_footer           = apply_filters( 'wp_polls_template_votefooter_markup', $template_footer, $poll_question, $template_footer_variables );

		// Print Out Voting Form Footer Template
		$temp_pollvote .= "\t\t$template_footer\n";
		$temp_pollvote .= "\t</form>\n";
		$temp_pollvote .= "</div>\n";
		if($display_loading) {
			$poll_ajax_style = get_option('poll_ajax_style');
			if((int) $poll_ajax_style['loading'] === 1) {
				$temp_pollvote .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
			}
		}
	} else {
		$temp_pollvote .= removeslashes(get_option('poll_template_disable'));
	}
	// Return Poll Vote Template
	return $temp_pollvote;
}


### Function: Display Results Form
function display_pollresult( $poll_id, $user_voted = array(), $display_loading = true ) {
	global $wpdb;
	do_action( 'wp_polls_display_pollresult', $poll_id, $user_voted );
	$poll_id = (int) $poll_id;
	// User Voted
	if( empty( $user_voted ) ) {
		$user_voted = array();
	}
	if ( is_array( $user_voted ) ) {
		$user_voted = array_map( 'intval', $user_voted );
	} else {
		$user_voted = array( (int) $user_voted );
	}

	// Temp Poll Result
	$temp_pollresult = '';
	// Most/Least Variables
	$poll_most_answer = '';
	$poll_most_votes = 0;
	$poll_most_percentage = 0;
	$poll_least_answer = '';
	$poll_least_votes = 0;
	$poll_least_percentage = 0;
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_active, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	// No poll could be loaded from the database
	if ( ! $poll_question ) {
		return removeslashes( get_option( 'poll_template_disable' ) );
	}
	// Poll Question Variables
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_question_active = (int) $poll_question->pollq_active;
	$poll_start_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_timestamp ) );
	$poll_expiry = trim( $poll_question->pollq_expiry );
	if ( empty( $poll_expiry ) ) {
		$poll_end_date  = __( 'No Expiry', 'wp-polls' );
	} else {
		$poll_end_date  = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;
	$template_question = removeslashes( get_option( 'poll_template_resultheader' ) );
	$template_variables = array(
		'%POLL_QUESTION%' => $poll_question_text,
		'%POLL_ID%' => $poll_question_id,
		'%POLL_TOTALVOTES%' => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%' => $poll_question_totalvoters,
		'%POLL_START_DATE%' => $poll_start_date,
		'%POLL_END_DATE%' => $poll_end_date
	);
	if ( $poll_multiple_ans > 0 ) {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
	} else {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
	}
	
	$template_variables = apply_filters('wp_polls_template_resultheader_variables', $template_variables );
	$template_question  = apply_filters('wp_polls_template_resultheader_markup', $template_question, $poll_question, $template_variables );

	// Get Poll Answers Data
	list( $order_by, $sort_order ) = _polls_get_ans_result_sort();
	$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// If There Is Poll Question With Answers
	if ( $poll_question && $poll_answers ) {
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		// Is The Poll Total Votes or Voters 0?
		$poll_totalvotes_zero = $poll_question_totalvotes <= 0;
		$poll_totalvoters_zero = $poll_question_totalvoters <= 0;
		// Print Out Result Header Template
		$temp_pollresult .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollresult .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
			$poll_answer_id = (int) $poll_answer->polla_aid;
			$poll_answer_text = wp_kses_post( removeslashes( $poll_answer->polla_answers ) );
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $poll_answer_votes > 0 ) {
				$poll_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 );
				$poll_multiple_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 );
				$poll_answer_imagewidth = round( $poll_answer_percentage );
				if ( $poll_answer_imagewidth === 100 ) {
					$poll_answer_imagewidth = 99;
				}
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			$round_percentage = apply_filters( 'wp_polls_round_percentage', false );
			if ( $round_percentage && $poll_multiple_ans === 0 ) {
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
				'%POLL_ANSWER_IMAGEWIDTH%' => $poll_answer_imagewidth
			);
			$template_variables = apply_filters('wp_polls_template_resultbody_variables', $template_variables);

			// Let User See What Options They Voted
			if ( in_array( $poll_answer_id, $user_voted, true ) ) {
				// Results Body Variables
				$template_answer = removeslashes( get_option( 'poll_template_resultbody2' ) );
				$template_answer = apply_filters('wp_polls_template_resultbody2_markup', $template_answer, $poll_answer, $template_variables);
			} else {
				// Results Body Variables
				$template_answer = removeslashes (get_option( 'poll_template_resultbody' ) );
				$template_answer = apply_filters('wp_polls_template_resultbody_markup', $template_answer, $poll_answer, $template_variables);
			}

			// Print Out Results Body Template
			$temp_pollresult .= "\t\t$template_answer\n";

			// Get Most Voted Data
			if ( $poll_answer_votes > $poll_most_votes ) {
				$poll_most_answer = $poll_answer_text;
				$poll_most_votes = $poll_answer_votes;
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if ( $poll_least_votes === 0 ) {
				$poll_least_votes = $poll_answer_votes;
			}
			if ( $poll_answer_votes <= $poll_least_votes ) {
				$poll_least_answer = $poll_answer_text;
				$poll_least_votes = $poll_answer_votes;
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
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
			'%POLL_LEAST_PERCENTAGE%' => $poll_least_percentage
		);
		if ( $poll_multiple_ans > 0 ) {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
		} else {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
		}
		$template_variables = apply_filters('wp_polls_template_resultfooter_variables', $template_variables );

		if ( ! empty( $user_voted ) || $poll_question_active === 0 || ! check_allowtovote() ) {
			$template_footer = removeslashes( get_option( 'poll_template_resultfooter' ) );
			$template_footer = apply_filters('wp_polls_template_resultfooter_markup', $template_footer, $poll_question, $template_variables);
		} else {
			$template_footer = removeslashes( get_option( 'poll_template_resultfooter2' ) );
			$template_footer = apply_filters('wp_polls_template_resultfooter2_markup', $template_footer, $poll_question, $template_variables);
		}

		// Print Out Results Footer Template
		$temp_pollresult .= "\t\t$template_footer\n";
		$temp_pollresult .= "\t\t<input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" />\n";
		$temp_pollresult .= "</div>\n";
		if ( $display_loading ) {
			$poll_ajax_style = get_option( 'poll_ajax_style' );
			if ( (int) $poll_ajax_style['loading'] === 1 ) {
				$temp_pollresult .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
			}
		}
	} else {
		$temp_pollresult .= removeslashes( get_option ('poll_template_disable' ) );
	}
	// Return Poll Result
	return apply_filters( 'wp_polls_result_markup', $temp_pollresult );
}

### Function: Display Polls Archive
function polls_archive() {
	do_action('wp_polls_polls_archive');
	global $wpdb, $in_pollsarchive;
	// Polls Variables
	$in_pollsarchive = true;
	$page = isset($_GET['poll_page']) ? (int) sanitize_key( $_GET['poll_page'] ) : 0;
	$polls_questions = array();
	$polls_answers = array();
	$polls_ips = array();
	$polls_perpage = (int) get_option('poll_archive_perpage');
	$poll_questions_ids = '0';
	$poll_voted = false;
	$poll_voted_aid = 0;
	$poll_id = 0;
	$pollsarchive_output_archive = '';
	$polls_type = (int) get_option('poll_archive_displaypoll');
	$polls_type_sql = '';
	// Determine What Type Of Polls To Show
	switch($polls_type) {
		case 1:
			$polls_type_sql = 'pollq_active = 0';
			break;
		case 2:
			$polls_type_sql = 'pollq_active = 1';
			break;
		case 3:
			$polls_type_sql = 'pollq_active IN (0,1)';
			break;
	}
	// Get Total Polls
	$total_polls = $wpdb->get_var("SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE $polls_type_sql AND pollq_active != -1");

	// Calculate Paging
	$numposts = $total_polls;
	$perpage = $polls_perpage;
	$max_page = ceil($numposts/$perpage);
	if(empty($page) || $page == 0) {
		$page = 1;
	}
	$offset = ($page-1) * $perpage;
	$pages_to_show = 10;
	$pages_to_show_minus_1 = $pages_to_show-1;
	$half_page_start = floor($pages_to_show_minus_1/2);
	$half_page_end = ceil($pages_to_show_minus_1/2);
	$start_page = $page - $half_page_start;
	if($start_page <= 0) {
		$start_page = 1;
	}
	$end_page = $page + $half_page_end;
	if(($end_page - $start_page) !== $pages_to_show_minus_1) {
		$end_page = $start_page + $pages_to_show_minus_1;
	}
	if($end_page > $max_page) {
		$start_page = $max_page - $pages_to_show_minus_1;
		$end_page = $max_page;
	}
	if($start_page <= 0) {
		$start_page = 1;
	}
	if(($offset + $perpage) > $numposts) {
		$max_on_page = $numposts;
	} else {
		$max_on_page = ($offset + $perpage);
	}
	if (($offset + 1) > ($numposts)) {
		$display_on_page = $numposts;
	} else {
		$display_on_page = ($offset + 1);
	}

	// Get Poll Questions
	$questions = $wpdb->get_results("SELECT * FROM $wpdb->pollsq WHERE $polls_type_sql ORDER BY pollq_id DESC LIMIT $offset, $polls_perpage");
	if($questions) {
		foreach($questions as $question) {
			$polls_questions[] = array( 'id' => (int) $question->pollq_id, 'question' => wp_kses_post( removeslashes( $question->pollq_question ) ), 'timestamp' => $question->pollq_timestamp, 'totalvotes' => (int) $question->pollq_totalvotes, 'start' => $question->pollq_timestamp, 'end' => trim( $question->pollq_expiry ), 'multiple' => (int) $question->pollq_multiple, 'totalvoters' => (int) $question->pollq_totalvoters );
			$poll_questions_ids .= (int) $question->pollq_id . ', ';
		}
		$poll_questions_ids = substr($poll_questions_ids, 0, -2);
	}

	// Get Poll Answers
	list($order_by, $sort_order) = _polls_get_ans_result_sort();
	$answers = $wpdb->get_results("SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid IN ($poll_questions_ids) ORDER BY $order_by $sort_order");
	if($answers) {
		foreach($answers as $answer) {
			$polls_answers[(int)$answer->polla_qid][] = array( 'aid' => (int)$answer->polla_aid, 'qid' => (int) $answer->polla_qid, 'answers' => wp_kses_post( removeslashes( $answer->polla_answers ) ), 'votes' => (int) $answer->polla_votes );
		}
	}

	// Get Poll IPs
	$ips = $wpdb->get_results( "SELECT pollip_qid, pollip_aid FROM $wpdb->pollsip WHERE pollip_qid IN ($poll_questions_ids) AND pollip_ip = '" . poll_get_ipaddress() . "' ORDER BY pollip_qid ASC" );
	if($ips) {
		foreach($ips as $ip) {
			$polls_ips[(int) $ip->pollip_qid][] = (int) $ip->pollip_aid;
		}
	}
	// Poll Archives
	$pollsarchive_output_archive .= "<div class=\"wp-polls wp-polls-archive\">\n";
	foreach($polls_questions as $polls_question) {
		// Most/Least Variables
		$poll_most_answer = '';
		$poll_most_votes = 0;
		$poll_most_percentage = 0;
		$poll_least_answer = '';
		$poll_least_votes = 0;
		$poll_least_percentage = 0;
		// Is The Poll Total Votes 0?
		$poll_totalvotes_zero = $polls_question['totalvotes'] <= 0;
		$poll_totalvoters_zero = $polls_question['totalvoters'] <= 0;
		$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['start']));
		if(empty($polls_question['end'])) {
			$poll_end_date  = __('No Expiry', 'wp-polls');
		} else {
			$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['end']));
		}
		// Archive Poll Header
		$template_archive_header = removeslashes(get_option('poll_template_pollarchiveheader'));
		// Poll Question Variables
		$template_question = removeslashes(get_option('poll_template_resultheader'));
		$template_question = str_replace("%POLL_QUESTION%", $polls_question['question'], $template_question);
		$template_question = str_replace("%POLL_ID%", $polls_question['id'], $template_question);
		$template_question = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_question);
		$template_question = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_question);
		$template_question = str_replace("%POLL_START_DATE%", $poll_start_date, $template_question);
		$template_question = str_replace("%POLL_END_DATE%", $poll_end_date, $template_question);
		if($polls_question['multiple'] > 0) {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_question);
		} else {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_question);
		}
		// Print Out Result Header Template
		$pollsarchive_output_archive .= $template_archive_header;
		$pollsarchive_output_archive .= $template_question;
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		foreach($polls_answers[$polls_question['id']] as $polls_answer) {
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $polls_answer['votes'] > 0 ) {
				$poll_answer_percentage = round( ( $polls_answer['votes'] / $polls_question['totalvotes'] ) * 100 );
				$poll_multiple_answer_percentage = round( ( $polls_answer['votes'] / $polls_question['totalvoters'] ) * 100 );
				$poll_answer_imagewidth = round( $poll_answer_percentage * 0.9 );
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			if($polls_question['multiple'] === 0) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if(count($poll_answer_percentage_array) === count($polls_answers[$polls_question['id']])) {
					$percentage_error_buffer = 100 - array_sum($poll_answer_percentage_array);
					$poll_answer_percentage += $percentage_error_buffer;
					if($poll_answer_percentage < 0) {
						$poll_answer_percentage = 0;
					}
				}
			}
			$polls_answer['answers'] = wp_kses_post( $polls_answer['answers'] );
			// Let User See What Options They Voted
			if (isset( $polls_ips[$polls_question['id']] ) && in_array( $polls_answer['aid'], check_voted_multiple( $polls_question['id'], $polls_ips[$polls_question['id']] ), true ) ) {
				$template_answer = removeslashes(get_option('poll_template_resultbody2'));
			} else {
				$template_answer = removeslashes(get_option('poll_template_resultbody'));
			}

			$template_answer = str_replace( array(
				'%POLL_ID%',
				'%POLL_ANSWER_ID%',
				'%POLL_ANSWER%',
				'%POLL_ANSWER_TEXT%',
				'%POLL_ANSWER_VOTES%',
				'%POLL_ANSWER_PERCENTAGE%',
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%',
				'%POLL_ANSWER_IMAGEWIDTH%',
			), array(
				$polls_question['id'],
				$polls_answer['aid'],
				$polls_answer['answers'],
				htmlspecialchars( wp_strip_all_tags( $polls_answer['answers'] ) ),
				number_format_i18n( $polls_answer['votes'] ),
				$poll_answer_percentage,
				$poll_multiple_answer_percentage,
				$poll_answer_imagewidth,
			),
				$template_answer );

			// Print Out Results Body Template
			$pollsarchive_output_archive .= $template_answer;

			// Get Most Voted Data
			if($polls_answer['votes'] > $poll_most_votes) {
				$poll_most_answer = $polls_answer['answers'];
				$poll_most_votes = $polls_answer['votes'];
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if($poll_least_votes === 0) {
				$poll_least_votes = $polls_answer['votes'];
			}
			if($polls_answer['votes'] <= $poll_least_votes) {
				$poll_least_answer = $polls_answer['answers'];
				$poll_least_votes = $polls_answer['votes'];
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_footer = removeslashes(get_option('poll_template_resultfooter'));
		$template_footer = str_replace("%POLL_ID%", $polls_question['id'], $template_footer);
		$template_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_footer);
		$template_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_footer);
		$template_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_footer);
		$template_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_footer);
		$template_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_footer);
		$template_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_footer);
		if($polls_question['multiple'] > 0) {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_footer);
		} else {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_footer);
		}
		// Archive Poll Footer
		$template_archive_footer = removeslashes(get_option('poll_template_pollarchivefooter'));
		$template_archive_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_archive_footer);
		if($polls_question['multiple'] > 0) {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_archive_footer);
		} else {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_archive_footer);
		}
		// Print Out Results Footer Template
		$pollsarchive_output_archive .= $template_footer;
		// Print Out Archive Poll Footer Template
		$pollsarchive_output_archive .= $template_archive_footer;
	}
	$pollsarchive_output_archive .= "</div>\n";

	// Polls Archive Paging
	if($max_page > 1) {
		$pollsarchive_output_archive .= removeslashes(get_option('poll_template_pollarchivepagingheader'));
		if(function_exists('wp_pagenavi')) {
			$pollsarchive_output_archive .= '<div class="wp-pagenavi">'."\n";
		} else {
			$pollsarchive_output_archive .= '<div class="wp-polls-paging">'."\n";
		}
		$pollsarchive_output_archive .= '<span class="pages">&#8201;'.sprintf(__('Page %s of %s', 'wp-polls'), number_format_i18n($page), number_format_i18n($max_page)).'&#8201;</span>';
		if ($start_page >= 2 && $pages_to_show < $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(1).'" title="'.__('&laquo; First', 'wp-polls').'">&#8201;'.__('&laquo; First', 'wp-polls').'&#8201;</a>';
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
		}
		if($page > 1) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page-1)).'" title="'.__('&laquo;', 'wp-polls').'">&#8201;'.__('&laquo;', 'wp-polls').'&#8201;</a>';
		}
		for($i = $start_page; $i  <= $end_page; $i++) {
			if($i === $page) {
				$pollsarchive_output_archive .= '<span class="current">&#8201;'.number_format_i18n($i).'&#8201;</span>';
			} else {
				$pollsarchive_output_archive .= '<a href="'.polls_archive_link($i).'" title="'.number_format_i18n($i).'">&#8201;'.number_format_i18n($i).'&#8201;</a>';
			}
		}
		if(empty($page) || ($page+1) <= $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page+1)).'" title="'.__('&raquo;', 'wp-polls').'">&#8201;'.__('&raquo;', 'wp-polls').'&#8201;</a>';
		}
		if ($end_page < $max_page) {
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link($max_page).'" title="'.__('Last &raquo;', 'wp-polls').'">&#8201;'.__('Last &raquo;', 'wp-polls').'&#8201;</a>';
		}
		$pollsarchive_output_archive .= '</div>';
		$pollsarchive_output_archive .= removeslashes(get_option('poll_template_pollarchivepagingfooter'));
	}

	// Output Polls Archive Page
	return apply_filters( 'wp_polls_archive', $pollsarchive_output_archive );
}
