<?php
/**
 * Poll Utility Class
 *
 * @package WP-Polls
 * @since 2.78.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Polls_Utility Class
 *
 * Provides utility functions for the WP-Polls plugin.
 *
 * @package WP-Polls
 * @since 2.78.0
 */
class WP_Polls_Utility {

	/**
	 * Get the raw IP address of the current user.
	 *
	 * @since 2.78.0
	 * @return string The IP address.
	 */
	public static function get_raw_ip_address() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		
		// Check custom header if configured.
		$poll_options = get_option( 'poll_options' );
		if ( ! empty( $poll_options ) && ! empty( $poll_options['ip_header'] ) && isset( $_SERVER[ $poll_options['ip_header'] ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $poll_options['ip_header'] ] ) );
		}

		return $ip;
	}

	/**
	 * Get the hashed IP address of the current user.
	 *
	 * @since 2.78.0
	 * @return string The hashed IP address.
	 */
	public static function get_ip_address() {
		return apply_filters( 'wp_polls_ipaddress', wp_hash( self::get_raw_ip_address() ) );
	}

	/**
	 * Get the hostname of the current user.
	 *
	 * @since 2.78.0
	 * @return string The hostname.
	 */
	public static function get_hostname() {
		$ip = self::get_raw_ip_address();
		$hostname = gethostbyaddr( $ip );
		
		if ( $hostname === $ip ) {
			$hostname = wp_privacy_anonymize_ip( $ip );
		}

		if ( false !== $hostname ) {
			$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
		}

		return apply_filters( 'wp_polls_hostname', $hostname );
	}

	/**
	 * Remove slashes from a string.
	 *
	 * @since 2.78.0
	 * @param string $string The string to remove slashes from.
	 * @return string The string with slashes removed.
	 */
	public static function remove_slashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}

	/**
	 * Display date/time selection elements for poll timestamp editing.
	 *
	 * @since 2.78.0
	 * @param int    $poll_timestamp The timestamp to edit.
	 * @param string $fieldname      The base name for the form fields.
	 * @param string $display        CSS display property value.
	 * @return void
	 */
	public static function datetime_selector( $poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block' ) {
		// Define localized month names.
		$months = array(
			1  => __( 'January' ),
			2  => __( 'February' ),
			3  => __( 'March' ),
			4  => __( 'April' ),
			5  => __( 'May' ),
			6  => __( 'June' ),
			7  => __( 'July' ),
			8  => __( 'August' ),
			9  => __( 'September' ),
			10 => __( 'October' ),
			11 => __( 'November' ),
			12 => __( 'December' ),
		);

		echo '<div id="' . esc_attr( $fieldname ) . '" style="display: ' . esc_attr( $display ) . '">' . "\n";

		// Day.
		$day = (int) gmdate( 'j', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_day">' . "\n";
		for ( $i = 1; $i <= 31; $i++ ) {
			echo '<option value="' . esc_attr( $i ) . '"' . selected( $day, $i, false ) . '>' . esc_html( $i ) . '</option>' . "\n";
		}
		echo '</select>&nbsp;&nbsp;' . "\n";

		// Month.
		$month_num = (int) gmdate( 'n', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_month">' . "\n";
		foreach ( $months as $i => $month_name ) {
			echo '<option value="' . esc_attr( $i ) . '"' . selected( $month_num, $i, false ) . '>' . esc_html( $month_name ) . '</option>' . "\n";
		}
		echo '</select>&nbsp;&nbsp;' . "\n";

		// Year.
		$poll_year = (int) gmdate( 'Y', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_year">' . "\n";
		for ( $i = 2000; $i <= ( $poll_year + 10 ); $i++ ) {
			echo '<option value="' . esc_attr( $i ) . '"' . selected( $poll_year, $i, false ) . '>' . esc_html( $i ) . '</option>' . "\n";
		}
		echo '</select>&nbsp;@' . "\n";

		// Time.
		echo '<span dir="ltr">' . "\n";

		// Hour.
		$hour = (int) gmdate( 'H', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_hour">' . "\n";
		for ( $i = 0; $i < 24; $i++ ) {
			printf(
				'<option value="%s"%s>%02s</option>' . "\n",
				esc_attr( $i ),
				selected( $hour, $i, false ),
				esc_html( $i )
			);
		}
		echo '</select>&nbsp;:' . "\n";

		// Minute.
		$minute = (int) gmdate( 'i', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_minute">' . "\n";
		for ( $i = 0; $i < 60; $i++ ) {
			printf(
				'<option value="%s"%s>%02d</option>' . "\n",
				esc_attr( $i ),
				selected( $minute, $i, false ),
				esc_html( $i )
			);
		}
		echo '</select>&nbsp;:' . "\n";

		// Second.
		$second = (int) gmdate( 's', $poll_timestamp );
		echo '<select name="' . esc_attr( $fieldname ) . '_second">' . "\n";
		for ( $i = 0; $i < 60; $i++ ) {
			printf(
				'<option value="%s"%s>%02d</option>' . "\n",
				esc_attr( $i ),
				selected( $second, $i, false ),
				esc_html( $i )
			);
		}
		echo '</select>' . "\n";

		echo '</span>' . "\n";
		echo '</div>' . "\n";
	}

	/**
	 * Create timestamp from form fields.
	 *
	 * @since 2.78.0
	 * @param array  $data      The POST data containing timestamp fields.
	 * @param string $fieldname The base field name.
	 * @return int The Unix timestamp.
	 */
	public static function create_timestamp_from_fields( $data, $fieldname = 'pollq_timestamp' ) {
		$year   = isset( $data[ $fieldname . '_year' ] ) ? (int) $data[ $fieldname . '_year' ] : 0;
		$month  = isset( $data[ $fieldname . '_month' ] ) ? (int) $data[ $fieldname . '_month' ] : 0;
		$day    = isset( $data[ $fieldname . '_day' ] ) ? (int) $data[ $fieldname . '_day' ] : 0;
		$hour   = isset( $data[ $fieldname . '_hour' ] ) ? (int) $data[ $fieldname . '_hour' ] : 0;
		$minute = isset( $data[ $fieldname . '_minute' ] ) ? (int) $data[ $fieldname . '_minute' ] : 0;
		$second = isset( $data[ $fieldname . '_second' ] ) ? (int) $data[ $fieldname . '_second' ] : 0;

		return gmmktime( $hour, $minute, $second, $month, $day, $year );
	}

	/**
	 * Get polls archive link with pagination.
	 *
	 * @since 2.78.0
	 * @param int $page The page number.
	 * @return string The archive URL with pagination parameters if needed.
	 */
	public static function get_archive_link( $page ) {
		$polls_archive_url = get_option( 'poll_archive_url' );
		
		if ( 0 < $page ) {
			if ( false !== strpos( $polls_archive_url, '?' ) ) {
				$polls_archive_url = $polls_archive_url . '&amp;poll_page=' . $page;
			} else {
				$polls_archive_url = $polls_archive_url . '?poll_page=' . $page;
			}
		}
		
		return $polls_archive_url;
	}

	/**
	 * Check if the current page is a poll archive page.
	 *
	 * @since 2.78.0
	 * @return bool True if current page is a poll archive, false otherwise.
	 */
	public static function is_poll_archive() {
		$poll_archive_url = get_option( 'poll_archive_url' );
		$poll_archive_url_array = explode( '/', $poll_archive_url );
		$poll_archive_url = $poll_archive_url_array[ count( $poll_archive_url_array ) - 1 ];
		
		if ( empty( $poll_archive_url ) ) {
			$poll_archive_url = $poll_archive_url_array[ count( $poll_archive_url_array ) - 2 ];
		}
		
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		
		if ( false === strpos( $current_url, (string) $poll_archive_url ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Acquire a lock for poll operations to prevent race conditions.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return resource|false File pointer resource on success, false on failure.
	 */
	public static function acquire_lock( $poll_id ) {
		$fp = fopen( self::get_lock_file( $poll_id ), 'w+' );

		if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
			return false;
		}

		ftruncate( $fp, 0 );
		fwrite( $fp, microtime( true ) );

		return $fp;
	}

	/**
	 * Release a previously acquired poll lock.
	 *
	 * @since 2.78.0
	 * @param resource $fp      The file pointer resource.
	 * @param int      $poll_id The poll ID.
	 * @return bool True on success, false on failure.
	 */
	public static function release_lock( $fp, $poll_id ) {
		if ( is_resource( $fp ) ) {
			fflush( $fp );
			flock( $fp, LOCK_UN );
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->delete( self::get_lock_file( $poll_id ) );
			return true;
		}
		return false;
	}

	/**
	 * Get the path to the lock file for a specific poll.
	 *
	 * @since 2.78.0
	 * @param int $poll_id The poll ID.
	 * @return string The full path to the lock file.
	 */
	public static function get_lock_file( $poll_id ) {
		return apply_filters( 'wp_polls_lock_file', get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-polls-' . $poll_id . '.lock', $poll_id );
	}
}
