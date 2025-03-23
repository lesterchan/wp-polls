<?php
/**
 * WP-Polls Database Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activate WP-Polls
 */
function polls_activate() {
	global $wpdb;

	if(@is_file(ABSPATH.'/wp-admin/includes/upgrade.php')) {
		include_once(ABSPATH.'/wp-admin/includes/upgrade.php');
	} elseif(@is_file(ABSPATH.'/wp-admin/upgrade-functions.php')) {
		include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	} else {
		die('We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'');
	}

	// Create Poll Tables (3 Tables)
	$charset_collate = $wpdb->get_charset_collate();

	$create_table = array();
	$create_table['pollsq'] = "CREATE TABLE $wpdb->pollsq (".
							  "pollq_id int(10) NOT NULL auto_increment," .
							  "pollq_question varchar(200) character set utf8 NOT NULL default ''," .
							  "pollq_timestamp varchar(20) NOT NULL default ''," .
							  "pollq_totalvotes int(10) NOT NULL default '0'," .
							  "pollq_active tinyint(1) NOT NULL default '1'," .
							  "pollq_expiry int(10) NOT NULL default '0'," .
							  "pollq_multiple tinyint(3) NOT NULL default '0'," .
							  "pollq_totalvoters int(10) NOT NULL default '0'," .
							  "pollq_type varchar(50) NOT NULL default 'classic'," .
							  "PRIMARY KEY  (pollq_id)" .
							  ") $charset_collate;";
	$create_table['pollsa'] = "CREATE TABLE $wpdb->pollsa (" .
							  "polla_aid int(10) NOT NULL auto_increment," .
							  "polla_qid int(10) NOT NULL default '0'," .
							  "polla_answers varchar(200) character set utf8 NOT NULL default ''," .
							  "polla_votes int(10) NOT NULL default '0'," .
							  "PRIMARY KEY  (polla_aid)" .
							  ") $charset_collate;";
	$create_table['pollsip'] = "CREATE TABLE $wpdb->pollsip (" .
							   "pollip_id int(10) NOT NULL auto_increment," .
							   "pollip_qid int(10) NOT NULL default '0'," .
							   "pollip_aid int(10) NOT NULL default '0'," .
							   "pollip_ip varchar(100) NOT NULL default ''," .
							   "pollip_host VARCHAR(200) NOT NULL default ''," .
							   "pollip_timestamp int(10) NOT NULL default '0'," .
							   "pollip_user tinytext NOT NULL," .
							   "pollip_userid int(10) NOT NULL default '0'," .
							   "PRIMARY KEY  (pollip_id)," .
							   "KEY pollip_ip (pollip_ip)," .
							   "KEY pollip_qid (pollip_qid)," .
							   "KEY pollip_ip_qid (pollip_ip, pollip_qid)" .
							   ") $charset_collate;";
	dbDelta( $create_table['pollsq'] );
	dbDelta( $create_table['pollsa'] );
	dbDelta( $create_table['pollsip'] );
	// Check Whether It is Install Or Upgrade
	$first_poll = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq LIMIT 1" );
	// If Install, Insert 1st Poll Question With 5 Poll Answers
	if ( empty( $first_poll ) ) {
		// Insert Poll Question (1 Record)
		$insert_pollq = $wpdb->insert( $wpdb->pollsq, array( 'pollq_question' => __( 'How Is My Site?', 'wp-polls' ), 'pollq_timestamp' => current_time( 'timestamp' ) ), array( '%s', '%s' ) );
		if ( $insert_pollq ) {
			// Insert Poll Answers  (5 Records)
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Good', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Excellent', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Bad', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Can Be Improved', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'No Comments', 'wp-polls' ) ), array( '%d', '%s' ) );
		}
	}
	// Add In Options (16 Records)
	add_option('poll_template_voteheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_votebody', '<li><input type="%POLL_CHECKBOX_RADIO%" id="poll-answer-%POLL_ANSWER_ID%" name="poll_%POLL_ID%" value="%POLL_ANSWER_ID%" /> <label for="poll-answer-%POLL_ANSWER_ID%">%POLL_ANSWER%</label></li>');
	add_option('poll_template_votefooter', '</ul>'.
	'<p style="text-align: center;"><input type="button" name="vote" value="   '.__('Vote', 'wp-polls').'   " class="Buttons" onclick="poll_vote(%POLL_ID%);" /></p>'.
	'<p style="text-align: center;"><a href="#ViewPollResults" onclick="poll_result(%POLL_ID%); return false;" title="'.__('View Results Of This Poll', 'wp-polls').'">'.__('View Results', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_resultheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_resultbody', '<li>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="%POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultbody2', '<li><strong><i>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small></i></strong><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="'.__('You Have Voted For This Choice', 'wp-polls').' - %POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultfooter', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'</div>');
	add_option('poll_template_resultfooter2', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'<p style="text-align: center;"><a href="#VotePoll" onclick="poll_booth(%POLL_ID%); return false;" title="'.__('Vote For This Poll', 'wp-polls').'">'.__('Vote', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_disable', __('Sorry, there are no polls available at the moment.', 'wp-polls'));
	add_option('poll_template_error', __('An error has occurred when processing your poll.', 'wp-polls'));
	add_option('poll_currentpoll', 0);
	add_option('poll_latestpoll', 1);
	add_option('poll_archive_perpage', 5);
	add_option('poll_ans_sortby', 'polla_aid');
	add_option('poll_ans_sortorder', 'asc');
	add_option('poll_ans_result_sortby', 'polla_votes');
	add_option('poll_ans_result_sortorder', 'desc');
	// Database Upgrade For WP-Polls 2.1
	add_option('poll_logging_method', '3');
	add_option('poll_allowtovote', '2');
	// Database Upgrade For WP-Polls 2.12
	add_option('poll_archive_url', site_url('pollsarchive'));
	// Database Upgrade For WP-Polls 2.13
	add_option('poll_bar', array('style' => 'default', 'background' => 'd8e1eb', 'border' => 'c8c8c8', 'height' => 8));
	// Database Upgrade For WP-Polls 2.14
	add_option('poll_close', 1);
	// Database Upgrade For WP-Polls 2.20
	add_option('poll_ajax_style', array('loading' => 1, 'fading' => 1));
	add_option('poll_template_pollarchivelink', '<ul>'.
	'<li><a href="%POLL_ARCHIVE_URL%">'.__('Polls Archive', 'wp-polls').'</a></li>'.
	'</ul>');
	add_option('poll_archive_displaypoll', 2);
	add_option('poll_template_pollarchiveheader', '');
	add_option('poll_template_pollarchivefooter', '<p>'.__('Start Date:', 'wp-polls').' %POLL_START_DATE%<br />'.__('End Date:', 'wp-polls').' %POLL_END_DATE%</p>');

	$pollq_totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
	if ( 0 === $pollq_totalvoters ) {
		$wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvoters = pollq_totalvotes" );
	}

	// Database Upgrade For WP-Polls 2.30
	add_option('poll_cookielog_expiry', 0);
	add_option('poll_template_pollarchivepagingheader', '');
	add_option('poll_template_pollarchivepagingfooter', '');

	// Database Upgrade For WP-Polls 2.50
	delete_option('poll_archive_show');

	// Database Upgrade for WP-Polls 2.76
	add_option( 'poll_options', array( 'ip_header' => '' ) );

	// Index
	$index = $wpdb->get_results( "SHOW INDEX FROM $wpdb->pollsip;" );
	$key_name = array();
	if( count( $index ) > 0 ) {
		foreach( $index as $i ) {
			$key_name[]= $i->Key_name;
		}
	}
	if ( ! in_array( 'pollip_ip', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip (pollip_ip);" );
	}
	if ( ! in_array( 'pollip_qid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_qid (pollip_qid);" );
	}
	if ( ! in_array( 'pollip_ip_qid_aid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip_qid_aid (pollip_ip, pollip_qid, pollip_aid);" );
	}
	// No longer needed index
	if ( in_array( 'pollip_ip_qid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip DROP INDEX pollip_ip_qid;" );
	}

	// Change column datatype for wp_pollsip
	$col_pollip_qid = $wpdb->get_row( "DESCRIBE $wpdb->pollsip pollip_qid" );
	if( 'varchar(10)' === $col_pollip_qid->Type ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_qid int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_aid int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_timestamp int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsq MODIFY COLUMN pollq_expiry int(10) NOT NULL default '0';" );
	}

	// Set 'manage_polls' Capabilities To Administrator
	$role = get_role( 'administrator' );
	if( ! $role->has_cap( 'manage_polls' ) ) {
		$role->add_cap( 'manage_polls' );
	}
	cron_polls_place();
}

/**
 * Plugin activation function called from main file
 * 
 * @param bool $network_wide Whether the plugin is being activated network-wide
 */
function polls_activation( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$ms_sites = wp_get_sites();

		if( 0 < count( $ms_sites ) ) {
			foreach ( $ms_sites as $ms_site ) {
				switch_to_blog( $ms_site['blog_id'] );
				polls_activate();
				restore_current_blog();
			}
		}
	} else {
		polls_activate();
	}
}

/**
 * Define database table names
 */
function polls_define_tables() {
	global $wpdb;
	$wpdb->pollsq   = $wpdb->prefix.'pollsq';
	$wpdb->pollsa   = $wpdb->prefix.'pollsa';
	$wpdb->pollsip  = $wpdb->prefix.'pollsip';
}
