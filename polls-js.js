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
	const showLoading = parseInt( l10n.show_loading, 10 ) > 0;
	const showFading = parseInt( l10n.show_fading, 10 ) > 0;

	// Matches the fade duration used before.
	const FADE_DURATION = 400;

	// The Poll Container For A Given Poll
	function pollContainer( currentPollId ) {
		return document.getElementById( 'polls-' + currentPollId );
	}

	// Show Or Hide The Loading Indicator
	// The indicator is hidden by a stylesheet rule, so it needs an explicit
	// display value rather than just clearing the inline one.
	function setLoading( currentPollId, visible ) {
		const loading = document.getElementById(
			'polls-' + currentPollId + '-loading',
		);
		if ( loading ) {
			loading.style.display = visible ? 'block' : 'none';
		}
	}

	// Fade An Element To The Given Opacity
	function fadeTo( element, opacity ) {
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
	function pollVote( currentPollId ) {
		const form = document.getElementById( 'polls_form_' + currentPollId );
		if ( ! form ) {
			return;
		}

		const multipleField = document.getElementById(
			'poll_multiple_ans_' + currentPollId,
		);
		const pollMultipleAns = multipleField
			? parseInt( multipleField.value, 10 )
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

		if ( pollMultipleAns > 0 ) {
			if ( selected.length === 0 ) {
				alert( l10n.text_valid );
			} else if ( selected.length > pollMultipleAns ) {
				alert( l10n.text_multiple + ' ' + pollMultipleAns );
			} else {
				pollProcess( currentPollId, selected.join( ',' ) );
			}
			return;
		}

		const pollAnswerId = selected.length
			? parseInt( selected[ selected.length - 1 ], 10 )
			: 0;
		if ( pollAnswerId > 0 ) {
			pollProcess( currentPollId, pollAnswerId );
		} else {
			alert( l10n.text_valid );
		}
	}

	// Send A Poll Request And Swap In The Markup That Comes Back
	function pollRequest( currentPollId, view, extraFields ) {
		const nonceField = document.getElementById(
			'poll_' + currentPollId + '_nonce',
		);

		const body = new URLSearchParams();
		body.append( 'action', 'polls' );
		body.append( 'view', view );
		body.append( 'poll_id', currentPollId );
		body.append(
			'poll_' + currentPollId + '_nonce',
			nonceField ? nonceField.value : '',
		);
		if ( extraFields ) {
			Object.keys( extraFields ).forEach( function( name ) {
				body.append( name, extraFields[ name ] );
			} );
		}

		if ( showFading ) {
			fadeTo( pollContainer( currentPollId ), 0 );
		}
		if ( showLoading ) {
			setLoading( currentPollId, true );
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
				pollProcessSuccess( currentPollId, data );
			} )
			.catch( function() {
				// Leave the poll readable rather than stuck faded out behind a spinner.
				setLoading( currentPollId, false );
				show( pollContainer( currentPollId ) );
			} );
	}

	// Process Poll (User Click "Vote" Button)
	function pollProcess( currentPollId, pollAnswerId ) {
		const fields = {};
		fields[ 'poll_' + currentPollId ] = pollAnswerId;
		pollRequest( currentPollId, 'process', fields );
	}

	// Poll's Result (User Click "View Results" Link)
	function pollResult( currentPollId ) {
		pollRequest( currentPollId, 'result' );
	}

	// Poll's Voting Booth (User Click "Vote" Link)
	function pollBooth( currentPollId ) {
		pollRequest( currentPollId, 'booth' );
	}

	// Poll Process Successfully
	function pollProcessSuccess( currentPollId, data ) {
		const container = pollContainer( currentPollId );
		if ( container ) {
			const parsed = document.createElement( 'div' );
			parsed.innerHTML = data;
			container.replaceWith.apply(
				container,
				Array.prototype.slice.call( parsed.childNodes ),
			);
		}

		if ( showLoading ) {
			setLoading( currentPollId, false );
		}

		if ( showFading ) {
			// The old poll was faded out and has now been thrown away, so all that
			// is left to do is make sure the markup that replaced it is visible.
			show( pollContainer( currentPollId ) );
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

		const pollId = trigger.getAttribute( 'data-poll-id' );

		// The result and booth templates use anchors, so stop the browser
		// from following the placeholder href while the request runs.
		if ( trigger.tagName === 'A' ) {
			event.preventDefault();
		}

		switch ( trigger.getAttribute( 'data-poll-action' ) ) {
			case 'vote':
				pollVote( pollId );
				break;
			case 'result':
				pollResult( pollId );
				break;
			case 'booth':
				pollBooth( pollId );
				break;
		}
	} );
}() );
