<?php
/**
 * Render tests for the four legacy admin pages.
 *
 * These pages are the only part of the plugin with no other coverage: the
 * Playground harness reaches front-end rendering, and every other class has its
 * own test file. Every rendering bug found during the 3.0.0 modernization lived
 * here, and each one was caught by reading a diff rather than by tooling —
 * translators comments printed on screen, output escaped twice, a stray cast
 * that made a branch permanently false. All of those are visible in the HTML,
 * so these tests assert on the HTML.
 *
 * @package WP-Polls
 */

/**
 * The Manage/Add/Options/Templates screens and the logs view.
 *
 * @covers WP_Polls_Admin
 */
class WP_Polls_Admin_Pages_Test extends WP_Polls_TestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->become_poll_admin();

		// The list table is loaded by the menu callback that renders Manage
		// Polls, because WP_List_Table only exists inside wp-admin. A test run
		// on its own with --filter has not been through that callback, so a
		// reference to WP_Polls_List_Table::PER_PAGE below would fatal purely
		// because of the order the tests happened to run in.
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-list-table.php';
	}

	/**
	 * Every admin view, as [ file, $_GET ].
	 *
	 * Manage Polls appears three times because its edit and logs branches are
	 * separate screens sharing one file, and each renders markup the list view
	 * never touches. Covering only the default view is how an early version of
	 * these tests missed a translators comment injected into the edit branch.
	 *
	 * Each case is the screen name WP_Polls_Admin::render_*() answers to, plus
	 * the query args that pick a view. The id placeholder is replaced with the
	 * seeded poll's real id.
	 *
	 * @return array
	 */
	public function admin_page_provider() {
		return array(
			'manage polls' => array( 'manage', array() ),
			'edit poll'    => array(
				'manage',
				array(
					'mode' => 'edit',
					'id'   => '%POLL_ID%',
				),
			),
			'poll logs'    => array(
				'manage',
				array(
					'mode' => 'logs',
					'id'   => '%POLL_ID%',
				),
			),
			'add poll'     => array( 'add', array() ),
			'settings'     => array( 'settings', array() ),
			'templates'    => array(
				'settings',
				array( 'tab' => 'templates' ),
			),
		);
	}

	/**
	 * Seed a poll with a vote log and resolve %POLL_ID% in provider args.
	 *
	 * @param array  $get      Query args from the provider.
	 * @param string $question Poll question.
	 * @return array
	 */
	private function seed_and_resolve( $get, $question = 'Which editor?' ) {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => $question ),
			array( array( 'Alpha', 2 ), array( 'Beta', 5 ) )
		);
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0], 'Registered Voter', 7 );
		$this->make_vote_log( $poll_id, $answers[1], 'Guest' );

		foreach ( $get as $key => $value ) {
			if ( '%POLL_ID%' === $value ) {
				$get[ $key ] = (string) $poll_id;
			}
		}

		return $get;
	}

	/**
	 * Each page renders without raising a PHP diagnostic.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file Screen name.
	 * @param array  $get  Query args.
	 * @return void
	 */
	public function test_page_renders_without_php_diagnostics( $file, $get ) {
		$get = $this->seed_and_resolve( $get );

		$html = $this->render_admin_page( $file, $get );

		$this->assertNotEmpty( $html, $file . ' produced no output' );
		$this->assertSame(
			array(),
			$this->admin_page_notices,
			$file . ' raised PHP diagnostics: ' . implode( ' | ', $this->admin_page_notices )
		);
	}

	/**
	 * No page leaks a translators comment or a raw PHP tag into the markup.
	 *
	 * A translators comment placed in HTML context rather than immediately
	 * before its gettext call renders literally on screen. Eight of them did.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file Screen name.
	 * @param array  $get  Query args.
	 * @return void
	 */
	public function test_page_does_not_leak_source_into_markup( $file, $get ) {
		$get = $this->seed_and_resolve( $get );

		$html = $this->render_admin_page( $file, $get );

		$this->assertStringNotContainsString( 'translators:', $html, $file );
		$this->assertStringNotContainsString( '<?php', $html, $file );
		$this->assertStringNotContainsString( 'Fatal error', $html, $file );
	}

	/**
	 * No view double escapes the question it renders.
	 *
	 * Runs across every view rather than a chosen pair, because the edit and
	 * logs branches build their markup independently of the list.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file Screen name.
	 * @param array  $get  Query args.
	 * @return void
	 */
	public function test_no_view_double_escapes( $file, $get ) {
		$get = $this->seed_and_resolve( $get, 'Tabs & "spaces"?' );

		$html = $this->render_admin_page( $file, $get );

		$this->assertStringNotContainsString( '&amp;amp;', $html, $file );
		$this->assertStringNotContainsString( '&amp;quot;', $html, $file );
		$this->assertStringNotContainsString( '&amp;#039;', $html, $file );
	}

	/**
	 * A question carrying an ampersand and quotes is escaped exactly once.
	 *
	 * Escaping an already-escaped value renders &amp;amp; on screen. That bug
	 * shipped in the poll bar tooltip and again in the logs answer dropdown.
	 *
	 * @return void
	 */
	public function test_question_is_not_double_escaped() {
		$this->make_poll( array( 'pollq_question' => 'Tabs & "spaces"?' ) );

		foreach ( array( 'manage', 'settings' ) as $file ) {
			$html = $this->render_admin_page( $file );

			$this->assertStringNotContainsString( '&amp;amp;', $html, $file );
			$this->assertStringNotContainsString( '&amp;quot;', $html, $file );
			$this->assertStringContainsString( 'Tabs &amp; &quot;spaces&quot;?', $html, $file );
		}
	}

	/**
	 * Manage Polls lists the polls that exist.
	 *
	 * @return void
	 */
	public function test_manage_lists_polls() {
		$this->make_poll( array( 'pollq_question' => 'First poll' ) );
		$this->make_poll( array( 'pollq_question' => 'Second poll' ) );

		$html = $this->render_admin_page( 'manage' );

		$this->assertStringContainsString( 'First poll', $html, 'The manage screen lists a poll.' );
		$this->assertStringContainsString( 'Second poll', $html, 'And another, so the list is not stopping at one.' );
	}

	/**
	 * The logs view renders inside Manage Polls under mode=logs.
	 *
	 * It has no menu slug of its own, so this is the only way to reach it.
	 *
	 * @return void
	 */
	public function test_logs_view_renders_vote_totals() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Logged poll' ),
			array( array( 'Yes', 3 ), array( 'No', 1 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$this->make_vote_log( $poll_id, $answers[0], 'Registered Voter', 7 );
		$this->make_vote_log( $poll_id, $answers[0], 'Comment Author' );
		$this->make_vote_log( $poll_id, $answers[1], 'Guest' );

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'Logged poll', $html, 'The logs view names the poll.' );
		$this->assertStringContainsString( 'There are a total of <strong>3</strong>', $html, 'Totals the votes cast.' );
		$this->assertStringContainsString( '<strong>1</strong> vote is cast by registered users', $html, 'Says how many came from registered users.' );
		$this->assertStringContainsString( 'Registered Voter', $html, 'And names them.' );
	}

	/**
	 * A voter name carrying an apostrophe survives the log round trip.
	 *
	 * The pollip_user column used to be written straight from the slashed comment
	 * cookie and unslashed again on read, so the two sides had to agree.
	 *
	 * @return void
	 */
	public function test_logs_view_renders_voter_name_with_apostrophe() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Apostrophe poll' ) );
		$answers = $this->answer_ids( $poll_id );

		$this->make_vote_log( $poll_id, $answers[0], "O'Brien" );

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertStringContainsString( 'O&#039;Brien', $html, 'An apostrophe in a voter name is escaped once.' );
		$this->assertStringNotContainsString( "O\\'Brien", $html, 'And is not left slashed as it came out of the database.' );
	}

	/**
	 * Editing a poll renders its answers into the form.
	 *
	 * @return void
	 */
	public function test_edit_mode_renders_existing_answers() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Editable poll' ),
			array( array( 'Alpha', 2 ), array( 'Beta', 5 ) )
		);

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'Editable poll', $html, 'Edit mode renders the question.' );
		$this->assertStringContainsString( 'Alpha', $html, 'The first existing answer.' );
		$this->assertStringContainsString( 'Beta', $html, 'And the second.' );
	}

	/**
	 * The Settings tab offers exactly the bar styles the sanitizer accepts.
	 *
	 * These two lists were computed separately and disagreed: the screen
	 * required a pollbg.gif and the sanitizer took any directory, so a style the
	 * UI offered could be silently rejected on save.
	 *
	 * @return void
	 */
	public function test_options_offers_only_saveable_bar_styles() {
		$html = $this->render_admin_page( 'settings' );

		$styles = WP_Polls_Settings::bar_styles();
		$this->assertNotEmpty( $styles, 'no bar styles found on disk' );

		foreach ( $styles as $style ) {
			$this->assertStringContainsString(
				'value="' . esc_attr( $style ) . '"',
				$html,
				$style . ' is accepted on save but not offered'
			);
		}
	}

	/**
	 * The two bar colours are picked, not typed.
	 *
	 * A colour input only accepts a full six digit value with its '#', so the
	 * setting - stored without one - has to be prefixed on the way out.
	 *
	 * @return void
	 */
	public function test_options_renders_the_bar_colours_as_colour_inputs() {
		WP_Polls_Options::set( 'bar.background', 'aabbcc' );
		WP_Polls_Options::set( 'bar.border', 'ddeeff' );

		$html = $this->render_admin_page( 'settings' );

		$this->assertMatchesRegularExpression( '/<input type="color" id="poll_bar_bg"[^>]*value="#aabbcc"/', $html, 'The stored background colour renders as a colour input carrying that value.' );
		$this->assertMatchesRegularExpression( '/<input type="color" id="poll_bar_border"[^>]*value="#ddeeff"/', $html, 'The stored border colour renders as a colour input carrying that value.' );
	}

	/**
	 * What the colour input posts is stored, without its '#'.
	 *
	 * @return void
	 */
	public function test_options_stores_what_the_colour_input_posts() {
		$saved = WP_Polls_Settings::sanitize( array( 'bar' => array( 'background' => '#123456' ) ) );

		$this->assertSame( '123456', $saved['bar']['background'], 'The colour is stored as the six digits the input posts, without the hash.' );
	}

	/**
	 * The saved bar style comes back checked.
	 *
	 * @return void
	 */
	public function test_options_checks_the_saved_bar_style() {
		$styles = WP_Polls_Settings::bar_styles();
		$style  = end( $styles );
		WP_Polls_Options::set( 'bar.style', $style );

		$html = $this->render_admin_page( 'settings' );

		$this->assertMatchesRegularExpression(
			'/id="poll_bar_style-' . preg_quote( $style, '/' ) . '"[^>]*\schecked/',
			$html,
			'The saved bar style ' . $style . ' is not the radio marked checked.'
		);
	}

	/**
	 * Add Poll renders the answer fields and the form target.
	 *
	 * @return void
	 */
	public function test_add_renders_answer_fields() {
		$html = $this->render_admin_page( 'add' );

		$this->assertStringContainsString( 'name="polla_answers[]"', $html, 'The add screen renders the answer fields.' );
		$this->assertStringContainsString( 'name="pollq_question"', $html, 'And the question field.' );
	}

	/**
	 * The Templates tab posts to options.php with the right settings group.
	 *
	 * An empty or wrong option_page makes the Settings API reject the save with
	 * a 403, which looks like a permissions problem rather than a typo.
	 *
	 * @return void
	 */
	public function test_templates_posts_to_the_right_settings_group() {
		$html = $this->render_admin_page( 'settings', array( 'tab' => 'templates' ) );

		$this->assertStringContainsString( 'action="options.php"', $html, 'The templates screen posts to options.php, so core handles the save.' );
		$this->assertStringContainsString( "value='" . WP_Polls_Settings::GROUP . "'", $html, 'Naming the settings group its fields are registered in.' );
	}

	/**
	 * The Settings tab posts to options.php with the right settings group.
	 *
	 * @return void
	 */
	public function test_options_posts_to_the_right_settings_group() {
		$html = $this->render_admin_page( 'settings' );

		$this->assertStringContainsString( 'action="options.php"', $html, 'The options screen posts to options.php too.' );
		$this->assertStringContainsString( "value='" . WP_Polls_Settings::GROUP . "'", $html, 'Naming the same settings group.' );
	}

	/**
	 * Manage Polls renders through WP_List_Table.
	 *
	 * @return void
	 */
	public function test_manage_renders_a_list_table_with_row_actions() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Listed poll' ) );

		$html = $this->render_admin_page( 'manage' );

		$this->assertStringContainsString( 'class="wp-list-table', $html, 'The manage screen is a core list table.' );
		$this->assertStringContainsString( '<tr id="poll-' . $poll_id . '"', $html, 'With a row per poll, addressable by id.' );
		$this->assertStringContainsString( 'class="row-actions"', $html, 'Carrying the core row actions.' );
		$this->assertStringContainsString( 'data-poll-action="delete-poll"', $html, 'Whose action is data for the script rather than an inline handler.' );
	}

	/**
	 * The list is paginated, and the second page holds the remainder.
	 *
	 * The screen used to select every poll in one query and print them all.
	 *
	 * @return void
	 */
	public function test_manage_paginates_the_polls() {
		for ( $i = 1; $i <= WP_Polls_List_Table::PER_PAGE + 3; $i++ ) {
			$this->make_poll(
				array(
					'pollq_question'  => 'Poll ' . $i,
					'pollq_timestamp' => 1000000000 + $i,
				)
			);
		}

		$first  = $this->render_admin_page( 'manage' );
		$second = $this->render_admin_page( 'manage', array( 'paged' => '2' ) );

		$this->assertSame( WP_Polls_List_Table::PER_PAGE, substr_count( $first, '<tr id="poll-' ), 'The first page holds a full page of rows.' );
		$this->assertSame( 3, substr_count( $second, '<tr id="poll-' ), 'And the second holds the remainder.' );
	}

	/**
	 * The stats under the list count every poll, not just the page shown.
	 *
	 * They were accumulated while printing rows, which stops being the whole
	 * table the moment the list is paginated.
	 *
	 * @return void
	 */
	public function test_manage_stats_cover_every_page() {
		for ( $i = 1; $i <= WP_Polls_List_Table::PER_PAGE + 2; $i++ ) {
			$this->make_poll(
				array( 'pollq_question' => 'Poll ' . $i ),
				array( array( 'Yes', 2 ), array( 'No', 1 ) )
			);
		}

		$stats = WP_Polls_List_Table::stats();

		$this->assertSame( WP_Polls_List_Table::PER_PAGE + 2, $stats['polls'], 'The stats count every poll, not just the page on screen.' );
		$this->assertSame( ( WP_Polls_List_Table::PER_PAGE + 2 ) * 2, $stats['answers'], 'Every answer.' );
		$this->assertSame( ( WP_Polls_List_Table::PER_PAGE + 2 ) * 3, $stats['votes'], 'And every vote.' );
	}

	/**
	 * The vote fields carry the class the admin script totals them by.
	 *
	 * The script used to find them with input[size="4"], which tied the running
	 * total to a presentational attribute.
	 *
	 * @return void
	 */
	public function test_edit_answers_carry_the_votes_class() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Counted poll' ),
			array( array( 'Alpha', 2 ), array( 'Beta', 5 ) )
		);
		$answers = $this->answer_ids( $poll_id );

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			)
		);

		foreach ( $answers as $answer_id ) {
			$this->assertMatchesRegularExpression(
				'/<input[^>]*class="wp-polls-votes"[^>]*name="polla_votes-' . $answer_id . '"/',
				$html,
				'The votes field for answer ' . $answer_id . ' is missing its class.'
			);
		}
	}

	/**
	 * The logs of a multiple answer poll render clean.
	 *
	 * Its two extra filters were initialised inside the branch that handles
	 * their own submission, so opening the screen warned about each of them.
	 *
	 * @return void
	 */
	public function test_logs_view_of_a_multiple_answer_poll_is_clean() {
		$poll_id = $this->make_poll(
			array(
				'pollq_question' => 'Pick two',
				'pollq_multiple' => 2,
			),
			array( array( 'Alpha', 1 ), array( 'Beta', 1 ) )
		);
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0], 'Registered Voter', 7 );

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'name="num_choices_sign"', $html, 'A multiple answer poll gets the choice count filter.' );
	}

	/**
	 * The log names its answers even when the filter panel is turned off.
	 *
	 * The answer id to answer text map was built while printing the filter
	 * dropdown, so hiding the filters left every group header blank.
	 *
	 * @return void
	 */
	public function test_logs_view_names_answers_without_the_filter_panel() {
		$poll_id = $this->make_poll(
			array( 'pollq_question' => 'Hidden filters' ),
			array( array( 'Distinctive answer', 1 ) )
		);
		$answers = $this->answer_ids( $poll_id );
		$this->make_vote_log( $poll_id, $answers[0], 'Guest' );

		add_filter( 'wp_polls_log_show_log_filter', '__return_false' );
		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);
		remove_filter( 'wp_polls_log_show_log_filter', '__return_false' );

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringNotContainsString( 'name="users_voted_for"', $html, 'Without the answer filter panel, which this poll has no use for.' );
		$this->assertStringContainsString( 'Distinctive answer', $html, 'The answers are still named.' );
	}

	/**
	 * A vote logged against no answer at all is labelled, not warned about.
	 *
	 * @return void
	 */
	public function test_logs_view_labels_a_null_vote() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Null vote poll' ) );
		$this->make_vote_log( $poll_id, 0, 'Guest' );

		$html = $this->render_admin_page(
			'manage',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'Null Votes', $html, 'A vote for no answer is labelled rather than left blank.' );
	}

	/**
	 * The settings screen writes no form markup of its own.
	 *
	 * It is meant to be nothing but the tab strip, settings_fields(),
	 * do_settings_sections() and submit_button(), with every row declared in
	 * WP_Polls_Settings. A table or an input appearing in the file means a field
	 * has been added outside the Settings API, where the sanitiser and the
	 * section ordering cannot see it.
	 *
	 * @return void
	 */
	public function test_the_settings_screen_contains_no_hand_written_form_markup() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file from the plugin under test, not a remote resource.
		$source = file_get_contents( WP_POLLS_DIR . 'includes/class-wp-polls-screen-settings.php' );

		foreach ( array( '<table', '<tr', '<th', '<td', '<input', '<select', '<textarea' ) as $tag ) {
			$this->assertStringNotContainsString( $tag, $source, 'the settings screen writes ' . $tag . ' by hand' );
		}
	}

	/**
	 * The two settings groups are tabs of one page, not two menu entries.
	 *
	 * @return void
	 */
	public function test_the_settings_screen_renders_a_tab_for_each_group() {
		$html = $this->render_admin_page( 'settings', array( 'tab' => 'templates' ) );

		$this->assertStringContainsString( 'nav-tab-wrapper', $html, 'The tab strip is core markup.' );

		foreach ( WP_Polls_Settings::tabs() as $tab => $label ) {
			$this->assertStringContainsString( esc_html( $label ), $html, $tab . ' has no tab' );
			$this->assertStringContainsString( 'tab=' . $tab, $html, $tab . ' has no link' );
		}

		$this->assertStringContainsString( 'nav-tab-active', $html, 'The current tab is marked active.' );
	}

	/**
	 * Every control on both tabs posts into the plugin's own option.
	 *
	 * A field named anything else is either dropped on save or written to a row
	 * nothing reads, and both failures are silent.
	 *
	 * @return void
	 */
	public function test_both_tabs_only_post_into_the_option() {
		// settings_fields() and submit_button() contribute these.
		$allowed = array( '_wpnonce', '_wp_http_referer', 'option_page', 'action', 'submit' );

		foreach ( array_keys( WP_Polls_Settings::tabs() ) as $tab ) {
			$html = $this->render_admin_page( 'settings', array( 'tab' => $tab ) );

			preg_match_all( '/\sname="([^"]+)"/', $html, $matches );
			$this->assertNotEmpty( $matches[1], $tab . ' rendered no fields' );

			foreach ( $matches[1] as $name ) {
				if ( in_array( $name, $allowed, true ) ) {
					continue;
				}

				$this->assertStringStartsWith( WP_Polls_Options::OPTION . '[', $name, $tab );
			}
		}
	}

	/**
	 * The Options tab renders every section it registers.
	 *
	 * @return void
	 */
	public function test_options_renders_every_registered_section() {
		global $wp_settings_sections;

		$html = $this->render_admin_page( 'settings' );

		foreach ( $wp_settings_sections[ WP_Polls_Settings::tab_bucket( WP_Polls_Settings::TAB_OPTIONS ) ] as $section ) {
			// Matched loosely on purpose: do_settings_sections() gained an id
			// attribute on the heading in WP 6.6, so a literal <h2> only holds on
			// the older end of the versions this plugin supports.
			$this->assertMatchesRegularExpression(
				'/<h2[^>]*>' . preg_quote( $section['title'], '/' ) . '<\/h2>/',
				$html,
				'The ' . $section['title'] . ' section is registered but never rendered.'
			);
		}
	}

	/**
	 * The template editor shows the stored templates rather than the defaults.
	 *
	 * @return void
	 */
	public function test_templates_renders_stored_template() {
		WP_Polls_Options::set( 'templates.voteheader', '<p>CUSTOM HEADER</p>' );

		$html = $this->render_admin_page( 'settings', array( 'tab' => 'templates' ) );

		$this->assertStringContainsString( 'CUSTOM HEADER', $html, 'The templates screen renders the stored template, not the default.' );
	}
}
