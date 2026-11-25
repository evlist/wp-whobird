<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Plugin Name: whoBIRD observations
 * Description: Display your whoBIRD observations in WordPress posts and pages using a custom Gutenberg block.
 * Requires at least: 6.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * License File: LICENSES/GPL-3.0-or-later.txt
 * Version: 1.0.0
 * Contributors: @evlist
 * Tags: block, birds, observations, gutenberg, taxonomy
 * Text Domain: wp-whobird
 * Domain Path: /languages
 */
/**
 *
 * @package Wpwbd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the script handles and the block (no build step: the files in
 * blocks/wp-whobird are loaded as they are).
 *
 * The handles are referenced by name in block.json; their script dependencies
 * are declared here by hand.
 */
function wpwbd_wp_whobird_block_init() {
    $block_dir = __DIR__ . '/blocks/wp-whobird';
    $block_url = plugin_dir_url( __FILE__ ) . 'blocks/wp-whobird';

    $scripts = [
        'wpwbd-wp-whobird-editor-script' => [
            'file' => 'index.js',
            'deps' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
        ],
        'wpwbd-wp-whobird-view-script'   => [
            'file' => 'view.js',
            'deps' => [ 'wp-i18n' ],
        ],
    ];
    foreach ( $scripts as $handle => $script ) {
        wp_register_script(
            $handle,
            $block_url . '/' . $script['file'],
            $script['deps'],
            (string) filemtime( $block_dir . '/' . $script['file'] ),
            true
        );
        wp_set_script_translations( $handle, 'wp-whobird', __DIR__ . '/languages' );
    }

    register_block_type( $block_dir );
}
add_action( 'init', 'wpwbd_wp_whobird_block_init' );

/**
 * Enqueue the FontAwesome CSS locally.
 */
function wpwhobird_enqueue_fontawesome_local() {
    wp_enqueue_style(
            'font-awesome',
            plugin_dir_url( __FILE__ ) . 'third-party/fontawesome/css/all.min.css',
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
                plugins_url('blocks/wp-whobird/admin-mapping.js', __FILE__),
                [ 'wp-i18n' ],
                filemtime(plugin_dir_path(__FILE__) . 'blocks/wp-whobird/admin-mapping.js'),
                true
                );
        wp_set_script_translations( 'wpwhobird-admin-mapping', 'wp-whobird', plugin_dir_path( __FILE__ ) . 'languages' );
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
require_once plugin_dir_path( __FILE__ ) . 'includes/WhoBirdActivator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/AjaxBirdListItemRefresher.php';
register_activation_hook( __FILE__, [ 'WhoBirdActivator', 'activate' ] );
require_once plugin_dir_path( __FILE__ ) . 'includes/WhobirdMappingTableManager.php';
register_activation_hook( __FILE__, 'whobirdMappingTableInit' );
require_once __DIR__ . '/includes/ajax-generate-mapping.php';
