<?php
namespace WPWhoBird;

global $wpdb;

class Config
{
    /**
     * Get the full table name for the SPARQL cache.
     * 
     * @return string The full table name with the WordPress prefix.
     */
    public static function getTableSparqlCache()
    {
        global $wpdb;
        return $wpdb->prefix . 'whobird_sparql_cache';
    }
}
