<?php
/**
 * Plugin Name: WP-Polls
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-polls
 * Domain Path: /languages
 *
 * @package WP-Polls
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

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


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Version
define( 'WP_POLLS_VERSION', '3.0.0' );
define( 'WP_POLLS_MAIN_FILE', __FILE__ );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once __DIR__ . '/includes/class-polls-templates.php';
require_once __DIR__ . '/includes/class-polls-options.php';
require_once __DIR__ . '/includes/class-polls-settings.php';
require_once __DIR__ . '/includes/class-polls-widget.php';
require_once __DIR__ . '/includes/class-polls-install.php';
Polls_Install::init();
Polls_Settings::init();


// Create Text Domain For Translations
add_action( 'plugins_loaded', 'polls_textdomain' );
function polls_textdomain() {
	load_plugin_textdomain( 'wp-polls' );
}


// Polls Table Name
// Registering the names in $wpdb->tables is what makes them survive
// switch_to_blog(): wpdb::set_blog_id() rebuilds every registered table name
// against the new prefix. A bare assignment keeps pointing at the site that
// happened to be current when this file loaded.
global $wpdb;
foreach ( array( 'pollsq', 'pollsa', 'pollsip' ) as $poll_table ) {
	if ( ! in_array( $poll_table, $wpdb->tables, true ) ) {
		$wpdb->tables[] = $poll_table;
	}
	$wpdb->$poll_table = $wpdb->prefix . $poll_table;
}
unset( $poll_table );


// Function: Poll Administration Menu
add_action( 'admin_menu', 'poll_menu' );
function poll_menu() {
	add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php', '', 'dashicons-chart-bar' );

	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Manage Polls', 'wp-polls' ), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Add Poll', 'wp-polls' ), __( 'Add Poll', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-add.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Options', 'wp-polls' ), __( 'Poll Options', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-options.php' );
	add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Templates', 'wp-polls' ), __( 'Poll Templates', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-templates.php' );
}


// Function: Get Poll
function get_poll( $temp_poll_id = 0, $display = true ) {
	global $wpdb, $polls_loaded;
	// Poll Result Link
	if ( isset( $_GET['pollresult'] ) ) {
		$pollresult_id = (int) $_GET['pollresult'];
	} else {
		$pollresult_id = 0;
	}
	$temp_poll_id = (int) $temp_poll_id;
	// Check Whether Poll Is Disabled
	if ( (int) Polls_Options::get( 'current_poll' ) === -1 ) {
		if ( $display ) {
			echo removeslashes( Polls_Options::get( 'templates.disable' ) );
			return '';
		}

		return removeslashes( Polls_Options::get( 'templates.disable' ) );
		// Poll Is Enabled
	} else {
		do_action( 'wp_polls_get_poll' );
		// Hardcoded Poll ID Is Not Specified
		switch ( $temp_poll_id ) {
			// Random Poll
			case -2:
				$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1" );
				break;
			// Latest Poll
			case 0:
				// Random Poll
				if ( (int) Polls_Options::get( 'current_poll' ) === -2 ) {
					$random_poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1" );
					$poll_id        = (int) $random_poll_id;
					if ( $pollresult_id > 0 ) {
						$poll_id = $pollresult_id;
					} elseif ( (int) $_POST['poll_id'] > 0 ) {
						$poll_id = (int) $_POST['poll_id'];
					}
					// Current Poll ID Is Not Specified
				} elseif ( (int) Polls_Options::get( 'current_poll' ) === 0 ) {
					// Get Lastest Poll ID
					$poll_id = (int) Polls_Options::get( 'latest_poll' );
				} else {
					// Get Current Poll ID
					$poll_id = (int) Polls_Options::get( 'current_poll' );
				}
				break;
			// Take Poll ID From Arguments
			default:
				$poll_id = $temp_poll_id;
		}
	}

	// Assign All Loaded Poll To $polls_loaded
	if ( empty( $polls_loaded ) ) {
		$polls_loaded = array();
	}
	if ( ! in_array( $poll_id, $polls_loaded, true ) ) {
		$polls_loaded[] = $poll_id;
	}

	// User Click on View Results Link
	if ( $pollresult_id === $poll_id ) {
		if ( $display ) {
			echo display_pollresult( $poll_id );
		} else {
			return display_pollresult( $poll_id );
		}
		// Check Whether User Has Voted
	} else {
		$poll_active = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_active FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
		$poll_active = (int) $poll_active;
		$check_voted = check_voted( $poll_id );
		$poll_close  = 0;
		if ( $poll_active === 0 ) {
			$poll_close = (int) Polls_Options::get( 'close' );
		}
		if ( $poll_close === 2 ) {
			if ( $display ) {
				echo '';
			} else {
				return '';
			}
		}
		if ( $poll_close === 1 || (int) $check_voted > 0 || ( is_array( $check_voted ) && count( $check_voted ) > 0 ) ) {
			if ( $display ) {
				echo display_pollresult( $poll_id, $check_voted );
			} else {
				return display_pollresult( $poll_id, $check_voted );
			}
		} elseif ( $poll_close === 3 || ! check_allowtovote() ) {
			$disable_poll_form = '#polls_form_' . (int) $poll_id;
			$disable_poll_js   = '<script type="text/javascript">document.querySelectorAll(\'' . $disable_poll_form . ' input, ' . $disable_poll_form . ' select, ' . $disable_poll_form . ' textarea, ' . $disable_poll_form . ' button\').forEach(function (field) { field.disabled = true; });</script>';
			if ( $display ) {
				echo display_pollvote( $poll_id ) . $disable_poll_js;
			} else {
				return display_pollvote( $poll_id ) . $disable_poll_js;
			}
		} elseif ( $poll_active === 1 ) {
			if ( $display ) {
				echo display_pollvote( $poll_id );
			} else {
				return display_pollvote( $poll_id );
			}
		}
	}
}


// Function: Enqueue Polls JavaScripts/CSS
add_action( 'wp_enqueue_scripts', 'poll_scripts' );
function poll_scripts() {
	if ( @file_exists( get_stylesheet_directory() . '/polls-css.css' ) ) {
		wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/polls-css.css', false, WP_POLLS_VERSION, 'all' );
	} else {
		wp_enqueue_style( 'wp-polls', plugins_url( 'wp-polls/polls-css.css' ), false, WP_POLLS_VERSION, 'all' );
	}
	if ( is_rtl() ) {
		if ( @file_exists( get_stylesheet_directory() . '/polls-css-rtl.css' ) ) {
			wp_enqueue_style( 'wp-polls-rtl', get_stylesheet_directory_uri() . '/polls-css-rtl.css', false, WP_POLLS_VERSION, 'all' );
		} else {
			wp_enqueue_style( 'wp-polls-rtl', plugins_url( 'wp-polls/polls-css-rtl.css' ), false, WP_POLLS_VERSION, 'all' );
		}
	}
	$pollbar = Polls_Options::get( 'bar' );
	// This lands in an inline <style> block on every front end page, so never
	// trust the stored values even though only 'manage_polls' can set them.
	$pollbar_height     = (int) $pollbar['height'];
	$pollbar_background = _polls_sanitize_hex_color( $pollbar['background'] );
	$pollbar_border     = _polls_sanitize_hex_color( $pollbar['border'] );
	if ( $pollbar['style'] === 'use_css' ) {
		$pollbar_css  = '.wp-polls .pollbar {' . "\n";
		$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
		$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
		$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'background: #' . $pollbar_background . ';' . "\n";
		$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
		$pollbar_css .= '}' . "\n";
	} else {
		$pollbar_css  = '.wp-polls .pollbar {' . "\n";
		$pollbar_css .= "\t" . 'margin: 1px;' . "\n";
		$pollbar_css .= "\t" . 'font-size: ' . ( $pollbar_height - 2 ) . 'px;' . "\n";
		$pollbar_css .= "\t" . 'line-height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'height: ' . $pollbar_height . 'px;' . "\n";
		$pollbar_css .= "\t" . 'background-image: url(\'' . esc_url( plugins_url( 'wp-polls/images/' . $pollbar['style'] . '/pollbg.gif' ) ) . '\');' . "\n";
		$pollbar_css .= "\t" . 'border: 1px solid #' . $pollbar_border . ';' . "\n";
		$pollbar_css .= '}' . "\n";
	}
	wp_add_inline_style( 'wp-polls', $pollbar_css );
	$poll_ajax_style = Polls_Options::get( 'ajax' );
	wp_enqueue_script( 'wp-polls', plugins_url( 'wp-polls/polls-js.js' ), array(), WP_POLLS_VERSION, true );
	wp_localize_script(
		'wp-polls',
		'pollsL10n',
		array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'text_wait'     => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
			'text_valid'    => __( 'Please choose a valid poll answer.', 'wp-polls' ),
			'text_multiple' => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
			'show_loading'  => (int) $poll_ajax_style['loading'],
			'show_fading'   => (int) $poll_ajax_style['fading'],
		)
	);
}


// Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin
add_action( 'admin_enqueue_scripts', 'poll_scripts_admin' );
function poll_scripts_admin( $hook_suffix ) {
	$poll_admin_pages = array( 'wp-polls/polls-manager.php', 'wp-polls/polls-add.php', 'wp-polls/polls-options.php', 'wp-polls/polls-templates.php', 'wp-polls/polls-uninstall.php' );
	if ( in_array( $hook_suffix, $poll_admin_pages, true ) ) {
		wp_enqueue_style( 'wp-polls-admin', plugins_url( 'wp-polls/polls-admin-css.css' ), false, WP_POLLS_VERSION, 'all' );
		wp_enqueue_script( 'wp-polls-admin', plugins_url( 'wp-polls/polls-admin-js.js' ), array(), WP_POLLS_VERSION, true );
		wp_localize_script(
			'wp-polls-admin',
			'pollsAdminL10n',
			array(
				'admin_ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'text_direction'                 => is_rtl() ? 'right' : 'left',
				'text_delete_poll'               => __( 'Delete Poll', 'wp-polls' ),
				'text_no_poll_logs'              => __( 'No poll logs available.', 'wp-polls' ),
				'text_delete_all_logs'           => __( 'Delete All Logs', 'wp-polls' ),
				'text_checkbox_delete_all_logs'  => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs.', 'wp-polls' ),
				'text_delete_poll_logs'          => __( 'Delete Logs For This Poll Only', 'wp-polls' ),
				'text_checkbox_delete_poll_logs' => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs for this poll ONLY.', 'wp-polls' ),
				'text_delete_poll_ans'           => __( 'Delete Poll Answer', 'wp-polls' ),
				'text_open_poll'                 => __( 'Open Poll', 'wp-polls' ),
				'text_close_poll'                => __( 'Close Poll', 'wp-polls' ),
				'text_answer'                    => __( 'Answer', 'wp-polls' ),
				'text_remove_poll_answer'        => __( 'Remove', 'wp-polls' ),
			)
		);
	}
}


// Function: Displays Polls Footer In WP-Admin
add_action( 'admin_footer-post-new.php', 'poll_footer_admin' );
add_action( 'admin_footer-post.php', 'poll_footer_admin' );
add_action( 'admin_footer-page-new.php', 'poll_footer_admin' );
add_action( 'admin_footer-page.php', 'poll_footer_admin' );
function poll_footer_admin() {
	?>
	<script type="text/javascript">
		QTags.addButton('ed_wp_polls', '<?php echo esc_js( __( 'Poll', 'wp-polls' ) ); ?>', function() {
			var poll_id = (prompt('<?php echo esc_js( __( 'Enter Poll ID', 'wp-polls' ) ); ?>') || '').trim();
			while(isNaN(poll_id)) {
				poll_id = (prompt("<?php echo esc_js( __( 'Error: Poll ID must be numeric', 'wp-polls' ) ); ?>\n\n<?php echo esc_js( __( 'Please enter Poll ID again', 'wp-polls' ) ); ?>") || '').trim();
			}
			if (poll_id >= -1 && poll_id != null && poll_id != "") {
				QTags.insertContent('[poll id="' + poll_id + '"]');
			}
		});
	</script>
	<?php
}

// Function: Add Quick Tag For Poll In TinyMCE >= WordPress 2.5
add_action( 'init', 'poll_tinymce_addbuttons' );
function poll_tinymce_addbuttons() {
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	if ( get_user_option( 'rich_editing' ) === 'true' ) {
		add_filter( 'mce_external_plugins', 'poll_tinymce_addplugin' );
		add_filter( 'mce_buttons', 'poll_tinymce_registerbutton' );
		add_filter( 'wp_mce_translation', 'poll_tinymce_translation' );
	}
}
function poll_tinymce_registerbutton( $buttons ) {
	array_push( $buttons, 'separator', 'polls' );
	return $buttons;
}
function poll_tinymce_addplugin( $plugin_array ) {
	if ( WP_DEBUG ) {
		$plugin_array['polls'] = plugins_url( 'wp-polls/tinymce/plugins/polls/plugin.js?v=' . WP_POLLS_VERSION );
	} else {
		$plugin_array['polls'] = plugins_url( 'wp-polls/tinymce/plugins/polls/plugin.min.js?v=' . WP_POLLS_VERSION );
	}
	return $plugin_array;
}
function poll_tinymce_translation( $mce_translation ) {
	$mce_translation['Enter Poll ID']                  = esc_js( __( 'Enter Poll ID', 'wp-polls' ) );
	$mce_translation['Error: Poll ID must be numeric'] = esc_js( __( 'Error: Poll ID must be numeric', 'wp-polls' ) );
	$mce_translation['Please enter Poll ID again']     = esc_js( __( 'Please enter Poll ID again', 'wp-polls' ) );
	$mce_translation['Insert Poll']                    = esc_js( __( 'Insert Poll', 'wp-polls' ) );
	return $mce_translation;
}


// Function: Check Who Is Allow To Vote
function check_allowtovote() {
	global $user_ID;
	$user_ID       = (int) $user_ID;
	$allow_to_vote = (int) Polls_Options::get( 'allow_to_vote' );
	switch ( $allow_to_vote ) {
		// Guests Only
		case 0:
			if ( $user_ID > 0 ) {
				return false;
			}
			return true;
			break;
		// Registered Users Only
		case 1:
			if ( $user_ID === 0 ) {
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


// Funcrion: Check Voted By Cookie Or IP
function check_voted( $poll_id ) {
	$poll_logging_method = (int) Polls_Options::get( 'logging_method' );
	switch ( $poll_logging_method ) {
		// Do Not Log
		case 0:
			return 0;
			break;
		// Logged By Cookie
		case 1:
			return check_voted_cookie( $poll_id );
			break;
		// Logged By IP
		case 2:
			return check_voted_ip( $poll_id );
			break;
		// Logged By Cookie And IP
		case 3:
			$check_voted_cookie = check_voted_cookie( $poll_id );
			if ( ! empty( $check_voted_cookie ) ) {
				return $check_voted_cookie;
			}
			return check_voted_ip( $poll_id );
			break;
		// Logged By Username
		case 4:
			return check_voted_username( $poll_id );
			break;
	}
}


// Function: Check Voted By Cookie
function check_voted_cookie( $poll_id ) {
	$get_voted_aids = 0;
	if ( ! empty( $_COOKIE[ 'voted_' . $poll_id ] ) ) {
		$get_voted_aids = explode( ',', $_COOKIE[ 'voted_' . $poll_id ] );
		$get_voted_aids = array_map( 'intval', array_map( 'sanitize_key', $get_voted_aids ) );
	}
	return $get_voted_aids;
}


// Function: Check Voted By IP
function check_voted_ip( $poll_id ) {
	global $wpdb;
	$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
	$log_expiry_sql = '';
	if ( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time( 'timestamp' ) . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check IP From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_ip = %s", $poll_id, poll_get_ipaddress() ) . $log_expiry_sql );
	if ( $get_voted_aids ) {
		return $get_voted_aids;
	}

	return 0;
}


// Function: Check Voted By Username
function check_voted_username( $poll_id ) {
	global $wpdb, $user_ID;
	// Check IP If User Is Guest
	if ( ! is_user_logged_in() ) {
		return 1;
	}
	$pollsip_userid = (int) $user_ID;
	$log_expiry     = (int) Polls_Options::get( 'cookie_expiry' );
	$log_expiry_sql = '';
	if ( $log_expiry > 0 ) {
		$log_expiry_sql = ' AND (' . current_time( 'timestamp' ) . '-(pollip_timestamp+0)) < ' . $log_expiry;
	}
	// Check User ID From IP Logging Database
	$get_voted_aids = $wpdb->get_col( $wpdb->prepare( "SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid = %d", $poll_id, $pollsip_userid ) . $log_expiry_sql );
	if ( $get_voted_aids ) {
		return $get_voted_aids;
	} else {
		return 0;
	}
}

add_filter( 'wp_polls_template_voteheader_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votebody_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_votefooter_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_resultheader_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_resultbody_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_resultbody2_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_resultfooter_markup', 'poll_template_vote_markup', 10, 3 );
add_filter( 'wp_polls_template_resultfooter2_markup', 'poll_template_vote_markup', 10, 3 );

function poll_template_vote_markup( $template, $object, $variables ) {
	return str_replace( array_keys( $variables ), array_values( $variables ), $template );
}


// Function: Display Voting Form
function display_pollvote( $poll_id, $display_loading = true ) {
	do_action( 'wp_polls_display_pollvote' );
	global $wpdb;
	// Temp Poll Result
	$temp_pollvote = '';
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	// No poll could be loaded from the database
	if ( ! $poll_question ) {
		return removeslashes( Polls_Options::get( 'templates.disable' ) );
	}

	// Poll Question Variables
	$poll_question_text        = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id          = (int) $poll_question->pollq_id;
	$poll_question_totalvotes  = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_start_date           = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_timestamp ) );
	$poll_expiry               = trim( $poll_question->pollq_expiry );
	if ( empty( $poll_expiry ) ) {
		$poll_end_date = __( 'No Expiry', 'wp-polls' );
	} else {
		$poll_end_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
	}
	$poll_multiple_ans = (int) $poll_question->pollq_multiple;

	$template_question = removeslashes( Polls_Options::get( 'templates.voteheader' ) );

	$template_question_variables = array(
		'%POLL_QUESTION%'         => $poll_question_text,
		'%POLL_ID%'               => $poll_question_id,
		'%POLL_TOTALVOTES%'       => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%'      => $poll_question_totalvoters,
		'%POLL_START_DATE%'       => $poll_start_date,
		'%POLL_END_DATE%'         => $poll_end_date,
		'%POLL_MULTIPLE_ANS_MAX%' => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1,
	);
	$template_question_variables = apply_filters( 'wp_polls_template_voteheader_variables', $template_question_variables );
	$template_question           = apply_filters( 'wp_polls_template_voteheader_markup', $template_question, $poll_question, $template_question_variables );

	// Get Poll Answers Data
	list($order_by, $sort_order) = _polls_get_ans_sort();
	$poll_answers                = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// If There Is Poll Question With Answers
	if ( $poll_question && $poll_answers ) {
		// Display Poll Voting Form
		$temp_pollvote .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollvote .= "\t<form id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\" action=\"" . esc_url( $_SERVER['SCRIPT_NAME'] ) . "\" method=\"post\">\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"" . wp_create_nonce( 'poll_' . $poll_question_id . '-nonce' ) . "\" /></p>\n";
		$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_question_id\" /></p>\n";
		if ( $poll_multiple_ans > 0 ) {
			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_question_id\" name=\"poll_multiple_ans_$poll_question_id\" value=\"$poll_multiple_ans\" /></p>\n";
		}
		// Print Out Voting Form Header Template
		$temp_pollvote .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
			$poll_answer_id                  = (int) $poll_answer->polla_aid;
			$poll_answer_text                = wp_kses_post( removeslashes( $poll_answer->polla_answers ) );
			$poll_answer_votes               = (int) $poll_answer->polla_votes;
			$poll_answer_percentage          = $poll_question_totalvotes > 0 ? round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 ) : 0;
			$poll_multiple_answer_percentage = $poll_question_totalvoters > 0 ? round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 ) : 0;
			$template_answer                 = removeslashes( Polls_Options::get( 'templates.votebody' ) );

			$template_answer_variables = array(
				'%POLL_ID%'                         => $poll_question_id,
				'%POLL_ANSWER_ID%'                  => $poll_answer_id,
				'%POLL_ANSWER%'                     => $poll_answer_text,
				'%POLL_ANSWER_VOTES%'               => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%'          => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_CHECKBOX_RADIO%'             => $poll_multiple_ans > 0 ? 'checkbox' : 'radio',
			);

			$template_answer_variables = apply_filters( 'wp_polls_template_votebody_variables', $template_answer_variables );
			$template_answer           = apply_filters( 'wp_polls_template_votebody_markup', $template_answer, $poll_answer, $template_answer_variables );

			// Print Out Voting Form Body Template
			$temp_pollvote .= "\t\t$template_answer\n";
		}
		// Determine Poll Result URL
		$poll_result_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
		$poll_result_url = preg_replace( '/pollresult=(\d+)/i', 'pollresult=' . $poll_question_id, $poll_result_url );
		if ( isset( $_GET['pollresult'] ) && (int) $_GET['pollresult'] === 0 ) {
			if ( strpos( $poll_result_url, '?' ) !== false ) {
				$poll_result_url = "$poll_result_url&pollresult=$poll_question_id";
			} else {
				$poll_result_url = "$poll_result_url?pollresult=$poll_question_id";
			}
		}
		// Voting Form Footer Variables
		$template_footer = removeslashes( Polls_Options::get( 'templates.votefooter' ) );

		$template_footer_variables = array(
			'%POLL_ID%'               => $poll_question_id,
			'%POLL_RESULT_URL%'       => esc_url( $poll_result_url ),
			'%POLL_START_DATE%'       => $poll_start_date,
			'%POLL_END_DATE%'         => $poll_end_date,
			'%POLL_MULTIPLE_ANS_MAX%' => $poll_multiple_ans > 0 ? $poll_multiple_ans : 1,
		);

		$template_footer_variables = apply_filters( 'wp_polls_template_votefooter_variables', $template_footer_variables );
		$template_footer           = apply_filters( 'wp_polls_template_votefooter_markup', $template_footer, $poll_question, $template_footer_variables );

		// Print Out Voting Form Footer Template
		$temp_pollvote .= "\t\t$template_footer\n";
		$temp_pollvote .= "\t</form>\n";
		$temp_pollvote .= "</div>\n";
		if ( $display_loading ) {
			$poll_ajax_style = Polls_Options::get( 'ajax' );
			if ( (int) $poll_ajax_style['loading'] === 1 ) {
				$temp_pollvote .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"" . plugins_url( 'wp-polls/images/loading.gif' ) . '" width="16" height="16" alt="' . __( 'Loading', 'wp-polls' ) . ' ..." title="' . __( 'Loading', 'wp-polls' ) . ' ..." class="wp-polls-image" />&nbsp;' . __( 'Loading', 'wp-polls' ) . " ...</div>\n";
			}
		}
	} else {
		$temp_pollvote .= removeslashes( Polls_Options::get( 'templates.disable' ) );
	}
	// Return Poll Vote Template
	return $temp_pollvote;
}


// Function: Display Results Form
function display_pollresult( $poll_id, $user_voted = array(), $display_loading = true ) {
	global $wpdb;
	do_action( 'wp_polls_display_pollresult', $poll_id, $user_voted );
	$poll_id = (int) $poll_id;
	// User Voted
	if ( empty( $user_voted ) ) {
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
	$poll_most_answer      = '';
	$poll_most_votes       = 0;
	$poll_most_percentage  = 0;
	$poll_least_answer     = '';
	$poll_least_votes      = 0;
	$poll_least_percentage = 0;
	// Get Poll Question Data
	$poll_question = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_active, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
	// No poll could be loaded from the database
	if ( ! $poll_question ) {
		return removeslashes( Polls_Options::get( 'templates.disable' ) );
	}
	// Poll Question Variables
	$poll_question_text        = wp_kses_post( removeslashes( $poll_question->pollq_question ) );
	$poll_question_id          = (int) $poll_question->pollq_id;
	$poll_question_totalvotes  = (int) $poll_question->pollq_totalvotes;
	$poll_question_totalvoters = (int) $poll_question->pollq_totalvoters;
	$poll_question_active      = (int) $poll_question->pollq_active;
	$poll_start_date           = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_question->pollq_timestamp ) );
	$poll_expiry               = trim( $poll_question->pollq_expiry );
	if ( empty( $poll_expiry ) ) {
		$poll_end_date = __( 'No Expiry', 'wp-polls' );
	} else {
		$poll_end_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $poll_expiry ) );
	}
	$poll_multiple_ans  = (int) $poll_question->pollq_multiple;
	$template_question  = removeslashes( Polls_Options::get( 'templates.resultheader' ) );
	$template_variables = array(
		'%POLL_QUESTION%'    => $poll_question_text,
		'%POLL_ID%'          => $poll_question_id,
		'%POLL_TOTALVOTES%'  => $poll_question_totalvotes,
		'%POLL_TOTALVOTERS%' => $poll_question_totalvoters,
		'%POLL_START_DATE%'  => $poll_start_date,
		'%POLL_END_DATE%'    => $poll_end_date,
	);
	if ( $poll_multiple_ans > 0 ) {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
	} else {
		$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
	}

	$template_variables = apply_filters( 'wp_polls_template_resultheader_variables', $template_variables );
	$template_question  = apply_filters( 'wp_polls_template_resultheader_markup', $template_question, $poll_question, $template_variables );

	// Get Poll Answers Data
	list( $order_by, $sort_order ) = _polls_get_ans_result_sort();
	$poll_answers                  = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_question_id ) );
	// If There Is Poll Question With Answers
	if ( $poll_question && $poll_answers ) {
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		// Is The Poll Total Votes or Voters 0?
		$poll_totalvotes_zero  = $poll_question_totalvotes <= 0;
		$poll_totalvoters_zero = $poll_question_totalvoters <= 0;
		// Print Out Result Header Template
		$temp_pollresult .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
		$temp_pollresult .= "\t\t$template_question\n";
		foreach ( $poll_answers as $poll_answer ) {
			// Poll Answer Variables
			$poll_answer_id    = (int) $poll_answer->polla_aid;
			$poll_answer_text  = wp_kses_post( removeslashes( $poll_answer->polla_answers ) );
			$poll_answer_votes = (int) $poll_answer->polla_votes;
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage          = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth          = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $poll_answer_votes > 0 ) {
				$poll_answer_percentage          = round( ( $poll_answer_votes / $poll_question_totalvotes ) * 100 );
				$poll_multiple_answer_percentage = round( ( $poll_answer_votes / $poll_question_totalvoters ) * 100 );
				$poll_answer_imagewidth          = round( $poll_answer_percentage );
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
				'%POLL_ID%'                         => $poll_question_id,
				'%POLL_ANSWER_ID%'                  => $poll_answer_id,
				'%POLL_ANSWER%'                     => $poll_answer_text,
				// esc_attr() rather than htmlspecialchars(): the answer has already
				// been through wp_kses_post(), so & is already &amp; and
				// htmlspecialchars() default $double_encode = true turns that into
				// &amp;amp;, which renders as a literal &amp; in the tooltip.
				'%POLL_ANSWER_TEXT%'                => esc_attr( wp_strip_all_tags( $poll_answer_text ) ),
				'%POLL_ANSWER_VOTES%'               => number_format_i18n( $poll_answer_votes ),
				'%POLL_ANSWER_PERCENTAGE%'          => $poll_answer_percentage,
				'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => $poll_multiple_answer_percentage,
				'%POLL_ANSWER_IMAGEWIDTH%'          => $poll_answer_imagewidth,
			);
			$template_variables = apply_filters( 'wp_polls_template_resultbody_variables', $template_variables );

			// Let User See What Options They Voted
			if ( in_array( $poll_answer_id, $user_voted, true ) ) {
				// Results Body Variables
				$template_answer = removeslashes( Polls_Options::get( 'templates.resultbody2' ) );
				$template_answer = apply_filters( 'wp_polls_template_resultbody2_markup', $template_answer, $poll_answer, $template_variables );
			} else {
				// Results Body Variables
				$template_answer = removeslashes( Polls_Options::get( 'templates.resultbody' ) );
				$template_answer = apply_filters( 'wp_polls_template_resultbody_markup', $template_answer, $poll_answer, $template_variables );
			}

			// Print Out Results Body Template
			$temp_pollresult .= "\t\t$template_answer\n";

			// Get Most Voted Data
			if ( $poll_answer_votes > $poll_most_votes ) {
				$poll_most_answer     = $poll_answer_text;
				$poll_most_votes      = $poll_answer_votes;
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if ( $poll_least_votes === 0 ) {
				$poll_least_votes = $poll_answer_votes;
			}
			if ( $poll_answer_votes <= $poll_least_votes ) {
				$poll_least_answer     = $poll_answer_text;
				$poll_least_votes      = $poll_answer_votes;
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_variables = array(
			'%POLL_START_DATE%'       => $poll_start_date,
			'%POLL_END_DATE%'         => $poll_end_date,
			'%POLL_ID%'               => $poll_question_id,
			'%POLL_TOTALVOTES%'       => number_format_i18n( $poll_question_totalvotes ),
			'%POLL_TOTALVOTERS%'      => number_format_i18n( $poll_question_totalvoters ),
			'%POLL_MOST_ANSWER%'      => $poll_most_answer,
			'%POLL_MOST_VOTES%'       => number_format_i18n( $poll_most_votes ),
			'%POLL_MOST_PERCENTAGE%'  => $poll_most_percentage,
			'%POLL_LEAST_ANSWER%'     => $poll_least_answer,
			'%POLL_LEAST_VOTES%'      => number_format_i18n( $poll_least_votes ),
			'%POLL_LEAST_PERCENTAGE%' => $poll_least_percentage,
		);
		if ( $poll_multiple_ans > 0 ) {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = $poll_multiple_ans;
		} else {
			$template_variables['%POLL_MULTIPLE_ANS_MAX%'] = '1';
		}
		$template_variables = apply_filters( 'wp_polls_template_resultfooter_variables', $template_variables );

		if ( ! empty( $user_voted ) || $poll_question_active === 0 || ! check_allowtovote() ) {
			$template_footer = removeslashes( Polls_Options::get( 'templates.resultfooter' ) );
			$template_footer = apply_filters( 'wp_polls_template_resultfooter_markup', $template_footer, $poll_question, $template_variables );
		} else {
			$template_footer = removeslashes( Polls_Options::get( 'templates.resultfooter2' ) );
			$template_footer = apply_filters( 'wp_polls_template_resultfooter2_markup', $template_footer, $poll_question, $template_variables );
		}

		// Print Out Results Footer Template
		$temp_pollresult .= "\t\t$template_footer\n";
		$temp_pollresult .= "\t\t<input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"" . wp_create_nonce( 'poll_' . $poll_question_id . '-nonce' ) . "\" />\n";
		$temp_pollresult .= "</div>\n";
		if ( $display_loading ) {
			$poll_ajax_style = Polls_Options::get( 'ajax' );
			if ( (int) $poll_ajax_style['loading'] === 1 ) {
				$temp_pollresult .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"" . plugins_url( 'wp-polls/images/loading.gif' ) . '" width="16" height="16" alt="' . __( 'Loading', 'wp-polls' ) . ' ..." title="' . __( 'Loading', 'wp-polls' ) . ' ..." class="wp-polls-image" />&nbsp;' . __( 'Loading', 'wp-polls' ) . " ...</div>\n";
			}
		}
	} else {
		$temp_pollresult .= removeslashes( Polls_Options::get( 'templates.disable' ) );
	}
	// Return Poll Result
	return apply_filters( 'wp_polls_result_markup', $temp_pollresult );
}


// Function: Get IP Address
function poll_get_raw_ipaddress() {
	// REMOTE_ADDR is absent under WP-CLI and cron, where this is still reached
	// through the poll display path. Reading it unguarded warns on PHP 8.
	$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? esc_attr( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip_header = Polls_Options::get( 'ip_header', '' );
	if ( ! empty( $ip_header ) && ! empty( $_SERVER[ $ip_header ] ) ) {
		$ip = esc_attr( wp_unslash( $_SERVER[ $ip_header ] ) );
	}

	return $ip;
}

function poll_get_ipaddress() {
	return apply_filters( 'wp_polls_ipaddress', wp_hash( poll_get_raw_ipaddress() ) );
}

function poll_get_hostname() {
	$ip = poll_get_raw_ipaddress();

	// gethostbyaddr() warns on anything that is not an IP, which includes the
	// empty string when REMOTE_ADDR is absent and whatever a spoofable proxy
	// header happens to contain.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return apply_filters( 'wp_polls_hostname', '' );
	}

	$hostname = gethostbyaddr( $ip );
	if ( $hostname === $ip ) {
		$hostname = wp_privacy_anonymize_ip( $ip );
	}

	if ( false !== $hostname ) {
		$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
	}

	return apply_filters( 'wp_polls_hostname', $hostname );
}

// Function: Short Code For Inserting Polls Archive Into Page
add_shortcode( 'page_polls', 'poll_page_shortcode' );
function poll_page_shortcode( $atts ) {
	return polls_archive();
}


// Function: Short Code For Inserting Polls Into Posts
add_shortcode( 'poll', 'poll_shortcode' );
function poll_shortcode( $atts ) {
	$attributes = shortcode_atts(
		array(
			'id'   => 0,
			'type' => 'vote',
		),
		$atts
	);
	if ( ! is_feed() ) {
		$id = (int) $attributes['id'];

		// To maintain backward compatibility with [poll=1]. Props @tz-ua
		if ( ! $id && isset( $atts[0] ) ) {
			$id = (int) trim( $atts[0], '="\'' );
		}

		if ( $attributes['type'] === 'vote' ) {
			return get_poll( $id, false );
		} elseif ( $attributes['type'] === 'result' ) {
			return display_pollresult( $id );
		}
	} else {
		return __( 'Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls' );
	}
}


// Function: Get Poll Question Based On Poll ID
if ( ! function_exists( 'get_poll_question' ) ) {
	function get_poll_question( $poll_id ) {
		global $wpdb;
		$poll_id       = (int) $poll_id;
		$poll_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		return wp_kses_post( removeslashes( $poll_question ) );
	}
}


// Function: Get Poll Total Questions
if ( ! function_exists( 'get_pollquestions' ) ) {
	function get_pollquestions( $display = true ) {
		global $wpdb;
		$totalpollq = (int) $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalpollq;
		} else {
			return $totalpollq;
		}
	}
}


// Function: Get Poll Total Answers
if ( ! function_exists( 'get_pollanswers' ) ) {
	function get_pollanswers( $display = true ) {
		global $wpdb;
		$totalpolla = (int) $wpdb->get_var( "SELECT COUNT(polla_aid) FROM $wpdb->pollsa" );
		if ( $display ) {
			echo $totalpolla;
		} else {
			return $totalpolla;
		}
	}
}


// Function: Get Poll Total Votes
if ( ! function_exists( 'get_pollvotes' ) ) {
	function get_pollvotes( $display = true ) {
		global $wpdb;
		$totalvotes = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}

// Function: Get Poll Votes Based on Poll ID
if ( ! function_exists( 'get_pollvotes_by_id' ) ) {
	function get_pollvotes_by_id( $poll_id, $display = true ) {
		global $wpdb;
		$poll_id    = (int) $poll_id;
		$totalvotes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_totalvotes FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		if ( $display ) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}


// Function: Get Poll Total Voters
if ( ! function_exists( 'get_pollvoters' ) ) {
	function get_pollvoters( $display = true ) {
		global $wpdb;
		$totalvoters = (int) $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" );
		if ( $display ) {
			echo $totalvoters;
		} else {
			return $totalvoters;
		}
	}
}

// Function: Get Poll Time Based on Poll ID and Date Format
if ( ! function_exists( 'get_polltime' ) ) {
	function get_polltime( $poll_id, $date_format = 'd/m/Y', $display = true ) {
		global $wpdb;
		$poll_id        = (int) $poll_id;
		$timestamp      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d LIMIT 1", $poll_id ) );
		$formatted_date = date( $date_format, $timestamp );
		if ( $display ) {
			echo $formatted_date;
		} else {
			return $formatted_date;
		}
	}
}


// Function: Check Voted To Get Voted Answer
function check_voted_multiple( $poll_id, $polls_ips ) {
	if ( ! empty( $_COOKIE[ "voted_$poll_id" ] ) ) {
		return explode( ',', $_COOKIE[ "voted_$poll_id" ] );
	} elseif ( $polls_ips ) {
			return $polls_ips;
	} else {
		return array();
	}
}


// Function: Polls Archive Link
function polls_archive_link( $page ) {
	$polls_archive_url = Polls_Options::get( 'archive.url' );
	if ( $page > 0 ) {
		if ( strpos( $polls_archive_url, '?' ) !== false ) {
			$polls_archive_url = "$polls_archive_url&amp;poll_page=$page";
		} else {
			$polls_archive_url = "$polls_archive_url?poll_page=$page";
		}
	}
	return $polls_archive_url;
}


// Function: Displays Polls Archive Link
function display_polls_archive_link( $display = true ) {
	$template_pollarchivelink = removeslashes( Polls_Options::get( 'templates.pollarchivelink' ) );
	$template_pollarchivelink = str_replace( '%POLL_ARCHIVE_URL%', esc_url( Polls_Options::get( 'archive.url' ) ), $template_pollarchivelink );
	if ( $display ) {
		echo $template_pollarchivelink;
	} else {
		return $template_pollarchivelink;
	}
}


// Function: Display Polls Archive
function polls_archive() {
	do_action( 'wp_polls_polls_archive' );
	global $wpdb, $in_pollsarchive;
	// Polls Variables
	$in_pollsarchive             = true;
	$page                        = isset( $_GET['poll_page'] ) ? (int) sanitize_key( $_GET['poll_page'] ) : 0;
	$polls_questions             = array();
	$polls_answers               = array();
	$polls_ips                   = array();
	$polls_perpage               = (int) Polls_Options::get( 'archive.per_page' );
	$poll_questions_ids          = '0';
	$poll_voted                  = false;
	$poll_voted_aid              = 0;
	$poll_id                     = 0;
	$pollsarchive_output_archive = '';
	$polls_type                  = (int) Polls_Options::get( 'archive.display_poll' );
	$polls_type_sql              = '';
	// Determine What Type Of Polls To Show
	switch ( $polls_type ) {
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
	$total_polls = $wpdb->get_var( "SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE $polls_type_sql AND pollq_active != -1" );

	// Calculate Paging
	$numposts = $total_polls;
	$perpage  = $polls_perpage;
	$max_page = ceil( $numposts / $perpage );
	if ( empty( $page ) || $page == 0 ) {
		$page = 1;
	}
	$offset                = ( $page - 1 ) * $perpage;
	$pages_to_show         = 10;
	$pages_to_show_minus_1 = $pages_to_show - 1;
	$half_page_start       = floor( $pages_to_show_minus_1 / 2 );
	$half_page_end         = ceil( $pages_to_show_minus_1 / 2 );
	$start_page            = $page - $half_page_start;
	if ( $start_page <= 0 ) {
		$start_page = 1;
	}
	$end_page = $page + $half_page_end;
	if ( ( $end_page - $start_page ) !== $pages_to_show_minus_1 ) {
		$end_page = $start_page + $pages_to_show_minus_1;
	}
	if ( $end_page > $max_page ) {
		$start_page = $max_page - $pages_to_show_minus_1;
		$end_page   = $max_page;
	}
	if ( $start_page <= 0 ) {
		$start_page = 1;
	}
	if ( ( $offset + $perpage ) > $numposts ) {
		$max_on_page = $numposts;
	} else {
		$max_on_page = ( $offset + $perpage );
	}
	if ( ( $offset + 1 ) > ( $numposts ) ) {
		$display_on_page = $numposts;
	} else {
		$display_on_page = ( $offset + 1 );
	}

	// Get Poll Questions
	$questions = $wpdb->get_results( "SELECT * FROM $wpdb->pollsq WHERE $polls_type_sql ORDER BY pollq_id DESC LIMIT $offset, $polls_perpage" );
	if ( $questions ) {
		foreach ( $questions as $question ) {
			$polls_questions[]   = array(
				'id'          => (int) $question->pollq_id,
				'question'    => wp_kses_post( removeslashes( $question->pollq_question ) ),
				'timestamp'   => $question->pollq_timestamp,
				'totalvotes'  => (int) $question->pollq_totalvotes,
				'start'       => $question->pollq_timestamp,
				'end'         => trim( $question->pollq_expiry ),
				'multiple'    => (int) $question->pollq_multiple,
				'totalvoters' => (int) $question->pollq_totalvoters,
			);
			$poll_questions_ids .= (int) $question->pollq_id . ', ';
		}
		$poll_questions_ids = substr( $poll_questions_ids, 0, -2 );
	}

	// Get Poll Answers
	list($order_by, $sort_order) = _polls_get_ans_result_sort();
	$answers                     = $wpdb->get_results( "SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid IN ($poll_questions_ids) ORDER BY $order_by $sort_order" );
	if ( $answers ) {
		foreach ( $answers as $answer ) {
			$polls_answers[ (int) $answer->polla_qid ][] = array(
				'aid'     => (int) $answer->polla_aid,
				'qid'     => (int) $answer->polla_qid,
				'answers' => wp_kses_post( removeslashes( $answer->polla_answers ) ),
				'votes'   => (int) $answer->polla_votes,
			);
		}
	}

	// Get Poll IPs
	$ips = $wpdb->get_results( "SELECT pollip_qid, pollip_aid FROM $wpdb->pollsip WHERE pollip_qid IN ($poll_questions_ids) AND pollip_ip = '" . poll_get_ipaddress() . "' ORDER BY pollip_qid ASC" );
	if ( $ips ) {
		foreach ( $ips as $ip ) {
			$polls_ips[ (int) $ip->pollip_qid ][] = (int) $ip->pollip_aid;
		}
	}
	// Poll Archives
	$pollsarchive_output_archive .= "<div class=\"wp-polls wp-polls-archive\">\n";
	foreach ( $polls_questions as $polls_question ) {
		// Most/Least Variables
		$poll_most_answer      = '';
		$poll_most_votes       = 0;
		$poll_most_percentage  = 0;
		$poll_least_answer     = '';
		$poll_least_votes      = 0;
		$poll_least_percentage = 0;
		// Is The Poll Total Votes 0?
		$poll_totalvotes_zero  = $polls_question['totalvotes'] <= 0;
		$poll_totalvoters_zero = $polls_question['totalvoters'] <= 0;
		$poll_start_date       = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $polls_question['start'] ) );
		if ( empty( $polls_question['end'] ) ) {
			$poll_end_date = __( 'No Expiry', 'wp-polls' );
		} else {
			$poll_end_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $polls_question['end'] ) );
		}
		// Archive Poll Header
		$template_archive_header = removeslashes( Polls_Options::get( 'templates.pollarchiveheader' ) );
		// Poll Question Variables
		$template_question = removeslashes( Polls_Options::get( 'templates.resultheader' ) );
		$template_question = str_replace( '%POLL_QUESTION%', $polls_question['question'], $template_question );
		$template_question = str_replace( '%POLL_ID%', $polls_question['id'], $template_question );
		$template_question = str_replace( '%POLL_TOTALVOTES%', number_format_i18n( $polls_question['totalvotes'] ), $template_question );
		$template_question = str_replace( '%POLL_TOTALVOTERS%', number_format_i18n( $polls_question['totalvoters'] ), $template_question );
		$template_question = str_replace( '%POLL_START_DATE%', $poll_start_date, $template_question );
		$template_question = str_replace( '%POLL_END_DATE%', $poll_end_date, $template_question );
		if ( $polls_question['multiple'] > 0 ) {
			$template_question = str_replace( '%POLL_MULTIPLE_ANS_MAX%', $polls_question['multiple'], $template_question );
		} else {
			$template_question = str_replace( '%POLL_MULTIPLE_ANS_MAX%', '1', $template_question );
		}
		// Print Out Result Header Template
		$pollsarchive_output_archive .= $template_archive_header;
		$pollsarchive_output_archive .= $template_question;
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		foreach ( $polls_answers[ $polls_question['id'] ] as $polls_answer ) {
			// Calculate Percentage And Image Bar Width
			$poll_answer_percentage          = 0;
			$poll_multiple_answer_percentage = 0;
			$poll_answer_imagewidth          = 1;
			if ( ! $poll_totalvotes_zero && ! $poll_totalvoters_zero && $polls_answer['votes'] > 0 ) {
				$poll_answer_percentage          = round( ( $polls_answer['votes'] / $polls_question['totalvotes'] ) * 100 );
				$poll_multiple_answer_percentage = round( ( $polls_answer['votes'] / $polls_question['totalvoters'] ) * 100 );
				$poll_answer_imagewidth          = round( $poll_answer_percentage * 0.9 );
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			if ( $polls_question['multiple'] === 0 ) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if ( count( $poll_answer_percentage_array ) === count( $polls_answers[ $polls_question['id'] ] ) ) {
					$percentage_error_buffer = 100 - array_sum( $poll_answer_percentage_array );
					$poll_answer_percentage += $percentage_error_buffer;
					if ( $poll_answer_percentage < 0 ) {
						$poll_answer_percentage = 0;
					}
				}
			}
			$polls_answer['answers'] = wp_kses_post( $polls_answer['answers'] );
			// Let User See What Options They Voted
			if ( isset( $polls_ips[ $polls_question['id'] ] ) && in_array( $polls_answer['aid'], check_voted_multiple( $polls_question['id'], $polls_ips[ $polls_question['id'] ] ), true ) ) {
				$template_answer = removeslashes( Polls_Options::get( 'templates.resultbody2' ) );
			} else {
				$template_answer = removeslashes( Polls_Options::get( 'templates.resultbody' ) );
			}

			$template_answer = str_replace(
				array(
					'%POLL_ID%',
					'%POLL_ANSWER_ID%',
					'%POLL_ANSWER%',
					'%POLL_ANSWER_TEXT%',
					'%POLL_ANSWER_VOTES%',
					'%POLL_ANSWER_PERCENTAGE%',
					'%POLL_MULTIPLE_ANSWER_PERCENTAGE%',
					'%POLL_ANSWER_IMAGEWIDTH%',
				),
				array(
					$polls_question['id'],
					$polls_answer['aid'],
					$polls_answer['answers'],
					htmlspecialchars( wp_strip_all_tags( $polls_answer['answers'] ) ),
					number_format_i18n( $polls_answer['votes'] ),
					$poll_answer_percentage,
					$poll_multiple_answer_percentage,
					$poll_answer_imagewidth,
				),
				$template_answer
			);

			// Print Out Results Body Template
			$pollsarchive_output_archive .= $template_answer;

			// Get Most Voted Data
			if ( $polls_answer['votes'] > $poll_most_votes ) {
				$poll_most_answer     = $polls_answer['answers'];
				$poll_most_votes      = $polls_answer['votes'];
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if ( $poll_least_votes === 0 ) {
				$poll_least_votes = $polls_answer['votes'];
			}
			if ( $polls_answer['votes'] <= $poll_least_votes ) {
				$poll_least_answer     = $polls_answer['answers'];
				$poll_least_votes      = $polls_answer['votes'];
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_footer = removeslashes( Polls_Options::get( 'templates.resultfooter' ) );
		$template_footer = str_replace( '%POLL_ID%', $polls_question['id'], $template_footer );
		$template_footer = str_replace( '%POLL_START_DATE%', $poll_start_date, $template_footer );
		$template_footer = str_replace( '%POLL_END_DATE%', $poll_end_date, $template_footer );
		$template_footer = str_replace( '%POLL_TOTALVOTES%', number_format_i18n( $polls_question['totalvotes'] ), $template_footer );
		$template_footer = str_replace( '%POLL_TOTALVOTERS%', number_format_i18n( $polls_question['totalvoters'] ), $template_footer );
		$template_footer = str_replace( '%POLL_MOST_ANSWER%', $poll_most_answer, $template_footer );
		$template_footer = str_replace( '%POLL_MOST_VOTES%', number_format_i18n( $poll_most_votes ), $template_footer );
		$template_footer = str_replace( '%POLL_MOST_PERCENTAGE%', $poll_most_percentage, $template_footer );
		$template_footer = str_replace( '%POLL_LEAST_ANSWER%', $poll_least_answer, $template_footer );
		$template_footer = str_replace( '%POLL_LEAST_VOTES%', number_format_i18n( $poll_least_votes ), $template_footer );
		$template_footer = str_replace( '%POLL_LEAST_PERCENTAGE%', $poll_least_percentage, $template_footer );
		if ( $polls_question['multiple'] > 0 ) {
			$template_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', $polls_question['multiple'], $template_footer );
		} else {
			$template_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', '1', $template_footer );
		}
		// Archive Poll Footer
		$template_archive_footer = removeslashes( Polls_Options::get( 'templates.pollarchivefooter' ) );
		$template_archive_footer = str_replace( '%POLL_START_DATE%', $poll_start_date, $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_END_DATE%', $poll_end_date, $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_TOTALVOTES%', number_format_i18n( $polls_question['totalvotes'] ), $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_TOTALVOTERS%', number_format_i18n( $polls_question['totalvoters'] ), $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_MOST_ANSWER%', $poll_most_answer, $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_MOST_VOTES%', number_format_i18n( $poll_most_votes ), $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_MOST_PERCENTAGE%', $poll_most_percentage, $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_LEAST_ANSWER%', $poll_least_answer, $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_LEAST_VOTES%', number_format_i18n( $poll_least_votes ), $template_archive_footer );
		$template_archive_footer = str_replace( '%POLL_LEAST_PERCENTAGE%', $poll_least_percentage, $template_archive_footer );
		if ( $polls_question['multiple'] > 0 ) {
			$template_archive_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', $polls_question['multiple'], $template_archive_footer );
		} else {
			$template_archive_footer = str_replace( '%POLL_MULTIPLE_ANS_MAX%', '1', $template_archive_footer );
		}
		// Print Out Results Footer Template
		$pollsarchive_output_archive .= $template_footer;
		// Print Out Archive Poll Footer Template
		$pollsarchive_output_archive .= $template_archive_footer;
	}
	$pollsarchive_output_archive .= "</div>\n";

	// Polls Archive Paging
	if ( $max_page > 1 ) {
		$pollsarchive_output_archive .= removeslashes( Polls_Options::get( 'templates.pollarchivepagingheader' ) );
		if ( function_exists( 'wp_pagenavi' ) ) {
			$pollsarchive_output_archive .= '<div class="wp-pagenavi">' . "\n";
		} else {
			$pollsarchive_output_archive .= '<div class="wp-polls-paging">' . "\n";
		}
		$pollsarchive_output_archive .= '<span class="pages">&#8201;' . sprintf( __( 'Page %1$s of %2$s', 'wp-polls' ), number_format_i18n( $page ), number_format_i18n( $max_page ) ) . '&#8201;</span>';
		if ( $start_page >= 2 && $pages_to_show < $max_page ) {
			$pollsarchive_output_archive .= '<a href="' . polls_archive_link( 1 ) . '" title="' . __( '&laquo; First', 'wp-polls' ) . '">&#8201;' . __( '&laquo; First', 'wp-polls' ) . '&#8201;</a>';
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
		}
		if ( $page > 1 ) {
			$pollsarchive_output_archive .= '<a href="' . polls_archive_link( ( $page - 1 ) ) . '" title="' . __( '&laquo;', 'wp-polls' ) . '">&#8201;' . __( '&laquo;', 'wp-polls' ) . '&#8201;</a>';
		}
		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			if ( $i === $page ) {
				$pollsarchive_output_archive .= '<span class="current">&#8201;' . number_format_i18n( $i ) . '&#8201;</span>';
			} else {
				$pollsarchive_output_archive .= '<a href="' . polls_archive_link( $i ) . '" title="' . number_format_i18n( $i ) . '">&#8201;' . number_format_i18n( $i ) . '&#8201;</a>';
			}
		}
		if ( empty( $page ) || ( $page + 1 ) <= $max_page ) {
			$pollsarchive_output_archive .= '<a href="' . polls_archive_link( ( $page + 1 ) ) . '" title="' . __( '&raquo;', 'wp-polls' ) . '">&#8201;' . __( '&raquo;', 'wp-polls' ) . '&#8201;</a>';
		}
		if ( $end_page < $max_page ) {
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
			$pollsarchive_output_archive .= '<a href="' . polls_archive_link( $max_page ) . '" title="' . __( 'Last &raquo;', 'wp-polls' ) . '">&#8201;' . __( 'Last &raquo;', 'wp-polls' ) . '&#8201;</a>';
		}
		$pollsarchive_output_archive .= '</div>';
		$pollsarchive_output_archive .= removeslashes( Polls_Options::get( 'templates.pollarchivepagingfooter' ) );
	}

	// Output Polls Archive Page
	return apply_filters( 'wp_polls_archive', $pollsarchive_output_archive );
}


// Edit Timestamp Options
function poll_timestamp( $poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block' ) {
	global $month;
	echo '<div id="' . $fieldname . '" style="display: ' . $display . '">' . "\n";
	$day = (int) gmdate( 'j', $poll_timestamp );
	echo '<select name="' . $fieldname . '_day" size="1">' . "\n";
	for ( $i = 1; $i <= 31; $i++ ) {
		if ( $day === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;' . "\n";
	$month2 = (int) gmdate( 'n', $poll_timestamp );
	echo '<select name="' . $fieldname . '_month" size="1">' . "\n";
	for ( $i = 1; $i <= 12; $i++ ) {
		if ( $i < 10 ) {
			$ii = '0' . $i;
		} else {
			$ii = $i;
		}
		if ( $month2 === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$month[$ii]</option>\n";
		} else {
			echo "<option value=\"$i\">$month[$ii]</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;' . "\n";
	$year = (int) gmdate( 'Y', $poll_timestamp );
	echo '<select name="' . $fieldname . '_year" size="1">' . "\n";
	for ( $i = 2000; $i <= ( $year + 10 ); $i++ ) {
		if ( $year === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;@' . "\n";
	echo '<span dir="ltr">' . "\n";
	$hour = (int) gmdate( 'H', $poll_timestamp );
	echo '<select name="' . $fieldname . '_hour" size="1">' . "\n";
	for ( $i = 0; $i < 24; $i++ ) {
		if ( $hour === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;:' . "\n";
	$minute = (int) gmdate( 'i', $poll_timestamp );
	echo '<select name="' . $fieldname . '_minute" size="1">' . "\n";
	for ( $i = 0; $i < 60; $i++ ) {
		if ( $minute === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}

	echo '</select>&nbsp;:' . "\n";
	$second = (int) gmdate( 's', $poll_timestamp );
	echo '<select name="' . $fieldname . '_second" size="1">' . "\n";
	for ( $i = 0; $i <= 60; $i++ ) {
		if ( $second === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>' . "\n";
	echo '</span>' . "\n";
	echo '</div>' . "\n";
}


// Function: Place Cron
function cron_polls_place() {
	wp_clear_scheduled_hook( 'polls_cron' );
	if ( ! wp_next_scheduled( 'polls_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'polls_cron' );
	}
}

// Funcion: Check All Polls Status To Check If It Expires
add_action( 'polls_cron', 'cron_polls_status' );
function cron_polls_status() {
	global $wpdb;
	// Close Poll
	$close_polls = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < '" . current_time( 'timestamp' ) . "' AND pollq_expiry != 0 AND pollq_active != 0" );
	// Open Future Polls
	$active_polls = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= '" . current_time( 'timestamp' ) . "' AND pollq_active = -1" );
	// Update Latest Poll If Future Poll Is Opened
	if ( $active_polls ) {
		$update_latestpoll = Polls_Options::set( 'latest_poll', polls_latest_id() );
	}
	return;
}


// Funcion: Get Latest Poll ID
function polls_latest_id() {
	global $wpdb;
	$poll_id = $wpdb->get_var( "SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1" );
	return (int) $poll_id;
}


// Check If In Poll Archive Page
function in_pollarchive() {
	$poll_archive_url       = Polls_Options::get( 'archive.url' );
	$poll_archive_url_array = explode( '/', $poll_archive_url );
	$poll_archive_url       = $poll_archive_url_array[ count( $poll_archive_url_array ) - 1 ];
	if ( empty( $poll_archive_url ) ) {
		$poll_archive_url = $poll_archive_url_array[ count( $poll_archive_url_array ) - 2 ];
	}
	$current_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
	if ( strpos( $current_url, strval( $poll_archive_url ) ) === false ) {
		return false;
	}

	return true;
}

function vote_poll_process( $poll_id, $poll_aid_array = array() ) {
	global $wpdb, $user_identity, $user_ID;

	do_action( 'wp_polls_vote_poll' );

	// Acquire lock
	$fp_lock = polls_acquire_lock( $poll_id );
	if ( $fp_lock === false ) {
		throw new InvalidArgumentException( sprintf( __( 'Unable to obtain lock for Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	$polla_aids = $wpdb->get_col( $wpdb->prepare( "SELECT polla_aid FROM $wpdb->pollsa WHERE polla_qid = %d", $poll_id ) );
	$is_real    = count( array_intersect( $poll_aid_array, $polla_aids ) ) === count( $poll_aid_array );

	if ( ! $is_real ) {
		throw new InvalidArgumentException( sprintf( __( 'Invalid Answer to Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	if ( ! check_allowtovote() ) {
		throw new InvalidArgumentException( sprintf( __( 'User is not allowed to vote for Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	if ( empty( $poll_aid_array ) ) {
		throw new InvalidArgumentException( sprintf( __( 'No answers given for Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	if ( $poll_id === 0 ) {
		throw new InvalidArgumentException( sprintf( __( 'Invalid Poll ID. Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	$is_poll_open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pollsq WHERE pollq_id = %d AND pollq_active = 1", $poll_id ) );

	if ( $is_poll_open === 0 ) {
		throw new InvalidArgumentException( sprintf( __( 'Poll ID #%s is closed', 'wp-polls' ), $poll_id ) );
	}

	$check_voted = check_voted( $poll_id );
	if ( ! empty( $check_voted ) ) {
		throw new InvalidArgumentException( sprintf( __( 'You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	if ( ! empty( $user_identity ) ) {
		$pollip_user = $user_identity;
	} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
		$pollip_user = $_COOKIE[ 'comment_author_' . COOKIEHASH ];
	} else {
		$pollip_user = __( 'Guest', 'wp-polls' );
	}

	$pollip_user         = sanitize_text_field( $pollip_user );
	$pollip_userid       = $user_ID;
	$pollip_ip           = poll_get_ipaddress();
	$pollip_host         = poll_get_hostname();
	$pollip_timestamp    = current_time( 'timestamp' );
	$poll_logging_method = (int) Polls_Options::get( 'logging_method' );

	// Only Create Cookie If User Choose Logging Method 1 Or 3
	if ( $poll_logging_method === 1 || $poll_logging_method === 3 ) {
		$cookie_expiry = (int) Polls_Options::get( 'cookie_expiry' );
		if ( $cookie_expiry === 0 ) {
			$cookie_expiry = YEAR_IN_SECONDS;
		}
		setcookie( 'voted_' . $poll_id, implode( ',', $poll_aid_array ), $pollip_timestamp + $cookie_expiry, apply_filters( 'wp_polls_cookiepath', SITECOOKIEPATH ) );
	}

	$i = 0;
	foreach ( $poll_aid_array as $polla_aid ) {
		$update_polla_votes = $wpdb->query( "UPDATE $wpdb->pollsa SET polla_votes = (polla_votes + 1) WHERE polla_qid = $poll_id AND polla_aid = $polla_aid" );
		if ( ! $update_polla_votes ) {
			unset( $poll_aid_array[ $i ] );
		}
		++$i;
	}

	$vote_q = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes+" . count( $poll_aid_array ) . "), pollq_totalvoters = (pollq_totalvoters + 1) WHERE pollq_id = $poll_id AND pollq_active = 1" );
	if ( ! $vote_q ) {
		throw new InvalidArgumentException( sprintf( __( 'Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls' ), $poll_id ) );
	}

	foreach ( $poll_aid_array as $polla_aid ) {
		// Log Ratings In DB If User Choose Logging Method 2, 3 or 4
		if ( $poll_logging_method > 1 ) {
			$wpdb->insert(
				$wpdb->pollsip,
				array(
					'pollip_qid'       => $poll_id,
					'pollip_aid'       => $polla_aid,
					'pollip_ip'        => $pollip_ip,
					'pollip_host'      => $pollip_host,
					'pollip_timestamp' => $pollip_timestamp,
					'pollip_user'      => $pollip_user,
					'pollip_userid'    => $pollip_userid,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
				)
			);
		}
	}

	// Release lock
	polls_release_lock( $fp_lock, $poll_id );

	do_action( 'wp_polls_vote_poll_success' );

	return display_pollresult( $poll_id, $poll_aid_array, false );
}


// Function: Vote Poll
add_action( 'wp_ajax_polls', 'vote_poll' );
add_action( 'wp_ajax_nopriv_polls', 'vote_poll' );
function vote_poll() {
	global $wpdb, $user_identity, $user_ID;

	if ( isset( $_REQUEST['action'] ) && sanitize_key( $_REQUEST['action'] ) === 'polls' ) {
		// Load Headers
		polls_textdomain();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) . '' );

		// Get Poll ID
		$poll_id = ( isset( $_REQUEST['poll_id'] ) ? (int) sanitize_key( $_REQUEST['poll_id'] ) : 0 );

		// Ensure Poll ID Is Valid
		if ( $poll_id === 0 ) {
			_e( 'Invalid Poll ID', 'wp-polls' );
			exit();
		}

		// Verify Referer
		if ( ! check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_' . $poll_id . '_nonce', false ) ) {
			_e( 'Failed To Verify Referrer', 'wp-polls' );
			exit();
		}

		// Which View
		switch ( sanitize_key( $_REQUEST['view'] ) ) {
			// Poll Vote
			case 'process':
				try {
					$poll_answer_ids = isset( $_POST[ "poll_$poll_id" ] ) ? $_POST[ "poll_$poll_id" ] : '';
					$poll_aid_array  = array_unique( array_map( 'intval', array_map( 'sanitize_key', explode( ',', $poll_answer_ids ) ) ) );
					echo vote_poll_process( $poll_id, $poll_aid_array );
				} catch ( Exception $e ) {
					echo $e->getMessage();
				}
				break;
			// Poll Result
			case 'result':
				echo display_pollresult( $poll_id, 0, false );
				break;
			// Poll Booth Aka Poll Voting Form
			case 'booth':
				echo display_pollvote( $poll_id, false );
				break;
		} // End switch($_REQUEST['view'])
	} // End if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'polls')
	exit();
}


// Function: Manage Polls
add_action( 'wp_ajax_polls-admin', 'manage_poll' );
function manage_poll() {
	global $wpdb;

	// Every branch below is an administrative action. The per-action nonces are
	// only ever rendered on pages that already require 'manage_polls', but check
	// the capability itself rather than relying on the nonce for authorisation.
	if ( ! current_user_can( 'manage_polls' ) ) {
		exit();
	}

	// Form Processing
	if ( isset( $_POST['action'] ) && sanitize_key( $_POST['action'] ) === 'polls-admin' ) {
		if ( ! empty( $_POST['do'] ) ) {
			// Set Header
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) . '' );

			// Decide What To Do
			switch ( $_POST['do'] ) {
				// Delete Polls Logs
				case __( 'Delete All Logs', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_delete-polls-logs' );
					if ( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes' ) {
						$delete_logs = $wpdb->query( "DELETE FROM $wpdb->pollsip" );
						if ( $delete_logs ) {
							echo '<p style="color: green;">' . __( 'All Polls Logs Have Been Deleted.', 'wp-polls' ) . '</p>';
						} else {
							echo '<p style="color: red;">' . __( 'An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls' ) . '</p>';
						}
					}
					break;
				// Delete Poll Logs For Individual Poll
				case __( 'Delete Logs For This Poll Only', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_delete-poll-logs' );
					$pollq_id       = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					if ( sanitize_key( trim( $_POST['delete_logs_yes'] ) ) === 'yes' ) {
						$delete_logs = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
						if ( $delete_logs ) {
							echo '<p style="color: green;">' . sprintf( __( 'All Logs For \'%s\' Has Been Deleted.', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
						} else {
							echo '<p style="color: red;">' . sprintf( __( 'An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
						}
					}
					break;
				// Delete Poll's Answer
				case __( 'Delete Poll Answer', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_delete-poll-answer' );
					$pollq_id                = (int) sanitize_key( $_POST['pollq_id'] );
					$polla_aid               = (int) sanitize_key( $_POST['polla_aid'] );
					$poll_answers            = $wpdb->get_row( $wpdb->prepare( "SELECT polla_votes, polla_answers FROM $wpdb->pollsa WHERE polla_aid = %d AND polla_qid = %d", $polla_aid, $pollq_id ) );
					$polla_votes             = (int) $poll_answers->polla_votes;
					$polla_answers           = wp_kses_post( removeslashes( trim( $poll_answers->polla_answers ) ) );
					$delete_polla_answers    = $wpdb->delete(
						$wpdb->pollsa,
						array(
							'polla_aid' => $polla_aid,
							'polla_qid' => $pollq_id,
						),
						array( '%d', '%d' )
					);
					$delete_pollip           = $wpdb->delete(
						$wpdb->pollsip,
						array(
							'pollip_qid' => $pollq_id,
							'pollip_aid' => $polla_aid,
						),
						array( '%d', '%d' )
					);
					$update_pollq_totalvotes = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes - $polla_votes) WHERE pollq_id = $pollq_id" );
					if ( $delete_polla_answers ) {
						echo '<p style="color: green;">' . sprintf( __( 'Poll Answer \'%s\' Deleted Successfully.', 'wp-polls' ), $polla_answers ) . '</p>';
					} else {
						echo '<p style="color: red;">' . sprintf( __( 'Error In Deleting Poll Answer \'%s\'.', 'wp-polls' ), $polla_answers ) . '</p>';
					}
					break;
				// Open Poll
				case __( 'Open Poll', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_open-poll' );
					$pollq_id       = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$open_poll      = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 1,
						),
						array(
							'pollq_id' => $pollq_id,
						),
						array(
							'%d',
						),
						array(
							'%d',
						)
					);
					if ( $open_poll ) {
						echo '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Is Now Opened', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					} else {
						echo '<p style="color: red;">' . sprintf( __( 'Error Opening Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					}
					break;
				// Close Poll
				case __( 'Close Poll', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_close-poll' );
					$pollq_id       = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$close_poll     = $wpdb->update(
						$wpdb->pollsq,
						array(
							'pollq_active' => 0,
						),
						array(
							'pollq_id' => $pollq_id,
						),
						array(
							'%d',
						),
						array(
							'%d',
						)
					);
					if ( $close_poll ) {
						echo '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Is Now Closed', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					} else {
						echo '<p style="color: red;">' . sprintf( __( 'Error Closing Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					}
					break;
				// Delete Poll
				case __( 'Delete Poll', 'wp-polls' ):
					check_ajax_referer( 'wp-polls_delete-poll' );
					$pollq_id                = (int) sanitize_key( $_POST['pollq_id'] );
					$pollq_question          = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
					$delete_poll_question    = $wpdb->delete( $wpdb->pollsq, array( 'pollq_id' => $pollq_id ), array( '%d' ) );
					$delete_poll_answers     = $wpdb->delete( $wpdb->pollsa, array( 'polla_qid' => $pollq_id ), array( '%d' ) );
					$delete_poll_ip          = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
					$poll_option_lastestpoll = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'poll_latestpoll'" );
					if ( ! $delete_poll_question ) {
						echo '<p style="color: red;">' . sprintf( __( 'Error In Deleting Poll \'%s\' Question', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					}
					if ( empty( $text ) ) {
						echo '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Deleted Successfully', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>';
					}

					// Update Lastest Poll ID To Poll Options
					Polls_Options::set( 'latest_poll', polls_latest_id() );
					do_action( 'wp_polls_delete_poll', $pollq_id );
					break;
			}
			exit();
		}
	}
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

function _polls_get_ans_sort() {
	$order_by = Polls_Options::get( 'sort.answers_by' );
	switch ( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = Polls_Options::get( 'sort.answers_order' ) === 'desc' ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}

function _polls_get_ans_result_sort() {
	$order_by = Polls_Options::get( 'sort.results_by' );
	switch ( $order_by ) {
		case 'polla_votes':
		case 'polla_aid':
		case 'polla_answers':
		case 'RAND()':
			break;
		default:
			$order_by = 'polla_aid';
			break;
	}
	$sort_order = Polls_Options::get( 'sort.results_order' ) === 'desc' ? 'desc' : 'asc';
	return array( $order_by, $sort_order );
}


// Function: Plug Into WP-Stats
add_action( 'plugins_loaded', 'polls_wp_stats' );
function polls_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'polls_page_admin_general_stats' );
	add_filter( 'wp_stats_page_plugins', 'polls_page_general_stats' );
}


// Function: Add WP-Polls General Stats To WP-Stats Page Options
function polls_page_admin_general_stats( $content ) {
	$stats_display = get_option( 'stats_display' );
	if ( (int) ( $stats_display['polls'] ?? 0 ) === 1 ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" checked="checked" />&nbsp;&nbsp;<label for="wpstats_polls">' . __( 'WP-Polls', 'wp-polls' ) . '</label><br />' . "\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" />&nbsp;&nbsp;<label for="wpstats_polls">' . __( 'WP-Polls', 'wp-polls' ) . '</label><br />' . "\n";
	}
	return $content;
}


// Function: Add WP-Polls General Stats To WP-Stats Page
function polls_page_general_stats( $content ) {
	$stats_display = get_option( 'stats_display' );
	if ( (int) ( $stats_display['polls'] ?? 0 ) === 1 ) {
		$content .= '<p><strong>' . __( 'WP-Polls', 'wp-polls' ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions( false ), 'wp-polls' ), number_format_i18n( get_pollquestions( false ) ) ) . '</li>' . "\n";
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers( false ), 'wp-polls' ), number_format_i18n( get_pollanswers( false ) ) ) . '</li>' . "\n";
		$content .= '<li>' . sprintf( _n( '<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', get_pollvotes( false ), 'wp-polls' ), number_format_i18n( get_pollvotes( false ) ) ) . '</li>' . "\n";
		$content .= '</ul>' . "\n";
	}
	return $content;
}


// Class: WP-Polls Widget
// Function: Init WP-Polls Widget
add_action( 'widgets_init', 'widget_polls_init' );
function widget_polls_init() {
	polls_textdomain();
	register_widget( 'Polls_Widget' );
}

if ( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}


// Function: Sanitize A 3 Or 6 Digit Hex Colour Stored Without Its Leading '#'
function _polls_sanitize_hex_color( $color ) {
	$color = substr( trim( (string) $color ), 0, 6 );

	if ( ! preg_match( '/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
		return '000000';
	}

	return $color;
}
