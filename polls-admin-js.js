/**
 * WP-Polls admin pages.
 *
 * Nothing here is exported. Admin markup asks for behaviour through
 * data-poll-action attributes, which are routed by the dispatcher at the
 * bottom of this file.
 */
(function () {
	"use strict";

	var pollsAdminL10n = window.pollsAdminL10n || {};

	var global_poll_id = 0;
	var global_poll_aid = 0;
	var global_poll_aid_votes = 0;
	var count_poll_answer_new = 0;
	var count_poll_answer = 3;
	var temp_vote_count = 0;

	// Post An Admin Action And Show The Response In The Message Box
	function poll_admin_request(fields, on_success) {
		var body = new URLSearchParams();
		body.append("action", "polls-admin");
		Object.keys(fields).forEach(function (name) {
			body.append(name, fields[name]);
		});

		fetch(pollsAdminL10n.admin_ajax_url, {
			method: "POST",
			credentials: "same-origin",
			cache: "no-cache",
			body: body,
		})
			.then(function (response) {
				return response.text();
			})
			.then(function (data) {
				var message = document.getElementById("message");
				if (message) {
					message.innerHTML = data;
					message.style.display = "";
				}
				if (on_success) {
					on_success();
				}
			});
	}

	// Remove An Element By Its ID
	function poll_remove_element(id) {
		var element = document.getElementById(id);
		if (element) {
			element.remove();
		}
	}

	// Is The "Yes" Confirmation Checkbox Ticked?
	function poll_delete_logs_confirmed() {
		var checkbox = document.getElementById("delete_logs_yes");
		return !!checkbox && checkbox.checked;
	}

	// Replace The Poll Logs Panel With The "No Logs" Message
	function poll_clear_logs_panel() {
		var logs = document.getElementById("poll_logs");
		if (logs) {
			logs.textContent = pollsAdminL10n.text_no_poll_logs;
		}
	}

	// Delete Poll
	function delete_poll(poll_id, poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		global_poll_id = poll_id;
		poll_admin_request(
			{
				do: pollsAdminL10n.text_delete_poll,
				pollq_id: poll_id,
				_ajax_nonce: nonce,
			},
			function () {
				poll_remove_element("poll-" + global_poll_id);
			}
		);
	}

	// Delete Poll Logs
	function delete_poll_logs(poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		if (!poll_delete_logs_confirmed()) {
			alert(pollsAdminL10n.text_checkbox_delete_all_logs);
			return;
		}
		poll_admin_request(
			{
				do: pollsAdminL10n.text_delete_all_logs,
				delete_logs_yes: "yes",
				_ajax_nonce: nonce,
			},
			poll_clear_logs_panel
		);
	}

	// Delete Individual Poll Logs
	function delete_this_poll_logs(poll_id, poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		if (!poll_delete_logs_confirmed()) {
			alert(pollsAdminL10n.text_checkbox_delete_poll_logs);
			return;
		}
		global_poll_id = poll_id;
		poll_admin_request(
			{
				do: pollsAdminL10n.text_delete_poll_logs,
				pollq_id: poll_id,
				delete_logs_yes: "yes",
				_ajax_nonce: nonce,
			},
			function () {
				poll_clear_logs_panel();

				var display = document.getElementById("poll_logs_display");
				if (display) {
					display.style.display = "none";
				}

				var empty = document.getElementById("poll_logs_display_none");
				if (empty) {
					empty.style.display = "";
				}
			}
		);
	}

	// Delete Poll Answer
	function delete_poll_ans(poll_id, poll_aid, poll_aid_vote, poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		global_poll_id = poll_id;
		global_poll_aid = poll_aid;
		global_poll_aid_votes = poll_aid_vote;
		temp_vote_count = 0;
		poll_admin_request(
			{
				do: pollsAdminL10n.text_delete_poll_ans,
				pollq_id: poll_id,
				polla_aid: poll_aid,
				_ajax_nonce: nonce,
			},
			function () {
				var total_votes = document.getElementById("poll_total_votes");
				if (total_votes) {
					total_votes.textContent =
						parseInt(total_votes.textContent) - parseInt(global_poll_aid_votes);
				}

				var totalvotes_field = document.getElementById("pollq_totalvotes");
				if (totalvotes_field) {
					totalvotes_field.value = temp_vote_count;
				}

				poll_remove_element("poll-answer-" + global_poll_aid);
				check_totalvotes();
				reorder_answer_num();
			}
		);
	}

	// Show Either The Open Or The Close Button
	function poll_toggle_open_close(show_open) {
		var open_button = document.getElementById("open_poll");
		var close_button = document.getElementById("close_poll");
		if (open_button) {
			open_button.style.display = show_open ? "" : "none";
		}
		if (close_button) {
			close_button.style.display = show_open ? "none" : "";
		}
	}

	// Open Poll
	function opening_poll(poll_id, poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		global_poll_id = poll_id;
		poll_admin_request(
			{
				do: pollsAdminL10n.text_open_poll,
				pollq_id: poll_id,
				_ajax_nonce: nonce,
			},
			function () {
				poll_toggle_open_close(false);
			}
		);
	}

	// Close Poll
	function closing_poll(poll_id, poll_confirm, nonce) {
		if (!confirm(poll_confirm)) {
			return;
		}
		global_poll_id = poll_id;
		poll_admin_request(
			{
				do: pollsAdminL10n.text_close_poll,
				pollq_id: poll_id,
				_ajax_nonce: nonce,
			},
			function () {
				poll_toggle_open_close(true);
			}
		);
	}

	// Reorder Answer Numbers
	function reorder_answer_num() {
		var pollq_multiple = document.getElementById("pollq_multiple");
		if (!pollq_multiple) {
			return;
		}

		var selected = parseInt(pollq_multiple.value);
		var previous_size = pollq_multiple.options.length;
		pollq_multiple.innerHTML = "";

		var headers = document.querySelectorAll("#poll_answers tr > th");
		Array.prototype.forEach.call(headers, function (header, index) {
			header.textContent = pollsAdminL10n.text_answer + " " + (index + 1);

			var option = document.createElement("option");
			option.value = index + 1;
			option.textContent = index + 1;
			pollq_multiple.appendChild(option);
		});

		if (selected > 1) {
			var current_size = pollq_multiple.options.length;
			if (selected <= current_size) {
				pollq_multiple.options[selected - 1].selected = true;
			} else if (selected === previous_size) {
				pollq_multiple.options[current_size - 1].selected = true;
			}
		}
	}

	// Calculate Total Votes
	function check_totalvotes() {
		temp_vote_count = 0;

		var vote_fields = document.querySelectorAll(
			'#poll_answers tr td input[size="4"]'
		);
		Array.prototype.forEach.call(vote_fields, function (vote_field) {
			var votes = parseInt(vote_field.value);
			if (!isNaN(votes)) {
				temp_vote_count += votes;
			}
		});

		var totalvotes_field = document.getElementById("pollq_totalvotes");
		if (totalvotes_field) {
			totalvotes_field.value = temp_vote_count;
		}
	}

	// Build One Answer Row For The Add/Edit Poll Pages
	// Built through the DOM rather than an HTML string so the translated button
	// label can never be parsed as markup.
	function poll_build_answer_row(row_id, input_name, on_remove, votes_name) {
		var row = document.createElement("tr");
		row.id = row_id;

		var heading = document.createElement("th");
		heading.width = "20%";
		heading.scope = "row";
		heading.setAttribute("valign", "top");
		row.appendChild(heading);

		var answer_cell = document.createElement("td");
		answer_cell.width = votes_name ? "60%" : "80%";

		var answer_field = document.createElement("input");
		answer_field.type = "text";
		answer_field.size = 50;
		answer_field.maxLength = 200;
		answer_field.name = input_name;
		answer_cell.appendChild(answer_field);
		answer_cell.appendChild(document.createTextNode("   "));

		var remove_button = document.createElement("input");
		remove_button.type = "button";
		remove_button.className = "button";
		remove_button.value = pollsAdminL10n.text_remove_poll_answer;
		remove_button.addEventListener("click", on_remove);
		answer_cell.appendChild(remove_button);
		row.appendChild(answer_cell);

		if (votes_name) {
			var votes_cell = document.createElement("td");
			votes_cell.width = "20%";
			votes_cell.align = pollsAdminL10n.text_direction;
			votes_cell.appendChild(document.createTextNode("0 "));

			var votes_field = document.createElement("input");
			votes_field.type = "text";
			votes_field.size = 4;
			votes_field.name = votes_name;
			votes_field.value = "0";
			votes_field.addEventListener("blur", check_totalvotes);
			votes_cell.appendChild(votes_field);
			row.appendChild(votes_cell);
		}

		return row;
	}

	// Add Poll's Answer In Add Poll Page
	function add_poll_answer_add() {
		var answers = document.getElementById("poll_answers");
		if (!answers) {
			return;
		}

		var row_id = "poll-answer-" + count_poll_answer;
		answers.appendChild(
			poll_build_answer_row(row_id, "polla_answers[]", function () {
				poll_remove_element(row_id);
				reorder_answer_num();
			})
		);

		count_poll_answer++;
		reorder_answer_num();
	}

	// Remove Poll's Answer In Add Poll Page
	function remove_poll_answer_add(poll_answer_id) {
		poll_remove_element("poll-answer-" + poll_answer_id);
		reorder_answer_num();
	}

	// Add Poll's Answer In Edit Poll Page
	function add_poll_answer_edit() {
		var answers = document.getElementById("poll_answers");
		if (!answers) {
			return;
		}

		var row_id = "poll-answer-new-" + count_poll_answer_new;
		answers.appendChild(
			poll_build_answer_row(
				row_id,
				"polla_answers_new[]",
				function () {
					poll_remove_element(row_id);
					check_totalvotes();
					reorder_answer_num();
				},
				"polla_answers_new_votes[]"
			)
		);

		count_poll_answer_new++;
		reorder_answer_num();
	}

	// Remove Poll's Answer In Edit Poll Page
	function remove_poll_answer_edit(poll_answer_new_id) {
		poll_remove_element("poll-answer-new-" + poll_answer_new_id);
		check_totalvotes();
		reorder_answer_num();
	}

	// Check Poll Whether It Is Multiple Poll Answer
	function check_pollq_multiple() {
		var multiple_yes = document.getElementById("pollq_multiple_yes");
		var multiple = document.getElementById("pollq_multiple");
		if (!multiple_yes || !multiple) {
			return;
		}

		if (parseInt(multiple_yes.value) === 1) {
			multiple.disabled = false;
		} else {
			multiple.value = 1;
			multiple.disabled = true;
		}
	}

	// Show/Hide Poll's Timestamp
	function check_polltimestamp() {
		var edit_timestamp = document.getElementById("edit_polltimestamp");
		var timestamp = document.getElementById("pollq_timestamp");
		if (edit_timestamp && timestamp) {
			timestamp.style.display = edit_timestamp.checked ? "" : "none";
		}
	}

	// Show/Hide Poll's Expiry Date
	function check_pollexpiry() {
		var expiry_no = document.getElementById("pollq_expiry_no");
		var expiry = document.getElementById("pollq_expiry");
		if (expiry_no && expiry) {
			expiry.style.display = expiry_no.checked ? "none" : "";
		}
	}

	// Read A data-poll-* Attribute Off An Element
	function poll_attr(element, name) {
		return element.getAttribute("data-poll-" + name) || "";
	}

	// Actions The Admin Pages Can Ask For Through data-poll-action
	// 'on' is the event the action answers to. Anything listening for "focusout"
	// replaces what used to be an inline onblur; focusout is used rather than blur
	// because blur does not bubble up to the document.
	var poll_admin_actions = {
		"delete-poll": {
			on: "click",
			run: function (el) {
				delete_poll(
					poll_attr(el, "id"),
					poll_attr(el, "confirm"),
					poll_attr(el, "nonce")
				);
			},
		},
		"delete-answer": {
			on: "click",
			run: function (el) {
				delete_poll_ans(
					poll_attr(el, "id"),
					poll_attr(el, "aid"),
					poll_attr(el, "votes"),
					poll_attr(el, "confirm"),
					poll_attr(el, "nonce")
				);
			},
		},
		"delete-all-logs": {
			on: "click",
			run: function (el) {
				delete_poll_logs(poll_attr(el, "confirm"), poll_attr(el, "nonce"));
			},
		},
		"delete-poll-logs": {
			on: "click",
			run: function (el) {
				delete_this_poll_logs(
					poll_attr(el, "id"),
					poll_attr(el, "confirm"),
					poll_attr(el, "nonce")
				);
			},
		},
		"open-poll": {
			on: "click",
			run: function (el) {
				opening_poll(
					poll_attr(el, "id"),
					poll_attr(el, "confirm"),
					poll_attr(el, "nonce")
				);
			},
		},
		"close-poll": {
			on: "click",
			run: function (el) {
				closing_poll(
					poll_attr(el, "id"),
					poll_attr(el, "confirm"),
					poll_attr(el, "nonce")
				);
			},
		},
		"add-answer": {
			on: "click",
			run: function () {
				add_poll_answer_add();
			},
		},
		"add-answer-edit": {
			on: "click",
			run: function () {
				add_poll_answer_edit();
			},
		},
		"remove-answer": {
			on: "click",
			run: function (el) {
				remove_poll_answer_add(poll_attr(el, "answer"));
			},
		},
		"toggle-timestamp": {
			on: "click",
			run: function () {
				check_polltimestamp();
			},
		},
		"toggle-expiry": {
			on: "click",
			run: function () {
				check_pollexpiry();
			},
		},
		"toggle-multiple": {
			on: "change",
			run: function () {
				check_pollq_multiple();
			},
		},
		"total-votes": {
			on: "focusout",
			run: function () {
				check_totalvotes();
			},
		},
		"go-back": {
			on: "click",
			run: function () {
				history.go(-1);
			},
		},
	};

	// Route An Event To Its data-poll-action Handler
	function poll_admin_dispatch(event) {
		var target = event.target;
		if (!target || typeof target.closest !== "function") {
			return;
		}

		var el = target.closest("[data-poll-action]");
		if (!el) {
			return;
		}

		var action = poll_admin_actions[el.getAttribute("data-poll-action")];
		if (!action || action.on !== event.type) {
			return;
		}

		if (el.tagName === "A") {
			event.preventDefault();
		}

		action.run(el);
	}

	document.addEventListener("click", poll_admin_dispatch);
	document.addEventListener("change", poll_admin_dispatch);
	document.addEventListener("focusout", poll_admin_dispatch);
})();
