<?php
/**
 * The consolidated option row and the 3.0.0 migration.
 *
 * Ported from the Playground harness used during the rewrite. The migration is
 * the highest risk code in the plugin: it runs once, unattended, against data
 * it did not create, and getting it wrong silently resets every setting.
 *
 * @package WP-Polls
 */

/**
 * The consolidated poll_options row: defaults, dot paths and migration.
 *
 * @covers Polls_Options
 */
class Test_Polls_Options extends WP_Polls_TestCase {

	/**
	 * Dot paths resolve through the nested array.
	 */
	public function test_get_reads_nested_paths() {
		$this->assertSame( 'polla_aid', Polls_Options::get( 'sort.answers_by' ) );
		$this->assertIsArray( Polls_Options::get( 'templates' ) );
		$this->assertNotEmpty( Polls_Options::get( 'templates.votefooter' ) );
	}

	/**
	 * An absent path returns the supplied default rather than null.
	 */
	public function test_get_returns_default_for_missing_path() {
		$this->assertSame( 'fallback', Polls_Options::get( 'nope.not.here', 'fallback' ) );
	}

	/**
	 * Writing one leaf with set() leaves its siblings alone.
	 */
	public function test_set_writes_one_leaf_only() {
		$before = Polls_Options::get( 'templates.votebody' );

		Polls_Options::set( 'templates.votefooter', 'CHANGED' );
		Polls_Options::flush();

		$this->assertSame( 'CHANGED', Polls_Options::get( 'templates.votefooter' ) );
		$this->assertSame( $before, Polls_Options::get( 'templates.votebody' ) );
	}

	/**
	 * Every setting lives in one row.
	 *
	 * The two version markers are deliberately separate: they record what state
	 * the install is in, and are read to decide whether the settings row and
	 * the schema need migrating, so they cannot live inside what is being
	 * migrated. Anything else appearing here means a setting escaped.
	 */
	public function test_settings_occupy_a_single_option_row() {
		global $wpdb;

		Polls_Options::set( 'archive.per_page', 7 );

		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'poll\_%' ORDER BY option_name"
		);

		$this->assertSame(
			array( 'poll_db_version', 'poll_options', 'poll_version' ),
			$rows
		);
	}

	/**
	 * The pre-3.0.0 rows are folded in, with their values intact.
	 */
	public function test_migration_carries_legacy_values_across() {
		delete_option( Polls_Options::OPTION );
		delete_option( 'poll_version' );
		Polls_Options::flush();

		update_option( 'poll_template_votefooter', '<ul>CUSTOM %POLL_ID%</ul>' );
		update_option( 'poll_ans_sortby', 'polla_votes' );
		update_option( 'poll_archive_perpage', 17 );
		update_option( 'poll_logging_method', 2 );
		update_option(
			'poll_bar',
			array(
				'style'      => 'aqua',
				'background' => 'ff0000',
				'border'     => '00ff00',
				'height'     => 12,
			)
		);
		update_option( 'poll_options', array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );

		Polls_Install::upgrade();
		Polls_Options::flush();

		$this->assertSame( '<ul>CUSTOM %POLL_ID%</ul>', Polls_Options::get( 'templates.votefooter' ) );
		$this->assertSame( 'polla_votes', Polls_Options::get( 'sort.answers_by' ) );
		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );
		$this->assertSame( 2, (int) Polls_Options::get( 'logging_method' ) );
		$this->assertSame( 'aqua', Polls_Options::get( 'bar.style' ) );
		$this->assertSame( 12, (int) Polls_Options::get( 'bar.height' ) );
		$this->assertSame( 'HTTP_X_FORWARDED_FOR', Polls_Options::get( 'ip_header' ) );
	}

	/**
	 * Keys the old install never set keep their defaults, not null.
	 */
	public function test_migration_leaves_untouched_keys_at_defaults() {
		delete_option( Polls_Options::OPTION );
		delete_option( 'poll_version' );
		Polls_Options::flush();
		update_option( 'poll_archive_perpage', 17 );

		Polls_Install::upgrade();
		Polls_Options::flush();

		$this->assertSame( 'polla_votes', Polls_Options::get( 'sort.results_by' ) );
		$this->assertNotEmpty( Polls_Options::get( 'templates.voteheader' ) );
	}

	/**
	 * The old rows are removed once folded in - except poll_options, which is
	 * the row they were folded into.
	 */
	public function test_migration_deletes_the_legacy_rows() {
		delete_option( Polls_Options::OPTION );
		delete_option( 'poll_version' );
		Polls_Options::flush();
		update_option( 'poll_archive_perpage', 17 );
		update_option( 'poll_ans_sortby', 'polla_votes' );

		Polls_Install::upgrade();

		$this->assertFalse( get_option( 'poll_archive_perpage', false ) );
		$this->assertFalse( get_option( 'poll_ans_sortby', false ) );
		$this->assertNotFalse( get_option( Polls_Options::OPTION, false ) );
	}

	/**
	 * Re-running the upgrade must not reset anything.
	 *
	 * The regression this guards: the migration used to seed from defaults(),
	 * so a second run with no legacy rows left to read wrote defaults straight
	 * over the migrated values.
	 */
	public function test_migration_is_idempotent() {
		delete_option( Polls_Options::OPTION );
		delete_option( 'poll_version' );
		Polls_Options::flush();
		update_option( 'poll_archive_perpage', 17 );

		Polls_Install::upgrade();
		Polls_Options::flush();

		Polls_Install::upgrade();
		Polls_Options::flush();
		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );

		// And with the version row missing, which is what a partial restore or
		// a downgrade-and-re-upgrade looks like.
		delete_option( 'poll_version' );
		Polls_Install::upgrade();
		Polls_Options::flush();
		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );
	}

	/**
	 * An install stamped 3.0.0 from the development branch still holds the
	 * scattered rows; a version-only gate would skip it.
	 */
	public function test_migration_handles_install_stamped_with_current_version() {
		delete_option( Polls_Options::OPTION );
		Polls_Options::flush();
		update_option( 'poll_version', WP_POLLS_VERSION );
		update_option( 'poll_archive_perpage', 23 );

		Polls_Install::upgrade();
		Polls_Options::flush();

		$this->assertSame( 23, (int) Polls_Options::get( 'archive.per_page' ) );
	}

	/**
	 * A corrupt row falls back to defaults rather than fataling.
	 */
	public function test_non_array_option_falls_back_to_defaults() {
		update_option( Polls_Options::OPTION, 'not an array' );
		Polls_Options::flush();

		$this->assertSame( 'polla_aid', Polls_Options::get( 'sort.answers_by' ) );
	}
}
