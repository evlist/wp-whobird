<?php
if (!defined('ABSPATH')) exit;

/**
 * Abstract base class for all mapping sources.
 */
abstract class WhoBirdAbstractSource {
    protected $key;
    protected $cfg;
    protected $table;

    public function __construct($key, $cfg, $table) {
        $this->key = $key;
        $this->cfg = $cfg;
        $this->table = $table;
    }

    /**
     * Get status array for UI table row.
     */
    abstract public function getStatus();

    /**
     * Update the source (download latest and store in DB).
     */
    abstract public function update();

    /**
     * Store content in a queryable DB table (not just raw storage).
     */
    public function uploadToTable() {
        // Implement in subclasses as needed
        return [false, 'Not implemented for this source.'];
    }

    /**
     * Download content as a file.
     */
    public function download() {
        // Implement in subclasses as needed
        return false;
    }

    /**
     * Get the raw content row from DB.
     */
    public function getDBRow() {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE source = %s", $this->key), ARRAY_A);
    }
}

/**
 * Abstract class for GitHub file-based sources.
 */
abstract class WhoBirdGithubSource extends WhoBirdAbstractSource {
    protected function fetchLatestCommit() {
        $repo = $this->cfg['github_repo'];
        $path = $this->cfg['github_path'];
        $api_url = "https://api.github.com/repos/$repo/commits?path=" . urlencode($path) . "&per_page=1";
        $response = wp_remote_get($api_url, [
            'headers' => ['User-Agent' => 'whoBIRD-plugin']
        ]);
        if (is_wp_error($response)) return [null, null];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body[0]['sha']) && !empty($body[0]['commit']['committer']['date'])) {
            return [$body[0]['sha'], $body[0]['commit']['committer']['date']];
        }
        return [null, null];
    }

    protected function fetchRawFile() {
        $url = $this->cfg['raw_url'];
        $response = wp_remote_get($url, [
            'headers' => ['User-Agent' => 'whoBIRD-plugin']
        ]);
        if (is_wp_error($response)) return null;
        return wp_remote_retrieve_body($response);
    }

    public function update() {
        global $wpdb;
        list($latest_sha, $latest_date) = $this->fetchLatestCommit();
        if (!$latest_sha || !$latest_date) {
            return [false, 'Could not get commit info from GitHub.'];
        }
        $content = $this->fetchRawFile();
        if (!$content) {
            return [false, 'File download error.'];
        }
        $now = current_time('mysql');
        $latest_date_sql = date('Y-m-d H:i:s', strtotime($latest_date));
        $wpdb->replace(
            $this->table,
            [
                'source' => $this->key,
                'raw_content' => $content,
                'updated_at' => $now,
                'source_commit_sha' => $latest_sha,
                'source_commit_date' => $latest_date_sql,
            ]
        );
        return [true, 'File updated successfully.'];
    }

    public function getStatus() {
        $row = $this->getDBRow();
        list($remote_sha, $remote_date) = $this->fetchLatestCommit();
        $is_new = $row && $remote_sha && $row['source_commit_sha'] !== $remote_sha;
        return [
            'key' => $this->key,
            'label' => $this->cfg['label'],
            'description' => $this->cfg['description'],
            'local_commit_sha' => $row['source_commit_sha'] ?? null,
            'local_commit_date' => $row['source_commit_date'] ?? null,
            'local_update' => $row['updated_at'] ?? null,
            'remote_commit_sha' => $remote_sha,
            'remote_commit_date' => $remote_date,
            'is_new' => $is_new,
        ];
    }
}

/**
 * TaxoCode source with table import.
 */
class WhoBirdTaxoCodeSource extends WhoBirdGithubSource {
    public function uploadToTable() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'whobird_taxocode';

        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
        $sql = "CREATE TABLE `$table_name` (
            birdnet_id INT UNSIGNED NOT NULL PRIMARY KEY,
            ebird_id VARCHAR(24) NOT NULL
        ) DEFAULT CHARSET=utf8mb4";
        $wpdb->query($sql);

        // Get raw content
        $row = $this->getDBRow();
        if (!$row || !isset($row['raw_content']) || $row['raw_content'] === '') {
            return [false, 'No raw content available for import.'];
        }

        // Insert each line (line number = birdnet_id, line content = ebird_id)
        $lines = preg_split('/\r\n|\r|\n/', trim($row['raw_content']));
        $inserted = 0;
        $errors = [];
        foreach ($lines as $i => $ebird_id) {
            $ebird_id = trim($ebird_id);
            if ($ebird_id === '') continue;
            $result = $wpdb->insert($table_name, [
                'birdnet_id' => $i,
                'ebird_id' => $ebird_id
            ]);
            if ($result === false) {
                $errors[] = "Insert error for birdnet_id=$i : " . $wpdb->last_error;
            } else {
                $inserted++;
            }
        }
        $msg = "Imported $inserted rows to $table_name.";
        if ($errors) {
            $msg .= " " . count($errors) . " insert errors occurred. First error: " . $errors[0];
        }
        return [empty($errors), $msg];
    }
}

/**
 * BirdnetSpecies source with table import.
 */
class WhoBirdBirdnetSpeciesSource extends WhoBirdGithubSource {
    public function uploadToTable() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'whobird_birdnet_species';

        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
        $sql = "CREATE TABLE `$table_name` (
            birdnet_id INT UNSIGNED NOT NULL PRIMARY KEY,
            scientific_name VARCHAR(128) NOT NULL,
            english_name VARCHAR(128) NOT NULL
        ) DEFAULT CHARSET=utf8mb4";
        $wpdb->query($sql);

        // Get raw content
        $row = $this->getDBRow();
        if (!$row || !isset($row['raw_content']) || $row['raw_content'] === '') {
            return [false, 'No raw content available for import.'];
        }

        // Insert each line (line number = birdnet_id, content split by "_")
        $lines = preg_split('/\r\n|\r|\n/', trim($row['raw_content']));
        $inserted = 0;
        $errors = [];
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '_') === false) continue;
            $parts = explode('_', $line, 3);
            $scientific = trim($parts[0]);
            $english = isset($parts[1]) ? trim($parts[1]) : '';
            $result = $wpdb->insert($table_name, [
                'birdnet_id' => $i,
                'scientific_name' => $scientific,
                'english_name' => $english
            ]);
            if ($result === false) {
                $errors[] = "Insert error for birdnet_id=$i : " . $wpdb->last_error;
            } else {
                $inserted++;
            }
        }
        $msg = "Imported $inserted rows to $table_name.";
        if ($errors) {
            $msg .= " " . count($errors) . " insert errors occurred. First error: " . $errors[0];
        }
        return [empty($errors), $msg];
    }
}

/**
 * Wikidata/SPARQL source with JSON import to table.
 */
class WhoBirdWikidataSource extends WhoBirdAbstractSource {
    public function update() {
        global $wpdb;
        $sparql_url = $this->cfg['sparql_url'];
        $query = $this->cfg['query'];

        // Always request JSON
        $url = $sparql_url . '?query=' . urlencode($query);
        $accept = 'application/sparql-results+json';

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
            $this->table,
            [
                'source' => $this->key,
                'raw_content' => $content,
                'updated_at' => $now,
                'source_commit_sha' => null,
                'source_commit_date' => null,
            ]
        );
        return [true, 'Wikidata SPARQL result updated successfully.'];
    }

    public function getStatus() {
        $row = $this->getDBRow();
        return [
            'key' => $this->key,
            'label' => $this->cfg['label'],
            'description' => $this->cfg['description'],
            'local_commit_sha' => null,
            'local_commit_date' => null,
            'local_update' => $row['updated_at'] ?? null,
            'remote_commit_sha' => null,
            'remote_commit_date' => null,
            'is_new' => false,
        ];
    }

    /**
     * Import SPARQL JSON results into a structured table.
     * The table is named {$wpdb->prefix}whobird_wikidata_species.
     * Columns: one for each property in the SPARQL result (item, itemLabel, scientificName, taxonRankLabel, eBirdID).
     */
    public function uploadToTable() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'whobird_wikidata_species';

        // Get raw content
        $row = $this->getDBRow();
        if (!$row || !isset($row['raw_content']) || $row['raw_content'] === '') {
            return [false, 'No raw content available for import.'];
        }

        $json = json_decode($row['raw_content'], true);
        if (!$json || !isset($json['head']['vars']) || !isset($json['results']['bindings'])) {
            return [false, 'Invalid or unexpected Wikidata SPARQL result.'];
        }

        $vars = $json['head']['vars']; // e.g. ['item', 'itemLabel', ...]
        $bindings = $json['results']['bindings'];

        // Compose SQL for columns
        // We'll use VARCHAR(255) for all columns, including 'item'
        $columns_sql = [];
        foreach ($vars as $var) {
            $columns_sql[] = "`$var` VARCHAR(255) DEFAULT NULL";
        }
        $columns_str = implode(",\n", $columns_sql);

        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
        $sql = "CREATE TABLE `$table_name` (
            $columns_str
        ) DEFAULT CHARSET=utf8mb4";
        $wpdb->query($sql);

        // Prepare and insert rows
        $inserted = 0;
        $errors = [];
        foreach ($bindings as $row) {
            $row_data = [];
            foreach ($vars as $var) {
                $row_data[$var] = isset($row[$var]['value']) ? $row[$var]['value'] : null;
            }
            if (!empty($row_data['item'])) {
                $result = $wpdb->insert($table_name, $row_data);
                if ($result === false) {
                    $errors[] = "Insert error for item=" . esc_html($row_data['item']) . ": " . $wpdb->last_error;
                } else {
                    $inserted++;
                }
            }
        }
        $msg = "Imported $inserted rows to $table_name.";
        if ($errors) {
            $msg .= " " . count($errors) . " insert errors occurred. First error: " . $errors[0];
        }
        return [empty($errors), $msg];
    }
}

/**
 * Factory for WhoBird sources.
 */
function whobird_get_source_instance($key, $cfg, $table) {
    switch ($key) {
        case 'taxo_code':
            return new WhoBirdTaxoCodeSource($key, $cfg, $table);
        case 'birdnet_species':
            return new WhoBirdBirdnetSpeciesSource($key, $cfg, $table);
        case 'wikidata_species':
            return new WhoBirdWikidataSource($key, $cfg, $table);
        default:
            throw new Exception("Unknown mapping source key: $key");
    }
}
?>
