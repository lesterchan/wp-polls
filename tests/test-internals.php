<?php
/**
 * Tests for the vote lock, activation, asset output and the small helpers.
 *
 * @package WP-Polls
 */

/**
 * The last entry points without coverage of their own.
 *
 * @covers Polls_Vote::polls_acquire_lock
 * @covers Polls_Vote::polls_release_lock
 * @covers Polls_Vote::polls_lock_file
 * @covers Polls_Install::activation
 * @covers Polls_Core::poll_scripts
 */
class Test_Polls_Internals extends WP_Polls_TestCase {

	/**
	 * Set up.
	 *
	 * The script and style registries are globals that live for the whole
	 * process, so wp_add_inline_style() and wp_localize_script() append across
	 * tests. Without rebuilding them an assertion here can be satisfied by
	 * output another test produced.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Rebuilding the registries is the point.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( glob( get_temp_dir() . '/wp-blog-*-wp-polls-*.lock' ) as $stale ) {
			wp_delete_file( $stale );
		}
		parent::tear_down();
	}

	// --- the vote lock ----------------------------------------------------

	/**
	 * The lock file is named per site and per poll.
	 *
	 * Two polls sharing one lock would serialise unrelated votes, and two sites
	 * sharing one would do it across a whole network.
	 *
	 * @return void
	 */
	public function test_lock_files_are_named_per_site_and_poll() {
		$first  = Polls_Vote::polls_lock_file( 1 );
		$second = Polls_Vote::polls_lock_file( 2 );

		$this->assertNotSame( $first, $second );
		$this->assertStringContainsString( 'wp-blog-' . get_current_blog_id() . '-', $first );
		$this->assertStringContainsString( '-wp-polls-1.lock', $first );
		$this->assertStringStartsWith( get_temp_dir(), $first );
	}

	/**
	 * The lock path is filterable.
	 *
	 * @return void
	 */
	public function test_lock_file_is_filterable() {
		add_filter(
			'wp_polls_lock_file',
			static function ( $path, $poll_id ) {
				unset( $path );
				return '/tmp/custom-' . $poll_id . '.lock';
			},
			10,
			2
		);

		$this->assertSame( '/tmp/custom-9.lock', Polls_Vote::polls_lock_file( 9 ) );
	}

	/**
	 * Acquiring the lock creates the file and hands back a handle.
	 *
	 * @return void
	 */
	public function test_acquiring_the_lock_creates_the_file() {
		$handle = Polls_Vote::polls_acquire_lock( 1 );

		$this->assertIsResource( $handle );
		$this->assertFileExists( Polls_Vote::polls_lock_file( 1 ) );

		Polls_Vote::polls_release_lock( $handle, 1 );
	}

	/**
	 * Releasing the lock closes the handle and removes the file.
	 *
	 * A lock file left behind is harmless, but one left *locked* would refuse
	 * every later vote on that poll for the life of the process.
	 *
	 * @return void
	 */
	public function test_releasing_the_lock_removes_the_file() {
		$handle = Polls_Vote::polls_acquire_lock( 1 );
		$path   = Polls_Vote::polls_lock_file( 1 );

		$this->assertTrue( Polls_Vote::polls_release_lock( $handle, 1 ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Releasing something that is not a handle reports failure.
	 *
	 * @return void
	 */
	public function test_releasing_a_non_handle_is_refused() {
		$this->assertFalse( Polls_Vote::polls_release_lock( false, 1 ) );
		$this->assertFalse( Polls_Vote::polls_release_lock( null, 1 ) );
	}

	/**
	 * A second holder of the same lock is turned away, not blocked.
	 *
	 * This is the whole point of the mutex: the second concurrent vote on a
	 * poll has to fail fast rather than wait, so LOCK_NB has to stay. Opening
	 * the file again in the same process shares the lock, so the contending
	 * attempt is made against a separate handle.
	 *
	 * @return void
	 */
	public function test_a_contending_lock_fails_fast() {
		$path = Polls_Vote::polls_lock_file( 1 );

		$holder = fopen( $path, 'w+' );
		$this->assertTrue( flock( $holder, LOCK_EX | LOCK_NB ) );

		$contender = fopen( $path, 'w+' );
		$this->assertFalse( flock( $contender, LOCK_EX | LOCK_NB ), 'the lock was not exclusive' );

		flock( $holder, LOCK_UN );
		fclose( $holder );
		fclose( $contender );
	}

	/**
	 * The lock is taken and given back around a real vote.
	 *
	 * @return void
	 */
	public function test_voting_leaves_no_lock_behind() {
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Options::set( 'allow_to_vote', 2 );
		Polls_Options::set( 'logging_method', 0 );

		Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->assertFileDoesNotExist( Polls_Vote::polls_lock_file( $poll_id ) );
	}

	/**
	 * A vote that cannot take the lock is refused rather than double counted.
	 *
	 * @return void
	 */
	public function test_a_vote_that_cannot_lock_is_refused() {
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		Polls_Options::set( 'allow_to_vote', 2 );
		Polls_Options::set( 'logging_method', 0 );

		$holder = fopen( Polls_Vote::polls_lock_file( $poll_id ), 'w+' );
		flock( $holder, LOCK_EX | LOCK_NB );

		try {
			Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
			$this->fail( 'the vote was not refused' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( (string) $poll_id, $e->getMessage() );
		} finally {
			flock( $holder, LOCK_UN );
			fclose( $holder );
		}

		global $wpdb;
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	// --- activation -------------------------------------------------------

	/**
	 * Single site activation creates the tables and the capability.
	 *
	 * @return void
	 */
	public function test_activation_on_a_single_site() {
		global $wpdb;

		get_role( 'administrator' )->remove_cap( 'manage_polls' );

		Polls_Install::activation( false );

		foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $table ) {
			$name = $wpdb->$table;
			$this->assertSame(
				$name,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ),
				$table . ' was not created'
			);
		}

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ) );
	}

	/**
	 * Network activation walks every site instead of fataling.
	 *
	 * The wp_get_sites() helper was removed in WordPress 5.1, so this path was a fatal
	 * error from 2019 until it was fixed in 3.0.0. The function it now uses has
	 * to exist and has to lift the default hundred-site limit.
	 *
	 * @return void
	 */
	public function test_network_activation_uses_a_function_that_still_exists() {
		$this->assertFalse( function_exists( 'wp_get_sites' ), 'wp_get_sites() is back; the guard needs revisiting' );

		// get_sites() is declared in ms-blogs.php, which a single site install
		// never loads, so it only has to exist where the branch actually runs.
		if ( is_multisite() ) {
			$this->assertTrue( function_exists( 'get_sites' ) );
		}

		// Checked over tokens rather than raw text: the comment that explains
		// this fix names wp_get_sites(), so a substring search matches the
		// documentation and never the code.
		$called = array();
		foreach ( token_get_all( file_get_contents( WP_POLLS_DIR . 'includes/class-polls-install.php' ) ) as $token ) {
			if ( is_array( $token ) && T_STRING === $token[0] ) {
				$called[] = $token[1];
			}
		}

		$this->assertNotContains( 'wp_get_sites', $called, 'the removed function is still called' );
		$this->assertContains( 'get_sites', $called );
		$this->assertStringContainsString(
			"get_sites( array( 'number' => 0 ) )",
			file_get_contents( WP_POLLS_DIR . 'includes/class-polls-install.php' ),
			'the hundred site default limit is no longer lifted'
		);
	}

	/**
	 * Network activation on a single site install still activates it.
	 *
	 * Multisite is off here, so the network flag must not send the
	 * routine down a branch that never runs activate().
	 *
	 * @return void
	 */
	public function test_network_activation_falls_back_on_single_site() {
		// Deliberately not asserted by dropping a table: DDL commits implicitly
		// in MySQL, so WP_UnitTestCase cannot roll it back, and an interrupted
		// run would leave the shared test database unusable. Removing the
		// capability proves activate() ran and is undone by the transaction.
		$role = get_role( 'administrator' );
		$role->remove_cap( 'manage_polls' );
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'manage_polls' ) );

		Polls_Install::activation( true );

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ) );
	}

	/**
	 * Activating twice is harmless.
	 *
	 * @return void
	 */
	public function test_activation_is_idempotent() {
		Polls_Install::activation( false );
		Polls_Install::activation( false );

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ) );
	}

	// --- front end assets -------------------------------------------------

	/**
	 * The bar settings become custom properties, not rules.
	 *
	 * The rules that consume them live in polls-css.css. Only the configured
	 * values are inline, which is what makes the bar restyleable from a theme
	 * stylesheet rather than by copying polls-css.css into the theme.
	 *
	 * @return void
	 */
	public function test_poll_scripts_emits_the_bar_custom_properties() {
		Polls_Options::set( 'bar.style', 'flat' );
		Polls_Options::set( 'bar.height', 12 );
		Polls_Options::set( 'bar.background', 'ff0000' );
		Polls_Options::set( 'bar.border', '00ff00' );

		Polls_Core::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-height: 12px;', $css );
		$this->assertStringContainsString( '--wp-polls-bar-background: #ff0000;', $css );
		$this->assertStringContainsString( '--wp-polls-bar-border: #00ff00;', $css );
		$this->assertStringContainsString( '--wp-polls-bar-image: none;', $css );
		$this->assertStringNotContainsString( '.pollbar', $css );
	}

	/**
	 * The gradient style is CSS rather than a GIF tile.
	 *
	 * @return void
	 */
	public function test_poll_scripts_emits_the_gradient_without_an_image() {
		Polls_Options::set( 'bar.style', 'gradient' );

		Polls_Core::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-image: linear-gradient(', $css );
		$this->assertStringNotContainsString( 'pollbg.gif', $css );
	}

	/**
	 * The gradient shades whatever colour is configured.
	 *
	 * The GIF tiles carried their own colours, so selecting one silently
	 * discarded the Poll Bar Background setting. The overlay is translucent, so
	 * the configured colour still shows through.
	 *
	 * @return void
	 */
	public function test_the_gradient_keeps_the_configured_colour() {
		Polls_Options::set( 'bar.style', 'gradient' );
		Polls_Options::set( 'bar.background', 'ff0000' );

		Polls_Core::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-background: #ff0000;', $css );
	}

	/**
	 * A junk colour cannot escape into the stylesheet.
	 *
	 * The CSS lands in an inline style block on every front end page, so the
	 * stored values are sanitised on the way out even though only an
	 * administrator can set them.
	 *
	 * @return void
	 */
	public function test_poll_scripts_sanitises_the_bar_colours() {
		Polls_Options::set( 'bar.style', 'flat' );
		Polls_Options::set( 'bar.background', 'red; } body { display: none; } .x {' );
		Polls_Options::set( 'bar.height', 8 );

		Polls_Core::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringNotContainsString( 'display: none', $css );
		$this->assertStringContainsString( '--wp-polls-bar-background: #000000;', $css );
	}

	/**
	 * One stylesheet serves both directions.
	 *
	 * The old polls-css-rtl.css existed only to flip text-align and swap a margin.
	 * Logical properties do that on their own, so there is no second file and
	 * no separate handle to enqueue.
	 *
	 * @return void
	 */
	public function test_one_stylesheet_serves_both_directions() {
		Polls_Core::poll_scripts();

		$this->assertTrue( wp_style_is( 'wp-polls', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wp-polls-rtl', 'enqueued' ), 'the RTL handle is back' );
		$this->assertFileDoesNotExist( WP_POLLS_DIR . 'polls-css-rtl.css' );

		$css = file_get_contents( WP_POLLS_DIR . 'polls-css.css' );

		// A physical direction here is what would need a second file again.
		$this->assertStringNotContainsString( 'text-align: left', $css );
		$this->assertStringNotContainsString( 'text-align: right', $css );
		$this->assertStringContainsString( 'text-align: start', $css );
		$this->assertStringContainsString( 'margin-inline:', $css );
	}

	/**
	 * The script is enqueued with its strings and no jQuery dependency.
	 *
	 * @return void
	 */
	public function test_poll_scripts_localises_without_jquery() {
		Polls_Options::set( 'ajax.loading', 1 );
		Polls_Options::set( 'ajax.fading', 0 );

		Polls_Core::poll_scripts();

		$this->assertTrue( wp_script_is( 'wp-polls', 'enqueued' ) );
		$this->assertSame( array(), wp_scripts()->registered['wp-polls']->deps, 'jQuery is back' );

		$data = wp_scripts()->get_data( 'wp-polls', 'data' );

		// wp_localize_script() stringifies every value, which is why the script
		// reads them back through parseInt().
		$this->assertStringContainsString( '"show_loading":"1"', $data );
		$this->assertStringContainsString( '"show_fading":"0"', $data );
		$this->assertStringContainsString( 'admin-ajax.php', $data );
	}

	// --- small helpers ----------------------------------------------------

	/**
	 * The answer sort only ever yields a column the query may order by.
	 *
	 * Both halves are interpolated into SQL, so anything not on the list has to
	 * collapse to the default rather than through.
	 *
	 * @return void
	 */
	public function test_answer_sort_rejects_anything_off_the_list() {
		Polls_Options::set( 'sort.answers_by', 'polla_votes' );
		Polls_Options::set( 'sort.answers_order', 'desc' );
		$this->assertSame( array( 'polla_votes', 'desc' ), Polls_Display::get_ans_sort() );

		Polls_Options::set( 'sort.answers_by', 'polla_aid; DROP TABLE wp_pollsq' );
		Polls_Options::set( 'sort.answers_order', 'desc; DROP TABLE wp_pollsq' );
		$this->assertSame( array( 'polla_aid', 'asc' ), Polls_Display::get_ans_sort() );
	}

	/**
	 * The result sort is guarded the same way.
	 *
	 * @return void
	 */
	public function test_result_sort_rejects_anything_off_the_list() {
		Polls_Options::set( 'sort.results_by', 'RAND()' );
		Polls_Options::set( 'sort.results_order', 'asc' );
		$this->assertSame( array( 'RAND()', 'asc' ), Polls_Display::get_ans_result_sort() );

		Polls_Options::set( 'sort.results_by', 'nonsense' );
		$this->assertSame( array( 'polla_aid', 'asc' ), Polls_Display::get_ans_result_sort() );
	}

	/**
	 * The archive link appends its page in a way that survives a query string.
	 *
	 * @return void
	 */
	public function test_archive_link_paging() {
		Polls_Options::set( 'archive.url', 'https://example.com/polls/' );

		$this->assertSame( 'https://example.com/polls/', Polls_Display::polls_archive_link( 0 ) );
		$this->assertSame( 'https://example.com/polls/?poll_page=2', Polls_Display::polls_archive_link( 2 ) );

		Polls_Options::set( 'archive.url', 'https://example.com/?page_id=9' );

		$this->assertSame( 'https://example.com/?page_id=9&amp;poll_page=3', Polls_Display::polls_archive_link( 3 ) );
	}

	/**
	 * The latest poll is the newest open one.
	 *
	 * @return void
	 */
	public function test_latest_poll_id_ignores_closed_and_scheduled_polls() {
		$this->make_poll(
			array(
				'pollq_active'    => 1,
				'pollq_timestamp' => 1000,
			)
		);
		$newest = $this->make_poll(
			array(
				'pollq_active'    => 1,
				'pollq_timestamp' => 3000,
			)
		);
		$this->make_poll(
			array(
				'pollq_active'    => 0,
				'pollq_timestamp' => 4000,
			)
		);
		$this->make_poll(
			array(
				'pollq_active'    => -1,
				'pollq_timestamp' => 5000,
			)
		);

		$this->assertSame( $newest, Polls_Core::polls_latest_id() );
	}

	/**
	 * With no polls at all the latest id is zero rather than null.
	 *
	 * @return void
	 */
	public function test_latest_poll_id_with_no_polls() {
		$this->assertSame( 0, Polls_Core::polls_latest_id() );
	}

	/**
	 * Placing the cron job schedules it exactly once.
	 *
	 * @return void
	 */
	public function test_cron_is_scheduled_once() {
		wp_clear_scheduled_hook( 'polls_cron' );

		Polls_Core::cron_polls_place();
		$first = wp_next_scheduled( 'polls_cron' );
		$this->assertNotFalse( $first );

		Polls_Core::cron_polls_place();
		$this->assertNotFalse( wp_next_scheduled( 'polls_cron' ) );
		$this->assertCount( 1, _get_cron_array()[ wp_next_scheduled( 'polls_cron' ) ]['polls_cron'] );
	}

	/**
	 * Every template key has a default, and the list matches the stored shape.
	 *
	 * A key present in one and not the other is how a template silently stops
	 * being editable or stops being migrated.
	 *
	 * @return void
	 */
	public function test_every_template_key_has_a_default() {
		$keys     = Polls_Options::template_keys();
		$defaults = Polls_Templates::defaults();

		$this->assertNotEmpty( $keys );
		$this->assertSame( array(), array_diff( $keys, array_keys( $defaults ) ), 'keys with no default' );
		$this->assertSame( array(), array_diff( array_keys( $defaults ), $keys ), 'defaults with no key' );

		// The archive wrappers are deliberately empty: they exist so a theme can
		// wrap the list without the plugin imposing markup of its own.
		$optional = array( 'pollarchiveheader', 'pollarchivepagingheader', 'pollarchivepagingfooter' );

		foreach ( array_diff( $keys, $optional ) as $key ) {
			$this->assertNotSame( '', Polls_Templates::get_default( $key ), $key . ' has an empty default' );
		}

		foreach ( $optional as $key ) {
			$this->assertSame( '', Polls_Templates::get_default( $key ), $key . ' is no longer optional' );
		}
	}

	/**
	 * An unknown template key yields an empty string rather than a notice.
	 *
	 * @return void
	 */
	public function test_an_unknown_template_key_is_empty() {
		$this->assertSame( '', Polls_Templates::get_default( 'no-such-template' ) );
	}

	/**
	 * Template variables are substituted into the markup.
	 *
	 * @return void
	 */
	public function test_template_variables_are_substituted() {
		$out = Polls_Display::poll_template_vote_markup(
			'<li>%POLL_ANSWER% (%POLL_ANSWER_VOTES%)</li>',
			null,
			array(
				'%POLL_ANSWER%'       => 'vim',
				'%POLL_ANSWER_VOTES%' => '3',
			)
		);

		$this->assertSame( '<li>vim (3)</li>', $out );
	}

	/**
	 * The WP-Stats hooks are only added, never replacing that plugin's own.
	 *
	 * @return void
	 */
	public function test_wp_stats_defaults_are_additive() {
		$merged = Polls_Admin::polls_wp_stats_defaults( array( 'something_else' => 0 ) );

		$this->assertSame( 1, $merged['polls'] );
		$this->assertArrayHasKey( 'something_else', $merged );
	}

	/**
	 * WP-Stats' own value for polls wins over the added default.
	 *
	 * @return void
	 */
	public function test_wp_stats_defaults_do_not_override() {
		$merged = Polls_Admin::polls_wp_stats_defaults( array( 'polls' => 0 ) );

		$this->assertSame( 0, $merged['polls'] );
	}

	// --- the upgrade notice ----------------------------------------------

	/**
	 * The notice is shown to an administrator on a poll screen.
	 *
	 * @return void
	 */
	public function test_onclick_notice_is_shown_when_a_template_needs_converting() {
		global $hook_suffix;

		$this->become_poll_admin();
		Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		$hook_suffix = WP_POLLS_SLUG . '/polls-manager.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		Polls_Install::onclick_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'data-poll-action', $html );
	}

	/**
	 * The notice stays off other admin screens.
	 *
	 * @return void
	 */
	public function test_onclick_notice_is_not_shown_elsewhere() {
		global $hook_suffix;

		$this->become_poll_admin();
		Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		$hook_suffix = 'options-general.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		Polls_Install::onclick_notice();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * A user without the capability is never shown it.
	 *
	 * @return void
	 */
	public function test_onclick_notice_needs_the_capability() {
		global $hook_suffix;

		Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$hook_suffix = WP_POLLS_SLUG . '/polls-manager.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		Polls_Install::onclick_notice();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Converting the templates is what makes the notice go away.
	 *
	 * @return void
	 */
	public function test_upgrading_the_templates_silences_the_notice() {
		Polls_Options::set( 'templates.votefooter', '<a href="#" onclick="poll_result(%POLL_ID%); return false;">Results</a>' );

		$this->assertTrue( Polls_Install::templates_have_onclick() );

		Polls_Install::upgrade_templates_onclick();

		$this->assertFalse( Polls_Install::templates_have_onclick() );
		$this->assertStringContainsString( 'data-poll-action="result"', Polls_Options::get( 'templates.votefooter' ) );
	}
}
