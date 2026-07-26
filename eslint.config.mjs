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
				pollsL10n: 'readonly',
				pollsAdminL10n: 'readonly',
			},
		},
		rules: {
			// Both scripts name every local in snake_case, matching the PHP side they
			// mirror. Renaming ~210 identifiers is pure churn with no user-visible
			// effect, and polls-admin-js.js has no test coverage at all, so a bulk
			// rename there could not be verified. Left as deliberate debt.
			camelcase: 'off',

			// The plugin confirms destructive admin actions and reports vote
			// validation with the native dialogs. Replacing them means building a
			// modal, which is a UX change, not a lint fix.
			'no-alert': 'off',
		},
		settings: {
			react: { version: '18.0' },
		},
	},
	{
		// The TinyMCE button runs inside the editor iframe against TinyMCE's own API.
		files: [ 'tinymce/**/*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
				tinyMCE: 'readonly',
				tinymce: 'readonly',
			},
		},
	},
];
