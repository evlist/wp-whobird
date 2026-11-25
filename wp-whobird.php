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

require_once __DIR__ . '/includes/plugin-setup.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/config.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-tools.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/WhoBirdActivator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/AjaxBirdListItemRefresher.php';
register_activation_hook( __FILE__, [ 'WhoBirdActivator', 'activate' ] );
require_once plugin_dir_path( __FILE__ ) . 'includes/WhobirdMappingTableManager.php';
register_activation_hook( __FILE__, 'whobirdMappingTableInit' );
require_once __DIR__ . '/includes/ajax-generate-mapping.php';
