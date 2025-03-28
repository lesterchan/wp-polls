/**
 * WP-Polls Frontend JavaScript
 * 
 * Handles frontend poll creation form interactivity.
 */
(function($) {
    'use strict';
    
    // Variables
    let answerCount = 2;
    
    /**
     * Initialize the poll creation form
     */
    function initPollCreateForm() {
        // Form elements
        const $form = $('#polls-create-form');
        const $pollType = $('#poll-type');
        const $multipleOptions = $('#poll-multiple-options');
        const $pollAnswersContainer = $('#poll-answers-container');
        const $pollExpiry = $('#poll-expiry');
        const $pollExpiryOptions = $('#poll-expiry-options');
        const $message = $('.polls-frontend-message');
        
        // Toggle multiple choice options visibility
        $pollType.on('change', function() {
            if ($(this).val() === 'multiple') {
                $multipleOptions.slideDown(200);
            } else {
                $multipleOptions.slideUp(200);
            }
            
            // If ranked choice is selected, add a note about drag to reorder
            if ($(this).val() === 'ranked') {
                if ($('.ranked-choice-note').length === 0) {
                    $pollAnswersContainer.before('<div class="ranked-choice-note"><em>' + 
                        'In ranked choice polls, voters will be able to drag answers to rank them in order of preference.' + 
                        '</em></div>');
                }
            } else {
                $('.ranked-choice-note').remove();
            }
        });
        
        // Toggle expiry options visibility
        $pollExpiry.on('change', function() {
            if ($(this).is(':checked')) {
                $pollExpiryOptions.slideDown(200);
            } else {
                $pollExpiryOptions.slideUp(200);
            }
        });
        
        // Add new answer field
        $('#poll-add-answer').on('click', function(e) {
            e.preventDefault();
            addAnswerField();
        });
        
        // Remove answer field
        $pollAnswersContainer.on('click', '.poll-answer-remove', function() {
            // Don't allow removing if we have only 2 answers
            if ($pollAnswersContainer.find('.poll-answer-row').length <= 2) {
                return;
            }
            
            if (confirm(pollsCreateL10n.confirm_delete)) {
                $(this).closest('.poll-answer-row').remove();
                
                // Renumber remaining answers
                renumberAnswers();
            }
        });
        
        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Clear previous messages
            $message.hide().removeClass('error success');
            
            // Basic validation
            const question = $('#poll-question').val().trim();
            
            if (!question) {
                showMessage(pollsCreateL10n.error_question, 'error');
                return;
            }
            
            // Count non-empty answers
            let validAnswersCount = 0;
            $pollAnswersContainer.find('input[name="poll_answers[]"]').each(function() {
                if ($(this).val().trim()) {
                    validAnswersCount++;
                }
            });
            
            if (validAnswersCount < 2) {
                showMessage(pollsCreateL10n.error_answers, 'error');
                return;
            }
            
            // Disable submit button to prevent multiple submissions
            $('#poll-submit').prop('disabled', true).text('Creating...');
            
            // Prepare data for AJAX submission
            const formData = new FormData($form[0]);
            formData.append('action', 'polls_create');
            formData.append('nonce', pollsCreateL10n.nonce);
            
            // Submit the form via AJAX
            $.ajax({
                url: pollsCreateL10n.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showMessage(response.data.message, 'success');
                        
                        // Clear the form
                        $form[0].reset();
                        
                        // Reset the answers to just 2
                        $pollAnswersContainer.empty();
                        answerCount = 0;
                        addAnswerField();
                        addAnswerField();
                        
                        // Hide any options that might be showing
                        $multipleOptions.hide();
                        $pollExpiryOptions.hide();
                        $('.ranked-choice-note').remove();
                        
                        // Show the created poll
                        if (response.data.html) {
                            $form.after('<div class="poll-preview"><h3>Your Poll Preview</h3>' + response.data.html + '</div>');
                        }
                    } else {
                        // Show error message
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    // Show generic error message
                    showMessage(pollsCreateL10n.error_message, 'error');
                },
                complete: function() {
                    // Re-enable submit button
                    $('#poll-submit').prop('disabled', false).text('Create Poll');
                }
            });
        });
    }
    
    /**
     * Add a new answer field to the form
     */
    function addAnswerField() {
        answerCount++;
        
        const newAnswer = $(
            '<div class="poll-answer-row" data-id="' + answerCount + '">' +
            '<span class="poll-answer-number">' + answerCount + '</span>' +
            '<input type="text" name="poll_answers[]" placeholder="Enter answer here">' +
            '<button type="button" class="poll-answer-remove">&times;</button>' +
            '</div>'
        );
        
        $('#poll-answers-container').append(newAnswer);
        
        // Show remove buttons if we have more than 2 answers
        if (answerCount > 2) {
            $('.poll-answer-remove').css('visibility', 'visible');
        }
        
        // Focus the new input
        newAnswer.find('input').focus();
    }
    
    /**
     * Renumber the answer fields after deletion
     */
    function renumberAnswers() {
        const $answers = $('#poll-answers-container').find('.poll-answer-row');
        
        // If we're down to 2 answers, hide the remove buttons
        if ($answers.length <= 2) {
            $('.poll-answer-remove').css('visibility', 'hidden');
        } else {
            $('.poll-answer-remove').css('visibility', 'visible');
        }
        
        // Update numbers
        $answers.each(function(index) {
            $(this).attr('data-id', index + 1);
            $(this).find('.poll-answer-number').text(index + 1);
        });
        
        // Reset global counter
        answerCount = $answers.length;
    }
    
    /**
     * Show a message to the user
     * 
     * @param {string} text Message text
     * @param {string} type Message type (success or error)
     */
    function showMessage(text, type) {
        const $message = $('.polls-frontend-message');
        
        $message
            .removeClass('success error')
            .addClass(type)
            .html(text)
            .fadeIn(200);
        
        // Scroll to the message
        $('html, body').animate({
            scrollTop: $message.offset().top - 50
        }, 200);
    }
    
    // Initialize when the document is ready
    $(document).ready(function() {
        if ($('#polls-create-form').length) {
            initPollCreateForm();
        }
    });
    
})(jQuery);
