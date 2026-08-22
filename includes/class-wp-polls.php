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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		// Priority 10, ahead of core printing footer scripts and late styles at 20.
		add_action( 'wp_footer', array( __CLASS__, 'footer_scripts' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'block_editor_styles' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
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
	 * Enqueue the front end assets, where the head can already see a poll coming.
	 *
	 * A page showing no poll carries neither the stylesheet nor the script. The
	 * shapes visible this early are the active widget and a shortcode or block
	 * in the current post; anything rendering later than the head -- a template
	 * tag, a poll in a loop page -- asks via WP_Polls_Display::request_assets()
	 * and footer_scripts() picks it up.
	 *
	 * @return void
	 */
	public static function scripts() {
		if ( ! self::needs_assets() ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * Whether the current request is already known to render a poll.
	 *
	 * @return bool
	 */
	protected static function needs_assets() {
		if ( is_active_widget( false, false, 'polls-widget', true ) ) {
			return true;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'poll' )
			|| has_shortcode( $post->post_content, 'page_polls' )
			|| has_block( 'wp-polls/poll', $post )
			|| has_block( 'wp-polls/page-polls', $post );
	}

	/**
	 * Enqueue late, for a poll the head could not see coming.
	 *
	 * Runs at `wp_footer` priority 10, before core prints footer scripts and
	 * late styles at 20, so both assets still make it onto the page.
	 *
	 * @return void
	 */
	public static function footer_scripts() {
		if ( ! WP_Polls_Display::needs_assets() ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * The stylesheet with its inline custom properties, and the script with its
	 * strings and the AJAX endpoint.
	 *
	 * Guarded on the style handle because both passes can run on one request,
	 * and wp_add_inline_style() appends rather than replaces -- a second pass
	 * would emit the bar's custom properties twice.
	 *
	 * @return void
	 */
	protected static function enqueue_assets() {
		if ( wp_style_is( 'wp-polls', 'enqueued' ) ) {
			return;
		}

		self::styles();

		wp_enqueue_script( 'wp-polls', WP_POLLS_URL . 'js/wp-polls.js', array(), WP_POLLS_VERSION, true );
		wp_localize_script(
			'wp-polls',
			'wpPollsL10n',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'text_wait'     => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
				'text_valid'    => __( 'Please choose a valid poll answer.', 'wp-polls' ),
				'text_multiple' => __( 'Maximum number of choices allowed:', 'wp-polls' ),
			)
		);
	}

	/**
	 * Enqueue the stylesheet in the block editor, and only there.
	 *
	 * A block's preview is server-rendered, so the editor draws the same markup
	 * the front end draws -- but without this it draws it with none of the
	 * styles, and the most visible consequence is the AJAX loading placeholder.
	 * `.wp-polls-loading` is hidden by a stylesheet rule rather than by an
	 * attribute, so with no stylesheet the editor shows a permanent
	 * "Loading ..." under every poll.
	 *
	 * The styles only, never the script: the front-end script attaches vote
	 * handlers, and a preview that can be voted in casts real votes from the
	 * editor.
	 *
	 * Guarded on is_admin() because `enqueue_block_assets` fires on the front
	 * end too, where the conditional enqueue owns the decision -- and
	 * wp_add_inline_style() appends rather than replaces, so running here as
	 * well would emit the bar's custom properties twice.
	 *
	 * @return void
	 */
	public static function block_editor_styles() {
		if ( ! is_admin() ) {
			return;
		}

		self::styles();
	}

	/**
	 * Register and enqueue the stylesheet, with the bar's custom properties.
	 *
	 * Split out of scripts() so the block editor can have the styles
	 * without the script.
	 *
	 * @return void
	 */
	public static function styles() {
		if ( file_exists( get_stylesheet_directory() . '/wp-polls.css' ) ) {
			wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/wp-polls.css', array(), WP_POLLS_VERSION );
		} else {
			wp_enqueue_style( 'wp-polls', WP_POLLS_URL . 'css/wp-polls.css', array(), WP_POLLS_VERSION );
		}
		$pollbar = WP_Polls_Options::get( 'bar' );
		// This lands in an inline <style> block on every page that shows a poll,
		// so never trust the stored values even though only 'manage_polls' can
		// set them.
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

		$id = (int) $attributes['id'];

		// To maintain backward compatibility with [poll=1]. Props @tz-ua.
		//
		// This stays here rather than moving into the shared renderer: it is
		// shortcode syntax, and a block has no positional attribute to parse.
		if ( ! $id && isset( $atts[0] ) ) {
			$id = (int) trim( $atts[0], '="\'' );
		}

		return WP_Polls_Display::render_poll( $id, $attributes['type'] );
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
	public static function register_widget() {
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
