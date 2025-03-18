<?php
### Check Whether User Can Manage Polls
if( ! current_user_can( 'manage_polls' ) ) {
    die( 'Access Denied' );
}


### Variables Variables Variables
$base_name = plugin_basename( 'wp-polls/polls-options.php' );
$base_page = 'admin.php?page=' . $base_name;
$id = isset( $_GET['id'] ) ? (int) sanitize_key( $_GET['id'] ) : 0;

### If Form Is Submitted
if( isset($_POST['Submit']) && $_POST['Submit'] ) {
    check_admin_referer('wp-polls_options');
    $default_templates_set_text         = !empty( $_POST['default_templates_set_text'] )    ? (int) sanitize_key( $_POST['default_templates_set_text'] )    : wp_polls_get_child_option('default_templates_set_text');
    $default_templates_set_object       = !empty( $_POST['default_templates_set_object'] )  ? (int) sanitize_key( $_POST['default_templates_set_object'] )  : wp_polls_get_child_option('default_templates_set_object');
    $global_poll_archive_perpage        = isset( $_POST['global_poll_archive_perpage'] ) ? (int) sanitize_key( $_POST['global_poll_archive_perpage'] ) : wp_polls_get_child_option('global_poll_archive_perpage');
    $global_poll_archive_displaypoll    = isset( $_POST['global_poll_archive_displaypoll'] ) ? (int) sanitize_key( $_POST['global_poll_archive_displaypoll'] ) : wp_polls_get_child_option('global_poll_archive_displaypoll');
    $global_poll_archive_url            = isset( $_POST['global_poll_archive_url'] ) ? esc_url_raw( strip_tags( trim( $_POST['global_poll_archive_url'] ) ) ) : wp_polls_get_child_option('global_poll_archive_url');
    $update_poll_queries[] = wp_polls_add_or_update_child_option('default_templates_set_text', $default_templates_set_text );
    $update_poll_queries[] = wp_polls_add_or_update_child_option('default_templates_set_object', $default_templates_set_object );
    $update_poll_queries[] = wp_polls_add_or_update_child_option('global_poll_archive_perpage', $global_poll_archive_perpage );
    $update_poll_queries[] = wp_polls_add_or_update_child_option('global_poll_archive_displaypoll', $global_poll_archive_displaypoll );
    $update_poll_queries[] = wp_polls_add_or_update_child_option('global_poll_archive_url', $global_poll_archive_url );
    $update_poll_text[] = __('Default Templates For Text Polls', 'wp-polls');
    $update_poll_text[] = __('Default Templates For Object Polls', 'wp-polls');
    $update_poll_text[] = __('Number Of Polls Per Page in Global Archive', 'wp-polls');
    $update_poll_text[] = __('Type Of Polls To Display In Global Archive', 'wp-polls');
    $update_poll_text[] = __('Global Archive URL', 'wp-polls');
    $i=0;
    $text = '';
    foreach($update_poll_queries as $update_poll_query) {
        if($update_poll_query) {
            $text .= '<p style="color: green;">'.$update_poll_text[$i].' '.__('Updated', 'wp-polls').'</p>';
        }
        $i++;
    }
    if(empty($text)) {
        $text = '<p style="color: red;">'.__('General Options Have Not Been Updated', 'wp-polls').'</p>';
    }
    cron_polls_place();
}
?>

<div class="wrap">
    <h1><?php echo __('General Options', 'wp-polls'); ?></h1>

    <?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; } ?>

    <div id="loader_container" class="loader-container wp-polls-hide">
        <span class="loader-spinner"></span>
    </div>
    <form id="poll_options_form" method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
        <?php wp_nonce_field('wp-polls_options'); ?>
        <div class="wrap">
            
            <!-- Add & Manage Polls -->
            <h3 style="display:inline-block"><?php _e('Add/Manage Polls Screens', 'wp-polls'); ?></h3>
            <table class="form-table">
                <tr>
                    <th width="20%" scope="row" valign="top">
                        <?php _e('Number of Items to Load in Object Answers Selection List:', 'wp-polls'); ?>
                    </th>
                    <td width="80%">
                        <input type="text" name="global_poll_archive_perpage" value="<?php echo (int) esc_attr( wp_polls_get_child_option('obj_answers_selection_posts_per_page')); ?>" size="2" />                     
                        <?php wp_polls_print_tooltip_markup(__('You can set it to <code>-1</code> to load all items at once and disable the infinite scroll effect. Note that this may affect performances if you have many items in the database.', 'wp-polls')); ?>
                    </td>
                </tr>
            </table>            
            
            <!-- General Templates Sets Options -->
            <h3><?php _e('General Templates Sets Options', 'wp-polls') ?></h3>
            <table class="form-table">
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Select A Default Templates Set For Polls With Text Answers Type', 'wp-polls'); ?></th>
                    <td width="80%">
                        <select name="default_templates_set_text" id="default_templates_set_text" size="1">
                            <?php echo wp_polls_list_available_templates_sets('select_list_options', -1, -1, 'text'); ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Select A Default Templates Set For Polls With Object Answers Type', 'wp-polls'); ?></th>
                    <td width="80%">
                        <select name="default_templates_set_object" id="default_templates_set_object" size="1">
                            <?php echo wp_polls_list_available_templates_sets('select_list_options', -1, -1, 'object'); ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Reset all Polls To Default Templates', 'wp-polls'); ?></th>
                    <td width="80%">
                        <input type="button" name="SetAllPollsToDefaultTemplates" value="<?php _e('Set All Polls To Default Templates', 'wp-polls') ?>" onclick="set_poll_to_default_templates(-1, '<?php _e('Are you sure you want to remove all existing associations between polls and templates sets and reset them to default?', 'wp-polls'); ?>', '<?php echo wp_create_nonce('wp-polls_set-poll-to-default-templates'); ?>');" class="button" />
                        <?php wp_polls_print_tooltip_markup(__('This will remove all associations between polls and templates sets and set all polls to follow the default templates defined hereinabove.', 'wp-polls')); ?>
                    </td>
                </tr>               
                <tr>
            </table>
            
            <!-- Global Poll Archive -->
            <h3 style="display:inline-block"><?php _e('Global Poll Archive', 'wp-polls'); ?></h3>
            <?php wp_polls_print_tooltip_markup(__('The scope of the Global Poll Archive encompasses all polls (mixing polls associated with all templates sets).', 'wp-polls')); ?>
            <table class="form-table">
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Number Of Polls Per Page:', 'wp-polls'); ?></th>
                    <td width="80%"><input type="text" name="global_poll_archive_perpage" value="<?php echo (int) esc_attr( wp_polls_get_child_option('global_poll_archive_perpage')); ?>" size="2" /></td>
                </tr>
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Type Of Polls To Display In Poll Archive:', 'wp-polls'); ?></th>
                    <td width="80%">
                        <select name="global_poll_archive_displaypoll" size="1">
                            <option value="1"<?php selected('1', wp_polls_get_child_option('global_poll_archive_displaypoll')); ?>><?php _e('Closed Polls Only', 'wp-polls'); ?></option>
                            <option value="2"<?php selected('2', wp_polls_get_child_option('global_poll_archive_displaypoll')); ?>><?php _e('Opened Polls Only', 'wp-polls'); ?></option>
                            <option value="3"<?php selected('3', wp_polls_get_child_option('global_poll_archive_displaypoll')); ?>><?php _e('Closed And Opened Polls', 'wp-polls'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th width="20%" scope="row" valign="top"><?php _e('Poll Archive URL:', 'wp-polls'); ?></th>
                    <td width="80%"><input type="text" name="global_poll_archive_url" value="<?php echo esc_url( wp_polls_get_child_option('global_poll_archive_url') ?? ''); ?>" size="50" dir="ltr" /></td>
                </tr>
            </table>            
            
            <br />
            
            <!-- Submit Button -->
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'wp-polls'); ?>" />
            </p>
        </div>
    </form>
</div>
