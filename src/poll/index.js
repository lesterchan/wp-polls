/**
 * The `wp-polls/poll` block.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That is what
 * makes the block and the `[poll]` shortcode able to share one renderer -- the
 * markup is decided in exactly one place, at render time, for both of them.
 *
 * The preview is core's ServerSideRender, which posts the attributes to
 * /wp/v2/block-renderer/wp-polls/poll and draws what the front end would draw.
 * That is also why this block registers no REST route of its own.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * The editor view.
 *
 * A named component with a capitalised name rather than an `edit()` shorthand
 * on the settings object: useBlockProps is a React hook, and the hook rules can
 * only tell a component from a plain function by that capital.
 *
 * The poll is chosen by id rather than from a dropdown of polls. A dropdown
 * would need a route listing every poll, and this plugin's namespace carries
 * only what its AJAX endpoint already carried -- reading a poll, reading a
 * result, and voting. Adding a list route to populate a select would be new
 * public surface invented for the convenience of one control.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor view.
 */
function Edit( { attributes, setAttributes } ) {
	const { id, type } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Poll', 'wp-polls' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Poll ID', 'wp-polls' ) }
						help={ __(
							'Zero shows the current poll, which is what an empty [poll] shortcode does.',
							'wp-polls',
						) }
						type="number"
						min={ 0 }
						value={ id }
						onChange={ ( value ) =>
							setAttributes( { id: parseInt( value, 10 ) || 0 } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Show', 'wp-polls' ) }
						value={ type }
						options={ [
							{
								label: __( 'Voting form', 'wp-polls' ),
								value: 'vote',
							},
							{
								label: __( 'Result', 'wp-polls' ),
								value: 'result',
							},
						] }
						onChange={ ( value ) => setAttributes( { type: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ /* Voting from inside the editor would cast a real vote, so the
				     preview is deliberately not interactive. */ }
				<div inert="">
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
