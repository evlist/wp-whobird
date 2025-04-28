<?php
namespace WPWhoBird;

use WPWhoBird\Config;

class WhoBirdAdminTools
{
    public function __construct()
    {
        // Add the tool page to the WordPress admin menu
        add_action('admin_menu', [$this, 'addToolPage']);
    }

    /**
     * Add a new tool page under Tools menu in WordPress admin.
     */
    public function addToolPage()
    {
        add_management_page(
            __('WhoBird Cache Tool', 'wpwhobird'), // Page title
            __('WhoBird Cache Tool', 'wpwhobird'), // Menu title
            'manage_options',                      // Capability
            'wpwhobird-cache-tool',                // Slug
            [$this, 'renderToolPage']              // Callback function
        );
    }

    /**
     * Render the tool page content.
     */
    public function renderToolPage()
    {
        // Check if the user has clicked the clear cache button
        if (isset($_POST['wpwhobird_clear_cache']) && check_admin_referer('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce')) {
            $this->clearCacheTable();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache table has been cleared successfully!', 'wpwhobird') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WhoBird Cache Tool', 'wpwhobird'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('wpwhobird_clear_cache_action', 'wpwhobird_clear_cache_nonce'); ?>
                <p><?php echo esc_html__('Click the button below to clear the WhoBird cache table.', 'wpwhobird'); ?></p>
                <input type="submit" name="wpwhobird_clear_cache" class="button button-primary" value="<?php echo esc_attr__('Clear Cache', 'wpwhobird'); ?>">
            </form>
        </div>
        <?php
    }

    /**
     * Clear the WhoBird cache table.
     */
    private function clearCacheTable()
    {
        global $wpdb;

        // Define the cache table name
        $table_name = $wpdb->prefix . Config::TABLE_SPARQL_CACHE;

        // Clear the cache table
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
}

// Initialize the admin tools
new WhoBirdAdminTools();
