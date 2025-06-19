<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Handles bird mapping sources configuration and mapping table logic for the WhoBird plugin.
 * 
 * Provides:
 * - Source configuration for mapping data files and Wikidata queries
 * - Stepwise database schema and data manipulation for mapping tables
 * - WordPress admin export action
 * - Utility functions for mapping step execution
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/WhoBirdSources.php';

// Register export handler for mapping table as JSON.
add_action('admin_post_whobird_export_mapping_json', function() {
    require_once __DIR__ . '/bird-mappings-export.php';
    exit;
});

use WPWhoBird\Config;

// ---- CONFIGURATION ----

/**
 * @var array $WHOBIRD_MAPPING_SOURCES
 * Defines sources for mapping: BirdNET, eBird, and Wikidata species lists.
 */
$WHOBIRD_MAPPING_SOURCES = [
    'taxo_code' => [
        'label' => __('whoBIRD taxo_code.txt', 'wp-whobird'),
        'description' => __('Maps BirdNET IDs to eBird IDs', 'wp-whobird'),
        'github_repo' => 'woheller69/whoBIRD',
        'github_path' => 'app/src/main/assets/taxo_code.txt',
        'raw_url' => 'https://github.com/woheller69/whoBIRD/raw/master/app/src/main/assets/taxo_code.txt',
    ],
    'birdnet_species' => [
        'label' => __('whoBIRD BirdNET species file (labels_en.txt)', 'wp-whobird'),
        'description' => __('BirdNET species list (ID, scientific name, common name, etc.) from whoBIRD, kept in sync with taxo_code.txt.', 'wp-whobird'),
        'github_repo' => 'woheller69/whoBIRD',
        'github_path' => 'app/src/main/assets/labels_en.txt',
        'raw_url' => 'https://github.com/woheller69/whoBIRD/raw/master/app/src/main/assets/labels_en.txt',
    ],
    'wikidata_species' => [
        'label' => __('Wikidata birds SPARQL export (English names, eBird IDs)', 'wp-whobird'),
        'description' => __('Bird species exported from Wikidata via SPARQL. Includes Wikidata Q ID, English common name, scientific name, taxon rank, and eBird taxon ID.', 'wp-whobird'),
        'sparql_url' => 'https://query.wikidata.org/sparql',
        'query' => <<<SPARQL
        SELECT ?item ?itemLabel ?scientificName ?taxonRankLabel ?eBirdID WHERE {
            ?item wdt:P105 wd:Q7432.  # Taxon (species)
                ?item wdt:P225 ?scientificName.
                OPTIONAL { ?item wdt:P3444 ?eBirdID. }
            OPTIONAL { ?item wdt:P105 ?taxonRank. }
            ?item wdt:P171* wd:Q5113.  # Descendant of Aves (birds)
                SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
        }
SPARQL,
    ],
];

// ---- DATABASE TABLE ----

global $wpdb;
/**
 * @var string $WHOBIRD_MAPPING_TABLE
 * Table name for storing remote mapping files (used for tracking sources).
 */
$WHOBIRD_MAPPING_TABLE = $wpdb->prefix . 'whobird_remote_files';

/**
 * Returns the array of mapping steps used to build and update the mapping table.
 * Each step contains SQL (or is handled in PHP) and a status message.
 * 
 * @return array
 */
function whobird_get_mapping_steps() {
    global $wpdb;
    $mapping_table = $wpdb->prefix . 'whobird_mapping';
    $birdnet_species = $wpdb->prefix . 'whobird_birdnet_species';
    $wikidata_species = $wpdb->prefix . 'whobird_wikidata_species';
    $taxocode = $wpdb->prefix . 'whobird_taxocode';

    return [
        // Step 1: Drop table if exists
        'drop_table' => [
            'sql' => "DROP TABLE IF EXISTS {$mapping_table}",
            'msg' => __('Dropped previous mapping table (if any).', 'wp-whobird'),
        ],
        // Step 2: Create mapping table
        'create_table' => [
            'sql' => "CREATE TABLE {$mapping_table} (
                    birdnet_id INT(10) UNSIGNED PRIMARY KEY,
                    scientific_name VARCHAR(128),
                    wikidata_qid VARCHAR(64)
                    )",
            'msg' => __('Created mapping table.', 'wp-whobird'),
        ],
        // Step 3: Create index on scientific name in Wikidata species table
        'add_wikidata_scientific_name_index' => [
            'sql' => '', // handled in PHP
            'msg' => __('Added index on scientificName in wikidata_species table (if not already present).', 'wp-whobird'),
        ],
        // Step 4: Create index on eBird id in Wikidata species table
        'add_wikidata_ebirdid_index' => [
            'sql' => '', // handled in PHP
            'msg' => __('Added index on eBird id in wikidata_species table (if not already present).', 'wp-whobird'),
        ],
        // Step 5: Insert BirdNET species into mapping table
        'insert_birdnet_species' => [
            'sql' => "INSERT INTO {$mapping_table} (birdnet_id, scientific_name)
                SELECT birdnet_id, scientific_name FROM {$birdnet_species}",
            'msg' => __('Inserted BirdNET species into mapping table.', 'wp-whobird'),
        ],
        // Step 6: Update mapping with Wikidata QIDs by scientific name
        'update_wikidata_by_scientific_name' => [
            'sql' => "UPDATE {$mapping_table} m
                JOIN {$wikidata_species} w ON m.scientific_name = w.scientificName
                SET m.wikidata_qid = w.wikidata_qid",
            'msg' => __('Updated Wikidata IDs using scientific names.', 'wp-whobird'),
        ],
        // Step 7: Update mapping with Wikidata QIDs by eBird ID for unmatched rows
        'update_wikidata_by_ebird_id' => [
            'sql' => "UPDATE {$mapping_table} m
                JOIN {$taxocode} t ON m.birdnet_id = t.birdnet_id
                JOIN {$wikidata_species} w ON t.ebird_id = w.eBirdID
                SET m.wikidata_qid = w.wikidata_qid
                WHERE m.wikidata_qid IS NULL",
            'msg' => __('Updated Wikidata IDs using eBird IDs for unmatched rows.', 'wp-whobird'),
        ],
        // Step 8: Update mapping table using "truthy" scientific names via Wikidata SPARQL
        'update_mapping_from_wikidata_truthy' => [
            'sql' => '', // handled in PHP
            'msg' => __('Queried Wikidata for truthy scientific names and updated mapping table.', 'wp-whobird'),
        ],
        // Step 9: Update mapping table using all scientific names via Wikidata SPARQL
        'update_mapping_from_wikidata_all' => [
            'sql' => '', // handled in PHP
            'msg' => __('Queried Wikidata for all scientific names and updated mapping table.', 'wp-whobird'),
        ],
    ];
}

/**
 * Executes a mapping table step by name (SQL or PHP logic).
 *
 * @param string $step Mapping step name.
 * @return array [ 'success' => bool, 'msg' => string ]
 */
function whobird_execute_mapping_step($step) {
    $steps = whobird_get_mapping_steps();
    if (!isset($steps[$step])) {
        return [ 'success' => false, 'msg' => sprintf(__('Unknown step: %s', 'wp-whobird'), esc_html($step)) ];
    }

    global $wpdb;
    global $WHOBIRD_MAPPING_SOURCES;

    $mapping_table = $wpdb->prefix . 'whobird_mapping';

    // Add index on scientific name if not present (handled in PHP).
    if ($step === 'add_wikidata_scientific_name_index') {
        $wikidata_species = $wpdb->prefix . 'whobird_wikidata_species';
        $index_name = 'idx_scientificName';

        // Check if index exists, otherwise create it.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW INDEX FROM {$wikidata_species} WHERE Key_name = %s",
                $index_name
            )
        );
        if (!$exists) {
            $result = $wpdb->query("ALTER TABLE {$wikidata_species} ADD INDEX {$index_name} (scientificName(64))");
            if ($result === false) {
                return [ 'success' => false, 'msg' => __('SQL error creating index on scientific name: ', 'wp-whobird') . $wpdb->last_error ];
            }
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " (" . __('created', 'wp-whobird') . ")" ];
        } else {
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " (" . __('already present', 'wp-whobird') . ")" ];
        }
    }

    // Add index on eBird ID if not present (handled in PHP).
    if ($step === 'add_wikidata_ebirdid_index') {
        $wikidata_species = $wpdb->prefix . 'whobird_wikidata_species';
        $index_name = 'idx_ebirdid';

        // Check if index exists, otherwise create it.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW INDEX FROM {$wikidata_species} WHERE Key_name = %s",
                $index_name
            )
        );
        if (!$exists) {
            $result = $wpdb->query("ALTER TABLE {$wikidata_species} ADD INDEX {$index_name} (eBirdID)");
            if ($result === false) {
                $error = $wpdb->last_error;
                error_log("SQL error in bird-mapping index creation: $error");
                return [ 'success' => false, 'msg' => __('SQL error, see error in the log', 'wp-whobird') ];
            }
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " (" . __('created', 'wp-whobird') . ")" ];
        } else {
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " (" . __('already present', 'wp-whobird') . ")" ];
        }
    }

    // Update mapping table with fetched Wikidata QIDs from previous SPARQL queries.
    if ($step === 'update_mapping_with_fetched_ids') {
        $results = get_option('whobird_fetched_wikidata_qids', []);
        if (!$results) {
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . ' (' . __('none to update', 'wp-whobird') . ')' ];
        }
        $updated = 0;
        foreach ($results as $sci => $qid) {
            $wpdb->update($mapping_table, ['wikidata_qid' => $qid], ['scientific_name' => $sci]);
            $updated++;
        }
        delete_option('whobird_fetched_wikidata_qids');
        return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " ($updated " . __('updated', 'wp-whobird') . ")" ];
    }

    // Update mapping table using Wikidata SPARQL, truthy or all scientific names.
    if ($step === 'update_mapping_from_wikidata_truthy' || $step === 'update_mapping_from_wikidata_all') {
        $p225_mode = $step === 'update_mapping_from_wikidata_truthy' ? 'wdt' : 'ps';

        $missing = $wpdb->get_col("SELECT scientific_name FROM {$mapping_table} WHERE wikidata_qid IS NULL AND scientific_name IS NOT NULL");
        error_log("Step $step, missing from $mapping_table: $missing");
        if (!$missing) {
            return [ 'success' => true, 'msg' => $steps[$step]['msg'] . ' (' . __('none missing', 'wp-whobird') . ')' ];
        }
        $sparql_url = $WHOBIRD_MAPPING_SOURCES['wikidata_species']['sparql_url'];
        $updated = 0;
        $chunks = array_chunk($missing, 50); // batch

        foreach ($chunks as $batch) {
            $values = '';
            foreach ($batch as $name) {
                $values .= ' "' . addcslashes($name, '"\\') . '"';
            }
            if ($p225_mode === 'wdt') {
                // Direct property (fastest, returns entity URL in ?item)
                $sparql = <<<SPARQL
                    SELECT ?item ?scientificName WHERE {
                        ?item wdt:P225 ?scientificName .
                            VALUES ?scientificName { $values }
                    }
SPARQL;
            } else { // ps
                // Statement property (also returns entity URL in ?item)
                $sparql = <<<SPARQL
                    SELECT ?item ?scientificName WHERE {
                        ?item p:P225/ps:P225 ?scientificName .
                            VALUES ?scientificName { $values }
                    }
SPARQL;
            }
            error_log("Step $step, sparql: $sparql");
            $response = wp_remote_post($sparql_url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/sparql-results+json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query(['query' => $sparql]),
            ]);
            if (is_wp_error($response)) continue;
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($data['results']['bindings'])) continue;
            foreach ($data['results']['bindings'] as $row) {
                $sci = $row['scientificName']['value'];
                $qurl = $row['item']['value'];
                // Extract Q-id from URL
                $qid = substr($qurl, strrpos($qurl, '/') + 1);
                $result = $wpdb->update($mapping_table, ['wikidata_qid' => $qid], ['scientific_name' => $sci]);
                if ($result !== false && $wpdb->rows_affected > 0) {
                    $updated++;
                }
            }
        }
        return [ 'success' => true, 'msg' => $steps[$step]['msg'] . " ($updated " . __('updated', 'wp-whobird') . ")" ];
    }

    // Default: Execute step SQL using dbDelta for CREATE TABLE, otherwise with $wpdb->query().
    $sql = $steps[$step]['sql'];
    // For CREATE TABLE, use dbDelta for best compatibility
    if ($step === 'create_table') {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        return [ 'success' => true, 'msg' => $steps[$step]['msg'] ];
    } else {
        $result = $wpdb->query($sql);
        if ($result === false) {
            $error = $wpdb->last_error;
            error_log("SQL error in step $step: $error");
            return [ 'success' => false, 'msg' => __('SQL error, see error in the log', 'wp-whobird') ];
        }
        return [ 'success' => true, 'msg' => $steps[$step]['msg'] ];
    }
}
