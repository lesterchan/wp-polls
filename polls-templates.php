<?php
### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
	die('Access Denied');
}
# Allow HTML
$allowed_tags = wp_kses_allowed_html( 'post' );
$allowed_tags['input'] = array(
	'type'      => true,
	'id'        => true,
	'name'      => true,
	'value'     => true,
	'class'     => true,
	'onclick'   => true,
	'onchange'  => true,
);
$allowed_tags['a']['onclick'] = true;

### Variables Variables Variables
$base_name = plugin_basename('wp-polls/polls-templates.php');
$base_page = 'admin.php?page='.$base_name;
$templates_set_id = 0;
if( isset($_GET['tpl_id']) ){
	$tpl_id_to_check = (int) sanitize_key( $_GET['tpl_id'] );
	$db_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->pollstpl WHERE polltpl_id = %d", $tpl_id_to_check) );
	if ($db_count>0) $templates_set_id = $tpl_id_to_check;
}
$mode = ( isset( $_GET['mode'] ) && !empty($templates_set_id) ) ? sanitize_key( trim( $_GET['mode'] ) ) : '';

### Get Poll Bar Images
$pollbar_path = WP_PLUGIN_DIR . '/wp-polls/images';
$poll_bars = array();
if( $handle = @opendir( $pollbar_path ) ) {
	while( false !== ( $filename = readdir( $handle ) ) ) {
		if( substr( $filename, 0, 1 ) !== '.' && substr( $filename, 0, 2 ) !== '..' ) {
			if( is_dir( $pollbar_path.'/'.$filename ) ) {
				$poll_bars[$filename] = getimagesize( $pollbar_path . '/' . $filename . '/pollbg.gif' );
			}
		}
	}
	closedir( $handle );
}

### Form Processing
if(!empty($_POST['do'])) {
	$update_poll_queries = array();
	$update_poll_text = array();
	$is_details_query = false;
	$polla_unsaved_fields_str = '';
	
	// Decide What To Do
	switch($_POST['do']) {
		
		// Edit Templates Details
		case __('Edit Templates Set Details', 'wp-polls'):
			check_admin_referer( 'wp-polls_templates_details' );
			$is_details_query = true;
			
			// Get submitted fields
			$templates_set_id 						= isset( $_POST['templates_set_id'] ) ? (int) sanitize_key( $_POST['templates_set_id'] ) : 0;
			$templates_set_name 					= isset( $_POST['poll_templates_set_name'] ) ? (string) sanitize_text_field( $_POST['poll_templates_set_name'] ) : __('Templates Set', 'wp-polls').' #'.$templates_set_id;
			$poll_answers_type 						= isset( $_POST['poll_answers_type'] ) ? (string) sanitize_key( $_POST['poll_answers_type'] ) : 'text';	
			$previous_poll_answers_type 			= isset( $_POST['previous_poll_answers_type'] ) ? (string) sanitize_key( $_POST['previous_poll_answers_type'] ) : 'text';	
			$object_fields_just_checked_str 		= isset( $_POST['poll_ans_obj_fields'] ) ? (string) sanitize_text_field( $_POST['poll_ans_obj_fields'] ) : ''; //JSON encoded string representing an object of arrays such as '{"post_type_1":["field_1","field_2"],"post_type_2":["field_1","field_4"]}'
			
			// Prepare query data
			$data_col = array(
								'polltpl_set_name'  						=> $templates_set_name,
								'polltpl_answers_type'  					=> $poll_answers_type,
			);
			$data_where = array(
								'polltpl_id' 								=> $templates_set_id,
			);
			$format_col = array(
								'%s',
								'%s',
			);
			$format_where = array(
								'%d',
			);
			$update_poll_text[] = __('Templates Set Details', 'wp-polls');
			//~ $update_poll_text[] = __('Templates Set Name', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Templates Answers Type', 'wp-polls');
			break;
 
		// Edit Templates Structure
		case __('Edit Templates Structure', 'wp-polls'):
			check_admin_referer( 'wp-polls_templates_structure' );
			 
			// Get submitted fields
			$templates_set_id 						= isset( $_POST['templates_set_id'] ) ? (int) sanitize_key( $_POST['templates_set_id'] ) : 0;
			$template_voteheader 					= wp_kses_post( trim( $_POST['poll_template_voteheader'] ) );
			$template_votebody 						= wp_kses( $_POST['poll_template_votebody'], $allowed_tags );
			$template_votefooter 					= wp_kses( $_POST['poll_template_votefooter'], $allowed_tags );
			$template_resultheader 					= wp_kses_post( trim($_POST['poll_template_resultheader'] ) );
			$template_resultbody 					= wp_kses_post( trim($_POST['poll_template_resultbody'] ) );
			$template_resultbody2 					= wp_kses_post( trim($_POST['poll_template_resultbody2'] ) );
			$template_resultfooter 					= wp_kses( trim($_POST['poll_template_resultfooter'] ), $allowed_tags );
			$template_resultfooter2 				= wp_kses( trim($_POST['poll_template_resultfooter2'] ), $allowed_tags );
			$template_pollarchivelink 				= wp_kses_post( trim($_POST['poll_template_pollarchivelink'] ) );
			$template_pollarchiveheader 			= wp_kses_post( trim($_POST['poll_template_pollarchiveheader'] ) ); 
			$template_pollarchivefooter 			= wp_kses_post( trim($_POST['poll_template_pollarchivefooter'] ) );
			$template_pollarchivepagingheader 		= wp_kses_post( trim($_POST['poll_template_pollarchivepagingheader'] ) );
			$template_pollarchivepagingfooter 		= wp_kses_post( trim($_POST['poll_template_pollarchivepagingfooter'] ) );
			$template_disable 						= wp_kses_post( trim($_POST['poll_template_disable'] ) );
			$template_error 						= wp_kses_post( trim($_POST['poll_template_error'] ) );
			$template_aftervote 					= wp_kses_post( trim($_POST['poll_template_aftervote'] ) );
			
			// Prepare query data
			$update_poll_queries = array();
			$data_col = array(
								'polltpl_template_voteheader'  				=> $template_voteheader,
								'polltpl_template_votebody' 				=> $template_votebody,
								'polltpl_template_votefooter'  				=> $template_votefooter,
								'polltpl_template_resultheader'				=> $template_resultheader,
								'polltpl_template_resultbody'				=> $template_resultbody,
								'polltpl_template_resultbody2'				=> $template_resultbody2,
								'polltpl_template_resultfooter'				=> $template_resultfooter,
								'polltpl_template_resultfooter2'			=> $template_resultfooter2,
								'polltpl_template_pollarchivelink'			=> $template_pollarchivelink,
								'polltpl_template_pollarchiveheader'		=> $template_pollarchiveheader,
								'polltpl_template_pollarchivefooter'		=> $template_pollarchivefooter,
								'polltpl_template_pollarchivepagingheader'	=> $template_pollarchivepagingheader,
								'polltpl_template_pollarchivepagingfooter'	=> $template_pollarchivepagingfooter,
								'polltpl_template_disable'  				=> $template_disable,
								'polltpl_template_error'					=> $template_error,
								'polltpl_template_aftervote'				=> $template_aftervote,
			);
			$data_where = array(
								'polltpl_id' 								=> $templates_set_id,
			);
			$format_col = array(
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
			);
			$format_where = array(
								'%d',
			);
			$update_poll_text[] = __('Templates Structure', 'wp-polls');
			//~ $update_poll_text[] = __('Voting Form Header Template', 'wp-polls');
			//~ $update_poll_text[] = __('Voting Form Body Template', 'wp-polls');
			//~ $update_poll_text[] = __('Voting Form Footer Template', 'wp-polls');
			//~ $update_poll_text[] = __('Result Header Template', 'wp-polls');
			//~ $update_poll_text[] = __('Result Body Template', 'wp-polls');
			//~ $update_poll_text[] = __('Result Body2 Template', 'wp-polls');
			//~ $update_poll_text[] = __('Result Footer Template', 'wp-polls');
			//~ $update_poll_text[] = __('Result Footer2 Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive Link Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive Poll Header Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive Poll Footer Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive Paging Header Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive Paging Footer Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Disabled Template', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Error Template', 'wp-polls');	
			break;
			
		// Edit Templates Settings
		case __('Edit Templates Set Settings', 'wp-polls'):
			 check_admin_referer( 'wp-polls_templates_settings' );

			// Get submitted fields
			$templates_set_id 						= isset( $_POST['templates_set_id'] ) ? (int) sanitize_key( $_POST['templates_set_id'] ) : 0;
			$poll_bar_style             			= isset( $_POST['poll_bar_style'] ) && in_array( $_POST['poll_bar_style'], array_merge( array_keys( $poll_bars ), array( 'use_css' ) ), true ) ? $_POST['poll_bar_style'] : wp_polls_get_default_template_string('poll_bar_style', $poll_answers_type);
			$poll_bar_background        			= isset( $_POST['poll_bar_background'] ) ? substr( strip_tags( trim( $_POST['poll_bar_background'] ) ), 0, 6 ) : wp_polls_get_default_template_string('poll_bar_background', $poll_answers_type);
			$poll_bar_border 			            = isset( $_POST['poll_bar_border'] ) ? substr( strip_tags( trim( $_POST['poll_bar_border'] ) ), 0, 6 ) : wp_polls_get_default_template_string('poll_bar_border', $poll_answers_type);
			$poll_bar_height            			= isset( $_POST['poll_bar_height'] ) ? (int) sanitize_key( $_POST['poll_bar_height'] ) : wp_polls_get_default_template_string('poll_bar_height', $poll_answers_type);
			$poll_bar			                    = array(
															'style'         => $poll_bar_style,
															'background'    => $poll_bar_background,
															'border'        => $poll_bar_border,
															'height'        => $poll_bar_height
													);
			$poll_ajax_style_loading				= isset( $_POST['poll_ajax_style_loading'] ) ? (int) sanitize_key( $_POST['poll_ajax_style_loading'] ) : wp_polls_get_default_template_string('poll_ajax_style_loading', $poll_answers_type);
			$poll_ajax_style_fading					= isset( $_POST['poll_ajax_style_fading'] ) ? (int) sanitize_key( $_POST['poll_ajax_style_fading'] ) : wp_polls_get_default_template_string('poll_ajax_style_fading', $poll_answers_type);
			$poll_ajax_style 						= array(
															'loading'   => $poll_ajax_style_loading,
															'fading'    => $poll_ajax_style_fading
													);
			$poll_ans_sortby            			= isset( $_POST['poll_ans_sortby'] ) && in_array( $_POST['poll_ans_sortby'], array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ), true ) ? $_POST['poll_ans_sortby'] : 'polla_aid';
			$poll_ans_sortorder         			= isset( $_POST['poll_ans_sortorder'] ) && in_array( $_POST['poll_ans_sortorder'], array( 'asc', 'desc' ), true ) ? $_POST['poll_ans_sortorder'] : wp_polls_get_default_template_string('poll_ans_sortorder', $poll_answers_type);
			$poll_ans_result_sortby     			= isset( $_POST['poll_ans_result_sortby'] ) && in_array( $_POST['poll_ans_result_sortby'], array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ), true ) ? $_POST['poll_ans_result_sortby'] : wp_polls_get_default_template_string('poll_ans_result_sortby', $poll_answers_type);
			$poll_ans_result_sortorder  			= isset( $_POST['poll_ans_result_sortorder'] ) && in_array( $_POST['poll_ans_result_sortorder'], array( 'asc', 'desc' ), true ) ? $_POST['poll_ans_result_sortorder'] : wp_polls_get_default_template_string('poll_ans_result_sortorder', $poll_answers_type);
			$poll_allowtovote  						= isset( $_POST['poll_allowtovote'] ) ? (int) sanitize_key( $_POST['poll_allowtovote'] ) : wp_polls_get_default_template_string('poll_allowtovote', $poll_answers_type);
			$poll_aftervote  						= isset( $_POST['poll_aftervote'] ) ? (int) sanitize_key( $_POST['poll_aftervote'] ) : wp_polls_get_default_template_string('poll_aftervote', $poll_answers_type);
			$poll_logging_method  					= isset( $_POST['poll_logging_method'] ) ? (int) sanitize_key( $_POST['poll_logging_method'] ) : wp_polls_get_default_template_string('poll_logging_method', $poll_answers_type);
			$poll_cookielog_expiry  				= isset( $_POST['poll_cookielog_expiry'] ) ? (int) sanitize_key ($_POST['poll_cookielog_expiry'] ) : wp_polls_get_default_template_string('poll_cookielog_expiry', $poll_answers_type);
			$poll_ip_header  						= isset( $_POST['poll_ip_header'] ) ? sanitize_text_field( $_POST['poll_ip_header'] ) : wp_polls_get_default_template_string('poll_ip_header', $poll_answers_type);
			$poll_archive_perpage  					= isset( $_POST['poll_archive_perpage'] ) ? (int) sanitize_key( $_POST['poll_archive_perpage'] ) : wp_polls_get_default_template_string('poll_archive_perpage', $poll_answers_type);
			$poll_archive_displaypoll 				= isset( $_POST['poll_archive_displaypoll'] ) ? (int) sanitize_key( $_POST['poll_archive_displaypoll'] ) : wp_polls_get_default_template_string('poll_archive_displaypoll', $poll_answers_type);
			$poll_archive_url  						= isset( $_POST['poll_archive_url'] ) ? esc_url_raw( strip_tags( trim( $_POST['poll_archive_url'] ) ) ) : wp_polls_get_default_template_string('poll_archive_url', $poll_answers_type);
			$poll_currentpoll  						= isset( $_POST['poll_currentpoll'] ) ? (int) sanitize_key( $_POST['poll_currentpoll'] ) : wp_polls_get_default_template_string('poll_currentpoll', $poll_answers_type);
			$poll_close  							= isset( $_POST['poll_close'] ) ? (int) sanitize_key( $_POST['poll_close'] ) : wp_polls_get_default_template_string('poll_close', $poll_answers_type);
			
			// Prepare query data
			$update_poll_queries = array();
			$data_col = array(
								'polltpl_bar_style' 						=> $poll_bar['style'],
								'polltpl_bar_background'					=> $poll_bar['background'],
								'polltpl_bar_border' 						=> $poll_bar['border'],
								'polltpl_bar_height' 						=> $poll_bar['height'],
								'polltpl_ajax_style_loading' 				=> $poll_ajax_style['loading'],
								'polltpl_ajax_style_fading' 				=> $poll_ajax_style['fading'],
								'polltpl_ans_sortby' 						=> $poll_ans_sortby,
								'polltpl_ans_sortorder' 					=> $poll_ans_sortorder,
								'polltpl_ans_result_sortby' 				=> $poll_ans_result_sortby,
								'polltpl_ans_result_sortorder'				=> $poll_ans_result_sortorder,
								'polltpl_allowtovote' 						=> $poll_allowtovote,
								'polltpl_aftervote' 						=> $poll_aftervote,
								'polltpl_logging_method'					=> $poll_logging_method,
								'polltpl_cookielog_expiry' 					=> $poll_cookielog_expiry,
								'polltpl_ip_header' 						=> $poll_ip_header,
								'polltpl_archive_perpage'					=> $poll_archive_perpage,
								'polltpl_archive_displaypoll'				=> $poll_archive_displaypoll,
								'polltpl_archive_url'						=> $poll_archive_url,
								'polltpl_currentpoll'						=> $poll_currentpoll,
								'polltpl_close'								=> $poll_close,
			);
			$data_where = array(
								'polltpl_id' 								=> $templates_set_id,
			);
			$format_col = array(
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
			);
			$format_where = array(
								'%d',
			);
			$update_poll_text[] = __('Templates Set Settings', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Bar Style', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Bar Style', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Bar Style', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Bar Style', 'wp-polls');
			//~ $update_poll_text[] = __('Poll AJAX Style', 'wp-polls');
			//~ $update_poll_text[] = __('Poll AJAX Style', 'wp-polls');
			//~ $update_poll_text[] = __('Sort Poll Answers By Option', 'wp-polls');
			//~ $update_poll_text[] = __('Sort Order Of Poll Answers Option', 'wp-polls');
			//~ $update_poll_text[] = __('Sort Poll Results By Option', 'wp-polls');
			//~ $update_poll_text[] = __('Sort Order Of Poll Results Option', 'wp-polls');
			//~ $update_poll_text[] = __('Allow To Vote Option', 'wp-polls');
			//~ $update_poll_text[] = __('Logging Method', 'wp-polls');
			//~ $update_poll_text[] = __('Cookie And Log Expiry Option', 'wp-polls');
			//~ $update_poll_text[] = __('IP Header', 'wp-polls');
			//~ $update_poll_text[] = __('Number Of Polls Per Page To Display In Poll Archive', 'wp-polls');
			//~ $update_poll_text[] = __('Type Of Polls To Display In Poll Archive', 'wp-polls');
			//~ $update_poll_text[] = __('Poll Archive URL', 'wp-polls');
			//~ $update_poll_text[] = __('Current Active Poll', 'wp-polls');
			//~ $update_poll_text[] = __('When Poll Is Closed', 'wp-polls');
			break;			
	}

	// Execute Update Query 
	$update_poll_queries[] = $wpdb->update($wpdb->pollstpl, $data_col, $data_where, $format_col, $format_where);
	$i=0;
	$text = '';
	foreach($update_poll_queries as $key => $update_poll_query) {
		if($update_poll_query) {
			$text .= '<p style="color: green;">'.$update_poll_text[$i].' '.__('Updated', 'wp-polls').'</p>';
		}
		$i++;
	}
	// Update Object Fields' Table As Per The Fields Checked In The Templates Details' Form (object type only)
	if( ($is_details_query) && ($poll_answers_type === 'object') ) {
		$poll_saved_fields = array();
		$poll_saved_fields_array_of_obj = $wpdb->get_results( $wpdb->prepare( "SELECT pollaof_optype, pollaof_obj_field FROM $wpdb->pollsaof WHERE pollaof_tplid = %d ORDER BY pollaof_aofid ASC", $templates_set_id ) );
		foreach ($poll_saved_fields_array_of_obj as $obj){
			$poll_saved_fields[$obj->pollaof_optype][] = $obj->pollaof_obj_field; //associative array such as '["post_type_1" => ["field_1","field_2"], "post_type_2" => ["field_1", "field_4"]'}
		}								
		if (empty($object_fields_just_checked_str)) {
			$object_fields_just_checked_str = wp_polls_get_default_template_string('poll_ans_obj_fields', 'object');
			$text = '<p style="color: blue;">'.__('Object Answers Fields cannot be left all unchecked - falling back to default fields', 'wp-polls').'</p>';
		}
		$object_fields_just_checked = array();
		$object_fields_just_checked = json_decode(html_entity_decode(stripslashes($object_fields_just_checked_str)), true); //associative array such as '["post_type_1" => ["field_1","field_2"], "post_type_2" => ["field_1", "field_4"]'
		$all_fields = array_merge($poll_saved_fields, $object_fields_just_checked); //this merges the first level of the array, but will not merge sub-elements (entries of an array of arrays) even if two first level array with same key have different sub-elements.  
		foreach($poll_saved_fields as $post_type => $fields){ 
			if (array_key_exists($post_type, $object_fields_just_checked)) $all_fields[$post_type] = array_unique(array_merge($fields,$object_fields_just_checked[$post_type])); //merge sub-elements.
		}
		$wpdb_operations = array();
		foreach($all_fields as $post_type => $fields){
			$post_type_obj = get_post_type_object($post_type);
			$post_type_singular_name = $post_type_obj->labels->singular_name;
			if (!array_key_exists($post_type, $object_fields_just_checked)){ //saved post type not related to any new field 
				foreach($fields as $field){
					$wpdb_operations[$post_type_singular_name.'___'.$field.'___delete'] = $wpdb->delete( $wpdb->pollsaof, array( 'pollaof_tplid' => $templates_set_id, 'pollaof_optype' => $post_type, 'pollaof_obj_field' => $field ), array( '%d', '%s', '%s' ) ); //delete all fields entries in $fields for this poll ID and this post type.
				}
			} elseif (!array_key_exists($post_type, $poll_saved_fields)){ //new post type not related to any saved field  
				foreach($fields as $field){ 
					$wpdb_operations[$post_type_singular_name.'___'.$field.'___insert'] = $wpdb->insert( $wpdb->pollsaof, array( 'pollaof_tplid' => $templates_set_id, 'pollaof_optype' => $post_type, 'pollaof_obj_field' => $field ), array( '%d', '%s', '%s' ) ); //add all fields in $fields for this poll ID and this post type
				}
			} else { //saved post type also in new
				foreach($fields as $field){
					if (!in_array($field, $object_fields_just_checked[$post_type])){ //field not in the list of fields to keep 
						$wpdb_operations[$post_type_singular_name.'___'.$field.'___delete'] = $wpdb->delete( $wpdb->pollsaof, array( 'pollaof_tplid' => $templates_set_id, 'pollaof_optype' => $post_type, 'pollaof_obj_field' => $field ), array( '%d', '%s', '%s' ) ); //delete this field for this poll ID and this post type.
					} elseif (!in_array($field, $poll_saved_fields[$post_type])){ //field not in the list of fields already saved  
						$wpdb_operations[$post_type_singular_name.'___'.$field.'___insert'] = $wpdb->insert( $wpdb->pollsaof, array( 'pollaof_tplid' => $templates_set_id, 'pollaof_optype' => $post_type, 'pollaof_obj_field' => $field ), array( '%d', '%s', '%s' ) ); //add this field for this poll ID and this post type
					} //else do nothing, the field is both into saved and new, i.e. already saved and meant to remain.		
				}
			}
		}
		foreach($wpdb_operations as $op_key => $op_result_bool){
			$op_result_arr = explode('___', $op_key);
			$op_result_name = "\"".$op_result_arr[1]."\" (". $op_result_arr[0] .")";
			if (empty($op_result_bool)){
				 $text .= '<p style="color: red;">' . sprintf(__('Error in adding Poll\'s Answer\'s field %s.', 'wp-polls'), $op_result_name) . '</p>';
			} else {
				 switch($op_result_arr[2]){
					 case 'delete':
						$text .= '<p style="color: green;">' . sprintf(__('Poll\'s Answer\'s field %s removed successfully.', 'wp-polls'), $op_result_name) . '</p>';
						break;
					 case 'insert':
						$text .= '<p style="color: green;">' . sprintf(__('Poll\'s Answer\'s field %s added successfully.', 'wp-polls'), $op_result_name) . '</p>';
						break;
				}
			}
		}	
	}
	
	//If a Default Template's type was changed, set a new default template for the type it used to be the default one and update associated polls.
	if( ($is_details_query) && ($poll_answers_type != $previous_poll_answers_type)) {
		wp_polls_replace_templates_set_for_associated_polls($templates_set_id, $previous_poll_answers_type); 
	}
	
	if(empty($text)) {
		$text = '<p style="color: blue;">'.__('No Changes Had Been Made To Templates Set', 'wp-polls').'</p>';
	}
	
	wp_clear_scheduled_hook('polls_cron');
	if (!wp_next_scheduled('polls_cron')) {
		wp_schedule_event(time(), 'daily', 'polls_cron');
	}	
}

### Determines Which Mode It Is
switch($mode) {
	// Mode - Edit A Templates Set
	case 'edit':
		$base_page_path = "?page=wp-polls%2Fpolls-templates.php&amp;mode=edit&tpl_id=$templates_set_id";
		$default_tab = 'details';
		$active_tab = (isset($_GET['tab'])) ? sanitize_key( trim( $_GET['tab'] )) : $default_tab;
		$restore_button_name = __('Restore Built-in Template', 'wp-polls');
		$templates_set_obj = $wpdb->get_row( $wpdb->prepare( "SELECT 					
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
																polltpl_template_aftervote, 
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
																polltpl_aftervote,
																polltpl_logging_method,
																polltpl_cookielog_expiry,
																polltpl_ip_header,
																polltpl_archive_perpage,
																polltpl_archive_displaypoll,
																polltpl_archive_url,
																polltpl_currentpoll,
																polltpl_close
															FROM $wpdb->pollstpl 
															WHERE polltpl_id = %d", $templates_set_id 
														)
											);
		$tpl_answers_type = $templates_set_obj->polltpl_answers_type;
		?>
		<div class="wrap">
			<h1><?php echo __('Edit Templates Set', 'wp-polls') . ' - #' . $templates_set_id . ' - ' . $templates_set_obj->polltpl_set_name; ?></h1>
			<nav class="nav-tab-wrapper">
			  <a href="<?php echo $base_page_path . '&tab=details';	  ?>" class="nav-tab <?php echo ($active_tab===$default_tab) ? 'nav-tab-active' : '';?>"><?php echo __('Templates Set Details', 'wp-polls') ?></a>
			  <a href="<?php echo $base_page_path . '&tab=structure'; ?>" class="nav-tab <?php echo ($active_tab==='structure')  ? 'nav-tab-active' : '';?>"><?php echo __('Templates Structure', 'wp-polls') ?></a>
			  <a href="<?php echo $base_page_path . '&tab=settings';  ?>" class="nav-tab <?php echo ($active_tab==='settings') 	 ? 'nav-tab-active' : '';?>"><?php echo __('Templates Set Settings', 'wp-polls') ?></a>
			</nav>

			<div class="tab-content">
				<div id="loader_container" class="loader-container wp-polls-hide">
					<span class="loader-spinner"></span>
				</div>
				
				<?php 
				if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; }
				
				switch($active_tab) {
					//Tab - Structure
					case 'structure': 
				?> 
						<div id="tab_templates_structure">
						<h2><?php _e('Edit Templates\' Structure', 'wp-polls'); ?></h2>
						<script type="text/javascript">
						/* <![CDATA[*/
						function poll_default_templates(template) {
							var default_template;
							switch(template) {
								case "voteheader":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_voteheader', $tpl_answers_type)); ?>;
									break;
								case "votebody":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_votebody', $tpl_answers_type)); ?>;
									break;
								case "votefooter":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_votefooter', $tpl_answers_type)); ?>;
									break;
								case "resultheader":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_resultheader', $tpl_answers_type)); ?>;
									break;
								case "resultbody":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_resultbody', $tpl_answers_type, $templates_set_id)); ?>;
									break;
								case "resultbody2":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_resultbody2', $tpl_answers_type, $templates_set_id)); ?>;
									break;
								case "resultfooter":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_resultfooter', $tpl_answers_type)); ?>;
									break;
								case "resultfooter2":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_resultfooter2', $tpl_answers_type)); ?>;
									break;
								case "pollarchivelink":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_pollarchivelink', $tpl_answers_type)); ?>;
									break;
								case "pollarchiveheader":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_pollarchiveheader', $tpl_answers_type)); ?>;
									break;
								case "pollarchivefooter":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_pollarchivefooter', $tpl_answers_type)); ?>;
									break;
								case "pollarchivepagingheader":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_pollarchivepagingheader', $tpl_answers_type)); ?>;
									break;
								case "pollarchivepagingfooter":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_pollarchivepagingfooter', $tpl_answers_type)); ?>;
									break;
								case "disable":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_disable', $tpl_answers_type)); ?>;
									break;
								case "error":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_error', $tpl_answers_type)); ?>;
									break;
								case "aftervote":
									default_template = <?php echo json_encode(wp_polls_get_default_template_string('poll_template_aftervote', $tpl_answers_type)); ?>;
									break;
							}
							jQuery("#poll_template_" + template).val(default_template);
						}
						/* ]]> */
						</script>

						<form id="poll_template_form_structure" method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)).'&amp;mode=edit&amp;tpl_id='.$templates_set_id.'&amp;tab='.$active_tab; ?>">
							<?php wp_nonce_field('wp-polls_templates_structure'); ?>
							<input type="hidden" name="templates_set_id" value="<?php echo $templates_set_id; ?>" />

							<!-- Default Template Variables -->
							<h3><?php _e('Template Variables', 'wp-polls'); ?></h3>
							<table class="widefat">
								<tr>
									<td>
										<strong>%POLL_ID%</strong><br />
										<?php _e('Display the poll\'s ID', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER_ID%</strong><br />
										<?php _e('Display the poll\'s answer ID', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_QUESTION%</strong><br />
										<?php _e('Display the poll\'s question', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER%</strong><br />
										<?php _e('Display the poll\'s answer', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_TOTALVOTES%</strong><br />
										<?php _e('Display the poll\'s total votes NOT the number of people who voted for the poll', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER_TEXT%</strong><br />
										<?php _e('Display the poll\'s answer without HTML formatting.', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_RESULT_URL%</strong><br />
										<?php _e('Displays URL to poll\'s result', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER_VOTES%</strong><br />
										<?php _e('Display the poll\'s answer votes', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_MOST_ANSWER%</strong><br />
										<?php _e('Display the poll\'s most voted answer', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER_PERCENTAGE%</strong><br />
										<?php _e('Display the poll\'s answer percentage', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_MOST_VOTES%</strong><br />
										<?php _e('Display the poll\'s answer votes for the most voted answer', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ANSWER_IMAGEWIDTH%</strong><br />
										<?php _e('Display the poll\'s answer image width', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_MOST_PERCENTAGE%</strong><br />
										<?php _e('Display the poll\'s answer percentage for the most voted answer', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_LEAST_ANSWER%</strong><br />
										<?php _e('Display the poll\'s least voted answer', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_START_DATE%</strong><br />
										<?php _e('Display the poll\'s start date/time', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_LEAST_VOTES%</strong><br />
										<?php _e('Display the poll\'s answer votes for the least voted answer', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_END_DATE%</strong><br />
										<?php _e('Display the poll\'s end date/time', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_LEAST_PERCENTAGE%</strong><br />
										<?php _e('Display the poll\'s answer percentage for the least voted answer', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_MULTIPLE_ANS_MAX%</strong><br />
										<?php _e('Display the the maximum number of answers the user can choose if the poll supports multiple answers', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_CHECKBOX_RADIO%</strong><br />
										<?php _e('Display "checkbox" or "radio" input types depending on the poll type', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_TOTALVOTERS%</strong><br />
										<?php _e('Display the number of people who voted for the poll NOT the total votes of the poll', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_ARCHIVE_URL%</strong><br />
										<?php _e('Display the poll archive URL', 'wp-polls'); ?>
									</td>
								</tr>
								<tr class="alternate">
									<td>
										<strong>%POLL_USER_VOTED_ANSWERS_ID_LIST%</strong><br />
										<?php _e('Display all the poll\'s answers IDs the user voted for in a list HTML markup.', 'wp-polls'); ?>
									</td>
									<td>
										<strong>%POLL_USER_VOTED_ANSWERS_LIST%</strong><br />
										<?php _e('Display all the poll\'s answers the user voted for in a list HTML markup.', 'wp-polls'); ?>
									</td>
								</tr>
								<tr>
									<td>
										<strong>%POLL_MULTIPLE_ANSWER_PERCENTAGE%</strong><br />
										<?php _e('Display the poll\'s mutiple answer percentage. This is total votes divided by total voters.', 'wp-polls'); ?>
									</td>
									<td>
										<?php echo ($tpl_answers_type == 'object') ? '<strong>%POLL_ANSWER_OBJECT_URL%</strong><br />'.__('Display the permalink of the object used as answer.','wp-polls') : '&nbsp;'; ?>
									</td>
								</tr>
								<tr class="alternate">
									<td colspan="2">
										<?php _e('Note: <strong>%POLL_TOTALVOTES%</strong> and <strong>%POLL_TOTALVOTERS%</strong> will be different if your poll supports multiple answers. If your poll allows only single answer, both value will be the same.', 'wp-polls'); ?>
									</td>
								</tr>
							</table>

							<!-- Custom Template Variables -->
							<h3><?php _e('Custom Template Variables', 'wp-polls'); ?></h3>
							<?php 
							if ($tpl_answers_type == 'object' ){
								wp_polls_list_post_type_fields('', $templates_set_id, '', 'template_tags'); //print main custom variables table
								add_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_raw_text_list', 10, 3 );
								$custom_vars_list_raw = wp_polls_list_post_type_fields('', $templates_set_id, '', 'template_tags', false); //list that will be printed next to each template input with custom variables
								remove_filter( 'wp_polls_custom_template_tags', 'wp_polls_template_tags_filter_for_raw_text_list');
							} else {
								echo '<p style="padding-left:10px;">'.__('Note: The custom templates variabes are set in the Templates Set Details\' tab and are only available for templates dedicated to Polls with Object Answers Type.', 'wp-polls').'</p>';
							}
							?>

							<!-- Poll Voting Form Templates -->
							<h3><?php _e('Poll Voting Form Templates', 'wp-polls'); ?></h3> 
							<table class="form-table">
								 <tr>
									<td width="30%" valign="top">
										<strong><?php _e('Voting Form Header:', 'wp-polls'); ?></strong><br /><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_QUESTION%</p>
										<p style="margin: 2px 0">- %POLL_START_DATE%</p>
										<p style="margin: 2px 0">- %POLL_END_DATE%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('voteheader');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_voteheader, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_voteheader" name="poll_template_voteheader"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_voteheader ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Voting Form Body:', 'wp-polls'); ?></strong><br /><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_CHECKBOX_RADIO%</p>
										<?php echo ($tpl_answers_type == 'object') ? '<p style="margin: 2px 0">- %POLL_ANSWER_OBJECT_URL%</p>' : ''; ?>
										<p style="margin: 2px 0;"><?php echo (empty($custom_vars_list_raw)) ? __('(No custom variable set for this poll)', 'wp-polls') : $custom_vars_list_raw; ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('votebody');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_votebody, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_votebody" name="poll_template_votebody"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_votebody ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Voting Form Footer:', 'wp-polls'); ?></strong><br /><br /><br />
											<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
											<p style="margin: 2px 0">- %POLL_ID%</p>
											<p style="margin: 2px 0">- %POLL_RESULT_URL%</p>
											<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('votefooter');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_votefooter, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_votefooter" name="poll_template_votefooter"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_votefooter ) ); ?></textarea>
									</td>
								</tr>
							</table>

							<!-- Poll Result Templates -->
							<h3><?php _e('Poll Result Templates', 'wp-polls'); ?></h3>
							<table class="form-table">
								 <tr>
									<td width="30%" valign="top">
										<strong><?php _e('Result Header:', 'wp-polls'); ?></strong><br /><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_QUESTION%</p>
										<p style="margin: 2px 0">- %POLL_START_DATE%</p>
										<p style="margin: 2px 0">- %POLL_END_DATE%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('resultheader');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_resultheader, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_resultheader" name="poll_template_resultheader"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_resultheader ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Result Body:', 'wp-polls'); ?></strong><br /><?php _e('Displayed When The User HAS NOT Voted', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_TEXT%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANSWER_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_IMAGEWIDTH%</p>
										<?php echo ($tpl_answers_type == 'object') ? '<p style="margin: 2px 0">- %POLL_ANSWER_OBJECT_URL%</p>' : ''; ?>
										<p style="margin: 2px 0;"><?php echo (empty($custom_vars_list_raw)) ? __('(No custom variable set for this poll)', 'wp-polls') : $custom_vars_list_raw; ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('resultbody');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_resultbody, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_resultbody" name="poll_template_resultbody"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_resultbody ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Result Body:', 'wp-polls'); ?></strong><br /><?php _e('Displayed When The User HAS Voted', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_ID%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_TEXT%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANSWER_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_ANSWER_IMAGEWIDTH%</p>
										<?php echo ($tpl_answers_type == 'object') ? '<p style="margin: 2px 0">- %POLL_ANSWER_OBJECT_URL%</p>' : ''; ?>
										<p style="margin: 2px 0;"><?php echo (empty($custom_vars_list_raw)) ? __('(No custom variable set for this poll)', 'wp-polls') : $custom_vars_list_raw; ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('resultbody2');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_resultbody2, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_resultbody2" name="poll_template_resultbody2"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_resultbody2 ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Result Footer:', 'wp-polls'); ?></strong><br /><?php _e('Displayed When The User HAS Voted', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_START_DATE%</p>
										<p style="margin: 2px 0">- %POLL_END_DATE%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
										<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('resultfooter');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_resultfooter, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_resultfooter" name="poll_template_resultfooter"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_resultfooter ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Result Footer:', 'wp-polls'); ?></strong><br /><?php _e('Displayed When The User HAS NOT Voted', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_START_DATE%</p>
										<p style="margin: 2px 0">- %POLL_END_DATE%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
										<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('resultfooter2');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_resultfooter2, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_resultfooter2" name="poll_template_resultfooter2"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_resultfooter2 ) ); ?></textarea>
									</td>
								</tr>
							</table>

							<!-- Poll Archive Templates -->
							<h3><?php echo __('Poll Archive Templates', 'wp-polls'); ?></h3>
							<table class="form-table">
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Poll Archive Link', 'wp-polls'); ?></strong><br /><?php _e('Template For Displaying Poll Archive Link', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ARCHIVE_URL%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('pollarchivelink');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_pollarchivelink, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_pollarchivelink" name="poll_template_pollarchivelink"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_pollarchivelink ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Individual Poll Header', 'wp-polls'); ?></strong><br /><?php _e('Displayed Before Each Poll In The Poll Archive', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- <?php _e('N/A', 'wp-polls'); ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('pollarchiveheader');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_pollarchiveheader, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_pollarchiveheader" name="poll_template_pollarchiveheader"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_pollarchiveheader ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Individual Poll Footer', 'wp-polls'); ?></strong><br /><?php _e('Displayed After Each Poll In The Poll Archive', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_START_DATE%</p>
										<p style="margin: 2px 0">- %POLL_END_DATE%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTES%</p>
										<p style="margin: 2px 0">- %POLL_TOTALVOTERS%</p>
										<p style="margin: 2px 0">- %POLL_MOST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_MOST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_MOST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_ANSWER%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_VOTES%</p>
										<p style="margin: 2px 0">- %POLL_LEAST_PERCENTAGE%</p>
										<p style="margin: 2px 0">- %POLL_MULTIPLE_ANS_MAX%</p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('pollarchivefooter');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_pollarchivefooter, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_pollarchivefooter" name="poll_template_pollarchivefooter"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_pollarchivefooter ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Paging Header', 'wp-polls'); ?></strong><br /><?php _e('Displayed Before Paging In The Poll Archive', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- <?php _e('N/A', 'wp-polls'); ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('pollarchivepagingheader');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_pollarchivepagingheader, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_pollarchivepagingheader" name="poll_template_pollarchivepagingheader"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_pollarchivepagingheader ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Paging Footer', 'wp-polls'); ?></strong><br /><?php _e('Displayed After Paging In The Poll Archive', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- <?php _e('N/A', 'wp-polls'); ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('pollarchivepagingfooter');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_pollarchivepagingfooter, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_pollarchivepagingfooter" name="poll_template_pollarchivepagingfooter"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_pollarchivepagingfooter ) ); ?></textarea>
									</td>
								</tr>
							</table>

							<!-- Poll Misc Templates -->
							<h3><?php _e('Poll Misc Templates', 'wp-polls'); ?></h3>
							<table class="form-table">
								 <tr>
									<td width="30%" valign="top">
										<strong><?php _e('Poll Disabled', 'wp-polls'); ?></strong><br /><?php _e('Displayed When The Poll Is Disabled', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- <?php _e('N/A', 'wp-polls'); ?></p><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('disable');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_disable, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_disable" name="poll_template_disable"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_disable ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Poll Error', 'wp-polls'); ?></strong><br /><?php _e('Displayed When An Error Has Occured While Processing The Poll', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- <?php _e('N/A', 'wp-polls'); ?><br /><br />
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('error');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_error, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_error" name="poll_template_error"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_error ) ); ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="30%" valign="top">
										<strong><?php _e('Poll After Vote message', 'wp-polls'); ?></strong><br /><?php _e('Displayed When Poll Results Are Set To Be Hidden From User Who Voted', 'wp-polls'); ?><br /><br />
										<?php _e('Allowed Variables:', 'wp-polls'); ?><br />
										<p style="margin: 2px 0">- %POLL_ID%</p>
										<p style="margin: 2px 0">- %POLL_QUESTION%</p>									
										<p style="margin: 2px 0">- %POLL_USER_VOTED_ANSWERS_ID_LIST%</p>									
										<p style="margin: 2px 0">- %POLL_USER_VOTED_ANSWERS_LIST%</p><br />								
										<input type="button" name="RestoreDefault" value="<?php echo $restore_button_name; ?>" onclick="poll_default_templates('aftervote');" class="button" />
									</td>
									<td valign="top">
										<?php wp_polls_display_class_mismatch_warnings_if_any($templates_set_obj->polltpl_template_aftervote, $templates_set_id); ?>
										<textarea cols="80" rows="15" id="poll_template_aftervote" name="poll_template_aftervote"><?php echo esc_textarea( removeslashes( $templates_set_obj->polltpl_template_aftervote ) ); ?></textarea>
									</td>
								</tr>
							</table>
							<p class="submit">
								<input type="submit" name="do" value="<?php _e('Edit Templates Structure', 'wp-polls'); ?>" class="button-primary" />&nbsp;&nbsp;
								&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="window.location.href='<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>'"/>
							</p>	
							</form>
						</div>
					<?php
						break;

					//Tab - Setting
					case 'settings':
					?>
						<div id="tab_templates_settings">
							<h2><?php echo __('Edit Templates Set Settings', 'wp-polls'); ?></h2>
							<script type="text/javascript">
							/* <![CDATA[*/
								function set_pollbar_height(height) {
										jQuery("#poll_bar_height").val(height);
								}
								function update_pollbar(where) {
									pollbar_background = "#" + jQuery("#poll_bar_background").val();
									pollbar_border = "#" + jQuery("#poll_bar_border").val();
									pollbar_height = jQuery("#poll_bar_height").val() + "px";
									if(where  == "background") {
										jQuery("#wp-polls-pollbar-bg").css("background-color", pollbar_background);
									} else if(where == "border") {
										jQuery("#wp-polls-pollbar-border").css("background-color", pollbar_border);
									} else if(where == "style") {
										pollbar_style = jQuery("input[name='poll_bar_style']:checked").val();
										if(pollbar_style == "use_css") {
											jQuery("#wp-polls-pollbar").css("background-image", "none");
										} else {
											jQuery("#wp-polls-pollbar").css("background-image", "url('<?php echo plugins_url('wp-polls/images/'); ?>" + pollbar_style + "/pollbg.gif')");
										}
									}
									jQuery("#wp-polls-pollbar").css({"background-color":pollbar_background, "border":"1px solid " + pollbar_border, "height":pollbar_height});
								}
							/* ]]> */
							</script>
							
							<form id="poll_template_form_settings" method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)).'&amp;mode=edit&amp;tpl_id='.$templates_set_id.'&amp;tab='.$active_tab; ?>">
								<?php wp_nonce_field('wp-polls_templates_settings'); ?>
								<input type="hidden" name="templates_set_id" value="<?php echo $templates_set_id; ?>" />
									
								<!-- Poll Bar Style -->
								<h3><?php _e('Poll Bar Style', 'wp-polls'); ?></h3>
								<table class="form-table">
									 <tr>
										<th scope="row" valign="top"><?php _e('Poll Bar Style', 'wp-polls'); ?></th>
										<td colspan="2">
											<?php
												$pollbar = array( 
																	'style'         => $templates_set_obj->polltpl_bar_style,
																	'background'    => $templates_set_obj->polltpl_bar_background,
																	'border'        => $templates_set_obj->polltpl_bar_border,
																	'height'        => $templates_set_obj->polltpl_bar_height
																);					
												$pollbar_url = plugins_url('wp-polls/images');
												if( count( $poll_bars ) > 0 ) {
													foreach( $poll_bars as $filename => $pollbar_info ) {
														echo '<p>'."\n";
														if($pollbar['style'] == $filename) {
															echo '<input type="radio" id="poll_bar_style-'.$filename.'" name="poll_bar_style" value="'.$filename.'" checked="checked" onclick="set_pollbar_height('.$pollbar_info[1].'); update_pollbar(\'style\');" />';
														} else {
															echo '<input type="radio" id="poll_bar_style-'.$filename.'" name="poll_bar_style" value="'.$filename.'" onclick="set_pollbar_height('.$pollbar_info[1].'); update_pollbar(\'style\');" />';
														}
														echo '<label for="poll_bar_style-'.$filename.'">&nbsp;&nbsp;&nbsp;';
														echo '<img src="'.$pollbar_url.'/'.$filename.'/pollbg.gif" height="'.$pollbar_info[1].'" width="100" alt="pollbg.gif" />';
														echo '&nbsp;&nbsp;&nbsp;('.$filename.')</label>';
														echo '</p>'."\n";
													}
												}
											?>
											<input type="radio" id="poll_bar_style-use_css" name="poll_bar_style" value="use_css"<?php checked('use_css', $pollbar['style']); ?> onclick="update_pollbar('style');" /><label for="poll_bar_style-use_css"> <?php _e('Use CSS Style', 'wp-polls'); ?></label>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Poll Bar Background', 'wp-polls'); ?></th>
										<td width="10%" dir="ltr">#<input type="text" id="poll_bar_bg" name="poll_bar_background" value="<?php echo esc_attr( $pollbar['background'] ); ?>" size="6" maxlength="6" onblur="update_pollbar('background');" /></td>
										<td><div id="wp-polls-pollbar-bg" style="background-color: #<?php echo $pollbar['background']; ?>;"></div></td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Poll Bar Border', 'wp-polls'); ?></th>
										<td width="10%" dir="ltr">#<input type="text" id="poll_bar_border" name="poll_bar_border" value="<?php echo esc_attr( $pollbar['border'] ); ?>" size="6" maxlength="6" onblur="update_pollbar('border');" /></td>
										<td><div id="wp-polls-pollbar-border" style="background-color: #<?php echo $pollbar['border']; ?>;"></div></td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Poll Bar Height', 'wp-polls'); ?></th>
										<td colspan="2" dir="ltr"><input type="text" id="poll_bar_height" name="poll_bar_height" value="<?php echo $pollbar['height']; ?>" size="2" maxlength="2" onblur="update_pollbar('height');" />px</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Your poll bar will look like this', 'wp-polls'); ?></th>
										<td colspan="2">
											<?php
												if($pollbar['style'] == 'use_css') {
													echo '<div id="wp-polls-pollbar" style="width: 100px; height: '.$pollbar['height'].'px; background-color: #'.$pollbar['background'].'; border: 1px solid #'.$pollbar['border'].'"></div>'."\n";
												} else {
													echo '<div id="wp-polls-pollbar" style="width: 100px; height: '.$pollbar['height'].'px; background-color: #'.$pollbar['background'].'; border: 1px solid #'.$pollbar['border'].'; background-image: url(\''.plugins_url('wp-polls/images/'.$pollbar['style'].'/pollbg.gif').'\');"></div>'."\n";
												}
											?>
										</td>
									</tr>
								</table>

								<!-- Polls AJAX Style -->
								<?php 
								$poll_ajax_style = array( 
															'loading'   => $templates_set_obj->polltpl_ajax_style_loading,
															'fading'    => $templates_set_obj->polltpl_ajax_style_fading
														);		
								
								?>
								<h3><?php _e('Poll AJAX Style', 'wp-polls'); ?></h3>
								<table class="form-table">
									 <tr>
										<th scope="row" valign="top"><?php _e('Show Loading Image With Text', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ajax_style_loading" size="1">
												<option value="0"<?php selected('0', $poll_ajax_style['loading']); ?>><?php _e('No', 'wp-polls'); ?></option>
												<option value="1"<?php selected('1', $poll_ajax_style['loading']); ?>><?php _e('Yes', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Show Fading In And Fading Out Of Poll', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ajax_style_fading" size="1">
												<option value="0"<?php selected('0', $poll_ajax_style['fading']); ?>><?php _e('No', 'wp-polls'); ?></option>
												<option value="1"<?php selected('1', $poll_ajax_style['fading']); ?>><?php _e('Yes', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
								</table>

								<!-- Sorting Of Poll Answers -->
								<h3><?php _e('Voting options', 'wp-polls'); ?></h3>
								<table class="form-table">
									 <tr>
										<th scope="row" valign="top"><?php _e('Sort Poll Answers By:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ans_sortby" size="1">
												<option value="polla_votes"<?php selected('polla_votes', $templates_set_obj->polltpl_ans_sortby); ?>><?php _e('Votes Cast', 'wp-polls'); ?></option>
												<option value="polla_aid"<?php selected('polla_aid', $templates_set_obj->polltpl_ans_sortby); ?>><?php _e('Exact Order', 'wp-polls'); ?></option>
												<option value="polla_answers"<?php selected('polla_answers', $templates_set_obj->polltpl_ans_sortby); ?>><?php _e('Alphabetical Order', 'wp-polls'); ?></option>
												<option value="RAND()"<?php selected('RAND()', $templates_set_obj->polltpl_ans_sortby); ?>><?php _e('Random Order', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Sort Order Of Poll Answers:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ans_sortorder" size="1">
												<option value="asc"<?php selected('asc', $templates_set_obj->polltpl_ans_sortorder); ?>><?php _e('Ascending', 'wp-polls'); ?></option>
												<option value="desc"<?php selected('desc', $templates_set_obj->polltpl_ans_sortorder); ?>><?php _e('Descending', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Sort Poll Results By:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ans_result_sortby" size="1">
												<option value="polla_votes"<?php selected('polla_votes', $templates_set_obj->polltpl_ans_result_sortby); ?>><?php _e('Votes Cast', 'wp-polls'); ?></option>
												<option value="polla_aid"<?php selected('polla_aid', $templates_set_obj->polltpl_ans_result_sortby); ?>><?php _e('Exact Order', 'wp-polls'); ?></option>
												<option value="polla_answers"<?php selected('polla_answers', $templates_set_obj->polltpl_ans_result_sortby); ?>><?php _e('Alphabetical Order', 'wp-polls'); ?></option>
												<option value="RAND()"<?php selected('RAND()', $templates_set_obj->polltpl_ans_result_sortby); ?>><?php _e('Random Order', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Sort Order Of Poll Results:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_ans_result_sortorder" size="1">
												<option value="asc"<?php selected('asc', $templates_set_obj->polltpl_ans_result_sortorder); ?>><?php _e('Ascending', 'wp-polls'); ?></option>
												<option value="desc"<?php selected('desc', $templates_set_obj->polltpl_ans_result_sortorder); ?>><?php _e('Descending', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Who Is Allowed To Vote?', 'wp-polls'); ?></th>
										<td>
											<select name="poll_allowtovote" size="1">
												<option value="0"<?php selected('0', $templates_set_obj->polltpl_allowtovote); ?>><?php _e('Guests Only', 'wp-polls'); ?></option>
												<option value="1"<?php selected('1', $templates_set_obj->polltpl_allowtovote); ?>><?php _e('Registered Users Only', 'wp-polls'); ?></option>
												<option value="2"<?php selected('2', $templates_set_obj->polltpl_allowtovote); ?>><?php _e('Registered Users And Guests', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('After Vote', 'wp-polls'); ?>:</th>
										<td>
											<select name="poll_aftervote" size="1">
												<option value="1"<?php selected(1, $templates_set_obj->polltpl_aftervote); ?>><?php _e('Display Poll\'s Results', 'wp-polls'); ?></option>
												<option value="2"<?php selected(2, $templates_set_obj->polltpl_aftervote); ?>><?php _e('Display Disabled Poll\'s Voting Form', 'wp-polls'); ?></option>
												<option value="3"<?php selected(3, $templates_set_obj->polltpl_aftervote); ?>><?php _e('Do Not Display Poll In Post/Sidebar', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>																	
								</table>
								
								<!-- Logging Method -->
								<h3><?php _e('Logging Method', 'wp-polls'); ?></h3>
								<table class="form-table">
									 <tr valign="top">
										<th scope="row" valign="top"><?php _e('Poll Logging Method:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_logging_method" size="1">
												<option value="0"<?php selected('0', $templates_set_obj->polltpl_logging_method); ?>><?php _e('Do Not Log', 'wp-polls'); ?></option>
												<option value="1"<?php selected('1', $templates_set_obj->polltpl_logging_method); ?>><?php _e('Logged By Cookie', 'wp-polls'); ?></option>
												<option value="2"<?php selected('2', $templates_set_obj->polltpl_logging_method); ?>><?php _e('Logged By IP', 'wp-polls'); ?></option>
												<option value="3"<?php selected('3', $templates_set_obj->polltpl_logging_method); ?>><?php _e('Logged By Cookie And IP', 'wp-polls'); ?></option>
												<option value="4"<?php selected('4', $templates_set_obj->polltpl_logging_method); ?>><?php _e('Logged By Username', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Expiry Time For Cookie And Log:', 'wp-polls'); ?></th>
										<td><input type="text" name="poll_cookielog_expiry" value="<?php echo (int) esc_attr( $templates_set_obj->polltpl_cookielog_expiry ); ?>" size="10" /> <?php _e('seconds (0 to disable)', 'wp-polls'); ?></td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e( 'Header That Contains The IP:', 'wp-polls' ); ?></th>
										<td><input type="text" name="poll_ip_header" value="<?php echo esc_attr( $templates_set_obj->polltpl_ip_header ); ?>" size="30" /> <?php _e( 'You can leave it blank to use the default', 'wp-polls' ); ?><br /><?php _e( 'Example: REMOTE_ADDR', 'wp-polls' ); ?></td>
									</tr>
								</table>

								<!-- Poll Archive -->
								<h3><?php _e('Poll Archive', 'wp-polls'); ?></h3>
								<table class="form-table">
									<tr>
										<th scope="row" valign="top"><?php _e('Number Of Polls Per Page:', 'wp-polls'); ?></th>
										<td><input type="text" name="poll_archive_perpage" value="<?php echo (int) esc_attr( $templates_set_obj->polltpl_archive_perpage ); ?>" size="2" /></td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Type Of Polls To Display In Poll Archive:', 'wp-polls'); ?></th>
										<td>
											<select name="poll_archive_displaypoll" size="1">
												<option value="1"<?php selected('1', $templates_set_obj->polltpl_archive_displaypoll); ?>><?php _e('Closed Polls Only', 'wp-polls'); ?></option>
												<option value="2"<?php selected('2', $templates_set_obj->polltpl_archive_displaypoll); ?>><?php _e('Opened Polls Only', 'wp-polls'); ?></option>
												<option value="3"<?php selected('3', $templates_set_obj->polltpl_archive_displaypoll); ?>><?php _e('Closed And Opened Polls', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Poll Archive URL:', 'wp-polls'); ?></th>
										<td><input type="text" name="poll_archive_url" value="<?php echo esc_url( $templates_set_obj->polltpl_archive_url ); ?>" size="50" dir="ltr" /></td>
									</tr>
									<tr>
										<th scope="row" valign="top"><?php _e('Note', 'wp-polls'); ?></th>
										<td><em><?php _e('Only polls\' results will be shown in the Poll Archive regardless of whether the poll is closed or opened.', 'wp-polls'); ?></em></td>
									</tr>
								</table>

								<!-- Current Active Poll -->
								<h3><?php _e('Current Active Poll', 'wp-polls'); ?></h3>
								<table class="form-table">
									 <tr>
										<th scope="row" valign="top"><?php _e('Current Active Poll', 'wp-polls'); ?>:</th>
										<td>
											<select name="poll_currentpoll" size="1">
												<option value="-1"<?php selected(-1, $templates_set_obj->polltpl_currentpoll); ?>><?php _e('Do NOT Display Poll (Disable)', 'wp-polls'); ?></option>
												<option value="-2"<?php selected(-2, $templates_set_obj->polltpl_currentpoll); ?>><?php _e('Display Random Poll', 'wp-polls'); ?></option>
												<option value="0"<?php selected(0, $templates_set_obj->polltpl_currentpoll); ?>><?php _e('Display Latest Poll', 'wp-polls'); ?></option>
												<optgroup>&nbsp;</optgroup>
												<?php
													$polls = $wpdb->get_results("SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC");
													if($polls) {
														foreach($polls as $poll) {
															$poll_question = removeslashes($poll->pollq_question);
															$poll_id = (int) $poll->pollq_id;
															if($poll_id === (int) $templates_set_obj->polltpl_currentpoll ) {
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
										<th scope="row" valign="top"><?php _e('When Poll Is Closed', 'wp-polls'); ?>:</th>
										<td>
											<select name="poll_close" size="1">
												<option value="1"<?php selected(1, $templates_set_obj->polltpl_close); ?>><?php _e('Display Poll\'s Results', 'wp-polls'); ?></option>
												<option value="3"<?php selected(3, $templates_set_obj->polltpl_close); ?>><?php _e('Display Disabled Poll\'s Voting Form', 'wp-polls'); ?></option>
												<option value="2"<?php selected(2, $templates_set_obj->polltpl_close); ?>><?php _e('Do Not Display Poll In Post/Sidebar', 'wp-polls'); ?></option>
											</select>
										</td>
									</tr>
								</table>
								
								<p class="submit">
									<input type="submit" name="do" value="<?php _e('Edit Templates Set Settings', 'wp-polls'); ?>" class="button-primary" />&nbsp;&nbsp;
									&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="window.location.href='<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>'"/>
								</p>										
							</form>			
						</div>
						
				<?php	
						break;
					
					//Tab - Type 
					default:
				?>			
						<div id="tab_templates_details">
							<h2><?php echo __('Edit Templates Set Details', 'wp-polls'); ?></h2>							
							<form id="poll_template_form_details" method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)).'&amp;mode=edit&amp;tpl_id='.$templates_set_id.'&amp;tab='.$active_tab; ?>">
								<?php wp_nonce_field('wp-polls_templates_details'); ?>
								<input type="hidden" name="templates_set_id" value="<?php echo $templates_set_id; ?>" />
								<input type="hidden" name="previous_poll_answers_type" value="<?php echo $tpl_answers_type; ?>" />

								<!-- Template Name -->
								<h3><?php _e('Templates Set Name', 'wp-polls'); ?></h3>
								<table class="form-table ">
									<tr>
										<th scope="row" valign="top"><?php _e('Templates Set Name', 'wp-polls' ); ?></th>
										<td><?php echo __('ID', 'wp-polls' ).'#'.$templates_set_id ?>&nbsp;&nbsp;<input type="text" name="poll_templates_set_name" size="60" value="<?php echo esc_attr( $templates_set_obj->polltpl_set_name ); ?>" /></td>
									</tr>
								</table>
								
								<!-- Template Answers Type -->
								<h3><?php _e('Polls\' Answers Type', 'wp-polls'); ?></h3>
								<table id="pollq_answer_entries_type_selection" class="form-table ">
									<tr>
										<th scope="row" valign="top"><?php _e('Answers\' entries type', 'wp-polls') ?></th>
										<td>
											<input type="radio" name="poll_answers_type" value="text" <?php checked($tpl_answers_type, 'text') ?> onchange="check_poll_answer_entries_type(value, 'change', 'templates');"/><label><?php _e('Text (default)', 'wp-polls') ?></label>
											&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="radio" name="poll_answers_type" value="object" <?php checked($tpl_answers_type, 'object') ?> onchange="check_poll_answer_entries_type(value, 'change', 'templates');"/><label class="wp-polls-tooltip"><?php _e('Objects (post types items)', 'wp-polls') ?><label>
											<div class="wp-polls-tooltip-container">
												<span class="wp-polls-tooltip-icon">?</span>
												<p class="wp-polls-tooltip-info">
													<span class="info"><?php echo __('A poll template of a given type can only be associated with polls of the same type.', 'wp-polls') . '<br/><br/><strong>' . __('Text mode', 'wp-polls') . '</strong> ' . __('lets you manually type all the poll\'s answers.', 'wp-polls') . '<br/><br/><strong>' . __('Objects mode', 'wp-polls') . '</strong> ' . __('enables you to select post type items\' data (i.e. data already saved in your website) to serve as poll\'s answers (e.g. post type item\'s name, post type item\'s thumbnail, etc.).', 'wp-polls'); ?></span>
												</p>
											</div>
										</td>
									</tr>
								</table>	
							
								<!-- Default Objects' Fields Per Post Type -->
								<h3 style="display: inline-block"><?php _e('Object Answers\' fields (Custom Templates Tags)', 'wp-polls'); ?></h3>
								<div class="wp-polls-tooltip-container">
									<span class="wp-polls-tooltip-icon">?</span>
									<p class="wp-polls-tooltip-info">
										<span class="info"><?php echo __('Every field checked here will add a corresponding Custom Template Tag in the Template\'s Structure tab.', 'wp-polls') ?></span>
									</p>
								</div>
								<table class="form-table">
									
							
									<tr id="obj_answers_fields" class="<?php echo ($tpl_answers_type == 'object')? '' : 'wp-polls-hide';?>">
										<td>
											<div id="pollq_fields_list" class="wp-polls-ajax-parent-container wp-polls-ajax-box wp-polls-ajax-selected">
												<ul></ul>
												<?php
													$post_types_array = get_post_types(array('public' => true), 'names');
													$post_types_array = apply_filters('wp_polls_templates_post_type_fields', $post_types_array);
													wp_polls_list_post_type_fields($post_types_array, $templates_set_id);
													$poll_saved_fields_array_of_obj = $wpdb->get_results( $wpdb->prepare( "SELECT pollaof_optype, pollaof_obj_field FROM $wpdb->pollsaof WHERE pollaof_tplid = %d ORDER BY pollaof_aofid ASC", $templates_set_id ) );
													$poll_saved_fields = array(); //fields already saved for this poll 
													foreach ($poll_saved_fields_array_of_obj as $obj){
														$poll_saved_fields[$obj->pollaof_optype][] = $obj->pollaof_obj_field; //associative array such as '["post_type_1" => ["field_1","field_2"], "post_type_2" => ["field_1", "field_4"]'
													}		
												?>
												<br/>
												<br/>
											</div>
										</td>
									</tr>
									<div class="wp-polls-dropdown-input-containers">
										<input type="hidden" name="poll_ans_obj_fields" id="ans_obj_fields" value="<?php echo (!empty($poll_saved_fields)) ? htmlspecialchars(json_encode($poll_saved_fields)) : (htmlspecialchars(wp_polls_get_default_template_string('poll_ans_obj_fields', 'object'))) ; ?>"/>
									</div>			
									<tr>
										<td>
											<em><?php _e('Object Fields\' selection is only available when Answers\' entries type is set to "Object".', 'wp-polls'); ?></em>	
										</td>
									</tr>
								</table>
							
								<p class="submit">
									<input type="submit" name="do" value="<?php _e('Edit Templates Set Details', 'wp-polls'); ?>" class="button-primary" />&nbsp;&nbsp;
									&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-polls'); ?>" class="button" onclick="window.location.href='<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>'"/>
								</p>								
							</form>			
						</div>
				<?php						
						break;
				}
				?>
				
			</div>
		</div>	
<?php
		break;
	
	// Mode - Templates List
	default:
		if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; }
?>

		<!-- Manage Templates -->
		<div class="wrap">
			<h2><?php _e('Manage Templates', 'wp-polls'); ?></h2>
			<h3><?php _e('Templates Sets', 'wp-polls'); ?></h3>
			<br style="clear" />
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('ID', 'wp-polls'); ?></th>
						<th><?php _e('Answer\'s Type', 'wp-polls'); ?></th>
						<th><?php _e('Templates Set\'s Name', 'wp-polls'); ?></th>
						<th><?php _e('Total Associated Polls', 'wp-polls'); ?></th>
						<th colspan="3"><?php _e('Action', 'wp-polls'); ?></th>
					</tr>
				</thead>
				<tbody id="manage_templates">
					<?php echo wp_polls_list_available_templates_sets('table_rows'); ?>
				</tbody>
			</table>
			
			<!-- Insert Default Templates Set Button -->
			<br style="clear" />
			<input type="button" name="InsertBuiltinTemplatesSet" value="<?php echo __('Insert Built-in Templates Set', 'wp-polls').' ('.__('Text Answers Type', 'wp-polls').')'; ?>" onclick="insert_builtin_templates_set('text', '<?php echo wp_create_nonce('wp-polls_add_templates_set'); ?>');" class="button" />
			<input type="button" name="InsertBuiltinTemplatesSet" value="<?php echo __('Insert Built-in Templates Set', 'wp-polls').' ('.__('Object Answers Type', 'wp-polls').')'; ?>" onclick="insert_builtin_templates_set('object', '<?php echo wp_create_nonce('wp-polls_add_templates_set'); ?>');" class="button" />
						
		 </div>
		<p>&nbsp;</p>
			 
		<!-- Reset Templates -->
		<div class="wrap">
			<h3><?php _e('Reset All Templates To Default', 'wp-polls'); ?></h3>
			<br style="clear" />
			<div align="center" id="reset_all_templates">
			<?php
				$poll_templates = (int) $wpdb->get_var( "SELECT COUNT(polltpl_id) FROM $wpdb->pollstpl" );
				if($poll_templates > 0) {
			?>
				<strong><?php _e('Are You Sure You Want To Remove All Templates Sets?', 'wp-polls'); ?></strong>
				<div class="wp-polls-tooltip-container">
					<span class="wp-polls-tooltip-icon">?</span>
					<p class="wp-polls-tooltip-info">
						<span class="info"><?php _e('All templates sets listed above will be removed and existing polls will be reassociated with built-in templates sets as default.', 'wp-polls'); ?></span>
					</p>
				</div><br /><br />
				<input type="checkbox" name="reset_all_templates_sets_yes" id="reset_all_templates_sets_yes" value="yes" />&nbsp;<label for="reset_all_templates_sets_yes"><?php _e('Yes', 'wp-polls'); ?></label><br /><br />
				<input type="button" value="<?php _e('Reset All Templates Sets', 'wp-polls'); ?>" class="button" onclick="reset_all_templates_sets('<?php echo esc_js(__('You are about to delete all polls templates sets. This action is not reversible.', 'wp-polls')); ?>', '<?php echo wp_create_nonce('wp-polls_reset-all-templates-sets'); ?>');" />
			<?php
				} else {
					_e('No poll templates sets available. Start with inserting the built-in ones and customize them as you need.', 'wp-polls');
				}
			?>
			</div>
		</div>            
<?php
} // End switch($mode)
