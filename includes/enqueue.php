<?php
/**
 * WP-Polls Enqueue Scripts and Styles
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

### Function: Enqueue Polls JavaScripts/CSS
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
	$pollbar = get_option( 'poll_bar' );
	if( $pollbar['style'] === 'use_css' ) {
		$pollbar_css = '.wp-polls .pollbar {'."\n";
		$pollbar_css .= "\t".'margin: 1px;'."\n";
		$pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
		$pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
		$pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
		$pollbar_css .= "\t".'background: #'.$pollbar['background'].';'."\n";
		$pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
		$pollbar_css .= '}'."\n";
	} else {
		$pollbar_css = '.wp-polls .pollbar {'."\n";
		$pollbar_css .= "\t".'margin: 1px;'."\n";
		$pollbar_css .= "\t".'font-size: '.($pollbar['height']-2).'px;'."\n";
		$pollbar_css .= "\t".'line-height: '.$pollbar['height'].'px;'."\n";
		$pollbar_css .= "\t".'height: '.$pollbar['height'].'px;'."\n";
		$pollbar_css .= "\t".'background-image: url(\''.plugins_url('wp-polls/images/'.$pollbar['style'].'/pollbg.gif').'\');'."\n";
		$pollbar_css .= "\t".'border: 1px solid #'.$pollbar['border'].';'."\n";
		$pollbar_css .= '}'."\n";
	}
	wp_add_inline_style( 'wp-polls', $pollbar_css );
	$poll_ajax_style = get_option('poll_ajax_style');
	wp_enqueue_script('wp-polls', plugins_url('wp-polls/polls-js.js'), array('jquery'), WP_POLLS_VERSION, true);
	wp_localize_script('wp-polls', 'pollsL10n', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'text_wait' => __('Your last request is still being processed. Please wait a while ...', 'wp-polls'),
		'text_valid' => __('Please choose a valid poll answer.', 'wp-polls'),
		'text_multiple' => __('Maximum number of choices allowed: ', 'wp-polls'),
		'show_loading' => (int) $poll_ajax_style['loading'],
		'show_fading' => (int) $poll_ajax_style['fading']
	));
}


### Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin
add_action('admin_enqueue_scripts', 'poll_scripts_admin');
function poll_scripts_admin($hook_suffix) {
	$poll_admin_pages = array('wp-polls/polls-manager.php', 'wp-polls/polls-add.php', 'wp-polls/polls-options.php', 'wp-polls/polls-templates.php', 'wp-polls/polls-uninstall.php');
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
			'text_open_poll' => __('Open Poll', 'wp-polls'),
			'text_close_poll' => __('Close Poll', 'wp-polls'),
			'text_answer' => __('Answer', 'wp-polls'),
			'text_remove_poll_answer' => __('Remove', 'wp-polls')
		));
	}
}
