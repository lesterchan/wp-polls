<?php
/*
+----------------------------------------------------------------+
|																|
|	WordPress Plugin: WP-Polls									|
|	Copyright (c) 2012 Lester "GaMerZ" Chan						|
|																|
|	File Written By:											|
|	- Lester "GaMerZ" Chan										|
|	- http://lesterchan.net										|
|																|
|	File Information:											|
|	- Manage Your Polls											|
|	- wp-content/plugins/wp-polls/polls-manager.php				|
|																|
+----------------------------------------------------------------+
*/

### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
	die('Access Denied');
}

### Variables Variables Variables
$base_name = plugin_basename('wp-polls/polls-manager.php');
$base_page = 'admin.php?page='.$base_name;
$mode = (isset($_GET['mode']) ? trim($_GET['mode']) : '');
$poll_id = (isset($_GET['id']) ? intval($_GET['id']) : 0);
$poll_aid = (isset($_GET['aid']) ? intval($_GET['aid']) : 0);

### Form Processing
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		// Edit Poll
		case __('Edit Poll', 'wp-polls'):
			check_admin_referer('wp-polls_edit-poll');
			// Poll ID
			$pollq_id  = intval($_POST['pollq_id']);
			// Poll Total Votes
			$pollq_totalvotes = intval($_POST['pollq_totalvotes']);
			// Poll Total Voters
			$pollq_totalvoters = intval($_POST['pollq_totalvoters']);
			// Poll Question
			$pollq_question = addslashes(trim($_POST['pollq_question']));
			// Poll Active
			$pollq_active = intval($_POST['pollq_active']);
			// Poll Start Date
			$edit_polltimestamp = isset($_POST['edit_polltimestamp']) && intval($_POST['edit_polltimestamp']) == 1;
			$timestamp_sql = '';
			if($edit_polltimestamp == 1) {
				$pollq_timestamp_day = intval($_POST['pollq_timestamp_day']);
				$pollq_timestamp_month = intval($_POST['pollq_timestamp_month']);
				$pollq_timestamp_year = intval($_POST['pollq_timestamp_year']);
				$pollq_timestamp_hour = intval($_POST['pollq_timestamp_hour']);
				$pollq_timestamp_minute = intval($_POST['pollq_timestamp_minute']);
				$pollq_timestamp_second = intval($_POST['pollq_timestamp_second']);
				$pollq_timestamp = gmmktime($pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year);
				$timestamp_sql = ", pollq_timestamp = '$pollq_timestamp'";
				if($pollq_timestamp > current_time('timestamp')) {
					$pollq_active = -1;
				}
			}
			// Poll End Date
			$pollq_expiry_no = intval($_POST['pollq_expiry_no']);
			if($pollq_expiry_no == 1) {
				$pollq_expiry = '';
			} else {
				$pollq_expiry_day = intval($_POST['pollq_expiry_day']);
				$pollq_expiry_month = intval($_POST['pollq_expiry_month']);
				$pollq_expiry_year = intval($_POST['pollq_expiry_year']);
				$pollq_expiry_hour = intval($_POST['pollq_expiry_hour']);
				$pollq_expiry_minute = intval($_POST['pollq_expiry_minute']);
				$pollq_expiry_second = intval($_POST['pollq_expiry_second']);
				$pollq_expiry = gmmktime($pollq_expiry_hour, $pollq_expiry_minute, $pollq_expiry_second, $pollq_expiry_month, $pollq_expiry_day, $pollq_expiry_year);
				if($pollq_expiry <= current_time('timestamp')) {
					$pollq_active = 0;
				}
				if($edit_polltimestamp == 1) {
					if($pollq_expiry < $pollq_timestamp) {
						$pollq_active = 0;
					}
				}
			}
			// Mutilple Poll
			$pollq_multiple_yes = intval($_POST['pollq_multiple_yes']);
			$pollq_multiple = 0;
			if($pollq_multiple_yes == 1) {
				$pollq_multiple = intval($_POST['pollq_multiple']);
			} else {
				$pollq_multiple = 0;
			}
			// Update Poll's Question
			$edit_poll_question = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_question = '$pollq_question', pollq_totalvotes = $pollq_totalvotes, pollq_expiry = '$pollq_expiry', pollq_active = $pollq_active, pollq_multiple = $pollq_multiple, pollq_totalvoters = $pollq_totalvoters $timestamp_sql WHERE pollq_id = $pollq_id");
			if(!$edit_poll_question) {
				$text = '<p style="color: blue">'.sprintf(__('No Changes Had Been Made To Poll\'s Question \'%s\'.', 'wp-polls'), stripslashes($pollq_question)).'</p>';
			}
			// Update Polls' Answers
			$polla_aids = array();
			$get_polla_aids = $wpdb->get_results("SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = $pollq_id ORDER BY polla_aid ASC");
			if($get_polla_aids) {
				foreach($get_polla_aids as $get_polla_aid) {
						$polla_aids[] = intval($get_polla_aid->polla_aid);
				}
				foreach($polla_aids as $polla_aid) {
					$polla_answers = addslashes(trim($_POST['polla_aid-'.$polla_aid]));
					$polla_votes = intval($_POST['polla_votes-'.$polla_aid]);
					$edit_poll_answer = $wpdb->query("UPDATE $wpdb->pollsa SET polla_answers = '$polla_answers', polla_votes = $polla_votes WHERE polla_qid = $pollq_id AND polla_aid = $polla_aid");
					if(!$edit_poll_answer) {
						$text .= '<p style="color: blue">'.sprintf(__('No Changes Had Been Made To Poll\'s Answer \'%s\'.', 'wp-polls'), stripslashes($polla_answers)).'</p>';
					} else {
						$text .= '<p style="color: green">'.sprintf(__('Poll\'s Answer \'%s\' Edited Successfully.', 'wp-polls'), stripslashes($polla_answers)).'</p>';
					}
				}
			} else {
				$text .= '<p style="color: red">'.sprintf(__('Invalid Poll \'%s\'.', 'wp-polls'), stripslashes($pollq_question)).'</p>';
			}
			// Add Poll Answers (If Needed)
			$polla_answers_new = isset($_POST['polla_answers_new']) ? $_POST['polla_answers_new'] : null;
			if(!empty($polla_answers_new)) {
				$i = 0;
				$polla_answers_new_votes = $_POST['polla_answers_new_votes'];
				foreach($polla_answers_new as $polla_answer_new) {
					$polla_answer_new = addslashes(trim($polla_answer_new));
					if(!empty($polla_answer_new)) {
						$polla_answer_new_vote = intval($polla_answers_new_votes[$i]);
						$add_poll_answers = $wpdb->query("INSERT INTO $wpdb->pollsa VALUES (0, $pollq_id, '$polla_answer_new', $polla_answer_new_vote)");
						if(!$add_poll_answers) {
							$text .= '<p style="color: red;">'.sprintf(__('Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls'), stripslashes($polla_answer_new)).'</p>';
						} else {
							$text .= '<p style="color: green;">'.sprintf(__('Poll\'s Answer \'%s\' Added Successfully.', 'wp-polls'), stripslashes($polla_answer_new)).'</p>';
						}
					}
					$i++;
				}
			}
			if(empty($text)) {
				$text = '<p style="color: green">'.sprintf(__('Poll \'%s\' Edited Successfully.', 'wp-polls'), stripslashes($pollq_question)).'</p>';
			}
			// Update Lastest Poll ID To Poll Options
			$latest_pollid = polls_latest_id();
			$update_latestpoll = update_option('poll_latestpoll', $latest_pollid);
			cron_polls_place();
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
		$last_col_align = is_rtl() ? 'right' : 'left';
		$poll_question = $wpdb->get_row("SELECT pollq_question, pollq_timestamp, pollq_totalvotes, pollq_active, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = $poll_id");
		$poll_answers = $wpdb->get_results("SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = $poll_id ORDER BY polla_aid ASC");
		$poll_noquestion = $wpdb->get_var("SELECT COUNT(polla_aid) FROM $wpdb->pollsa WHERE polla_qid = $poll_id");
		$poll_question_text = stripslashes($poll_question->pollq_question);
		$poll_totalvotes = intval($poll_question->pollq_totalvotes);
		$poll_timestamp = $poll_question->pollq_timestamp;
		$poll_active = intval($poll_question->pollq_active);
		$poll_expiry = trim($poll_question->pollq_expiry);
		$poll_multiple = intval($poll_question->pollq_multiple);
		$poll_totalvoters = intval($poll_question->pollq_totalvoters);
?>
		<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.stripslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; } ?>

		<!-- Edit Poll -->
		<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__).'&amp;mode=edit&amp;id='.$poll_id); ?>">
		<?php wp_nonce_field('wp-polls_edit-poll'); ?>
		<input type="hidden" name="pollq_id" value="<?php echo $poll_id; ?>" />
		<input type="hidden" name="pollq_active" value="<?php echo $poll_active; ?>" />
		<div class="wrap">
			<h2><?php _e('Edit Poll', 'wp-polls'); ?></h2>
			<!-- Poll Question -->
			<h3><?php _e('Poll Question', 'wp-polls'); ?></h3>
			<table class="form-table">
				<tr>
					<th width="20%" scope="row" valign="top"><?php _e('Question', 'wp-polls') ?></th>
					<td width="80%"><input type="text" size="70" name="pollq_question" value="<?php echo htmlspecialchars($poll_question_text); ?>" /></td>
				</tr>
			</table>
			<!-- Poll Answers -->
			<h3><?php _e('Poll Answers', 'wp-polls'); ?></h3>
			<table class="form-table">
				<thead>
					<tr>
						<th width="20%" scope="row" valign="top"><?php _e('Answer No.', 'wp-polls') ?></th>
						<th width="60%" scope="row" valign="top"><?php _e('Answer Text', 'wp-polls') ?></th>
						<th width="20%" scope="row" valign="top" style="text-align: <?php echo $last_col_align; ?>;"><?php _e('No. Of Votes', 'wp-polls') ?></th>
					</tr>
				</thead>
				<tbody id="poll_answers">
					<?php
						$i=1;
						$poll_actual_totalvotes = 0;
						if($poll_answers) {
							$pollip_answers = array();
							$pollip_answers[0] = __('Null Votes', 'wp-polls');
							foreach($poll_answers as $poll_answer) {
								$polla_aid = intval($poll_answer->polla_aid);
								$polla_answers = stripslashes($poll_answer->polla_answers);
								$polla_votes = intval($poll_answer->polla_votes);
								$pollip_answers[$polla_aid] = $polla_answers;
								echo "<tr id=\"poll-answer-$polla_aid\">\n";
								echo '<th width="20%" scope="row" valign="top">'.sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($i)).'</th>'."\n";
								echo "<td width=\"60%\"><input type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_aid-$polla_aid\" value=\"".htmlspecialchars($polla_answers)."\" />&nbsp;&nbsp;&nbsp;";
								echo "<input type=\"button\" value=\"".__('Delete', 'wp-polls')."\" onclick=\"delete_poll_ans($poll_id, $polla_aid, $polla_votes, '".sprintf(esc_js(__('You are about to delete this poll\'s answer \'%s\'.', 'wp-polls')), esc_js(htmlspecialchars($polla_answers)))."', '".wp_create_nonce('wp-polls_delete-poll-answer')."');\" class=\"button\" /></td>\n";
								echo '<td width="20%" align="'.$last_col_align.'">'.number_format_i18n($polla_votes)." <input type=\"text\" size=\"4\" id=\"polla_votes-$polla_aid\" name=\"polla_votes-$polla_aid\" value=\"$polla_votes\" onblur=\"check_totalvotes();\" /></td>\n</tr>\n";
								$poll_actual_totalvotes += $polla_votes;
								$i++;
							}
						}
					?>
				</tbody>
				<tbody>
					<tr>
						<td width="20%">&nbsp;</td>
						<td width="60%"><input type="button" value="<?php _e('Add Answer', 'wp-polls') ?>" onclick="add_poll_answer_edit();" class="button" /></td>
						<td width="20%" align="<?php echo $last_col_align; ?>"><strong><?php _e('Total Votes:', 'wp-polls'); ?></strong> <strong id="poll_total_votes"><?php echo number_format_i18n($poll_actual_totalvotes); ?></strong> <input type="text" size="4" readonly="readonly" id="pollq_totalvotes" name="pollq_totalvotes" value="<?php echo $poll_actual_totalvotes; ?>" onblur="check_totalvotes();" /></td>
					</tr>
					<tr>
						<td width="20%">&nbsp;</td>
						<td width="60%">&nbsp;</td>
						<td width="20%" align="<?php echo $last_col_align; ?>"><strong><?php _e('Total Voters:', 'wp-polls'); ?> <?php echo number_format_i18n($poll_totalvoters); ?></strong> <input type="text" size="4" name="pollq_totalvoters" value="<?php echo $poll_totalvoters; ?>" /></td>
					</tr>
				</tbody>
			</table>
			<!-- Poll Multiple Answers -->
			<h3><?php _e('Poll Multiple Answers', 'wp-polls') ?></h3>
			<table class="form-table">
				<tr>
					<th width="40%" scope="row" valign="top"><?php _e('Allows Users To Select More Than One Answer?', 'wp-polls'); ?></th>
					<td width="60%">
						<select name="pollq_multiple_yes" id="pollq_multiple_yes" size="1" onchange="check_pollq_multiple();">
							<option value="0"<?php selected('0', $poll_multiple); ?>><?php _e('No', 'wp-polls'); ?></option>
							<option value="1"<?php if($poll_multiple > 0) { echo ' selected="selected"'; } ?>><?php _e('Yes', 'wp-polls'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th width="40%" scope="row" valign="top"><?php _e('Maximum Number Of Selected Answers Allowed?', 'wp-polls') ?></th>
					<td width="60%">
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
				<input type="button" class="button" name="do" id="close_poll" value="<?php _e('Close Poll', 'wp-polls'); ?>" onclick="closing_poll(<?php echo $poll_id; ?>, '<?php printf(esc_js(__('You are about to CLOSE this poll \'%s\'.', 'wp-polls')), htmlspecialchars(esc_js($poll_question_text))); ?>', '<?php echo wp_create_nonce('wp-polls_close-poll'); ?>');" style="display: <?php echo $poll_close_display; ?>;" />
				<input type="button" class="button" name="do" id="open_poll" value="<?php _e('Open Poll', 'wp-polls'); ?>" onclick="opening_poll(<?php echo $poll_id; ?>, '<?php printf(esc_js(__('You are about to OPEN this poll \'%s\'.', 'wp-polls')), htmlspecialchars(esc_js($poll_question_text))); ?>', '<?php echo wp_create_nonce('wp-polls_open-poll'); ?>');" style="display: <?php echo $poll_open_display; ?>;" />
				&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="javascript:history.go(-1)" />
			</p>
		</div>
		</form>
<?php
		break;
	// Main Page
	default:
		$polls = $wpdb->get_results("SELECT * FROM $wpdb->pollsq  ORDER BY pollq_timestamp DESC");
		$total_ans =  $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pollsa");
		$total_votes = 0;
		$total_voters = 0;
?>
		<!-- Last Action -->
		<div id="message" class="updated" style="display: none;"></div>

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
							if(function_exists('dynamic_sidebar')) {
								$options = get_option('widget_polls');
								$multiple_polls = explode(',', $options['multiple_polls']);
							} else {
								$multiple_polls = array();
							}
							$i = 0;
							$current_poll = intval(get_option('poll_currentpoll'));
							$latest_poll = intval(get_option('poll_latestpoll'));
							foreach($polls as $poll) {
								$poll_id = intval($poll->pollq_id);
								$poll_question = stripslashes($poll->pollq_question);
								$poll_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll->pollq_timestamp));
								$poll_totalvotes = intval($poll->pollq_totalvotes);
								$poll_totalvoters = intval($poll->pollq_totalvoters);
								$poll_active = intval($poll->pollq_active);
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
									if($current_poll == $poll_id) {
										$style = 'class="highlight"';
									}
								} elseif($current_poll == 0) {
									if($poll_id == $latest_poll) {
										$style = 'class="highlight"';
									}
								} else if(in_array($poll_id, $multiple_polls)) {
									$style = 'class="highlight"';
								}
								echo "<tr id=\"poll-$poll_id\" $style>\n";
								echo '<td><strong>'.number_format_i18n($poll_id).'</strong></td>'."\n";
								echo '<td>';
								if($current_poll > 0) {
									if($current_poll == $poll_id) {
										echo '<strong>'.__('Displayed:', 'wp-polls').'</strong> ';
									}
								} elseif($current_poll == 0) {
									if($poll_id == $latest_poll) {
										echo '<strong>'.__('Displayed:', 'wp-polls').'</strong> ';
									}
								} else if(in_array($poll_id, $multiple_polls)) {
										echo '<strong>'.__('Displayed:', 'wp-polls').'</strong> ';
								}
								echo "$poll_question</td>\n";
								echo '<td>'.number_format_i18n($poll_totalvoters)."</td>\n";
								echo "<td>$poll_date</td>\n";
								echo "<td>$poll_expiry_text</td>\n";
								echo '<td>';
								if($poll_active == 1) {
									_e('Open', 'wp-polls');
								} elseif($poll_active == -1) {
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
				$poll_ips = intval($wpdb->get_var("SELECT COUNT(pollip_id) FROM $wpdb->pollsip"));
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
