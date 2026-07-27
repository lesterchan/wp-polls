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
 * @covers Polls_Install::upgrade
 * @covers Polls_Options::migrate_from_legacy_rows
 */
class Test_Polls_Migration extends WP_Polls_TestCase {

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
		delete_option( Polls_Options::OPTION );
		delete_option( 'poll_version' );
		Polls_Options::flush();

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
		update_option(
			'poll_ajax_style',
			array(
				'loading' => 0,
				'fading'  => 0,
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
		Polls_Install::upgrade();
		Polls_Options::flush();
	}

	/**
	 * Every scalar setting survives the fold.
	 *
	 * @return void
	 */
	public function test_scalar_settings_are_carried_across() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', Polls_Options::get( 'templates.votefooter' ) );
		$this->assertSame( 'No polls, sorry.', Polls_Options::get( 'templates.disable' ) );
		$this->assertSame( 'polla_votes', Polls_Options::get( 'sort.answers_by' ) );
		$this->assertSame( 'desc', Polls_Options::get( 'sort.answers_order' ) );
		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );
		$this->assertSame( 3, (int) Polls_Options::get( 'archive.display_poll' ) );
		$this->assertSame( 3, (int) Polls_Options::get( 'close' ) );
		$this->assertSame( 2, (int) Polls_Options::get( 'logging_method' ) );
		$this->assertSame( 86400, (int) Polls_Options::get( 'cookie_expiry' ) );
		$this->assertSame( 1, (int) Polls_Options::get( 'allow_to_vote' ) );
		$this->assertSame( 2, (int) Polls_Options::get( 'current_poll' ) );
		$this->assertSame( 3, (int) Polls_Options::get( 'latest_poll' ) );
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
		$this->assertSame( 'gradient', Polls_Options::get( 'bar.style' ) );
		$this->assertSame( 'ff0000', Polls_Options::get( 'bar.background' ) );
		$this->assertSame( '00ff00', Polls_Options::get( 'bar.border' ) );
		$this->assertSame( 12, (int) Polls_Options::get( 'bar.height' ) );
		$this->assertSame( 0, (int) Polls_Options::get( 'ajax.loading' ) );
		$this->assertSame( 0, (int) Polls_Options::get( 'ajax.fading' ) );
	}

	/**
	 * The poll_options row already existed and is the one being migrated into.
	 *
	 * Pre-3.0.0 it held only { ip_header: ... }. Reusing the name means the
	 * migration has to read that value before it overwrites the row, and must
	 * never appear in the list of rows to delete afterwards.
	 *
	 * @return void
	 */
	public function test_ip_header_survives_reusing_the_same_row() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame( 'HTTP_X_FORWARDED_FOR', Polls_Options::get( 'ip_header' ) );
	}

	/**
	 * A key nobody set keeps its default rather than becoming null.
	 *
	 * @return void
	 */
	public function test_untouched_keys_keep_their_defaults() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$defaults = Polls_Options::defaults();
		$this->assertSame( $defaults['sort']['results_by'], Polls_Options::get( 'sort.results_by' ) );
		$this->assertNotNull( Polls_Options::get( 'templates.voteheader' ) );
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
			array_keys( Polls_Options::legacy_map() ),
			Polls_Options::legacy_extra_rows()
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
	 * The consolidated row is never itself deleted as a legacy row.
	 *
	 * @return void
	 */
	public function test_the_consolidated_row_is_not_in_the_delete_list() {
		$this->assertNotContains( Polls_Options::OPTION, Polls_Options::legacy_extra_rows() );
		$this->assertArrayNotHasKey( Polls_Options::OPTION, Polls_Options::legacy_map() );
	}

	/**
	 * The version is stamped so the upgrade does not run forever.
	 *
	 * @return void
	 */
	public function test_version_is_recorded() {
		$this->make_legacy_install();
		$this->run_upgrade();

		$this->assertSame( WP_POLLS_VERSION, get_option( 'poll_version' ) );
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

		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', Polls_Options::get( 'templates.votefooter' ) );
		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );
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

		delete_option( 'poll_version' );
		$this->run_upgrade();

		$this->assertSame( 17, (int) Polls_Options::get( 'archive.per_page' ) );
		$this->assertSame( '<ul>CUSTOM FOOTER %POLL_ID%</ul>', Polls_Options::get( 'templates.votefooter' ) );
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

		delete_option( Polls_Options::OPTION );
		update_option( 'poll_version', '3.0.0' );
		update_option( 'poll_archive_perpage', 23 );
		update_option( 'poll_template_disable', 'DEV BRANCH TEXT' );
		Polls_Options::flush();

		$this->run_upgrade();

		$this->assertSame( 23, (int) Polls_Options::get( 'archive.per_page' ) );
		$this->assertSame( 'DEV BRANCH TEXT', Polls_Options::get( 'templates.disable' ) );
	}

	/**
	 * A fully migrated install is left alone.
	 *
	 * @return void
	 */
	public function test_already_migrated_install_is_untouched() {
		Polls_Options::set( 'archive.per_page', 42 );
		update_option( 'poll_version', WP_POLLS_VERSION );

		$this->run_upgrade();

		$this->assertSame( 42, (int) Polls_Options::get( 'archive.per_page' ) );
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

		$footer = Polls_Options::get( 'templates.votefooter' );

		$this->assertStringNotContainsString( 'onclick', $footer );
		$this->assertStringContainsString( 'data-poll-id="%POLL_ID%"', $footer );
		$this->assertStringContainsString( 'data-poll-action="result"', $footer );
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

		$this->assertSame( $custom, Polls_Options::get( 'templates.votefooter' ) );
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

		$body = Polls_Options::get( 'templates.resultbody' );

		$this->assertStringNotContainsString( 'class="pollbar"', $body );
		$this->assertStringContainsString( 'class="wp-polls-bar"', $body );
		$this->assertStringContainsString( 'class="wp-polls-bar-fill"', $body );
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

		$body = Polls_Options::get( 'templates.resultbody' );

		$this->assertStringNotContainsString( 'MY OWN MARKUP', $body );
		$this->assertSame( Polls_Templates::get_default( 'resultbody' ), $body );
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

		$this->assertSame( $expected, Polls_Options::get( 'bar.style' ) );
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
		Polls_Options::set( 'templates.resultbody', '<li>EDITED AFTERWARDS</li>' );

		$this->run_upgrade();

		$this->assertSame( '<li>EDITED AFTERWARDS</li>', Polls_Options::get( 'templates.resultbody' ) );
	}
}
