var global_poll_id = 0;
var global_poll_aid = 0;
var global_poll_aid_votes = 0;
var global_templates_set_id = 0;
var global_poll_debounce_timer = 0;
var count_poll_answer_new = 0;
var count_poll_answer = 3;
var settings_obj = {};
var poll_context = '';

if (window.location.href.match('polls\-add\.php') != null) poll_context = 'add';
else if (window.location.href.match('polls\-manager\.php&mode=edit') != null) poll_context = 'edit';

window.onload = (event) => {
    if (poll_context == 'add' || poll_context == 'edit') { //only when address bar contains polls-add.php or polls-manager.php (add/edit poll)
        cleanPreviousSearch();
        init_multiselect();
    }
};

// Forms clearing function
function cleanPreviousSearch() {
    let checkedTypeValue = document.querySelector('#pollq_answer_entries_type_selection input[name="pollq_expected_atype"]:checked').value;
    if (checkedTypeValue === null) {
        const answerTypeText = document.querySelector('#pollq_answer_entries_type_selection input[name="pollq_expected_atype"][value="text"]');
        answerTypeText.checked = true;
        checkedTypeValue = 'text';
    }
    check_poll_answer_entries_type(checkedTypeValue, 'init');
    
    //text answers type
    check_pollq_multiple();
    check_pollexpiry();
    
    //object answers type
    const checkboxes = document.querySelectorAll('#pollq_post_types_list_options input[type="checkbox"]');
    for(var i=0; i<checkboxes.length;i++){
        let checkbox = checkboxes[i];
        if(!checkbox.disabled){
            checkbox.checked = false;
        }
    }
    const searchKeyword = document.getElementById('pollq_search_keyword');
    searchKeyword.value = '';

    const searchTax = document.getElementById('pollq_tax_list');
    searchTax.value = '';
    
    const filterTermsInput = document.getElementById('terms_search_input');
    filterTermsInput.value = '';

    const radios = document.querySelectorAll('#pollq_terms_results input[type="radio"]');
    for(var i=0; i<radios.length;i++){
        let radio = radios[i];
        if(!radio.disabled){
            radio.checked = false;
        }
    }
}

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
function delete_poll_ans(poll_id, poll_aid, poll_aid_vote, nonce, poll_confirm) {
    delete_poll_ans_confirm = confirm(poll_confirm);
    if(delete_poll_ans_confirm) {
        global_poll_id = poll_id;
        global_poll_aid = poll_aid;
        global_poll_aid_votes = poll_aid_vote;
        temp_vote_count = 0;
        jQuery(document).ready(function($) {
            $('#poll_total_votes').html((parseInt($('#poll_total_votes').html()) - parseInt(global_poll_aid_votes)));
            $('#pollq_totalvotes').val(temp_vote_count);
            const answer_tr_elem = $('#poll-answer-' + global_poll_aid);
            const answer_input_elem = answer_tr_elem.find('input[name="polla_aid-' + global_poll_aid + '"]');
            if ( answer_input_elem[0].dataset.type === 'post_types_items' ) {
                sync_selection_with_object_picker_list('unpick', answer_input_elem.attr('id').replace('_selected', ''), true, null);
            }
            answer_tr_elem.remove();
            check_totalvotes();
            reorder_answer_num();
        });
    }
}

// Batch delete Poll Answer 
function batch_delete_poll_ans(answers_batch_array, poll_confirm, nonce) { //'answers_batch_array' is an array composed of objects with the following properties: 'poll_id', 'poll_aid' and 'poll_aid_vote')
    const batch_delete_answers = (answers_batch_array, nonce) => {
        temp_vote_count = 0;
        admin_loader(true, 'form');
        jQuery(document).ready(function($) {
                for (const answer of answers_batch_array) {
                    global_poll_aid_votes += answer.poll_aid_votes;
                    $('#poll-answer-' + answer.poll_aid).remove();
                }
                $('#poll_total_votes').html((parseInt($('#poll_total_votes').html()) - parseInt(global_poll_aid_votes)));
                $('#pollq_totalvotes').val(temp_vote_count);
                check_totalvotes();
                reorder_answer_num();
                admin_loader(false, 'form');
        });
    }
    if (poll_confirm === 'no_confirm'){
        batch_delete_answers(answers_batch_array, nonce);
    } else {
        delete_poll_ans_confirm = confirm(poll_confirm);
        if(delete_poll_ans_confirm) {
            batch_delete_answers(answers_batch_array, nonce);
        }
    }
}

// Add Default Template Sets
function insert_builtin_templates_set(answers_type, nonce) {
    jQuery(document).ready(function($) {
        $.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_insert_builtin_templates_set + '&answers_type=' + answers_type + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
            data = JSON.parse(data); 
            $('#message').html(data.msg);
            $('#message').show();
            $('#manage_templates').prepend(data.content);
            const firstChild = $("#manage_templates tr:nth-child(1)");
            const secondChild = $("#manage_templates tr:nth-child(2)");
            const isAlternateClass = ( secondChild.hasClass('alternate') ) ? firstChild.removeClass('alternate') : firstChild.addClass('alternate');
        }});
    });
}

// Duplicate Template
function duplicate_templates_set(templates_set_id, nonce) {
    global_templates_set_id = templates_set_id;
    jQuery(document).ready(function($) {
        $.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_duplicate_templates_set + '&polltpl_id=' + templates_set_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
            data = JSON.parse(data); 
            $('#message').html(data.msg);
            $('#message').show();
            $('#manage_templates').prepend(data.content);
            const firstChild = $("#manage_templates tr:nth-child(1)");
            const secondChild = $("#manage_templates tr:nth-child(2)");
            const isAlternateClass = ( secondChild.hasClass('alternate') ) ? firstChild.removeClass('alternate') : firstChild.addClass('alternate');            
        }});
    });
}

// Delete Template
function delete_templates_set(templates_set_id, templates_set_confirm, nonce) {
    delete_templates_set_confirm = confirm(templates_set_confirm);
    if(delete_templates_set_confirm) {
        global_templates_set_id = templates_set_id;
        jQuery(document).ready(function($) {
            $.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_delete_templates_set + '&polltpl_id=' + templates_set_id + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
                data = JSON.parse(data); 
                $('#message').html(data.msg);
                $('#message').show();
                $('#manage_templates').html(data.content);
            }});
        });
    }
}

// Reset All Templates
function reset_all_templates_sets(reset_all_templates_confirm, nonce) {
    reset_all_templates_sets_confirm = confirm(reset_all_templates_confirm);
    if(reset_all_templates_sets_confirm) {
        jQuery(document).ready(function($) {
            if($('#reset_all_templates_sets_yes').is(':checked')) {
                $.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_reset_all_templates_sets + '&reset_all_templates_sets_yes=yes&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
                    data = JSON.parse(data); 
                    $('#message').html(data.msg);
                    $('#message').show();
                    $('#manage_templates').html(data.content);
                }});
            } else {
                alert(pollsAdminL10n.text_checkbox_reset_all_templates);
            }
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

// Set one or all polls to using default templates (poll_id = -1 for all)
function set_poll_to_default_templates(poll_id, poll_confirm, nonce) {
    reset_poll_settings_confirm = confirm(poll_confirm);
    if(reset_poll_settings_confirm) {
        jQuery(document).ready(function($) {
            $.ajax({type: 'POST', url: pollsAdminL10n.admin_ajax_url, data: 'do=' + pollsAdminL10n.text_set_poll_to_default_templates + '&pollq_id=' + poll_id  + '&action=polls-admin&_ajax_nonce=' + nonce, cache: false, success: function (data) {
                $('#message').html(data);
                $('#message').show();
            }});
        }); 
    }
}                   

// Only display the form for the selected answers type and remove answers of any other type than the selected one
function check_poll_answer_entries_type(checkedTypeValue, action = 'change', context = '') {
    switch(context){
        case 'templates': //templates page
            const fieldsList = document.getElementById('obj_answers_fields');
            const toggleClass = (checkedTypeValue == 'object') ? fieldsList.classList.remove("wp-polls-hide") : fieldsList.classList.add("wp-polls-hide");
            break;
        default: //add or edit pages
            const answersList = document.getElementById('poll_answers');
            const isEveryInputEmpty = (inputType) => {
                let answersListInputs = answersList.querySelectorAll('input[type="' + inputType + '"][name*="polla_answers"]:not([name*="votes"]), input[type="' + inputType +'"][name*="polla_aid-"]'); //input name containing "polla_answers" (but not "votes", e.g. polla_answers_votes[]) = unsaved answers, or "poll_aid"  = saved answers.
                for (const answersListInput of answersListInputs)
                    if (answersListInput.value !== '') return false;
                return true;
            }
            const removeAnswers = (answersType, answersCat) => {
                default_ajax_box_result(action);
                document.getElementById("pollq_post_types_list_label").getElementsByTagName('option')[0].innerText = pollsAdminL10n.text_none_selected; //reset object post types select box
                postTypesSelectBoxItems = document.getElementById('pollq_post_types_list_options').querySelectorAll('input[type="checkbox"]');
                for (const postTypesSelectBoxItem of postTypesSelectBoxItems){
                    postTypesSelectBoxItem.checked = false;
                }
                switch(answersCat){
                    case 'unsaved':
                        const unsavedAnswers = answersList.querySelectorAll('tr.poll-unsaved-answers'); 
                        for (const unsavedAnswer of unsavedAnswers){
                            answerID = unsavedAnswer.id.replace(/poll-answer-(new-)?/, '');
                            const startRemoval = (poll_context == 'edit') ? remove_poll_answer_edit(answerID, answersType) : remove_poll_answer_add(answerID, answersType);
                        }
                        break;
                    case 'saved':
                        const savedAnswers = answersList.querySelectorAll('tr.poll-saved-answers');
                        for (const savedAnswer of savedAnswers){
                            answerID = savedAnswer.id.replace(/poll-answer-(new-)?/, '');
                            const startRemoval = remove_poll_answer_add(answerID, answersType);
                        }                       
                        break;
                    case 'all':
                        if (answersList.querySelector('tr.poll-saved-answers'))   removeAnswers(answersType, 'saved');
                        if (answersList.querySelector('tr.poll-unsaved-answers')) removeAnswers(answersType, 'unsaved');
                        const poll_id = document.querySelector('input[type="hidden"][name="pollq_id"]').value;
                        break;
                    }
            }
            const resetAnswerForm = (answersType) => {
                switch(answersType){
                    case 'text':
                        jQuery(document).ready(function($) { 
                            if ( document.getElementById('poll_answers').innerHTML.replace(/\s/g, "").length === 0 ){ //if answer div is empty or containing only whitespaces 
                                switch(poll_context){ 
                                    case 'add': //add two empty answers inputs (as required on 'add' page)
                                        add_poll_answer_add(); 
                                        add_poll_answer_add();
                                        break;
                                    case 'edit': //add two empty answers inputs (as required on 'edit' page) 
                                        add_poll_answer_edit(); 
                                        add_poll_answer_edit(); 
                                        break;
                                }
                            }
                        });
                        break;
                    case 'object':
                        break;
                }           
            }
            const filterSelect = (answersType, selectID) => {
                const selectElem = document.getElementById(selectID);
                const length = selectElem.options.length;
                for (let i = 0; i < length; i++) {
                    const option = selectElem.options[i];
                    if (option.dataset.type == answersType){
                        option.removeAttribute('hidden'); //show option matching answersType
                    } else {
                        option.setAttribute('hidden', 'hidden'); //hide option not matching answersType 
                        option.removeAttribute('selected'); //remove selected attribute if hidden
                    }
                }
                const isSelectedElem = selectElem.options[selectElem.selectedIndex].getAttribute('selected');
                if (!isSelectedElem){ //make sure that at least the default option is selected
                    for (let i = 0; i < length; i++) {
                        const option = selectElem.options[i];
                        if (!option.hidden && option.dataset.default == 1) option.setAttribute('selected', 'selected'); //mark option as selected if not hidden (=matching answerType) and indicated as default 
                    }
                }
            }
            const previousTypeValue = (checkedTypeValue === 'text') ? 'object' : 'text';
            switch(action){
                    case 'init':
                        filterSelect(checkedTypeValue,'pollq_templates_set_id');
                        break;
                    case 'change':
                        if ( !answersList.innerHTML === "" || ( checkedTypeValue === 'text' && !isEveryInputEmpty('checkbox') ) || ( checkedTypeValue === 'object' && !isEveryInputEmpty('text') ) ) { //some unsaved answers have been entered
                            const confirmMsg = (poll_context === 'edit') ? pollsAdminL10n.text_confirm_change_type_edit : pollsAdminL10n.text_confirm_change_type;
                            if (confirm(confirmMsg)) { //ask for confirmation to remove or/and delete 
                                removeTrigger = (poll_context === 'edit') ? removeAnswers(previousTypeValue, 'all') : removeAnswers(previousTypeValue, 'unsaved');
                                filterSelect(checkedTypeValue,'pollq_templates_set_id');
                                wp_polls_delay(function(e){
                                    resetAnswerForm(checkedTypeValue);
                                }, 2000);
                            } else { //cancel type change and leave previousTypeValue radiobutton checked
                                let radios = document.getElementsByName('pollq_expected_atype'); 
                                for (let i = 0, length = radios.length; i < length; i++) {
                                    if (radios[i].value == previousTypeValue) {
                                        radios[i].checked = true;
                                        break;
                                    }
                                }
                                return false;
                            }           
                        } else { //change from empty form to other type
                            removeAnswers(previousTypeValue, 'unsaved');
                            resetAnswerForm(checkedTypeValue);
                            filterSelect(checkedTypeValue,'pollq_templates_set_id');
                        }
                        break;
            }
            
            let answerTypeBlocks = document.querySelectorAll('table.pollq-answers-entries-type-table')
            for (const answerTypeBlock of answerTypeBlocks){
                answerTypeBlock.style.display = "none";
            }
            document.getElementById('pollq_expected_atype_' + checkedTypeValue).style.display = "table"; //display answer block corresponding to $value
    }
}

// Loader animation for admin window
function admin_loader(bool, selector){
    const elementToOverlap = document.querySelector(selector);
    if (!elementToOverlap) return;
    const loaderContainer = document.getElementById('loader_container');
    if (bool){
        elementToOverlap.classList.add('wp-polls-disable');
        loaderContainer.classList.remove("wp-polls-hide");
    } else {
        elementToOverlap.classList.remove('wp-polls-disable');
        loaderContainer.classList.add("wp-polls-hide");     
    }
}

// Retrieve content for object answers mode according to select boxes and keyword (search can be done either by taxonomy OR keyword).
function retrieve_content(contentType, nonce, source = "", postTypes = "", rows_qty = -1, pageNumber = 1) {
    postTypes = (postTypes) ? postTypes : document.getElementById("pollq_post_types_list_options").dataset.values;
    const keywordInput = document.getElementById('pollq_search_keyword');
    const taxSelect = document.getElementById('pollq_tax_list');
    const termsList = document.getElementById("pollq_terms_results_container"); 
    
    if (!postTypes){
        termsList.classList.remove("wp-polls-slidein");
        default_ajax_box_result();
        taxSelect.disabled = "disabled";
        return;
    }
    
    // Set keyword var
    let searchKeyword = "";
    if (source === 'keyword' || source === 'search_all_tax') { //as for the 'search_all_tax' context, keyword should be empty anyway
        searchKeyword = keywordInput.value;
        taxSelect.value = "";
        termsList.classList.remove("wp-polls-slidein"); //hide terms list
    }
    
    // Set taxonomy var
    let searchTax = "";
    if (source === 'tax_select' || source === 'term_select') {
        searchTax = document.getElementById("pollq_tax_list").value;
        keywordInput.value = ""; //reset keyword input field
    }
    
    // Set term var
    let searchTerm = "";
    if (searchTax && source === 'term_select') {
        let checkedTerm = document.querySelector('input[name="pollq_terms_list"]:checked');
        if (checkedTerm) searchTerm = checkedTerm.value;
    }
    
    switch(contentType) {
        case 'post_types_items':
            admin_loader(true, 'form');
            jQuery(document).ready(function($) {
                $.ajax({
                        type: 'POST', 
                        url: pollsAdminL10n.admin_ajax_url, 
                        data: 'do=' + pollsAdminL10n.text_retrieve_content 
                                + '&content_type=' + contentType 
                                + '&post_types=' + postTypes 
                                + '&tax=' + searchTax                               
                                + '&term=' + searchTerm                                                                 
                                + '&keyword=' + searchKeyword 
                                + '&page=' + pageNumber
                                + '&context=' + poll_context
                                + '&action=polls-admin&_ajax_nonce=' + nonce, 
                        cache: false, 
                        success: function (data) {
                                                    if (source === 'post_types_select'){
                                                         termsList.classList.remove("wp-polls-slidein");
                                                         keywordInput.value = ""
                                                         retrieve_content('allowed_tax', nonce);
                                                    }
                                                    
                                                    if (source === 'scroll_load_more'){
                                                        if (!data.includes('<li>')) { //no (more) result, stop inifinite loading 
                                                            $('#pollq_posts_items_select_container').addClass('wp-polls-scroll-no-more');
                                                        } else {
                                                            $('#pollq_posts_items_select_results').append(data);
                                                        }
                                                    } else {
                                                        $('#pollq_posts_items_select_container').removeClass('wp-polls-scroll-no-more');
                                                        $('#pollq_posts_items_select_results').html(data);
                                                        wp_polls_enable_infinite_scroll_for_answers_selection();                        
                                                    }

                                                    // Hide elements already selected from the search results
                                                    let selectedItemsIDsArray = $('#poll_answers input[type="checkbox"]').map(function() {
                                                        return this.getAttribute('value');
                                                    }).get(); //get array of post type items IDs
                                                    if (selectedItemsIDsArray.length !== 0){
                                                        const ulPostItems = $('#pollq_posts_items_select_results').first();                                                 
                                                        ulPostItems.find('input[type=checkbox]').each(function(index,value) {
                                                            if ($.inArray($(this).val(), selectedItemsIDsArray) != -1){
                                                                $(this).parent().parent().addClass('wp-polls-hide');
                                                            }
                                                        });
                                                    }
                                                    if (source !== 'post_types_select') admin_loader(false, 'form'); //else loader will be closed within the call to 'allowed_case' case.
                                                }
                });
            });
            break;
        case 'allowed_tax':
            jQuery(document).ready(function($) {
                $.ajax({
                        type: 'POST', 
                        url: pollsAdminL10n.admin_ajax_url, 
                        data: 'do=' + pollsAdminL10n.text_retrieve_content 
                            + '&content_type=' + contentType 
                            + '&post_types=' + postTypes 
                            + '&action=polls-admin&_ajax_nonce=' + nonce, 
                        cache: false, 
                        success: function (data) {
                                                    $('#pollq_tax_list').html(data);
                                                    taxSelect.disabled = "";
                                                }
                });
                admin_loader(false, 'form'); //close loader displayed from the 'post_types_items' case in which a callback to 'allowed_tax' has been made.  
            });
            break;
        case 'terms':
            if (!searchTax){ 
                retrieve_content('post_types_items', nonce, 'search_all_tax'); //return list of selected post types items for all tax
                return;
            } 
            admin_loader(true, 'form');
            jQuery(document).ready(function($) {
                $.ajax({
                        type: 'POST', 
                        url: pollsAdminL10n.admin_ajax_url, 
                        data: 'do=' + pollsAdminL10n.text_retrieve_content 
                            + '&content_type=' + contentType 
                            + '&post_types=' + postTypes 
                            + '&tax=' + searchTax 
                            + '&action=polls-admin&_ajax_nonce=' + nonce, 
                        cache: false, 
                        success: function (data) {
                                                    $('#pollq_terms_results').html(data);
                                                    admin_loader(false, 'form');
                                                    termsList.classList.add("wp-polls-slidein");
                                                }
                });
            });
            break;
    }
}

// Scroll calculation helper
function hasScrolledToBottom(scrollContainer, offsetThreshold) {
    let reachedBottom = false;
    let scrollY = scrollContainer.scrollHeight - scrollContainer.scrollTop;
    let height = scrollContainer.offsetHeight;
    let offset = height - scrollY;
    if (offset >= offsetThreshold) reachedBottom = true; //offset starts from a negative interger value and equals 0 or a weak positive integer value when reaching the bottom of the container) 
    return reachedBottom;
}

// Inifinite scroll for object answers' selection list in ADD and EDIT screen 
function wp_polls_enable_infinite_scroll_for_answers_selection() {
    const scrollContainer = document.querySelector('#pollq_posts_items_select_container'); 
    pageNumber = 2; //first page is loaded at initial page display, scroll only has to start with loading page 2
    scrollContainer.addEventListener("scroll", (event) => {
        if ( hasScrolledToBottom(scrollContainer, -80) && !scrollContainer.classList.contains('wp-polls-scroll-loading') && !scrollContainer.classList.contains('wp-polls-scroll-no-more') ) {
            scrollContainer.classList.add('wp-polls-scroll-loading', 'wp-polls-disable');
            retrieve_content('post_types_items', scrollContainer.dataset.nonce, 'scroll_load_more', undefined, undefined, pageNumber);
            wp_polls_delay(function(e){
                pageNumber++;
                scrollContainer.classList.remove('wp-polls-scroll-loading', 'wp-polls-disable');
            }, 800);
        }
    });
}

// Debounce function - adapted (for direct function call) from https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing#1909508 
function wp_polls_delay(fn, ms) {
    clearTimeout(global_poll_debounce_timer);
    global_poll_debounce_timer = setTimeout(fn, ms || 0);
}

// Reoder Answer
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
function add_poll_answer_add(answer_type = 'text') {
    jQuery(document).ready(function($) {
        const selectedItemsContainer = $('#poll_answers');
        switch (answer_type) {
          case 'text':
            selectedItemsContainer.append('<tr id="poll-answer-' + count_poll_answer + '" class="poll-unsaved-answers"><th width="20%" scope="row" valign="top"></th><td width="80%"><input type="text" size="50" maxlength="200" name="polla_answers[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_add(' + count_poll_answer + ');" class="button" /></td></tr>');
            break;
          case 'object':        
            sync_selection_with_object_picker_list('pick', null, false, count_poll_answer);
            break;
        }
        count_poll_answer++;
        reorder_answer_num();
    });
}

// Remove Poll's Answer in Add Poll Page
function remove_poll_answer_add(poll_answer_id, answer_type = 'text') {
    jQuery(document).ready(function($) {
        switch(answer_type) {
          case 'text':
            $('#poll-answer-' + poll_answer_id).remove();
            break;
          case 'object':
            let answerToRemove = $('#poll-answer-' + poll_answer_id);
            let inputID = answerToRemove.find('input').first()[0].id.replace('_selected', '');
            sync_selection_with_object_picker_list('unpick', inputID, false, null);
            answerToRemove.remove();
            break;
        }
        reorder_answer_num();
    });
}

// Add Poll's Answer In Edit Poll Page
function add_poll_answer_edit(answer_type = 'text') {
    jQuery(document).ready(function($) {
        switch(answer_type) {
          case 'text':
            const selectedItemsContainer = $('#poll_answers');
            selectedItemsContainer.append('<tr id="poll-answer-new-' + count_poll_answer_new + '" class="poll-unsaved-answers"><th width="20%" scope="row" valign="top"></th><td width="60%"><input type="text" size="50" maxlength="200" name="polla_answers_new[]" />&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer_edit(' + count_poll_answer_new + ');" class="button" /></td><td width="20%" align="' + pollsAdminL10n.text_direction + '">0 <input type="text" size="4" name="polla_answers_new_votes[]" value="0" onblur="check_totalvotes();" /></td></tr>');
            break;
          case 'object':        
            sync_selection_with_object_picker_list('pick', null, true, count_poll_answer_new);
            break;
        }       
        count_poll_answer_new++;
        reorder_answer_num();
    });
}

// Remove Poll's Answer In Edit Poll Page
function remove_poll_answer_edit(poll_answer_new_id, answer_type = 'text') {
    jQuery(document).ready(function($) {
        switch(answer_type){
            case 'text':
                $('#poll-answer-new-' + poll_answer_new_id).remove();
                break;
            case 'object':
                let answerToRemove = $('#poll-answer-new-' + poll_answer_new_id);
                let inputID = answerToRemove.find('input').first()[0].id.replace('_selected', '');
                sync_selection_with_object_picker_list('unpick', inputID, true, null);
                answerToRemove.remove();
                break;
        }
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

// Multiselect box - based on https://stackoverflow.com/questions/19206919/how-to-create-checkbox-inside-dropdown#answer-69675987
function init_multiselect() {
    post_types_multiselect_dropdown_change('init');
    document.addEventListener("click", function(evt) {
        let flyoutElement = document.getElementById('pollq_post_types_list');
        let targetElement = evt.target; // clicked element
        do {
            //click inside
            if (targetElement == flyoutElement) { 
                return;
            }
            targetElement = targetElement.parentNode; //access DOM
        } while (targetElement);
        //click outside
        toggle_checkbox_area(true);
    });
}

function post_types_multiselect_dropdown_change(context = 'change') {
    const multiselect = document.getElementById("pollq_post_types_list_label");
    let multiselectOption = multiselect.getElementsByTagName('option')[0];
    let values = [];
    const checkboxes = document.getElementById("pollq_post_types_list_options");
    let checkedCheckboxes = checkboxes.querySelectorAll('input[type=checkbox]:checked');

    for (const item of checkedCheckboxes) {
        var checkboxValue = item.getAttribute('value');
        values.push(checkboxValue);
    }
    
    let dropdownValue = pollsAdminL10n.text_none_selected;
    const nonce = checkboxes.dataset.nonce;
    if (values.length == 1) {
        dropdownValue = "1 " + pollsAdminL10n.text_1_selected;
        checkboxes.dataset.values = values;
        wp_polls_delay(function(e){
            retrieve_content('post_types_items', nonce, 'post_types_select');
        }, 1000);
        
    }
    else if (values.length > 1){ 
        dropdownValue = values.length + " " + pollsAdminL10n.text_x_selected;
        checkboxes.dataset.values = values.join(', ');
        wp_polls_delay(function(e) {
            retrieve_content('post_types_items', nonce, 'post_types_select');
        }, 1000);
    }
    else {
        default_ajax_box_result(context);
    }
    multiselectOption.innerText = dropdownValue;
}

function toggle_checkbox_area(onlyHide = false) {
    const checkboxes = document.getElementById("pollq_post_types_list_options");
    let displayValue = checkboxes.style.display;

    if (displayValue != "block") {
        if (onlyHide == false) {
            checkboxes.style.display = "block";
        }
    } else {
        checkboxes.style.display = "none";
    }
}

// Append placeholder text to Ajax containers 
function default_ajax_box_result(action = 'change'){
    const ajaxResultsBoxes = document.querySelectorAll(".wp-polls-ajax-placeholder-container");
    for(var i=0; i<ajaxResultsBoxes.length;i++){
        const msg = pollsAdminL10n.text_ajax_box_no_post_type_selected;
        const pollAnswers = document.querySelectorAll('#poll_answers > tr.poll-saved-answers');
        if (pollAnswers && action !== 'init') ajaxResultsBoxes[i].innerHTML = "";
        if (!ajaxResultsBoxes[i].querySelector(".wp-polls-ajax-placeholder")){
            const placeholder = document.createElement('p');
            const classes = ['wp-polls-ajax-placeholder'];
            placeholder.classList.add(...classes);
            placeholder.innerHTML = msg;
            ajaxResultsBoxes[i].appendChild(placeholder);
        }
    }
}

// Filter function for dropdowns - based on https://www.w3schools.com/howto/howto_js_filter_dropdown.asp
function filter_items(InputID, containerID, liElem) {
    var input, filter, ul, li, a, i;
    input = document.getElementById(InputID);
    filter = input.value.toUpperCase();
    div = document.getElementById(containerID);
    a = div.querySelectorAll(liElem);
    for (i = 0; i < a.length; i++) {
        txtValue = a[i].parentNode.textContent;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            a[i].style.display = "";
            a[i].parentNode.style.display = "";
        } else {
            a[i].style.display = "none";
            a[i].parentNode.style.display = "none";
        }
    }
} 

function term_radio_status_change() {
    const termsResults = document.getElementById("pollq_terms_results");
    let labels = termsResults.querySelectorAll('label');
    
    for (let label of labels) {
        label.classList.remove('wp-polls-selected-radio');
    }

    let checkedradio = termsResults.querySelector('input[type=radio]:checked');
        checkedradio.parentNode.classList.add('wp-polls-selected-radio');
    
    const nonce = termsResults.dataset.nonce;       
    wp_polls_delay(function(e) {
        retrieve_content('post_types_items', nonce, 'term_select');
    }, 700);
}

// Move item from object picker to selection list and vice versa without creating duplicates 
function sync_selection_with_object_picker_list(action, inputID, edit = false, counter = null ) {
    const postItemsSelectResults = document.getElementById("pollq_posts_items_select_results");
    const nonce = document.getElementById("pollq_post_types_list_options").dataset.nonce;
    const selectedItemsContainer = document.getElementById('poll_answers');
    switch(action){
        case 'pick': //select in the picker's results' list
            const inputName = (edit === true) ? 'polla_answers_new' : 'polla_answers';
            const newSuffix = (edit ===  true) ? 'new-' : '';
            const editSuffix = (edit === true) ? '_edit' : '_add';
            const midColWidth = (edit === true) ? '60%' : '80%';
            if (counter != null){
                const checkedBoxes = postItemsSelectResults.querySelectorAll('input[type="checkbox"]:checked');
                for (var i = 0; i < checkedBoxes.length; i++) {
                    const liElem = checkedBoxes[i].parentNode.parentNode;
                    const alreadySelectedCheckbox = document.getElementById(checkedBoxes[i].id +"_selected");
                    if (!alreadySelectedCheckbox) {
                        const newAnswer = document.createElement('tr'); //create answer element
                        newAnswer.id = 'poll-answer-' + newSuffix + counter;
                        newAnswer.classList.add('wp-polls-object-answers', 'poll-unsaved-answers');
                        newAnswer.innerHTML = '<th width="20%" scope="row" valign="top"></th><td width="' + midColWidth + '">&nbsp;&nbsp;&nbsp;<input type="button" value="' + pollsAdminL10n.text_remove_poll_answer + '" onclick="remove_poll_answer' + editSuffix + '(' + counter + ', \'object\')" class="button" /></td></tr>';
                        if (edit) newAnswer.innerHTML += '<td width="20%" align="' + pollsAdminL10n.text_direction + '">0 <input type="text" size="4" name="polla_answers_new_votes[]" value="0" onblur="check_totalvotes();" /></td></tr>';
                        const labelNodeClone = liElem.querySelector('label').cloneNode(true);
                        labelNodeClone.querySelector('input').id = checkedBoxes[i].id + '_selected'; 
                        labelNodeClone.querySelector('input').name = inputName + '[]'; 
                        newAnswer.querySelector('td').prepend(labelNodeClone); 
                        selectedItemsContainer.append(newAnswer); //move answer element to selected answers' list
                        liElem.classList.add('wp-polls-hide'); //hide original element
                        checkedBoxes[i].checked = false; //uncheck original element
                    }
                }
            }       
            break;
        case 'unpick': //remove from the selected items' list
            if (inputID != null){
                const inputElemInResultsList = postItemsSelectResults.querySelector('#' + inputID);
                if (inputElemInResultsList){ //if element to remove from selection actually belongs to the results list  
                    const liElem = inputElemInResultsList.parentNode.parentNode;
                    liElem.classList.remove('wp-polls-hide'); //unhide element from results list
                }
            }
            break;
    }
}

function fields_checkbox_status_change() {
    const fieldsObjToSave = document.getElementById("ans_obj_fields");
    const fieldsContainer = document.getElementById("pollq_fields_list");   
    const fieldsUl = fieldsContainer.querySelectorAll('ul');
    let fieldsObj = {};
    for (var i = 0; i < fieldsUl.length; i++) {
        const postType = fieldsUl[i].className.replace('fields-post-type-box wp-polls-', ''); //extract post type from classes
        const checkedBoxes = fieldsUl[i].querySelectorAll('input[type=checkbox]:checked');
        if (checkedBoxes.length > 0) {
            let arrCheckedBoxes = [];
            for (var j = 0; j < checkedBoxes.length; j++) {
                arrCheckedBoxes.push(checkedBoxes[j].value); //assign field name to obj under a property named after the related post type 
            }
            fieldsObj[postType] = arrCheckedBoxes;
        }
    }
    const isObjEmpty = (obj) => {
      for (const prop in obj) {
        if (Object.hasOwn(obj, prop)) {
          return false;
        }
      }
      return true;
    }
    if (isObjEmpty(fieldsObj)) {
        fieldsObjToSave.value = ''; //if object is empty, leave no brackets
    } else {
        fieldsObjToSave.value = JSON.stringify(fieldsObj);
    }
}
