<?php
/**
 * The Polls widget.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders a poll in a sidebar.
 */
class WP_Polls_Widget extends WP_Widget {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'polls-widget', __( 'Polls', 'wp-polls' ), array( 'description' => __( 'WP-Polls polls', 'wp-polls' ) ) );
	}

	/**
	 * The settings a stored instance is filled out against.
	 *
	 * Read by both widget() and form() so the two cannot disagree about what an
	 * unset key means. The title matches the widget's own name, because a
	 * sidebar with no heading above a poll reads as part of whatever sits above
	 * it.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'title'               => __( 'Polls', 'wp-polls' ),
			'poll_id'             => 0,
			'display_pollarchive' => 1,
		);
	}

	/**
	 * Display Widget.
	 *
	 * @param mixed $args     Value.
	 * @param mixed $instance Value.
	 *
	 * @return mixed
	 */
	public function widget( $args, $instance ) {
		// An instance is not guaranteed to hold every key. the_widget() renders
		// this class with whatever array the caller passed and nothing else, and
		// a sidebar can carry an instance stored before a setting existed --
		// reading a key straight out of it printed "Undefined array key" into
		// the middle of the page.
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$title               = apply_filters( 'widget_title', esc_attr( $instance['title'] ) );
		$poll_id             = (int) $instance['poll_id'];
		$display_pollarchive = (int) $instance['display_pollarchive'];
		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
		}
		WP_Polls_Display::get_poll( $poll_id );
		if ( $display_pollarchive ) {
			WP_Polls_Display::display_polls_archive_link();
		}
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * When Widget Control Form Is Posted.
	 *
	 * @param mixed $new_instance Value.
	 * @param mixed $old_instance Value.
	 *
	 * @return mixed
	 */
	public function update( $new_instance, $old_instance ) {
		if ( ! isset( $new_instance['submit'] ) ) {
			return false;
		}
		$instance                        = $old_instance;
		$instance['title']               = wp_strip_all_tags( $new_instance['title'] );
		$instance['poll_id']             = (int) $new_instance['poll_id'];
		$instance['display_pollarchive'] = (int) $new_instance['display_pollarchive'];
		return $instance;
	}

	/**
	 * DIsplay Widget Control Form.
	 *
	 * @param mixed $instance Value.
	 *
	 * @return mixed
	 */
	public function form( $instance ) {
		global $wpdb;
		$instance            = wp_parse_args( (array) $instance, $this->defaults() );
		$title               = esc_attr( $instance['title'] );
		$poll_id             = (int) $instance['poll_id'];
		$display_pollarchive = (int) $instance['display_pollarchive'];
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-polls' ); ?> <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_pollarchive' ) ); ?>"><?php esc_html_e( 'Display Polls Archive Link Below Poll?', 'wp-polls' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'display_pollarchive' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'display_pollarchive' ) ); ?>" class="widefat">
					<option value="0"<?php selected( 0, $display_pollarchive ); ?>><?php esc_html_e( 'No', 'wp-polls' ); ?></option>
					<option value="1"<?php selected( 1, $display_pollarchive ); ?>><?php esc_html_e( 'Yes', 'wp-polls' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'poll_id' ) ); ?>"><?php esc_html_e( 'Poll To Display:', 'wp-polls' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'poll_id' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'poll_id' ) ); ?>" class="widefat">
					<option value="-1"<?php selected( -1, $poll_id ); ?>><?php esc_html_e( 'Do NOT Display Poll (Disable)', 'wp-polls' ); ?></option>
					<option value="-2"<?php selected( -2, $poll_id ); ?>><?php esc_html_e( 'Display Random Poll', 'wp-polls' ); ?></option>
					<option value="0"<?php selected( 0, $poll_id ); ?>><?php esc_html_e( 'Display Latest Poll', 'wp-polls' ); ?></option>
					<optgroup>&nbsp;</optgroup>
					<?php
					$polls = $wpdb->get_results( "SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC" );
					if ( $polls ) {
						foreach ( $polls as $poll ) {
							$pollq_question = wp_kses_post( removeslashes( $poll->pollq_question ) );
							$pollq_id       = (int) $poll->pollq_id;
							if ( $pollq_id === $poll_id ) {
								echo '<option value="' . esc_attr( $pollq_id ) . '" selected="selected">' . wp_kses_post( $pollq_question ) . "</option>\n";
							} else {
								echo '<option value="' . esc_attr( $pollq_id ) . '">' . wp_kses_post( $pollq_question ) . "</option>\n";
							}
						}
					}
					?>
				</select>
			</label>
		</p>
		<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'submit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'submit' ) ); ?>" value="1" />
		<?php
	}
}
