<?php
/**
 * Settings API registration for the Poll Options and Poll Templates screens.
 *
 * Both screens write into the same poll_options row. That matters: the Settings
 * API hands sanitize_callback only the fields the submitting form rendered,
 * and update_option() then replaces the entire row - so a naive callback that
 * returns its input would erase whichever half of the settings the other
 * screen owns. self::sanitize() therefore merges the submitted subset into the
 * stored value instead of replacing it.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the single setting and validates what the screens submit.
 */
class Polls_Settings {

	/**
	 * Settings group both screens post under.
	 *
	 * @var string
	 */
	const GROUP = 'poll_options_group';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the one setting.
	 *
	 * Registered once rather than once per screen: register_setting() installs
	 * the callback as a sanitize_option_{$option} filter, so a second
	 * registration for the same option would simply replace the first.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			Polls_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Polls_Options::defaults(),
			)
		);
	}

	/**
	 * Allowed values for the fields that are a fixed set.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'sort.answers_by'    => array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ),
			'sort.results_by'    => array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ),
			'sort.answers_order' => array( 'asc', 'desc' ),
			'sort.results_order' => array( 'asc', 'desc' ),
		);
	}

	/**
	 * Merge and validate a submitted subset of the option.
	 *
	 * @param mixed $input Whatever the submitting screen posted.
	 * @return array The complete option, ready to store.
	 */
	public static function sanitize( $input ) {
		$current = Polls_Options::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		// --- Poll Options screen -------------------------------------------
		if ( isset( $input['bar'] ) && is_array( $input['bar'] ) ) {
			$bar                          = $input['bar'];
			$current['bar']['style']      = isset( $bar['style'] ) ? sanitize_text_field( $bar['style'] ) : $current['bar']['style'];
			$current['bar']['background'] = isset( $bar['background'] ) ? Polls_Core::sanitize_bar_color( $bar['background'] ) : $current['bar']['background'];
			$current['bar']['border']     = isset( $bar['border'] ) ? Polls_Core::sanitize_bar_color( $bar['border'] ) : $current['bar']['border'];
			$current['bar']['height']     = isset( $bar['height'] ) ? max( 1, (int) $bar['height'] ) : $current['bar']['height'];

			if ( ! in_array( $current['bar']['style'], self::bar_styles(), true ) ) {
				$current['bar']['style'] = 'gradient';
			}
		}

		if ( isset( $input['ajax'] ) && is_array( $input['ajax'] ) ) {
			$current['ajax']['loading'] = empty( $input['ajax']['loading'] ) ? 0 : 1;
			$current['ajax']['fading']  = empty( $input['ajax']['fading'] ) ? 0 : 1;
		}

		if ( isset( $input['sort'] ) && is_array( $input['sort'] ) ) {
			foreach ( self::choices() as $path => $allowed ) {
				list( , $key ) = explode( '.', $path );
				if ( isset( $input['sort'][ $key ] ) && in_array( $input['sort'][ $key ], $allowed, true ) ) {
					$current['sort'][ $key ] = $input['sort'][ $key ];
				}
			}
		}

		if ( isset( $input['archive'] ) && is_array( $input['archive'] ) ) {
			$archive = $input['archive'];
			if ( isset( $archive['per_page'] ) ) {
				$current['archive']['per_page'] = max( 1, (int) $archive['per_page'] );
			}
			if ( isset( $archive['display_poll'] ) ) {
				$current['archive']['display_poll'] = (int) $archive['display_poll'];
			}
			if ( isset( $archive['url'] ) ) {
				$current['archive']['url'] = esc_url_raw( wp_strip_all_tags( trim( $archive['url'] ) ) );
			}
		}

		foreach ( array( 'current_poll', 'close', 'logging_method', 'cookie_expiry', 'allow_to_vote' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = (int) $input[ $key ];
			}
		}

		if ( isset( $input['ip_header'] ) ) {
			$current['ip_header'] = sanitize_text_field( $input['ip_header'] );
		}

		// --- Poll Templates screen -----------------------------------------
		if ( isset( $input['templates'] ) && is_array( $input['templates'] ) ) {
			foreach ( Polls_Options::template_keys() as $key ) {
				if ( ! isset( $input['templates'][ $key ] ) ) {
					continue;
				}
				$current['templates'][ $key ] = self::sanitize_template( $key, $input['templates'][ $key ] );
			}
		}

		return $current;
	}

	/**
	 * The poll bar styles the Poll Options screen offers.
	 *
	 * Up to 3.0.0 this globbed images/ for directories holding a pollbg.gif, so
	 * the list of styles was whatever happened to be on disk and every entry
	 * shipped its own hardcoded colour. The shading is CSS now, so there are
	 * exactly two: a flat fill, and the same fill under a translucent gradient.
	 *
	 * @return array
	 */
	public static function bar_styles() {
		return array( 'flat', 'gradient' );
	}

	/**
	 * Filter one template through kses.
	 *
	 * The footer templates need input and anchor elements carrying
	 * data-poll-action, which wp_kses_post() alone does not allow on input.
	 * onclick stays disallowed - removing it is what 3.0.0 was for.
	 *
	 * @param string $key      Template slug.
	 * @param string $template Submitted markup.
	 * @return string
	 */
	public static function sanitize_template( $key, $template ) {
		$template = trim( (string) $template );

		$needs_input = array( 'votebody', 'votefooter', 'resultfooter', 'resultfooter2' );
		if ( ! in_array( $key, $needs_input, true ) ) {
			return wp_kses_post( $template );
		}

		$allowed          = wp_kses_allowed_html( 'post' );
		$allowed['input'] = array(
			'type'             => true,
			'id'               => true,
			'name'             => true,
			'value'            => true,
			'class'            => true,
			'data-poll-id'     => true,
			'data-poll-action' => true,
		);

		return wp_kses( $template, $allowed );
	}
}
