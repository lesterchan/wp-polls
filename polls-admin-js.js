/**
 * WP-Polls admin pages.
 *
 * Nothing here is exported. Admin markup asks for behaviour through
 * data-poll-action attributes, which are routed by the dispatcher at the
 * bottom of this file.
 */
( function() {
	'use strict';

	const pollsAdminL10n = window.pollsAdminL10n || {};

	let globalPollId = 0;
	let globalPollAid = 0;
	let globalPollAidVotes = 0;
	let countPollAnswerNew = 0;
	let countPollAnswer = 3;
	let tempVoteCount = 0;

	// Post An Admin Action And Show The Response In The Message Box
	function pollAdminRequest( fields, onSuccess ) {
		const body = new URLSearchParams();
		body.append( 'action', 'polls-admin' );
		Object.keys( fields ).forEach( function( name ) {
			body.append( name, fields[ name ] );
		} );

		fetch( pollsAdminL10n.admin_ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-cache',
			body,
		} )
			.then( function( response ) {
				return response.text();
			} )
			.then( function( data ) {
				const message = document.getElementById( 'message' );
				if ( message ) {
					message.innerHTML = data;
					message.style.display = '';
				}
				if ( onSuccess ) {
					onSuccess();
				}
			} );
	}

	// Remove An Element By Its ID
	function pollRemoveElement( id ) {
		const element = document.getElementById( id );
		if ( element ) {
			element.remove();
		}
	}

	// Is The "Yes" Confirmation Checkbox Ticked?
	function pollDeleteLogsConfirmed() {
		const checkbox = document.getElementById( 'delete_logs_yes' );
		return !! checkbox && checkbox.checked;
	}

	// Replace The Poll Logs Panel With The "No Logs" Message
	function pollClearLogsPanel() {
		const logs = document.getElementById( 'poll_logs' );
		if ( logs ) {
			logs.textContent = pollsAdminL10n.text_no_poll_logs;
		}
	}

	// Delete Poll
	function deletePoll( pollId, pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		globalPollId = pollId;
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_delete_poll,
				pollq_id: pollId,
				_ajax_nonce: nonce,
			},
			function() {
				pollRemoveElement( 'poll-' + globalPollId );
			},
		);
	}

	// Delete Poll Logs
	function deletePollLogs( pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		if ( ! pollDeleteLogsConfirmed() ) {
			alert( pollsAdminL10n.text_checkbox_delete_all_logs );
			return;
		}
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_delete_all_logs,
				delete_logs_yes: 'yes',
				_ajax_nonce: nonce,
			},
			pollClearLogsPanel,
		);
	}

	// Delete Individual Poll Logs
	function deleteThisPollLogs( pollId, pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		if ( ! pollDeleteLogsConfirmed() ) {
			alert( pollsAdminL10n.text_checkbox_delete_poll_logs );
			return;
		}
		globalPollId = pollId;
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_delete_poll_logs,
				pollq_id: pollId,
				delete_logs_yes: 'yes',
				_ajax_nonce: nonce,
			},
			function() {
				pollClearLogsPanel();

				const display = document.getElementById( 'poll_logs_display' );
				if ( display ) {
					display.style.display = 'none';
				}

				const empty = document.getElementById( 'poll_logs_display_none' );
				if ( empty ) {
					empty.style.display = '';
				}
			},
		);
	}

	// Delete Poll Answer
	function deletePollAns( pollId, pollAid, pollAidVote, pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		globalPollId = pollId;
		globalPollAid = pollAid;
		globalPollAidVotes = pollAidVote;
		tempVoteCount = 0;
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_delete_poll_ans,
				pollq_id: pollId,
				polla_aid: pollAid,
				_ajax_nonce: nonce,
			},
			function() {
				const totalVotes = document.getElementById( 'poll_total_votes' );
				if ( totalVotes ) {
					totalVotes.textContent =
						parseInt( totalVotes.textContent ) - parseInt( globalPollAidVotes );
				}

				const totalvotesField = document.getElementById( 'pollq_totalvotes' );
				if ( totalvotesField ) {
					totalvotesField.value = tempVoteCount;
				}

				pollRemoveElement( 'poll-answer-' + globalPollAid );
				checkTotalvotes();
				reorderAnswerNum();
			},
		);
	}

	// Show Either The Open Or The Close Button
	function pollToggleOpenClose( showOpen ) {
		const openButton = document.getElementById( 'open_poll' );
		const closeButton = document.getElementById( 'close_poll' );
		if ( openButton ) {
			openButton.style.display = showOpen ? '' : 'none';
		}
		if ( closeButton ) {
			closeButton.style.display = showOpen ? 'none' : '';
		}
	}

	// Open Poll
	function openingPoll( pollId, pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		globalPollId = pollId;
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_open_poll,
				pollq_id: pollId,
				_ajax_nonce: nonce,
			},
			function() {
				pollToggleOpenClose( false );
			},
		);
	}

	// Close Poll
	function closingPoll( pollId, pollConfirm, nonce ) {
		if ( ! confirm( pollConfirm ) ) {
			return;
		}
		globalPollId = pollId;
		pollAdminRequest(
			{
				do: pollsAdminL10n.text_close_poll,
				pollq_id: pollId,
				_ajax_nonce: nonce,
			},
			function() {
				pollToggleOpenClose( true );
			},
		);
	}

	// Reorder Answer Numbers
	function reorderAnswerNum() {
		const pollqMultiple = document.getElementById( 'pollq_multiple' );
		if ( ! pollqMultiple ) {
			return;
		}

		const selected = parseInt( pollqMultiple.value );
		const previousSize = pollqMultiple.options.length;
		pollqMultiple.innerHTML = '';

		const headers = document.querySelectorAll( '#poll_answers tr > th' );
		Array.prototype.forEach.call( headers, function( header, index ) {
			header.textContent = pollsAdminL10n.text_answer + ' ' + ( index + 1 );

			const option = document.createElement( 'option' );
			option.value = index + 1;
			option.textContent = index + 1;
			pollqMultiple.appendChild( option );
		} );

		if ( selected > 1 ) {
			const currentSize = pollqMultiple.options.length;
			if ( selected <= currentSize ) {
				pollqMultiple.options[ selected - 1 ].selected = true;
			} else if ( selected === previousSize ) {
				pollqMultiple.options[ currentSize - 1 ].selected = true;
			}
		}
	}

	// Calculate Total Votes
	function checkTotalvotes() {
		tempVoteCount = 0;

		const voteFields = document.querySelectorAll(
			'#poll_answers input.wp-polls-votes',
		);
		Array.prototype.forEach.call( voteFields, function( voteField ) {
			const votes = parseInt( voteField.value );
			if ( ! isNaN( votes ) ) {
				tempVoteCount += votes;
			}
		} );

		const totalvotesField = document.getElementById( 'pollq_totalvotes' );
		if ( totalvotesField ) {
			totalvotesField.value = tempVoteCount;
		}
	}

	// Build One Answer Row For The Add/Edit Poll Pages
	// Built through the DOM rather than an HTML string so the translated button
	// label can never be parsed as markup.
	function pollBuildAnswerRow( rowId, inputName, onRemove, votesName ) {
		const row = document.createElement( 'tr' );
		row.id = rowId;

		const heading = document.createElement( 'th' );
		heading.scope = 'row';
		row.appendChild( heading );

		const answerCell = document.createElement( 'td' );
		answerCell.className = 'wp-polls-answer-text';

		const answerField = document.createElement( 'input' );
		answerField.type = 'text';
		answerField.className = 'large-text';
		answerField.maxLength = 200;
		answerField.name = inputName;
		answerCell.appendChild( answerField );
		answerCell.appendChild( document.createTextNode( '   ' ) );

		const removeButton = document.createElement( 'button' );
		removeButton.type = 'button';
		removeButton.className = 'button';
		removeButton.textContent = pollsAdminL10n.text_remove_poll_answer;
		removeButton.addEventListener( 'click', onRemove );
		answerCell.appendChild( removeButton );
		row.appendChild( answerCell );

		if ( votesName ) {
			const votesCell = document.createElement( 'td' );
			votesCell.className = 'wp-polls-answer-votes';

			const votesField = document.createElement( 'input' );
			votesField.type = 'text';
			votesField.className = 'wp-polls-votes';
			votesField.name = votesName;
			votesField.value = '0';
			votesField.addEventListener( 'blur', checkTotalvotes );
			votesCell.appendChild( votesField );
			row.appendChild( votesCell );
		}

		return row;
	}

	// Add Poll's Answer In Add Poll Page
	function addPollAnswerAdd() {
		const answers = document.getElementById( 'poll_answers' );
		if ( ! answers ) {
			return;
		}

		const rowId = 'poll-answer-' + countPollAnswer;
		answers.appendChild(
			pollBuildAnswerRow( rowId, 'polla_answers[]', function() {
				pollRemoveElement( rowId );
				reorderAnswerNum();
			} ),
		);

		countPollAnswer++;
		reorderAnswerNum();
	}

	// Remove Poll's Answer In Add Poll Page
	function removePollAnswerAdd( pollAnswerId ) {
		pollRemoveElement( 'poll-answer-' + pollAnswerId );
		reorderAnswerNum();
	}

	// Add Poll's Answer In Edit Poll Page
	function addPollAnswerEdit() {
		const answers = document.getElementById( 'poll_answers' );
		if ( ! answers ) {
			return;
		}

		const rowId = 'poll-answer-new-' + countPollAnswerNew;
		answers.appendChild(
			pollBuildAnswerRow(
				rowId,
				'polla_answers_new[]',
				function() {
					pollRemoveElement( rowId );
					checkTotalvotes();
					reorderAnswerNum();
				},
				'polla_answers_new_votes[]',
			),
		);

		countPollAnswerNew++;
		reorderAnswerNum();
	}

	// Check Poll Whether It Is Multiple Poll Answer
	function checkPollqMultiple() {
		const multipleYes = document.getElementById( 'pollq_multiple_yes' );
		const multiple = document.getElementById( 'pollq_multiple' );
		if ( ! multipleYes || ! multiple ) {
			return;
		}

		if ( parseInt( multipleYes.value ) === 1 ) {
			multiple.disabled = false;
		} else {
			multiple.value = 1;
			multiple.disabled = true;
		}
	}

	// Show/Hide Poll's Timestamp
	function checkPolltimestamp() {
		const editTimestamp = document.getElementById( 'edit_polltimestamp' );
		const timestamp = document.getElementById( 'pollq_timestamp' );
		if ( editTimestamp && timestamp ) {
			timestamp.style.display = editTimestamp.checked ? '' : 'none';
		}
	}

	// Show/Hide Poll's Expiry Date
	function checkPollexpiry() {
		const expiryNo = document.getElementById( 'pollq_expiry_no' );
		const expiry = document.getElementById( 'pollq_expiry' );
		if ( expiryNo && expiry ) {
			expiry.style.display = expiryNo.checked ? 'none' : '';
		}
	}

	// Read A data-poll-* Attribute Off An Element
	function pollAttr( element, name ) {
		return element.getAttribute( 'data-poll-' + name ) || '';
	}

	// Actions The Admin Pages Can Ask For Through data-poll-action
	// 'on' is the event the action answers to. Anything listening for "focusout"
	// replaces what used to be an inline onblur; focusout is used rather than blur
	// because blur does not bubble up to the document.
	const pollAdminActions = {
		'delete-poll': {
			on: 'click',
			run( el ) {
				deletePoll(
					pollAttr( el, 'id' ),
					pollAttr( el, 'confirm' ),
					pollAttr( el, 'nonce' ),
				);
			},
		},
		'delete-answer': {
			on: 'click',
			run( el ) {
				deletePollAns(
					pollAttr( el, 'id' ),
					pollAttr( el, 'aid' ),
					pollAttr( el, 'votes' ),
					pollAttr( el, 'confirm' ),
					pollAttr( el, 'nonce' ),
				);
			},
		},
		'delete-all-logs': {
			on: 'click',
			run( el ) {
				deletePollLogs( pollAttr( el, 'confirm' ), pollAttr( el, 'nonce' ) );
			},
		},
		'delete-poll-logs': {
			on: 'click',
			run( el ) {
				deleteThisPollLogs(
					pollAttr( el, 'id' ),
					pollAttr( el, 'confirm' ),
					pollAttr( el, 'nonce' ),
				);
			},
		},
		'open-poll': {
			on: 'click',
			run( el ) {
				openingPoll(
					pollAttr( el, 'id' ),
					pollAttr( el, 'confirm' ),
					pollAttr( el, 'nonce' ),
				);
			},
		},
		'close-poll': {
			on: 'click',
			run( el ) {
				closingPoll(
					pollAttr( el, 'id' ),
					pollAttr( el, 'confirm' ),
					pollAttr( el, 'nonce' ),
				);
			},
		},
		'add-answer': {
			on: 'click',
			run() {
				addPollAnswerAdd();
			},
		},
		'add-answer-edit': {
			on: 'click',
			run() {
				addPollAnswerEdit();
			},
		},
		'remove-answer': {
			on: 'click',
			run( el ) {
				removePollAnswerAdd( pollAttr( el, 'answer' ) );
			},
		},
		'toggle-timestamp': {
			on: 'click',
			run() {
				checkPolltimestamp();
			},
		},
		'toggle-expiry': {
			on: 'click',
			run() {
				checkPollexpiry();
			},
		},
		'toggle-multiple': {
			on: 'change',
			run() {
				checkPollqMultiple();
			},
		},
		'total-votes': {
			on: 'focusout',
			run() {
				checkTotalvotes();
			},
		},
	};

	// Route An Event To Its data-poll-action Handler
	function pollAdminDispatch( event ) {
		const target = event.target;
		if ( ! target || typeof target.closest !== 'function' ) {
			return;
		}

		const el = target.closest( '[data-poll-action]' );
		if ( ! el ) {
			return;
		}

		const action = pollAdminActions[ el.getAttribute( 'data-poll-action' ) ];
		if ( ! action || action.on !== event.type ) {
			return;
		}

		if ( el.tagName === 'A' ) {
			event.preventDefault();
		}

		action.run( el );
	}

	document.addEventListener( 'click', pollAdminDispatch );
	document.addEventListener( 'change', pollAdminDispatch );
	document.addEventListener( 'focusout', pollAdminDispatch );
}() );
