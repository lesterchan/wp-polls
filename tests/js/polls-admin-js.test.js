/**
 * Tests for the wp-admin script.
 *
 * This file had no coverage of any kind before, which is why the camelCase
 * rename was held back until it existed: every identifier in it is internal to
 * one IIFE, so nothing outside can tell whether a rename missed a reference.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { clickAndSettle, loadScript, sentFields, stubFetch } from './helpers.js';

const L10N = {
	admin_ajax_url: '/wp-admin/admin-ajax.php',
	text_delete_poll: 'Delete Poll',
	text_no_poll_logs: 'No poll logs available.',
	text_delete_all_logs: 'Delete All Logs',
	text_checkbox_delete_all_logs: 'Please check the Yes checkbox.',
	text_delete_poll_logs: 'Delete Logs For This Poll Only',
	text_checkbox_delete_poll_logs: 'Please check the Yes checkbox for this poll.',
	text_delete_poll_ans: 'Delete Poll Answer',
	text_open_poll: 'Open Poll',
	text_close_poll: 'Close Poll',
	text_answer: 'Answer',
	text_remove_poll_answer: 'Remove',
};

beforeAll( () => {
	window.wpPollsAdminL10n = L10N;
	loadScript( 'js/wp-polls-admin.js' );
} );

beforeEach( () => {
	document.body.innerHTML = '';
	window.alert = vi.fn();
	window.confirm = vi.fn( () => true );
} );

/**
 * Markup for the answers table used by Add Poll and Edit Poll.
 *
 * @param {number} rows      How many answer rows.
 * @param {string} inputName Name for the answer inputs.
 * @return {string} HTML.
 */
function answersTable( rows = 2, inputName = 'polla_answers[]' ) {
	const body = Array.from(
		{ length: rows },
		( _, i ) =>
			`<tr id="poll-answer-${ i }"><th>Answer ${ i + 1 }</th>` +
			`<td><input type="text" name="${ inputName }" /></td></tr>`,
	).join( '' );

	return `
		<select id="pollq_multiple"><option value="1">1</option></select>
		<table><tbody id="poll_answers">${ body }</tbody></table>
		<input type="button" value="Add Answer" data-poll-action="add-answer" />
		<input type="button" value="Add Answer Edit" data-poll-action="add-answer-edit" />
		<input type="text" id="pollq_totalvotes" value="0" />
	`;
}

describe( 'deleting a poll', () => {
	it( 'posts the nonce and removes the row once confirmed', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<table><tbody><tr id="poll-7"><td>A poll</td></tr></tbody></table>
			<input type="button" data-poll-action="delete-poll" data-poll-id="7"
			       data-poll-confirm="Delete?" data-poll-nonce="NONCE7" />
		`;
		const spy = stubFetch( 'Poll deleted.' );

		expect( document.getElementById( 'poll-7' ) ).not.toBeNull();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-poll"]' ),
		);

		const fields = sentFields( spy );
		expect( fields.action ).toBe( 'polls-admin' );
		expect( fields.do ).toBe( L10N.text_delete_poll );
		expect( fields.pollq_id ).toBe( '7' );
		expect( fields._ajax_nonce ).toBe( 'NONCE7' );

		expect( document.getElementById( 'poll-7' ) ).toBeNull();
	} );

	it( 'does nothing when the confirm is cancelled', async () => {
		window.confirm = vi.fn( () => false );
		document.body.innerHTML = `
			<table><tbody><tr id="poll-7"><td>A poll</td></tr></tbody></table>
			<input type="button" data-poll-action="delete-poll" data-poll-id="7"
			       data-poll-confirm="Delete?" data-poll-nonce="NONCE7" />
		`;
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-poll"]' ),
		);

		expect( spy ).not.toHaveBeenCalled();
		expect( document.getElementById( 'poll-7' ) ).not.toBeNull();
	} );

	it( 'shows the server response in the message box', async () => {
		document.body.innerHTML = `
			<div id="message" class="hidden"></div>
			<input type="button" data-poll-action="delete-poll" data-poll-id="7"
			       data-poll-confirm="Delete?" data-poll-nonce="NONCE7" />
		`;
		stubFetch( '<p>Poll deleted.</p>' );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-poll"]' ),
		);

		const message = document.getElementById( 'message' );
		expect( message.innerHTML ).toBe( '<p>Poll deleted.</p>' );
		expect( message.classList.contains( 'hidden' ) ).toBe( false );
	} );
} );

describe( 'deleting logs', () => {
	it( 'requires the confirmation checkbox before posting', async () => {
		document.body.innerHTML = `
			<input type="checkbox" id="delete_logs_yes" />
			<div id="poll_logs">some logs</div>
			<input type="button" data-poll-action="delete-all-logs"
			       data-poll-confirm="Delete all?" data-poll-nonce="NONCEALL" />
		`;
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-all-logs"]' ),
		);

		expect( spy ).not.toHaveBeenCalled();
		expect( window.alert ).toHaveBeenCalledWith(
			L10N.text_checkbox_delete_all_logs,
		);
	} );

	it( 'posts and clears the panel once the checkbox is ticked', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<input type="checkbox" id="delete_logs_yes" checked />
			<div id="poll_logs">some logs</div>
			<input type="button" data-poll-action="delete-all-logs"
			       data-poll-confirm="Delete all?" data-poll-nonce="NONCEALL" />
		`;
		const spy = stubFetch( 'Logs deleted.' );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-all-logs"]' ),
		);

		const fields = sentFields( spy );
		expect( fields.do ).toBe( L10N.text_delete_all_logs );
		expect( fields.delete_logs_yes ).toBe( 'yes' );
		expect( fields._ajax_nonce ).toBe( 'NONCEALL' );

		expect( document.getElementById( 'poll_logs' ).textContent ).toBe(
			L10N.text_no_poll_logs,
		);
	} );
} );

describe( 'answer rows', () => {
	it( 'adds a row on Add Poll and renumbers the headings', async () => {
		document.body.innerHTML = answersTable( 2 );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="add-answer"]' ),
		);

		const rows = document.querySelectorAll( '#poll_answers tr' );
		expect( rows ).toHaveLength( 3 );

		const headings = Array.from(
			document.querySelectorAll( '#poll_answers tr > th' ),
			( th ) => th.textContent,
		);
		expect( headings ).toEqual( [ 'Answer 1', 'Answer 2', 'Answer 3' ] );
	} );

	it( 'gives the new row a working Remove button', async () => {
		document.body.innerHTML = answersTable( 2 );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="add-answer"]' ),
		);
		expect( document.querySelectorAll( '#poll_answers tr' ) ).toHaveLength( 3 );

		const added = document.querySelector( '#poll_answers tr:last-child' );
		await clickAndSettle( added.querySelector( 'button' ) );

		expect( document.querySelectorAll( '#poll_answers tr' ) ).toHaveLength( 2 );
	} );

	it( 'adds a votes field on Edit Poll but not on Add Poll', async () => {
		document.body.innerHTML = answersTable( 1 );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="add-answer"]' ),
		);
		const addRow = document.querySelector( '#poll_answers tr:last-child' );
		expect( addRow.querySelectorAll( 'td' ) ).toHaveLength( 1 );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="add-answer-edit"]' ),
		);
		const editRow = document.querySelector( '#poll_answers tr:last-child' );
		expect( editRow.querySelectorAll( 'td' ) ).toHaveLength( 2 );
		expect(
			editRow.querySelector( 'input[name="polla_answers_new_votes[]"]' ),
		).not.toBeNull();
	} );

	it( 'keeps the multiple answer select in step with the row count', async () => {
		document.body.innerHTML = answersTable( 2 );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="add-answer"]' ),
		);

		const select = document.getElementById( 'pollq_multiple' );
		expect( select.options ).toHaveLength( 3 );
	} );
} );

describe( 'total votes', () => {
	it( 'sums the vote fields on focusout', () => {
		document.body.innerHTML = `
			<table><tbody id="poll_answers">
				<tr><th>Answer 1</th><td><input type="text" class="wp-polls-votes" value="3" data-poll-action="total-votes" /></td></tr>
				<tr><th>Answer 2</th><td><input type="text" class="wp-polls-votes" value="4" data-poll-action="total-votes" /></td></tr>
			</tbody></table>
			<input type="text" id="pollq_totalvotes" value="0" />
		`;

		document
			.querySelector( '[data-poll-action="total-votes"]' )
			.dispatchEvent( new window.FocusEvent( 'focusout', { bubbles: true } ) );

		expect( document.getElementById( 'pollq_totalvotes' ).value ).toBe( '7' );
	} );

	it( 'ignores fields that are not numbers', () => {
		document.body.innerHTML = `
			<table><tbody id="poll_answers">
				<tr><th>Answer 1</th><td><input type="text" class="wp-polls-votes" value="5" data-poll-action="total-votes" /></td></tr>
				<tr><th>Answer 2</th><td><input type="text" class="wp-polls-votes" value="" data-poll-action="total-votes" /></td></tr>
			</tbody></table>
			<input type="text" id="pollq_totalvotes" value="0" />
		`;

		document
			.querySelector( '[data-poll-action="total-votes"]' )
			.dispatchEvent( new window.FocusEvent( 'focusout', { bubbles: true } ) );

		expect( document.getElementById( 'pollq_totalvotes' ).value ).toBe( '5' );
	} );
} );

describe( 'opening and closing a poll', () => {
	it( 'swaps the buttons after closing', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<input type="button" id="open_poll" class="hidden" />
			<input type="button" id="close_poll" data-poll-action="close-poll" data-poll-id="3"
			       data-poll-confirm="Close?" data-poll-nonce="NONCE3" />
		`;
		const spy = stubFetch( 'Poll closed.' );

		await clickAndSettle( document.getElementById( 'close_poll' ) );

		expect( sentFields( spy ).do ).toBe( L10N.text_close_poll );
		expect(
			document.getElementById( 'open_poll' ).classList.contains( 'hidden' ),
		).toBe( false );
		expect(
			document.getElementById( 'close_poll' ).classList.contains( 'hidden' ),
		).toBe( true );
	} );
} );

describe( 'toggles', () => {
	it( 'enables the maximum answers select only when multiple is Yes', () => {
		document.body.innerHTML = `
			<select id="pollq_multiple_yes" data-poll-action="toggle-multiple">
				<option value="0" selected>No</option>
				<option value="1">Yes</option>
			</select>
			<select id="pollq_multiple" disabled><option value="1">1</option></select>
		`;
		const yes = document.getElementById( 'pollq_multiple_yes' );
		const max = document.getElementById( 'pollq_multiple' );

		yes.value = '1';
		yes.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		expect( max.disabled ).toBe( false );

		yes.value = '0';
		yes.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		expect( max.disabled ).toBe( true );
	} );

	it( 'shows the expiry fields only when expiry is not "no"', () => {
		document.body.innerHTML = `
			<input type="checkbox" id="pollq_expiry_no" data-poll-action="toggle-expiry" checked />
			<div id="pollq_expiry"></div>
		`;
		const toggle = document.getElementById( 'pollq_expiry_no' );
		const expiry = document.getElementById( 'pollq_expiry' );

		// Clicking flips the box, so start unchecked to have the handler see it ticked.
		toggle.checked = false;
		toggle.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( toggle.checked ).toBe( true );
		expect( expiry.classList.contains( 'hidden' ) ).toBe( true );

		toggle.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( toggle.checked ).toBe( false );
		expect( expiry.classList.contains( 'hidden' ) ).toBe( false );
	} );
} );

describe( 'deleting an answer', () => {
	it( 'posts the answer id and subtracts its votes from the total', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<span id="poll_total_votes">10</span>
			<input type="text" id="pollq_totalvotes" value="10" />
			<select id="pollq_multiple"><option value="1">1</option></select>
			<table><tbody id="poll_answers">
				<tr id="poll-answer-4"><th>Answer 1</th><td><input type="text" class="wp-polls-votes" value="3" /></td></tr>
				<tr id="poll-answer-5"><th>Answer 2</th><td><input type="text" class="wp-polls-votes" value="7" /></td></tr>
			</tbody></table>
			<input type="button" data-poll-action="delete-answer" data-poll-id="2"
			       data-poll-aid="4" data-poll-votes="3"
			       data-poll-confirm="Delete answer?" data-poll-nonce="NONCEANS" />
		`;
		const spy = stubFetch( 'Answer deleted.' );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-answer"]' ),
		);

		const fields = sentFields( spy );
		expect( fields.do ).toBe( L10N.text_delete_poll_ans );
		expect( fields.pollq_id ).toBe( '2' );
		expect( fields.polla_aid ).toBe( '4' );
		expect( fields._ajax_nonce ).toBe( 'NONCEANS' );

		expect( document.getElementById( 'poll-answer-4' ) ).toBeNull();
		expect( document.getElementById( 'poll_total_votes' ).textContent ).toBe( '7' );
	} );

	it( 'does nothing when the confirm is cancelled', async () => {
		window.confirm = vi.fn( () => false );
		document.body.innerHTML = `
			<table><tbody id="poll_answers">
				<tr id="poll-answer-4"><th>Answer 1</th><td></td></tr>
			</tbody></table>
			<input type="button" data-poll-action="delete-answer" data-poll-id="2"
			       data-poll-aid="4" data-poll-votes="3"
			       data-poll-confirm="Delete answer?" data-poll-nonce="NONCEANS" />
		`;
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-answer"]' ),
		);

		expect( spy ).not.toHaveBeenCalled();
		expect( document.getElementById( 'poll-answer-4' ) ).not.toBeNull();
	} );
} );

describe( "deleting one poll's logs", () => {
	it( 'requires the confirmation checkbox', async () => {
		document.body.innerHTML = `
			<input type="checkbox" id="delete_logs_yes" />
			<input type="button" data-poll-action="delete-poll-logs" data-poll-id="3"
			       data-poll-confirm="Delete?" data-poll-nonce="NONCEONE" />
		`;
		const spy = stubFetch();

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-poll-logs"]' ),
		);

		expect( spy ).not.toHaveBeenCalled();
		expect( window.alert ).toHaveBeenCalledWith(
			L10N.text_checkbox_delete_poll_logs,
		);
	} );

	it( 'swaps the logs panel for the empty message once ticked', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<input type="checkbox" id="delete_logs_yes" checked />
			<div id="poll_logs">logs</div>
			<div id="poll_logs_display">rows</div>
			<div id="poll_logs_display_none" style="display: none;">none</div>
			<input type="button" data-poll-action="delete-poll-logs" data-poll-id="3"
			       data-poll-confirm="Delete?" data-poll-nonce="NONCEONE" />
		`;
		const spy = stubFetch( 'Logs deleted.' );

		await clickAndSettle(
			document.querySelector( '[data-poll-action="delete-poll-logs"]' ),
		);

		const fields = sentFields( spy );
		expect( fields.do ).toBe( L10N.text_delete_poll_logs );
		expect( fields.pollq_id ).toBe( '3' );

		expect(
			document
				.getElementById( 'poll_logs_display' )
				.classList.contains( 'hidden' ),
		).toBe( true );
		expect(
			document
				.getElementById( 'poll_logs_display_none' )
				.classList.contains( 'hidden' ),
		).toBe( false );
	} );
} );

describe( 'opening a poll', () => {
	it( 'swaps the buttons the other way round', async () => {
		document.body.innerHTML = `
			<div id="message"></div>
			<input type="button" id="open_poll" data-poll-action="open-poll" data-poll-id="3"
			       data-poll-confirm="Open?" data-poll-nonce="NONCEOPEN" />
			<input type="button" id="close_poll" class="hidden" />
		`;
		const spy = stubFetch( 'Poll opened.' );

		await clickAndSettle( document.getElementById( 'open_poll' ) );

		expect( sentFields( spy ).do ).toBe( L10N.text_open_poll );
		expect(
			document.getElementById( 'open_poll' ).classList.contains( 'hidden' ),
		).toBe( true );
		expect(
			document.getElementById( 'close_poll' ).classList.contains( 'hidden' ),
		).toBe( false );
	} );
} );

describe( 'the statically rendered Remove button', () => {
	it( 'removes the row named by data-poll-answer', async () => {
		document.body.innerHTML = `
			<select id="pollq_multiple"><option value="1">1</option></select>
			<table><tbody id="poll_answers">
				<tr id="poll-answer-0"><th>Answer 1</th><td>
					<input type="button" data-poll-action="remove-answer" data-poll-answer="0" />
				</td></tr>
				<tr id="poll-answer-1"><th>Answer 2</th><td>
					<input type="button" data-poll-action="remove-answer" data-poll-answer="1" />
				</td></tr>
			</tbody></table>
		`;

		await clickAndSettle(
			document.querySelector( '[data-poll-answer="0"]' ),
		);

		expect( document.getElementById( 'poll-answer-0' ) ).toBeNull();
		expect( document.getElementById( 'poll-answer-1' ) ).not.toBeNull();
		expect(
			document.querySelector( '#poll_answers tr > th' ).textContent,
		).toBe( 'Answer 1' );
	} );
} );

describe( 'the timestamp toggle', () => {
	it( 'shows the timestamp fields only while the box is ticked', () => {
		document.body.innerHTML = `
			<input type="checkbox" id="edit_polltimestamp" data-poll-action="toggle-timestamp" />
			<div id="pollq_timestamp" class="hidden"></div>
		`;
		const toggle = document.getElementById( 'edit_polltimestamp' );
		const fields = document.getElementById( 'pollq_timestamp' );

		toggle.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( toggle.checked ).toBe( true );
		expect( fields.classList.contains( 'hidden' ) ).toBe( false );

		toggle.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( toggle.checked ).toBe( false );
		expect( fields.classList.contains( 'hidden' ) ).toBe( true );
	} );
} );

