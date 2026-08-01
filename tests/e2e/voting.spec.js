/**
 * Voting, as a visitor does it.
 *
 * The vote is the plugin: a form that swaps itself for results without leaving
 * the page, and then refuses to be voted on again. Everything here runs
 * against a poll created through the same tables a real install has, and the
 * assertions are on what a visitor can see -- the counts, the bars, and the
 * absence of the form afterwards.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ALLOW,
	CHECK,
	configure,
	createPoll,
	createPollPost,
	deleteAllPolls,
	expectResults,
	markPage,
	pageWasNotReloaded,
	poll,
	uniqueQuestion,
	vote,
} = require( './helpers.js' );

test.describe( 'Voting on a poll', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async () => {
		deleteAllPolls();
	} );

	test.afterEach( async ( { admin, page } ) => {
		// Back to the defaults, through the form, so a spec that tightened the
		// repeat-vote rules does not decide the outcome of the next one.
		await admin.visitAdminPage( 'index.php' );
		await configure( page );
	} );

	test( 'a vote swaps in the results without leaving the page', async ( {
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Favourite season?' ),
			answers: [ 'Spring', 'Summer', 'Winter' ],
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Season post' ) );

		await page.goto( post.link );
		await markPage( page );

		await vote( page, pollId, [ 1 ] );
		await expectResults( page, pollId );

		// The count and the marked choice, not just "some results": the row a
		// visitor picked is the one wearing the vote.
		await expect( poll( page, pollId ) ).toContainText( 'Summer' );
		await expect( poll( page, pollId ).locator( 'li strong' ) ).toContainText( 'Summer' );
		await expect( poll( page, pollId ) ).toContainText( 'Total Voters: 1' );

		expect( await pageWasNotReloaded( page ) ).toBe( true );
	} );

	test( 'reloading after a vote shows results, not the form', async ( {
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Remembered?' ),
			answers: [ 'Yes', 'No' ],
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Memory post' ) );

		await page.goto( post.link );
		await vote( page, pollId );
		await expectResults( page, pollId );

		// The default check method is cookie-and-ip, so coming back is the
		// repeat visit both of those exist to recognise.
		await page.reload();

		await expect( poll( page, pollId ).locator( '.wp-polls-bar-fill' ).first() ).toBeVisible();
		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount( 0 );
	} );

	test( 'with checking off, the same visitor can vote twice', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Twice?' ),
			answers: [ 'Once', 'Again' ],
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Twice post' ) );

		await admin.visitAdminPage( 'index.php' );
		await configure( page, { check: CHECK.never } );

		await page.goto( post.link );
		await vote( page, pollId );
		await expectResults( page, pollId );

		// "Do Not Check" means exactly that: the form is offered again on the
		// next visit and the second vote lands on top of the first.
		await page.reload();
		await vote( page, pollId );
		await expectResults( page, pollId );

		await expect( poll( page, pollId ) ).toContainText( 'Total Voters: 2' );
	} );

	test( 'a multiple-answer poll records every chosen answer', async ( {
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Pick two toppings' ),
			answers: [ 'Cheese', 'Basil', 'Olives' ],
			multiple: 2,
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Toppings post' ) );

		await page.goto( post.link );
		await vote( page, pollId, [ 0, 2 ] );
		await expectResults( page, pollId );

		// Two answers, one voter: the totals row is what separates "recorded
		// both choices" from "counted one person twice".
		await expect( poll( page, pollId ).locator( 'li strong' ) ).toHaveCount( 2 );
		await expect( poll( page, pollId ) ).toContainText( 'Total Voters: 1' );
	} );

	test( 'choosing more than the poll allows is refused before it is sent', async ( {
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Pick at most two' ),
			answers: [ 'A', 'B', 'C' ],
			multiple: 2,
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Limit post' ) );

		await page.goto( post.link );

		// The refusal arrives as an alert, so the test must catch it: an
		// unhandled dialog blocks every event after it.
		const messages = [];
		page.on( 'dialog', ( dialog ) => {
			messages.push( dialog.message() );
			return dialog.dismiss();
		} );

		await vote( page, pollId, [ 0, 1, 2 ] );

		await expect
			.poll( () => messages.length, { timeout: 5000 } )
			.toBeGreaterThan( 0 );
		expect( messages[ 0 ] ).toContain( '2' );

		// Refused client-side means nothing was sent: the form is still there.
		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toBeVisible();
	} );

	test( 'a closed poll offers results and no form', async ( { page, requestUtils } ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Closed already' ),
			answers: [ 'Was', 'Is' ],
			active: 0,
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Closed post' ) );

		await page.goto( post.link );

		// A closed poll with no votes still renders results -- zero-width bars,
		// so attached rather than visible -- and offers no ballot.
		await expect( poll( page, pollId ).locator( '.wp-polls-bar-fill' ).first() ).toBeAttached();
		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount( 0 );
	} );

	test( 'View Results shows the numbers without spending the vote', async ( {
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Peek first?' ),
			answers: [ 'Peek', 'Vote blind' ],
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Peek post' ) );

		await page.goto( post.link );
		await poll( page, pollId ).locator( '[data-poll-action="result"]' ).click();

		// Results with a way back: the visitor has not voted, so the booth
		// link is offered where a voter would see nothing. Attached rather than
		// visible because the untouched poll's bars have no width yet.
		await expect( poll( page, pollId ).locator( '.wp-polls-bar-fill' ).first() ).toBeAttached();
		await expect( poll( page, pollId ).locator( '[data-poll-action="booth"]' ) ).toBeVisible();
		await poll( page, pollId ).locator( '[data-poll-action="booth"]' ).click();

		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toBeVisible();

		await vote( page, pollId );
		await expectResults( page, pollId );
		await expect( poll( page, pollId ) ).toContainText( 'Total Voters: 1' );
	} );

	test( 'only registered visitors may vote when the setting says so', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const pollId = createPoll( {
			question: uniqueQuestion( 'Members only' ),
			answers: [ 'In', 'Out' ],
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Members post' ) );

		await admin.visitAdminPage( 'index.php' );
		await configure( page, { check: CHECK.username, allow: ALLOW.loggedInOnly } );

		// A guest gets the results and no ballot. A separate context rather
		// than logging out, so the admin session survives for the cleanup.
		const guest = await page.context().browser().newContext( { storageState: undefined } );
		const guestPage = await guest.newPage();

		await guestPage.goto( post.link );
		await expect(
			poll( guestPage, pollId ).locator( '.wp-polls-bar-fill' ).first(),
		).toBeAttached();
		await expect( poll( guestPage, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount(
			0,
		);
		await guest.close();

		// The logged-in admin can vote -- once. Checking by username survives
		// a cleared cookie jar, which is what it is for.
		await page.goto( post.link );
		await vote( page, pollId );
		await expectResults( page, pollId );

		await page.reload();
		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount( 0 );
	} );

	test( 'two polls on one page vote independently', async ( { page, requestUtils } ) => {
		const first = createPoll( {
			question: uniqueQuestion( 'First of two' ),
			answers: [ 'A1', 'A2' ],
		} );
		const second = createPoll( {
			question: uniqueQuestion( 'Second of two' ),
			answers: [ 'B1', 'B2' ],
		} );
		const post = await requestUtils.createPost( {
			title: uniqueQuestion( 'Two polls' ),
			content: `[poll id="${ first }"]\n\n[poll id="${ second }"]`,
			status: 'publish',
		} );

		await page.goto( post.link );
		await vote( page, first );
		await expectResults( page, first );

		// The second poll must still be a ballot: a vote for one poll that
		// closed the other would be the two ids crossing somewhere.
		await expect( poll( page, second ).locator( '[data-poll-action="vote"]' ) ).toBeVisible();

		await vote( page, second, [ 1 ] );
		await expectResults( page, second );
	} );
} );
