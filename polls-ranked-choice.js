/**
 * WP-Polls Ranked Choice JavaScript
 * 
 * This file adds drag and drop ranking functionality for ranked choice polls.
 */
(function($) {
	'use strict';

	// Initialize the ranked choice poll functionality
	function initRankedChoicePoll(pollId) {
		// Find the poll container
		const pollContainer = $('#polls-' + pollId);
		if (!pollContainer.length) return;
		
		// Get the form where answers are
		const pollForm = $('#polls_form_' + pollId);
		if (!pollForm.length) return;
		
		// Get the answers list
		const answersList = pollForm.find('.wp-polls-ranked-choice');
		if (!answersList.length) return;
		
		// Get all the poll answers
		const answers = answersList.find('.poll-answer');
		
		// Initialize the drag and drop functionality
		let draggedItem = null;
		
		// Add listeners to each answer
		answers.each(function() {
			const item = $(this);
			
			// Set up drag events
			item.attr('draggable', 'true');
			
			item.on('dragstart', function(e) {
				draggedItem = item;
				item.addClass('dragging');
				
				// Store the id of the dragged element
				if (e.originalEvent.dataTransfer) {
					e.originalEvent.dataTransfer.setData('text/plain', item.attr('id'));
				}
			});
			
			item.on('dragend', function() {
				item.removeClass('dragging');
				draggedItem = null;
				
				// Update the radio button values based on new positions
				updateRankValues();
			});
			
			item.on('dragover', function(e) {
				e.preventDefault();
			});
			
			item.on('dragenter', function(e) {
				e.preventDefault();
				if (draggedItem && draggedItem[0] !== item[0]) {
					// Determine if we should insert before or after
					const rect = item[0].getBoundingClientRect();
					const y = e.originalEvent.clientY;
					const dropPos = (y - rect.top) / (rect.bottom - rect.top);
					
					if (dropPos < 0.5) {
						// Insert before
						item.before(draggedItem);
					} else {
						// Insert after
						item.after(draggedItem);
					}
					
					// Update the rank displays
					updateRankDisplay();
				}
			});
		});
		
		// Create a mapping of original inputs to store their values and position
		const originalInputValues = [];
		
		// Initialize the array with original values and positions
		answers.each(function(index) {
			const input = $(this).find('input[type="radio"], input[type="checkbox"]');
			const originalValue = input.val();
			
			// Store original value in data attribute for reference
			input.data('original-value', originalValue);
			input.data('original-index', index);
			
			// Add to our tracking array
			originalInputValues.push({
				value: originalValue,
				element: input
			});
		});
		
		// Add hidden fields to the form to store the rank order
		pollForm.append('<input type="hidden" name="ranked_poll" value="1" />');
		pollForm.append('<input type="hidden" name="ranked_poll_id" value="' + pollId + '" />');
		pollForm.append('<input type="hidden" name="ranked_order" id="ranked_order_' + pollId + '" value="" />');
		
		// Store original IDs for debugging
		const originalIds = [];
		answers.each(function() {
			const input = $(this).find('input[type="radio"], input[type="checkbox"]');
			originalIds.push(input.val());
		});
		pollForm.append('<input type="hidden" name="original_order" value="' + originalIds.join(',') + '" />');
		
		// Completely replace the form submission process for ranked polls
		pollForm.on('submit', function(e) {
			// Prevent default submission
			e.preventDefault();
			
			// First, clear any existing values that might be checked
			answersList.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);
			
			// Get current order of answers after dragging
			const currentOrder = [];
			const displayOrder = [];
			const mappedValues = {}; // Key is position (0,1,2...), value is the answer ID
			
			// Loop through answers in their current order after dragging
			answersList.find('.poll-answer').each(function(newPosition) {
				const $answer = $(this);
				const input = $answer.find('input[type="radio"], input[type="checkbox"]');
				const originalValue = input.data('original-value');
				const label = $answer.find('label').text().trim();
				
				// Add to our tracking arrays
				currentOrder.push(originalValue);
				displayOrder.push(label);
				
				// Map the new position to the original value
				mappedValues[newPosition] = originalValue;
			});
			
			// Create appropriate poll_X value for server - this is critical
			// For ranked polls, higher rank (lower index) = higher value
			// Format as "value1=N,value2=N-1,..."
			const pollValueParts = [];
			Object.keys(mappedValues).forEach(function(position) {
				const value = mappedValues[position];
				// For this value, we need to check its input
				answersList.find('.poll-answer').each(function() {
					const input = $(this).find('input[type="radio"], input[type="checkbox"]');
					if (input.val() == value) {
						input.prop('checked', true);
					}
				});
				pollValueParts.push(value);
			});
			
			// Set the correct poll_X value that the server expects
			const pollValue = pollValueParts.join(',');
			
			// For debugging 
			console.log("Poll submission data:", {
				pollId: pollId,
				currentOrder: currentOrder,
				displayOrder: displayOrder,
				pollValue: pollValue
			});
			
			// Create a POST data object
			const postData = {
				action: 'polls',
				view: 'process',
				poll_id: pollId,
				ranked_poll: '1',
				ranked_order: currentOrder.join(',')
			};
			
			// Add the standard poll value expected by the server
			postData['poll_' + pollId] = pollValue;
			
			// Add the nonce
			postData['poll_' + pollId + '_nonce'] = $('#poll_' + pollId + '_nonce').val();
			
			// Submit using AJAX directly
			$.ajax({
				type: 'POST',
				url: pollsL10n.ajax_url,
				data: postData,
				success: function(response) {
					// Replace the poll with the result
					$('#polls-' + pollId).replaceWith(response);
					
					// Hide the loading indicator if shown
					if (pollsL10n.show_loading) {
						$('#polls-' + pollId + '-loading').hide();
					}
				},
				error: function(xhr, status, error) {
					console.error('Ranked poll submission error:', error);
					alert('Error submitting ranked poll: ' + error);
				}
			});
			
			return false;
		});
		
		// Function to update the radio/checkbox values based on the new ranking
		function updateRankValues() {
			// Get the current order of answers
			const currentOrder = answersList.find('.poll-answer');
			
			// For each answer in the new order, update its corresponding hidden input value
			currentOrder.each(function(newIndex) {
				// Find the input within this answer (radio or checkbox)
				const input = $(this).find('input[type="radio"], input[type="checkbox"]');
				
				// Get the original index to determine the correct order
				const originalIndex = input.data('original-index');
				
				// Store the new rank as a data attribute
				input.data('rank-position', newIndex);
				
				// The value for submission should be the original value
				// We're just changing the order visually, not the value IDs
				const originalValue = input.data('original-value');
				if (originalValue) {
					input.val(originalValue);
				}
				
				// Check/select all inputs in ranked choice polls
				// This ensures the form will submit with all values selected
				input.prop('checked', true);
				
				// Update the rank display
				$(this).find('.poll-answer-rank').text(newIndex + 1);
			});
		}
		
		// Function to update the rank display (the numbers)
		function updateRankDisplay() {
			answersList.find('.poll-answer').each(function(index) {
				$(this).find('.poll-answer-rank').text(index + 1);
			});
		}
		
		// Initially update the rank display and select all inputs
		updateRankDisplay();
		updateRankValues(); // Auto-check all inputs on page load for ranked choice polls
		
		// Mobile touch support for drag and drop
		if ('ontouchstart' in window) {
			let touchStartY, currentItem;
			
			answers.each(function() {
				const item = $(this);
				
				item.on('touchstart', function(e) {
					touchStartY = e.originalEvent.touches[0].clientY;
					currentItem = item;
					item.addClass('dragging');
				});
				
				item.on('touchmove', function(e) {
					if (!currentItem) return;
					
					e.preventDefault();
					const touchY = e.originalEvent.touches[0].clientY;
					const items = answersList.find('.poll-answer');
					let closestItem = null;
					let minDistance = Number.MAX_VALUE;
					
					// Find the closest item to the current touch position
					items.each(function() {
						if ($(this)[0] === currentItem[0]) return;
						
						const rect = this.getBoundingClientRect();
						const centerY = rect.top + rect.height / 2;
						const distance = Math.abs(touchY - centerY);
						
						if (distance < minDistance) {
							minDistance = distance;
							closestItem = $(this);
						}
					});
					
					// Reposition the current item
					if (closestItem) {
						const closestRect = closestItem[0].getBoundingClientRect();
						if (touchY < closestRect.top + closestRect.height / 2) {
							closestItem.before(currentItem);
						} else {
							closestItem.after(currentItem);
						}
						
						// Update the rank displays
						updateRankDisplay();
					}
				});
				
				item.on('touchend', function() {
					if (!currentItem) return;
					currentItem.removeClass('dragging');
					updateRankValues();
					currentItem = null;
				});
			});
		}
	}
	
	// Initialize ranked choice polls when the document is ready
	$(document).ready(function() {
		// Look for all poll containers
		$('.wp-polls').each(function() {
			// Get the poll ID from the id attribute (format: polls-X)
			const pollIdMatch = this.id.match(/^polls-(\d+)$/);
			if (pollIdMatch && pollIdMatch[1]) {
				const pollId = pollIdMatch[1];
				
				// Check if this is a ranked choice poll by looking for the class
				if ($(this).find('.wp-polls-ranked-choice').length) {
					initRankedChoicePoll(pollId);
				}
			}
		});
	});
	
	// Re-initialize after AJAX poll loading
	$(document).ajaxSuccess(function(event, xhr, settings) {
		if (settings.data && settings.data.indexOf('action=polls') !== -1) {
			// Look for all poll containers
			$('.wp-polls').each(function() {
				// Get the poll ID from the id attribute (format: polls-X)
				const pollIdMatch = this.id.match(/^polls-(\d+)$/);
				if (pollIdMatch && pollIdMatch[1]) {
					const pollId = pollIdMatch[1];
					
					// Check if this is a ranked choice poll by looking for the class
					if ($(this).find('.wp-polls-ranked-choice').length) {
						initRankedChoicePoll(pollId);
					}
				}
			});
		}
	});
	
})(jQuery);
