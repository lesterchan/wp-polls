<?php
/**
 * Settings API registration for the Poll Options and Poll Templates tabs.
 *
 * Both tabs are built entirely out of add_settings_section() and
 * add_settings_field(): WP_Polls_Screen_Settings draws the tabs, opens the form,
 * hands it to do_settings_sections() and closes it, so every row on both tabs
 * is declared here rather than written out as hand rolled table markup.
 *
 * Both tabs write into the same wp_polls_options row. That matters: the
 * Settings API hands sanitize_callback only the fields the submitting form
 * rendered, and update_option() then replaces the entire row - so a naive
 * callback that returns its input would erase whichever half of the settings
 * the other tab owns. self::sanitize() therefore merges the submitted subset
 * into the stored value instead of replacing it.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the setting, the sections and the fields, and validates the input.
 */
class WP_Polls_Settings {

	/**
	 * Settings group both tabs post under.
	 *
	 * @var string
	 */
	const GROUP = 'wp_polls_options';

	/**
	 * Slug of the single settings screen the two tabs live on.
	 *
	 * @var string
	 */
	const PAGE = 'wp-polls-settings';

	/**
	 * The Options tab, and the bucket its sections are registered under.
	 *
	 * A tab is not a screen: both tabs are one page posting one setting, and
	 * these only name the buckets do_settings_sections() reads back.
	 *
	 * @var string
	 */
	const TAB_OPTIONS = 'options';

	/**
	 * The Templates tab, and the bucket its sections are registered under.
	 *
	 * @var string
	 */
	const TAB_TEMPLATES = 'templates';

	/**
	 * Section ids on the Options tab.
	 *
	 * @var string
	 */
	const SECTION_BAR          = 'wp_polls_bar';
	const SECTION_AJAX         = 'wp_polls_ajax';
	const SECTION_ANSWERS_SORT = 'wp_polls_answers_sort';
	const SECTION_RESULTS_SORT = 'wp_polls_results_sort';
	const SECTION_VOTE         = 'wp_polls_vote';
	const SECTION_LOGGING      = 'wp_polls_logging';
	const SECTION_ARCHIVE      = 'wp_polls_archive';
	const SECTION_STATS        = 'wp_polls_stats';
	const SECTION_CURRENT      = 'wp_polls_current';

	/**
	 * Section ids on the Templates tab.
	 *
	 * @var string
	 */
	const SECTION_TEMPLATE_VARIABLES = 'wp_polls_template_variables';
	const SECTION_TEMPLATES_VOTE     = 'wp_polls_templates_vote';
	const SECTION_TEMPLATES_RESULT   = 'wp_polls_templates_result';
	const SECTION_TEMPLATES_ARCHIVE  = 'wp_polls_templates_archive';
	const SECTION_TEMPLATES_MISC     = 'wp_polls_templates_misc';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );

		// The cron schedule follows the cookie/log expiry setting, so it has to be
		// rebuilt whenever the option is written. Hooked here rather than from the
		// Poll Options screen because the save happens on options.php, which never
		// loads that screen - a callback added while the screen renders would only
		// ever run on a request that is not saving anything.
		add_action( 'update_option_' . WP_Polls_Options::OPTION, array( 'WP_Polls', 'cron_polls_place' ) );
	}

	/**
	 * The tabs of the settings screen, in order, as slug => label.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			self::TAB_OPTIONS   => __( 'Poll Options', 'wp-polls' ),
			self::TAB_TEMPLATES => __( 'Poll Templates', 'wp-polls' ),
		);
	}

	/**
	 * The bucket a tab's sections and fields are registered under.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_bucket( $tab ) {
		return self::PAGE . '-' . $tab;
	}

	/**
	 * The tab the request asked for, defaulting to the first one.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// Reading which tab to render. Nothing is written on this request, and
		// the value is checked against the fixed list below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads ?tab= to decide which tab to render, and the value is checked against self::tabs().
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( self::tabs()[ $requested ] ) ? $requested : self::TAB_OPTIONS;
	}

	/**
	 * The URL of one tab of the settings screen.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Register the setting and everything both screens render.
	 *
	 * Registered once rather than once per screen: register_setting() installs
	 * the callback as a sanitize_option_{$option} filter, so a second
	 * registration for the same option would simply replace the first. The
	 * sections and fields are keyed by id in the globals they land in, so a
	 * second call is a no-op rather than a duplicated row.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_Polls_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_Polls_Options::defaults(),
			)
		);

		self::register_options_fields();
		self::register_template_fields();
	}

	/**
	 * Sections and fields for the Poll Options screen.
	 *
	 * @return void
	 */
	protected static function register_options_fields() {
		$page = self::tab_bucket( self::TAB_OPTIONS );

		$yes_no = array(
			0 => __( 'No', 'wp-polls' ),
			1 => __( 'Yes', 'wp-polls' ),
		);

		$sort_by = array(
			'polla_votes'   => __( 'Votes Cast', 'wp-polls' ),
			'polla_aid'     => __( 'Exact Order', 'wp-polls' ),
			'polla_answers' => __( 'Alphabetical Order', 'wp-polls' ),
			'RAND()'        => __( 'Random Order', 'wp-polls' ),
		);

		$sort_order = array(
			'asc'  => __( 'Ascending', 'wp-polls' ),
			'desc' => __( 'Descending', 'wp-polls' ),
		);

		// --- Poll Bar Style ---------------------------------------------------
		add_settings_section( self::SECTION_BAR, __( 'Poll Bar Style', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_bar_style',
			__( 'Poll Bar Style', 'wp-polls' ),
			array( __CLASS__, 'field_bar_style' ),
			$page,
			self::SECTION_BAR
		);

		add_settings_field(
			'poll_bar_bg',
			__( 'Poll Bar Background', 'wp-polls' ),
			array( __CLASS__, 'field_bar_color' ),
			$page,
			self::SECTION_BAR,
			array(
				'label_for' => 'poll_bar_bg',
				'path'      => 'bar.background',
				'field'     => 'background',
			)
		);

		add_settings_field(
			'poll_bar_border',
			__( 'Poll Bar Border', 'wp-polls' ),
			array( __CLASS__, 'field_bar_color' ),
			$page,
			self::SECTION_BAR,
			array(
				'label_for' => 'poll_bar_border',
				'path'      => 'bar.border',
				'field'     => 'border',
			)
		);

		add_settings_field(
			'poll_bar_height',
			__( 'Poll Bar Height', 'wp-polls' ),
			array( __CLASS__, 'field_text' ),
			$page,
			self::SECTION_BAR,
			array(
				'label_for'  => 'poll_bar_height',
				'path'       => 'bar.height',
				'class_name' => 'small-text',
				'maxlength'  => 2,
				'after'      => 'px',
				'dir'        => 'ltr',
				'attributes' => array(
					'data-poll-action' => 'pollbar-update',
					'data-poll-field'  => 'height',
				),
			)
		);

		add_settings_field(
			'poll_bar_preview',
			__( 'Your poll bar will look like this', 'wp-polls' ),
			array( __CLASS__, 'field_bar_preview' ),
			$page,
			self::SECTION_BAR
		);

		// --- Polls AJAX Style -------------------------------------------------
		add_settings_section( self::SECTION_AJAX, __( 'Polls AJAX Style', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_ajax_loading',
			__( 'Show Loading Image With Text', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_AJAX,
			array(
				'label_for' => 'poll_ajax_loading',
				'path'      => 'ajax.loading',
				'options'   => $yes_no,
			)
		);

		add_settings_field(
			'poll_ajax_fading',
			__( 'Show Fading In And Fading Out Of Poll', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_AJAX,
			array(
				'label_for' => 'poll_ajax_fading',
				'path'      => 'ajax.fading',
				'options'   => $yes_no,
			)
		);

		// --- Sorting Of Poll Answers -----------------------------------------
		add_settings_section( self::SECTION_ANSWERS_SORT, __( 'Sorting Of Poll Answers', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_ans_sortby',
			__( 'Sort Poll Answers By:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_ANSWERS_SORT,
			array(
				'label_for' => 'poll_ans_sortby',
				'path'      => 'sort.answers_by',
				'options'   => $sort_by,
			)
		);

		add_settings_field(
			'poll_ans_sortorder',
			__( 'Sort Order Of Poll Answers:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_ANSWERS_SORT,
			array(
				'label_for' => 'poll_ans_sortorder',
				'path'      => 'sort.answers_order',
				'options'   => $sort_order,
			)
		);

		// --- Sorting Of Poll Results -----------------------------------------
		add_settings_section( self::SECTION_RESULTS_SORT, __( 'Sorting Of Poll Results', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_result_sortby',
			__( 'Sort Poll Results By:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_RESULTS_SORT,
			array(
				'label_for' => 'poll_result_sortby',
				'path'      => 'sort.results_by',
				'options'   => $sort_by,
			)
		);

		add_settings_field(
			'poll_result_sortorder',
			__( 'Sort Order Of Poll Results:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_RESULTS_SORT,
			array(
				'label_for' => 'poll_result_sortorder',
				'path'      => 'sort.results_order',
				'options'   => $sort_order,
			)
		);

		// --- Allow To Vote ----------------------------------------------------
		add_settings_section( self::SECTION_VOTE, __( 'Allow To Vote', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_allowtovote',
			__( 'Who Is Allowed To Vote?', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_VOTE,
			array(
				'label_for' => 'poll_allowtovote',
				'path'      => 'allow_to_vote',
				'options'   => array(
					0 => __( 'Guests Only', 'wp-polls' ),
					1 => __( 'Registered Users Only', 'wp-polls' ),
					2 => __( 'Registered Users And Guests', 'wp-polls' ),
				),
			)
		);

		// --- Logging Method ---------------------------------------------------
		add_settings_section( self::SECTION_LOGGING, __( 'Logging Method', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_logging_method',
			__( 'Poll Logging Method:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_LOGGING,
			array(
				'label_for' => 'poll_logging_method',
				'path'      => 'logging_method',
				'options'   => array(
					0 => __( 'Do Not Log', 'wp-polls' ),
					1 => __( 'Logged By Cookie', 'wp-polls' ),
					2 => __( 'Logged By IP', 'wp-polls' ),
					3 => __( 'Logged By Cookie And IP', 'wp-polls' ),
					4 => __( 'Logged By Username', 'wp-polls' ),
				),
			)
		);

		add_settings_field(
			'poll_cookielog_expiry',
			__( 'Expiry Time For Cookie And Log:', 'wp-polls' ),
			array( __CLASS__, 'field_text' ),
			$page,
			self::SECTION_LOGGING,
			array(
				'label_for'  => 'poll_cookielog_expiry',
				'path'       => 'cookie_expiry',
				'class_name' => 'small-text',
				'after'      => __( 'seconds (0 to disable)', 'wp-polls' ),
			)
		);

		add_settings_field(
			'poll_ip_header',
			__( 'Header That Contains The IP:', 'wp-polls' ),
			array( __CLASS__, 'field_text' ),
			$page,
			self::SECTION_LOGGING,
			array(
				'label_for'   => 'poll_ip_header',
				'path'        => 'ip_header',
				'placeholder' => 'REMOTE_ADDR',
				'description' => array( __CLASS__, 'describe_ip_header' ),
			)
		);

		// --- Poll Archive -----------------------------------------------------
		add_settings_section( self::SECTION_ARCHIVE, __( 'Poll Archive', 'wp-polls' ), array( __CLASS__, 'section_archive' ), $page );

		add_settings_field(
			'poll_archive_perpage',
			__( 'Number Of Polls Per Page:', 'wp-polls' ),
			array( __CLASS__, 'field_text' ),
			$page,
			self::SECTION_ARCHIVE,
			array(
				'label_for'  => 'poll_archive_perpage',
				'path'       => 'archive.per_page',
				'class_name' => 'small-text',
			)
		);

		add_settings_field(
			'poll_archive_displaypoll',
			__( 'Type Of Polls To Display In Poll Archive:', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_ARCHIVE,
			array(
				'label_for' => 'poll_archive_displaypoll',
				'path'      => 'archive.display_poll',
				'options'   => array(
					1 => __( 'Closed Polls Only', 'wp-polls' ),
					2 => __( 'Opened Polls Only', 'wp-polls' ),
					3 => __( 'Closed And Opened Polls', 'wp-polls' ),
				),
			)
		);

		add_settings_field(
			'poll_archive_url',
			__( 'Poll Archive URL:', 'wp-polls' ),
			array( __CLASS__, 'field_text' ),
			$page,
			self::SECTION_ARCHIVE,
			array(
				'label_for'  => 'poll_archive_url',
				'path'       => 'archive.url',
				'class_name' => 'regular-text code',
				'dir'        => 'ltr',
			)
		);

		// --- WP-Stats ---------------------------------------------------------
		add_settings_section( self::SECTION_STATS, __( 'WP-Stats', 'wp-polls' ), array( __CLASS__, 'section_stats' ), $page );

		add_settings_field(
			'poll_stats_display',
			__( 'Show Poll Statistics In WP-Stats', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_STATS,
			array(
				'label_for' => 'poll_stats_display',
				'path'      => 'stats_display',
				'options'   => $yes_no,
			)
		);

		// --- Current Active Poll ----------------------------------------------
		add_settings_section( self::SECTION_CURRENT, __( 'Current Active Poll', 'wp-polls' ), '__return_false', $page );

		add_settings_field(
			'poll_currentpoll',
			__( 'Current Active Poll', 'wp-polls' ),
			array( __CLASS__, 'field_current_poll' ),
			$page,
			self::SECTION_CURRENT,
			array( 'label_for' => 'poll_currentpoll' )
		);

		add_settings_field(
			'poll_close',
			__( 'When Poll Is Closed', 'wp-polls' ),
			array( __CLASS__, 'field_select' ),
			$page,
			self::SECTION_CURRENT,
			array(
				'label_for' => 'poll_close',
				'path'      => 'close',
				'options'   => array(
					1 => __( 'Display Poll\'s Results', 'wp-polls' ),
					3 => __( 'Display Disabled Poll\'s Voting Form', 'wp-polls' ),
					2 => __( 'Do Not Display Poll In Post/Sidebar', 'wp-polls' ),
				),
			)
		);
	}

	/**
	 * Sections and fields for the Poll Templates screen.
	 *
	 * Every row is the same shape - a textarea, the tokens it understands and a
	 * restore button - so these are registered from the definition in
	 * self::template_sections() rather than written out one call at a time.
	 *
	 * @return void
	 */
	protected static function register_template_fields() {
		$page = self::tab_bucket( self::TAB_TEMPLATES );

		add_settings_section(
			self::SECTION_TEMPLATE_VARIABLES,
			__( 'Template Variables', 'wp-polls' ),
			array( __CLASS__, 'section_template_variables' ),
			$page
		);

		foreach ( self::template_sections() as $section_id => $section ) {
			add_settings_section( $section_id, $section['title'], '__return_false', $page );

			foreach ( $section['fields'] as $key => $field ) {
				add_settings_field(
					'poll_template_' . $key,
					self::template_field_title( $field ),
					array( __CLASS__, 'field_template' ),
					$page,
					$section_id,
					array(
						'label_for' => 'poll_template_' . $key,
						'key'       => $key,
						'variables' => $field['variables'],
					)
				);
			}
		}
	}

	/**
	 * The Poll Templates screen, as sections of template fields.
	 *
	 * The order matches WP_Polls_Options::template_keys().
	 *
	 * @return array
	 */
	public static function template_sections() {
		// The header of a voting form and the header of a result are the same
		// fields about the poll as a whole, so they take the same tokens.
		$question_header = array( '%POLL_ID%', '%POLL_QUESTION%', '%POLL_START_DATE%', '%POLL_END_DATE%', '%POLL_TOTALVOTES%', '%POLL_TOTALVOTERS%', '%POLL_MULTIPLE_ANS_MAX%' );

		$result_body = array( '%POLL_ID%', '%POLL_ANSWER_ID%', '%POLL_ANSWER%', '%POLL_ANSWER_TEXT%', '%POLL_ANSWER_VOTES%', '%POLL_ANSWER_PERCENTAGE%', '%POLL_MULTIPLE_ANSWER_PERCENTAGE%' );

		$result_footer = array( '%POLL_ID%', '%POLL_START_DATE%', '%POLL_END_DATE%', '%POLL_TOTALVOTES%', '%POLL_TOTALVOTERS%', '%POLL_MOST_ANSWER%', '%POLL_MOST_VOTES%', '%POLL_MOST_PERCENTAGE%', '%POLL_LEAST_ANSWER%', '%POLL_LEAST_VOTES%', '%POLL_LEAST_PERCENTAGE%', '%POLL_MULTIPLE_ANS_MAX%' );

		// A poll in the archive is rendered without knowing its own id.
		$archive_footer = array_values( array_diff( $result_footer, array( '%POLL_ID%' ) ) );

		return array(
			self::SECTION_TEMPLATES_VOTE    => array(
				'title'  => __( 'Poll Voting Form Templates', 'wp-polls' ),
				'fields' => array(
					'voteheader' => array(
						'title'     => __( 'Voting Form Header:', 'wp-polls' ),
						'note'      => '',
						'variables' => $question_header,
					),
					'votebody'   => array(
						'title'     => __( 'Voting Form Body:', 'wp-polls' ),
						'note'      => '',
						'variables' => array( '%POLL_ID%', '%POLL_ANSWER_ID%', '%POLL_ANSWER%', '%POLL_ANSWER_VOTES%', '%POLL_CHECKBOX_RADIO%' ),
					),
					'votefooter' => array(
						'title'     => __( 'Voting Form Footer:', 'wp-polls' ),
						'note'      => '',
						'variables' => array( '%POLL_ID%', '%POLL_RESULT_URL%', '%POLL_MULTIPLE_ANS_MAX%' ),
					),
				),
			),
			self::SECTION_TEMPLATES_RESULT  => array(
				'title'  => __( 'Poll Result Templates', 'wp-polls' ),
				'fields' => array(
					'resultheader'  => array(
						'title'     => __( 'Result Header:', 'wp-polls' ),
						'note'      => '',
						'variables' => $question_header,
					),
					'resultbody'    => array(
						'title'     => __( 'Result Body:', 'wp-polls' ),
						'note'      => __( 'Displayed When The User HAS NOT Voted', 'wp-polls' ),
						'variables' => $result_body,
					),
					'resultbody2'   => array(
						'title'     => __( 'Result Body:', 'wp-polls' ),
						'note'      => __( 'Displayed When The User HAS Voted', 'wp-polls' ),
						'variables' => $result_body,
					),
					'resultfooter'  => array(
						'title'     => __( 'Result Footer:', 'wp-polls' ),
						'note'      => __( 'Displayed When The User HAS Voted', 'wp-polls' ),
						'variables' => $result_footer,
					),
					'resultfooter2' => array(
						'title'     => __( 'Result Footer:', 'wp-polls' ),
						'note'      => __( 'Displayed When The User HAS NOT Voted', 'wp-polls' ),
						'variables' => $result_footer,
					),
				),
			),
			self::SECTION_TEMPLATES_ARCHIVE => array(
				'title'  => __( 'Poll Archive Templates', 'wp-polls' ),
				'fields' => array(
					'pollarchivelink'         => array(
						'title'     => __( 'Poll Archive Link', 'wp-polls' ),
						'note'      => __( 'Template For Displaying Poll Archive Link', 'wp-polls' ),
						'variables' => array( '%POLL_ARCHIVE_URL%' ),
					),
					'pollarchiveheader'       => array(
						'title'     => __( 'Individual Poll Header', 'wp-polls' ),
						'note'      => __( 'Displayed Before Each Poll In The Poll Archive', 'wp-polls' ),
						'variables' => array(),
					),
					'pollarchivefooter'       => array(
						'title'     => __( 'Individual Poll Footer', 'wp-polls' ),
						'note'      => __( 'Displayed After Each Poll In The Poll Archive', 'wp-polls' ),
						'variables' => $archive_footer,
					),
					'pollarchivepagingheader' => array(
						'title'     => __( 'Paging Header', 'wp-polls' ),
						'note'      => __( 'Displayed Before Paging In The Poll Archive', 'wp-polls' ),
						'variables' => array(),
					),
					'pollarchivepagingfooter' => array(
						'title'     => __( 'Paging Footer', 'wp-polls' ),
						'note'      => __( 'Displayed After Paging In The Poll Archive', 'wp-polls' ),
						'variables' => array(),
					),
				),
			),
			self::SECTION_TEMPLATES_MISC    => array(
				'title'  => __( 'Poll Misc Templates', 'wp-polls' ),
				'fields' => array(
					'disable' => array(
						'title'     => __( 'Poll Disabled', 'wp-polls' ),
						'note'      => __( 'Displayed When The Poll Is Disabled', 'wp-polls' ),
						'variables' => array(),
					),
					'error'   => array(
						'title'     => __( 'Poll Error', 'wp-polls' ),
						'note'      => __( 'Displayed When An Error Has Occured While Processing The Poll', 'wp-polls' ),
						'variables' => array(),
					),
				),
			),
		);
	}

	/**
	 * The tokens the templates understand, as token => what it prints.
	 *
	 * @return array
	 */
	public static function template_variables() {
		return array(
			'%POLL_ID%'                         => __( 'Display the poll\'s ID', 'wp-polls' ),
			'%POLL_QUESTION%'                   => __( 'Display the poll\'s question', 'wp-polls' ),
			'%POLL_TOTALVOTES%'                 => __( 'Display the poll\'s total votes NOT the number of people who voted for the poll', 'wp-polls' ),
			'%POLL_TOTALVOTERS%'                => __( 'Display the number of people who voted for the poll NOT the total votes of the poll', 'wp-polls' ),
			'%POLL_START_DATE%'                 => __( 'Display the poll\'s start date/time', 'wp-polls' ),
			'%POLL_END_DATE%'                   => __( 'Display the poll\'s end date/time', 'wp-polls' ),
			'%POLL_RESULT_URL%'                 => __( 'Displays URL to poll\'s result', 'wp-polls' ),
			'%POLL_ARCHIVE_URL%'                => __( 'Display the poll archive URL', 'wp-polls' ),
			'%POLL_MULTIPLE_ANS_MAX%'           => __( 'Display the the maximum number of answers the user can choose if the poll supports multiple answers', 'wp-polls' ),
			'%POLL_CHECKBOX_RADIO%'             => __( 'Display "checkbox" or "radio" input types depending on the poll type', 'wp-polls' ),
			'%POLL_ANSWER_ID%'                  => __( 'Display the poll\'s answer ID', 'wp-polls' ),
			'%POLL_ANSWER%'                     => __( 'Display the poll\'s answer', 'wp-polls' ),
			'%POLL_ANSWER_TEXT%'                => __( 'Display the poll\'s answer without HTML formatting.', 'wp-polls' ),
			'%POLL_ANSWER_VOTES%'               => __( 'Display the poll\'s answer votes', 'wp-polls' ),
			'%POLL_ANSWER_PERCENTAGE%'          => __( 'Display the poll\'s answer percentage', 'wp-polls' ),
			'%POLL_MULTIPLE_ANSWER_PERCENTAGE%' => __( 'Display the poll\'s mutiple answer percentage. This is total votes divided by total voters.', 'wp-polls' ),
			'%POLL_MOST_ANSWER%'                => __( 'Display the poll\'s most voted answer', 'wp-polls' ),
			'%POLL_MOST_VOTES%'                 => __( 'Display the poll\'s answer votes for the most voted answer', 'wp-polls' ),
			'%POLL_MOST_PERCENTAGE%'            => __( 'Display the poll\'s answer percentage for the most voted answer', 'wp-polls' ),
			'%POLL_LEAST_ANSWER%'               => __( 'Display the poll\'s least voted answer', 'wp-polls' ),
			'%POLL_LEAST_VOTES%'                => __( 'Display the poll\'s answer votes for the least voted answer', 'wp-polls' ),
			'%POLL_LEAST_PERCENTAGE%'           => __( 'Display the poll\'s answer percentage for the least voted answer', 'wp-polls' ),
		);
	}

	/**
	 * The th contents for one template field: its label and what it is for.
	 *
	 * @param array $field One entry from self::template_sections().
	 * @return string
	 */
	protected static function template_field_title( $field ) {
		$title = esc_html( $field['title'] );

		if ( ! empty( $field['note'] ) ) {
			$title .= '<br /><span class="description">' . esc_html( $field['note'] ) . '</span>';
		}

		return $title;
	}

	// --- Section callbacks ---------------------------------------------------

	/**
	 * What the Poll Archive shows regardless of the poll's state.
	 *
	 * @return void
	 */
	public static function section_archive() {
		echo '<p class="description">' . esc_html__( 'Only polls\' results will be shown in the Poll Archive regardless of whether the poll is closed or opened.', 'wp-polls' ) . '</p>';
	}

	/**
	 * What the WP-Stats toggle does, and what happens without WP-Stats.
	 *
	 * @return void
	 */
	public static function section_stats() {
		echo '<p class="description">' . esc_html__( 'WP-Polls contributes its own section to the WP-Stats page. The setting is stored by WP-Polls rather than by WP-Stats, so turning it off here cannot affect any other plugin\'s section, and it does nothing at all if WP-Stats is not installed.', 'wp-polls' ) . '</p>';
	}

	/**
	 * The token reference above the template fields.
	 *
	 * @return void
	 */
	public static function section_template_variables() {
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Variable', 'wp-polls' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Description', 'wp-polls' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( self::template_variables() as $token => $description ) {
			echo '<tr>';
			echo '<td><strong><code>' . esc_html( $token ) . '</code></strong></td>';
			echo '<td>' . esc_html( $description ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="description">';
		// The two token names are passed in rather than written inside the
		// translatable string on purpose: phpcbf reads %TOKEN% inside __() as a
		// printf placeholder and renumbers it to %1$TOKEN%, which would then be
		// shown to the user.
		printf(
			/* translators: 1: %POLL_TOTALVOTES% token, 2: %POLL_TOTALVOTERS% token. */
			esc_html__( 'Note: %1$s and %2$s will be different if your poll supports multiple answers. If your poll allows only single answer, both value will be the same.', 'wp-polls' ),
			'<strong><code>%POLL_TOTALVOTES%</code></strong>',
			'<strong><code>%POLL_TOTALVOTERS%</code></strong>'
		);
		echo '</p>';
	}

	// --- Field callbacks -----------------------------------------------------

	/**
	 * A select bound to one dot path.
	 *
	 * @param array $args Field args: path, options, label_for.
	 * @return void
	 */
	public static function field_select( $args ) {
		$value = WP_Polls_Options::get( $args['path'] );
		// Booleans stringify to '1' and '', neither of which is an option value,
		// so they are cast through int first.
		$current = is_bool( $value ) ? (string) (int) $value : (string) $value;

		echo '<select name="' . esc_attr( self::field_name( $args['path'] ) ) . '" id="' . esc_attr( $args['label_for'] ) . '">';
		foreach ( $args['options'] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		self::field_description( $args );
	}

	/**
	 * A text input bound to one dot path.
	 *
	 * @param array $args Field args: path, label_for, and any of class_name,
	 *                    maxlength, placeholder, dir, before, after, attributes,
	 *                    description.
	 * @return void
	 */
	public static function field_text( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'class_name'  => 'regular-text',
				'maxlength'   => 0,
				'placeholder' => '',
				'dir'         => '',
				'before'      => '',
				'after'       => '',
				'attributes'  => array(),
			)
		);

		$input  = '<input type="text" id="' . esc_attr( $args['label_for'] ) . '"';
		$input .= ' name="' . esc_attr( self::field_name( $args['path'] ) ) . '"';
		$input .= ' value="' . esc_attr( WP_Polls_Options::get( $args['path'] ) ) . '"';
		$input .= ' class="' . esc_attr( $args['class_name'] ) . '"';

		if ( $args['maxlength'] ) {
			$input .= ' maxlength="' . esc_attr( $args['maxlength'] ) . '"';
		}
		if ( '' !== $args['placeholder'] ) {
			$input .= ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
		}
		foreach ( $args['attributes'] as $attribute => $value ) {
			$input .= ' ' . esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}

		$input .= ' />';

		$wrapper_open  = '';
		$wrapper_close = '';
		if ( '' !== $args['dir'] ) {
			$wrapper_open  = '<span dir="' . esc_attr( $args['dir'] ) . '">';
			$wrapper_close = '</span>';
		}

		// $input is assembled from escaped parts above; before/after are literal
		// markup or already translated text.
		echo $wrapper_open . esc_html( $args['before'] ) . $input . ' ' . esc_html( $args['after'] ) . $wrapper_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Assembled from the escaped parts above; before/after are literal markup or already translated text.

		self::field_description( $args );
	}

	/**
	 * One of the two poll bar colours.
	 *
	 * The browser's own colour input, the same control WP-Postratings uses for
	 * its rated and unrated colours. Up to 3.0.0 this was a six character text
	 * field with a '#' printed beside it and a swatch that JavaScript kept in
	 * step; the input is the swatch now, and it cannot be typed into wrong.
	 *
	 * @param array $args Field args: path, field, label_for.
	 * @return void
	 */
	public static function field_bar_color( $args ) {
		printf(
			'<input type="color" id="%1$s" name="%2$s" value="#%3$s" data-poll-action="pollbar-update" data-poll-field="%4$s" />',
			esc_attr( $args['label_for'] ),
			esc_attr( self::field_name( $args['path'] ) ),
			esc_attr( WP_Polls::sanitize_bar_color( WP_Polls_Options::get( $args['path'] ) ) ),
			esc_attr( $args['field'] )
		);
	}

	/**
	 * The bar styles, each shown as the bar it produces.
	 *
	 * @return void
	 */
	public static function field_bar_style() {
		$bar    = WP_Polls_Options::get( 'bar' );
		$labels = array(
			'flat'     => __( 'Flat', 'wp-polls' ),
			'gradient' => __( 'Gradient', 'wp-polls' ),
		);

		foreach ( self::bar_styles() as $style ) {
			// Each swatch is the real bar markup carrying the style it offers,
			// so what is offered is what is rendered. The colours and the
			// background image come from the stylesheet
			// WP_Polls_Admin::pollbar_css() generates, rather than from a style
			// attribute per swatch.
			echo '<p>';
			echo '<input type="radio" id="poll_bar_style-' . esc_attr( $style ) . '"';
			echo ' name="' . esc_attr( self::field_name( 'bar.style' ) ) . '"';
			echo ' value="' . esc_attr( $style ) . '"';
			checked( $style, $bar['style'] );
			echo ' data-poll-action="pollbar-style" />';
			echo '<label for="poll_bar_style-' . esc_attr( $style ) . '">&nbsp;&nbsp;&nbsp;';
			echo '<span class="wp-polls wp-polls-swatch" data-poll-style="' . esc_attr( $style ) . '">';
			echo '<span class="wp-polls-bar"><span class="wp-polls-bar-fill"></span></span>';
			echo '</span>';
			echo '&nbsp;&nbsp;&nbsp;' . esc_html( isset( $labels[ $style ] ) ? $labels[ $style ] : $style ) . '</label>';
			echo '</p>';
		}
	}

	/**
	 * The bar as configured, in the front end's own markup.
	 *
	 * Rendered with the front end classes under the front end stylesheet, so
	 * this is the bar itself rather than an approximation of it maintained
	 * separately.
	 *
	 * @return void
	 */
	public static function field_bar_preview() {
		echo '<div id="wp-polls-pollbar" class="wp-polls">';
		echo '<div class="wp-polls-bar"><div class="wp-polls-bar-fill"></div></div>';
		echo '</div>';
	}

	/**
	 * The poll shown wherever a poll is asked for without an id.
	 *
	 * @param array $args Field args: label_for.
	 * @return void
	 */
	public static function field_current_poll( $args ) {
		global $wpdb;

		$current = (int) WP_Polls_Options::get( 'current_poll' );

		echo '<select name="' . esc_attr( self::field_name( 'current_poll' ) ) . '" id="' . esc_attr( $args['label_for'] ) . '">';

		$fixed = array(
			-1 => __( 'Do NOT Display Poll (Disable)', 'wp-polls' ),
			-2 => __( 'Display Random Poll', 'wp-polls' ),
			0  => __( 'Display Latest Poll', 'wp-polls' ),
		);
		foreach ( $fixed as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $current, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$polls = $wpdb->get_results( "SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC" );
		if ( $polls ) {
			echo '<optgroup label="' . esc_attr__( 'Polls', 'wp-polls' ) . '">';
			foreach ( $polls as $poll ) {
				$poll_id = (int) $poll->pollq_id;
				echo '<option value="' . esc_attr( $poll_id ) . '"' . selected( $poll_id, $current, false ) . '>' . esc_html( removeslashes( $poll->pollq_question ) ) . '</option>';
			}
			echo '</optgroup>';
		}

		echo '</select>';
	}

	/**
	 * One template, the tokens it understands and its restore button.
	 *
	 * @param array $args Field args: key, variables, label_for.
	 * @return void
	 */
	public static function field_template( $args ) {
		$path = 'templates.' . $args['key'];

		printf(
			'<textarea id="%1$s" name="%2$s" class="large-text code" rows="10">%3$s</textarea>',
			esc_attr( $args['label_for'] ),
			esc_attr( self::field_name( $path ) ),
			esc_textarea( removeslashes( WP_Polls_Options::get( $path ) ) )
		);

		$tokens = esc_html__( 'N/A', 'wp-polls' );
		if ( ! empty( $args['variables'] ) ) {
			$tokens = '<code>' . implode( '</code> <code>', array_map( 'esc_html', $args['variables'] ) ) . '</code>';
		}

		// $tokens is either an escaped string or escaped tokens in code spans.
		echo '<p class="description">' . esc_html__( 'Allowed Variables:', 'wp-polls' ) . ' ' . $tokens . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The tokens are escaped above, either as a string or inside code spans.

		printf(
			'<p><button type="button" class="button" data-poll-action="restore-template" data-poll-template="%1$s">%2$s</button></p>',
			esc_attr( $args['key'] ),
			esc_html__( 'Restore Default Template', 'wp-polls' )
		);
	}

	/**
	 * Why the IP header field is usually best left alone.
	 *
	 * @return void
	 */
	public static function describe_ip_header() {
		echo esc_html__( 'Leave blank to use REMOTE_ADDR. Only set this if a proxy in front of WordPress always overwrites the header, because visitors can otherwise forge it and vote more than once.', 'wp-polls' ) . '<br />';
		printf(
			/* translators: 1: The WP_POLLS_TRUST_PROXY constant, 2: the wp_polls_trust_proxy filter, both in code spans. */
			esc_html__( 'Example: HTTP_X_FORWARDED_FOR. You can also opt in with the %1$s constant or the %2$s filter.', 'wp-polls' ),
			'<code>WP_POLLS_TRUST_PROXY</code>',
			'<code>wp_polls_trust_proxy</code>'
		);
	}

	// --- Helpers -------------------------------------------------------------

	/**
	 * The form field name for a dot path, e.g. poll_options[bar][style].
	 *
	 * The nesting has to mirror the option's own shape, because what the form
	 * posts is what self::sanitize() is handed.
	 *
	 * @param string $path Dot separated path into the option.
	 * @return string
	 */
	protected static function field_name( $path ) {
		$name = WP_Polls_Options::OPTION;

		foreach ( explode( '.', $path ) as $segment ) {
			$name .= '[' . $segment . ']';
		}

		return $name;
	}

	/**
	 * Print a field's description, if it has one.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	protected static function field_description( $args ) {
		if ( empty( $args['description'] ) ) {
			return;
		}

		echo '<p class="description">';
		call_user_func( $args['description'] );
		echo '</p>';
	}

	/**
	 * Allowed values for the fields that are a fixed set.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'sort.answers_by'    => array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ),
			'sort.results_by'    => array( 'polla_votes', 'polla_aid', 'polla_answers', 'RAND()' ),
			'sort.answers_order' => array( 'asc', 'desc' ),
			'sort.results_order' => array( 'asc', 'desc' ),
		);
	}

	/**
	 * Merge and validate a submitted subset of the option.
	 *
	 * @param mixed $input Whatever the submitting screen posted.
	 * @return array The complete option, ready to store.
	 */
	public static function sanitize( $input ) {
		$current = WP_Polls_Options::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		// --- Poll Options screen -------------------------------------------
		if ( isset( $input['bar'] ) && is_array( $input['bar'] ) ) {
			$bar                          = $input['bar'];
			$current['bar']['style']      = isset( $bar['style'] ) ? sanitize_text_field( $bar['style'] ) : $current['bar']['style'];
			$current['bar']['background'] = isset( $bar['background'] ) ? WP_Polls::sanitize_bar_color( $bar['background'] ) : $current['bar']['background'];
			$current['bar']['border']     = isset( $bar['border'] ) ? WP_Polls::sanitize_bar_color( $bar['border'] ) : $current['bar']['border'];
			$current['bar']['height']     = isset( $bar['height'] ) ? max( 1, (int) $bar['height'] ) : $current['bar']['height'];

			if ( ! in_array( $current['bar']['style'], self::bar_styles(), true ) ) {
				$current['bar']['style'] = 'gradient';
			}
		}

		if ( isset( $input['ajax'] ) && is_array( $input['ajax'] ) ) {
			$current['ajax']['loading'] = empty( $input['ajax']['loading'] ) ? 0 : 1;
			$current['ajax']['fading']  = empty( $input['ajax']['fading'] ) ? 0 : 1;
		}

		if ( isset( $input['sort'] ) && is_array( $input['sort'] ) ) {
			foreach ( self::choices() as $path => $allowed ) {
				list( , $key ) = explode( '.', $path );
				if ( isset( $input['sort'][ $key ] ) && in_array( $input['sort'][ $key ], $allowed, true ) ) {
					$current['sort'][ $key ] = $input['sort'][ $key ];
				}
			}
		}

		if ( isset( $input['archive'] ) && is_array( $input['archive'] ) ) {
			$archive = $input['archive'];
			if ( isset( $archive['per_page'] ) ) {
				$current['archive']['per_page'] = max( 1, (int) $archive['per_page'] );
			}
			if ( isset( $archive['display_poll'] ) ) {
				$current['archive']['display_poll'] = (int) $archive['display_poll'];
			}
			if ( isset( $archive['url'] ) ) {
				$current['archive']['url'] = esc_url_raw( wp_strip_all_tags( trim( $archive['url'] ) ) );
			}
		}

		foreach ( array( 'current_poll', 'close', 'logging_method', 'cookie_expiry', 'allow_to_vote' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = (int) $input[ $key ];
			}
		}

		if ( isset( $input['ip_header'] ) ) {
			$current['ip_header'] = sanitize_text_field( $input['ip_header'] );
		}

		if ( isset( $input['stats_display'] ) ) {
			$current['stats_display'] = ! empty( $input['stats_display'] );
		}

		// --- Poll Templates screen -----------------------------------------
		if ( isset( $input['templates'] ) && is_array( $input['templates'] ) ) {
			foreach ( WP_Polls_Options::template_keys() as $key ) {
				if ( ! isset( $input['templates'][ $key ] ) ) {
					continue;
				}
				$current['templates'][ $key ] = self::sanitize_template( $key, $input['templates'][ $key ] );
			}
		}

		return $current;
	}

	/**
	 * The poll bar styles the Poll Options screen offers.
	 *
	 * Up to 3.0.0 this globbed images/ for directories holding a pollbg.gif, so
	 * the list of styles was whatever happened to be on disk and every entry
	 * shipped its own hardcoded colour. The shading is CSS now, so there are
	 * exactly two: a flat fill, and the same fill under a translucent gradient.
	 *
	 * @return array
	 */
	public static function bar_styles() {
		return array( 'flat', 'gradient' );
	}

	/**
	 * Filter one template through kses.
	 *
	 * The footer templates need input and anchor elements carrying
	 * data-poll-action, which wp_kses_post() alone does not allow on input.
	 * onclick stays disallowed - removing it is what 3.0.0 was for.
	 *
	 * @param string $key      Template slug.
	 * @param string $template Submitted markup.
	 * @return string
	 */
	public static function sanitize_template( $key, $template ) {
		$template = trim( (string) $template );

		$needs_input = array( 'votebody', 'votefooter', 'resultfooter', 'resultfooter2' );
		if ( ! in_array( $key, $needs_input, true ) ) {
			return wp_kses_post( $template );
		}

		$allowed          = wp_kses_allowed_html( 'post' );
		$allowed['input'] = array(
			'type'             => true,
			'id'               => true,
			'name'             => true,
			'value'            => true,
			'class'            => true,
			'data-poll-id'     => true,
			'data-poll-action' => true,
		);

		return wp_kses( $template, $allowed );
	}
}
