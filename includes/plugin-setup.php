<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Hooks that register the block, its scripts and styles, the admin script and the text domain.
 *
 * Coexistence with other plugins and themes: every hook below is added with an
 * explicit priority (10, the WordPress default, on purpose: nothing here has to run
 * before or after anybody else). Handles are prefixed (`wpwbd-` or `wpwhobird-`) so
 * they cannot be taken by, or take the place of, a handle of another plugin; in
 * particular FontAwesome is registered as `wpwhobird-fontawesome`, not `font-awesome`.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the styles, the script handles and the block (no build step: the files in
 * blocks/wp-whobird are loaded as they are).
 *
 * The handles are referenced by name in block.json; the script dependencies are
 * declared here by hand. FontAwesome is a style of the block (block.json), so WordPress
 * loads it only on pages that show the block, and in the editor.
 */
function wpwbd_wp_whobird_block_init() {
    $plugin_dir = dirname( __DIR__ );
    $plugin_url = plugin_dir_url( $plugin_dir . '/wp-whobird.php' );
    $block_dir  = $plugin_dir . '/blocks/wp-whobird';
    $block_url  = $plugin_url . 'blocks/wp-whobird';

    wp_register_style(
        'wpwhobird-fontawesome',
        $plugin_url . 'third-party/fontawesome/css/all.min.css',
        [],
        '6.7.2'
    );

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
        wp_set_script_translations( $handle, 'wp-whobird', $plugin_dir . '/languages' );
    }

    // The front-end script needs the `ajaxurl` global (it is only defined by WordPress in the admin).
    // Attached to the handle, it is printed whenever the script is, however late it is enqueued.
    wp_add_inline_script(
        'wpwbd-wp-whobird-view-script',
        sprintf( 'var ajaxurl = %s;', wp_json_encode( admin_url( 'admin-ajax.php' ) ) ),
        'before'
    );

    register_block_type( $block_dir );
}
add_action( 'init', 'wpwbd_wp_whobird_block_init', 10 );

/**
 * Enqueues the script of the mapping tool on the whoBIRD tools admin page only.
 *
 * @param string $hook Suffix of the admin page being displayed.
 */
function wpwhobird_enqueue_admin_scripts( $hook ) {
    if ( 'tools_page_whobird-admin-tools' !== $hook ) {
        return;
    }
    $plugin_dir = dirname( __DIR__ );
    $plugin_url = plugin_dir_url( $plugin_dir . '/wp-whobird.php' );
    $file       = 'blocks/wp-whobird/admin-mapping.js';

    wp_enqueue_script(
        'wpwhobird-admin-mapping',
        $plugin_url . $file,
        [ 'wp-i18n' ],
        (string) filemtime( $plugin_dir . '/' . $file ),
        true
    );
    wp_set_script_translations( 'wpwhobird-admin-mapping', 'wp-whobird', $plugin_dir . '/languages' );
    wp_localize_script(
        'wpwhobird-admin-mapping',
        'wpwhobirdMappingVars',
        [
            'nonce'   => wp_create_nonce( 'whobird-generate-mapping' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'wpwhobird_enqueue_admin_scripts', 10 );

/**
 * Load the plugin text domain for translations.
 */
function wp_whobird_load_textdomain() {
    load_plugin_textdomain( 'wp-whobird', false, dirname( plugin_basename( dirname( __DIR__ ) . '/wp-whobird.php' ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'wp_whobird_load_textdomain', 10 );
