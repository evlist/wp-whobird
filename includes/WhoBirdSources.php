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
        return false;
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
        foreach ($lines as $i => $ebird_id) {
            $ebird_id = trim($ebird_id);
            if ($ebird_id === '') continue;
            $wpdb->insert($table_name, [
                'birdnet_id' => $i,
                'ebird_id' => $ebird_id
            ]);
            $inserted++;
        }
        return [true, "Imported $inserted lines to $table_name."];
    }
}

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
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '_') === false) continue;
            // Split at first two underscores only
            $parts = explode('_', $line, 3);
            $scientific = trim($parts[0]);
            $english = isset($parts[1]) ? trim($parts[1]) : '';
            $wpdb->insert($table_name, [
                'birdnet_id' => $i,
                'scientific_name' => $scientific,
                'english_name' => $english
            ]);
            $inserted++;
        }
        return [true, "Imported $inserted lines to $table_name."];
    }
}

/**
 * Wikidata/SPARQL source (JSON only).
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
