<?php
if (!defined('ABSPATH')) exit;

// ---- CONFIGURATION ----

$WHOBIRD_MAPPING_SOURCES = [
    'taxo_code' => [
        'label' => 'whoBIRD taxo_code.txt',
        'description' => 'Maps BirdNET IDs to eBird IDs',
        'github_repo' => 'woheller69/whoBIRD',
        'github_path' => 'app/src/main/assets/taxo_code.txt',
        'raw_url' => 'https://github.com/woheller69/whoBIRD/raw/master/app/src/main/assets/taxo_code.txt',
    ],
    'birdnet_species' => [
        'label' => 'BirdNET species file (GLOBAL 6K V2.4, en-uk)',
        'description' => 'BirdNET species list (ID, scientific name, common name, etc.)',
        'github_repo' => 'birdnet-team/BirdNET-Analyzer',
        'github_path' => 'birdnet_analyzer/labels/V2.4/BirdNET_GLOBAL_6K_V2.4_Labels_en_uk.txt',
        'raw_url' => 'https://raw.githubusercontent.com/birdnet-team/BirdNET-Analyzer/main/birdnet_analyzer/labels/V2.4/BirdNET_GLOBAL_6K_V2.4_Labels_en_uk.txt',
    ],
    'wikidata_species' => [
        'label' => 'Wikidata birds SPARQL export (English names, eBird IDs)',
        'description' => 'Bird species exported from Wikidata via SPARQL. Includes Wikidata Q ID, English common name, scientific name, taxon rank, and eBird taxon ID.',
        'sparql_url' => 'https://query.wikidata.org/sparql',
        'query' => <<<SPARQL
SELECT ?item ?itemLabel ?scientificName ?taxonRankLabel ?eBirdID WHERE {
  ?item wdt:P105 wd:Q7432.  # Taxon (species or below)
  ?item wdt:P225 ?scientificName.  # Scientific name
  OPTIONAL { ?item wdt:P3444 ?eBirdID. }  # eBird ID
  OPTIONAL { ?item wdt:P105 ?taxonRank. }  # Taxon rank
  ?item wdt:P171* wd:Q5113.  # Descendant of Aves (birds)
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
ORDER BY ?scientificName
SPARQL,
        'format' => 'json',
    ],
];

// ---- DATABASE TABLE ----

global $wpdb;
$WHOBIRD_MAPPING_TABLE = $wpdb->prefix . 'whobird_remote_files';

// ---- SYNC LOGIC ----

/**
 * Download file (GitHub or Wikidata), get metadata, and store in DB.
 * For GitHub sources, gets latest commit info.
 * For SPARQL sources, stores as raw JSON or CSV, with null for commit info.
 * Returns [success(bool), message(string)]
 */
function whobird_sync_remote_source($source_key, $source_cfg, $table) {
    global $wpdb;

    // Special handling for Wikidata SPARQL source
    if ($source_key === 'wikidata_species') {
        $sparql_url = $source_cfg['sparql_url'];
        $query = $source_cfg['query'];
        $format = $source_cfg['format'] ?? 'json';
        $accept = ($format === 'csv') ? 'text/csv' : 'application/sparql-results+json';
        $url = $sparql_url . '?query=' . urlencode($query);
        if ($format === 'csv') $url .= '&format=text/csv';

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => $accept,
                'User-Agent' => 'whoBIRD-plugin'
            ],
            'timeout' => 60
        ]);
        if (is_wp_error($response)) {
            return [false, 'SPARQL query error: ' . $response->get_error_message()];
        }
        $content = wp_remote_retrieve_body($response);
        if (!$content || strlen($content) < 10) {
            return [false, 'No results from Wikidata.'];
        }

        $now = current_time('mysql');
        $wpdb->replace(
            $table,
            [
                'source' => $source_key,
                'raw_content' => $content,
                'updated_at' => $now,
                'source_commit_sha' => null,
                'source_commit_date' => null,
            ]
        );
        return [true, 'Wikidata SPARQL result updated successfully.'];
    }

    // --- Default: GitHub file sources ---
    $repo = $source_cfg['github_repo'];
    $path = $source_cfg['github_path'];
    $api_url = "https://api.github.com/repos/$repo/commits?path=" . urlencode($path) . "&per_page=1";
    $response = wp_remote_get($api_url, [
        'headers' => [ 'User-Agent' => 'whoBIRD-plugin' ]
    ]);
    if (is_wp_error($response)) return [false, 'GitHub API error: ' . $response->get_error_message()];
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body[0]['sha']) || empty($body[0]['commit']['committer']['date'])) {
        return [false, 'Could not get commit info from GitHub.'];
    }
    $latest_sha = $body[0]['sha'];
    $latest_date = $body[0]['commit']['committer']['date'];
    $latest_date_sql = date('Y-m-d H:i:s', strtotime($latest_date));

    $file_response = wp_remote_get($source_cfg['raw_url'], [
        'headers' => [ 'User-Agent' => 'whoBIRD-plugin' ]
    ]);
    if (is_wp_error($file_response)) return [false, 'File download error: ' . $file_response->get_error_message()];
    $content = wp_remote_retrieve_body($file_response);

    $now = current_time('mysql');
    $wpdb->replace(
        $table,
        [
            'source' => $source_key,
            'raw_content' => $content,
            'updated_at' => $now,
            'source_commit_sha' => $latest_sha,
            'source_commit_date' => $latest_date_sql,
        ]
    );
    return [true, 'File updated successfully.'];
}

// ---- TABLE CLASS ----

class WhoBIRD_Mapping_Sources_Table extends WP_List_Table {
    private $sources_cfg;
    private $table_name;

    function __construct($sources_cfg, $table_name) {
        parent::__construct([
            'singular' => 'mapping_source',
            'plural'   => 'mapping_sources',
            'ajax'     => false
        ]);
        $this->sources_cfg = $sources_cfg;
        $this->table_name = $table_name;
    }

    function get_columns() {
        return [
            'name'        => 'Source Name',
            'description' => 'Description',
            'local_commit' => 'Stored Commit',
            'local_update' => 'Last Downloaded',
            'remote_commit' => 'GitHub Commit',
            'status'      => 'Status',
            'actions'     => 'Actions'
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
    function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = [];
        // Build table rows for each source
        foreach ($this->sources_cfg as $key => $cfg) {
            $local = whobird_get_local_source_info($key, $this->table_name);
            $remote = ($key === 'wikidata_species')
                ? null
                : whobird_check_new_github_version($cfg, $local['source_commit_sha'] ?? null);
            $this->items[] = [
                'key' => $key,
                'label' => $cfg['label'],
                'description' => $cfg['description'],
                'local_commit_sha' => $local['source_commit_sha'] ?? null,
                'local_commit_date' => $local['source_commit_date'] ?? null,
                'local_update' => $local['updated_at'] ?? null,
                'remote_commit_sha' => $remote['remote_sha'] ?? null,
                'remote_commit_date' => $remote['remote_date'] ?? null,
                'is_new' => $remote['is_new'] ?? false,
            ];
        }
    }
}

// ---- DB HELPERS ----

function whobird_get_local_source_info($source, $table) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE source = %s", $source), ARRAY_A);
}

function whobird_check_new_github_version($source_cfg, $local_sha) {
    $repo = $source_cfg['github_repo'];
    $path = $source_cfg['github_path'];
    $api_url = "https://api.github.com/repos/$repo/commits?path=" . urlencode($path) . "&per_page=1";
    $response = wp_remote_get($api_url, [
        'headers' => [ 'User-Agent' => 'whoBIRD-plugin' ]
    ]);
    if (is_wp_error($response)) return null;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body[0]['sha'])) {
        $latest_sha = $body[0]['sha'];
        $latest_date = $body[0]['commit']['committer']['date'];
        $is_new = ($local_sha && $local_sha !== $latest_sha);
        return [
            'is_new' => $is_new,
            'remote_sha' => $latest_sha,
            'remote_date' => $latest_date,
        ];
    }
    return null;
}

// ---- HANDLE FORM ACTIONS ----

if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
    foreach ($WHOBIRD_MAPPING_SOURCES as $key => $cfg) {
        if (isset($_POST['update_' . $key])) {
            list($ok, $msg) = whobird_sync_remote_source($key, $cfg, $WHOBIRD_MAPPING_TABLE);
            if ($ok) {
                echo '<div class="updated notice"><p>' . esc_html($cfg['label'] . ': ' . $msg) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($cfg['label'] . ': ' . $msg) . '</p></div>';
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
                $mapping_table = new WhoBIRD_Mapping_Sources_Table($WHOBIRD_MAPPING_SOURCES, $WHOBIRD_MAPPING_TABLE);
                $mapping_table->prepare_items();
                $mapping_table->display();
            ?>
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
