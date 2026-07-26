<?php
/**
 * Installation, activation and version upgrades.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the tables, seeds the options and runs version gated upgrades.
 */
class Polls_Install {

	/**
	 * Schema version.
	 *
	 * Bump this whenever the CREATE TABLE statements or the indexes change.
	 * Nothing else needs to change: the schema work then runs once on the next
	 * load and records the new version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Option holding the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'poll_db_version';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( WP_POLLS_MAIN_FILE, array( __CLASS__, 'activation' ) );
		add_action( 'admin_init', array( __CLASS__, 'upgrade' ) );
		add_action( 'admin_notices', array( __CLASS__, 'onclick_notice' ) );
	}

	// Function: Activate Plugin.

	/**
	 * Activation.
	 *
	 * @param mixed $network_wide Value.
	 *
	 * @return mixed
	 */
	public static function activation( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// wp_get_sites() was removed in WP 5.1, so network activation has
			// been a fatal error since then. get_sites() replaces it and
			// returns WP_Site objects rather than arrays. 'number' => 0 lifts
			// the default limit of 100 sites.
			$ms_sites = get_sites( array( 'number' => 0 ) );

			foreach ( $ms_sites as $ms_site ) {
				switch_to_blog( (int) $ms_site->blog_id );
				self::activate();
				restore_current_blog();
			}
		} else {
			self::activate();
		}
	}

	// Function: Run Version Specific Upgrades
	// Plugin updates do not fire the activation hook, so the stored version is
	// checked on every admin request and the outstanding upgrades are run once.

	/**
	 * Upgrade.
	 *
	 * @return mixed
	 */
	public static function upgrade() {
		$installed_version = get_option( 'poll_version' );
		$is_pre_3          = empty( $installed_version ) || version_compare( $installed_version, '3.0.0', '<' );

		// Version 3.0.0: fold the ~30 scattered option rows into a single one.
		// Must run before anything else that touches templates, so there is only
		// one place they live by the time the later steps read them.
		//
		// Gated on the stored shape as well as the version. 3.0.0 spent a while
		// unreleased on the development branch, so an install can be stamped
		// 3.0.0 and still hold the scattered rows; a version-only gate would
		// skip it and quietly drop that site to defaults. Checking for the
		// nested 'templates' key catches those, and the migration is a no-op
		// when there is nothing left to fold in.
		$stored = get_option( Polls_Options::OPTION, array() );
		if ( $is_pre_3 || ! is_array( $stored ) || ! isset( $stored['templates'] ) ) {
			Polls_Options::migrate_from_legacy_rows();
		}

		if ( WP_POLLS_VERSION === $installed_version ) {
			return;
		}

		// Version 3.0.0: Inline onclick handlers were replaced by data-poll-* attributes.
		if ( $is_pre_3 ) {
			self::upgrade_templates_onclick();
		}

		update_option( 'poll_version', WP_POLLS_VERSION );
	}

	/**
	 * Function: Convert Inline onclick Handlers In The Footer Templates To data-poll-* Attributes.
	 *
	 * @return mixed
	 */
	public static function upgrade_templates_onclick() {
		foreach ( array( 'votefooter', 'resultfooter2' ) as $key ) {
			$template = Polls_Options::get( 'templates.' . $key );

			if ( ! is_string( $template ) || stripos( $template, 'onclick' ) === false ) {
				continue;
			}

			// onclick="poll_result(%POLL_ID%); return false;" => data-poll-id="%POLL_ID%" data-poll-action="result".
			$migrated = preg_replace(
				'/onclick\s*=\s*\\\\?(["\'])\s*poll_(vote|result|booth)\s*\(\s*%POLL_ID%\s*\)\s*;?\s*(?:return\s+false\s*;?\s*)?\\\\?\1/i',
				'data-poll-id="%POLL_ID%" data-poll-action="$2"',
				$template
			);

			if ( null !== $migrated && $migrated !== $template ) {
				Polls_Options::set( 'templates.' . $key, $migrated );
			}
		}
	}

	// Function: Warn When A Poll Template Still Relies On An Inline onclick Handler
	// Since 3.0.0 the scripts export nothing, so an onclick left behind by a
	// customised template no longer calls anything at all. The upgrade converts
	// the stock templates automatically; this covers the ones too customised to
	// convert, which would otherwise fail silently on the front end.

	/**
	 * Onclick notice.
	 *
	 * @return mixed
	 */
	public static function onclick_notice() {
		global $hook_suffix;

		$poll_admin_pages = array( 'wp-polls/polls-manager.php', 'wp-polls/polls-add.php', 'wp-polls/polls-options.php', 'wp-polls/polls-templates.php' );
		if ( ! in_array( $hook_suffix, $poll_admin_pages, true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_polls' ) || ! self::templates_have_onclick() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post( __( '<strong>WP-Polls:</strong> one of your poll templates still uses an inline <code>onclick</code> handler. Inline handlers are no longer used, so the vote button or the result/vote links in that template will not do anything.', 'wp-polls' ) );
		echo '</p><p>';
		printf(
			/* translators: %s: value. */
			wp_kses_post( __( 'Open <a href="%s">Poll Templates</a> and press <strong>Restore Default Template</strong> on the Voting Form Footer and Result Footer, or replace the handler yourself with <code>data-poll-id="%%POLL_ID%%"</code> and <code>data-poll-action="vote"</code> (or <code>result</code> / <code>booth</code>).', 'wp-polls' ) ),
			esc_url( admin_url( 'admin.php?page=wp-polls/polls-templates.php' ) )
		);
		echo '</p></div>';
	}

	/**
	 * Check Whether Any Poll Template Still Contains An Inline onclick Handler.
	 *
	 * @return mixed
	 */
	public static function templates_have_onclick() {
		foreach ( array( 'votefooter', 'resultfooter2' ) as $key ) {
			$template = Polls_Options::get( 'templates.' . $key );

			if ( is_string( $template ) && stripos( $template, 'onclick' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Activate.
	 *
	 * @return mixed
	 */
	public static function activate() {
		global $wpdb;

		if ( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) ) {
			include_once ABSPATH . '/wp-admin/includes/upgrade.php';
		} elseif ( @is_file( ABSPATH . '/wp-admin/upgrade-functions.php' ) ) {
			include_once ABSPATH . '/wp-admin/upgrade-functions.php';
		} else {
			die( 'We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'' );
		}

		// Create Poll Tables (3 Tables).
		$charset_collate = $wpdb->get_charset_collate();

		$create_table            = array();
		$create_table['pollsq']  = "CREATE TABLE $wpdb->pollsq (" .
									'pollq_id int(10) NOT NULL auto_increment,' .
									"pollq_question varchar(200) character set utf8 NOT NULL default ''," .
									"pollq_timestamp varchar(20) NOT NULL default ''," .
									"pollq_totalvotes int(10) NOT NULL default '0'," .
									"pollq_active tinyint(1) NOT NULL default '1'," .
									"pollq_expiry int(10) NOT NULL default '0'," .
									"pollq_multiple tinyint(3) NOT NULL default '0'," .
									"pollq_totalvoters int(10) NOT NULL default '0'," .
									'PRIMARY KEY  (pollq_id)' .
									") $charset_collate;";
		$create_table['pollsa']  = "CREATE TABLE $wpdb->pollsa (" .
									'polla_aid int(10) NOT NULL auto_increment,' .
									"polla_qid int(10) NOT NULL default '0'," .
									"polla_answers varchar(200) character set utf8 NOT NULL default ''," .
									"polla_votes int(10) NOT NULL default '0'," .
									'PRIMARY KEY  (polla_aid)' .
									") $charset_collate;";
		$create_table['pollsip'] = "CREATE TABLE $wpdb->pollsip (" .
									'pollip_id int(10) NOT NULL auto_increment,' .
									"pollip_qid int(10) NOT NULL default '0'," .
									"pollip_aid int(10) NOT NULL default '0'," .
									"pollip_ip varchar(100) NOT NULL default ''," .
									"pollip_host VARCHAR(200) NOT NULL default ''," .
									"pollip_timestamp int(10) NOT NULL default '0'," .
									'pollip_user tinytext NOT NULL,' .
									"pollip_userid int(10) NOT NULL default '0'," .
									'PRIMARY KEY  (pollip_id),' .
									'KEY pollip_ip (pollip_ip),' .
									'KEY pollip_qid (pollip_qid),' .
									'KEY pollip_ip_qid (pollip_ip, pollip_qid)' .
									") $charset_collate;";
		// Only create tables that are missing. dbDelta against a table that
		// already exists re-diffs every column, and for pollq_id - int NOT NULL
		// auto_increment - it decides the column needs a default and emits
		// ALTER TABLE wp_pollsq ALTER COLUMN `pollq_id` SET DEFAULT ''
		// which MySQL rejects with "Invalid default value for 'pollq_id'". That
		// landed in the error log on every single activation. Schema changes
		// after the initial create are handled by the explicit index and column
		// work below, so nothing is lost by not re-diffing.
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			foreach ( $create_table as $table => $sql ) {
				$table_name = $wpdb->$table;
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
					dbDelta( $sql );
				}
			}
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
		// Check Whether It is Install Or Upgrade.
		$first_poll = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq LIMIT 1" );
		// If Install, Insert 1st Poll Question With 5 Poll Answers.
		if ( empty( $first_poll ) ) {
			// Insert Poll Question (1 Record).
			$insert_pollq = $wpdb->insert(
				$wpdb->pollsq,
				array(
					'pollq_question'  => __( 'How Is My Site?', 'wp-polls' ),
					'pollq_timestamp' => current_time( 'timestamp' ),
				),
				array( '%s', '%s' )
			);
			if ( $insert_pollq ) {
				// Insert Poll Answers  (5 Records).
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $insert_pollq,
						'polla_answers' => __( 'Good', 'wp-polls' ),
					),
					array( '%d', '%s' )
				);
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $insert_pollq,
						'polla_answers' => __( 'Excellent', 'wp-polls' ),
					),
					array( '%d', '%s' )
				);
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $insert_pollq,
						'polla_answers' => __( 'Bad', 'wp-polls' ),
					),
					array( '%d', '%s' )
				);
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $insert_pollq,
						'polla_answers' => __( 'Can Be Improved', 'wp-polls' ),
					),
					array( '%d', '%s' )
				);
				$wpdb->insert(
					$wpdb->pollsa,
					array(
						'polla_qid'     => $insert_pollq,
						'polla_answers' => __( 'No Comments', 'wp-polls' ),
					),
					array( '%d', '%s' )
				);
			}
		}
		// Options live in one row from 3.0.0 onward. Defaults come from
		// Polls_Options so activation and the settings screen cannot drift.
		add_option( Polls_Options::OPTION, Polls_Options::defaults() );
		Polls_Options::flush();

		// Backfill pollq_totalvoters for installs that predate the column being
		// populated: before 2.74 only pollq_totalvotes was maintained.
		$pollq_totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
		if ( 0 === $pollq_totalvoters ) {
			$wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvoters = pollq_totalvotes" );
		}

		// Index.
		$index    = $wpdb->get_results( "SHOW INDEX FROM $wpdb->pollsip;" );
		$key_name = array();
		if ( count( $index ) > 0 ) {
			foreach ( $index as $i ) {
				$key_name[] = $i->Key_name;
			}
		}
		if ( ! in_array( 'pollip_ip', $key_name, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip (pollip_ip);" );
		}
		if ( ! in_array( 'pollip_qid', $key_name, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_qid (pollip_qid);" );
		}
		if ( ! in_array( 'pollip_ip_qid_aid', $key_name, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip_qid_aid (pollip_ip, pollip_qid, pollip_aid);" );
		}
		// No longer needed index.
		if ( in_array( 'pollip_ip_qid', $key_name, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip DROP INDEX pollip_ip_qid;" );
		}

		// Change column datatype for wp_pollsip.
		$col_pollip_qid = $wpdb->get_row( "DESCRIBE $wpdb->pollsip pollip_qid" );
		if ( 'varchar(10)' === $col_pollip_qid->Type ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_qid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_aid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_timestamp int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsq MODIFY COLUMN pollq_expiry int(10) NOT NULL default '0';" );
		}

		// Set 'manage_polls' Capabilities To Administrator.
		$role = get_role( 'administrator' );
		if ( ! $role->has_cap( 'manage_polls' ) ) {
			$role->add_cap( 'manage_polls' );
		}

		// Run any outstanding version upgrades and record the current version.
		// Called here as well as on 'admin_init' so that network activation
		// upgrades every site while it is switched to.
		self::upgrade();

		Polls_Core::cron_polls_place();
	}
}
