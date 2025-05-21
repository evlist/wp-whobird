<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/bird-mappings.php';

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WhoBIRD_Mapping_Sources_Table extends WP_List_Table {
    private $sources_cfg;
    private $table_name;
    private $source_instances;

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

    function get_columns() {
        return [
            'name'        => 'Source Name',
            'description' => 'Description',
            'local_commit' => 'Stored Commit',
            'local_update' => 'Last Downloaded',
            'remote_commit' => 'GitHub Commit',
            'status'      => 'Status',
            'actions'     => 'Actions',
            'update_table' => 'Update Table',
        ];
    }

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
            return '<span style="color:#bbb;">(none)</span>';
        }
    }
    function column_local_update($item) {
        return $item['local_update'] ? esc_html($item['local_update']) : '<span style="color:#bbb;">(never)</span>';
    }
    function column_remote_commit($item) {
        if ($item['remote_commit_sha']) {
            return substr($item['remote_commit_sha'], 0, 8) . '<br><small>' . esc_html($item['remote_commit_date']) . '</small>';
        } else {
            return '<span style="color:#bbb;">(unknown)</span>';
        }
    }
    function column_status($item) {
        if (!$item['local_commit_sha'] && $item['key'] !== 'wikidata_species') {
            return '<span style="color:#bbb;">Not downloaded</span>';
        }
        if ($item['key'] === 'wikidata_species') {
            return $item['local_update']
                ? '<span style="color:green;">Up to date</span>'
                : '<span style="color:#bbb;">Not downloaded</span>';
        }
        if ($item['is_new']) {
            return '<span style="color:orange;font-weight:bold;">New version available!</span>';
        }
        return '<span style="color:green;">Up to date</span>';
    }
    function column_actions($item) {
        $btn = '<button type="submit" name="update_' . esc_attr($item['key']) . '" class="button">Update</button>';
        return $btn;
    }
    function column_update_table($item) {
        // All sources should get this button as per next steps
        return '<button type="submit" name="update_table_' . esc_attr($item['key']) . '" class="button">Update Table</button>';
    }
    function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = [];
        foreach ($this->source_instances as $key => $srcObj) {
            $this->items[] = $srcObj->getStatus();
        }
    }
}

// ---- Instantiate source objects ----
$whobird_source_instances = [];
foreach ($WHOBIRD_MAPPING_SOURCES as $key => $cfg) {
    $whobird_source_instances[$key] = whobird_get_source_instance($key, $cfg, $WHOBIRD_MAPPING_TABLE);
}

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
    foreach ($whobird_source_instances as $key => $srcObj) {
        if (isset($_POST['update_' . $key])) {
            list($ok, $msg) = $srcObj->update();
            if ($ok) {
                echo '<div class="updated notice"><p>' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'] . ': ' . $msg) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label'] . ': ' . $msg) . '</p></div>';
            }
        }
        if (isset($_POST['update_table_' . $key])) {
            list($ok, $msg) = $srcObj->uploadToTable();
            if ($ok) {
                echo '<div class="updated notice"><p>Update table (' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label']) . '): ' . esc_html($msg) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Update table (' . esc_html($WHOBIRD_MAPPING_SOURCES[$key]['label']) . '): ' . esc_html($msg) . '</p></div>';
            }
        }
    }
}
?>
<div id="mapping-tool-wrapper" style="margin-top:2em;">
    <button type="button" class="collapsible">For maintainers: Mapping Sources</button>
    <div class="content" style="display:none;">
        <h2>Mapping Sources</h2>
        <form method="POST">
            <?php
                $mapping_table = new WhoBIRD_Mapping_Sources_Table($WHOBIRD_MAPPING_SOURCES, $WHOBIRD_MAPPING_TABLE, $whobird_source_instances);
                $mapping_table->prepare_items();
                $mapping_table->display();
            ?>
        </form>
        <form method="post" action="" style="margin-top:2em;">
            <?php wp_nonce_field('whobird-generate-mapping'); ?>
            <button type="submit" name="whobird_generate_mapping_table" class="button button-primary">
                <?php esc_html_e('Generate/Update Mapping Table', 'wp-whobird'); ?>
            </button>
        </form>
        <p style="font-size:smaller;color:#888;margin-top:1em;">
            Last commit = last change in the file’s GitHub repository. Last downloaded = when you last imported it.<br>
            For Wikidata, only last downloaded/imported is shown.
        </p>
    </div>
</div>
<script>
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
<?php
// Handle the new mapping table generation form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['whobird_generate_mapping_table']) && current_user_can('manage_options')) {
    check_admin_referer('whobird-generate-mapping');
    whobird_generate_mapping_table();
    echo '<div class="updated notice"><p>' . esc_html__('Mapping table has been (re)generated successfully.', 'wp-whobird') . '</p></div>';
}
?>
