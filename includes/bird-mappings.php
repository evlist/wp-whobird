<?php
if (!defined('ABSPATH')) exit;

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WhoBIRD_Mapping_Sources_Table extends WP_List_Table {
    private $sources;

    function __construct($sources) {
        parent::__construct([
            'singular' => 'mapping_source',
            'plural'   => 'mapping_sources',
            'ajax'     => false
        ]);
        $this->sources = $sources;
    }

    function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'name'        => 'Source Name',
            'description' => 'Description',
            'last_update' => 'Last Update',
            'actions'     => 'Actions'
        ];
    }

    function column_cb($item) {
        return sprintf('<input type="checkbox" name="selected_sources[]" value="%s" />', esc_attr($item['id']));
    }

    function column_name($item) {
        return esc_html($item['name']);
    }

    function column_description($item) {
        return esc_html($item['description']);
    }

    function column_last_update($item) {
        return esc_html($item['last_update']);
    }

    function column_actions($item) {
        return isset($item['actions']) ? $item['actions'] : '';
    }

    function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = $this->sources;
    }
}

// Dummy data for now; replace with dynamic logic as needed
$sources = [
    [
        'id' => 'taxo_code',
        'name' => 'whoBIRD taxo_code.txt',
        'description' => 'Maps BirdNET IDs to eBird IDs',
        'last_update' => get_option('whobird_mapping_taxo_code_last_update', 'Never'),
        'actions' => '<button type="submit" name="update_taxo_code" class="button">Update</button>',
    ],
    [
        'id' => 'wikidata_sparql',
        'name' => 'Wikidata SPARQL',
        'description' => 'Wikidata Q-ID, eBird ID, taxonomic rank, scientific and English names, picture',
        'last_update' => get_option('whobird_mapping_wikidata_last_update', 'Never'),
        'actions' => '<button type="submit" name="update_wikidata" class="button">Update</button>',
    ],
    [
        'id' => 'birdnet_species',
        'name' => 'BirdNET species file',
        'description' => 'BirdNET species list (ID, scientific name, common name, etc.)',
        'last_update' => get_option('whobird_mapping_birdnet_species_last_update', 'Never'),
        'actions' => '<button type="submit" name="update_birdnet_species" class="button">Update</button>',
    ],
];

// Handle POST actions (replace with actual update logic as you implement sources)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
    if (isset($_POST['update_taxo_code'])) {
        update_option('whobird_mapping_taxo_code_last_update', date('Y-m-d H:i:s'));
        echo '<div class="updated notice"><p>whoBIRD taxo_code.txt updated.</p></div>';
    }
    if (isset($_POST['update_wikidata'])) {
        update_option('whobird_mapping_wikidata_last_update', date('Y-m-d H:i:s'));
        echo '<div class="updated notice"><p>Wikidata SPARQL updated.</p></div>';
    }
    if (isset($_POST['update_birdnet_species'])) {
        update_option('whobird_mapping_birdnet_species_last_update', date('Y-m-d H:i:s'));
        echo '<div class="updated notice"><p>BirdNET species file updated.</p></div>';
    }
}

?>

<div id="mapping-tool-wrapper" style="margin-top:2em;">
    <button type="button" class="collapsible">For maintainers: Mapping Sources</button>
    <div class="content" style="display:none;">
        <h2>Mapping Sources</h2>
        <form method="POST">
            <?php
                $mapping_table = new WhoBIRD_Mapping_Sources_Table($sources);
                $mapping_table->prepare_items();
                $mapping_table->display();
            ?>
        </form>
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
