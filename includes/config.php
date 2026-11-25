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
    /** Option names (as registered on the settings page). */
    const OPTION_RECORDINGS_PATH = 'wpwhobird_recordings_path';
    const OPTION_DATABASE_PATH   = 'wpwhobird_database_path';
    const OPTION_THRESHOLD       = 'wpwhobird_threshold';
    const OPTION_GENERATE_TEXT   = 'wpwhobird_should_generate_text_when_no_observations';

    /** Defaults, shared by the settings page and by the code that reads the options. */
    const DEFAULT_RECORDINGS_PATH = 'WhoBird/recordings';
    const DEFAULT_DATABASE_PATH   = 'WhoBird/databases/BirdDatabase.db';
    const DEFAULT_THRESHOLD       = 0.7;
    const DEFAULT_GENERATE_TEXT   = true;

    /**
     * Normalise a path that must stay relative to the uploads directory.
     *
     * Rejects (returns '') empty values, absolute paths, URL schemes or drive letters,
     * parent directory segments ("..") and control characters. Backslashes become
     * slashes; "." segments and repeated or trailing slashes are removed.
     *
     * @param mixed $value Raw value.
     * @return string The normalised relative path, or '' when the value is not acceptable.
     */
    public static function sanitizeRelativePath($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $path = str_replace('\\', '/', trim($value));
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return '';
        }
        if ($path[0] === '/' || preg_match('#^[A-Za-z][A-Za-z0-9+.\-]*:#', $path)) {
            return '';
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return '';
            }
            if ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        return implode('/', $segments);
    }

    /**
     * Whether a file or directory really lies inside a base directory (symbolic links resolved).
     *
     * @param string $path Path to test; it must exist.
     * @param string $base Base directory.
     * @return bool
     */
    public static function isInsideDirectory(string $path, string $base): bool
    {
        $realPath = realpath($path);
        $realBase = realpath($base);
        if ($realPath === false || $realBase === false) {
            return false;
        }
        return strpos($realPath . DIRECTORY_SEPARATOR, rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Recordings directory, relative to the uploads directory (setting, validated, with default).
     *
     * @return string
     */
    public static function getRecordingsPath(): string
    {
        $path = self::sanitizeRelativePath(get_option(self::OPTION_RECORDINGS_PATH, self::DEFAULT_RECORDINGS_PATH));
        return $path !== '' ? $path : self::DEFAULT_RECORDINGS_PATH;
    }

    /**
     * Database file, relative to the uploads directory (setting, validated, with default).
     *
     * @return string
     */
    public static function getDatabasePath(): string
    {
        $path = self::sanitizeRelativePath(get_option(self::OPTION_DATABASE_PATH, self::DEFAULT_DATABASE_PATH));
        return $path !== '' ? $path : self::DEFAULT_DATABASE_PATH;
    }

    /**
     * Minimum probability for an identification to be displayed (0 to 1, with default).
     *
     * @return float
     */
    public static function getThreshold(): float
    {
        $value = get_option(self::OPTION_THRESHOLD, self::DEFAULT_THRESHOLD);
        if (!is_numeric($value) || $value < 0 || $value > 1) {
            return self::DEFAULT_THRESHOLD;
        }
        return (float) $value;
    }

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
        return (bool) get_option(self::OPTION_GENERATE_TEXT, self::DEFAULT_GENERATE_TEXT);
    }
}
