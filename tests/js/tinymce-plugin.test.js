/**
 * Tests for the TinyMCE "Insert Poll" button.
 *
 * The plugin registers itself against TinyMCE's own API rather than the DOM, so
 * the editor is stubbed and the registered command invoked directly. The guard
 * on the entered id is the interesting part: it used to test the prompt result
 * against null, which it never is, so the check did nothing.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

let command;
let editor;

beforeAll( () => {
	// Captured when the script registers itself.
	let register;

	window.tinymce = {
		PluginManager: {
			add: ( name, callback ) => {
				register = { name, callback };
			},
		},
		translate: ( text ) => text,
	};
	window.tinyMCE = { activeEditor: { execCommand: vi.fn() } };

	loadScript( 'tinymce/plugins/polls/plugin.js' );

	expect( register.name ).toBe( 'polls' );

	editor = {
		addCommand: vi.fn( ( name, callback ) => {
			command = { name, callback };
		} ),
		addButton: vi.fn(),
		insertContent: vi.fn(),
	};
	register.callback( editor );
} );

beforeEach( () => {
	editor.insertContent.mockClear();
} );

describe( 'registration', () => {
	it( 'registers the insert command and the toolbar button', () => {
		expect( command.name ).toBe( 'WP-Polls-Insert_Poll' );
		expect( editor.addButton ).toHaveBeenCalledWith(
			'polls',
			expect.objectContaining( { text: false } ),
		);
	} );

	it( 'runs the command from the button', () => {
		const [ , config ] = editor.addButton.mock.calls[ 0 ];
		config.onclick();

		expect( window.tinyMCE.activeEditor.execCommand ).toHaveBeenCalledWith(
			'WP-Polls-Insert_Poll',
		);
	} );
} );

describe( 'entering a poll id', () => {
	it( 'inserts the shortcode for a numeric id', () => {
		window.prompt = vi.fn( () => '7' );

		command.callback();

		expect( editor.insertContent ).toHaveBeenCalledWith( '[poll id="7"]' );
	} );

	it( 'trims surrounding whitespace', () => {
		window.prompt = vi.fn( () => '  12  ' );

		command.callback();

		expect( editor.insertContent ).toHaveBeenCalledWith( '[poll id="12"]' );
	} );

	it( 'accepts the -1 sentinel', () => {
		window.prompt = vi.fn( () => '-1' );

		command.callback();

		expect( editor.insertContent ).toHaveBeenCalledWith( '[poll id="-1"]' );
	} );

	it( 'rejects an id below -1', () => {
		window.prompt = vi.fn( () => '-5' );

		command.callback();

		expect( editor.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'inserts nothing when the prompt is cancelled', () => {
		window.prompt = vi.fn( () => null );

		command.callback();

		expect( editor.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'inserts nothing for an empty entry', () => {
		window.prompt = vi.fn( () => '' );

		command.callback();

		expect( editor.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'asks again until the entry is numeric', () => {
		window.prompt = vi
			.fn()
			.mockReturnValueOnce( 'abc' )
			.mockReturnValueOnce( 'still not a number' )
			.mockReturnValueOnce( '3' );

		command.callback();

		expect( window.prompt ).toHaveBeenCalledTimes( 3 );
		expect( editor.insertContent ).toHaveBeenCalledWith( '[poll id="3"]' );
	} );
} );
