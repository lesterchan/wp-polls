<?php
/**
 * Manage Polls admin screen.
 *
 * Three views share this file: the list of polls, the edit form, and the logs
 * of one poll. The list is a WP_List_Table; the other two are ordinary
 * form-table forms that post back here.
 *
 * Required from WP_Polls_Admin::render_manage(), so the globals wp-admin used
 * to have in scope for a plugin page file are pulled in explicitly.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Check Whether User Can Manage Polls.
if ( ! current_user_can( WP_Polls_Admin::capability() ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage polls.', 'wp-polls' ), '', array( 'response' => 403 ) );
}

// Loaded here rather than from the plugin bootstrap: WP_List_Table only exists
// inside wp-admin, and this is the one screen that extends it.
require_once WP_POLLS_DIR . 'includes/class-wp-polls-list-table.php';

// Variables Variables Variables.
$poll_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Selects which poll to display; the write paths below verify their own nonces.
$poll_id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$poll_aid = isset( $_GET['aid'] ) ? (int) $_GET['aid'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Form Processing.
if ( ! empty( $_POST['do'] ) ) {
	// Decide What To Do.
	switch ( $_POST['do'] ) {
		// Edit Poll.
		case __( 'Edit Poll', 'wp-polls' ):
			check_admin_referer( 'wp-polls_edit-poll' );
			// Poll ID.
			$pollq_id = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
			// Poll Total Votes.
			$pollq_totalvotes = isset( $_POST['pollq_totalvotes'] ) ? (int) $_POST['pollq_totalvotes'] : 0;
			// Poll Total Voters.
			$pollq_totalvoters = isset( $_POST['pollq_totalvoters'] ) ? (int) $_POST['pollq_totalvoters'] : 0;
			// Poll Question.
			$pollq_question = isset( $_POST['pollq_question'] ) ? esc_sql( trim( wp_kses_post( wp_unslash( $_POST['pollq_question'] ) ) ) ) : '';
			// Poll Active.
			$pollq_active = isset( $_POST['pollq_active'] ) ? (int) $_POST['pollq_active'] : 0;
			// Poll Start Date.
			$pollq_timestamp    = isset( $_POST['poll_timestamp_old'] ) ? (int) $_POST['poll_timestamp_old'] : WP_Polls::now();
			$edit_polltimestamp = ( isset( $_POST['edit_polltimestamp'] ) && 1 === (int) $_POST['edit_polltimestamp'] ) ? 1 : 0;
			if ( 1 === $edit_polltimestamp ) {
				$pollq_timestamp_day    = isset( $_POST['pollq_timestamp_day'] ) ? (int) $_POST['pollq_timestamp_day'] : 0;
				$pollq_timestamp_month  = isset( $_POST['pollq_timestamp_month'] ) ? (int) $_POST['pollq_timestamp_month'] : 0;
				$pollq_timestamp_year   = isset( $_POST['pollq_timestamp_year'] ) ? (int) $_POST['pollq_timestamp_year'] : 0;
				$pollq_timestamp_hour   = isset( $_POST['pollq_timestamp_hour'] ) ? (int) $_POST['pollq_timestamp_hour'] : 0;
				$pollq_timestamp_minute = isset( $_POST['pollq_timestamp_minute'] ) ? (int) $_POST['pollq_timestamp_minute'] : 0;
				$pollq_timestamp_second = isset( $_POST['pollq_timestamp_second'] ) ? (int) $_POST['pollq_timestamp_second'] : 0;
				$pollq_timestamp        = gmmktime( $pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year );
				if ( $pollq_timestamp > WP_Polls::now() ) {
					$pollq_active = -1;
				}
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
				if ( 1 === $edit_polltimestamp ) {
					if ( $pollq_expiry < $pollq_timestamp ) {
						$pollq_active = 0;
					}
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
			// Update Poll's Question.
			$text               = '';
			$edit_poll_question = $wpdb->update(
				$wpdb->pollsq,
				array(
					'pollq_question'    => $pollq_question,
					'pollq_timestamp'   => $pollq_timestamp,
					'pollq_totalvotes'  => $pollq_totalvotes,
					'pollq_active'      => $pollq_active,
					'pollq_expiry'      => $pollq_expiry,
					'pollq_multiple'    => $pollq_multiple,
					'pollq_totalvoters' => $pollq_totalvoters,
				),
				array(
					'pollq_id' => $pollq_id,
				),
				array(
					'%s',
					'%s',
					'%d',
					'%d',
					'%s',
					'%d',
					'%d',
				),
				array(
					'%d',
				)
			);
			if ( ! $edit_poll_question ) {
				/* translators: %s: value. */
				$text = '<div class="notice notice-info inline"><p>' . sprintf( __( 'No Changes Had Been Made To Poll\'s Question \'%s\'.', 'wp-polls' ), removeslashes( $pollq_question ) ) . '</p></div>';
			}
			// Update Polls' Answers.
			$polla_aids     = array();
			$get_polla_aids = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC", $pollq_id ) );
			if ( $get_polla_aids ) {
				foreach ( $get_polla_aids as $get_polla_aid ) {
						$polla_aids[] = (int) $get_polla_aid->polla_aid;
				}
				foreach ( $polla_aids as $polla_aid ) {
					$polla_answers    = isset( $_POST[ 'polla_aid-' . $polla_aid ] ) ? trim( wp_kses_post( wp_unslash( $_POST[ 'polla_aid-' . $polla_aid ] ) ) ) : '';
					$polla_votes      = isset( $_POST[ 'polla_votes-' . $polla_aid ] ) ? (int) $_POST[ 'polla_votes-' . $polla_aid ] : 0;
					$edit_poll_answer = $wpdb->update(
						$wpdb->pollsa,
						array(
							'polla_answers' => $polla_answers,
							'polla_votes'   => $polla_votes,
						),
						array(
							'polla_qid' => $pollq_id,
							'polla_aid' => $polla_aid,
						),
						array(
							'%s',
							'%d',
						),
						array(
							'%d',
							'%d',
						)
					);
					if ( ! $edit_poll_answer ) {
						/* translators: %s: value. */
						$text .= '<div class="notice notice-info inline"><p>' . sprintf( __( 'No Changes Had Been Made To Poll\'s Answer \'%s\'.', 'wp-polls' ), $polla_answers ) . '</p></div>';
					} else {
						/* translators: %s: value. */
						$text .= '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll\'s Answer \'%s\' Edited Successfully.', 'wp-polls' ), $polla_answers ) . '</p></div>';
					}
				}
			} else {
				/* translators: %s: value. */
				$text .= '<div class="notice notice-error inline"><p>' . sprintf( __( 'Invalid Poll \'%s\'.', 'wp-polls' ), removeslashes( $pollq_question ) ) . '</p></div>';
			}
			// Add Poll Answers (If Needed).
			$polla_answers_new = isset( $_POST['polla_answers_new'] ) ? array_map( 'trim', array_map( 'wp_kses_post', wp_unslash( (array) $_POST['polla_answers_new'] ) ) ) : array();
			if ( ! empty( $polla_answers_new ) ) {
				$i                       = 0;
				$polla_answers_new_votes = isset( $_POST['polla_answers_new_votes'] ) ? array_map( 'intval', (array) $_POST['polla_answers_new_votes'] ) : array();
				foreach ( $polla_answers_new as $polla_answer_new ) {
					$polla_answer_new = wp_kses_post( trim( $polla_answer_new ) );
					if ( ! empty( $polla_answer_new ) ) {
						$polla_answer_new_vote = (int) sanitize_key( $polla_answers_new_votes[ $i ] );
						$add_poll_answers      = $wpdb->insert(
							$wpdb->pollsa,
							array(
								'polla_qid'     => $pollq_id,
								'polla_answers' => $polla_answer_new,
								'polla_votes'   => $polla_answer_new_vote,
							),
							array(
								'%d',
								'%s',
								'%d',
							)
						);
						if ( ! $add_poll_answers ) {
							/* translators: %s: value. */
							$text .= '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls' ), $polla_answer_new ) . '</p></div>';
						} else {
							/* translators: %s: value. */
							$text .= '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll\'s Answer \'%s\' Added Successfully.', 'wp-polls' ), $polla_answer_new ) . '</p></div>';
						}
					}
					++$i;
				}
			}
			if ( empty( $text ) ) {
				/* translators: %s: value. */
				$text = '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%s\' Edited Successfully.', 'wp-polls' ), removeslashes( $pollq_question ) ) . '</p></div>';
			}
			// Update Lastest Poll ID To Poll Options.
			$latest_pollid     = WP_Polls::polls_latest_id();
			$update_latestpoll = WP_Polls_Options::set( 'latest_poll', $latest_pollid );
			/**
			 * Fires after a poll's question, answers or dates have been edited.
			 *
			 * @since 2.70.0
			 *
			 * @param int $pollq_id Poll that was edited.
			 */
			do_action( 'wp_polls_update_poll', $pollq_id );
			WP_Polls::cron_polls_place();
			break;
	}
}

// Determines Which Mode It Is.
switch ( $poll_mode ) {
	// Poll Logging.
	case 'logs':
		require __DIR__ . '/screen-logs.php';
		break;
	// Edit A Poll.
	case 'edit':
		$poll_question      = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_question, pollq_timestamp, pollq_totalvotes, pollq_active, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_answers       = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid ASC", $poll_id ) );
		$poll_noquestion    = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(polla_aid) FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
		$poll_question_text = removeslashes( $poll_question->pollq_question );
		$poll_totalvotes    = (int) $poll_question->pollq_totalvotes;
		$poll_timestamp     = $poll_question->pollq_timestamp;
		$poll_active        = (int) $poll_question->pollq_active;
		$poll_expiry        = trim( $poll_question->pollq_expiry );
		$poll_multiple      = (int) $poll_question->pollq_multiple;
		$poll_totalvoters   = (int) $poll_question->pollq_totalvoters;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Edit Poll', 'wp-polls' ); ?></h1>
			<?php
			if ( ! empty( $text ) ) {
				echo wp_kses_post( '<div id="message" class="notice notice-success is-dismissible">' . removeslashes( $text ) . '</div>' );
			} else {
				echo '<div id="message" class="notice notice-success hidden"></div>';
			}
			?>
			<form method="post" action="<?php echo esc_url( WP_Polls_List_Table::page_url( array( 'mode' => 'edit' ), $poll_id ) ); ?>">
				<?php wp_nonce_field( 'wp-polls_edit-poll' ); ?>
				<input type="hidden" name="pollq_id" value="<?php echo esc_attr( $poll_id ); ?>" />
				<input type="hidden" name="pollq_active" value="<?php echo esc_attr( $poll_active ); ?>" />
				<input type="hidden" name="poll_timestamp_old" value="<?php echo esc_attr( $poll_timestamp ); ?>" />

				<h2><?php esc_html_e( 'Poll Question', 'wp-polls' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pollq_question"><?php esc_html_e( 'Question', 'wp-polls' ); ?></label></th>
						<td><input type="text" class="large-text" id="pollq_question" name="pollq_question" value="<?php echo esc_attr( $poll_question_text ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Poll Answers', 'wp-polls' ); ?></h2>
				<table class="widefat striped wp-polls-answers">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Answer No.', 'wp-polls' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Answer Text', 'wp-polls' ); ?></th>
							<th scope="col" class="wp-polls-answer-votes"><?php esc_html_e( 'No. Of Votes', 'wp-polls' ); ?></th>
						</tr>
					</thead>
					<tbody id="poll_answers">
						<?php
							$i                      = 1;
							$poll_actual_totalvotes = 0;
						if ( $poll_answers ) {
							$pollip_answers    = array();
							$pollip_answers[0] = __( 'Null Votes', 'wp-polls' );
							foreach ( $poll_answers as $poll_answer ) {
								$polla_aid                    = (int) $poll_answer->polla_aid;
								$polla_answers                = removeslashes( $poll_answer->polla_answers );
								$polla_votes                  = (int) $poll_answer->polla_votes;
								$pollip_answers[ $polla_aid ] = $polla_answers;
								/* translators: %s: The answer's position in the poll. */
								$answer_label = sprintf( __( 'Answer %s', 'wp-polls' ), number_format_i18n( $i ) );
								/* translators: %s: The answer text. */
								$delete_answer_confirm = sprintf( __( 'You are about to delete this poll\'s answer \'%s\'.', 'wp-polls' ), $polla_answers );
								?>
								<tr id="poll-answer-<?php echo esc_attr( $polla_aid ); ?>">
									<th scope="row"><label for="polla_aid-<?php echo esc_attr( $polla_aid ); ?>"><?php echo esc_html( $answer_label ); ?></label></th>
									<td class="wp-polls-answer-text">
										<input type="text" class="large-text" maxlength="200" id="polla_aid-<?php echo esc_attr( $polla_aid ); ?>" name="polla_aid-<?php echo esc_attr( $polla_aid ); ?>" value="<?php echo esc_attr( $polla_answers ); ?>" />
										<button type="button" class="button" data-poll-action="delete-answer" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-poll-aid="<?php echo esc_attr( $polla_aid ); ?>" data-poll-votes="<?php echo esc_attr( $polla_votes ); ?>" data-poll-confirm="<?php echo esc_attr( $delete_answer_confirm ); ?>" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-polls_delete-poll-answer' ) ); ?>"><?php esc_html_e( 'Delete', 'wp-polls' ); ?></button>
									</td>
									<td class="wp-polls-answer-votes">
										<input type="text" class="wp-polls-votes" id="polla_votes-<?php echo esc_attr( $polla_aid ); ?>" name="polla_votes-<?php echo esc_attr( $polla_aid ); ?>" value="<?php echo esc_attr( $polla_votes ); ?>" data-poll-action="total-votes" />
									</td>
								</tr>
								<?php
								$poll_actual_totalvotes += $polla_votes;
								++$i;
							}
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<td></td>
							<td><button type="button" class="button" data-poll-action="add-answer-edit"><?php esc_html_e( 'Add Answer', 'wp-polls' ); ?></button></td>
							<td class="wp-polls-answer-votes">
								<label for="pollq_totalvotes"><strong><?php esc_html_e( 'Total Votes:', 'wp-polls' ); ?></strong> <strong id="poll_total_votes"><?php echo esc_html( number_format_i18n( $poll_actual_totalvotes ) ); ?></strong></label>
								<input type="text" class="wp-polls-votes" readonly="readonly" id="pollq_totalvotes" name="pollq_totalvotes" value="<?php echo esc_attr( $poll_actual_totalvotes ); ?>" data-poll-action="total-votes" />
							</td>
						</tr>
						<tr>
							<td></td>
							<td></td>
							<td class="wp-polls-answer-votes">
								<label for="pollq_totalvoters"><strong><?php esc_html_e( 'Total Voters:', 'wp-polls' ); ?> <?php echo esc_html( number_format_i18n( $poll_totalvoters ) ); ?></strong></label>
								<input type="text" class="wp-polls-votes" id="pollq_totalvoters" name="pollq_totalvoters" value="<?php echo esc_attr( $poll_totalvoters ); ?>" />
							</td>
						</tr>
					</tfoot>
				</table>

				<h2><?php esc_html_e( 'Poll Multiple Answers', 'wp-polls' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pollq_multiple_yes"><?php esc_html_e( 'Allows Users To Select More Than One Answer?', 'wp-polls' ); ?></label></th>
						<td>
							<select name="pollq_multiple_yes" id="pollq_multiple_yes" data-poll-action="toggle-multiple">
								<option value="0"<?php selected( 0, $poll_multiple ); ?>><?php esc_html_e( 'No', 'wp-polls' ); ?></option>
								<option value="1"<?php selected( true, $poll_multiple > 0 ); ?>><?php esc_html_e( 'Yes', 'wp-polls' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pollq_multiple"><?php esc_html_e( 'Maximum Number Of Selected Answers Allowed?', 'wp-polls' ); ?></label></th>
						<td>
							<select name="pollq_multiple" id="pollq_multiple" <?php disabled( 0, $poll_multiple ); ?>>
								<?php
								for ( $i = 1; $i <= $poll_noquestion; $i++ ) {
									echo '<option value="' . esc_attr( $i ) . '"' . selected( $i, $poll_multiple, false ) . '>' . esc_html( number_format_i18n( $i ) ) . '</option>';
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
						<td>
							<?php echo esc_html( WP_Polls_List_Table::format_date( $poll_timestamp ) ); ?><br />
							<input type="checkbox" name="edit_polltimestamp" id="edit_polltimestamp" value="1" data-poll-action="toggle-timestamp" />&nbsp;<label for="edit_polltimestamp"><?php esc_html_e( 'Edit Start Date/Time', 'wp-polls' ); ?></label><br />
							<?php WP_Polls_Admin::poll_timestamp( $poll_timestamp, 'pollq_timestamp', true ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'End Date/Time', 'wp-polls' ); ?></th>
						<td>
							<?php
							if ( empty( $poll_expiry ) ) {
								esc_html_e( 'This Poll Will Not Expire', 'wp-polls' );
							} else {
								echo esc_html( WP_Polls_List_Table::format_date( $poll_expiry ) );
							}
							?>
							<br />
							<input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" data-poll-action="toggle-expiry" <?php checked( true, empty( $poll_expiry ) ); ?> />
							<label for="pollq_expiry_no"><?php esc_html_e( 'Do NOT Expire This Poll', 'wp-polls' ); ?></label><br />
							<?php
							if ( empty( $poll_expiry ) ) {
								WP_Polls_Admin::poll_timestamp( WP_Polls::now(), 'pollq_expiry', true );
							} else {
								WP_Polls_Admin::poll_timestamp( $poll_expiry, 'pollq_expiry' );
							}
							?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php
					submit_button( __( 'Edit Poll', 'wp-polls' ), 'primary', 'do', false );

					// Only one of the two is ever the action that makes sense, but
					// which one changes without a reload once the button is used.
					$poll_open_class  = 1 === $poll_active ? ' hidden' : '';
					$poll_close_class = 1 === $poll_active ? '' : ' hidden';
					/* translators: %s: The poll question. */
					$close_confirm = sprintf( __( 'You are about to CLOSE this poll \'%s\'.', 'wp-polls' ), $poll_question_text );
					/* translators: %s: The poll question. */
					$open_confirm = sprintf( __( 'You are about to OPEN this poll \'%s\'.', 'wp-polls' ), $poll_question_text );
					?>
					<button type="button" class="button<?php echo esc_attr( $poll_close_class ); ?>" id="close_poll" data-poll-action="close-poll" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-poll-confirm="<?php echo esc_attr( $close_confirm ); ?>" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-polls_close-poll' ) ); ?>" ><?php esc_html_e( 'Close Poll', 'wp-polls' ); ?></button>
					<button type="button" class="button<?php echo esc_attr( $poll_open_class ); ?>" id="open_poll" data-poll-action="open-poll" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-poll-confirm="<?php echo esc_attr( $open_confirm ); ?>" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-polls_open-poll' ) ); ?>" ><?php esc_html_e( 'Open Poll', 'wp-polls' ); ?></button>
					<a class="button" href="<?php echo esc_url( WP_Polls_List_Table::page_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-polls' ); ?></a>
				</p>
			</form>
		</div>
		<?php
		break;
	// Main Page.
	default:
		$poll_list = new WP_Polls_List_Table();
		$poll_list->prepare_items();
		$poll_stats = WP_Polls_List_Table::stats();
		$poll_ips   = (int) $wpdb->get_var( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip" );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Manage Polls', 'wp-polls' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-polls-add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Poll', 'wp-polls' ); ?></a>
			<hr class="wp-header-end" />

			<!-- Where the AJAX actions report what they did. -->
			<div id="message" class="notice notice-success hidden"></div>

			<?php $poll_list->display(); ?>

			<h2><?php esc_html_e( 'Polls Stats:', 'wp-polls' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total Polls:', 'wp-polls' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $poll_stats['polls'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total Polls\' Answers:', 'wp-polls' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $poll_stats['answers'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total Votes Cast:', 'wp-polls' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $poll_stats['votes'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total Voters:', 'wp-polls' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $poll_stats['voters'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Polls Logs', 'wp-polls' ); ?></h2>
			<div id="poll_logs">
				<?php if ( $poll_ips > 0 ) : ?>
					<p><strong><?php esc_html_e( 'Are You Sure You Want To Delete All Polls Logs?', 'wp-polls' ); ?></strong></p>
					<p>
						<input type="checkbox" name="delete_logs_yes" id="delete_logs_yes" value="yes" />&nbsp;<label for="delete_logs_yes"><?php esc_html_e( 'Yes', 'wp-polls' ); ?></label>
					</p>
					<p>
						<button type="button" class="button" data-poll-action="delete-all-logs" data-poll-confirm="<?php echo esc_attr__( 'You are about to delete all poll logs. This action is not reversible.', 'wp-polls' ); ?>" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-polls_delete-polls-logs' ) ); ?>"><?php esc_html_e( 'Delete All Logs', 'wp-polls' ); ?></button>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'No poll logs available.', 'wp-polls' ); ?></p>
				<?php endif; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Note: If your logging method is by IP and Cookie or by Cookie, users may still be unable to vote if they have voted before as the cookie is still stored in their computer.', 'wp-polls' ); ?></p>
		</div>
		<?php
}
