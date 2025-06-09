<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

use WPWhoBird\Config;

class WhoBirdActivator
{
    /**
     * Activation hook to create or update required plugin tables.
     */
    public static function activate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        // Table 1: SPARQL Cache (keyed by birdnet_id INT)
        $sparqlCacheTable = Config::getTableSparqlCache();

        // Drop the old cache table if it exists (safe for cache tables)
        $wpdb->query("DROP TABLE IF EXISTS $sparqlCacheTable");

        // Now create the new cache table
        $sparqlCacheSQL = "CREATE TABLE $sparqlCacheTable (
                birdnet_id INT NOT NULL UNIQUE,
                result LONGTEXT NOT NULL,
                expiration DATETIME NOT NULL,
                PRIMARY KEY (birdnet_id)
                ) $charsetCollate;";
        $wpdb->query($sparqlCacheSQL);

        // Table 2: Remote Files Table (use dbDelta in case you want to preserve or migrate data)
        $remoteFilesTable = Config::getTableRemoteFiles();
        $remoteFilesSQL = "CREATE TABLE $remoteFilesTable (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(50) NOT NULL UNIQUE,
                raw_content LONGTEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                source_commit_sha VARCHAR(64) DEFAULT NULL,
                source_commit_date DATETIME DEFAULT NULL
                ) $charsetCollate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($remoteFilesSQL);

        // Table 3: Bird Mapping Table (use dbDelta for data safety)
        $mapping_table = Config::getTableMapping();
        $wpdb->query("DROP TABLE IF EXISTS `$mapping_table`");
        $sql_mapping = "CREATE TABLE `$mapping_table` (
                birdnet_id INT UNSIGNED NOT NULL,
                scientific_name VARCHAR(255) DEFAULT NULL,
                wikidata_qid VARCHAR(32) DEFAULT NULL,
                PRIMARY KEY (birdnet_id)
                ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_mapping);

        // --- 3. Populate mapping table from JSON ---
        $json_path = plugin_dir_path(__DIR__) . '../assets/data/whobird_mapping.json';
        if (!file_exists($json_path)) {
            error_log("whobird: Mapping JSON not found at $json_path");
            return;
        }
        $json = file_get_contents($json_path);
        $data = json_decode($json, true);

        if (!$data || !isset($data['data'])) {
            error_log("whobird: Invalid or empty mapping JSON.");
            return;
        }

        $inserted = 0;
        foreach ($data['data'] as $row) {
            $birdnet_id = intval($row['birdnet_id']);
            $scientific_name = $row['scientific_name'];
            $wikidata_qid = $row['wikidata_qid'];
            $result = $wpdb->insert(
                    $mapping_table,
                    [
                    'birdnet_id' => $birdnet_id,
                    'scientific_name' => $scientific_name,
                    'wikidata_qid' => $wikidata_qid,
                    ]
                    );
            if ($result) $inserted++;
        }

        error_log("whobird: Mapping table recreated and $inserted rows inserted.");

    }
}
