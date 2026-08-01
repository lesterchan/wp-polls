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
	 * The poll_archive_show row was already dead before 3.0.0. poll_version and
	 * poll_db_version are the two markers that collapse into the single
	 * wp_polls_version row. poll_options is read by the migration - both the
	 * pre-3.0.0 shape, which held only ip_header, and the unreleased 3.0.0
	 * shape, which held the whole nested array - and removed afterwards, so it
	 * is listed here rather than in the map.
	 *
	 * stats_display is the shared, unprefixed row seven plugins used to write
	 * their WP-Stats toggle into. Each of them now owns its own copy of the
	 * setting, and each removes the shared row, which is what the cross-plugin
	 * contract asks for.
	 *
	 * @return array
	 */
	public static function legacy_extra_rows() {
		return array(
			'poll_archive_show',
			self::LEGACY_OPTION,
			self::LEGACY_VERSION,
			self::LEGACY_DB_VERSION,
			self::LEGACY_STATS_DISPLAY,
		);
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
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		self::$cache = self::merge( self::defaults(), (array) $values );

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
	}
}
