/**
 * The pre-3.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so upgrade() hangs
 * off init, at priority 5. That is a hook every request fires, WP-CLI's boot
 * included, which is why the fixtures hand their pre-migration state back from
 * the process that seeds them: any later CLI call migrates the site on its way
 * in. Loading an admin page in a browser is the request these specs let do it.
 *
 * Three separate things happen on that path and none of them can be seen from
 * PHPUnit alone:
 *
 *   1. Thirty-odd `poll_*` rows fold into one, including the toggle this plugin
 *      used to read out of a row shared with six siblings.
 *   2. The two result templates are replaced, because the poll bar's markup,
 *      class names and stylesheet all changed together -- a template still
 *      holding the old single `div.pollbar` renders an invisible bar.
 *   3. The inline `onclick="poll_vote(%POLL_ID%)"` handlers in the two footer
 *      templates become `data-poll-*` attributes. Whether that actually
 *      *worked* is a question about a browser: the handler is gone, the script
 *      binds to the attributes, and a vote either goes through or it does not.
 *
 * Rows are read raw. WP_Polls_Options::all() merges over the defaults, so it
 * answers identically for a row holding the defaults and for no row at all --
 * the §7.6.1 failure exactly. Ask the database, not the plugin.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SETTINGS_URL,
	createPoll,
	createPollPost,
	defaultOptions,
	deleteAllPolls,
	expectResults,
	installLegacyRows,
	poll,
	rawOptions,
	resetOptions,
	runningVersions,
	setVersionRow,
	survivingLegacyRows,
	uniqueQuestion,
	versionRow,
	vote,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

/**
 * The rows a 2.77.4 install carries, in the shapes it wrote them.
 *
 * Not all thirty -- the fifteen templates alone would drown the file -- but one
 * of each kind the migration has to handle differently: a flat rename, two that
 * land two levels deep, a template, the pre-3.0.0 `poll_options` row that held
 * nothing but the proxy header, the shared WP-Stats row, and the two version
 * markers that collapse into one.
 *
 * @param {Object} overrides Anything this particular site had changed.
 * @return {Object} Legacy option name => value.
 */
function legacyInstall( overrides = {} ) {
	return {
		poll_close: 0,
		poll_archive_perpage: 3,
		poll_archive_url: 'https://example.com/old-archive',
		poll_ans_sortby: 'polla_votes',
		poll_logging_method: 1,
		poll_options: { ip_header: 'HTTP_X_FORWARDED_FOR' },
		poll_version: '2.77.4',
		poll_db_version: '1.2',
		...overrides,
	};
}

test.describe( 'The pre-3.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install: markers stamped, settings at a fresh
		// install's, no legacy rows anywhere. Every other spec in this suite
		// starts from that, and this is the only file that takes it apart.
		wpEval(
			`foreach ( array_merge(
				array_keys( WP_Polls_Options::legacy_map() ),
				WP_Polls_Options::legacy_extra_rows(),
				WP_Polls_Options::legacy_shared_rows()
			) as $row ) {
				delete_option( $row );
			}
			echo '<<<done>>>';`,
		);
		setVersionRow( runningVersions() );
		resetOptions();
	} );

	test( 'the scattered rows fold into one, every old row goes, and the markers are stamped', async ( {
		page,
	} ) => {
		const seeded = installLegacyRows( legacyInstall() );

		// The fixture really is a pre-3.0.0 install: old rows present, new ones
		// absent. Without this the assertions below could be describing a site
		// that was already migrated, and would pass with the fold-in deleted.
		expect( seeded.surviving ).toContain( 'poll_close' );
		expect( seeded.options ).toBe( false );
		expect( seeded.versions ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// Written, not merely readable through the defaults.
		expect( stored ).not.toBe( false );
		expect( stored.archive.url ).toBe( 'https://example.com/old-archive' );
		expect( stored.sort.answers_by ).toBe( 'polla_votes' );

		// Strings, not integers, and that is WordPress rather than this plugin:
		// a scalar option round-trips through the database as text, so what the
		// fold-in reads out of poll_close is "0" whatever was written. The rows
		// that were arrays keep their types, because those are serialised.
		// Every reader casts, which is why it has never mattered -- and why a
		// test asserting 0 here would be asserting something untrue of every
		// install in the world.
		expect( stored.close ).toBe( '0' );
		expect( stored.archive.per_page ).toBe( '3' );

		// logging_method became check_method: the setting never chose whether to
		// log anything, only how a repeat vote is recognised.
		expect( stored.check_method ).toBe( '1' );

		// The pre-3.0.0 poll_options row held one thing, and it comes across too.
		expect( stored.ip_header ).toBe( 'HTTP_X_FORWARDED_FOR' );

		// Every old row gone rather than left to rot, read through the plugin's
		// own three lists so a row added to the migration and forgotten by the
		// cleanup shows up here rather than going unnoticed.
		expect( survivingLegacyRows() ).toEqual( [] );

		// Two markers in one row, in one write, matching the code that is running.
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'a site on the shipped settings still gets a row written', async ( { page } ) => {
		const defaults = defaultOptions();

		// The commonest install there is: never changed a setting. Its migrated
		// result is what the defaults would have answered anyway, which is the
		// one shape where a skipped write leaves no trace -- and the shape every
		// customised fixture in this file is blind to. save() tells an absent
		// row from a defaulted one by passing an explicit default to
		// get_option() and add_option()ing it; this is what that is for.
		const seeded = installLegacyRows( {
			poll_close: defaults.close,
			poll_archive_perpage: defaults.archive.per_page,
			poll_archive_url: defaults.archive.url,
			poll_logging_method: defaults.check_method,
			poll_version: '2.77.4',
			poll_db_version: '1.2',
		} );

		expect( seeded.options ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored ).not.toBe( false );
		expect( String( stored.archive.per_page ) ).toBe( String( defaults.archive.per_page ) );
		expect( survivingLegacyRows() ).toEqual( [] );
	} );

	test( "this plugin's share of the WP-Stats row is folded in and the row deleted", async ( {
		page,
	} ) => {
		// The shared row in the shape a bare name="stats_display[]" posts: a
		// plain list of the enabled keys, with polls not among them.
		installLegacyRows( legacyInstall( { stats_display: [ 'postviews', 'ratings' ] } ) );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( false );

		// Deleted by the migration that folded it in -- and by nothing else.
		// §13.2 splits the two jobs: uninstall must leave this row alone,
		// because up to six siblings that have not upgraded are still reading it.
		expect( survivingLegacyRows() ).not.toContain( 'stats_display' );
	} );

	test( 'an absent shared row means on, not off', async ( { page } ) => {
		// A sibling upgraded first and took the row with it. Reading that as a
		// deliberate opt-out would take the polls block off the WP-Stats page of
		// any site that updated a sibling first, with nothing to say why.
		const seeded = installLegacyRows( legacyInstall() );

		expect( seeded.surviving ).not.toContain( 'stats_display' );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( true );
	} );

	test( 'the old poll bar is replaced, and the migrated poll still renders', async ( {
		page,
		requestUtils,
	} ) => {
		// A 2.77.4 result template, complete with the single div the bar used to
		// be and a bar style naming an images/ directory that no longer exists.
		// Both are replaced outright rather than patched: the markup, the class
		// names and the stylesheet changed together, so there is no version of
		// the old template that still draws a visible bar.
		installLegacyRows(
			legacyInstall( {
				poll_bar: { style: 'default_gradient', background: 'd8e1eb', border: 'c8c8c8', height: 8 },
				poll_template_resultbody:
					'<li><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%px;"></div>%POLL_ANSWER_TEXT%</li>',
			} ),
		);

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// 'default_gradient' was one of the two shipped tiles, both of which
		// shaded light to dark, so it becomes the CSS gradient rather than the
		// flat fill.
		expect( stored.bar.style ).toBe( 'gradient' );
		expect( stored.templates.resultbody ).toBe( defaultOptions().templates.resultbody );

		// Present is not alive: the migrated templates have to be ones the front
		// end can still render a poll from.
		deleteAllPolls();

		const question = uniqueQuestion( 'Which bar' );
		const pollId = createPoll( { question, answers: [ 'The new one', 'The old one' ] } );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'After the upgrade' ) );

		await page.goto( post.link );

		await expect( poll( page, pollId ) ).toContainText( question );
	} );

	test( 'a migrated onclick template still votes, without an inline handler', async ( {
		page,
		requestUtils,
	} ) => {
		// The 2.77.4 vote footer, whose button called a global function from an
		// inline onclick. 3.0.0 binds to data-poll-* attributes instead, and the
		// migration rewrites the stored template -- so this is the one assertion
		// that can say whether the rewrite produced something that still works,
		// rather than something that merely looks right in the row.
		installLegacyRows(
			legacyInstall( {
				poll_template_votefooter:
					'<p style="text-align: center;"><input type="button" name="vote" value="   Vote   " class="Buttons" onclick="poll_vote(%POLL_ID%); return false;" /></p>',
			} ),
		);

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.templates.votefooter ).toContain( 'data-poll-id="%POLL_ID%"' );
		expect( stored.templates.votefooter ).toContain( 'data-poll-action="vote"' );
		expect( stored.templates.votefooter ).not.toContain( 'onclick' );

		deleteAllPolls();

		const question = uniqueQuestion( 'Does the migrated button work' );
		const pollId = createPoll( { question, answers: [ 'Yes', 'No' ] } );
		const post = await createPollPost( requestUtils, pollId, uniqueQuestion( 'Voting after the upgrade' ) );

		await page.goto( post.link );

		// No inline handler survives on the rendered control, and the vote goes
		// through anyway -- which is the whole claim the rewrite makes.
		await expect( poll( page, pollId ).locator( '[onclick]' ) ).toHaveCount( 0 );

		await vote( page, pollId, [ 0 ] );
		await expectResults( page, pollId );

		await expect( poll( page, pollId ) ).toContainText( 'Yes' );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A legacy row that should never be read, alongside markers saying the
		// upgrade has already happened. The gate returning early is what keeps
		// every admin request from being an option write, and the proof it
		// returned early is that this row survives untouched.
		wpEval( "update_option( 'poll_close', 0 ); echo '<<<done>>>';" );
		setVersionRow( runningVersions() );

		await page.goto( DASHBOARD_URL );

		expect( survivingLegacyRows() ).toContain( 'poll_close' );
		expect( rawOptions().close ).toBe( defaultOptions().close );
	} );

	test( 'the settings screen is reachable after all of it', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect( page.getByRole( 'heading', { name: 'Poll Settings' } ) ).toBeVisible();
	} );
} );
