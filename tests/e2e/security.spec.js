/**
 * The stored XSS regressions, in a real browser.
 *
 * The released 2.77.3 could be made to hand a script tag stored in a poll
 * question or answer back to every visitor. These tests put those payloads
 * into the tables directly -- the row a compromised or pre-fix install
 * already has -- and then check every surface that renders them: the ballot,
 * the AJAX-returned results, the Manage Polls screen and the vote log.
 *
 * The assertion is the same everywhere: the sentinel the payload would set
 * must never become defined, the dangerous form -- a script element, an event
 * handler -- must be absent, and the payload's text must survive on the page.
 * Escaping that ate the payload entirely would pass the first two and still be
 * a bug, because a poll answer that silently vanishes is one.
 *
 * The plugin runs stored questions and answers through wp_kses_post(), which
 * keeps post-level markup: a bare <img> is allowed through, and only its
 * onerror handler is stripped. So the check is not "no image" -- an image is
 * fine -- but "nothing that runs": no onerror, no script, and the sentinel
 * still undefined.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createPoll,
	createPollPost,
	deleteAllPolls,
	expectResults,
	poll,
	uniqueQuestion,
	vote,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

/**
 * A poll whose question and answers are all attacks.
 *
 * @return {number} The poll id.
 */
function createHostilePoll() {
	return createPoll( {
		question: `${ uniqueQuestion( 'Hostile' ) } ${ SCRIPT_PAYLOAD }`,
		answers: [ `Answer one ${ IMG_PAYLOAD }`, `Answer two ${ ATTR_PAYLOAD }`, 'Plain answer' ],
	} );
}

test.describe( 'Stored XSS stays stored', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async () => {
		deleteAllPolls();
	} );

	test( 'the ballot renders a hostile poll as text', async ( { page, requestUtils } ) => {
		const pollId = createHostilePoll();
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Hostile ballot' ) );

		await page.goto( post.link );

		await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );

		// As text, not merely absent: the question and both poisoned answers
		// still say what the row says.
		await expect( poll( page, pollId ) ).toContainText( 'window.__pwned' );
		await expect( poll( page, pollId ) ).toContainText( 'Answer one' );
		await expect( poll( page, pollId ) ).toContainText( 'onmouseover' );
		await expect( poll( page, pollId ).locator( 'img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the AJAX results carry the payloads back escaped', async ( { page, requestUtils } ) => {
		const pollId = createHostilePoll();
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Hostile results' ) );

		await page.goto( post.link );

		// The classic wp-polls vector: the results markup is built server-side
		// in the AJAX response and swapped straight into the page. A payload
		// that survives the round trip runs here even if the ballot was clean.
		await vote( page, pollId );
		await expectResults( page, pollId );

		expect( await pwned( page ) ).toBe( false );

		await expect( poll( page, pollId ) ).toContainText( 'window.__pwned' );
		await expect( poll( page, pollId ).locator( 'img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the Manage Polls screen renders a hostile poll as text', async ( {
		admin,
		page,
	} ) => {
		createHostilePoll();

		await admin.visitAdminPage( 'admin.php', 'page=wp-polls' );

		expect( await pwned( page ) ).toBe( false );

		await expect( page.locator( '.wp-list-table' ) ).toContainText( 'window.__pwned' );
		await expect( page.locator( '.wp-list-table img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the vote log renders hostile answers as text', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const pollId = createHostilePoll();
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Hostile log' ) );

		// A logged vote is what puts the answer text on the logs screen.
		await page.goto( post.link );
		await vote( page, pollId );
		await expectResults( page, pollId );

		await admin.visitAdminPage( 'admin.php', `page=wp-polls&mode=logs&id=${ pollId }` );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( 'body' ) ).toContainText( 'Answer one' );
		await expect( page.locator( '#wpbody img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the edit screen round-trips a hostile poll without running or mangling it', async ( {
		admin,
		page,
	} ) => {
		const pollId = createHostilePoll();

		await admin.visitAdminPage( 'admin.php', `page=wp-polls&mode=edit&id=${ pollId }` );

		expect( await pwned( page ) ).toBe( false );

		// The form field must hold the stored value itself. An edit screen
		// that escaped the value into the attribute but decoded it on save is
		// fine; one that dropped or double-escaped it corrupts the poll the
		// moment an admin presses save.
		await expect( page.locator( '#pollq_question' ) ).toHaveValue( /window\.__pwned/ );
	} );
} );

test.describe( 'The admin screens are gated', () => {
	test( 'a subscriber gets no Polls menu and no screen, and an admin gets both', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes
		// with the plugin deactivated; the admin half is what proves the gate
		// is the capability and not a missing page.
		await admin.visitAdminPage( 'index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'Polls' );

		await admin.visitAdminPage( 'admin.php', 'page=wp-polls' );
		await expect( page.getByRole( 'heading', { name: 'Manage Polls' } ) ).toBeVisible();

		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username: 'polls_subscriber',
				email: 'polls_subscriber@example.com',
				password: 'correct-horse-battery-staple',
				roles: [ 'subscriber' ],
			},
		} ).catch( () => {} ); // Already there from an earlier run.

		const context = await page.context().browser().newContext( { storageState: undefined } );
		const other = await context.newPage();

		await other.goto( '/wp-login.php' );

		// wp-login.php focuses and selects #user_login on a 200ms timer, so
		// that a visitor can start typing. Filling across that moment puts the
		// password into the username box: Playwright focuses #user_pass, the
		// timer takes focus back and selects what is there, and the typed text
		// replaces the selection. Waiting for the timer's own effect is the
		// signal that it has already fired.
		await expect( other.locator( '#user_login' ) ).toBeFocused();

		await other.locator( '#user_login' ).fill( 'polls_subscriber' );
		await other.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
		await other.locator( '#wp-submit' ).click();
		await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

		await other.goto( '/wp-admin/index.php' );
		await expect( other.locator( '#adminmenu' ).getByText( 'Polls' ) ).toHaveCount( 0 );

		await other.goto( '/wp-admin/admin.php?page=wp-polls' );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await context.close();
	} );
} );
