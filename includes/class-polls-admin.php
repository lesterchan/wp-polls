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
class Polls_Admin {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'poll_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'poll_scripts_admin' ) );
		foreach ( array( 'post-new.php', 'post.php', 'page-new.php', 'page.php' ) as $screen ) {
			add_action( 'admin_footer-' . $screen, array( __CLASS__, 'poll_footer_admin' ) );
		}
		add_action( 'init', array( __CLASS__, 'poll_tinymce_addbuttons' ) );
		add_action( 'wp_ajax_polls-admin', array( __CLASS__, 'manage_poll' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'polls_wp_stats' ) );
	}

	/**
	 * The hook suffixes WordPress reports for this plugin's own admin screens.
	 *
	 * The logs screen is absent on purpose: polls-logs.php renders inside
	 * polls-manager.php under mode=logs, so it arrives with the manager's
	 * hook suffix.
	 *
	 * @return array
	 */
	public static function admin_pages() {
		return array(
			WP_POLLS_SLUG . '/polls-manager.php',
			WP_POLLS_SLUG . '/polls-add.php',
			WP_POLLS_SLUG . '/polls-options.php',
			WP_POLLS_SLUG . '/polls-templates.php',
		);
	}

	// Function: Poll Administration Menu.

	/**
	 * Poll menu.
	 *
	 * @return mixed
	 */
	public static function poll_menu() {
		$manager = WP_POLLS_SLUG . '/polls-manager.php';

		add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', $manager, '', 'dashicons-chart-bar' );

		add_submenu_page( $manager, __( 'Manage Polls', 'wp-polls' ), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', $manager );
		add_submenu_page( $manager, __( 'Add Poll', 'wp-polls' ), __( 'Add Poll', 'wp-polls' ), 'manage_polls', WP_POLLS_SLUG . '/polls-add.php' );
		add_submenu_page( $manager, __( 'Poll Options', 'wp-polls' ), __( 'Poll Options', 'wp-polls' ), 'manage_polls', WP_POLLS_SLUG . '/polls-options.php' );
		add_submenu_page( $manager, __( 'Poll Templates', 'wp-polls' ), __( 'Poll Templates', 'wp-polls' ), 'manage_polls', WP_POLLS_SLUG . '/polls-templates.php' );
	}

	// Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin.

	/**
	 * Poll scripts admin.
	 *
	 * @param mixed $hook_suffix Value.
	 *
	 * @return mixed
	 */
	public static function poll_scripts_admin( $hook_suffix ) {
		if ( in_array( $hook_suffix, self::admin_pages(), true ) ) {
			wp_enqueue_style( 'wp-polls-admin', WP_POLLS_URL . 'polls-admin-css.css', false, WP_POLLS_VERSION, 'all' );
			wp_enqueue_script( 'wp-polls-admin', WP_POLLS_URL . 'polls-admin-js.js', array(), WP_POLLS_VERSION, true );
			wp_localize_script(
				'wp-polls-admin',
				'pollsAdminL10n',
				array(
					'admin_ajax_url'                 => admin_url( 'admin-ajax.php' ),
					'text_direction'                 => is_rtl() ? 'right' : 'left',
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
				)
			);
		}
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
	 * @param mixed $poll_timestamp Value.
	 * @param mixed $fieldname      Optional.
	 * @param mixed $display        Optional.
	 *
	 * @return mixed
	 */
	public static function poll_timestamp( $poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block' ) {
		global $month;
		echo '<div id="' . esc_attr( $fieldname ) . '" style="display: ' . esc_attr( $display ) . '">' . "\n";
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
	public static function manage_poll() {
		global $wpdb;

		// Every branch below is an administrative action. The per-action nonces are
		// only ever rendered on pages that already require 'manage_polls', but check
		// the capability itself rather than relying on the nonce for authorisation.
		if ( ! current_user_can( 'manage_polls' ) ) {
			exit();
		}

		// Form Processing.
		// Each branch below calls check_ajax_referer() with its own action before
		// touching anything; the dispatch itself only reads which branch to take.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['action'] ) && 'polls-admin' === sanitize_key( wp_unslash( $_POST['action'] ) ) ) {
			if ( ! empty( $_POST['do'] ) ) {
				// Set Header.
				header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) . '' );

				// Decide What To Do.
				switch ( $_POST['do'] ) {
					// Delete Polls Logs.
					case __( 'Delete All Logs', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-polls-logs' );
						if ( isset( $_POST['delete_logs_yes'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['delete_logs_yes'] ) ) ) {
							$delete_logs = $wpdb->query( "DELETE FROM $wpdb->pollsip" );
							if ( $delete_logs ) {
								echo wp_kses_post( '<p style="color: green;">' . __( 'All Polls Logs Have Been Deleted.', 'wp-polls' ) . '</p>' );
							} else {
								echo wp_kses_post( '<p style="color: red;">' . __( 'An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls' ) . '</p>' );
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
								/* translators: %s: value. */
								echo wp_kses_post( '<p style="color: green;">' . sprintf( __( 'All Logs For \'%s\' Has Been Deleted.', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
							} else {
								/* translators: %s: value. */
								echo wp_kses_post( '<p style="color: red;">' . sprintf( __( 'An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
							}
						}
						break;
					// Delete Poll's Answer.
					case __( 'Delete Poll Answer', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-poll-answer' );
						$pollq_id                = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$polla_aid               = isset( $_POST['polla_aid'] ) ? (int) $_POST['polla_aid'] : 0;
						$poll_answers            = $wpdb->get_row( $wpdb->prepare( "SELECT polla_votes, polla_answers FROM $wpdb->pollsa WHERE polla_aid = %d AND polla_qid = %d", $polla_aid, $pollq_id ) );
						$polla_votes             = (int) $poll_answers->polla_votes;
						$polla_answers           = wp_kses_post( removeslashes( trim( $poll_answers->polla_answers ) ) );
						$delete_polla_answers    = $wpdb->delete(
							$wpdb->pollsa,
							array(
								'polla_aid' => $polla_aid,
								'polla_qid' => $pollq_id,
							),
							array( '%d', '%d' )
						);
						$delete_pollip           = $wpdb->delete(
							$wpdb->pollsip,
							array(
								'pollip_qid' => $pollq_id,
								'pollip_aid' => $polla_aid,
							),
							array( '%d', '%d' )
						);
						$update_pollq_totalvotes = $wpdb->query( "UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes - $polla_votes) WHERE pollq_id = $pollq_id" );
						if ( $delete_polla_answers ) {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: green;">' . sprintf( __( 'Poll Answer \'%s\' Deleted Successfully.', 'wp-polls' ), $polla_answers ) . '</p>' );
						} else {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: red;">' . sprintf( __( 'Error In Deleting Poll Answer \'%s\'.', 'wp-polls' ), $polla_answers ) . '</p>' );
						}
						break;
					// Open Poll.
					case __( 'Open Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_open-poll' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
						$open_poll      = $wpdb->update(
							$wpdb->pollsq,
							array(
								'pollq_active' => 1,
							),
							array(
								'pollq_id' => $pollq_id,
							),
							array(
								'%d',
							),
							array(
								'%d',
							)
						);
						if ( $open_poll ) {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Is Now Opened', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						} else {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: red;">' . sprintf( __( 'Error Opening Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						}
						break;
					// Close Poll.
					case __( 'Close Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_close-poll' );
						$pollq_id       = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$pollq_question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
						$close_poll     = $wpdb->update(
							$wpdb->pollsq,
							array(
								'pollq_active' => 0,
							),
							array(
								'pollq_id' => $pollq_id,
							),
							array(
								'%d',
							),
							array(
								'%d',
							)
						);
						if ( $close_poll ) {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Is Now Closed', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						} else {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: red;">' . sprintf( __( 'Error Closing Poll \'%s\'', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						}
						break;
					// Delete Poll.
					case __( 'Delete Poll', 'wp-polls' ):
						check_ajax_referer( 'wp-polls_delete-poll' );
						$pollq_id                = isset( $_POST['pollq_id'] ) ? (int) $_POST['pollq_id'] : 0;
						$pollq_question          = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $pollq_id ) );
						$delete_poll_question    = $wpdb->delete( $wpdb->pollsq, array( 'pollq_id' => $pollq_id ), array( '%d' ) );
						$delete_poll_answers     = $wpdb->delete( $wpdb->pollsa, array( 'polla_qid' => $pollq_id ), array( '%d' ) );
						$delete_poll_ip          = $wpdb->delete( $wpdb->pollsip, array( 'pollip_qid' => $pollq_id ), array( '%d' ) );
						$poll_option_lastestpoll = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'poll_latestpoll'" );
						if ( ! $delete_poll_question ) {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: red;">' . sprintf( __( 'Error In Deleting Poll \'%s\' Question', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						}
						if ( empty( $text ) ) {
							/* translators: %s: value. */
							echo wp_kses_post( '<p style="color: green;">' . sprintf( __( 'Poll \'%s\' Deleted Successfully', 'wp-polls' ), wp_kses_post( removeslashes( $pollq_question ) ) ) . '</p>' );
						}

						// Update Lastest Poll ID To Poll Options.
						Polls_Options::set( 'latest_poll', Polls_Core::polls_latest_id() );
						do_action( 'wp_polls_delete_poll', $pollq_id );
						break;
				}
				exit();
			}
		}
	}

	// Function: Plug Into WP-Stats.

	/**
	 * Polls wp stats.
	 *
	 * @return mixed
	 */
	public static function polls_wp_stats() {
		add_filter( 'wp_stats_display_defaults', array( __CLASS__, 'polls_wp_stats_defaults' ) );
		add_filter( 'wp_stats_page_admin_plugins', array( __CLASS__, 'polls_page_admin_general_stats' ) );
		add_filter( 'wp_stats_page_plugins', array( __CLASS__, 'polls_page_general_stats' ) );
	}

	/**
	 * Tell WP-Stats about the toggle this plugin owns, and its default.
	 *
	 * Without this WP-Stats only learns the key exists once its checkbox has
	 * been submitted, so the panel would start out off on a fresh install.
	 *
	 * @param mixed $defaults Registered toggles.
	 *
	 * @return array
	 */
	public static function polls_wp_stats_defaults( $defaults ) {
		// WP-Stats' own defaults win, so this only ever adds.
		return array_merge( array( 'polls' => 1 ), (array) $defaults );
	}

	/**
	 * Whether the WP-Polls toggle is on in WP-Stats.
	 *
	 * @return bool
	 */
	protected static function polls_wp_stats_enabled() {
		if ( function_exists( 'wp_stats_display_enabled' ) ) {
			return wp_stats_display_enabled( 'polls' );
		}

		// WP-Stats before 3.0.0 kept the toggles in their own option row.
		$stats_display = get_option( 'stats_display' );

		return is_array( $stats_display ) && 1 === (int) ( $stats_display['polls'] ?? 0 );
	}

	/**
	 * Add WP-Polls General Stats To WP-Stats Page Options.
	 *
	 * @param mixed $content Value.
	 *
	 * @return mixed
	 */
	public static function polls_page_admin_general_stats( $content ) {
		// WP-Stats 3.0.0 owns the field name, which changed when it consolidated
		// its option rows.
		if ( function_exists( 'wp_stats_checkbox' ) ) {
			return $content . wp_stats_checkbox( 'polls', __( 'WP-Polls', 'wp-polls' ) );
		}

		$checked = self::polls_wp_stats_enabled() ? ' checked="checked"' : '';

		return $content . '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls"' . $checked . ' />&nbsp;&nbsp;<label for="wpstats_polls">' . __( 'WP-Polls', 'wp-polls' ) . '</label><br />' . "\n";
	}

	/**
	 * Add WP-Polls General Stats To WP-Stats Page.
	 *
	 * @param mixed $content Value.
	 *
	 * @return mixed
	 */
	public static function polls_page_general_stats( $content ) {
		if ( self::polls_wp_stats_enabled() ) {
			$content .= '<p><strong>' . __( 'WP-Polls', 'wp-polls' ) . '</strong></p>' . "\n";
			$content .= '<ul>' . "\n";
			/* translators: %1$s: value, %2$s: value. */
			$content .= '<li>' . sprintf( _n( '<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions( false ), 'wp-polls' ), esc_html( number_format_i18n( get_pollquestions( false ) ) ) ) . '</li>' . "\n";
			/* translators: %1$s: value, %2$s: value. */
			$content .= '<li>' . sprintf( _n( '<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers( false ), 'wp-polls' ), esc_html( number_format_i18n( get_pollanswers( false ) ) ) ) . '</li>' . "\n";
			/* translators: %1$s: value, %2$s: value. */
			$content .= '<li>' . sprintf( _n( '<strong>%s</strong> vote was cast.', '<strong>%s</strong> votes were cast.', get_pollvotes( false ), 'wp-polls' ), esc_html( number_format_i18n( get_pollvotes( false ) ) ) ) . '</li>' . "\n";
			$content .= '</ul>' . "\n";
		}
		return $content;
	}
}
