<?php
/*
Plugin Name: WP-Polls
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
Version: 3.00.0
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-polls
*/


/*
	Copyright 2023  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

### Version
define( 'WP_POLLS_VERSION', '3.00.0' );


### Create Text Domain For Translations
add_action( 'plugins_loaded', 'polls_textdomain' );
function polls_textdomain() {
	load_plugin_textdomain( 'wp-polls' );
}


### Polls Table Name
global $wpdb;
$wpdb->pollsq		= $wpdb->prefix.'pollsq';
$wpdb->pollstpl		= $wpdb->prefix.'pollstpl';
$wpdb->pollsa		= $wpdb->prefix.'pollsa';
$wpdb->pollsaof		= $wpdb->prefix.'pollsaof';
$wpdb->pollsip		= $wpdb->prefix.'pollsip';


### Function: Poll Administration Menu
add_action( 'admin_menu', 'poll_menu' );
function poll_menu() {
	add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php', '', 'dashicons-chart-bar' );

	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Manage Polls', 'wp-polls'), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Add Poll', 'wp-polls'), __( 'Add Poll', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-add.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Templates', 'wp-polls'), __( 'Templates', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-templates.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'General Options', 'wp-polls'), __( 'General Options', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-options.php' );
}


### Function: Get Poll
function get_poll($temp_poll_id = 0, $display = true, $templates_set_id = 0) {
	global $wpdb, $templates_sets_loaded;
	// Poll Result Link
	if(isset($_GET['pollresult'])) {
		$pollresult_id = (int) $_GET['pollresult'];
	} else {
		$pollresult_id = 0;
	}
	$temp_poll_id = (int) $temp_poll_id;
	if((int) $templates_set_id > 0 ) { //specified templates set
		$templates_set_id = (int) $templates_set_id;
	} elseif($temp_poll_id > 0) { //specified poll's templates set 
		$templates_set_id = wp_polls_get_poll_templates_set_id($temp_poll_id); //returns 0 if not found (= intval(null))
		if ($templates_set_id === 0) return ''; //poll does not exist (all polls should have an associated templates_set_id).
	} elseif((int) $templates_set_id === -1) { //all templates sets. It allows returning the latest poll ID (with temp_poll_id set to 0 or less but '-2') or a random poll ID (with temp_poll_id set to '-2') within the scope of all templates sets. 
		$templates_set_id = '%';
		$latest_poll = wp_polls_get_child_option('global_poll_latestpoll');
		$current_poll = 0; 
		$poll_close = 3;
	} else{ //0 or other value - get text default template ID (i.e. stick to the behavior of previous versions of WP-Polls). 
		$templates_set_id = wp_polls_get_child_option('default_templates_set_text');
	}
	if ($templates_set_id !== '%'){ //a specific templates_set_ID is defined
		$poll_templates_set = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_currentpoll, polltpl_latestpoll, polltpl_template_disable, polltpl_template_aftervote, polltpl_close, polltpl_aftervote FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id) );	
		if (empty($poll_templates_set)) return ''; //to avoid displaying errors if poll_id is not defined or does not exist
		$current_poll = (int) $poll_templates_set->polltpl_currentpoll;
		$latest_poll = (int) $poll_templates_set->polltpl_latestpoll;
		$template_disable = (string) $poll_templates_set->polltpl_template_disable;
		$poll_close = (int) $poll_templates_set->polltpl_close;
		$poll_aftervote = (int) $poll_templates_set->polltpl_aftervote;
	}
	// Check Whether Showing Current Poll Is Disabled
	if($current_poll === -1) {
		if($display) {
			echo removeslashes($template_disable);
			return '';
		}
		return removeslashes($template_disable);
	// Poll Is Enabled
	} else {
		do_action('wp_polls_get_poll');
		// Hardcoded Poll ID Is Not Specified
		switch($temp_poll_id) {
			// Random Poll
			case -2:
				$poll_id = $wpdb->get_var($wpdb->prepare("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 AND pollq_tplid LIKE %s ORDER BY RAND() LIMIT 1", $templates_set_id));
				break;
			// Latest Poll
			case 0:
				// Random Poll
				if($current_poll === -2) {
					$random_poll_id = $wpdb->get_var($wpdb->prepare("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 AND pollq_tplid LIKE %s ORDER BY RAND() LIMIT 1", $templates_set_id));
					$poll_id = (int) $random_poll_id;
					if($pollresult_id > 0) {
						$poll_id = $pollresult_id;
					} elseif((int) $_POST['poll_id'] > 0) {
						$poll_id = (int) $_POST['poll_id'];
					}
				// Current Poll ID Is Not Specified
				} elseif($current_poll === 0) {
					// Get Lastest Poll ID
					$poll_id = $latest_poll;
				} else {
					// Get Current Poll ID
					$poll_id = $current_poll;
				}
				break;
			// Take Poll ID From Arguments
			default:
				$poll_id = $temp_poll_id;
		}
	}

	// Assign All Loaded Templates Sets To $templates_sets_loaded
	$templates_set_id = wp_polls_get_poll_templates_set_id($poll_id); //above defined $templates_set_id was not necessarily corresponding to the templates set ID associated with $poll_id, so overwrite the var with it now. 
	if(empty($templates_sets_loaded)) {
		$templates_sets_loaded = array();
	}
	if(!in_array( (int) $templates_set_id, $templates_sets_loaded, true)) {
		$templates_sets_loaded[] = (int) $templates_set_id;
	}

	$output = "";
	// User Click on View Results Link (even if user has not voted)
	if($pollresult_id === $poll_id && $poll_aftervote === 1) { //aftervote must be set to "Display results" to prevent user from accessing results via URL when aftervote is set to other actions.
		$output = display_pollresult($poll_id);
		
	// Check Whether User Has Voted
	} else {
		$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_question, pollq_active, pollq_expected_atype FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_active = (int) $poll_question->pollq_active;
		$check_voted = check_voted( $poll_id );
		$poll_aftervote_message = "";
		if( $poll_active !== 0 ) {
			$poll_close = 0;
			//Print aftervote message template
			if ( (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ){ //if user has voted WHEN poll is active
				$poll_aftervote_template = $poll_templates_set->polltpl_template_aftervote;
				$poll_question_text = $poll_question->pollq_question;
				$poll_type = $poll_question->pollq_expected_atype;
				$poll_answers_ids = (is_array($check_voted)) ? $check_voted : array($check_voted);
				$poll_answers_ids = array_map( 'intval', $poll_answers_ids );
				$poll_answers_ids_list = implode(',',$poll_answers_ids);
				$poll_aftervote_message = wp_polls_apply_variables_to_after_vote_message_template($poll_question_text, $poll_type, $poll_aftervote_template, $poll_answers_ids_list, $poll_id, $templates_set_id );
			}
		}
		// Hide both poll form and results
		if( $poll_close === 2  || (( (int) $check_voted > 0 || (is_array( $check_voted ) && count( $check_voted ) > 0 )) && $poll_aftervote === 3 ) ) { //inactive poll with poll_close set to 'hide' OR user has voted and aftervote set to 'hide' 
			$output = $poll_aftervote_message; //will be an empty string when poll is inactive
		// Display poll results
		} elseif( ( $poll_close === 1 || (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ) && $poll_aftervote === 1  ) { //inactive poll with poll_close set to 'show results' OR active/inactive poll when user has voted AND aftervote is set to 'show results'
			$output = $poll_aftervote_message.display_pollresult($poll_id, $check_voted);
		// Display poll disabled form
		} elseif( $poll_close === 3 || ! check_allowtovote() || (( (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ) && $poll_aftervote === 2 ) ) { //inactive poll when poll_close is set to 'show form' OR active/inactive poll when the user is not allowed to vote OR when user has voted with aftervote set to 'show form'
			$disable_poll_js = '<script type="text/javascript">jQuery("#polls_form_'.$poll_id.' :input").each(function (i){jQuery(this).attr("disabled","disabled")});</script>';
			$output = $poll_aftervote_message.display_pollvote($poll_id).$disable_poll_js;
		// Display poll active form
		} elseif( $poll_active === 1 ) { //active polls
			$output = display_pollvote($poll_id);
		}
	}
	if($display) {
		echo $output;
	} else {
		return $output;
	}
}


### Function: Enqueue Polls JavaScripts/CSS
//~ add_action('wp_footer', 'poll_scripts');
//~ function poll_scripts() {
	//~ global $templates_sets_loaded, $wpdb;

	//~ if(@file_exists(get_stylesheet_directory().'/polls-css.css')) {
		//~ wp_enqueue_style('wp-polls', get_stylesheet_directory_uri().'/polls-css.css', false, WP_POLLS_VERSION, 'all', array('strategy' => 'defer'));
	//~ } else {
		//~ wp_enqueue_style('wp-polls', plugins_url('wp-polls/polls-css.css'), false, WP_POLLS_VERSION, 'all', array('strategy' => 'defer'));
	//~ }
	//~ if( is_rtl() ) {
		//~ if(@file_exists(get_stylesheet_directory().'/polls-css-rtl.css')) {
			//~ wp_enqueue_style('wp-polls-rtl', get_stylesheet_directory_uri().'/polls-css-rtl.css', false, WP_POLLS_VERSION, 'all');
		//~ } else {
			//~ wp_enqueue_style('wp-polls-rtl', plugins_url('wp-polls/polls-css-rtl.css'), false, WP_POLLS_VERSION, 'all');
		//~ }
	//~ }
	//~ $script_localization_arr = array(
										//~ 'ajax_url' 		=> admin_url('admin-ajax.php'),
										//~ 'text_wait' 	=> __('Your last request is still being processed. Please wait a while ...', 'wp-polls'),
										//~ 'text_valid' 	=> __('Please choose a valid poll answer.', 'wp-polls'),
										//~ 'text_multiple' => __('Maximum number of choices allowed: ', 'wp-polls'),
									//~ );	
	
	//~ // Templates-sets-related
	//~ if (empty($templates_sets_loaded)) $templates_sets_loaded = array('0');
	//~ $poll_templates_ids_list = implode(',',$templates_sets_loaded);
	//~ $poll_templates_sets = $wpdb->get_results( $wpdb->prepare( "SELECT polltpl_id, polltpl_bar_style, polltpl_bar_background, polltpl_bar_border, polltpl_bar_height, polltpl_ajax_style_loading, polltpl_ajax_style_fading FROM $wpdb->pollstpl WHERE polltpl_id IN (%1s) ORDER BY polltpl_id ASC", $poll_templates_ids_list ) );       
	//~ $pollbar_css = '';
	//~ foreach ($poll_templates_sets as $poll_templates_set) {
		//~ $tpl_id = $poll_templates_set->polltpl_id;
		//~ // Styles
		//~ $pollbar = array(
							//~ 'height' 	 => $poll_templates_set->polltpl_bar_height,
							//~ 'background' => $poll_templates_set->polltpl_bar_background,
							//~ 'border' 	 => $poll_templates_set->polltpl_bar_border,
							//~ 'style' 	 => $poll_templates_set->polltpl_bar_style
						//~ );
		//~ if( $pollbar['style'] === 'use_css' ) {
			//~ $pollbar_css .= '.wp-polls .pollbar.wp-polls-tpl-'.$tpl_id.' {'."\n";
			//~ $pollbar_css .= "\t".'margin: 1px;'."\n";
			//~ $pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
			//~ $pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
			//~ $pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
			//~ $pollbar_css .= "\t".'background: #'.$pollbar['background'].';'."\n";
			//~ $pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
			//~ $pollbar_css .= '}'."\n";
		//~ } else {
			//~ $pollbar_css .= '.wp-polls .pollbar.wp-polls-tpl-'.$tpl_id.' {'."\n";
			//~ $pollbar_css .= "\t".'margin: 1px;'."\n";
			//~ $pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
			//~ $pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
			//~ $pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
			//~ $pollbar_css .= "\t".'background-image: url(\''.plugins_url('wp-polls/images/'.$pollbar['style'].'/pollbg.gif').'\');'."\n";
			//~ $pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
			//~ $pollbar_css .= '}'."\n";
		//~ }
		//~ //Script variables
		//~ $script_localization_arr['show_loading_tpl_'.$tpl_id] = $poll_templates_set->polltpl_ajax_style_loading;
		//~ $script_localization_arr['show_fading_tpl_'.$tpl_id] = $poll_templates_set->polltpl_ajax_style_fading;
	//~ }
	//~ wp_add_inline_style( 'wp-polls', $pollbar_css );
	//~ wp_enqueue_script('wp-polls', plugins_url('wp-polls/polls-js.js'), array('jquery'), WP_POLLS_VERSION, array('strategy' => 'defer'));
	//~ wp_localize_script('wp-polls', 'pollsL10n', $script_localization_arr);
//~ }
// Universal poll's styles & scripts
add_action('wp_enqueue_scripts', 'poll_scripts');
function poll_scripts() {
	if(@file_exists(get_stylesheet_directory().'/polls-css.css')) {
		wp_enqueue_style('wp-polls', get_stylesheet_directory_uri().'/polls-css.css', false, WP_POLLS_VERSION, 'all');
	} else {
		wp_enqueue_style('wp-polls', plugins_url('wp-polls/polls-css.css'), false, WP_POLLS_VERSION, 'all');
	}
	if( is_rtl() ) {
		if(@file_exists(get_stylesheet_directory().'/polls-css-rtl.css')) {
			wp_enqueue_style('wp-polls-rtl', get_stylesheet_directory_uri().'/polls-css-rtl.css', false, WP_POLLS_VERSION, 'all');
		} else {
			wp_enqueue_style('wp-polls-rtl', plugins_url('wp-polls/polls-css-rtl.css'), false, WP_POLLS_VERSION, 'all');
		}
	}
	$script_localization_arr = array(
										'ajax_url' 		=> admin_url('admin-ajax.php'),
										'text_wait' 	=> __('Your last request is still being processed. Please wait a while ...', 'wp-polls'),
										'text_valid' 	=> __('Please choose a valid poll answer.', 'wp-polls'),
										'text_multiple' => __('Maximum number of choices allowed: ', 'wp-polls'),
									);	
	wp_enqueue_script('wp-polls', plugins_url('wp-polls/polls-js.js'), array('jquery'), WP_POLLS_VERSION, true);
	wp_localize_script('wp-polls', 'pollsL10n', $script_localization_arr);
}
// Templates-sets-specific styles & scripts
add_action('wp_footer', 'poll_templates_scripts');
function poll_templates_scripts() {
	global $templates_sets_loaded, $wpdb;
	if (empty($templates_sets_loaded)) $templates_sets_loaded = array('0');
	$poll_templates_ids_list = implode(',',$templates_sets_loaded);
	$poll_templates_sets = $wpdb->get_results( $wpdb->prepare( "SELECT polltpl_id, polltpl_bar_style, polltpl_bar_background, polltpl_bar_border, polltpl_bar_height, polltpl_ajax_style_loading, polltpl_ajax_style_fading FROM $wpdb->pollstpl WHERE polltpl_id IN (%1s) ORDER BY polltpl_id ASC", $poll_templates_ids_list ) );       
	$pollbar_css = '';
	$additional_pollsL10n_vars = '';
	foreach ($poll_templates_sets as $poll_templates_set) {
		$tpl_id = $poll_templates_set->polltpl_id;
		// Styles
		$pollbar = array(
							'height' 	 => $poll_templates_set->polltpl_bar_height,
							'background' => $poll_templates_set->polltpl_bar_background,
							'border' 	 => $poll_templates_set->polltpl_bar_border,
							'style' 	 => $poll_templates_set->polltpl_bar_style
						);
		if( $pollbar['style'] === 'use_css' ) {
			$pollbar_css .= '.wp-polls .pollbar.wp-polls-tpl-'.$tpl_id.' {'."\n";
			$pollbar_css .= "\t".'margin: 1px;'."\n";
			$pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
			$pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
			$pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
			$pollbar_css .= "\t".'background: #'.$pollbar['background'].';'."\n";
			$pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
			$pollbar_css .= '}'."\n";
		} else {
			$pollbar_css .= '.wp-polls .pollbar.wp-polls-tpl-'.$tpl_id.' {'."\n";
			$pollbar_css .= "\t".'margin: 1px;'."\n";
			$pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
			$pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
			$pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
			$pollbar_css .= "\t".'background-image: url(\''.plugins_url('wp-polls/images/'.$pollbar['style'].'/pollbg.gif').'\');'."\n";
			$pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
			$pollbar_css .= '}'."\n";
		}
		//Script variables
		$additional_pollsL10n_vars .= 'pollsL10n.show_loading_tpl_' . $tpl_id . ' = ' . $poll_templates_set->polltpl_ajax_style_loading . ';';
		$additional_pollsL10n_vars .= 'pollsL10n.show_fading_tpl_' . $tpl_id . ' = ' . $poll_templates_set->polltpl_ajax_style_fading . ';' ;
	}
	wp_register_style( 'wp-polls-templates', '', [], '', 'all' ); //dummy style to add inline one
	wp_enqueue_style( 'wp-polls-templates'  );
	wp_add_inline_style( 'wp-polls-templates', $pollbar_css );

	wp_register_script( 'wp-polls-templates', '', [], '', true ); //dummy script to add inline one
	wp_enqueue_script( 'wp-polls-templates'  );
	wp_add_inline_script( 'wp-polls-templates', $additional_pollsL10n_vars );
}

### Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin
add_action('admin_enqueue_scripts', 'poll_scripts_admin');
function poll_scripts_admin($hook_suffix) {
	$poll_admin_pages = array('wp-polls/polls-manager.php', 'wp-polls/polls-add.php', 'wp-polls/polls-options.php', 'wp-polls/polls-templates.php', 'wp-polls/uninstall.php');
	if(in_array($hook_suffix, $poll_admin_pages, true)) {
		wp_enqueue_style('wp-polls-admin', plugins_url('wp-polls/polls-admin-css.css'), false, WP_POLLS_VERSION, 'all');
		wp_enqueue_script('wp-polls-admin', plugins_url('wp-polls/polls-admin-js.js'), array('jquery'), WP_POLLS_VERSION, true);
		wp_localize_script('wp-polls-admin', 'pollsAdminL10n', array(
			'admin_ajax_url' => admin_url('admin-ajax.php'),
			'text_direction' => is_rtl() ? 'right' : 'left',
			'text_delete_poll' => __('Delete Poll', 'wp-polls'),
			'text_no_poll_logs' => __('No poll logs available.', 'wp-polls'),
			'text_delete_all_logs' => __('Delete All Logs', 'wp-polls'),
			'text_checkbox_delete_all_logs' => __('Please check the \\\'Yes\\\' checkbox if you want to delete all logs.', 'wp-polls'),
			'text_delete_poll_logs' => __('Delete Logs For This Poll Only', 'wp-polls'),
			'text_checkbox_delete_poll_logs' => __('Please check the \\\'Yes\\\' checkbox if you want to delete all logs for this poll ONLY.', 'wp-polls'),
			'text_delete_poll_ans' => __('Delete Poll Answer', 'wp-polls'),
			'text_batch_delete_poll_ans' => __('Batch Delete Poll Answers', 'wp-polls'),
			'text_insert_builtin_templates_set' => __('Insert Built-in Templates Set', 'wp-polls'),
			'text_duplicate_templates_set' => __('Duplicate Templates Set', 'wp-polls'),
			'text_delete_templates_set' => __('Delete Templates Set', 'wp-polls'),
			'text_set_poll_to_default_templates' => __('Set Poll To Default Templates', 'wp-polls'),
			'text_reset_all_templates_sets' => __('Reset All Templates Sets', 'wp-polls'),
			'text_reset_all_templates_sets_checkbox' => __('Tick the checkbox before clicking the button to confirm.', 'wp-polls'),
			'text_open_poll' => __('Open Poll', 'wp-polls'),
			'text_close_poll' => __('Close Poll', 'wp-polls'),
			'text_retrieve_content' => __('Retrieve Content', 'wp-polls'),
			'text_answer' => __('Answer', 'wp-polls'),
			'text_remove_poll_answer' => __('Remove', 'wp-polls'),
			'text_none_selected' => __('None selected', 'wp-polls'),
			'text_1_selected' => __('selected', 'wp-polls'),
			'text_x_selected' => __('selected', 'wp-polls'),
			'text_ajax_box_no_post_type_selected' => __('Select one or several post type(s) to load results.', 'wp-polls'),
			'text_ajax_box_no_answer_item_selected' => __('Select one or several answer item(s) to load associated fields.', 'wp-polls'),
			'text_confirm_change_type' => __('Changing the entries\' type will remove current unsaved answers. Continue?', 'wp-polls'),
			'text_confirm_change_type_edit' => __("Changing the entries' type will remove all existing answers for this poll.\nDo you want to continue?", 'wp-polls'),
		));
	}
}


### Function: Displays Polls Footer In WP-Admin
add_action('admin_footer-post-new.php', 'poll_footer_admin');
add_action('admin_footer-post.php', 'poll_footer_admin');
add_action('admin_footer-page-new.php', 'poll_footer_admin');
add_action('admin_footer-page.php', 'poll_footer_admin');
function poll_footer_admin() {
?>
	<script type="text/javascript">
		QTags.addButton('ed_wp_polls', '<?php echo esc_js(__('Poll', 'wp-polls')); ?>', function() {
			var poll_id = jQuery.trim(prompt('<?php echo esc_js(__('Enter Poll ID', 'wp-polls')); ?>'));
			while(isNaN(poll_id)) {
				poll_id = jQuery.trim(prompt("<?php echo esc_js(__('Error: Poll ID must be numeric', 'wp-polls')); ?>\n\n<?php echo esc_js(__('Please enter Poll ID again', 'wp-polls')); ?>"));
			}
			if (poll_id >= -1 && poll_id != null && poll_id != "") {
				QTags.insertContent('[poll id="' + poll_id + '"]');
			}
		});
	</script>
<?php
}

### Function: Add Quick Tag For Poll In TinyMCE >= WordPress 2.5
add_action('init', 'poll_tinymce_addbuttons');
function poll_tinymce_addbuttons() {
	if(!current_user_can('edit_posts') && ! current_user_can('edit_pages')) {
		return;
	}
	if(get_user_option('rich_editing') === 'true') {
		add_filter('mce_external_plugins', 'poll_tinymce_addplugin');
		add_filter('mce_buttons', 'poll_tinymce_registerbutton');
		add_filter('wp_mce_translation', 'poll_tinymce_translation');
	}
}
function poll_tinymce_registerbutton($buttons) {
	array_push($buttons, 'separator', 'polls');
	return $buttons;
}
function poll_tinymce_addplugin($plugin_array) {
	if(WP_DEBUG) {
		$plugin_array['polls'] = plugins_url( 'wp-polls/tinymce/plugins/polls/plugin.js?v=' . WP_POLLS_VERSION );
	} else {
		$plugin_array['polls'] = plugins_url( 'wp-polls/tinymce/plugins/polls/plugin.min.js?v=' . WP_POLLS_VERSION );
	}
	return $plugin_array;
}
function poll_tinymce_translation($mce_translation) {
	$mce_translation['Enter Poll ID'] = esc_js(__('Enter Poll ID', 'wp-polls'));
	$mce_translation['Error: Poll ID must be numeric'] = esc_js(__('Error: Poll ID must be numeric', 'wp-polls'));
	$mce_translation['Please enter Poll ID again'] = esc_js(__('Please enter Poll ID again', 'wp-polls'));
	$mce_translation['Insert Poll'] = esc_js(__('Insert Poll', 'wp-polls'));
	return $mce_translation;
}


### Function: Check Who Is Allow To Vote
function check_allowtovote($templates_set_id = 0) {
	global $user_ID;
	$user_ID = (int) $user_ID;
	if ($templates_set_id == 0) $templates_set_id = wp_polls_get_child_option('default_templates_set_text');
	$allow_to_vote = (int) wp_polls_get_templates_set_setting('polltpl_allowtovote', $templates_set_id);
	switch($allow_to_vote) {
		// Guests Only
		case 0:
			if($user_ID > 0) {
				return false;
			}
			return true;
			break;
		// Registered Users Only
		case 1:
			if($user_ID === 0) {
				return false;
			}
			return true;
			break;
		// Registered Users And Guests
		case 2:
		default:
			return true;
	}
}


### Funcrion: Check Voted By Cookie Or IP
function check_voted($poll_id) {
	$templates_set_id = wp_polls_get_poll_templates_set_id($poll_id);
	$poll_logging_method = (int) wp_polls_get_templates_set_setting('polltpl_logging_method', $templates_set_id);
	switch($poll_logging_method) {
		// Do Not Log
		case 0:
			return 0;
			break;
		// Logged By Cookie
		case 1:
			return check_voted_cookie($poll_id);
			break;
		// Logged By IP
		case 2:
			return check_voted_ip($poll_id, $templates_set_id);
			break;
		// Logged By Cookie And IP
		case 3:
			$check_voted_cookie = check_voted_cookie($poll_id);
			if(!empty($check_voted_cookie)) {
				return $check_voted_cookie;
			}
			return check_voted_ip($poll_id, $templates_set_id);
			break;
		// Logged By Username
		case 4:
			return check_voted_username($poll_id);
			break;
	}
}


### Function: Check Voted By Cookie
function check_voted_cookie( $poll_id ) {
	$get_voted_aids = 0;
	if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
		$get_voted_aids = explode( ',', $_COOKIE[ 'voted_' . $poll_id ] );
		$get_voted_aids = array_map( 'intval', array_map( 'sanitize_key', $get_voted_aids ) );
	}
	return $get_voted_aids;
}


### Function: Check Voted By IP
function check_voted_ip( $poll_id, $templates_set_id) {
	global $wpdb;
	$log_expiry = (int) wp_polls_get_templates_set_setting('polltpl_cookielog_expiry', $templates_set_id);
	$log_expiry_sql = '';
	if( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time('timestamp') . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check IP From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s", $poll_id, poll_get_ipaddress($templates_set_id) ) . $log_expiry_sql );
	if( $get_voted_aids ) {
		return $get_voted_aids;
	}

	return 0;
}


### Function: Check Voted By Username
function check_voted_username($poll_id, $templates_set_id) {
	global $wpdb, $user_ID;
	// Check IP If User Is Guest
	if ( ! is_user_logged_in() ) {
		return 1;
	}
	$pollsip_userid = (int) $user_ID;
	$log_expiry = (int) wp_polls_get_templates_set_setting('polltpl_cookielog_expiry', $templates_set_id);
	$log_expiry_sql = '';
	if( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time('timestamp') . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check User ID From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d", $poll_id, $pollsip_userid ) . $log_expiry_sql );
	if($get_voted_aids) {
		return $get_voted_aids;
	} else {
		return 0;
	}
}

add_filter( 'wp_polls_template_voteheader_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votebody_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votefooter_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultheader_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultbody_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultbody2_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultfooter_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_resultfooter2_markup', 'poll_template_vote_markup', 10, 3) ;
add_filter( 'wp_polls_template_aftervote_markup', 'poll_template_vote_markup', 10, 3) ;

function poll_template_vote_markup( $template, $object, $variables ) {
	return str_replace( array_keys( $variables ), array_values( $variables ), $template ) ;
}

### Function: Display Voting Form
function display_pollvote($poll_id, $display_loading = true) {
	do_action('wp_polls_display_pollvote');
	global $wpdb;
	// Temp Poll Result
	$temp_pollvote = '';
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_expected_atype, pollq_totalvotes, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	// Get Poll Templates settings
	$templates_set_id = wp_polls_get_poll_templates_set_id($poll_id);
	$poll_templates_set = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_template_voteheader, polltpl_template_votebody, polltpl_template_votefooter, polltpl_template_disable, polltpl_ajax_style_loading, polltpl_ajax_style_fading FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id ) );
	if (empty($poll_templates_set)) return ''; //to avoid displaying errors if poll_id is not defined or does not exist
	
	// Poll Question Variables
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_type = trim($poll_question->pollq_expected_atype);
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_question->pollq_timestamp));
	$poll_expiry = trim($poll_question->pollq_expiry);
	if(empty($poll_expiry)) {
		$poll_end_date  = __('No Expiry', 'wp-polls');
	} else {
		$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;
	
	$template_question = removeslashes( $poll_templates_set->polltpl_template_voteheader );

	$template_question_variables = array(
		'%POLL_QUESTION%'           => $poll_question_text,
		'%POLL_ID%'                 => $poll_question_id,
		'%POLL_TOTALVOTES%'         => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%'        => $poll_question_totalvoters,
		'%POLL_START_DATE%'         => $poll_start_date,
		'%POLL_END_DATE%'           => $poll_end_date,
		'%POLL_MULTIPLE_ANS_MAX%'   => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1
	);
	$template_question_variables = apply_filters( 'wp_polls_template_voteheader_variables', $template_question_variables );
	$template_question  		 = apply_filters( 'wp_polls_template_voteheader_markup', $template_question, $poll_question, $template_question_variables );
	
	// Get Poll Answers Data
	list($order_by, $sort_order) = _polls_get_ans_sort($templates_set_id);
	$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_qid, polla_answers, polla_atype, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// Sort again if object answers with alphabetical order to make the sorting reference the answer text instead of the answer ID. 
	if ($poll_type == 'object' && $order_by == 'polla_answers'){ 
		$sort_factor = ($sort_order == 'asc') ? 1 : -1;
		usort($poll_answers, fn($a, $b) => $sort_factor * strnatcasecmp(get_the_title($a->polla_answers), get_the_title($b->polla_answers) )); //cf. https://stackoverflow.com/questions/4282413/sort-array-of-objects-by-one-property
	}
	// If There Is Poll Question With Answers
	if($poll_question && $poll_answers) {
		// Display Poll Voting Form
		$temp_pollvote .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollvote .= "\t<form id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\" action=\"" . sanitize_text_field( $_SERVER['SCRIPT_NAME'] ) ."\" method=\"post\">\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" /></p>\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_question_id\" /></p>\n";
		if($poll_multiple_ans > 0) {
			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_question_id\" name=\"poll_multiple_ans_$poll_question_id\" value=\"$poll_multiple_ans\" /></p>\n";
		}
		// Print Out Voting Form Header Template
		$temp_pollvote .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
			$poll_answer_id = (int) $poll_answer->polla_aid;
			$poll_answer_content = wp_kses_post( removeslashes( $poll_answer->polla_answers ) ); //text if answer's type is 'text'; ID corresponding to the associated answer if answer's type is 'object'.  
			$poll_answer_type = trim($poll_answer->polla_atype);
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			$poll_answer_percentage = $poll_question_totalvotes > 0 ? round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 ) : 0;
			$poll_multiple_answer_percentage = $poll_question_totalvoters > 0 ? round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 ) : 0;
			$template_answer = removeslashes( $poll_templates_set->polltpl_template_votebody );

			$template_answer_variables = array(
				'%POLL_ID%'                         => $poll_question_id,
				'%POLL_ANSWER_ID%'                  => $poll_answer_id,
				'%POLL_ANSWER%'                     => $poll_answer_content,
				'%POLL_ANSWER_VOTES%'               => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%'          => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_CHECKBOX_RADIO%'             => $poll_multiple_ans > 0 ? 'checkbox' : 'radio',
			);

			if ($poll_answer_type === 'object') {
				// Fields to use in every cases
				$template_answer_variables['%POLL_ANSWER_OBJECT_URL%'] = get_permalink($poll_answer_content);
				// Fields to use if they were checked in the Templates set's details tab
				add_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array', 10, 3 );
				$custom_vars_list_str = trim( wp_polls_list_post_type_fields('', $templates_set_id, '', 'template_tags', false) );
				remove_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array');
				$custom_vars_arr = explode(' ', $custom_vars_list_str);
				$custom_vars_arr = wp_polls_get_custom_templates_vars_values_array($poll_answer_content, $custom_vars_arr);
				$template_answer_variables = array_merge($template_answer_variables, $custom_vars_arr);
				//for object answers it must be sorted again per answers' text instead of per answer's ID
			}
			$template_answer_variables = apply_filters( 'wp_polls_template_votebody_variables', $template_answer_variables );
			$template_answer           = apply_filters( 'wp_polls_template_votebody_markup', $template_answer, $poll_answer, $template_answer_variables );
			
			// Print Out Voting Form Body Template
			$temp_pollvote .= "\t\t$template_answer\n";
		}
		// Determine Poll Result URL
		$poll_result_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
		$poll_result_url = preg_replace('/pollresult=(\d+)/i', 'pollresult='.$poll_question_id, $poll_result_url);
		if(isset($_GET['pollresult']) && (int) $_GET['pollresult'] === 0) {
			if(strpos($poll_result_url, '?') !== false) {
				$poll_result_url = "$poll_result_url&amp;pollresult=$poll_question_id";
			} else {
				$poll_result_url = "$poll_result_url?pollresult=$poll_question_id";
			}
		}
		// Voting Form Footer Variables
		$template_footer = removeslashes( $poll_templates_set->polltpl_template_votefooter );
		
		$template_footer_variables = array(
			'%POLL_ID%'               => $poll_question_id,
			'%POLL_RESULT_URL%'       => $poll_result_url,
			'%POLL_START_DATE%'       => $poll_start_date,
			'%POLL_END_DATE%'         => $poll_end_date,
			'%POLL_MULTIPLE_ANS_MAX%' => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1
		);

		$template_footer_variables = apply_filters( 'wp_polls_template_votefooter_variables', $template_footer_variables );
		$template_footer           = apply_filters( 'wp_polls_template_votefooter_markup', $template_footer, $poll_question, $template_footer_variables );

		// Print Out Voting Form Footer Template
		$temp_pollvote .= "\t\t$template_footer\n";
		$temp_pollvote .= "\t</form>\n";
		$temp_pollvote .= "</div>\n";
		if($display_loading) {
			$poll_ajax_style_loading = (int) $poll_templates_set->polltpl_ajax_style_loading;
			if($poll_ajax_style_loading === 1) {
				$temp_pollvote .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
			}
		}
	} else {
		$temp_pollvote .= removeslashes( $poll_templates_set->polltpl_template_disable );
	}
	// Return Poll Vote Template
	return $temp_pollvote;
}

### Helper to clean string from slashes (declared before display_pollresult due to errors)
if( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		if (is_null($string) || $string === '') return '';  
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}

### Function: Display Results Form
function display_pollresult( $poll_id, $user_voted = array(), $display_loading = true ) {
	global $wpdb;
	do_action( 'wp_polls_display_pollresult', $poll_id, $user_voted );
	$poll_id = (int) $poll_id;
	// User Voted
	if( empty( $user_voted ) ) {
		$user_voted = array();
	}
	if ( is_array( $user_voted ) ) {
		$user_voted = array_map( 'intval', $user_voted );
	} else {
		$user_voted = array( (int) $user_voted );
	}

	// Temp Poll Result
	$temp_pollresult = '';
	// Most/Least Variables
	$poll_most_answer = '';
	$poll_most_votes = 0;
	$poll_most_percentage = 0;
	$poll_least_answer = '';
	$poll_least_votes = 0;
	$poll_least_percentage = 0;
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_expected_atype, pollq_totalvotes, pollq_active, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	
	//Get Poll Templates settings
	$templates_set_id = wp_polls_get_poll_templates_set_id($poll_id);
	$poll_templates_set = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_template_resultheader, polltpl_template_resultbody, polltpl_template_resultbody2, polltpl_template_resultfooter, polltpl_template_resultfooter2, polltpl_template_disable, polltpl_ajax_style_loading FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id ) );
	if (empty($poll_templates_set)) return ''; //to avoid displaying errors if poll_id is not defined or does not exist

	// No poll could be loaded from the database
	if ( ! $poll_question ) {
		return removeslashes( $poll_templates_set->polltpl_template_disable );
	}
	// Poll Question Variables
	$poll_question_text = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id = (int) $poll_question->pollq_id;
	$poll_type = trim($poll_question->pollq_expected_atype);
	$poll_question_totalvotes = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_question_active = (int) $poll_question->pollq_active;
	$poll_start_date = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_timestamp ) );
	$poll_expiry = trim( $poll_question->pollq_expiry );
	if ( empty( $poll_expiry ) ) {
		$poll_end_date  = __( 'No Expiry', 'wp-polls' );
	} else {
		$poll_end_date  = mysql2date( sprintf( __( '%s @ %s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;
	$template_question = removeslashes( $poll_templates_set->polltpl_template_resultheader );
	$template_variables = array(
		'%POLL_QUESTION%' => $poll_question_text,
		'%POLL_ID%' => $poll_question_id,
		'%POLL_TOTALVOTES%' => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%' => $poll_question_totalvoters,
		'%POLL_START_DATE%' => $poll_start_date,
		'%POLL_END_DATE%' => $poll_end_date
	);
	if ( $poll_multiple_ans > 0 ) {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
	} else {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
	}
	
	$template_variables = apply_filters('wp_polls_template_resultheader_variables', $template_variables );
	$template_question  = apply_filters('wp_polls_template_resultheader_markup', $template_question, $poll_question, $template_variables );

	// Get Poll Answers Data
	list( $order_by, $sort_order ) = _polls_get_ans_result_sort();
	$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_atype, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// Sort again if object answers with alphabetical order to make the sorting reference the answer text instead of the answer ID. 
	if ($poll_type == 'object' && $order_by == 'polla_answers'){ 
		$sort_factor = ($sort_order == 'asc') ? 1 : -1;
		usort($poll_answers, fn($a, $b) => $sort_factor * strnatcasecmp(get_the_title($a->polla_answers), get_the_title($b->polla_answers) )); //cf. https://stackoverflow.com/questions/4282413/sort-array-of-objects-by-one-property
	}
	// If There Is Poll Question With Answers
	if ( $poll_question && $poll_answers ) {
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		// Is The Poll Total Votes or Voters 0?
		$poll_totalvotes_zero = $poll_question_totalvotes <= 0;
		$poll_totalvoters_zero = $poll_question_totalvoters <= 0;
		// Print Out Result Header Template
		$temp_pollresult .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollresult .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
			$poll_answer_id = (int) $poll_answer->polla_aid;
			$poll_answer_content = wp_kses_post( removeslashes( $poll_answer->polla_answers ) ); //text if answer's type is 'text'; ID corresponding to the associated answer if answer's type is 'object'.  
			$poll_answer_type = trim($poll_answer->polla_atype);
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $poll_answer_votes > 0 ) {
				$poll_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 );
				$poll_multiple_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 );
				$poll_answer_imagewidth = round( $poll_answer_percentage );
				if ( $poll_answer_imagewidth === 100 ) {
					$poll_answer_imagewidth = 99;
				}
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			$round_percentage = apply_filters( 'wp_polls_round_percentage', false );
			if ( $round_percentage && $poll_multiple_ans === 0 ) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if ( count( $poll_answer_percentage_array ) === count( $poll_answers ) ) {
					$percentage_error_buffer = 100 - array_sum( $poll_answer_percentage_array );
					$poll_answer_percentage += $percentage_error_buffer;
					if ( $poll_answer_percentage < 0 ) {
						$poll_answer_percentage = 0;
					}
				}
			}

			$template_variables = array(
				'%POLL_ID%' => $poll_question_id,
				'%POLL_ANSWER_ID%' => $poll_answer_id,
				'%POLL_ANSWER%' => $poll_answer_content,
				'%POLL_ANSWER_TEXT%' => htmlspecialchars( wp_strip_all_tags( $poll_answer_content ) ),
				'%POLL_ANSWER_VOTES%' => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%' => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_ANSWER_IMAGEWIDTH%' => $poll_answer_imagewidth
			);

			if ($poll_type === 'object') {
				// Fields to use in every cases
				$template_answer_variables['%POLL_ANSWER_OBJECT_URL%'] = get_permalink($poll_answer_content);
				// Fields to use if they were checked in the Templates set's details tab				
				add_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array', 10, 3 );
				$custom_vars_list_str = trim( wp_polls_list_post_type_fields('', $templates_set_id, '', 'template_tags', false) );
				remove_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array');
				$custom_vars_arr = explode(' ', $custom_vars_list_str);
				$custom_vars_arr = wp_polls_get_custom_templates_vars_values_array($poll_answer_content, $custom_vars_arr);
				$template_variables = array_merge($template_variables, $custom_vars_arr);
			}
			$template_variables = apply_filters('wp_polls_template_resultbody_variables', $template_variables);

			// Let User See What Options They Voted
			if ( in_array( $poll_answer_id, $user_voted, true ) ) {
				// Results Body Variables
				$template_answer = removeslashes( $poll_templates_set->polltpl_template_resultbody2 );
				$template_answer = apply_filters('wp_polls_template_resultbody2_markup', $template_answer, $poll_answer, $template_variables);
			} else {
				// Results Body Variables
				$template_answer = removeslashes ($poll_templates_set->polltpl_template_resultbody );
				$template_answer = apply_filters('wp_polls_template_resultbody_markup', $template_answer, $poll_answer, $template_variables);
			}

			// Print Out Results Body Template
			$temp_pollresult .= "\t\t$template_answer\n";

			// Get Most Voted Data
			if ( $poll_answer_votes > $poll_most_votes ) {
				$poll_most_answer = $poll_answer_content;
				$poll_most_votes = $poll_answer_votes;
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if ( $poll_least_votes === 0 ) {
				$poll_least_votes = $poll_answer_votes;
			}
			if ( $poll_answer_votes <= $poll_least_votes ) {
				$poll_least_answer = $poll_answer_content;
				$poll_least_votes = $poll_answer_votes;
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_variables = array(
			'%POLL_START_DATE%' => $poll_start_date,
			'%POLL_END_DATE%' => $poll_end_date,
			'%POLL_ID%' => $poll_question_id,
			'%POLL_TOTALVOTES%' => number_format_i18n( $poll_question_totalvotes ),
			'%POLL_TOTALVOTERS%' => number_format_i18n( $poll_question_totalvoters ),
			'%POLL_MOST_ANSWER%' => $poll_most_answer,
			'%POLL_MOST_VOTES%' => number_format_i18n( $poll_most_votes ),
			'%POLL_MOST_PERCENTAGE%' => $poll_most_percentage,
			'%POLL_LEAST_ANSWER%' => $poll_least_answer,
			'%POLL_LEAST_VOTES%' => number_format_i18n( $poll_least_votes ),
			'%POLL_LEAST_PERCENTAGE%' => $poll_least_percentage
		);
		if ( $poll_multiple_ans > 0 ) {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
		} else {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
		}
		$template_variables = apply_filters('wp_polls_template_resultfooter_variables', $template_variables );

		if ( ! empty( $user_voted ) || $poll_question_active === 0 || ! check_allowtovote() ) {
			$template_footer = removeslashes( $poll_templates_set->polltpl_template_resultfooter );
			$template_footer = apply_filters('wp_polls_template_resultfooter_markup', $template_footer, $poll_question, $template_variables);
		} else {
			$template_footer = removeslashes( $poll_templates_set->polltpl_template_resultfooter2 );
			$template_footer = apply_filters('wp_polls_template_resultfooter2_markup', $template_footer, $poll_question, $template_variables);
		}

		// Print Out Results Footer Template
		$temp_pollresult .= "\t\t$template_footer\n";
		$temp_pollresult .= "\t\t<input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" />\n";
		$temp_pollresult .= "</div>\n";
		if ( $display_loading ) {
			if ( (int) $poll_templates_set->polltpl_ajax_style_loading === 1 ) {
				$temp_pollresult .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
			}
		}
	} else {
		$temp_pollresult .= removeslashes( $poll_templates_set->polltpl_template_disable );
	}
	// Return Poll Result
	return apply_filters( 'wp_polls_result_markup', $temp_pollresult );
}


### Function: Get IP Address
function poll_get_raw_ipaddress($templates_set_id) {
	$ip = esc_attr( $_SERVER['REMOTE_ADDR'] );
	$ip_header = wp_polls_get_templates_set_setting('polltpl_ip_header', $templates_set_id);
	if ( ! empty( $poll_options ) && ! empty( $ip_header ) && ! empty( $_SERVER[ $ip_header ] ) ) {
		$ip = esc_attr( $_SERVER[ $ip_header ] );
	}

	return $ip;
}

function poll_get_ipaddress($templates_set_id) {
	return apply_filters( 'wp_polls_ipaddress', wp_hash( poll_get_raw_ipaddress($templates_set_id) ) );
}

function poll_get_hostname($templates_set_id) {
	$ip = poll_get_raw_ipaddress($templates_set_id);
	$hostname = gethostbyaddr( $ip );
	if ( $hostname === $ip ) {
		$hostname = wp_privacy_anonymize_ip( $ip );
	}

	if ( false !== $hostname ) {
		$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
	}

	return apply_filters( 'wp_polls_hostname', $hostname );
}

### Function: Short Code For Inserting Polls Archive Into Page
add_shortcode('page_polls', 'poll_page_shortcode');
function poll_page_shortcode( $atts ) {
	$attributes = shortcode_atts( array('tpl_id' => -1), $atts );
	$templates_set_id = (int) $attributes['tpl_id'];
	return polls_archive($templates_set_id);
}

### Function: Short Code For Inserting Polls Into Posts
add_shortcode( 'poll', 'poll_shortcode' );
function poll_shortcode( $atts ) {
	$attributes = shortcode_atts( array( 'id' => 0, 'type' => 'vote' ), $atts );
	if( ! is_feed() ) {
		$id = (int) $attributes['id'];
		// To maintain backward compatibility with [poll=1]. Props @tz-ua
		if( ! $id && isset( $atts[0] ) ) {
			$id = (int) trim( $atts[0], '="\'' );
		}
		
		if( $attributes['type'] === 'vote' ) {
			return get_poll( $id, false );
		} elseif( $attributes['type'] === 'result' ) {
			return display_pollresult( $id );
		}
	} else {
		return __( 'Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls' );
	}
}

### Function: Get Poll Question Based On Poll ID
if(!function_exists('get_poll_question')) {
	function get_poll_question($poll_id) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}


### Function: Get Poll Total Questions
if(!function_exists('get_pollquestions')) {
	function get_pollquestions($display = true) {
		global $wpdb;
		$totalpollq = (int) $wpdb->get_var("SELECT COUNT(pollq_id) FROM $wpdb->pollsq");
		if($display) {
			echo $totalpollq;
		} else {
			return $totalpollq;
		}
	}
}


### Function: Get Poll Total Answers
if(!function_exists('get_pollanswers')) {
	function get_pollanswers($display = true) {
		global $wpdb;
		$totalpolla = (int) $wpdb->get_var("SELECT COUNT(polla_aid) FROM $wpdb->pollsa");
		if($display) {
			echo $totalpolla;
		} else {
			return $totalpolla;
		}
	}
}


### Function: Get Poll Total Votes
if(!function_exists('get_pollvotes')) {
	function get_pollvotes($display = true) {
		global $wpdb;
		$totalvotes = (int) $wpdb->get_var("SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq");
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

### Function: Get Poll Votes Based on Poll ID
if(!function_exists('get_pollvotes_by_id')) {
	function get_pollvotes_by_id($poll_id, $display = true) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare("SELECT pollq_totalvotes FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id));
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

### Function: Get Poll Votes Based on Templates Set ID
if(!function_exists('get_pollvotes_by_templates_set_id')) {
	function get_pollvotes_by_templates_set_id($templates_set_id, $display = true) {
		global $wpdb;
		$templates_set_id = (int) $templates_set_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare("SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq WHERE pollq_tplid = %d", $templates_set_id));
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

### Function: Get Poll Total Voters
if(!function_exists('get_pollvoters')) {
	function get_pollvoters($display = true) {
		global $wpdb;
		$totalvoters = (int) $wpdb->get_var("SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq");
		if($display) {
			echo $totalvoters;
		} else {
			return $totalvoters;
		}
	}
}

### Function: Get Poll Time Based on Poll ID and Date Format
if ( ! function_exists( 'get_polltime' ) ) {
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id = (int) $poll_id;
		$timestamp = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = date( $date_format, $timestamp );
		if ( $display ) {
			echo $formatted_date;
		} else {
			return $formatted_date;
		}
	}
}


### Function: Check Voted To Get Voted Answer
function check_voted_multiple($poll_id, $polls_ips) {
	if(!empty($_COOKIE["voted_$poll_id"])) {
		return explode(',', $_COOKIE["voted_$poll_id"]);
	} else {
		if($polls_ips) {
			return $polls_ips;
		} else {
			return array();
		}
	}
}


### Function: Polls Archive Link
function polls_archive_link($page, $templates_set_id = -1) {
	$templates_set_id = (int) $templates_set_id;
	$polls_archive_url = ($templates_set_id > 0) ?  wp_polls_get_templates_set_setting('polltpl_archive_url', $templates_set_id) : wp_polls_get_child_option('global_poll_archive_url');
	if (empty($polls_archive_url)) return ''; //to avoid errors if supplied $templates_set_id does not exist  
	if($page > 0) {
		if(strpos($polls_archive_url, '?') !== false) { 
			$polls_archive_url = "$polls_archive_url&amp;poll_page=$page";
		} else {
			$polls_archive_url = "$polls_archive_url?poll_page=$page";
		}
	}
	return $polls_archive_url;
}


### Function: Displays Polls Archive Link
function display_polls_archive_link($display = true, $templates_set_id = -1) {
	$templates_set_id = (int) $templates_set_id;
	$polls_archive_url = ($templates_set_id > 0) ?  wp_polls_get_templates_set_setting('polltpl_archive_url', $templates_set_id) : wp_polls_get_child_option('global_poll_archive_url');
	$template_pollarchivelink = ($templates_set_id > 0) ?  wp_polls_get_templates_set_setting('polltpl_template_pollarchivelink', $templates_set_id) : wp_polls_get_default_template_string('poll_template_pollarchivelink', 'text');
	$template_pollarchivelink = apply_filters('wp_polls_display_archive_link_template', $template_pollarchivelink);
	$template_pollarchivelink = str_replace("%POLL_ARCHIVE_URL%", $polls_archive_url, removeslashes($template_pollarchivelink));
	if (empty($polls_archive_url) || empty($template_pollarchivelink)) return ''; //to avoid errors if supplied $templates_set_id does not exist 
	if($display) {
		echo $template_pollarchivelink;
	} else{
		return $template_pollarchivelink;
	}
}


### Function: Display Polls Archive
function polls_archive($templates_set_id = -1) {
	do_action('wp_polls_polls_archive');
	global $wpdb, $in_pollsarchive, $templates_sets_loaded;
	// Polls Variables
	$templates_set_id = (int) $templates_set_id;
	$query_templates_set_id = ($templates_set_id > 0) ? $templates_set_id : '%'; //specific ID or SQL wildcard for 'all'
	$in_pollsarchive = true;
	$page = isset($_GET['poll_page']) ? (int) sanitize_key( $_GET['poll_page'] ) : 0;
	$polls_questions = array();
	$polls_answers = array();
	$polls_ips = array();
	$polls_perpage = ($templates_set_id > 0) ? (int) wp_polls_get_templates_set_setting('polltpl_archive_perpage', $templates_set_id) : (int) wp_polls_get_child_option('global_poll_archive_perpage');
	$poll_questions_ids = 0;
	$poll_voted = false;
	$poll_voted_aid = 0;
	$poll_id = 0;
	$pollsarchive_output_archive = '';
	$polls_type = ($templates_set_id > 0) ? (int) wp_polls_get_templates_set_setting('polltpl_archive_displaypoll', $templates_set_id) : (int) wp_polls_get_child_option('global_poll_archive_displaypoll');
	$polls_type_sql = '';

	// Determine What Type Of Polls To Show
	switch($polls_type) {
		case 1:
			$polls_type_sql = 'pollq_active = 0';
			break;
		case 2:
			$polls_type_sql = 'pollq_active = 1';
			break;
		case 3:
			$polls_type_sql = 'pollq_active IN (0,1)';
			break;
	}
	
	// Get Total Polls
	$total_polls = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE $polls_type_sql AND pollq_active != -1 AND pollq_tplid LIKE %s", $query_templates_set_id) );
	
	// Calculate Paging
	$numposts = $total_polls;
	$perpage = ($polls_perpage > 1) ? $polls_perpage : 1; //to make sure to avoid the divide by 0 error at the next line
	$max_page = (int) ceil($numposts/$perpage);
	$page = (empty($page) || $page == 0) ? 1 : $page;
	$offset = ($page-1) * $perpage;
	$pages_to_show = 10;
	$pages_to_show_minus_1 = $pages_to_show-1;
	$half_page_start = floor($pages_to_show_minus_1/2);
	$half_page_end = ceil($pages_to_show_minus_1/2);
	$start_page = $page - $half_page_start;
	if($start_page <= 0) {
		$start_page = 1;
	}
	$end_page = $page + $half_page_end;
	if(($end_page - $start_page) !== $pages_to_show_minus_1) {
		$end_page = $start_page + $pages_to_show_minus_1;
	}
	if($end_page > $max_page) {
		$start_page = $max_page - $pages_to_show_minus_1;
		$end_page = $max_page;
	}
	if($start_page <= 0) {
		$start_page = 1;
	}
	if(($offset + $perpage) > $numposts) {
		$max_on_page = $numposts;
	} else {
		$max_on_page = ($offset + $perpage);
	}
	if (($offset + 1) > ($numposts)) {
		$display_on_page = $numposts;
	} else {
		$display_on_page = ($offset + 1);
	}
	
	// Get Poll Questions
	$questions = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->pollsq WHERE $polls_type_sql AND pollq_tplid LIKE %s ORDER BY pollq_id DESC LIMIT $offset, $polls_perpage", $query_templates_set_id) );
	if($questions) {
		foreach($questions as $question) {
			$polls_questions[] = array( 'id' => (int) $question->pollq_id, 'question' => wp_kses_post( removeslashes( $question->pollq_question ) ), 'timestamp' => $question->pollq_timestamp, 'totalvotes' => (int) $question->pollq_totalvotes, 'start' => $question->pollq_timestamp, 'end' => trim( $question->pollq_expiry ), 'multiple' => (int) $question->pollq_multiple, 'totalvoters' => (int) $question->pollq_totalvoters, 'templateid' => (int) $question->pollq_tplid );
			$poll_questions_ids .= (int) $question->pollq_id . ', '; //as for the 0 at start, in SQL '01' is treated like '1'
		}
		$poll_questions_ids = substr($poll_questions_ids, 0, -2); //remove last comma
	}
	// Get Poll Answers
	list($order_by, $sort_order) = _polls_get_ans_result_sort();
	$answers = $wpdb->get_results("SELECT polla_aid, polla_qid, polla_answers, polla_votes, polla_atype FROM $wpdb->pollsa WHERE polla_qid IN ($poll_questions_ids) ORDER BY $order_by $sort_order");
	if($answers) {
		foreach($answers as $answer) {
			$polls_answers[(int)$answer->polla_qid][] = array( 'aid' => (int)$answer->polla_aid, 'qid' => (int) $answer->polla_qid, 'answers' => wp_kses_post( removeslashes( $answer->polla_answers ) ), 'votes' => (int) $answer->polla_votes, 'type' => sanitize_key($answer->polla_atype) );
		}
		// Sort again if object answers with alphabetical order to make the sorting reference the answer text instead of the answer ID. 
		foreach($polls_answers as $polls_answer){
			if ($polls_answer[array_key_first($polls_answer)]['type'] == 'object' && $order_by == 'polla_answers'){ 
				$sort_factor = ($sort_order == 'asc') ? 1 : -1;
				usort($polls_answer, fn($a, $b) => $sort_factor * strnatcasecmp(get_the_title($a['answers']), get_the_title($b['answers']) )); //cf. https://stackoverflow.com/questions/4282413/sort-array-of-objects-by-one-property
			}
		}
	}

	// Get Poll IPs
	$ips = $wpdb->get_results( "SELECT pollip_qid, pollip_aid FROM $wpdb->pollsip WHERE pollip_qid IN ($poll_questions_ids) AND pollip_ip = '" . poll_get_ipaddress($templates_set_id) . "' ORDER BY pollip_qid ASC" );
	if($ips) {
		foreach($ips as $ip) {
			$polls_ips[(int) $ip->pollip_qid][] = (int) $ip->pollip_aid;
		}
	}
	// Poll Archives
	$pollsarchive_output_archive .= "<div class=\"wp-polls wp-polls-archive\">\n";
	foreach($polls_questions as $polls_question) {
		$pollsarchive_output_archive .= "<div class=\"wp-polls-archive-item\">\n";
		// Assign All Loaded Templates Sets To $templates_sets_loaded
		if(empty($templates_sets_loaded)) {
			$templates_sets_loaded = array();
		}
		if(!in_array( (int) $polls_question['templateid'], $templates_sets_loaded, true)) {
			$templates_sets_loaded[] = (int) $polls_question['templateid'];
		}
		// Most/Least Variables
		$poll_most_answer = '';
		$poll_most_votes = 0;
		$poll_most_percentage = 0;
		$poll_least_answer = '';
		$poll_least_votes = 0;
		$poll_least_percentage = 0;
		// Is The Poll Total Votes 0?
		$poll_totalvotes_zero = $polls_question['totalvotes'] <= 0;
		$poll_totalvoters_zero = $polls_question['totalvoters'] <= 0;
		$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['start']));
		if(empty($polls_question['end'])) {
			$poll_end_date  = __('No Expiry', 'wp-polls');
		} else {
			$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['end']));
		}
		// Archive Poll Header
		$template_archive_header = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_pollarchiveheader', $polls_question['templateid']) );
		// Poll Question Variables
		$template_question = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_resultheader', $polls_question['templateid']) );
		$template_question = str_replace("%POLL_QUESTION%", $polls_question['question'], $template_question);
		$template_question = str_replace("%POLL_ID%", $polls_question['id'], $template_question);
		$template_question = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_question);
		$template_question = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_question);
		$template_question = str_replace("%POLL_START_DATE%", $poll_start_date, $template_question);
		$template_question = str_replace("%POLL_END_DATE%", $poll_end_date, $template_question);
		if($polls_question['multiple'] > 0) {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_question);
		} else {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_question);
		}
		// Print Out Result Header Template
		$pollsarchive_output_archive .= $template_archive_header;
		$pollsarchive_output_archive .= $template_question;
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		foreach($polls_answers[$polls_question['id']] as $polls_answer) {
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $polls_answer['votes'] > 0 ) {
				$poll_answer_percentage = round( ( $polls_answer['votes'] / $polls_question['totalvotes'] ) * 100 );
				$poll_multiple_answer_percentage = round( ( $polls_answer['votes'] / $polls_question['totalvoters'] ) * 100 );
				$poll_answer_imagewidth = round( $poll_answer_percentage * 0.9 );
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			if($polls_question['multiple'] === 0) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if(count($poll_answer_percentage_array) === count($polls_answers[$polls_question['id']])) {
					$percentage_error_buffer = 100 - array_sum($poll_answer_percentage_array);
					$poll_answer_percentage += $percentage_error_buffer;
					if($poll_answer_percentage < 0) {
						$poll_answer_percentage = 0;
					}
				}
			}
			$polls_answer['answers'] = wp_kses_post( $polls_answer['answers'] );
			// Let User See What Options They Voted
			if (isset( $polls_ips[$polls_question['id']] ) && in_array( $polls_answer['aid'], check_voted_multiple( $polls_question['id'], $polls_ips[$polls_question['id']] ), true ) ) {
				$template_answer = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_resultbody2', $polls_question['templateid']) );
			} else {
				$template_answer = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_resultbody', $polls_question['templateid']) );
			}
			$template_tags_arr = array(
										'%POLL_ID%',
										'%POLL_ANSWER_ID%',
										'%POLL_ANSWER%',
										'%POLL_ANSWER_TEXT%',
										'%POLL_ANSWER_VOTES%',
										'%POLL_ANSWER_PERCENTAGE%',
										'%POLL_MULTIPLE_ANSWER_PERCENTAGE%',
										'%POLL_ANSWER_IMAGEWIDTH%',
									);
			$template_val_arr = array(
										$polls_question['id'],
										$polls_answer['aid'],
										$polls_answer['answers'],
										htmlspecialchars( wp_strip_all_tags( $polls_answer['answers'] ) ),
										number_format_i18n( $polls_answer['votes'] ),
										$poll_answer_percentage,
										$poll_multiple_answer_percentage,
										$poll_answer_imagewidth,
									);
			if ($polls_answer['type'] == 'object') {
				add_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array', 10, 3 );
				$custom_vars_list_str = trim( wp_polls_list_post_type_fields('', $templates_set_id, '', 'template_tags', false) );
				remove_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_array');
				$custom_vars_arr = explode(' ', $custom_vars_list_str);
				$custom_vars_arr = wp_polls_get_custom_templates_vars_values_array($polls_answer['answers'], $custom_vars_arr);
				$template_tags_arr = array_merge($template_tags_arr, array_keys($custom_vars_arr));
				$template_val_arr = array_merge($template_val_arr, array_values($custom_vars_arr));
			}
			$template_answer = str_replace( $template_tags_arr, $template_val_arr, $template_answer );

			// Print Out Results Body Template
			$pollsarchive_output_archive .= $template_answer;

			// Get Most Voted Data
			if($polls_answer['votes'] > $poll_most_votes) {
				$poll_most_answer = $polls_answer['answers'];
				$poll_most_votes = $polls_answer['votes'];
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if($poll_least_votes === 0) {
				$poll_least_votes = $polls_answer['votes'];
			}
			if($polls_answer['votes'] <= $poll_least_votes) {
				$poll_least_answer = $polls_answer['answers'];
				$poll_least_votes = $polls_answer['votes'];
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_footer = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_resultfooter', $polls_question['templateid']) );
		$template_footer = str_replace("%POLL_ID%", $polls_question['id'], $template_footer);
		$template_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_footer);
		$template_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_footer);
		$template_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_footer);
		$template_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_footer);
		$template_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_footer);
		$template_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_footer);
		if($polls_question['multiple'] > 0) {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_footer);
		} else {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_footer);
		}
		// Archive Poll Footer
		$template_archive_footer = removeslashes( wp_polls_get_templates_set_setting('polltpl_template_pollarchivefooter', $polls_question['templateid']) );
		$template_archive_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_archive_footer);
		if($polls_question['multiple'] > 0) {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_archive_footer);
		} else {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_archive_footer);
		}
		// Print Out Results Footer Template
		$pollsarchive_output_archive .= $template_footer;
		// Print Out Archive Poll Footer Template
		$pollsarchive_output_archive .= $template_archive_footer;
		
		$pollsarchive_output_archive .= "</div>\n";
	}
	$pollsarchive_output_archive .= "</div>\n";
	
	// Polls Archive Paging
	if($max_page > 1) {
		$pollsarchive_output_archive .= ( $templates_set_id == $polls_question['templateid'] ) ? removeslashes( wp_polls_get_templates_set_setting('polltpl_template_pollarchivepagingheader', $polls_question['templateid']) ) : ''; //do not display pagin footer if unified archive page
		if(function_exists('wp_pagenavi')) {
			$pollsarchive_output_archive .= '<div class="wp-pagenavi">'."\n";
		} else {
			$pollsarchive_output_archive .= '<div class="wp-polls-paging">'."\n";
		}
		$pollsarchive_output_archive .= '<span class="pages">&#8201;'.sprintf(__('Page %s of %s', 'wp-polls'), number_format_i18n($page), number_format_i18n($max_page)).'&#8201;</span>';
		if ($start_page >= 2 && $pages_to_show < $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(1).'" title="'.__('&laquo; First', 'wp-polls').'">&#8201;'.__('&laquo; First', 'wp-polls').'&#8201;</a>';
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
		}
		if($page > 1) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page-1)).'" title="'.__('&laquo;', 'wp-polls').'">&#8201;'.__('&laquo;', 'wp-polls').'&#8201;</a>';
		}
		for($i = $start_page; $i  <= $end_page; $i++) {
			if($i === $page) {
				$pollsarchive_output_archive .= '<span class="current">&#8201;'.number_format_i18n($i).'&#8201;</span>';
			} else {
				$pollsarchive_output_archive .= '<a href="'.polls_archive_link($i).'" title="'.number_format_i18n($i).'">&#8201;'.number_format_i18n($i).'&#8201;</a>';
			}
		}
		if(empty($page) || ($page+1) <= $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page+1)).'" title="'.__('&raquo;', 'wp-polls').'">&#8201;'.__('&raquo;', 'wp-polls').'&#8201;</a>';
		}
		if ($end_page < $max_page) {
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link($max_page).'" title="'.__('Last &raquo;', 'wp-polls').'">&#8201;'.__('Last &raquo;', 'wp-polls').'&#8201;</a>';
		}
		$pollsarchive_output_archive .= '</div>';
		$pollsarchive_output_archive .= ( $templates_set_id == $polls_question['templateid'] ) ? removeslashes( wp_polls_get_templates_set_setting('polltpl_template_pollarchivepagingfooter', $polls_question['templateid']) ) : ''; //do not display pagin footer if unified archive page
	}

	// Output Polls Archive Page
	return apply_filters( 'wp_polls_archive', $pollsarchive_output_archive );
}


// Edit Timestamp Options
function poll_timestamp($poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block') {
	global $month;
	echo '<div id="'.$fieldname.'" style="display: '.$display.'">'."\n";
	$day = (int) gmdate('j', $poll_timestamp);
	echo '<select name="'.$fieldname.'_day" size="1">'."\n";
	for($i = 1; $i <=31; $i++) {
		if($day === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$month2 = (int) gmdate('n', $poll_timestamp);
	echo '<select name="'.$fieldname.'_month" size="1">'."\n";
	for($i = 1; $i <= 12; $i++) {
		if ($i < 10) {
			$ii = '0'.$i;
		} else {
			$ii = $i;
		}
		if($month2 === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$month[$ii]</option>\n";
		} else {
			echo "<option value=\"$i\">$month[$ii]</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$year = (int) gmdate('Y', $poll_timestamp);
	echo '<select name="'.$fieldname.'_year" size="1">'."\n";
	for($i = 2000; $i <= ($year+10); $i++) {
		if($year === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;@'."\n";
	echo '<span dir="ltr">'."\n";
	$hour = (int) gmdate('H', $poll_timestamp);
	echo '<select name="'.$fieldname.'_hour" size="1">'."\n";
	for($i = 0; $i < 24; $i++) {
		if($hour === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;:'."\n";
	$minute = (int) gmdate('i', $poll_timestamp);
	echo '<select name="'.$fieldname.'_minute" size="1">'."\n";
	for($i = 0; $i < 60; $i++) {
		if($minute === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}

	echo '</select>&nbsp;:'."\n";
	$second = (int) gmdate('s', $poll_timestamp);
	echo '<select name="'.$fieldname.'_second" size="1">'."\n";
	for($i = 0; $i <= 60; $i++) {
		if($second === $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>'."\n";
	echo '</span>'."\n";
	echo '</div>'."\n";
}


### Function: Place Cron
function cron_polls_place() {
	wp_clear_scheduled_hook('polls_cron');
	if (!wp_next_scheduled('polls_cron')) {
		wp_schedule_event(time(), 'hourly', 'polls_cron');
	}
}

### Funcion: Check All Polls Status To Check If It Expires
add_action('polls_cron', 'cron_polls_status');
function cron_polls_status() {
	global $wpdb;
	// Close Poll
	$close_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < '".current_time('timestamp')."' AND pollq_expiry != 0 AND pollq_active != 0");
	// Open Future Polls
	$active_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= '".current_time('timestamp')."' AND pollq_active = -1");
	// Update Latest Poll If Future Poll Is Opened
	if($active_polls) {
		$update_latestpoll = wp_polls_update_latest_id();
	}
	return;
}


### Function: Get Latest Poll ID
function polls_latest_id($templates_set_id = -1) {
	global $wpdb;
	$templates_set_id = ($templates_set_id > 0) ? (int) $templates_set_id : '%';
	$poll_id = $wpdb->get_var($wpdb->prepare("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 AND pollq_tplid LIKE %s ORDER BY pollq_timestamp DESC LIMIT 1", $templates_set_id));
	return (int) $poll_id;
}

### Function: Helper to update to Latest Poll ID
function wp_polls_update_latest_id($templates_set_id = -1) {
	global $wpdb;
	$latest_pollid = polls_latest_id($templates_set_id);
	$update_global_latestpoll = wp_polls_add_or_update_child_option( 'global_poll_latestpoll', $latest_pollid ); //global option
	$update_template_latestpoll = $wpdb->update( $wpdb->pollstpl, array('polltpl_latestpoll' => $latest_pollid), array('polltpl_id' => $templates_set_id), array('%d'), array('%d') ); //templates set specific option
	return (int) $latest_pollid;
}


### Check If In Poll Archive Page
function in_pollarchive($templates_set_id = -1) {
	$poll_archive_url = ($templates_set_id > 0) ?  wp_polls_get_templates_set_setting('polltpl_archive_url', $templates_set_id) : wp_polls_get_child_option('global_poll_archive_url');
	$poll_archive_url_array = explode('/', $poll_archive_url);
	$poll_archive_url = $poll_archive_url_array[count($poll_archive_url_array)-1];
	if(empty($poll_archive_url)) {
		$poll_archive_url = $poll_archive_url_array[count($poll_archive_url_array)-2];
	}
	$current_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
	if ( strpos( $current_url, strval( $poll_archive_url ) ) === false ) {
		return false;
	}

	return true;
}

function vote_poll_process( $poll_id, $poll_aid_array = [] ) {
	global $wpdb, $user_identity, $user_ID;

	do_action( 'wp_polls_vote_poll' );

	// Acquire lock
	$fp_lock = polls_acquire_lock( $poll_id );
	if ( $fp_lock === false ) {
		throw new InvalidArgumentException( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls'), $poll_id ) );
	}

	$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
	$is_real = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

	if( !$is_real ) {
		throw new InvalidArgumentException(sprintf(__('Invalid Answer to Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (!check_allowtovote()) {
		throw new InvalidArgumentException(sprintf(__('User is not allowed to vote for Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (empty($poll_aid_array)) {
		throw new InvalidArgumentException(sprintf(__('No answers given for Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if($poll_id === 0) {
		throw new InvalidArgumentException(sprintf(__('Invalid Poll ID. Poll ID #%s', 'wp-polls'), $poll_id));
	}

	$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

	if ($is_poll_open === 0) {
		throw new InvalidArgumentException(sprintf(__( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ));
	}

	$check_voted = check_voted($poll_id);
	if ( !empty( $check_voted ) ) {
		throw new InvalidArgumentException(sprintf(__('You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls'), $poll_id));
	}

	if (!empty($user_identity)) {
		$pollip_user = $user_identity;
	} elseif ( ! empty( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
		$pollip_user = $_COOKIE['comment_author_' . COOKIEHASH];
	} else {
		$pollip_user = __('Guest', 'wp-polls');
	}

	$pollip_user = sanitize_text_field( $pollip_user );
	$pollip_userid = $user_ID;
	$templates_set_id = wp_polls_get_poll_templates_set_id($poll_id);
	$pollip_ip = poll_get_ipaddress($templates_set_id);
	$pollip_host = poll_get_hostname($templates_set_id);
	$pollip_timestamp = current_time('timestamp');
	$poll_templates_set = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_logging_method, polltpl_cookielog_expiry, polltpl_template_aftervote, polltpl_aftervote, polltpl_answers_type FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id) );	
	$poll_logging_method = (int) $poll_templates_set->polltpl_logging_method;
	$cookie_expiry = (int) $poll_templates_set->polltpl_cookielog_expiry;
	$poll_aftervote = (int) $poll_templates_set->polltpl_aftervote;
	$poll_aftervote_template = (string) $poll_templates_set->polltpl_template_aftervote;
	$poll_type = (string) $poll_templates_set->polltpl_answers_type;

				
	// Only Create Cookie If User Choose Logging Method 1 Or 3
	if ( $poll_logging_method === 1 || $poll_logging_method === 3 ) {
		if ($cookie_expiry === 0) {
			$cookie_expiry = YEAR_IN_SECONDS;
		}
		setcookie( 'voted_' . $poll_id, implode(',', $poll_aid_array ), $pollip_timestamp + $cookie_expiry, apply_filters( 'wp_polls_cookiepath', SITECOOKIEPATH ) );
	}

	$i = 0;
	foreach ($poll_aid_array as $polla_aid) {
		$update_polla_votes = $wpdb->query( "UPDATE $wpdb->pollsa SET polla_votes = (polla_votes + 1) WHERE polla_qid = $poll_id AND polla_aid = $polla_aid" );
		if (!$update_polla_votes) {
			unset($poll_aid_array[$i]);
		}
		$i++;
	}

	$vote_q = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes+" . count( $poll_aid_array ) . "), pollq_totalvoters = (pollq_totalvoters + 1) WHERE pollq_id = $poll_id AND pollq_active = 1");
	if (!$vote_q) {
		throw new InvalidArgumentException(sprintf(__('Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls'), $poll_id));
	}
	
	foreach ($poll_aid_array as $polla_aid) {
		// Log Ratings In DB If User Choose Logging Method 2, 3 or 4
		if ( $poll_logging_method > 1 ){
			$wpdb->insert(
				$wpdb->pollsip,
				array(
					'pollip_qid'       => $poll_id,
					'pollip_aid'       => $polla_aid,
					'pollip_ip'        => $pollip_ip,
					'pollip_host'      => $pollip_host,
					'pollip_timestamp' => $pollip_timestamp,
					'pollip_user'      => $pollip_user,
					'pollip_userid'    => $pollip_userid
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
		}
	}

	// Release lock
	polls_release_lock( $fp_lock, $poll_id );
	
	do_action( 'wp_polls_vote_poll_success' );
	// Display success message
	$poll_question_text = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
	$poll_answers_ids_list = implode(',',$poll_aid_array);
	$poll_aftervote_message = wp_polls_apply_variables_to_after_vote_message_template($poll_question_text, $poll_type, $poll_aftervote_template, $poll_answers_ids_list, $poll_id, $templates_set_id );

	switch($poll_aftervote){
		case 1: //display results
			$output = $poll_aftervote_message.display_pollresult($poll_id, $poll_aid_array, false);
			break;
		case 2: //display disabled form
			$disable_poll_js = '<script type="text/javascript">jQuery("#polls_form_'.$poll_id.' :input").each(function (i){jQuery(this).attr("disabled","disabled")});</script>';
			$output = $poll_aftervote_message.display_pollvote($poll_id).$disable_poll_js;
			break;
		case 3: //only message
			$output = $poll_aftervote_message;
			break;
	}
	return $output;
}


### Function: Vote Poll
add_action('wp_ajax_polls', 'vote_poll');
add_action('wp_ajax_nopriv_polls', 'vote_poll');
function vote_poll() {
	global $wpdb, $user_identity, $user_ID;

	if( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls') {
		// Load Headers
		polls_textdomain();
		header('Content-Type: text/html; charset='.get_option('blog_charset').'');

		// Get Poll ID
		$poll_id = (isset($_REQUEST['poll_id']) ? (int) sanitize_key( $_REQUEST['poll_id'] ) : 0);

		// Ensure Poll ID Is Valid
		if($poll_id === 0) {
			_e('Invalid Poll ID', 'wp-polls');
			exit();
		}

		// Verify Referer
		if( ! check_ajax_referer( 'poll_'.$poll_id.'-nonce', 'poll_'.$poll_id.'_nonce', false ) ) {
			_e('Failed To Verify Referrer', 'wp-polls');
			exit();
		}

		// Which View
		switch( sanitize_key( $_REQUEST['view'] ) ) {
			// Poll Vote
			case 'process':
				try {
					$poll_aid_array = array_unique( array_map('intval', array_map('sanitize_key', explode( ',', $_POST["poll_$poll_id"] ) ) ) );
					echo vote_poll_process($poll_id, $poll_aid_array);
				} catch (Exception $e) {
					echo $e->getMessage();
				}
				break;
			// Poll Result
			case 'result':
				$poll_active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
				$templates_set_id = (int) wp_polls_get_poll_templates_set_id($poll_id);
				$poll_templates_set = $wpdb->get_row( $wpdb->prepare( "SELECT polltpl_close, polltpl_aftervote FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id) );	
				$poll_close = (int) $poll_templates_set->polltpl_close;
				$poll_aftervote = (int) $poll_templates_set->polltpl_aftervote;	
				$check_voted = check_voted($poll_id); 
				// Send results only if poll templates set's 'aftervote' setting allows it
				if ($poll_aftervote === 1) {
					echo display_pollresult($poll_id, 0, false);
				} elseif ($poll_aftervote === 2) { //if aftervote is set to 'show form', form must be sent again or it will be removed from the screen by the JS script.
					$disable_poll_js = ( ($poll_active === 0 && $poll_close === 3) || ! check_allowtovote() || ( (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) )) ? '<script type="text/javascript">jQuery("#polls_form_'.$poll_id.' :input").each(function (i){jQuery(this).attr("disabled","disabled")});</script>' : ''; //form disabled IF inactive poll with poll_close is set to 'show form' OR active/inactive poll when the user is not allowed to vote or has already voted
					echo display_pollvote($poll_id, false).$disable_poll_js;
				} // else do nothing = hide both form & results
				break;
			// Poll Booth Aka Poll Voting Form
			case 'booth':
				echo display_pollvote($poll_id, false);
				break;
		} // End switch($_REQUEST['view'])
	} // End if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'polls')
	exit();
}


### Function: Manage Polls
add_action('wp_ajax_polls-admin', 'manage_poll');
function manage_poll() {
	global $wpdb;
	### Form Processing
	if( isset( $_POST['action'] ) && sanitize_key( $_POST['action'] ) === 'polls-admin' ) {
		if( ! empty( $_POST['do'] ) ) {
			// Set Header
			header('Content-Type: text/html; charset='.get_option('blog_charset').'');

			// Decide What To Do
			switch($_POST['do']) {
				// Delete Polls Logs
				case __('Delete All Logs', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-polls-logs');
					if( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes') {
						$delete_logs = $wpdb->query("DELETE FROM $wpdb->pollsip");
						if($delete_logs) {
							echo '<p style="color: green;">'.__('All Polls Logs Have Been Deleted.', 'wp-polls').'</p>';
						} else {
							echo '<p style="color: red;">'.__('An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls').'</p>';
						}
					}
					break;
				// Delete Poll Logs For Individual Poll
				case __('Delete Logs For This Poll Only', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-poll-logs');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					if( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes') {
						$delete_logs = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
						if( $delete_logs ) {
							echo '<p style="color: green;">'.sprintf(__('All Logs For \'%s\' Has Been Deleted.', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
						} else {
							echo '<p style="color: red;">'.sprintf(__('An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
						}
					}
					break;
				// Open Poll
				case __('Open Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_open-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$open_poll = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 1
						),
						array(
							'pollq_id' => $pollq_id
						),
						array(
							'%d'
						),
						array(
							'%d'
						)
					);
					if( $open_poll ) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Opened', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					} else {
						echo '<p style="color: red;">'.sprintf(__('Error Opening Poll \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}
					break;
				// Close Poll
				case __('Close Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_close-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$close_poll = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 0
						),
						array(
							'pollq_id' => $pollq_id
						),
						array(
							'%d'
						),
						array(
							'%d'
						)
					);
					if( $close_poll ) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Closed', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					} else {
						echo '<p style="color: red;">'.sprintf(__('Error Closing Poll \'%s\'', 'wp-polls'), wp_kses_post( removeslashes( $pollq_question ) ) ).'</p>';
					}
					break;
				// Delete Poll
				case __('Delete Poll', 'wp-polls'):
					check_ajax_referer('wp-polls_delete-poll');
					$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
					$poll 								= $wpdb->get_row( $wpdb->prepare( "SELECT pollq_question, pollq_tplid FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$delete_poll_question 				= $wpdb->delete(  $wpdb->pollsq,   array( 'pollq_id' 	  => $pollq_id ), array( '%d' ) );
					$delete_poll_answers 				= $wpdb->delete(  $wpdb->pollsa,   array( 'polla_qid'	  => $pollq_id ), array( '%d' ) );
					$delete_poll_ip 					= $wpdb->delete(  $wpdb->pollsip,  array( 'pollip_qid' 	  => $pollq_id ), array( '%d' ) );
					$latest_pollid = wp_polls_update_latest_id($poll->pollq_tplid);
					if(!$delete_poll_question) {
						echo '<p style="color: red;">'.sprintf(__('Error In Deleting Poll \'%s\' Question', 'wp-polls'), wp_kses_post( removeslashes( $poll->pollq_question ) ) ).'</p>';
					}
					if(empty($text)) {
						echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Deleted Successfully', 'wp-polls'), wp_kses_post( removeslashes( $poll->pollq_question ) ) ).'</p>';
					}

					// Update Lastest Poll ID To Poll Options
					update_option( 'poll_latestpoll', polls_latest_id() );
					do_action( 'wp_polls_delete_poll', $pollq_id );
					break;
				// Insert Built-in Templates Set
				case __('Insert Built-in Templates Set', 'wp-polls');
					check_ajax_referer('wp-polls_add_templates_set');
					$answers_type  = (string) sanitize_text_field( $_POST['answers_type'] );
					$inserted_default_templates_set_id = wp_polls_insert_default_templates_set($answers_type);
					$data_arr = array();
					if(!$inserted_default_templates_set_id) {
						$data_arr['msg'] = '<p style="color: red;">'.sprintf(__('Error In Adding %s Default Templates Set', 'wp-polls'), ucfirst($answers_type)).'</p>';
					} else {
						$data_arr['msg'] = '<p style="color: green;">'.sprintf(__('Built-in %s Templates Set Inserted Successfully Under ID#%d', 'wp-polls'), ucfirst($answers_type), $inserted_default_templates_set_id) .'</p>';
					}
					$data_arr['content'] = wp_polls_list_available_templates_sets('table_rows', 1);
					echo json_encode($data_arr);
					break;
				// Duplicate Templates Set
				case __('Duplicate Templates Set', 'wp-polls');
					check_ajax_referer('wp-polls_duplicate-template');
					$templates_set_id  = (int) sanitize_key( $_POST['polltpl_id'] );
					// Duplicate all table's columns but the primary ID key
					$cols = "
						polltpl_set_name,
						polltpl_answers_type,
						polltpl_template_voteheader,
						polltpl_template_votebody,
						polltpl_template_votefooter,
						polltpl_template_resultheader,
						polltpl_template_resultbody,
						polltpl_template_resultbody2,
						polltpl_template_resultfooter,
						polltpl_template_resultfooter2,
						polltpl_template_pollarchivelink,
						polltpl_template_pollarchiveheader,
						polltpl_template_pollarchivefooter,
						polltpl_template_pollarchivepagingheader,
						polltpl_template_pollarchivepagingfooter,
						polltpl_template_disable,
						polltpl_template_error,
						polltpl_bar_style,
						polltpl_bar_background,
						polltpl_bar_border,
						polltpl_bar_height,
						polltpl_ajax_style_loading,
						polltpl_ajax_style_fading,
						polltpl_ans_sortby,
						polltpl_ans_sortorder,
						polltpl_ans_result_sortby,
						polltpl_ans_result_sortorder,
						polltpl_allowtovote,
						polltpl_logging_method,
						polltpl_cookielog_expiry,
						polltpl_ip_header,
						polltpl_archive_displaypoll,
						polltpl_archive_url,
						polltpl_currentpoll,
						polltpl_close
					";
					$query = "
						INSERT INTO $wpdb->pollstpl ($cols)
						SELECT $cols 
						FROM $wpdb->pollstpl 
						WHERE $wpdb->pollstpl.polltpl_id = '%d'
					";					
					$duplicate_templates_set = $wpdb->query( $wpdb->prepare($query, $templates_set_id) );
					if(!$duplicate_templates_set) {
						$data_arr['msg'] = '<p style="color: red;">'.__('Error In Duplicating Templates Set', 'wp-polls').'</p>';
					} else {
						$data_arr['msg'] = '<p style="color: green;" data-newID="'.$wpdb->insert_id.'">'.__('Templates Set Duplicated Successfully', 'wp-polls').'</p>';
					}
					$data_arr['content'] = wp_polls_list_available_templates_sets('table_rows', 1);
					echo json_encode($data_arr);
					break;
				// Delete Templates Set
				case __('Delete Templates Set', 'wp-polls');
					check_ajax_referer('wp-polls_delete-template');
					$templates_set_id  = (int) sanitize_key( $_POST['polltpl_id'] );
					$templates_set_type = $wpdb->get_var( $wpdb->prepare("SELECT polltpl_answers_type FROM $wpdb->pollstpl WHERE polltpl_id = %d", $templates_set_id) );
					$original_default_templates_set_id = wp_polls_get_child_option('default_templates_set_'.$templates_set_type); 
					$associated_polls_count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE pollq_tplid = %d", $templates_set_id ) );
					$new_templates_set_id = wp_polls_replace_templates_set_for_associated_polls($templates_set_id, $templates_set_type); 
					if (!$new_templates_set_id && $associated_polls_count>0){ //no other templates set available for the given type and it will impact some associated poll
						$data_arr['msg'] = '<p style="color: red;">'.sprintf(__('This template set is the only one currently available for polls with %s answers type and thus cannot be deleted.', 'wp-polls'), $templates_set_type).'</p>';
						$data_arr['content'] = wp_polls_list_available_templates_sets('table_rows', -1);
						echo json_encode($data_arr);	
						break;
					} elseif (!$new_templates_set_id && empty($associated_polls_count)) { //no other templates available for the given type, but it will have no impact on any poll
						wp_polls_add_or_update_child_option('default_templates_set_'.$templates_set_type, 0); 
						$new_templates_set_id = 0;
					}
					$delete_templates_set = $wpdb->delete( $wpdb->pollstpl, array( 'polltpl_id' => $templates_set_id ), array( '%d' ) );
					if(!$delete_templates_set) {
						$data_arr['msg'] = '<p style="color: red;">'.__('Error In Deleting Templates Set', 'wp-polls').'</p>';
					} else {
						$data_arr['msg'] = '<p style="color: green;">'.__('Templates Set Deleted Successfully', 'wp-polls').'</p>';
						$delete_poll_answers_objects_fields = $wpdb->delete( $wpdb->pollsaof, array( 'pollaof_tplid' => $templates_set_id ), array( '%d' ) );
						if ($new_templates_set_id == 0) $data_arr['msg'] .= '<p style="color: blue;">'.sprintf(__('No More %s Default Templates Set', 'wp-polls'), ucfirst($templates_set_type)).'</p>';
						if ($new_templates_set_id > 0 && ($new_templates_set_id != $original_default_templates_set_id)) $data_arr['msg'] .= '<p style="color: blue;">'.sprintf(__('Fallback default templates set for %s templates type is now ID#%d', 'wp-polls'), $templates_set_type, $new_templates_set_id ).'</p>';
					}					
					$data_arr['content'] = wp_polls_list_available_templates_sets('table_rows', -1);
					echo json_encode($data_arr);	
					break;
				// Set Poll To Default Templates
				case __('Set Poll To Default Templates', 'wp-polls'):
					check_ajax_referer('wp-polls_set-poll-to-default-templates');
					$poll_id  = (!empty($poll_id) && is_int($poll_id)) ? sanitize_key( $_POST['pollq_id'] ) : -1;
					$set_polls_to_default_templates_poll_id = false;
					$set_polls_to_default_templates_all_text = false;
					$set_polls_to_default_templates_all_object = false;
					if ($poll_id > 0){ //one poll only
						$set_polls_to_default_templates_poll_id = wp_polls_associate_poll_to_default_templates_set($poll_id);
					} else { //all polls 
						$set_polls_to_default_templates_all_text = wp_polls_associate_poll_to_default_templates_set($poll_id, 'text');
						$set_polls_to_default_templates_all_object = wp_polls_associate_poll_to_default_templates_set($poll_id, 'object');
					}
					if( is_bool($set_polls_to_default_templates_poll_id) && ( is_bool($set_polls_to_default_templates_all_text) || is_bool($set_polls_to_default_templates_all_object) )) { //the query returns bool (false) if error, or int (number of updated rows between 0 to X) if success. 
						echo '<p style="color: red;">'.__('Error While Setting All Polls To Default Templates', 'wp-polls').'</p>';
					} else {
						echo '<p style="color: green;">'.__('All Polls Are Succesfully Associated To Default Templates Sets', 'wp-polls') .'</p>';
					}					
					break;
				// Reset All Templates Sets
				case __('Reset All Templates Sets', 'wp-polls');
					check_ajax_referer('wp-polls_reset-all-templates-sets');
					$confirm_check  = sanitize_key( $_POST['reset_all_templates_sets_yes'] );
					$data_arr = array();
					if ($confirm_check === 'yes'){
						$delete_all_rows = $wpdb->query("TRUNCATE TABLE $wpdb->pollstpl");
						if ($delete_all_rows){
							$delete_poll_answers_objects_fields = $wpdb->query("TRUNCATE TABLE $wpdb->pollsaof");
							//reset default templates
							wp_polls_add_or_update_child_option('default_templates_set_text', 0);
							wp_polls_add_or_update_child_option('default_templates_set_object', 0);
							//insert text built-in templates sets and use them as default templates set
							$default_tpl_text_id = wp_polls_insert_default_templates_set('text'); 
							$default_tpl_object_id = wp_polls_insert_default_templates_set('object');
							if(!$default_tpl_text_id || !$default_tpl_object_id) {
								$data_arr['msg'] = '<p style="color: red;">'.__('Error While Resetting All Templates Sets', 'wp-polls').'</p>';
							} else {
								$data_arr['msg'] = '<p style="color: green;">'.__('All Templates Sets Are Succesfully Reset', 'wp-polls') .'</p>';
								$data_arr['content'] = wp_polls_list_available_templates_sets('table_rows', -1);
							}
						} else {
							$data_arr['msg'] = '<p style="color: red;">'.__('Could Not Delete All Templates Sets', 'wp-polls').'</p>';
						}
					}
					echo json_encode($data_arr);	
					break;
				// Retrieve Content
				case __('Retrieve Content', 'wp-polls'):
					check_ajax_referer('wp-polls_retrieve-content');
					$content_type = (string) sanitize_key($_POST['content_type']);
					$post_types_array = explode(',', $_POST['post_types']);
					$post_types_array = array_map('sanitize_key', $post_types_array);
					switch($content_type){
						//Post types items
						case 'post_types_items': 
							$keyword = (string) sanitize_text_field($_POST['keyword']);
							$posts_per_page = (int) wp_polls_get_child_option('obj_answers_selection_posts_per_page');
							$paged = ($posts_per_page > 0) ? (int) $_POST['page'] : 1;
							$posts_args = array(
												'orderby'	 		=> 'date',
												'order' 			=> 'DESC',
												'post_type' 		=> $post_types_array,
												'post_status' 		=> 'publish', // excluding: 'future', 'draft', 'pending', 'private'
												'posts_per_page'	=> $posts_per_page,
												's' 				=> $keyword,
												'paged' 			=> $paged,
												);
							$tax = (string) sanitize_key($_POST['tax']);
							if (!empty($tax)){
								$terms = (string) sanitize_key($_POST['term']);
								if (empty($terms)) $terms = get_terms(array('taxonomy' => $tax, 'fields' => 'ids')); //search all terms of selected taxonomy
								$posts_args['tax_query'] = array(
																array(
																		'taxonomy' 			=> $tax,
																		'field'    			=> 'term_id',
																		'terms'    			=> $terms,
																		'include_children' 	=> true,
																	),
															);
							}
							$posts_args = apply_filters('wp_polls_admin_ajax_get_posts_args', $posts_args, $post_types_array, $keyword);
							$query = new WP_Query($posts_args);
							$posts = $query->posts;
							if (empty($posts)){
								echo __('No post found', 'wp-polls');
								break;
							}
							$context = (string) sanitize_key($_POST['context']); //check whether it is called from the edit page or other
							echo_items_list($posts, 'ID', 'post_title', $content_type, $context);
							break;
						//Taxonomies with existing association to given post types 
						case 'allowed_tax':
							wp_polls_list_allowed_tax($post_types_array);
							break;	 
						//Terms
						case 'terms':
							$tax_array = explode(',', $_POST['tax']);
							$tax_array = array_map('sanitize_key', $tax_array);
							$allowed_tax_array = get_object_taxonomies($post_types_array, 'names'); //allow only taxonomies associated to given post type 
							$tax_array = array_intersect($tax_array, $allowed_tax_array);
							$terms_args = array(
												'taxonomy' => $tax_array,
												'orderby' => 'name',
												'order' => 'DESC',
												'hide_empty' => true,
												'hierarchical' => true,
												);
							$terms_args = apply_filters('wp_polls_admin_ajax_get_terms_args', $terms_args, $tax_array);
							$terms = get_terms($terms_args);
							
							if (empty($terms)){
								echo __('No term found', 'wp-polls');
								break;
							}
							$sorted_terms = array();
							sort_terms_hierarchically($terms, $sorted_terms);
							echo_items_list($sorted_terms, 'term_id', 'name', $content_type);
							break;	 
						// Fields
						case 'fields': 
							$pollq_id  = (int) sanitize_key( $_POST['pollq_id'] );
							$templates_set_id = (int) wp_polls_get_poll_templates_set_id($pollq_id);
							wp_polls_list_post_type_fields($post_types_array, $templates_set_id);
							break;
						}
						break;
			}
			exit();
		}
	}
}

### Function: Helper that prints lists of post type items fields in object type answer admin form. It may not support all custom fields (testing needed); this might need to be further implemented like so: https://wordpress.stackexchange.com/questions/260442/how-to-get-all-custom-fields-of-any-post-type#answer-261316
function wp_polls_list_post_type_fields($post_types_array = array(), $templates_set_id = '', $yet_unsaved_selected_fields = '', $echo_context = '', $echo = true){
	global $wpdb;
	
	$poll_saved_fields = array(); //fields already saved for this poll 
	if ($templates_set_id){ 
		$query_templates_set_id = ($templates_set_id > 0) ? $templates_set_id : '%'; //specific ID or SQL wildcard for 'all'
		$poll_saved_fields_array_of_obj = $wpdb->get_results( $wpdb->prepare( "SELECT pollaof_optype, pollaof_obj_field FROM $wpdb->pollsaof WHERE pollaof_tplid LIKE %s ORDER BY pollaof_aofid ASC", $query_templates_set_id ) );
		foreach ($poll_saved_fields_array_of_obj as $obj){
			$poll_saved_fields[$obj->pollaof_optype][] = $obj->pollaof_obj_field; //associative array such as '["post_type_1" => ["field_1","field_2"], "post_type_2" => ["field_1", "field_4"]'				}
		}
	} elseif ($yet_unsaved_selected_fields) { //used to remember selected fields and avoid form reset
		$poll_saved_fields = json_decode(html_entity_decode(stripslashes($yet_unsaved_selected_fields)), true); //associative array such as '["post_type_1" => ["field_1","field_2"], "post_type_2" => ["field_1", "field_4"]'
	}
	if (empty($poll_saved_fields)){ //fields to be saved by default at first save 
		$poll_saved_fields = json_decode(html_entity_decode(stripslashes( wp_polls_get_default_template_string('poll_ans_obj_fields', 'object') )), true); 
		$poll_saved_fields = apply_filters('wp_polls_checked_templates_fields_before_save', $poll_saved_fields);
	}
	switch($echo_context){
		case 'template_tags': //list poll templates tags (as in polls-templates.php)
			$alt_counter = 0;
			$default_fields = json_decode(html_entity_decode(stripslashes( wp_polls_get_default_template_string('poll_ans_obj_fields', 'object') )), true);
			$fields_to_loop = (!empty($poll_saved_fields)) ? $poll_saved_fields : $default_fields;
			$return_output = '';
			$table_class = (empty($fields_to_loop)) ? '' : 'widefat';
			if ($echo){
				echo '<table class="'.$table_class.'"><tbody>';
				if (empty($fields_to_loop)) {
					echo '<tr><td>';
					echo __('No custom variables available.','wp-polls').'<br />'.__('Object Answers Fields should first be checked in the Templates Details Page.', 'wp-polls');
					echo '</td></tr></tbody></table>';
					break;					
				}
			}
			foreach ($fields_to_loop as $post_type => $fields_arr){
				$post_type_obj = get_post_type_object( $post_type );
				$post_type_name = $post_type_obj->labels->singular_name;
				foreach ($fields_arr as $key => $field){
					if ($echo){
						$alt_counter++;
						if ($alt_counter & 1) echo '<tr'. ( (is_int($alt_counter / 3))? ' class="alternate"':'' ).'>'; //opening 'tr' when counter is odd and adding 'alt' class every two 'tr'.
						echo '<td>';
						echo '<strong>%ANSWER_' . strtoupper(sanitize_key($post_type_name)) . '_' . strtoupper(sanitize_key($field)) . '%</strong><br />' . sprintf(__('Variable corresponding to the object answer\'s field %s', 'wp-polls'), "\"".$field."\" (".$post_type_name.")");
						echo '</td>';
						if ( !($alt_counter & 1) || ($key === array_key_last($fields_arr)) ) echo '</tr>'; //closing 'tr' when counter is even or at during the last loop.
					} else {
						$tag = '%ANSWER_' . strtoupper(sanitize_key($post_type)) . '_' . strtoupper(sanitize_key($field)) . '%';
						$return_output .= apply_filters( 'wp_polls_custom_template_tags', $tag, $post_type, $field );
					}
				}
			}
			if ($echo) {
				echo 		'<tr class="alternate">';
				echo 			'<td colspan="2">';
				echo 				__('Note: The custom templates variabes are set in the Templates Set Details\' tab and is only available for template dedicated to Polls with Object Answers Type.', 'wp-polls');
				echo 			'</td>';
				echo 		'</tr>';
				echo 	'</tbody>';
				echo '</table>';
			} else {
				return $return_output;
			}
			break;
		default: //list fields as checkboxes
			foreach ($post_types_array as $post_type){ // print fields container per post type
				echo '<div id="' . $post_type . '_fields_list" class="fields-box-container">';
				$post_type_obj = get_post_type_object( $post_type );
				echo 	'<h4>' . $post_type_obj->labels->name . '</h4>';
				echo 	'<div class="wp-polls-dropdown-input-containers">';
				echo 		'<input type="text" placeholder="'. __(sprintf("Filter %s fields", strtolower($post_type_obj->labels->name)), "wp-polls") . '" id="fields_' . $post_type . '_items_search_input" class="wp-polls-dropdown-filter" onkeydown="if (event.keyCode == 13) {return false;}" onkeyup="filter_items(\'fields_' . $post_type . '_items_search_input\', \'' . $post_type . '_fields_list\', \'input[type=&quot;checkbox&quot;]\')">';
				echo 	'</div>';
				echo 	'<ul class="fields-post-type-box wp-polls-' . $post_type . '">';
				$default_fields = get_all_post_type_supports($post_type);
				$default_fields = array_keys($default_fields, true);
				$default_fields_to_remove = array('editor', 'custom-fields', 'page-attributes'); //can't think of any use for these default fields here, so remove them from the list.
				$default_fields = array_diff($default_fields, $default_fields_to_remove); 
				$plugins_fields = wp_polls_get_all_meta_keys($post_type, true, true); //should retrieve fields created with plugins like ACF - tested with ACF Pro 5.9.1 and 6.2.2 
				$all_fields = array_merge($default_fields, $plugins_fields);
				$all_fields = apply_filters('wp_polls_admin_ajax_get_plugins_fields', $all_fields, $post_type);
				if (empty($all_fields)){
					printf( __( 'No field found for post type %s.', 'wp-polls' ), $post_type );
					continue;
				}
				foreach ($all_fields as $field){ //print fields list items; fields checkboxes will be checked only if there are saved fields, or default fields will apply.
					$field_saved_or_default = (bool) isset($poll_saved_fields[$post_type]) ? in_array($field, $poll_saved_fields[$post_type], true) : false;
					echo 	'<li><label><input type="checkbox" name="pollq_fields_list" data-posttype="' . $post_type . '" value="' . $field . '" onchange="fields_checkbox_status_change()" ' . checked($field_saved_or_default, true, false) . '> <span> <span class="wp-polls-lighter-color">[' . $post_type_obj->labels->name . ']</span> ' . $field . '</span></label>';
				}
				echo 	'</ul>';
				echo '</div>';
			}
	}

}

function wp_polls_template_tags_filter_for_raw_text_list($tag, $post_type_name, $field) {
	$string = '<p style="margin: 2px 0;" class="wp-polls-custom-template-vars">- ' . $tag . '</p>';
	return $string;
}

function wp_polls_template_tags_filter_for_array($tag, $post_type_name, $field) {
	$string = $tag . ' ';
	return $string;
}

function polls_acquire_lock( $poll_id ) {
	$fp = fopen( polls_lock_file( $poll_id ), 'w+' );

	if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
		return false;
	}

	ftruncate( $fp, 0 );
	fwrite( $fp, microtime( true ) );

	return $fp;
}

function polls_release_lock( $fp, $poll_id ) {
	if ( is_resource( $fp ) ) {
		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		unlink( polls_lock_file( $poll_id ) );

		return true;
	}

	return false;
}

function polls_lock_file( $poll_id ) {
	return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
}

function _polls_get_ans_sort($templates_set_id = -1) {
	$order_by = ($templates_set_id > 0) ? wp_polls_get_templates_set_setting('polltpl_ans_sortby', $templates_set_id) : wp_polls_get_default_template_string('poll_ans_sortby', 'text');
	switch( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = ($templates_set_id > 0) ? wp_polls_get_templates_set_setting('polltpl_ans_sortorder', $templates_set_id) : wp_polls_get_default_template_string('poll_ans_sortorder', 'text');
	return array( $order_by, $sort_order );
}

function _polls_get_ans_result_sort($templates_set_id = -1) {
	$order_by = ($templates_set_id > 0) ? wp_polls_get_templates_set_setting('polltpl_ans_result_sortby', $templates_set_id) : wp_polls_get_default_template_string('poll_ans_result_sortby', 'text');
	switch( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = ($templates_set_id > 0) ? wp_polls_get_templates_set_setting('polltpl_ans_result_sortorder', $templates_set_id) : wp_polls_get_default_template_string('poll_ans_result_sortorder', 'text');
	return array( $order_by, $sort_order );
}


### Function: Plug Into WP-Stats
add_action( 'plugins_loaded','polls_wp_stats' );
function polls_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'polls_page_admin_general_stats' );
	add_filter( 'wp_stats_page_plugins', 'polls_page_general_stats' );
}


### Function: Add WP-Polls General Stats To WP-Stats Page Options
function polls_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if( (int) $stats_display['polls'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" checked="checked" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-Polls General Stats To WP-Stats Page
function polls_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if( (int)  $stats_display['polls'] === 1) {
		$content .= '<p><strong>'.__('WP-Polls', 'wp-polls').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions(false), 'wp-polls'), number_format_i18n(get_pollquestions(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers(false), 'wp-polls'), number_format_i18n(get_pollanswers(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', get_pollvotes(false), 'wp-polls'), number_format_i18n(get_pollvotes(false))).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}

### Function: Helper to sort terms - source: https://wordpress.stackexchange.com/questions/37285/custom-taxonomy-get-the-terms-listing-in-order-of-parent-child#answer-239935
function sort_terms_hierarchically( array &$terms, array &$into, $parent_id = 0 ) { 
	foreach ( $terms as $i => $term ) {
		if ( $term->parent == $parent_id ) {
			$into[$term->term_id] = $term;
			unset( $terms[ $i ] );
		}
	}

	foreach ( $into as $top_term ) {
		$top_term->children = array();
		sort_terms_hierarchically( $terms, $top_term->children, $top_term->term_id );
	}

}

### Function: Helper to print list of posts or terms as a select box's content (if terms have children, they will be displayed as sublists).
function echo_items_list($items, $id_prop, $name_prop, $items_type, $context = "") { 
	$input_type = "";
	$javascript_hook = "";
	switch($items_type) {
		case 'post_types_items':
			$input_type = 'checkbox';
			$javascript_hook = ($context == 'edit') ? 'add_poll_answer_edit(\'object\')' : 'add_poll_answer_add(\'object\')';
		break;
		case 'terms':
			$input_type = 'radio';
			$javascript_hook = 'term_radio_status_change()';
		break;
	}
	echo '<ul class="pollq-items-lists">';
	foreach ($items as $item) {
		$data_post_type_attr = "";
		$post_type_span = "";
		if ($items_type === 'post_types_items') {
			$item_post_type = $item->post_type;
			$data_post_type_attr = 'data-post-type = "' . $item_post_type . '"';
			$post_type_obj = get_post_type_object($item_post_type);
			$post_type_span = '<span class="wp-polls-lighter-color">[' . $post_type_obj->labels->singular_name . '] </span>';
		}
		echo '<li><label for="' . $input_type . '_' . $item->$id_prop . '"><input type="' . $input_type . '" name="pollq_' . $items_type . '_list" id="' . $input_type . '_' . $item->$id_prop . '" ' . $data_post_type_attr . ' value="' . $item->$id_prop . '" onchange="' . $javascript_hook . '"><span>' . $post_type_span . $item->$name_prop . '</span></label>';
		if ( !empty($item->children) ){
			echo '<ul class="children">';
			echo_items_list($item->children, $id_prop, $name_prop, $items_type, $context);
			echo '</ul>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

### Function: Helper to print list options list of taxonomies associated to given post types 
function wp_polls_list_allowed_tax($post_types_array, $init = false ) { 
	$selected = ($init) ? 'selected ' : '' ; 
	echo '<option ' . $selected . 'value>' . __('All taxonomies', 'wp-polls') . '</option>';
	$post_types_array = apply_filters('wp_polls_admin_list_taxonomies_with_post_types', $post_types_array); //taxonomies' query result will only include taxonomies associated to post types names included in that array. 
	$tax_objects = get_object_taxonomies($post_types_array, 'objects');
	$tax_objects = apply_filters('wp_polls_admin_list_taxonomies', $tax_objects);
	foreach ($tax_objects as $tax_obj) {
		echo '<option value="' . $tax_obj->name . '">' . $tax_obj->labels->name . '</option>';
	} 
}

### Function: Helper to print a select list with available poll templates sets.
function wp_polls_list_available_templates_sets($context = 'select_list_options', $rows_quantity = -1, $poll_id = -1, $restrict_to_type = '', $selected_val = '') { 
	global $wpdb;
	$templates_sets = $wpdb->get_results( "SELECT polltpl_id, polltpl_set_name, polltpl_answers_type FROM $wpdb->pollstpl ORDER BY polltpl_id DESC" );
	$templates_sets = apply_filters('wp_polls_admin_list_available_templates_sets', $templates_sets);
	if (empty($templates_sets)) {
		return '<tr><td colspan="9" align="center"><strong>'.__('No Templates Set Found', 'wp-polls').'</strong></td></tr>';
	}
	$data = '';
	$i = 0;
	foreach ($templates_sets as $templates_set) {
		if($i == $rows_quantity) break; //limit number of rows output. -1 means full list.
		$tpl_id = $templates_set->polltpl_id;
		$tpl_name =  removeslashes(wp_kses_post($templates_set->polltpl_set_name));
		$tpl_type = $templates_set->polltpl_answers_type;
		$is_default = wp_polls_is_default_template($tpl_id, $tpl_type);
		$text_type_default = ($is_default) ? '<strong>['.__('Default', 'wp-polls').']</strong> ' : '';
		switch($context){
			case 'select_list_options':
				if (!empty($restrict_to_type) && $restrict_to_type != $tpl_type) continue 2; //skip end of foreach loop not to list a template which is not of the given answers type.
				$saved_templates_set_id = (is_int($poll_id) && $poll_id>0) ? $wpdb->get_var( $wpdb->prepare("SELECT pollq_tplid FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id) ) : wp_polls_get_child_option('default_templates_set_'.$tpl_type);
				$data .= '<option value="'.$tpl_id.'" data-type="'.$tpl_type.'" data-default="'.$is_default.'" '.selected($tpl_id, (!empty($selected_val)) ? $selected_val : $saved_templates_set_id).'>'.$text_type_default. 'TPL#'.$tpl_id.' - '.$tpl_name.'</option>';
				break;
			case 'table_rows':
				$associated_polls = $wpdb->get_results( $wpdb->prepare("SELECT pollq_id, pollq_question FROM $wpdb->pollsq WHERE pollq_tplid = %d ORDER BY pollq_id DESC", $tpl_id) );
				$total_associated_polls_count = (int) $wpdb->num_rows;
				$total_associated_polls_list_tooltip = '';
				if($total_associated_polls_count>0) {
					$total_associated_polls_list_tooltip .= "<div class=\"wp-polls-tooltip-container\">";
					$total_associated_polls_list_tooltip .= 	"<span class=\"wp-polls-tooltip-icon\">?</span>";
					$total_associated_polls_list_tooltip .=		"<p class=\"wp-polls-tooltip-info\">";
					$total_associated_polls_list_tooltip .= 		"<span class=\"info\">";			
					$j=0;
					foreach($associated_polls as $associated_poll) {
						if ($associated_poll && $j == 0) $total_associated_polls_list_tooltip .= "<strong>".__("Associated Polls", "wp-polls")."</strong><br />";			
						$total_associated_polls_list_tooltip .= __('Poll', 'wp-polls') . ' #' . $associated_poll->pollq_id . ' - ' . $associated_poll->pollq_question ."<br />";
						$j++;
					}
					$total_associated_polls_list_tooltip .= 		"</span>";
					$total_associated_polls_list_tooltip .= 	"</p>";
					$total_associated_polls_list_tooltip .= "</div>";									
				}
				if($i%2 == 0) {
					$style = 'class="alternate"';
				}  else {
					$style = '';
				}				
				$base_page = 'admin.php?page=wp-polls%2Fpolls-templates.php';
				$data .= '<tr id="tplset-'.$tpl_id.'" '.$style.'>
							<td><strong>'.$tpl_id.'</strong></td>
							<td>'.ucfirst($tpl_type).'</td>
							<td>'.$text_type_default.$tpl_name.'</td>
							<td>'.$total_associated_polls_count.$total_associated_polls_list_tooltip.'</td>
							<td><a href="#DuplicateTemplate" onclick="duplicate_templates_set(\''.$tpl_id.'\',\''.wp_create_nonce('wp-polls_duplicate-template').'\');" class="duplicate">'.__('Duplicate', 'wp-polls').'</a></td>
							<td><a href="'.$base_page.'&amp;mode=edit&amp;tpl_id='.$tpl_id.'" class="edit">'.__('Edit', 'wp-polls').'</a></td>
							<td><a href="#DeleteTemplate" onclick="delete_templates_set(\''.$tpl_id.'\', \''.sprintf(esc_js(__('You are about to delete this template: \"%s\".', 'wp-polls')), esc_js($tpl_name)).'\', \''.wp_create_nonce('wp-polls_delete-template').'\');" class="delete">'.__('Delete', 'wp-polls').'</a></td>
						  </tr>';
				break;
		}
		$i++;
	}
	if (empty($data) && $context === 'select_list_options'){
		$msg = (empty($restrict_to_type)) ? __('No Templates Set Found','wp-polls') :  sprintf(__('No Templates Set Found for %s type ','wp-polls'), ucfirst($restrict_to_type));
		$data .= '<option value="0" data-type="'.$restrict_to_type.'" data-default="0" selected="selected">'.$msg.'</option>';
	}
	return $data; 
}

### Function: Get all post type fields saved as post metadata (where ACF store its fields names for instance) - taken from https://wordpress.stackexchange.com/questions/249505/get-all-meta-keys-assigned-to-a-post-type
function wp_polls_get_all_meta_keys($post_type = 'post', $exclude_empty = false, $exclude_hidden = false) {
	global $wpdb;
	$query = "
		SELECT DISTINCT($wpdb->postmeta.meta_key) 
		FROM $wpdb->posts 
		LEFT JOIN $wpdb->postmeta 
		ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
		WHERE $wpdb->posts.post_type = '%s'
	";
	if($exclude_empty) 
		$query .= " AND $wpdb->postmeta.meta_key != ''";
	if($exclude_hidden) 
		$query .= " AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)' ";

	$meta_keys = $wpdb->get_col($wpdb->prepare($query, $post_type));

	return $meta_keys;
}

### Function: Helper to get a child option's value from 'poll_options' array.
function wp_polls_get_child_option($child_option_key) {
	$poll_options = get_option('poll_options');
	return ( !empty($poll_options) && isset($poll_options[$child_option_key]) ) ? $poll_options[$child_option_key] : null;
}

### Function: Helper to update a child option's value in 'poll_options' array.
function wp_polls_add_or_update_child_option($child_option_key, $value) {
	$poll_options = get_option('poll_options');
	if($poll_options === false){
		add_option('poll_options');
		$poll_options = array();
	} 
	$poll_options[$child_option_key] = $value;
	return update_option('poll_options', $poll_options);
}

### Function: Helper to delete a child option from 'poll_options' array.
function wp_polls_delete_child_option($child_option_key) {
	$poll_options = get_option('poll_options');
	if(!empty($poll_options)){
		unset($poll_options[$child_option_key]);
		return update_option('poll_options', $poll_options);
	} else return true;
}

### Function: Helper to retrieve field content associated to given template variable 
function wp_polls_get_custom_templates_vars_values_array($object_ID, $custom_vars_arr) {
	$custom_template_answer_variables = array();
	foreach ($custom_vars_arr as $custom_var_key) { //generate associative array with key formatted as '%ANSWER_POST-TYPE_FIELD%', and values of corresponding fields, as in $template_answer_variables above.
		$custom_var = trim($custom_var_key, '%');
		$custom_var = explode('_', $custom_var);
		$answer_post_type = isset($custom_var[1]) ? strtolower($custom_var[1]) : '';
		unset($custom_var[0]); //remove 'ANSWER'
		if (isset($custom_var[1])) unset($custom_var[1]); //remove 'POST-TYPE'
		$answer_field = isset($custom_var[2]) ? strtolower(implode('_', $custom_var)) : '';
		
		if (post_type_supports($answer_post_type, $answer_field)) {
			switch($answer_field){
				case 'title':
					$custom_template_answer_variables[$custom_var_key] = get_the_title($object_ID);
					break;
				case 'author':
					$author_id = get_post_field ('post_author', $object_ID);
					break;
				case 'thumbnail':
					$size = 'medium';
					$size = apply_filters('wp_polls_template_thumbnail_size', $size);
					$all_sizes = array_keys(wp_get_registered_image_subsizes()); //list of sizes names: thumbnail, medium, large, etc.
					$size = (in_array($size, $all_sizes)) ? $size : $all_sizes[0]; //set first value found as fallback.
					$custom_template_answer_variables[$custom_var_key] = get_the_post_thumbnail_url($object_ID, 'medium');
					break;
				case 'excerpt':
					$custom_template_answer_variables[$custom_var_key] = get_the_excerpt($object_ID);
					break;
					/* could following default post_type_supports fields ever prove useful?
					case 'trackbacks': //get trackbacks count text
						$args = array(
							'type'    => 'trackback',
							'post_id' => $object_ID,
							'count'   => true,
						);
						$trackbacks_count = (int) get_comments($args);
						if ($trackbacks_count === 0) {
							$trackbacks_count_text = __('0 trackback', 'wp-polls');
						} elseif ($trackbacks_count === 1) {
							$trackbacks_count_text = __('1 trackback', 'wp-polls');
						} elseif ($trackbacks_count > 1) {
							$trackbacks_count_text = sprintf(__('%d trackbacks', 'wp-polls'), $trackbacks_count);
						}
						$custom_template_answer_variables[$custom_var_key] = $trackbacks_count_text;
						break;
					case 'comments': //get comments count text
						$custom_template_answer_variables[$custom_var_key] = get_comments_number_text( __('0 comment', 'wp-polls'), __('1 comment', 'wp-polls'), __('% comments', 'wp-polls'), $object_ID );
						break;
					case 'revisions': //get revisions count text
						$revisions_arr = wp_get_post_revisions($object_ID);
						$revisions_count = (int) count($revisions_arr);
						if ($revisions_count === 0) {
							$revisions_count_text = __('0 revision', 'wp-polls');
						} elseif ($revisions_count === 1) {
							$revisions_count_text = __('1 revision', 'wp-polls');
						} elseif ($revisions_count > 1) {
							$revisions_count_text = sprintf(__('%d revisions', 'wp-polls'), $revisions_count);
						}
						$custom_template_answer_variables[$custom_var_key] = $revisions_count_text;
						break;						
					case 'post_format':
						$custom_template_answer_variables[$custom_var_key] = get_post_format($object_ID);
						break;							
					*/
			}
		} else { //retrieve custom field
			$custom_template_answer_variables[$custom_var_key] = get_post_meta($object_ID, $answer_field, true); //bool(true) to return a single value, i.e. would fail to retrieve the complete value in case an array is stored in the corresponding meta key (which is not recommended - cf. https://wordpress.stackexchange.com/questions/245478/whats-the-point-of-get-post-metas-single-param#answer-245505)   
		}
	}
	$custom_template_answer_variables = apply_filters( 'wp_polls_template_custom_dynamic_variables', $custom_template_answer_variables );
	return (array) $custom_template_answer_variables;
}

### Function: Helper to check whether a template ID is set as default.
function wp_polls_is_default_template($templates_set_id, $answers_type = 'any') {
	if ($answers_type === 'any'){
		return ( ($templates_set_id == wp_polls_get_child_option('default_templates_set_text')) || ($templates_set_id == wp_polls_get_child_option('default_templates_set_object')) ) ? true : false ;
	} else {
		return ( $templates_set_id == wp_polls_get_child_option('default_templates_set_'.$answers_type) ) ? true : false;
	}
}

### Function: Helper to update associated polls when removing or changing the type of a templates set (poll_id = -1 stands for all polls)
function wp_polls_associate_poll_to_default_templates_set($poll_id = -1, $answers_type = '') {
	global $wpdb;
	$where_clause = ($poll_id>0) ? array('pollq_id' => $poll_id) : array('pollq_expected_atype' => $answers_type); //match specific poll or all polls of given answers type 
	$where_format = ($poll_id>0) ? array('%d') : array('%s');
	$poll_expected_atype = ($poll_id>0) ? $wpdb->get_var( $wpdb->prepare( "SELECT pollq_expected_atype FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) ) : $answers_type;
	$default_tpl_id = wp_polls_get_child_option('default_templates_set_'.$poll_expected_atype);
	if (empty($default_tpl_id)) return false; 
	$set_polls_to_default_templates_set = $wpdb->update( $wpdb->pollsq, array('pollq_tplid' => $default_tpl_id), $where_clause, array('%d'), $where_format );
	return $set_polls_to_default_templates_set;
}

### Function: Helper to update associated polls when removing or changing the type of a templates set.
function wp_polls_replace_templates_set_for_associated_polls($templates_set_id_to_replace, $answers_type) {
	global $wpdb;
	$new_templates_set_id = '';
	if(wp_polls_is_default_template($templates_set_id_to_replace, $answers_type)) { //it was default templates set, so use instead last available templates set of the same type as new default
		$new_templates_set_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT polltpl_id FROM $wpdb->pollstpl WHERE polltpl_answers_type = %s AND polltpl_id <> %d ORDER BY polltpl_id DESC LIMIT 1", $answers_type, $templates_set_id_to_replace) ); //pick last set of given type excluding the one to replace
		if ($new_templates_set_id) wp_polls_add_or_update_child_option('default_templates_set_'.$answers_type, $new_templates_set_id); //set it as default
	} else { //use default templates set
		$new_templates_set_id = wp_polls_get_child_option('default_templates_set_'.$answers_type);
	}
	if (!empty($new_templates_set_id)){ //update associated polls' rows
		$replace_templates_set_in_associated_polls = $wpdb->query( $wpdb->prepare("UPDATE $wpdb->pollsq SET pollq_tplid = %d WHERE pollq_tplid = %d", $new_templates_set_id, $templates_set_id_to_replace) );
		return (int) $new_templates_set_id;
	} else { //failure - there is no existing template for this type
		return false;
	}					
}

### Function: Helper to change a key prefix
function wp_polls_replace_key_prefix($key, $new_prefix, $separator = '_') {
	$key_arr = explode($separator, $key); 
	$key_arr[0] = $new_prefix;
	$new_key = implode($separator, $key_arr);
	return $new_key;
}

### Function: Helper to print tooltip markup
function wp_polls_print_tooltip_markup($tooltip_text_i18n) {
	$markup  =  "<div class=\"wp-polls-tooltip-container\">\n";
	$markup .=	"	<span class=\"wp-polls-tooltip-icon\">?</span>\n";
	$markup .=	"		<p class=\"wp-polls-tooltip-info\">\n";
	$markup .=	"			<span class=\"info\">".$tooltip_text_i18n."</span>\n";
	$markup .=	"		</p>\n";
	$markup .=	"</div>\n";
	echo $markup;
}

### Function: Returns hard-coded strings serving as default templates
function wp_polls_get_default_template_string($key, $answers_type, $templates_set_id = '') {
	if($answers_type === 'object'){ 
		switch($key){
			case "poll_set_name":
				return __('Built-in Templates Set For Polls With Object Answers', 'wp-polls');
			case "poll_answers_type":
				return 'object';
			case "poll_template_voteheader":
				return "<p style=\"text-align:center\"><strong>%POLL_QUESTION%</strong><div class=\"wp-polls-ans wp-polls-ans-obj\" id=\"polls-%POLL_ID%-ans\" data-ansmax=\"%POLL_MULTIPLE_ANS_MAX%\"><div class=\"wp-polls-grid-wrapper\">";
			case "poll_template_votebody":
				return "<label class=\"wp-polls-radio-card\" for=\"wp_polls_radio_card_%POLL_ANSWER_ID%\"><input id=\"wp_polls_radio_card_%POLL_ANSWER_ID%\" name=\"wp-polls-ans-obj\" type=\"%POLL_CHECKBOX_RADIO%\" value=\"%POLL_ANSWER_ID%\" onchange=\"poll_limit_checkbox_selection(this);\"><div class=\"wp-polls-card-content-wrapper\"><span class=\"wp-polls-check-icon\"></span><div class=\"wp-polls-card-content\"><div class=\"wp-polls-img-container\"><img alt=\"%ANSWER_POST_TITLE%\" src=\"%ANSWER_POST_THUMBNAIL%\"></div><h4>%ANSWER_POST_TITLE%</h4><h5>%ANSWER_POST_EXCERPT%</h5></div></div></label>";
			case "poll_template_votefooter":
				return "</div></div><p style=\"text-align:center\"><input class=\"wp-polls-buttons btn\" name=\"vote\" onclick=\"poll_vote(%POLL_ID%);\" type=\"button\" value=\"Votez\"><p style=\"text-align:center\"><a href=\"#ViewPollResults\" onclick=\"poll_result(%POLL_ID%); return false;\" title=\"Voir les résultats de ce sondage\">Voir les résultats</a>";
			case "poll_template_resultheader":
				return "<p style=\"text-align: center;\"><strong>%POLL_QUESTION%</strong></p>\n<div id=\"polls-%POLL_ID%-ans\" class=\"wp-polls-ans\">\n<ul class=\"wp-polls-ul\">";
			case "poll_template_resultbody":
				return "<li>%ANSWER_POST_TITLE% <small>(%POLL_ANSWER_PERCENTAGE%%".__(',', 'wp-polls')." %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")</small><div class=\"pollbar wp-polls-tpl-$templates_set_id\" style=\"width: %POLL_ANSWER_IMAGEWIDTH%%;\" title=\"%ANSWER_POST_TITLE% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")\"></div></li>";
			case "poll_template_resultbody2":
				return "<li><strong><i>%ANSWER_POST_TITLE% <small>(%POLL_ANSWER_PERCENTAGE%%".__(',', 'wp-polls')." %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")</small></i></strong><div class=\"pollbar wp-polls-tpl-$templates_set_id\" style=\"width: %POLL_ANSWER_IMAGEWIDTH%%;\" title=\"".__('You Have Voted For This Choice', 'wp-polls')." - %ANSWER_POST_TITLE% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")\"></div></li>";
			case "poll_template_resultfooter":
				return "</ul>\n<p style=\"text-align: center;\">".__('Total Voters', 'wp-polls').": <strong>%POLL_TOTALVOTERS%</strong></p>\n</div>";
			case "poll_template_resultfooter2":
				return "</ul>\n<p style=\"text-align: center;\">".__('Total Voters', 'wp-polls').": <strong>%POLL_TOTALVOTERS%</strong></p>\n<p style=\"text-align: center;\"><a href=\"#VotePoll\" onclick=\"poll_booth(%POLL_ID%); return false;\" title=\"".__('Vote For This Poll', 'wp-polls')."\">".__('Vote', 'wp-polls')."</a></p>\n</div>";
			case "poll_template_pollarchivelink":
				return "<ul>\n<li><a href=\"%POLL_ARCHIVE_URL%\">".__('Polls Archive', 'wp-polls')."</a></li>\n</ul>";
			case "poll_template_pollarchiveheader":
				return "";
			case "poll_template_pollarchivefooter":
				return "<p>".__('Start Date:', 'wp-polls')." %POLL_START_DATE%<br />".__('End Date:', 'wp-polls')." %POLL_END_DATE%</p>";
			case "poll_template_pollarchivepagingheader":
				return "";
			case "poll_template_pollarchivepagingfooter":
				return "";
			case "poll_template_disable":
				return __('Sorry, there are no polls available at the moment.', 'wp-polls');
			case "poll_template_error":
				return __('An error has occurred when processing your poll.', 'wp-polls');
			case "poll_template_aftervote":
				return "<div class=\"wp-polls-aftervote-msg\"><em>".__('Thank you, your vote for question', 'wp-polls').' <strong>%POLL_QUESTION%</strong> '.__('has been saved:', 'wp-polls')."</em><br /><em>%POLL_USER_VOTED_ANSWERS_LIST%</em></div>";		
			case "poll_bar_style":
				return "default";				
			case "poll_bar_background":
				return "d8e1eb";				
			case "poll_bar_border":
				return "c8c8c8";				
			case "poll_bar_height":
				return "8";				
			case "poll_ajax_style_loading":
				return "1";				
			case "poll_ajax_style_fading":
				return "1";				
			case "poll_ans_sortby":
				return "polla_aid";				
			case "poll_ans_sortorder":
				return "asc";				
			case "poll_ans_result_sortby":
				return "polla_votes";				
			case "poll_ans_result_sortorder":
				return "desc";				
			case "poll_allowtovote":
				return "2";				
			case "poll_aftervote":
				return "1";					
			case "poll_logging_method":
				return "3";				
			case "poll_cookielog_expiry":
				return "0";				
			case "poll_ip_header":
				return "";				
			case "poll_archive_perpage":
				return "5";				
			case "poll_archive_displaypoll":
				return "2";				
			case "poll_archive_url":
				return "";				
			case "poll_currentpoll":
				return "0";				
			case "poll_close":
				return "3";
			case "poll_latestpoll":
				return "0";										
			case "poll_ans_obj_fields": //only for object answers type
				return "{\"post\":[\"title\",\"thumbnail\",\"excerpt\"]}";			
			default:
				return __('Error: string could not be retrieved', 'wp-polls');
		}
	} else { //return default templates for text answers
		switch($key){
			case "poll_set_name":
				return __('Built-in Templates Set For Polls With Text Answers', 'wp-polls');
			case "poll_answers_type":
				return 'text';
			case "poll_template_voteheader":
				return "<p style=\"text-align: center;\"><strong>%POLL_QUESTION%</strong></p>\n<div id=\"polls-%POLL_ID%-ans\" class=\"wp-polls-ans\" data-ansmax=\"%POLL_MULTIPLE_ANS_MAX%\">\n<ul class=\"wp-polls-ul\">";
			case "poll_template_votebody":
				return "<li><input type=\"%POLL_CHECKBOX_RADIO%\" id=\"poll-answer-%POLL_ANSWER_ID%\" name=\"poll_%POLL_ID%\" value=\"%POLL_ANSWER_ID%\" onchange=\"poll_limit_checkbox_selection(this);\" /> <label for=\"poll-answer-%POLL_ANSWER_ID%\">%POLL_ANSWER%</label></li>";
			case "poll_template_votefooter":
				return "</ul>\n<p style=\"text-align: center;\"><input type=\"button\" name=\"vote\" value=\"".__('Vote', 'wp-polls')."\" class=\"wp-polls-buttons btn\" onclick=\"poll_vote(%POLL_ID%);\" /></p>\n<p style=\"text-align: center;\"><a href=\"#ViewPollResults\" onclick=\"poll_result(%POLL_ID%); return false;\" title=\"".__('View Results Of This Poll', 'wp-polls')."\">".__('View Results', 'wp-polls')."</a></p>\n</div>";
			case "poll_template_resultheader":
				return "<p style=\"text-align: center;\"><strong>%POLL_QUESTION%</strong></p>\n<div id=\"polls-%POLL_ID%-ans\" class=\"wp-polls-ans\">\n<ul class=\"wp-polls-ul\">";
			case "poll_template_resultbody":
				return "<li>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%".__(',', 'wp-polls')." %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")</small><div class=\"pollbar wp-polls-tpl-$templates_set_id\" style=\"width: %POLL_ANSWER_IMAGEWIDTH%%;\" title=\"%POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")\"></div></li>";
			case "poll_template_resultbody2":
				return "<li><strong><i>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%".__(',', 'wp-polls')." %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")</small></i></strong><div class=\"pollbar wp-polls-tpl-$templates_set_id\" style=\"width: %POLL_ANSWER_IMAGEWIDTH%%;\" title=\"".__('You Have Voted For This Choice', 'wp-polls')." - %POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% ".__('Votes', 'wp-polls').")\"></div></li>";
			case "poll_template_resultfooter":
				return "</ul>\n<p style=\"text-align: center;\">".__('Total Voters', 'wp-polls').": <strong>%POLL_TOTALVOTERS%</strong></p>\n</div>";
			case "poll_template_resultfooter2":
				return "</ul>\n<p style=\"text-align: center;\">".__('Total Voters', 'wp-polls').": <strong>%POLL_TOTALVOTERS%</strong></p>\n<p style=\"text-align: center;\"><a href=\"#VotePoll\" onclick=\"poll_booth(%POLL_ID%); return false;\" title=\"".__('Vote For This Poll', 'wp-polls')."\">".__('Vote', 'wp-polls')."</a></p>\n</div>";
			case "poll_template_pollarchivelink":
				return "<ul>\n<li><a href=\"%POLL_ARCHIVE_URL%\">".__('Polls Archive', 'wp-polls')."</a></li>\n</ul>";
			case "poll_template_pollarchiveheader":
				return "";
			case "poll_template_pollarchivefooter":
				return "<p>".__('Start Date:', 'wp-polls')." %POLL_START_DATE%<br />".__('End Date:', 'wp-polls')." %POLL_END_DATE%</p>";
			case "poll_template_pollarchivepagingheader":
				return "";
			case "poll_template_pollarchivepagingfooter":
				return "";
			case "poll_template_disable":
				return __('Sorry, there are no polls available at the moment.', 'wp-polls');
			case "poll_template_error":
				return __('An error has occurred when processing your poll.', 'wp-polls');
			case "poll_template_aftervote":
				return "<div class=\"wp-polls-aftervote-msg\"><em>".__('Thank you, your vote for question', 'wp-polls').' <strong>%POLL_QUESTION%</strong> '.__('has been saved:', 'wp-polls')."</em><br /><em>%POLL_USER_VOTED_ANSWERS_LIST%</em></div>";		
			case "poll_bar_style":
				return "default";				
			case "poll_bar_background":
				return "d8e1eb";				
			case "poll_bar_border":
				return "c8c8c8";				
			case "poll_bar_height":
				return "8";				
			case "poll_ajax_style_loading":
				return "1";				
			case "poll_ajax_style_fading":
				return "1";				
			case "poll_ans_sortby":
				return "polla_aid";				
			case "poll_ans_sortorder":
				return "asc";				
			case "poll_ans_result_sortby":
				return "polla_votes";				
			case "poll_ans_result_sortorder":
				return "desc";				
			case "poll_allowtovote":
				return "2";
			case "poll_aftervote":
				return "1";									
			case "poll_logging_method":
				return "3";				
			case "poll_cookielog_expiry":
				return "0";				
			case "poll_ip_header":
				return "";				
			case "poll_archive_perpage":
				return "5";				
			case "poll_archive_displaypoll":
				return "2";				
			case "poll_archive_url":
				return "";				
			case "poll_currentpoll":
				return "0";				
			case "poll_close":
				return "3";				
			case "poll_latestpoll":
				return "0";				
			default:
				return __('Error: string could not be retrieved', 'wp-polls');
		}
	}
}

### Function: Insert Default templates into DB
function wp_polls_insert_default_templates_set($answers_type) {
	global $wpdb;
	$data = array();
	$keys =	array(
					'polltpl_set_name',
					'polltpl_answers_type',
					'polltpl_template_voteheader',
					'polltpl_template_votebody',
					'polltpl_template_votefooter',
					'polltpl_template_resultheader',
					'polltpl_template_resultbody',
					'polltpl_template_resultbody2',
					'polltpl_template_resultfooter', 
					'polltpl_template_resultfooter2', 
					'polltpl_template_pollarchivelink',
					'polltpl_template_pollarchiveheader',
					'polltpl_template_pollarchivefooter',
					'polltpl_template_pollarchivepagingheader',
					'polltpl_template_pollarchivepagingfooter',
					'polltpl_template_disable',
					'polltpl_template_error', 
					'polltpl_template_aftervote', 
					'polltpl_bar_style',
					'polltpl_bar_background',
					'polltpl_bar_border',
					'polltpl_bar_height',
					'polltpl_ajax_style_loading',
					'polltpl_ajax_style_fading',
					'polltpl_ans_sortby',
					'polltpl_ans_sortorder',
					'polltpl_ans_result_sortby',
					'polltpl_ans_result_sortorder',
					'polltpl_allowtovote',
					'polltpl_aftervote',
					'polltpl_logging_method',
					'polltpl_cookielog_expiry',
					'polltpl_ip_header',
					'polltpl_archive_perpage',
					'polltpl_archive_displaypoll',
					'polltpl_archive_url',
					'polltpl_currentpoll',
					'polltpl_close',					
					'polltpl_latestpoll',					
				);
	$format = array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%d',
					'%s',								
					'%s',								
					'%s',								
					'%s',													
					'%d',													
					'%d',													
					'%d',													
					'%d',													
					'%s',	
					'%d',	
					'%d',	
					'%s',	
					'%d',	
					'%d',	
					'%d',	
				);
	foreach($keys as $key){
		$template_key = wp_polls_replace_key_prefix($key, 'poll');
		$data[$key] = wp_polls_get_default_template_string($template_key, $answers_type);
	}
	$insert_templates_set = $wpdb->insert($wpdb->pollstpl, $data, $format);
	$inserted_templates_set_id = $wpdb->insert_id;
	
	//update templates string which requires templates_set_id with the newly created ID
	$update_val_requiring_template_id = $wpdb->update( $wpdb->pollstpl, 
														array(
																'polltpl_template_resultbody' => wp_polls_get_default_template_string('poll_template_resultbody', $answers_type, $inserted_templates_set_id),
																'polltpl_template_resultbody2' => wp_polls_get_default_template_string('poll_template_resultbody2', $answers_type, $inserted_templates_set_id),
														), 
														array('polltpl_id' => $inserted_templates_set_id), 
														array('%s', '%s'), 
														array('%d') 
												);
	
	//make sure a default template set is designated for the given answer type 
	if (empty(wp_polls_get_child_option('default_templates_set_'.$answers_type))){ 
		wp_polls_add_or_update_child_option('default_templates_set_'.$answers_type, $inserted_templates_set_id);
		$set_polls_to_default_templates_set = $wpdb->update( $wpdb->pollsq, array('pollq_tplid' => $inserted_templates_set_id), array('pollq_expected_atype' => $answers_type), array('%d'), array('%s') );
	}
	return ($insert_templates_set) ? $inserted_templates_set_id : false;
}

### Helper Function to retrieve the templates set ID associated to a poll 
function wp_polls_get_poll_templates_set_id($poll_id) {
	global $wpdb;
	$poll_id = (int) $poll_id;
	$templates_set_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_tplid FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id) );
	return $templates_set_id;
}

### Helper function to retrieve the setting associated to a templates set for a given key 
function wp_polls_get_templates_set_setting($db_key, $templates_set_id) {
	global $wpdb;
	$db_key = sanitize_key($db_key);
	$templates_set_id = (int) $templates_set_id;
	$db_setting = $wpdb->get_var( $wpdb->prepare ( "SELECT %i FROM $wpdb->pollstpl WHERE polltpl_id = %d", $db_key, $templates_set_id ) );       
	return $db_setting;
}

### Helper function to check whether a dynamic class (format "my-class-XYZ", where XYZ are the number that should match the current_ID) is actually used with the current ID, and display a warning if not. 
function wp_polls_class_mismatch_warnings($string_to_check, $current_ID) {
	$output = '';
	$class_list = array(
							'wp-polls-tpl',
						);
	$class_list = apply_filters('wp_polls_check_class_mismatch_list', $class_list); //for cusomization of the class list to be checked (string format for each class must be "my-class" since the "-XYZ" portion is managed automatically)
	foreach ($class_list as $class){
		$regex_match = preg_match_all("/$class-\d+/", $string_to_check, $matches);
		foreach ($matches[0] as $match){
			$match_ID = (int) str_replace("$class-", '', $match);
			if ($match_ID != $current_ID){
				$output .= '<p style="color: blue">'.sprintf(__('CSS class mismatch detected: <code>%s</code> used instead of <code>%s</code> (ignore if done on purpose).', 'wp-polls'), removeslashes($match), "$class-$current_ID").'</p>';
			}
		} 
	}
	return $output;
}

### Helper function to echo warning regarding class mismatch 
function wp_polls_display_class_mismatch_warnings_if_any($string_to_check, $current_ID) {
	$warning = wp_polls_class_mismatch_warnings($string_to_check, $current_ID);
	if (!empty($warning)) echo '<div class="wp-polls-warning fade">' . $warning . '</div>';
}

### Helper function to apply templates variables to after_vote_message_template
function wp_polls_apply_variables_to_after_vote_message_template($poll_question_text, $poll_type, $poll_aftervote_template, $poll_answers_ids_list, $poll_id, $templates_set_id) {
	global $wpdb;

	list($order_by, $sort_order) = _polls_get_ans_sort($templates_set_id);
	$poll_answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers FROM $wpdb->pollsa WHERE polla_aid IN (%1s) ORDER BY %s %s", $poll_answers_ids_list, $order_by, $sort_order ) );
	//for object answers, replace answer ID by corresponding text 
	foreach ($poll_answers as $poll_answer) { 
		$poll_answer->polla_answers = ($poll_type == 'object') ? get_the_title($poll_answer->polla_answers) : $poll_answer->polla_answers;
	}
	//for object answers it must be sorted again per answers' text instead of per answer's ID
	if ($poll_type == 'object') usort( $poll_answers, function($a, $b) {return strnatcasecmp($a->polla_answers, $b->polla_answers);} ); //cf. https://stackoverflow.com/questions/4282413/sort-array-of-objects-by-one-property
	//generate lists markup
	$poll_answers_ids_list_ordered = "<ul>";
	$poll_answers_content_list_ordered = "<ul>";
	foreach ($poll_answers as $poll_answer) {
		$poll_answers_ids_list_ordered .= '<li>'. $poll_answer->polla_aid .'</li>';
		$poll_answers_content_list_ordered .= '<li>'. $poll_answer->polla_answers .'</li>';
	}
	$poll_answers_ids_list_ordered .= "</ul>";
	$poll_answers_content_list_ordered .= "</ul>";
	//populate template variables
	$template_variables = array(
		'%POLL_QUESTION%' => $poll_question_text,
		'%POLL_ID%' => $poll_id,
		'%POLL_USER_VOTED_ANSWERS_ID_LIST%' => $poll_answers_ids_list_ordered,
		'%POLL_USER_VOTED_ANSWERS_LIST%' => $poll_answers_content_list_ordered,				
	);
	//apply template variables
	$poll_aftervote_template_variables = apply_filters( 'wp_polls_template_aftervote_variables', $template_variables );
	$poll_aftervote_template  = apply_filters( 'wp_polls_template_aftervote_markup', $poll_aftervote_template, '', $poll_aftervote_template_variables );
	
	return $poll_aftervote_template;
}

### Class: WP-Polls Widget
 class WP_Widget_Polls extends WP_Widget {
	// Constructor
	public function __construct() {
		$widget_ops = array('description' => __('WP-Polls polls', 'wp-polls'));
		parent::__construct('polls-widget', __('Polls', 'wp-polls'), $widget_ops);
	}

	// Display Widget
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', esc_attr( $instance['title'] ) );
		$poll_id = (int) $instance['poll_id'];
		$display_pollarchive = (int) $instance['display_pollarchive'];
		echo $args['before_widget'];
		if( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		get_poll( $poll_id );
		if( $display_pollarchive ) {
			display_polls_archive_link();
		}
		echo $args['after_widget'];
	}

	// When Widget Control Form Is Posted
	public function update($new_instance, $old_instance) {
		if (!isset($new_instance['submit'])) {
			return false;
		}
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['poll_id'] = (int) $new_instance['poll_id'];
		$instance['display_pollarchive'] = (int) $new_instance['display_pollarchive'];
		return $instance;
	}

	// DIsplay Widget Control Form
	public function form($instance) {
		global $wpdb;
		$instance = wp_parse_args((array) $instance, array('title' => __('Polls', 'wp-polls'), 'poll_id' => 0, 'display_pollarchive' => 1));
		$title = esc_attr($instance['title']);
		$poll_id = (int) $instance['poll_id'];
		$display_pollarchive = (int) $instance['display_pollarchive'];
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-polls'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('display_pollarchive'); ?>"><?php _e('Display Polls Archive Link Below Poll?', 'wp-polls'); ?>
				<select name="<?php echo $this->get_field_name('display_pollarchive'); ?>" id="<?php echo $this->get_field_id('display_pollarchive'); ?>" class="widefat">
					<option value="0"<?php selected(0, $display_pollarchive); ?>><?php _e('No', 'wp-polls'); ?></option>
					<option value="1"<?php selected(1, $display_pollarchive); ?>><?php _e('Yes', 'wp-polls'); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('poll_id'); ?>"><?php _e('Poll To Display:', 'wp-polls'); ?>
				<select name="<?php echo $this->get_field_name('poll_id'); ?>" id="<?php echo $this->get_field_id('poll_id'); ?>" class="widefat">
					<option value="-1"<?php selected(-1, $poll_id); ?>><?php _e('Do NOT Display Poll (Disable)', 'wp-polls'); ?></option>
					<option value="-2"<?php selected(-2, $poll_id); ?>><?php _e('Display Random Poll', 'wp-polls'); ?></option>
					<option value="0"<?php selected(0, $poll_id); ?>><?php _e('Display Latest Poll', 'wp-polls'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<?php
					$polls = $wpdb->get_results("SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC");
					if($polls) {
						foreach($polls as $poll) {
							$pollq_question = wp_kses_post( removeslashes( $poll->pollq_question ) );
							$pollq_id = (int) $poll->pollq_id;
							if($pollq_id === $poll_id) {
								echo "<option value=\"$pollq_id\" selected=\"selected\">$pollq_question</option>\n";
							} else {
								echo "<option value=\"$pollq_id\">$pollq_question</option>\n";
							}
						}
					}
					?>
				</select>
			</label>
		</p>
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
	}
}


### Function: Init WP-Polls Widget
add_action('widgets_init', 'widget_polls_init');
function widget_polls_init() {
	polls_textdomain();
	register_widget('WP_Widget_Polls');
}


### Function: Activate Plugin
register_activation_hook( __FILE__, 'polls_activation' );
function polls_activation( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$ms_sites = wp_get_sites();

		if( 0 < count( $ms_sites ) ) {
			foreach ( $ms_sites as $ms_site ) {
				switch_to_blog( $ms_site['blog_id'] );
				polls_activate();
				restore_current_blog();
			}
		}
	} else {
		polls_activate();
	}
}

function polls_activate() {
	global $wpdb;

	if(@is_file(ABSPATH.'/wp-admin/includes/upgrade.php')) {
		include_once(ABSPATH.'/wp-admin/includes/upgrade.php');
	} elseif(@is_file(ABSPATH.'/wp-admin/upgrade-functions.php')) {
		include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	} else {
		die('We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'');
	}

	// Create Poll Tables (3 Tables)
	$charset_collate = $wpdb->get_charset_collate();

	$create_table = array();
	$create_table['pollsq'] = "CREATE TABLE $wpdb->pollsq (".
							  "pollq_id int(10) NOT NULL auto_increment," .
							  "pollq_tplid int(10) NOT NULL default '1'," .
							  "pollq_question varchar(200) character set utf8 NOT NULL default ''," .
							  "pollq_expected_atype varchar(10) NOT NULL default 'text'," .
							  "pollq_timestamp varchar(20) NOT NULL default ''," .
							  "pollq_totalvotes int(10) NOT NULL default '0'," .
							  "pollq_active tinyint(1) NOT NULL default '1'," .
							  "pollq_expiry int(10) NOT NULL default '0'," .
							  "pollq_multiple tinyint(3) NOT NULL default '0'," .
							  "pollq_totalvoters int(10) NOT NULL default '0'," .
							  "PRIMARY KEY  (pollq_id)" .
							  ") $charset_collate;";
	$create_table['pollstpl'] = "CREATE TABLE $wpdb->pollstpl (".
							  "polltpl_id int(10) NOT NULL auto_increment," .
							  "polltpl_set_name varchar(150) NOT NULL default ''," .
							  "polltpl_answers_type varchar(10) NOT NULL default ''," .
							  "polltpl_template_voteheader text NOT NULL default ''," .
							  "polltpl_template_votebody text NOT NULL default ''," .
							  "polltpl_template_votefooter text NOT NULL default ''," .
							  "polltpl_template_resultheader text NOT NULL default ''," .
							  "polltpl_template_resultbody text NOT NULL default ''," .
							  "polltpl_template_resultbody2 text NOT NULL default ''," .
							  "polltpl_template_resultfooter text NOT NULL default ''," .
							  "polltpl_template_resultfooter2 text NOT NULL default ''," .
							  "polltpl_template_pollarchivelink text NOT NULL default ''," .
							  "polltpl_template_pollarchiveheader text NOT NULL default ''," .
							  "polltpl_template_pollarchivefooter text NOT NULL default ''," .
							  "polltpl_template_pollarchivepagingheader text NOT NULL default ''," .
							  "polltpl_template_pollarchivepagingfooter text NOT NULL default ''," .
							  "polltpl_template_disable varchar(500) NOT NULL default ''," .
							  "polltpl_template_error varchar(500) NOT NULL default ''," .
							  "polltpl_template_aftervote text NOT NULL default ''," .
							  "polltpl_bar_style varchar(20) NOT NULL default ''," .
							  "polltpl_bar_background varchar(6) NOT NULL default ''," .
							  "polltpl_bar_border varchar(6) NOT NULL default ''," .
							  "polltpl_bar_height tinyint(4) NOT NULL default '8'," .
							  "polltpl_ajax_style_loading tinyint(1) NOT NULL default '1'," .
							  "polltpl_ajax_style_fading tinyint(1) NOT NULL default '1'," .
							  "polltpl_ans_sortby varchar(20) NOT NULL default ''," .
							  "polltpl_ans_sortorder varchar(4) NOT NULL default ''," .
							  "polltpl_ans_result_sortby varchar(20) NOT NULL default ''," .
							  "polltpl_ans_result_sortorder varchar(4) NOT NULL default ''," .
							  "polltpl_allowtovote tinyint(1) NOT NULL default '2'," .
							  "polltpl_aftervote tinyint(1) NOT NULL default '1'," .
							  "polltpl_logging_method tinyint(1) NOT NULL default '3'," .
							  "polltpl_cookielog_expiry tinyint(10) NOT NULL default '0'," .
							  "polltpl_ip_header varchar(100) NOT NULL default ''," .
							  "polltpl_archive_perpage tinyint(5) NOT NULL default '5'," .
							  "polltpl_archive_displaypoll tinyint(1) NOT NULL default '2'," .
							  "polltpl_archive_url varchar(300) NOT NULL default ''," .
							  "polltpl_currentpoll tinyint(2) NOT NULL default '0'," .
							  "polltpl_close tinyint(1) NOT NULL default '3'," .
							  "polltpl_latestpoll tinyint(10) NOT NULL default '0'," .
							  "PRIMARY KEY  (polltpl_id)" .	
							  ") $charset_collate;";
	$create_table['pollsa'] = "CREATE TABLE $wpdb->pollsa (" .
							  "polla_aid int(10) NOT NULL auto_increment," .
							  "polla_qid int(10) NOT NULL default '0'," .
							  "polla_answers TEXT character set utf8 NOT NULL default ''," .
							  "polla_atype varchar(10) NOT NULL default 'text'," .
							  "polla_votes int(10) NOT NULL default '0'," .
							  "PRIMARY KEY  (polla_aid)" .
							  ") $charset_collate;";
	$create_table['pollsaof'] = "CREATE TABLE $wpdb->pollsaof (" .
							  "pollaof_aofid int(10) NOT NULL auto_increment," .
							  "pollaof_tplid int(10) NOT NULL default '0'," .
							  "pollaof_optype varchar(300) NOT NULL default ''," .
							  "pollaof_obj_field varchar(300) NOT NULL default ''," .
							  "PRIMARY KEY  (pollaof_aofid)" .
							  ") $charset_collate;";
	$create_table['pollsip'] = "CREATE TABLE $wpdb->pollsip (" .
							   "pollip_id int(10) NOT NULL auto_increment," .
							   "pollip_qid int(10) NOT NULL default '0'," .
							   "pollip_aid int(10) NOT NULL default '0'," .
							   "pollip_ip varchar(100) NOT NULL default ''," .
							   "pollip_host varchar(200) NOT NULL default ''," .
							   "pollip_timestamp int(10) NOT NULL default '0'," .
							   "pollip_user tinytext NOT NULL," .
							   "pollip_userid int(10) NOT NULL default '0'," .
							   "PRIMARY KEY  (pollip_id)," .
							   "KEY pollip_ip (pollip_ip)," .
							   "KEY pollip_qid (pollip_qid)," .
							   "KEY pollip_ip_qid (pollip_ip, pollip_qid)" .
							   ") $charset_collate;";
	dbDelta( $create_table['pollsq'] );
	dbDelta( $create_table['pollstpl'] );
	dbDelta( $create_table['pollsa'] );
	dbDelta( $create_table['pollsaof'] );
	dbDelta( $create_table['pollsip'] );
	// Check Whether It is Install Or Upgrade
	$first_poll = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq LIMIT 1" );
	// If Install, Insert 1st Poll Question With 5 Poll Answers
	if ( empty( $first_poll ) ) {
		$first_template = $wpdb->get_var( "SELECT polltpl_id FROM $wpdb->pollstpl LIMIT 1" );
		if ( empty( $first_template ) ) { 
			//Install default polls templates sets
			wp_polls_insert_default_templates_set('text');
			wp_polls_insert_default_templates_set('object');
		}
		// Insert Poll Question (1 Record)
		$insert_pollq = $wpdb->insert( $wpdb->pollsq, array( 'pollq_question' => __( 'How Is My Site?', 'wp-polls' ), 'pollq_timestamp' => current_time( 'timestamp' ) ), array( '%s', '%s' ) );
		if ( $insert_pollq ) {
			$set_latest_poll_id = wp_polls_update_latest_id( wp_polls_get_child_option('default_templates_set_text') );

			// Insert Poll Answers  (5 Records)
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Good', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Excellent', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Bad', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'Can Be Improved', 'wp-polls' ) ), array( '%d', '%s' ) );
			$wpdb->insert( $wpdb->pollsa, array( 'polla_qid' => $insert_pollq, 'polla_answers' => __( 'No Comments', 'wp-polls' ) ), array( '%d', '%s' ) );
		}
		//Add new child options (install case only)
		wp_polls_add_or_update_child_option('obj_answers_selection_posts_per_page', 15); //introduced in WP-Polls 3.00.0
	}
	// Add In Options (16 Records)
	add_option('poll_template_voteheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_votebody', '<li><input type="%POLL_CHECKBOX_RADIO%" id="poll-answer-%POLL_ANSWER_ID%" name="poll_%POLL_ID%" value="%POLL_ANSWER_ID%" /> <label for="poll-answer-%POLL_ANSWER_ID%">%POLL_ANSWER%</label></li>');
	add_option('poll_template_votefooter', '</ul>'.
	'<p style="text-align: center;"><input type="button" name="vote" value="   '.__('Vote', 'wp-polls').'   " class="Buttons" onclick="poll_vote(%POLL_ID%);" /></p>'.
	'<p style="text-align: center;"><a href="#ViewPollResults" onclick="poll_result(%POLL_ID%); return false;" title="'.__('View Results Of This Poll', 'wp-polls').'">'.__('View Results', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_resultheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_resultbody', '<li>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="%POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultbody2', '<li><strong><i>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small></i></strong><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="'.__('You Have Voted For This Choice', 'wp-polls').' - %POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultfooter', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'</div>');
	add_option('poll_template_resultfooter2', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'<p style="text-align: center;"><a href="#VotePoll" onclick="poll_booth(%POLL_ID%); return false;" title="'.__('Vote For This Poll', 'wp-polls').'">'.__('Vote', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_disable', __('Sorry, there are no polls available at the moment.', 'wp-polls'));
	add_option('poll_template_error', __('An error has occurred when processing your poll.', 'wp-polls'));
	add_option('poll_currentpoll', 0);
	add_option('poll_latestpoll', 1);
	add_option('poll_archive_perpage', 5);
	add_option('poll_ans_sortby', 'polla_aid');
	add_option('poll_ans_sortorder', 'asc');
	add_option('poll_ans_result_sortby', 'polla_votes');
	add_option('poll_ans_result_sortorder', 'desc');
	// Database Upgrade For WP-Polls 2.1
	add_option('poll_logging_method', '3');
	add_option('poll_allowtovote', '2');
	// Database Upgrade For WP-Polls 2.12
	add_option('poll_archive_url', site_url('pollsarchive'));
	// Database Upgrade For WP-Polls 2.13
	add_option('poll_bar', array('style' => 'default', 'background' => 'd8e1eb', 'border' => 'c8c8c8', 'height' => 8));
	// Database Upgrade For WP-Polls 2.14
	add_option('poll_close', 1);
	// Database Upgrade For WP-Polls 2.20
	add_option('poll_ajax_style', array('loading' => 1, 'fading' => 1));
	add_option('poll_template_pollarchivelink', '<ul>'.
	'<li><a href="%POLL_ARCHIVE_URL%">'.__('Polls Archive', 'wp-polls').'</a></li>'.
	'</ul>');
	add_option('poll_archive_displaypoll', 2);
	add_option('poll_template_pollarchiveheader', '');
	add_option('poll_template_pollarchivefooter', '<p>'.__('Start Date:', 'wp-polls').' %POLL_START_DATE%<br />'.__('End Date:', 'wp-polls').' %POLL_END_DATE%</p>');

	$pollq_totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
	if ( 0 === $pollq_totalvoters ) {
		$wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvoters = pollq_totalvotes" );
	}

	// Database Upgrade For WP-Polls 2.30
	add_option('poll_cookielog_expiry', 0);
	add_option('poll_template_pollarchivepagingheader', '');
	add_option('poll_template_pollarchivepagingfooter', '');

	// Database Upgrade For WP-Polls 2.50
	delete_option('poll_archive_show');

	// Database Upgrade for WP-Polls 2.76
	add_option( 'poll_options', array( 'ip_header' => '' ) );

	// Index
	$index = $wpdb->get_results( "SHOW INDEX FROM $wpdb->pollsip;" );
	$key_name = array();
	if( count( $index ) > 0 ) {
		foreach( $index as $i ) {
			$key_name[]= $i->Key_name;
		}
	}
	if ( ! in_array( 'pollip_ip', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip (pollip_ip);" );
	}
	if ( ! in_array( 'pollip_qid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_qid (pollip_qid);" );
	}
	if ( ! in_array( 'pollip_ip_qid_aid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip_qid_aid (pollip_ip, pollip_qid, pollip_aid);" );
	}
	// No longer needed index
	if ( in_array( 'pollip_ip_qid', $key_name, true ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip DROP INDEX pollip_ip_qid;" );
	}

	// Change column datatype for wp_pollsip
	$col_pollip_qid = $wpdb->get_row( "DESCRIBE $wpdb->pollsip pollip_qid" );
	if( 'varchar(10)' === $col_pollip_qid->Type ) {
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_qid int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_aid int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsip MODIFY COLUMN pollip_timestamp int(10) NOT NULL default '0';" );
		$wpdb->query( "ALTER TABLE $wpdb->pollsq MODIFY COLUMN pollq_expiry int(10) NOT NULL default '0';" );
	}

	// Database Upgrade From WP-Polls 3.00.0
	$options_to_move_to_pollstpl = array(
											'poll_template_voteheader',
											'poll_template_votebody',
											'poll_template_votefooter',
											'poll_template_resultheader',
											'poll_template_resultbody',
											'poll_template_resultbody2',
											'poll_template_resultfooter',
											'poll_template_resultfooter2',
											'poll_template_pollarchivelink',
											'poll_template_pollarchiveheader',
											'poll_template_pollarchivefooter',
											'poll_template_pollarchivepagingheader',
											'poll_template_pollarchivepagingfooter',
											'poll_template_disable',
											'poll_template_error',
											'poll_bar_style',
											'poll_bar_background',
											'poll_bar_border',
											'poll_bar_height',
											'poll_ajax_style_loading',
											'poll_ajax_style_fading',
											'poll_ans_sortby',
											'poll_ans_sortorder',
											'poll_ans_result_sortby',
											'poll_ans_result_sortorder',
											'poll_allowtovote',											
											'poll_logging_method',
											'poll_cookielog_expiry',
											'poll_ip_header',
											'poll_currentpoll',
											'poll_close',
											'poll_latestpoll',
										);		
	$col_polla_answers = $wpdb->get_row( "DESCRIBE $wpdb->pollsa polla_answers" );
	if( 'varchar(200)' === $col_polla_answers->Type ) { //This IF block should apply only in case of update from a previous version
		// Adapt existing tables
		$wpdb->query( "ALTER TABLE $wpdb->pollsa MODIFY COLUMN polla_answers TEXT character set utf8 NOT NULL default '';" ); //increase allowed storage space as an object serialized array will quickly exceed varchar(200).
		$wpdb->query( "ALTER TABLE $wpdb->pollsa ADD polla_atype varchar(10) NOT NULL default 'text';" ); //add answer type ('text' or 'objects')
		$wpdb->query( "ALTER TABLE $wpdb->pollsq ADD pollq_expected_atype varchar(10) NOT NULL default 'text';" ); //add expected answer type ('text' or 'objects')
		$wpdb->query( "ALTER TABLE $wpdb->pollsq ADD pollq_tplid int(10) NOT NULL default '0';" ); //add templates set id
		// Populate new pollstpl table with existing options
		wp_polls_insert_default_templates_set('text'); //add default text poll templates (ID#1)
		wp_polls_insert_default_templates_set('object'); //add default object poll templates (ID#2)
		$default_text_templates_id = 1; 
		$latest_poll_id = get_option('poll_latestpoll');
		$option_to_copy_to_poll_options = wp_polls_add_or_update_child_option('global_poll_latestpoll', $latest_poll_id);
		$first_custom_template_data = array(
											'polltpl_id'			=> 3,
											'polltpl_set_name'		=> __('Inherited Custom Text Template', 'wp-polls'),
											'polltpl_answers_type'	=> 'text',
										);
		foreach($options_to_move_to_pollstpl as $option_key){
			$db_key = wp_polls_replace_key_prefix($option_key, 'polltpl');
			if( (strpos($option_key, 'poll_bar') !== false) || (strpos($option_key, 'poll_ajax_style') !== false) ) { //parse key to get value in subarray  
				$option_key_arr = explode('_', $option_key);
				$option_child_key = end($option_key_arr);
				array_pop($option_key_arr);
				$option_parent_key = implode('_', $option_key_arr);
				$opts_arr = get_option($option_parent_key);
				$first_custom_template_data[$db_key] = $opts_arr[$option_child_key];
			} elseif( (strpos($option_key, 'poll_ip_header') !== false) ) { //get subarray value from $poll_options
				$first_custom_template_data[$db_key] = wp_polls_get_child_option('ip_header');
			} else {
				$first_custom_template_data[$db_key] = get_option($option_key);
			}
		}
		$create_first_custom_template_from_current_options = $wpdb->insert( //populate pollstpl with custom text templates inherited from existing options (ID#3)
			$wpdb->pollstpl,
			$first_custom_template_data,
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
		if ($create_first_custom_template_from_current_options) {
			$default_text_templates_id = 3;
			wp_polls_add_or_update_child_option('default_templates_set_text', $default_text_templates_id); //set polltpl_id #3 as default text template.
		}
		wp_polls_associate_poll_to_default_templates_set(-1, 'text');
		wp_polls_associate_poll_to_default_templates_set(-1, 'object');
	}
	$delete_bar = false; //from here it applies at each plugin activation (aiming at both new install & upgrades) as it modifies the options' structure created above at previous upgrades which is itself reapplied at every plugin activation.
	$delete_style = false;
	foreach ($options_to_move_to_pollstpl as $option_key){ //remove options created with previous updates now that they live in the DB in the table 'pollstpl'. New options live as child options of the key 'poll_options'.
		if( (strpos($option_key, 'poll_bar') !== false) && !$delete_bar ) { 
			delete_option('poll_bar');
			$delete_bar = true;
		} elseif( (strpos($option_key, 'poll_ajax_style') !== false) && !$delete_style ) { 
			delete_option('poll_ajax_style');
			$delete_style = true;				
		} elseif( (strpos($option_key, 'poll_ip_header') !== false) ) {
			wp_polls_delete_child_option('ip_header');
		} else {
			delete_option($option_key);
		}	
	}	
	// Populate the 'general options' table (actually subkeys of 'poll_options' key in 'wp_options' table)  
	$options_to_move_to_poll_options = array(
												'poll_archive_perpage',
												'poll_archive_displaypoll',
												'poll_archive_url',
												'widget_polls',
												'widget_polls-widget',
											);
	foreach ($options_to_move_to_poll_options as $option_key){
		$option_value = get_option($option_key);
		if( (strpos($option_key, 'poll_archive') !== false)) { 
			$option_key = 'global_'.$option_key;
		}	
		$add_option = wp_polls_add_or_update_child_option($option_key, $option_value);
		if ($add_option) delete_option($option_key);
	} 
	$add_new_option = wp_polls_add_or_update_child_option('obj_answers_selection_posts_per_page', 15);
	//END - Database Upgrade From WP-Polls 3.00.0

	// Set 'manage_polls' Capabilities To Administrator
	$role = get_role( 'administrator' );
	if( ! $role->has_cap( 'manage_polls' ) ) {
		$role->add_cap( 'manage_polls' );
	}
	cron_polls_place();
}
