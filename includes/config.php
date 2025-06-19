<?php
/**
 * SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @license   GPL-3.0-or-later
 *
 * Configuration utility class for the WhoBird plugin.
 * 
 * Provides static methods to retrieve key database table names (using the current WordPress prefix)
 * and to fetch plugin options.
 */

namespace WPWhoBird;

global $wpdb;

/**
 * Configuration utility class for WhoBird plugin.
 *
 * Provides static methods to retrieve key database table names (using current WP prefix)
 * and to fetch certain plugin options.
 */
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

    /**
     * Get the full table name for the remote files table.
     *
     * @return string The full table name with the WordPress prefix.
     */
    public static function getTableRemoteFiles()
    {
        global $wpdb;
        return $wpdb->prefix . 'whobird_remote_files';
    }

    /**
     * Get the full table name for the mapping table.
     *
     * @return string The full table name with the WordPress prefix.
     */
    public static function getTableMapping()
    {
        global $wpdb;
        return $wpdb->prefix . 'whobird_mapping';
    }

    /**
     * Determine whether text should be generated if there are no observations for the selected period.
     *
     * @return bool True if text should be generated when there are no observations, false otherwise.
     */
    public static function shouldGenerateTextWhenNoObservations()
    {
        return (bool) get_option('wpwhobird_should_generate_text_when_no_observations', true);
    }
}
