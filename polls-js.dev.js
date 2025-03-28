function poll_vote(pollID) {
	jQuery(document).ready(function($) {
		var poll_answer_id = '';
		var poll_multiple_ans = 0;
		var poll_multiple_ans_count = 0;
		var ranked_data = '';
		
		// For ranked choice polls, check if the order data is present
		if ($('#poll_' + pollID + '_ranked_order').length) {
			ranked_data = $('#poll_' + pollID + '_ranked_order').val();
			if (ranked_data) {
				poll_answer_id = ranked_data;
				poll_process(pollID, poll_answer_id);
				return;
			}
		}
		
		// For regular and multiple choice polls
		if($('#poll_multiple_ans_' + pollID).length) {
			poll_multiple_ans = parseInt($('#poll_multiple_ans_' + pollID).val());
		}
		
		$('#polls_form_' + pollID + ' input:checkbox, #polls_form_' + pollID + ' input:radio, #polls_form_' + pollID + ' option').each(function(i) {
			if($(this).is(':checked') || $(this).is(':selected')) {
				if(poll_multiple_ans > 0) {
					poll_answer_id = $(this).val() + ',' + poll_answer_id;
					poll_multiple_ans_count++;
				} else {
					poll_answer_id = parseInt($(this).val());
				}
			}
		});
		
		if(poll_multiple_ans > 0) {
			if(poll_multiple_ans_count > 0 && poll_multiple_ans_count <= poll_multiple_ans) {
				poll_answer_id = poll_answer_id.substring(0, (poll_answer_id.length - 1));
				poll_process(pollID, poll_answer_id);
			} else if(poll_multiple_ans_count === 0) {
				alert(pollsL10n.text_valid);
			} else {
				alert(pollsL10n.text_multiple + ' ' + poll_multiple_ans);
			}
		} else {
			if(poll_answer_id > 0) {
				poll_process(pollID, poll_answer_id);
			} else {
				alert(pollsL10n.text_valid);
			}
		}
	});
}

function poll_process(pollID, pollAnswerID) {
	jQuery(document).ready(function($) {
		var poll_nonce = $('#poll_' + pollID + '_nonce').val();
		
		if(pollsL10n.show_fading) {
			$('#polls-' + pollID).fadeTo('def', 0);
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=process&poll_id=' + pollID + '&poll_' + pollID + '=' + pollAnswerID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		} else {
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=process&poll_id=' + pollID + '&poll_' + pollID + '=' + pollAnswerID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		}
	});
}

function poll_result(pollID) {
	jQuery(document).ready(function($) {
		var poll_nonce = $('#poll_' + pollID + '_nonce').val();
		if(pollsL10n.show_fading) {
			$('#polls-' + pollID).fadeTo('def', 0);
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=result&poll_id=' + pollID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		} else {
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=result&poll_id=' + pollID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		}
	});
}

function poll_booth(pollID) {
	jQuery(document).ready(function($) {
		var poll_nonce = $('#poll_' + pollID + '_nonce').val();
		if(pollsL10n.show_fading) {
			$('#polls-' + pollID).fadeTo('def', 0);
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=booth&poll_id=' + pollID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		} else {
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').show();
			}
			$.ajax({
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				url: pollsL10n.ajax_url,
				data: 'action=polls&view=booth&poll_id=' + pollID + '&poll_' + pollID + '_nonce=' + poll_nonce,
				cache: false,
				success: poll_process_success(pollID)
			});
		}
	});
}

function poll_process_success(pollID) {
	return function(data) {
		jQuery(document).ready(function($) {
			$('#polls-' + pollID).replaceWith(data);
			if(pollsL10n.show_loading) {
				$('#polls-' + pollID + '-loading').hide();
			}
			if(pollsL10n.show_fading) {
				$('#polls-' + pollID).fadeTo('def', 1);
			}
		});
	};
}

// Initialize the values from settings
pollsL10n.show_loading = parseInt(pollsL10n.show_loading);
pollsL10n.show_fading = parseInt(pollsL10n.show_fading);
