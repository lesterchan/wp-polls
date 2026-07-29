<?php
/**
 * One poll's vote log.
 *
 * Rendered by WP_Polls_Screen_Manage under mode=logs, so it has no menu entry
 * and no hook suffix of its own. Up to 3.0.0 it was a partial that read the
 * poll id and the status message out of the including file's scope; both are
 * arguments now.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logs screen.
 */
class WP_Polls_Screen_Logs {

	/**
	 * Render the log of one poll.
	 *
	 * @param int    $poll_id Poll whose log to show.
	 * @param string $text    Status message from the including screen, if any.
	 * @return void
	 */
	public static function render( $poll_id, $text = '' ) {
		global $wpdb;
		// Check Whether User Can Manage Polls.
		if ( ! current_user_can( WP_Polls_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage polls.', 'wp-polls' ), '', array( 'response' => 403 ) );
		}

		// Variables.
		$max_records                   = 2000;
		$poll_question_data            = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_multiple, pollq_question, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_question                 = wp_kses_post( removeslashes( $poll_question_data->pollq_question ) );
		$poll_totalvoters              = (int) $poll_question_data->pollq_totalvoters;
		$poll_multiple                 = (int) $poll_question_data->pollq_multiple;
		$poll_registered               = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_userid) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid > 0", $poll_id ) );
		$poll_comments                 = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user != %s AND pollip_userid = 0", $poll_id, __( 'Guest', 'wp-polls' ) ) );
		$poll_guest                    = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user = %s", $poll_id, __( 'Guest', 'wp-polls' ) ) );
		$poll_totalrecorded            = ( $poll_registered + $poll_comments + $poll_guest );
		list( $order_by, $sort_order ) = WP_Polls_Display::get_ans_sort();
		$poll_answers_data             = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_id ) );
		$poll_voters                   = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pollip_user FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user != %s ORDER BY pollip_user ASC", $poll_id, __( 'Guest', 'wp-polls' ) ) );
		$poll_logs_count               = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip WHERE pollip_qid = %d", $poll_id ) );

		// Every filter field, defaulted. The two multiple-answer filters used to be
		// initialised inside the branch that handles their own submission, so a poll
		// that allows multiple answers raised an undefined variable warning for each
		// of them the first time the screen was opened.
		$exclude_registered   = 0;
		$exclude_comment      = 0;
		$exclude_guest        = 0;
		$exclude_registered_2 = 0;
		$exclude_comment_2    = 0;
		$num_choices          = 0;
		$num_choices_sign     = '';
		$users_voted_for      = null;
		$what_user_voted      = null;

		// Answer id => answer text, for labelling the rows of the log below. Built
		// here rather than while printing the filter dropdown, which is not rendered
		// at all when the poll has no recorded votes or when wp_polls_log_show_log_filter
		// turns the filters off - and the log was then labelled with blanks. Index 0 is
		// the "did not pick anything" pseudo-answer a null vote is logged against.
		$pollip_answers = array( 0 => __( 'Null Votes', 'wp-polls' ) );
		foreach ( (array) $poll_answers_data as $poll_answer_data ) {
			$pollip_answers[ (int) $poll_answer_data->polla_aid ] = wp_strip_all_tags( removeslashes( $poll_answer_data->polla_answers ) );
		}

		// Which of the three filter forms was submitted, if any. Read before the nonce
		// check only to decide that; check_admin_referer() below still runs before
		// anything is queried.
// phpcs:disable WordPress.Security.NonceVerification.Missing
		$poll_filtered  = ! empty( $_POST['do'] );
		$poll_filter_id = isset( $_POST['filter'] ) ? (int) $_POST['filter'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Filters 2 and 3 list one voter's rows under their name; filter 1 and the
		// unfiltered view list the voters under each answer.
		$poll_filtered_by_voter = $poll_filtered && $poll_filter_id > 1;

		// Shown for a log row whose answer is no longer in the poll.
		$poll_answer_deleted = __( 'Deleted Answer', 'wp-polls' );

		// Process Filters.
		if ( $poll_filtered ) {
			check_admin_referer( 'wp-polls_logs' );
			$registered_sql       = '';
			$comment_sql          = '';
			$guest_sql            = '';
			$users_voted_for_sql  = '';
			$what_user_voted_sql  = '';
			$num_choices_sql      = '';
			$num_choices_sign_sql = '';
			$order_by             = '';
			switch ( $poll_filter_id ) {
				case 1:
					$users_voted_for     = isset( $_POST['users_voted_for'] ) ? (int) $_POST['users_voted_for'] : 0;
					$exclude_registered  = isset( $_POST['exclude_registered'] ) && 1 === (int) $_POST['exclude_registered'];
					$exclude_comment     = isset( $_POST['exclude_comment'] ) && 1 === (int) $_POST['exclude_comment'];
					$exclude_guest       = isset( $_POST['exclude_guest'] ) && 1 === (int) $_POST['exclude_guest'];
					$users_voted_for_sql = "AND pollip_aid = $users_voted_for";
					if ( $exclude_registered ) {
						$registered_sql = 'AND pollip_userid = 0';
					}
					if ( $exclude_comment ) {
						if ( ! $exclude_registered ) {
							$comment_sql = 'AND pollip_userid > 0';
						} else {
							$comment_sql = 'AND pollip_user = \'' . __( 'Guest', 'wp-polls' ) . '\'';
						}
					}
					if ( $exclude_guest ) {
						$guest_sql = 'AND pollip_user != \'' . __( 'Guest', 'wp-polls' ) . '\'';
					}
					$order_by = 'pollip_timestamp DESC';
					break;
				case 2:
					$exclude_registered_2 = isset( $_POST['exclude_registered_2'] ) ? (int) $_POST['exclude_registered_2'] : 0;
					$exclude_comment_2    = isset( $_POST['exclude_comment_2'] ) ? (int) $_POST['exclude_comment_2'] : 0;
					$num_choices          = isset( $_POST['num_choices'] ) ? (int) $_POST['num_choices'] : 0;
					$num_choices_sign     = isset( $_POST['num_choices_sign'] ) ? sanitize_key( wp_unslash( $_POST['num_choices_sign'] ) ) : '';
					switch ( $num_choices_sign ) {
						case 'more':
							$num_choices_sign_sql = '>';
							break;
						case 'more_exactly':
							$num_choices_sign_sql = '>=';
							break;
						case 'exactly':
							$num_choices_sign_sql = '=';
							break;
						case 'less_exactly':
							$num_choices_sign_sql = '<=';
							break;
						case 'less':
							$num_choices_sign_sql = '<';
							break;
					}
					if ( $exclude_registered_2 ) {
						$registered_sql = 'AND pollip_userid = 0';
					}
					if ( $exclude_comment_2 ) {
						if ( ! $exclude_registered_2 ) {
							$comment_sql = 'AND pollip_userid > 0';
						} else {
							$comment_sql = 'AND pollip_user = \'' . __( 'Guest', 'wp-polls' ) . '\'';
						}
					}
					$guest_sql         = 'AND pollip_user != \'' . __( 'Guest', 'wp-polls' ) . '\'';
					$num_choices_query = $wpdb->get_col( "SELECT pollip_user, COUNT(pollip_ip) AS num_choices FROM $wpdb->pollsip WHERE pollip_qid = $poll_id GROUP BY pollip_ip, pollip_user HAVING num_choices $num_choices_sign_sql $num_choices" );
					$num_choices_sql   = 'AND pollip_user IN (\'' . implode( '\',\'', array_map( 'esc_sql', $num_choices_query ) ) . '\')';
					$order_by          = 'pollip_user, pollip_ip';
					break;
				case 3:
					$what_user_voted     = isset( $_POST['what_user_voted'] ) ? esc_sql( sanitize_text_field( wp_unslash( $_POST['what_user_voted'] ) ) ) : '';
					$what_user_voted_sql = "AND pollip_user = '$what_user_voted'";
					$order_by            = 'pollip_user, pollip_ip';
					break;
			}
			$poll_ips = $wpdb->get_results( "SELECT $wpdb->pollsip.* FROM $wpdb->pollsip WHERE pollip_qid = $poll_id $users_voted_for_sql $registered_sql $comment_sql $guest_sql $what_user_voted_sql $num_choices_sql ORDER BY $order_by" );
		} else {
			$poll_ips = $wpdb->get_results( $wpdb->prepare( "SELECT pollip_aid, pollip_ip, pollip_host, pollip_timestamp, pollip_user FROM $wpdb->pollsip WHERE pollip_qid = %d ORDER BY pollip_aid ASC, pollip_user ASC LIMIT %d", $poll_id, $max_records ) );
		}
		$poll_logs_url = WP_Polls_List_Table::page_url( array( 'mode' => 'logs' ), $poll_id );
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Poll\'s Logs', 'wp-polls' ); ?></h1>
		<?php
		if ( ! empty( $text ) ) {
			echo wp_kses_post( '<div id="message" class="notice notice-success is-dismissible">' . removeslashes( $text ) . '</div>' );
		} else {
			echo '<div id="message" class="notice notice-success hidden"></div>';
		}
		?>
	<h2><?php echo wp_kses_post( $poll_question ); ?></h2>
	<p>
		<?php /* translators: %s: The number of recorded votes. */ ?>
		<?php echo wp_kses_post( sprintf( _n( 'There are a total of <strong>%s</strong> recorded vote for this poll.', 'There are a total of <strong>%s</strong> recorded votes for this poll.', $poll_totalrecorded, 'wp-polls' ), esc_html( number_format_i18n( $poll_totalrecorded ) ) ) ); ?><br />
		<?php /* translators: %s: The number of votes cast by registered users. */ ?>
		<?php echo wp_kses_post( sprintf( _n( '<strong>&raquo;</strong> <strong>%s</strong> vote is cast by registered users', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by registered users', $poll_registered, 'wp-polls' ), esc_html( number_format_i18n( $poll_registered ) ) ) ); ?><br />
		<?php /* translators: %s: The number of votes cast by comment authors. */ ?>
		<?php echo wp_kses_post( sprintf( _n( '<strong>&raquo;</strong> <strong>%s</strong> vote is cast by comment authors', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by comment authors', $poll_comments, 'wp-polls' ), esc_html( number_format_i18n( $poll_comments ) ) ) ); ?><br />
		<?php /* translators: %s: The number of votes cast by guests. */ ?>
		<?php echo wp_kses_post( sprintf( _n( '<strong>&raquo;</strong> <strong>%s</strong> vote is cast by guests', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by guests', $poll_guest, 'wp-polls' ), esc_html( number_format_i18n( $poll_guest ) ) ) ); ?>
	</p>

		<?php
		/**
		 * Filters whether the log filter forms are shown.
		 *
		 * @since 2.75.0
		 *
		 * @param bool $show Whether to render the filters.
		 */
		$poll_show_log_filter = apply_filters( 'wp_polls_log_show_log_filter', true );
		?>
		<?php if ( $poll_totalrecorded > 0 && $poll_show_log_filter ) : ?>
		<h2><?php esc_html_e( 'Filter Poll\'s Logs', 'wp-polls' ); ?></h2>

		<form method="post" action="<?php echo esc_url( $poll_logs_url ); ?>">
			<?php wp_nonce_field( 'wp-polls_logs' ); ?>
			<input type="hidden" name="filter" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="users_voted_for"><?php esc_html_e( 'Display All Users That Voted For', 'wp-polls' ); ?></label></th>
					<td>
						<select name="users_voted_for" id="users_voted_for">
							<?php
							foreach ( (array) $poll_answers_data as $data ) {
								$polla_id = (int) $data->polla_aid;
								echo '<option value="' . esc_attr( $polla_id ) . '"' . selected( $polla_id, (int) $users_voted_for, false ) . '>' . esc_html( $pollip_answers[ $polla_id ] ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Voters To EXCLUDE', 'wp-polls' ); ?></th>
					<td>
						<input type="checkbox" id="exclude_registered_1" name="exclude_registered" value="1" <?php checked( '1', $exclude_registered ); ?> />&nbsp;<label for="exclude_registered_1"><?php esc_html_e( 'Registered Users', 'wp-polls' ); ?></label><br />
						<input type="checkbox" id="exclude_comment_1" name="exclude_comment" value="1" <?php checked( '1', $exclude_comment ); ?> />&nbsp;<label for="exclude_comment_1"><?php esc_html_e( 'Comment Authors', 'wp-polls' ); ?></label><br />
						<input type="checkbox" id="exclude_guest_1" name="exclude_guest" value="1" <?php checked( '1', $exclude_guest ); ?> />&nbsp;<label for="exclude_guest_1"><?php esc_html_e( 'Guests', 'wp-polls' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Filter', 'wp-polls' ), 'secondary', 'do', false ); ?>
		</form>

			<?php if ( $poll_multiple > 0 ) : ?>
			<form method="post" action="<?php echo esc_url( $poll_logs_url ); ?>">
				<?php wp_nonce_field( 'wp-polls_logs' ); ?>
				<input type="hidden" name="filter" value="2" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="num_choices_sign"><?php esc_html_e( 'Display Users That Voted For', 'wp-polls' ); ?></label></th>
						<td>
							<select name="num_choices_sign" id="num_choices_sign">
								<option value="more" <?php selected( 'more', $num_choices_sign ); ?>><?php esc_html_e( 'More Than', 'wp-polls' ); ?></option>
								<option value="more_exactly" <?php selected( 'more_exactly', $num_choices_sign ); ?>><?php esc_html_e( 'More Than Or Exactly', 'wp-polls' ); ?></option>
								<option value="exactly" <?php selected( 'exactly', $num_choices_sign ); ?>><?php esc_html_e( 'Exactly', 'wp-polls' ); ?></option>
								<option value="less_exactly" <?php selected( 'less_exactly', $num_choices_sign ); ?>><?php esc_html_e( 'Less Than Or Exactly', 'wp-polls' ); ?></option>
								<option value="less" <?php selected( 'less', $num_choices_sign ); ?>><?php esc_html_e( 'Less Than', 'wp-polls' ); ?></option>
							</select>
							<select name="num_choices" id="num_choices">
								<?php
								for ( $i = 1; $i <= $poll_multiple; $i++ ) {
									/* translators: %s: A number of answers. */
									$label = sprintf( _n( '%s Answer', '%s Answers', $i, 'wp-polls' ), number_format_i18n( $i ) );
									echo '<option value="' . esc_attr( $i ) . '"' . selected( $i, (int) $num_choices, false ) . '>' . esc_html( $label ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Voters To EXCLUDE', 'wp-polls' ); ?></th>
						<td>
							<input type="checkbox" id="exclude_registered_2" name="exclude_registered_2" value="1" <?php checked( '1', $exclude_registered_2 ); ?> />&nbsp;<label for="exclude_registered_2"><?php esc_html_e( 'Registered Users', 'wp-polls' ); ?></label><br />
							<input type="checkbox" id="exclude_comment_2" name="exclude_comment_2" value="1" <?php checked( '1', $exclude_comment_2 ); ?> />&nbsp;<label for="exclude_comment_2"><?php esc_html_e( 'Comment Authors', 'wp-polls' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Guests will automatically be excluded', 'wp-polls' ); ?></span>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Filter', 'wp-polls' ), 'secondary', 'do', false ); ?>
			</form>
		<?php endif; ?>

			<?php if ( $poll_voters ) : ?>
			<form method="post" action="<?php echo esc_url( $poll_logs_url ); ?>">
				<?php wp_nonce_field( 'wp-polls_logs' ); ?>
				<input type="hidden" name="filter" value="3" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="what_user_voted"><?php esc_html_e( 'Display What This User Has Voted', 'wp-polls' ); ?></label></th>
						<td>
							<select name="what_user_voted" id="what_user_voted">
								<?php
								foreach ( $poll_voters as $pollip_user ) {
									$voter = removeslashes( $pollip_user );
									echo '<option value="' . esc_attr( $voter ) . '"' . selected( (string) $pollip_user, (string) $what_user_voted, false ) . '>' . esc_html( $voter ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Filter', 'wp-polls' ), 'secondary', 'do', false ); ?>
			</form>
		<?php endif; ?>

		<p><a class="button" href="<?php echo esc_url( $poll_logs_url ); ?>"><?php esc_html_e( 'Clear Filter', 'wp-polls' ); ?></a></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Poll Logs', 'wp-polls' ); ?></h2>
	<div id="poll_logs_display">
		<?php
		if ( $poll_ips ) {
			if ( ! $poll_filtered ) {
				/* translators: %s: The number of records the unfiltered view stops at. */
				echo wp_kses_post( '<p>' . sprintf( __( 'This default filter is limited to display only <strong>%s</strong> records.', 'wp-polls' ), esc_html( number_format_i18n( $max_records ) ) ) . '</p>' );
			}
			echo '<table class="widefat">' . "\n";
			echo '<tbody>' . "\n";
			echo wp_kses_post( '<tr class="highlight"><td colspan="4">' . $poll_question . '</td></tr>' );
			$k                = 1;
			$j                = 0;
			$i                = 0;
			$poll_last_aid    = -1;
			$temp_pollip_user = null;
			if ( $poll_filtered_by_voter ) {
				echo "<tr class=\"thead\">\n";
				echo '<th scope="col">' . esc_html__( 'Answer', 'wp-polls' ) . "</th>\n";
				echo '<th scope="col">' . esc_html__( 'Hashed IP / Host', 'wp-polls' ) . "</th>\n";
				echo '<th scope="col">' . esc_html__( 'Date', 'wp-polls' ) . "</th>\n";
				echo "</tr>\n";
				foreach ( $poll_ips as $poll_ip ) {
					$pollip_aid  = (int) $poll_ip->pollip_aid;
					$pollip_user = removeslashes( $poll_ip->pollip_user );
					$pollip_ip   = $poll_ip->pollip_ip;
					$pollip_host = $poll_ip->pollip_host;
					$pollip_date = WP_Polls_List_Table::format_date( $poll_ip->pollip_timestamp );

					if ( $pollip_user !== $temp_pollip_user ) {
						echo '<tr class="highlight">';
						echo '<td colspan="3"><strong>' . esc_html__( 'User', 'wp-polls' ) . ' ' . esc_html( number_format_i18n( $k ) ) . ': ' . esc_html( $pollip_user ) . '</strong></td>';
						echo '</tr>';
						++$k;
						// A new voter restarts the striping, so each block reads as
						// its own list. This used to reset on every row instead, so
						// nothing ever alternated.
						$i = 0;
					}
					echo '<tr class="' . esc_attr( 0 === $i % 2 ? '' : 'alternate' ) . '">' . "\n";
					echo '<td>' . esc_html( isset( $pollip_answers[ $pollip_aid ] ) ? $pollip_answers[ $pollip_aid ] : $poll_answer_deleted ) . '</td>';
					echo '<td>' . esc_html( $pollip_ip ) . ' / ' . esc_html( $pollip_host ) . '</td>';
					echo '<td>' . esc_html( $pollip_date ) . '</td>';
					echo "</tr>\n";
					$temp_pollip_user = $pollip_user;
					++$i;
					++$j;
				}
			} else {
				foreach ( $poll_ips as $poll_ip ) {
					$pollip_aid = (int) $poll_ip->pollip_aid;
					/**
					 * Filters the voter name printed in the poll log.
					 *
					 * Returning a fixed string here makes the log a secret ballot.
					 *
					 * @since 2.75.0
					 *
					 * @param string $pollip_user Voter name as recorded.
					 */
					$pollip_user = apply_filters( 'wp_polls_log_secret_ballot', removeslashes( $poll_ip->pollip_user ) );
					$pollip_ip   = $poll_ip->pollip_ip;
					$pollip_host = $poll_ip->pollip_host;
					$pollip_date = WP_Polls_List_Table::format_date( $poll_ip->pollip_timestamp );
					if ( $pollip_aid !== $poll_last_aid ) {
						if ( 0 === $pollip_aid ) {
							echo '<tr class="highlight"><td colspan="4"><strong>' . esc_html( $pollip_answers[0] ) . '</strong></td></tr>';
						} else {
							echo '<tr class="highlight"><td colspan="4"><strong>' . esc_html__( 'Answer', 'wp-polls' ) . ' ' . esc_html( number_format_i18n( $k ) ) . ': ' . esc_html( isset( $pollip_answers[ $pollip_aid ] ) ? $pollip_answers[ $pollip_aid ] : $poll_answer_deleted ) . '</strong></td></tr>';
							++$k;
						}
						echo "<tr class=\"thead\">\n";
						echo '<th scope="col">' . esc_html__( 'No.', 'wp-polls' ) . "</th>\n";
						echo '<th scope="col">' . esc_html__( 'User', 'wp-polls' ) . "</th>\n";
						echo '<th scope="col">' . esc_html__( 'Hashed IP / Host', 'wp-polls' ) . "</th>\n";
						echo '<th scope="col">' . esc_html__( 'Date', 'wp-polls' ) . "</th>\n";
						echo "</tr>\n";
						$i = 1;
					}
					echo '<tr class="' . esc_attr( 0 === $i % 2 ? '' : 'alternate' ) . '">' . "\n";
					echo '<td>' . esc_html( number_format_i18n( $i ) ) . '</td>';
					echo '<td>' . esc_html( $pollip_user ) . '</td>';
					echo '<td>' . esc_html( $pollip_ip ) . ' / ' . esc_html( $pollip_host ) . '</td>';
					echo '<td>' . esc_html( $pollip_date ) . '</td>';
					echo "</tr>\n";
					$poll_last_aid = $pollip_aid;
					++$i;
					++$j;
				}
			}
			echo "<tr class=\"highlight\">\n";
			/* translators: %s: The number of matching log records. */
			echo wp_kses_post( '<td colspan="4">' . sprintf( __( 'Total number of records that matches this filter: <strong>%s</strong>', 'wp-polls' ), esc_html( number_format_i18n( $j ) ) ) . '</td>' );
			echo "</tr>\n";
			echo '</tbody>' . "\n";
			echo '</table>' . "\n";
		}
		?>
	</div>
		<?php
		// The panel the delete script unhides once it has emptied the log.
		if ( $poll_filtered ) {
			$poll_logs_empty      = ! $poll_ips;
			$poll_logs_empty_text = __( 'No poll logs matches the filter.', 'wp-polls' );
		} else {
			$poll_logs_empty      = ! $poll_logs_count;
			$poll_logs_empty_text = __( 'No poll logs available for this poll.', 'wp-polls' );
		}
		?>
	<p id="poll_logs_display_none" class="<?php echo $poll_logs_empty ? '' : 'hidden'; ?>"><?php echo esc_html( $poll_logs_empty_text ); ?></p>

	<h2><?php esc_html_e( 'Delete Poll Logs', 'wp-polls' ); ?></h2>
	<div id="poll_logs">
		<?php if ( $poll_logs_count ) : ?>
			<?php /* translators: %s: The poll question. */ ?>
			<?php $delete_logs_confirm = sprintf( __( 'You are about to delete poll logs for this poll \'%s\' ONLY. This action is not reversible.', 'wp-polls' ), wp_strip_all_tags( $poll_question ) ); ?>
			<p><strong><?php esc_html_e( 'Are You Sure You Want To Delete Logs For This Poll Only?', 'wp-polls' ); ?></strong></p>
			<p>
				<input type="checkbox" id="delete_logs_yes" name="delete_logs_yes" value="yes" />&nbsp;<label for="delete_logs_yes"><?php esc_html_e( 'Yes', 'wp-polls' ); ?></label>
			</p>
			<p>
				<button type="button" class="button" data-poll-action="delete-poll-logs" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-poll-confirm="<?php echo esc_attr( $delete_logs_confirm ); ?>" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-polls_delete-poll-logs' ) ); ?>"><?php esc_html_e( 'Delete Logs For This Poll Only', 'wp-polls' ); ?></button>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'No poll logs available for this poll.', 'wp-polls' ); ?></p>
		<?php endif; ?>
	</div>
	<p class="description"><?php esc_html_e( 'Note: If your logging method is by IP and Cookie or by Cookie, users may still be unable to vote if they have voted before as the cookie is still stored in their computer.', 'wp-polls' ); ?></p>
</div>
		<?php
	}
}
