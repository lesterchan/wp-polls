<?php
/**
 * Admin View: Poll Logs
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get poll ID.
$poll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

// Pagination settings.
$limit = 200;
$page = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
if ( $page < 1 ) {
	$page = 1;
}
$offset = ( $page - 1 ) * $limit;

// Get poll data.
$poll = WP_Polls_Data::get_poll( $poll_id );
if ( ! $poll ) {
	wp_die( esc_html__( 'Poll not found.', 'wp-polls' ) );
}

// Get poll logs.
$poll_logs_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip WHERE pollip_qid = %d", $poll_id ) );
$poll_logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->pollsip WHERE pollip_qid = %d ORDER BY pollip_timestamp DESC LIMIT %d, %d", $poll_id, $offset, $limit ) );

// Calculate pagination.
$total_pages = ceil( $poll_logs_count / $limit );
$current_url = add_query_arg( array( 'page' => 'polls-manager', 'mode' => 'logs', 'id' => $poll_id ), admin_url( 'admin.php' ) );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Poll Logs', 'wp-polls' ); ?></h1>
	
	<?php WP_Polls_Manager::display_messages(); ?>
	
	<h2><?php echo esc_html( $poll->pollq_question ); ?></h2>
	
	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-1">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<h3 class="hndle"><?php esc_html_e( 'Voting Records', 'wp-polls' ); ?></h3>
						<div class="inside">
							<?php if ( $poll_logs ) : ?>
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'No.', 'wp-polls' ); ?></th>
											<th><?php esc_html_e( 'Username', 'wp-polls' ); ?></th>
											<th><?php esc_html_e( 'IP', 'wp-polls' ); ?></th>
											<th><?php esc_html_e( 'Host', 'wp-polls' ); ?></th>
											<th><?php esc_html_e( 'Answer', 'wp-polls' ); ?></th>
											<th><?php esc_html_e( 'Timestamp', 'wp-polls' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$i = 0;
										foreach ( $poll_logs as $poll_log ) {
											$i++;
											$log_id = (int) $poll_log->pollip_id;
											$log_answertext = wp_kses_post( WP_Polls_Utility::remove_slashes( $poll_log->pollip_answertext ) );
											$log_ip = esc_html( $poll_log->pollip_ip );
											$log_host = esc_html( $poll_log->pollip_host );
											$log_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_log->pollip_timestamp ) );
											$log_user = '';
											
											if ( $poll_log->pollip_userid > 0 ) {
												$user = get_userdata( $poll_log->pollip_userid );
												if ( $user ) {
													$log_user = $user->display_name . ' (' . $user->user_login . ')';
												}
											}
											?>
											<tr>
												<td><?php echo esc_html( number_format_i18n( $i + $offset ) ); ?></td>
												<td><?php echo esc_html( $log_user ); ?></td>
												<td><?php echo esc_html( $log_ip ); ?></td>
												<td><?php echo esc_html( $log_host ); ?></td>
												<td><?php echo $log_answertext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
												<td><?php echo esc_html( $log_date ); ?></td>
											</tr>
											<?php
										}
										?>
									</tbody>
								</table>
								
								<?php if ( $total_pages > 1 ) : ?>
									<div class="tablenav">
										<div class="tablenav-pages">
											<span class="displaying-num">
												<?php 
												/* translators: %s: Number of logs */
												printf( esc_html__( '%s logs', 'wp-polls' ), esc_html( number_format_i18n( $poll_logs_count ) ) ); 
												?>
											</span>
											
											<span class="pagination-links">
												<?php if ( $page > 1 ) : ?>
													<a class="first-page" href="<?php echo esc_url( add_query_arg( 'paged', 1, $current_url ) ); ?>">
														<span class="screen-reader-text"><?php esc_html_e( 'First page', 'wp-polls' ); ?></span>
														<span aria-hidden="true">&laquo;</span>
													</a>
													<a class="prev-page" href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $page - 1 ), $current_url ) ); ?>">
														<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'wp-polls' ); ?></span>
														<span aria-hidden="true">&lsaquo;</span>
													</a>
												<?php else : ?>
													<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
													<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
												<?php endif; ?>
												
												<span class="paging-input">
													<span class="tablenav-paging-text">
														<?php printf( esc_html__( '%1$s of %2$s', 'wp-polls' ), esc_html( number_format_i18n( $page ) ), esc_html( number_format_i18n( $total_pages ) ) ); ?>
													</span>
												</span>
												
												<?php if ( $page < $total_pages ) : ?>
													<a class="next-page" href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $page + 1 ), $current_url ) ); ?>">
														<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'wp-polls' ); ?></span>
														<span aria-hidden="true">&rsaquo;</span>
													</a>
													<a class="last-page" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ); ?>">
														<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'wp-polls' ); ?></span>
														<span aria-hidden="true">&raquo;</span>
													</a>
												<?php else : ?>
													<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
													<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
												<?php endif; ?>
											</span>
										</div>
										<br class="clear" />
									</div>
								<?php endif; ?>
							<?php else : ?>
								<p><?php esc_html_e( 'No logs found for this poll.', 'wp-polls' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<br class="clear" />
	</div>
	
	<p>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'delete-poll-logs', 'id' => $poll_id, '_wpnonce' => wp_create_nonce( 'wp-polls_delete-polls-logs' ) ), admin_url( 'admin.php?page=polls-manager' ) ) ); ?>" 
		   class="button button-secondary" 
		   onclick="return confirm('<?php echo esc_js( __( 'You are about to delete all logs for this poll. This action is not reversible.', 'wp-polls' ) ); ?>');">
			<?php esc_html_e( 'Delete Logs For This Poll', 'wp-polls' ); ?>
		</a>
		&nbsp;&nbsp;
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=polls-manager' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Back To Polls', 'wp-polls' ); ?></a>
	</p>
</div>
