<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Test bootstrap: minimal stubs of the WordPress functions used by the renderer.
 * Nothing here runs against a real WordPress.
 *
 * @package WPWhoBird
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/' );

// The plugin logs on purpose (debug timings); keep the test output clean.
ini_set( 'error_log', '/dev/null' );

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		return $text;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) {
		return json_encode( $data, $flags );
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'fr_FR';
	}
}
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'http://example.test/wp-content/plugins/wp-whobird/' . $path;
	}
}

// ---- Capability, nonce, JSON answers: decided by the tests through these globals. ----
$GLOBALS['wpwbd_test_can']      = true;      // Answer of current_user_can().
$GLOBALS['wpwbd_test_nonce_ok'] = true;      // Whether check_*_referer() passes.
$GLOBALS['wpwbd_test_actions']  = array();   // Hooks registered with add_action(), by hook name.

/** Thrown by wp_send_json_success()/wp_send_json_error() (the real ones end the request). */
class WpTestJsonResponse extends Exception {
	public $success;
	public $data;
	public $status;
}

/** Thrown by wp_die() and by a failing nonce check (the real ones end the request). */
class WpTestDie extends Exception {
	public $status;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['wpwbd_test_actions'][ $hook ][] = $callback;
}
function current_user_can( $capability ) {
	return $GLOBALS['wpwbd_test_can'];
}
function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
	if ( ! $GLOBALS['wpwbd_test_nonce_ok'] ) {
		throw new WpTestDie( 'bad nonce (ajax)' );
	}
	return 1;
}
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( ! $GLOBALS['wpwbd_test_nonce_ok'] ) {
		throw new WpTestDie( 'bad nonce (admin)' );
	}
	return 1;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return ( $GLOBALS['wpwbd_test_nonce_ok'] && 'good-nonce' === $nonce ) ? 1 : false;
}
function wp_send_json_success( $data = null, $status = null ) {
	$e          = new WpTestJsonResponse( 'json success' );
	$e->success = true;
	$e->data    = $data;
	$e->status  = $status;
	throw $e;
}
function wp_send_json_error( $data = null, $status = null ) {
	$e          = new WpTestJsonResponse( 'json error' );
	$e->success = false;
	$e->data    = $data;
	$e->status  = $status;
	throw $e;
}
function wp_die( $message = '', $title = '', $args = array() ) {
	$e         = new WpTestDie( (string) $message );
	$e->status = is_array( $args ) ? ( $args['response'] ?? null ) : null;
	throw $e;
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function __( $text, $domain = 'default' ) {
	return $text;
}
function plugin_dir_path( $file ) {
	// Points to tests/stubs so that require_once plugin_dir_path( __FILE__ ) . '/X.php' finds the stubs.
	return __DIR__ . '/stubs/';
}

/** Minimal $wpdb: records queries, answers with what the test sets. */
class WpTestDb {
	public $prefix       = 'wp_';
	public $queries      = array();
	public $query_result = 0;
	public $var_result   = null;

	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( '%d', '%s', $query ), array_map( 'intval', $args ) );
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		return $this->query_result;
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		return $this->var_result;
	}
}

// The renderer does require_once 'WikidataQuery.php' and 'ImageUtils.php' by relative
// name, which PHP resolves through include_path first: tests/stubs shadows the real files.
set_include_path( __DIR__ . '/stubs' . PATH_SEPARATOR . get_include_path() );

// Stand-ins for the collaborators of BirdListItemRenderer, set by each test.
$GLOBALS['wpwbd_test_mapping'] = null;
$GLOBALS['wpwbd_test_cache']   = false;

/**
 * Stand-in for the mapping table lookup (global namespace, as in the plugin).
 *
 * @param int $birdnetId BirdNET id.
 * @return array|null
 */
function getMappingByBirdnetId( int $birdnetId ): ?array {
	return $GLOBALS['wpwbd_test_mapping'];
}

