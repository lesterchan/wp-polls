<?php
/**
 * Poll Data Access Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Data Class
 *
 * Handles all database interactions for the WP-Polls plugin.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Data {

	/**
	 * Define database tables.
	 *
	 * Makes the WordPress database variables available in global scope
	 * for WP-Polls tables.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function define_tables() {
		global $wpdb;
	
		// Define polls tables.
		$wpdb->pollsq = $wpdb->prefix . 'pollsq';
		$wpdb->pollsa = $wpdb->prefix . 'pollsa';
		$wpdb->pollsip = $wpdb->prefix . 'pollsip';
	}

	/**
	 * Get a single poll.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return object|false Poll data or false if not found.
	 */
	public static function get_poll( $poll_id ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		
		// Get poll question data
		$poll = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1",
				$poll_id
			)
		);
		
		if ( ! $poll ) {
			return false;
		}
		
		// Get poll answers
		$poll->answers = self::get_poll_answers( $poll_id );
		
		return $poll;
	}

	/**
	 * Get poll answers.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return array Array of poll answer objects.
	 */
	public static function get_poll_answers( $poll_id ) {
		global $wpdb;
		
		list( $order_by, $sort_order ) = self::get_answers_sort();
		
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order",
				$poll_id
			)
		);
	}

	/**
	 * Get the answer sort order settings.
	 *
	 * @since 2.78.0
	 * @return array Array containing order by field and sort direction.
	 */
	public static function get_answers_sort() {
		$order_by = get_option( 'poll_ans_sortby' );
		
		switch ( $order_by ) {
			case 'polla_votes':
			case 'polla_aid':
			case 'polla_answers':
			case 'RAND()':
				break;
			default:
				$order_by = 'polla_aid';
				break;
		}
		
		$sort_order = 'desc' === get_option( 'poll_ans_sortorder' ) ? 'desc' : 'asc';
		
		return array( $order_by, $sort_order );
	}

	/**
	 * Get the results sort order settings.
	 *
	 * @since 2.78.0
	 * @return array Array containing order by field and sort direction.
	 */
	public static function get_results_sort() {
		$order_by = get_option( 'poll_ans_result_sortby' );
		
		switch ( $order_by ) {
			case 'polla_votes':
			case 'polla_aid':
			case 'polla_answers':
			case 'RAND()':
				break;
			default:
				$order_by = 'polla_aid';
				break;
		}
		
		$sort_order = 'desc' === get_option( 'poll_ans_result_sortorder' ) ? 'desc' : 'asc';
		
		return array( $order_by, $sort_order );
	}

	/**
	 * Get multiple polls.
	 *
	 * @since 2.78.0
	 * @param array $args Query arguments.
	 * @return array Array of polls.
	 */
	public static function get_polls( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'per_page'      => 10,
			'page'          => 1,
			'active'        => null,
			'orderby'       => 'pollq_timestamp',
			'order'         => 'DESC',
			'include_answers' => false,
		);
		
		$args = wp_parse_args( $args, $defaults );
		$limit = '';
		$where = '1=1';
		
		// Active filter.
		if ( null !== $args['active'] ) {
			$where .= $wpdb->prepare( ' AND pollq_active = %d', $args['active'] );
		}
		
		// Pagination.
		if ( $args['per_page'] > 0 ) {
			$offset = ( $args['page'] - 1 ) * $args['per_page'];
			$limit = $wpdb->prepare( 'LIMIT %d, %d', $offset, $args['per_page'] );
		}
		
		// Order.
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'pollq_timestamp DESC';
		}
		
		// Get polls.
		$polls = $wpdb->get_results(
			"SELECT * FROM $wpdb->pollsq WHERE $where ORDER BY $orderby $limit"
		);
		
		// Include answers if requested.
		if ( $args['include_answers'] && $polls ) {
			foreach ( $polls as $poll ) {
				$poll->answers = self::get_poll_answers( $poll->pollq_id );
			}
		}
		
		return $polls;
	}

	/**
	 * Count total polls.
	 *
	 * @since 2.78.0
	 * @param array $args Query arguments.
	 * @return int Total number of polls.
	 */
	public static function get_polls_count( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'active' => null,
		);
		
		$args = wp_parse_args( $args, $defaults );
		$where = '1=1';
		
		// Active filter.
		if ( null !== $args['active'] ) {
			$where .= $wpdb->prepare( ' AND pollq_active = %d', $args['active'] );
		}
		
		return (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE $where" );
	}

	/**
	 * Get poll logs.
	 *
	 * @since 2.78.0
	 * @param array $args Query arguments.
	 * @return array Array of poll logs.
	 */
	public static function get_poll_logs( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'poll_id'   => 0,
			'per_page'  => 20,
			'page'      => 1,
			'orderby'   => 'pollip_timestamp',
			'order'     => 'DESC',
		);
		
		$args = wp_parse_args( $args, $defaults );
		$limit = '';
		$where = '1=1';
		
		// Poll ID filter.
		if ( $args['poll_id'] > 0 ) {
			$where .= $wpdb->prepare( ' AND pollip_qid = %d', $args['poll_id'] );
		}
		
		// Pagination.
		if ( $args['per_page'] > 0 ) {
			$offset = ( $args['page'] - 1 ) * $args['per_page'];
			$limit = $wpdb->prepare( 'LIMIT %d, %d', $offset, $args['per_page'] );
		}
		
		// Order.
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'pollip_timestamp DESC';
		}
		
		// Get logs.
		return $wpdb->get_results(
			"SELECT l.*, a.polla_answers, q.pollq_question 
			FROM $wpdb->pollsip l 
			INNER JOIN $wpdb->pollsa a ON l.pollip_aid = a.polla_aid 
			INNER JOIN $wpdb->pollsq q ON l.pollip_qid = q.pollq_id 
			WHERE $where 
			ORDER BY $orderby $limit"
		);
	}

	/**
	 * Count total poll logs.
	 *
	 * @since 2.78.0
	 * @param array $args Query arguments.
	 * @return int Total number of poll logs.
	 */
	public static function get_poll_logs_count( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'poll_id' => 0,
		);
		
		$args = wp_parse_args( $args, $defaults );
		$where = '1=1';
		
		// Poll ID filter.
		if ( $args['poll_id'] > 0 ) {
			$where .= $wpdb->prepare( ' AND pollip_qid = %d', $args['poll_id'] );
		}
		
		return (int) $wpdb->get_var( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip WHERE $where" );
	}

	/**
	 * Create a new poll.
	 *
	 * @since 2.78.0
	 * @param array $data Poll data.
	 * @return int|false New poll ID or false on failure.
	 */
	public static function add_poll( $data ) {
		global $wpdb;
		
		$defaults = array(
			'question'   => '',
			'timestamp'  => current_time( 'timestamp' ),
			'active'     => 1,
			'expiry'     => 0,
			'multiple'   => 0,
			'answers'    => array(),
		);
		
		$data = wp_parse_args( $data, $defaults );
		
		// Insert poll question.
		$insert_data = array(
			'pollq_question'    => $data['question'],
			'pollq_timestamp'   => $data['timestamp'],
			'pollq_totalvotes'  => 0,
			'pollq_active'      => $data['active'],
			'pollq_expiry'      => $data['expiry'],
			'pollq_multiple'    => $data['multiple'],
			'pollq_totalvoters' => 0,
		);
		
		$insert_format = array(
			'%s',  // question
			'%d',  // timestamp
			'%d',  // totalvotes
			'%d',  // active
			'%d',  // expiry
			'%d',  // multiple
			'%d',  // totalvoters
		);
		
		// Add poll type if set (standard, multiple, ranked)
		if ( isset( $data['type'] ) ) {
			$insert_data['pollq_type'] = $data['type'];
			$insert_format[] = '%s';  // type
		}
		
		$result = $wpdb->insert(
			$wpdb->pollsq,
			$insert_data,
			$insert_format
		);
		
		if ( ! $result ) {
			return false;
		}
		
		$poll_id = $wpdb->insert_id;
		
		// Insert poll answers.
		if ( ! empty( $data['answers'] ) ) {
			foreach ( $data['answers'] as $answer ) {
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $poll_id,
						'polla_answers' => $answer,
						'polla_votes'   => 0,
					),
					array(
						'%d',
						'%s',
						'%d',
					)
				);
			}
		}
		
		// Update latest poll ID.
		$latest_poll_id = self::get_latest_poll_id();
		update_option( 'poll_latestpoll', $latest_poll_id );
		
		return $poll_id;
	}

	/**
	 * Update an existing poll.
	 *
	 * @since 2.78.0
	 * @param int   $poll_id The poll ID.
	 * @param array $data    Poll data to update.
	 * @return bool Success or failure.
	 */
	public static function update_poll( $poll_id, $data ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		
		// Build update array.
		$update_data = array();
		$update_format = array();
		
		// Question.
		if ( isset( $data['question'] ) ) {
			$update_data['pollq_question'] = $data['question'];
			$update_format[] = '%s';
		}
		
		// Timestamp.
		if ( isset( $data['timestamp'] ) ) {
			$update_data['pollq_timestamp'] = $data['timestamp'];
			$update_format[] = '%d';
		}
		
		// Total votes.
		if ( isset( $data['totalvotes'] ) ) {
			$update_data['pollq_totalvotes'] = $data['totalvotes'];
			$update_format[] = '%d';
		}
		
		// Active.
		if ( isset( $data['active'] ) ) {
			$update_data['pollq_active'] = $data['active'];
			$update_format[] = '%d';
		}
		
		// Expiry.
		if ( isset( $data['expiry'] ) ) {
			$update_data['pollq_expiry'] = $data['expiry'];
			$update_format[] = '%d';
		}
		
		// Multiple.
		if ( isset( $data['multiple'] ) ) {
			$update_data['pollq_multiple'] = $data['multiple'];
			$update_format[] = '%d';
		}
		
		// Total voters.
		if ( isset( $data['totalvoters'] ) ) {
			$update_data['pollq_totalvoters'] = $data['totalvoters'];
			$update_format[] = '%d';
		}
		
		// Update if we have data.
		if ( ! empty( $update_data ) ) {
			$result = $wpdb->update(
				$wpdb->pollsq,
				$update_data,
				array( 'pollq_id' => $poll_id ),
				$update_format,
				array( '%d' )
			);
		} else {
			$result = true; // No changes to poll question.
		}
		
		// Update poll answers if provided.
		if ( isset( $data['answers'] ) && is_array( $data['answers'] ) ) {
			foreach ( $data['answers'] as $answer_id => $answer_data ) {
				if ( ! is_array( $answer_data ) ) {
					continue;
				}
				
				$answer_update = array();
				$answer_format = array();
				
				// Answer text.
				if ( isset( $answer_data['text'] ) ) {
					$answer_update['polla_answers'] = $answer_data['text'];
					$answer_format[] = '%s';
				}
				
				// Votes.
				if ( isset( $answer_data['votes'] ) ) {
					$answer_update['polla_votes'] = $answer_data['votes'];
					$answer_format[] = '%d';
				}
				
				if ( ! empty( $answer_update ) ) {
					$wpdb->update(
						$wpdb->pollsa,
						$answer_update,
						array(
							'polla_qid' => $poll_id,
							'polla_aid' => $answer_id,
						),
						$answer_format,
						array( '%d', '%d' )
					);
				}
			}
		}
		
		// Add new poll answers if provided.
		if ( isset( $data['new_answers'] ) && is_array( $data['new_answers'] ) ) {
			foreach ( $data['new_answers'] as $new_answer ) {
				if ( empty( $new_answer['text'] ) ) {
					continue;
				}
				
				$votes = isset( $new_answer['votes'] ) ? (int) $new_answer['votes'] : 0;
				
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $poll_id,
						'polla_answers' => $new_answer['text'],
						'polla_votes'   => $votes,
					),
					array(
						'%d',
						'%s',
						'%d',
					)
				);
			}
		}
		
		// Update latest poll ID.
		$latest_poll_id = self::get_latest_poll_id();
		update_option( 'poll_latestpoll', $latest_poll_id );
		
		return $result !== false;
	}

	/**
	 * Delete a poll and its answers.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return bool Success or failure.
	 */
	public static function delete_poll( $poll_id ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		
		// Delete poll logs.
		$wpdb->delete(
			$wpdb->pollsip,
			array( 'pollip_qid' => $poll_id ),
			array( '%d' )
		);
		
		// Delete poll answers.
		$wpdb->delete(
			$wpdb->pollsa,
			array( 'polla_qid' => $poll_id ),
			array( '%d' )
		);
		
		// Delete poll question.
		$result = $wpdb->delete(
			$wpdb->pollsq,
			array( 'pollq_id' => $poll_id ),
			array( '%d' )
		);
		
		// Update latest poll ID.
		$latest_poll_id = self::get_latest_poll_id();
		update_option( 'poll_latestpoll', $latest_poll_id );
		
		return $result !== false;
	}

	/**
	 * Delete a single poll answer.
	 *
	 * @since 2.78.0
	 * @param int $answer_id The answer ID.
	 * @param int $poll_id   The poll ID.
	 * @return bool Success or failure.
	 */
	public static function delete_poll_answer( $answer_id, $poll_id ) {
		global $wpdb;
		
		$answer_id = (int) $answer_id;
		$poll_id = (int) $poll_id;
		
		// Delete answer logs.
		$wpdb->delete(
			$wpdb->pollsip,
			array(
				'pollip_qid' => $poll_id,
				'pollip_aid' => $answer_id,
			),
			array( '%d', '%d' )
		);
		
		// Delete answer.
		$result = $wpdb->delete(
			$wpdb->pollsa,
			array(
				'polla_qid' => $poll_id,
				'polla_aid' => $answer_id,
			),
			array( '%d', '%d' )
		);
		
		// Update total votes for the poll.
		self::update_poll_total_votes( $poll_id );
		
		return $result !== false;
	}

	/**
	 * Delete all poll logs.
	 *
	 * @since 2.78.0
	 * @return bool Success or failure.
	 */
	public static function delete_all_poll_logs() {
		global $wpdb;
		
		$result = $wpdb->query( "TRUNCATE TABLE $wpdb->pollsip" );
		
		return $result !== false;
	}

	/**
	 * Update the total votes count for a poll.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return bool Success or failure.
	 */
	public static function update_poll_total_votes( $poll_id ) {
		global $wpdb;
		
		$poll_id = (int) $poll_id;
		
		// Get total votes.
		$total_votes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(polla_votes) FROM $wpdb->pollsa WHERE polla_qid = %d",
				$poll_id
			)
		);
		
		// Update poll total votes.
		$result = $wpdb->update(
			$wpdb->pollsq,
			array( 'pollq_totalvotes' => $total_votes ),
			array( 'pollq_id' => $poll_id ),
			array( '%d' ),
			array( '%d' )
		);
		
		return $result !== false;
	}

	/**
	 * Get the latest active poll ID.
	 *
	 * @since 2.78.0
	 * @return int The latest poll ID.
	 */
	public static function get_latest_poll_id() {
		global $wpdb;
		
		$poll_id = $wpdb->get_var(
			"SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1"
		);
		
		return (int) $poll_id;
	}

	/**
	 * Verify that all poll tables exist.
	 *
	 * @since 2.78.0
	 * @return bool True if all tables exist, false otherwise.
	 */
	public static function tables_exist() {
		global $wpdb;
		
		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}pollsq'" );
		
		return ! empty( $result );
	}

	/**
	 * Install polls tables and default options.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		
		self::define_tables();
		
		// Create poll question table.
		$create_pollsq_sql = "CREATE TABLE $wpdb->pollsq (
			pollq_id int(10) NOT NULL auto_increment,
			pollq_question varchar(200) NOT NULL default '',
			pollq_timestamp varchar(20) NOT NULL default '',
			pollq_totalvotes int(10) NOT NULL default '0',
			pollq_active tinyint(1) NOT NULL default '1',
			pollq_expiry varchar(20) NOT NULL default '',
			pollq_multiple tinyint(3) NOT NULL default '0',
			pollq_totalvoters int(10) NOT NULL default '0',
			pollq_type varchar(20) default NULL,
			PRIMARY KEY  (pollq_id)
			) $wpdb->charset_collate;";
		
		// Create poll answer table.
		$create_pollsa_sql = "CREATE TABLE $wpdb->pollsa (
			polla_aid int(10) NOT NULL auto_increment,
			polla_qid int(10) NOT NULL default '0',
			polla_answers varchar(200) NOT NULL default '',
			polla_votes int(10) NOT NULL default '0',
			PRIMARY KEY  (polla_aid)
			) $wpdb->charset_collate;";
		
		// Create poll IP table.
		$create_pollsip_sql = "CREATE TABLE $wpdb->pollsip (
			pollip_id int(10) NOT NULL auto_increment,
			pollip_qid int(10) NOT NULL default '0',
			pollip_aid int(10) NOT NULL default '0',
			pollip_ip varchar(100) NOT NULL default '',
			pollip_host varchar(200) NOT NULL default '',
			pollip_timestamp int(10) NOT NULL default '0',
			pollip_userid int(10) NOT NULL default '0',
			pollip_user_agent varchar(255) NOT NULL default '',
			PRIMARY KEY  (pollip_id),
			KEY pollip_ip (pollip_ip),
			KEY pollip_qid (pollip_qid),
			KEY pollip_timestamp (pollip_timestamp)
			) $wpdb->charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// Create tables.
		dbDelta( $create_pollsq_sql );
		dbDelta( $create_pollsa_sql );
		dbDelta( $create_pollsip_sql );
		
		// Set default options if they don't exist.
		add_option( 'poll_template_voteheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>' );
		add_option( 'poll_template_votebody', '<div id="polls-%POLL_ID%-ans" class="wp-polls-ans"><ul class="wp-polls-ul">%POLL_ANSWERS%</ul></div>' );
		add_option( 'poll_template_votefooter', '<p style="text-align: center;"><input type="button" name="vote" value="%POLL_VOTE_BUTTON%" class="Buttons" onclick="poll_vote(%POLL_ID%);" /></p>' );
		
		add_option( 'poll_template_resultheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>' );
		add_option( 'poll_template_resultbody', '<div id="polls-%POLL_ID%-ans" class="wp-polls-ans"><ul class="wp-polls-ul">%POLL_ANSWERS%</ul><div class="pollbar-result">%POLL_MOST_ANSWER% <strong>%POLL_MOST_VOTES%</strong> %POLL_MOST_PERCENTAGE%% </div></div>' );
		add_option( 'poll_template_resultfooter', '<p style="text-align: center;">%POLL_MULTIPLE_ANS_MSG%<br />%POLL_TOTAL_VOTES%</p>' );
		
		add_option( 'poll_template_resultfooter2', '<p style="text-align: center;">%POLL_START_DATE%<br />%POLL_END_DATE%<br />%POLL_MULTIPLE_ANS_MSG%<br />%POLL_TOTAL_VOTES%</p>' );
		add_option( 'poll_template_disable', '<p style="text-align: center; font-weight: bold;">%POLL_DISABLED_MSG%</p>' );
		add_option( 'poll_template_error', '<p style="text-align: center; font-weight: bold;">%POLL_ERROR_MSG%</p>' );
		
		add_option( 'poll_currentpoll', 0 );
		add_option( 'poll_latestpoll', 0 );
		add_option( 'poll_archive_perpage', 5 );
		add_option( 'poll_ans_sortby', 'polla_aid' );
		add_option( 'poll_ans_sortorder', 'asc' );
		add_option( 'poll_ans_result_sortby', 'polla_votes' );
		add_option( 'poll_ans_result_sortorder', 'desc' );
		add_option( 'poll_logging_method', 3 );
		add_option( 'poll_allowtovote', 2 );
		add_option( 'poll_archive_url', site_url( 'pollsarchive' ) );
		add_option( 'poll_bar', 1 );
		add_option( 'poll_close', 1 );
		add_option( 'poll_ajax_style', 1 );
		add_option( 'poll_template_pollarchivelink', '<ul><li><a href="%POLL_ARCHIVE_URL%">%POLL_ARCHIVE_TEXT%</a></li></ul>' );
		add_option( 'poll_archive_displaypoll', 2 );
		add_option( 'poll_template_pollarchiveheader', '' );
		add_option( 'poll_template_pollarchivefooter', '<p style="text-align: center;">%POLL_ARCHIVE_NAVIGATION%</p>' );
		add_option( 'poll_cookielog_expiry', 0 );
		add_option( 'poll_stylesheet_url', WP_POLLS_PLUGIN_URL . '/polls-css.css' );
	}
}
