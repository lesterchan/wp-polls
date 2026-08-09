/**
 * Managing polls from wp-admin.
 *
 * Adding, editing and deleting a poll all happen through screens a person
 * fills in, so these tests fill them in the same way and then check the front
 * end -- the place the work was for. The delete and log-clearing controls put
 * up a native confirm(), which the tests answer both ways: a dismissed confirm
 * that still deleted would be worse than no confirm at all.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ADD_URL,
	MANAGE_URL,
	createPoll,
	createPollPost,
	deleteAllPolls,
	poll,
	pollColumn,
	uniqueQuestion,
	vote,
	expectResults,
	wpEval,
} = require( './helpers.js' );

/**
 * How many vote-log rows a poll has.
 *
 * @param {number} pollId Poll to count.
 * @return {number} Row count in the log table.
 */
function countLog( pollId ) {
	return parseInt(
		wpEval(
			`global $wpdb;
			echo '<<<' . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pollsip} WHERE pollip_qid = %d", ${ pollId } ) ) . '>>>';`,
		),
		10,
	);
}

/**
 * Fill in and submit the Add Poll screen.
 *
 * @param {import('@playwright/test').Page} page     Page under test.
 * @param {string}                          question Poll question.
 * @param {string[]}                        answers  Answer texts.
 * @return {Promise<void>} Resolves once the poll is confirmed added.
 */
async function addPoll( page, question, answers ) {
	await page.goto( ADD_URL );

	await page.locator( '#pollq_question' ).fill( question );

	const boxes = page.locator( 'input[name="polla_answers[]"]' );

	// The screen starts with two answer rows; Add Answer grows it one at a time.
	for ( let i = 2; i < answers.length; i++ ) {
		await page.getByRole( 'button', { name: 'Add Answer' } ).click();
	}

	for ( let i = 0; i < answers.length; i++ ) {
		await boxes.nth( i ).fill( answers[ i ] );
	}

	await page.getByRole( 'button', { name: 'Add Poll', exact: true } ).click();

	// The screen does not redirect: it stays on Add Poll and prints a notice
	// with the new poll's id and a link back to Manage Polls. The notice is the
	// confirmation, so that is what the wait is on. It sits inside the bare
	// #message container the admin JavaScript writes into, and is the only
	// element on the screen carrying a notice class.
	await expect( page.locator( '.notice-success.inline' ) ).toContainText( 'added successfully' );
}

test.describe( 'Managing polls', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async () => {
		deleteAllPolls();
	} );

	test( 'a poll added through the screen appears in the list and on the front end', async ( {
		page,
		requestUtils,
	} ) => {
		const question = uniqueQuestion( 'Which way?' );

		await addPoll( page, question, [ 'Left', 'Right', 'Straight on' ] );

		await page.goto( MANAGE_URL );
		await expect( page.locator( '.wp-list-table' ) ).toContainText( question );

		// The id the plugin assigned, read back from the row so the front-end
		// check is against the poll that was actually made.
		const row = page.locator( '.wp-list-table tr', { hasText: question } );
		const pollId = parseInt( await row.locator( '.column-pollq_id' ).innerText(), 10 );

		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Way post' ) );
		await page.goto( post.link );

		await expect( poll( page, pollId ) ).toContainText( question );
		await expect( poll( page, pollId ) ).toContainText( 'Straight on' );
	} );

	test( 'editing a poll changes its question and answers on the front end', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Before edit' ),
			answers: [ 'Keep', 'Change me' ],
		} );

		await admin.visitAdminPage( 'admin.php', `page=wp-polls&mode=edit&id=${ pollId }` );

		const newQuestion = uniqueQuestion( 'After edit' );
		await page.locator( '#pollq_question' ).fill( newQuestion );
		await page.getByRole( 'button', { name: 'Edit Poll' } ).click();

		// Not the notice: editing the question but leaving the answers alone
		// makes the screen report "No Changes… To Poll's Answer" and suppress
		// the poll-level "Edited Successfully", though the question is saved
		// either way. The stored row is the honest signal, and it is what the
		// front end renders from.
		await expect.poll( () => pollColumn( pollId, 'pollq_question' ) ).toBe( newQuestion );

		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Edited post' ) );
		await page.goto( post.link );
		await expect( poll( page, pollId ) ).toContainText( newQuestion );
	} );

	test( 'deleting a poll removes it, but only once the confirm is accepted', async ( {
		admin,
		page,
	} ) => {
		const question = uniqueQuestion( 'Delete me' );
		const pollId = createPoll( { question, answers: [ 'A', 'B' ] } );

		await admin.visitAdminPage( 'admin.php', 'page=wp-polls' );

		const row = page.locator( '.wp-list-table tr', { hasText: question } );
		await row.hover();

		// Dismissed first: the confirm is the guard, and a delete that ignored
		// it would take the row anyway.
		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await row.getByRole( 'link', { name: 'Delete' } ).click();
		await expect( page.locator( '.wp-list-table' ) ).toContainText( question );

		// Accepted: the row goes, and the tables agree it is gone.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await row.getByRole( 'link', { name: 'Delete' } ).click();
		await expect( page.locator( '.wp-list-table tr', { hasText: question } ) ).toHaveCount( 0 );
		expect( pollColumn( pollId, 'pollq_id' ) ).toBe( '' );
	} );

	test( 'closing a poll from the edit screen ends voting on the front end', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Still open?' ),
			answers: [ 'Yes', 'No' ],
		} );

		await admin.visitAdminPage( 'admin.php', `page=wp-polls&mode=edit&id=${ pollId }` );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page.locator( '#close_poll' ).click();

		// The close is an AJAX call; the stored flag is the thing to wait on
		// rather than a toast.
		await expect
			.poll( () => pollColumn( pollId, 'pollq_active' ) )
			.toBe( '0' );

		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Closed now' ) );
		await page.goto( post.link );
		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount( 0 );
	} );

	test( 'the logs screen lists votes, and clears only the chosen poll', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const kept = createPoll( { question: uniqueQuestion( 'Keep logs' ), answers: [ 'K1', 'K2' ] } );
		const cleared = createPoll( {
			question: uniqueQuestion( 'Clear logs' ),
			answers: [ 'C1', 'C2' ],
		} );

		for ( const [ id, title ] of [
			[ kept, 'Keep post' ],
			[ cleared, 'Clear post' ],
		] ) {
			const post = await createPollPost( requestUtils, id, uniqueQuestion( title ) );
			await page.goto( post.link );
			await vote( page, id );
			await expectResults( page, id );
		}

		// Both polls have a logged vote to start.
		expect( countLog( kept ) ).toBe( 1 );
		expect( countLog( cleared ) ).toBe( 1 );

		await admin.visitAdminPage( 'admin.php', `page=wp-polls&mode=logs&id=${ cleared }` );

		// The delete is double-gated: a "Yes" checkbox and then a confirm().
		// Skip the checkbox and the script alerts and deletes nothing, which is
		// the guard working -- so the test ticks it, exactly as an admin must.
		await page.locator( '#delete_logs_yes' ).check();
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page.getByRole( 'button', { name: 'Delete Logs For This Poll Only' } ).click();

		// Only the one poll's log is emptied; the other is untouched, which is
		// the whole difference between this and Delete All Logs.
		await expect.poll( () => countLog( cleared ) ).toBe( 0 );
		expect( countLog( kept ) ).toBe( 1 );
	} );
} );
