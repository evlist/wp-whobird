<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * WhoBird Mapping Table Manager
 *
 * Handles the creation, initialization, and querying of the custom mapping table
 * (whobird_mapping) used by the whoBIRD WordPress plugin.
 * Data is loaded from the whobird_mapping.json resource file.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

/**
 * Initializes or recreates the whobird_mapping table and populates it from the whobird_mapping.json file.
 * Should be called during plugin activation.
 *
 * Reads whobird_mapping.json and creates a table with columns:
 *   - birdnet_id (int, primary key)
 *   - scientific_name (varchar)
 *   - wikidata_qid (varchar)
 * Populates the table with the parsed data.
 *
 * Logs errors if the resource file is missing or cannot be parsed.
 */
function whobirdMappingTableInit() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'whobird_mapping';

    $file_path = plugin_dir_path( __FILE__ ) . '../resources/data/whobird_mapping.json';

    if ( ! file_exists( $file_path ) ) {
        error_log( "The file $file_path was not found. The table $table_name was not modified." );
        return;
    }

    $json = file_get_contents($file_path);
    $data = json_decode($json, true);

    if ( ! $data || ! isset($data['data']) ) {
        error_log( "The file $file_path did not contain valid mapping data. The table $table_name was not modified." );
        return;
    }

    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        birdnet_id INT NOT NULL PRIMARY KEY,
        scientific_name VARCHAR(128),
        wikidata_qid VARCHAR(128)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    foreach ( $data['data'] as $row ) {
        $wpdb->insert(
            $table_name,
            [
                'birdnet_id'      => intval($row['birdnet_id']),
                'scientific_name' => $row['scientific_name'],
                'wikidata_qid'     => $row['wikidata_qid'] ?? null,
            ],
            [ '%d', '%s', '%s' ]
        );
    }
}

/**
 * Retrieves the mapping information for a given birdnet_id.
 *
 * @param int $birdnetId The BirdNET species ID.
 * @return array|null An associative array with keys 'scientific_name' and 'wikidata_qid',
 *                    or null if no record is found.
 */
function getMappingByBirdnetId(int $birdnetId): ?array {
    global $wpdb;
    $table_name = $wpdb->prefix . 'whobird_mapping';
    $query = $wpdb->prepare(
        "SELECT scientific_name, wikidata_qid FROM $table_name WHERE birdnet_id = %d",
        $birdnetId
    );
    $result = $wpdb->get_row($query, ARRAY_A);

    return $result ?: null;
}

