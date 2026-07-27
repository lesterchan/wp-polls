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
 * @covers Polls_Admin
 */
class Test_Polls_Admin_Pages extends WP_Polls_TestCase {

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->become_poll_admin();
	}

	/**
	 * Every admin view, as [ file, $_GET ].
	 *
	 * Manage Polls appears three times because its edit and logs branches are
	 * separate screens sharing one file, and each renders markup the list view
	 * never touches. Covering only the default view is how an early version of
	 * these tests missed a translators comment injected into the edit branch.
	 *
	 * The id placeholder is replaced with the seeded poll's real id.
	 *
	 * @return array
	 */
	public function admin_page_provider() {
		return array(
			'manage polls' => array( 'polls-manager.php', array() ),
			'edit poll'    => array(
				'polls-manager.php',
				array(
					'mode' => 'edit',
					'id'   => '%POLL_ID%',
				),
			),
			'poll logs'    => array(
				'polls-manager.php',
				array(
					'mode' => 'logs',
					'id'   => '%POLL_ID%',
				),
			),
			'add poll'     => array( 'polls-add.php', array() ),
			'poll options' => array( 'polls-options.php', array() ),
			'templates'    => array( 'polls-templates.php', array() ),
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
	 * @param string $file File name.
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
	 * @param string $file File name.
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
	 * @param string $file File name.
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

		foreach ( array( 'polls-manager.php', 'polls-options.php' ) as $file ) {
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

		$html = $this->render_admin_page( 'polls-manager.php' );

		$this->assertStringContainsString( 'First poll', $html );
		$this->assertStringContainsString( 'Second poll', $html );
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
			'polls-manager.php',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'Logged poll', $html );
		$this->assertStringContainsString( 'There are a total of <strong>3</strong>', $html );
		$this->assertStringContainsString( '<strong>1</strong> vote is cast by registered users', $html );
		$this->assertStringContainsString( 'Registered Voter', $html );
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
			'polls-manager.php',
			array(
				'mode' => 'logs',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertStringContainsString( 'O&#039;Brien', $html );
		$this->assertStringNotContainsString( "O\\'Brien", $html );
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
			'polls-manager.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $poll_id,
			)
		);

		$this->assertSame( array(), $this->admin_page_notices, implode( ' | ', $this->admin_page_notices ) );
		$this->assertStringContainsString( 'Editable poll', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringContainsString( 'Beta', $html );
	}

	/**
	 * Poll Options offers exactly the bar styles the sanitizer accepts.
	 *
	 * These two lists were computed separately and disagreed: the screen
	 * required a pollbg.gif and the sanitizer took any directory, so a style the
	 * UI offered could be silently rejected on save.
	 *
	 * @return void
	 */
	public function test_options_offers_only_saveable_bar_styles() {
		$html = $this->render_admin_page( 'polls-options.php' );

		$styles = Polls_Settings::bar_styles();
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
	 * The saved bar style comes back checked.
	 *
	 * @return void
	 */
	public function test_options_checks_the_saved_bar_style() {
		$styles = Polls_Settings::bar_styles();
		$style  = end( $styles );
		Polls_Options::set( 'bar.style', $style );

		$html = $this->render_admin_page( 'polls-options.php' );

		$this->assertMatchesRegularExpression(
			'/id="poll_bar_style-' . preg_quote( $style, '/' ) . '"[^>]*\schecked/',
			$html
		);
	}

	/**
	 * Add Poll renders the answer fields and the form target.
	 *
	 * @return void
	 */
	public function test_add_renders_answer_fields() {
		$html = $this->render_admin_page( 'polls-add.php' );

		$this->assertStringContainsString( 'name="polla_answers[]"', $html );
		$this->assertStringContainsString( 'name="pollq_question"', $html );
	}

	/**
	 * Poll Templates posts to options.php with the right settings group.
	 *
	 * An empty or wrong option_page makes the Settings API reject the save with
	 * a 403, which looks like a permissions problem rather than a typo.
	 *
	 * @return void
	 */
	public function test_templates_posts_to_the_right_settings_group() {
		$html = $this->render_admin_page( 'polls-templates.php' );

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( "value='" . Polls_Settings::GROUP . "'", $html );
	}

	/**
	 * Poll Options posts to options.php with the right settings group.
	 *
	 * @return void
	 */
	public function test_options_posts_to_the_right_settings_group() {
		$html = $this->render_admin_page( 'polls-options.php' );

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( "value='" . Polls_Settings::GROUP . "'", $html );
	}

	/**
	 * Neither settings screen writes its own form markup.
	 *
	 * Both are meant to be nothing but settings_fields(), do_settings_sections()
	 * and submit_button(), with every row declared in Polls_Settings. A table or
	 * an input appearing in either file means a field has been added outside the
	 * Settings API, where the sanitiser and the section ordering cannot see it.
	 *
	 * @return void
	 */
	public function test_settings_screens_contain_no_hand_written_form_markup() {
		foreach ( array( 'polls-options.php', 'polls-templates.php' ) as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file from the plugin under test, not a remote resource.
			$source = file_get_contents( WP_POLLS_DIR . $file );

			foreach ( array( '<table', '<tr', '<th', '<td', '<input', '<select', '<textarea' ) as $tag ) {
				$this->assertStringNotContainsString( $tag, $source, $file . ' writes ' . $tag . ' by hand' );
			}
		}
	}

	/**
	 * Every control on both screens posts into the plugin's own option.
	 *
	 * A field named anything else is either dropped on save or written to a row
	 * nothing reads, and both failures are silent.
	 *
	 * @return void
	 */
	public function test_settings_screens_only_post_into_the_option() {
		// settings_fields() and submit_button() contribute these.
		$allowed = array( '_wpnonce', '_wp_http_referer', 'option_page', 'action', 'submit' );

		foreach ( array( 'polls-options.php', 'polls-templates.php' ) as $file ) {
			$html = $this->render_admin_page( $file );

			preg_match_all( '/\sname="([^"]+)"/', $html, $matches );
			$this->assertNotEmpty( $matches[1], $file . ' rendered no fields' );

			foreach ( $matches[1] as $name ) {
				if ( in_array( $name, $allowed, true ) ) {
					continue;
				}

				$this->assertStringStartsWith( Polls_Options::OPTION . '[', $name, $file );
			}
		}
	}

	/**
	 * The Poll Options screen renders every section it registers.
	 *
	 * @return void
	 */
	public function test_options_renders_every_registered_section() {
		global $wp_settings_sections;

		$html = $this->render_admin_page( 'polls-options.php' );

		foreach ( $wp_settings_sections[ Polls_Settings::PAGE_OPTIONS ] as $section ) {
			$this->assertStringContainsString( '<h2>' . $section['title'] . '</h2>', $html );
		}
	}

	/**
	 * The template editor shows the stored templates rather than the defaults.
	 *
	 * @return void
	 */
	public function test_templates_renders_stored_template() {
		Polls_Options::set( 'templates.voteheader', '<p>CUSTOM HEADER</p>' );

		$html = $this->render_admin_page( 'polls-templates.php' );

		$this->assertStringContainsString( 'CUSTOM HEADER', $html );
	}
}
