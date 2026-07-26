<?php
/**
 * Poll Templates admin screen.
 *
 * @package WP-Polls
 */

// Check Whether User Can Manage Polls.
if ( ! current_user_can( 'manage_polls' ) ) {
	die( 'Access Denied' );
}

// Variables Variables Variables.
$base_name = WP_POLLS_SLUG . '/polls-templates.php';
$base_page = 'admin.php?page=' . $base_name;

?>
<script type="text/javascript">
/* <![CDATA[*/
(function () {
	// Defaults come from Polls_Templates so this screen and the activation
	// routine cannot drift apart. Before 3.0.0 the markup was written out
	// twice, which is how one copy kept its inline onclick handlers after the
	// other had them removed.
	var pollDefaults = <?php echo wp_json_encode( Polls_Templates::defaults() ); ?>;

	document.addEventListener("click", function (event) {
		var target = event.target;
		if (!target || typeof target.closest !== "function") {
			return;
		}
		var button = target.closest('[data-poll-action="restore-template"]');
		if (!button) {
			return;
		}
		var key = button.getAttribute("data-poll-template");
		var field = document.getElementById("poll_template_" + key);
		if (field && Object.prototype.hasOwnProperty.call(pollDefaults, key)) {
			field.value = pollDefaults[key];
		}
	});
})();
/* ]]> */
</script>
<?php settings_errors(); ?>
<form id="poll_template_form" method="post" action="options.php">
<?php settings_fields( Polls_Settings::GROUP ); ?>
<div class="wrap">
	<h2><?php esc_html_e( 'Poll Templates', 'wp-polls' ); ?></h2>
	<!-- Template Variables -->
	<h3><?php esc_html_e( 'Template Variables', 'wp-polls' ); ?></h3>
	<table class="widefat">
		<tr>
			<td>
				<strong>%POLL_ID%</strong><br />
				<?php esc_html_e( 'Display the poll\'s ID', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER_ID%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer ID', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong>%POLL_QUESTION%</strong><br />
				<?php esc_html_e( 'Display the poll\'s question', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_TOTALVOTES%</strong><br />
				<?php esc_html_e( 'Display the poll\'s total votes NOT the number of people who voted for the poll', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER_TEXT%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer without HTML formatting.', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong>%POLL_RESULT_URL%</strong><br />
				<?php esc_html_e( 'Displays URL to poll\'s result', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER_VOTES%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer votes', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_MOST_ANSWER%</strong><br />
				<?php esc_html_e( 'Display the poll\'s most voted answer', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER_PERCENTAGE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer percentage', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong>%POLL_MOST_VOTES%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer votes for the most voted answer', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ANSWER_IMAGEWIDTH%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer image width', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_MOST_PERCENTAGE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer percentage for the most voted answer', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_LEAST_ANSWER%</strong><br />
				<?php esc_html_e( 'Display the poll\'s least voted answer', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong>%POLL_START_DATE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s start date/time', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_LEAST_VOTES%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer votes for the least voted answer', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_END_DATE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s end date/time', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_LEAST_PERCENTAGE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s answer percentage for the least voted answer', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong>%POLL_MULTIPLE_ANS_MAX%</strong><br />
				<?php esc_html_e( 'Display the the maximum number of answers the user can choose if the poll supports multiple answers', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_CHECKBOX_RADIO%</strong><br />
				<?php esc_html_e( 'Display "checkbox" or "radio" input types depending on the poll type', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_TOTALVOTERS%</strong><br />
				<?php esc_html_e( 'Display the number of people who voted for the poll NOT the total votes of the poll', 'wp-polls' ); ?>
			</td>
			<td>
				<strong>%POLL_ARCHIVE_URL%</strong><br />
				<?php esc_html_e( 'Display the poll archive URL', 'wp-polls' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong>%POLL_MULTIPLE_ANSWER_PERCENTAGE%</strong><br />
				<?php esc_html_e( 'Display the poll\'s mutiple answer percentage. This is total votes divided by total voters.', 'wp-polls' ); ?>
			</td>
			<td>
				&nbsp;
			</td>
		</tr>
		<tr class="alternate">
			<td colspan="2">
				<?php
				// The two token names are emitted outside the translatable string on
				// purpose: phpcbf reads %TOKEN% inside __() as a printf placeholder and
				// renumbers it to %1$TOKEN%, which would then be shown to the user.
				printf(
					/* translators: 1: %POLL_TOTALVOTES% token, 2: %POLL_TOTALVOTERS% token. */
					esc_html__( 'Note: %1$s and %2$s will be different if your poll supports multiple answers. If your poll allows only single answer, both value will be the same.', 'wp-polls' ),
					'<strong><code>%POLL_TOTALVOTES%</code></strong>',
					'<strong><code>%POLL_TOTALVOTERS%</code></strong>'
				);
				?>
			</td>
		</tr>
	</table>

	<!-- Poll Voting Form Templates -->
	<h3><?php esc_html_e( 'Poll Voting Form Templates', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Voting Form Header:', 'wp-polls' ); ?></strong><br /><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_QUESTION%</p>
				<p style="margin: 2px 0">- %POLL_START_DATE%</p>
				<p style="margin: 2px 0">- %POLL_END_DATE%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="voteheader" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_voteheader" name="poll_options[templates][voteheader]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.voteheader' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Voting Form Body:', 'wp-polls' ); ?></strong><br /><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_CHECKBOX_RADIO%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="votebody" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_votebody" name="poll_options[templates][votebody]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.votebody' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Voting Form Footer:', 'wp-polls' ); ?></strong><br /><br /><br />
					<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
					<p style="margin: 2px 0">- %POLL_ID%</p>
					<p style="margin: 2px 0">- %POLL_RESULT_URL%</p>
					<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="votefooter" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_votefooter" name="poll_options[templates][votefooter]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.votefooter' ) ) ); ?></textarea></td>
		</tr>
	</table>

	<!-- Poll Result Templates -->
	<h3><?php esc_html_e( 'Poll Result Templates', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Result Header:', 'wp-polls' ); ?></strong><br /><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_QUESTION%</p>
				<p style="margin: 2px 0">- %POLL_START_DATE%</p>
				<p style="margin: 2px 0">- %POLL_END_DATE%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="resultheader" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_resultheader" name="poll_options[templates][resultheader]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.resultheader' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Result Body:', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When The User HAS NOT Voted', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_TEXT%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANSWER_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_IMAGEWIDTH%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="resultbody" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_resultbody" name="poll_options[templates][resultbody]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.resultbody' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Result Body:', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When The User HAS Voted', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_TEXT%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANSWER_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_ANSWER_IMAGEWIDTH%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="resultbody2" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_resultbody2" name="poll_options[templates][resultbody2]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.resultbody2' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Result Footer:', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When The User HAS Voted', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_START_DATE%</p>
				<p style="margin: 2px 0">- %POLL_END_DATE%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
				<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="resultfooter" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_resultfooter" name="poll_options[templates][resultfooter]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.resultfooter' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Result Footer:', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When The User HAS NOT Voted', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ID%</p>
				<p style="margin: 2px 0">- %POLL_START_DATE%</p>
				<p style="margin: 2px 0">- %POLL_END_DATE%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
				<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="resultfooter2" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_resultfooter2" name="poll_options[templates][resultfooter2]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.resultfooter2' ) ) ); ?></textarea></td>
		</tr>
	</table>

	<!-- Poll Archive Templates -->
	<h3><?php esc_html_e( 'Poll Archive Templates', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Poll Archive Link', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Template For Displaying Poll Archive Link', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_ARCHIVE_URL%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="pollarchivelink" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_pollarchivelink" name="poll_options[templates][pollarchivelink]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.pollarchivelink' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Individual Poll Header', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed Before Each Poll In The Poll Archive', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- <?php esc_html_e( 'N/A', 'wp-polls' ); ?></p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="pollarchiveheader" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_pollarchiveheader" name="poll_options[templates][pollarchiveheader]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.pollarchiveheader' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Individual Poll Footer', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed After Each Poll In The Poll Archive', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- %POLL_START_DATE%</p>
				<p style="margin: 2px 0">- %POLL_END_DATE%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
				<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
				<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
				<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
				<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="pollarchivefooter" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_pollarchivefooter" name="poll_options[templates][pollarchivefooter]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.pollarchivefooter' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Paging Header', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed Before Paging In The Poll Archive', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- <?php esc_html_e( 'N/A', 'wp-polls' ); ?></p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="pollarchivepagingheader" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_pollarchivepagingheader" name="poll_options[templates][pollarchivepagingheader]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.pollarchivepagingheader' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Paging Footer', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed After Paging In The Poll Archive', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- <?php esc_html_e( 'N/A', 'wp-polls' ); ?></p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="pollarchivepagingfooter" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_pollarchivepagingfooter" name="poll_options[templates][pollarchivepagingfooter]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.pollarchivepagingfooter' ) ) ); ?></textarea></td>
		</tr>
	</table>

	<!-- Poll Misc Templates -->
	<h3><?php esc_html_e( 'Poll Misc Templates', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Poll Disabled', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When The Poll Is Disabled', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- <?php esc_html_e( 'N/A', 'wp-polls' ); ?></p><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="disable" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_disable" name="poll_options[templates][disable]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.disable' ) ) ); ?></textarea></td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<strong><?php esc_html_e( 'Poll Error', 'wp-polls' ); ?></strong><br /><?php esc_html_e( 'Displayed When An Error Has Occured While Processing The Poll', 'wp-polls' ); ?><br /><br />
				<?php esc_html_e( 'Allowed Variables:', 'wp-polls' ); ?><br />
				<p style="margin: 2px 0">- <?php esc_html_e( 'N/A', 'wp-polls' ); ?><br /><br />
				<input type="button" name="RestoreDefault" value="<?php esc_attr_e( 'Restore Default Template', 'wp-polls' ); ?>" data-poll-action="restore-template" data-poll-template="error" class="button" />
			</td>
			<td valign="top"><textarea cols="80" rows="15" id="poll_template_error" name="poll_options[templates][error]"><?php echo esc_textarea( removeslashes( Polls_Options::get( 'templates.error' ) ) ); ?></textarea></td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-polls' ); ?>" />
	</p>
</div>
</form>
