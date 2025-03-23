<?php
/**
 * WP-Polls TinyMCE Integration Functions
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
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
