/**
 * The REST routes.
 *
 * Three routes -- read a poll, read its result, vote -- under `polls/v1`, a
 * bare noun rather than the plugin slug, because a `wp-` prefix is a
 * wordpress.org directory convention and not a naming rule for what a plugin
 * registers. Another plugin can claim the same noun and WordPress will not
 * detect it; that is the accepted trade.
 *
 * The PHPUnit suite already dispatches these through WP_REST_Server, so what is
 * worth testing here is only what the HTTP layer decides: that the namespace is
 * really the one that got registered, that voting works for a visitor who is
 * not logged in at all -- which is who polls are for and who a dispatcher test
 * cannot impersonate -- and that the AJAX endpoint these sit beside still
 * answers, because it was kept on purpose.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createPoll,
	deleteAllPolls,
	pollAnswerRows,
	wpEval,
} = require( './helpers.js' );

/** Every route lives under this namespace. */
const NAMESPACE = '/polls/v1';

test.describe( 'The REST routes', () => {
	let pollId;

	test.beforeEach( async () => {
		deleteAllPolls();
		pollId = createPoll( {
			question: 'Which REST answer?',
			answers: [ 'First', 'Second' ],
		} );
	} );

	test.afterAll( async () => {
		deleteAllPolls();
	} );

	test( 'the fixture really is the namespace this plugin registered', async ( {
		requestUtils,
	} ) => {
		// Every call below is under one namespace. If it were ever renamed, all
		// of them would 404 and the "refused" tests would pass for the wrong
		// reason.
		const index = await requestUtils.rest( { path: '/' } );

		expect( index.namespaces ).toContain( 'polls/v1' );
		expect( index.namespaces ).not.toContain( 'wp-polls/v1' );
	} );

	test( 'reading a poll returns its answers and its rendered markup', async ( {
		requestUtils,
	} ) => {
		const poll = await requestUtils.rest( { path: `${ NAMESPACE }/poll/${ pollId }` } );

		expect( poll.question ).toBe( 'Which REST answer?' );
		expect( poll.answers.map( ( answer ) => answer.text ) ).toEqual( [ 'First', 'Second' ] );
		expect( poll.is_open ).toBe( true );
		// The markup matters as much as the data: a poll's templates are the
		// site's to edit, so a client rebuilding the markup itself would ignore
		// every one of them.
		expect( poll.html ).toContain( 'Which REST answer?' );
	} );

	test( 'a poll that does not exist is a 404, not an empty poll', async ( {
		request,
	} ) => {
		const response = await request.get( `/index.php?rest_route=${ NAMESPACE }/poll/123456` );

		expect( response.status() ).toBe( 404 );
	} );

	// Everything above runs as the administrator, because playwright.config.js
	// sets `use.storageState` for the whole suite and the `request` fixture
	// inherits it like any other. That matters more here than anywhere else in
	// this plugin: voting is the one thing a site's visitors do, and a "logged
	// out" test that quietly carries an admin cookie is testing the wrong person
	// -- it fails on a nonce minted for user 0 and verified against user 1,
	// which reads as a broken endpoint rather than a broken fixture.
	test.describe( 'as a visitor who is not logged in', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'a logged-out visitor can vote, which is who polls are for', async ( {
			request,
		} ) => {
			// Minted through WP-CLI rather than scraped off the page, and sound
			// only because both sides are the same user: `wp eval` runs with
			// nobody logged in, and the context above has had its cookies taken
			// away. A nonce is tied to the user it was made for.
			const nonce = wpEval(
				`echo '<<<' . wp_create_nonce( 'poll_${ pollId }-nonce' ) . '>>>';`,
			);

			expect( nonce ).not.toBe( '' );

			const answerId = pollAnswerRows( pollId )[ 0 ].id;

			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/poll/${ pollId }/vote`,
				{ form: { answers: String( answerId ), nonce } },
			);

			expect( response.status() ).toBe( 200 );
			expect( pollAnswerRows( pollId )[ 0 ].votes ).toBe( 1 );
		} );

		test( 'the fixture really is logged out', async ( { request } ) => {
			// Without this the test above proves nothing on the day somebody
			// changes the storage state: it would pass as the administrator too,
			// and a vote is not the thing that would tell you.
			const me = await request.get( '/index.php?rest_route=/wp/v2/users/me' );

			expect( me.status() ).toBe( 401 );
		} );

		test( 'a vote without the poll nonce is refused and records nothing', async ( {
			request,
		} ) => {
			const answerId = pollAnswerRows( pollId )[ 0 ].id;

			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/poll/${ pollId }/vote`,
				{ form: { answers: String( answerId ), nonce: 'not-the-nonce' } },
			);

			expect( response.status() ).toBe( 403 );
			expect( pollAnswerRows( pollId )[ 0 ].votes ).toBe( 0 );
		} );
	} );

	test( 'the AJAX endpoint these sit beside still answers', async ( { request } ) => {
		// Kept on purpose: a theme or a cached script may still be calling it,
		// and those are in the position of a post holding a shortcode. If this
		// ever 404s, the routes above stopped being an addition and became a
		// replacement.
		const response = await request.get(
			`/wp-admin/admin-ajax.php?action=polls&view=result&poll_id=${ pollId }`,
		);

		expect( response.status() ).toBe( 200 );
	} );
} );
