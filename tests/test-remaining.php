<?php
/**
 * Tests for the archive, the widget, the cron job and the upgrade notice.
 *
 * @package WP-Polls
 */

/**
 * The last few entry points with no coverage of their own.
 *
 * @covers WP_Polls_Display::polls_archive
 * @covers WP_Polls_Widget
 * @covers WP_Polls::cron_polls_status
 * @covers WP_Polls_Install::templates_have_onclick
 */
class WP_Polls_Remaining_Test extends WP_Polls_TestCase {

	// --- the archive ------------------------------------------------------

	/**
	 * The archive lists polls and links to each one's results.
	 *
	 * @return void
	 */
	public function test_archive_lists_polls() {
		$this->make_poll( array( 'pollq_question' => 'Archive poll one' ) );
		$this->make_poll( array( 'pollq_question' => 'Archive poll two' ) );
		WP_Polls_Options::set( 'archive.display_poll', 3 );

		$html = WP_Polls_Display::polls_archive();

		$this->assertStringContainsString( 'Archive poll one', $html );
		$this->assertStringContainsString( 'Archive poll two', $html );
	}

	/**
	 * A poll that has not opened yet is never archived.
	 *
	 * @return void
	 */
	public function test_archive_hides_scheduled_polls() {
		$this->make_poll( array( 'pollq_question' => 'Published poll' ) );
		$this->make_poll(
			array(
				'pollq_question' => 'Scheduled poll',
				'pollq_active'   => -1,
			)
		);
		WP_Polls_Options::set( 'archive.display_poll', 3 );

		$html = WP_Polls_Display::polls_archive();

		$this->assertStringContainsString( 'Published poll', $html );
		$this->assertStringNotContainsString( 'Scheduled poll', $html );
	}

	/**
	 * "Closed polls only" and "open polls only" filter the list.
	 *
	 * @return void
	 */
	public function test_archive_respects_the_display_type() {
		$this->make_poll(
			array(
				'pollq_question' => 'An open poll',
				'pollq_active'   => 1,
			)
		);
		$this->make_poll(
			array(
				'pollq_question' => 'A closed poll',
				'pollq_active'   => 0,
			)
		);

		WP_Polls_Options::set( 'archive.display_poll', 1 );
		$closed_only = WP_Polls_Display::polls_archive();
		$this->assertStringContainsString( 'A closed poll', $closed_only );
		$this->assertStringNotContainsString( 'An open poll', $closed_only );

		WP_Polls_Options::set( 'archive.display_poll', 2 );
		$open_only = WP_Polls_Display::polls_archive();
		$this->assertStringContainsString( 'An open poll', $open_only );
		$this->assertStringNotContainsString( 'A closed poll', $open_only );
	}

	/**
	 * A question carrying markup is not escaped twice on the archive.
	 *
	 * @return void
	 */
	public function test_archive_does_not_double_escape() {
		$this->make_poll( array( 'pollq_question' => 'Tabs & "spaces"' ) );
		WP_Polls_Options::set( 'archive.display_poll', 3 );

		$html = WP_Polls_Display::polls_archive();

		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	// --- the widget -------------------------------------------------------

	/**
	 * The widget renders the poll it was configured with.
	 *
	 * @return void
	 */
	public function test_widget_renders_the_configured_poll() {
		$poll_id = $this->make_poll( array( 'pollq_question' => 'Widget poll' ) );

		$widget = new WP_Polls_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title'               => 'Polls',
				'poll_id'             => $poll_id,
				'display_pollarchive' => 0,
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<aside>', $html );
		$this->assertStringContainsString( '<h2>Polls</h2>', $html );
		$this->assertStringContainsString( 'Widget poll', $html );
		$this->assertStringContainsString( '</aside>', $html );
	}

	/**
	 * With no title the heading is left out entirely.
	 *
	 * @return void
	 */
	public function test_widget_omits_an_empty_title() {
		$poll_id = $this->make_poll();

		$widget = new WP_Polls_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title'               => '',
				'poll_id'             => $poll_id,
				'display_pollarchive' => 0,
			)
		);
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<h2>', $html );
	}

	/**
	 * Saving the widget form keeps the settings and casts the ids.
	 *
	 * @return void
	 */
	public function test_widget_update_casts_its_settings() {
		$widget = new WP_Polls_Widget();

		$saved = $widget->update(
			array(
				'submit'              => '1',
				'title'               => 'My <b>polls</b>',
				'poll_id'             => '7',
				'display_pollarchive' => '1',
			),
			array()
		);

		$this->assertSame( 'My polls', $saved['title'] );
		$this->assertSame( 7, $saved['poll_id'] );
		$this->assertSame( 1, $saved['display_pollarchive'] );
	}

	/**
	 * A form that was not submitted leaves the old settings alone.
	 *
	 * @return void
	 */
	public function test_widget_update_without_submit_keeps_the_old_instance() {
		$widget = new WP_Polls_Widget();

		$this->assertFalse( $widget->update( array( 'title' => 'New' ), array( 'title' => 'Old' ) ) );
	}

	// --- the cron job -----------------------------------------------------

	/**
	 * A poll past its expiry is closed.
	 *
	 * @return void
	 */
	public function test_cron_closes_an_expired_poll() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array(
				'pollq_question' => 'Expired',
				'pollq_active'   => 1,
				'pollq_expiry'   => WP_Polls::now() - HOUR_IN_SECONDS,
			)
		);

		WP_Polls::cron_polls_status();

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	/**
	 * A poll with no expiry is left open.
	 *
	 * @return void
	 */
	public function test_cron_leaves_a_poll_with_no_expiry_open() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array(
				'pollq_active' => 1,
				'pollq_expiry' => 0,
			)
		);

		WP_Polls::cron_polls_status();

		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	/**
	 * A scheduled poll whose start time has arrived is opened.
	 *
	 * @return void
	 */
	public function test_cron_opens_a_due_poll() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array(
				'pollq_question'  => 'Due',
				'pollq_active'    => -1,
				'pollq_timestamp' => WP_Polls::now() - HOUR_IN_SECONDS,
			)
		);

		WP_Polls::cron_polls_status();

		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	/**
	 * A poll scheduled for later stays closed.
	 *
	 * @return void
	 */
	public function test_cron_leaves_a_future_poll_scheduled() {
		global $wpdb;

		$poll_id = $this->make_poll(
			array(
				'pollq_active'    => -1,
				'pollq_timestamp' => WP_Polls::now() + DAY_IN_SECONDS,
			)
		);

		WP_Polls::cron_polls_status();

		$this->assertSame(
			-1,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM {$wpdb->pollsq} WHERE pollq_id = %d", $poll_id ) )
		);
	}

	/**
	 * Opening a due poll refreshes the recorded latest poll.
	 *
	 * @return void
	 */
	public function test_cron_updates_the_latest_poll() {
		$poll_id = $this->make_poll(
			array(
				'pollq_active'    => -1,
				'pollq_timestamp' => WP_Polls::now() - HOUR_IN_SECONDS,
			)
		);

		WP_Polls_Options::set( 'latest_poll', 0 );
		WP_Polls::cron_polls_status();

		$this->assertSame( $poll_id, (int) WP_Polls_Options::get( 'latest_poll' ) );
	}

	// --- the upgrade notice ----------------------------------------------

	/**
	 * A template still carrying an onclick handler is detected.
	 *
	 * @return void
	 */
	public function test_onclick_left_in_a_template_is_detected() {
		WP_Polls_Options::set( 'templates.votefooter', '<a onclick="poll_vote(1)">Vote</a>' );

		$this->assertTrue( WP_Polls_Install::templates_have_onclick() );
	}

	/**
	 * Converted templates are not flagged.
	 *
	 * @return void
	 */
	public function test_converted_templates_are_not_flagged() {
		WP_Polls_Options::set( 'templates.votefooter', '<a data-poll-action="vote">Vote</a>' );
		WP_Polls_Options::set( 'templates.resultfooter2', '<a data-poll-action="booth">Vote</a>' );

		$this->assertFalse( WP_Polls_Install::templates_have_onclick() );
	}

	/**
	 * The second template is checked too, not just the first.
	 *
	 * @return void
	 */
	public function test_the_result_footer_is_checked_as_well() {
		WP_Polls_Options::set( 'templates.votefooter', '<a data-poll-action="vote">Vote</a>' );
		WP_Polls_Options::set( 'templates.resultfooter2', '<a onClick="poll_booth(1)">Vote</a>' );

		$this->assertTrue( WP_Polls_Install::templates_have_onclick() );
	}
}
