<?php
/**
 * Tests for uninstall.php.
 *
 * @package WP-Polls
 */

/**
 * The uninstall routine.
 *
 * These are source-level guards rather than behavioural tests, deliberately.
 * The bugs they cover only appear on a multisite network larger than a hundred
 * sites, which a single-site suite cannot build; and uninstall.php drops the
 * three poll tables, which is DDL, so it cannot be rolled back by the
 * transaction the test case runs in - actually invoking it would take the rest
 * of the suite down with it.
 *
 * Asserting on the source is what is left. Each assertion below stands in for a
 * failure that reports success while silently leaving data behind.
 */
class Test_Polls_Uninstall extends WP_Polls_TestCase {

	/**
	 * The uninstall source.
	 *
	 * @return string
	 */
	private function source() {
		return (string) file_get_contents( WP_POLLS_DIR . 'uninstall.php' );
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
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $this->source() );
	}

	/**
	 * The loop asks for IDs rather than hydrating a WP_Site per site.
	 *
	 * @return void
	 */
	public function test_get_sites_only_asks_for_ids() {
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $this->source() );
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
			'/foreach \( \$option_names as \$option_name \) \{(.*?)\}/s',
			$this->source(),
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
	 * The list is built from Polls_Options so it cannot drift from the
	 * migration's idea of which rows belong to the plugin, and the consolidated
	 * row itself has to be on it.
	 *
	 * @return void
	 */
	public function test_the_consolidated_row_is_removed() {
		$source = $this->source();

		$this->assertStringContainsString( 'Polls_Options::OPTION', $source );
		$this->assertStringContainsString( 'Polls_Options::legacy_map()', $source );
		$this->assertStringContainsString( 'Polls_Install::DB_VERSION_OPTION', $source );
		$this->assertStringContainsString( "'poll_version'", $source );
	}

	/**
	 * The helper is prefixed, so it cannot collide during uninstall.
	 *
	 * @return void
	 */
	public function test_the_helper_is_namespaced_to_the_plugin() {
		$source = $this->source();

		$this->assertStringContainsString( 'function wp_polls_uninstall_site(', $source );
		$this->assertStringNotContainsString( 'function plugin_uninstalled(', $source );
	}
}
