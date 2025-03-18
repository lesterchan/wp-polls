<?php
### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
	die('Access Denied');
}

### Variables Variables Variables
$base_name = plugin_basename('wp-polls/polls-manager.php');
$base_page = 'admin.php?page='.$base_name;
$mode       = ( isset( $_GET['mode'] ) ? sanitize_key( trim( $_GET['mode'] ) ) : '' );
$poll_id    = ( isset( $_GET['id'] ) ? (int) sanitize_key( $_GET['id'] ) : 0 );
$poll_aid   = ( isset( $_GET['aid'] ) ? (int) sanitize_key( $_GET['aid'] ) : 0 );

### Form Processing
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		// Edit Poll
		case __('Edit Poll', 'wp-polls'):
			check_admin_referer( 'wp-polls_edit-poll' );
			$text = '';
			// Poll Question
			$pollq_question = esc_sql( wp_kses_post( trim( $_POST['pollq_question'] ) ) );
			// Poll Answers
			$polla_answers_saved = array();
			foreach($_POST as $key => $value) {
				if (strpos($key, 'polla_aid-') === 0) { //answers already saved in DB
					$polla_answers_saved[] = sanitize_text_field($value);
				}
			}
			$polla_answers_new = isset($_POST['polla_answers_new']) ? $_POST['polla_answers_new'] : array();
			$polla_all_answers = array_merge($polla_answers_saved, $polla_answers_new);
			if ( !empty($pollq_question) && !empty(array_filter($polla_all_answers)) ) {
				// Poll ID
				$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
				// Poll Total Votes
				$pollq_totalvotes = (int) sanitize_key($_POST['pollq_totalvotes']);
				// Poll Total Voters
				$pollq_totalvoters = (int) sanitize_key($_POST['pollq_totalvoters']);
				// Polls answers type
				$pollq_expected_atype = isset( $_POST['pollq_expected_atype'] ) ? (string) sanitize_key( $_POST['pollq_expected_atype'] ) : 'text';
				// Poll Active
				$pollq_active = (int) sanitize_key($_POST['pollq_active']);
				// Poll Start Date
				$pollq_timestamp = isset( $_POST['poll_timestamp_old'] ) ? $_POST['poll_timestamp_old'] : current_time( 'timestamp' );
				$edit_polltimestamp = isset( $_POST['edit_polltimestamp'] ) && (int) sanitize_key( $_POST['edit_polltimestamp'] ) === 1 ? 1 : 0;
				if($edit_polltimestamp === 1) {
					$pollq_timestamp_day = (int) sanitize_key($_POST['pollq_timestamp_day']);
					$pollq_timestamp_month = (int) sanitize_key($_POST['pollq_timestamp_month']);
					$pollq_timestamp_year = (int) sanitize_key($_POST['pollq_timestamp_year']);
					$pollq_timestamp_hour = (int) sanitize_key($_POST['pollq_timestamp_hour']);
					$pollq_timestamp_minute = (int) sanitize_key($_POST['pollq_timestamp_minute']);
					$pollq_timestamp_second = (int) sanitize_key($_POST['pollq_timestamp_second']);
					$pollq_timestamp = gmmktime($pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year);
					if ( $pollq_timestamp > current_time( 'timestamp' ) ) {
						$pollq_active = -1;
					}
				}
				// Poll End Date
				$pollq_expiry_no = isset( $_POST['pollq_expiry_no'] ) ? (int) sanitize_key( $_POST['pollq_expiry_no'] ) : 0;
				if ( $pollq_expiry_no === 1 ) {
					$pollq_expiry = 0;
				} else {
					$pollq_expiry_day = (int) sanitize_key($_POST['pollq_expiry_day']);
					$pollq_expiry_month = (int) sanitize_key($_POST['pollq_expiry_month']);
					$pollq_expiry_year = (int) sanitize_key($_POST['pollq_expiry_year']);
					$pollq_expiry_hour = (int) sanitize_key($_POST['pollq_expiry_hour']);
					$pollq_expiry_minute = (int) sanitize_key($_POST['pollq_expiry_minute']);
					$pollq_expiry_second = (int) sanitize_key($_POST['pollq_expiry_second']);
					$pollq_expiry = gmmktime($pollq_expiry_hour, $pollq_expiry_minute, $pollq_expiry_second, $pollq_expiry_month, $pollq_expiry_day, $pollq_expiry_year);
					if($pollq_expiry <= current_time('timestamp')) {
						$pollq_active = 0;
					}
					if($edit_polltimestamp === 1) {
						if($pollq_expiry < $pollq_timestamp) {
							$pollq_active = 0;
						}
					}
				}
				// Mutilple Poll
				$pollq_multiple_yes = (int) sanitize_key($_POST['pollq_multiple_yes']);
				$pollq_multiple = 0;
				if($pollq_multiple_yes == 1) {
					$pollq_multiple = isset($_POST['pollq_multiple']) ? (int) sanitize_key($_POST['pollq_multiple']) : 0;
				} else {
					$pollq_multiple = 0;
				}
				//Poll Template
				$pollq_templates_set_id = (int) sanitize_key($_POST['pollq_templates_set_id']);
				
				// Update Poll's Question
				$edit_poll_question = $wpdb->update(
					$wpdb->pollsq,
					array(
						'pollq_question'        => $pollq_question,
						'pollq_timestamp'       => $pollq_timestamp,
						'pollq_totalvotes'      => $pollq_totalvotes,
						'pollq_active'          => $pollq_active,
						'pollq_expiry'          => $pollq_expiry,
						'pollq_multiple'        => $pollq_multiple,
						'pollq_totalvoters'     => $pollq_totalvoters,
						'pollq_expected_atype'  => $pollq_expected_atype,
						'pollq_tplid'  			=> $pollq_templates_set_id
					),
					array(
						'pollq_id' => $pollq_id
					),
					array(
						'%s',
						'%s',
						'%d',
						'%d',
						'%s',
						'%d',
						'%d',
						'%s',
						'%d'
					),
					array(
						'%d'
					)
				);
				if( ! $edit_poll_question ) {
					$text = '<p style="color: blue">'.sprintf(__('No Changes Had Been Made To Poll\'s Question \'%s\'.', 'wp-polls'), removeslashes($pollq_question)).'</p>';
				}
				// Update Polls' Answers
				$polla_aids = array();
				$get_polla_aids = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC", $pollq_id) );
				if($get_polla_aids) {
					foreach($get_polla_aids as $get_polla_aid) {
						$polla_aids[] = (int) $get_polla_aid->polla_aid;
					}
					foreach($polla_aids as $polla_aid_key => $polla_aid) {
						$polla_answers = isset($_POST['polla_aid-'.$polla_aid]) ? wp_kses_post( trim( $_POST['polla_aid-'.$polla_aid] ) ) : '';
						if (!$polla_answers){ //no field on the page matching the DB key - it has been removed by user via the JS UI, so now delete it from the DB. 
							$poll_answers = $wpdb->get_row( $wpdb->prepare( "SELECT polla_votes, polla_answers, polla_atype FROM $wpdb->pollsa WHERE polla_aid = %d AND polla_qid = %d", $polla_aid, $pollq_id ) );
							$polla_votes = (int) $poll_answers->polla_votes;
							$polla_answers = wp_kses_post( removeslashes( trim( $poll_answers->polla_answers ) ) ); //answer's text for 'text' type answer OR associated post type ID for 'object' type answeer. 
							$polla_atype = wp_kses_post( removeslashes( trim( $poll_answers->polla_atype ) ) );
							$delete_polla_answers = $wpdb->delete( $wpdb->pollsa, array( 'polla_aid' => $polla_aid, 'polla_qid' => $pollq_id ), array( '%d', '%d' ) );
							$delete_pollip = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id, 'pollip_aid' => $polla_aid ), array( '%d', '%d' ) );
							$update_pollq_totalvotes = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes - $polla_votes) WHERE pollq_id = $pollq_id" );
							$answer_text = ($polla_atype === 'object') ? get_the_title($polla_answers) : $polla_answers;
							if($delete_polla_answers) {
								$text .= '<p style="color: green;">'.sprintf(__('Poll Answer \'%s\' Deleted Successfully.', 'wp-polls'), $answer_text).'</p>';
							} else {
								$text .= '<p style="color: red;">'.sprintf(__('Error In Deleting Poll Answer \'%s\'.', 'wp-polls'), $answer_text).'</p>';
							}
						} else {                    
							$polla_votes = (int) sanitize_key($_POST['polla_votes-'.$polla_aid]);
							$edit_poll_answer = $wpdb->update(
								$wpdb->pollsa,
								array(
									'polla_answers' => $polla_answers,
									'polla_votes'   => $polla_votes,
									'polla_atype'   => $pollq_expected_atype 								
								),
								array(
									'polla_qid' => $pollq_id,
									'polla_aid' => $polla_aid
								),
								array(
									'%s',
									'%d',
									'%s'
								),
								array(
									'%d',
									'%d'
								)
							);
							$answer_text = ($pollq_expected_atype === 'object') ? get_the_title($polla_answers) : $polla_answers;
							if( ! $edit_poll_answer ) {
								$text .= '<p style="color: blue">'.sprintf(__('No Changes Had Been Made To Poll\'s Answer \'%s\'.', 'wp-polls'), $answer_text ).'</p>';
							} else {
								$text .= '<p style="color: green">'.sprintf(__('Poll\'s Answer \'%s\' Edited Successfully.', 'wp-polls'), $answer_text ).'</p>';
							}
						}
					}
				}
				// Add Poll Answers (If Needed)
				$add_new_counter = 0;
				$add_new_errors_counter = 0;
				if(!empty($polla_answers_new)) {
					$polla_answers_new_votes = $_POST['polla_answers_new_votes'];
					$fields_already_saved_for_post_types = array();
					foreach($polla_answers_new as $polla_answer_new) {
						$polla_answer_new = wp_kses_post( trim( $polla_answer_new ) );
						if(!empty($polla_answer_new)) {
							$polla_answer_new_vote = (int) sanitize_key( $polla_answers_new_votes[$add_new_counter] );
							$add_poll_answers = $wpdb->insert(
								$wpdb->pollsa,
								array(
									'polla_qid'      => $pollq_id,
									'polla_answers'  => $polla_answer_new,
									'polla_votes'    => $polla_answer_new_vote,
									'polla_atype'    => $pollq_expected_atype 	
								),
								array(
									'%d',
									'%s',
									'%d',
									'%s'
								)
							);
							$answer_text = ($pollq_expected_atype === 'object') ? get_the_title($polla_answer_new) : $polla_answer_new;
							if( ! $add_poll_answers ) {
								$text .= '<p style="color: red;">'.sprintf(__('Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls'), $answer_text).'</p>';
								$add_new_errors_counter++; 
							} else {
								$text .= '<p style="color: green;">'.sprintf(__('Poll\'s Answer \'%s\' Added Successfully.', 'wp-polls'), $answer_text).'</p>';
							}
						}
						$add_new_counter++;
					}
				}
				if(empty($text)) {
					$text = '<p style="color: green">'.sprintf(__('Poll \'%s\' Edited Successfully.', 'wp-polls'), removeslashes($pollq_question)).'</p>';
				}
				$latest_pollid = wp_polls_update_latest_id($pollq_templates_set_id); //update lastest poll ID for both global and template specific options, and return the poll ID
				
				do_action( 'wp_polls_update_poll', $pollq_id );
				cron_polls_place();
				
				//Form was fully processed, now refresh the page to reset the form while passing result messages to the refreshed page.
				set_transient('wp_polls_previous_form_submission_result', removeslashes($text), 1800);
					echo("<script>location.href = '".$_SERVER['HTTP_REFERER']."'</script>"); //refresh page, using JS due to the added complexity of using PHP to do so (i.e. 'wp_redirect()' or 'header()' functions) while headers are being sent around 'wp_loaded' hook (i.e. earlier than this page's execution)
			} else {
				if (empty($pollq_question)) $text .= '<p style="color: red;">' . __('Invalid Poll (Not Saved)', 'wp-polls') . ' - '. __( 'Poll Question is empty.', 'wp-polls' ) . '</p>';
				if (empty(array_filter($polla_all_answers))) $text .= '<p style="color: red;">' . __('Invalid Poll (Not Saved)', 'wp-polls') . ' - '. __( 'No Answers Provided.', 'wp-polls' ) . '</p>'; //if the array doesn't contain any key, or only with empty values (e.g. new empty answers). 
			}
			break;
	}
}

### Determines Which Mode It Is
switch($mode) {
	// Poll Logging
	case 'logs':
		require('polls-logs.php');
		break;
	// Edit A Poll
	case 'edit':
		$base_page_path = "?page=wp-polls%2Fpolls-manager.php&mode=edit&id=$poll_id";
		?>
		<div class="wrap">
			<h1><?php echo __('Edit Poll', 'wp-polls'); ?></h1>

			<div class="wrap">
				<?php 
				if (!empty($_POST)){ //in case of form submission error, retrieve submitted answers in order to autocomplete the form.
					$polla_answers_saved = array();
					$polla_aid_saved = array();
					foreach($_POST as $key => $value) {
						if (strpos($key, 'polla_aid-') === 0) { //answers already saved in DB
							$polla_aid_saved[] = (int) sanitize_key(str_replace('polla_aid-', '', $key));
							$polla_answers_saved[] = sanitize_text_field($value);
						} elseif (strpos($key, 'polla_votes-') === 0) { //answers already saved in DB
							$polla_votes_saved[] = (int) sanitize_key($value);
						}
					}
					$polla_answers_new = $_POST['polla_answers_new'] ?? array();
					$polla_votes_new = $_POST['polla_answers_new_votes'] ?? array();
				}
				$last_col_align = is_rtl() ? 'right' : 'left';
				$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_question, pollq_timestamp, pollq_totalvotes, pollq_active, pollq_expiry, pollq_multiple, pollq_totalvoters, pollq_expected_atype, pollq_tplid FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
				$poll_templates_set_id = $_POST['pollq_templates_set_id'] ?? (int) $poll_question->pollq_tplid;
				$poll_templates = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_currentpoll, polltpl_latestpoll FROM $wpdb->pollstpl WHERE polltpl_id = %d", $poll_templates_set_id ) );
				$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC", $poll_id ) );       
				$poll_noquestion = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(polla_aid) FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
				$poll_question_text = $_POST["pollq_question"] ?? removeslashes($poll_question->pollq_question);
				$poll_totalvotes = $_POST["pollq_totalvotes"] ?? (int) $poll_question->pollq_totalvotes;
				$poll_timestamp = ( !empty($_POST["pollq_timestamp_day"]) && !empty($_POST["pollq_timestamp_month"]) && !empty($_POST["pollq_timestamp_year"]) && !empty($_POST["pollq_timestamp_hour"]) && !empty($_POST["pollq_timestamp_minute"]) && !empty($_POST["pollq_timestamp_second"]) ) ? strtotime($_POST["pollq_timestamp_year"].'-'.$_POST["pollq_timestamp_month"].'-'.$_POST["pollq_timestamp_day"].' '.$_POST["pollq_timestamp_hour"].':'.$_POST["pollq_timestamp_minute"].':'.$_POST["pollq_timestamp_second"]) : $poll_question->pollq_timestamp;
				$poll_active = (int) $poll_question->pollq_active;
				$poll_expiry = ( empty($_POST["pollq_expiry_no"]) && !empty($_POST["pollq_expiry_day"]) && !empty($_POST["pollq_expiry_month"]) && !empty($_POST["pollq_expiry_year"]) && !empty($_POST["pollq_expiry_hour"]) && !empty($_POST["pollq_expiry_minute"]) && !empty($_POST["pollq_expiry_second"]) ) ? strtotime($_POST["pollq_expiry_year"].'-'.$_POST["pollq_expiry_month"].'-'.$_POST["pollq_expiry_day"].' '.$_POST["pollq_expiry_hour"].':'.$_POST["pollq_expiry_minute"].':'.$_POST["pollq_expiry_second"]) : trim($poll_question->pollq_expiry);
				$poll_multiple = $_POST["pollq_multiple"] ?? (int) $poll_question->pollq_multiple;
				$poll_totalvoters = $_POST["pollq_totalvoters"] ?? (int) $poll_question->pollq_totalvoters;
				$poll_type = $_POST["pollq_expected_atype"] ?? trim($poll_question->pollq_expected_atype);
				$previous_form_submission_message = get_transient('wp_polls_previous_form_submission_result');
				$text = !empty($text) ? $text : $previous_form_submission_message;
				if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; }
				if(!$_POST) delete_transient('wp_polls_previous_form_submission_result'); //if this is run after page refresh, message has been printed on the new page and transient can be deleted.
				?>
				<!-- Edit Poll -->
				<div id="loader_container" class="loader-container wp-polls-hide">
					<span class="loader-spinner"></span>
				</div>	
							
				<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__).'&amp;mode=edit&amp;id='.$poll_id); ?>">
					<?php wp_nonce_field('wp-polls_edit-poll'); ?>
					<input type="hidden" name="pollq_id" value="<?php echo $poll_id; ?>" />
					<input type="hidden" name="pollq_active" value="<?php echo $poll_active; ?>" />
					<input type="hidden" name="poll_timestamp_old" value="<?php echo $poll_timestamp; ?>" />
					<div class="wrap">
						<!-- Poll Question -->
						<h3><?php _e('Poll Question', 'wp-polls'); ?></h3>
						<table class="form-table">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Question', 'wp-polls') ?></th>
								<td width="80%"><input type="text" size="70" name="pollq_question" value="<?php echo esc_attr($poll_question_text); ?>" /></td>
							</tr>
						</table>
						<!-- Poll Answers -->
						<h3><?php _e('Poll Answers', 'wp-polls'); ?></h3>
						<table id="pollq_answer_entries_type_selection" class="form-table ">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Answers\' entries type', 'wp-polls') ?></th>
								<td width="80%">
									<input type="radio" name="pollq_expected_atype" value="text" <?php checked($poll_type, 'text') ?> onchange="check_poll_answer_entries_type(value, 'change');"/><label><?php _e('Text (default)', 'wp-polls') ?></label>
									&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
									<input type="radio" name="pollq_expected_atype" value="object" <?php checked($poll_type, 'object') ?> onchange="check_poll_answer_entries_type(value, 'change');"/><label class="wp-polls-tooltip"><?php _e('Objects (post types items)', 'wp-polls') ?><label>
									<div class="wp-polls-tooltip-container">
										<span class="wp-polls-tooltip-icon">?</span>
										<p class="wp-polls-tooltip-info">
											<span class="info"><?php echo '<strong>' . __('Text mode', 'wp-polls') . '</strong> ' . __('lets you manually type all the poll\'s answers.', 'wp-polls') . '<br/><br/><strong>' . __('Objects mode', 'wp-polls') . '</strong> ' . __('enables you to select post type items\' data (i.e. data already saved in your website) to serve as poll\'s answers (e.g. post type item\'s name, post type item\'s thumbnail, etc.).', 'wp-polls'); ?></span>
										</p>
									</div>
								</td>
							</tr>
						</table>
						<table class="form-table">
							<thead>
								<tr>
									<th width="20%" scope="row" valign="top"><?php _e('Answer No.', 'wp-polls') ?></th>
									<th width="60%" scope="row" valign="top"><?php _e('Answer Content', 'wp-polls') ?></th>
									<th width="20%" scope="row" valign="top" style="text-align: <?php echo $last_col_align; ?>;"><?php echo __('No. Of Votes', 'wp-polls').' <em>'.__('(current/modified)', 'wp-polls').'</em>'; ?></th>
								</tr>
							</thead>
							<tbody id="poll_answers">
								<?php
									$i=1;
									$poll_actual_totalvotes = 0;
									$saved_objects_post_types = array();
									$original_poll_votes = (!empty($poll_answers)) ? array_combine(array_column($poll_answers, 'polla_aid'), array_column($poll_answers, 'polla_votes')) : 0; //associative array coming from DB with 'polla_aid' as key and 'polla_votes' as values. 
									$original_poll_totalvoters = (!empty($poll_question)) ? $poll_question->pollq_totalvoters : 0; 
									// Form submission error - recover changes made to saved answers  
									if(isset($polla_aid_saved) && isset($polla_answers_saved) && isset($polla_votes_saved) && !empty($poll_answers)) { 
										for ($j = 0; $j < count($polla_aid_saved); $j++) { //loop among saved answers
											$polla_aid = (int) $polla_aid_saved[$j];
											$polla_answers = removeslashes($polla_answers_saved[$j]);
											$polla_votes = (int) $polla_votes_saved[$j];
											$original_poll_vote = (int) $original_poll_votes[$polla_aid];
											echo "<tr id=\"poll-answer-$polla_aid\" class=\"wp-polls-$poll_type-answers poll-saved-answers\">\n";
											echo '<th width="20%" scope="row" valign="top">'.sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($i)).'</th>'."\n";
											switch($poll_type){
												case 'text': // poll_answer is a string
													echo "<td width=\"60%\"><input type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_aid-$polla_aid\" value=\"". esc_attr( $polla_answers ) . "\" />&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Delete', 'wp-polls')."\" onclick=\"delete_poll_ans($poll_id, $polla_aid, $polla_votes, '".wp_create_nonce('wp-polls_delete-poll-answer')."', '".sprintf(esc_js(__('You are about to delete this poll\'s answer \'%s\'.', 'wp-polls')), esc_js( esc_attr( $polla_answers ) ) ) . "');\" class=\"button\" /></td>\n";
													break;
												case 'object': //poll_answer is a post ID
													$obj_post_type = get_post_type($polla_answers);
													$obj_post_type_obj = get_post_type_object($obj_post_type);
													if (!in_array($obj_post_type, $saved_objects_post_types)) $saved_objects_post_types[] = $obj_post_type;
													$obj_title = ucfirst(get_the_title($polla_answers));
													echo "<td width=\"60%\">";
													echo "<label for=\"checkbox_19067\"><input type=\"checkbox\" name=\"polla_aid-$polla_aid\" id=\"checkbox_".$polla_answers."_selected\" data-type=\"post_types_items\" data-post-type=\"" . $obj_post_type . "\" value=\"".$polla_answers."\" checked=\"checked\"><span><span class=\"wp-polls-lighter-color\">[". $obj_post_type_obj->labels->singular_name ."] </span>".$obj_title."</span></label>&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Delete', 'wp-polls')."\" onclick=\"delete_poll_ans($poll_id, $polla_aid, $polla_votes, '".wp_create_nonce('wp-polls_delete-poll-answer')."', '".sprintf(esc_js(__('You are about to delete this poll\'s answer \'%s\'.', 'wp-polls')), esc_js( esc_attr( "[".$obj_post_type."] ".$obj_title ) ) ) . "');\" class=\"button\" /></td>\n";
													echo "</td>\n";
													break;
											}
											echo '<td width="20%" align="'.$last_col_align.'">'.number_format_i18n($original_poll_vote)." <input type=\"text\" size=\"4\" id=\"polla_votes-$polla_aid\" name=\"polla_votes-$polla_aid\" value=\"$polla_votes\" onblur=\"check_totalvotes();\" /></td>\n</tr>\n";
											$poll_actual_totalvotes += $polla_votes;
											$i++;
										}
									// Form not yet submitted - load initial page fetching content from DB
									} elseif($poll_answers && !isset($polla_aid_saved)) { 
										$pollip_answers = array();
										$pollip_answers[0] = __('Null Votes', 'wp-polls');
										foreach($poll_answers as $poll_answer) {
											$polla_aid = (int) $poll_answer->polla_aid;
											$polla_answers = removeslashes($poll_answer->polla_answers);
											$polla_votes = (int) $poll_answer->polla_votes;
											$pollip_answers[$polla_aid] = $polla_answers;
											echo "<tr id=\"poll-answer-$polla_aid\" class=\"wp-polls-$poll_type-answers poll-saved-answers\">\n";
											echo '<th width="20%" scope="row" valign="top">'.sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($i)).'</th>'."\n";
											switch($poll_type){
												case 'text': // poll_answer is a string
													echo "<td width=\"60%\"><input type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_aid-$polla_aid\" value=\"". esc_attr( $polla_answers ) . "\" />&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Delete', 'wp-polls')."\" onclick=\"delete_poll_ans($poll_id, $polla_aid, $polla_votes, '".wp_create_nonce('wp-polls_delete-poll-answer')."', '".sprintf(esc_js(__('You are about to delete this poll\'s answer \'%s\'.', 'wp-polls')), esc_js( esc_attr( $polla_answers ) ) ) . "');\" class=\"button\" /></td>\n";
													break;
												case 'object': //poll_answer is a post ID
													$obj_post_type = get_post_type($polla_answers);
													$obj_post_type_obj = get_post_type_object($obj_post_type);
													if (!in_array($obj_post_type, $saved_objects_post_types)) $saved_objects_post_types[] = $obj_post_type;
													$obj_title = ucfirst(get_the_title($polla_answers));
													echo "<td width=\"60%\">";
													echo "<label for=\"checkbox_".$polla_answers."\"><input type=\"checkbox\" name=\"polla_aid-$polla_aid\" id=\"checkbox_".$polla_answers."_selected\" data-type=\"post_types_items\" data-post-type=\"" . $obj_post_type . "\" value=\"".$polla_answers."\" checked=\"checked\"><span><span class=\"wp-polls-lighter-color\">[". $obj_post_type_obj->labels->singular_name ."] </span>".$obj_title."</span></label>&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Delete', 'wp-polls')."\" onclick=\"delete_poll_ans($poll_id, $polla_aid, $polla_votes, '".wp_create_nonce('wp-polls_delete-poll-answer')."', '".sprintf(esc_js(__('You are about to delete this poll\'s answer \'%s\'.', 'wp-polls')), esc_js( esc_attr( "[".$obj_post_type."] ".$obj_title ) ) ) . "');\" class=\"button\" /></td>\n";
													echo "</td>\n";
													break;
											}
											echo '<td width="20%" align="'.$last_col_align.'">'.number_format_i18n($polla_votes)." <input type=\"text\" size=\"4\" id=\"polla_votes-$polla_aid\" name=\"polla_votes-$polla_aid\" value=\"$polla_votes\" onblur=\"check_totalvotes();\" /></td>\n</tr>\n";
											$poll_actual_totalvotes += $polla_votes;
											$i++;
										}
									}
									// Form submission error - recover unsaved answers
									if (isset($polla_answers_new)) { 
										for ($j = 0; $j < count($polla_answers_new); $j++) { //loop among unsaved answers
											$polla_answers = removeslashes($polla_answers_new[$j]);
											$polla_votes = (int) $polla_votes_new[$j];
											echo "<tr id=\"poll-answer-new-$j\" class=\"wp-polls-$poll_type-answers poll-unsaved-answers\">\n";
											echo '<th width="20%" scope="row" valign="top">'.sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($j)).'</th>'."\n";
											switch($poll_type){
												case 'text': // poll_answer is a string
													echo "<td width=\"60%\"><input type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_answers_new[]\" value=\"". esc_attr( $polla_answers ) . "\" />&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Remove', 'wp-polls')."\" onclick=\"remove_poll_answer_edit($j);\" class=\"button\" /></td>\n";
													break;
												case 'object': //poll_answer is a post ID
													$obj_post_type = get_post_type($polla_answers);
													$obj_post_type_obj = get_post_type_object($obj_post_type);
													if (!in_array($obj_post_type, $saved_objects_post_types)) $saved_objects_post_types[] = $obj_post_type;
													$obj_title = ucfirst(get_the_title($polla_answers));
													echo "<td width=\"60%\">";
													echo "<label for=\"checkbox_".$polla_answers."\"><input type=\"checkbox\" name=\"polla_answers_new[]\" id=\"checkbox_".$polla_answers."_selected\" data-type=\"post_types_items\" data-post-type=\"" . $obj_post_type . "\" value=\"".$polla_answers."\" checked=\"checked\"><span><span class=\"wp-polls-lighter-color\">[". $obj_post_type_obj->labels->singular_name ."] </span>".$obj_title."</span></label>&nbsp;&nbsp;&nbsp;";
													echo "<input type=\"button\" value=\"".__('Remove', 'wp-polls')."\" onclick=\"remove_poll_answer_edit($j);\" class=\"button\" /></td>\n";
													echo "</td>\n";
													break;
											}
											echo '<td width="20%" align="'.$last_col_align.'">0'." <input type=\"text\" size=\"4\" name=\"polla_answers_new_votes[]\" value=\"$polla_votes\" onblur=\"check_totalvotes();\" /></td>\n</tr>\n";
											$poll_actual_totalvotes += $polla_votes;
										}
									}									
								?>
							</tbody>
							<tbody>
								<tr>
									<td width="20%">&nbsp;</td>
									<td width="60%">&nbsp;</td>
									<td width="20%" align="<?php echo $last_col_align; ?>"><strong><?php _e('Total Votes:', 'wp-polls'); ?></strong> <strong id="poll_total_votes"><?php echo number_format_i18n(array_sum($original_poll_votes)); ?></strong> <input type="text" size="4" readonly="readonly" id="pollq_totalvotes" name="pollq_totalvotes" value="<?php echo $poll_actual_totalvotes; ?>" onblur="check_totalvotes();" /></td>
								</tr>
								<tr>
									<td width="20%">&nbsp;</td>
									<td width="60%">&nbsp;</td>
									<td width="20%" align="<?php echo $last_col_align; ?>"><strong><?php _e('Total Voters:', 'wp-polls'); ?> <?php echo number_format_i18n($original_poll_totalvoters); ?></strong> <input type="text" size="4" name="pollq_totalvoters" value="<?php echo $poll_totalvoters; ?>" /></td>
								</tr>
							</tbody>
						</table>
						<table id="pollq_expected_atype_text" class="form-table pollq-answers-entries-type-table">
							<tfoot>
								<tr>
									<td width="20%">&nbsp;</td>
									<td width="80%"><input type="button" value="<?php _e('Add Answer', 'wp-polls') ?>" onclick="add_poll_answer_edit();" class="button" /></td>
								</tr>
							</tfoot>
						</table>
						<table id="pollq_expected_atype_object" class="form-table pollq-answers-entries-type-table">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Select items to be used as answers', 'wp-polls'); ?></th>
								<td width="80%" id="obj_answers">
									<br>
									<div id="wp_polls_ajax_parent_container" class="wp-polls-ajax-parent-container">
										<?php 
										$post_type_args = array('public' => true);
										$post_types_objects = get_post_types($post_type_args, 'objects');
										$post_types_objects = apply_filters('wp_polls_admin_list_post_types', $post_types_objects);
										?>
										<span class="wp-polls-multiselect-group">
											<label for="pollq_post_types_list"><?php _e('Select post type(s)', 'wp-polls'); ?></label>
											<div id="pollq_post_types_list" class="wp-polls-multiselect">
												<div id="pollq_post_types_list_label" class="wp-polls-multiselect-selectBox" onclick="toggle_checkbox_area()">
													<select class="wp-polls-multiselect-form-select">
														<option>0 <?php _e('selected', 'wp-polls'); ?></option>
													</select>
													<div class="wp-polls-multiselect-overSelect"></div>
												</div>
												<div id="pollq_post_types_list_options" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content');?>" data-values="">
													<?php 
													$post_types_names = array();
													foreach ($post_types_objects as $post_types_obj) { ?>
														<div class="outer-wrapper">
														  <div class="inner-wrapper">
															<?php echo '<label for="' . $post_types_obj->name . '"><input type="checkbox" id="' . $post_types_obj->name . '" onchange="wp_polls_delay(function() { post_types_multiselect_dropdown_change() }, 100);" value="' . $post_types_obj->name . '" /> ' . $post_types_obj->labels->name . '</label>'; ?>
														  </div>
														</div>
													<?php
														$post_types_names[] = $post_types_obj->name;
													} 
													?>					
												</div>
											</div>
										</span>
										<input type="text" name="keyword" id="pollq_search_keyword" placeholder="<?php _e('Search items by title','wp_polls'); ?>" onkeyup="wp_polls_delay(function() {retrieve_content('post_types_items', '<?php echo wp_create_nonce('wp-polls_retrieve-content'); ?>', 'keyword')}, 1000);"></input>
										<label for="taxonomies_list"><?php _e('Search by taxonomy', 'wp-polls'); ?></label>
										<select disabled="" name="tax_list" id="pollq_tax_list" onchange="wp_polls_delay(function() {retrieve_content('terms', '<?php echo wp_create_nonce('wp-polls_retrieve-content'); ?>', 'tax_select')}, 400); ">
											<?php wp_polls_list_allowed_tax($post_types_names, true); ?>
										</select>
									</div>
									<div id="wp_polls_ajax_results_parent_container" class="wp-polls-ajax-parent-container">
										<div id="pollq_terms_results_container" class="wp-polls-dropdown wp-polls-ajax-box">
											<div class="wp-polls-dropdown-input-containers">
												<input type="text" placeholder="<?php _e('Filter terms', 'wp-polls'); ?>" id="terms_search_input" class="wp-polls-dropdown-filter" onkeydown="if (event.keyCode == 13) {return false;}" onkeyup="filter_items('terms_search_input', 'pollq_terms_results', 'input[type=&quot;radio&quot;]')">
											</div>
											<div id="pollq_terms_results" class="wp-polls-dropdown-content wp-polls-ajax-placeholder-container" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content');?>">
											</div>
										</div>
										<div id="pollq_posts_items_select_container" class="wp-polls-dropdown wp-polls-ajax-box wp-polls-ajax-selected" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content'); ?>">
											<div class="wp-polls-dropdown-input-containers">
												<input type="text" placeholder="<?php _e('Filter titles', 'wp-polls'); ?>" id="items_search_input" class="wp-polls-dropdown-filter" onkeydown="if (event.keyCode == 13) {return false;}" onkeyup="filter_items('items_search_input', 'pollq_posts_items_select_results', 'input[type=&quot;checkbox&quot;]')">
											</div>
											<div id="pollq_posts_items_select_results" class="wp-polls-dropdown-content wp-polls-ajax-placeholder-container">
											</div>
										</div>
									</div>
								</td>
							</tr>
						</table>
						<!-- Poll Multiple Answers -->
						<h3><?php _e('Poll Multiple Answers', 'wp-polls') ?></h3>
						<table class="form-table">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Allows Users To Select More Than One Answer?', 'wp-polls'); ?></th>
								<td width="80%">
									<select name="pollq_multiple_yes" id="pollq_multiple_yes" size="1" onchange="check_pollq_multiple();">
										<option value="0"<?php selected('0', $poll_multiple); ?>><?php _e('No', 'wp-polls'); ?></option>
										<option value="1"<?php if($poll_multiple > 0) { echo ' selected="selected"'; } ?>><?php _e('Yes', 'wp-polls'); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Maximum Number Of Selected Answers Allowed?', 'wp-polls') ?></th>
								<td width="80%">
									<select name="pollq_multiple" id="pollq_multiple" size="1" <?php if($poll_multiple == 0) { echo 'disabled="disabled"'; } ?>>
										<?php
											for($i = 1; $i <= $poll_noquestion; $i++) {
												if($poll_multiple > 0 && $poll_multiple == $i) {
													echo "<option value=\"$i\" selected=\"selected\">".number_format_i18n($i)."</option>\n";
												} else {
													echo "<option value=\"$i\">".number_format_i18n($i)."</option>\n";
												}
											}
										?>
									</select>
								</td>
							</tr>
						</table>
						<!-- Poll Templates Set -->
						<h3><?php _e('Poll Templates Set', 'wp-polls') ?></h3>
						<table class="form-table">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Select A Templates Set For This Poll', 'wp-polls'); ?></th>
								<td width="80%">
									<select name="pollq_templates_set_id" id="pollq_templates_set_id" size="1">
										<?php 
											echo wp_polls_list_available_templates_sets('select_list_options', -1, $poll_id, '', $poll_templates_set_id); 
										?>
									</select>
								</td>
							</tr>
							<tr>
						</table>
						<!-- Poll Start/End Date -->
						<h3><?php _e('Poll Start/End Date', 'wp-polls'); ?></h3>
						<table class="form-table">
							<tr>
								<th width="20%" scope="row" valign="top"><?php _e('Start Date/Time', 'wp-polls'); ?></th>
								<td width="80%">
									<?php echo mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_timestamp)); ?><br />
									<input type="checkbox" name="edit_polltimestamp" id="edit_polltimestamp" value="1" onclick="check_polltimestamp()" />&nbsp;<label for="edit_polltimestamp"><?php _e('Edit Start Date/Time', 'wp-polls'); ?></label><br />
									<?php poll_timestamp($poll_timestamp, 'pollq_timestamp', 'none'); ?>
								</td>
							</tr>
								<tr>
								<th width="20%" scope="row" valign="top"><?php _e('End Date/Time', 'wp-polls'); ?></th>
								<td width="80%">
									<?php
										if(empty($poll_expiry)) {
											_e('This Poll Will Not Expire', 'wp-polls');
										} else {
											echo mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
										}
									?>
									<br />
									<input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" onclick="check_pollexpiry();" <?php if(empty($poll_expiry)) { echo 'checked="checked"'; } ?> />
									<label for="pollq_expiry_no"><?php _e('Do NOT Expire This Poll', 'wp-polls'); ?></label><br />
									<?php
										if(empty($poll_expiry)) {
											poll_timestamp(current_time('timestamp'), 'pollq_expiry', 'none');
										} else {
											poll_timestamp($poll_expiry, 'pollq_expiry');
										}
									?>
								</td>
							</tr>
						</table>
						<!-- Submit Button -->					
						<p style="text-align: center;">
							<input type="submit" name="do" value="<?php _e('Edit Poll', 'wp-polls'); ?>" class="button-primary" />&nbsp;&nbsp;
						<?php
							if($poll_active == 1) {
								$poll_open_display = 'none';
								$poll_close_display = 'inline';
							} else {
								$poll_open_display = 'inline';
								$poll_close_display = 'none';
							}
						?>
							<input type="button" class="button" name="do" id="close_poll" value="<?php _e('Close Poll', 'wp-polls'); ?>" onclick="closing_poll(<?php echo $poll_id; ?>, '<?php printf(esc_js(__('You are about to CLOSE this poll \'%s\'.', 'wp-polls')), esc_attr( esc_js( $poll_question_text ) ) ); ?>', '<?php echo wp_create_nonce('wp-polls_close-poll'); ?>');" style="display: <?php echo $poll_close_display; ?>;" />
							<input type="button" class="button" name="do" id="open_poll" value="<?php _e('Open Poll', 'wp-polls'); ?>" onclick="opening_poll(<?php echo $poll_id; ?>, '<?php printf(esc_js(__('You are about to OPEN this poll \'%s\'.', 'wp-polls')), esc_attr( esc_js( $poll_question_text ) ) ); ?>', '<?php echo wp_create_nonce('wp-polls_open-poll'); ?>');" style="display: <?php echo $poll_open_display; ?>;" />
							&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="javascript:history.go(-1)" />
						</p>
					</div>
				</form>	
			</div>
		</div>
<?php
		break;
	// Main Page
	default:
		$polls = $wpdb->get_results( "SELECT * FROM $wpdb->pollsq ORDER BY pollq_timestamp DESC" );
		$poll_templates_sets = $wpdb->get_results( "SELECT polltpl_id, polltpl_currentpoll, polltpl_latestpoll FROM $wpdb->pollstpl" );
		$total_ans =  $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->pollsa" );
		$total_votes = 0;
		$total_voters = 0;

		if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; }
?>

		<!-- Manage Polls -->
		<div class="wrap">
			<h2><?php _e('Manage Polls', 'wp-polls'); ?></h2>
			<h3><?php _e('Polls', 'wp-polls'); ?></h3>
			<br style="clear" />
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('ID', 'wp-polls'); ?></th>
						<th><?php _e('Question', 'wp-polls'); ?></th>
						<th><?php _e('Answers\' Type', 'wp-polls'); ?></th>
						<th><?php _e('Template ID', 'wp-polls'); ?></th>
						<th><?php _e('Total Voters', 'wp-polls'); ?></th>
						<th><?php _e('Start Date/Time', 'wp-polls'); ?></th>
						<th><?php _e('End Date/Time', 'wp-polls'); ?></th>
						<th><?php _e('Status', 'wp-polls'); ?></th>
						<th colspan="3"><?php _e('Action', 'wp-polls'); ?></th>
					</tr>
				</thead>
				<tbody id="manage_polls">
					<?php
						if($polls) {
							$i = 0;
							foreach($polls as $poll) {
								$current_poll = 0;
								$latest_poll = 0;
	  							$templates_set_id = (int) $poll->pollq_tplid;
								foreach($poll_templates_sets as $poll_templates_set) {
									if ($poll_templates_set->polltpl_id == $templates_set_id){
										$current_poll = (int) $poll_templates_set->polltpl_currentpoll;
										$latest_poll = (int) $poll_templates_set->polltpl_latestpoll;
									}
								}
								$poll_id = (int) $poll->pollq_id;
								$poll_question = removeslashes($poll->pollq_question);
								$poll_type = removeslashes($poll->pollq_expected_atype);
								$poll_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll->pollq_timestamp));
								$poll_totalvotes = (int) $poll->pollq_totalvotes;
								$poll_totalvoters = (int) $poll->pollq_totalvoters;
								$poll_active = (int) $poll->pollq_active;
								$poll_expiry = trim($poll->pollq_expiry);
								if(empty($poll_expiry)) {
									$poll_expiry_text  = __('No Expiry', 'wp-polls');
								} else {
									$poll_expiry_text = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
								}
								if($i%2 == 0) {
									$style = 'class="alternate"';
								}  else {
									$style = '';
								}
								if($current_poll > 0) {
									if($current_poll === $poll_id) {
										$style = 'class="highlight"';
									}
								} elseif($current_poll === 0) {
									if($poll_id === $latest_poll) {
										$style = 'class="highlight"';
									}
								}
								echo "<tr id=\"poll-$poll_id\" $style>\n";
								echo '<td><strong>'.number_format_i18n($poll_id).'</strong></td>'."\n";
								echo '<td>';
								if($current_poll > 0) {
									if($current_poll === $poll_id) {
										echo '<strong>'.__('Displayed for Templates Set #', 'wp-polls').$templates_set_id.':</strong> ';
									}
								} elseif($current_poll === 0) {
									if($poll_id === $latest_poll) {
										echo '<strong>'.__('Displayed for Templates Set #', 'wp-polls').$templates_set_id.':</strong> ';
									}
								}
								echo wp_kses_post( $poll_question )."</td>\n";
								echo '<td>'.ucfirst($poll_type)."</td>\n";
								echo '<td>'.number_format_i18n($templates_set_id)."</td>\n";
								echo '<td>'.number_format_i18n($poll_totalvoters)."</td>\n";
								echo "<td>$poll_date</td>\n";
								echo "<td>$poll_expiry_text</td>\n";
								echo '<td>';
								if($poll_active === 1) {
									_e('Open', 'wp-polls');
								} elseif($poll_active === -1) {
									_e('Future', 'wp-polls');
								} else {
									_e('Closed', 'wp-polls');
								}
								echo "</td>\n";
								echo "<td><a href=\"$base_page&amp;mode=logs&amp;id=$poll_id\" class=\"edit\">".__('Logs', 'wp-polls')."</a></td>\n";
								echo "<td><a href=\"$base_page&amp;mode=edit&amp;id=$poll_id\" class=\"edit\">".__('Edit', 'wp-polls')."</a></td>\n";
								echo "<td><a href=\"#DeletePoll\" onclick=\"delete_poll($poll_id, '".sprintf(esc_js(__('You are about to delete this poll, \'%s\'.', 'wp-polls')), esc_js($poll_question))."', '".wp_create_nonce('wp-polls_delete-poll')."');\" class=\"delete\">".__('Delete', 'wp-polls')."</a></td>\n";
								echo '</tr>';
								$i++;
								$total_votes+= $poll_totalvotes;
								$total_voters+= $poll_totalvoters;

							}
						} else {
							echo '<tr><td colspan="9" align="center"><strong>'.__('No Polls Found', 'wp-polls').'</strong></td></tr>';
						}
					?>
				</tbody>
			</table>
		</div>
		<p>&nbsp;</p>

		<!-- Polls Stats -->
		<div class="wrap">
			<h3><?php _e('Polls Stats:', 'wp-polls'); ?></h3>
			<br style="clear" />
			<table class="widefat">
			<tr>
				<th><?php _e('Total Polls:', 'wp-polls'); ?></th>
				<td><?php echo number_format_i18n($i); ?></td>
			</tr>
			<tr class="alternate">
				<th><?php _e('Total Polls\' Answers:', 'wp-polls'); ?></th>
				<td><?php echo number_format_i18n($total_ans); ?></td>
			</tr>
			<tr>
				<th><?php _e('Total Votes Cast:', 'wp-polls'); ?></th>
				<td><?php echo number_format_i18n($total_votes); ?></td>
			</tr>
			<tr class="alternate">
				<th><?php _e('Total Voters:', 'wp-polls'); ?></th>
				<td><?php echo number_format_i18n($total_voters); ?></td>
			</tr>
			</table>
		</div>
		<p>&nbsp;</p>

		<!-- Delete Polls Logs -->
		<div class="wrap">
			<h3><?php _e('Polls Logs', 'wp-polls'); ?></h3>
			<br style="clear" />
			<div align="center" id="poll_logs">
			<?php
				$poll_ips = (int) $wpdb->get_var( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip" );
				if($poll_ips > 0) {
			?>
				<strong><?php _e('Are You Sure You Want To Delete All Polls Logs?', 'wp-polls'); ?></strong><br /><br />
				<input type="checkbox" name="delete_logs_yes" id="delete_logs_yes" value="yes" />&nbsp;<label for="delete_logs_yes"><?php _e('Yes', 'wp-polls'); ?></label><br /><br />
				<input type="button" value="<?php _e('Delete All Logs', 'wp-polls'); ?>" class="button" onclick="delete_poll_logs('<?php echo esc_js(__('You are about to delete all poll logs. This action is not reversible.', 'wp-polls')); ?>', '<?php echo wp_create_nonce('wp-polls_delete-polls-logs'); ?>');" />
			<?php
				} else {
					_e('No poll logs available.', 'wp-polls');
				}
			?>
			</div>
			<p><?php _e('Note: If your logging method is by IP and Cookie or by Cookie, users may still be unable to vote if they have voted before as the cookie is still stored in their computer.', 'wp-polls'); ?></p>
		</div>
<?php
} // End switch($mode)
