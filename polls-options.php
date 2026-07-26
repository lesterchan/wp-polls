<?php
// Check Whether User Can Manage Polls.
if ( ! current_user_can( 'manage_polls' ) ) {
	die( 'Access Denied' );
}


// Variables Variables Variables.
$base_name = plugin_basename( 'wp-polls/polls-options.php' );
$base_page = 'admin.php?page=' . $base_name;
$id        = isset( $_GET['id'] ) ? (int) sanitize_key( $_GET['id'] ) : 0;


// Get Poll Bar Images.
$pollbar_path = WP_PLUGIN_DIR . '/wp-polls/images';
$poll_bars    = array();
if ( $handle = @opendir( $pollbar_path ) ) {
	while ( false !== ( $filename = readdir( $handle ) ) ) {
		if ( substr( $filename, 0, 1 ) !== '.' && substr( $filename, 0, 2 ) !== '..' ) {
			if ( is_dir( $pollbar_path . '/' . $filename ) ) {
				$poll_bars[ $filename ] = getimagesize( $pollbar_path . '/' . $filename . '/pollbg.gif' );
			}
		}
	}
	closedir( $handle );
}

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
	function set_pollbar_height(height) {
		var field = document.getElementById("poll_bar_height");
		if(field) {
			field.value = height;
		}
	}
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
		} else if(where == "style") {
			var checked_style = document.querySelector("input[name='poll_options[bar][style]']:checked");
			var pollbar_style = checked_style ? checked_style.value : "";
			if(preview) {
				if(pollbar_style == "use_css") {
					preview.style.backgroundImage = "none";
				} else {
					preview.style.backgroundImage = "url('<?php echo esc_url( plugins_url( 'wp-polls/images/' ) ); ?>" + pollbar_style + "/pollbg.gif')";
				}
			}
		}
		if(preview) {
			preview.style.backgroundColor = pollbar_background;
			preview.style.border = "1px solid " + pollbar_border;
			preview.style.height = pollbar_height;
		}
	}
	document.addEventListener("click", function (event) {
		var target = event.target;
		if(!target || typeof target.closest !== "function") {
			return;
		}
		var radio = target.closest('[data-poll-action="pollbar-style"]');
		if(radio) {
			var height = radio.getAttribute("data-poll-height");
			if(height) {
				set_pollbar_height(height);
			}
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
					$pollbar     = Polls_Options::get( 'bar' );
					$pollbar_url = plugins_url( 'wp-polls/images' );
				if ( count( $poll_bars ) > 0 ) {
					foreach ( $poll_bars as $filename => $pollbar_info ) {
						$pollbar_name  = esc_attr( $filename );
						$pollbar_img_h = (int) $pollbar_info[1];
						echo '<p>' . "\n";
						if ( $pollbar['style'] == $filename ) {
							echo '<input type="radio" id="poll_bar_style-' . $pollbar_name . '" name="poll_options[bar][style]" value="' . $pollbar_name . '" checked="checked" data-poll-action="pollbar-style" data-poll-height="' . $pollbar_img_h . '" />';
						} else {
							echo '<input type="radio" id="poll_bar_style-' . $pollbar_name . '" name="poll_options[bar][style]" value="' . $pollbar_name . '" data-poll-action="pollbar-style" data-poll-height="' . $pollbar_img_h . '" />';
						}
						echo '<label for="poll_bar_style-' . $pollbar_name . '">&nbsp;&nbsp;&nbsp;';
						echo '<img src="' . esc_url( $pollbar_url . '/' . $filename . '/pollbg.gif' ) . '" height="' . $pollbar_img_h . '" width="100" alt="pollbg.gif" />';
						echo '&nbsp;&nbsp;&nbsp;(' . esc_html( $filename ) . ')</label>';
						echo '</p>' . "\n";
					}
				}
				?>
				<input type="radio" id="poll_bar_style-use_css" name="poll_options[bar][style]" value="use_css"<?php checked( 'use_css', $pollbar['style'] ); ?> data-poll-action="pollbar-style" /><label for="poll_bar_style-use_css"> <?php esc_html_e( 'Use CSS Style', 'wp-polls' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Background', 'wp-polls' ); ?></th>
			<td width="10%" dir="ltr">#<input type="text" id="poll_bar_bg" name="poll_options[bar][background]" value="<?php echo esc_attr( $pollbar['background'] ); ?>" size="6" maxlength="6" data-poll-action="pollbar-update" data-poll-field="background" /></td>
			<td><div id="wp-polls-pollbar-bg" style="background-color: #<?php echo esc_attr( Polls_Core::_polls_sanitize_hex_color( $pollbar['background'] ) ); ?>;"></div></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Border', 'wp-polls' ); ?></th>
			<td width="10%" dir="ltr">#<input type="text" id="poll_bar_border" name="poll_options[bar][border]" value="<?php echo esc_attr( $pollbar['border'] ); ?>" size="6" maxlength="6" data-poll-action="pollbar-update" data-poll-field="border" /></td>
			<td><div id="wp-polls-pollbar-border" style="background-color: #<?php echo esc_attr( Polls_Core::_polls_sanitize_hex_color( $pollbar['border'] ) ); ?>;"></div></td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Poll Bar Height', 'wp-polls' ); ?></th>
			<td colspan="2" dir="ltr"><input type="text" id="poll_bar_height" name="poll_options[bar][height]" value="<?php echo esc_attr( $pollbar['height'] ); ?>" size="2" maxlength="2" data-poll-action="pollbar-update" data-poll-field="height" />px</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php esc_html_e( 'Your poll bar will look like this', 'wp-polls' ); ?></th>
			<td colspan="2">
				<?php
					$pollbar_height     = (int) $pollbar['height'];
					$pollbar_background = esc_attr( Polls_Core::_polls_sanitize_hex_color( $pollbar['background'] ) );
					$pollbar_border     = esc_attr( Polls_Core::_polls_sanitize_hex_color( $pollbar['border'] ) );
				if ( $pollbar['style'] == 'use_css' ) {
					echo '<div id="wp-polls-pollbar" style="width: 100px; height: ' . $pollbar_height . 'px; background-color: #' . $pollbar_background . '; border: 1px solid #' . $pollbar_border . '"></div>' . "\n";
				} else {
					echo '<div id="wp-polls-pollbar" style="width: 100px; height: ' . $pollbar_height . 'px; background-color: #' . $pollbar_background . '; border: 1px solid #' . $pollbar_border . '; background-image: url(\'' . esc_url( plugins_url( 'wp-polls/images/' . $pollbar['style'] . '/pollbg.gif' ) ) . '\');"></div>' . "\n";
				}
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
			<td><input type="text" name="poll_options[ip_header]" value="<?php echo esc_attr( $poll_options['ip_header'] ); ?>" size="30" /> <?php esc_html_e( 'You can leave it blank to use the default', 'wp-polls' ); ?><br /><?php esc_html_e( 'Example: REMOTE_ADDR', 'wp-polls' ); ?></td>
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
						foreach ( $polls as $poll ) {
							$poll_question = removeslashes( $poll->pollq_question );
							$poll_id       = (int) $poll->pollq_id;
							if ( $poll_id === (int) Polls_Options::get( 'current_poll' ) ) {
								echo '<option value="' . $poll_id . '" selected="selected">' . esc_attr( $poll_question ) . '</option>';
							} else {
								echo '<option value="' . $poll_id . '">' . esc_attr( $poll_question ) . '</option>';
							}
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
