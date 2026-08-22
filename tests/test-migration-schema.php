<?php
/**
 * The upgrade at the table level: real legacy schemas, populated, upgraded.
 *
 * Where test-upgrade.php proves the option rows are folded in correctly,
 * this file proves the other half of "upgrading will not break": the three tables of a
 * genuinely old install -- built from the CREATE TABLE statements those
 * versions shipped, not from what 3.0.0 would like them to have been -- come
 * through activation with every vote intact, and voting still works on the
 * migrated rows.
 *
 * Two legacy shapes matter. A 2.77.3 site, which is what almost every install
 * is: its columns are already identical to 3.0.0's, and the job is proving the
 * upgrade touches nothing. And the ancient shape, pollip_qid as varchar(10),
 * which is what the datatype conversion in activate() exists for: those sites
 * have been carried this far, and a 3.0.0 that dropped their votes would do it
 * silently.
 *
 * These tests issue DDL, and DDL commits the transaction WP_UnitTestCase
 * wraps around each test. tear_down() rebuilds the tables by hand instead of
 * trusting the rollback -- and then commits, which is the subtle half. The
 * test runner leaves autocommit off, so everything after the mid-test DDL
 * commit sits in a new implicit transaction; without an explicit COMMIT the
 * parent's ROLLBACK undoes the cleanup itself, and the test's leftover legacy
 * rows (made durable by the DDL in the next test's fixture) leak into
 * whichever file runs after this one. That is not a hypothetical: it cost the
 * beta-key test in test-upgrade.php its expected check_method.
 *
 * @package WP-Polls
 */

/**
 * Table-level migration tests.
 */
class WP_Polls_Migration_Schema_Test extends WP_Polls_TestCase {

	/**
	 * Rebuild a pristine 3.0.0 install, because the rollback cannot.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsq}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsa}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsip}" );

		$this->delete_legacy_rows();
		delete_option( WP_Polls_Options::OPTION );
		delete_option( WP_Polls_Options::VERSION );
		WP_Polls_Options::flush();

		WP_Polls_Install::install();

		// Make the rebuild durable before the parent rolls back. See the file
		// docblock for why skipping this poisons the next test file.
		$wpdb->query( 'COMMIT' );

		parent::tear_down();
	}

	/**
	 * Turn the site into an un-upgraded install: no option row, no markers.
	 *
	 * @return void
	 */
	private function forget_the_upgrade() {
		delete_option( WP_Polls_Options::OPTION );
		delete_option( WP_Polls_Options::VERSION );
		WP_Polls_Options::flush();
	}

	/**
	 * Remove every scattered pre-3.0.0 row this file seeds.
	 *
	 * @return void
	 */
	private function delete_legacy_rows() {
		foreach ( array( 'poll_logging_method', 'poll_cookielog_expiry', 'poll_allowtovote', 'poll_close' ) as $name ) {
			delete_option( $name );
		}
	}

	/**
	 * Drop the three tables and recreate them as the released 2.77.3 does.
	 *
	 * These CREATE TABLE statements are transcribed from the 2.77.3 zip on
	 * wordpress.org, not from class-wp-polls-install.php. That is the point:
	 * if the two ever disagree, this file should be the one still holding the
	 * shape real sites have.
	 *
	 * The pollip_ip_qid key is the 2.77.3 CREATE's own; its activation hook
	 * replaced it with pollip_ip_qid_aid, but a site that arrived at 2.77.3
	 * through the plugins screen never ran that hook. This is that site --
	 * the least-upgraded shape 3.0.0 can meet.
	 *
	 * @return void
	 */
	private function create_2_77_3_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsq}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsa}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsip}" );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Building the legacy fixture, verbatim, is the test.
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsq} (" .
			'pollq_id int(10) NOT NULL auto_increment,' .
			"pollq_question varchar(200) character set utf8 NOT NULL default ''," .
			"pollq_timestamp varchar(20) NOT NULL default ''," .
			"pollq_totalvotes int(10) NOT NULL default '0'," .
			"pollq_active tinyint(1) NOT NULL default '1'," .
			"pollq_expiry int(10) NOT NULL default '0'," .
			"pollq_multiple tinyint(3) NOT NULL default '0'," .
			"pollq_totalvoters int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (pollq_id)' .
			") $charset_collate"
		);
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsa} (" .
			'polla_aid int(10) NOT NULL auto_increment,' .
			"polla_qid int(10) NOT NULL default '0'," .
			"polla_answers varchar(200) character set utf8 NOT NULL default ''," .
			"polla_votes int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (polla_aid)' .
			") $charset_collate"
		);
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsip} (" .
			'pollip_id int(10) NOT NULL auto_increment,' .
			"pollip_qid int(10) NOT NULL default '0'," .
			"pollip_aid int(10) NOT NULL default '0'," .
			"pollip_ip varchar(100) NOT NULL default ''," .
			"pollip_host VARCHAR(200) NOT NULL default ''," .
			"pollip_timestamp int(10) NOT NULL default '0'," .
			'pollip_user tinytext NOT NULL,' .
			"pollip_userid int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (pollip_id),' .
			'KEY pollip_ip (pollip_ip),' .
			'KEY pollip_qid (pollip_qid),' .
			'KEY pollip_ip_qid (pollip_ip, pollip_qid)' .
			") $charset_collate"
		);
		// phpcs:enable
	}

	/**
	 * Drop the three tables and recreate the ancient shape.
	 *
	 * The varchar(10) pollip_qid is the fingerprint activate() looks for with
	 * DESCRIBE before converting; the timestamps rode along as strings too.
	 * pollq carries a varchar expiry, and the empty string was how those
	 * versions spelled "no expiry" -- which matters, because '' does not
	 * survive a MODIFY COLUMN to int under strict SQL.
	 *
	 * @return void
	 */
	private function create_ancient_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsq}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsa}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->pollsip}" );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Building the legacy fixture, verbatim, is the test.
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsq} (" .
			'pollq_id int(10) NOT NULL auto_increment,' .
			"pollq_question varchar(200) character set utf8 NOT NULL default ''," .
			"pollq_timestamp varchar(20) NOT NULL default ''," .
			"pollq_totalvotes int(10) NOT NULL default '0'," .
			"pollq_active tinyint(1) NOT NULL default '1'," .
			"pollq_expiry varchar(20) NOT NULL default ''," .
			"pollq_multiple tinyint(3) NOT NULL default '0'," .
			"pollq_totalvoters int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (pollq_id)' .
			") $charset_collate"
		);
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsa} (" .
			'polla_aid int(10) NOT NULL auto_increment,' .
			"polla_qid int(10) NOT NULL default '0'," .
			"polla_answers varchar(200) character set utf8 NOT NULL default ''," .
			"polla_votes int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (polla_aid)' .
			") $charset_collate"
		);
		$wpdb->query(
			"CREATE TABLE {$wpdb->pollsip} (" .
			'pollip_id int(10) NOT NULL auto_increment,' .
			"pollip_qid varchar(10) NOT NULL default ''," .
			"pollip_aid varchar(10) NOT NULL default ''," .
			"pollip_ip varchar(100) NOT NULL default ''," .
			"pollip_host varchar(200) NOT NULL default ''," .
			"pollip_timestamp varchar(20) NOT NULL default ''," .
			'pollip_user tinytext NOT NULL,' .
			"pollip_userid int(10) NOT NULL default '0'," .
			'PRIMARY KEY  (pollip_id),' .
			'KEY pollip_ip_qid (pollip_ip, pollip_qid)' .
			") $charset_collate"
		);
		// phpcs:enable
	}

	/**
	 * Populate whichever legacy tables exist with a site's worth of votes.
	 *
	 * The answer text carries quotes, an ampersand and multibyte characters,
	 * because a migration that mangles encoding "passes" every test written in
	 * ASCII.
	 *
	 * @return array Expected snapshots, keyed by table.
	 */
	private function populate_legacy_data() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'How Is My Site?',
				'pollq_timestamp'   => '1600000000',
				'pollq_totalvotes'  => 5,
				'pollq_active'      => 1,
				'pollq_multiple'    => 0,
				'pollq_totalvoters' => 5,
			)
		);
		$first = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => "D\xC3\xA9j\xC3\xA0 vu — \"quotes\" & ampersands?",
				'pollq_timestamp'   => '1700000000',
				'pollq_totalvotes'  => 2,
				'pollq_active'      => 0,
				'pollq_multiple'    => 2,
				'pollq_totalvoters' => 1,
			)
		);
		$second = (int) $wpdb->insert_id;

		foreach ( array(
			array( $first, 'Good', 3 ),
			array( $first, 'Bad', 2 ),
			array( $second, "S\xC3\xAE, \"tr\xC3\xA8s\" <bien>", 2 ),
			array( $second, 'No & yes', 0 ),
		) as $answer ) {
			$wpdb->insert(
				$wpdb->pollsa,
				array(
					'polla_qid'     => $answer[0],
					'polla_answers' => $answer[1],
					'polla_votes'   => $answer[2],
				)
			);
		}

		for ( $i = 1; $i <= 5; $i++ ) {
			$wpdb->insert(
				$wpdb->pollsip,
				array(
					'pollip_qid'       => $first,
					'pollip_aid'       => $i > 3 ? 2 : 1,
					'pollip_ip'        => "203.0.113.$i",
					'pollip_host'      => "host$i.example.com",
					'pollip_timestamp' => 1600000000 + $i,
					'pollip_user'      => 0 === $i % 2 ? "voter$i" : '',
					'pollip_userid'    => 0 === $i % 2 ? $i : 0,
				)
			);
		}

		return array(
			'pollsq'  => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsq} ORDER BY pollq_id", ARRAY_A ),
			'pollsa'  => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsa} ORDER BY polla_aid", ARRAY_A ),
			'pollsip' => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsip} ORDER BY pollip_id", ARRAY_A ),
		);
	}

	/**
	 * The index names on the vote log, for asserting the ALTERs happened.
	 *
	 * @return string[]
	 */
	private function pollsip_index_names() {
		global $wpdb;

		$names = array();
		foreach ( $wpdb->get_results( "SHOW INDEX FROM {$wpdb->pollsip}" ) as $index ) {
			$names[] = $index->Key_name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column name returned by SHOW INDEX.
		}

		return array_unique( $names );
	}

	public function test_a_populated_2_77_3_install_survives_reactivation_row_for_row() {
		global $wpdb;

		$this->create_2_77_3_tables();
		$before = $this->populate_legacy_data();
		update_option( 'poll_logging_method', 2 );
		update_option( 'poll_cookielog_expiry', 604800 );
		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$after = array(
			'pollsq'  => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsq} ORDER BY pollq_id", ARRAY_A ),
			'pollsa'  => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsa} ORDER BY polla_aid", ARRAY_A ),
			'pollsip' => $wpdb->get_results( "SELECT * FROM {$wpdb->pollsip} ORDER BY pollip_id", ARRAY_A ),
		);

		// Row for row and byte for byte: an upgrade that "only" reset a vote
		// count or re-encoded a question would still fail this.
		$this->assertSame( $before, $after, 'the upgrade changed poll data it had no reason to touch' );
	}

	public function test_reactivation_does_not_seed_the_default_poll_into_an_install_that_has_polls() {
		global $wpdb;

		$this->create_2_77_3_tables();
		$this->populate_legacy_data();
		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		// The default "How Is My Site?" poll is for empty installs only. This
		// fixture already has a poll by that name; a second one appearing means
		// the emptiness check looked at the wrong thing.
		$this->assertSame(
			2,
			(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->pollsq}" ),
			'reactivation seeded the default poll into a populated install'
		);
	}

	public function test_the_2_77_3_index_set_is_brought_forward() {
		$this->create_2_77_3_tables();
		$this->populate_legacy_data();
		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$names = $this->pollsip_index_names();

		$this->assertContains( 'pollip_ip_qid_aid', $names, 'the covering index for repeat-vote checks was not added' );
		$this->assertNotContains( 'pollip_ip_qid', $names, 'the superseded two-column index was left behind' );
	}

	public function test_the_plugin_update_path_migrates_options_without_touching_tables() {
		global $wpdb;

		$this->create_2_77_3_tables();
		$before = $this->populate_legacy_data();
		update_option( 'poll_logging_method', 2 );
		$this->forget_the_upgrade();

		// Updating through the plugins screen never fires the activation hook.
		// The init hook runs upgrade() alone, and that path on its own must leave
		// a working install.
		WP_Polls_Install::upgrade();

		$this->assertSame( 2, (int) WP_Polls_Options::get( 'check_method' ), 'the update path did not migrate the options' );
		$this->assertFalse( get_option( 'poll_logging_method' ), 'the update path left the legacy row behind' );

		$markers = WP_Polls_Options::markers();
		$this->assertSame( WP_POLLS_VERSION, $markers['plugin'], 'the update path did not stamp the plugin marker' );

		$after = $wpdb->get_results( "SELECT * FROM {$wpdb->pollsip} ORDER BY pollip_id", ARRAY_A );
		$this->assertSame( $before['pollsip'], $after, 'the update path touched the vote log' );
	}

	public function test_voting_still_works_on_migrated_data() {
		global $wpdb;

		$this->create_2_77_3_tables();
		$this->populate_legacy_data();
		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$poll_id = (int) $wpdb->get_var( "SELECT pollq_id FROM {$wpdb->pollsq} WHERE pollq_active = 1 LIMIT 1" );
		$answer  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_aid FROM {$wpdb->pollsa} WHERE polla_qid = %d ORDER BY polla_aid LIMIT 1", $poll_id ) );
		$votes   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answer ) );
		$logged  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) );

		// The proof that the migrated rows are not just present but alive: a
		// vote lands in the answer count, the poll totals and the log, all
		// three keyed by ids that came through the upgrade.
		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answer ) );

		$this->assertSame(
			$votes + 1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT polla_votes FROM {$wpdb->pollsa} WHERE polla_aid = %d", $answer ) ),
			'a vote on a migrated answer did not count'
		);
		$this->assertSame(
			$logged + 1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", $poll_id ) ),
			'a vote on a migrated poll was not logged'
		);
	}

	public function test_the_ancient_varchar_columns_are_converted_without_losing_votes() {
		global $wpdb;

		$this->create_ancient_tables();

		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'An old poll',
				'pollq_timestamp'   => '1000000000',
				'pollq_totalvotes'  => 3,
				'pollq_expiry'      => '',
				'pollq_totalvoters' => 3,
			)
		);
		$poll_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'An old poll with an expiry',
				'pollq_timestamp'   => '1000000001',
				'pollq_totalvotes'  => 0,
				'pollq_expiry'      => '1500000000',
				'pollq_totalvoters' => 0,
			)
		);

		$wpdb->insert(
			$wpdb->pollsa,
			array(
				'polla_qid'     => $poll_id,
				'polla_answers' => 'Kept',
				'polla_votes'   => 3,
			)
		);
		$answer_id = (int) $wpdb->insert_id;

		// The log rows the varchar era actually wrote: ids and timestamps as
		// strings, and the empty string wherever nothing was recorded.
		for ( $i = 1; $i <= 3; $i++ ) {
			$wpdb->insert(
				$wpdb->pollsip,
				array(
					'pollip_qid'       => (string) $poll_id,
					'pollip_aid'       => (string) $answer_id,
					'pollip_ip'        => "198.51.100.$i",
					'pollip_host'      => '',
					'pollip_timestamp' => (string) ( 1000000000 + $i ),
					'pollip_user'      => '',
					'pollip_userid'    => 0,
				)
			);
		}

		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$qid_type = $wpdb->get_row( "DESCRIBE {$wpdb->pollsip} pollip_qid" )->Type;
		$this->assertStringStartsWith( 'int', $qid_type, 'pollip_qid was not converted from varchar' );

		$expiry_type = $wpdb->get_row( "DESCRIBE {$wpdb->pollsq} pollq_expiry" )->Type;
		$this->assertStringStartsWith( 'int', $expiry_type, 'pollq_expiry was not converted from varchar' );

		// The conversion succeeding is half the story; the values coming out
		// the other side as the same numbers is the other half.
		$this->assertSame(
			3,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d AND pollip_aid = %d", $poll_id, $answer_id ) ),
			'vote log rows lost their poll or answer id in the conversion'
		);
		$this->assertSame(
			array( '1000000001', '1000000002', '1000000003' ),
			$wpdb->get_col( "SELECT pollip_timestamp FROM {$wpdb->pollsip} ORDER BY pollip_id" ),
			'vote log timestamps were mangled in the conversion'
		);
		$this->assertSame(
			array( '0', '1500000000' ),
			$wpdb->get_col( "SELECT pollq_expiry FROM {$wpdb->pollsq} ORDER BY pollq_id" ),
			'the empty no-expiry value did not become 0'
		);

		$names = $this->pollsip_index_names();
		$this->assertContains( 'pollip_ip', $names, 'the single-column ip index was not added to the ancient table' );
		$this->assertContains( 'pollip_ip_qid_aid', $names, 'the covering index was not added to the ancient table' );
		$this->assertNotContains( 'pollip_ip_qid', $names, 'the ancient two-column index was left behind' );
	}

	public function test_pre_2_74_installs_get_totalvoters_backfilled() {
		global $wpdb;

		$this->create_2_77_3_tables();

		// Before 2.74 only pollq_totalvotes was maintained; totalvoters sat at
		// zero on every row. The backfill copies votes across -- the best
		// available guess -- but only when the whole column is zero, because a
		// single populated row means the install has been counting voters all
		// along and a zero elsewhere is a real zero.
		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'Ancient poll one',
				'pollq_timestamp'   => '900000000',
				'pollq_totalvotes'  => 7,
				'pollq_totalvoters' => 0,
			)
		);
		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'Ancient poll two',
				'pollq_timestamp'   => '900000001',
				'pollq_totalvotes'  => 4,
				'pollq_totalvoters' => 0,
			)
		);

		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$this->assertSame(
			array( '7', '4' ),
			$wpdb->get_col( "SELECT pollq_totalvoters FROM {$wpdb->pollsq} ORDER BY pollq_id" ),
			'totalvoters was not backfilled from totalvotes'
		);
	}

	public function test_an_install_already_counting_voters_is_not_backfilled() {
		global $wpdb;

		$this->create_2_77_3_tables();

		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'Counted poll',
				'pollq_timestamp'   => '1600000000',
				'pollq_totalvotes'  => 9,
				'pollq_totalvoters' => 6,
			)
		);
		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => 'Genuinely unvoted poll',
				'pollq_timestamp'   => '1600000001',
				'pollq_totalvotes'  => 0,
				'pollq_totalvoters' => 0,
			)
		);

		$this->forget_the_upgrade();

		WP_Polls_Install::install();

		$this->assertSame(
			array( '6', '0' ),
			$wpdb->get_col( "SELECT pollq_totalvoters FROM {$wpdb->pollsq} ORDER BY pollq_id" ),
			'the backfill overwrote voter counts on an install that had real ones'
		);
	}

	public function test_activation_is_idempotent_at_the_table_level() {
		global $wpdb;

		$this->create_2_77_3_tables();
		$this->populate_legacy_data();
		$this->forget_the_upgrade();

		WP_Polls_Install::install();
		$first = $wpdb->get_results( "SELECT * FROM {$wpdb->pollsq} ORDER BY pollq_id", ARRAY_A );

		// Users deactivate and reactivate plugins to "fix" things constantly.
		// The second activation meets a fully migrated install and must be a
		// bystander.
		WP_Polls_Install::install();
		$second = $wpdb->get_results( "SELECT * FROM {$wpdb->pollsq} ORDER BY pollq_id", ARRAY_A );

		$this->assertSame( $first, $second, 'a second activation changed poll rows' );
		$this->assertSame(
			array( 'PRIMARY', 'pollip_ip', 'pollip_ip_qid_aid', 'pollip_qid' ),
			$this->sorted_pollsip_indexes(),
			'a second activation changed the index set'
		);
	}

	/**
	 * The pollsip index names, sorted for a stable comparison.
	 *
	 * @return string[]
	 */
	private function sorted_pollsip_indexes() {
		$names = $this->pollsip_index_names();
		sort( $names );

		return $names;
	}
}
