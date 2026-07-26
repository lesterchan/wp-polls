/**
 * WP-Polls front end.
 *
 * Nothing here is exported. Poll markup asks for behaviour through
 * data-poll-action / data-poll-id attributes, which are picked up by the
 * delegated listener at the bottom of this file.
 */
( function() {
	'use strict';

	const l10n = window.pollsL10n || {};
	const show_loading = parseInt( l10n.show_loading, 10 ) > 0;
	const show_fading = parseInt( l10n.show_fading, 10 ) > 0;

	// Matches the fade duration used before.
	const FADE_DURATION = 400;

	// The Poll Container For A Given Poll
	function poll_container( current_poll_id ) {
		return document.getElementById( 'polls-' + current_poll_id );
	}

	// Show Or Hide The Loading Indicator
	// The indicator is hidden by a stylesheet rule, so it needs an explicit
	// display value rather than just clearing the inline one.
	function set_loading( current_poll_id, visible ) {
		const loading = document.getElementById(
			'polls-' + current_poll_id + '-loading',
		);
		if ( loading ) {
			loading.style.display = visible ? 'block' : 'none';
		}
	}

	// Fade An Element To The Given Opacity
	function fade_to( element, opacity ) {
		if ( element ) {
			element.style.transition = 'opacity ' + FADE_DURATION + 'ms';
			element.style.opacity = opacity;
		}
	}

	// Make Sure A Poll Is Fully Visible
	// Only ever clears opacity, never animates towards it. Fading a poll *in*
	// would have to start it at zero opacity, and browsers freeze animations in
	// background tabs, which would leave the poll invisible until it is looked at.
	function show( element ) {
		if ( element ) {
			element.style.transition = '';
			element.style.opacity = '';
		}
	}

	// When User Vote For Poll
	function poll_vote( current_poll_id ) {
		const form = document.getElementById( 'polls_form_' + current_poll_id );
		if ( ! form ) {
			return;
		}

		const multiple_field = document.getElementById(
			'poll_multiple_ans_' + current_poll_id,
		);
		const poll_multiple_ans = multiple_field
			? parseInt( multiple_field.value, 10 )
			: 0;

		const selected = [];
		const choices = form.querySelectorAll(
			'input[type="checkbox"], input[type="radio"], option',
		);
		Array.prototype.forEach.call( choices, function( choice ) {
			// Inputs expose 'checked', options expose 'selected'.
			if ( choice.checked || choice.selected ) {
				selected.push( choice.value );
			}
		} );

		if ( poll_multiple_ans > 0 ) {
			if ( selected.length === 0 ) {
				alert( l10n.text_valid );
			} else if ( selected.length > poll_multiple_ans ) {
				alert( l10n.text_multiple + ' ' + poll_multiple_ans );
			} else {
				poll_process( current_poll_id, selected.join( ',' ) );
			}
			return;
		}

		const poll_answer_id = selected.length
			? parseInt( selected[ selected.length - 1 ], 10 )
			: 0;
		if ( poll_answer_id > 0 ) {
			poll_process( current_poll_id, poll_answer_id );
		} else {
			alert( l10n.text_valid );
		}
	}

	// Send A Poll Request And Swap In The Markup That Comes Back
	function poll_request( current_poll_id, view, extra_fields ) {
		const nonce_field = document.getElementById(
			'poll_' + current_poll_id + '_nonce',
		);

		const body = new URLSearchParams();
		body.append( 'action', 'polls' );
		body.append( 'view', view );
		body.append( 'poll_id', current_poll_id );
		body.append(
			'poll_' + current_poll_id + '_nonce',
			nonce_field ? nonce_field.value : '',
		);
		if ( extra_fields ) {
			Object.keys( extra_fields ).forEach( function( name ) {
				body.append( name, extra_fields[ name ] );
			} );
		}

		if ( show_fading ) {
			fade_to( poll_container( current_poll_id ), 0 );
		}
		if ( show_loading ) {
			set_loading( current_poll_id, true );
		}

		fetch( l10n.ajax_url, {
			method: 'POST',
			credentials: 'include',
			cache: 'no-cache',
			body,
		} )
			.then( function( response ) {
				return response.text();
			} )
			.then( function( data ) {
				poll_process_success( current_poll_id, data );
			} )
			.catch( function() {
				// Leave the poll readable rather than stuck faded out behind a spinner.
				set_loading( current_poll_id, false );
				show( poll_container( current_poll_id ) );
			} );
	}

	// Process Poll (User Click "Vote" Button)
	function poll_process( current_poll_id, poll_answer_id ) {
		const fields = {};
		fields[ 'poll_' + current_poll_id ] = poll_answer_id;
		poll_request( current_poll_id, 'process', fields );
	}

	// Poll's Result (User Click "View Results" Link)
	function poll_result( current_poll_id ) {
		poll_request( current_poll_id, 'result' );
	}

	// Poll's Voting Booth (User Click "Vote" Link)
	function poll_booth( current_poll_id ) {
		poll_request( current_poll_id, 'booth' );
	}

	// Poll Process Successfully
	function poll_process_success( current_poll_id, data ) {
		const container = poll_container( current_poll_id );
		if ( container ) {
			const parsed = document.createElement( 'div' );
			parsed.innerHTML = data;
			container.replaceWith.apply(
				container,
				Array.prototype.slice.call( parsed.childNodes ),
			);
		}

		if ( show_loading ) {
			set_loading( current_poll_id, false );
		}

		if ( show_fading ) {
			// The old poll was faded out and has now been thrown away, so all that
			// is left to do is make sure the markup that replaced it is visible.
			show( poll_container( current_poll_id ) );
		}
	}

	// On Click Events
	document.addEventListener( 'click', function( event ) {
		const target = event.target;
		if ( ! target || typeof target.closest !== 'function' ) {
			return;
		}

		const trigger = target.closest( '[data-poll-action]' );
		if ( ! trigger ) {
			return;
		}

		const poll_id = trigger.getAttribute( 'data-poll-id' );

		// The result and booth templates use anchors, so stop the browser
		// from following the placeholder href while the request runs.
		if ( trigger.tagName === 'A' ) {
			event.preventDefault();
		}

		switch ( trigger.getAttribute( 'data-poll-action' ) ) {
			case 'vote':
				poll_vote( poll_id );
				break;
			case 'result':
				poll_result( poll_id );
				break;
			case 'booth':
				poll_booth( poll_id );
				break;
		}
	} );
}() );
