<?php
/**
 * Consolidated option storage for WP-Polls.
 *
 * Everything the plugin configures lives in one wp_polls_options row holding a
 * nested array, rather than the thirty separate rows used up to 3.0.0. The
 * value is a plain PHP array: update_option() serialises it and get_option()
 * unserialises it, so there is no encode/decode layer at the call sites and
 * register_setting()'s sanitize_callback receives the structure intact.
 *
 * Two things deliberately stay in their own rows:
 *   - wp_polls_version, which holds the plugin and schema markers. A
 *     sanitize_callback maps what the form posted to what gets stored, and the
 *     settings form never posts a version marker, so a marker kept in this
 *     array would have to be rescued from the stored value on every save
 *   - widget_polls-widget, which belongs to WP_Widget rather than to us
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single wp_polls_options row.
 */
class WP_Polls_Options {

	/**
	 * Name of the consolidated settings row.
	 *
	 * @var string
	 */
	const OPTION = 'wp_polls_options';

	/**
	 * Name of the row holding the plugin and schema version markers.
	 *
	 * @var string
	 */
	const VERSION = 'wp_polls_version';

	/**
	 * The pre-3.0.0 settings row, read once by the migration and then removed.
	 *
	 * The four legacy names below are constants rather than literals at their
	 * call sites so that each one is written down exactly once - the migration
	 * that reads it and the list that deletes it can never disagree about the
	 * spelling.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'poll_options';

	/**
	 * The pre-3.0.0 plugin version marker.
	 *
	 * @var string
	 */
	const LEGACY_VERSION = 'poll_version';

	/**
	 * The pre-3.0.0 schema version marker.
	 *
	 * @var string
	 */
	const LEGACY_DB_VERSION = 'poll_db_version';

	/**
	 * WP-Stats' shared, unprefixed toggle row, which seven plugins wrote into.
	 *
	 * @var string
	 */
	const LEGACY_STATS_DISPLAY = 'stats_display';

	/**
	 * Runtime cache so a page render does not re-read the row per lookup.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Legacy option name => dot path in the consolidated array.
	 *
	 * Drives both the migration and uninstall, so the two can never disagree
	 * about which rows belong to the plugin.
	 *
	 * @return array
	 */
	public static function legacy_map() {
		$map = array(
			'poll_bar'                  => 'bar',
			'poll_ans_sortby'           => 'sort.answers_by',
			'poll_ans_sortorder'        => 'sort.answers_order',
			'poll_ans_result_sortby'    => 'sort.results_by',
			'poll_ans_result_sortorder' => 'sort.results_order',
			'poll_archive_perpage'      => 'archive.per_page',
			'poll_archive_displaypoll'  => 'archive.display_poll',
			'poll_archive_url'          => 'archive.url',
			'poll_currentpoll'          => 'current_poll',
			'poll_latestpoll'           => 'latest_poll',
			'poll_close'                => 'close',
			'poll_logging_method'       => 'check_method',
			'poll_cookielog_expiry'     => 'cookie_expiry',
			'poll_allowtovote'          => 'allow_to_vote',
		);

		foreach ( self::template_keys() as $key ) {
			$map[ 'poll_template_' . $key ] = 'templates.' . $key;
		}

		return $map;
	}

	/**
	 * The template slugs, in the order the settings screen shows them.
	 *
	 * @return array
	 */
	public static function template_keys() {
		return array(
			'voteheader',
			'votebody',
			'votefooter',
			'resultheader',
			'resultbody',
			'resultbody2',
			'resultfooter',
			'resultfooter2',
			'pollarchivelink',
			'pollarchiveheader',
			'pollarchivefooter',
			'pollarchivepagingheader',
			'pollarchivepagingfooter',
			'disable',
			'error',
		);
	}

	/**
	 * Legacy rows that carry no value forward but must still be cleaned up.
	 *
	 * The poll_archive_show row was already dead before 3.0.0. poll_ajax_style is
	 * the setting 3.0.0 retired outright - the loading indicator always shows and
	 * the fade follows prefers-reduced-motion - so it carries nothing forward and
	 * is listed here rather than in the map. It is autoloaded, so leaving it would
	 * put a row nothing reads into every request on every site, for good.
	 * poll_version and poll_db_version are the two markers that collapse into the
	 * single wp_polls_version row. poll_options is read by the migration - both the
	 * pre-3.0.0 shape, which held only ip_header, and the unreleased 3.0.0
	 * shape, which held the whole nested array - and removed afterwards, so it
	 * is listed here rather than in the map.
	 *
	 * Every row here belongs to WP-Polls, which is what makes it safe for
	 * WP_Polls_Install::option_names() to drive uninstall from the same list the
	 * migration cleans up. The shared row is deliberately not among them --
	 * legacy_shared_rows() below says why.
	 *
	 * @return array
	 */
	public static function legacy_extra_rows() {
		return array(
			'poll_archive_show',
			'poll_ajax_style',
			self::LEGACY_OPTION,
			self::LEGACY_VERSION,
			self::LEGACY_DB_VERSION,
		);
	}

	/**
	 * The unprefixed row this plugin shared with WP-Stats and five others.
	 *
	 * It was never any one plugin's to own: whichever of the seven saved the
	 * WP-Stats screen last wrote the whole row. Each plugin keeps its own copy of
	 * the toggle now, so the migration folds this in and deletes it -- and, unlike
	 * legacy_extra_rows(), it is deliberately kept off the uninstall list.
	 *
	 * §13.2 draws that line and this is the mirror half of it: the migration
	 * deletes the shared row because it has folded it in, and uninstall must leave
	 * it alone because up to six siblings that have not upgraded yet are still
	 * reading it. Removing WP-Polls from a site was taking the WP-Stats block
	 * settings of the other six with it, silently.
	 *
	 * Keeping the two lists apart is the fix, rather than dropping the row from
	 * the migration: it still has to be deleted once it has been folded in, or the
	 * fold runs again on the next request.
	 *
	 * @return array
	 */
	public static function legacy_shared_rows() {
		return array( self::LEGACY_STATS_DISPLAY );
	}

	/**
	 * Whether the shared WP-Stats row had the polls block switched on.
	 *
	 * The row reached this plugin in two shapes over the years: a map of
	 * key => 1, which is what the checkbox posted once WP-Stats normalised it,
	 * and a plain list of the enabled keys, which is what a bare
	 * name="stats_display[]" posts. Both are read here so an older install is
	 * not quietly treated as an opt-out.
	 *
	 * @param mixed $legacy The stats_display row as stored.
	 * @return bool
	 */
	public static function legacy_stats_display( $legacy ) {
		if ( ! is_array( $legacy ) ) {
			return (bool) $legacy;
		}

		if ( array_key_exists( 'polls', $legacy ) ) {
			return (bool) $legacy['polls'];
		}

		return in_array( 'polls', $legacy, true );
	}

	/**
	 * The plugin and schema markers, normalised.
	 *
	 * Always exactly the two keys, whatever is in the row, so callers never
	 * have to guard a partial or absent value.
	 *
	 * @return array
	 */
	public static function markers() {
		$stored = get_option( self::VERSION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Record both markers in one write.
	 *
	 * Together rather than one at a time, so a half finished upgrade never
	 * records itself as complete.
	 *
	 * @param string $plugin Plugin version just brought up to date.
	 * @param string $db     Schema version just brought up to date.
	 * @return bool
	 */
	public static function save_markers( $plugin, $db ) {
		return update_option(
			self::VERSION,
			array(
				'plugin' => (string) $plugin,
				'db'     => (string) $db,
			)
		);
	}

	/**
	 * Default values for every key.
	 *
	 * Templates are filled in by WP_Polls_Template so the markup lives in one
	 * place rather than being duplicated between defaults and the reset button.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'templates'     => WP_Polls_Template::defaults(),
			// These mirror the pre-3.0.0 add_option() calls exactly. Changing any
			// of them silently changes what a fresh install looks like.
			// 'gradient' rather than the pre-3.0.0 'default': that default was the
			// images/default/pollbg.gif tile, a light-to-dark shade, so gradient is
			// the value that leaves a fresh install looking the same.
			'bar'           => array(
				'style'      => 'gradient',
				'background' => 'd8e1eb',
				'border'     => 'c8c8c8',
				'height'     => 8,
			),
			'sort'          => array(
				'answers_by'    => 'polla_aid',
				'answers_order' => 'asc',
				'results_by'    => 'polla_votes',
				'results_order' => 'desc',
			),
			'archive'       => array(
				'per_page'     => 5,
				'display_poll' => 2,
				'url'          => site_url( 'pollsarchive' ),
			),
			'current_poll'  => 0,
			'latest_poll'   => 1,
			'close'         => 1,
			'check_method'  => 3,
			'cookie_expiry' => 0,
			'allow_to_vote' => 2,
			'ip_header'     => '',
			// Whether WP-Polls contributes a section to WP-Stats. Owned here
			// rather than in the shared stats_display row WP-Stats used to
			// keep, so no plugin can read or clobber another's toggle.
			'stats_display' => true,
		);
	}

	/**
	 * The whole option, defaults merged in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = self::merge( self::defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Read one value by dot path, e.g. 'templates.votefooter'.
	 *
	 * @param string $path    Dot separated path.
	 * @param mixed  $fallback Returned when the path is absent.
	 * @return mixed
	 */
	public static function get( $path, $fallback = null ) {
		$value = self::all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Write one value by dot path and persist the row.
	 *
	 * @param string $path  Dot separated path.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public static function set( $path, $value ) {
		$all      = self::all();
		$cursor   = &$all;
		$segments = explode( '.', $path );
		$last     = array_pop( $segments );
		foreach ( $segments as $segment ) {
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = array();
			}
			$cursor = &$cursor[ $segment ];
		}
		$cursor[ $last ] = $value;
		unset( $cursor );

		return self::save( $all );
	}

	/**
	 * Replace the whole option.
	 *
	 * The `add_option()` branch is belt and braces, and the reasoning is worth
	 * writing down because it is not obvious that it is *currently* redundant.
	 *
	 * `WP_Polls_Settings::register()` passes a `default`, which installs a
	 * `default_option_wp_polls_options` filter answering with the shipped
	 * defaults for a row that does not exist -- so on an admin request an absent
	 * row reads back as the defaults. Core anticipates exactly this: after the
	 * value/old-value comparison, `update_option()` asks the `default_option_*`
	 * filter what it would answer and calls `add_option()` when that is what
	 * `$old_value` was. What it does *not* cover is the comparison above that
	 * fallback, which returns early when the value being written is identical to
	 * the one just read -- and an absent row reads as the defaults precisely
	 * when a stock install is what is being saved.
	 *
	 * Two accidents keep this plugin out of that gap, and neither is a
	 * guarantee. `update_option()` sanitises before it compares, and
	 * `WP_Polls_Settings::sanitize()` does not return the defaults unchanged, so
	 * the early return is never reached. And `wp-polls.php` calls
	 * `WP_Polls_Install::init()` on line 71 and `WP_Polls_Settings::init()` on
	 * line 77 -- both hooking `admin_init` at priority 10 -- so the migration
	 * runs before the filter exists at all, on insertion order alone.
	 *
	 * Either could change without anything noticing: swapping two adjacent lines
	 * in a file that does nothing but wire classes up, or a sanitiser that
	 * becomes a no-op for already-clean input. Passing an explicit default to
	 * `get_option()` defeats the registered one, because
	 * `filter_default_option()` returns early when a default was passed, which
	 * makes an absent row tellable from a defaulted one here rather than three
	 * layers down in core. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the write changes. §7.6.1.
	 *
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		self::$cache = self::merge( self::defaults(), (array) $values );

		if ( false === get_option( self::OPTION, false ) ) {
			return add_option( self::OPTION, self::$cache );
		}

		return update_option( self::OPTION, self::$cache );
	}

	/**
	 * Drop the runtime cache. Needed after a migration writes the row.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Recursive defaults merge that does not renumber list arrays.
	 *
	 * @param array $defaults Defaults.
	 * @param array $values   Stored values.
	 * @return array
	 */
	protected static function merge( $defaults, $values ) {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}

		return $defaults;
	}

	/**
	 * Fold the pre-3.0.0 option rows into the single row, then delete them.
	 *
	 * Gated by the caller on the stored version rather than on "do the old rows
	 * still exist" - an install that has already migrated has no old rows, and
	 * a presence check would write defaults straight over its settings.
	 *
	 * @return void
	 */
	public static function migrate_from_legacy_rows() {
		// Start from whatever is already stored, not from the defaults. The
		// version gate is the primary guard, but it is not sufficient on its
		// own: an install whose marker row is missing while wp_polls_options
		// survives - a partial restore, a downgrade and re-upgrade, an
		// over-eager cleanup plugin - would otherwise have every setting
		// overwritten with defaults, because there are no legacy rows left to
		// read them back from. Seeding from all() makes the migration a no-op
		// in that case instead of destructive.
		self::flush();
		$values = self::all();

		// The old consolidated row first, so the individual rows below still
		// win where both exist. Two shapes reach this: the pre-3.0.0 one, which
		// held only ip_header, and the unreleased 3.0.0 one, which held the
		// whole nested array.
		$legacy_row = get_option( self::LEGACY_OPTION, array() );
		if ( is_array( $legacy_row ) ) {
			$values = self::merge( $values, $legacy_row );
		}

		/*
		 * The key is 'check_method' now, not 'logging_method', and the AJAX
		 * style toggles are gone.
		 *
		 * Here rather than after the loop below, and unconditional rather than
		 * guarded on check_method being unset: $values was seeded from all(),
		 * so every default key is already present and "is it missing" is never
		 * true. Renaming first lets the released poll_logging_method row still
		 * win in the loop, which is the rule the whole migration follows.
		 *
		 * Only a 3.0.0 beta ever wrote these names into the consolidated array;
		 * the released install keeps them in rows of their own. The numbers are
		 * unchanged -- what moved is the name, because the setting never chose
		 * whether to log anything.
		 */
		if ( isset( $values['logging_method'] ) ) {
			$values['check_method'] = $values['logging_method'];

			unset( $values['logging_method'] );
		}

		unset( $values['ajax'] );

		foreach ( self::legacy_map() as $legacy => $path ) {
			$stored = get_option( $legacy, null );
			if ( null === $stored || false === $stored ) {
				continue;
			}

			$segments = explode( '.', $path );
			if ( 1 === count( $segments ) ) {
				$values[ $segments[0] ] = $stored;
			} else {
				// Two levels is all the structure has.
				$values[ $segments[0] ][ $segments[1] ] = $stored;
			}
		}

		// WP-Stats used to keep one shared, unprefixed stats_display row that
		// seven plugins wrote their toggle into. Take the WP-Polls entry out of
		// it; the row itself is deleted below with the rest of the legacy ones.
		//
		// An ABSENT row means a sibling upgraded first and deleted it, not that
		// the site switched this block off - all seven migrations delete it, so
		// only the first one to run ever sees it. Reading absence as an opt-out
		// would make the polls block vanish from WP-Stats with no error on any
		// site that updated WP-Stats before WP-Polls. Absent therefore leaves
		// the default, which is on.
		$legacy_stats = get_option( self::LEGACY_STATS_DISPLAY, null );
		if ( null !== $legacy_stats && false !== $legacy_stats ) {
			$values['stats_display'] = self::legacy_stats_display( $legacy_stats );
		}

		self::save( $values );

		foreach ( array_keys( self::legacy_map() ) as $legacy ) {
			delete_option( $legacy );
		}
		foreach ( self::legacy_extra_rows() as $legacy ) {
			delete_option( $legacy );
		}

		// Deleted here, where it has just been folded in, and nowhere else -- see
		// legacy_shared_rows(). Uninstall must not touch it.
		foreach ( self::legacy_shared_rows() as $legacy ) {
			delete_option( $legacy );
		}
	}
}
