<?php
/**
 * The WP-Stats integration.
 *
 * WP-Stats collects the sections it renders by firing one filter, and each
 * contributing plugin answers with an entry keyed by its own slug. Nothing here
 * reads WP-Stats' option row and WP-Stats reads none of ours, so a section can
 * never be rendered for a plugin that is not installed, and turning the toggle
 * off on Poll Options cannot affect any other plugin's section.
 *
 * Loaded unconditionally. With WP-Stats absent the filter is never fired and
 * this class simply does nothing, which is why there is no class_exists()
 * probing between the two plugins.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contributes the WP-Polls section to the WP-Stats page.
 */
class WP_Polls_WPStats {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_sections', array( __CLASS__, 'register_section' ) );
	}

	/**
	 * Add the WP-Polls section, unless the site has opted out.
	 *
	 * @param array $sections Sections keyed by plugin slug with underscores.
	 * @return array
	 */
	public static function register_section( $sections ) {
		// Read through the option accessor rather than get_option() so an
		// install whose row has not been written yet gets the default, which is
		// on. A raw read would see nothing and read that as an opt-out.
		if ( ! WP_Polls_Options::get( 'stats_display', true ) ) {
			// Opted out: contribute nothing rather than an empty section.
			return $sections;
		}

		$sections['wp_polls'] = array(
			'title'    => __( 'Polls', 'wp-polls' ),
			'priority' => 10,
			'render'   => array( __CLASS__, 'render' ),
		);

		return $sections;
	}

	/**
	 * Echo the body of the WP-Polls section.
	 *
	 * The heading is WP-Stats' to print: its own listener on
	 * wp_stats_section_wp_polls echoes the title and then calls this. Echoing
	 * rather than returning matters too - WP-Stats assembles the page inside an
	 * output buffer, so a returned string would be dropped without a word.
	 *
	 * @return void
	 */
	public static function render() {
		$questions = get_pollquestions( false );
		$answers   = get_pollanswers( false );
		$votes     = get_pollvotes( false );

		echo '<ul>' . "\n";
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: The number of polls created. */
					_n( '<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', $questions, 'wp-polls' ),
					esc_html( number_format_i18n( $questions ) )
				)
			)
		);
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: The number of poll answers given. */
					_n( '<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', $answers, 'wp-polls' ),
					esc_html( number_format_i18n( $answers ) )
				)
			)
		);
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: The number of votes cast. */
					_n( '<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', $votes, 'wp-polls' ),
					esc_html( number_format_i18n( $votes ) )
				)
			)
		);
		echo '</ul>' . "\n";
	}
}
