<?php
/**
 * SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @license   GPL-3.0-or-later
 * 
 * Cache management tools for the WhoBird plugin.
 * 
 * This file provides a UI section and logic for clearing the WhoBird cache table from the WordPress admin.
 * - Handles cache clearing form submission and nonce verification.
 * - Provides user feedback on cache clearance.
 * - Outputs a form/button to trigger cache clearing.
 */

namespace WPWhoBird;

use WPWhoBird\Config;

/**
 * Renders the cache tools section for the WhoBird admin page.
 *
 * - Handles cache clearing form submission.
 * - Provides user feedback on cache table clearance.
 * - Outputs a form/button to trigger cache clearing.
 */
function whobird_render_cache_tools_section() {
    // Handle cache clearing form submission with nonce verification.
    if (
        isset($_POST['wpwhobird_clear_cache']) &&
        check_admin_referer('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce')
    ) {
        global $wpdb;
        $table_name = Config::getTableSparqlCache();
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        if ($result === false) {
            // Display error notice if cache clearing fails.
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to clear the cache table. Please check the table configuration.', 'wp-whobird') . '</p></div>';
        } else {
            // Display success notice if cache clearance is successful.
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache table has been cleared successfully!', 'wp-whobird') . '</p></div>';
        }
    }
    ?>
    <div id="cache-tool-wrapper" style="margin-bottom:2em;">
        <h2><?php echo esc_html__('Cache Tool', 'wp-whobird'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce'); ?>
            <p><?php echo esc_html__('Click the button below to clear the WhoBird cache table.', 'wp-whobird'); ?></p>
            <input type="submit" name="wpwhobird_clear_cache" class="button button-primary" value="<?php echo esc_attr__('Clear Cache', 'wp-whobird'); ?>">
        </form>
    </div>
    <?php
}

// Render the cache tools section immediately.
whobird_render_cache_tools_section();
