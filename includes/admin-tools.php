<?php
// Main admin tools script for whoBIRD plugin

if (!defined('ABSPATH')) exit;

// Add submenu under whoBIRD or as a top-level menu if needed
add_action('admin_menu', function() {
    add_menu_page(
        'whoBIRD Admin Tools',
        'whoBIRD Tools',
        'manage_options',
        'whobird-admin-tools',
        'whobird_admin_tools_page',
        'dashicons-admin-tools'
    );
});

function whobird_admin_tools_page() {
    echo '<div class="wrap"><h1>whoBIRD Admin Tools</h1>';

    // Cache Tools Section
    include_once __DIR__ . '/cache-tools.php';

    // Mapping Tools Section
    include_once __DIR__ . '/bird-mappings.php';

    echo '</div>';
}
