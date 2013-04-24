/*
+----------------------------------------------------------------+
|																							|
|	WordPress Plugin: WP-Polls										|
|	Copyright © 2012 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Polls Admin Javascript File													|
|	- wp-content/plugins/wp-polls/polls-admin-js.js	 						|
|																							|
+----------------------------------------------------------------+
*/


var global_poll_id = 0;
var global_poll_aid = 0;
var global_poll_aid_votes  = 0;
var count_poll_answer_new = 0;
var count_poll_answer = 3;

// Delete Poll
function delete_poll(poll_id, poll_confirm, nonce) {
	delete_poll_confirm = confirm(poll_confirm);
	if(delete_poll_confirm) {
		global_poll_id = poll_id;
		jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
			jQuery('#message').html(data);
			jQuery('#message').show();
			jQuery('#poll-' + global_poll_id).remove();
		}});
	}
}

// Delete Poll Logs
function delete_poll_logs(poll_confirm, nonce) {
	delete_poll_logs_confirm = confirm(poll_confirm);
	if(delete_poll_logs_confirm) {
		if(jQuery('#delete_logs_yes').is(':checked')) {
			jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_all_logs + '&delete_logs_yes=yes&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				jQuery('#message').html(data);
				jQuery('#message').show();
				jQuery('#poll_logs').html(pollsAdminL10n.text_no_poll_logs);
			}});
		} else {
			alert(pollsAdminL10n.text_checkbox_delete_all_logs);
		}
	}
}

// Delete Individual Poll Logs
function delete_this_poll_logs(poll_id, poll_confirm, nonce) {
	delete_poll_logs_confirm = confirm(poll_confirm);
	if(delete_poll_logs_confirm) {
		if(jQuery('#delete_logs_yes').is(':checked')) {
			global_poll_id = poll_id;
			jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll_logs + '&pollq_id=' + poll_id + '&delete_logs_yes=yes&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				jQuery('#message').html(data);
				jQuery('#message').show();
				jQuery('#poll_logs').html(pollsAdminL10n.text_no_poll_logs);
				jQuery('#poll_logs_display').hide();
				jQuery('#poll_logs_display_none').show();
			}});
		} else {
			alert(pollsAdminL10n.text_checkbox_delete_poll_logs);
		}
	}
}

// Delete Poll Answer
function delete_poll_ans(poll_id, poll_aid, poll_aid_vote, poll_confirm, nonce) {
	delete_poll_ans_confirm = confirm(poll_confirm);
	if(delete_poll_ans_confirm) {
		global_poll_id = poll_id;
		global_poll_aid = poll_aid;
		global_poll_aid_votes = poll_aid_vote;
		temp_vote_count = 0;
		jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll_ans + '&pollq_id=' + poll_id + '&polla_aid=' + poll_aid + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
			jQuery('#message').html(data);
			jQuery('#message').show();
			jQuery('#poll_total_votes').html((parseInt(jQuery('#poll_total_votes').html()) - parseInt(global_poll_aid_votes)));
			jQuery('#pollq_totalvotes').val(temp_vote_count);
			jQuery('#poll-answer-' + global_poll_aid).remove();
			check_totalvotes();
			reorder_answer_num();
		}});
	}
}

// Open Poll
function opening_poll(poll_id, poll_confirm, nonce) {
	open_poll_confirm = confirm(poll_confirm);
	if(open_poll_confirm) {
		global_poll_id = poll_id;
		jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_open_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
			jQuery('#message').html(data);
			jQuery('#message').show();
			jQuery('#open_poll').hide();
			jQuery('#close_poll').show();
		}});
	}
}

// Close Poll
function closing_poll(poll_id, poll_confirm, nonce) {
	close_poll_confirm = confirm(poll_confirm);
	if(close_poll_confirm) {
		global_poll_id = poll_id;
		jQuery.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_close_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
			jQuery('#message').html(data);
			jQuery('#message').show();
			jQuery('#open_poll').show();
			jQuery('#close_poll').hide();
		}});
	}
}

// Reoder Answer Answer
function reorder_answer_num() {
	var pollq_multiple = jQuery('#pollq_multiple');
	var selected = pollq_multiple.val();
	var previous_size = jQuery('> option', pollq_multiple).size();
	pollq_multiple.empty();
	jQuery('#poll_answers tr > th').each(function (i) {
		jQuery(this).text(pollsAdminL10n.text_answer + ' ' + (i+1));
		jQuery(pollq_multiple).append('<option value="' + (i+1) + '">' + (i+1) + '</option>');
	});
	if(selected > 1)
	{
		var current_size = jQuery('> option', pollq_multiple).size();
		if(selected <= current_size)
			jQuery('> option', pollq_multiple).eq(selected - 1).attr('selected', 'selected');
		else if(selected == previous_size)
			jQuery('> option', pollq_multiple).eq(current_size - 1).attr('selected', 'selected');
	}
}

// Calculate Total Votes
function check_totalvotes() {
	temp_vote_count = 0;
	jQuery("#poll_answers tr td input[size=4]").each(function (i) {
		if(isNaN(jQuery(this).val())) {
			temp_vote_count += 0;
		} else {
			temp_vote_count += parseInt(jQuery(this).val());
		}
	});
	jQuery('#pollq_totalvotes').val(temp_vote_count);
}

// Add Poll's Answer In Add Poll Page
function add_poll_answer_add() {
	jQuery('#poll_answers').append('<tr id="poll-answer-' + count_poll_answer + '"><th width="20%" scope="row" valign="top"></th><td width="80%"><input type="text" size="50" maxlength="200" name="polla_answers[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_add(' + count_poll_answer + ');" class="button" /></td></tr>');
	count_poll_answer++;
	reorder_answer_num();
}

// Remove Poll's Answer in Add Poll Page
function remove_poll_answer_add(poll_answer_id) {
	jQuery('#poll-answer-' + poll_answer_id).remove();
	reorder_answer_num();
}

// Add Poll's Answer In Edit Poll Page
function add_poll_answer_edit() {
	jQuery('#poll_answers').append('<tr id="poll-answer-new-' + count_poll_answer_new + '"><th width="20%" scope="row" valign="top"></th><td width="60%"><input type="text" size="50" maxlength="200" name="polla_answers_new[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_edit(' + count_poll_answer_new + ');" class="button" /></td><td width="20%" align="' + pollsAdminL10n.text_direction + '">0 <input type="text" size="4" name="polla_answers_new_votes[]" value="0" onblur="check_totalvotes();" /></td></tr>');
	count_poll_answer_new++;
	reorder_answer_num();
}

// Remove Poll's Answer In Edit Poll Page
function remove_poll_answer_edit(poll_answer_new_id) {
	jQuery('#poll-answer-new-' + poll_answer_new_id).remove();
	check_totalvotes();
	reorder_answer_num();
}

// Check Poll Whether It is Multiple Poll Answer
function check_pollq_multiple() {
	if(parseInt(jQuery('#pollq_multiple_yes').val()) == 1) {
		jQuery('#pollq_multiple').attr('disabled', false);
	} else {
		jQuery('#pollq_multiple').val(1);
		jQuery('#pollq_multiple').attr('disabled', true);
	}
}

// Show/Hide Poll's Timestamp
function check_polltimestamp() {
	if(jQuery('#edit_polltimestamp').is(':checked')) {
		jQuery('#pollq_timestamp').show();
	} else {
		jQuery('#pollq_timestamp').hide();
	}
}

// Show/Hide  Poll's Expiry Date
function check_pollexpiry() {
	if(jQuery('#pollq_expiry_no').is(':checked')) {
		jQuery('#pollq_expiry').hide();
	} else {
		jQuery('#pollq_expiry').show();
	}
}