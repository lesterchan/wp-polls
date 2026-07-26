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
class Polls_Core {

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
	}

	// Function: Enqueue Polls JavaScripts/CSS.

	/**
	 * Poll scripts.
	 *
	 * @return mixed
	 */
	public static function poll_scripts() {
		if ( file_exists( get_stylesheet_directory() . '/polls-css.css' ) ) {
			wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/polls-css.css', false, WP_POLLS_VERSION, 'all' );
		} else {
			wp_enqueue_style( 'wp-polls', plugins_url( 'wp-polls/polls-css.css' ), false, WP_POLLS_VERSION, 'all' );
		}
		if ( is_rtl() ) {
			if ( file_exists( get_stylesheet_directory() . '/polls-css-rtl.css' ) ) {
				wp_enqueue_style( 'wp-polls-rtl', get_stylesheet_directory_uri() . '/polls-css-rtl.css', false, WP_POLLS_VERSION, 'all' );
			} else {
				wp_enqueue_style( 'wp-polls-rtl', plugins_url( 'wp-polls/polls-css-rtl.css' ), false, WP_POLLS_VERSION, 'all' );
			}
		}
		$pollbar = Polls_Options::get( 'bar' );
		// This lands in an inline <style> block on every front end page, so never
		// trust the stored values even though only 'manage_polls' can set them.
		$pollbar_height     = (int) $pollbar['height'];
		$pollbar_background = self::sanitize_bar_color( $pollbar['background'] );
		$pollbar_border     = self::sanitize_bar_color( $pollbar['border'] );
		if ( 'use_css' === $pollbar['style'] ) {
			$pollbar_css  = '.wp-polls .pollbar {' . "\n";
			$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
			$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
			$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
			$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
			$pollbar_css .= "\t" . 'background: #' . $pollbar_background . ';' . "\n";
			$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
			$pollbar_css .= '}' . "\n";
		} else {
			$pollbar_css  = '.wp-polls .pollbar {' . "\n";
			$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
			$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
			$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
			$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
			$pollbar_css .= "\t" . 'background-image: url(\'' . esc_url( plugins_url( 'wp-polls/images/' . $pollbar['style'] . '/pollbg.gif' ) ) . '\');' . "\n";
			$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
			$pollbar_css .= '}' . "\n";
		}
		wp_add_inline_style( 'wp-polls', $pollbar_css );
		$poll_ajax_style = Polls_Options::get( 'ajax' );
		wp_enqueue_script( 'wp-polls', plugins_url( 'wp-polls/polls-js.js' ), array(), WP_POLLS_VERSION, true );
		wp_localize_script(
			'wp-polls',
			'pollsL10n',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'text_wait'     => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
				'text_valid'    => __( 'Please choose a valid poll answer.', 'wp-polls' ),
				'text_multiple' => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
				'show_loading'  => (int) $poll_ajax_style['loading'],
				'show_fading'   => (int) $poll_ajax_style['fading'],
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
		return Polls_Display::polls_archive();
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
				return Polls_Display::get_poll( $id, false );
			} elseif ( 'result' === $attributes['type'] ) {
				return Polls_Display::display_pollresult( $id );
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
		$now = current_time( 'timestamp' );
		// Close Poll.
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < %d AND pollq_expiry != 0 AND pollq_active != 0", $now ) );
		// Open Future Polls.
		$active_polls = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= %d AND pollq_active = -1", $now ) );
		// Update Latest Poll If Future Poll Is Opened.
		if ( $active_polls ) {
			Polls_Options::set( 'latest_poll', self::polls_latest_id() );
		}
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
		register_widget( 'Polls_Widget' );
	}

	/**
	 * Sanitize a 3 or 6 digit hex colour for the poll bar, stored without its leading '#'.
	 *
	 * @param mixed $color Value.
	 *
	 * @return mixed
	 */
	public static function sanitize_bar_color( $color ) {
		$color = substr( trim( (string) $color ), 0, 6 );

		if ( ! preg_match( '/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
			return '000000';
		}

		return $color;
	}
}
