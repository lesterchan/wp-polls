/**
 * The `wp-polls/page-polls` block.
 *
 * The archive the `[page_polls]` shortcode renders. It takes no attributes,
 * because the shortcode takes none either -- `poll_page_shortcode()` accepts
 * `$atts` only because add_shortcode() insists on passing it, and throws it
 * away.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the
 * only reason the two spellings differ.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

/**
 * The editor view.
 *
 * Capitalised and named rather than an `edit()` shorthand because useBlockProps
 * is a React hook, and the hook rules identify a component by that capital.
 *
 * @return {Element} The editor view.
 */
function Edit() {
	return (
		<div { ...useBlockProps() }>
			{ /* An archive lists every poll's voting form, so an interactive
			     preview would let the editor vote. */ }
			<div inert="">
				<ServerSideRender block={ metadata.name } />
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
