<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * WhoBird Sources
 *
 * Abstract and concrete classes for handling mapping sources
 * (GitHub, Wikidata/SPARQL, etc.) for the whoBIRD WordPress plugin.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

if (!defined('ABSPATH')) exit;

/**
 * Abstract base class for all mapping sources.
 *
 * Provides the interface and shared logic for handling data sources
 * such as GitHub or Wikidata. Subclasses must implement source-specific
 * update and status logic.
 */
abstract class WhoBirdAbstractSource {
    /** @var string Unique key for the source */
    protected $key;
    /** @var array Configuration for the source */
    protected $cfg;
    /** @var string Database table name for storing raw content */
    protected $table;

    /**
     * Constructor.
     * @param string $key  Source key
     * @param array  $cfg  Source configuration
     * @param string $table Database table name
     */
    public function __construct($key, $cfg, $table) {
        $this->key = $key;
        $this->cfg = $cfg;
        $this->table = $table;
    }

    /**
     * Get status array for UI table row.
     * Must be implemented by subclasses.
     * @return array
     */
    abstract public function getStatus();

    /**
     * Update the source (download latest and store in DB).
     * Must be implemented by subclasses.
     * @return array [bool success, string message]
     */
    abstract public function update();

    /**
     * Store content in a queryable DB table (not just raw storage).
     * Should be implemented in subclasses where table import is required.
     * @return array [bool success, string message]
     */
    public function uploadToTable() {
        // Implement in subclasses as needed
        return [false, __('Not implemented for this source.', 'wp-whobird')];
    }

    /**
     * Download content as a file.
     * Should be implemented in subclasses if direct file download is needed.
     * @return bool
     */
    public function download() {
        // Implement in subclasses as needed
        return false;
    }

    /**
     * Get the raw content row from DB.
     * @return array|null
     */
    public function getDBRow() {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE source = %s", $this->key), ARRAY_A);
    }
}

/**
 * Abstract class for GitHub file-based sources.
 *
 * Implements logic for fetching files and commit metadata from GitHub.
 */
abstract class WhoBirdGithubSource extends WhoBirdAbstractSource {
    /**
     * Fetch the latest commit SHA and date from GitHub.
     * @return array [sha, date] or [null, null] on failure
     */
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

    /**
     * Fetch the raw file contents from GitHub.
     * @return string|null
     */
    protected function fetchRawFile() {
        $url = $this->cfg['raw_url'];
        $response = wp_remote_get($url, [
                'headers' => ['User-Agent' => 'whoBIRD-plugin']
        ]);
        if (is_wp_error($response)) return null;
        return wp_remote_retrieve_body($response);
    }

    /**
     * Update the local DB with the latest file from GitHub.
     * @return array [bool success, string message]
     */
    public function update() {
        global $wpdb;
        list($latest_sha, $latest_date) = $this->fetchLatestCommit();
        if (!$latest_sha || !$latest_date) {
            return [false, __('Could not get commit info from GitHub.', 'wp-whobird')];
        }
        $content = $this->fetchRawFile();
        if (!$content) {
            return [false, __('File download error.', 'wp-whobird')];
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
        return [true, __('File updated successfully.', 'wp-whobird')];
    }

    /**
     * Get the status for this source.
     * @return array
     */
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
 *
 * Downloads and imports the birdnet_id to ebird_id mapping from GitHub.
 */
class WhoBirdTaxoCodeSource extends WhoBirdGithubSource {
    /**
     * Import the raw content into a structured table.
     * Drops and recreates the target table with imported data.
     * @return array [bool success, string message]
     */
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
            return [false, __('No raw content available for import.', 'wp-whobird')];
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
                $errors[] = sprintf(__('Insert error for birdnet_id=%d : %s', 'wp-whobird'), $i, $wpdb->last_error);
            } else {
                $inserted++;
            }
        }
        $msg = sprintf(__('Imported %d rows to %s.', 'wp-whobird'), $inserted, $table_name);
        if ($errors) {
            $msg .= ' ' . sprintf(_n('%d insert error occurred. First error: %s', '%d insert errors occurred. First error: %s', count($errors), 'wp-whobird'), count($errors), $errors[0]);
        }
        return [empty($errors), $msg];
    }
}

/**
 * BirdnetSpecies source with table import.
 *
 * Downloads and imports the birdnet_id to scientific and English names mapping from GitHub.
 */
class WhoBirdBirdnetSpeciesSource extends WhoBirdGithubSource {
    /**
     * Import the raw content into a structured table.
     * Drops and recreates the target table with imported data.
     * @return array [bool success, string message]
     */
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
            return [false, __('No raw content available for import.', 'wp-whobird')];
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
                $errors[] = sprintf(__('Insert error for birdnet_id=%d : %s', 'wp-whobird'), $i, $wpdb->last_error);
            } else {
                $inserted++;
            }
        }
        $msg = sprintf(__('Imported %d rows to %s.', 'wp-whobird'), $inserted, $table_name);
        if ($errors) {
            $msg .= ' ' . sprintf(_n('%d insert error occurred. First error: %s', '%d insert errors occurred. First error: %s', count($errors), 'wp-whobird'), count($errors), $errors[0]);
        }
        return [empty($errors), $msg];
    }
}

/**
 * Wikidata/SPARQL source with JSON import to table.
 *
 * Handles downloading and importing species data via SPARQL from Wikidata.
 */
class WhoBirdWikidataSource extends WhoBirdAbstractSource {
    /**
     * Update the local DB with the latest SPARQL results from Wikidata.
     * @return array [bool success, string message]
     */
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
            return [false, __('SPARQL query error: ', 'wp-whobird') . $response->get_error_message()];
        }
        $content = wp_remote_retrieve_body($response);
        if (!$content || strlen($content) < 10) {
            return [false, __('No results from Wikidata.', 'wp-whobird')];
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
        return [true, __('Wikidata SPARQL result updated successfully.', 'wp-whobird')];
    }

    /**
     * Get the status for this source.
     * @return array
     */
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
     *
     * The table is named {$wpdb->prefix}whobird_wikidata_species.
     * Columns:
     *   - wikidata_qid: Wikidata Q-id (e.g. "Q12345"), primary key
     *   - itemLabel: English label from Wikidata
     *   - scientificName: Scientific name
     *   - taxonRankLabel: Taxon rank label
     *   - eBirdID: eBird taxon ID
     *
     * Assumes the SPARQL query returns a ?wikidata_qid variable for each row.
     * Does NOT store the full Wikidata entity URL.
     *
     * @return array [bool success, string message]
     */
    public function uploadToTable() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'whobird_wikidata_species';

        // Get raw content
        $row = $this->getDBRow();
        if (!$row || !isset($row['raw_content']) || $row['raw_content'] === '') {
            return [false, __('No raw content available for import.', 'wp-whobird')];
        }

        $json = json_decode($row['raw_content'], true);
        if (!$json || !isset($json['head']['vars']) || !isset($json['results']['bindings'])) {
            return [false, __('Invalid or unexpected Wikidata SPARQL result.', 'wp-whobird')];
        }

        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
        $sql = "CREATE TABLE `$table_name` (
                `wikidata_qid` VARCHAR(32),
                `itemLabel` VARCHAR(255) DEFAULT NULL,
                `scientificName` VARCHAR(255) DEFAULT NULL,
                `taxonRankLabel` VARCHAR(255) DEFAULT NULL,
                `eBirdID` VARCHAR(255) DEFAULT NULL
                ) DEFAULT CHARSET=utf8mb4";
        $wpdb->query($sql);

        // Prepare and insert rows
        $inserted = 0;
        $errors = [];
        foreach ($json['results']['bindings'] as $row) {
            $itemUrl = $row['item']['value']; // e.g., "http://www.wikidata.org/entity/Q12345"
            $wikidata_qid = substr($itemUrl, strrpos($itemUrl, '/') + 1);
            $row_data = [
                'wikidata_qid' => $wikidata_qid,
                'itemLabel' => isset($row['itemLabel']['value']) ? $row['itemLabel']['value'] : null,
                'scientificName' => isset($row['scientificName']['value']) ? $row['scientificName']['value'] : null,
                'taxonRankLabel' => isset($row['taxonRankLabel']['value']) ? $row['taxonRankLabel']['value'] : null,
                'eBirdID' => isset($row['eBirdID']['value']) ? $row['eBirdID']['value'] : null,
            ];
            if (!empty($row_data['wikidata_qid'])) {
                $result = $wpdb->insert($table_name, $row_data);
                if ($result === false) {
                    $errors[] = sprintf(__('Insert error for wikidata_qid=%s: %s', 'wp-whobird'),
                        esc_html($row_data['wikidata_qid']),
                        $wpdb->last_error
                    );
                } else {
                    $inserted++;
                }
            }
        }
        $msg = sprintf(__('Imported %d rows to %s.', 'wp-whobird'), $inserted, $table_name);
        if ($errors) {
            $msg .= ' ' . sprintf(_n('%d insert error occurred. First error: %s', '%d insert errors occurred. First error: %s', count($errors), 'wp-whobird'), count($errors), $errors[0]);
        }
        return [empty($errors), $msg];
    }
}

/**
 * Factory for WhoBird sources.
 *
 * Returns an instance of the appropriate source class based on $key.
 *
 * @param string $key
 * @param array $cfg
 * @param string $table
 * @return WhoBirdAbstractSource
 * @throws Exception
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
            throw new Exception(sprintf(__('Unknown mapping source key: %s', 'wp-whobird'), $key));
    }
}
?>
