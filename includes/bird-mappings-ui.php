<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Mapping sources UI for WhoBird admin panel.
 * 
 * Provides a table interface for managing, updating, and importing mapping sources,
 * and allows for AJAX-powered mapping table generation and export.
 * 
 * This file assumes it runs in a WordPress admin context.
 */

if (!defined('ABSPATH')) exit;

global $WHOBIRD_MAPPING_SOURCES;
global $WHOBIRD_MAPPING_TABLE;

require_once __DIR__ . '/bird-mappings.php';

// Ensure WP_List_Table is available for custom table rendering in admin.
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Admin table for displaying and managing mapping sources.
 */
class WhoBIRD_Mapping_Sources_Table extends WP_List_Table {
    private $sources_cfg;
    private $table_name;
    private $source_instances;

    /**
     * Constructor for the mapping sources table.
     *
     * @param array $sources_cfg Source configuration.
     * @param string $table_name Mapping table name.
     * @param array $source_instances Instantiated source objects.
     */
    function __construct($sources_cfg, $table_name, $source_instances) {
        parent::__construct([
            'singular' => 'mapping_source',
            'plural'   => 'mapping_sources',
            'ajax'     => false
        ]);
        $this->sources_cfg = $sources_cfg;
        $this->table_name = $table_name;
        $this->source_instances = $source_instances;
    }

    /**
     * Define table columns.
     *
     * @return array
     */
    function get_columns() {
        return [
            'name'        => __('Source Name', 'wp-whobird'),
            'description' => __('Description', 'wp-whobird'),
            'local_commit' => __('Stored Commit', 'wp-whobird'),
            'local_update' => __('Last Downloaded', 'wp-whobird'),
            'remote_commit' => __('GitHub Commit', 'wp-whobird'),
            'status'      => __('Status', 'wp-whobird'),
            'actions'     => __('Actions', 'wp-whobird'),
            'update_table' => __('Update Table', 'wp-whobird'),
        ];
    }

    // Column rendering methods for each column in the table.
    function column_name($item) {
        return esc_html($item['label']);
    }
    function column_description($item) {
        return esc_html($item['description']);
    }
    function column_local_commit($item) {
        if ($item['local_commit_sha']) {
            return substr($item['local_commit_sha'], 0, 8) . '<br><small>' . esc_html($item['local_commit_date']) . '</small>';
        } else {
            return '<span style="color:#bbb;">' . esc_html__('(none)', 'wp-whobird') . '</span>';
        }
    }
    function column_local_update($item) {
        return $item['local_update'] ? esc_html($item['local_update']) : '<span style="color:#bbb;">' . esc_html__('(never)', 'wp-whobird') . '</span>';
    }
    function column_remote_commit($item) {
        if ($item['remote_commit_sha']) {
            return substr($item['remote_commit_sha'], 0, 8) . '<br><small>' . esc_html($item['remote_commit_date']) . '</small>';
        } else {
            return '<span style="color:#bbb;">' . esc_html__('(unknown)', 'wp-whobird') . '</span>';
        }
    }
    function column_status($item) {
        if (!$item['local_commit_sha'] && $item['key'] !== 'wikidata_species') {
            return '<span style="color:#bbb;">' . esc_html__('Not downloaded', 'wp-whobird') . '</span>';
        }
        if ($item['key'] === 'wikidata_species') {
            return $item['local_update']
                ? '<span style="color:green;">' . esc_html__('Up to date', 'wp-whobird') . '</span>'
                : '<span style="color:#bbb;">' . esc_html__('Not downloaded', 'wp-whobird') . '</span>';
        }
        if ($item['is_new']) {
            return '<span style="color:orange;font-weight:bold;">' . esc_html__('New version available!', 'wp-whobird') . '</span>';
        }
        return '<span style="color:green;">' . esc_html__('Up to date', 'wp-whobird') . '</span>';
    }
    function column_actions($item) {
        // Button to update the source file.
        $btn = '<button type="submit" name="update_' . esc_attr($item['key']) . '" class="button">' . esc_html__('Update', 'wp-whobird') . '</button>';
        return $btn;
    }
    function column_update_table($item) {
        // Button to update the mapping table from the selected source.
        return '<button type="submit" name="update_table_' . esc_attr($item['key']) . '" class="button">' . esc_html__('Update Table', 'wp-whobird') . '</button>';
    }

    /**
     * Prepares the data for the table.
     */
    function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = [];
        foreach ($this->source_instances as $key => $srcObj) {
            $this->items[] = $srcObj->getStatus();
        }
    }
}

// ---- Instantiate source objects for use/display/update ----
$whobird_source_instances = [];
foreach ($WHOBIRD_MAPPING_SOURCES as $key => $cfg) {
    $whobird_source_instances[$key] = whobird_get_source_instance($key, $cfg, $WHOBIRD_MAPPING_TABLE);
}

// ---- Handle POST actions for updating sources or mapping tables ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
    foreach ($whobird_source_instances as $key => $srcObj) {
        // Handle source file update.
        if (isset($_POST['update_' . $key])) {
            list($ok, $msg) = $srcObj->update();
            if ($ok) {
                echo '<div class="updated notice"><p>' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'] . ': ' . __($msg, 'wp-whobird')) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'] . ': ' . __($msg, 'wp-whobird')) . '</p></div>';
            }
        }
        // Handle mapping table update from source.
        if (isset($_POST['update_table_' . $key])) {
            list($ok, $msg) = $srcObj->uploadToTable();
            if ($ok) {
                echo '<div class="updated notice"><p>' . sprintf(
                    // Translators: %s is mapping source label.
                    esc_html__('Update table (%s): ', 'wp-whobird'), esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'])
                ) . esc_html__($msg, 'wp-whobird') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
                    esc_html__('Update table (%s): ', 'wp-whobird'), esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'])
                ) . esc_html__($msg, 'wp-whobird') . '</p></div>';
            }
        }
    }
}
?>
<div id="mapping-tool-wrapper" style="margin-top:2em;">
    <!-- Collapsible admin panel section for maintainers -->
    <button type="button" class="collapsible"><?php echo esc_html__('For maintainers: Mapping Sources', 'wp-whobird'); ?></button>
    <div class="content" style="display:none;">
        <h2><?php echo esc_html__('Mapping Sources', 'wp-whobird'); ?></h2>
        <form method="POST">
            <?php
                $mapping_table = new WhoBIRD_Mapping_Sources_Table($WHOBIRD_MAPPING_SOURCES, $WHOBIRD_MAPPING_TABLE, $whobird_source_instances);
                $mapping_table->prepare_items();
                $mapping_table->display();
            ?>
        </form>
        <!-- AJAX-powered mapping table generation button and status list -->
        <button id="whobird-generate-mapping-btn" class="button button-primary"><?php echo esc_html__('Generate/Update mapping table', 'wp-whobird'); ?></button>
        <ul id="whobird-mapping-steps-status" style="margin-top:1em"></ul>
        <?php
        $export_url = admin_url('admin-post.php?action=whobird_export_mapping_json');
        ?>
        <p>
            <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">
                <?php echo esc_html__('Download Mapping Table (JSON)', 'wp-whobird'); ?>
            </a>
        </p>
        <p style="font-size:smaller;color:#888;margin-top:1em;">
            <?php echo esc_html__('Last commit = last change in the file’s GitHub repository. Last downloaded = when you last imported it.', 'wp-whobird'); ?><br>
            <?php echo esc_html__('For Wikidata, only last downloaded/imported is shown.', 'wp-whobird'); ?>
        </p>
    </div>
</div>
<script>
/**
 * Collapsible section toggle for the mapping sources panel.
 */
document.querySelectorAll('.collapsible').forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.toggle('active');
        let content = this.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    });
});
</script>
<style>
#mapping-tool-wrapper .content {padding: 1em;}
.collapsible {background: #f5f5f5; border:1px solid #ccc; padding: 8px 16px; cursor: pointer;}
.collapsible.active, .collapsible:focus {background: #e2eaff;}
</style>
