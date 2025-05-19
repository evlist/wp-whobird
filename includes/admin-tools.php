<?php
// Main admin tools script for whoBIRD plugin

if (!defined('ABSPATH')) exit;

// Add a submenu under Tools (not a top-level menu)
add_action('admin_menu', function() {
    add_management_page(
        'whoBIRD tools',
        'whoBIRD tools',
        'manage_options',
        'whobird-admin-tools',
        'whobird_admin_tools_page'
    );
});

function whobird_admin_tools_page() {
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <img src="<?php echo esc_url(plugins_url('../build/assets/images/whoBIRD.svg', __FILE__)); ?>" alt="whoBIRD" style="height:32px;width:auto;vertical-align:middle;">
            <?php echo esc_html__('whoBIRD tools', 'wpwhobird'); ?>
        </h1>
        <?php
        // Cache Tools Section
        include_once __DIR__ . '/cache-tools.php';

        // Mapping Tools Section
        include_once __DIR__ . '/bird-mappings.php';
        ?>
    </div>
    <?php
}
