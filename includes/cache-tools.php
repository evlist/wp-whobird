<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

use WPWhoBird\Config;

// Inline cache tool for modular admin page
function whobird_render_cache_tools_section() {
    // Handle form submission
    if (
        isset($_POST['wpwhobird_clear_cache']) &&
        check_admin_referer('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce')
    ) {
        global $wpdb;
        $table_name = Config::getTableSparqlCache();
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        if ($result === false) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to clear the cache table. Please check the table configuration.', 'wpwhobird') . '</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache table has been cleared successfully!', 'wpwhobird') . '</p></div>';
        }
    }
    ?>
    <div id="cache-tool-wrapper" style="margin-bottom:2em;">
        <h2><?php echo esc_html__('Cache Tool', 'wpwhobird'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce'); ?>
            <p><?php echo esc_html__('Click the button below to clear the WhoBird cache table.', 'wpwhobird'); ?></p>
            <input type="submit" name="wpwhobird_clear_cache" class="button button-primary" value="<?php echo esc_attr__('Clear Cache', 'wpwhobird'); ?>">
        </form>
    </div>
    <?php
}

whobird_render_cache_tools_section();
