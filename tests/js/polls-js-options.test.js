/**
 * The two front end display options, with both switched off.
 *
 * They are read once as the IIFE runs, so they cannot be changed inside a test
 * file that already loaded the script with them on — hence a file of their own.
 * Poll Options exposes both, and neither had any coverage.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { clickAndSettle, loadScript, stubFetch, voteForm } from './helpers.js';

beforeAll( () => {
	window.pollsL10n = {
		ajax_url: '/wp-admin/admin-ajax.php',
		show_loading: '0',
		show_fading: '0',
		text_valid: 'Please choose a valid poll answer.',
		text_multiple: 'Maximum number of choices allowed:',
	};
	loadScript( 'polls-js.js' );
} );

beforeEach( () => {
	document.body.innerHTML = '';
	window.alert = vi.fn();
} );

describe( 'with both display options off', () => {
	it( 'never touches the loading indicator, leaving the stylesheet to hide it', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const loading = document.getElementById( 'polls-1-loading' );

		let resolveBody;
		const pending = new Promise( ( resolve ) => {
			resolveBody = resolve;
		} );
		global.fetch = vi.fn( () => Promise.resolve( { text: () => pending } ) );
		window.fetch = global.fetch;

		document.getElementById( 'poll-answer-1' ).checked = true;
		document
			.querySelector( '[data-poll-action="vote"]' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		// show_loading: '1' would have set this to block mid-flight.
		expect( loading.style.display ).toBe( '' );

		resolveBody( '<div id="polls-1">done</div>' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		// And left alone afterwards, rather than pinned to none inline.
		expect( loading.style.display ).toBe( '' );
	} );

	it( 'does not fade the poll out before replacing it', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const container = document.getElementById( 'polls-1' );

		let resolveBody;
		const pending = new Promise( ( resolve ) => {
			resolveBody = resolve;
		} );
		global.fetch = vi.fn( () => Promise.resolve( { text: () => pending } ) );
		window.fetch = global.fetch;

		document.getElementById( 'poll-answer-1' ).checked = true;
		document
			.querySelector( '[data-poll-action="vote"]' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( container.style.opacity ).toBe( '' );

		resolveBody( '<div id="polls-1">done</div>' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	} );

	it( 'still votes and still swaps in the response', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch( '<div id="polls-1">RESULTS</div>' );

		document.getElementById( 'poll-answer-2' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( spy ).toHaveBeenCalledTimes( 1 );
		expect( document.getElementById( 'polls-1' ).textContent ).toContain(
			'RESULTS',
		);
	} );

	it( 'leaves the replaced poll fully visible', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		stubFetch( '<div id="polls-1">RESULTS</div>' );

		document.getElementById( 'poll-answer-1' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		const container = document.getElementById( 'polls-1' );
		expect( container.style.opacity ).toBe( '' );
		expect( container.style.transition ).toBe( '' );
	} );
} );
