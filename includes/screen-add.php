<?php
/**
 * Add Poll admin screen.
 *
 * Required from WP_Polls_Admin::render_add(), so the globals wp-admin used to
 * have in scope for a plugin page file are pulled in explicitly.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Check Whether User Can Manage Polls.
if ( ! current_user_can( WP_Polls_Admin::capability() ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage polls.', 'wp-polls' ), '', array( 'response' => 403 ) );
}

// Poll Manager.
$base_page = admin_url( 'admin.php?page=wp-polls' );

// Form Processing.
if ( ! empty( $_POST['do'] ) ) {
	// Decide What To Do.
	switch ( $_POST['do'] ) {
		// Add Poll.
		case __( 'Add Poll', 'wp-polls' ):
			check_admin_referer( 'wp-polls_add-poll' );
			$text = '';
			// Poll Question.
			$pollq_question = isset( $_POST['pollq_question'] ) ? trim( wp_kses_post( wp_unslash( $_POST['pollq_question'] ) ) ) : '';
			if ( ! empty( $pollq_question ) ) {
				// Poll Start Date.
				$timestamp_sql          = '';
				$pollq_timestamp_day    = isset( $_POST['pollq_timestamp_day'] ) ? (int) $_POST['pollq_timestamp_day'] : 0;
				$pollq_timestamp_month  = isset( $_POST['pollq_timestamp_month'] ) ? (int) $_POST['pollq_timestamp_month'] : 0;
				$pollq_timestamp_year   = isset( $_POST['pollq_timestamp_year'] ) ? (int) $_POST['pollq_timestamp_year'] : 0;
				$pollq_timestamp_hour   = isset( $_POST['pollq_timestamp_hour'] ) ? (int) $_POST['pollq_timestamp_hour'] : 0;
				$pollq_timestamp_minute = isset( $_POST['pollq_timestamp_minute'] ) ? (int) $_POST['pollq_timestamp_minute'] : 0;
				$pollq_timestamp_second = isset( $_POST['pollq_timestamp_second'] ) ? (int) $_POST['pollq_timestamp_second'] : 0;
				$pollq_timestamp        = gmmktime( $pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year );
				if ( $pollq_timestamp > WP_Polls::now() ) {
					$pollq_active = -1;
				} else {
					$pollq_active = 1;
				}
				// Poll End Date.
				$pollq_expiry_no = isset( $_POST['pollq_expiry_no'] ) ? (int) $_POST['pollq_expiry_no'] : 0;
				if ( 1 === $pollq_expiry_no ) {
					$pollq_expiry = 0;
				} else {
					$pollq_expiry_day    = isset( $_POST['pollq_expiry_day'] ) ? (int) $_POST['pollq_expiry_day'] : 0;
					$pollq_expiry_month  = isset( $_POST['pollq_expiry_month'] ) ? (int) $_POST['pollq_expiry_month'] : 0;
					$pollq_expiry_year   = isset( $_POST['pollq_expiry_year'] ) ? (int) $_POST['pollq_expiry_year'] : 0;
					$pollq_expiry_hour   = isset( $_POST['pollq_expiry_hour'] ) ? (int) $_POST['pollq_expiry_hour'] : 0;
					$pollq_expiry_minute = isset( $_POST['pollq_expiry_minute'] ) ? (int) $_POST['pollq_expiry_minute'] : 0;
					$pollq_expiry_second = isset( $_POST['pollq_expiry_second'] ) ? (int) $_POST['pollq_expiry_second'] : 0;
					$pollq_expiry        = gmmktime( $pollq_expiry_hour, $pollq_expiry_minute, $pollq_expiry_second, $pollq_expiry_month, $pollq_expiry_day, $pollq_expiry_year );
					if ( $pollq_expiry <= WP_Polls::now() ) {
						$pollq_active = 0;
					}
				}
				// Mutilple Poll.
				$pollq_multiple_yes = isset( $_POST['pollq_multiple_yes'] ) ? (int) $_POST['pollq_multiple_yes'] : 0;
				$pollq_multiple     = 0;
				if ( 1 === $pollq_multiple_yes ) {
					$pollq_multiple = isset( $_POST['pollq_multiple'] ) ? (int) $_POST['pollq_multiple'] : 0;
				} else {
					$pollq_multiple = 0;
				}
				// Insert Poll.
				$add_poll_question = $wpdb->insert(
					$wpdb->pollsq,
					array(
						'pollq_question'    => $pollq_question,
						'pollq_timestamp'   => $pollq_timestamp,
						'pollq_totalvotes'  => 0,
						'pollq_active'      => $pollq_active,
						'pollq_expiry'      => $pollq_expiry,
						'pollq_multiple'    => $pollq_multiple,
						'pollq_totalvoters' => 0,
					),
					array(
						'%s',
						'%s',
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
					)
				);
				if ( ! $add_poll_question ) {
					/* translators: %s: value. */
					$text .= '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error In Adding Poll \'%s\'.', 'wp-polls' ), $pollq_question ) . '</p></div>';
				}
				// Add Poll Answers.
				$polla_answers = isset( $_POST['polla_answers'] ) ? array_map( 'trim', array_map( 'wp_kses_post', wp_unslash( (array) $_POST['polla_answers'] ) ) ) : array();
				$polla_qid     = (int) $wpdb->insert_id;
				foreach ( $polla_answers as $polla_answer ) {
					$polla_answer = wp_kses_post( trim( $polla_answer ) );
					if ( ! empty( $polla_answer ) ) {
						$add_poll_answers = $wpdb->insert(
							$wpdb->pollsa,
							array(
								'polla_qid'     => $polla_qid,
								'polla_answers' => $polla_answer,
								'polla_votes'   => 0,
							),
							array(
								'%d',
								'%s',
								'%d',
							)
						);
						if ( ! $add_poll_answers ) {
							/* translators: %s: value. */
							$text .= '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls' ), $polla_answer ) . '</p></div>';
						}
					} else {
						$text .= '<div class="notice notice-error inline"><p>' . __( 'Poll\'s Answer is empty.', 'wp-polls' ) . '</p></div>';
					}
				}
				// Update Lastest Poll ID To Poll Options.
				$latest_pollid     = WP_Polls::polls_latest_id();
				$update_latestpoll = WP_Polls_Options::set( 'latest_poll', $latest_pollid );
				// If poll starts in the future use the correct poll ID.
				$latest_pollid = ( $latest_pollid < $polla_qid ) ? $polla_qid : $latest_pollid;
				if ( empty( $text ) ) {
					/* translators: 1: value, 2: value, 3: value, 4: value. */
					$text = '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%1$s\' (ID: %2$s) added successfully. Embed this poll with the shortcode: %3$s or go back to <a href="%4$s">Manage Polls</a>', 'wp-polls' ), $pollq_question, $latest_pollid, '<input type="text" value=\'[poll id="' . $latest_pollid . '"]\' readonly="readonly" size="10" />', $base_page ) . '</p></div>';
				} elseif ( $add_poll_question ) {
						/* translators: 1: value, 2: value, 3: value, 4: value. */
						$text .= '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%1$s\' (ID: %2$s) added successfully, but there are some errors with the Poll\'s Answers. Embed this poll with the shortcode: %3$s or go back to <a href="%4$s">Manage Polls</a>', 'wp-polls' ), $pollq_question, $latest_pollid, '<input type="text" value=\'[poll id="' . $latest_pollid . '"]\' readonly="readonly" size="10" />', $base_page ) . '</p></div>';
				}
					/**
				 * Fires after a poll has been created.
				 *
				 * @since 2.70.0
				 *
				 * @param int $latest_pollid Poll that was created.
				 */
				do_action( 'wp_polls_add_poll', $latest_pollid );
				WP_Polls::cron_polls_place();
			} else {
				$text .= '<div class="notice notice-error inline"><p>' . __( 'Poll Question is empty.', 'wp-polls' ) . '</p></div>';
			}
			break;
	}
}

// Add Poll Form.
$poll_noquestion = 2;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Add Poll', 'wp-polls' ); ?></h1>
	<?php
	if ( ! empty( $text ) ) {
		echo wp_kses_post( '<div id="message" class="notice notice-success is-dismissible">' . removeslashes( $text ) . '</div>' );
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wp-polls-add' ) ); ?>">
		<?php wp_nonce_field( 'wp-polls_add-poll' ); ?>

		<h2><?php esc_html_e( 'Poll Question', 'wp-polls' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pollq_question"><?php esc_html_e( 'Question', 'wp-polls' ); ?></label></th>
				<td><input type="text" class="large-text" id="pollq_question" name="pollq_question" value="" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Poll Answers', 'wp-polls' ); ?></h2>
		<table class="widefat striped wp-polls-answers">
			<tbody id="poll_answers">
			<?php
			for ( $i = 1; $i <= $poll_noquestion; $i++ ) {
				/* translators: %s: The answer's position in the poll. */
				$answer_label = sprintf( __( 'Answer %s', 'wp-polls' ), number_format_i18n( $i ) );
				?>
				<tr id="poll-answer-<?php echo esc_attr( $i ); ?>">
					<th scope="row"><?php echo esc_html( $answer_label ); ?></th>
					<td class="wp-polls-answer-text">
						<input type="text" class="large-text" maxlength="200" name="polla_answers[]" />
						<button type="button" class="button" data-poll-action="remove-answer" data-poll-answer="<?php echo esc_attr( $i ); ?>"><?php esc_html_e( 'Remove', 'wp-polls' ); ?></button>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<td></td>
					<td><button type="button" class="button" data-poll-action="add-answer"><?php esc_html_e( 'Add Answer', 'wp-polls' ); ?></button></td>
				</tr>
			</tfoot>
		</table>

		<h2><?php esc_html_e( 'Poll Multiple Answers', 'wp-polls' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pollq_multiple_yes"><?php esc_html_e( 'Allows Users To Select More Than One Answer?', 'wp-polls' ); ?></label></th>
				<td>
					<select name="pollq_multiple_yes" id="pollq_multiple_yes" data-poll-action="toggle-multiple">
						<option value="0"><?php esc_html_e( 'No', 'wp-polls' ); ?></option>
						<option value="1"><?php esc_html_e( 'Yes', 'wp-polls' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pollq_multiple"><?php esc_html_e( 'Maximum Number Of Selected Answers Allowed?', 'wp-polls' ); ?></label></th>
				<td>
					<select name="pollq_multiple" id="pollq_multiple" disabled="disabled">
						<?php
						for ( $i = 1; $i <= $poll_noquestion; $i++ ) {
							echo '<option value="' . esc_attr( $i ) . '">' . esc_html( number_format_i18n( $i ) ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Poll Start/End Date', 'wp-polls' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Start Date/Time', 'wp-polls' ); ?></th>
				<td><?php WP_Polls_Admin::poll_timestamp( WP_Polls::now() ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'End Date/Time', 'wp-polls' ); ?></th>
				<td>
					<input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" checked="checked" data-poll-action="toggle-expiry" />&nbsp;<label for="pollq_expiry_no"><?php esc_html_e( 'Do NOT Expire This Poll', 'wp-polls' ); ?></label>
					<?php WP_Polls_Admin::poll_timestamp( WP_Polls::now(), 'pollq_expiry', true ); ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<?php submit_button( __( 'Add Poll', 'wp-polls' ), 'primary', 'do', false ); ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-polls' ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-polls' ); ?></a>
		</p>
	</form>
</div>
