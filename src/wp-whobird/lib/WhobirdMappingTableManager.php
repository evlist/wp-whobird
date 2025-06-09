<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Handles the management of the custom table for storing data from the taxo_code.txt file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

/**
 * Function triggered during plugin activation.
 */
/**
 * Creates or recreates the whobird_mapping table and populates it from the whobird_mapping.json file.
 */
function whobirdMappingTableInit() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'whobird_mapping';

    $file_path = plugin_dir_path( __FILE__ ) . '../../assets/data/whobird_mapping.json';

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

