<?php
/**
 * Front end wiring: assets, shortcodes and the scheduled poll-closing job.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots the front end side of the plugin.
 */
class WP_Polls {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'poll_scripts' ) );
		add_action( 'widgets_init', array( __CLASS__, 'widget_polls_init' ) );
		add_action( 'polls_cron', array( __CLASS__, 'cron_polls_status' ) );
		add_shortcode( 'page_polls', array( __CLASS__, 'poll_page_shortcode' ) );
		add_shortcode( 'poll', array( __CLASS__, 'poll_shortcode' ) );

		self::register_command();
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * The class file is required here rather than at plugin load because it
	 * extends WP_CLI_Command, which only exists when WP-CLI is the one running
	 * WordPress. Requiring it unconditionally is a fatal error on every web
	 * request.
	 *
	 * @return void
	 */
	public static function register_command() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once WP_POLLS_DIR . 'includes/class-wp-polls-command.php';

		WP_CLI::add_command( 'polls', 'WP_Polls_Command' );
	}

	// Function: Enqueue Polls JavaScripts/CSS.

	/**
	 * Poll scripts.
	 *
	 * @return mixed
	 */
	public static function poll_scripts() {
		if ( file_exists( get_stylesheet_directory() . '/wp-polls.css' ) ) {
			wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/wp-polls.css', array(), WP_POLLS_VERSION );
		} else {
			wp_enqueue_style( 'wp-polls', WP_POLLS_URL . 'css/wp-polls.css', array(), WP_POLLS_VERSION );
		}
		$pollbar = WP_Polls_Options::get( 'bar' );
		// This lands in an inline <style> block on every front end page, so never
		// trust the stored values even though only 'manage_polls' can set them.
		$pollbar_height     = (int) $pollbar['height'];
		$pollbar_background = self::sanitize_bar_color( $pollbar['background'] );
		$pollbar_border     = self::sanitize_bar_color( $pollbar['border'] );
		// Only the configured values are emitted here. The rules that consume
		// them live in css/wp-polls.css, which is why this no longer branches on the
		// bar style: every style is now a difference in these four values.
		$pollbar_css  = '.wp-polls {' . "\n";
		$pollbar_css .= "\t" . '--wp-polls-bar-height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . '--wp-polls-bar-background: #' . $pollbar_background . ';' . "\n";
		$pollbar_css .= "\t" . '--wp-polls-bar-border: #' . $pollbar_border . ';' . "\n";
		$pollbar_css .= "\t" . '--wp-polls-bar-image: ' . self::bar_image( $pollbar['style'] ) . ';' . "\n";
		$pollbar_css .= '}' . "\n";
		wp_add_inline_style( 'wp-polls', $pollbar_css );
		wp_enqueue_script( 'wp-polls', WP_POLLS_URL . 'js/wp-polls.js', array(), WP_POLLS_VERSION, true );
		wp_localize_script(
			'wp-polls',
			'wpPollsL10n',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'text_wait'     => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
				'text_valid'    => __( 'Please choose a valid poll answer.', 'wp-polls' ),
				'text_multiple' => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
			)
		);
	}

	// Function: Short Code For Inserting Polls Archive Into Page.

	/**
	 * Poll page shortcode.
	 *
	 * Takes no attributes; $atts is accepted only because add_shortcode() passes it.
	 *
	 * @param array|string $atts Shortcode attributes. Unused.
	 *
	 * @return string
	 */
	public static function poll_page_shortcode( $atts ) {
		unset( $atts );
		return WP_Polls_Display::polls_archive();
	}

	// Function: Short Code For Inserting Polls Into Posts.

	/**
	 * Poll shortcode.
	 *
	 * @param mixed $atts Value.
	 *
	 * @return mixed
	 */
	public static function poll_shortcode( $atts ) {
		$attributes = shortcode_atts(
			array(
				'id'   => 0,
				'type' => 'vote',
			),
			$atts
		);
		if ( ! is_feed() ) {
			$id = (int) $attributes['id'];

			// To maintain backward compatibility with [poll=1]. Props @tz-ua.
			if ( ! $id && isset( $atts[0] ) ) {
				$id = (int) trim( $atts[0], '="\'' );
			}

			if ( 'vote' === $attributes['type'] ) {
				return WP_Polls_Display::get_poll( $id, false );
			} elseif ( 'result' === $attributes['type'] ) {
				return WP_Polls_Display::display_pollresult( $id );
			}
		} else {
			return __( 'Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls' );
		}
	}

	/**
	 * Place Cron.
	 *
	 * @return mixed
	 */
	public static function cron_polls_place() {
		wp_clear_scheduled_hook( 'polls_cron' );
		if ( ! wp_next_scheduled( 'polls_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'polls_cron' );
		}
	}

	// Funcion: Check All Polls Status To Check If It Expires.

	/**
	 * Cron polls status.
	 *
	 * @return mixed
	 */
	public static function cron_polls_status() {
		global $wpdb;
		$now = self::now();
		// Close Poll.
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < %d AND pollq_expiry != 0 AND pollq_active != 0", $now ) );
		// Open Future Polls.
		$active_polls = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= %d AND pollq_active = -1", $now ) );
		// Update Latest Poll If Future Poll Is Opened.
		if ( $active_polls ) {
			WP_Polls_Options::set( 'latest_poll', self::polls_latest_id() );
		}
	}

	/**
	 * The current time as WP-Polls has always stored it.
	 *
	 * Poll timestamps are site-local rather than UTC and are rendered back with
	 * gmdate(), so time() is not a drop-in replacement: it would shift every
	 * newly created poll by the GMT offset relative to the rows already in the
	 * table. This spells out what the deprecated current_time( 'timestamp' )
	 * did, so the intent - site-local, on purpose - is visible rather than
	 * hidden behind a call WordPress now warns about.
	 *
	 * @return int
	 */
	public static function now() {
		return time() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	/**
	 * Get Latest Poll ID.
	 *
	 * @return mixed
	 */
	public static function polls_latest_id() {
		global $wpdb;
		$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1" );
		return (int) $poll_id;
	}

	// Class: WP-Polls Widget
	// Function: Init WP-Polls Widget.

	/**
	 * Widget polls init.
	 *
	 * @return mixed
	 */
	public static function widget_polls_init() {
		register_widget( 'WP_Polls_Widget' );
	}

	/**
	 * Sanitize a hex colour for the poll bar, stored without its leading '#'.
	 *
	 * Accepts the '#rrggbb' the colour input on Poll Options posts as well as
	 * the bare three or six digits the setting has always been stored as, and
	 * always answers six digits: '#abc' is a colour CSS understands but not one
	 * <input type="color"> will show, so a value carried over from 2.x has to be
	 * expanded before the field can display it.
	 *
	 * @param mixed $color Value.
	 *
	 * @return string Six hex digits, without a leading '#'.
	 */
	public static function sanitize_bar_color( $color ) {
		// Lowercased so the stored value is the same string whichever way it was
		// set: the colour input always posts lowercase, hand typed values and
		// 2.x settings did not have to be.
		$color = strtolower( ltrim( trim( (string) $color ), '#' ) );

		if ( preg_match( '/^[0-9a-fA-F]{3}$/', $color ) ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}

		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $color ) ) {
			return '000000';
		}

		return $color;
	}

	/**
	 * The background-image value for a poll bar style.
	 *
	 * Up to 3.0.0 the shaded styles were 1px wide GIF tiles under images/, which
	 * carried their own colour - so picking one silently discarded the Poll Bar
	 * Background setting. The gradient is a translucent overlay rather than a
	 * fixed pair of colours, so it now shades whatever colour is configured.
	 *
	 * @param mixed $style Stored bar style.
	 *
	 * @return string A CSS background-image value.
	 */
	public static function bar_image( $style ) {
		if ( 'gradient' === $style ) {
			return 'linear-gradient(to bottom, rgba(255, 255, 255, 0.28), rgba(0, 0, 0, 0.07))';
		}

		return 'none';
	}
}
