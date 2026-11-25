<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Removes what the plugin stored in the database when it is deleted from WordPress.
 *
 * Removed: the three tables (SPARQL cache, mapping, remote files), the options
 * and the throttle lock file in the system temporary directory.
 * Not removed: the whoBIRD database and the recordings in the uploads directory
 * (they are the user's files, not the plugin's).
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

namespace WPWhoBird;

class Uninstaller
{
    /**
     * Options stored by the plugin.
     *
     * @return string[]
     */
    public static function optionNames(): array
    {
        return [
            Config::OPTION_RECORDINGS_PATH,
            Config::OPTION_DATABASE_PATH,
            Config::OPTION_THRESHOLD,
            Config::OPTION_GENERATE_TEXT,
            'wpwhobird_fallback_languages',
            'whobird_fetched_wikidata_qids',
        ];
    }

    /**
     * Removes the plugin data, on every site of a multisite network.
     *
     * @return void
     */
    public static function run(): void
    {
        if (function_exists('is_multisite') && is_multisite()) {
            foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
                switch_to_blog($siteId);
                self::removeSiteData();
                restore_current_blog();
            }
        } else {
            self::removeSiteData();
        }
        self::removeThrottleFiles();
    }

    /**
     * Removes the tables and the options of the current site.
     *
     * @return void
     */
    private static function removeSiteData(): void
    {
        global $wpdb;

        $tables = [
            Config::getTableSparqlCache(),
            Config::getTableMapping(),
            Config::getTableRemoteFiles(),
        ];
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `$table`");
        }
        foreach (self::optionNames() as $option) {
            delete_option($option);
        }
    }

    /**
     * Removes the lock file used to throttle the Wikidata requests (see FileLockThrottle).
     *
     * @return void
     */
    private static function removeThrottleFiles(): void
    {
        $file = sys_get_temp_dir() . '/throttle_wikidata.lock';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
