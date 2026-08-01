<?php
/**
 * Shared fixture helpers.
 *
 * The plugin owns three custom tables, so every test that touches polls needs
 * a deterministic set of rows. WP_UnitTestCase wraps each test in a
 * transaction and rolls back, but only for tables it knows about, so these
 * helpers clear and reseed explicitly.
 *
 * @package WP-Polls
 */

/**
 * Base class carrying the poll fixture.
 */
abstract class WP_Polls_TestCase extends WP_UnitTestCase {

	/**
	 * Empty the three poll tables and reset the option row.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->pollsq}" );
		$wpdb->query( "DELETE FROM {$wpdb->pollsa}" );
		$wpdb->query( "DELETE FROM {$wpdb->pollsip}" );

		delete_option( WP_Polls_Options::OPTION );
		WP_Polls_Options::flush();
		WP_Polls_Options::save( WP_Polls_Options::defaults() );
		// Both markers up to date, so a test that does not care about upgrading
		// starts from a fully migrated install rather than one the next call to
		// WP_Polls_Install::upgrade() would migrate underneath it.
		WP_Polls_Options::save_markers( WP_POLLS_VERSION, WP_POLLS_DB_VERSION );
	}

	/**
	 * Run the uninstaller's option work, repeatably.
	 *
	 * Deliberately not a require of uninstall.php. That file calls
	 * WP_Polls_Install::uninstall_site(), which drops the three poll tables -
	 * DDL, so MySQL commits it and it survives the transaction the rest of the
	 * suite is wrapped in. One test would then take the tables away from every
	 * test after it. The deletions are performed here instead, over the same
	 * WP_Polls_Install::option_names() list the uninstaller loops over, so a row
	 * missing from that list still fails the shared uninstall test. That
	 * uninstall.php itself calls into this list, once per site on a network, is
	 * asserted against the source in test-uninstall.php.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		foreach ( WP_Polls_Install::option_names() as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Insert a poll and its answers.
	 *
	 * @param array $args    Poll column overrides.
	 * @param array $answers List of [ answer text, votes ] pairs.
	 * @return int Poll ID.
	 */
	protected function make_poll( $args = array(), $answers = array( array( 'Yes', 0 ), array( 'No', 0 ) ) ) {
		global $wpdb;

		$args = array_merge(
			array(
				'pollq_question'    => 'Test question',
				'pollq_timestamp'   => 1000000000,
				'pollq_totalvotes'  => 0,
				'pollq_active'      => 1,
				'pollq_expiry'      => 0,
				'pollq_multiple'    => 0,
				'pollq_totalvoters' => 0,
			),
			$args
		);

		$wpdb->insert( $wpdb->pollsq, $args );
		$poll_id = (int) $wpdb->insert_id;

		$total = 0;
		foreach ( $answers as $answer ) {
			$wpdb->insert(
				$wpdb->pollsa,
				array(
					'polla_qid'     => $poll_id,
					'polla_answers' => $answer[0],
					'polla_votes'   => $answer[1],
				)
			);
			$total += $answer[1];
		}

		if ( $total > 0 && 0 === (int) $args['pollq_totalvotes'] ) {
			$wpdb->update(
				$wpdb->pollsq,
				array(
					'pollq_totalvotes'  => $total,
					'pollq_totalvoters' => $total,
				),
				array( 'pollq_id' => $poll_id )
			);
		}

		return $poll_id;
	}

	/**
	 * PHP diagnostics raised by the last render_admin_page() call.
	 *
	 * @var array
	 */
	protected $admin_page_notices = array();

	/**
	 * Render one of the plugin's admin screens and return its HTML.
	 *
	 * The screens read $month and $wp_locale, which wp-admin has in scope by the
	 * time a page callback runs, so this helper pulls the same globals in first;
	 * without that they would silently render half a page.
	 *
	 * Every notice, warning and deprecation raised while the screen runs is
	 * collected into $admin_page_notices so a test can assert the page is clean
	 * under PHP 8 rather than only that it produced some output.
	 *
	 * @param string $screen Screen class suffix: 'manage', 'add' or 'settings'.
	 * @param array  $get    $_GET for the request.
	 * @param array  $post   $_POST for the request.
	 * @return string
	 */
	protected function render_admin_page( $screen, $get = array(), $post = array() ) {
		global $wpdb, $month, $wp_locale, $hook_suffix;

		$this->admin_page_notices = array();

		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = array_merge( $get, $post );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Collecting PHP diagnostics is the point of this helper.
		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				$this->admin_page_notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				return true;
			}
		);

		// wp-admin fires admin_init before it reaches a plugin's page callback, so
		// by the time either tab runs its sections and fields are registered.
		// Calling the screen directly skips that, and do_settings_sections()
		// would then render an empty screen.
		WP_Polls_Settings::register();

		$depth = ob_get_level();
		$html  = '';

		try {
			ob_start();
			call_user_func( array( 'WP_Polls_Admin', 'render_' . $screen ) );
		} finally {
			// check_admin_referer() calls wp_die(), which throws out of the
			// require, so the buffer has to be closed here or it leaks into the
			// next test and PHPUnit reports the run as risky.
			while ( ob_get_level() > $depth ) {
				$html = ob_get_clean() . $html;
			}
			restore_error_handler();
			$_GET     = array();
			$_POST    = array();
			$_REQUEST = array();
		}

		return $html;
	}

	/**
	 * Give the current user the plugin's capability.
	 *
	 * The admin pages call die() when the check fails, which would take the test
	 * runner with it, so every render test has to be an administrator.
	 *
	 * @return int User ID.
	 */
	protected function become_poll_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Record a vote in the log table.
	 *
	 * The logs screen counts rows here, not polla_votes, so a poll can show
	 * answer totals and still report no recorded votes.
	 *
	 * @param int    $poll_id   Poll ID.
	 * @param int    $answer_id Answer ID.
	 * @param string $user      Display name recorded against the vote.
	 * @param int    $user_id   User ID, 0 for a guest or comment author.
	 * @return void
	 */
	protected function make_vote_log( $poll_id, $answer_id, $user = 'Guest', $user_id = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->pollsip,
			array(
				'pollip_qid'       => $poll_id,
				'pollip_aid'       => $answer_id,
				'pollip_ip'        => '127.0.0.1',
				'pollip_host'      => 'localhost',
				'pollip_timestamp' => 1000000000,
				'pollip_user'      => $user,
				'pollip_userid'    => $user_id,
			)
		);
	}

	/**
	 * The answer IDs of a poll, in insertion order.
	 *
	 * @param int $poll_id Poll ID.
	 * @return array
	 */
	protected function answer_ids( $poll_id ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM {$wpdb->pollsa} WHERE polla_qid = %d ORDER BY polla_aid ASC", $poll_id ) )
		);
	}
}
