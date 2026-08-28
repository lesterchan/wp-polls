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
	 * Row held for the duration of an upgrade, so only one request runs it.
	 */
	const UPGRADE_LOCK = 'wp_polls_upgrade_lock';

	/**
	 * How long a held lock is believed before it is treated as abandoned.
	 */
	const UPGRADE_LOCK_TIMEOUT = 300;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'onclick_notice' ) );
	}

	/**
	 * Activation hook: install every site the activation covers.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// get_sites(), not the wp_get_sites() this used to call: deprecated
			// since WP 4.6, it still ships in ms-deprecated.php, so it raised a
			// notice rather than failing outright while capped at 100 sites.
			// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would otherwise skip every site past the hundredth while reporting success.
			$ms_site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			// Inside the loop: switch_to_blog() pushes onto a stack, so restoring once after the loop unwinds it by exactly one.
			foreach ( $ms_site_ids as $ms_site_id ) {
				switch_to_blog( (int) $ms_site_id );
				self::install();
				restore_current_blog();
			}
		} else {
			self::install();
		}
	}

	/**
	 * Run the outstanding version gated upgrades, once.
	 *
	 * Activation does not fire on a plugin update, which is the single most
	 * common reason a migration never runs. The stored markers are therefore
	 * checked on every request, on `init` at priority 5, and anything they say
	 * is outstanding runs before the rest of the plugin reads the options.
	 *
	 * @return void
	 */
	public static function upgrade() {
		// Nothing owed is the case on every request but a handful in an install's
		// life, and it must stay a read: the lock below costs two writes.
		if ( ! self::is_behind() ) {
			return;
		}

		// Running on init means running on front-end requests, so a busy site can
		// have two of these in the migration at once -- and the fold is a
		// read-modify-write of one row. The loser of that race writes the values
		// it read before the winner saved, over the top of the winner's, and by
		// then the legacy rows it would have read them back from are deleted.
		if ( ! self::lock() ) {
			return;
		}

		// Re-read behind the lock: the request that held it may have finished the
		// whole upgrade between the check above and the lock coming free.
		WP_Polls_Options::flush();
		$steps = self::outstanding();

		if ( ! in_array( true, $steps, true ) ) {
			self::unlock();

			return;
		}

		// Version 3.0.0: fold the ~30 scattered option rows into a single one.
		// Must run before anything else that touches templates, so there is only
		// one place they live by the time the later steps read them.
		if ( $steps['legacy_rows'] ) {
			WP_Polls_Options::migrate_legacy_rows();
		}

		// Version 3.0.0: the poll bar became a track holding a fill, styled from
		// CSS custom properties.
		if ( $steps['poll_bar'] ) {
			self::upgrade_poll_bar();
		}

		// Version 3.0.0: Inline onclick handlers were replaced by data-poll-* attributes.
		if ( $steps['onclick'] ) {
			self::upgrade_templates_onclick();
		}

		if ( $steps['markers'] ) {
			// Both markers in one write, at the end, so a half finished upgrade
			// never records itself as complete.
			WP_Polls_Options::update_markers();
		}

		self::unlock();
	}

	/**
	 * Whether this install still owes any upgrade step.
	 *
	 * @return bool
	 */
	protected static function is_behind() {
		return in_array( true, self::outstanding(), true );
	}

	/**
	 * Which upgrade steps this install still owes, keyed by step.
	 *
	 * Every gate is derived in one place and read twice -- once before the lock
	 * and once behind it -- so the two answers cannot be computed differently.
	 *
	 * @return array<string,bool>
	 */
	protected static function outstanding() {
		$markers = WP_Polls_Options::markers();

		// An install that has not run this yet has no marker row at all, so the
		// pre-3.0.0 poll_version row is still the only record of what it last
		// ran. Read through to it once; the migration deletes it.
		$installed_version = '' !== $markers['plugin'] ? $markers['plugin'] : (string) get_option( WP_Polls_Options::LEGACY_VERSION, '' );
		$is_pre_3          = '' === $installed_version || version_compare( $installed_version, '3.0.0', '<' );
		$stored            = get_option( WP_Polls_Options::OPTION, array() );

		return array(
			// Gated on the stored shape as well as the version. 3.0.0 spent a
			// while unreleased on the development branch, so an install can be
			// stamped 3.0.0 and still hold the scattered rows; a version-only
			// gate would skip it and quietly drop that site to defaults.
			'legacy_rows' => $is_pre_3 || ! is_array( $stored ) || ! isset( $stored['templates'] ),
			// Gated on the stored shape too, for the same reason: a development
			// install can be stamped 3.0.0 and still hold the old bar.
			'poll_bar'    => $is_pre_3 || self::needs_poll_bar_upgrade( $stored ),
			'onclick'     => $is_pre_3,
			'markers'     => WP_POLLS_VERSION !== $markers['plugin'] || WP_POLLS_DB_VERSION !== $markers['db'],
		);
	}

	/**
	 * Take the upgrade lock for this site.
	 *
	 * The atomic half is add_option(): the options table has a unique key on
	 * option_name, so a second request's INSERT fails rather than overwriting,
	 * and only one caller is told it succeeded. wp_cache_add() would not do --
	 * with no persistent object cache it succeeds in every request, and a site
	 * with no object cache is exactly the one at risk.
	 *
	 * @return bool Whether this request now holds the lock.
	 */
	protected static function lock() {
		$held = get_option( self::UPGRADE_LOCK, false );

		if ( false !== $held ) {
			// A request that died mid-upgrade must not stop every later one from
			// ever finishing it.
			if ( ( time() - (int) $held ) < self::UPGRADE_LOCK_TIMEOUT ) {
				return false;
			}

			delete_option( self::UPGRADE_LOCK );
		}

		return add_option( self::UPGRADE_LOCK, time(), '', false );
	}

	/**
	 * Release the upgrade lock.
	 *
	 * @return void
	 */
	protected static function unlock() {
		delete_option( self::UPGRADE_LOCK );
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
	 * Move the stored poll bar onto the 3.0.0 markup and styles.
	 *
	 * The two result templates are replaced outright rather than patched, and
	 * customised copies are not spared. The markup, the class names and the
	 * stylesheet all changed together, so a template still holding the old
	 * single `div.pollbar` has no rules left to match and renders an invisible
	 * bar - there is no version of the old markup that still works. Anyone who
	 * had customised these gets the stock bar back and re-applies their changes;
	 * the changelog and the upgrade notice both say so.
	 *
	 * @return void
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
	 * Convert inline onclick handlers in the footer templates to data-poll-*
	 * attributes.
	 *
	 * @return void
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

	/**
	 * Warn when a poll template still relies on an inline onclick handler.
	 *
	 * Since 3.0.0 the scripts export nothing, so an onclick left behind by a
	 * customised template no longer calls anything at all. The upgrade converts
	 * the stock templates automatically; this covers the ones too customised to
	 * convert, which would otherwise fail silently on the front end.
	 *
	 * @return void
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
			/* translators: %s: URL of the Templates tab. */
			wp_kses_post( __( 'Open <a href="%s">Templates</a> and press <strong>Restore Default Template</strong> on the Voting Form Footer and Result Footer, or replace the handler yourself with <code>data-poll-id="%%POLL_ID%%"</code> and <code>data-poll-action="vote"</code> (or <code>result</code> / <code>booth</code>).', 'wp-polls' ) ),
			esc_url( WP_Polls_Settings::tab_url( WP_Polls_Settings::TAB_TEMPLATES ) )
		);
		echo '</p></div>';
	}

	/**
	 * Whether any poll template still contains an inline onclick handler.
	 *
	 * @return bool
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
			// The lock is the upgrade's own bookkeeping and is absent on a site
			// that finished one, but a site uninstalling part way through an
			// interrupted upgrade would otherwise keep it.
			array( self::UPGRADE_LOCK, 'widget_polls', 'widget_polls-widget' )
		);
	}

	/**
	 * Grant the administrator role the capability the screens actually check.
	 *
	 * `WP_Polls_Admin::capability()`, not the `manage_polls` constant behind it.
	 * The screens gate on the filtered value, so granting the raw constant while
	 * checking the filtered one means a site that filters `wp_polls_capability`
	 * hands its administrator a capability nothing looks at and gates every
	 * polls screen on one nobody holds -- the owner is locked out of their own
	 * plugin, with nothing in any log to say why.
	 *
	 * Two places deciding one fact and only one of them told. wp-postratings
	 * has done it this way since 2.0.0 and says the same thing in its own
	 * remove_capability().
	 *
	 * @return void
	 */
	private static function add_capability() {
		$role = get_role( 'administrator' );

		// Null when the role has been removed, which is a fatal rather than a
		// missing capability if it is not checked.
		if ( $role instanceof WP_Role ) {
			$role->add_cap( WP_Polls_Admin::capability() );
		}
	}

	/**
	 * Take the capability back off the administrator role.
	 *
	 * The same expression add_capability() grants, filter and all: removing the
	 * constant while granting the filtered value would leave a site that uses
	 * `wp_polls_capability` with a capability nothing takes back.
	 *
	 * @return void
	 */
	private static function remove_capability() {
		$role = get_role( 'administrator' );

		if ( $role instanceof WP_Role ) {
			$role->remove_cap( WP_Polls_Admin::capability() );
		}
	}

	/**
	 * Uninstall the plugin: every site on a network, or just the one.
	 *
	 * The whole job is delegated here by uninstall.php, so the loop over sites
	 * lives beside the per-site work it drives and both are reachable from the
	 * test suite.
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( ! is_multisite() ) {
			self::uninstall_site();

			return;
		}

		// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would otherwise skip every site past the hundredth while reporting success.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		// Inside the loop: switch_to_blog() pushes onto a stack, so restoring once after the loop unwinds it by exactly one.
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::uninstall_site();
			restore_current_blog();
		}
	}

	/**
	 * Remove every option row, the capability, and the three tables.
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

		self::remove_capability();

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
	 * Install one site: the tables, the sample poll, the options, the
	 * capability and the cron job.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
		// A fresh install gets the sample poll; a reactivation already has
		// rows and must not gain a duplicate.
		$first_poll = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq LIMIT 1" );
		if ( empty( $first_poll ) ) {
			$insert_pollq = $wpdb->insert(
				$wpdb->pollsq,
				array(
					'pollq_question'  => __( 'How Is My Site?', 'wp-polls' ),
					'pollq_timestamp' => WP_Polls::now(),
				),
				array( '%s', '%s' )
			);
			if ( $insert_pollq ) {
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

		// The explicit index work the dbDelta gate above points at: bring the
		// vote log's indexes up to date whatever schema the site started on.
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
		// Superseded by pollip_ip_qid_aid, which covers the same lookups.
		if ( in_array( 'pollip_ip_qid', $key_name, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->pollsip DROP INDEX pollip_ip_qid;" );
		}

		// Very old installs stored these columns as varchar(10); the DESCRIBE
		// detects that shape so the widening runs exactly once.
		$col_pollip_qid = $wpdb->get_row( "DESCRIBE $wpdb->pollsip pollip_qid" );
		if ( 'varchar(10)' === $col_pollip_qid->Type ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column name returned by DESCRIBE.
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_qid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_aid int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_timestamp int(10) NOT NULL default '0';" );
			$wpdb->query( "ALTER TABLE $wpdb->pollsq MODIFY COLUMN pollq_expiry int(10) NOT NULL default '0';" );
		}

		self::add_capability();

		// Run any outstanding version upgrades and record the current version.
		// Called here as well as on 'init' so that network activation upgrades
		// every site while it is switched to.
		self::upgrade();

		WP_Polls::cron_polls_place();
	}
}
