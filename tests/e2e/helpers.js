/**
 * Shared steps for the WP-Polls end-to-end suite.
 *
 * A poll is not a post: it lives in three tables of its own, so there is no REST
 * route to create one with and requestUtils cannot help. Fixtures therefore come
 * from one of two places. Anything a person would type goes through the Add Poll
 * screen, so the screen is exercised by the tests that depend on it. Anything a
 * person could not type -- a question holding a script tag, a row as an older
 * release would have written it -- goes in through WP-CLI, because the point of
 * those tests is what the front end does with a row it did not sanitise itself.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const MANAGE_URL = '/wp-admin/admin.php?page=wp-polls';
const ADD_URL = '/wp-admin/admin.php?page=wp-polls-add';
const SETTINGS_URL = '/wp-admin/admin.php?page=wp-polls-settings';
const TEMPLATES_URL = `${ SETTINGS_URL }&tab=templates`;

/** Values the "Check For Repeat Votes" select stores. */
const CHECK = {
	never: '0',
	cookie: '1',
	ip: '2',
	cookieAndIp: '3',
	username: '4',
};

/** Values the "Who Is Allowed To Vote?" select stores. */
const ALLOW = {
	guestsOnly: '0',
	loggedInOnly: '1',
	everyone: '2',
};

/** What the defaults are, so a test that changes a setting can put it back. */
const DEFAULTS = {
	check: CHECK.cookieAndIp,
	allow: ALLOW.everyone,
	expiry: '0',
	ipHeader: '',
};

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a poll question holding
 * quotes, angle brackets and a script tag is exactly the sort of string that
 * arrives at the other end subtly different, and a fixture that is not the
 * payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Insert a poll straight into the tables, exactly as given.
 *
 * Nothing is sanitised on the way in, which is the point: this is the row an
 * install that has been running since 2.77.3 already has.
 *
 * @param {Object}   spec             What to insert.
 * @param {string}   spec.question    Poll question.
 * @param {string[]} spec.answers     Answer texts, in order.
 * @param {number}   [spec.multiple]  Maximum answers a voter may pick, 0 for single choice.
 * @param {number}   [spec.active]    1 open, 0 closed, -1 not started.
 * @param {number}   [spec.expiresIn] Seconds from now until it expires, 0 for never.
 * @param {number}   [spec.startsIn]  Seconds from now until it opens.
 * @return {number} The new poll's id.
 */
function createPoll( spec ) {
	const data = Buffer.from(
		JSON.stringify( {
			multiple: 0,
			active: 1,
			expiresIn: 0,
			startsIn: 0,
			...spec,
		} ),
		'utf8',
	).toString( 'base64' );

	const id = wpEval(
		`global $wpdb;
		$data = json_decode( base64_decode( '${ data }' ), true );
		$now = WP_Polls::now();
		$wpdb->insert(
			$wpdb->pollsq,
			array(
				'pollq_question'    => $data['question'],
				'pollq_timestamp'   => $now + $data['startsIn'],
				'pollq_totalvotes'  => 0,
				'pollq_active'      => $data['active'],
				'pollq_expiry'      => $data['expiresIn'] ? $now + $data['expiresIn'] : 0,
				'pollq_multiple'    => $data['multiple'],
				'pollq_totalvoters' => 0,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);
		$poll_id = (int) $wpdb->insert_id;
		foreach ( $data['answers'] as $answer ) {
			$wpdb->insert(
				$wpdb->pollsa,
				array( 'polla_qid' => $poll_id, 'polla_answers' => $answer, 'polla_votes' => 0 ),
				array( '%d', '%s', '%d' )
			);
		}
		WP_Polls_Options::set( 'latest_poll', WP_Polls::polls_latest_id() );
		echo '<<<' . $poll_id . '>>>';`,
	);

	return parseInt( id, 10 );
}

/**
 * One column of one poll row, as the database holds it.
 *
 * @param {number} pollId Poll to read.
 * @param {string} column Column of the questions table.
 * @return {string} The stored value.
 */
function pollColumn( pollId, column ) {
	return wpEval(
		`global $wpdb;
		echo '<<<' . $wpdb->get_var( $wpdb->prepare( "SELECT ${ column } FROM {$wpdb->pollsq} WHERE pollq_id = %d", ${ pollId } ) ) . '>>>';`,
	);
}

/**
 * Every answer of one poll, as the database holds them.
 *
 * @param {number} pollId Poll to read.
 * @return {string[]} Answer texts, in insertion order.
 */
function pollAnswers( pollId ) {
	return JSON.parse(
		wpEval(
			`global $wpdb;
			echo '<<<' . wp_json_encode( $wpdb->get_col( $wpdb->prepare( "SELECT polla_answers FROM {$wpdb->pollsa} WHERE polla_qid = %d ORDER BY polla_aid ASC", ${ pollId } ) ) ) . '>>>';`,
		),
	);
}

/**
 * Empty all three tables.
 *
 * A poll log outlives the poll it belongs to unless something says otherwise,
 * and the totals on the Manage screen are site-wide, so a spec that counts
 * anything has to start from a known table rather than from whatever the last
 * run left.
 *
 * @return {void}
 */
function deleteAllPolls() {
	wpEval(
		`global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->pollsq}" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->pollsa}" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->pollsip}" );
		WP_Polls_Options::set( 'latest_poll', 0 );
		echo '<<<done>>>';`,
	);
}

/**
 * Run the job that opens polls whose start has arrived and closes expired ones.
 *
 * @return {void}
 */
function runPollsCron() {
	wpEval( 'WP_Polls::cron_polls_status(); echo \'<<<done>>>\';' );
}

/**
 * Open the settings screen, on whichever tab.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @param {string}                          tab  Either 'options' or 'templates'.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page, tab = 'options' ) {
	await page.goto( 'templates' === tab ? TEMPLATES_URL : SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'Poll Settings' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice rather than the redirect: options.php sends the browser back
	// whether or not anything was written, so arriving here again says nothing.
	await expect( page.locator( '.settings-error, .notice-success' ).first() ).toContainText(
		'Settings saved.',
	);
}

/**
 * Put the repeat-vote settings into a known state.
 *
 * Through the form rather than through the option row, because the sanitizer
 * between the two is one of the things these tests are here to watch.
 *
 * @param {import('@playwright/test').Page} page              Page under test.
 * @param {Object}                          settings          What to select.
 * @param {string}                          [settings.check]  A value from CHECK.
 * @param {string}                          [settings.allow]  A value from ALLOW.
 * @param {string}                          [settings.expiry] Seconds a vote is remembered for.
 * @return {Promise<void>} Resolves once the settings are saved.
 */
async function configure( page, settings = {} ) {
	const {
		check = DEFAULTS.check,
		allow = DEFAULTS.allow,
		expiry = DEFAULTS.expiry,
	} = settings;

	await openSettings( page );

	await page.locator( '#poll_check_method' ).selectOption( check );
	await page.locator( '#poll_allowtovote' ).selectOption( allow );
	await page.locator( '#poll_cookielog_expiry' ).fill( expiry );

	await saveSettings( page );
}

/**
 * A question no earlier run can have used.
 *
 * The Manage screen lists every poll ever added, so two runs of the same test
 * leave two rows saying the same thing and an assertion counting them fails the
 * second time for no reason worth chasing.
 *
 * @param {string} base What the question should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueQuestion( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

/**
 * Publish a post carrying one poll's shortcode.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {number} pollId       Poll to embed.
 * @param {string} title        Post title.
 * @return {Promise<Object>} The created post.
 */
function createPollPost( requestUtils, pollId, title ) {
	return requestUtils.createPost( {
		title,
		content: `[poll id="${ pollId }"]`,
		status: 'publish',
	} );
}

/**
 * The poll on the page, whichever of its two states it is in.
 *
 * @param {import('@playwright/test').Page} page   Page showing the poll.
 * @param {number}                          pollId Poll to locate.
 * @return {import('@playwright/test').Locator} The poll container.
 */
function poll( page, pollId ) {
	return page.locator( `#polls-${ pollId }` );
}

/**
 * Choose answers and press Vote.
 *
 * The radio and checkbox inputs are visible controls here rather than hidden
 * behind a label, so a visitor clicks them and so does this.
 *
 * @param {import('@playwright/test').Page} page    Page showing the poll.
 * @param {number}                          pollId  Poll to vote in.
 * @param {number[]}                        choices Which answers, by position.
 * @return {Promise<void>} Resolves once the request has been sent.
 */
async function vote( page, pollId, choices = [ 0 ] ) {
	const inputs = poll( page, pollId ).locator( 'li input[type="radio"], li input[type="checkbox"]' );

	for ( const choice of choices ) {
		await inputs.nth( choice ).check();
	}

	await poll( page, pollId ).locator( '[data-poll-action="vote"]' ).click();
}

/**
 * Wait for the results to be on screen in place of the form.
 *
 * The bar is the thing only a result has, and the absence of the inputs is the
 * thing only a form has, so both are checked: markup that somehow held both
 * would be neither state.
 *
 * Attached rather than visible: a poll with no votes has zero-width bars, and a
 * zero-size element counts as hidden to Playwright. The results have still
 * rendered -- the bar is in the DOM and the vote button is gone -- which is the
 * state this waits for, whatever the counts are.
 *
 * @param {import('@playwright/test').Page} page   Page showing the poll.
 * @param {number}                          pollId Poll to wait for.
 * @return {Promise<void>} Resolves once the results have swapped in.
 */
async function expectResults( page, pollId ) {
	await expect( poll( page, pollId ).locator( '[data-poll-action="vote"]' ) ).toHaveCount( 0, {
		timeout: 15_000,
	} );
	await expect( poll( page, pollId ).locator( '.wp-polls-bar-fill' ).first() ).toBeAttached();
}

/**
 * Mark the page, so a later check can tell whether it was reloaded.
 *
 * A poll that answered by navigating would pass every assertion about the
 * result markup and still be the wrong plugin: swapping the results in without
 * leaving the page is the whole point of the AJAX vote.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the mark is set.
 */
function markPage( page ) {
	return page.evaluate( () => {
		window.__wpPollsSamePage = true;
	} );
}

/**
 * Whether the page is the one that was marked.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<boolean>} True when there has been no navigation.
 */
function pageWasNotReloaded( page ) {
	return page.evaluate( () => true === window.__wpPollsSamePage );
}

module.exports = {
	ADD_URL,
	ALLOW,
	CHECK,
	DEFAULTS,
	MANAGE_URL,
	SETTINGS_URL,
	TEMPLATES_URL,
	configure,
	createPoll,
	createPollPost,
	deleteAllPolls,
	expectResults,
	markPage,
	openSettings,
	pageWasNotReloaded,
	poll,
	pollAnswers,
	pollColumn,
	runPollsCron,
	saveSettings,
	uniqueQuestion,
	vote,
	wpEval,
};
