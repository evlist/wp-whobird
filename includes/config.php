<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

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
}
