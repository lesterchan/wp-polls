<?php
/**
 * Tests for uninstall.php.
 *
 * @package WP-Polls
 */

/**
 * The uninstall routine.
 *
 * Mostly source-level guards, deliberately. The bugs they cover only appear on
 * a multisite network larger than a hundred sites, which a single-site suite
 * cannot build. That uninstall actually removes every row is covered
 * behaviourally in test-metadata.php, which is also the only test that puts the
 * three tables back afterwards - dropping them is DDL, so it survives the
 * transaction the rest of the suite relies on.
 *
 * Each assertion below stands in for a failure that reports success while
 * silently leaving data behind.
 */
class WP_Polls_Uninstall_Test extends WP_Polls_TestCase {

	/**
	 * The uninstall source.
	 *
	 * @return string
	 */
	private function source() {
		return (string) file_get_contents( WP_POLLS_DIR . 'uninstall.php' );
	}

	/**
	 * The source of the class that does the per-site work.
	 *
	 * @return string
	 */
	private function install_source() {
		return (string) file_get_contents( WP_POLLS_DIR . 'includes/class-wp-polls-install.php' );
	}

	/**
	 * The get_sites() call is not left on its default cap of 100.
	 *
	 * WP_Site_Query's query_var_defaults sets 'number' to 100, so a bare
	 * get_sites() stops at the hundredth site and leaves the options and tables
	 * behind on every site after that. Nothing errors; uninstall just reports
	 * success.
	 *
	 * @return void
	 */
	public function test_get_sites_lifts_the_default_limit() {
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $this->source(), 'uninstall.php lifts the site query cap, or a network past the default is half-uninstalled.' );
	}

	/**
	 * The loop asks for IDs rather than hydrating a WP_Site per site.
	 *
	 * @return void
	 */
	public function test_get_sites_only_asks_for_ids() {
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $this->source(), 'uninstall.php asks for ids only, which is what makes the unlimited query affordable.' );
	}

	/**
	 * Each restore_current_blog() runs inside the loop, not once after it.
	 *
	 * Switching pushes onto a stack, so switching once per site and
	 * restoring once afterwards leaves the stack unwound by every site but the
	 * first. The regex allows no brace between the two calls, which is what
	 * fails if the restore is moved back outside the loop body.
	 *
	 * @return void
	 */
	public function test_each_switch_to_blog_is_restored_in_the_same_scope() {
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\(.*?\);[^{}]*restore_current_blog\(\);/s',
			$this->source(),
			'restore_current_blog() must sit inside the foreach, beside its switch_to_blog()'
		);
	}

	/**
	 * The tables are dropped once per site, not once per option row.
	 *
	 * The drop used to be called from inside the loop over option names, so it
	 * ran 36 times per site and issued three DROP TABLE statements each.
	 *
	 * @return void
	 */
	public function test_the_tables_are_not_dropped_once_per_option() {
		preg_match(
			'/foreach \( self::option_names\(\) as \$option_name \) \{(.*?)\}/s',
			$this->install_source(),
			$matches
		);

		$this->assertNotEmpty( $matches, 'could not find the loop over option names' );

		// Deleting the row is the only thing that belongs in here. Asserting on
		// the absence of "DROP TABLE" is not enough - the pre-3.0.0 body called a
		// helper that did the dropping, so the string never appeared in the loop.
		preg_match_all( '/([a-z_]+)\s*\(/i', $matches[1], $calls );

		$this->assertSame(
			array( 'delete_option' ),
			array_values( array_unique( $calls[1] ) ),
			'the loop over option names must only delete options'
		);
	}

	/**
	 * Every option row the plugin owns is on the uninstall list.
	 *
	 * The list is built from WP_Polls_Options so it cannot drift from the
	 * migration's idea of which rows belong to the plugin, and both current rows
	 * have to be on it.
	 *
	 * @return void
	 */
	public function test_every_row_the_plugin_owns_is_on_the_uninstall_list() {
		$names = WP_Polls_Install::option_names();

		$this->assertContains( WP_Polls_Options::OPTION, $names, 'the settings row' );
		$this->assertContains( WP_Polls_Options::VERSION, $names, 'the version markers' );

		foreach ( array_keys( WP_Polls_Options::legacy_map() ) as $legacy ) {
			$this->assertContains( $legacy, $names, $legacy . ' would be orphaned' );
		}

		foreach ( WP_Polls_Options::legacy_extra_rows() as $legacy ) {
			$this->assertContains( $legacy, $names, $legacy . ' would be orphaned' );
		}

		$this->assertContains( 'widget_polls-widget', $names, 'the widget instance row' );
	}

	/**
	 * And the row the plugin does not own stays off it.
	 *
	 * The stats_display row was shared with WP-Stats and five others, and up to
	 * six of them may not have upgraded yet and be reading it still. §13.2 splits
	 * the two jobs: the migration deletes the shared row because it has folded it
	 * in, and uninstall leaves it alone. Removing WP-Polls was reconfiguring the
	 * WP-Stats blocks of every sibling on the site, with nothing said anywhere.
	 *
	 * The assertion is deliberately the mirror of the one above rather than a
	 * second reading of the same list: the defect was that one list did both
	 * jobs, so a test that only walks that list cannot see it.
	 *
	 * @return void
	 */
	public function test_the_shared_stats_row_is_not_on_the_uninstall_list() {
		$names = WP_Polls_Install::option_names();

		foreach ( WP_Polls_Options::legacy_shared_rows() as $shared ) {
			$this->assertNotContains(
				$shared,
				$names,
				$shared . ' is read by six sibling plugins and is not this one\'s to delete.'
			);
		}
	}

	/**
	 * Deleting the plugin leaves the siblings' shared row where it was.
	 *
	 * The list above is the contract; this is the behaviour, run through the
	 * uninstaller itself so the two cannot drift.
	 *
	 * @return void
	 */
	public function test_uninstall_leaves_the_shared_stats_row_alone() {
		update_option(
			'stats_display',
			array(
				'polls'      => 1,
				'useronline' => 1,
			)
		);

		$this->run_uninstall();

		$this->assertSame(
			array(
				'polls'      => 1,
				'useronline' => 1,
			),
			get_option( 'stats_display' ),
			'Uninstalling WP-Polls must not touch a row its siblings are still reading.'
		);
	}

	/**
	 * The uninstall entry point declares no global function, so it cannot collide.
	 *
	 * Every plugin's uninstall.php is loaded into the same request when several
	 * are deleted at once, so an unprefixed global there is a fatal error
	 * waiting for a second plugin to do the same thing. The work is a class
	 * method now, and the entry point only loops.
	 *
	 * @return void
	 */
	public function test_uninstall_declares_no_global_function() {
		$this->assertDoesNotMatchRegularExpression( '/^\s*function\s+/m', $this->source(), 'uninstall.php declares a global function, which any other plugin could collide with.' );
		$this->assertStringContainsString( 'WP_Polls_Install::uninstall_site()', $this->source(), 'uninstall.php delegates rather than declaring a function of its own.' );
		$this->assertStringContainsString( 'public static function uninstall_site()', $this->install_source(), 'And the method it delegates to really exists.' );
	}
}
