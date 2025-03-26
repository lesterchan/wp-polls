<?php
/**
 * Poll Core Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Core Class
 *
 * Main core class that initializes and handles the WP-Polls plugin.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Core {

	/**
	 * Initialize the core plugin.
	 *
	 * Sets up all the actions and filters needed for the plugin.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function init() {
		// Actions.
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widgets' ) );
		add_action( 'wp_head', array( __CLASS__, 'poll_header_javascript' ) );
		add_action( 'wp_footer', array( __CLASS__, 'poll_footer_javascript' ) );
		add_action( 'polls_cron', array( __CLASS__, 'update_polls_status' ) );

		// Shortcodes.
		add_shortcode( 'poll', array( __CLASS__, 'poll_shortcode' ) );
		add_shortcode( 'polls_archive', array( __CLASS__, 'polls_archive_shortcode' ) );

		// Set up cron jobs.
		self::setup_cron_jobs();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'wp-polls' );
	}

	/**
	 * Enqueue front-end scripts and styles.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function enqueue_scripts() {
		$poll_styling = get_option( 'poll_stylesheet_url' );
		
		// Custom or default poll CSS.
		if ( 'use_custom' === $poll_styling ) {
			$custom_css = get_option( 'poll_custom_css' );
			if ( ! empty( $custom_css ) ) {
				wp_enqueue_style( 'wp-polls-custom', WP_POLLS_PLUGIN_URL . 'polls-css.css', array(), WP_POLLS_VERSION );
				wp_add_inline_style( 'wp-polls-custom', $custom_css );
			}
		} else {
			wp_enqueue_style( 'wp-polls', $poll_styling, array(), WP_POLLS_VERSION );
		}

		// Polls archive page might need ranked choice CSS if the feature is enabled.
		if ( WP_Polls_Utility::is_poll_archive() && (bool) get_option( 'poll_enable_ranked_choice', false ) ) {
			wp_enqueue_style( 'wp-polls-ranked-choice', WP_POLLS_PLUGIN_URL . 'polls-ranked-choice.css', array( 'wp-polls' ), WP_POLLS_VERSION );
		}

		// Check if any published polls have ranked choice voting enabled.
		global $wpdb;
		$has_ranked_polls = $wpdb->get_var(
			"SELECT 1 FROM $wpdb->pollsq 
			WHERE pollq_active = 1 
			AND pollq_type = 'ranked' 
			LIMIT 1"
		);

		// Poll JavaScript.
		wp_enqueue_script( 'wp-polls', WP_POLLS_PLUGIN_URL . 'polls-js.js', array( 'jquery' ), WP_POLLS_VERSION, true );
		
		// Load ranked choice script if needed.
		if ( $has_ranked_polls ) {
			wp_enqueue_script( 'wp-polls-ranked-choice', WP_POLLS_PLUGIN_URL . 'polls-ranked-choice.js', array( 'jquery', 'wp-polls' ), WP_POLLS_VERSION, true );
		}

		// Localize script.
		wp_localize_script(
			'wp-polls',
			'pollsL10n',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'text_wait'          => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
				'text_valid'         => __( 'Please choose a valid poll answer.', 'wp-polls' ),
				'text_multiple'      => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
				'show_loading'       => (int) get_option( 'poll_ajax_style' ),
				'show_fading'        => (int) get_option( 'poll_fade' ),
				'nonce'              => wp_create_nonce( 'wp-polls-nonce' ),
			)
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 2.78.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		// Only load on poll admin pages.
		if ( false === strpos( $hook, 'wp-polls' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-polls-admin', WP_POLLS_PLUGIN_URL . 'polls-admin-css.css', array(), WP_POLLS_VERSION );
		wp_enqueue_script( 'wp-polls-admin', WP_POLLS_PLUGIN_URL . 'polls-admin-js.js', array( 'jquery' ), WP_POLLS_VERSION, true );
		
		// Localize admin script.
		wp_localize_script(
			'wp-polls-admin',
			'pollsAdminL10n',
			array(
				'admin_ajax_url'     => admin_url( 'admin-ajax.php' ),
				'text_delete_poll'   => __( 'You are about to delete this poll.', 'wp-polls' ),
				'text_delete_answer' => __( 'You are about to delete this poll answer.', 'wp-polls' ),
				'text_delete_log'    => __( 'You are about to delete all logs.', 'wp-polls' ),
				'nonce'              => wp_create_nonce( 'wp-polls-admin-nonce' ),
			)
		);
	}

	/**
	 * Register the poll widget.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function register_widgets() {
		register_widget( 'WP_Polls_Widget' );
	}

	/**
	 * Shortcode to display a poll.
	 *
	 * @since 2.78.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output of the poll.
	 */
	public static function poll_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'type' => '',
			),
			$atts,
			'poll'
		);

		$poll_id = (int) $atts['id'];
		
		if ( 0 === $poll_id ) {
			$poll_id = self::get_latest_poll_id();
		}
		
		ob_start();
		display_poll_ajax( $poll_id );
		return ob_get_clean();
	}

	/**
	 * Shortcode to display the polls archive.
	 *
	 * @since 2.78.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output of the poll.
	 */
	public static function polls_archive_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 0,
				'page'  => 0,
			),
			$atts,
			'polls_archive'
		);

		$limit = (int) $atts['limit'];
		$page = (int) $atts['page'];

		ob_start();
		display_polls_archive( $limit, $page );
		return ob_get_clean();
	}

	/**
	 * Set up scheduled cron jobs for polls.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function setup_cron_jobs() {
		wp_clear_scheduled_hook( 'polls_cron' );
		if ( ! wp_next_scheduled( 'polls_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'polls_cron' );
		}
	}

	/**
	 * Check and update polls status based on expiry and scheduled times.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function update_polls_status() {
		global $wpdb;
		
		// Close Poll with caching.
		$current_hour = gmdate( 'YmdH' );
		$cache_key = 'wp_polls_close_' . $current_hour;
		$close_polls = wp_cache_get( $cache_key );
		
		if ( false === $close_polls ) {
			$close_polls = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < %d AND pollq_expiry != 0 AND pollq_active != 0",
					current_time( 'timestamp' )
				)
			);
			wp_cache_set( $cache_key, $close_polls, '', HOUR_IN_SECONDS );
		}
		
		// Open Future Polls.
		$active_polls = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= %d AND pollq_active = -1",
				current_time( 'timestamp' )
			)
		);
		
		// Update Latest Poll If Future Poll Is Opened.
		if ( $active_polls ) {
			update_option( 'poll_latestpoll', self::get_latest_poll_id() );
		}
	}

	/**
	 * Get the latest active poll ID.
	 *
	 * @since 2.78.0
	 * @return int The latest active poll ID.
	 */
	public static function get_latest_poll_id() {
		global $wpdb;
		
		$poll_id = $wpdb->get_var(
			"SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1"
		);
		
		return (int) $poll_id;
	}

	/**
	 * Output JavaScript variables in the header.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function poll_header_javascript() {
		// Only output if AJAX style is enabled.
		if ( (int) get_option( 'poll_ajax_style' ) === 1 ) {
			$loading_image = plugins_url( 'wp-polls/images/loading.gif' );
			echo '<script type="text/javascript">'."\n";
			echo '/* <![CDATA[ */'."\n";
			echo 'var poll_image = "' . esc_url( $loading_image ) . '";'."\n";
			echo '/* ]]> */'."\n";
			echo '</script>'."\n";
		}
	}

	/**
	 * Output JavaScript variables in the footer.
	 *
	 * @since 2.78.0
	 * @return void
	 */
	public static function poll_footer_javascript() {
		// Put any footer JS here if needed in the future.
	}
}
