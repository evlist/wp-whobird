<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * WhoBird Admin Tools
 *
 * Registers and renders the admin tools submenu page for the whoBIRD plugin,
 * providing access to cache and mapping management tools from the WordPress admin.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

// Main admin tools script for whoBIRD plugin

if (!defined('ABSPATH')) exit;

// Add a submenu under Tools (not a top-level menu)
add_action('admin_menu', function() {
    add_management_page(
        __('whoBIRD tools', 'wp-whobird'),
        __('whoBIRD tools', 'wp-whobird'),
        'manage_options',
        'whobird-admin-tools',
        'whobird_admin_tools_page'
    );
});

/**
 * Renders the whoBIRD admin tools page, including cache and mapping tools.
 */
function whobird_admin_tools_page() {
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <img src="<?php echo esc_url(plugins_url('../resources/images/whoBIRD.svg', __FILE__)); ?>" alt="whoBIRD" style="height:32px;width:auto;vertical-align:middle;">
            <?php echo esc_html__('whoBIRD tools', 'wp-whobird'); ?>
        </h1>
        <?php
        // Cache Tools Section
        include_once __DIR__ . '/cache-tools.php';

        // Mapping Tools Section
        include_once __DIR__ . '/bird-mappings-ui.php';
        ?>
    </div>
    <?php
}
