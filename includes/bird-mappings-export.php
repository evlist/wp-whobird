<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Exports the Whobird mapping table as a JSON file with metadata and source information.
 * 
 * This script is intended to run within a WordPress context (e.g., custom admin page or AJAX handler).
 * It assumes that the required global variables and dependencies are loaded beforehand.
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
global $WHOBIRD_MAPPING_SOURCES;
require_once __DIR__ . '/WhoBirdSources.php';
require_once __DIR__ . '/bird-mappings.php';

$mapping_table = $wpdb->prefix . 'whobird_mapping';

// 1. Export mapping table data: fetch all mapping rows ordered by birdnet_id.
$data = $wpdb->get_results("SELECT birdnet_id, scientific_name, wikidata_qid FROM {$mapping_table} ORDER BY birdnet_id", ARRAY_A);

// 2. Gather source metadata from WHOBIRD_MAPPING_SOURCES configuration.
/**
 * Build an array of source information including label, commit SHA, commit date, 
 * last update time, and SPARQL query (for Wikidata).
 */
$sources_cfg = $WHOBIRD_MAPPING_SOURCES;
$sources_info = [];
foreach ($sources_cfg as $key => $cfg) {
    $source = whobird_get_source_instance($key, $cfg, $wpdb->prefix . 'whobird_remote_files');
    $row = $source->getDBRow();
    if ($row) {
        $entry = [
            'key' => $key,
            'label' => $cfg['label'],
        ];
        if (!empty($row['source_commit_sha'])) {
            $entry['commit_sha'] = $row['source_commit_sha'];
        }
        if (!empty($row['source_commit_date'])) {
            $entry['commit_date'] = $row['source_commit_date'];
        }
        if (!empty($row['updated_at'])) {
            $entry['updated_at'] = $row['updated_at'];
        }
        // For Wikidata sources, include the SPARQL query if available.
        if ($key === 'wikidata_species' && !empty($cfg['query'])) {
            $entry['sparql_query'] = $cfg['query'];
        }
        $sources_info[] = $entry;
    }
}

// 3. Build metadata for export, including export timestamp, user, sources and row count.
/**
 * @var array $metadata Metadata describing the export (export time, user, sources, row count).
 */
$metadata = [
    'exported_at' => gmdate('c'),
    'user' => wp_get_current_user()->user_login,
    'sources' => $sources_info,
    'row_count' => count($data)
];

// 4. Build the final output structure containing metadata and data.
$output = [
    'metadata' => $metadata,
    'data' => $data
];

// 5. Output the export as a downloadable JSON file with proper headers.
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="whobird_mapping.json"');
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
?>
