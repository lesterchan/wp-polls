/**
 * Vitest configuration for WP-Polls.
 *
 * The two scripts are IIFEs that attach delegated listeners to `document` and
 * are loaded into a jsdom page, so the tests drive them the same way a visitor
 * does: build markup, dispatch a real event, assert on the DOM and on what was
 * sent to the endpoint.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
export default {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		restoreMocks: true,
	},
};
