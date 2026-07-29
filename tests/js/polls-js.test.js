/**
 * Tests for the front end script.
 *
 * The contract these lock down is the one the PHP side depends on: the answer
 * ids arrive as a comma separated string in a single poll_<id> field. If the
 * script ever sent an array instead, Polls_Vote would sanitize it to an empty
 * string and silently record no vote.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	clickAndSettle,
	loadScript,
	sentFields,
	stubFetch,
	voteForm,
} from './helpers.js';

beforeAll( () => {
	window.pollsL10n = {
		ajax_url: '/wp-admin/admin-ajax.php',
		show_loading: '1',
		show_fading: '1',
		text_valid: 'Please choose a valid poll answer.',
		text_multiple: 'Maximum number of choices allowed:',
	};
	loadScript( 'js/wp-polls.js' );
} );

beforeEach( () => {
	document.body.innerHTML = '';
	window.alert = vi.fn();
} );

describe( 'voting', () => {
	it( 'posts the selected answer for a single answer poll', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch();

		document.getElementById( 'poll-answer-2' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( spy ).toHaveBeenCalledTimes( 1 );
		expect( spy.mock.calls[ 0 ][ 0 ] ).toBe( '/wp-admin/admin-ajax.php' );

		const fields = sentFields( spy );
		expect( fields.action ).toBe( 'polls' );
		expect( fields.view ).toBe( 'process' );
		expect( fields.poll_id ).toBe( '1' );
		expect( fields.poll_1 ).toBe( '2' );
	} );

	it( 'sends the nonce from the form', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch();

		document.getElementById( 'poll-answer-1' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( sentFields( spy ).poll_1_nonce ).toBe( 'NONCE1' );
	} );

	it( 'joins multiple answers with commas rather than repeating the field', async () => {
		document.body.innerHTML = voteForm( {
			id: 2,
			type: 'checkbox',
			ids: [ 11, 12, 13 ],
			max: 2,
		} );
		const spy = stubFetch();

		document.getElementById( 'poll-answer-11' ).checked = true;
		document.getElementById( 'poll-answer-13' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		const [ , init ] = spy.mock.calls[ 0 ];
		const params = new URLSearchParams( init.body.toString() );

		expect( params.getAll( 'poll_2' ) ).toEqual( [ '11,13' ] );
	} );

	it( 'refuses to submit with nothing selected', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch();

		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( spy ).not.toHaveBeenCalled();
		expect( window.alert ).toHaveBeenCalledWith(
			window.pollsL10n.text_valid,
		);
	} );

	it( 'refuses to submit more answers than the poll allows', async () => {
		document.body.innerHTML = voteForm( {
			id: 2,
			type: 'checkbox',
			ids: [ 11, 12, 13 ],
			max: 2,
		} );
		const spy = stubFetch();

		[ 11, 12, 13 ].forEach( ( aid ) => {
			document.getElementById( 'poll-answer-' + aid ).checked = true;
		} );
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( spy ).not.toHaveBeenCalled();
		expect( window.alert ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'replaces the poll markup with the response', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		stubFetch( '<div id="polls-1" class="wp-polls">RESULTS HERE</div>' );

		document.getElementById( 'poll-answer-1' ).checked = true;
		await clickAndSettle( document.querySelector( '[data-poll-action="vote"]' ) );

		expect( document.getElementById( 'polls-1' ).textContent ).toContain(
			'RESULTS HERE',
		);
		expect( document.getElementById( 'polls_form_1' ) ).toBeNull();
	} );
} );

describe( 'result and booth links', () => {
	it( 'asks for the result view', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="result"]' ),
		);

		expect( sentFields( spy ).view ).toBe( 'result' );
	} );

	it( 'stops the anchor following its placeholder href', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		stubFetch();

		const link = document.querySelector( '[data-poll-action="result"]' );
		const event = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		link.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'asks for the booth view', async () => {
		document.body.innerHTML = `<a href="#VotePoll" data-poll-id="4" data-poll-action="booth">Vote</a>`;
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="booth"]' ),
		);

		const fields = sentFields( spy );
		expect( fields.view ).toBe( 'booth' );
		expect( fields.poll_id ).toBe( '4' );
	} );
} );

describe( 'loading indicator', () => {
	it( 'is shown while the request is in flight and hidden after', async () => {
		document.body.innerHTML = voteForm( { id: 1 } );
		const loading = document.getElementById( 'polls-1-loading' );

		let resolveBody;
		const pending = new Promise( ( resolve ) => {
			resolveBody = resolve;
		} );
		global.fetch = vi.fn( () =>
			Promise.resolve( { text: () => pending } ),
		);
		window.fetch = global.fetch;

		document.getElementById( 'poll-answer-1' ).checked = true;
		document
			.querySelector( '[data-poll-action="vote"]' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( loading.style.display ).toBe( 'block' );

		resolveBody( '<div id="polls-1">done</div>' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( loading.style.display ).toBe( 'none' );
	} );
} );

describe( 'unrelated clicks', () => {
	it( 'ignores elements with no data-poll-action', async () => {
		document.body.innerHTML =
			voteForm( { id: 1 } ) + '<button id="other">Other</button>';
		const spy = stubFetch();

		await clickAndSettle( document.getElementById( 'other' ) );

		expect( spy ).not.toHaveBeenCalled();
	} );
} );
