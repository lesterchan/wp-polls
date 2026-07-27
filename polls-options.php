<?php
/**
 * Poll Options admin screen.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

// Check Whether User Can Manage Polls.
if ( ! current_user_can( 'manage_polls' ) ) {
	die( 'Access Denied' );
}

// The poll bar styles are a fixed pair now that the shading is CSS rather than
// a pollbg.gif tile per directory under images/. Polls_Settings owns the list so
// that the screen cannot offer a style the sanitiser would reject.
$poll_bars = Polls_Settings::bar_styles();

$poll_bar_labels = array(
	'flat'     => __( 'Flat', 'wp-polls' ),
	'gradient' => __( 'Gradient', 'wp-polls' ),
);

// Saving is handled by the Settings API: the form posts to options.php,
// which validates the nonce and hands the input to Polls_Settings::sanitize().
// Polls_Core::cron_polls_place() still has to run afterwards because the cookie/log expiry
// option decides the schedule.
add_action(
	'update_option_' . Polls_Options::OPTION,
	function () {
		Polls_Core::cron_polls_place();
	}
);

$poll_options = array( 'ip_header' => Polls_Options::get( 'ip_header', '' ) );
?>
<script type="text/javascript">
/* <![CDATA[*/
(function () {
	function poll_field_value(id) {
		var field = document.getElementById(id);
		return field ? field.value : "";
	}
	// The background-image for each style comes from the same helper the front
	// end uses, so the preview cannot drift from what actually renders.
	var pollbar_images = <?php echo wp_json_encode( array_combine( $poll_bars, array_map( array( 'Polls_Core', 'bar_image' ), $poll_bars ) ) ); ?>;
	function update_pollbar(where) {
		var pollbar_background = "#" + poll_field_value("poll_bar_bg");
		var pollbar_border = "#" + poll_field_value("poll_bar_border");
		var pollbar_height = poll_field_value("poll_bar_height") + "px";
		var preview = document.getElementById("wp-polls-pollbar");
		if(where == "background") {
			var background_preview = document.getElementById("wp-polls-pollbar-bg");
			if(background_preview) {
				background_preview.style.backgroundColor = pollbar_background;
			}
		} else if(where == "border") {
			var border_preview = document.getElementById("wp-polls-pollbar-border");
			if(border_preview) {
				border_preview.style.backgroundColor = pollbar_border;
			}
		}
		// The preview is the real front end markup under the real front end
		// stylesheet, so setting the four custom properties is the whole job.
		if(preview) {
			var checked_style = document.querySelector("input[name='poll_options[bar][style]']:checked");
			var pollbar_style = checked_style ? checked_style.value : "";
			preview.style.setProperty("--wp-polls-bar-background", pollbar_background);
			preview.style.setProperty("--wp-polls-bar-border", pollbar_border);
			preview.style.setProperty("--wp-polls-bar-height", pollbar_height);
			preview.style.setProperty("--wp-polls-bar-image", pollbar_images[pollbar_style] || "none");
		}
		// The swatches follow the colours too, so the two styles stay comparable
		// while the fields are being edited. Their height stays fixed: it is set
		// in polls-admin-css.css so the gradient is visible at any bar height.
		var swatches = document.querySelectorAll(".wp-polls-swatch");
		for(var i = 0; i < swatches.length; i++) {
			swatches[i].style.setProperty("--wp-polls-bar-background", pollbar_background);
			swatches[i].style.setProperty("--wp-polls-bar-border", pollbar_border);
		}
	}
	document.addEventListener("click", function (event) {
		var target = event.target;
		if(!target || typeof target.closest !== "function") {
			return;
		}
		// Picking a style no longer touches the height field: it used to be set
		// from the height of that style's pollbg.gif, and there is no image now.
		if(target.closest('[data-poll-action="pollbar-style"]')) {
			update_pollbar("style");
		}
	});
	document.addEventListener("focusout", function (event) {
		var target = event.target;
		if(!target || typeof target.closest !== "function") {
			return;
		}
		var field = target.closest('[data-poll-action="pollbar-update"]');
		if(field) {
			update_pollbar(field.getAttribute("data-poll-field") || "");
		}
	});
})();
/* ]]> */
</script>
<?php settings_errors(); ?>
<form id="poll_options_form" method="post" action="options.php">
<?php settings_fields( Polls_Settings::GROUP ); ?>
<div class="wrap">
	<h2><?php esc_html_e( 'Poll Options', 'wp-polls' ); ?></h2>
	<!-- Poll Bar Style -->
	<h3><?php esc_html_e( 'Poll Bar Style', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Style', 'wp-polls' ); ?></th>
			<td colspan="2">
				<?php
					$pollbar = Polls_Options::get( 'bar' );
				foreach ( $poll_bars as $pollbar_style_name ) {
					// Each swatch is the real bar markup with only the image
					// property overridden, so what is offered is what is rendered.
					// No height here: polls-admin-css.css fixes the swatches at a size
					// the gradient is actually visible at.
					$pollbar_swatch = sprintf(
						'--wp-polls-bar-background: #%1$s; --wp-polls-bar-border: #%2$s; --wp-polls-bar-image: %3$s;',
						Polls_Core::sanitize_bar_color( $pollbar['background'] ),
						Polls_Core::sanitize_bar_color( $pollbar['border'] ),
						Polls_Core::bar_image( $pollbar_style_name )
					);
					echo '<p>' . "\n";
					echo '<input type="radio" id="poll_bar_style-' . esc_attr( $pollbar_style_name ) . '" name="poll_options[bar][style]" value="' . esc_attr( $pollbar_style_name ) . '"';
					checked( $pollbar_style_name, $pollbar['style'] );
					echo ' data-poll-action="pollbar-style" />';
					echo '<label for="poll_bar_style-' . esc_attr( $pollbar_style_name ) . '">&nbsp;&nbsp;&nbsp;';
					echo '<span class="wp-polls wp-polls-swatch" style="' . esc_attr( $pollbar_swatch ) . '">';
					echo '<span class="wp-polls-bar"><span class="wp-polls-bar-fill" style="width: 100%;"></span></span>';
					echo '</span>';
					echo '&nbsp;&nbsp;&nbsp;' . esc_html( isset( $poll_bar_labels[ $pollbar_style_name ] ) ? $poll_bar_labels[ $pollbar_style_name ] : $pollbar_style_name ) . '</label>';
					echo '</p>' . "\n";
				}
				?>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Background', 'wp-polls' ); ?></th>
			<td width="10%" dir="ltr">#<input type="text" id="poll_bar_bg" name="poll_options[bar][background]" value="<?php echo esc_attr( $pollbar['background'] ); ?>" size="6" maxlength="6" data-poll-action="pollbar-update" data-poll-field="background" /></td>
			<td><div id="wp-polls-pollbar-bg" style="background-color: #<?php echo esc_attr( Polls_Core::sanitize_bar_color( $pollbar['background'] ) ); ?>;"></div></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Border', 'wp-polls' ); ?></th>
			<td width="10%" dir="ltr">#<input type="text" id="poll_bar_border" name="poll_options[bar][border]" value="<?php echo esc_attr( $pollbar['border'] ); ?>" size="6" maxlength="6" data-poll-action="pollbar-update" data-poll-field="border" /></td>
			<td><div id="wp-polls-pollbar-border" style="background-color: #<?php echo esc_attr( Polls_Core::sanitize_bar_color( $pollbar['border'] ) ); ?>;"></div></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Height', 'wp-polls' ); ?></th>
			<td colspan="2" dir="ltr"><input type="text" id="poll_bar_height" name="poll_options[bar][height]" value="<?php echo esc_attr( $pollbar['height'] ); ?>" size="2" maxlength="2" data-poll-action="pollbar-update" data-poll-field="height" />px</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Your poll bar will look like this', 'wp-polls' ); ?></th>
			<td colspan="2">
				<?php
					// Rendered with the front end classes under the front end
					// stylesheet, so this is the bar itself rather than an
					// approximation of it maintained separately.
					$pollbar_preview = sprintf(
						'--wp-polls-bar-height: %1$dpx; --wp-polls-bar-background: #%2$s; --wp-polls-bar-border: #%3$s; --wp-polls-bar-image: %4$s;',
						(int) $pollbar['height'],
						Polls_Core::sanitize_bar_color( $pollbar['background'] ),
						Polls_Core::sanitize_bar_color( $pollbar['border'] ),
						Polls_Core::bar_image( $pollbar['style'] )
					);
					echo '<div id="wp-polls-pollbar" class="wp-polls" style="' . esc_attr( $pollbar_preview ) . '">';
					echo '<div class="wp-polls-bar"><div class="wp-polls-bar-fill" style="width: 100%;"></div></div>';
					echo '</div>' . "\n";
					?>
			</td>
		</tr>
	</table>

	<!-- Polls AJAX Style -->
	<?php $poll_ajax_style = Polls_Options::get( 'ajax' ); ?>
	<h3><?php esc_html_e( 'Polls AJAX Style', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Show Loading Image With Text', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[ajax][loading]" size="1">
					<option value="0"<?php selected( '0', $poll_ajax_style['loading'] ); ?>><?php esc_html_e( 'No', 'wp-polls' ); ?></option>
					<option value="1"<?php selected( '1', $poll_ajax_style['loading'] ); ?>><?php esc_html_e( 'Yes', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Show Fading In And Fading Out Of Poll', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[ajax][fading]" size="1">
					<option value="0"<?php selected( '0', $poll_ajax_style['fading'] ); ?>><?php esc_html_e( 'No', 'wp-polls' ); ?></option>
					<option value="1"<?php selected( '1', $poll_ajax_style['fading'] ); ?>><?php esc_html_e( 'Yes', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<!-- Sorting Of Poll Answers -->
	<h3><?php esc_html_e( 'Sorting Of Poll Answers', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Sort Poll Answers By:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[sort][answers_by]" size="1">
					<option value="polla_votes"<?php selected( 'polla_votes', Polls_Options::get( 'sort.answers_by' ) ); ?>><?php esc_html_e( 'Votes Cast', 'wp-polls' ); ?></option>
					<option value="polla_aid"<?php selected( 'polla_aid', Polls_Options::get( 'sort.answers_by' ) ); ?>><?php esc_html_e( 'Exact Order', 'wp-polls' ); ?></option>
					<option value="polla_answers"<?php selected( 'polla_answers', Polls_Options::get( 'sort.answers_by' ) ); ?>><?php esc_html_e( 'Alphabetical Order', 'wp-polls' ); ?></option>
					<option value="RAND()"<?php selected( 'RAND()', Polls_Options::get( 'sort.answers_by' ) ); ?>><?php esc_html_e( 'Random Order', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Sort Order Of Poll Answers:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[sort][answers_order]" size="1">
					<option value="asc"<?php selected( 'asc', Polls_Options::get( 'sort.answers_order' ) ); ?>><?php esc_html_e( 'Ascending', 'wp-polls' ); ?></option>
					<option value="desc"<?php selected( 'desc', Polls_Options::get( 'sort.answers_order' ) ); ?>><?php esc_html_e( 'Descending', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<!-- Sorting Of Poll Results -->
	<h3><?php esc_html_e( 'Sorting Of Poll Results', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Sort Poll Results By:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[sort][results_by]" size="1">
					<option value="polla_votes"<?php selected( 'polla_votes', Polls_Options::get( 'sort.results_by' ) ); ?>><?php esc_html_e( 'Votes Cast', 'wp-polls' ); ?></option>
					<option value="polla_aid"<?php selected( 'polla_aid', Polls_Options::get( 'sort.results_by' ) ); ?>><?php esc_html_e( 'Exact Order', 'wp-polls' ); ?></option>
					<option value="polla_answers"<?php selected( 'polla_answers', Polls_Options::get( 'sort.results_by' ) ); ?>><?php esc_html_e( 'Alphabetical Order', 'wp-polls' ); ?></option>
					<option value="RAND()"<?php selected( 'RAND()', Polls_Options::get( 'sort.results_by' ) ); ?>><?php esc_html_e( 'Random Order', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Sort Order Of Poll Results:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[sort][results_order]" size="1">
					<option value="asc"<?php selected( 'asc', Polls_Options::get( 'sort.results_order' ) ); ?>><?php esc_html_e( 'Ascending', 'wp-polls' ); ?></option>
					<option value="desc"<?php selected( 'desc', Polls_Options::get( 'sort.results_order' ) ); ?>><?php esc_html_e( 'Descending', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<!-- Allow To Vote -->
	<h3><?php esc_html_e( 'Allow To Vote', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Who Is Allowed To Vote?', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[allow_to_vote]" size="1">
					<option value="0"<?php selected( '0', Polls_Options::get( 'allow_to_vote' ) ); ?>><?php esc_html_e( 'Guests Only', 'wp-polls' ); ?></option>
					<option value="1"<?php selected( '1', Polls_Options::get( 'allow_to_vote' ) ); ?>><?php esc_html_e( 'Registered Users Only', 'wp-polls' ); ?></option>
					<option value="2"<?php selected( '2', Polls_Options::get( 'allow_to_vote' ) ); ?>><?php esc_html_e( 'Registered Users And Guests', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<!-- Logging Method -->
	<h3><?php esc_html_e( 'Logging Method', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr valign="top">
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Logging Method:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[logging_method]" size="1">
					<option value="0"<?php selected( '0', Polls_Options::get( 'logging_method' ) ); ?>><?php esc_html_e( 'Do Not Log', 'wp-polls' ); ?></option>
					<option value="1"<?php selected( '1', Polls_Options::get( 'logging_method' ) ); ?>><?php esc_html_e( 'Logged By Cookie', 'wp-polls' ); ?></option>
					<option value="2"<?php selected( '2', Polls_Options::get( 'logging_method' ) ); ?>><?php esc_html_e( 'Logged By IP', 'wp-polls' ); ?></option>
					<option value="3"<?php selected( '3', Polls_Options::get( 'logging_method' ) ); ?>><?php esc_html_e( 'Logged By Cookie And IP', 'wp-polls' ); ?></option>
					<option value="4"<?php selected( '4', Polls_Options::get( 'logging_method' ) ); ?>><?php esc_html_e( 'Logged By Username', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Expiry Time For Cookie And Log:', 'wp-polls' ); ?></th>
			<td><input type="text" name="poll_options[cookie_expiry]" value="<?php echo (int) esc_attr( Polls_Options::get( 'cookie_expiry' ) ); ?>" size="10" /> <?php esc_html_e( 'seconds (0 to disable)', 'wp-polls' ); ?></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Header That Contains The IP:', 'wp-polls' ); ?></th>
			<td>
				<input type="text" name="poll_options[ip_header]" value="<?php echo esc_attr( $poll_options['ip_header'] ); ?>" size="30" placeholder="REMOTE_ADDR" />
				<p class="description">
					<?php esc_html_e( 'Leave blank to use REMOTE_ADDR. Only set this if a proxy in front of WordPress always overwrites the header, because visitors can otherwise forge it and vote more than once.', 'wp-polls' ); ?>
					<br />
					<?php
					printf(
						/* translators: 1: The WP_POLLS_TRUST_PROXY constant, 2: the wp_polls_trust_proxy filter, both in code spans. */
						esc_html__( 'Example: HTTP_X_FORWARDED_FOR. You can also opt in with the %1$s constant or the %2$s filter.', 'wp-polls' ),
						'<code>WP_POLLS_TRUST_PROXY</code>',
						'<code>wp_polls_trust_proxy</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>

	<!-- Poll Archive -->
	<h3><?php esc_html_e( 'Poll Archive', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Number Of Polls Per Page:', 'wp-polls' ); ?></th>
			<td><input type="text" name="poll_options[archive][per_page]" value="<?php echo (int) esc_attr( Polls_Options::get( 'archive.per_page' ) ); ?>" size="2" /></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Type Of Polls To Display In Poll Archive:', 'wp-polls' ); ?></th>
			<td>
				<select name="poll_options[archive][display_poll]" size="1">
					<option value="1"<?php selected( '1', Polls_Options::get( 'archive.display_poll' ) ); ?>><?php esc_html_e( 'Closed Polls Only', 'wp-polls' ); ?></option>
					<option value="2"<?php selected( '2', Polls_Options::get( 'archive.display_poll' ) ); ?>><?php esc_html_e( 'Opened Polls Only', 'wp-polls' ); ?></option>
					<option value="3"<?php selected( '3', Polls_Options::get( 'archive.display_poll' ) ); ?>><?php esc_html_e( 'Closed And Opened Polls', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Archive URL:', 'wp-polls' ); ?></th>
			<td><input type="text" name="poll_options[archive][url]" value="<?php echo esc_url( Polls_Options::get( 'archive.url' ) ); ?>" size="50" dir="ltr" /></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Note', 'wp-polls' ); ?></th>
			<td><em><?php esc_html_e( 'Only polls\' results will be shown in the Poll Archive regardless of whether the poll is closed or opened.', 'wp-polls' ); ?></em></td>
		</tr>
	</table>

	<!-- Current Active Poll -->
	<h3><?php esc_html_e( 'Current Active Poll', 'wp-polls' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Current Active Poll', 'wp-polls' ); ?>:</th>
			<td>
				<select name="poll_options[current_poll]" size="1">
					<option value="-1"<?php selected( -1, Polls_Options::get( 'current_poll' ) ); ?>><?php esc_html_e( 'Do NOT Display Poll (Disable)', 'wp-polls' ); ?></option>
					<option value="-2"<?php selected( -2, Polls_Options::get( 'current_poll' ) ); ?>><?php esc_html_e( 'Display Random Poll', 'wp-polls' ); ?></option>
					<option value="0"<?php selected( 0, Polls_Options::get( 'current_poll' ) ); ?>><?php esc_html_e( 'Display Latest Poll', 'wp-polls' ); ?></option>
					<optgroup>&nbsp;</optgroup>
					<?php
						$polls = $wpdb->get_results( "SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC" );
					if ( $polls ) {
						$current_poll = (int) Polls_Options::get( 'current_poll' );
						foreach ( $polls as $poll ) {
							$poll_question = removeslashes( $poll->pollq_question );
							$poll_id       = (int) $poll->pollq_id;
							echo '<option value="' . esc_attr( $poll_id ) . '"';
							selected( $poll_id, $current_poll );
							echo '>' . esc_html( $poll_question ) . '</option>';
						}
					}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'When Poll Is Closed', 'wp-polls' ); ?>:</th>
			<td>
				<select name="poll_options[close]" size="1">
					<option value="1"<?php selected( 1, Polls_Options::get( 'close' ) ); ?>><?php esc_html_e( 'Display Poll\'s Results', 'wp-polls' ); ?></option>
					<option value="3"<?php selected( 3, Polls_Options::get( 'close' ) ); ?>><?php esc_html_e( 'Display Disabled Poll\'s Voting Form', 'wp-polls' ); ?></option>
					<option value="2"<?php selected( 2, Polls_Options::get( 'close' ) ); ?>><?php esc_html_e( 'Do Not Display Poll In Post/Sidebar', 'wp-polls' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<!-- Submit Button -->
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-polls' ); ?>" />
	</p>
</div>
</form>
