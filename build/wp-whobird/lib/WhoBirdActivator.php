<?php

use WPWhoBird\Config;

class WhoBirdActivator
{
    /**
     * Activation hook to create required plugin tables.
     */
    public static function activate()
    {
        global $wpdb;

        // Table 1: SPARQL Cache
        $tableName1 = Config::getTableSparqlCache();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE $tableName1 (
            ebird_id VARCHAR(255) NOT NULL UNIQUE,
            result LONGTEXT NOT NULL,
            expiration DATETIME NOT NULL
        ) $charsetCollate;";

        // Table 2: Remote Files Table
        $tableName2 = $wpdb->prefix . 'whobird_remote_files';
        $sql2 = "CREATE TABLE $tableName2 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(50) NOT NULL UNIQUE,
            raw_content LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            source_commit_sha VARCHAR(64) DEFAULT NULL,
            source_commit_date DATETIME DEFAULT NULL
        ) $charsetCollate;";

        // Include WordPress file for dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql1);
        dbDelta($sql2);
    }
}
