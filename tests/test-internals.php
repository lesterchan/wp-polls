<?php
/**
 * Tests for the vote lock, activation, asset output and the small helpers.
 *
 * @package WP-Polls
 */

/**
 * The last entry points without coverage of their own.
 *
 * @covers WP_Polls_Vote::polls_acquire_lock
 * @covers WP_Polls_Vote::polls_release_lock
 * @covers WP_Polls_Vote::polls_lock_file
 * @covers WP_Polls_Install::activation
 * @covers WP_Polls::poll_scripts
 */
class WP_Polls_Internals_Test extends WP_Polls_TestCase {

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
		$first  = WP_Polls_Vote::polls_lock_file( 1 );
		$second = WP_Polls_Vote::polls_lock_file( 2 );

		$this->assertNotSame( $first, $second, 'Two polls get two lock files.' );
		$this->assertStringContainsString( 'wp-blog-' . get_current_blog_id() . '-', $first, 'Named for the site.' );
		$this->assertStringContainsString( '-wp-polls-1.lock', $first, 'And the poll.' );
		$this->assertStringStartsWith( get_temp_dir(), $first, 'Written to the temporary directory rather than into the plugin.' );
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

		$this->assertSame( '/tmp/custom-9.lock', WP_Polls_Vote::polls_lock_file( 9 ), 'The lock file is filterable.' );
	}

	/**
	 * Acquiring the lock creates the file and hands back a handle.
	 *
	 * @return void
	 */
	public function test_acquiring_the_lock_creates_the_file() {
		$handle = WP_Polls_Vote::polls_acquire_lock( 1 );

		$this->assertIsResource( $handle, 'Acquiring the lock hands back an open handle.' );
		$this->assertFileExists( WP_Polls_Vote::polls_lock_file( 1 ), 'Acquiring the lock creates the lock file.' );

		WP_Polls_Vote::polls_release_lock( $handle, 1 );
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
		$handle = WP_Polls_Vote::polls_acquire_lock( 1 );
		$path   = WP_Polls_Vote::polls_lock_file( 1 );

		$this->assertTrue( WP_Polls_Vote::polls_release_lock( $handle, 1 ), 'Releasing a held lock reports success.' );
		$this->assertFileDoesNotExist( $path, 'Releasing the lock removes the file rather than leaving it behind.' );
	}

	/**
	 * Releasing something that is not a handle reports failure.
	 *
	 * @return void
	 */
	public function test_releasing_a_non_handle_is_refused() {
		$this->assertFalse( WP_Polls_Vote::polls_release_lock( false, 1 ), 'Releasing something that is not a handle is refused rather than fatal.' );
		$this->assertFalse( WP_Polls_Vote::polls_release_lock( null, 1 ), 'Releasing null is refused rather than fatal.' );
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
		$path = WP_Polls_Vote::polls_lock_file( 1 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Taking a second flock() on the lock file is the only way to simulate a concurrent voter from one process.
		$holder = fopen( $path, 'w+' );
		$this->assertTrue( flock( $holder, LOCK_EX | LOCK_NB ), 'The fixture really does hold the lock, or the contention below is not contention.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Taking a second flock() on the lock file is the only way to simulate a concurrent voter from one process.
		$contender = fopen( $path, 'w+' );
		$this->assertFalse( flock( $contender, LOCK_EX | LOCK_NB ), 'the lock was not exclusive' );

		flock( $holder, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Releasing the flock() taken above.
		fclose( $holder );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Releasing the flock() taken above.
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

		WP_Polls_Options::set( 'allow_to_vote', 2 );
		WP_Polls_Options::set( 'check_method', 0 );

		WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );

		$this->assertFileDoesNotExist( WP_Polls_Vote::polls_lock_file( $poll_id ), 'A completed vote leaves no lock file behind.' );
	}

	/**
	 * A vote that cannot take the lock is refused rather than double counted.
	 *
	 * @return void
	 */
	public function test_a_vote_that_cannot_lock_is_refused() {
		$poll_id = $this->make_poll();
		$answers = $this->answer_ids( $poll_id );

		WP_Polls_Options::set( 'allow_to_vote', 2 );
		WP_Polls_Options::set( 'check_method', 0 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Taking a second flock() on the lock file is the only way to simulate a concurrent voter from one process.
		$holder = fopen( WP_Polls_Vote::polls_lock_file( $poll_id ), 'w+' );
		flock( $holder, LOCK_EX | LOCK_NB );

		try {
			WP_Polls_Vote::vote_poll_process( $poll_id, array( $answers[0] ) );
			$this->fail( 'the vote was not refused' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( (string) $poll_id, $e->getMessage(), 'The failure names the poll that could not be locked.' );
		} finally {
			flock( $holder, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Releasing the flock() taken above.
			fclose( $holder );
		}

		global $wpdb;
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) ),
			'And no vote was counted.'
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

		WP_Polls_Install::activation( false );

		foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $table ) {
			$name = $wpdb->$table;
			$this->assertSame(
				$name,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ),
				$table . ' was not created'
			);
		}

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ), 'Activation grants the capability to the administrator role.' );
	}

	/**
	 * Network activation walks every site, and walks all of them.
	 *
	 * This used to call wp_get_sites(), which has been deprecated since
	 * WordPress 4.6 and is capped at 100 sites, so network activation quietly
	 * skipped every site past the hundredth. 3.0.0 moved to get_sites() with the
	 * limit lifted.
	 *
	 * Deliberately not asserted as `! function_exists( 'wp_get_sites' )`, which
	 * is what this test used to do. That function was never removed — it still
	 * ships in ms-deprecated.php — and only *looks* absent on a single site,
	 * because that file is loaded for multisite alone. So the assertion passed
	 * for the wrong reason single-site and failed outright as a network.
	 *
	 * @return void
	 */
	public function test_network_activation_uses_a_function_that_still_exists() {
		// get_sites() is declared in ms-blogs.php, which a single site install
		// never loads, so it only has to exist where the branch actually runs.
		if ( is_multisite() ) {
			$this->assertTrue( function_exists( 'get_sites' ), 'get_sites() is missing where the branch runs' );
		}

		// Checked over tokens rather than raw text: the comment that explains
		// this fix names wp_get_sites(), so a substring search matches the
		// documentation and never the code.
		$called = array();
		foreach ( token_get_all( file_get_contents( WP_POLLS_DIR . 'includes/class-wp-polls-install.php' ) ) as $token ) {
			if ( is_array( $token ) && T_STRING === $token[0] ) {
				$called[] = $token[1];
			}
		}

		$this->assertNotContains( 'wp_get_sites', $called, 'the deprecated, 100-site-capped function is still called' );
		$this->assertContains( 'get_sites', $called, 'The site walk goes through get_sites() instead.' );
		$this->assertStringContainsString(
			"get_sites( array( 'number' => 0 ) )",
			file_get_contents( WP_POLLS_DIR . 'includes/class-wp-polls-install.php' ),
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
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'manage_polls' ), 'The capability is absent to begin with, or the grant below proves nothing.' );

		WP_Polls_Install::activation( true );

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ), 'Network activation on a single site still grants the capability.' );
	}

	/**
	 * Activating twice is harmless.
	 *
	 * @return void
	 */
	public function test_activation_is_idempotent() {
		WP_Polls_Install::activation( false );
		WP_Polls_Install::activation( false );

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_polls' ), 'Activating twice leaves the capability granted rather than doubled or lost.' );
	}

	// --- front end assets -------------------------------------------------

	/**
	 * What the colour fields can post, and what comes back out.
	 *
	 * The Settings tab colour fields are colour inputs, which post '#rrggbb';
	 * the setting is stored as the six digits alone, and every caller adds the
	 * '#' back. Three digit values predate 3.0.0 and have to be expanded,
	 * because CSS understands '#abc' but a colour input will not show it.
	 *
	 * @return void
	 */
	public function test_sanitize_bar_color_normalises_what_the_picker_posts() {
		$this->assertSame( 'aabbcc', WP_Polls::sanitize_bar_color( '#aabbcc' ), 'The hash the colour input posts is stripped.' );
		$this->assertSame( 'aabbcc', WP_Polls::sanitize_bar_color( 'aabbcc' ), 'A value that already has none is left alone.' );
		$this->assertSame( 'aabbcc', WP_Polls::sanitize_bar_color( '  #AABBCC  ' ), 'Surrounding space is trimmed and the case lowered.' );
		$this->assertSame( 'aabbcc', WP_Polls::sanitize_bar_color( '#abc' ), 'A three digit value is expanded to six.' );
		$this->assertSame( 'ffffff', WP_Polls::sanitize_bar_color( 'fff' ), 'With or without the hash.' );
		$this->assertSame( '000000', WP_Polls::sanitize_bar_color( 'zzzzzz' ), 'Something that is not a colour becomes black rather than being stored.' );
		$this->assertSame( '000000', WP_Polls::sanitize_bar_color( 'red; } body { display: none; } .x {' ), 'A CSS injection becomes black too.' );
		$this->assertSame( '000000', WP_Polls::sanitize_bar_color( '' ), 'And so does nothing at all.' );
	}

	/**
	 * The bar settings become custom properties, not rules.
	 *
	 * The rules that consume them live in css/wp-polls.css. Only the configured
	 * values are inline, which is what makes the bar restyleable from a theme
	 * stylesheet rather than by copying css/wp-polls.css into the theme.
	 *
	 * @return void
	 */
	public function test_poll_scripts_emits_the_bar_custom_properties() {
		WP_Polls_Options::set( 'bar.style', 'flat' );
		WP_Polls_Options::set( 'bar.height', 12 );
		WP_Polls_Options::set( 'bar.background', 'ff0000' );
		WP_Polls_Options::set( 'bar.border', '00ff00' );

		WP_Polls::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-height: 12px;', $css, 'The bar height is emitted as a custom property.' );
		$this->assertStringContainsString( '--wp-polls-bar-background: #ff0000;', $css, 'The background colour.' );
		$this->assertStringContainsString( '--wp-polls-bar-border: #00ff00;', $css, 'The border colour.' );
		$this->assertStringContainsString( '--wp-polls-bar-image: none;', $css, 'And the image, which here is none.' );
		$this->assertStringNotContainsString( '.pollbar', $css, 'None of them is written as a rule against the class it replaced.' );
	}

	/**
	 * The gradient style is CSS rather than a GIF tile.
	 *
	 * @return void
	 */
	public function test_poll_scripts_emits_the_gradient_without_an_image() {
		WP_Polls_Options::set( 'bar.style', 'gradient' );

		WP_Polls::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-image: linear-gradient(', $css, 'The gradient style is drawn in CSS.' );
		$this->assertStringNotContainsString( 'pollbg.gif', $css, 'Rather than from the image file the plugin no longer ships.' );
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
		WP_Polls_Options::set( 'bar.style', 'gradient' );
		WP_Polls_Options::set( 'bar.background', 'ff0000' );

		WP_Polls::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringContainsString( '--wp-polls-bar-background: #ff0000;', $css, 'And the gradient keeps the configured colour rather than a hard coded one.' );
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
		WP_Polls_Options::set( 'bar.style', 'flat' );
		WP_Polls_Options::set( 'bar.background', 'red; } body { display: none; } .x {' );
		WP_Polls_Options::set( 'bar.height', 8 );

		WP_Polls::poll_scripts();

		$css = implode( '', (array) wp_styles()->get_data( 'wp-polls', 'after' ) );

		$this->assertStringNotContainsString( 'display: none', $css, 'A CSS injection in the colour setting never reaches the stylesheet.' );
		$this->assertStringContainsString( '--wp-polls-bar-background: #000000;', $css, 'It falls back to black instead.' );
	}

	/**
	 * One stylesheet serves both directions.
	 *
	 * The old wp-polls-rtl.css existed only to flip text-align and swap a margin.
	 * Logical properties do that on their own, so there is no second file and
	 * no separate handle to enqueue.
	 *
	 * @return void
	 */
	public function test_one_stylesheet_serves_both_directions() {
		WP_Polls::poll_scripts();

		$this->assertTrue( wp_style_is( 'wp-polls', 'enqueued' ), 'The one stylesheet is enqueued whichever direction the site reads.' );
		$this->assertFalse( wp_style_is( 'wp-polls-rtl', 'enqueued' ), 'the RTL handle is back' );
		$this->assertFileDoesNotExist( WP_POLLS_DIR . 'css/wp-polls-rtl.css', 'No separate RTL stylesheet ships; the one file uses logical properties.' );

		$css = file_get_contents( WP_POLLS_DIR . 'css/wp-polls.css' );

		// A physical direction here is what would need a second file again.
		$this->assertStringNotContainsString( 'text-align: left', $css, 'The stylesheet does not name the left edge.' );
		$this->assertStringNotContainsString( 'text-align: right', $css, 'Nor the right.' );
		$this->assertStringNotContainsString( 'margin-left', $css, 'Nor a left margin.' );
		$this->assertStringNotContainsString( 'padding-right', $css, 'Nor a right padding.' );
		$this->assertStringContainsString( 'text-align: start', $css, 'It uses the start of the line.' );
		$this->assertStringContainsString( 'margin-inline:', $css, 'Logical margins.' );
		$this->assertStringContainsString( 'border-inline-end-color', $css, 'And logical borders, so one stylesheet serves both directions.' );
	}

	/**
	 * The front end stylesheet leaves the theme's typography alone.
	 *
	 * A poll is part of the page it sits in, so it inherits the font and the
	 * text colour rather than declaring its own, and it never wins an argument
	 * with the theme by force.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_inherits_typography_and_uses_no_important() {
		$css = file_get_contents( WP_POLLS_DIR . 'css/wp-polls.css' );

		$this->assertStringNotContainsString( 'font-family', $css, 'The stylesheet sets no font family.' );
		$this->assertStringNotContainsString( 'font-size', $css, 'No font size.' );
		$this->assertStringNotContainsString( '!important', $css, 'And nothing a theme cannot override.' );
	}

	/**
	 * Any motion the stylesheet adds can be switched off by the visitor.
	 *
	 * @return void
	 */
	public function test_every_animation_respects_reduced_motion() {
		$css = file_get_contents( WP_POLLS_DIR . 'css/wp-polls.css' );

		$this->assertSame(
			substr_count( $css, '@keyframes' ),
			substr_count( $css, 'animation: none' ),
			'every animation needs a prefers-reduced-motion counterpart'
		);
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', $css, 'Every animation is behind the reduced motion query.' );
	}

	/**
	 * The script is enqueued with its strings and no jQuery dependency.
	 *
	 * @return void
	 */
	public function test_poll_scripts_localises_without_jquery() {
		WP_Polls_Options::set( 'ajax.loading', 1 );
		WP_Polls_Options::set( 'ajax.fading', 0 );

		WP_Polls::poll_scripts();

		$this->assertTrue( wp_script_is( 'wp-polls', 'enqueued' ), 'The vote script is enqueued without jQuery being pulled in.' );
		$this->assertSame( array(), wp_scripts()->registered['wp-polls']->deps, 'jQuery is back' );

		$data = wp_scripts()->get_data( 'wp-polls', 'data' );

		$this->assertStringContainsString( 'admin-ajax.php', $data, 'The endpoint reaches the script through localised data.' );

		// The two AJAX style flags are gone. The loading indicator always shows,
		// and the fade asks prefers-reduced-motion rather than an option.
		$this->assertStringNotContainsString( 'show_loading', $data, 'the AJAX style flags are back' );
		$this->assertStringNotContainsString( 'show_fading', $data, 'the AJAX style flags are back' );
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
		WP_Polls_Options::set( 'sort.answers_by', 'polla_votes' );
		WP_Polls_Options::set( 'sort.answers_order', 'desc' );
		$this->assertSame( array( 'polla_votes', 'desc' ), WP_Polls_Display::get_ans_sort(), 'A column on the list is honoured.' );

		WP_Polls_Options::set( 'sort.answers_by', 'polla_aid; DROP TABLE wp_pollsq' );
		WP_Polls_Options::set( 'sort.answers_order', 'desc; DROP TABLE wp_pollsq' );
		$this->assertSame( array( 'polla_aid', 'asc' ), WP_Polls_Display::get_ans_sort(), 'And one off it falls back rather than reaching ORDER BY.' );
	}

	/**
	 * The result sort is guarded the same way.
	 *
	 * @return void
	 */
	public function test_result_sort_rejects_anything_off_the_list() {
		WP_Polls_Options::set( 'sort.results_by', 'RAND()' );
		WP_Polls_Options::set( 'sort.results_order', 'asc' );
		$this->assertSame( array( 'RAND()', 'asc' ), WP_Polls_Display::get_ans_result_sort(), 'A result column on the list is honoured.' );

		WP_Polls_Options::set( 'sort.results_by', 'nonsense' );
		$this->assertSame( array( 'polla_aid', 'asc' ), WP_Polls_Display::get_ans_result_sort(), 'And one off it falls back rather than reaching ORDER BY.' );
	}

	/**
	 * The archive link appends its page in a way that survives a query string.
	 *
	 * @return void
	 */
	public function test_archive_link_paging() {
		WP_Polls_Options::set( 'archive.url', 'https://example.com/polls/' );

		$this->assertSame( 'https://example.com/polls/', WP_Polls_Display::polls_archive_link( 0 ), 'Page zero is the archive itself, with no page argument.' );
		$this->assertSame( 'https://example.com/polls/?poll_page=2', WP_Polls_Display::polls_archive_link( 2 ), 'A later page appends the page number.' );

		WP_Polls_Options::set( 'archive.url', 'https://example.com/?page_id=9' );

		$this->assertSame( 'https://example.com/?page_id=9&amp;poll_page=3', WP_Polls_Display::polls_archive_link( 3 ), 'And on a query string URL it is appended rather than starting a new one.' );
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

		$this->assertSame( $newest, WP_Polls::polls_latest_id(), 'The latest poll is the newest open one, not a closed or scheduled one.' );
	}

	/**
	 * With no polls at all the latest id is zero rather than null.
	 *
	 * @return void
	 */
	public function test_latest_poll_id_with_no_polls() {
		$this->assertSame( 0, WP_Polls::polls_latest_id(), 'With no polls at all there is no latest poll.' );
	}

	/**
	 * Placing the cron job schedules it exactly once.
	 *
	 * @return void
	 */
	public function test_cron_is_scheduled_once() {
		wp_clear_scheduled_hook( 'polls_cron' );

		WP_Polls::cron_polls_place();
		$first = wp_next_scheduled( 'polls_cron' );
		$this->assertNotFalse( $first, 'The cron was scheduled at all, or the once-only assertion below is vacuous.' );

		WP_Polls::cron_polls_place();
		$this->assertNotFalse( wp_next_scheduled( 'polls_cron' ), 'The cron is still scheduled after the second call.' );
		$this->assertCount( 1, _get_cron_array()[ wp_next_scheduled( 'polls_cron' ) ]['polls_cron'], 'Scheduling twice leaves one event, not two.' );
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
		$keys     = WP_Polls_Options::template_keys();
		$defaults = WP_Polls_Template::defaults();

		$this->assertNotEmpty( $keys, 'There are template keys at all, or the defaults check below is vacuous.' );
		$this->assertSame( array(), array_diff( $keys, array_keys( $defaults ) ), 'keys with no default' );
		$this->assertSame( array(), array_diff( array_keys( $defaults ), $keys ), 'defaults with no key' );

		// The archive wrappers are deliberately empty: they exist so a theme can
		// wrap the list without the plugin imposing markup of its own.
		$optional = array( 'pollarchiveheader', 'pollarchivepagingheader', 'pollarchivepagingfooter' );

		foreach ( array_diff( $keys, $optional ) as $key ) {
			$this->assertNotSame( '', WP_Polls_Template::get_default( $key ), $key . ' has an empty default' );
		}

		foreach ( $optional as $key ) {
			$this->assertSame( '', WP_Polls_Template::get_default( $key ), $key . ' is no longer optional' );
		}
	}

	/**
	 * An unknown template key yields an empty string rather than a notice.
	 *
	 * @return void
	 */
	public function test_an_unknown_template_key_is_empty() {
		$this->assertSame( '', WP_Polls_Template::get_default( 'no-such-template' ), 'An unknown template key is the empty string, not a notice.' );
	}

	/**
	 * Template variables are substituted into the markup.
	 *
	 * @return void
	 */
	public function test_template_variables_are_substituted() {
		$out = WP_Polls_Display::poll_template_vote_markup(
			'<li>%POLL_ANSWER% (%POLL_ANSWER_VOTES%)</li>',
			null,
			array(
				'%POLL_ANSWER%'       => 'vim',
				'%POLL_ANSWER_VOTES%' => '3',
			)
		);

		$this->assertSame( '<li>vim (3)</li>', $out, 'Every variable in the template is substituted.' );
	}

	/**
	 * The WP-Stats section is contributed under the plugin's own key.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_section_is_keyed_by_the_plugin_slug() {
		$sections = WP_Polls_WPStats::register_section( array( 'something_else' => array() ) );

		$this->assertArrayHasKey( 'wp_polls', $sections, 'WP-Polls contributes its own entry.' );
		$this->assertArrayHasKey( 'something_else', $sections, 'Another plugin\'s entry is left alone.' );
	}

	/**
	 * The entry carries exactly the three keys WP-Stats reads.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_section_has_a_title_a_priority_and_a_renderer() {
		$section = WP_Polls_WPStats::register_section( array() )['wp_polls'];

		$this->assertSame( array( 'title', 'priority', 'render' ), array_keys( $section ), 'The WP-Stats section declares exactly these three keys.' );
		$this->assertNotEmpty( $section['title'], 'The heading is translated, not empty.' );
		$this->assertIsInt( $section['priority'], 'The sort order is an integer.' );
		$this->assertIsCallable( $section['render'], 'The renderer can be called.' );
	}

	/**
	 * Turning the setting off contributes nothing at all.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_section_is_withheld_when_the_setting_is_off() {
		WP_Polls_Options::set( 'stats_display', false );

		$this->assertSame( array(), WP_Polls_WPStats::register_section( array() ), 'With the setting off there is nothing to register.' );
	}

	/**
	 * The renderer echoes the three counts rather than returning them.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_renderer_echoes_the_poll_counts() {
		$this->make_poll( array( 'pollq_question' => 'Counted' ), array( array( 'Yes', 2 ), array( 'No', 1 ) ) );

		ob_start();
		$returned = WP_Polls_WPStats::render();
		$html     = ob_get_clean();

		$this->assertNull( $returned, 'render() echoes; it does not return markup.' );
		$this->assertStringContainsString( '<li>', $html, 'The renderer echoes the counts as a list.' );
		$this->assertStringContainsString( '<strong>1</strong>', $html, 'One poll was created.' );
		$this->assertStringContainsString( '<strong>2</strong>', $html, 'Two answers were given.' );
		$this->assertStringContainsString( '<strong>3</strong>', $html, 'Three votes were cast.' );
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
		WP_Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		$hook_suffix = WP_Polls_Admin::hook_suffix( 'wp-polls' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		WP_Polls_Install::onclick_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html, 'A template that still needs converting raises a warning.' );
		$this->assertStringContainsString( 'data-poll-action', $html, 'Naming what it would be converted to.' );
	}

	/**
	 * The notice stays off other admin screens.
	 *
	 * @return void
	 */
	public function test_onclick_notice_is_not_shown_elsewhere() {
		global $hook_suffix;

		$this->become_poll_admin();
		WP_Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		$hook_suffix = 'options-general.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		WP_Polls_Install::onclick_notice();

		$this->assertSame( '', ob_get_clean(), 'The notice is not shown on other screens.' );
	}

	/**
	 * A user without the capability is never shown it.
	 *
	 * @return void
	 */
	public function test_onclick_notice_needs_the_capability() {
		global $hook_suffix;

		WP_Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$hook_suffix = WP_Polls_Admin::hook_suffix( 'wp-polls' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice reads this global.

		ob_start();
		WP_Polls_Install::onclick_notice();

		$this->assertSame( '', ob_get_clean(), 'Nor to a user who could not act on it.' );
	}

	/**
	 * Converting the templates is what makes the notice go away.
	 *
	 * @return void
	 */
	public function test_upgrading_the_templates_silences_the_notice() {
		WP_Polls_Options::set( 'templates.votefooter', '<a href="#" onclick="poll_result(%POLL_ID%); return false;">Results</a>' );

		$this->assertTrue( WP_Polls_Install::templates_have_onclick(), 'The fixture templates really do carry an onclick, or the upgrade below has nothing to do.' );

		WP_Polls_Install::upgrade_templates_onclick();

		$this->assertFalse( WP_Polls_Install::templates_have_onclick(), 'Upgrading the templates removes the onclick that raises the notice.' );
		$this->assertStringContainsString( 'data-poll-action="result"', WP_Polls_Options::get( 'templates.votefooter' ), 'Once converted the template carries the data attribute, so the notice has nothing left to say.' );
	}
}
