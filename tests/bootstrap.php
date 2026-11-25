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

