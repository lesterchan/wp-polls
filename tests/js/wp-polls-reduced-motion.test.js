/**
 * The fade, when the visitor has asked for less movement.
 *
 * This file replaces one that covered the two "Polls AJAX Style" options, which
 * are gone. They let a site owner switch off the loading indicator and the fade;
 * the indicator is feedback rather than decoration and now always shows, and
 * whether to animate is the visitor's answer rather than the site owner's.
 *
 * It is a file of its own because the script reads matchMedia through a helper
 * called on every fade, but the stub has to be installed before the IIFE runs
 * for the listener to be attached against it.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { clickAndSettle, loadScript, stubFetch, voteForm } from './helpers.js';

beforeAll( () => {
	window.wpPollsL10n = {
		ajax_url: '/wp-admin/admin-ajax.php',
		text_valid: 'Please choose a valid poll answer.',
		text_multiple: 'Maximum number of choices allowed:',
	};

	// jsdom has no matchMedia at all, so this is both the stub and the reason
	// the script guards on typeof before calling it.
	window.matchMedia = vi.fn( ( query ) => ( {
		matches: query.includes( 'prefers-reduced-motion' ),
		media: query,
		addEventListener() {},
		removeEventListener() {},
	} ) );

	loadScript( 'js/wp-polls.js' );
} );

beforeEach( () => {
	document.body.innerHTML = '';
	window.alert = vi.fn();
} );

describe( 'with prefers-reduced-motion: reduce', () => {
	it( 'dims the poll without animating towards it', async () => {
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

		// The poll still dims -- the request is in flight and the old numbers are
		// about to be replaced -- it just does not travel there.
		expect( container.style.opacity ).toBe( '0' );
		expect( container.style.transition ).toBe( '' );

		resolveBody( '<div id="polls-1">done</div>' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	} );

	it( 'still shows the loading indicator, which is not decoration', async () => {
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

		// Reduced motion is a request for less movement, not for less
		// information. The stylesheet stops the spinner spinning; the indicator
		// itself still appears.
		expect( loading.style.display ).toBe( 'block' );

		resolveBody( '<div id="polls-1">done</div>' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( loading.style.display ).toBe( 'none' );
	} );

	it( 'still votes and swaps in the response', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch( '<div id="polls-1">RESULTS</div>' );

		document.getElementById( 'poll-answer-2' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( spy ).toHaveBeenCalledTimes( 1 );
		expect( document.getElementById( 'polls-1' ).textContent ).toContain( 'RESULTS' );
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
