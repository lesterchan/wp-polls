<?php
### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
    die('Access Denied');
}

### Poll Manager
$base_name = plugin_basename('wp-polls/polls-manager.php');
$base_page = 'admin.php?page='.$base_name;

### Form Processing
if ( ! empty($_POST['do'] ) ) {
    // Decide What To Do
    switch ( $_POST['do'] ) {
        // Add Poll
        case __( 'Add Poll', 'wp-polls' ):
            check_admin_referer( 'wp-polls_add-poll' );
            $text = '';
            // Poll Question
            $pollq_question = isset( $_POST['pollq_question'] ) ? wp_kses_post( trim( $_POST['pollq_question'] ) ) : '';
            // Poll Answers
            $polla_answers = isset( $_POST['polla_answers'] ) ? $_POST['polla_answers'] : array();  
            if ( ! empty( $pollq_question ) && ! empty( array_filter($polla_answers) )) {
                // Polls answers type
                $pollq_expected_atype = isset( $_POST['pollq_expected_atype'] ) ? (string) sanitize_key( $_POST['pollq_expected_atype'] ) : 'text';
                // Poll Start Date
                $timestamp_sql = '';
                $pollq_timestamp_day = isset( $_POST['pollq_timestamp_day'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_day'] ) : 0;
                $pollq_timestamp_month = isset( $_POST['pollq_timestamp_month'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_month'] ) : 0;
                $pollq_timestamp_year = isset( $_POST['pollq_timestamp_year'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_year'] ) : 0;
                $pollq_timestamp_hour = isset( $_POST['pollq_timestamp_hour'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_hour'] ) : 0;
                $pollq_timestamp_minute = isset( $_POST['pollq_timestamp_minute'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_minute'] ) : 0;
                $pollq_timestamp_second = isset( $_POST['pollq_timestamp_second'] ) ? (int) sanitize_key( $_POST['pollq_timestamp_second'] ) : 0;
                $pollq_timestamp = gmmktime( $pollq_timestamp_hour, $pollq_timestamp_minute, $pollq_timestamp_second, $pollq_timestamp_month, $pollq_timestamp_day, $pollq_timestamp_year );
                if ( $pollq_timestamp > current_time( 'timestamp' ) ) {
                    $pollq_active = -1;
                } else {
                    $pollq_active = 1;
                }
                // Poll End Date
                $pollq_expiry_no = isset( $_POST['pollq_expiry_no'] ) ? (int) sanitize_key( $_POST['pollq_expiry_no'] ) : 0;
                if ( $pollq_expiry_no === 1 ) {
                    $pollq_expiry = 0;
                } else {
                    $pollq_expiry_day = isset( $_POST['pollq_expiry_day'] ) ? (int) sanitize_key( $_POST['pollq_expiry_day'] ) : 0;
                    $pollq_expiry_month = isset( $_POST['pollq_expiry_month'] ) ? (int) sanitize_key( $_POST['pollq_expiry_month'] ) : 0;
                    $pollq_expiry_year = isset( $_POST['pollq_expiry_year'] ) ? (int) sanitize_key( $_POST['pollq_expiry_year'] ) : 0;
                    $pollq_expiry_hour = isset( $_POST['pollq_expiry_hour'] ) ? (int) sanitize_key( $_POST['pollq_expiry_hour'] ) : 0;
                    $pollq_expiry_minute = isset( $_POST['pollq_expiry_minute'] ) ? (int) sanitize_key( $_POST['pollq_expiry_minute'] ) : 0;
                    $pollq_expiry_second = isset( $_POST['pollq_expiry_second'] ) ? (int) sanitize_key( $_POST['pollq_expiry_second'] ) : 0;
                    $pollq_expiry = gmmktime( $pollq_expiry_hour, $pollq_expiry_minute, $pollq_expiry_second, $pollq_expiry_month, $pollq_expiry_day, $pollq_expiry_year );
                    if ( $pollq_expiry <= current_time( 'timestamp' ) ) {
                        $pollq_active = 0;
                    }
                }
                // Mutilple Poll
                $pollq_multiple_yes = isset( $_POST['pollq_multiple_yes'] ) ? (int) sanitize_key( $_POST['pollq_multiple_yes'] ) : 0;
                $pollq_multiple = 0;
                if ( $pollq_multiple_yes === 1 ) {
                    $pollq_multiple = isset( $_POST['pollq_multiple'] ) ? (int) sanitize_key( $_POST['pollq_multiple'] ) : 0;
                } else {
                    $pollq_multiple = 0;
                }
                //Poll Template
                $pollq_templates_set_id = (int) sanitize_key($_POST['pollq_templates_set_id']);             
                
                // Insert Poll
                $add_poll_question = $wpdb->insert(
                    $wpdb->pollsq,
                    array(
                        'pollq_tplid'          => $pollq_templates_set_id,
                        'pollq_question'       => $pollq_question,
                        'pollq_expected_atype' => $pollq_expected_atype,
                        'pollq_timestamp'      => $pollq_timestamp,
                        'pollq_totalvotes'     => 0,
                        'pollq_active'         => $pollq_active,
                        'pollq_expiry'         => $pollq_expiry,
                        'pollq_multiple'       => $pollq_multiple,
                        'pollq_totalvoters'    => 0
                    ),
                    array(
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%d',
                        '%d',
                        '%d',                       
                        '%d'                        
                    )
                );
                if ( ! $add_poll_question ) {
                    $text .= '<p style="color: red;">' . sprintf(__('Error In Adding Poll \'%s\'.', 'wp-polls'), $pollq_question) . '</p>';
                } else {
                    // Add Poll Answers
                    $polla_qid = (int) $wpdb->insert_id;
                    $fields_already_saved_for_post_types = array(); //array of post types already looped through to avoid 'fields' duplicates. 
                    foreach ( $polla_answers as $polla_answer ) {
                        $polla_answer = wp_kses_post( trim( $polla_answer ) );
                        if ( ! empty( $polla_answer ) ) {
                            $add_poll_answers = $wpdb->insert(
                                $wpdb->pollsa,
                                array(
                                    'polla_qid'   => $polla_qid,
                                    'polla_answers'  => $polla_answer,
                                    'polla_votes'   => 0,
                                    'polla_atype'  => $pollq_expected_atype
                                    
                                ),
                                array(
                                    '%d',
                                    '%s',
                                    '%d',
                                    '%s'        
                                )
                            );
                            $answer_text = ($pollq_expected_atype === 'object') ? get_the_title($polla_answer) : $polla_answer;
                            if( ! $add_poll_answers ) {
                                $text .= '<p style="color: red;">'.sprintf(__('Error In Adding Poll\'s Answer \'%s\'.', 'wp-polls'), $answer_text).'</p>';
                            }
                        } else {
                            $text .= '<p style="color: red;">' . __( 'Poll\'s Answer is empty.', 'wp-polls' ) . '</p>';
                        }
                    }
                    $latest_pollid = wp_polls_update_latest_id($pollq_templates_set_id); //update lastest poll ID for both global and template specific options, and return the poll ID
                    
                    // If poll starts in the future use the correct poll ID
                    $latest_pollid = ( $latest_pollid < $polla_qid ) ? $polla_qid : $latest_pollid;
                    $shortcode_markup = '<input type="text" value=\'[poll id="' . $latest_pollid . '"]\' readonly="readonly" size="10" />';
                    if ( !empty( $text ) && (str_contains($text, '<p style="color: red;">') ) ){
                        $text .= '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' (ID: %s) added successfully, but there are some errors with the Poll\'s Answers. Embed this poll with the shortcode: %s or go back to <a href="%s">Manage Polls</a>', 'wp-polls' ), $pollq_question, $latest_pollid, $shortcode_markup, $shortcode_markup, $base_page ) .'</p>';
                    } elseif (str_contains($text, '<p style="color: blue;">')){
                        $text .= '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' (ID: %s) added successfully, but there are some warnings about the Poll\'s Answers. Embed this poll with the shortcode: %s or go back to <a href="%s">Manage Polls</a>', 'wp-polls' ), $pollq_question, $latest_pollid, $shortcode_markup, $shortcode_markup, $base_page ) .'</p>';
                    } else {
                        $text = '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' (ID: %s) added successfully. Embed this poll with the shortcode: %s or go back to <a href="%s">Manage Polls</a>', 'wp-polls' ), $pollq_question, $latest_pollid, $shortcode_markup, $base_page ) . '</p>';
                    }   
                    do_action( 'wp_polls_add_poll', $latest_pollid );
                    cron_polls_place();
                    
                    //Form was fully processed, now refresh the page to reset the form while passing result messages to the refreshed page.
                    set_transient('wp_polls_previous_form_submission_result', removeslashes($text), 1800);
                    echo("<script>location.href = '".$_SERVER['HTTP_REFERER']."'</script>"); //refresh page, using JS due to the added complexity of using PHP to do so (i.e. 'wp_redirect()' or 'header()' functions) while headers are being sent around 'wp_loaded' hook (i.e. earlier than this page's execution)
                }
            } else {
                if (empty($pollq_question)) $text .= '<p style="color: red;">' . __('Invalid Poll (Not Saved)', 'wp-polls') . ' - '. __( 'Poll Question is empty.', 'wp-polls' ) . '</p>';
                if (empty(array_filter($polla_answers))) $text .= '<p style="color: red;">' . __('Invalid Poll (Not Saved)', 'wp-polls') . ' - '. __( 'No Answers Provided.', 'wp-polls' ) . '</p>'; //if the array doesn't contain any key, or only with empty values (e.g. new empty answers). 
            }
            break;
    }
}

### Add Poll Form
$poll_noquestion = !empty($_POST['polla_answers']) ? count($_POST['polla_answers']) : 2;
$count = 0;
?>
<div id="loader_container" class="loader-container wp-polls-hide">
    <span class="loader-spinner"></span>
</div>
<?php 
$previous_form_submission_message = get_transient('wp_polls_previous_form_submission_result');
$text = !empty($text) ? $text : $previous_form_submission_message;
if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; }
if(!$_POST) delete_transient('wp_polls_previous_form_submission_result'); //if this is run after page refresh, message has been printed on the new page and transient can be deleted.
?>
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<?php wp_nonce_field('wp-polls_add-poll'); ?>
<div class="wrap">
    <h2><?php _e('Add Poll', 'wp-polls'); ?></h2>
    <!-- Poll Question -->
    <h3><?php _e('Poll Question', 'wp-polls'); ?></h3>
    <table class="form-table">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Question', 'wp-polls') ?></th>
            <td width="80%"><input type="text" size="70" name="pollq_question" value="<?php echo ($_POST["pollq_question"] ?? ""); ?>" /></td>
        </tr>
    </table>
    <!-- Poll Answers -->
    <h3><?php _e('Poll Answers', 'wp-polls'); ?></h3>
    
    <table id="pollq_answer_entries_type_selection" class="form-table ">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Answers\' entries type', 'wp-polls') ?></th>
            <td width="80%">
                <input type="radio" name="pollq_expected_atype" value="text" <?php checked((!isset($_POST["pollq_expected_atype"]) || (isset($_POST["pollq_expected_atype"]) && $_POST["pollq_expected_atype"] === 'text')), true, true) ?> onchange="check_poll_answer_entries_type(value, 'change');"/><label><?php _e('Text (default)', 'wp-polls') ?></label>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input type="radio" name="pollq_expected_atype" value="object" <?php checked((isset($_POST["pollq_expected_atype"]) && $_POST["pollq_expected_atype"] === 'object'), true, true) ?> onchange="check_poll_answer_entries_type(value, 'change');"/><label class="wp-polls-tooltip"><?php _e('Objects (post types items)', 'wp-polls') ?><label>
                <div class="wp-polls-tooltip-container">
                    <span class="wp-polls-tooltip-icon">?</span>
                    <p class="wp-polls-tooltip-info">
                        <span class="info"><?php echo '<strong>' . __('Text mode', 'wp-polls') . '</strong> ' . __('lets you manually type all the poll\'s answers.', 'wp-polls') . '<br/><br/><strong>' . __('Objects mode', 'wp-polls') . '</strong> ' . __('enables you to select post type items\' data (i.e. data already saved in your website) to serve as poll\'s answers (e.g. post type item\'s name, post type item\'s thumbnail, etc.).', 'wp-polls'); ?></span>
                    </p>
                </div>
            </td>
        </tr>
    </table>
    <table class="form-table">
        <tbody id="poll_answers">
        <?php
            $saved_objects_post_types = array();
            for($i = 1; $i <= $poll_noquestion; $i++) {
                $arr_index = $i-1;
                $answer_type = $_POST["pollq_expected_atype"] ?? "text" ;
                $answer_type = sanitize_text_field($answer_type);
                echo "<tr id=\"poll-answer-$i\" class=\"poll-unsaved-answers wp-polls-". $answer_type ."-answers\">\n";
                echo "<th width=\"20%\" scope=\"row\" valign=\"top\">".sprintf(__('Answer %s', 'wp-polls'), number_format_i18n($i))."</th>\n";
                if (!empty($answer_type) && $answer_type === 'object'){
                    $obj_id = $_POST["polla_answers"][$arr_index] ?? "";
                    $obj_id = sanitize_key($obj_id);
                    $obj_post_type = get_post_type($obj_id);
                    if (!in_array($obj_post_type, $saved_objects_post_types)) $saved_objects_post_types[] = $obj_post_type;
                    $obj_label = get_post_type_object($obj_post_type)->labels->name;
                    $obj_title = ucfirst(get_the_title($obj_id));
                    echo "<td width=\"80%\"><label for=\"checkbox_$obj_id\"><input id=\"checkbox_". $obj_id ."_selected\" type=\"checkbox\" name=\"polla_answers[]\" data-post-type=\"". $obj_post_type ."\" value=\"" . $obj_id . "\" checked=\"checked\"><span><span class=\"wp-polls-lighter-color\">[". $obj_label ."] </span>".$obj_title."</span></label>&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"".__('Remove', 'wp-polls')."\" onclick=\"remove_poll_answer_add(".$i.", 'object');\" class=\"button\" /></td>\n";
                } else {
                    $answer_text = $_POST["polla_answers"][$arr_index] ?? "";
                    $answer_text = ($answer_text) ? sanitize_text_field($answer_text) : "";
                    echo "<td width=\"80%\"><input id=\"polla_text_$i\" type=\"text\" size=\"50\" maxlength=\"200\" name=\"polla_answers[]\" value=\"" . $answer_text . "\"/>&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"".__('Remove', 'wp-polls')."\" onclick=\"remove_poll_answer_add(".$i.");\" class=\"button\" /></td>\n";
                }
                echo "</tr>\n";
                $count++;
            }
        ?>
        </tbody>
    </table>
    <table id="pollq_expected_atype_text" class="form-table pollq-answers-entries-type-table">
        <tfoot>
            <tr>
                <td width="20%">&nbsp;</td>
                <td width="80%"><input type="button" value="<?php _e('Add Answer', 'wp-polls') ?>" onclick="add_poll_answer_add();" class="button" /></td>
            </tr>
        </tfoot>
    </table>
    <table id="pollq_expected_atype_object" class="form-table pollq-answers-entries-type-table">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Select items to be used as answers', 'wp-polls'); ?></th>
            <td width="80%" id="obj_answers">
                <br>
                <div id="wp_polls_ajax_parent_container" class="wp-polls-ajax-parent-container">
                    <?php 
                    $post_type_args = array('public' => true);
                    $post_types_objects = get_post_types($post_type_args, 'objects');
                    $post_types_objects = apply_filters('wp_polls_admin_list_post_types', $post_types_objects);
                    ?>
                    <span class="wp-polls-multiselect-group">
                        <label for="pollq_post_types_list"><?php _e('Select post type(s)', 'wp-polls'); ?></label>
                        <div id="pollq_post_types_list" class="wp-polls-multiselect">
                            <div id="pollq_post_types_list_label" class="wp-polls-multiselect-selectBox" onclick="toggle_checkbox_area()">
                                <select class="wp-polls-multiselect-form-select">
                                    <option>0 <?php _e('selected', 'wp-polls'); ?></option>
                                </select>
                                <div class="wp-polls-multiselect-overSelect"></div>
                            </div>
                            <div id="pollq_post_types_list_options" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content');?>" data-values="">
                                <?php 
                                $post_types_names = array();
                                foreach ($post_types_objects as $post_types_obj) { ?>
                                    <div class="outer-wrapper">
                                      <div class="inner-wrapper">
                                        <?php echo '<label for="' . $post_types_obj->name . '"><input type="checkbox" id="' . $post_types_obj->name . '" onchange="wp_polls_delay(function() { post_types_multiselect_dropdown_change() }, 100);" value="' . $post_types_obj->name . '" /> ' . $post_types_obj->labels->name . '</label>'; ?>
                                      </div>
                                    </div>
                                <?php
                                    $post_types_names[] = $post_types_obj->name;
                                } 
                                ?>                  
                            </div>
                        </div>
                    </span>
                    <input type="text" name="keyword" id="pollq_search_keyword" placeholder="<?php _e('Search items by title','wp_polls'); ?>" onkeyup="wp_polls_delay(function() {retrieve_content('post_types_items', '<?php echo wp_create_nonce('wp-polls_retrieve-content'); ?>', 'keyword')}, 1000);"></input>
                    <label for="taxonomies_list"><?php _e('Search by taxonomy', 'wp-polls'); ?></label>
                    <select disabled="" name="tax_list" id="pollq_tax_list" onchange="wp_polls_delay(function() {retrieve_content('terms', '<?php echo wp_create_nonce('wp-polls_retrieve-content'); ?>', 'tax_select')}, 400); ">
                        <?php wp_polls_list_allowed_tax($post_types_names, true); ?>
                    </select>
                </div>
                <div id="wp_polls_ajax_results_parent_container" class="wp-polls-ajax-parent-container">
                    <div id="pollq_terms_results_container" class="wp-polls-dropdown wp-polls-ajax-box">
                        <div class="wp-polls-dropdown-input-containers">
                            <input type="text" placeholder="<?php _e('Filter terms', 'wp-polls'); ?>" id="terms_search_input" class="wp-polls-dropdown-filter" onkeydown="if (event.keyCode == 13) {return false;}" onkeyup="filter_items('terms_search_input', 'pollq_terms_results', 'input[type=&quot;radio&quot;]')">
                        </div>
                        <div id="pollq_terms_results" class="wp-polls-dropdown-content wp-polls-ajax-placeholder-container" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content');?>">
                        </div>
                    </div>
                    <div id="pollq_posts_items_select_container" class="wp-polls-dropdown wp-polls-ajax-box wp-polls-ajax-selected" data-nonce="<?php echo wp_create_nonce('wp-polls_retrieve-content');?>">
                        <div class="wp-polls-dropdown-input-containers">
                            <input type="text" placeholder="<?php _e('Filter titles', 'wp-polls'); ?>" id="items_search_input" class="wp-polls-dropdown-filter" onkeydown="if (event.keyCode == 13) {return false;}" onkeyup="filter_items('items_search_input', 'pollq_posts_items_select_results', 'input[type=&quot;checkbox&quot;]')">
                        </div>
                        <div id="pollq_posts_items_select_results" class="wp-polls-dropdown-content wp-polls-ajax-placeholder-container">
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    <!-- Poll Multiple Answers -->
    <h3><?php _e('Poll Multiple Answers', 'wp-polls') ?></h3>
    <table class="form-table">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Allows Users To Select More Than One Answer?', 'wp-polls'); ?></th>
            <td width="80%">
                <select name="pollq_multiple_yes" id="pollq_multiple_yes" size="1" onchange="check_pollq_multiple();">
                    <option value="0" <?php selected((!isset($_POST["pollq_multiple_yes"]) || (isset($_POST["pollq_multiple_yes"]) && $_POST["pollq_multiple_yes"] == 0)), true, true) ?> ><?php _e('No', 'wp-polls'); ?></option>
                    <option value="1" <?php selected((isset($_POST["pollq_multiple_yes"]) && $_POST["pollq_multiple_yes"] == 1), true, true) ?> ><?php _e('Yes', 'wp-polls'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Maximum Number Of Selected Answers Allowed?', 'wp-polls') ?></th>
            <td width="80%">
                <select name="pollq_multiple" id="pollq_multiple" size="1" disabled="disabled">
                    <?php
                        for($i = 1; $i <= $poll_noquestion; $i++) {
                            echo "<option value=\"$i\" " . selected((isset($_POST["pollq_multiple"]) && $_POST["pollq_multiple"] == $i), true, true) . ">".number_format_i18n($i)."</option>\n";
                        }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    <!-- Poll Template Set -->
    <h3><?php _e('Poll Templates Set', 'wp-polls') ?></h3>
    <table class="form-table">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Select A Templates Set For This Poll', 'wp-polls'); ?></th>
            <td width="80%">
                <select name="pollq_templates_set_id" id="pollq_templates_set_id" size="1">
                <?php 
                    $previously_selected = (isset($_POST['pollq_templates_set_id'])) ? $_POST['pollq_templates_set_id'] : ''; 
                    echo wp_polls_list_available_templates_sets('select_list_options', -1, -1, '', $previously_selected); 
                ?>
                </select>
            </td>
        </tr>
        <tr>
    </table>
    <!-- Poll Start/End Date -->
    <h3><?php _e('Poll Start/End Date', 'wp-polls'); ?></h3>
    <table class="form-table">
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('Start Date/Time', 'wp-polls') ?></th>
            <?php $poll_timestamp = ( !empty($_POST["pollq_timestamp_day"]) && !empty($_POST["pollq_timestamp_month"]) && !empty($_POST["pollq_timestamp_year"]) && !empty($_POST["pollq_timestamp_hour"]) && !empty($_POST["pollq_timestamp_minute"]) && !empty($_POST["pollq_timestamp_second"]) ) ? strtotime($_POST["pollq_timestamp_year"].'-'.$_POST["pollq_timestamp_month"].'-'.$_POST["pollq_timestamp_day"].' '.$_POST["pollq_timestamp_hour"].':'.$_POST["pollq_timestamp_minute"].':'.$_POST["pollq_timestamp_second"]) : current_time('timestamp'); ?>
            <td width="80%"><?php poll_timestamp($poll_timestamp); ?></td>
        </tr>
        <tr>
            <th width="20%" scope="row" valign="top"><?php _e('End Date/Time', 'wp-polls') ?></th>
            <?php $poll_expiry_timestamp = ( empty($_POST["pollq_expiry_no"]) && !empty($_POST["pollq_expiry_day"]) && !empty($_POST["pollq_expiry_month"]) && !empty($_POST["pollq_expiry_year"]) && !empty($_POST["pollq_expiry_hour"]) && !empty($_POST["pollq_expiry_minute"]) && !empty($_POST["pollq_expiry_second"]) ) ? strtotime($_POST["pollq_expiry_year"].'-'.$_POST["pollq_expiry_month"].'-'.$_POST["pollq_expiry_day"].' '.$_POST["pollq_expiry_hour"].':'.$_POST["pollq_expiry_minute"].':'.$_POST["pollq_expiry_second"]) : current_time('timestamp'); ?>
            <td width="80%"><input type="checkbox" name="pollq_expiry_no" id="pollq_expiry_no" value="1" <?php if( empty($_POST) || isset($_POST['pollq_expiry_no'] )) { echo 'checked="checked"'; } ?> onclick="check_pollexpiry();" />&nbsp;&nbsp;<label for="pollq_expiry_no"><?php _e('Do NOT Expire This Poll', 'wp-polls'); ?></label><?php poll_timestamp($poll_expiry_timestamp, 'pollq_expiry', 'none'); ?></td>
        </tr>
    </table>
    <!-- Submit Button -->      
    <p style="text-align: center;"><input type="submit" name="do" value="<?php _e('Add Poll', 'wp-polls'); ?>"  class="button-primary" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="javascript:history.go(-1)" /></p>
</div>
</form>
