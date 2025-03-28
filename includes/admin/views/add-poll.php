<?php
/**
 * Admin View: Add Poll
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Determine text alignment based on RTL.
$last_col_align = is_rtl() ? 'right' : 'left';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Add New Poll', 'wp-polls' ); ?></h1>
	
	<?php WP_Polls_Manager::display_messages(); ?>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=polls-manager&mode=add' ) ); ?>">
		<?php wp_nonce_field( 'wp-polls_add-poll' ); ?>
		
		<!-- Poll Question -->
		<h2><?php esc_html_e( 'Poll Question', 'wp-polls' ); ?></h2>
		<table class="form-table">
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'Question', 'wp-polls' ); ?></th>
				<td width="80%">
					<input type="text" size="70" maxlength="200" name="pollq_question" placeholder="<?php esc_attr_e( 'Enter your poll question here', 'wp-polls' ); ?>" />
				</td>
			</tr>
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'Poll Type', 'wp-polls' ); ?></th>
				<td width="80%">
					<select name="pollq_type" id="pollq_type">
						<option value="standard"><?php esc_html_e( 'Standard Poll (Radio Buttons)', 'wp-polls' ); ?></option>
						<option value="multiple"><?php esc_html_e( 'Multiple Choice (Checkboxes)', 'wp-polls' ); ?></option>
						<option value="ranked"><?php esc_html_e( 'Ranked Choice (Drag to rank)', 'wp-polls' ); ?></option>
					</select>
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
						<?php esc_html_e( 'Status', 'wp-polls' ); ?>
					</th>
				</tr>
			</thead>
			<tbody id="poll_answers">
				<?php for ( $i = 1; $i <= 10; $i++ ) { ?>
					<tr id="poll-answer-<?php echo esc_attr( $i ); ?>">
						<th width="20%" scope="row" valign="top">
							<?php printf( esc_html__( 'Answer %s', 'wp-polls' ), number_format_i18n( $i ) ); ?>
						</th>
						<td width="60%">
							<input type="text" size="50" maxlength="200" name="polla_answers[]" value="" />
						</td>
						<td width="20%" align="<?php echo esc_attr( $last_col_align ); ?>">
							<?php
							if ( $i <= 2 ) {
								esc_html_e( 'Required', 'wp-polls' );
							} else {
								echo '<span id="pollAnswer'.$i.'Required">';
								esc_html_e( 'Optional', 'wp-polls' );
								echo '</span>';
							}
							?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
			<tbody>
				<tr>
					<td width="20%">&nbsp;</td>
					<td width="60%">
						<input type="button" value="<?php esc_attr_e( 'Add New Answer', 'wp-polls' ); ?>" onclick="add_poll_answer_add();" class="button" />
					</td>
					<td width="20%">&nbsp;</td>
				</tr>
			</tbody>
		</table>
		
		<!-- Poll Multiple Answers -->
		<h2 id="multiple-answers-header" style="display: none;"><?php esc_html_e( 'Poll Multiple Answers', 'wp-polls' ); ?></h2>
		<table class="form-table" id="multiple-answers-options" style="display: none;">
			<tr>
				<th width="40%" scope="row" valign="top">
					<?php esc_html_e( 'Maximum Number Of Selected Answers Allowed?', 'wp-polls' ); ?>
				</th>
				<td width="60%">
					<select name="pollq_multiple" id="pollq_multiple" size="1">
						<?php
						for ( $i = 1; $i <= 10; $i++ ) {
							echo '<option value="' . esc_attr( $i ) . '">' . esc_html( number_format_i18n( $i ) ) . '</option>';
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
					<?php echo esc_html( mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) ) ); ?><br />
					<input type="checkbox" name="custom_timestamp" id="custom_timestamp" value="1" onclick="check_custom_timestamp();" />
					&nbsp;<label for="custom_timestamp"><?php esc_html_e( 'Custom Start Date/Time', 'wp-polls' ); ?></label><br />
					<?php WP_Polls_Utility::datetime_selector( current_time( 'timestamp' ), 'pollq_timestamp', 'none' ); ?>
				</td>
			</tr>
			<tr>
				<th width="20%" scope="row" valign="top"><?php esc_html_e( 'End Date/Time', 'wp-polls' ); ?></th>
				<td width="80%">
					<input type="checkbox" name="enable_expiry" id="enable_expiry" value="1" onclick="check_expiry();" />
					&nbsp;<label for="enable_expiry"><?php esc_html_e( 'Set Poll End Date/Time', 'wp-polls' ); ?></label><br />
					<?php WP_Polls_Utility::datetime_selector( current_time( 'timestamp' ) + ( 60 * 60 * 24 * 14 ), 'pollq_expiry', 'none' ); ?>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="do" value="<?php esc_attr_e( 'Add Poll', 'wp-polls' ); ?>" class="button-primary" />
			&nbsp;&nbsp;
			<input type="button" name="cancel" value="<?php esc_attr_e( 'Cancel', 'wp-polls' ); ?>" class="button" onclick="javascript:history.go(-1)" />
		</p>
	</form>
</div>

<script type="text/javascript">
// <![CDATA[
var answer_count = 10;

// Toggle Poll Type Options
function check_poll_type() {
	var poll_type = jQuery('#pollq_type').val();
	if (poll_type === 'multiple') {
		jQuery('#multiple-answers-header').show();
		jQuery('#multiple-answers-options').show();
	} else {
		jQuery('#multiple-answers-header').hide();
		jQuery('#multiple-answers-options').hide();
	}
}

// Toggle Custom Timestamp
function check_custom_timestamp() {
	if (jQuery('#custom_timestamp').is(':checked')) {
		jQuery('#pollq_timestamp').show();
	} else {
		jQuery('#pollq_timestamp').hide();
	}
}

// Toggle Expiry
function check_expiry() {
	if (jQuery('#enable_expiry').is(':checked')) {
		jQuery('#pollq_expiry').show();
	} else {
		jQuery('#pollq_expiry').hide();
	}
}

// Add New Poll Answer
function add_poll_answer_add() {
	answer_count++;
	jQuery('#poll_answers').append(
		'<tr id="poll-answer-' + answer_count + '">' +
		'<th width="20%" scope="row" valign="top"><?php echo esc_js( __( 'Answer', 'wp-polls' ) ); ?> ' + answer_count + '</th>' +
		'<td width="60%">' +
		'<input type="text" size="50" maxlength="200" name="polla_answers[]" />' +
		'</td>' +
		'<td width="20%" align="<?php echo esc_js( $last_col_align ); ?>">' +
		'<span id="pollAnswer' + answer_count + 'Required"><?php echo esc_js( __( 'Optional', 'wp-polls' ) ); ?></span>' +
		'</td>' +
		'</tr>'
	);
}

// Initialize
jQuery(document).ready(function() {
	check_custom_timestamp();
	check_expiry();
	check_poll_type();
	
	jQuery('#pollq_type').on('change', function() {
		check_poll_type();
	});
});
// ]]>
</script>
