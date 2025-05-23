<?php

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
        $mappingTable = Config::getTableMapping();
        $mappingSQL = "CREATE TABLE $mappingTable (
            birdnet_id INT NOT NULL UNIQUE,
            wikidata_qid VARCHAR(32) NOT NULL,
            scientific_name VARCHAR(255) NOT NULL,
            PRIMARY KEY (birdnet_id)
        ) $charsetCollate;";
        dbDelta($mappingSQL);
    }
}
