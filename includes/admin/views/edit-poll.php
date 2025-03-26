<?php
/**
 * Admin View: Edit Poll
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get poll data.
$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$poll = WP_Polls_Data::get_poll( $poll_id );

if ( ! $poll ) {
	wp_die( esc_html__( 'Poll not found.', 'wp-polls' ) );
}

// Prepare poll data.
$poll_question = isset( $poll->pollq_question ) ? WP_Polls_Utility::remove_slashes( $poll->pollq_question ) : '';
$poll_total_votes = isset( $poll->pollq_totalvotes ) ? (int) $poll->pollq_totalvotes : 0;
$poll_total_voters = isset( $poll->pollq_totalvoters ) ? (int) $poll->pollq_totalvoters : 0;
$poll_timestamp = isset( $poll->pollq_timestamp ) ? (int) $poll->pollq_timestamp : 0;
$poll_active = isset( $poll->pollq_active ) ? (int) $poll->pollq_active : 0;
$poll_expiry = isset( $poll->pollq_expiry ) ? $poll->pollq_expiry : 0;
$poll_multiple = isset( $poll->pollq_multiple ) ? (int) $poll->pollq_multiple : 0;
$poll_answers = isset( $poll->answers ) ? $poll->answers : array();

// Determine the text alignment based on RTL.
$last_col_align = is_rtl() ? 'right' : 'left';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Edit Poll', 'wp-polls' ); ?></h1>
	
	<?php WP_Polls_Manager::display_messages(); ?>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=polls-manager&mode=edit&id=' . $poll_id ) ); ?>">
		<?php wp_nonce_field( 'wp-polls_edit-poll' ); ?>
		<input type="hidden" name="pollq_id" value="<?php echo esc_attr( $poll_id ); ?>" />
		<input type="hidden" name="pollq_active" value="<?php echo esc_attr( $poll_active ); ?>" />
		<input type="hidden" name="poll_timestamp_old" value="<?php echo esc_attr( $poll_timestamp ); ?>" />
		
		<!-- Poll Question -->
		<h2><?php esc_html_e( 'Poll Question', 'wp-polls' ); ?></h2>
		<table class="form-table">
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'Question', 'wp-polls' ); ?></th>
				<td width="80%">
					<input type="text" size="70" name="pollq_question" value="<?php echo esc_attr( $poll_question ); ?>" />
				</td>
			</tr>
		</table>
		
		<!-- Poll Answers -->
		<h2><?php esc_html_e( 'Poll Answers', 'wp-polls' ); ?></h2>
		<table class="form-table">
			<thead>
				<tr>
					<th width="20%" scope="row" valign="top"><?php esc_html_e( 'Answer No.', 'wp-polls' ); ?></th>
					<th width="60%" scope="row" valign="top"><?php esc_html_e( 'Answer Text', 'wp-polls' ); ?></th>
					<th width="20%" scope="row" valign="top" style="text-align: <?php echo esc_attr( $last_col_align ); ?>;">
						<?php esc_html_e( 'No. Of Votes', 'wp-polls' ); ?>
					</th>
				</tr>
			</thead>
			<tbody id="poll_answers">
				<?php
				$i = 1;
				$poll_actual_totalvotes = 0;
				
				if ( $poll_answers ) {
					foreach ( $poll_answers as $poll_answer ) {
						$polla_aid = (int) $poll_answer->polla_aid;
						$polla_answers = WP_Polls_Utility::remove_slashes( $poll_answer->polla_answers );
						$polla_votes = (int) $poll_answer->polla_votes;
						$poll_actual_totalvotes += $polla_votes;
						?>
						<tr id="poll-answer-<?php echo esc_attr( $polla_aid ); ?>">
							<th width="20%" scope="row" valign="top">
								<?php printf( esc_html__( 'Answer %s', 'wp-polls' ), number_format_i18n( $i ) ); ?>
							</th>
							<td width="60%">
								<input type="text" size="50" maxlength="200" name="polla_aid-<?php echo esc_attr( $polla_aid ); ?>" value="<?php echo esc_attr( $polla_answers ); ?>" />
								&nbsp;&nbsp;&nbsp;
								<input type="button" value="<?php esc_attr_e( 'Delete', 'wp-polls' ); ?>" 
									onclick="delete_poll_ans(
										<?php echo esc_js( $poll_id ); ?>, 
										<?php echo esc_js( $polla_aid ); ?>, 
										<?php echo esc_js( $polla_votes ); ?>, 
										'<?php echo esc_js( sprintf( __( 'You are about to delete this poll\'s answer \'%s\'.', 'wp-polls' ), esc_attr( $polla_answers ) ) ); ?>', 
										'<?php echo esc_js( wp_create_nonce( 'wp-polls_delete-poll-answer' ) ); ?>'
									);" 
									class="button" />
							</td>
							<td width="20%" align="<?php echo esc_attr( $last_col_align ); ?>">
								<?php echo esc_html( number_format_i18n( $polla_votes ) ); ?> 
								<input type="text" size="4" id="polla_votes-<?php echo esc_attr( $polla_aid ); ?>" 
									name="polla_votes-<?php echo esc_attr( $polla_aid ); ?>" 
									value="<?php echo esc_attr( $polla_votes ); ?>" 
									onblur="check_totalvotes();" />
							</td>
						</tr>
						<?php
						$i++;
					}
				}
				?>
			</tbody>
			<tbody>
				<tr>
					<td width="20%">&nbsp;</td>
					<td width="60%">
						<input type="button" value="<?php esc_attr_e( 'Add Answer', 'wp-polls' ); ?>" onclick="add_poll_answer_edit();" class="button" />
					</td>
					<td width="20%" align="<?php echo esc_attr( $last_col_align ); ?>">
						<strong><?php esc_html_e( 'Total Votes:', 'wp-polls' ); ?></strong> 
						<strong id="poll_total_votes"><?php echo esc_html( number_format_i18n( $poll_actual_totalvotes ) ); ?></strong> 
						<input type="text" size="4" readonly="readonly" id="pollq_totalvotes" name="pollq_totalvotes" 
							value="<?php echo esc_attr( $poll_actual_totalvotes ); ?>" onblur="check_totalvotes();" />
					</td>
				</tr>
				<tr>
					<td width="20%">&nbsp;</td>
					<td width="60%">&nbsp;</td>
					<td width="20%" align="<?php echo esc_attr( $last_col_align ); ?>">
						<strong><?php esc_html_e( 'Total Voters:', 'wp-polls' ); ?> 
							<?php echo esc_html( number_format_i18n( $poll_total_voters ) ); ?>
						</strong> 
						<input type="text" size="4" name="pollq_totalvoters" value="<?php echo esc_attr( $poll_total_voters ); ?>" />
					</td>
				</tr>
			</tbody>
		</table>
		
		<!-- Poll Multiple Answers -->
		<h2><?php esc_html_e( 'Poll Multiple Answers', 'wp-polls' ); ?></h2>
		<table class="form-table">
			<tr>
				<th width="40%" scope="row" valign="top">
					<?php esc_html_e( 'Allows Users To Select More Than One Answer?', 'wp-polls' ); ?>
				</th>
				<td width="60%">
					<select name="pollq_multiple_yes" id="pollq_multiple_yes" size="1" onchange="check_pollq_multiple();">
						<option value="0" <?php selected( '0', $poll_multiple ); ?>>
							<?php esc_html_e( 'No', 'wp-polls' ); ?>
						</option>
						<option value="1" <?php selected( $poll_multiple > 0 ); ?>>
							<?php esc_html_e( 'Yes', 'wp-polls' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th width="40%" scope="row" valign="top">
					<?php esc_html_e( 'Maximum Number Of Selected Answers Allowed?', 'wp-polls' ); ?>
				</th>
				<td width="60%">
					<select name="pollq_multiple" id="pollq_multiple" size="1" <?php disabled( $poll_multiple == 0 ); ?>>
						<?php
						$poll_answer_count = count( $poll_answers );
						for ( $i = 1; $i <= $poll_answer_count; $i++ ) {
							if ( $poll_multiple > 0 && $poll_multiple == $i ) {
								echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( number_format_i18n( $i ) ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $i ) . '">' . esc_html( number_format_i18n( $i ) ) . '</option>';
							}
						}
						?>
					</select>
				</td>
			</tr>
		</table>
		
		<!-- Poll Start/End Date -->
		<h2><?php esc_html_e( 'Poll Start/End Date', 'wp-polls' ); ?></h2>
		<table class="form-table">
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'Start Date/Time', 'wp-polls' ); ?></th>
				<td width="80%">
					<?php echo esc_html( mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_timestamp ) ) ); ?><br />
					<input type="checkbox" name="edit_polltimestamp" id="edit_polltimestamp" value="1" onclick="check_polltimestamp()" />
					&nbsp;<label for="edit_polltimestamp"><?php esc_html_e( 'Edit Start Date/Time', 'wp-polls' ); ?></label><br />
					<?php WP_Polls_Utility::datetime_selector( $poll_timestamp, 'pollq_timestamp', 'none' ); ?>
				</td>
			</tr>
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'End Date/Time', 'wp-polls' ); ?></th>
				<td width="80%">
					<?php
					if ( empty( $poll_expiry ) ) {
						esc_html_e( 'This Poll Will Not Expire', 'wp-polls' );
					} else {
						echo esc_html( mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) ) );
					}
					?>
					<br />
					<input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" 
						onclick="check_pollexpiry();" <?php checked( empty( $poll_expiry ) ); ?> />
					<label for="pollq_expiry_no"><?php esc_html_e( 'Do NOT Expire This Poll', 'wp-polls' ); ?></label><br />
					<?php
					if ( empty( $poll_expiry ) ) {
						WP_Polls_Utility::datetime_selector( current_time( 'timestamp' ), 'pollq_expiry', 'none' );
					} else {
						WP_Polls_Utility::datetime_selector( $poll_expiry, 'pollq_expiry' );
					}
					?>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="do" value="<?php esc_attr_e( 'Edit Poll', 'wp-polls' ); ?>" class="button-primary" />
			&nbsp;&nbsp;
			<?php
			if ( 1 === $poll_active ) {
				$poll_open_display = 'none';
				$poll_close_display = 'inline';
			} else {
				$poll_open_display = 'inline';
				$poll_close_display = 'none';
			}
			?>
			<input type="button" class="button" name="do" id="close_poll" 
				value="<?php esc_attr_e( 'Close Poll', 'wp-polls' ); ?>" 
				onclick="closing_poll(
					<?php echo esc_js( $poll_id ); ?>, 
					'<?php echo esc_js( sprintf( __( 'You are about to CLOSE this poll \'%s\'.', 'wp-polls' ), esc_attr( $poll_question ) ) ); ?>', 
					'<?php echo esc_js( wp_create_nonce( 'wp-polls_close-poll' ) ); ?>'
				);" 
				style="display: <?php echo esc_attr( $poll_close_display ); ?>;" />
			<input type="button" class="button" name="do" id="open_poll" 
				value="<?php esc_attr_e( 'Open Poll', 'wp-polls' ); ?>" 
				onclick="opening_poll(
					<?php echo esc_js( $poll_id ); ?>, 
					'<?php echo esc_js( sprintf( __( 'You are about to OPEN this poll \'%s\'.', 'wp-polls' ), esc_attr( $poll_question ) ) ); ?>', 
					'<?php echo esc_js( wp_create_nonce( 'wp-polls_open-poll' ) ); ?>'
				);" 
				style="display: <?php echo esc_attr( $poll_open_display ); ?>;" />
			&nbsp;&nbsp;
			<input type="button" name="cancel" value="<?php esc_attr_e( 'Cancel', 'wp-polls' ); ?>" class="button" onclick="javascript:history.go(-1)" />
		</p>
	</form>
</div>

<script type="text/javascript">
// <![CDATA[
function check_pollq_multiple() {
	if (jQuery('#pollq_multiple_yes').val() === '1') {
		jQuery('#pollq_multiple').prop('disabled', false);
	} else {
		jQuery('#pollq_multiple').prop('disabled', true);
	}
}

function check_polltimestamp() {
	if (jQuery('#edit_polltimestamp').is(':checked')) {
		jQuery('#pollq_timestamp').show();
	} else {
		jQuery('#pollq_timestamp').hide();
	}
}

function check_pollexpiry() {
	if (jQuery('#pollq_expiry_no').is(':checked')) {
		jQuery('#pollq_expiry').hide();
	} else {
		jQuery('#pollq_expiry').show();
	}
}

function add_poll_answer_edit() {
	var answer_count = jQuery('#poll_answers tr').length + 1;
	jQuery('#poll_answers').append(
		'<tr id="poll-answer-new-' + answer_count + '">' +
		'<th width="20%" scope="row" valign="top"><?php echo esc_js( __( 'New Answer', 'wp-polls' ) ); ?></th>' +
		'<td width="60%">' +
		'<input type="text" size="50" maxlength="200" name="polla_answers_new[]" />' +
		'</td>' +
		'<td width="20%" align="<?php echo esc_js( $last_col_align ); ?>">' +
		'<input type="text" size="4" name="polla_answers_new_votes[]" value="0" />' +
		'</td>' +
		'</tr>'
	);
}

// Check Total Votes
function check_totalvotes() {
	var total_votes = 0;
	jQuery('input[name^="polla_votes-"]').each(function() {
		total_votes += parseInt(jQuery(this).val(), 10);
	});
	jQuery('#poll_total_votes').text(total_votes);
	jQuery('#pollq_totalvotes').val(total_votes);
}

// Initialize
jQuery(document).ready(function() {
	check_pollq_multiple();
	check_polltimestamp();
	check_pollexpiry();
});
// ]]>
</script>
