<?php
/**
 * Tests for the option consolidation an upgrading install goes through.
 *
 * @package WP-Polls
 */

/**
 * The 2.x -> 3.0.0 option consolidation.
 *
 * This is the upgrade path every existing install takes, and until now its only
 * coverage lived in a throwaway Playground harness that never ran in CI.
 *
 * Each test rebuilds a pre-3.0.0 install from scratch -- scattered legacy rows,
 * no consolidated row, no version -- then runs the upgrade and checks the data
 * survived. The fixture values are deliberately *not* the defaults: a migration
 * that quietly wrote defaults instead of carrying values across would look
 * correct against a default-valued fixture.
 *
 * @covers WP_Polls_Install::upgrade
 * @covers WP_Polls_Options::migrate_from_legacy_rows
 */
class WP_Polls_Migration_Test extends WP_Polls_TestCase {

	/**
	 * A 3.0.0 install's worth of scattered rows, none of them default.
	 *
	 * @var array
	 */
	private $legacy = array(
		'poll_template_votefooter' => '<ul>CUSTOM FOOTER %POLL_ID%</ul>',
		'poll_template_disable'    => 'No polls, sorry.',
		'poll_ans_sortby'          => 'polla_votes',
		'poll_ans_sortorder'       => 'desc',
		'poll_archive_perpage'     => 17,
		'poll_archive_displaypoll' => 3,
		'poll_close'               => 3,
		'poll_logging_method'      => 2,
		'poll_cookielog_expiry'    => 86400,
		'poll_allowtovote'         => 1,
		'poll_currentpoll'         => 2,
		'poll_latestpoll'          => 3,
		'poll_archive_show'        => 1,
	);

	/**
	 * Put the site back into its pre-3.0.0 shape.
	 *
	 * @return void
	 */
	private function make_legacy_install() {
		delete_option( WP_Polls_Options::OPTION );
		delete_option( WP_Polls_Options::VERSION );
		WP_Polls_Options::flush();

		foreach ( $this->legacy as $name => $value ) {
			update_option( $name, $value );
		}

		update_option(
			'poll_bar',
			array(
				'style'      => 'default_gradient',
				'background' => 'ff0000',
				'border'     => '00ff00',
				'height'     => 12,
			)
		);
		update_option( 'poll_options', array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );
	}

	/**
	 * Run the upgrade the way admin_init does.
	 *
	 * @return void
	 */
	private function run_upgrade() {
		WP_Polls_Install::upgrade();
		WP_Polls_Options::flush();
	}

	/**
	 * A pre-3.0.0 install whose every setting is the one the plugin ships.
	 *
	 * The fixture at the top of this file is deliberately all-customised, and
	 * that policy is right for "did the values carry across" -- but it cannot
	 * see §7.6.1, because a result that differs from the defaults is written
	 * whatever the read before it did. This is the other fixture, and the
	 * commonest install there is: somebody who never opened the settings screen.
	 *
	 * Built from legacy_map() rather than typed out, so a row added to the map
	 * is in this fixture too instead of quietly falling outside it.
	 *
	 * @return void
	 */
	private function make_stock_legacy_install() {
		delete_option( WP_Polls_Options::OPTION );
		delete_option( WP_Polls_Options::VERSION );
		WP_Polls_Options::flush();

		// With no stored row and the cache dropped, get() answers with the
		// shipped default for each path -- which is what makes each legacy row
		// below stock rather than merely plausible.
		foreach ( WP_Polls_Options::legacy_map() as $legacy => $path ) {
			update_option( $legacy, WP_Polls_Options::get( $path ) );
		}

		WP_Polls_Options::flush();
	}

	/**
	 * Run the upgrade on the far side of register_setting(), as admin_init does.
	 *
	 * WP_Polls_Install::init() and WP_Polls_Settings::init() both hook
	 * admin_init at priority 10, so which of them runs first is decided by the
	 * order of two adjacent lines in wp-polls.php. Registering first is the
	 * harder ordering and the one this plugin does *not* currently get, so it is
	 * the one worth pinning: it is the only arrangement under which the
	 * default_option_wp_polls_options filter is live while the migration writes.
	 *
	 * @return void
	 */
	private function run_upgrade_on_admin_init() {
		WP_Polls_Settings::register();

		$this->run_upgrade();
	}

	/**
	 * A stock install comes out of the migration with a row, not without one.
	 *
	 * This is the §7.6.1 shape: a migration whose result equals the shipped
	 * defaults, run with register_setting()'s `default` in force so that an
	 * absent row reads back as those same defaults. WP-Polls survives it, and
	 * the reason is worth pinning rather than trusting -- core's update_option()
	 * falls back to add_option() when the default_option filter is what
	 * answered, and WP_Polls_Options::save() now takes that decision itself
	 * instead of relying on it.
	 *
	 * Asserted on the raw row rather than through get(), which merges over the
	 * defaults and so cannot tell a write that happened from one that did not.
	 *
	 * @return void
	 */
	public function test_a_stock_install_still_gets_its_row_written() {
		$this->make_stock_legacy_install();

		$this->assertFalse( get_option( WP_Polls_Options::OPTION, false ), 'The fixture is only pre-migration if the consolidated row is genuinely absent.' );

		$this->run_upgrade_on_admin_init();

		$this->assertIsArray( get_option( WP_Polls_Options::OPTION, false ), 'The migration must write the consolidated row even when its result equals the shipped defaults.' );
	}

	/**
	 * And the legacy rows it deleted are not deleted for nothing.
	 *
	 * The row existing is half the claim; the other half is that the settings it
	 * holds are the ones the plugin then acts on, rather than the defaults it
	 * happens to resemble.
	 *
	 * @return void
	 */
	public function test_a_stock_install_keeps_its_settings_through_the_migration() {
		$this->make_stock_legacy_install();
		$this->run_upgrade_on_admin_init();

		$stored = get_option( WP_Polls_Options::OPTION, false );

		$this->assertIsArray( $stored, 'The migration must write the consolidated row.' );
		$this->assertArrayHasKey( 'templates', $stored, 'The written row is the whole nested structure, not a fragment of it.' );
		$this->assertSame( WP_Polls_Options::defaults()['check_method'], WP_Polls_Options::get( 'check_method' ), 'A stock setting survives the fold rather than being lost with the legacy row.' );

		foreach ( array_keys( WP_Polls_Options::legacy_map() ) as $legacy ) {
			$this->assertFalse( get_option( $legacy, false ), sprintf( 'The legacy row %s must not survive the migration.', $legacy ) );
		}
	}

	/**
	 * Every scalar setting survives the fold.
	 *
	 * @return void
	 */
	public function test_scalar_settings_are_carried_across() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', WP_Polls_Options::get( 'templates.votefooter' ), 'The customised footer template carries across.' );
		$this->assertSame( 'No polls, sorry.', WP_Polls_Options::get( 'templates.disable' ), 'The disabled message.' );
		$this->assertSame( 'polla_votes', WP_Polls_Options::get( 'sort.answers_by' ), 'The answer sort column.' );
		$this->assertSame( 'desc', WP_Polls_Options::get( 'sort.answers_order' ), 'The answer sort direction.' );
		$this->assertSame( 17, (int) WP_Polls_Options::get( 'archive.per_page' ), 'The archive page size.' );
		$this->assertSame( 3, (int) WP_Polls_Options::get( 'archive.display_poll' ), 'The archive display type.' );
		$this->assertSame( 3, (int) WP_Polls_Options::get( 'close' ), 'The close setting.' );
		$this->assertSame( 2, (int) WP_Polls_Options::get( 'check_method' ), 'The check method.' );
		$this->assertSame( 86400, (int) WP_Polls_Options::get( 'cookie_expiry' ), 'The cookie expiry.' );
		$this->assertSame( 1, (int) WP_Polls_Options::get( 'allow_to_vote' ), 'The permission setting.' );
		$this->assertSame( 2, (int) WP_Polls_Options::get( 'current_poll' ), 'The current poll.' );
		$this->assertSame( 3, (int) WP_Polls_Options::get( 'latest_poll' ), 'And the latest poll.' );
	}

	/**
	 * The rows that already held arrays are folded in too.
	 *
	 * @return void
	 */
	public function test_array_settings_are_carried_across() {
		$this->make_legacy_install();
		$this->run_upgrade();

		// The fold carries poll_bar across intact and the bar upgrade then maps
		// the style: images/default_gradient is gone, and it shaded light to
		// dark, so it lands on the CSS gradient. The colours are untouched.
		$this->assertSame( 'gradient', WP_Polls_Options::get( 'bar.style' ), 'The bar style carries across.' );
		$this->assertSame( 'ff0000', WP_Polls_Options::get( 'bar.background' ), 'The background colour.' );
		$this->assertSame( '00ff00', WP_Polls_Options::get( 'bar.border' ), 'The border colour.' );
		$this->assertSame( 12, (int) WP_Polls_Options::get( 'bar.height' ), 'The bar height.' );
		$this->assertSame( 0, (int) WP_Polls_Options::get( 'ajax.loading' ), 'The loading toggle.' );
		$this->assertSame( 0, (int) WP_Polls_Options::get( 'ajax.fading' ), 'And the fading toggle.' );
	}

	/**
	 * The old poll_options row is read before it is deleted.
	 *
	 * Pre-3.0.0 it held only { ip_header: ... }, and the unreleased 3.0.0 held
	 * the whole nested array. Both are folded into wp_polls_options before the
	 * old name is removed.
	 *
	 * @return void
	 */
	public function test_ip_header_survives_the_move_to_the_new_row() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame( 'HTTP_X_FORWARDED_FOR', WP_Polls_Options::get( 'ip_header' ), 'The header setting survives the move to the new row.' );
	}

	/**
	 * A key nobody set keeps its default rather than becoming null.
	 *
	 * @return void
	 */
	public function test_untouched_keys_keep_their_defaults() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$defaults = WP_Polls_Options::defaults();
		$this->assertSame( $defaults['sort']['results_by'], WP_Polls_Options::get( 'sort.results_by' ), 'A key the legacy install never set keeps its default.' );
		$this->assertNotNull( WP_Polls_Options::get( 'templates.voteheader' ), 'A key the migration did not touch keeps its shipped default.' );
	}

	/**
	 * Every legacy row is removed once it has been folded in.
	 *
	 * @return void
	 */
	public function test_legacy_rows_are_deleted() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$names = array_merge(
			array_keys( WP_Polls_Options::legacy_map() ),
			WP_Polls_Options::legacy_extra_rows()
		);

		$leftover = array();
		foreach ( $names as $name ) {
			if ( false !== get_option( $name, false ) ) {
				$leftover[] = $name;
			}
		}

		$this->assertSame( array(), $leftover, 'rows left behind: ' . implode( ', ', $leftover ) );
	}

	/**
	 * Neither of the two rows WP-Polls keeps is on the delete list.
	 *
	 * @return void
	 */
	public function test_the_current_rows_are_not_in_the_delete_list() {
		$this->assertNotContains( WP_Polls_Options::OPTION, WP_Polls_Options::legacy_extra_rows(), 'The options row is not on the list of rows to delete.' );
		$this->assertNotContains( WP_Polls_Options::VERSION, WP_Polls_Options::legacy_extra_rows(), 'Nor the version row, which would take the install with it.' );
		$this->assertArrayNotHasKey( WP_Polls_Options::OPTION, WP_Polls_Options::legacy_map(), 'The current settings row is on the delete list, so migrating would destroy it.' );
	}

	/**
	 * Both markers are stamped so the upgrade does not run forever.
	 *
	 * @return void
	 */
	public function test_both_version_markers_are_recorded() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_POLLS_VERSION,
				'db'     => WP_POLLS_DB_VERSION,
			),
			get_option( WP_Polls_Options::VERSION ),
			'The marker row holds exactly the plugin and schema versions.'
		);
	}

	/**
	 * The WP-Stats toggle is taken out of the shared row and the row removed.
	 *
	 * Up to 3.0.0 seven plugins wrote their toggle into one unprefixed
	 * stats_display row. Each of them owns its own copy now.
	 *
	 * @return void
	 */
	public function test_the_shared_stats_display_row_is_folded_in_and_removed() {
		$this->make_legacy_install();
		update_option( 'stats_display', array( 'polls' => 0 ) );

		$this->run_upgrade();

		$this->assertFalse( WP_Polls_Options::get( 'stats_display' ), 'The opt-out carried across.' );
		$this->assertFalse( get_option( 'stats_display', false ), 'The shared row is gone.' );
	}

	/**
	 * The older list shape of the shared row is read too.
	 *
	 * @return void
	 */
	public function test_the_shared_stats_display_row_is_read_as_a_list_as_well() {
		$this->make_legacy_install();
		update_option( 'stats_display', array( 'email', 'polls' ) );

		$this->run_upgrade();

		$this->assertTrue( WP_Polls_Options::get( 'stats_display' ), 'The shared stats_display row is read when it holds a list, not only a scalar.' );
	}

	/**
	 * A shared row that some other plugin already deleted leaves the block on.
	 *
	 * All seven migrations delete that row, so only whichever plugin the site
	 * upgrades first ever sees it. Reading its absence as a deliberate opt-out
	 * would make the polls block disappear from WP-Stats, silently, on every
	 * site that updated WP-Stats before WP-Polls.
	 *
	 * @return void
	 */
	public function test_a_missing_shared_stats_display_row_is_not_an_opt_out() {
		$this->make_legacy_install();
		delete_option( 'stats_display' );

		$this->run_upgrade();

		$this->assertTrue( WP_Polls_Options::get( 'stats_display' ), 'Absent means already migrated, not off.' );
		$this->assertArrayHasKey( 'wp_polls', WP_Polls_WPStats::register_section( array() ), 'A missing shared row is not an opt out; the section is still offered.' );
	}

	/**
	 * Running the upgrade twice must not reset anything.
	 *
	 * @return void
	 */
	public function test_upgrade_is_idempotent() {
		$this->make_legacy_install();
		$this->run_upgrade();
		$this->run_upgrade();

		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', WP_Polls_Options::get( 'templates.votefooter' ), 'A second upgrade leaves the template where the first put it.' );
		$this->assertSame( 17, (int) WP_Polls_Options::get( 'archive.per_page' ), 'And the page size.' );
	}

	/**
	 * Re-running with the version cleared and no legacy rows left keeps settings.
	 *
	 * This is the shape that wrote defaults over a migrated install during
	 * development: the migration found no old keys and seeded from defaults
	 * rather than from what was already there.
	 *
	 * @return void
	 */
	public function test_rerun_with_no_legacy_rows_keeps_settings() {
		$this->make_legacy_install();
		$this->run_upgrade();

		delete_option( WP_Polls_Options::VERSION );
		$this->run_upgrade();

		$this->assertSame( 17, (int) WP_Polls_Options::get( 'archive.per_page' ), 'A rerun with no legacy rows left keeps the page size.' );
		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', WP_Polls_Options::get( 'templates.votefooter' ), 'And the template.' );
	}

	/**
	 * An install stamped 3.0.0 that still holds scattered rows is migrated.
	 *
	 * 3.0.0 sat unreleased on the development branch long enough that installs
	 * exist carrying the version and the old rows. A version-only gate would
	 * skip them and drop the site to defaults, which is why upgrade() checks the
	 * stored shape as well.
	 *
	 * @return void
	 */
	public function test_install_stamped_3_0_0_but_unmigrated_is_still_folded_in() {
		$this->make_legacy_install();

		delete_option( WP_Polls_Options::OPTION );
		WP_Polls_Options::save_markers( '3.0.0', WP_POLLS_DB_VERSION );
		update_option( 'poll_archive_perpage', 23 );
		update_option( 'poll_template_disable', 'DEV BRANCH TEXT' );
		WP_Polls_Options::flush();

		$this->run_upgrade();

		$this->assertSame( 23, (int) WP_Polls_Options::get( 'archive.per_page' ), 'An install stamped with the new version but never migrated is still folded in.' );
		$this->assertSame( 'DEV BRANCH TEXT', WP_Polls_Options::get( 'templates.disable' ), 'Templates and all.' );
	}

	/**
	 * A fully migrated install is left alone.
	 *
	 * @return void
	 */
	public function test_already_migrated_install_is_untouched() {
		WP_Polls_Options::set( 'archive.per_page', 42 );
		WP_Polls_Options::save_markers( WP_POLLS_VERSION, WP_POLLS_DB_VERSION );

		$this->run_upgrade();

		$this->assertSame( 42, (int) WP_Polls_Options::get( 'archive.per_page' ), 'An install that has already migrated is left as it stands.' );
	}

	/**
	 * Inline onclick handlers in stored templates are converted.
	 *
	 * @return void
	 */
	public function test_onclick_templates_are_converted_on_upgrade() {
		$this->make_legacy_install();
		update_option(
			'poll_template_votefooter',
			'<a href="#" onclick="poll_result(%POLL_ID%); return false;">View Results</a>'
		);

		$this->run_upgrade();

		$footer = WP_Polls_Options::get( 'templates.votefooter' );

		$this->assertStringNotContainsString( 'onclick', $footer, 'The upgrade takes the click handler out of the template.' );
		$this->assertStringContainsString( 'data-poll-id="%POLL_ID%"', $footer, 'Replacing it with the poll as data.' );
		$this->assertStringContainsString( 'data-poll-action="result"', $footer, 'And the action as data.' );
	}

	/**
	 * A template with no onclick is left exactly as it was.
	 *
	 * @return void
	 */
	public function test_templates_without_onclick_are_untouched() {
		$this->make_legacy_install();
		$custom = '<a href="#" data-poll-id="%POLL_ID%" data-poll-action="result">Results</a>';
		update_option( 'poll_template_votefooter', $custom );

		$this->run_upgrade();

		$this->assertSame( $custom, WP_Polls_Options::get( 'templates.votefooter' ), 'A template with no click handler is left exactly as it was.' );
	}

	// --- the poll bar -----------------------------------------------------

	/**
	 * The result templates are replaced with the new bar markup on upgrade.
	 *
	 * @return void
	 */
	public function test_result_templates_are_moved_onto_the_new_bar() {
		$this->make_legacy_install();
		update_option(
			'poll_template_resultbody',
			'<li>%POLL_ANSWER%<div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;"></div></li>'
		);

		$this->run_upgrade();

		$body = WP_Polls_Options::get( 'templates.resultbody' );

		$this->assertStringNotContainsString( 'class="pollbar"', $body, 'The result template loses the class it used to draw the bar with.' );
		$this->assertStringContainsString( 'class="wp-polls-bar"', $body, 'Gaining the track.' );
		$this->assertStringContainsString( 'class="wp-polls-bar-fill"', $body, 'And the fill inside it.' );
	}

	/**
	 * A customised result template is replaced too, not preserved.
	 *
	 * This is the deliberate breaking half of the change and the one users have
	 * to act on, so it is pinned: the bar markup, its class names and the
	 * stylesheet all moved together, and a customised copy of the old template
	 * would render a bar with no rules left to match it.
	 *
	 * @return void
	 */
	public function test_a_customised_result_template_is_overwritten() {
		$this->make_legacy_install();
		update_option(
			'poll_template_resultbody',
			'<li>MY OWN MARKUP %POLL_ANSWER% <div class="pollbar"></div></li>'
		);

		$this->run_upgrade();

		$body = WP_Polls_Options::get( 'templates.resultbody' );

		$this->assertStringNotContainsString( 'MY OWN MARKUP', $body, 'A customised result template is not kept.' );
		$this->assertSame( WP_Polls_Template::get_default( 'resultbody' ), $body, 'It is replaced with the new default, because the old markup cannot draw the new bar.' );
	}

	/**
	 * Every retired bar style maps onto one of the two that are left.
	 *
	 * @dataProvider data_legacy_bar_styles
	 *
	 * @param string $stored   The style a pre-3.0.0 install holds.
	 * @param string $expected What it should become.
	 *
	 * @return void
	 */
	public function test_legacy_bar_styles_are_mapped( $stored, $expected ) {
		$this->make_legacy_install();
		update_option(
			'poll_bar',
			array(
				'style'      => $stored,
				'background' => 'ff0000',
				'border'     => '00ff00',
				'height'     => 12,
			)
		);

		$this->run_upgrade();

		$this->assertSame( $expected, WP_Polls_Options::get( 'bar.style' ), 'A legacy bar style maps onto the style that replaced it.' );
	}

	/**
	 * The retired styles and what each becomes.
	 *
	 * @return array
	 */
	public function data_legacy_bar_styles() {
		return array(
			// 'use_css' was the flat fill with no image over it.
			'the CSS sentinel'     => array( 'use_css', 'flat' ),
			// Both shipped tiles shaded light to dark.
			'the default tile'     => array( 'default', 'gradient' ),
			'the gradient tile'    => array( 'default_gradient', 'gradient' ),
			// A third-party images/ directory that no longer resolves.
			'an unknown directory' => array( 'some_theme_bar', 'gradient' ),
		);
	}

	/**
	 * An install already on the new bar is left alone.
	 *
	 * The upgrade replaces the result templates outright, so it must not run a
	 * second time on an install that has already been through it - that would
	 * discard customisations made after upgrading, every time wp-admin loaded.
	 *
	 * @return void
	 */
	public function test_the_bar_upgrade_does_not_run_twice() {
		$this->make_legacy_install();
		$this->run_upgrade();

		// Customise the result template the way a user would, post-upgrade.
		WP_Polls_Options::set( 'templates.resultbody', '<li>EDITED AFTERWARDS</li>' );

		$this->run_upgrade();

		$this->assertSame( '<li>EDITED AFTERWARDS</li>', WP_Polls_Options::get( 'templates.resultbody' ), 'The bar upgrade does not run again over a template edited since.' );
	}
	/**
	 * A 3.0.0 beta row carrying the old key names is brought forward.
	 *
	 * The released row is poll_logging_method, which the legacy map folds
	 * straight into check_method. This is the other shape: a consolidated array
	 * written by a beta, holding the old name inside it. Left behind, the site
	 * would silently fall back to checking by cookie and IP -- and would keep an
	 * 'ajax' key nothing reads.
	 */
	public function test_a_beta_row_is_brought_forward() {
		update_option(
			WP_Polls_Options::LEGACY_OPTION,
			array(
				'logging_method' => 4,
				'ajax'           => array(
					'loading' => 0,
					'fading'  => 0,
				),
			)
		);

		WP_Polls_Options::migrate_from_legacy_rows();
		WP_Polls_Options::flush();

		$this->assertSame( 4, (int) WP_Polls_Options::get( 'check_method' ), 'the beta key was not carried into check_method' );

		$all = WP_Polls_Options::all();

		$this->assertArrayNotHasKey( 'logging_method', $all, 'the old key survived the migration' );
		$this->assertArrayNotHasKey( 'ajax', $all, 'the AJAX style key survived the migration' );
	}
}
