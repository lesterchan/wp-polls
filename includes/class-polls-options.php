<?php
/**
 * Consolidated option storage for WP-Polls.
 *
 * Everything the plugin configures lives in one wp_options row holding a
 * nested array, rather than the thirty separate rows used up to 3.0.0. It
 * reuses the existing poll_options name, which before 4.0.0 held only the
 * ip_header setting. The
 * value is a plain PHP array: update_option() serialises it and get_option()
 * unserialises it, so there is no encode/decode layer at the call sites and
 * register_setting()'s sanitize_callback receives the structure intact.
 *
 * Two things deliberately stay in their own rows:
 *   - poll_version, because it is read to decide whether this option needs
 *     migrating and so cannot live inside the thing being migrated
 *   - widget_polls-widget, which belongs to WP_Widget rather than to us
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single poll_options row.
 */
class Polls_Options {

	/**
	 * Name of the consolidated option row.
	 *
	 * @var string
	 */
	const OPTION = 'poll_options';

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
			'poll_ajax_style'           => 'ajax',
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
			'poll_logging_method'       => 'logging_method',
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
	 * poll_archive_show was already dead before 3.0.0. poll_options is NOT
	 * listed: it is the row the settings now live in, so deleting it would
	 * throw away everything the migration just wrote.
	 *
	 * @return array
	 */
	public static function legacy_extra_rows() {
		return array( 'poll_archive_show' );
	}

	/**
	 * Default values for every key.
	 *
	 * Templates are filled in by Polls_Templates so the markup lives in one
	 * place rather than being duplicated between defaults and the reset button.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'templates'      => Polls_Templates::defaults(),
			// These mirror the pre-4.0.0 add_option() calls exactly. Changing any
			// of them silently changes what a fresh install looks like.
			'bar'            => array(
				'style'      => 'default',
				'background' => 'd8e1eb',
				'border'     => 'c8c8c8',
				'height'     => 8,
			),
			'ajax'           => array(
				'loading' => 1,
				'fading'  => 1,
			),
			'sort'           => array(
				'answers_by'    => 'polla_aid',
				'answers_order' => 'asc',
				'results_by'    => 'polla_votes',
				'results_order' => 'desc',
			),
			'archive'        => array(
				'per_page'     => 5,
				'display_poll' => 2,
				'url'          => site_url( 'pollsarchive' ),
			),
			'current_poll'   => 0,
			'latest_poll'    => 1,
			'close'          => 1,
			'logging_method' => 3,
			'cookie_expiry'  => 0,
			'allow_to_vote'  => 2,
			'ip_header'      => '',
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
	 * @param mixed  $default Returned when the path is absent.
	 * @return mixed
	 */
	public static function get( $path, $default = null ) {
		$value = self::all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
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
	 * Fold the pre-4.0.0 option rows into the single row, then delete them.
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
		// own: an install whose poll_version row is missing while poll_options
		// survives - a partial restore, a downgrade and re-upgrade, an
		// over-eager cleanup plugin - would otherwise have every setting
		// overwritten with defaults, because there are no legacy rows left to
		// read them back from. Seeding from all() makes the migration a no-op
		// in that case instead of destructive.
		self::flush();
		$values = self::all();

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

		// Pre-4.0.0 the row held only { ip_header: ... }. No special case is
		// needed: it is the same row, so all() above already merged that single
		// key over the defaults.

		self::save( $values );

		foreach ( array_keys( self::legacy_map() ) as $legacy ) {
			delete_option( $legacy );
		}
		foreach ( self::legacy_extra_rows() as $legacy ) {
			delete_option( $legacy );
		}
	}
}
