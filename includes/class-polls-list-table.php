<?php
/**
 * The Manage Polls list.
 *
 * Up to 3.0.0 this was a hand written <table class="widefat"> that selected
 * every poll in one query and printed nine columns per row, three of them
 * holding nothing but a link. It is a WP_List_Table now, so the screen gets
 * pagination, sortable columns and hover row actions from core, and looks like
 * every other list in wp-admin.
 *
 * @package WP-Polls
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists the polls on the Manage Polls screen.
 */
class Polls_List_Table extends WP_List_Table {

	/**
	 * Polls shown per page.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * The poll the site is currently showing, or 0 when that is the latest one.
	 *
	 * @var int
	 */
	protected $current_poll = 0;

	/**
	 * The newest poll's id, which is what "Display Latest Poll" resolves to.
	 *
	 * @var int
	 */
	protected $latest_poll = 0;

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'poll',
				'plural'   => 'polls',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The columns, in display order.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'pollq_id'          => __( 'ID', 'wp-polls' ),
			'pollq_question'    => __( 'Question', 'wp-polls' ),
			'pollq_totalvoters' => __( 'Total Voters', 'wp-polls' ),
			'pollq_timestamp'   => __( 'Start Date/Time', 'wp-polls' ),
			'pollq_expiry'      => __( 'End Date/Time', 'wp-polls' ),
			'pollq_active'      => __( 'Status', 'wp-polls' ),
		);
	}

	/**
	 * The columns the header can sort on.
	 *
	 * Only real columns of the questions table, because the value is spliced
	 * into an ORDER BY. self::order_by() will not accept anything else.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'pollq_id'          => array( 'pollq_id', false ),
			'pollq_totalvoters' => array( 'pollq_totalvoters', false ),
			'pollq_timestamp'   => array( 'pollq_timestamp', true ),
		);
	}

	/**
	 * The column the row actions hang off.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'pollq_question';
	}

	/**
	 * Shown in place of the table body when there are no polls.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No Polls Found', 'wp-polls' );
	}

	/**
	 * Read one page of polls.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$this->current_poll = (int) Polls_Options::get( 'current_poll' );
		$this->latest_poll  = (int) Polls_Options::get( 'latest_poll' );

		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->pollsq" );
		$order_by = $this->order_by();
		$offset   = ( $this->get_pagenum() - 1 ) * self::PER_PAGE;

		// $order_by is built from a whitelist in self::order_by(); the values
		// interpolated here are never taken from the request.
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->pollsq ORDER BY $order_by LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::PER_PAGE,
				$offset
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);

		// Set rather than left to get_column_info(), which would read the columns
		// back through get_column_headers(). That function caches per screen id
		// for the whole request, and wp-admin/admin-header.php has already asked
		// it for this screen - to build the Screen Options panel - by the time a
		// plugin page file runs. The answer it cached is the empty array, because
		// this table, and so the manage_{screen}_columns filter its constructor
		// adds, did not exist yet: the table rendered as rows with no cells.
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			$this->get_default_primary_column_name(),
		);
	}

	/**
	 * The ORDER BY clause the request asked for, or the default.
	 *
	 * @return string
	 */
	protected function order_by() {
		// Reading which column to sort on. Nothing is written on this request,
		// and both values are checked against a whitelist below.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$direction = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$sortable = $this->get_sortable_columns();
		if ( ! isset( $sortable[ $requested ] ) ) {
			return 'pollq_timestamp DESC';
		}

		return $requested . ' ' . $direction;
	}

	/**
	 * Whether this poll is the one the site is showing.
	 *
	 * @param object $item Poll row.
	 * @return bool
	 */
	protected function is_displayed( $item ) {
		$poll_id = (int) $item->pollq_id;

		if ( $this->current_poll > 0 ) {
			return $this->current_poll === $poll_id;
		}

		// -1 disables the poll and -2 picks a random one, so neither has a row
		// to point at. 0 means the latest poll.
		return 0 === $this->current_poll && $poll_id === $this->latest_poll;
	}

	/**
	 * One row, carrying the id the delete script removes it by.
	 *
	 * @param object $item Poll row.
	 * @return void
	 */
	public function single_row( $item ) {
		$classes = $this->is_displayed( $item ) ? 'wp-polls-displayed' : '';

		echo '<tr id="poll-' . (int) $item->pollq_id . '" class="' . esc_attr( $classes ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * The poll id.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_id( $item ) {
		return '<strong>' . esc_html( number_format_i18n( (int) $item->pollq_id ) ) . '</strong>';
	}

	/**
	 * The question, plus the row actions.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_question( $item ) {
		$poll_id  = (int) $item->pollq_id;
		$question = removeslashes( $item->pollq_question );

		$column = '';
		if ( $this->is_displayed( $item ) ) {
			$column .= '<strong>' . esc_html__( 'Displayed:', 'wp-polls' ) . '</strong> ';
		}
		$column .= wp_kses_post( $question );

		/* translators: %s: The poll question. */
		$confirm = sprintf( __( 'You are about to delete this poll, \'%s\'.', 'wp-polls' ), $question );

		$actions = array(
			'edit'   => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::page_url( array( 'mode' => 'edit' ), $poll_id ) ),
				esc_html__( 'Edit', 'wp-polls' )
			),
			'logs'   => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::page_url( array( 'mode' => 'logs' ), $poll_id ) ),
				esc_html__( 'Logs', 'wp-polls' )
			),
			'delete' => sprintf(
				'<a href="#DeletePoll" class="submitdelete" data-poll-action="delete-poll" data-poll-id="%1$d" data-poll-confirm="%2$s" data-poll-nonce="%3$s">%4$s</a>',
				$poll_id,
				esc_attr( $confirm ),
				esc_attr( wp_create_nonce( 'wp-polls_delete-poll' ) ),
				esc_html__( 'Delete', 'wp-polls' )
			),
		);

		return $column . $this->row_actions( $actions );
	}

	/**
	 * How many people voted.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_totalvoters( $item ) {
		return esc_html( number_format_i18n( (int) $item->pollq_totalvoters ) );
	}

	/**
	 * When the poll opened.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_timestamp( $item ) {
		return esc_html( self::format_date( $item->pollq_timestamp ) );
	}

	/**
	 * When the poll closes, if it ever does.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_expiry( $item ) {
		$expiry = trim( $item->pollq_expiry );

		if ( empty( $expiry ) ) {
			return esc_html__( 'No Expiry', 'wp-polls' );
		}

		return esc_html( self::format_date( $expiry ) );
	}

	/**
	 * Open, closed, or not started yet.
	 *
	 * @param object $item Poll row.
	 * @return string
	 */
	public function column_pollq_active( $item ) {
		switch ( (int) $item->pollq_active ) {
			case 1:
				return esc_html__( 'Open', 'wp-polls' );
			case -1:
				return esc_html__( 'Future', 'wp-polls' );
			default:
				return esc_html__( 'Closed', 'wp-polls' );
		}
	}

	/**
	 * A timestamp in the site's configured date and time format.
	 *
	 * @param int|string $timestamp Unix timestamp.
	 * @return string
	 */
	public static function format_date( $timestamp ) {
		return mysql2date(
			/* translators: 1: The site's date format, 2: the site's time format. */
			sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ),
			gmdate( 'Y-m-d H:i:s', (int) $timestamp )
		);
	}

	/**
	 * A URL back to the Manage Polls screen, optionally in one of its modes.
	 *
	 * @param array $args    Extra query args.
	 * @param int   $poll_id Poll the mode applies to, 0 for the list itself.
	 * @return string
	 */
	public static function page_url( $args = array(), $poll_id = 0 ) {
		$args = array_merge( array( 'page' => WP_POLLS_SLUG . '/polls-manager.php' ), $args );

		if ( $poll_id ) {
			$args['id'] = (int) $poll_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Site-wide totals for the stats panel under the list.
	 *
	 * Summed in SQL rather than while printing rows: the list is paginated now,
	 * so a running total would only ever count the page being looked at.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;

		$totals = $wpdb->get_row( "SELECT COUNT(*) AS polls, SUM(pollq_totalvotes) AS votes, SUM(pollq_totalvoters) AS voters FROM $wpdb->pollsq" );

		return array(
			'polls'   => (int) $totals->polls,
			'answers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->pollsa" ),
			'votes'   => (int) $totals->votes,
			'voters'  => (int) $totals->voters,
		);
	}
}
