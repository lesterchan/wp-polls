<?php
/**
 * Block registration.
 *
 * @package WP-Polls
 */

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The blocks are added beside the shortcodes, never in place of them.**
 * `[poll]` and `[page_polls]` stay registered, documented and supported, and
 * nothing here deprecates either. This plugin has shipped for fifteen years and
 * those shortcodes sit in an unknowable number of published posts; a block that
 * replaced them would break every one of those pages on update.
 *
 * Both blocks are dynamic. Their `save` returns null in JavaScript, so what
 * lands in post_content is the block comment and its attributes and no markup
 * at all -- every view re-renders through the callbacks below. That is what
 * lets a block and a shortcode share one renderer: the markup is decided once,
 * at render time, for both of them.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over `WP_Polls_Display`, which is where the rendering lives.
 * Routing the block through `do_shortcode()` would make it inherit the
 * shortcode's attribute parsing -- including the positional `[poll=1]` form,
 * which a block has no way to produce -- and would break the block outright the
 * day anybody unregistered the shortcode.
 *
 * **One class registers both blocks**, rather than a class per block: a class
 * whose entire body is a single `register_block_type_from_metadata()` call
 * would be a file per block for no gain.
 */
class WP_Polls_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title, attributes
	 * and script handle all come out of the `block.json` copied there by the
	 * build, so this class never restates any of them.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'poll'       => array( __CLASS__, 'render_poll' ),
			'page-polls' => array( __CLASS__, 'render_page_polls' ),
		);
	}

	/**
	 * Hooks block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcodes working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_POLLS_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Renders the `wp-polls/poll` block.
	 *
	 * The attributes arrive typed, because block.json declares them: `id` is
	 * already an integer here where the shortcode's is the string a user typed.
	 * Both are cast in the shared renderer, so the two entry points cannot
	 * disagree about what `id="0"` means.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_poll( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'id'   => 0,
				'type' => 'vote',
			)
		);

		return WP_Polls_Display::render_poll( $attributes['id'], $attributes['type'] );
	}

	/**
	 * Renders the `wp-polls/page-polls` block.
	 *
	 * Takes no attributes, because `[page_polls]` takes none either.
	 *
	 * @return string
	 */
	public static function render_page_polls() {
		return WP_Polls_Display::polls_archive();
	}
}
