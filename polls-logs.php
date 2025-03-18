<?php
### Check Whether User Can Manage Polls
if( ! current_user_can( 'manage_polls' ) ) {
    die( 'Access Denied' );
}


### Variables
$max_records = 2000;
$pollip_answers = array();
$poll_question_data = $wpdb->get_row( $wpdb->prepare( "SELECT pollq_multiple, pollq_question, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );
$poll_question = wp_kses_post( removeslashes( $poll_question_data->pollq_question ) );
$poll_totalvoters = (int) $poll_question_data->pollq_totalvoters;
$poll_multiple = (int) $poll_question_data->pollq_multiple;
$poll_registered = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_userid) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_userid > 0", $poll_id ) );
$poll_comments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user != %s AND pollip_userid = 0", $poll_id, __( 'Guest', 'wp-polls' ) ) );
$poll_guest = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user = %s", $poll_id, __( 'Guest', 'wp-polls' ) ) );
$poll_totalrecorded = ( $poll_registered + $poll_comments + $poll_guest );
list( $order_by, $sort_order ) = _polls_get_ans_sort();
$poll_answers_data = $wpdb->get_results( $wpdb->prepare( "SELECT polla_aid, polla_answers, polla_atype FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY $order_by $sort_order", $poll_id ) );
$poll_voters = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pollip_user FROM $wpdb->pollsip WHERE pollip_qid = %d AND pollip_user != %s ORDER BY pollip_user ASC", $poll_id, __( 'Guest', 'wp-polls' ) ) );
$poll_logs_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pollip_id) FROM $wpdb->pollsip WHERE pollip_qid = %d", $poll_id ) );

$exclude_registered = 0;
$exclude_comment = 0;
$exclude_guest = 0;

$users_voted_for = null;
$what_user_voted = null;

$num_choices_sign = 'more';
$num_choices = 0;
$exclude_registered_2 = 0;
$exclude_comment_2 = 0;

### Process Filters
if( ! empty( $_POST['do'] ) ) {
    check_admin_referer('wp-polls_logs');
    $registered_sql = '';
    $comment_sql = '';
    $guest_sql = '';
    $users_voted_for_sql = '';
    $what_user_voted_sql = '';
    $num_choices_sql = '';
    $num_choices_sign_sql = '';
    $order_by = '';
    switch((int) sanitize_key( $_POST['filter'] ) ) {
        case 1:
            $users_voted_for = (int) sanitize_key( $_POST['users_voted_for'] );
            $exclude_registered = isset( $_POST['exclude_registered'] ) && (int) sanitize_key( $_POST['exclude_registered'] ) === 1;
            $exclude_comment = isset( $_POST['exclude_comment'] ) && (int) sanitize_key( $_POST['exclude_comment'] ) === 1;
            $exclude_guest = isset( $_POST['exclude_guest'] ) && (int) sanitize_key( $_POST['exclude_guest'] ) === 1;
            $users_voted_for_sql = "AND pollip_aid = $users_voted_for";
            if($exclude_registered) {
                $registered_sql = 'AND pollip_userid = 0';
            }
            if($exclude_comment) {
                if(!$exclude_registered) {
                    $comment_sql = 'AND pollip_userid > 0';
                } else {
                    $comment_sql = 'AND pollip_user = \''.__('Guest', 'wp-polls').'\'';
                }
            }
            if($exclude_guest) {
                $guest_sql  = 'AND pollip_user != \''.__('Guest', 'wp-polls').'\'';
            }
            $order_by = 'pollip_timestamp DESC';
            break;
        case 2:
            $exclude_registered_2 = (array_key_exists('exclude_registered_2', $_POST)) ? (int) sanitize_key( $_POST['exclude_registered_2'] ) : 0;
            $exclude_comment_2 = (array_key_exists('exclude_comment_2', $_POST)) ? (int) sanitize_key( $_POST['exclude_comment_2'] )  : 0;
            $num_choices = (array_key_exists('num_choices', $_POST)) ? (int) sanitize_key( $_POST['num_choices']) : 0;
            $num_choices_sign = (array_key_exists('num_choices_sign', $_POST)) ? sanitize_key( $_POST['num_choices_sign'] ) : 'more';
            switch($num_choices_sign) {
                case 'more':
                    $num_choices_sign_sql = '>';
                    break;
                case 'more_exactly':
                    $num_choices_sign_sql = '>=';
                    break;
                case 'exactly':
                    $num_choices_sign_sql = '=';
                    break;
                case 'less_exactly':
                    $num_choices_sign_sql = '<=';
                    break;
                case 'less':
                    $num_choices_sign_sql = '<';
                    break;
            }
            if($exclude_registered_2) {
                $registered_sql = 'AND pollip_userid = 0';
            }
            if($exclude_comment_2) {
                if(!$exclude_registered_2) {
                    $comment_sql = 'AND pollip_userid > 0';
                } else {
                    $comment_sql = 'AND pollip_user = \''.__('Guest', 'wp-polls').'\'';
                }
            }
            $guest_sql  = 'AND pollip_user != \''.__('Guest', 'wp-polls').'\'';
            $num_choices_query = $wpdb->get_col("SELECT pollip_user, COUNT(pollip_ip) AS num_choices FROM $wpdb->pollsip WHERE pollip_qid = $poll_id GROUP BY pollip_ip, pollip_user HAVING num_choices $num_choices_sign_sql $num_choices");
            $num_choices_sql = 'AND pollip_user IN (\'' . implode( '\',\'', array_map( 'esc_sql', $num_choices_query ) ) . '\')';
            $order_by = 'pollip_user, pollip_ip';
            break;
        case 3;
            $what_user_voted = esc_sql( $_POST['what_user_voted'] );
            $what_user_voted_sql = "AND pollip_user = '$what_user_voted'";
            $order_by = 'pollip_user, pollip_ip';
            break;
    }
    $poll_ips = $wpdb->get_results("SELECT $wpdb->pollsip.* FROM $wpdb->pollsip WHERE pollip_qid = $poll_id $users_voted_for_sql $registered_sql $comment_sql $guest_sql $what_user_voted_sql $num_choices_sql ORDER BY $order_by");
} else {
    $poll_ips = $wpdb->get_results( $wpdb->prepare( "SELECT pollip_aid, pollip_ip, pollip_host, pollip_timestamp, pollip_user FROM $wpdb->pollsip WHERE pollip_qid = %d ORDER BY pollip_aid ASC, pollip_user ASC LIMIT %d", $poll_id, $max_records ) );
}
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.removeslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; } ?>
<div class="wrap">
    <h2><?php _e('Poll\'s Logs', 'wp-polls'); ?></h2>
    <h3><?php echo $poll_question; ?></h3>
    <p>
        <?php printf(_n('There are a total of <strong>%s</strong> recorded vote for this poll.', 'There are a total of <strong>%s</strong> recorded votes for this poll.', $poll_totalrecorded, 'wp-polls'), number_format_i18n($poll_totalrecorded)); ?><br />
        <?php printf(_n('<strong>&raquo;</strong> <strong>%s</strong> vote is cast by registered users', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by registered users', $poll_registered, 'wp-polls'), number_format_i18n($poll_registered)); ?><br />
        <?php printf(_n('<strong>&raquo;</strong> <strong>%s</strong> vote is cast by comment authors', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by comment authors', $poll_comments, 'wp-polls'), number_format_i18n($poll_comments)); ?><br />
        <?php printf(_n('<strong>&raquo;</strong> <strong>%s</strong> vote is cast by guests', '<strong>&raquo;</strong> <strong>%s</strong> votes are cast by guests', $poll_guest, 'wp-polls'), number_format_i18n($poll_guest)); ?>
    </p>
</div>
<?php if($poll_totalrecorded > 0 && apply_filters( 'wp_polls_log_show_log_filter', true )) { ?>
<div class="wrap">
    <h3><?php _e('Filter Poll\'s Logs', 'wp-polls') ?></h3>
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="50%">
                <form method="post" action="<?php echo admin_url('admin.php?page='.$base_name.'&amp;mode=logs&amp;id='.$poll_id); ?>">
                <?php wp_nonce_field('wp-polls_logs'); ?>
                <p style="display: none;"><input type="hidden" name="filter" value="1" /></p>
                <table class="form-table">
                    <tr>
                        <th scope="row" valign="top"><?php _e('Display All Users That Voted For', 'wp-polls'); ?></th>
                        <td>
                            <select name="users_voted_for" size="1">
                                <?php
                                    if($poll_answers_data) {
                                        foreach($poll_answers_data as $data) {
                                            $polla_id = (int) $data->polla_aid;
                                            $polla_atype = $data->polla_atype;
                                            $polla_answers = ($polla_atype == 'object') ? get_the_title($data->polla_answers) : $data->polla_answers;
                                            $polla_answers = removeslashes( strip_tags( esc_attr( $polla_answers ) ) );
                                            if($polla_id  == $users_voted_for) {
                                                echo '<option value="'.$polla_id .'" selected="selected">'.$polla_answers.'</option>';
                                            } else {
                                                echo '<option value="'.$polla_id .'">'.$polla_answers.'</option>';
                                            }
                                            $pollip_answers[$polla_id] = $polla_answers;
                                        }
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" valign="top"><?php _e('Voters To EXCLUDE', 'wp-polls'); ?></th>
                        <td>
                            <input type="checkbox" id="exclude_registered_1" name="exclude_registered" value="1" <?php checked('1', $exclude_registered); ?> />&nbsp;<label for="exclude_registered_1"><?php _e('Registered Users', 'wp-polls'); ?></label><br />
                            <input type="checkbox" id="exclude_comment_1" name="exclude_comment" value="1" <?php checked('1', $exclude_comment); ?> />&nbsp;<label for="exclude_comment_1"><?php _e('Comment Authors', 'wp-polls'); ?></label><br />
                            <input type="checkbox" id="exclude_guest_1" name="exclude_guest" value="1" <?php checked('1', $exclude_guest); ?> />&nbsp;<label for="exclude_guest_1"><?php _e('Guests', 'wp-polls'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center;"><input type="submit" name="do" value="<?php _e('Filter', 'wp-polls'); ?>" class="button" /></td>
                    </tr>
                </table>
                </form>
            </td>
            <td width="50%">
                <?php if($poll_multiple > 0) { ?>
                    <form method="post" action="<?php echo admin_url('admin.php?page='.$base_name.'&amp;mode=logs&amp;id='.$poll_id); ?>">
                    <?php wp_nonce_field('wp-polls_logs'); ?>
                    <p style="display: none;"><input type="hidden" name="filter" value="2" /></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row" valign="top"><?php _e('Display Users That Voted For', 'wp-polls'); ?></th>
                            <td>
                                <select name="num_choices_sign" size="1">
                                    <option value="more" <?php selected('more', $num_choices_sign); ?>><?php _e('More Than', 'wp-polls'); ?></option>
                                    <option value="more_exactly" <?php selected('more_exactly', $num_choices_sign); ?>><?php _e('More Than Or Exactly', 'wp-polls'); ?></option>
                                    <option value="exactly" <?php selected('exactly', $num_choices_sign); ?>><?php _e('Exactly', 'wp-polls'); ?></option>
                                    <option value="less_exactly" <?php selected('less_exactly', $num_choices_sign); ?>><?php _e('Less Than Or Exactly', 'wp-polls'); ?></option>
                                    <option value="less" <?php selected('less', $num_choices_sign); ?>><?php _e('Less Than', 'wp-polls'); ?></option>
                                </select>
                                &nbsp;&nbsp;
                                <select name="num_choices" size="1">
                                    <?php
                                        for($i = 1; $i <= $poll_multiple; $i++) {
                                            if($i == 1) {
                                                echo '<option value="1">'.__('1 Answer', 'wp-polls').'</option>';
                                            } else {
                                                if($i == $num_choices) {
                                                    echo '<option value="'.$i.'" selected="selected">'.sprintf(_n('%s Answer', '%s Answers', $i, 'wp-polls'), number_format_i18n($i)).'</option>';
                                                } else {
                                                    echo '<option value="'.$i.'">'.sprintf(_n('%s Answer', '%s Answers', $i, 'wp-polls'), number_format_i18n($i)).'</option>';
                                                }
                                            }
                                        }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" valign="top"><?php _e('Voters To EXCLUDE', 'wp-polls'); ?></th>
                            <td>
                                <input type="checkbox" id="exclude_registered_2" name="exclude_registered_2" value="1" <?php checked('1', $exclude_registered_2); ?> />&nbsp;<label for="exclude_registered_2"><?php _e('Registered Users', 'wp-polls'); ?></label><br />
                                <input type="checkbox" id="exclude_comment_2" name="exclude_comment_2" value="1" <?php checked('1', $exclude_comment_2); ?> />&nbsp;<label for="exclude_comment_2"><?php _e('Comment Authors', 'wp-polls'); ?></label><br />
                                <?php _e('Guests will automatically be excluded', 'wp-polls'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: center;"><input type="submit" name="do" value="<?php _e('Filter', 'wp-polls'); ?>" class="button" /></td>
                        </tr>
                    </table>
                    </form>
                <?php } else { ?>
                    &nbsp;
                <?php } // End if($poll_multiple > -1) ?>
            </td>
        </tr>
        <tr>
            <td>
                <?php if($poll_voters) { ?>
                <form method="post" action="<?php echo admin_url('admin.php?page='.$base_name.'&amp;mode=logs&amp;id='.$poll_id); ?>">
                <?php wp_nonce_field('wp-polls_logs'); ?>
                <p style="display: none;"><input type="hidden" name="filter" value="3" /></p>
                <table class="form-table">
                    <tr>
                        <th scope="row" valign="top"><?php _e('Display What This User Has Voted', 'wp-polls'); ?></th>
                        <td>
                            <select name="what_user_voted" size="1">
                                <?php
                                    if($poll_voters) {
                                        foreach($poll_voters as $pollip_user) {
                                            if($pollip_user == $what_user_voted) {
                                                echo '<option value="' . removeslashes( esc_attr( $pollip_user ) ) . '" selected="selected">' . removeslashes( esc_attr( $pollip_user ) ) . '</option>';
                                            } else {
                                                echo '<option value="' . removeslashes( esc_attr( $pollip_user ) ) . '">' . removeslashes( esc_attr( $pollip_user ) ) . '</option>';
                                            }
                                        }
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center;"><input type="submit" name="do" value="<?php _e('Filter', 'wp-polls'); ?>" class="button" /></td>
                    </tr>
                </table>
                </form>
                <?php } else { ?>
                    &nbsp;
                <?php } // End if($poll_multiple > -1) ?>
            </td>
            <td style="text-align: center;"><input type="button" value="<?php _e('Clear Filter', 'wp-polls'); ?>" onclick="self.location.href = '<?php echo esc_attr( $base_page ); ?>&amp;mode=logs&amp;id=<?php echo $poll_id; ?>';" class="button" /></td>
        </tr>
    </table>
</div>
<p>&nbsp;</p>
<?php } // End if($poll_totalrecorded > 0) ?>
<div class="wrap">
    <h3><?php _e('Poll Logs', 'wp-polls'); ?></h3>
    <div id="poll_logs_display">
        <?php
            if($poll_ips) {
                if(empty($_POST['do'])) {
                    echo '<p>'.sprintf(__('This default filter is limited to display only <strong>%s</strong> records.', 'wp-polls'), number_format_i18n($max_records)).'</p>';
                }
                echo '<table class="widefat">'."\n";
                echo "<tr class=\"highlight\"><td colspan=\"4\">". $poll_question . "</td></tr>";
                $k = 1;
                $j = 0;
                $poll_last_aid = -1;
                $temp_pollip_user = null;
                if(isset($_POST['filter']) && (int) sanitize_key( $_POST['filter'] ) > 1) {
                    echo "<tr class=\"thead\">\n";
                    echo "<th>".__('Answer', 'wp-polls')."</th>\n";
                    echo "<th>".__('Hashed IP / Host', 'wp-polls')."</th>\n";
                    echo "<th>".__('Date', 'wp-polls')."</th>\n";
                    echo "</tr>\n";
                    foreach($poll_ips as $poll_ip) {
                        $pollip_aid = (int) $poll_ip->pollip_aid;
                        $pollip_user = removeslashes($poll_ip->pollip_user);
                        $pollip_ip = $poll_ip->pollip_ip;
                        $pollip_host = $poll_ip->pollip_host;
                        $pollip_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_ip->pollip_timestamp));

                        $i = 0;
                        if($i % 2 === 0) {
                            $style = '';
                        }  else {
                            $style = 'class="alternate"';
                        }
                        if ( $pollip_user !== $temp_pollip_user ) {
                            echo '<tr class="highlight">';
                            echo '<td colspan="3"><strong>' . __( 'User', 'wp-polls') . ' ' . esc_html( number_format_i18n( $k ) ) . ': ' . esc_html( $pollip_user ) . '</strong></td>';
                            echo '</tr>';
                            $k++;
                        }
                        echo "<tr $style>\n";
                        echo '<td>' . esc_html( $pollip_answers[$pollip_aid] ) . '</td>';
                        echo '<td>' . esc_html( $pollip_ip ) . ' / ' . esc_html( $pollip_host ) . '</td>';
                        echo '<td>' . esc_html( $pollip_date ) . '</td>';
                        echo "</tr>\n";
                        $temp_pollip_user = $pollip_user;
                        $i++;
                        $j++;
                    }
                } else {
                    foreach($poll_ips as $poll_ip) {
                        $pollip_aid = (int) $poll_ip->pollip_aid;
                        $pollip_user = apply_filters( 'wp_polls_log_secret_ballot', removeslashes( $poll_ip->pollip_user ) );
                        $pollip_ip = $poll_ip->pollip_ip;
                        $pollip_host = $poll_ip->pollip_host;
                        $pollip_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_ip->pollip_timestamp));
                        if($pollip_aid != $poll_last_aid) {
                            if ( $pollip_aid ===  0 ) {
                                echo '<tr class="highlight"><td colspan="4"><strong>' . esc_html( $pollip_answers[$pollip_aid] ) . '</strong></td></tr>';
                            } else {
                                $polla_answer = ! empty( $pollip_answers[$pollip_aid] ) ? $pollip_answers[ $pollip_aid ] : $poll_answers_data[ $k-1 ]->polla_answers;
                                echo '<tr class="highlight"><td colspan="4"><strong>' . __('Answer', 'wp-polls') . ' ' . esc_html( number_format_i18n( $k ) ) . ': ' . esc_html( $polla_answer ) . '</strong></td></tr>';
                                $k++;
                            }
                            echo "<tr class=\"thead\">\n";
                            echo "<th>".__('No.', 'wp-polls')."</th>\n";
                            echo "<th>".__('User', 'wp-polls')."</th>\n";
                            echo "<th>".__('Hashed IP / Host', 'wp-polls')."</th>\n";
                            echo "<th>".__('Date', 'wp-polls')."</th>\n";
                            echo "</tr>\n";
                            $i = 1;
                        }
                        if($i%2 == 0) {
                            $style = '';
                        }  else {
                            $style = 'class="alternate"';
                        }
                        echo "<tr $style>\n";
                        echo '<td>' . esc_html( number_format_i18n( $i ) ) . '</td>';
                        echo '<td>' . esc_html( $pollip_user ) . '</td>';
                        echo '<td>' . esc_html( $pollip_ip ) . ' / ' . esc_html( $pollip_host ) . '</td>';
                        echo '<td>' . esc_html( $pollip_date ) . '</td>';
                        echo "</tr>\n";
                        $poll_last_aid = $pollip_aid;
                        $i++;
                        $j++;
                    }
                }
                echo "<tr class=\"highlight\">\n";
                echo "<td colspan=\"4\">".sprintf(__('Total number of records that matches this filter: <strong>%s</strong>', 'wp-polls'), number_format_i18n($j))."</td>";
                echo "</tr>\n";
                echo '</table>'."\n";
            }
        ?>
    </div>
    <?php if(!empty($_POST['do'])) { ?>
        <br class="clear" /><div id="poll_logs_display_none" style="text-align: center; display: <?php if(!$poll_ips) { echo 'block'; } else { echo 'none'; } ?>;" ><?php _e('No poll logs matches the filter.', 'wp-polls'); ?></div>
    <?php } else { ?>
        <br class="clear" /><div id="poll_logs_display_none" style="text-align: center; display: <?php if(!$poll_logs_count) { echo 'block'; } else { echo 'none'; } ?>;" ><?php _e('No poll logs available for this poll.', 'wp-polls'); ?></div>
    <?php } ?>
</div>
<p>&nbsp;</p>

<!-- Delete Poll Logs -->
<div class="wrap">
    <h3><?php _e('Delete Poll Logs', 'wp-polls'); ?></h3>
    <br class="clear" />
    <div style="text-align: center;" id="poll_logs">
        <?php if($poll_logs_count) { ?>
            <strong><?php _e('Are You Sure You Want To Delete Logs For This Poll Only?', 'wp-polls'); ?></strong><br /><br />
            <input type="checkbox" id="delete_logs_yes" name="delete_logs_yes" value="yes" />&nbsp;<label for="delete_logs_yes"><?php _e('Yes', 'wp-polls'); ?></label><br /><br />
            <input type="button" name="do" value="<?php _e('Delete Logs For This Poll Only', 'wp-polls'); ?>" class="button" onclick="delete_this_poll_logs(<?php echo $poll_id; ?>, '<?php printf( esc_js( __( 'You are about to delete poll logs for this poll \'%s\' ONLY. This action is not reversible.', 'wp-polls' ) ), esc_js( esc_attr( $poll_question ) ) ); ?>', '<?php echo wp_create_nonce('wp-polls_delete-poll-logs'); ?>');" />
        <?php
            } else {
                _e('No poll logs available for this poll.', 'wp-polls');
            }
        ?>
    </div>
    <p><?php _e('Note: If your logging method is by IP and Cookie or by Cookie, users may still be unable to vote if they have voted before as the cookie is still stored in their computer.', 'wp-polls'); ?></p>