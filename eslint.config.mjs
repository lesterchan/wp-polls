/**
 * WordPress JS coding standards for WP-Polls.
 *
 * "recommended-with-formatting" uses native ESLint formatting rules rather
 * than delegating to Prettier, so no Prettier install is needed.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [ '**/node_modules/**', '**/vendor/**', '**/*.min.js' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			globals: {
				...globals.browser,
				// Localised into the page by wp_localize_script().
				wpPollsL10n: 'readonly',
				wpPollsAdminL10n: 'readonly',
			},
		},
		rules: {
			// The plugin confirms destructive admin actions and reports vote
			// validation with the browser's own dialogs. Replacing them means
			// building a modal, which is a UX change rather than a lint fix.
			'no-alert': 'off',
		},
		settings: {
			react: { version: '18.0' },
		},
	},
	{
		// The Classic Editor button runs inside the editor iframe against
		// TinyMCE's own API, which is a global the editor puts there.
		files: [ 'tinymce/**/*.js' ],
		languageOptions: {
			globals: {
				tinyMCE: 'readonly',
				tinymce: 'readonly',
			},
		},
	},
	{
		files: [ 'tests/js/**/*.test.js' ],
		languageOptions: {
			globals: {
				...globals.node,
			},
		},
	},
	{
		// The Playwright suite is CommonJS and runs under Node, not in a page:
		// it requires its helpers and exports nothing to a browser.
		files: [ 'tests/e2e/**/*.js', 'playwright.config.js' ],
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				...globals.node,
			},
		},
	},
];
