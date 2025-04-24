<?php

class WikidataQueryActivator
{
    /**
     * Activation hook to create the wp_whobird_sparql_cache table.
     */
    public static function activate()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'whobird_sparql_cache';
        $charsetCollate = $wpdb->get_charset_collate();

        // SQL to create the table
        $sql = "CREATE TABLE $tableName (
            ebird_id VARCHAR(255) NOT NULL UNIQUE, -- Clé unique basée sur species_name
            result LONGTEXT NOT NULL,
            expiration DATETIME NOT NULL
        ) $charsetCollate;";

        // Include WordPress file for dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create or update the table
        dbDelta($sql);
    }
}

