<?php
/**
 * Admin View: Polls List
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get polls data.
$polls = WP_Polls_Data::get_polls( array(
	'orderby' => 'pollq_timestamp',
	'order'   => 'desc',
) );

// Get statistics.
$total_polls = count( $polls );
$total_votes = WP_Polls_Data::get_polls_count();
$total_answers = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->pollsa" );
$total_votes_sum = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq" );
$total_voters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );

// Get current and latest poll IDs.
$current_poll = (int) get_option( 'poll_currentpoll' );
$latest_poll = (int) get_option( 'poll_latestpoll' );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Manage Polls', 'wp-polls' ); ?></h1>
	
	<?php WP_Polls_Manager::display_messages(); ?>
	
	<div id="message" class="updated" style="display: none;"></div>
	
	<h2><?php esc_html_e( 'Polls', 'wp-polls' ); ?></h2>
	
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'wp-polls' ); ?></th>
				<th><?php esc_html_e( 'Question', 'wp-polls' ); ?></th>
				<th><?php esc_html_e( 'Total Voters', 'wp-polls' ); ?></th>
				<th><?php esc_html_e( 'Start Date/Time', 'wp-polls' ); ?></th>
				<th><?php esc_html_e( 'End Date/Time', 'wp-polls' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-polls' ); ?></th>
				<th colspan="3"><?php esc_html_e( 'Action', 'wp-polls' ); ?></th>
			</tr>
		</thead>
		<tbody id="manage_polls">
			<?php
			if ( $polls ) {
				foreach ( $polls as $poll ) {
					$poll_id = (int) $poll->pollq_id;
					$poll_question = WP_Polls_Utility::remove_slashes( $poll->pollq_question );
					$poll_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll->pollq_timestamp ) );
					$poll_totalvotes = (int) $poll->pollq_totalvotes;
					$poll_totalvoters = (int) $poll->pollq_totalvoters;
					$poll_active = (int) $poll->pollq_active;
					$poll_expiry = trim( $poll->pollq_expiry );
					
					if ( empty( $poll_expiry ) ) {
						$poll_expiry_text = __( 'No Expiry', 'wp-polls' );
					} else {
						$poll_expiry_text = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
					}
					
					$row_class = '';
					
					if ( $current_poll > 0 ) {
						if ( $current_poll === $poll_id ) {
							$row_class = 'class="highlight"';
						}
					} elseif ( 0 === $current_poll ) {
						if ( $poll_id === $latest_poll ) {
							$row_class = 'class="highlight"';
						}
					}
					
					echo '<tr id="poll-' . esc_attr( $poll_id ) . '" ' . $row_class . '>';
					echo '<td><strong>' . esc_html( number_format_i18n( $poll_id ) ) . '</strong></td>';
					echo '<td>';
					
					if ( $current_poll > 0 ) {
						if ( $current_poll === $poll_id ) {
							echo '<strong>' . esc_html__( 'Displayed:', 'wp-polls' ) . '</strong> ';
						}
					} elseif ( 0 === $current_poll ) {
						if ( $poll_id === $latest_poll ) {
							echo '<strong>' . esc_html__( 'Displayed:', 'wp-polls' ) . '</strong> ';
						}
					}
					
					echo esc_html( $poll_question ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( $poll_totalvoters ) ) . '</td>';
					echo '<td>' . esc_html( $poll_date ) . '</td>';
					echo '<td>' . esc_html( $poll_expiry_text ) . '</td>';
					echo '<td>';
					
					if ( 1 === $poll_active ) {
						esc_html_e( 'Open', 'wp-polls' );
					} elseif ( -1 === $poll_active ) {
						esc_html_e( 'Future', 'wp-polls' );
					} else {
						esc_html_e( 'Closed', 'wp-polls' );
					}
					
					echo '</td>';
					echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=polls-manager&mode=logs&id=' . $poll_id ) ) . '" class="button button-secondary">' . esc_html__( 'Logs', 'wp-polls' ) . '</a></td>';
					echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=polls-manager&mode=edit&id=' . $poll_id ) ) . '" class="button button-secondary">' . esc_html__( 'Edit', 'wp-polls' ) . '</a></td>';
					echo '<td><a href="#DeletePoll" onclick="delete_poll(' . esc_js( $poll_id ) . ', \'' . esc_js( sprintf( __( 'You are about to delete this poll, \'%s\'.', 'wp-polls' ), $poll_question ) ) . '\', \'' . esc_js( wp_create_nonce( 'wp-polls_delete-poll' ) ) . '\');" class="button button-secondary delete">' . esc_html__( 'Delete', 'wp-polls' ) . '</a></td>';
					echo '</tr>';
				}
			} else {
				echo '<tr><td colspan="9" align="center"><strong>' . esc_html__( 'No Polls Found', 'wp-polls' ) . '</strong></td></tr>';
			}
			?>
		</tbody>
	</table>
	
	<p class="submit">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=polls-manager&mode=add' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Poll', 'wp-polls' ); ?></a>
	</p>
	
	<h3><?php esc_html_e( 'Polls Stats:', 'wp-polls' ); ?></h3>
	<table class="widefat">
		<tr>
			<th><?php esc_html_e( 'Total Polls:', 'wp-polls' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $total_polls ) ); ?></td>
		</tr>
		<tr class="alternate">
			<th><?php esc_html_e( 'Total Polls\' Answers:', 'wp-polls' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $total_answers ) ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Total Votes Cast:', 'wp-polls' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $total_votes_sum ) ); ?></td>
		</tr>
		<tr class="alternate">
			<th><?php esc_html_e( 'Total Voters:', 'wp-polls' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $total_voters ) ); ?></td>
		</tr>
	</table>
	
	<h3><?php esc_html_e( 'Polls Logs', 'wp-polls' ); ?></h3>
	<div align="center" id="poll_logs">
		<?php
		$poll_ips = (int) $wpdb->get_var( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip" );
		if ( $poll_ips > 0 ) {
			?>
			<p><strong><?php esc_html_e( 'Are You Sure You Want To Delete All Polls Logs?', 'wp-polls' ); ?></strong></p>
			<p>
				<label>
					<input type="checkbox" name="delete_logs_yes" id="delete_logs_yes" value="yes" />
					<?php esc_html_e( 'Yes', 'wp-polls' ); ?>
				</label>
			</p>
			<p>
				<input type="button" value="<?php esc_attr_e( 'Delete All Logs', 'wp-polls' ); ?>" class="button button-secondary" 
				onclick="delete_poll_logs('<?php echo esc_js( __( 'You are about to delete all poll logs. This action is not reversible.', 'wp-polls' ) ); ?>', '<?php echo esc_js( wp_create_nonce( 'wp-polls_delete-polls-logs' ) ); ?>');" />
			</p>
			<?php
		} else {
			esc_html_e( 'No poll logs available.', 'wp-polls' );
		}
		?>
	</div>
	
	<p><?php esc_html_e( 'Note: If your logging method is by IP and Cookie or by Cookie, users may still be unable to vote if they have voted before as the cookie is still stored in their computer.', 'wp-polls' ); ?></p>
</div>
