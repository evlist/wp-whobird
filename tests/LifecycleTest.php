<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;
use WPWhoBird\Uninstaller;

require_once dirname( __DIR__ ) . '/includes/config.php';
require_once dirname( __DIR__ ) . '/includes/Uninstaller.php';

/**
 * Tests of slice 006: uninstall, text domain.
 */
class LifecycleTest extends TestCase {

	private string $lock;

	protected function setUp(): void {
		$GLOBALS['wpdb']                       = new WpTestDb();
		$GLOBALS['wpwbd_test_options']         = array();
		$GLOBALS['wpwbd_test_deleted_options'] = array();
		$GLOBALS['wpwbd_test_sites']           = null;
		$GLOBALS['wpwbd_test_blog_switches']   = array();
		$this->lock                            = sys_get_temp_dir() . '/throttle_wikidata.lock';
	}

	protected function tearDown(): void {
		@unlink( $this->lock );
	}

	private function expectedTables( string $prefix ): array {
		return array(
			"DROP TABLE IF EXISTS `{$prefix}whobird_sparql_cache`",
			"DROP TABLE IF EXISTS `{$prefix}whobird_mapping`",
			"DROP TABLE IF EXISTS `{$prefix}whobird_remote_files`",
		);
	}

	public function test_uninstall_drops_the_tables_and_deletes_the_options(): void {
		Uninstaller::run();
		$this->assertSame( $this->expectedTables( 'wp_' ), $GLOBALS['wpdb']->queries );
		$this->assertEqualsCanonicalizing(
			array(
				'wpwhobird_recordings_path',
				'wpwhobird_database_path',
				'wpwhobird_threshold',
				'wpwhobird_should_generate_text_when_no_observations',
				'wpwhobird_fallback_languages',
				'whobird_fetched_wikidata_qids',
			),
			$GLOBALS['wpwbd_test_deleted_options']
		);
	}

	public function test_every_option_the_plugin_registers_is_removed_on_uninstall(): void {
		( new \WPWhoBird\WhoBirdAdminSettings() )->registerSettings();
		foreach ( array_keys( $GLOBALS['wpwbd_test_registered'] ) as $option ) {
			$this->assertContains( $option, Uninstaller::optionNames(), $option );
		}
	}

	public function test_uninstall_removes_the_throttle_lock_file(): void {
		touch( $this->lock );
		Uninstaller::run();
		$this->assertFileDoesNotExist( $this->lock );
	}

	public function test_uninstall_does_not_touch_the_uploads_directory(): void {
		$dir = sys_get_temp_dir() . '/wpwbd-uninstall-' . uniqid();
		mkdir( $dir . '/WhoBird/recordings', 0777, true );
		touch( $dir . '/WhoBird/recordings/1.wav' );
		$GLOBALS['wpwbd_test_uploads'] = array( 'basedir' => $dir, 'baseurl' => 'http://example.test/uploads' );
		Uninstaller::run();
		$this->assertFileExists( $dir . '/WhoBird/recordings/1.wav' );
		exec( 'rm -rf ' . escapeshellarg( $dir ) );
	}

	public function test_uninstall_cleans_every_site_of_a_network(): void {
		$GLOBALS['wpwbd_test_sites'] = array( 1, 2 );
		Uninstaller::run();
		$this->assertSame( array( 'switch:1', 'restore', 'switch:2', 'restore' ), $GLOBALS['wpwbd_test_blog_switches'] );
		$this->assertSame(
			array_merge( $this->expectedTables( 'wp_1_' ), $this->expectedTables( 'wp_2_' ) ),
			$GLOBALS['wpdb']->queries
		);
	}

	public function test_uninstall_php_does_nothing_outside_the_uninstall_process(): void {
		$output = shell_exec( 'php ' . escapeshellarg( dirname( __DIR__ ) . '/uninstall.php' ) . ' 2>&1' );
		$this->assertSame( '', trim( (string) $output ) );
	}

	public function test_uninstall_php_runs_the_uninstaller(): void {
		$script = sys_get_temp_dir() . '/wpwbd-run-uninstall-' . uniqid() . '.php';
		file_put_contents(
			$script,
			'<?php require ' . var_export( __DIR__ . '/bootstrap.php', true ) . ';'
			. '$GLOBALS["wpdb"] = new WpTestDb(); define("WP_UNINSTALL_PLUGIN", "wp-whobird/wp-whobird.php");'
			. 'require ' . var_export( dirname( __DIR__ ) . '/uninstall.php', true ) . ';'
			. 'echo json_encode($GLOBALS["wpdb"]->queries);'
		);
		$queries = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $script ) ), true );
		unlink( $script );
		$this->assertSame( $this->expectedTables( 'wp_' ), $queries );
	}

	// ---- Text domain ---------------------------------------------------------------------

	public function test_plugin_header_declares_the_text_domain(): void {
		$header = file_get_contents( dirname( __DIR__ ) . '/wp-whobird.php', false, null, 0, 2000 );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Text Domain:\s*wp-whobird\s*$/m', $header );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Domain Path:\s*\/languages\s*$/m', $header );
	}

	public function test_all_translation_calls_use_the_plugin_text_domain(): void {
		$pattern = '/\b(?:esc_html__|esc_attr__|__|_e|esc_html_e|esc_attr_e)\((?:[^()]|\([^()]*\))*?,\s*\'([^\']+)\'\s*\)/';
		$count   = 0;
		$files   = array_merge( glob( dirname( __DIR__ ) . '/includes/*.php' ), array( dirname( __DIR__ ) . '/wp-whobird.php', dirname( __DIR__ ) . '/blocks/wp-whobird/render.php' ) );
		foreach ( $files as $file ) {
			preg_match_all( $pattern, file_get_contents( $file ), $m );
			foreach ( $m[1] as $domain ) {
				$this->assertSame( 'wp-whobird', $domain, basename( $file ) );
				++$count;
			}
		}
		$this->assertGreaterThan( 50, $count );
	}
}
