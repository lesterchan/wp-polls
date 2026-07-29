<?php
/**
 * The checks every one of these plugins carries.
 *
 * None of them is about polls. They are the shared contract - readme shape,
 * header fields, option rows, directory layout - and they exist so that a
 * plugin drifting away from its siblings fails a test rather than being
 * noticed a year later by a reader.
 *
 * @package WP-Polls
 */

/**
 * Metadata and layout checks shared by all nineteen plugins.
 */
class WP_Polls_Metadata_Test extends WP_Polls_TestCase {

	/**
	 * Whether uninstall.php ran and the tables need putting back.
	 *
	 * @var bool
	 */
	protected $uninstalled = false;

	/**
	 * Put the tables back after the uninstall test dropped them.
	 *
	 * DROP TABLE is not rolled back with the rest of the fixture, because MySQL
	 * commits DDL, so this is the one test that has to clean up after itself.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();

		if ( $this->uninstalled ) {
			WP_Polls_Install::activate();
			$this->uninstalled = false;
		}
	}

	/**
	 * The plugin file, as text.
	 *
	 * @return string
	 */
	protected function plugin_file() {
		return file_get_contents( WP_POLLS_DIR . 'wp-polls.php' );
	}

	/**
	 * The readme, as text.
	 *
	 * @return string
	 */
	protected function readme() {
		return file_get_contents( WP_POLLS_DIR . 'README.md' );
	}

	/**
	 * The nine readme header lines, in order.
	 *
	 * @return array
	 */
	protected function readme_header() {
		$lines  = explode( "\n", $this->readme() );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}
			$header[] = $line;
		}

		return $header;
	}

	/**
	 * One field out of the plugin file header.
	 *
	 * @param string $field Field name, without the colon.
	 * @return string
	 */
	protected function header_field( $field ) {
		preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m', $this->plugin_file(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One field out of the readme header.
	 *
	 * @param string $field Field name, without the colon.
	 * @return string
	 */
	protected function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = $this->readme_header();

		$this->assertCount( 9, $header, 'The readme header is nine fields.' );

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				'"' . trim( $line ) . '" needs two trailing spaces or the line break is lost'
			);
		}

		$last = $header[8];
		$this->assertSame( rtrim( $last ), $last, 'The last header line takes no trailing spaces.' );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame( 'https://lesterchan.net/portfolio/programming/php/', $this->header_field( 'Plugin URI' ) );
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI' ) );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->header_field( 'License URI' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI' ) );
	}

	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors' ), 'Contributors is GamerZ and nothing else.' );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-polls', $this->header_field( 'Text Domain' ) );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path' ) );
		$this->assertSame( 'wp-polls', WP_POLLS_SLUG );
	}

	public function test_version_matches_everywhere() {
		$this->assertSame( WP_POLLS_VERSION, $this->header_field( 'Version' ), 'The plugin header disagrees with WP_POLLS_VERSION.' );
		$this->assertSame( WP_POLLS_VERSION, $this->readme_field( 'Stable tag' ), 'The readme stable tag disagrees with WP_POLLS_VERSION.' );
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->header_field( 'Requires at least' ) );
		$this->assertSame( '6.8', $this->readme_field( 'Requires at least' ) );
		$this->assertSame( '8.2', $this->header_field( 'Requires PHP' ) );
		$this->assertSame( '8.2', $this->readme_field( 'Requires PHP' ) );
	}

	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## .+$/m', $this->readme(), $matches );

		$this->assertSame(
			array(
				'## Description',
				'## Usage',
				'## Frequently Asked Questions',
				'## Screenshots',
				'## Changelog',
				'## Upgrade Notice',
			),
			array_map( 'rtrim', $matches[0] ),
			'The level two headings are a closed set, in this order.'
		);
	}

	public function test_changelog_prefixes_are_canonical() {
		$allowed  = array( 'BREAKING:', 'NEW:', 'CHANGED:', 'FIXED:', 'NOTE:' );
		$readme   = $this->readme();
		$section  = substr( $readme, strpos( $readme, '## Changelog' ) );
		$section  = substr( $section, 0, strpos( $section, '## Upgrade Notice' ) );
		$offences = array();

		foreach ( explode( "\n", $section ) as $line ) {
			if ( 0 !== strpos( $line, '* ' ) ) {
				continue;
			}

			$body = substr( $line, 2 );
			foreach ( $allowed as $prefix ) {
				if ( 0 === strpos( $body, $prefix ) ) {
					continue 2;
				}
			}

			$offences[] = substr( $body, 0, 60 );
		}

		$this->assertSame( array(), $offences, 'Changelog bullets start with one of: ' . implode( ' ', $allowed ) );
	}

	public function test_no_jquery_is_enqueued() {
		WP_Polls::poll_scripts();
		WP_Polls_Admin::poll_scripts_admin( WP_Polls_Admin::hook_suffix( WP_Polls_Admin::PAGE ) );

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, 'wp-polls' ) ) {
				continue;
			}

			$this->assertNotContains( 'jquery', $script->deps, $handle . ' depends on jQuery' );
		}
	}

	public function test_every_directory_has_an_index_php() {
		$skip    = array( 'node_modules', 'vendor', '.git', '.github', 'languages' );
		$missing = array();

		$directories = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( WP_POLLS_DIR, FilesystemIterator::SKIP_DOTS ),
				static function ( $current ) use ( $skip ) {
					return ! $current->isDir() || ! in_array( $current->getFilename(), $skip, true );
				}
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $directories as $entry ) {
			if ( ! $entry->isDir() || 0 === strpos( $entry->getFilename(), '.' ) ) {
				continue;
			}

			if ( ! file_exists( $entry->getPathname() . '/index.php' ) ) {
				$missing[] = str_replace( WP_POLLS_DIR, '', $entry->getPathname() );
			}
		}

		$this->assertSame( array(), $missing, 'Every shipped directory needs a silence-is-golden index.php.' );
	}

	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_Polls_Options::save( WP_Polls_Options::defaults() );
		WP_Polls_Options::save_markers( WP_POLLS_VERSION, WP_POLLS_DB_VERSION );

		$this->uninstalled = true;
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-polls/wp-polls.php' );
		}
		require_once WP_POLLS_DIR . 'uninstall.php';

		// The multisite half of this is the same run under
		// phpunit-multisite.xml.dist: uninstall.php loops over get_sites() and
		// this assertion then covers whichever site the run is on.
		$survivors = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\_polls\_%'"
		);

		$this->assertSame( array(), $survivors, 'Uninstall left rows behind: ' . implode( ', ', $survivors ) );
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_Polls_Options::save_markers( WP_POLLS_VERSION, WP_POLLS_DB_VERSION );

		$row = get_option( WP_Polls_Options::VERSION );

		$this->assertIsArray( $row, 'The marker row is an array.' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $row ), 'The marker row holds these two keys and nothing else.' );
	}

	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_Polls_Settings::sanitize(
			array(
				'archive'    => array( 'per_page' => 9 ),
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $marker ) {
			$this->assertArrayNotHasKey(
				$marker,
				$clean,
				'A version marker must never be written into the settings row; that is what wp_polls_version is for.'
			);
		}

		$this->assertSame( 9, (int) $clean['archive']['per_page'], 'The sanitiser still cleans what the form did post.' );
	}

	public function test_no_rtl_stylesheet_is_registered() {
		WP_Polls::poll_scripts();

		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( 0 !== strpos( $handle, 'wp-polls' ) ) {
				continue;
			}

			$this->assertFalse( wp_styles()->get_data( $handle, 'rtl' ), $handle . ' registers rtl style data' );
			$this->assertStringNotContainsString( '-rtl.css', $style->src, $handle . ' points at an RTL stylesheet' );
		}

		$this->assertSame( array(), glob( WP_POLLS_DIR . 'css/*-rtl.css' ), 'No plugin ships an RTL stylesheet.' );
	}
}
