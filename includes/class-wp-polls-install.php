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
class WP_Polls_Install {

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
			// get_sites(), not the wp_get_sites() this used to call. That one has
			// been deprecated since WP 4.6 and still ships in ms-deprecated.php,
			// so the old call raised a deprecation notice rather than failing
			// outright — and silently activated on only the first 100 sites,
			// because that is its default limit. get_sites() returns WP_Site
			// objects rather than arrays, and 'number' => 0 lifts the limit.
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
		$markers = WP_Polls_Options::markers();

		// An install that has not run this yet has no marker row at all, so the
		// pre-3.0.0 poll_version row is still the only record of what it last
		// ran. Read through to it once; the migration deletes it.
		$installed_version = '' !== $markers['plugin'] ? $markers['plugin'] : (string) get_option( WP_Polls_Options::LEGACY_VERSION, '' );
		$is_pre_3          = '' === $installed_version || version_compare( $installed_version, '3.0.0', '<' );

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
		$stored = get_option( WP_Polls_Options::OPTION, array() );
		if ( $is_pre_3 || ! is_array( $stored ) || ! isset( $stored['templates'] ) ) {
			WP_Polls_Options::migrate_from_legacy_rows();
		}

		// Version 3.0.0: the poll bar became a track holding a fill, styled from
		// CSS custom properties. Gated on the stored shape as well as the version
		// for the same reason the migration above is - a development install can
		// be stamped 3.0.0 and still hold the old bar.
		if ( $is_pre_3 || self::needs_poll_bar_upgrade( $stored ) ) {
			self::upgrade_poll_bar();
		}

		// Version 3.0.0: Inline onclick handlers were replaced by data-poll-* attributes.
		if ( $is_pre_3 ) {
			self::upgrade_templates_onclick();
		}

		if ( WP_POLLS_VERSION !== $markers['plugin'] || WP_POLLS_DB_VERSION !== $markers['db'] ) {
			// Both markers in one write, at the end, so a half finished upgrade
			// never records itself as complete.
			WP_Polls_Options::save_markers( WP_POLLS_VERSION, WP_POLLS_DB_VERSION );
		}
	}

	/**
	 * Whether the stored option still describes the pre-3.0.0 poll bar.
	 *
	 * @param mixed $stored The poll_options row as read from the database.
	 *
	 * @return bool
	 */
	public static function needs_poll_bar_upgrade( $stored ) {
		if ( ! is_array( $stored ) ) {
			return false;
		}

		// A bar style that is not one of the two CSS styles is either a leftover
		// images/ directory name or the old 'use_css' sentinel.
		if ( isset( $stored['bar']['style'] ) && ! in_array( $stored['bar']['style'], WP_Polls_Settings::bar_styles(), true ) ) {
			return true;
		}

		// Or the result template still carries the single div the bar used to be.
		foreach ( array( 'resultbody', 'resultbody2' ) as $key ) {
			if ( isset( $stored['templates'][ $key ] )
				&& is_string( $stored['templates'][ $key ] )
				&& false !== stripos( $stored['templates'][ $key ], 'pollbar' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Function: Move The Stored Poll Bar Onto The 3.0.0 Markup And Styles.
	 *
	 * The two result templates are replaced outright rather than patched, and
	 * customised copies are not spared. The markup, the class names and the
	 * stylesheet all changed together, so a template still holding the old
	 * single `div.pollbar` has no rules left to match and renders an invisible
	 * bar - there is no version of the old markup that still works. Anyone who
	 * had customised these gets the stock bar back and re-applies their changes;
	 * the changelog and the upgrade notice both say so.
	 *
	 * @return mixed
	 */
	public static function upgrade_poll_bar() {
		foreach ( array( 'resultbody', 'resultbody2' ) as $key ) {
			WP_Polls_Options::set( 'templates.' . $key, WP_Polls_Template::get_default( $key ) );
		}

		// images/default and images/default_gradient no longer exist. Both tiles
		// shaded light to dark, so they become the gradient; 'use_css' was the
		// flat fill. Anything else was a third-party directory that has nothing
		// left to point at.
		$map   = array(
			'use_css'          => 'flat',
			'default'          => 'gradient',
			'default_gradient' => 'gradient',
		);
		$style = WP_Polls_Options::get( 'bar.style' );

		if ( isset( $map[ $style ] ) ) {
			WP_Polls_Options::set( 'bar.style', $map[ $style ] );
		} elseif ( ! in_array( $style, WP_Polls_Settings::bar_styles(), true ) ) {
			WP_Polls_Options::set( 'bar.style', 'gradient' );
		}
	}

	/**
	 * Function: Convert Inline onclick Handlers In The Footer Templates To data-poll-* Attributes.
	 *
	 * @return mixed
	 */
	public static function upgrade_templates_onclick() {
		foreach ( array( 'votefooter', 'resultfooter2' ) as $key ) {
			$template = WP_Polls_Options::get( 'templates.' . $key );

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
				WP_Polls_Options::set( 'templates.' . $key, $migrated );
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

		if ( ! in_array( $hook_suffix, WP_Polls_Admin::admin_pages(), true ) ) {
			return;
		}

		if ( ! current_user_can( WP_Polls_Admin::capability() ) || ! self::templates_have_onclick() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post( __( '<strong>WP-Polls:</strong> one of your poll templates still uses an inline <code>onclick</code> handler. Inline handlers are no longer used, so the vote button or the result/vote links in that template will not do anything.', 'wp-polls' ) );
		echo '</p><p>';
		printf(
			/* translators: %s: value. */
			wp_kses_post( __( 'Open <a href="%s">Templates</a> and press <strong>Restore Default Template</strong> on the Voting Form Footer and Result Footer, or replace the handler yourself with <code>data-poll-id="%%POLL_ID%%"</code> and <code>data-poll-action="vote"</code> (or <code>result</code> / <code>booth</code>).', 'wp-polls' ) ),
			esc_url( WP_Polls_Settings::tab_url( WP_Polls_Settings::TAB_TEMPLATES ) )
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
			$template = WP_Polls_Options::get( 'templates.' . $key );

			if ( is_string( $template ) && stripos( $template, 'onclick' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every option row the plugin owns, current and legacy.
	 *
	 * The pre-3.0.0 names are still listed because an install that was never
	 * loaded after upgrading - deleted straight from the plugins screen - still
	 * has them, and they would otherwise be orphaned forever. The list comes
	 * from WP_Polls_Options so it cannot drift from the migration's idea of
	 * which rows belong to the plugin.
	 *
	 * @return array
	 */
	public static function option_names() {
		return array_merge(
			array( WP_Polls_Options::OPTION, WP_Polls_Options::VERSION ),
			array_keys( WP_Polls_Options::legacy_map() ),
			WP_Polls_Options::legacy_extra_rows(),
			array( 'widget_polls', 'widget_polls-widget' )
		);
	}

	/**
	 * Remove every option row and drop the three tables for the current site.
	 *
	 * Before 3.0.0 the table drop was called from inside the loop over option
	 * names, so it ran once per option rather than once per site - 36 times
	 * over, issuing three DROP TABLE statements each.
	 *
	 * @return void
	 */
	public static function uninstall_site() {
		global $wpdb;

		foreach ( self::option_names() as $option_name ) {
			delete_option( $option_name );
		}

		// $wpdb->prefix rather than the registered $wpdb->pollsq: uninstall.php
		// does not load the main plugin file, so nothing has registered the
		// table names. switch_to_blog() updates the prefix, so this stays
		// correct per site on multisite. %i binds the identifier, which is what
		// makes the statement preparable at all.
		foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $table_name ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table_name ) );
		}
	}

	/**
	 * Activate.
	 *
	 * @return mixed
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
		//
		// The gate is the schema marker, which self::upgrade() records at the
		// end of this method along with the plugin marker.
		$markers = WP_Polls_Options::markers();
		if ( WP_POLLS_DB_VERSION !== $markers['db'] ) {
			foreach ( $create_table as $table => $sql ) {
				$table_name = $wpdb->$table;
				if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
					dbDelta( $sql );
				}
			}
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
					'pollq_timestamp' => WP_Polls::now(),
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
		// WP_Polls_Options so activation and the settings screen cannot drift.
		add_option( WP_Polls_Options::OPTION, WP_Polls_Options::defaults() );
		WP_Polls_Options::flush();

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
				$key_name[] = $i->Key_name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column name returned by SHOW INDEX.
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
		if ( 'varchar(10)' === $col_pollip_qid->Type ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column name returned by DESCRIBE.
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_qid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_aid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_timestamp int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsq MODIFY COLUMN pollq_expiry int(10) NOT NULL default '0';" );
		}

		// Set 'manage_polls' Capabilities To Administrator.
		$role = get_role( 'administrator' );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_polls is this plugin's own capability, and these two lines are what create it.
		if ( ! $role->has_cap( 'manage_polls' ) ) {
			$role->add_cap( 'manage_polls' );
		}

		// Run any outstanding version upgrades and record the current version.
		// Called here as well as on 'admin_init' so that network activation
		// upgrades every site while it is switched to.
		self::upgrade();

		WP_Polls::cron_polls_place();
	}
}
