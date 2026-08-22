<?php
/**
 * The wp-admin side: menu, assets, editor buttons and the admin endpoint.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything that only runs inside wp-admin.
 */
class WP_Polls_Admin {

	/**
	 * Slug of the top level menu, and of the screen it opens on.
	 *
	 * @var string
	 */
	const PAGE = 'wp-polls';

	/**
	 * Capability every screen and every write path checks, before the filter.
	 *
	 * Deliberately not manage_options. WP-Polls has shipped its own capability
	 * for years, and it exists so a site can hand the polls to somebody without
	 * handing over the rest of wp-admin. Removing it would leave such a site two
	 * choices, both worse: stop delegating, or grant manage_options and open
	 * every settings screen on the installation.
	 *
	 * **It gates the Settings tab as well as the data screens, which §2.7 asks
	 * to be argued rather than assumed.** The argument is that these settings
	 * govern exactly the data the capability already covers -- what a poll looks
	 * like, how votes are checked, how long a voter is remembered. Somebody
	 * trusted to add and close polls but not to set the default poll template is
	 * a distinction with nothing behind it. wp-dbmanager is the other side of
	 * the same clause and reaches the opposite arrangement for a good reason:
	 * its settings screen sets the mysqldump path, so it gates *higher* than
	 * manage_options rather than lower.
	 *
	 * Granting and revoking both go through capability(), never this constant --
	 * see WP_Polls_Install::add_capability() for what that stops.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_polls';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		foreach ( array( 'post-new.php', 'post.php', 'page-new.php', 'page.php' ) as $screen ) {
			add_action( 'admin_footer-' . $screen, array( __CLASS__, 'poll_footer_admin' ) );
		}
		add_action( 'init', array( __CLASS__, 'poll_tinymce_addbuttons' ) );
		add_action( 'wp_ajax_polls-admin', array( __CLASS__, 'ajax_manage' ) );
	}

	/**
	 * The page slugs this plugin registers, in menu order.
	 *
	 * The logs screen is absent on purpose: WP_Polls_Screen_Logs renders inside
	 * WP_Polls_Screen_Manage under mode=logs, so it has no slug of its own.
	 *
	 * @return array
	 */
	public static function page_slugs() {
		return array( self::PAGE, self::PAGE . '-add', WP_Polls_Settings::PAGE );
	}

	/**
	 * The hook suffix WordPress reports for one of this plugin's page slugs.
	 *
	 * Derived rather than captured from add_submenu_page(), so it can be asked
	 * for before 'admin_menu' has run - the notice on 'admin_notices' and the
	 * enqueue on 'admin_enqueue_scripts' both need it, and so do the tests,
	 * which never build a menu at all. WordPress names a submenu screen after
	 * the sanitised title of its parent menu, which is why that title is read
	 * from one place here rather than written out twice.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function hook_suffix( $slug ) {
		if ( self::PAGE === $slug ) {
			return 'toplevel_page_' . self::PAGE;
		}

		return sanitize_title( self::menu_title() ) . '_page_' . $slug;
	}

	/**
	 * The top level menu's title, which also names every submenu's hook suffix.
	 *
	 * The plugin's own name, per STANDARDS.md 4.1: a site owner installs
	 * WP-Polls and sees WP-Polls on the Plugins screen, so that is the string to
	 * look for in the sidebar. The submenus underneath still say what they are --
	 * Manage Polls, Add Poll, Settings.
	 *
	 * Changing this moves every submenu hook suffix with it, from polls_page_* to
	 * wp-polls_page_*, because WordPress derives that prefix by running the menu
	 * title through sanitize_title(). That is why hook_suffix() derives it here
	 * too rather than hardcoding the string.
	 *
	 * @return string
	 */
	public static function menu_title() {
		return __( 'WP-Polls', 'wp-polls' );
	}

	/**
	 * The hook suffixes WordPress reports for this plugin's own admin screens.
	 *
	 * @return array
	 */
	public static function admin_pages() {
		return array_map( array( __CLASS__, 'hook_suffix' ), self::page_slugs() );
	}

	// Function: Poll Administration Menu.

	/**
	 * Poll menu.
	 *
	 * @return mixed
	 */
	public static function add_page() {
		$capability = self::capability();
		$menu_title = self::menu_title();

		add_menu_page( $menu_title, $menu_title, $capability, self::PAGE, array( __CLASS__, 'render_manage' ), 'dashicons-chart-bar' );

		add_submenu_page( self::PAGE, __( 'Manage Polls', 'wp-polls' ), __( 'Manage Polls', 'wp-polls' ), $capability, self::PAGE, array( __CLASS__, 'render_manage' ) );
		add_submenu_page( self::PAGE, __( 'Add Poll', 'wp-polls' ), __( 'Add Poll', 'wp-polls' ), $capability, self::PAGE . '-add', array( __CLASS__, 'render_add' ) );
		add_submenu_page( self::PAGE, __( 'Poll Settings', 'wp-polls' ), __( 'Settings', 'wp-polls' ), $capability, WP_Polls_Settings::PAGE, array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * The capability every screen and every write path checks.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string
	 */
	public static function capability( $context = 'screen' ) {
		/**
		 * Filters the capability required to manage polls.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    What it is being checked for.
		 */
		return apply_filters( 'wp_polls_capability', self::CAPABILITY, $context );
	}

	/**
	 * Render the Manage Polls screen.
	 *
	 * @return void
	 */
	public static function render_manage() {
		// Loaded here rather than from the plugin bootstrap: the list table
		// extends WP_List_Table, which only exists inside wp-admin.
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-list-table.php';
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-screen-logs.php';
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-screen-manage.php';
		WP_Polls_Screen_Manage::render();
	}

	/**
	 * Render the Add Poll screen.
	 *
	 * @return void
	 */
	public static function render_add() {
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-screen-add.php';
		WP_Polls_Screen_Add::render();
	}

	/**
	 * Render the settings screen, whichever tab was asked for.
	 *
	 * @return void
	 */
	public static function render_settings() {
		require_once WP_POLLS_DIR . 'includes/class-wp-polls-screen-settings.php';
		WP_Polls_Screen_Settings::render();
	}

	/**
	 * Print the status messages a screen collected, inside the box the admin
	 * JavaScript writes its own responses into.
	 *
	 * The box is a bare container on purpose. Everything that goes in it is
	 * already a `.notice` carrying its own success or error colour, so making
	 * the container a notice as well draws a second bar down the left of the
	 * first one - and paints an error message inside a green box.
	 *
	 * @param string $text Concatenated notice markup, empty when there is
	 *                     nothing to report yet.
	 * @return void
	 */
	public static function messages( $text ) {
		$text = (string) $text;

		// The new-poll message offers its shortcode in a readonly input, which
		// wp_kses_post() strips: input is not a tag posts are allowed to use.
		$allowed          = wp_kses_allowed_html( 'post' );
		$allowed['input'] = array(
			'type'     => true,
			'value'    => true,
			'readonly' => true,
			'size'     => true,
			'class'    => true,
		);

		printf(
			'<div id="message" class="%1$s">%2$s</div>',
			esc_attr( '' === $text ? 'hidden' : '' ),
			wp_kses( removeslashes( $text ), $allowed )
		);
	}

	// Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin.

	/**
	 * Poll scripts admin.
	 *
	 * @param mixed $hook_suffix Value.
	 *
	 * @return mixed
	 */
	public static function enqueue( $hook_suffix ) {
		if ( in_array( $hook_suffix, self::admin_pages(), true ) ) {
			wp_enqueue_style( 'wp-polls-admin', WP_POLLS_URL . 'css/wp-polls-admin.css', array(), WP_POLLS_VERSION );
			// The Settings tab previews the bar using the real front end
			// markup, so it loads the real front end rules rather than keeping a
			// second copy of them in the admin stylesheet to drift out of sync.
			// Always the plugin's own copy: a theme override of wp-polls.css
			// would make the preview show the theme's bar, not the setting.
			if ( self::hook_suffix( WP_Polls_Settings::PAGE ) === $hook_suffix ) {
				wp_enqueue_style( 'wp-polls', WP_POLLS_URL . 'css/wp-polls.css', array(), WP_POLLS_VERSION );
				wp_add_inline_style( 'wp-polls-admin', self::pollbar_css() );
			}
			wp_enqueue_script( 'wp-polls-admin', WP_POLLS_URL . 'js/wp-polls-admin.js', array(), WP_POLLS_VERSION, true );
			wp_localize_script(
				'wp-polls-admin',
				'wpPollsAdminL10n',
				array(
					'admin_ajax_url'                 => admin_url( 'admin-ajax.php' ),
					'text_delete_poll'               => __( 'Delete Poll', 'wp-polls' ),
					'text_no_poll_logs'              => __( 'No poll logs available.', 'wp-polls' ),
					'text_delete_all_logs'           => __( 'Delete All Logs', 'wp-polls' ),
					'text_checkbox_delete_all_logs'  => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs.', 'wp-polls' ),
					'text_delete_poll_logs'          => __( 'Delete Logs For This Poll Only', 'wp-polls' ),
					'text_checkbox_delete_poll_logs' => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs for this poll ONLY.', 'wp-polls' ),
					'text_delete_poll_ans'           => __( 'Delete Poll Answer', 'wp-polls' ),
					'text_open_poll'                 => __( 'Open Poll', 'wp-polls' ),
					'text_close_poll'                => __( 'Close Poll', 'wp-polls' ),
					'text_answer'                    => __( 'Answer', 'wp-polls' ),
					'text_remove_poll_answer'        => __( 'Remove', 'wp-polls' ),
					// The settings screen previews the poll bar and restores
					// default templates from these, so both come from the same
					// place the front end and the activation routine read them
					// from rather than being written out a second time.
					'pollbar_images'                 => array_combine(
						WP_Polls_Settings::bar_styles(),
						array_map( array( 'WP_Polls', 'bar_image' ), WP_Polls_Settings::bar_styles() )
					),
					'template_defaults'              => WP_Polls_Template::defaults(),
				)
			);
		}
	}

	/**
	 * The configured poll bar, as rules rather than as style attributes.
	 *
	 * The preview and the two style swatches are the real front end markup, so
	 * all they need is the four custom properties the front end stylesheet
	 * reads. Emitted as a stylesheet because a screen in wp-admin carries no
	 * inline style attributes, and because the swatches differ only by which
	 * background image their style produces - one rule per style says that once
	 * instead of once per swatch.
	 *
	 * @return string
	 */
	public static function pollbar_css() {
		$bar        = WP_Polls_Options::get( 'bar' );
		$background = WP_Polls::sanitize_bar_color( $bar['background'] );
		$border     = WP_Polls::sanitize_bar_color( $bar['border'] );

		$css = sprintf(
			'#wp-polls-pollbar { --wp-polls-bar-height: %1$dpx; --wp-polls-bar-background: #%2$s; --wp-polls-bar-border: #%3$s; --wp-polls-bar-image: %4$s; }' . "\n",
			(int) $bar['height'],
			$background,
			$border,
			WP_Polls::bar_image( $bar['style'] )
		);

		$css .= sprintf(
			'.wp-polls-swatch { --wp-polls-bar-background: #%1$s; --wp-polls-bar-border: #%2$s; }' . "\n",
			$background,
			$border
		);

		foreach ( WP_Polls_Settings::bar_styles() as $style ) {
			$css .= sprintf(
				'.wp-polls-swatch[data-poll-style="%1$s"] { --wp-polls-bar-image: %2$s; }' . "\n",
				$style,
				WP_Polls::bar_image( $style )
			);
		}

		return $css;
	}

	// Function: Displays Polls Footer In WP-Admin.

	/**
	 * Poll footer admin.
	 *
	 * @return mixed
	 */
	public static function poll_footer_admin() {
		?>
		<script type="text/javascript">
			QTags.addButton('ed_wp_polls', '<?php echo esc_js( __( 'Poll', 'wp-polls' ) ); ?>', function() {
				var poll_id = (prompt('<?php echo esc_js( __( 'Enter Poll ID', 'wp-polls' ) ); ?>') || '').trim();
				while(isNaN(poll_id)) {
					poll_id = (prompt("<?php echo esc_js( __( 'Error: Poll ID must be numeric', 'wp-polls' ) ); ?>\n\n<?php echo esc_js( __( 'Please enter Poll ID again', 'wp-polls' ) ); ?>") || '').trim();
				}
				if (poll_id !== "" && Number(poll_id) >= -1) {
					QTags.insertContent('[poll id="' + poll_id + '"]');
				}
			});
		</script>
		<?php
	}

	// Function: Add Quick Tag For Poll In TinyMCE >= WordPress 2.5.

	/**
	 * Poll tinymce addbuttons.
	 *
	 * @return mixed
	 */
	public static function poll_tinymce_addbuttons() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( get_user_option( 'rich_editing' ) === 'true' ) {
			add_filter( 'mce_external_plugins', array( __CLASS__, 'poll_tinymce_addplugin' ) );
			add_filter( 'mce_buttons', array( __CLASS__, 'poll_tinymce_registerbutton' ) );
			add_filter( 'wp_mce_translation', array( __CLASS__, 'poll_tinymce_translation' ) );
		}
	}

	/**
	 * Poll tinymce registerbutton.
	 *
	 * @param mixed $buttons Value.
	 *
	 * @return mixed
	 */
	public static function poll_tinymce_registerbutton( $buttons ) {
		array_push( $buttons, 'separator', 'polls' );
		return $buttons;
	}

	/**
	 * Poll tinymce addplugin.
	 *
	 * @param mixed $plugin_array Value.
	 *
	 * @return mixed
	 */
	public static function poll_tinymce_addplugin( $plugin_array ) {
		if ( WP_DEBUG ) {
			$plugin_array['polls'] = WP_POLLS_URL . 'tinymce/plugins/polls/plugin.js?v=' . WP_POLLS_VERSION;
		} else {
			$plugin_array['polls'] = WP_POLLS_URL . 'tinymce/plugins/polls/plugin.min.js?v=' . WP_POLLS_VERSION;
		}
		return $plugin_array;
	}

	/**
	 * Poll tinymce translation.
	 *
	 * @param mixed $mce_translation Value.
	 *
	 * @return mixed
	 */
	public static function poll_tinymce_translation( $mce_translation ) {
		$mce_translation['Enter Poll ID']                  = esc_js( __( 'Enter Poll ID', 'wp-polls' ) );
		$mce_translation['Error: Poll ID must be numeric'] = esc_js( __( 'Error: Poll ID must be numeric', 'wp-polls' ) );
		$mce_translation['Please enter Poll ID again']     = esc_js( __( 'Please enter Poll ID again', 'wp-polls' ) );
		$mce_translation['Insert Poll']                    = esc_js( __( 'Insert Poll', 'wp-polls' ) );
		return $mce_translation;
	}

	/**
	 * Edit Timestamp Options.
	 *
	 * @param mixed  $poll_timestamp Value.
	 * @param string $fieldname      Optional. Name prefix for the six selects.
	 * @param bool   $hidden         Optional. Whether the group starts hidden,
	 *                               which the checkbox beside it then toggles.
	 *
	 * @return mixed
	 */
	public static function poll_timestamp( $poll_timestamp, $fieldname = 'pollq_timestamp', $hidden = false ) {
		global $month;
		// core's own utility class rather than a style attribute, so wp-admin
		// keeps one way of hiding something.
		echo '<div id="' . esc_attr( $fieldname ) . '" class="' . esc_attr( $hidden ? 'hidden' : '' ) . '">' . "\n";
		$day = (int) gmdate( 'j', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_day" size="1">' . "\n";
		for ( $i = 1; $i <= 31; $i++ ) {
			if ( $day === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $i ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . "</option>\n";
			}
		}
		echo '</select>&nbsp;&nbsp;' . "\n";
		$month2 = (int) gmdate( 'n', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_month" size="1">' . "\n";
		for ( $i = 1; $i <= 12; $i++ ) {
			if ( $i < 10 ) {
				$ii = '0' . $i;
			} else {
				$ii = $i;
			}
			if ( $month2 === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $month[ $ii ] ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $month[ $ii ] ) . "</option>\n";
			}
		}
		echo '</select>&nbsp;&nbsp;' . "\n";
		$year = (int) gmdate( 'Y', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_year" size="1">' . "\n";
		for ( $i = 2000; $i <= ( $year + 10 ); $i++ ) {
			if ( $year === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $i ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . "</option>\n";
			}
		}
		echo '</select>&nbsp;@' . "\n";
		echo '<span dir="ltr">' . "\n";
		$hour = (int) gmdate( 'H', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_hour" size="1">' . "\n";
		for ( $i = 0; $i < 24; $i++ ) {
			if ( $hour === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $i ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . "</option>\n";
			}
		}
		echo '</select>&nbsp;:' . "\n";
		$minute = (int) gmdate( 'i', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_minute" size="1">' . "\n";
		for ( $i = 0; $i < 60; $i++ ) {
			if ( $minute === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $i ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . "</option>\n";
			}
		}

		echo '</select>&nbsp;:' . "\n";
		$second = (int) gmdate( 's', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_second" size="1">' . "\n";
		for ( $i = 0; $i <= 60; $i++ ) {
			if ( $second === $i ) {
				echo '<option value="' . esc_attr( $i ) . '" selected="selected">' . esc_html( $i ) . "</option>\n";
			} else {
				echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . "</option>\n";
			}
		}
		echo '</select>' . "\n";
		echo '</span>' . "\n";
		echo '</div>' . "\n";
	}

	// Function: Manage Polls.

	/**
	 * Manage poll.
	 *
	 * @return mixed
	 */
	public static function ajax_manage() {
		global $wpdb;

		// Every branch below is an administrative action. The per-action nonces are
		// only ever rendered on pages that already require 'manage_polls', but check
		// the capability itself rather than relying on the nonce for authorisation.
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( '', '', array( 'response' => null ) );
		}

		// Form Processing.
		// Each branch below calls check_ajax_referer() with its own action before
		// touching anything; the dispatch itself only reads which branch to take.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads which branch to take; each one calls check_ajax_referer() before touching anything.
		if ( isset( $_POST['action'] ) && 'polls-admin' === sanitize_key( wp_unslash( $_POST['action'] ) ) ) {
			if ( ! empty( $_POST['do'] ) ) {
				// Set Header.
				// Guarded: anything that has already emitted output - a stray newline
				// in another plugin - would otherwise turn this into a PHP warning
				// inside the response body.
				if ( ! headers_sent() ) {
					header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
				}

				// Decide What To Do.
				switch ( $_POST['do'] ) {
					// Delete Polls Logs.
					case __( 'Delete All Logs', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-polls-logs' );
						if ( isset( $_POST['delete_logs_yes'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['delete_logs_yes'] ) ) ) {
							$delete_logs = $wpdb->query( "DELETE FROM $wpdb->pollsip" );
							if ( $delete_logs ) {
								echo wp_kses_post( '<div class="notice notice-success inline"><p>' . __( 'All Polls Logs Have Been Deleted.', 'wp-polls' ) . '</p></div>' );
							} else {
								echo wp_kses_post( '<div class="notice notice-error inline"><p>' . __( 'An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls' ) . '</p></div>' );
							}
						}
						break;
					// Delete Poll Logs For Individual Poll.
					case __( 'Delete Logs For This Poll Only', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-poll-logs' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
						if ( isset( $_POST['delete_logs_yes'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['delete_logs_yes'] ) ) ) {
							$delete_logs = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
							if ( $delete_logs ) {
								/* translators: %s: The poll question. */
								echo wp_kses_post( '<div class="notice notice-success inline"><p>' . sprintf( __( 'All Logs For \'%s\' Has Been Deleted.', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
							} else {
								/* translators: %s: The poll question. */
								echo wp_kses_post( '<div class="notice notice-error inline"><p>' . sprintf( __( 'An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
							}
						}
						break;
					// Delete Poll's Answer.
					case __( 'Delete Poll Answer', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-poll-answer' );
						$pollq_id             = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$polla_aid            = isset( $_POST['polla_aid'] ) ? (int) $_POST['polla_aid'] : 0;
						$poll_answers         = $wpdb->get_row( $wpdb->prepare( "SELECT polla_votes, polla_answers FROM $wpdb->pollsa WHERE polla_aid = %d AND polla_qid = %d", $polla_aid, $pollq_id ) );
						$polla_votes          = (int) $poll_answers->polla_votes;
						$polla_answers        = wp_kses_post( removeslashes( trim( $poll_answers->polla_answers ) ) );
						$delete_polla_answers = $wpdb->delete(
							$wpdb->pollsa,
							array(
								'polla_aid' => $polla_aid,
								'polla_qid' => $pollq_id,
							),
							array( '%d', '%d' )
						);
						$delete_pollip        = $wpdb->delete(
							$wpdb->pollsip,
							array(
								'pollip_qid' => $pollq_id,
								'pollip_aid' => $polla_aid,
							),
							array( '%d', '%d' )
						);
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE $wpdb->pollsq SET pollq_totalvotes = ( pollq_totalvotes - %d ) WHERE pollq_id = %d",
								$polla_votes,
								$pollq_id
							)
						);
						if ( $delete_polla_answers ) {
							/* translators: %s: The poll answer. */
							echo wp_kses_post( '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll Answer \'%s\' Deleted Successfully.', 'wp-polls' ), $polla_answers ) . '</p></div>' );
						} else {
							/* translators: %s: The poll answer. */
							echo wp_kses_post( '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error In Deleting Poll Answer \'%s\'.', 'wp-polls' ), $polla_answers ) . '</p></div>' );
						}
						break;
					// Open Poll.
					case __( 'Open Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_open-poll' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$poll           = WP_Polls_Poll::get( $pollq_id );
						$pollq_question = $poll ? $poll->pollq_question : '';
						$open_poll      = WP_Polls_Poll::open( $pollq_id );
						if ( $open_poll ) {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%s\' Is Now Opened', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						} else {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error Opening Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						}
						break;
					// Close Poll.
					case __( 'Close Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_close-poll' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$poll           = WP_Polls_Poll::get( $pollq_id );
						$pollq_question = $poll ? $poll->pollq_question : '';
						$close_poll     = WP_Polls_Poll::close( $pollq_id );
						if ( $close_poll ) {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%s\' Is Now Closed', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						} else {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error Closing Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						}
						break;
					// Delete Poll.
					case __( 'Delete Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-poll' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$poll           = WP_Polls_Poll::get( $pollq_id );
						$pollq_question = $poll ? $poll->pollq_question : '';
						// Deleting the answers and the vote log, recomputing the
						// stored latest-poll id and firing wp_polls_delete_poll all
						// happen inside delete(). Three of the four results this
						// used to collect were assigned and never read, one of them
						// a query for `poll_latestpoll` -- a row the 3.0.0 migration
						// folded into the options array and deleted, so it had been
						// reading nothing for a whole major version.
						$delete_poll_question = WP_Polls_Poll::delete( $pollq_id );
						if ( ! $delete_poll_question ) {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-error inline"><p>' . sprintf( __( 'Error In Deleting Poll \'%s\' Question', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						}
						if ( empty( $text ) ) {
							/* translators: %s: The poll question. */
							echo wp_kses_post( '<div class="notice notice-success inline"><p>' . sprintf( __( 'Poll \'%s\' Deleted Successfully', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p></div>' );
						}
						break;
				}
				wp_die( '', '', array( 'response' => null ) );
			}
		}
	}
}
