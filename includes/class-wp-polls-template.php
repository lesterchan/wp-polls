<?php
/**
 * Default poll templates.
 *
 * Single source of truth for the stock markup. Before 3.0.0 the same strings
 * were written out twice - once in the activation routine and once in the
 * JavaScript behind the "Restore Default Template" buttons - which is how the
 * two drifted apart and how the inline onclick handlers survived in one copy
 * after being removed from the other.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the default template markup.
 */
class WP_Polls_Template {

	/**
	 * Every default template, keyed by slug.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'voteheader'              => '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>' .
				'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">' .
				'<ul class="wp-polls-ul">',

			'votebody'                => '<li><input type="%POLL_CHECKBOX_RADIO%" id="poll-answer-%POLL_ANSWER_ID%" name="poll_%POLL_ID%" value="%POLL_ANSWER_ID%" /> <label for="poll-answer-%POLL_ANSWER_ID%">%POLL_ANSWER%</label></li>',

			'votefooter'              => '</ul>' .
				'<p style="text-align: center;"><input type="button" name="vote" value="' . __( 'Vote', 'wp-polls' ) . '" class="Buttons" data-poll-id="%POLL_ID%" data-poll-action="vote" /></p>' .
				'<p style="text-align: center;"><a href="#ViewPollResults" data-poll-id="%POLL_ID%" data-poll-action="result" title="' . __( 'View Results Of This Poll', 'wp-polls' ) . '">' . __( 'View Results', 'wp-polls' ) . '</a></p>' .
				'</div>',

			'resultheader'            => '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>' .
				'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">' .
				'<ul class="wp-polls-ul">',

			// The bar is a track holding a fill rather than one div sized by width,
			// so the border no longer eats into the measurement and a unanimous
			// answer can render at a true 100%. It carries aria-hidden because the
			// percentage and the vote count are already in the <small> beside it -
			// an ARIA-labelled bar would only make a screen reader read the same
			// numbers a second time.
			'resultbody'              => '<li>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%' . __( ',', 'wp-polls' ) . ' %POLL_ANSWER_VOTES% ' . __( 'Votes', 'wp-polls' ) . ')</small><div class="wp-polls-bar" aria-hidden="true"><div class="wp-polls-bar-fill" style="width: %POLL_ANSWER_PERCENTAGE%%;"></div></div></li>',

			// The "you voted for this" tooltip moves onto the <strong> that already
			// marks the choice visually, because the bar itself is now decorative.
			'resultbody2'             => '<li><strong title="' . __( 'You Have Voted For This Choice', 'wp-polls' ) . '"><i>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%' . __( ',', 'wp-polls' ) . ' %POLL_ANSWER_VOTES% ' . __( 'Votes', 'wp-polls' ) . ')</small></i></strong><div class="wp-polls-bar" aria-hidden="true"><div class="wp-polls-bar-fill" style="width: %POLL_ANSWER_PERCENTAGE%%;"></div></div></li>',

			'resultfooter'            => '</ul>' .
				'<p style="text-align: center;">' . __( 'Total Voters', 'wp-polls' ) . ': <strong>%POLL_TOTALVOTERS%</strong></p>' .
				'</div>',

			'resultfooter2'           => '</ul>' .
				'<p style="text-align: center;">' . __( 'Total Voters', 'wp-polls' ) . ': <strong>%POLL_TOTALVOTERS%</strong></p>' .
				'<p style="text-align: center;"><a href="#VotePoll" data-poll-id="%POLL_ID%" data-poll-action="booth" title="' . __( 'Vote For This Poll', 'wp-polls' ) . '">' . __( 'Vote', 'wp-polls' ) . '</a></p>' .
				'</div>',

			'pollarchivelink'         => '<ul>' .
				'<li><a href="%POLL_ARCHIVE_URL%">' . __( 'Polls Archive', 'wp-polls' ) . '</a></li>' .
				'</ul>',

			'pollarchiveheader'       => '',

			'pollarchivefooter'       => '<p>' . __( 'Start Date:', 'wp-polls' ) . ' %POLL_START_DATE%<br />' . __( 'End Date:', 'wp-polls' ) . ' %POLL_END_DATE%</p>',

			'pollarchivepagingheader' => '',

			'pollarchivepagingfooter' => '',

			'disable'                 => __( 'Sorry, there are no polls available at the moment.', 'wp-polls' ),

			'error'                   => __( 'An error has occurred when processing your poll.', 'wp-polls' ),
		);
	}

	/**
	 * One default template by slug.
	 *
	 * @param string $key Template slug.
	 * @return string
	 */
	public static function get_default( $key ) {
		$defaults = self::defaults();

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
}
