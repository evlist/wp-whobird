<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/config.php';
require_once dirname( __DIR__ ) . '/includes/ajax-generate-mapping.php'; // Also loads bird-mappings.php.
require_once dirname( __DIR__ ) . '/includes/cache-tools.php';
require_once dirname( __DIR__ ) . '/includes/mapping-sources-actions.php';
require_once dirname( __DIR__ ) . '/includes/AjaxBirdListItemRefresher.php';

/**
 * Tests of slice 004: capability and nonce checks on every entry point.
 */
class SecurityTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpwbd_test_can']          = true;
		$GLOBALS['wpwbd_test_nonce_ok']     = true;
		$GLOBALS['wpwbd_test_cache']        = false;
		$GLOBALS['wpwbd_test_fetch_result'] = array( 'commonName' => 'Fetched', 'latinName' => 'Latin' );
		$GLOBALS['wpwbd_test_fetch_calls']  = array();
		$GLOBALS['wpwbd_test_wq_locales']   = array();
		$GLOBALS['wpdb']                    = new WpTestDb();
		$_POST                              = array();
	}

	/** Runs a callable that ends with a JSON answer, and returns the answer. */
	private function json( callable $fn ): WpTestJsonResponse {
		try {
			$fn();
		} catch ( WpTestJsonResponse $e ) {
			return $e;
		}
		$this->fail( 'No JSON answer was sent.' );
	}

	// ---- Registered entry points ------------------------------------------------------

	public function test_entry_points_are_registered(): void {
		$actions = $GLOBALS['wpwbd_test_actions'];
		$this->assertContains( 'whobird_ajax_generate_mapping_handler', $actions['wp_ajax_whobird_generate_mapping'] );
		$this->assertContains( 'whobird_export_mapping_json_handler', $actions['admin_post_whobird_export_mapping_json'] );
		$this->assertContains( 'WPWhoBird\\update_bird_data', $actions['wp_ajax_update_bird_data'] );
		$this->assertContains( 'WPWhoBird\\update_bird_data', $actions['wp_ajax_nopriv_update_bird_data'] );
	}

	// ---- 1. AJAX whobird_generate_mapping -------------------------------------------------

	public function test_generate_mapping_requires_the_capability(): void {
		$GLOBALS['wpwbd_test_can'] = false;
		$answer                    = $this->json( 'whobird_ajax_generate_mapping_handler' );
		$this->assertFalse( $answer->success );
		$this->assertSame( 403, $answer->status );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'no step may run' );
	}

	public function test_generate_mapping_requires_the_nonce(): void {
		$GLOBALS['wpwbd_test_nonce_ok'] = false;
		$this->expectException( WpTestDie::class );
		whobird_ajax_generate_mapping_handler();
	}

	public function test_generate_mapping_rejects_an_unknown_step(): void {
		$_POST  = array( 'step' => 'no_such_step' );
		$answer = $this->json( 'whobird_ajax_generate_mapping_handler' );
		$this->assertFalse( $answer->success );
		$this->assertSame( 'Unknown step.', $answer->data['msg'] );
	}

	// ---- 4. Export -------------------------------------------------------------------

	public function test_export_requires_the_capability(): void {
		$GLOBALS['wpwbd_test_can'] = false;
		try {
			whobird_export_mapping_json_handler();
			$this->fail( 'wp_die expected' );
		} catch ( WpTestDie $e ) {
			$this->assertSame( 403, $e->status );
		}
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'nothing may be read' );
	}

	// ---- 3. Cache tool -----------------------------------------------------------------

	public function test_clear_cache_ignores_requests_that_do_not_ask(): void {
		$this->assertSame( '', \WPWhoBird\clear_cache_if_requested( array() ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function test_clear_cache_requires_the_capability(): void {
		$GLOBALS['wpwbd_test_can'] = false;
		$html                      = \WPWhoBird\clear_cache_if_requested( array( 'wpwhobird_clear_cache' => '1' ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'nothing may be truncated' );
	}

	public function test_clear_cache_requires_the_nonce(): void {
		$GLOBALS['wpwbd_test_nonce_ok'] = false;
		try {
			\WPWhoBird\clear_cache_if_requested( array( 'wpwhobird_clear_cache' => '1' ) );
			$this->fail( 'nonce check expected to stop the request' );
		} catch ( WpTestDie $e ) {
			$this->assertSame( array(), $GLOBALS['wpdb']->queries );
		}
	}

	public function test_clear_cache_truncates_when_allowed(): void {
		$html = \WPWhoBird\clear_cache_if_requested( array( 'wpwhobird_clear_cache' => '1' ) );
		$this->assertSame( array( 'TRUNCATE TABLE wp_whobird_sparql_cache' ), $GLOBALS['wpdb']->queries );
		$this->assertStringContainsString( 'notice-success', $html );
	}

	public function test_clear_cache_reports_a_failure(): void {
		$GLOBALS['wpdb']->query_result = false;
		$html                          = \WPWhoBird\clear_cache_if_requested( array( 'wpwhobird_clear_cache' => '1' ) );
		$this->assertStringContainsString( 'notice-error', $html );
	}

	// ---- 2. Mapping sources -------------------------------------------------------------

	private function fakeSource( bool $ok = true, string $msg = 'Done' ): object {
		return new class( $ok, $msg ) {
			public $calls = array();
			private $ok;
			private $msg;
			public function __construct( $ok, $msg ) {
				$this->ok  = $ok;
				$this->msg = $msg;
			}
			public function update() {
				$this->calls[] = 'update';
				return array( $this->ok, $this->msg );
			}
			public function uploadToTable() {
				$this->calls[] = 'uploadToTable';
				return array( $this->ok, $this->msg );
			}
		};
	}

	private function nonce(): array {
		return array( WHOBIRD_MAPPING_SOURCES_NONCE_FIELD => 'good-nonce' );
	}

	public function test_sources_without_action_do_nothing(): void {
		$src = $this->fakeSource();
		$this->assertSame( '', whobird_process_mapping_sources_request( array(), array( 'gh' => array( 'label' => 'GH' ) ), array( 'gh' => $src ) ) );
		$this->assertSame( array(), $src->calls );
	}

	public function test_sources_require_the_capability(): void {
		$GLOBALS['wpwbd_test_can'] = false;
		$src                       = $this->fakeSource();
		$html                      = whobird_process_mapping_sources_request( $this->nonce() + array( 'update_gh' => '' ), array( 'gh' => array( 'label' => 'GH' ) ), array( 'gh' => $src ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertSame( array(), $src->calls );
	}

	public function test_sources_require_a_valid_nonce(): void {
		$src = $this->fakeSource();
		foreach ( array( array(), array( WHOBIRD_MAPPING_SOURCES_NONCE_FIELD => 'forged' ) ) as $nonce ) {
			$html = whobird_process_mapping_sources_request( $nonce + array( 'update_table_gh' => '' ), array( 'gh' => array( 'label' => 'GH' ) ), array( 'gh' => $src ) );
			$this->assertStringContainsString( 'notice-error', $html );
		}
		$this->assertSame( array(), $src->calls, 'no action without a valid nonce' );
	}

	public function test_sources_run_the_requested_action_when_allowed(): void {
		$gh = $this->fakeSource( true, 'Updated' );
		$wd = $this->fakeSource( false, 'Failed' );
		$html = whobird_process_mapping_sources_request(
			$this->nonce() + array( 'update_gh' => '', 'update_table_wd' => '' ),
			array( 'gh' => array( 'label' => 'GH' ), 'wd' => array( 'label' => 'WD' ) ),
			array( 'gh' => $gh, 'wd' => $wd )
		);
		$this->assertSame( array( 'update' ), $gh->calls );
		$this->assertSame( array( 'uploadToTable' ), $wd->calls );
		$this->assertStringContainsString( 'GH: Updated', $html );
		$this->assertStringContainsString( 'Update table (WD): Failed', $html );
	}

	// ---- 5. Public AJAX update_bird_data ----------------------------------------------------

	private function refresh(): WpTestJsonResponse {
		return $this->json( '\\WPWhoBird\\update_bird_data' );
	}

	public function test_refresh_rejects_a_missing_or_invalid_id(): void {
		foreach ( array( array(), array( 'birdnet_id' => '0' ), array( 'birdnet_id' => 'abc' ), array( 'birdnet_id' => '' ) ) as $post ) {
			$_POST  = $post;
			$answer = $this->refresh();
			$this->assertFalse( $answer->success, json_encode( $post ) );
		}
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'no query for an invalid id' );
	}

	public function test_refresh_rejects_an_id_without_wikidata_qid(): void {
		$_POST                         = array( 'birdnet_id' => '42' );
		$GLOBALS['wpdb']->var_result = null;
		$this->assertFalse( $this->refresh()->success );
		$this->assertSame( array(), $GLOBALS['wpwbd_test_fetch_calls'] );
	}

	public function test_refresh_of_a_fresh_entry_uses_the_cache_only(): void {
		$_POST                         = array( 'birdnet_id' => '42' );
		$GLOBALS['wpdb']->var_result = 'Q25471';
		$GLOBALS['wpwbd_test_cache']   = array( 'isFresh' => true, 'data' => array( 'commonName' => 'Cached', 'latinName' => 'L' ) );
		$answer                        = $this->refresh();
		$this->assertTrue( $answer->success );
		$this->assertStringContainsString( 'Cached', $answer->data['html'] );
		$this->assertSame( array(), $GLOBALS['wpwbd_test_fetch_calls'], 'no Wikidata request for a fresh entry' );
	}

	public function test_refresh_of_a_stale_entry_fetches_once(): void {
		$_POST                         = array( 'birdnet_id' => '42' );
		$GLOBALS['wpdb']->var_result = 'Q25471';
		$GLOBALS['wpwbd_test_cache']   = array( 'isFresh' => false, 'data' => array( 'commonName' => 'Old' ) );
		$answer                        = $this->refresh();
		$this->assertTrue( $answer->success );
		$this->assertStringContainsString( 'Fetched', $answer->data['html'] );
		$this->assertSame( array( array( 42, 'Q25471' ) ), $GLOBALS['wpwbd_test_fetch_calls'] );
	}

	public function test_refresh_ignores_the_locale_sent_by_the_browser(): void {
		$_POST                         = array( 'birdnet_id' => '42', 'locale' => 'xx_XX' );
		$GLOBALS['wpdb']->var_result = 'Q25471';
		$this->refresh();
		$this->assertNotEmpty( $GLOBALS['wpwbd_test_wq_locales'] );
		$this->assertSame( array( 'fr_FR' ), array_unique( $GLOBALS['wpwbd_test_wq_locales'] ), 'the site locale is used' );
	}
}
