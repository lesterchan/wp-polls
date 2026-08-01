/**
 * The settings screen, and what each setting changes.
 *
 * A setting that saves but does nothing, or does something but will not save,
 * are the two failures a screenshot cannot tell apart. So each test here
 * drives the form and then checks the far end: the stored option for the ones
 * the vote logic reads, and the rendered page for the ones a visitor sees.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	CHECK,
	createPoll,
	createPollPost,
	deleteAllPolls,
	openSettings,
	poll,
	saveSettings,
	uniqueQuestion,
	wpEval,
} = require( './helpers.js' );

/**
 * One value from the single option row, by its dotted path.
 *
 * @param {string} path Dotted path into the option array, e.g. 'bar.height'.
 * @return {string} The stored value, as a string.
 */
function option( path ) {
	const keys = path.split( '.' );
	const walk = keys.map( ( k ) => `[ '${ k }' ]` ).join( '' );

	return wpEval(
		`$all = WP_Polls_Options::all();
		echo '<<<' . $all${ walk } . '>>>';`,
	);
}

test.describe( 'Poll settings', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.afterEach( async ( { admin, page } ) => {
		// Everything this file changes lives in one option row, so one reset
		// through the form puts it all back for the next test.
		await admin.visitAdminPage( 'index.php' );
		await openSettings( page );
		await page.locator( '#poll_check_method' ).selectOption( CHECK.cookieAndIp );
		await page.locator( '#poll_cookielog_expiry' ).fill( '0' );
		await page.locator( '#poll_ip_header' ).fill( '' );
		await page.locator( '#poll_bar_height' ).fill( '8' );
		await saveSettings( page );
	} );

	for ( const [ name, value ] of Object.entries( CHECK ) ) {
		test( `the "${ name }" repeat-vote method saves`, async ( { admin, page } ) => {
			await admin.visitAdminPage( 'index.php' );
			await openSettings( page );

			await page.locator( '#poll_check_method' ).selectOption( value );
			await saveSettings( page );

			// The vote path reads this as an int, so the stored value is what
			// decides behaviour -- the selected option only matters if it lands
			// there.
			expect( option( 'check_method' ) ).toBe( value );
		} );
	}

	test( 'Remember A Voter For saves a number of seconds', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await openSettings( page );

		await page.locator( '#poll_cookielog_expiry' ).fill( '86400' );
		await saveSettings( page );

		expect( option( 'cookie_expiry' ) ).toBe( '86400' );

		// It survives a reload of the screen, i.e. it is the field's value that
		// was stored and not just a notice that lied.
		await openSettings( page );
		await expect( page.locator( '#poll_cookielog_expiry' ) ).toHaveValue( '86400' );
	} );

	test( 'the proxy header field saves what is typed into it', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await openSettings( page );

		await page.locator( '#poll_ip_header' ).fill( 'HTTP_X_FORWARDED_FOR' );
		await saveSettings( page );

		expect( option( 'ip_header' ) ).toBe( 'HTTP_X_FORWARDED_FOR' );
	} );

	test( 'the bar height reaches the front-end result bar', async ( { admin, page, requestUtils } ) => {
		deleteAllPolls();
		const pollId = createPoll( {
			question: uniqueQuestion( 'Tall bars' ),
			answers: [ 'One', 'Two' ],
			active: 0, // Closed, so the results -- and the bar -- show without a vote.
		} );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Bar height post' ) );

		await admin.visitAdminPage( 'index.php' );
		await openSettings( page );
		await page.locator( '#poll_bar_height' ).fill( '24' );
		await saveSettings( page );

		await page.goto( post.link );

		// The height is carried by a custom property on an inline style block
		// and consumed by the stylesheet, so the honest check is the height the
		// browser actually computed for the bar.
		const bar = poll( page, pollId ).locator( '.wp-polls-bar' ).first();
		await expect( bar ).toBeVisible();
		const height = await bar.evaluate( ( el ) => getComputedStyle( el ).height );
		expect( height ).toBe( '24px' );
	} );

	test( 'saving shows the confirmation notice', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await openSettings( page );

		// options.php registers "Settings saved." under the general slug, and a
		// settings page that scopes settings_errors() to its own slug filters
		// it out. Its presence is the proof the page does not do that.
		await saveSettings( page );
	} );

	test( 'the options and templates tabs are both reachable', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await openSettings( page, 'templates' );

		await expect( page.getByRole( 'heading', { name: 'Poll Settings' } ) ).toBeVisible();
		// The templates tab carries the template editors; landing on the
		// options tab instead would still show the heading, so assert on
		// something only this tab has.
		await expect( page.locator( 'textarea, input[name*="templates"]' ).first() ).toBeVisible();
	} );
} );
