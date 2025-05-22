<?php
/**
 * Plugin Name:       WhoBIRD observations
 * Description:       Display your WhoBIRD observations.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-whobird
 *
 * @package Wpwbd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 */
function wpwbd_wp_whobird_block_init() {
    if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
        wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
        return;
    }

    if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
        wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
    }

    $manifest_data = require __DIR__ . '/build/blocks-manifest.php';
    foreach ( array_keys( $manifest_data ) as $block_type ) {
        register_block_type( __DIR__ . "/build/{$block_type}" );
    }
}
add_action( 'init', 'wpwbd_wp_whobird_block_init' );

/**
 * Enqueue the FontAwesome CSS locally.
 */
function wpwhobird_enqueue_fontawesome_local() {
    wp_enqueue_style(
        'font-awesome',
        plugin_dir_url( __FILE__ ) . 'build/css/all.min.css',
        [],
        '6.7.2' // Update to the correct version
    );
}
add_action( 'enqueue_block_assets', 'wpwhobird_enqueue_fontawesome_local' );

/**
 * Add the `ajaxurl` global variable for the front-end scripts.
 */

function wpwhobird_add_ajaxurl_inline_script() {
    // wpwbd-wp-whobird-view-script
    $script_handle = 'wpwbd-wp-whobird-view-script'; // Adjust this handle if needed
    if ( wp_script_is( $script_handle, 'enqueued' ) ) {
        $data = sprintf(
            'var ajaxurl = %s;',
            json_encode( admin_url( 'admin-ajax.php' ) )
        );
        wp_add_inline_script( $script_handle, $data, 'before' );
    } else {
        error_log( "Script handle {$script_handle} is NOT enqueued." );
    }
}
add_action( 'wp_enqueue_scripts', 'wpwhobird_add_ajaxurl_inline_script', 20 );

function wpwhobird_debug_scripts() {
    global $wp_scripts;
    foreach ( $wp_scripts->queue as $handle ) {
        error_log( "Enqueued script handle: {$handle}" );
    }
}
add_action( 'wp_enqueue_scripts', 'wpwhobird_debug_scripts' );

/*
function wpwhobird_localize_script() {
    $script_handle = 'wpwbd-wp-whobird-view-script';
    wp_localize_script(
        $script_handle,
        'wpWhoBirdConfig',
        [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ]
    );
}
add_action( 'wp_enqueue_scripts', 'wpwhobird_localize_script' );
*/
/*
add_action('wp_enqueue_scripts', function () {
    // Add pre-loaded data for my-namespace/my-block
    wp_add_inline_script('wpwbd-wp-whobird-view-script', 'ajaxurl = ' . admin_url( 'admin-ajax.php'
    ), 'before');
});
*/

add_action('admin_enqueue_scripts', function($hook) {
    // You can restrict to your page using $hook here if you want
   if ($hook === 'tools_page_whobird-admin-tools') {
        // error_log($hook);
        wp_enqueue_script(
            'wpwhobird-admin-mapping',
            plugins_url('build/wp-whobird/admin-mapping.js', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . 'build/wp-whobird/admin-mapping.js'),
            true
        );
        wp_localize_script(
            'wpwhobird-admin-mapping',
            'wpwhobirdMappingVars',
            [
                'nonce' => wp_create_nonce('whobird-generate-mapping'),
                'ajaxurl' => admin_url('admin-ajax.php'),
            ]
        );
    }
});

/**
 * Load the plugin text domain for translations.
 */
function wp_whobird_load_textdomain() {
    load_plugin_textdomain( 'wp-whobird', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'wp_whobird_load_textdomain' );

require_once plugin_dir_path( __FILE__ ) . 'includes/config.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-tools.php';
require_once plugin_dir_path( __FILE__ ) . 'build/wp-whobird/lib/WhoBirdActivator.php';
require_once plugin_dir_path( __FILE__ ) . 'build/wp-whobird/lib/AjaxBirdListItemRefresher.php';
register_activation_hook( __FILE__, [ 'WhoBirdActivator', 'activate' ] );
require_once plugin_dir_path( __FILE__ ) . 'build/wp-whobird/lib/TaxoCodeTableManager.php';
register_activation_hook( __FILE__, 'taxoCodeTableInit' );
require_once __DIR__ . '/includes/ajax-generate-mapping.php';
