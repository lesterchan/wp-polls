<?php
### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
	die('Access Denied');
}

### Poll Manager
$base_name = plugin_basename('wp-polls/polls-manager.php');
$base_page = 'admin.php?page='.$base_name;

### Form Processing
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		// Add Poll
		case __('Add Poll', 'wp-polls'):
			check_admin_referer('wp-polls_add-poll');
			// Poll Question
			$pollq_question = addslashes(trim($_POST['pollq_question']));
			if( ! empty( $pollq_question ) ) {
				// Poll Start Date
				$timestamp_sql = '';
				$pollq_timestamp_day = intval($_POST['pollq_timestamp_day']);
				$pollq_timestamp_month = intval($_POST['pollq_timestamp_month']);
				$pollq_timestamp_year = intval($_POST['pollq_timestamp_year']);
				$pollq_timestamp_hour = intval($_POST['pollq_timestamp_hour']);
				$pollq_timestamp_minute = intval($_POST['pollq_timestamp_minute']);
				$pollq_timestamp_second = intval($_POST['pollq_timestamp_second']);
				$pollq_timestamp = gmmktime($pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year);
				if ($pollq_timestamp > current_time('timestamp')) {
					$pollq_active = -1;
				} else {
					$pollq_active = 1;
				}
				// Poll End Date
				$pollq_expiry_no = intval($_POST['pollq_expiry_no']);
				if ($pollq_expiry_no == 1) {
					$pollq_expiry = '';
				} else {
					$pollq_expiry_day = intval($_POST['pollq_expiry_day']);
					$pollq_expiry_month = intval($_POST['pollq_expiry_month']);
					$pollq_expiry_year = intval($_POST['pollq_expiry_year']);
					$pollq_expiry_hour = intval($_POST['pollq_expiry_hour']);
					$pollq_expiry_minute = intval($_POST['pollq_expiry_minute']);
					$pollq_expiry_second = intval($_POST['pollq_expiry_second']);
					$pollq_expiry = gmmktime($pollq_expiry_hour, $pollq_expiry_minute, $pollq_expiry_second, $pollq_expiry_month, $pollq_expiry_day, $pollq_expiry_year);
					if ($pollq_expiry <= current_time('timestamp')) {
						$pollq_active = 0;
					}
				}
				// Mutilple Poll
				$pollq_multiple_yes = intval($_POST['pollq_multiple_yes']);
				$pollq_multiple = 0;
				if ($pollq_multiple_yes == 1) {
					$pollq_multiple = intval($_POST['pollq_multiple']);
				} else {
					$pollq_multiple = 0;
				}
				// Insert Poll
				$add_poll_question = $wpdb->query("INSERT INTO $wpdb->pollsq VALUES (0, '$pollq_question', '$pollq_timestamp', 0, $pollq_active, '$pollq_expiry', $pollq_multiple, 0)");
				if (!$add_poll_question) {
					$text .= '<p style="color: red;">' . sprintf(__('Error In Adding Poll \'%s\'.', 'wp-polls'), stripslashes($pollq_question)) . '</p>';
				}
				// Add Poll Answers
				$polla_answers = $_POST['polla_answers'];
				$polla_qid = intval($wpdb->insert_id);
				foreach ($polla_answers as $polla_answer) {
					$polla_answer = addslashes(trim($polla_answer));
					if( ! empty( $polla_answer ) ) {
						$add_poll_answers = $wpdb->query("INSERT INTO $wpdb->pollsa VALUES (0, $polla_qid, '$polla_answer', 0)");
						if (!$add_poll_answers) {
							$text .= '<p style="color: red;">' . sprintf(__('Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls'), stripslashes($polla_answer)) . '</p>';
						}
					} else {
						$text .= '<p style="color: red;">' . __( 'Poll\'s Answer is empty.', 'wp-polls' ) . '</p>';
					}
				}
				// Update Lastest Poll ID To Poll Options
				$latest_pollid = polls_latest_id();
				$update_latestpoll = update_option('poll_latestpoll', $latest_pollid);
				if ( empty( $text ) ) {
					$text = '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' (ID: %s) added successfully. Embed this poll with the shortcode: %s or go back to <a href="%s">Manage Polls</a>', 'wp-polls' ), stripslashes( $pollq_question ), $latest_pollid, '<input type="text" value=\'[poll id="' . $latest_pollid . '"]\' readonly="readonly" size="10" />', $base_page ) . '</p>';
				} else {
					if( $add_poll_question ) {
						$text .= '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' (ID: %s) (Shortcode: %s) added successfully, but there are some errors with the Poll\'s Answers. Embed this poll with the shortcode: %s or go back to <a href="%s">Manage Polls</a>', 'wp-polls' ), stripslashes( $pollq_question ), $latest_pollid, '<input type="text" value=\'[poll id="' . $latest_pollid . '"]\' readonly="readonly" size="10" />' ) .'</p>';
					}
				}
				cron_polls_place();
			} else {
				$text .= '<p style="color: red;">' . __( 'Poll Question is empty.', 'wp-polls' ) . '</p>';
			}
			break;
	}
}

### Add Poll Form
$poll_noquestion = 2;
$count = 0;
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.stripslashes($text).'</div>'; } ?>
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<?php wp_nonce_field('wp-polls_add-poll'); ?>
<div class="wrap">
	<h2><?php _e('Add Poll', 'wp-polls'); ?></h2>
	<!-- Poll Question -->
	<h3><?php _e('Poll Question', 'wp-polls'); ?></h3>
	<table class="form-table">
		<tr>
			<th width="20%" scope="row" valign="top"><?php _e('Question', 'wp-polls') ?></th>
			<td width="80%"><input type="text" size="70" name="pollq_question" value="" /></td>
		</tr>
	</table>
	<!-- Poll Answers -->
	<h3><?php _e('Poll Answers', 'wp-polls'); ?></h3>
	<table class="form-table">
		<tfoot>
			<tr>
				<td width="20%">&nbsp;</td>
				<td width="80%"><input type="button" value="<?php _e('Add Answer', 'wp-polls') ?>" onclick="add_poll_answer_add();" class="button" /></td>
			</tr>
		</tfoot>
		<tbody id="poll_answers">
		<?php
			for($i = 1; $i <= $poll_noquestion; $i++) {
				echo "<tr id=\"poll-answer-$i\">\n";
				echo "<th width=\"20%\" scope=\"row\" valign=\"top\">".sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($i))."</th>\n";
				echo "<td width=\"80%\"><input type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_answers[]\" />&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"".__('Remove', 'wp-polls')."\" onclick=\"remove_poll_answer_add(".$i.");\" class=\"button\" /></td>\n";
				echo "</tr>\n";
				$count++;
			}
		?>
		</tbody>
	</table>
	<!-- Poll Multiple Answers -->
	<h3><?php _e('Poll Multiple Answers', 'wp-polls') ?></h3>
	<table class="form-table">
		<tr>
			<th width="40%" scope="row" valign="top"><?php _e('Allows Users To Select More Than One Answer?', 'wp-polls'); ?></th>
			<td width="60%">
				<select name="pollq_multiple_yes" id="pollq_multiple_yes" size="1" onchange="check_pollq_multiple();">
					<option value="0"><?php _e('No', 'wp-polls'); ?></option>
					<option value="1"><?php _e('Yes', 'wp-polls'); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th width="40%" scope="row" valign="top"><?php _e('Maximum Number Of Selected Answers Allowed?', 'wp-polls') ?></th>
			<td width="60%">
				<select name="pollq_multiple" id="pollq_multiple" size="1" disabled="disabled">
					<?php
						for($i = 1; $i <= $poll_noquestion; $i++) {
							echo "<option value=\"$i\">".number_format_i18n($i)."</option>\n";
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
			<th width="20%" scope="row" valign="top"><?php _e('Start Date/Time', 'wp-polls') ?></th>
			<td width="80%"><?php poll_timestamp(current_time('timestamp')); ?></td>
		</tr>
		<tr>
			<th width="20%" scope="row" valign="top"><?php _e('End Date/Time', 'wp-polls') ?></th>
			<td width="80%"><input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" checked="checked" onclick="check_pollexpiry();" />&nbsp;&nbsp;<label for="pollq_expiry_no"><?php _e('Do NOT Expire This Poll', 'wp-polls'); ?></label><?php poll_timestamp(current_time('timestamp'), 'pollq_expiry', 'none'); ?></td>
		</tr>
	</table>
	<p style="text-align: center;"><input type="submit" name="do" value="<?php _e('Add Poll', 'wp-polls'); ?>"  class="button-primary" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="javascript:history.go(-1)" /></p>
</div>
</form>