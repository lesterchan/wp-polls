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
		jQuery(document).ready(function($) {
			$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				$('#message').html(data);
				$('#message').show();
				$('#poll-' + global_poll_id).remove();
			}});
		});
	}
}

// Delete Poll Logs
function delete_poll_logs(poll_confirm, nonce) {
	delete_poll_logs_confirm = confirm(poll_confirm);
	if(delete_poll_logs_confirm) {
		jQuery(document).ready(function($) {
			if($('#delete_logs_yes').is(':checked')) {
				$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_all_logs + '&delete_logs_yes=yes&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
					$('#message').html(data);
					$('#message').show();
					$('#poll_logs').html(pollsAdminL10n.text_no_poll_logs);
				}});
			} else {
				alert(pollsAdminL10n.text_checkbox_delete_all_logs);
			}
		});
	}
}

// Delete Individual Poll Logs
function delete_this_poll_logs(poll_id, poll_confirm, nonce) {
	delete_poll_logs_confirm = confirm(poll_confirm);
	if(delete_poll_logs_confirm) {
		jQuery(document).ready(function($) {
			if($('#delete_logs_yes').is(':checked')) {
				global_poll_id = poll_id;
				$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll_logs + '&pollq_id=' + poll_id + '&delete_logs_yes=yes&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
					$('#message').html(data);
					$('#message').show();
					$('#poll_logs').html(pollsAdminL10n.text_no_poll_logs);
					$('#poll_logs_display').hide();
					$('#poll_logs_display_none').show();
				}});
			} else {
				alert(pollsAdminL10n.text_checkbox_delete_poll_logs);
			}
		});
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
		jQuery(document).ready(function($) {
			$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_poll_ans + '&pollq_id=' + poll_id + '&polla_aid=' + poll_aid + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				$('#message').html(data);
				$('#message').show();
				$('#poll_total_votes').html((parseInt($('#poll_total_votes').html()) - parseInt(global_poll_aid_votes)));
				$('#pollq_totalvotes').val(temp_vote_count);
				$('#poll-answer-' + global_poll_aid).remove();
				check_totalvotes();
				reorder_answer_num();
			}});
		});
	}
}

// Open Poll
function opening_poll(poll_id, poll_confirm, nonce) {
	open_poll_confirm = confirm(poll_confirm);
	if(open_poll_confirm) {
		global_poll_id = poll_id;
		jQuery(document).ready(function($) {
			$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_open_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				$('#message').html(data);
				$('#message').show();
				$('#open_poll').hide();
				$('#close_poll').show();
			}});
		});
	}
}

// Close Poll
function closing_poll(poll_id, poll_confirm, nonce) {
	close_poll_confirm = confirm(poll_confirm);
	if(close_poll_confirm) {
		global_poll_id = poll_id;
		jQuery(document).ready(function($) {
			$.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_close_poll + '&pollq_id=' + poll_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
				$('#message').html(data);
				$('#message').show();
				$('#open_poll').show();
				$('#close_poll').hide();
			}});
		});
	}
}

// Reoder Answer Answer
function reorder_answer_num() {
	jQuery(document).ready(function($) {
		var pollq_multiple = $('#pollq_multiple');
		var selected = pollq_multiple.val();
		var previous_size = $('> option', pollq_multiple).size();
		pollq_multiple.empty();
		$('#poll_answers tr > th').each(function (i) {
			$(this).text(pollsAdminL10n.text_answer + ' ' + (i+1));
			$(pollq_multiple).append('<option value="' + (i+1) + '">' + (i+1) + '</option>');
		});
		if(selected > 1)
		{
			var current_size = $('> option', pollq_multiple).size();
			if(selected <= current_size)
				$('> option', pollq_multiple).eq(selected - 1).attr('selected', 'selected');
			else if(selected == previous_size)
				$('> option', pollq_multiple).eq(current_size - 1).attr('selected', 'selected');
		}
	});
}

// Calculate Total Votes
function check_totalvotes() {
	temp_vote_count = 0;
	jQuery(document).ready(function($) {
		$("#poll_answers tr td input[size=4]").each(function (i) {
			if(isNaN($(this).val())) {
				temp_vote_count += 0;
			} else {
				temp_vote_count += parseInt($(this).val());
			}
		});
		$('#pollq_totalvotes').val(temp_vote_count);
	});
}

// Add Poll's Answer In Add Poll Page
function add_poll_answer_add() {
	jQuery(document).ready(function($) {
		$('#poll_answers').append('<tr id="poll-answer-' + count_poll_answer + '"><th width="20%" scope="row" valign="top"></th><td width="80%"><input type="text" size="50" maxlength="200" name="polla_answers[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_add(' + count_poll_answer + ');" class="button" /></td></tr>');
		count_poll_answer++;
		reorder_answer_num();
	});
}

// Remove Poll's Answer in Add Poll Page
function remove_poll_answer_add(poll_answer_id) {
	jQuery(document).ready(function($) {
		$('#poll-answer-' + poll_answer_id).remove();
		reorder_answer_num();
	});
}

// Add Poll's Answer In Edit Poll Page
function add_poll_answer_edit() {
	jQuery(document).ready(function($) {
		$('#poll_answers').append('<tr id="poll-answer-new-' + count_poll_answer_new + '"><th width="20%" scope="row" valign="top"></th><td width="60%"><input type="text" size="50" maxlength="200" name="polla_answers_new[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_edit(' + count_poll_answer_new + ');" class="button" /></td><td width="20%" align="' + pollsAdminL10n.text_direction + '">0 <input type="text" size="4" name="polla_answers_new_votes[]" value="0" onblur="check_totalvotes();" /></td></tr>');
		count_poll_answer_new++;
		reorder_answer_num();
	});
}

// Remove Poll's Answer In Edit Poll Page
function remove_poll_answer_edit(poll_answer_new_id) {
	jQuery(document).ready(function($) {
		$('#poll-answer-new-' + poll_answer_new_id).remove();
		check_totalvotes();
		reorder_answer_num();
	});
}

// Check Poll Whether It is Multiple Poll Answer
function check_pollq_multiple() {
	jQuery(document).ready(function($) {
		if(parseInt($('#pollq_multiple_yes').val()) == 1) {
			$('#pollq_multiple').attr('disabled', false);
		} else {
			$('#pollq_multiple').val(1);
			$('#pollq_multiple').attr('disabled', true);
		}
	});
}

// Show/Hide Poll's Timestamp
function check_polltimestamp() {
	jQuery(document).ready(function($) {
		if($('#edit_polltimestamp').is(':checked')) {
			$('#pollq_timestamp').show();
		} else {
			$('#pollq_timestamp').hide();
		}
	});
}

// Show/Hide  Poll's Expiry Date
function check_pollexpiry() {
	jQuery(document).ready(function($) {
		if($('#pollq_expiry_no').is(':checked')) {
			$('#pollq_expiry').hide();
		} else {
			$('#pollq_expiry').show();
		}
	});
}