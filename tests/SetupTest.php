<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/plugin-setup.php';

/**
 * Tests of slice 007: block registration, assets, hooks, no debug logging.
 */
class SetupTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$GLOBALS['wpwbd_test_calls'] = array();
		$this->root                  = dirname( __DIR__ );
	}

	private function calls( string $function ): array {
		return $GLOBALS['wpwbd_test_calls'][ $function ] ?? array();
	}

	private function handles( string $function ): array {
		return array_column( $this->calls( $function ), 0 );
	}

	public function test_hooks_are_added_with_an_explicit_priority(): void {
		foreach ( array( 'init', 'admin_enqueue_scripts', 'plugins_loaded' ) as $hook ) {
			$this->assertContains( 10, $GLOBALS['wpwbd_test_priorities'][ $hook ], $hook );
		}
		$this->assertContains( 'wpwbd_wp_whobird_block_init', $GLOBALS['wpwbd_test_actions']['init'] );
	}

	public function test_block_init_registers_styles_scripts_and_the_block(): void {
		wpwbd_wp_whobird_block_init();

		$this->assertSame( array( 'wpwhobird-fontawesome' ), $this->handles( 'wp_register_style' ) );
		$this->assertSame(
			array( 'wpwbd-wp-whobird-editor-script', 'wpwbd-wp-whobird-view-script' ),
			$this->handles( 'wp_register_script' )
		);
		$this->assertSame( array( $this->root . '/blocks/wp-whobird' ), $this->calls( 'register_block_type' )[0] );
		$this->assertSame(
			array( 'wpwbd-wp-whobird-editor-script', 'wpwbd-wp-whobird-view-script' ),
			$this->handles( 'wp_set_script_translations' )
		);
	}

	public function test_script_dependencies_are_declared(): void {
		wpwbd_wp_whobird_block_init();
		$deps = array_column( $this->calls( 'wp_register_script' ), 2, 0 );
		$this->assertEqualsCanonicalizing( array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ), $deps['wpwbd-wp-whobird-editor-script'] );
		$this->assertSame( array( 'wp-i18n' ), $deps['wpwbd-wp-whobird-view-script'] );
	}

	public function test_registered_files_exist(): void {
		wpwbd_wp_whobird_block_init();
		foreach ( $this->calls( 'wp_register_script' ) as $call ) {
			$relative = substr( $call[1], strlen( 'http://example.test/wp-content/plugins/wp-whobird/' ) );
			$this->assertFileExists( $this->root . '/' . $relative, $call[0] );
		}
		$css = $this->calls( 'wp_register_style' )[0][1];
		$this->assertFileExists( $this->root . '/' . substr( $css, strlen( 'http://example.test/wp-content/plugins/wp-whobird/' ) ) );
	}

	public function test_ajaxurl_is_attached_to_the_view_script_before_it(): void {
		wpwbd_wp_whobird_block_init();
		$call = $this->calls( 'wp_add_inline_script' )[0];
		$this->assertSame( 'wpwbd-wp-whobird-view-script', $call[0] );
		$this->assertSame( 'var ajaxurl = "http:\/\/example.test\/wp-admin\/admin-ajax.php";', $call[1] );
		$this->assertSame( 'before', $call[2] );
	}

	public function test_fontawesome_is_a_style_of_the_block_not_a_global_enqueue(): void {
		wpwbd_wp_whobird_block_init();
		$json = json_decode( file_get_contents( $this->root . '/blocks/wp-whobird/block.json' ), true );
		$this->assertContains( 'wpwhobird-fontawesome', $json['style'] );
		$this->assertArrayNotHasKey( 'enqueue_block_assets', $GLOBALS['wpwbd_test_actions'] );
		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', $GLOBALS['wpwbd_test_actions'], 'no debug or inline hook on every front-end request' );
		$this->assertNotContains( 'font-awesome', $this->handles( 'wp_register_style' ), 'handle shared with other plugins' );
	}

	public function test_admin_script_is_loaded_on_the_tools_page_only(): void {
		wpwhobird_enqueue_admin_scripts( 'index.php' );
		wpwhobird_enqueue_admin_scripts( 'settings_page_wpwhobird-settings' );
		$this->assertSame( array(), $this->calls( 'wp_enqueue_script' ) );

		wpwhobird_enqueue_admin_scripts( 'tools_page_whobird-admin-tools' );
		$this->assertSame( array( 'wpwhobird-admin-mapping' ), $this->handles( 'wp_enqueue_script' ) );
		$vars = $this->calls( 'wp_localize_script' )[0];
		$this->assertSame( 'wpwhobirdMappingVars', $vars[1] );
		$this->assertSame( 'nonce-for-whobird-generate-mapping', $vars[2]['nonce'], 'the nonce checked by the AJAX handler' );
	}

	public function test_text_domain_is_loaded_from_the_languages_directory(): void {
		wp_whobird_load_textdomain();
		$this->assertSame( array( 'wp-whobird', false, 'wp-whobird/languages/' ), $this->calls( 'load_plugin_textdomain' )[0] );
	}

	public function test_no_debug_logging_is_left(): void {
		$files = array_merge( glob( $this->root . '/includes/*.php' ), array( $this->root . '/wp-whobird.php' ) );
		foreach ( $files as $file ) {
			$code = file_get_contents( $file );
			$this->assertDoesNotMatchRegularExpression( '/microtime\(true\)\s*-\s*\$start/', $code, basename( $file ) );
			$this->assertDoesNotMatchRegularExpression( '#^\s*(//|\#)\s*error_log#m', $code, basename( $file ) . ' (commented debug)' );
			$this->assertStringNotContainsString( 'Enqueued script handle', $code, basename( $file ) );
		}
	}

	public function test_main_file_only_loads_the_parts(): void {
		$main = file_get_contents( $this->root . '/wp-whobird.php' );
		$this->assertStringNotContainsString( 'add_action', $main );
		$this->assertStringContainsString( "includes/plugin-setup.php", $main );
	}
}
