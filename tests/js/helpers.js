/**
 * Shared setup for the script tests.
 *
 * Both scripts are IIFEs with no exports: they read their localised strings off
 * `window` as they execute, then attach delegated listeners to `document`. So
 * the l10n object has to exist before the script is evaluated, and the script
 * can only be evaluated once per test file or every listener fires twice.
 */
import { readFileSync } from 'node:fs';
import { vi } from 'vitest';

/**
 * Evaluate a plugin script in the current jsdom page.
 *
 * @param {string} name File name relative to the plugin root.
 */
export function loadScript( name ) {
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
}

/**
 * Replace fetch with a spy resolving to the given body.
 *
 * @param {string} body Response text.
 * @return {Function} The spy.
 */
export function stubFetch( body = '' ) {
	const spy = vi.fn( () =>
		Promise.resolve( { text: () => Promise.resolve( body ) } ),
	);
	global.fetch = spy;
	window.fetch = spy;
	return spy;
}

/**
 * The body of the single request fetch was called with, as a plain object.
 *
 * @param {Function} spy  fetch spy.
 * @param {number}   call Which call to read.
 * @return {Object} Field name to value.
 */
export function sentFields( spy, call = 0 ) {
	const [ , init ] = spy.mock.calls[ call ];
	return Object.fromEntries( new URLSearchParams( init.body.toString() ) );
}

/**
 * Click an element and let the fetch promise chain settle.
 *
 * @param {Element} el Element to click.
 */
export async function clickAndSettle( el ) {
	el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

/**
 * Markup for a poll voting form.
 *
 * @param {Object} options      Options.
 * @param {number} options.id   Poll id.
 * @param {string} options.type "radio" or "checkbox".
 * @param {Array}  options.ids  Answer ids.
 * @param {number} options.max  Max selectable answers, 0 for single answer.
 * @return {string} HTML.
 */
export function voteForm( { id = 1, type = 'radio', ids = [ 1, 2, 3 ], max = 0 } = {} ) {
	const multiple =
		max > 0
			? `<input type="hidden" id="poll_multiple_ans_${ id }" name="poll_multiple_ans_${ id }" value="${ max }" />`
			: '';

	const answers = ids
		.map(
			( aid ) =>
				`<li><input type="${ type }" id="poll-answer-${ aid }" name="poll_${ id }" value="${ aid }" /></li>`,
		)
		.join( '' );

	return `
		<div id="polls-${ id }" class="wp-polls">
			<form id="polls_form_${ id }" class="wp-polls-form">
				<input type="hidden" id="poll_${ id }_nonce" name="wp-polls-nonce" value="NONCE${ id }" />
				${ multiple }
				<ul>${ answers }</ul>
				<input type="button" value="Vote" data-poll-id="${ id }" data-poll-action="vote" />
				<a href="#ViewPollResults" data-poll-id="${ id }" data-poll-action="result">View Results</a>
			</form>
		</div>
		<div id="polls-${ id }-loading" class="wp-polls wp-polls-loading"><span class="wp-polls-spinner" aria-hidden="true"></span> Loading ...</div>
	`;
}
