<?php
/**
 * Tests for the blocks.
 *
 * @package WP-Polls
 */

/**
 * The blocks, and the promise that they are an addition rather than a
 * replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is
 * one line -- but the three things a later change could quietly break:
 *
 * * the shortcodes still work, because they sit in published posts everywhere;
 * * the block and the shortcode render the *same* markup, because they are
 *   meant to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops the shortcode's parsing quirks leaking into the block.
 */
class WP_Polls_Blocks_Test extends WP_Polls_TestCase {

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshots the global state these tests deliberately break.
	 *
	 * Two tests below unregister a shortcode or a block on purpose, to prove
	 * neither entry point is implemented in terms of the other. Both registries
	 * are process-global and WP_UnitTestCase restores neither, so without this
	 * the first such test silently disarms every test that runs after it -- and
	 * they fail with `[poll id="1"]` rendering as literal text, which reads as
	 * a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_blocks();
	}

	/**
	 * Puts both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_blocks();

		parent::tear_down();
	}

	/**
	 * Returns the block registry to exactly the two registered blocks.
	 *
	 * Unregisters before registering rather than registering conditionally:
	 * the plugin has already registered both on `init` by the time any test
	 * runs, and registering a second time is a doing_it_wrong notice that the
	 * suite fails on.
	 *
	 * @return void
	 */
	private function restore_blocks() {
		foreach ( array( 'wp-polls/poll', 'wp-polls/page-polls' ) as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		WP_Polls_Blocks::register();
	}

	// --- registration ----------------------------------------------------

	/**
	 * Both blocks register, under the prefixed names.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a
	 * collision there is survivable and visible. A block name is written into
	 * post_content and stays there for the life of the post, so a collision
	 * would render another plugin's block inside somebody's published posts.
	 *
	 * @return void
	 */
	public function test_both_blocks_register_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'wp-polls/poll' ), 'The poll block registers.' );
		$this->assertTrue( $registry->is_registered( 'wp-polls/page-polls' ), 'The archive block registers.' );

		$this->assertFalse( $registry->is_registered( 'polls/poll' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The blocks are dynamic, so each carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that neither does.
	 *
	 * @return void
	 */
	public function test_the_blocks_are_dynamic() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertIsCallable( $registry->get_registered( 'wp-polls/poll' )->render_callback, 'The poll block renders server-side.' );
		$this->assertIsCallable( $registry->get_registered( 'wp-polls/page-polls' )->render_callback, 'So does the archive block.' );
	}

	/**
	 * The attributes come from block.json rather than from PHP.
	 *
	 * @return void
	 */
	public function test_the_poll_block_declares_its_attributes() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'wp-polls/poll' )->attributes;

		$this->assertArrayHasKey( 'id', $attributes, 'The poll block takes an id.' );
		$this->assertArrayHasKey( 'type', $attributes, 'And a type.' );
		$this->assertSame( 'number', $attributes['id']['type'], 'The id arrives typed, unlike a shortcode attribute.' );
	}

	// --- the shortcodes survive ------------------------------------------

	/**
	 * Adding the blocks did not unregister either shortcode.
	 *
	 * If this ever fails, the blocks have stopped being an addition and become
	 * a replacement, and every published post holding `[poll]` renders literal
	 * text.
	 *
	 * @return void
	 */
	public function test_both_shortcodes_are_still_registered() {
		$this->assertTrue( shortcode_exists( 'poll' ), 'The poll shortcode survives the block.' );
		$this->assertTrue( shortcode_exists( 'page_polls' ), 'And so does the archive shortcode.' );
	}

	/**
	 * The legacy positional form still renders.
	 *
	 * `[poll=1]` is shortcode syntax with no block equivalent, so it stays in
	 * the shortcode callback rather than moving into the shared renderer.
	 *
	 * @return void
	 */
	public function test_the_legacy_positional_shortcode_still_renders() {
		$poll_id = $this->make_poll();

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', do_shortcode( '[poll=' . $poll_id . ']' ), 'The legacy form still renders its poll.' );
	}

	// --- the block and the shortcode agree -------------------------------

	/**
	 * The block and the shortcode render the same poll identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_render_the_same_markup() {
		$poll_id = $this->make_poll();

		$block     = WP_Polls_Blocks::render_poll( array( 'id' => $poll_id ) );
		$shortcode = do_shortcode( '[poll id="' . $poll_id . '"]' );

		$this->assertNotSame( '', $block, 'The block rendered something.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * The same holds for the result type.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_agree_on_the_result_type() {
		$poll_id = $this->make_poll( array(), array( array( 'A', 1 ) ) );

		$block     = WP_Polls_Blocks::render_poll(
			array(
				'id'   => $poll_id,
				'type' => 'result',
			)
		);
		$shortcode = do_shortcode( '[poll id="' . $poll_id . '" type="result"]' );

		$this->assertStringContainsString( 'wp-polls-bar', $block, 'The result type renders bars.' );
		$this->assertSame( $shortcode, $block, 'And the two entry points agree.' );
	}

	/**
	 * And for the archive.
	 *
	 * @return void
	 */
	public function test_the_archive_block_and_shortcode_agree() {
		$this->make_poll();

		$block     = WP_Polls_Blocks::render_page_polls();
		$shortcode = do_shortcode( '[page_polls]' );

		$this->assertStringContainsString( 'wp-polls-archive', $block, 'The archive block renders the archive.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * An id of zero means the current poll, in both entry points.
	 *
	 * Zero is the block's default and an empty `[poll]`'s default, so the two
	 * have to mean the same thing or an empty block and an empty shortcode
	 * render different polls.
	 *
	 * @return void
	 */
	public function test_a_zero_id_means_the_current_poll_in_both() {
		$this->make_poll();

		$this->assertSame( do_shortcode( '[poll]' ), WP_Polls_Blocks::render_poll( array() ), 'An attributeless block and an attributeless shortcode render the same poll.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The block does not render by running the shortcode.
	 *
	 * Routing a block through do_shortcode() would make it inherit shortcode
	 * parsing it has no way to produce, and would break it outright the day
	 * anybody unregistered the shortcode. So: unregister the shortcodes, and
	 * assert the blocks carry on rendering.
	 *
	 * @return void
	 */
	public function test_the_blocks_render_with_the_shortcodes_unregistered() {
		$poll_id = $this->make_poll();

		remove_shortcode( 'poll' );
		remove_shortcode( 'page_polls' );

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', WP_Polls_Blocks::render_poll( array( 'id' => $poll_id ) ), 'The poll block does not need the shortcode.' );
		$this->assertStringContainsString( 'wp-polls-archive', WP_Polls_Blocks::render_page_polls(), 'Nor does the archive block.' );
	}

	/**
	 * The shortcode does not render by running the block.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcodes_render_with_the_blocks_unregistered() {
		$poll_id = $this->make_poll();

		unregister_block_type( 'wp-polls/poll' );
		unregister_block_type( 'wp-polls/page-polls' );

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', do_shortcode( '[poll id="' . $poll_id . '"]' ), 'The poll shortcode does not need the block.' );
		$this->assertStringContainsString( 'wp-polls-archive', do_shortcode( '[page_polls]' ), 'Nor does the archive shortcode.' );
	}

	// --- the shared renderer ---------------------------------------------

	/**
	 * An unrecognised type renders nothing, as it did before the split.
	 *
	 * The block cannot reach this branch, its type being an enum of two. The
	 * shortcode can, and this pins that the refactor did not quietly start
	 * rendering a voting form for `[poll type="banana"]`.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_type_renders_nothing() {
		$poll_id = $this->make_poll();

		$this->assertSame( '', do_shortcode( '[poll id="' . $poll_id . '" type="banana"]' ), 'An unknown type renders nothing.' );
	}

	/**
	 * In a feed, both entry points return the note instead of a form.
	 *
	 * The guard lives in the shared renderer rather than in the shortcode
	 * precisely so the block gets it too: a dynamic block renders in a feed,
	 * and a voting form in an RSS reader votes nowhere.
	 *
	 * @return void
	 */
	public function test_a_feed_gets_the_note_from_both_entry_points() {
		$poll_id = $this->make_poll();

		$this->go_to( '/?feed=rss2' );

		$this->assertTrue( is_feed(), 'The fixture really is a feed request.' );
		$this->assertStringContainsString( 'please visit the site', WP_Polls_Blocks::render_poll( array( 'id' => $poll_id ) ), 'The block returns the note.' );
		$this->assertStringContainsString( 'please visit the site', do_shortcode( '[poll id="' . $poll_id . '"]' ), 'And so does the shortcode.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the poll.
	 *
	 * The tests above call the callbacks directly, which does not prove the
	 * registration wired them to the name that gets saved into post_content.
	 * This goes through do_blocks(), the way a published post does.
	 *
	 * @return void
	 */
	public function test_a_saved_block_renders_through_the_block_parser() {
		$poll_id = $this->make_poll();

		$rendered = do_blocks( '<!-- wp:wp-polls/poll {"id":' . $poll_id . '} /-->' );

		$this->assertStringContainsString( 'id="polls-' . $poll_id . '"', $rendered, 'The saved block renders its poll.' );
	}
}
